<?php
// ...existing code...

// Suppression d'un transfert
if (isset($_POST['action']) && $_POST['action'] == 'deleteTransfer') {
    $id = $_POST['id'];
    
    // Préparez et exécutez la requête de suppression
    $stmt = $pdo->prepare("DELETE FROM transfers WHERE id = :id");
    $stmt->execute(['id' => $id]);
    
    echo json_encode(['success' => true]);
    exit;
}

// ...existing code...
?>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom fichier</th>
            <th>Date</th>
            <th>Entreprise</th>
            <th>Code</th>
            <th>Téléchargé</th>
            <th>IP</th>
            <th>Navigateur</th>
            <th>Date téléchargement</th>
            <th>Ville</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($transfers as $transfer): ?>
        <tr data-id="<?= $transfer['id'] ?>">
            <td><?= $transfer['id'] ?></td>
            <td class="truncate" title="<?= $transfer['file_name'] ?>"><?= $transfer['file_name'] ?></td>
            <td><?= $transfer['date'] ?></td>
            <td><?= $transfer['company'] ?></td>
            <td class="code-cell"><?= $transfer['code'] ?></td>
            <td><?= $transfer['downloaded'] ? 'Oui' : 'Non' ?></td>
            <td><?= $transfer['ip'] ?></td>
            <td><?= $transfer['browser'] ?></td>
            <td><?= $transfer['download_date'] ?></td>
            <td><?= $transfer['city'] ?></td>
            <td>
                <button onclick="showHistory(<?= $transfer['id'] ?>)" class="history-btn">Historique</button>
                <button onclick="editTransfer(<?= $transfer['id'] ?>)" class="edit-btn">Modifier</button>
                <button onclick="generateNewAuthCode(<?= $transfer['id'] ?>)" class="auth-btn">Nouveau code</button>
                <button onclick="deleteTransfer(<?= $transfer['id'] ?>)" class="delete-btn">Supprimer</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
function deleteTransfer(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
        $.ajax({
            url: 'admin-dashboard.php',
            type: 'POST',
            data: { action: 'deleteTransfer', id: id },
            success: function(response) {
                response = JSON.parse(response);
                if (response.success) {
                    alert('Transfert supprimé avec succès.');
                    location.reload();
                } else {
                    alert('Une erreur est survenue lors de la suppression du transfert.');
                }
            }
        });
    }
}
</script>

<style>
.delete-btn {
    background-color: #e74c3c;
    color: white;
}

.delete-btn:hover {
    background-color: #c0392b;
}
</style>