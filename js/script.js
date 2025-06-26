/**
 * Scripts utilitaires généraux pour TeleLec-Transfert
 * 
 * Gère les interactions de base, drag & drop et fonctionnalités
 * communes à travers l'application.
 *
 * @author  TeleLec
 * @version 1.2
 */

/**
 * Initialise les fonctionnalités drag & drop sur les éléments de liste
 *
 * @param {NodeList} listItems Collection d'éléments à rendre triables
 */
function initializeDragAndDrop(listItems) {
    /** @type {HTMLAnchorElement[]} */
    const items = listItems;

    let dragStartClientY;
    let draggedItem;

    /**
     * @param {HTMLElement} target 
     */
    const addBorder = (target) => {
        if (target !== draggedItem) {
            target.classList.add('border', 'border-2', 'border-primary');
        }
    }

    /**
     * @param {HTMLElement} target 
     */
    const removeBorder = (target) => {
        if (target !== draggedItem) {
            target.classList.remove('border', 'border-2', 'border-primary');
        }
    }

    /**
     * Gère le début du glisser-déposer
     *
     * @param {DragEvent} event Événement de drag start
     */
    function handleDragStart(event) {
        /** @type {HTMLAnchorElement} */
        const target = event.target;

        target.style.opacity = 0.5;

        draggedItem = target;
        dragStartClientY = event.clientY;
    }

    /**
     * Gère l'entrée dans une zone de drop
     *
     * @param {DragEvent} event Événement de drag enter
     */
    function handleDragEnter(event) {
        addBorder(event.target);
    }

    /**
     * Gère la sortie d'une zone de drop
     *
     * @param {DragEvent} event Événement de drag leave
     */
    function handleDragLeave(event) {
        removeBorder(event.target);
    }

    /**
     * Gère le survol d'une zone de drop
     *
     * @param {DragEvent} event Événement de drag over
     */
    function handleDragOver(event) {
        event.preventDefault();
    }

    /**
     * Gère la fin du glisser-déposer
     *
     * @param {DragEvent} event Événement de drag end
     */
    function handleDragEnd(event) {
        /** @type {HTMLAnchorElement} */
        const target = event.target;

        target.classList.add('bg-primary', 'text-white');
        target.style.opacity = 1;

        setTimeout(() => {
            target.classList.remove('bg-primary', 'text-white');
        }, 500);
    }

    /**
     * Gère le dépôt d'un élément
     *
     * @param {DragEvent} event Événement de drop
     */
    function handleDrop(event) {
        event.preventDefault();
        
        /** @type {HTMLAnchorElement} */
        const target = event.target;

        if (dragStartClientY > event.clientY) {
            target.parentNode.insertBefore(draggedItem, target.previousSibling);
        } else {
            target.parentNode.insertBefore(draggedItem, target.nextSibling);
        }

        removeBorder(target);

        draggedItem = undefined;
    }

    // Activer le tri des éléments (liste)
    items.forEach(item => {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragenter', handleDragEnter);
        item.addEventListener('dragleave', handleDragLeave);
        item.addEventListener('dragover', handleDragOver);
        item.addEventListener('dragend', handleDragEnd);
        item.addEventListener('drop', handleDrop);
    });
}

/**
 * Supprime un fichier avec confirmation
 *
 * @param {number} fileId Identifiant du fichier à supprimer
 */
function deleteFile(fileId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')) {
        fetch('/Transfert/delete_file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'file_id=' + fileId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Supprimer la ligne du tableau
                document.querySelector(`tr[data-file-id="${fileId}"]`).remove();
            } else {
                alert('Erreur lors de la suppression : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors de la suppression');
        });
    }
}

/**
 * Affiche une notification temporaire à l'utilisateur
 *
 * @param {string} message Message à afficher
 * @param {string} type Type de notification ('success', 'error', 'info')
 * @param {number} duration Durée d'affichage en millisecondes
 */
function showNotification(message, type = 'info', duration = 3000) {
    // ...existing code...
}

/**
 * Valide un formulaire avant soumission
 *
 * @param {HTMLFormElement} form Formulaire à valider
 *
 * @return {boolean} True si formulaire valide
 */
function validateForm(form) {
    // ...existing code...
}

// Initialiser les fonctionnalités drag & drop sur les éléments de liste
initializeDragAndDrop(document.querySelectorAll('.list-group-item'));