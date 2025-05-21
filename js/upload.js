const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileToUpload');
const form = document.getElementById('uploadForm');
const fileInfo = document.getElementById('fileInfo');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, e => {
        e.preventDefault();
        e.stopPropagation();
    });
});

dropZone.addEventListener('dragover', () => dropZone.classList.add('dragover'));
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', handleDrop);
dropZone.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', () => updateFileInfo(fileInput.files));

function updateFileInfo(files) {
    if (files.length > 0) {
        const file = files[0];
        fileInfo.style.display = 'block';
        fileInfo.innerHTML = `Fichier sélectionné: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        dropZone.style.borderColor = '#4CAF50';
    } else {
        fileInfo.style.display = 'none';
        dropZone.style.borderColor = '#ED501C';
    }
}

function handleDrop(e) {
    const files = e.dataTransfer.files;
    fileInput.files = files;
    updateFileInfo(files);
}

form.addEventListener('submit', handleSubmit);

function handleSubmit(e) {
    // ... Le reste du code JavaScript existant ...
}