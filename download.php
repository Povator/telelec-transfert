<?php
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
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Pragma: no-cache');
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