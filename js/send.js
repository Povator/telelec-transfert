/**
 * Gestionnaire d'upload côté client avec interface drag & drop
 * 
 * Gère l'upload de fichiers avec barre de progression, validation
 * et suivi du statut d'analyse antivirus en temps réel.
 *
 * @author  TeleLec
 * @version 2.1
 */

/**
 * Formate une taille de fichier en octets vers une unité lisible
 *
 * @param {number} size Taille en octets
 * @returns {string} Taille formatée avec unité appropriée
 */
function formatFileSize(size) {
    const i = Math.floor(Math.log(size) / Math.log(1024));
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    return (size / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
}

/**
 * Vérifie le statut d'analyse antivirus d'un fichier
 *
 * @param {number} fileId Identifiant unique du fichier
 */
function checkScanStatus(fileId) {
    const scanStatusElement = document.getElementById('scanStatus');
    let attempts = 0;
    const maxAttempts = 60; // 2 minutes max
    
    const checkInterval = setInterval(() => {
        attempts++;
        
        fetch(`check-scan-status.php?file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Erreur API:', data.error);
                return;
            }
            
            console.log('Statut scan:', data); // Debug
            
            if (data.status === 'pending') {
                scanStatusElement.textContent = `🔍 Analyse en cours... (${attempts * 2}s)`;
                
                if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    scanStatusElement.textContent = '⚠️ Analyse prenant plus de temps que prévu...';
                    showFinalizeButton(data.filename);
                }
            } else {
                // Scan terminé
                clearInterval(checkInterval);
                
                if (data.status === 'true' || data.status === 'warning') {
                    scanStatusElement.innerHTML = `
                        <span style="color: green;">✅ Analyse terminée : ${data.message}</span>
                    `;
                    // Finaliser après 1 seconde
                    setTimeout(() => proceedToFinalization({filename: data.filename}), 1000);
                } else {
                    scanStatusElement.innerHTML = `
                        <span style="color: red;">❌ ${data.message}</span>
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Erreur vérification scan:', error);
            // Continue à essayer même en cas d'erreur
        });
    }, 2000); // Vérifier toutes les 2 secondes
}

/**
 * Procède à la finalisation de l'upload après analyse
 *
 * @param {Object} result Résultat de l'upload contenant les métadonnées
 */
function proceedToFinalization(result) {
    uploadResult.innerHTML = `
        <h3>⏳ Finalisation en cours...</h3>
        <p>Génération des codes de sécurité...</p>
    `;
    
    const formData = new FormData();
    formData.append('filename', result.filename);
    
    fetch('finalize-upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Affichage du résultat final SANS le code A2F
            uploadResult.innerHTML = `
                <div class="upload-success">
                    <h3>✅ Fichier envoyé avec succès !</h3>
                    
                    <div class="file-details">
                        <div class="info-block">
                            <div class="info-label">
                                📄 <strong>Fichier :</strong>
                            </div>
                            <div class="info-value">${selectedFile.name}</div>
                        </div>
                        
                        <div class="info-block">
                            <div class="info-label">
                                🔗 <strong>Lien de téléchargement :</strong>
                            </div>
                            <div class="info-value">${window.location.origin}${data.url}</div>
                        </div>
                    </div>
                    
                    <div class="download-link-container">
                        <button type="button" onclick="copyDownloadLink('${window.location.origin}${data.url}'); return false;" class="download-btn">
                            📋 Copier le lien de téléchargement
                        </button>
                    </div>
                    
                    <div class="share-instructions">
                        <strong>🔐 Instructions de partage sécurisé :</strong><br>
                        1. Cliquez sur le bouton ci-dessus pour <strong>copier le lien</strong><br>
                        2. Envoyez le lien au destinataire par votre canal habituel<br>
                        3. Le <strong>code A2F</strong> sera fourni séparément par l'administrateur<br>
                        4. Contactez l'administrateur pour obtenir le code d'authentification<br>
                        5. Le fichier sera automatiquement supprimé après ${Math.ceil((new Date(data.expiration_date) - new Date()) / (1000 * 60 * 60 * 24))} jours
                    </div>
                    
                    <div class="admin-notice" style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin-top: 20px;">
                        <strong>⚠️ Important :</strong> Pour des raisons de sécurité, le code A2F n'est accessible que dans le dashboard administrateur. 
                        L'administrateur devra le communiquer au destinataire par un canal séparé.
                    </div>
                </div>
            `;
        } else {
            uploadResult.innerHTML = `<h3>❌ Erreur lors de la finalisation : ${data.error}</h3>`;
        }
    })
    .catch(error => {
        console.error('Erreur finalisation:', error);
        uploadResult.innerHTML = `<h3>❌ Erreur lors de la finalisation</h3>`;
    });
}

/**
 * Copie un lien de téléchargement dans le presse-papiers
 *
 * @param {string} url URL de téléchargement à copier
 */
function copyDownloadLink(url) {
    // IMPORTANT: Empêcher le comportement par défaut
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Ajouter la classe d'animation
    button.classList.add('copying');
    button.innerHTML = '✅ Lien copié !';
    
    navigator.clipboard.writeText(url).then(() => {
        // Afficher la notification popup
        showCopyNotification('Lien de téléchargement copié dans le presse-papiers !');
        
        // Retourner à l'état normal après 2 secondes
        setTimeout(() => {
            button.classList.remove('copying');
            button.innerHTML = originalText;
        }, 2000);
        
    }).catch(() => {
        // Fallback pour les navigateurs plus anciens
        const textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        textArea.setSelectionRange(0, 99999);
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Afficher la notification même en fallback
        showCopyNotification('Lien de téléchargement copié !');
        
        // Retourner à l'état normal
        setTimeout(() => {
            button.classList.remove('copying');
            button.innerHTML = originalText;
        }, 2000);
    });
}

