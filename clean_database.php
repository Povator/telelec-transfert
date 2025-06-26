<?php
/**
 * Script de nettoyage de la base de données
 * 
 * Supprime les fichiers orphelins et nettoie les entrées
 * obsolètes de la base de données.
 *
 * @author  TeleLec
 * @version 1.0
 * @requires Accès direct au serveur
 */

/**
 * Recherche et supprime les fichiers orphelins
 *
 * @param PDO $conn Connexion à la base de données
 *
 * @return int Nombre d'entrées supprimées
 *
 * @throws PDOException Si erreur de base de données
 */
function cleanOrphanedFiles($conn) {
    $sql = "SELECT id, filename FROM files";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedCount = 0;

    foreach ($files as $file) {
        $filepath = __DIR__ . '/uploads/' . $file['filename'];
        if (!file_exists($filepath)) {
            // Supprimer d'abord dans download_auth_codes
            $conn->prepare("DELETE FROM download_auth_codes WHERE file_id = ?")->execute([$file['id']]);

            // Supprimer aussi dans download_history
            $conn->prepare("DELETE FROM download_history WHERE file_id = ?")->execute([$file['id']]);

            // Puis supprimer dans files
            $conn->prepare("DELETE FROM files WHERE id = ?")->execute([$file['id']]);
            $deletedCount++;
        }
    }

    return $deletedCount;
}

/**
 * Nettoie les codes d'authentification expirés
 *
 * @param PDO $conn Connexion à la base de données
 *
 * @return int Nombre de codes supprimés
 */
function cleanExpiredAuthCodes($conn) {
    $sql = "DELETE FROM download_auth_codes WHERE expiration_date < NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    return $stmt->rowCount();
}

/**
 * Supprime les anciens logs selon la politique de rétention
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $retentionDays Nombre de jours à conserver
 *
 * @return int Nombre de logs supprimés
 */
function cleanOldLogs($conn, $retentionDays = 90) {
    $sql = "DELETE FROM logs WHERE log_date < NOW() - INTERVAL ? DAY";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$retentionDays]);

    return $stmt->rowCount();
}

/**
 * Génère un rapport de nettoyage
 *
 * @param array $cleanupStats Statistiques du nettoyage
 *
 * @return string Rapport formaté
 */
function generateCleanupReport($cleanupStats) {
    $report = "Rapport de nettoyage :\n";
    foreach ($cleanupStats as $table => $count) {
        $report .= "- $count entrées supprimées de la table $table\n";
    }
    return $report;
}

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cleanupStats = [];

    $deletedFiles = cleanOrphanedFiles($conn);
    $cleanupStats['files'] = $deletedFiles;

    $deletedAuthCodes = cleanExpiredAuthCodes($conn);
    $cleanupStats['download_auth_codes'] = $deletedAuthCodes;

    $deletedLogs = cleanOldLogs($conn);
    $cleanupStats['logs'] = $deletedLogs;

    echo generateCleanupReport($cleanupStats);

} catch (PDOException $e) {
    http_response_code(500);
    echo "Erreur de base de données : " . $e->getMessage();
}