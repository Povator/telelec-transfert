<?php
/**
 * Interface de consultation des logs système
 * 
 * Affiche les journaux d'activité, alertes de sécurité et analyses
 * antivirus avec pagination et filtrage avancé.
 *
 * @author  TeleLec
 * @version 1.8
 * @requires Session admin active
 */

session_start();
// Fuseau horaire déjà défini ici, mais on s'assure qu'il est au bon endroit (tout en haut)
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

    /**
     * Génère des statistiques sur les logs en temps réel
     *
     * @param PDO $conn Connexion à la base de données
     *
     * @return array Statistiques incluant utilisateurs en ligne, erreurs récentes, etc.
     *
     * @throws PDOException Si erreur de base de données
     */
    function generateLogStats($conn) {
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
        $sql = "SELECT COUNT(*) FROM file_logs WHERE action_type = 'upload_complete' AND action_date >= CURDATE()";
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

        return $stats;
    }

    $stats = generateLogStats($conn);

    // Dernières alertes - Ajouter les connexions admin
    $sql = "SELECT *, action_date 
            FROM file_logs 
            WHERE status = 'error' 
               OR action_type IN ('failed_login', 'virus_detected', 'unauthorized_access', 'admin_login_success')
            ORDER BY action_date DESC LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Logs système - MODIFIÉ pour récupérer plus d'entrées
    $sql = "SELECT l.*, f.filename, 
        l.action_date, 
        l.details 
        FROM file_logs l 
        LEFT JOIN files f ON l.file_id = f.id 
        ORDER BY l.action_date DESC 
        LIMIT 500"; // Augmenté pour avoir plus de logs
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analyses Antivirus - CORRECTION
    $sql = "SELECT l.*, f.filename, l.action_date, l.details 
            FROM file_logs l 
            LEFT JOIN files f ON l.file_id = f.id 
            WHERE l.action_type IN ('antivirus_scan', 'virus_detected', 'virus_attempt') 
            ORDER BY l.action_date DESC 
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $antivirusLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    
    <!-- Favicon -->
    <link rel="icon" href="/flavicon/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/flavicon/favicon.png" type="image/png">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
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
        <div class="logs-section">
            <div class="logs-header">
                <h2>🚨 Dernières alertes</h2>
                <div class="logs-controls">
                    <span class="logs-count">Affichage de 10 sur <?= count($alerts) ?> alertes</span>
                    <?php if (count($alerts) > 10): ?>
                        <button onclick="toggleLogs('alerts')" id="alerts-toggle" class="toggle-btn">
                            📄 Voir plus
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <table class="admin-table alerts-table">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>IP</th>
                    <th>Détails</th>
                </tr>
                <?php 
                foreach ($alerts as $index => $alert): 
                    $isHidden = $index >= 10 ? 'class="hidden-row"' : '';
                ?>
                <tr <?= $isHidden ?> class="alert-row <?= $index >= 10 ? 'hidden-row' : '' ?>">
                    <td><?= (new DateTime($alert['action_date']))->format('d/m/Y H:i:s') ?></td>
                    <td><?= htmlspecialchars($alert['action_type']) ?></td>
                    <td><?= htmlspecialchars($alert['user_ip']) ?></td>
                    <td><?= htmlspecialchars($alert['details']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Logs système -->
        <div class="logs-section">
            <div class="logs-header">
                <h2>📝 Logs système</h2>
                <div class="logs-controls">
                    <span class="logs-count">Affichage de 10 sur <?= count($logs) ?> logs</span>
                    <?php if (count($logs) > 10): ?>
                        <button onclick="toggleLogs('system')" id="system-toggle" class="toggle-btn">
                            📄 Voir plus
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
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
                <?php 
                foreach ($logs as $index => $log): 
                    $errorClass = $log['status'] === 'error' ? 'error-row' : '';
                    $hiddenClass = $index >= 10 ? 'hidden-row' : '';
                ?>
                <tr class="<?= $errorClass ?> <?= $hiddenClass ?>">
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
                    <td><?= htmlspecialchars($log['city'] ?? 'Non renseigné') ?></td>
                    <td><?= htmlspecialchars($log['status']) ?></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Analyses Antivirus -->
        <div class="logs-section">
            <div class="logs-header">
                <h2>🦠 Analyses Antivirus</h2>
                <div class="logs-controls">
                    <span class="logs-count" id="antivirus-count">Chargement...</span>
                    <button onclick="toggleLogs('antivirus')" id="antivirus-toggle" class="toggle-btn">
                        📄 Voir plus
                    </button>
                </div>
            </div>
            
            <table class="admin-table">
                <tr>
                    <th>Date</th>
                    <th>Fichier</th>
                    <th>Statut</th>
                    <th>IP</th>
                    <th>Ville</th>
                    <th>Détails</th>
                </tr>
                <?php
                foreach ($antivirusLogs as $index => $log):
                    $statusClass = '';
                    if ($log['status'] === 'error') {
                        $statusClass = 'error-row';
                    } elseif ($log['status'] === 'warning') {
                        $statusClass = 'warning-row';
                    }
                    
                    $hiddenClass = $index >= 10 ? 'hidden-row' : '';
                ?>
                <tr class="<?= $statusClass ?> <?= $hiddenClass ?>">
                    <td><?= $log['action_date'] ? date('d/m/Y H:i:s', strtotime($log['action_date'])) : '-' ?></td>
                    <td>
                        <?php
                        if (!empty($log['filename'])) {
                            echo htmlspecialchars($log['filename']);
                        } elseif (empty($log['file_id'])) {
                            echo '<em>Fichier rejeté</em>';
                        } else {
                            echo '<em>Fichier analysé</em>';
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($log['status']) ?></td>
                    <td><?= htmlspecialchars($log['user_ip']) ?></td>
                    <td><?= htmlspecialchars($log['city'] ?? 'Non renseigné') ?></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
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

    .logs-section {
        margin: 30px 20px;
    }

    .logs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding: 10px 0;
        border-bottom: 2px solid #ED501C;
    }

    .logs-header h2 {
        margin: 0;
        color: #333;
    }

    .logs-controls {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .logs-count {
        font-size: 14px;
        color: #666;
        font-style: italic;
    }

    .toggle-btn {
        background: linear-gradient(45deg, #ED501C, #ff6b3d);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(237, 80, 28, 0.3);
    }

    .toggle-btn:hover {
        background: linear-gradient(45deg, #d64615, #ED501C);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(237, 80, 28, 0.4);
    }

    .hidden-row {
        display: none;
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

    /* Animation pour l'apparition des lignes */
    tr.show-animation {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>

    <script>
    let alertsExpanded = false;
    let systemExpanded = false;
    let antivirusExpanded = false;

    function toggleLogs(type) {
        const button = document.getElementById(type + '-toggle');
        const table = button.closest('.logs-section').querySelector('table');
        let hiddenRows;
        const countSpan = button.closest('.logs-controls').querySelector('.logs-count');
        
        if (type === 'alerts') {
            alertsExpanded = !alertsExpanded;
            const totalAlerts = <?= count($alerts) ?>;
            
            if (alertsExpanded) {
                // Sélectionne les lignes cachées (avec classe hidden-row)
                hiddenRows = table.querySelectorAll('.hidden-row');
                hiddenRows.forEach((row, index) => {
                    setTimeout(() => {
                        row.classList.remove('hidden-row');
                        row.classList.add('show-animation');
                    }, index * 50);
                });
                button.innerHTML = '📄 Voir moins';
                countSpan.textContent = `Affichage de ${totalAlerts} sur ${totalAlerts} alertes`;
            } else {
                // Sélectionne TOUTES les lignes à partir de l'index 10
                const rows = table.querySelectorAll('tr');
                for (let i = 11; i < rows.length; i++) { // commence à 11 car l'index 0 est l'en-tête
                    rows[i].classList.add('hidden-row');
                    rows[i].classList.remove('show-animation');
                }
                button.innerHTML = '📄 Voir plus';
                countSpan.textContent = `Affichage de 10 sur ${totalAlerts} alertes`;
            }
        } else if (type === 'system') {
            systemExpanded = !systemExpanded;
            const totalLogs = <?= count($logs) ?>;
            
            if (systemExpanded) {
                // Sélectionne les lignes cachées (avec classe hidden-row)
                hiddenRows = table.querySelectorAll('.hidden-row');
                hiddenRows.forEach((row, index) => {
                    setTimeout(() => {
                        row.classList.remove('hidden-row');
                        row.classList.add('show-animation');
                    }, index * 30);
                });
                button.innerHTML = '📄 Voir moins';
                countSpan.textContent = `Affichage de ${totalLogs} sur ${totalLogs} logs`;
            } else {
                // Sélectionne TOUTES les lignes à partir de l'index 10
                const rows = table.querySelectorAll('tr');
                for (let i = 11; i < rows.length; i++) { // commence à 11 car l'index 0 est l'en-tête
                    rows[i].classList.add('hidden-row');
                    rows[i].classList.remove('show-animation');
                }
                button.innerHTML = '📄 Voir plus';
                countSpan.textContent = `Affichage de 10 sur ${totalLogs} logs`;
            }
        } else if (type === 'antivirus') {
            antivirusExpanded = !antivirusExpanded;
            const totalLogs = <?= count($antivirusLogs) ?>;
            
            if (antivirusExpanded) {
                // Sélectionne les lignes cachées (avec classe hidden-row)
                hiddenRows = table.querySelectorAll('.hidden-row');
                hiddenRows.forEach((row, index) => {
                    setTimeout(() => {
                        row.classList.remove('hidden-row');
                        row.classList.add('show-animation');
                    }, index * 30);
                });
                button.innerHTML = '📄 Voir moins';
                document.getElementById('antivirus-count').textContent = `Affichage de ${totalLogs} sur ${totalLogs} analyses`;
            } else {
                // Sélectionne TOUTES les lignes à partir de l'index 10
                const rows = table.querySelectorAll('tr');
                for (let i = 11; i < rows.length; i++) { // commence à 11 car l'index 0 est l'en-tête
                    rows[i].classList.add('hidden-row');
                    rows[i].classList.remove('show-animation');
                }
                button.innerHTML = '📄 Voir plus';
                document.getElementById('antivirus-count').textContent = `Affichage de 10 sur ${totalLogs} analyses`;
            }
        }
    }

    // Initialisation du compteur antivirus
    document.getElementById('antivirus-count').textContent = 
        `Affichage de ${Math.min(10, <?= count($antivirusLogs ?? []) ?>)} sur <?= count($antivirusLogs ?? []) ?> analyses`;

    // Actualisation automatique toutes les 30 secondes
    setTimeout(function() {
        window.location.reload();
    }, 30000);
    </script>
</body>
</html>