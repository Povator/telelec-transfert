<?php
/**
 * Page de téléchargement sécurisé avec authentification 2FA
 * 
 * Gère le processus de téléchargement avec vérification du code
 * d'authentification et logging complet des accès.
 *
 * @author  TeleLec
 * @version 1.4
 */

/**
 * Valide le code d'authentification 2FA pour un fichier
 *
 * @param PDO $conn Connexion à la base de données
 * @param string $downloadCode Code de téléchargement
 * @param string $authCode Code d'authentification fourni
 *
 * @return array Résultat de la validation avec status et données du fichier
 *
 * @throws PDOException Si erreur de base de données
 */
function validateAuthCode($conn, $downloadCode, $authCode) {
    $sql = "SELECT f.*, dac.auth_code 
            FROM files f 
            JOIN download_auth_codes dac ON f.id = dac.file_id 
            WHERE f.download_code = ? 
            AND dac.auth_code = ? 
            AND dac.expiration_date > NOW() 
            AND dac.used = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$downloadCode, $authCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtient la ville à partir d'une adresse IP via API externe
 *
 * @param string $ip Adresse IP à géolocaliser
 *
 * @return string Nom de la ville ou 'Inconnue' si échec de géolocalisation
 */
function getCity($ip) {
    $apiUrl = "http://ip-api.com/json/" . $ip;
    $response = @file_get_contents($apiUrl);
    if ($response) {
        $data = json_decode($response, true);
        return ($data && $data['status'] === 'success') ? $data['city'] : 'Inconnue';
    }
    return 'Inconnue';
}

/**
 * Enregistre une tentative de téléchargement dans les logs
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 * @param string $status Statut de la tentative ('success', 'failed')
 * @param string $details Détails supplémentaires
 *
 * @return bool True si le log a été enregistré avec succès
 */
function logDownloadAttempt($conn, $fileId, $status, $details = '') {
    $sql = "INSERT INTO download_attempts (file_id, attempt_time, status, details) 
            VALUES (?, NOW(), ?, ?)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$fileId, $status, $details]);
}

/**
 * Met à jour l'historique de téléchargement
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 * @param string $city Ville de téléchargement
 *
 * @return bool True si historique mis à jour
 */
function updateDownloadHistory($conn, $fileId, $city) {
    $sql = "UPDATE download_history SET city = ? WHERE file_id = ? ORDER BY download_time DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$city, $fileId]);
}

/**
 * Marque un code d'authentification comme utilisé
 *
 * @param PDO $conn Connexion à la base de données
 * @param int $fileId Identifiant du fichier
 *
 * @return bool True si marquage réussi
 */
function markAuthCodeAsUsed($conn, $fileId) {
    $sql = "UPDATE download_auth_codes SET used = TRUE WHERE file_id = ? AND expiration_date > NOW() AND used = FALSE";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$fileId]);
}

/**
 * Vérifie si un fichier est encore téléchargeable
 *
 * @param array $fileData Données du fichier
 *
 * @return bool True si téléchargeable
 */
function isFileDownloadable($fileData) {
    // Vérifie si le fichier existe et si le téléchargement n'a pas déjà été effectué
    return file_exists(__DIR__ . '/uploads/' . $fileData['filename']) && !$fileData['downloaded'];
}

/**
 * Force le téléchargement sécurisé d'un fichier
 *
 * @param string $filepath Chemin complet du fichier
 * @param string $filename Nom à afficher lors du téléchargement
 *
 * @return void Force le téléchargement et arrête l'exécution
 */
function forceSecureDownload($filepath, $filename) {
    // Headers pour le téléchargement
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// AJOUT: Définir le fuseau horaire Europe/Paris pour tout le script
date_default_timezone_set('Europe/Paris');

if (!isset($_GET['code'])) {
    die('Code de téléchargement manquant');
}

$downloadCode = $_GET['code'];
$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifier si un code A2F a été soumis
    if (isset($_POST['auth_code'])) {
        // Vérifier le code A2F
        $file = validateAuthCode($conn, $downloadCode, $_POST['auth_code']);

        if ($file) {
            // Marquer le code comme utilisé
            markAuthCodeAsUsed($conn, $file['id']);

            $filepath = __DIR__ . '/uploads/' . $file['filename'];
            
            if (file_exists($filepath)) {
                // CORRECTION: Définir le fuseau horaire
                date_default_timezone_set('Europe/Paris');
                
                $userIp = $_SERVER['REMOTE_ADDR'];
                $city = getCity($userIp);
                $downloadTime = date('Y-m-d H:i:s'); // Utiliser PHP au lieu de NOW()

                $insertHistorySql = "INSERT INTO download_history 
                    (file_id, download_time, download_ip, user_agent, city) 
                    VALUES (?, ?, ?, ?, ?)";
                $historyStmt = $conn->prepare($insertHistorySql);
                $historyStmt->execute([
                    $file['id'],
                    $downloadTime, // Utiliser la variable PHP
                    $userIp,
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu',
                    $city
                ]);
                
                // AJOUT: Mettre à jour le statut "téléchargé" dans la table files
                $updateDownloadedSql = "UPDATE files SET downloaded = TRUE WHERE id = ?";
                $updateStmt = $conn->prepare($updateDownloadedSql);
                $updateStmt->execute([$file['id']]);

                // Force le téléchargement du fichier
                forceSecureDownload($filepath, $file['filename']);
            }
        } else {
            $error = "Code d'authentification invalide ou expiré";
        }
    }

    // Afficher le formulaire de saisie du code A2F
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentification requise</title>
        <link rel="stylesheet" href="/style.css">
        <style>
            .auth-container {
                max-width: 500px;
                margin: 50px auto;
                padding: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .auth-form {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .form-group {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .auth-form input {
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
            }
            .auth-form button {
                padding: 12px;
                background: #2ecc71;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
                transition: background 0.3s;
            }
            .auth-form button:hover {
                background: #27ae60;
            }
            .error {
                color: #e74c3c;
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <?php include './includes/header.php'; ?>
        
        <main>
            <div class="auth-container">
                <h2>Authentification requise</h2>
                <?php if (isset($error)): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="auth_code">Code d'authentification :</label>
                        <input type="text" id="auth_code" name="auth_code" required 
                               pattern="[0-9]{6}" maxlength="6" placeholder="Entrez le code à 6 chiffres">
                    </div>
                    <button type="submit">Valider</button>
                </form>
            </div>
        </main>
        
        <?php include './includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;

} catch (PDOException $e) {
    die('Erreur de base de données : ' . $e->getMessage());
}