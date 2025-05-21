<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

header('Content-Type: application/json');

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT * FROM download_history WHERE file_id = ? ORDER BY download_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['file_id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($history);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}