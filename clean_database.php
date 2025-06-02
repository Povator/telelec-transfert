<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT id, filename FROM files";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedCount = 0;

    foreach ($files as $file) {
        $filepath = __DIR__ . '/uploads/' . $file['filename'];
        if (!file_exists($filepath)) {
            // Supprimer d'abord dans download_auth_codes
            $conn->prepare("DELETE FROM download_auth_codes WHERE file_id = ?")->execute([$file['id']]);

            // Supprimer aussi dans download_history
            $conn->prepare("DELETE FROM download_history WHERE file_id = ?")->execute([$file['id']]);

            // Puis supprimer dans files
            $conn->prepare("DELETE FROM files WHERE id = ?")->execute([$file['id']]);
            $deletedCount++;
        }
    }

    echo "Nettoyage terminé. $deletedCount entrées supprimées.";

} catch (PDOException $e) {
    http_response_code(500);
    echo "Erreur de base de données : " . $e->getMessage();
}