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
        document.body.classList.remove('modal-open');
    }
    if (event.target == historyModal) {
        historyModal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
});

// CORRECTION: Ajouter la fermeture par touche Échap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (historyModal.style.display === 'block') {
            historyModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        if (modal.style.display === 'block') {
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
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
    const historyModal = document.getElementById('historyModal');
    
    // CORRECTION: Ajouter une classe au body pour empêcher le défilement
    document.body.classList.add('modal-open');
    
    // Afficher un indicateur de chargement
    historyContent.innerHTML = '<div style="text-align: center; padding: 20px;"><p>⏳ Chargement de l\'historique...</p></div>';
    
    // CORRECTION: Afficher le modal immédiatement pour éviter les bugs d'affichage
    historyModal.style.display = 'block';
    
    fetch(`/admin/get_history.php?file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                historyContent.innerHTML = '<div class="no-history">Aucun téléchargement enregistré pour ce fichier</div>';
            } else {
                let html = '<table class="history-table">';
                html += '<thead><tr><th>📅 Date</th><th>🌐 Adresse IP</th><th>🖥️ Navigateur</th><th>📍 Ville</th></tr></thead>';
                html += '<tbody>';
                
                data.forEach(download => {
                    // Formater la date de manière plus lisible
                    const date = new Date(download.download_time);
                    const formattedDate = date.toLocaleDateString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Raccourcir le user agent pour l'affichage
                    let shortUserAgent = download.user_agent || 'Inconnu';
                    if (shortUserAgent.length > 50) {
                        shortUserAgent = shortUserAgent.substring(0, 50) + '...';
                    }
                    
                    html += `<tr>
                        <td>${formattedDate}</td>
                        <td>${download.download_ip || 'Inconnu'}</td>
                        <td title="${download.user_agent || 'Inconnu'}">${shortUserAgent}</td>
                        <td>${download.city || 'Non renseigné'}</td>
                    </tr>`;
                });
                
                html += '</tbody></table>';
                
                // Ajouter un résumé en bas
                html += `<div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 6px; font-size: 13px; color: #6c757d;">
                    <strong>Résumé :</strong> ${data.length} téléchargement${data.length > 1 ? 's' : ''} enregistré${data.length > 1 ? 's' : ''}
                </div>`;
                
                historyContent.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            historyContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c;"><p>❌ Erreur lors du chargement de l\'historique</p></div>';
        });
}

// CORRECTION: Améliorer la fermeture du modal
historyCloseBtn.onclick = function() {
    historyModal.style.display = 'none';
    document.body.classList.remove('modal-open'); // Réactiver le défilement
};

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

// Fonction pour copier le code A2F
function copyAuthCode(authCode) {
    navigator.clipboard.writeText(authCode).then(() => {
        // Animation de confirmation
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '✅';
        button.style.background = '#28a745';
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = '#ffc107';
        }, 1500);
        
        // Notification plus discrète
        showNotification('Code A2F copié dans le presse-papiers !', 'success');
    }).catch(() => {
        // Fallback pour les navigateurs plus anciens
        const textArea = document.createElement('textarea');
        textArea.value = authCode;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Code A2F copié !', 'success');
    });
}

// Fonction pour afficher des notifications discrètes
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => document.body.removeChild(notification), 300);
    }, 3000);
}

// Ajouter les animations CSS pour les notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
