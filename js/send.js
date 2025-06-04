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
                        if (response.status === 'success') {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Erreur lors de l\'upload'));
                        }
                    } catch (e) {
                        reject(new Error('Réponse du serveur invalide'));
                    }
                } else {
                    reject(new Error('Problème de connexion au serveur'));
                }
            };
            
            xhr.onerror = () => reject(new Error('Erreur réseau'));
            
            xhr.open('POST', 'upload-handler.php', true);
            xhr.send(formData);
        });

        if (result.status === 'success') {
            // Afficher un message temporaire pendant la finalisation
            uploadResult.innerHTML = `
                <h3>⏳ Finalisation en cours...</h3>
                <p>Veuillez patienter pendant la génération des codes de sécurité</p>
            `;

            // Appel de finalize-upload.php pour générer le code A2F
            const finalizeResponse = await fetch('Transfert/finalize-upload.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `filename=${encodeURIComponent(result.filename)}`
            });

            if (!finalizeResponse.ok) {
                throw new Error('Erreur lors de la finalisation');
            }

            const finalData = await finalizeResponse.json();
            console.log("Réponse de finalisation:", finalData); // Débogage

            if (finalData.success) {
                progressBar.style.backgroundColor = "#4CAF50";
                uploadResult.innerHTML = `
                    <div class="upload-success">
                        <h3>✅ Transfert réussi!</h3>
                        
                        <div class="file-details">
                            <p><strong>📄 Fichier :</strong> ${result.original}</p>
                            <p><strong>📦 Taille :</strong> ${formatFileSize(selectedFile.size)}</p>
                            <p><strong>📆 Date d'envoi :</strong> ${new Date().toLocaleString()}</p>
                        </div>

                        <div class="download-info">
                            <div class="info-block">
                                <div class="info-label">
                                    <strong>🔑 Code de téléchargement :</strong>
                                    <button onclick="copyToClipboard('${finalData.code}')" class="copy-btn" title="Copier">📋</button>
                                </div>
                                <div class="info-value">${finalData.code}</div>
                            </div>
                            
                            <div class="info-block">
                                <div class="info-label">
                                    <strong>🔒 Code A2F :</strong>
                                    <button onclick="copyToClipboard('${finalData.auth_code}')" class="copy-btn" title="Copier">📋</button>
                                </div>
                                <div class="info-value code-a2f">${finalData.auth_code}</div>
                            </div>
                            
                            <div class="info-block">
                                <strong>⏱️ Expiration :</strong> ${new Date(finalData.expiration_date).toLocaleString()}
                            </div>
                        </div>

                        <div class="download-link-container">
                            <a href="${finalData.url}" target="_blank" class="download-btn">
                                <span>🔗 Lien de téléchargement</span>
                            </a>
                        </div>

                        <div class="share-instructions">
                            <p>📱 <strong>Comment partager :</strong> Envoyez le lien de téléchargement et le code A2F au destinataire via des canaux différents pour plus de sécurité.</p>
                        </div>
                    </div>
                `;

                // Ajouter la fonction pour copier dans le presse-papier
                window.copyToClipboard = function(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Copié dans le presse-papier !');
                    }).catch(err => {
                        console.error('Erreur lors de la copie :', err);
                    });
                };
            } else {
                throw new Error(finalData.error || 'Erreur lors de la finalisation');
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