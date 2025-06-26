/**
 * Scripts d'administration pour la gestion des fichiers
 * 
 * Fournit les fonctionnalités de gestion en lot, modals
 * et interactions AJAX pour l'interface d'administration.
 *
 * @author  TeleLec
 * @version 1.8
 */

/**
 * Génère un nouveau code d'authentification pour un fichier
 *
 * @param {number} fileId Identifiant du fichier
 */
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

/**
 * Supprime un transfert avec confirmation
 *
 * @param {number} id Identifiant du transfert à supprimer
 */
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

/**
 * Met à jour l'affichage du bouton de suppression en lot
 */
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

/**
 * Supprime plusieurs fichiers de manière séquentielle
 *
 * @param {Array<number>} fileIds Liste des identifiants de fichiers
 * @param {number} index Index actuel dans la liste
 * @param {string} originalButtonText Texte original du bouton
 */
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

/**
 * Copie un code d'authentification dans le presse-papiers
 *
 * @param {string} authCode Code d'authentification à copier
 */
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

/**
 * Copie un lien de téléchargement dans le presse-papiers
 *
 * @param {string} downloadCode Code de téléchargement pour construire l'URL
 */
function copyDownloadLink(downloadCode) {
    const baseUrl = window.location.origin;
    const downloadUrl = `${baseUrl}/download.php?code=${downloadCode}`;
    
    // Copier dans le presse-papiers
    navigator.clipboard.writeText(downloadUrl).then(() => {
        // Animation du bouton
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '✓ Copié !';
        button.classList.add('copied');
        
        // Afficher la notification
        showCopyNotification('Lien de téléchargement copié dans le presse-papiers !');
        
        // Remettre le bouton à l'état normal
        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('copied');
        }, 2000);
        
    }).catch(() => {
        // Fallback pour les navigateurs plus anciens
        const textArea = document.createElement('textarea');
        textArea.value = downloadUrl;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        showCopyNotification('Lien copié !');
    });
}

// Fonction pour afficher la notification de copie
function showCopyNotification(message) {
    // Supprimer les notifications existantes
    const existingNotifications = document.querySelectorAll('.copy-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Créer la nouvelle notification
    const notification = document.createElement('div');
    notification.className = 'copy-notification';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Afficher avec animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Supprimer après 3 secondes
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
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

// Gestionnaire pour fermer les modals
document.addEventListener('DOMContentLoaded', function() {
    // Gérer la fermeture des modals
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Fermer modal en cliquant à l'extérieur
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Gérer la soumission du formulaire d'édition
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('/admin/edit-file.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showBootstrapToast('Fichier modifié avec succès !', 'success');
                document.getElementById('editModal').style.display = 'none';
                // Recharger la page pour voir les changements
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Erreur: ' + (data.message || 'Modification échouée'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la modification');
        });
    });
});
