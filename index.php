<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <script src="/script.js"></script>
</head>
<body>
    <?php include './Present/header.php'; ?>
    
    <main>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            Nom de l'entreprise : <input type="text" name="company" required><br>
            Fichier : <input type="file" name="file" required><br>
            <input type="submit" value="Envoyer">
        </form>

        <h1>Bienvenue sur Telelec Transfert</h1>
        <p>Ce site vous permet d'envoyer et de recevoir des fichiers de manière sécurisée.</p>
        <p>Utilisez le menu pour naviguer entre les différentes sections.</p>
        <p>Pour toute question, n'hésitez pas à nous contacter.</p>
        <p>Le fichier doit faire maximum 50Go.</p>

        <h1>Liste des fichiers :</h1>
        <?php
            $host = 'db'; // Le nom du service dans le docker-compose
            $db = 'telelec';
            $user = 'telelecuser';
            $pass = 'userpassword';

            try {
                $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT * FROM files";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($files) > 0) {
                    echo "<table border='1'>";
                    echo "<tr><th>Nom du fichier</th><th>Date d'envoi</th><th>Entreprise</th></tr>";

                    foreach ($files as $file) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($file['filename']) . "</td>";
                        echo "<td>" . htmlspecialchars($file['upload_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($file['company']) . "</td>";
                        echo "</tr>";
                    }

                    echo "</table>";
                } else {
                    echo "<p>Aucun fichier enregistré pour le moment.</p>";
                }
            } catch (PDOException $e) {
                echo "<p>Erreur de connexion à la base de données : " . $e->getMessage() . "</p>";
            }
        ?>
    </main>

    <?php include './Present/footer.php'; ?>
</body>
</html>
