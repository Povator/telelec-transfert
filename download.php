<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['code'])) {
    die('Code de téléchargement manquant');
}

$downloadCode = $_GET['code'];

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT filename FROM files WHERE download_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$downloadCode]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        $filepath = __DIR__ . '/uploads/' . $file['filename'];
        
        if (file_exists($filepath)) {
            // Forcer le téléchargement
            $updateSql = "UPDATE files SET 
                downloaded = 1, 
                download_time = NOW(), 
                download_ip = ?, 
                user_agent = ? 
                WHERE download_code = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu',
                $downloadCode
            ]);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Content-Type: application/octet-stream');

            readfile($filepath);
            exit;
        } else {
            die('Fichier non trouvé sur le serveur');
        }
    } else {
        die('Code de téléchargement invalide');
    }
} catch (PDOException $e) {
    die('Erreur de base de données : ' . $e->getMessage());
}