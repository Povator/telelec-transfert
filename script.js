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

// Activer le tri des éléments (liste)
listItems.forEach(listItem => {
    listItem.addEventListener('dragstart', handleDragStart);
    listItem.addEventListener('dragenter', handleDragEnter);
    listItem.addEventListener('dragleave', handleDragLeave);
    listItem.addEventListener('dragover', handleDragOver);
    listItem.addEventListener('dragend', handleDragEnd);
    listItem.addEventListener('drop', handleDrop);
});