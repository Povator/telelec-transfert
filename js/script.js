/** @type {HTMLAnchorElement[]} */
const listItems = document.querySelectorAll('.list-group-item');

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
 * @param {DragEvent} e 
 */
const handleDragStart = (e) => {
    /** @type {HTMLAnchorElement} */
    const target = e.target;

    target.style.opacity = 0.5;

    draggedItem = target;
    dragStartClientY = e.clientY;
}

/**
 * @param {DragEvent} e 
 */
const handleDragEnter = (e) => {
    addBorder(e.target);
}

/**
 * @param {DragEvent} e 
 */
const handleDragLeave = (e) => {
    removeBorder(e.target);
}

/**
 * @param {DragEvent} e 
 */
const handleDragOver = (e) => {
    e.preventDefault();
}

/**
 * @param {DragEvent} e 
 */
const handleDragEnd = (e) => {
    /** @type {HTMLAnchorElement} */
    const target = e.target;

    target.classList.add('bg-primary', 'text-white');
    target.style.opacity = 1;

    setTimeout(() => {
        target.classList.remove('bg-primary', 'text-white');
    }, 500);
}

/**
 * @param {DragEvent} e 
 */
const handleDrop = (e) => {
    e.preventDefault();
    
    /** @type {HTMLAnchorElement} */
    const target = e.target;

    if (dragStartClientY > e.clientY) {
        target.parentNode.insertBefore(draggedItem, target.previousSibling);
    } else {
        target.parentNode.insertBefore(draggedItem, target.nextSibling);
    }

    removeBorder(target);

    draggedItem = undefined;
}

/**
 * Fonction pour supprimer un fichier
 * @param {number} fileId - L'ID du fichier à supprimer
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

// Activer le tri des éléments (liste)
listItems.forEach(listItem => {
    listItem.addEventListener('dragstart', handleDragStart);
    listItem.addEventListener('dragenter', handleDragEnter);
    listItem.addEventListener('dragleave', handleDragLeave);
    listItem.addEventListener('dragover', handleDragOver);
    listItem.addEventListener('dragend', handleDragEnd);
    listItem.addEventListener('drop', handleDrop);
});