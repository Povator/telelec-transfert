<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

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
        $sql = "SELECT f.*, dac.auth_code 
                FROM files f 
                JOIN download_auth_codes dac ON f.id = dac.file_id 
                WHERE f.download_code = ? 
                AND dac.auth_code = ? 
                AND dac.expiration_date > NOW() 
                AND dac.used = FALSE";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$downloadCode, $_POST['auth_code']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            // Marquer le code comme utilisé
            $sql = "UPDATE download_auth_codes SET used = TRUE WHERE file_id = ? AND auth_code = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$file['id'], $_POST['auth_code']]);

            $filepath = __DIR__ . '/uploads/' . $file['filename'];
            
            if (file_exists($filepath)) {
                // Enregistrer dans l'historique
                $insertHistorySql = "INSERT INTO download_history 
                    (file_id, download_time, download_ip, user_agent) 
                    VALUES (?, NOW(), ?, ?)";
                $historyStmt = $conn->prepare($insertHistorySql);
                $historyStmt->execute([
                    $file['id'],
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu'
                ]);

                // Headers pour le téléchargement
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit;
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
        <?php include './Present/header.php'; ?>
        
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
        
        <?php include './Present/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;

} catch (PDOException $e) {
    die('Erreur de base de données : ' . $e->getMessage());
}