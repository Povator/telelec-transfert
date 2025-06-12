<?php
session_start();
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

    // Récupérer toutes les tentatives de virus
    $sql = "SELECT 
                action_date,
                user_ip,
                details,
                status
            FROM file_logs 
            WHERE action_type IN ('virus_attempt', 'virus_detected') 
            ORDER BY action_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $virusAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques des menaces
    $stats = [];
    
    // Total tentatives
    $stmt = $conn->query("SELECT COUNT(*) FROM file_logs WHERE action_type IN ('virus_attempt', 'virus_detected')");
    $stats['total_attempts'] = $stmt->fetchColumn();
    
    // Tentatives dernières 24h
    $stmt = $conn->query("SELECT COUNT(*) FROM file_logs WHERE action_type IN ('virus_attempt', 'virus_detected') AND action_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['attempts_24h'] = $stmt->fetchColumn();
    
    // IPs uniques ayant tenté
    $stmt = $conn->query("SELECT COUNT(DISTINCT user_ip) FROM file_logs WHERE action_type IN ('virus_attempt', 'virus_detected')");
    $stats['unique_ips'] = $stmt->fetchColumn();
    
    // Top des IPs malveillantes
    $stmt = $conn->query("
        SELECT user_ip, COUNT(*) as attempts, MAX(action_date) as last_attempt 
        FROM file_logs 
        WHERE action_type IN ('virus_attempt', 'virus_detected') 
        GROUP BY user_ip 
        ORDER BY attempts DESC 
        LIMIT 10
    ");
    $topMaliciousIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Menaces de sécurité - TeleLec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <h1>🚨 Menaces de sécurité détectées</h1>

        <!-- Statistiques -->
        <div class="stats-container">
            <div class="stat-box">
                <h3>🦠 Total tentatives de virus</h3>
                <p><?= $stats['total_attempts'] ?></p>
            </div>
            <div class="stat-box">
                <h3>⚡ Dernières 24h</h3>
                <p><?= $stats['attempts_24h'] ?></p>
            </div>
            <div class="stat-box">
                <h3>🌐 IPs uniques malveillantes</h3>
                <p><?= $stats['unique_ips'] ?></p>
            </div>
        </div>

        <!-- Top des IPs malveillantes -->
        <?php if (!empty($topMaliciousIPs)): ?>
        <div class="section">
            <h2>🎯 Top des IPs malveillantes</h2>
            <table class="admin-table">
                <tr>
                    <th>Adresse IP</th>
                    <th>Nombre de tentatives</th>
                    <th>Dernière tentative</th>
                </tr>
                <?php foreach ($topMaliciousIPs as $ip): ?>
                <tr>
                    <td><?= htmlspecialchars($ip['user_ip']) ?></td>
                    <td><span class="badge bg-danger"><?= $ip['attempts'] ?></span></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($ip['last_attempt'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- Historique détaillé -->
        <div class="section">
            <h2>📋 Historique des tentatives</h2>
            <?php if (!empty($virusAttempts)): ?>
            <table class="admin-table">
                <tr>
                    <th>Date</th>
                    <th>Adresse IP</th>
                    <th>Type de menace</th>
                    <th>Détails</th>
                    <th>Statut</th>
                </tr>
                <?php foreach ($virusAttempts as $attempt): ?>
                <tr>
                    <td><?= date('d/m/Y H:i:s', strtotime($attempt['action_date'])) ?></td>
                    <td>
                        <span class="ip-address"><?= htmlspecialchars($attempt['user_ip']) ?></span>
                    </td>
                    <td>
                        <?php
                        $details = $attempt['details'];
                        if (strpos($details, 'EICAR') !== false) {
                            echo '<span class="badge bg-warning">🧪 Test EICAR</span>';
                        } elseif (strpos($details, 'ClamAV') !== false) {
                            echo '<span class="badge bg-danger">🦠 Virus réel</span>';
                        } elseif (strpos($details, 'PHP') !== false) {
                            echo '<span class="badge bg-danger">⚡ Code malveillant</span>';
                        } else {
                            echo '<span class="badge bg-secondary">❓ Autre</span>';
                        }
                        ?>
                    </td>
                    <td class="threat-details"><?= htmlspecialchars($attempt['details']) ?></td>
                    <td>
                        <span class="badge bg-success">🛡️ Bloqué</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <div class="alert alert-success">
                <h3>✅ Aucune menace détectée</h3>
                <p>Votre système est sécurisé ! Aucune tentative d'upload de virus n'a été détectée.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <style>
    .threat-details {
        max-width: 300px;
        word-wrap: break-word;
        font-size: 0.9em;
    }
    
    .ip-address {
        font-family: monospace;
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #dee2e6;
    }
    
    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        font-weight: bold;
    }
    
    .bg-danger { background-color: #dc3545; color: white; }
    .bg-warning { background-color: #ffc107; color: black; }
    .bg-success { background-color: #28a745; color: white; }
    .bg-secondary { background-color: #6c757d; color: white; }
    
    .alert {
        padding: 20px;
        margin: 20px 0;
        border-radius: 8px;
        text-align: center;
    }
    
    .alert-success {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    </style>
</body>
</html>