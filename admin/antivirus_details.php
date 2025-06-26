<?php
/**
 * Interface détaillée de gestion antivirus
 * 
 * Fournit un dashboard complet des analyses antivirus avec
 * statistiques, historique et génération de rapports CSV.
 *
 * @author  TeleLec
 * @version 2.0
 * @requires Session admin active
 * @requires Module antivirus inclus
 */

// DÉMARRAGE DE SESSION EN PREMIER
session_start();

// Configuration base de données
$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

// DÉFINITION DES FONCTIONS
/**
 * Formate une taille de fichier en octets vers une unité lisible
 *
 * @param int $bytes Taille en octets
 *
 * @return string Taille formatée avec unité (KB, MB, GB)
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Génère un rapport CSV des analyses antivirus
 *
 * @param PDO $conn Connexion à la base de données
 *
 * @return void Force le téléchargement du fichier CSV
 *
 * @throws Exception Si erreur lors de la génération
 */
function generateAntivirusReport($conn) {
    $sql = "SELECT 
                f.filename,
                f.file_size,
                f.upload_date,
                f.antivirus_status,
                f.antivirus_message
            FROM files f 
            WHERE f.deleted = 0
            ORDER BY f.upload_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($scans)) {
        $scans = [[
            'filename' => 'Aucun fichier trouvé',
            'file_size' => 0,
            'upload_date' => date('Y-m-d H:i:s'),
            'antivirus_status' => 'N/A',
            'antivirus_message' => 'Aucune donnée disponible'
        ]];
    }
    
    $filename = 'rapport_antivirus_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Nettoyer le buffer de sortie
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM UTF-8 pour Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Nom du fichier',
        'Taille',
        'Date upload',
        'Résultat scan',
        'Message antivirus'
    ]);
    
    foreach ($scans as $scan) {
        $status_text = 'Inconnu';
        if ($scan['antivirus_status'] === 'true' || $scan['antivirus_status'] === '1') {
            $status_text = 'Fichier sain';
        } elseif ($scan['antivirus_status'] === 'false' || $scan['antivirus_status'] === '0') {
            $status_text = 'Virus détecté';
        } elseif ($scan['antivirus_status'] === 'warning') {
            $status_text = 'Avertissement';
        }
        
        fputcsv($output, [
            $scan['filename'],
            formatFileSize($scan['file_size']),
            $scan['upload_date'],
            $status_text,
            $scan['antivirus_message'] ?: 'Aucun message'
        ]);
    }
    
    fclose($output);
    exit();
}

/**
 * Récupère les statistiques globales antivirus
 *
 * @param PDO $conn Connexion à la base de données
 *
 * @return array Statistiques avec totaux par statut
 *
 * @throws PDOException Si erreur de requête
 */
