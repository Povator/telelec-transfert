<?php
/**
 * Script de connexion pour l'interface administrateur
 * 
 * @author  TeleLec
 * @version 1.0
 */
session_start();
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: /admin/dashboard.php');
    exit;
}

/** @var array Configuration contenant les identifiants admin */
$config = require(__DIR__ . '/../secure/config.php');


/**
 * Établissement de la connexion à la base de données
 * @var PDO $conn Instance de connexion PDO
 */
try {
    $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

/** @var string $stored_username Nom d'utilisateur administrateur stocké */
$stored_username = $config['admin_username'];
/** @var string $stored_password_hash Hash du mot de passe administrateur */
$stored_password_hash = $config['admin_password_hash'];

/**
 * Traitement de la soumission du formulaire
 * Vérifie les identifiants et crée une session si valides
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';


    if ($username === $stored_username && password_verify($password, $stored_password_hash)) {
        $_SESSION['admin'] = true;

        header("Location: /admin/dashboard.php");

        try {
            $sql = "INSERT INTO sessions (id, user_id, last_activity, ip_address, user_agent) 
            VALUES (?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                session_id(),
                1,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (PDOException $e) {
            error_log("Erreur BDD session: " . $e->getMessage());
        }

        exit;
    } else {
        $error = "Identifiants incorrects";

        try {
            $sql = "INSERT INTO file_logs (action_type, action_date, user_ip, status, details)
            VALUES ('failed_login', NOW(), ?, 'error', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $_SERVER['REMOTE_ADDR'],
                'Tentative de connexion échouée avec identifiant : ' . htmlspecialchars($username)
            ]);
        } catch (PDOException $e) {
            error_log("Erreur BDD log: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        .login-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
    <!-- Favicon -->
    <link rel="icon" href="/flavicon/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/flavicon/favicon.png" type="image/png">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="login-form">
            <h2>Connexion Administrateur</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Identifiant</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="nav-button">Se connecter</button>
            </form>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
