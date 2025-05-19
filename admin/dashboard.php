<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="admin.css">
    <script src="/script.js" defer></script>
    <script src="admin.js" defer></script>
</head>
<body>
    <?php include '../Present/header.php'; ?>
    
    <main>
        
        <div class="admin-controls">
            <button type="button" onclick="refreshDatabase()" class="refresh-btn">Rafraîchir la BDD</button>
        </div>
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
                    echo "<table class='admin-table'>";
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
                            <th>Actions</th>
                        </tr>";

                    foreach ($files as $file) {
                        echo "<tr data-id='" . htmlspecialchars($file['id']) . "'>";
                        echo "<td>" . htmlspecialchars($file['id']) . "</td>";
                        echo "<td title='" . htmlspecialchars($file['filename']) . "' class='truncate'>" . htmlspecialchars($file['filename']) . "</td>";
                        $date = new DateTime($file['upload_date']);
                        echo "<td>" . $date->format('d/m/Y H:i') . "</td>";
                        echo "<td class='truncate'>" . htmlspecialchars($file['company']) . "</td>";
                        echo "<td>" . htmlspecialchars($file['download_code']) . " <a href='/download.php?code=" . htmlspecialchars($file['download_code']) . "' target='_blank' class='download-link'>🔗</a></td>";                        echo "<td>" . ($file['downloaded'] ? 'Oui' : 'Non') . "</td>";
                        echo "<td>" . htmlspecialchars($file['download_ip'] ?? 'Non téléchargé') . "</td>";
                        echo "<td class='truncate' title='" . htmlspecialchars($file['user_agent'] ?? 'Non téléchargé') . "'>" . htmlspecialchars($file['user_agent'] ?? 'Non téléchargé') . "</td>";
                        $download_time = $file['download_time'] ? (new DateTime($file['download_time']))->format('d/m/Y H:i') : 'Non téléchargé';
                        echo "<td>" . $download_time . "</td>";
                        echo "<td class='truncate'>" . htmlspecialchars($file['city'] ?? 'Non renseigné') . "</td>";
                        echo "<td><button class='edit-btn' onclick='editFile(" . htmlspecialchars($file['id']) . ")'>Modifier</button></td>";
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

    <!-- Modal d'édition -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Modifier le fichier</h2>
            <form id="editForm">
                <input type="hidden" id="fileId" name="fileId">
                <div class="form-group">
                    <label for="filename">Nom du fichier :</label>
                    <input type="text" id="filename" name="filename">
                </div>
                <div class="form-group">
                    <label for="company">Entreprise :</label>
                    <input type="text" id="company" name="company">
                </div>
                <div class="form-group">
                    <label for="downloadCode">Code de téléchargement :</label>
                    <input type="text" id="downloadCode" name="downloadCode">
                </div>
                <button type="submit">Enregistrer</button>
            </form>
        </div>
    </div>

    <?php include '../Present/footer.php'; ?>
</body>
</html>
