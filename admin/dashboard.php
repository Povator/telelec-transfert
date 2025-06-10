<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="admin.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/flavicon/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/flavicon/favicon.png" type="image/png">
    
    <script src="/script.js" defer></script>
    <script src="admin.js" defer></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
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

                // Modifier la requête SQL pour inclure la ville de l'auteur depuis les logs
                $sql = "SELECT f.*, 
                        (SELECT COUNT(*) FROM download_history WHERE file_id = f.id) as download_count,
                        dac.auth_code,
                        dac.expiration_date,
                        dh.download_time,
                        dh.download_ip,
                        dh.user_agent,
                        dh.city,
                        f.upload_ip as author_ip,
                        (SELECT city FROM file_logs WHERE file_id = f.id AND action_type = 'upload_complete' LIMIT 1) as author_city
                        FROM files f
                        LEFT JOIN download_auth_codes dac ON f.id = dac.file_id 
                            AND dac.expiration_date > NOW() 
                            AND dac.used = FALSE
                        LEFT JOIN download_history dh ON f.id = dh.file_id
                        WHERE 1=1"; // Ajout de WHERE 1=1 pour faciliter l'ajout de conditions
                $stmt = $conn->prepare($sql);

                // Filtrer selon le statut antivirus si demandé
                if (isset($_GET['filter'])) {
                    switch ($_GET['filter']) {
                        case 'clean':
                            $sql .= " AND (antivirus_status = 'true' OR antivirus_status = '1')";
                            break;
                        case 'warning':
                            $sql .= " AND antivirus_status = 'warning'";
                            break;
                        case 'virus':
                            $sql .= " AND (antivirus_status = 'false' OR antivirus_status = '0')";
                            break;
                    }
                }

                $sql .= " ORDER BY f.id DESC"; // Déplacer le ORDER BY ici
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($files) > 0) {
                    echo "<table class='admin-table'>";
                    // Ajouter une colonne de sélection
                    echo "<tr>
                            <th><input type='checkbox' id='selectAll' onchange='toggleSelectAll(this)'></th>
                            <th>ID</th>
                            <th>Nom du fichier</th>
                            <th>Date d'envoi</th>
                            <th>IP Auteur</th>
                            <th>Ville Auteur</th>
                            <th>Code téléchargement</th>
                            <th>Code A2F</th>
                            <th>Expiration A2F</th>
                            <th>Téléchargé</th>
                            <th>IP téléchargement</th>
                            <th>Navigateur</th>
                            <th>Date téléchargement</th>
                            <th>Ville</th>
                            <th>Nombre téléchargements</th>
                            <th>Test ClamAV</th>
                            <th>Actions</th>
                        </tr>";

                    foreach ($files as $file) {
                        echo "<tr data-id='" . htmlspecialchars((string)$file['id']) . "'>";
                        // Ajouter la case à cocher
                        echo "<td><input type='checkbox' class='file-checkbox' value='" . (int)$file['id'] . "' onchange='updateDeleteButton()'></td>";
                        echo "<td>" . htmlspecialchars((string)$file['id']) . "</td>";
                        echo "<td title='" . htmlspecialchars((string)$file['filename']) . "' class='truncate'>" . htmlspecialchars((string)$file['filename']) . "</td>";
                        echo "<td>" . (new DateTime($file['upload_date']))->format('d/m/Y H:i') . "</td>";
                        echo "<td>" . htmlspecialchars((string)$file['upload_ip']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($file['author_city'] ?? 'Non renseigné')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$file['download_code']) . " <a href='/download.php?code=" . htmlspecialchars((string)$file['download_code']) . "' target='_blank' class='download-link'>🔗</a></td>";
                        echo "<td>" . htmlspecialchars((string)($file['auth_code'] ?? 'Code expiré')) . "</td>";
                        echo "<td>" . ($file['expiration_date'] ? (new DateTime($file['expiration_date']))->format('d/m/Y H:i') : '-') . "</td>";
                        echo "<td>" . ($file['downloaded'] ? 'Oui' : 'Non') . "</td>";
                        echo "<td>" . htmlspecialchars((string)($file['download_ip'] ?? 'Non téléchargé')) . "</td>";
                        echo "<td class='truncate' title='" . htmlspecialchars((string)($file['user_agent'] ?? 'Non téléchargé')) . "'>" . htmlspecialchars((string)($file['user_agent'] ?? 'Non téléchargé')) . "</td>";
                        $download_time = $file['download_time'] ? (new DateTime($file['download_time']))->format('d/m/Y H:i') : 'Non téléchargé';
                        echo "<td>" . $download_time . "</td>";
                        echo "<td class='truncate'>" . htmlspecialchars((string)($file['city'] ?? 'Non renseigné')) . "</td>";
                        echo "<td>" . (int)$file['download_count'] . " fois</td>";
                        
                        // Nouvelle colonne pour le statut ClamAV
                        echo "<td>";
                        if ($file['antivirus_status'] == 'true' || $file['antivirus_status'] === '1' || $file['antivirus_status'] === 1) {
                            echo "<span class='badge bg-success' title='" . htmlspecialchars($file['antivirus_message'] ?? '') . "'>
                                    <i class='bi bi-shield-check'></i> Sain
                                  </span>";
                        } elseif ($file['antivirus_status'] == 'warning') {
                            echo "<span class='badge bg-warning' title='" . htmlspecialchars($file['antivirus_message'] ?? '') . "'>
                                    <i class='bi bi-shield-exclamation'></i> Attention
                                  </span>";
                        } elseif ($file['antivirus_status'] == 'false' || $file['antivirus_status'] === '0' || $file['antivirus_status'] === 0) {
                            echo "<span class='badge bg-danger' title='" . htmlspecialchars($file['antivirus_message'] ?? '') . "'>
                                    <i class='bi bi-shield-x'></i> Virus
                                  </span>";
                        } else {
                            echo "<span class='badge bg-secondary' title='Analyse non disponible'>
                                    <i class='bi bi-shield'></i> Inconnu
                                  </span>";
                        }
                        echo "</td>";
                        
                        echo "<td>
                                <button onclick='showHistory(" . (int)$file['id'] . ")'>Voir historique</button>
                                <button class='edit-btn' onclick='editFile(" . (int)$file['id'] . ")'>Modifier</button>
                                <button onclick='generateNewAuthCode(" . (int)$file['id'] . ")'>Générer code A2F</button>
                                <button onclick='deleteTransfer(" . (int)$file['id'] . ")' class='delete-btn'>Supprimer</button>
                            </td>";
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
        
        <!-- Ajouter à côté des autres filtres -->
        <div class="btn-group mb-3">
            <a href="?filter=all" class="btn btn-outline-secondary <?= (!isset($_GET['filter']) || $_GET['filter'] == 'all') ? 'active' : '' ?>">Tous</a>
            <a href="?filter=clean" class="btn btn-outline-success <?= (isset($_GET['filter']) && $_GET['filter'] == 'clean') ? 'active' : '' ?>">Fichiers sains</a>
            <a href="?filter=warning" class="btn btn-outline-warning <?= (isset($_GET['filter']) && $_GET['filter'] == 'warning') ? 'active' : '' ?>">Avertissements</a>
            <a href="?filter=virus" class="btn btn-outline-danger <?= (isset($_GET['filter']) && $_GET['filter'] == 'virus') ? 'active' : '' ?>">Virus</a>
        </div>
        
        <!-- DÉPLACER CES BOUTONS ICI, AVANT LA FERMETURE DE MAIN -->
        <div class="admin-controls">
            <button type="button" onclick="refreshDatabase()" class="refresh-btn">Rafraîchir la BDD</button>
            <button type="button" id="deleteSelectedBtn" onclick="deleteSelectedFiles()" class="delete-multiple-btn" style="display: none;">
                🗑️ Supprimer la sélection (<span id="selectedCount">0</span>)
            </button>
        </div>
    </main>
    
    <!-- Modals après main -->
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
                    <label for="downloadCode">Code de téléchargement :</label>
                    <input type="text" id="downloadCode" name="downloadCode">
                </div>
                <button type="submit">Enregistrer</button>
            </form>
        </div>
    </div>

    <!-- Modal d'historique -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Historique des téléchargements</h2>
            <div id="historyContent"></div>
        </div>
    </div>
            
    <?php include '../includes/footer.php'; ?>
</body>
</html>
