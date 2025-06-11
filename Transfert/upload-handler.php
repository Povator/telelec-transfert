<?php
/**
 * Gestionnaire d'upload de fichiers
 * 
 * Ce script gère le téléversement de fichiers, incluant:
 * - La validation des fichiers
 * - La sécurisation des noms de fichiers
 * - La gestion des doublons
 * - L'analyse antivirus
 * 
 * @author  TeleLec
 * @version 1.2
 */

// Configuration des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// AJOUT: Définir le fuseau horaire Europe/Paris pour tout le script
date_default_timezone_set('Europe/Paris');

header('Content-Type: application/json');

// Inclure les utilitaires et le système antivirus
require_once __DIR__ . '/../includes/file_utils.php';
require_once __DIR__ . '/../includes/antivirus.php';

// Traitement de la requête
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /** @var string $targetDir Répertoire de destination des fichiers */
    $targetDir = __DIR__ . "/../uploads/";

    // Crée le répertoire d'uploads s'il n'existe pas
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Vérifie si un fichier a été envoyé
    if (!isset($_FILES["fileToUpload"])) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun fichier reçu']);
        exit;
    }

    // Récupère le nom original et génère un nom unique
    $originalName = $_FILES["fileToUpload"]["name"];
    $uniqueFile = generateUniqueFilename($targetDir, $originalName);
    $finalName = $uniqueFile['filename'];
    $targetFile = $uniqueFile['filepath'];

    // Analyse antivirus du fichier temporaire
    $tempFilePath = $_FILES["fileToUpload"]["tmp_name"];
    
    try {
        $scanResult = scanFile($tempFilePath);
    } catch (Exception $e) {
        $scanResult = [
            'status' => 'warning',
            'message' => 'Impossible d\'analyser le fichier avec l\'antivirus : ' . $e->getMessage()
        ];
    }

    // Vérifier le résultat du scan
    if ($scanResult['status'] === true || $scanResult['status'] === 'warning') {
        // Le fichier est sûr ou suspect mais accepté
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
            
            try {
                // Connexion à la base de données
                $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // CORRECTION IMPORTANTE : Utiliser la même timezone partout
                date_default_timezone_set('Europe/Paris');

                // Générer un code de téléchargement
                function generateDownloadCode($length = 8) {
                    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $code = '';
                    for ($i = 0; $i < $length; $i++) {
                        $code .= $characters[rand(0, strlen($characters) - 1)];
                    }
                    return $code;
                }

                $downloadCode = generateDownloadCode();

                // Conversion explicite du statut en chaîne
                $antivirusStatus = $scanResult['status'];
                if ($antivirusStatus === true) {
                    $antivirusStatus = 'true';
                } elseif ($antivirusStatus === false) {
                    $antivirusStatus = 'false';
                }

                // CORRECTION: Récupérer la ville lors de l'upload
                function getCity($ip) {
                    $apiUrl = "http://ip-api.com/json/" . $ip;
                    $response = @file_get_contents($apiUrl);
                    if ($response) {
                        $data = json_decode($response, true);
                        return ($data && $data['status'] === 'success') ? $data['city'] : 'Unknown';
                    }
                    return 'Unknown';
                }
                
                $userCity = getCity($_SERVER['REMOTE_ADDR']);
                
                // Insertion du fichier dans la base de données
                // CORRECTION: Utiliser date() PHP au lieu de NOW() pour respecter le fuseau horaire
                $uploadDate = date('Y-m-d H:i:s'); // Utilise le fuseau Europe/Paris défini en haut du fichier
                
                $sql = "INSERT INTO files (filename, upload_date, upload_ip, upload_city, download_code, antivirus_status, antivirus_message) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $finalName, 
                    $uploadDate,
                    $_SERVER['REMOTE_ADDR'], 
                    $userCity, // Utiliser la vraie ville au lieu de 'Unknown'
                    $downloadCode,
                    $antivirusStatus,
                    $scanResult['message']
                ]);

                $fileId = $conn->lastInsertId();

                // Pour debug - enregistrer dans les logs
                error_log("UPLOAD SUCCESS: Original='{$originalName}' Final='{$finalName}' FileId={$fileId}");

                // Retourner le nom final
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Fichier uploadé avec succès',
                    'filename' => $finalName,  // Nom final (avec suffixe si doublon)
                    'original' => $originalName,
                    'file_id' => $fileId
                ]);

            } catch (PDOException $e) {
                error_log("UPLOAD ERROR: " . $e->getMessage());
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erreur lors de l\'enregistrement en base: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Erreur lors du déplacement du fichier'
            ]);
        }
    } else {
        // Le fichier est infecté
        echo json_encode([
            'status' => 'error',
            'message' => "Fichier rejeté: {$scanResult['message']}"
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Méthode HTTP non autorisée'
    ]);
}
?>