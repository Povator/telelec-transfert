<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec - Tableau de Bord</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/css/dashboard.css">
    <script src="/script.js" defer></script>
</head>
<body>
    <?php include './Present/header.php'; ?>
    
    <main class="main-container">
        <h1 class="main-title">Tableau de Bord</h1>
        
        <div class="file-table">
            <table>
                <tr>
                    <th>Nom du fichier</th>
                    <th>Date d'envoi</th>
                    <th>Actions</th>
                </tr>
                <?php while ($file = $stmt->fetch()): ?>
                <tr data-file-id="<?= $file['id'] ?>">
                    <td><?= htmlspecialchars($file['original_name']) ?></td>
                    <td><?= $file['upload_date'] ?></td>
                    <td>
                        <a href="/download.php?id=<?= $file['id'] ?>">Télécharger</a>
                        <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                        <button class="delete-file" onclick="deleteFile(<?= $file['id'] ?>)">Supprimer</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </main>

    <?php include './Present/footer.php'; ?>
</body>
</html>