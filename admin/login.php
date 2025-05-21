<?php
session_start();

$config = require('/secure/config.php');// ← adapte ce chemin si besoin

$stored_username = $config['admin_username'];
$stored_password_hash = $config['admin_password_hash'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $stored_username && password_verify($password, $stored_password_hash)) {
        $_SESSION['admin'] = true;
        header("Location: /admin/dashboard.php");
        exit;
    } else {
        $error = "Identifiants incorrects";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Transfert Tetelec</title>
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
</head>
<body>
    <?php include '../Present/header.php'; ?>
    
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

    <?php include '../Present/footer.php'; ?>
</body>
</html>
