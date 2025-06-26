<?php
/**
 * Vérification du statut d'analyse antivirus
 * 
 * API endpoint pour vérifier l'état d'analyse d'un fichier
 * en temps réel côté client.
 *
 * @author  TeleLec
 * @version 1.0
 */

/**
 * Récupère le statut d'analyse antivirus d'un fichier
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 *
 * @return array Statut de l'analyse avec message et progression
 *
 * @throws PDOException Si erreur de base de données
 */
function getScanStatus($conn, $fileId) {
    $sql = "SELECT antivirus_status, antivirus_message, filename FROM files WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        return ['error' => 'Fichier non trouvé'];
    }
    
    return [
        'status' => $file['antivirus_status'],
        'message' => $file['antivirus_message'],
        'filename' => $file['filename']
    ];
}

/**
 * Met à jour le statut d'analyse en base
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 * @param string $status Nouveau statut ('scanning', 'complete', 'error')
 * @param string $message Message descriptif du statut
 *
 * @return bool True si mise à jour réussie
 */
function updateScanStatus($conn, $fileId, $status, $message = '') {
    $sql = "UPDATE files SET antivirus_status = ?, antivirus_message = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$status, $message, $fileId]);
}

header('Content-Type: application/json');

if (!isset($_GET['file_id'])) {
    echo json_encode(['error' => 'ID fichier manquant']);
    exit;
}

$fileId = (int)$_GET['file_id'];

try {
    $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $status = getScanStatus($conn, $fileId);
    
    echo json_encode($status);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>