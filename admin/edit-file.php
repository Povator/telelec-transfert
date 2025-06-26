<?php
/**
 * Modification des métadonnées de fichier
 * 
 * Permet la modification sécurisée du nom de fichier et du code
 * de téléchargement avec validation et logging.
 *
 * @author  TeleLec
 * @version 1.1
 * @requires Session admin active
 * @method POST
 */

session_start();

// Vérifier les permissions admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données du formulaire
$fileId = (int)($_POST['fileId'] ?? 0);
$filename = trim($_POST['filename'] ?? '');
$downloadCode = trim($_POST['downloadCode'] ?? '');

// Validation des données
if ($fileId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de fichier invalide']);
    exit;
}

if (empty($filename)) {
    echo json_encode(['success' => false, 'message' => 'Le nom de fichier ne peut pas être vide']);
    exit;
}

if (empty($downloadCode)) {
    echo json_encode(['success' => false, 'message' => 'Le code de téléchargement ne peut pas être vide']);
    exit;
}

// Valider le format du code de téléchargement (8 caractères alphanumériques)
if (!preg_match('/^[a-zA-Z0-9]{8}$/', $downloadCode)) {
    echo json_encode(['success' => false, 'message' => 'Le code de téléchargement doit contenir exactement 8 caractères alphanumériques']);
    exit;
}

try {
    // Connexion à la base de données
    $host = 'db';
    $db = 'telelec';
    $user = 'telelecuser';
    $pass = 'userpassword';
    
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier que le fichier existe
    $checkStmt = $conn->prepare("SELECT id, filename, download_code FROM files WHERE id = ?");
    $checkStmt->execute([$fileId]);
    $existingFile = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingFile) {
        echo json_encode(['success' => false, 'message' => 'Fichier introuvable']);
        exit;
    }
    
    // Vérifier que le nouveau code de téléchargement n'est pas déjà utilisé (sauf si c'est le même)
    if ($downloadCode !== $existingFile['download_code']) {
        $codeCheckStmt = $conn->prepare("SELECT id FROM files WHERE download_code = ? AND id != ?");
        $codeCheckStmt->execute([$downloadCode, $fileId]);
        
        if ($codeCheckStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Ce code de téléchargement est déjà utilisé']);
            exit;
        }
    }
    
    // Mettre à jour le fichier
    $updateStmt = $conn->prepare("UPDATE files SET filename = ?, download_code = ? WHERE id = ?");
    $updateStmt->execute([$filename, $downloadCode, $fileId]);
    
    // Log de l'action admin
    $logStmt = $conn->prepare("INSERT INTO admin_logs (action, details, timestamp) VALUES (?, ?, NOW())");
    $logDetails = "Fichier modifié - ID: $fileId, Ancien nom: {$existingFile['filename']}, Nouveau nom: $filename, Ancien code: {$existingFile['download_code']}, Nouveau code: $downloadCode";
    $logStmt->execute(['file_edit', $logDetails]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Fichier modifié avec succès',
        'data' => [
            'id' => $fileId,
            'filename' => $filename,
            'download_code' => $downloadCode
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur lors de la modification du fichier: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
    exit;
}
?>