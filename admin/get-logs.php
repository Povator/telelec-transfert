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
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer tous les logs, avec ou sans file_id
    $stmt = $pdo->query("
        SELECT id, file_id, action, level, message, timestamp
        FROM file_logs
        ORDER BY timestamp DESC
    ");
    
    $logs = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $logs[] = [
            'id' => $row['id'],
            'file_id' => $row['file_id'] ?? null,
            'context' => $row['file_id'] ? "Fichier #" . $row['file_id'] : "Général / Upload en cours",
            'action' => $row['action'],
            'level' => $row['level'],
            'message' => $row['message'],
            'timestamp' => $row['timestamp']
        ];
    }

    echo json_encode($logs);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}