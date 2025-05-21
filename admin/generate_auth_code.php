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

function generateAuthCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $fileId = $_POST['fileId'] ?? null;
    if (!$fileId) {
        throw new Exception('ID du fichier manquant');
    }

    // Désactiver les anciens codes
    $sql = "UPDATE download_auth_codes SET used = TRUE WHERE file_id = ? AND used = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fileId]);

    // Générer un nouveau code
    $authCode = generateAuthCode();
    $expirationDate = date('Y-m-d H:i:s', strtotime('+5 days'));

    $sql = "INSERT INTO download_auth_codes (file_id, auth_code, expiration_date) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fileId, $authCode, $expirationDate]);

    echo json_encode([
        'success' => true,
        'code' => $authCode,
        'expiration' => $expirationDate
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}