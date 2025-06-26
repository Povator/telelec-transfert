<?php
/**
 * Script d'analyse antivirus en arrière-plan
 * 
 * Exécute des analyses ClamAV complètes sur les fichiers
 * de manière asynchrone pour éviter les timeouts.
 *
 * @author  TeleLec
 * @version 1.1
 * @requires Exécution en ligne de commande
 */

/**
 * Met à jour le statut antivirus d'un fichier en base
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 * @param string $status Statut de l'analyse ('true', 'false', 'warning')
 * @param string $message Message descriptif du résultat
 *
 * @return bool True si mise à jour réussie
 */
function updateAntivirusStatus($conn, $fileId, $status, $message) {
    $sql = "UPDATE files SET antivirus_status = ?, antivirus_message = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$status, $message, $fileId]);
}

/**
 * Enregistre le résultat d'analyse dans les logs
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 * @param string $result Résultat de l'analyse
 * @param float $executionTime Temps d'exécution en secondes
 *
 * @return bool True si log enregistré
 */
function logScanResult($conn, $fileId, $result, $executionTime) {
    $sql = "INSERT INTO scan_logs (file_id, result, execution_time) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$fileId, $result, $executionTime]);
}

// background_scan.php
if ($argc != 3) {
    die("Usage: php background_scan.php <file_id> <filepath>\n");
}

$fileId = (int)$argv[1];
$filepath = $argv[2];

date_default_timezone_set('Europe/Paris');

// Connexion BDD
try {
    $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Scan ClamAV complet
    require_once __DIR__ . '/../includes/antivirus.php';
    $result = scanFile($filepath);

    // Convertir le statut
    $status = $result['status'] === true ? 'true' : 
              ($result['status'] === false ? 'false' : 'warning');

    // Mettre à jour en base
    updateAntivirusStatus($conn, $fileId, $status, $result['message']);

    // Enregistrer le log
    $executionTime = 0; // Remplacer par le temps d'exécution réel si disponible
    logScanResult($conn, $fileId, $result['message'], $executionTime);

    echo "Scan terminé pour fichier ID {$fileId}: {$status}\n";

} catch (Exception $e) {
    echo "Erreur scan arrière-plan: " . $e->getMessage() . "\n";
}
?>