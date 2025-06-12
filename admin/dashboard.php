<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

// AJOUT: Définir le fuseau horaire Europe/Paris pour tout le script
date_default_timezone_set('Europe/Paris');
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
            $host = 'db'; // Le nom du service dans le docker-compose
            $db = 'telelec';
            $user = 'telelecuser';
            $pass = 'userpassword';

            try {
                $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Corriger la requête pour utiliser upload_city au lieu de chercher dans file_logs
                $sql = "SELECT f.*, 
                        (SELECT COUNT(*) FROM download_history WHERE file_id = f.id) as download_count,
                        dac.auth_code,
                        dac.expiration_date,
                        dh.download_time,
                        dh.download_ip,
                        dh.user_agent,
                        dh.city,
                        f.upload_ip as author_ip,
                        f.upload_city as author_city
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
                        echo "<td><input type='checkbox' class='file-checkbox' value='" . (int)$file['id'] . "'></td>";
                        echo "<td>" . (int)$file['id'] . "</td>";
                        echo "<td class='truncate' title='" . htmlspecialchars((string)$file['filename']) . "'>" . htmlspecialchars((string)$file['filename']) . "</td>";
                        
                        // CORRECTION: Utiliser DateTime pour formater correctement la date avec le fuseau horaire de Paris
                        $uploadDate = new DateTime($file['upload_date']);
                        echo "<td>" . $uploadDate->format('d/m/Y H:i') . "</td>";
                        
                        echo "<td>" . htmlspecialchars((string)($file['author_ip'] ?? 'Non renseigné')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($file['author_city'] ?? 'Non renseigné')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$file['download_code']) . " <a href='/download.php?code=" . htmlspecialchars((string)$file['download_code']) . "' target='_blank' class='download-link'>🔗</a></td>";
                        
                        // Code A2F avec mise en évidence
                        if ($file['auth_code']) {
                            echo "<td class='auth-code-cell'>
                                    <span class='auth-code-value'>" . htmlspecialchars((string)$file['auth_code']) . "</span>
                                    <button class='copy-auth-btn' onclick='copyAuthCode(\"" . htmlspecialchars((string)$file['auth_code']) . "\")'>📋</button>
                                  </td>";
                        } else {
                            echo "<td><span class='no-code'>Code expiré</span></td>";
                        }
                        
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
        
        <!-- AJOUTER cette section d'aide avant la table -->
        <div class="admin-help" style="background-color: #e7f3ff; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="color: #0c5460; margin-top: 0;">🔐 Gestion des codes A2F</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>📋 Copier un code A2F :</h4>
                    <p>Cliquez sur l'icône 📋 à côté du code pour le copier. Communiquez-le au destinataire par un canal sécurisé (SMS, appel, etc.).</p>
                </div>
                <div>
                    <h4>🔄 Générer un nouveau code :</h4>
                    <p>Utilisez le bouton "Générer code A2F" pour créer un nouveau code si l'ancien est compromis ou expiré.</p>
                </div>
            </div>
            <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 4px;">
                <strong>⚠️ Sécurité :</strong> Les codes A2F ne sont plus visibles lors de l'upload pour renforcer la sécurité. 
                Seuls les administrateurs y ont accès via ce dashboard.
            </div>
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
