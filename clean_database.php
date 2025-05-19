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
            $deleteSql = "DELETE FROM files WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->execute([$file['id']]);
            $deletedCount++;
        }
    }
    
    echo "Nettoyage terminé. $deletedCount entrées supprimées.";

} catch (PDOException $e) {
    http_response_code(500);
    echo "Erreur de base de données : " . $e->getMessage();
}