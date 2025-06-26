<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Suppression sécurisée de transferts
 * 
 * Supprime un fichier et toutes ses métadonnées associées
 * de manière atomique avec gestion d'erreurs complète.
 *
 * @author  TeleLec
 * @version 1.2
 * @requires Session admin active
 * @method POST
 */

/**
 * Valide l'identifiant de fichier à supprimer
 *
 * @param mixed $id Identifiant à valider
 *
 * @return int Identifiant validé et converti
 *
 * @throws InvalidArgumentException Si l'identifiant n'est pas valide
 */
function validateFileId($id) {
    $id = (int)$id;
    if ($id <= 0) {
        throw new InvalidArgumentException('Identifiant de fichier invalide');
    }
    return $id;
}

/**
 * Supprime un fichier physique du système
 *
 * @param string $filepath Chemin complet du fichier
 *
 * @return bool True si suppression réussie
 */
function deletePhysicalFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return true; // Considéré comme réussi si déjà inexistant
}

/**
 * Supprime toutes les données liées à un fichier
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 *
 * @return bool True si suppression complète réussie
 *
 * @throws PDOException Si erreur de base de données
 */
function deleteFileData($conn, $fileId) {
    $tables = ['download_history', 'download_auth_codes', 'files'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("DELETE FROM {$table} WHERE " . ($table === 'files' ? 'id' : 'file_id') . " = ?");
        $stmt->execute([$fileId]);
    }
    return true;
}

/**
 * Enregistre la suppression dans les logs
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier supprimé
 * @param string $filename Nom du fichier supprimé
 *
 * @return bool True si log enregistré avec succès
 */
function logFileDeletion($conn, $fileId, $filename) {
    $stmt = $conn->prepare("INSERT INTO deletion_logs (file_id, filename, deleted_at) VALUES (?, ?, NOW())");
    return $stmt->execute([$fileId, $filename]);
}

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

    $id = validateFileId($_POST['id']);
    
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
        deleteFileData($conn, $id);

        // 3. Supprimer le fichier physique
        $filepath = __DIR__ . '/../uploads/' . $file['filename'];
        if (!deletePhysicalFile($filepath)) {
            throw new Exception('Impossible de supprimer le fichier physique');
        }

        // 4. Enregistrer la suppression dans les logs
        if (!logFileDeletion($conn, $id, $file['filename'])) {
            throw new Exception('Erreur lors de l\'enregistrement de la suppression dans les logs');
        }

        // 5. Valider la transaction
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
