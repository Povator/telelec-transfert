<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérification de la session admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

header('Content-Type: application/json');

// Configuration de la base de données
$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('ID manquant ou invalide');
    }

    $id = intval($_POST['id']);
    
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Début de la transaction
    $conn->beginTransaction();

    try {
        // 1. Récupérer le nom du fichier avant suppression
        $stmt = $conn->prepare("SELECT filename FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            throw new Exception('Fichier non trouvé dans la base de données');
        }

        // 2. Supprimer les entrées dans les tables liées
        $tables = ['download_history', 'download_auth_codes', 'files'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE " . ($table === 'files' ? 'id' : 'file_id') . " = ?");
            $stmt->execute([$id]);
        }

        // 3. Supprimer le fichier physique
        $filepath = __DIR__ . '/../uploads/' . $file['filename'];
        if (file_exists($filepath)) {
            if (!unlink($filepath)) {
                throw new Exception('Impossible de supprimer le fichier physique');
            }
        }

        // 4. Valider la transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Fichier supprimé avec succès']);

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Erreur de suppression: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Erreur lors de la suppression: " . $e->getMessage()
    ]);
}
