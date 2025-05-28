const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileToUpload');
const form = document.getElementById('uploadForm');
const fileInfo = document.getElementById('fileInfo');
const progressBar = document.querySelector('.progress-bar');
const progressText = document.querySelector('.progress-text');
const speedText = document.querySelector('.speed-text');
const timeText = document.querySelector('.time-text');
const uploadResult = document.getElementById('uploadResult');

// Empêcher le comportement par défaut pour le drag & drop
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// Gérer l'apparence de la zone de drop
dropZone.addEventListener('dragenter', () => dropZone.classList.add('dragover'));
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

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!fileInput.files.length) {
        alert('Veuillez sélectionner un fichier');
        return;
    }

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('fileToUpload', file);

    uploadResult.innerHTML = '<h3>⏳ Upload en cours...</h3>';
    
    let startTime = Date.now();
    let lastLoaded = 0;

    try {
        // Créer une promesse pour gérer XMLHttpRequest
        const result = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = ((e.loaded / e.total) * 100).toFixed(2);
                    progressBar.style.width = percentComplete + '%';
                    progressText.textContent = percentComplete + '%';

                    const currentTime = Date.now();
                    const elapsedTime = (currentTime - startTime) / 1000;
                    const bytesPerSecond = e.loaded / elapsedTime;
                    const speedMBps = (bytesPerSecond / (1024 * 1024)).toFixed(2);
                    speedText.textContent = `Vitesse : ${speedMBps} MB/s`;

                    const remainingBytes = e.total - e.loaded;
                    const remainingTime = remainingBytes / bytesPerSecond;
                    const minutes = Math.floor(remainingTime / 60);
                    const seconds = Math.floor(remainingTime % 60);
                    timeText.textContent = `Temps restant : ${minutes}:${seconds.toString().padStart(2, '0')}`;

                    lastLoaded = e.loaded;
                }
            });

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        console.log("Réponse brute:", xhr.responseText); // Ajoutez cette ligne
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        reject(new Error('Réponse invalide du serveur'));
                    }
                } else {
                    reject(new Error(`Erreur serveur: ${xhr.status}`));
                }
            };

            xhr.onerror = () => reject(new Error('Erreur réseau'));
            
            // Modifier cette ligne
            xhr.open('POST', '/Transfert/upload-handler.php', true);
            xhr.send(formData);
        });

        if (result.status === 'success') {
            uploadResult.innerHTML = `
                <h3>⏳ Traitement du fichier en cours...</h3>
                <p>Veuillez patienter quelques instants</p>
            `;

            // Modifier cette ligne également
            const finalizeResponse = await fetch('/Transfert/finalize-upload.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `filename=${encodeURIComponent(result.filename)}`
            });

            const finalData = await finalizeResponse.json();

            if (finalData.success) {
                progressBar.style.backgroundColor = "#4CAF50";
                uploadResult.innerHTML = `
                    <h3>✅ Fichier bien envoyé : ${result.original}</h3>
                    <p>Code de téléchargement : ${finalData.code}</p>
                    <p>Code d'authentification (A2F) : ${finalData.auth_code}</p>
                    <p>Ce code A2F expire dans 5 jours</p>
                    <a href="${finalData.url}" target="_blank">${finalData.url}</a>
                `;
            } else {
                throw new Error('Erreur lors de la finalisation');
            }
        } else {
            throw new Error(result.message || 'Erreur lors de l\'upload');
        }
    } catch (error) {
        console.error('Erreur:', error);
        uploadResult.innerHTML = `<h3>❌ Erreur : ${error.message}</h3>`;
        progressBar.style.backgroundColor = "#e74c3c";
    }
});