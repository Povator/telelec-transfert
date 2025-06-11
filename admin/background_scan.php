<?php
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
    $sql = "UPDATE files SET antivirus_status = ?, antivirus_message = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status, $result['message'], $fileId]);

    echo "Scan terminé pour fichier ID {$fileId}: {$status}\n";

} catch (Exception $e) {
    echo "Erreur scan arrière-plan: " . $e->getMessage() . "\n";
}
?>