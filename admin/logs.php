<?php
session_start();
date_default_timezone_set('Europe/Paris');
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Statistiques en temps réel
    $stats = [
        'online_users' => 0,
        'total_uploads_today' => 0,
        'failed_login_attempts' => 0,
        'active_files' => 0
    ];

    // Utilisateurs en ligne (sessions actives dernières 15 minutes)
    $sql = "SELECT COUNT(*) FROM sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    $stmt = $conn->query($sql);
    $stats['online_users'] = $stmt->fetchColumn();

    // Uploads aujourd'hui
    $sql = "SELECT COUNT(*) FROM file_logs WHERE action_type = 'upload_complete' AND DATE(action_date) = CURDATE()";
    $stmt = $conn->query($sql);
    $stats['total_uploads_today'] = $stmt->fetchColumn();

    // Tentatives de connexion échouées (dernières 24h)
    $sql = "SELECT COUNT(*) FROM file_logs WHERE action_type = 'failed_login' AND action_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmt = $conn->query($sql);
    $stats['failed_login_attempts'] = $stmt->fetchColumn();

    // Fichiers actifs
    $sql = "SELECT COUNT(*) FROM files WHERE deleted = 0";
    $stmt = $conn->query($sql);
    $stats['active_files'] = $stmt->fetchColumn();

    // Dernières alertes
    $sql = "SELECT *, CONVERT_TZ(action_date, 'UTC', 'Europe/Paris') as action_date 
            FROM file_logs 
            WHERE status = 'error' OR action_type IN ('failed_login', 'virus_detected', 'unauthorized_access')
            ORDER BY action_date DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Logs système
    $sql = "SELECT l.*, f.filename, 
            CONVERT_TZ(l.action_date, 'UTC', 'Europe/Paris') as action_date 
            FROM file_logs l 
            LEFT JOIN files f ON l.file_id = f.id 
            ORDER BY l.action_date DESC 
            LIMIT 100";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Logs système - TeLelec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="admin.css">
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <?php include '../Present/header.php'; ?>
    
    <main>
        <h1>Surveillance système</h1>

        <!-- Statistiques en temps réel -->
        <div class="stats-container">
            <div class="stat-box">
                <h3>🟢 Utilisateurs en ligne</h3>
                <p><?= $stats['online_users'] ?></p>
            </div>
            <div class="stat-box">
                <h3>📤 Uploads aujourd'hui</h3>
                <p><?= $stats['total_uploads_today'] ?></p>
            </div>
            <div class="stat-box">
                <h3>⚠️ Tentatives de connexion échouées (24h)</h3>
                <p><?= $stats['failed_login_attempts'] ?></p>
            </div>
            <div class="stat-box">
                <h3>📁 Fichiers actifs</h3>
                <p><?= $stats['active_files'] ?></p>
            </div>
        </div>

        <!-- Alertes -->
        <h2>🚨 Dernières alertes</h2>
        <table class="admin-table alerts-table">
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>IP</th>
                <th>Détails</th>
            </tr>
            <?php foreach ($alerts as $alert): ?>
            <tr class="alert-row">
                <td><?= (new DateTime($alert['action_date']))->format('d/m/Y H:i:s') ?></td>
                <td><?= htmlspecialchars($alert['action_type']) ?></td>
                <td><?= htmlspecialchars($alert['user_ip']) ?></td>
                <td><?= htmlspecialchars($alert['details']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Logs système -->
        <h2>📝 Logs système</h2>
        <table class="admin-table">
            <tr>
                <th>Date</th>
                <th>Fichier</th>
                <th>Action</th>
                <th>IP</th>
                <th>Ville</th>
                <th>Status</th>
                <th>Détails</th>
            </tr>
            <?php foreach ($logs as $log): ?>
            <tr class="<?= $log['status'] === 'error' ? 'error-row' : '' ?>">
                <td><?= $log['action_date'] ? date('d/m/Y H:i:s', strtotime($log['action_date'])) : '-' ?></td>
                <td>
                    <?php
                    if (!empty($log['filename'])) {
                        echo htmlspecialchars($log['filename']);
                    } elseif (empty($log['file_id'])) {
                        echo '<em>Général</em>';
                    } else {
                        echo '<em>Fichier en cours d\'upload</em>';
                    }
                    ?>
                </td>
                <td><?= htmlspecialchars($log['action_type']) ?></td>
                <td><?= htmlspecialchars($log['user_ip']) ?></td>
                <td><?php echo "<td>" . (isset($log['city']) ? htmlspecialchars($log['city']) : 'N/A') . "</td>"; ?></td>
                <td><?= htmlspecialchars($log['status']) ?></td>
                <td><?= htmlspecialchars($log['details']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </main>

    <style>
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 20px;
    }

    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }

    .stat-box h3 {
        margin: 0 0 10px 0;
        color: #333;
    }

    .stat-box p {
        font-size: 24px;
        font-weight: bold;
        margin: 0;
        color: #ED501C;
    }

    .alerts-table {
        margin: 20px 0;
    }

    .alert-row {
        background-color: #fff3f3;
    }

    .error-row {
        background-color: #fff3f3;
    }

    h2 {
        margin: 30px 20px 10px;
        color: #333;
    }
    </style>

    <script>
    // Actualisation automatique toutes les 30 secondes
    setTimeout(function() {
        window.location.reload();
    }, 30000);
    </script>
</body>
</html>