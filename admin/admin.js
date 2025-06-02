const modal = document.getElementById('editModal');
const historyModal = document.getElementById('historyModal');
const closeBtn = document.getElementsByClassName('close')[0];
const historyCloseBtn = document.getElementById('historyModal').querySelector('.close');
const editForm = document.getElementById('editForm');

function editFile(id) {
    modal.style.display = 'block';
    document.getElementById('fileId').value = id;

    // Récupérer les données actuelles du fichier
    const row = document.querySelector(`tr[data-id="${id}"]`);
    document.getElementById('filename').value = row.children[1].textContent;
    document.getElementById('downloadCode').value = row.children[3].textContent;
}

closeBtn.onclick = function() {
    modal.style.display = 'none';
};

historyCloseBtn.onclick = function() {
    historyModal.style.display = 'none';
};

// Gestionnaire d'événements global pour les clics
window.addEventListener('click', function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
    if (event.target == historyModal) {
        historyModal.style.display = 'none';
    }
});

editForm.onsubmit = function(e) {
    e.preventDefault();
    
    const formData = new FormData(editForm);
    fetch('update_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la mise à jour');
        }
    });
}

function refreshDatabase() {
    if (confirm('Voulez-vous vraiment nettoyer la base de données ?')) {
        fetch('/clean_database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'refresh_database'
            })
        })
        .then(response => response.text())
        .then(result => {
            alert(result);
            window.location.href = '/admin/dashboard.php';
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du nettoyage de la base de données');
        });
    }
}

function showHistory(fileId) {
    const historyContent = document.getElementById('historyContent');
    
    fetch(`/admin/get_history.php?file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            let html = '<table class="history-table">';
            html += '<tr><th>Date</th><th>IP</th><th>Navigateur</th></tr>';
            data.forEach(download => {
                html += `<tr>
                    <td>${download.download_time}</td>
                    <td>${download.download_ip}</td>
                    <td>${download.user_agent}</td>
                </tr>`;
            });
            html += '</table>';
            historyContent.innerHTML = html;
            historyModal.style.display = 'block';
        })
        .catch(error => console.error('Erreur:', error));
}

// Ajouter cette nouvelle fonction
function generateNewAuthCode(fileId) {
    if (confirm('Voulez-vous générer un nouveau code d\'authentification ?')) {
        fetch('generate_auth_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `fileId=${fileId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Nouveau code généré : ${data.code}\nExpire le : ${data.expiration}`);
                location.reload();
            } else {
                alert('Erreur lors de la génération du code');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la génération du code');
        });
    }
}

function deleteTransfer(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce transfert ? Cette action est irréversible.')) {
        console.log('Tentative de suppression du fichier ID:', id);
        
        fetch('/admin/delete-transfer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + encodeURIComponent(id),
            credentials: 'same-origin' // Ajout important pour les sessions
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            console.log('Réponse:', data);
            if (data.success) {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.remove();
                }
                alert('Transfert supprimé avec succès');
            } else {
                throw new Error(data.message || 'Erreur inconnue');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors de la suppression: ' + error.message);
        });
    }
}
