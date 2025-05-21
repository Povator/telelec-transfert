<?php
header('Content-Type: application/json');

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE files SET filename = :filename, download_code = :downloadCode WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':filename' => $_POST['filename'],
        ':downloadCode' => $_POST['downloadCode'],
        ':id' => $_POST['fileId']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