// Fonction améliorée pour afficher une notification de copie
function showCopyNotification(message) {
    // Supprimer les notifications existantes
    const existingNotifications = document.querySelectorAll('.copy-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Créer la nouvelle notification
    const notification = document.createElement('div');
    notification.className = 'copy-notification slide-in';
    notification.innerHTML = message;
    
    document.body.appendChild(notification);
    
    // Ajouter l'effet de rebond après l'apparition
    setTimeout(() => {
        notification.classList.add('bounce');
    }, 400);
    
    // Supprimer la notification après 3 secondes
    setTimeout(() => {
        notification.classList.remove('slide-in', 'bounce');
        notification.classList.add('slide-out');
        
        // Supprimer l'élément du DOM après l'animation
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileToUpload');
const form = document.getElementById('uploadForm');
const fileInfo = document.getElementById('fileInfo');
const progressBar = document.querySelector('.progress-bar');
const progressText = document.querySelector('.progress-text');
const speedText = document.querySelector('.speed-text');
const timeText = document.querySelector('.time-text');
const uploadResult = document.getElementById('uploadResult');
const cancelBtn = document.getElementById('cancelUploadBtn');

let selectedFile = null;
let currentUpload = null; // <- Nouveau

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

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
    cancelBtn.style.display = 'inline-block'; // 👈 Affiche le bouton

    let startTime = Date.now();

    try {
        const result = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            currentUpload = xhr; // 👈 Stocke la requête pour pouvoir l'annuler

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
                cancelBtn.style.display = 'none';
                if (xhr.status === 200) {
                    console.log('Réponse brute du serveur:', xhr.responseText); // DEBUG
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Réponse parsée:', response); // DEBUG
                        if (response.status === 'success') {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Erreur lors de l\'upload'));
                        }
                    } catch (e) {
                        console.error('Erreur parsing JSON:', e); // DEBUG
                        console.error('Contenu de la réponse:', xhr.responseText); // DEBUG
                        reject(new Error('Réponse du serveur invalide'));
                    }
                } else {
                    console.error('Status HTTP:', xhr.status); // DEBUG
                    reject(new Error('Problème de connexion au serveur'));
                }
            };

            xhr.onerror = () => reject(new Error('Erreur réseau'));
            xhr.onabort = () => {
                uploadResult.innerHTML = `<h3>🛑 Upload annulé par l'utilisateur</h3>`;
                progressBar.style.backgroundColor = "#f39c12";
                progressBar.style.width = "0%";
                progressText.textContent = "0%";
                speedText.textContent = "";
                timeText.textContent = "";
                cancelBtn.style.display = 'none';
                reject(new Error('Upload annulé'));
            };

            xhr.open('POST', 'upload-handler.php', true);
            xhr.send(formData);
        });

        // Dans la section de traitement des résultats, ajouter du debug :
        if (result.status === 'success') {
            console.log('Upload réussi:', result); // Debug
            proceedToFinalization(result);
        } else if (result.status === 'quarantine') {
            console.log('Fichier en quarantaine:', result); // Debug
            // Fichier en quarantaine
            uploadResult.innerHTML = `
                <div class="quarantine-notice">
                    <h3>🔒 Fichier mis en quarantaine</h3>
                    <p>${result.message}</p>
                    <div class="quarantine-info">
                        <p><strong>📋 Que se passe-t-il maintenant ?</strong></p>
                        <ul>
                            <li>Votre fichier a été placé en quarantaine pour vérification</li>
                            <li>Un administrateur va examiner le fichier</li>
                            <li>Vous serez contacté avec la décision finale</li>
                            <li>Cette mesure protège tous les utilisateurs</li>
                        </ul>
                    </div>
                    <div class="contact-admin">
                        <p>🔗 <strong>Besoin d'aide ?</strong> Contactez l'administrateur</p>
                    </div>
                </div>
            `;
        } else if (result.security_alert) {
            console.log('Alerte sécurité:', result); // Debug
            // Virus détecté
            uploadResult.innerHTML = `
                <div class="security-alert">
                    <h3>🚨 Upload bloqué</h3>
                    <p>${result.message}</p>
                    <div class="security-info">
                        <p><strong>⚠️ Pourquoi ce blocage ?</strong></p>
                        <ul>
                            <li>Le fichier contient du contenu potentiellement dangereux</li>
                            <li>Cette protection préserve la sécurité de tous</li>
                            <li>Vérifiez votre fichier avec un antivirus local</li>
                        </ul>
                    </div>
                </div>
            `;
        } else {
            console.error('Erreur upload:', result); // Debug
            throw new Error(result.message || 'Erreur lors de l\'upload');
        }
    } catch (error) {
        if (error.message !== 'Upload annulé') {
            uploadResult.innerHTML = `<h3>❌ Erreur : ${error.message}</h3>`;
            progressBar.style.backgroundColor = "#e74c3c";
        }
        cancelBtn.style.display = 'none'; // 👈 Toujours cacher après
    }
});

cancelBtn.addEventListener('click', () => {
    if (currentUpload) {
        currentUpload.abort(); // 🛑
    }
});