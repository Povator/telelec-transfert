<?php
header('Content-Type: application/json');

if (!isset($_GET['file_id'])) {
    echo json_encode(['error' => 'ID fichier manquant']);
    exit;
}

$fileId = (int)$_GET['file_id'];

try {
    $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT antivirus_status, antivirus_message, filename FROM files WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        echo json_encode(['error' => 'Fichier non trouvé']);
        exit;
    }
    
    echo json_encode([
        'status' => $file['antivirus_status'],
        'message' => $file['antivirus_message'],
        'filename' => $file['filename']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>