function getAntivirusStats($conn) {
    $stats = ['total' => 0, 'clean' => 0, 'warning' => 0, 'virus' => 0];

    try {
        // Total des fichiers
        $stmt = $conn->query("SELECT COUNT(*) FROM files WHERE deleted = 0");
        $stats['total'] = (int)$stmt->fetchColumn();
        
        // Fichiers sains
        $stmt = $conn->query("SELECT COUNT(*) FROM files WHERE (antivirus_status = 'true' OR antivirus_status = '1') AND deleted = 0");
        $stats['clean'] = (int)$stmt->fetchColumn();
        
        // Fichiers avec avertissement
        $stmt = $conn->query("SELECT COUNT(*) FROM files WHERE antivirus_status = 'warning' AND deleted = 0");
        $stats['warning'] = (int)$stmt->fetchColumn();
        
        // Fichiers infectés
        $stmt = $conn->query("SELECT COUNT(*) FROM files WHERE (antivirus_status = 'false' OR antivirus_status = '0') AND deleted = 0");
        $stats['virus'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur getAntivirusStats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Récupère les derniers virus détectés
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $limit Nombre maximum de résultats
 *
 * @return array Liste des fichiers infectés récents
 */
function getLatestVirusDetections($conn, $limit = 10) {
    try {
        // CORRECTION: Utiliser la valeur directement dans la requête au lieu de LIMIT ?
        $limit = (int)$limit; // Sécurisation
        $sql = "SELECT id, filename, upload_date, antivirus_message FROM files 
                WHERE (antivirus_status = 'false' OR antivirus_status = '0') AND deleted = 0 
                ORDER BY upload_date DESC LIMIT " . $limit;
        
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur getLatestVirusDetections: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère l'historique des analyses récentes
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $limit Nombre maximum de résultats
 *
 * @return array Historique des analyses avec détails
 */
function getRecentScans($conn, $limit = 15) {
    try {
        // CORRECTION: Utiliser la valeur directement dans la requête au lieu de LIMIT ?
        $limit = (int)$limit; // Sécurisation
        $sql = "SELECT l.*, COALESCE(f.filename, 'Fichier supprimé') as filename 
                FROM file_logs l 
                LEFT JOIN files f ON l.file_id = f.id 
                WHERE l.action_type IN ('antivirus_scan', 'virus_detected', 'virus_attempt') 
                ORDER BY l.action_date DESC 
                LIMIT " . $limit;
        
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur getRecentScans: " . $e->getMessage());
        return [];
    }
}

// TRAITEMENT DU RAPPORT CSV - UNE SEULE FOIS !
if (isset($_POST['generate_report'])) {
    error_log("DEBUG: Génération rapport CSV demandée");
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Appel direct de la fonction de génération
        generateAntivirusReport($conn);
        
    } catch (Exception $e) {
        error_log("ERREUR génération rapport: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la génération du rapport: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Vérification d'authentification admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /admin/login.php');
    exit();
}

// MAINTENANT ON PEUT INCLURE LE HEADER SANS PROBLÈME
require_once '../includes/antivirus.php';

// Récupérer le statut de ClamAV
$clamavStatus = getClamAVStatus();

// Variables d'initialisation
$stats = ['total' => 0, 'clean' => 0, 'warning' => 0, 'virus' => 0];
$latestViruses = [];
$recentScans = [];
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Nettoyer le message d'erreur de la session
if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stats = getAntivirusStats($conn);
    $latestViruses = getLatestVirusDetections($conn, 10);
    $recentScans = getRecentScans($conn, 15);
    
} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
    error_log("Erreur antivirus_details: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Antivirus - TeleLec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="admin.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/favicon/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/favicon/favicon.png" type="image/png">
    
    <!-- Meta refresh pour actualisation automatique -->
    <meta http-equiv="refresh" content="60">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="page-header">
            <h1>🦠 Détails Antivirus</h1>
            <div class="page-actions">
                <button onclick="location.reload()" class="btn btn-refresh" title="Actualiser">
                    🔄 Actualiser
                </button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="generate_report" value="1">
                    <button type="submit" class="btn btn-primary">
                        📊 Télécharger Rapport CSV
                    </button>
                </form>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <h3>❌ Erreur</h3>
                <p><?= htmlspecialchars($error) ?></p>
                <button onclick="location.reload()" class="btn btn-small">Réessayer</button>
            </div>
        <?php endif; ?>

        <!-- Statut ClamAV -->
        <div class="section">
            <h2>🛡️ Statut ClamAV</h2>
            <div class="clamav-status">
                <div class="status-grid">
                    <div class="status-item <?= $clamavStatus['installed'] ? 'status-success' : 'status-danger' ?>">
                        <span class="status-label">Installé :</span>
                        <span class="status-value">
                            <?= $clamavStatus['installed'] ? '✅ Oui' : '❌ Non' ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Version :</span>
                        <span class="status-value"><?= htmlspecialchars($clamavStatus['version'] ?? 'Inconnue') ?></span>
                    </div>
                    <div class="status-item <?= $clamavStatus['updated'] ? 'status-success' : 'status-warning' ?>">
                        <span class="status-label">Définitions à jour :</span>
                        <span class="status-value">
                            <?= $clamavStatus['updated'] ? '✅ Oui' : '⚠️ Non' ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Dernière mise à jour :</span>
                        <span class="status-value"><?= htmlspecialchars($clamavStatus['last_update'] ?? 'Inconnue') ?></span>
                    </div>
                </div>
                
                <?php if (!$clamavStatus['installed']): ?>
                <div class="installation-help">
                    <h4>🔧 Installation ClamAV</h4>
                    <p>Pour installer ClamAV sur votre système :</p>
                    <code>sudo apt update && sudo apt install clamav clamav-daemon</code>
                </div>
                <?php elseif (!$clamavStatus['updated']): ?>
                <div class="update-help">
                    <h4>🔄 Mise à jour des définitions</h4>
                    <p>Pour mettre à jour les définitions antivirus :</p>
                    <code>sudo freshclam</code>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-container">
            <div class="stat-box">
                <div class="stat-icon">📁</div>
                <div class="stat-content">
                    <h3>Total des fichiers</h3>
                    <p class="stat-number"><?= number_format($stats['total']) ?></p>
                </div>
            </div>
            <div class="stat-box stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3>Fichiers sains</h3>
                    <p class="stat-number"><?= number_format($stats['clean']) ?></p>
                    <span class="stat-percentage"><?= $stats['total'] > 0 ? round(($stats['clean'] / $stats['total']) * 100, 1) : 0 ?>%</span>
                </div>
            </div>
            <div class="stat-box stat-warning">
                <div class="stat-icon">⚠️</div>
                <div class="stat-content">
                    <h3>Avertissements</h3>
                    <p class="stat-number"><?= number_format($stats['warning']) ?></p>
                    <span class="stat-percentage"><?= $stats['total'] > 0 ? round(($stats['warning'] / $stats['total']) * 100, 1) : 0 ?>%</span>
                </div>
            </div>
            <div class="stat-box stat-danger">
                <div class="stat-icon">🦠</div>
                <div class="stat-content">
                    <h3>Virus détectés</h3>
                    <p class="stat-number"><?= number_format($stats['virus']) ?></p>
                    <span class="stat-percentage"><?= $stats['total'] > 0 ? round(($stats['virus'] / $stats['total']) * 100, 1) : 0 ?>%</span>
                </div>
            </div>
        </div>

        <!-- Derniers virus détectés -->
        <?php if (!empty($latestViruses)): ?>
        <div class="section">
            <h2>🚨 Derniers virus détectés</h2>
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fichier</th>
                            <th>Date d'upload</th>
                            <th>Type de menace</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestViruses as $virus): ?>
                        <tr class="error-row">
                            <td><code>#<?= $virus['id'] ?></code></td>
                            <td>
                                <span class="filename" title="<?= htmlspecialchars($virus['filename']) ?>">
                                    <?= htmlspecialchars(strlen($virus['filename']) > 30 ? substr($virus['filename'], 0, 30) . '...' : $virus['filename']) ?>
                                </span>
                            </td>
                            <td>
                                <time datetime="<?= $virus['upload_date'] ?>">
                                    <?= date('d/m/Y H:i', strtotime($virus['upload_date'])) ?>
                                </time>
                            </td>
                            <td>
                                <span class="threat-type">
                                    <?= htmlspecialchars($virus['antivirus_message']) ?>
                                </span>
                            </td>
                            <td>
                                <button onclick="viewThreatDetails(<?= $virus['id'] ?>)" class="btn btn-small">
                                    👁️ Détails
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="section">
            <div class="no-threats">
                <div class="no-threats-icon">🛡️</div>
                <h3>Aucun virus détecté</h3>
                <p>Votre système est sécurisé ! Aucune menace n'a été détectée récemment.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Analyses récentes -->
        <?php if (!empty($recentScans)): ?>
        <div class="section">
            <h2>🔍 Analyses récentes</h2>
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Fichier</th>
                            <th>Type d'analyse</th>
                            <th>Statut</th>
                            <th>IP Source</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $displayCount = 0;
                        foreach ($recentScans as $scan): 
                            $displayCount++;
                            $isHidden = $displayCount > 10 ? 'hidden-row' : '';
                        ?>
                        <tr class="<?= $scan['status'] === 'blocked' ? 'error-row' : ($scan['status'] === 'warning' ? 'warning-row' : '') ?> <?= $isHidden ?>">
                            <td>
                                <time datetime="<?= $scan['action_date'] ?>">
                                    <?= date('d/m/Y H:i:s', strtotime($scan['action_date'])) ?>
                                </time>
                            </td>
                            <td>
                                <span class="filename" title="<?= htmlspecialchars($scan['filename']) ?>">
                                    <?= htmlspecialchars(strlen($scan['filename']) > 25 ? substr($scan['filename'], 0, 25) . '...' : $scan['filename']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="scan-type">
                                    <?php
                                    switch($scan['action_type']) {
                                        case 'antivirus_scan':
                                            echo '🔍 Analyse standard';
                                            break;
                                        case 'virus_detected':
                                            echo '🚨 Virus détecté';
                                            break;
                                        case 'virus_attempt':
                                            echo '⚠️ Tentative malveillante';
                                            break;
                                        default:
                                            echo htmlspecialchars($scan['action_type']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($scan['status'] === 'blocked'): ?>
                                    <span class="badge badge-danger">🚫 Bloqué</span>
                                <?php elseif ($scan['status'] === 'warning'): ?>
                                    <span class="badge badge-warning">⚠️ Attention</span>
                                <?php else: ?>
                                    <span class="badge badge-success">✅ OK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="ip-address"><?= htmlspecialchars($scan['user_ip'] ?? 'N/A') ?></code>
                            </td>
                            <td>
                                <span class="scan-details" title="<?= htmlspecialchars($scan['details']) ?>">
                                    <?= htmlspecialchars(strlen($scan['details']) > 40 ? substr($scan['details'], 0, 40) . '...' : $scan['details']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($recentScans) > 10): ?>
            <div class="table-controls">
                <button onclick="toggleAllRows()" class="btn btn-secondary" id="toggleBtn">
                    📄 Voir tout (<?= count($recentScans) ?> analyses)
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Test manuel -->
        <div class="section">
            <h2>🧪 Test d'antivirus manuel</h2>
            <div class="test-section">
                <div class="test-form-container">
                    <form action="scan_file.php" method="post" enctype="multipart/form-data" class="test-form" id="scanForm">
                        <div class="form-group">
                            <label for="fileToScan">Sélectionner un fichier à analyser :</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="fileToScan" name="fileToScan" required class="file-input" accept="*/*">
                                <span class="file-input-label">Choisir un fichier</span>
                            </div>
                            <small class="form-help">Taille maximum recommandée : 10 MB</small>
                        </div>
                        <button type="submit" class="btn btn-primary" id="scanBtn">
                            🔍 Analyser le fichier
                        </button>
                    </form>
                </div>
                
                <div class="test-info">
                    <h4>ℹ️ Informations sur le test :</h4>
                    <ul>
                        <li>Utilisez cette fonction pour tester des fichiers suspects</li>
                        <li>Le fichier sera analysé mais <strong>pas stocké</strong> sur le serveur</li>
                        <li>Seuls les administrateurs peuvent utiliser cette fonctionnalité</li>
                        <li>L'analyse peut prendre quelques secondes selon la taille du fichier</li>
                    </ul>
                    
                    <div class="test-examples">
                        <h5>🧪 Tests recommandés :</h5>
                        <ul>
                            <li><strong>EICAR :</strong> Fichier de test antivirus standard</li>
                            <li><strong>Archives :</strong> ZIP, RAR contenant différents types de fichiers</li>
                            <li><strong>Exécutables :</strong> .exe, .dll (attention !)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="section">
            <h2>⚡ Actions rapides</h2>
            <div class="quick-actions">
                <a href="/admin/dashboard.php" class="btn btn-secondary">
                    📊 Retour au dashboard
                </a>
                <a href="/admin/logs.php" class="btn btn-info">
                    📝 Consulter les logs
                </a>
                <button onclick="location.reload()" class="btn btn-warning">
                    🔄 Actualiser la page
                </button>
                <a href="/admin/debug_clamav.php" class="btn btn-danger">
                    🔧 Debug ClamAV
                </a>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <!-- Le CSS reste identique -->
    <style>
    /* Page header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e9ecef;
    }

    .page-header h1 {
        margin: 0;
        color: #2c3e50;
    }

    .page-actions {
        display: flex;
        gap: 1rem;
    }

    /* Statut ClamAV */
    .clamav-status {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: white;
        border-radius: 8px;
        border-left: 4px solid #dee2e6;
        transition: all 0.3s ease;
    }

    .status-item.status-success {
        border-left-color: #28a745;
        background: #f8fff9;
    }

    .status-item.status-warning {
        border-left-color: #ffc107;
        background: #fffdf7;
    }

    .status-item.status-danger {
        border-left-color: #dc3545;
        background: #fff8f8;
    }
    
    .status-label {
        font-weight: 600;
        color: #495057;
    }
    
    .status-value {
        font-weight: bold;
        font-family: monospace;
    }

    /* Installation et mise à jour help */
    .installation-help,
    .update-help {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
    }

    .installation-help h4,
    .update-help h4 {
        margin: 0 0 0.5rem 0;
        color: #856404;
    }

    .installation-help code,
    .update-help code {
        background: #2c3e50;
        color: #fff;
        padding: 0.5rem;
        border-radius: 4px;
        display: block;
        margin-top: 0.5rem;
        font-family: monospace;
    }

    /* Statistiques améliorées */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-box {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-box:hover {
        transform: translateY(-2px);
    }

    .stat-icon {
        font-size: 2rem;
        opacity: 0.8;
    }

    .stat-content h3 {
        margin: 0 0 0.5rem 0;
        font-size: 0.9rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        margin: 0;
        color: #2c3e50;
    }

    .stat-percentage {
        font-size: 0.8rem;
        color: #6c757d;
        font-weight: normal;
    }

    .stat-success {
        border-left: 4px solid #28a745;
    }

    .stat-warning {
        border-left: 4px solid #ffc107;
    }

    .stat-danger {
        border-left: 4px solid #dc3545;
    }

    /* Aucune menace */
    .no-threats {
        text-align: center;
        padding: 3rem 2rem;
        background: #f8fff9;
        border-radius: 12px;
        border: 2px dashed #28a745;
    }

    .no-threats-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.7;
    }

    .no-threats h3 {
        color: #28a745;
        margin-bottom: 0.5rem;
    }

    .no-threats p {
        color: #6c757d;
        font-size: 1.1rem;
    }

    /* Tableaux */
    .table-container {
        overflow-x: auto;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        font-size: 0.9rem;
        background: white;
    }

    .admin-table th,
    .admin-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid #dee2e6;
    }

    .admin-table th {
        background: #2c3e50;
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .admin-table tr:hover {
        background: #f8f9fa;
    }

    .error-row {
        background: #fff5f5 !important;
    }

    .warning-row {
        background: #fffdf7 !important;
    }

    .hidden-row {
        display: none;
    }

    .table-controls {
        text-align: center;
        margin-top: 1rem;
    }

    /* Badges */
    .badge {
        padding: 0.25rem 0.6rem;
        font-size: 0.75rem;
        font-weight: 700;
        border-radius: 0.375rem;
        display: inline-block;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
    }

    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    /* Éléments spéciaux */
    .filename,
    .scan-details {
        font-family: monospace;
        font-size: 0.85rem;
        background: #f8f9fa;
        padding: 0.2rem 0.4rem;
        border-radius: 3px;
    }

    .ip-address {
        font-family: monospace;
        background: #e9ecef;
        padding: 0.2rem 0.4rem;
        border-radius: 3px;
        font-size: 0.8rem;
    }

    .threat-type,
    .scan-type {
        font-weight: 500;
        color: #495057;
    }

    /* Section test */
    .test-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        align-items: start;
    }
    
    .test-form {
        background: #f8f9fa;
        padding: 2rem;
        border-radius: 12px;
        border: 2px solid #e9ecef;
    }

    .file-input-wrapper {
        position: relative;
        display: inline-block;
        width: 100%;
    }

    .file-input {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }

    .file-input-label {
        display: block;
        padding: 0.75rem 1rem;
        background: white;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .file-input:hover + .file-input-label,
    .file-input:focus + .file-input-label {
        border-color: #007bff;
        background: #f8f9fa;
    }

    .form-help {
        display: block;
        margin-top: 0.5rem;
        color: #6c757d;
        font-size: 0.85rem;
    }
    
    .test-info {
        background: #e7f3ff;
        padding: 2rem;
        border-radius: 12px;
        border-left: 4px solid #17a2b8;
    }
    
    .test-info ul {
        margin: 1rem 0 0 1.5rem;
        line-height: 1.6;
    }
    
    .test-examples {
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #bee5eb;
    }

    .test-examples h5 {
        margin-bottom: 0.5rem;
        color: #0c5460;
    }

    /* Actions rapides */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .quick-actions .btn {
        padding: 1rem 1.5rem;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    /* Boutons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: #007bff;
        color: white;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-warning {
        background: #ffc107;
        color: #212529;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-info {
        background: #17a2b8;
        color: white;
    }

    .btn-refresh {
        background: #28a745;
        color: white;
    }

    .btn-download {
        background: #6f42c1;
        color: white;
    }

    .btn-small {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            gap: 1rem;
        }

        .page-actions {
            width: 100%;
            justify-content: center;
        }

        .test-section {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }

        .status-grid {
            grid-template-columns: 1fr;
        }

        .stats-container {
            grid-template-columns: repeat(2, 1fr);
        }

        .admin-table {
            font-size: 0.8rem;
        }

        .admin-table th,
        .admin-table td {
            padding: 0.5rem 0.4rem;
        }
    }

    @media (max-width: 480px) {
        .stats-container {
            grid-template-columns: 1fr;
        }

        .stat-box {
            flex-direction: column;
            text-align: center;
        }
    }
    </style>

    <!-- JavaScript optimisé -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🦠 Page antivirus chargée');
        
        // Debug pour le bouton CSV
        const csvForm = document.querySelector('form[method="POST"]');
        if (csvForm) {
            csvForm.addEventListener('submit', function(e) {
                console.log('📊 Génération CSV demandée');
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '🔄 Génération en cours...';
                
                // Réactiver le bouton après 5 secondes si pas de téléchargement
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = '📊 Télécharger Rapport CSV';
                }, 5000);
            });
        }
        
        // Gestion du formulaire de test
        const scanForm = document.getElementById('scanForm');
        if (scanForm) {
            scanForm.addEventListener('submit', function(e) {
                const fileInput = document.getElementById('fileToScan');
                if (!fileInput.files.length) {
                    e.preventDefault();
                    showNotification('⚠️ Veuillez sélectionner un fichier', 'warning');
                    return;
                }
                
                const scanBtn = document.getElementById('scanBtn');
                scanBtn.disabled = true;
                scanBtn.innerHTML = '🔄 Analyse en cours...';
            });
        }
    });

    // Fonction pour afficher/masquer toutes les lignes
    function toggleAllRows() {
        const hiddenRows = document.querySelectorAll('.hidden-row');
        const toggleBtn = document.getElementById('toggleBtn');
        const isExpanded = toggleBtn.textContent.includes('Voir moins');
        
        hiddenRows.forEach((row, index) => {
            if (isExpanded) {
                row.classList.add('hidden-row');
            } else {
                setTimeout(() => {
                    row.classList.remove('hidden-row');
                    row.style.animation = 'fadeIn 0.3s ease';
                }, index * 50);
            }
        });
        
        toggleBtn.textContent = isExpanded ? 
            `📄 Voir tout (${document.querySelectorAll('tbody tr').length} analyses)` : 
            '📄 Voir moins';
    }

    // Fonction pour voir les détails d'une menace
    function viewThreatDetails(fileId) {
        showNotification('🔍 Chargement des détails...', 'info');
        window.open(`/admin/dashboard.php?highlight=${fileId}`, '_blank');
    }

    // Système de notifications
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;float:right;font-weight:bold;">×</button>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${getNotificationColor(type)};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            max-width: 350px;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    function getNotificationColor(type) {
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8'
        };
        return colors[type] || colors.info;
    }

    // CSS pour les animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>