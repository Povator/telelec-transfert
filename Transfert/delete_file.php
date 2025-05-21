<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /dashboard.php');
    exit();
}

if (isset($_POST['file_id'])) {
    $fileId = intval($_POST['file_id']);
    
    try {
        // Récupérer les informations du fichier
        $stmt = $pdo->prepare("SELECT file_path FROM uploads WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        
        if ($file) {
            // Supprimer le fichier physique
            $fullPath = __DIR__ . '/../uploads/' . $file['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Supprimer l'entrée dans la base de données
            $stmt = $pdo->prepare("DELETE FROM uploads WHERE id = ?");
            $stmt->execute([$fileId]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fichier non trouvé']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID du fichier manquant']);
}
