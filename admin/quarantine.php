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
    
    /**
     * Approuve un fichier en quarantaine et le déplace vers uploads
     *
     * @param PDO $conn Connexion à la base de données
     * @param int $fileId Identifiant du fichier
     * @param string $notes Notes administratives
     *
     * @return bool True si approbation réussie
     *
     * @throws Exception Si erreur lors du déplacement
     */
    function approveQuarantinedFile($conn, $fileId, $notes = '') {
        // ...existing code...
    }

    /**
     * Rejette définitivement un fichier en quarantaine
     *
     * @param PDO $conn Connexion à la base de données
     * @param int $fileId Identifiant du fichier
     * @param string $notes Raison du rejet
     *
     * @return bool True si rejet réussi
     */
    function rejectQuarantinedFile($conn, $fileId, $notes = '') {
        // ...existing code...
    }

    /**
     * Récupère la liste des fichiers en quarantaine
     *
     * @param PDO $conn Connexion à la base de données
     *
     * @return array Liste des fichiers avec métadonnées
     */
    function getQuarantinedFiles($conn) {
        // ...existing code...
    }
    
    // Traitement des actions admin
    if ($_POST['action'] ?? '') {
        $fileId = (int)$_POST['file_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? '';
        
        if ($action === 'approve') {
            // Approuver et déplacer vers uploads
            approveQuarantinedFile($conn, $fileId, $notes);
        } elseif ($action === 'reject') {
            // Rejeter et supprimer
            rejectQuarantinedFile($conn, $fileId, $notes);
        }
    }
    
    // Récupérer les fichiers en quarantaine
    $quarantineFiles = getQuarantinedFiles($conn);
    
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