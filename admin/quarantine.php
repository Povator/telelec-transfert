<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Traitement des actions admin
    if ($_POST['action'] ?? '') {
        $fileId = (int)$_POST['file_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? '';
        
        if ($action === 'approve') {
            // Approuver et déplacer vers uploads
            // ... logique d'approbation
        } elseif ($action === 'reject') {
            // Rejeter et supprimer
            // ... logique de rejet
        }
    }
    
    // Récupérer les fichiers en quarantaine
    $sql = "SELECT * FROM quarantine_files WHERE status = 'pending' ORDER BY upload_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $quarantineFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fichiers en quarantaine - Admin</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <h1>🔒 Fichiers en quarantaine</h1>
    
    <?php if (empty($quarantineFiles)): ?>
        <p>✅ Aucun fichier en attente de validation</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th>Date upload</th>
                    <th>IP</th>
                    <th>Détails scan</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quarantineFiles as $file): ?>
                <tr>
                    <td><?= htmlspecialchars($file['filename']) ?></td>
                    <td><?= $file['upload_date'] ?></td>
                    <td><?= htmlspecialchars($file['upload_ip']) ?></td>
                    <td><?= htmlspecialchars($file['scan_details']) ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="text" name="notes" placeholder="Notes..." size="20">
                            <button type="submit" class="approve-btn">✅ Approuver</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="reject-btn">❌ Rejeter</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>