const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileToUpload');
const form = document.getElementById('uploadForm');
const fileInfo = document.getElementById('fileInfo');
const progressBar = document.querySelector('.progress-bar');
const progressText = document.querySelector('.progress-text');
const speedText = document.querySelector('.speed-text');
const timeText = document.querySelector('.time-text');
const uploadResult = document.getElementById('uploadResult');

let selectedFile = null;

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
dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    
    const file = e.dataTransfer.files[0];
    handleFile(file);
});
dropZone.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', function(e) {
    if (this.files.length > 0) {
        handleFile(this.files[0]);
    }
});

function handleFile(file) {
    selectedFile = file;
    const fileInfo = document.getElementById('fileInfo');
    fileInfo.innerHTML = `
        <div>
            <strong>Fichier sélectionné :</strong> ${file.name} (${formatFileSize(file.size)})
        </div>
        <button type="button" class="remove-file" onclick="removeFile()">
            Retirer le fichier
        </button>
    `;
    fileInfo.style.display = 'flex';
    document.querySelector('button[type="submit"]').disabled = false;
}

function removeFile() {
    selectedFile = null;
    const fileInfo = document.getElementById('fileInfo');
    fileInfo.style.display = 'none';
    fileInfo.innerHTML = '';
    document.querySelector('button[type="submit"]').disabled = true;
}

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!selectedFile) {
        alert('Veuillez sélectionner un fichier');
        return;
    }

    const file = selectedFile;
    const formData = new FormData();
    formData.append('fileToUpload', file);

    uploadResult.innerHTML = '<h3>⏳ Upload en cours...</h3>';
    
    let startTime = Date.now();
    let lastLoaded = 0;

    try {
        // Utiliser XMLHttpRequest au lieu de fetch pour avoir accès aux événements de progression
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
                }
            });

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
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
            
            xhr.open('POST', 'upload-handler.php', true);
            xhr.send(formData);
        });

        if (result.status === 'success') {
            uploadResult.innerHTML = `
                <h3>⏳ Traitement du fichier en cours...</h3>
                <p>Veuillez patienter quelques instants</p>
            `;

            const finalizeResponse = await fetch('finalize-upload.php', {
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
                    <p>Code A2F : ${finalData.auth_code}</p>
                    <p>Date d'expiration du code A2F : ${finalData.expiration_date}</p>
                    <a href="${finalData.url}" target="_blank">Lien de téléchargement</a>
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

function formatFileSize(size) {
    const i = Math.floor(Math.log(size) / Math.log(1024));
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    return (size / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
}