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

// Fonctions pour la sélection multiple
function toggleSelectAll(checkbox) {
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    fileCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateDeleteButton();
}

function updateDeleteButton() {
    const selectedCheckboxes = document.querySelectorAll('.file-checkbox:checked');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const countSpan = document.getElementById('selectedCount');
    
    if (!deleteBtn || !countSpan) return; // Vérification de sécurité
    
    if (selectedCheckboxes.length > 0) {
        deleteBtn.style.display = 'inline-block';
        countSpan.textContent = selectedCheckboxes.length;
    } else {
        deleteBtn.style.display = 'none';
    }
    
    // Mettre à jour la case "Tout sélectionner"
    const selectAllCheckbox = document.getElementById('selectAll');
    const allCheckboxes = document.querySelectorAll('.file-checkbox');
    
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        if (selectedCheckboxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (selectedCheckboxes.length === allCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }
}

function deleteSelectedFiles() {
    const selectedCheckboxes = document.querySelectorAll('.file-checkbox:checked');
    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Aucun fichier sélectionné');
        return;
    }
    
    const confirmMessage = `Êtes-vous sûr de vouloir supprimer ${selectedIds.length} fichier(s) ? Cette action est irréversible.`;
    
    if (confirm(confirmMessage)) {
        // Afficher un indicateur de chargement
        const deleteBtn = document.getElementById('deleteSelectedBtn');
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '⏳ Suppression en cours...';
        deleteBtn.disabled = true;
        
        // Supprimer les fichiers un par un
        deleteFilesSequentially(selectedIds, 0, originalText);
    }
}

function deleteFilesSequentially(fileIds, index, originalButtonText) {
    if (index >= fileIds.length) {
        // Tous les fichiers ont été traités
        const deleteBtn = document.getElementById('deleteSelectedBtn');
        deleteBtn.innerHTML = originalButtonText;
        deleteBtn.disabled = false;
        deleteBtn.style.display = 'none';
        
        // Décocher toutes les cases
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        
        alert('Suppression terminée');
        location.reload();
        return;
    }
    
    const fileId = fileIds[index];
    
    fetch('/admin/delete-transfer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + encodeURIComponent(fileId),
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erreur réseau');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Supprimer la ligne du tableau
            const row = document.querySelector(`tr[data-id="${fileId}"]`);
            if (row) {
                row.remove();
            }
        } else {
            console.error(`Erreur lors de la suppression du fichier ${fileId}:`, data.message);
        }
        
        // Passer au fichier suivant
        setTimeout(() => {
            deleteFilesSequentially(fileIds, index + 1, originalButtonText);
        }, 200); // Petite pause entre les suppressions
    })
    .catch(error => {
        console.error(`Erreur lors de la suppression du fichier ${fileId}:`, error);
        
        // Continuer malgré l'erreur
        setTimeout(() => {
            deleteFilesSequentially(fileIds, index + 1, originalButtonText);
        }, 200);
    });
}

// NOUVELLE FONCTION - Empêcher la sélection des en-têtes
document.addEventListener('DOMContentLoaded', function() {
    // Empêcher la sélection des en-têtes de tableau
    document.querySelectorAll('.admin-table th').forEach(header => {
        header.addEventListener('selectstart', function(e) {
            e.preventDefault();
        });
        
        header.addEventListener('mousedown', function(e) {
            e.preventDefault();
        });
    });
    
    // Ajouter des événements aux cases à cocher existantes
    document.querySelectorAll('.file-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
            updateDeleteButton(); // Mettre à jour le bouton de suppression
        });
    });
    
    // Gérer la case "Tout sélectionner"
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.file-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
                const row = checkbox.closest('tr');
                if (this.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
            updateDeleteButton(); // Mettre à jour le bouton
        });
    }
    
    // Vérifier l'état initial
    updateDeleteButton();
});
