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
    <?php include '../Present/header.php'; ?>
    
    <main>

        <h1>Liste des fichiers :</h1>
        <?php
            date_default_timezone_set('Europe/Paris');
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
                    echo "<tr>
                            <th>ID</th>
                            <th>Nom du fichier</th>
                            <th>Date d'envoi</th>
                            <th>Entreprise</th>
                            <th>Code téléchargement</th>
                            <th>Téléchargé</th>
                            <th>IP téléchargement</th>
                            <th>Navigateur</th>
                            <th>Date téléchargement</th>
                            <th>Ville</th>
                        </tr>";

                    foreach ($files as $file) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($file['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($file['filename']) . "</td>";
                        $date = new DateTime($file['upload_date']);
                        echo "<td>" . $date->format('d/m/Y H:i') . "</td>";
                        echo "<td>" . htmlspecialchars($file['company']) . "</td>";
                        echo "<td>" . htmlspecialchars($file['download_code']) . "</td>";
                        echo "<td>" . ($file['downloaded'] ? 'Oui' : 'Non') . "</td>";
                        echo "<td>" . htmlspecialchars($file['download_ip'] ?? 'Non téléchargé') . "</td>";
                        echo "<td>" . htmlspecialchars($file['user_agent'] ?? 'Non téléchargé') . "</td>";
                        $download_time = $file['download_time'] ? (new DateTime($file['download_time']))->format('d/m/Y H:i') : 'Non téléchargé';
                        echo "<td>" . $download_time . "</td>";
                        echo "<td>" . htmlspecialchars($file['city'] ?? 'Non renseigné') . "</td>";
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

    <?php include '../Present/footer.php'; ?>
</body>
</html>
