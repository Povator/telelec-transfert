<?php
/**
 * Récupération de l'historique de téléchargement
 * 
 * API endpoint pour obtenir l'historique des téléchargements
 * d'un fichier spécifique avec géolocalisation.
 *
 * @author  TeleLec
 * @version 1.1
 * @requires Session admin active
 * @method GET
 */

/**
 * Récupère l'historique complet des téléchargements d'un fichier
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 *
 * @return array Historique avec IP, navigateur, ville et timestamps
 *
 * @throws PDOException Si erreur de base de données
 */
function getFileDownloadHistory($conn, $fileId) {
    // ...existing code...
}

/**
 * Formate l'historique pour l'affichage JSON
 *
 * @param array $rawHistory Données brutes de l'historique
 *
 * @return array Historique formaté avec labels utilisateur
 */
function formatHistoryForDisplay($rawHistory) {
    // ...existing code...
}

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

    // Modifier la requête pour inclure la ville
    $sql = "SELECT 
                download_time,
                download_ip,
                user_agent,
                city
            FROM download_history 
            WHERE file_id = ? 
            ORDER BY download_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['file_id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($history);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}