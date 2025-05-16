<!DOCTYPE html>
<html lang="fr">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Transfert Tetelec</title>
      <link rel="stylesheet" href="/style.css">
      <style>
        #dropZone {
          border: 2px dashed #ED501C;
          padding: 50px;
          text-align: center;
          cursor: pointer;
          background-color: #f9f9f9;
          transition: background-color 0.2s ease;
          margin-top: 30px;
        }

        #dropZone.dragover {
          background-color: #ffeae3;
        }

        #uploadForm input[type="submit"] {
          margin-top: 20px;
        }

        #fileInfo {
          margin-top: 10px;
          padding: 10px;
          display: none;
          background-color: #e8f5e9;
          border-radius: 4px;
          text-align: center;
        }

        #uploadProgress {
          display: none;
          margin: 20px 0;
        }

        .progress-info {
          width: 100%;
          background-color: #f0f0f0;
          border-radius: 4px;
          overflow: hidden;
        }
        
        .progress-bar {
          height: 20px;
          background-color: #4CAF50;
          transition: width 0.3s;
          border-radius: 4px;
        }
        
        .progress-text, .speed-text, .time-text {
          margin: 5px 0;
          text-align: center;
          font-size: 14px;
        }
      </style>
  </head>
  <body>
    <?php include '../Present/header.php'; ?>

    <main>
      <h2>Glissez un fichier ici ou cliquez pour le sélectionner</h2>
      
      <form id="uploadForm" action="upload-handler.php" method="post" enctype="multipart/form-data">
        <div id="dropZone" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">
          <p>Déposez un fichier ici</p>
          <input type="file" name="fileToUpload" id="fileToUpload" hidden>
        </div>
        <div id="fileInfo"></div>
        <div id="uploadProgress" style="display:none; margin: 20px 0;">
          <div class="progress-info">
            <div class="progress-bar" style="width: 0%; height: 20px; background-color: #4CAF50; transition: width 0.3s;"></div>
          </div>
          <p class="progress-text">0%</p>
          <p class="speed-text">Vitesse : -- MB/s</p>
          <p class="time-text">Temps restant : --:--</p>
        </div>
        <div id="uploadResult"></div>
        <button type="submit">Envoyer le fichier</button>
      </form>    
    </main>

    <?php include '../Present/footer.php'; ?>

    <script>
      const dropZone = document.getElementById('dropZone');
      const fileInput = document.getElementById('fileToUpload');
      const form = document.getElementById('uploadForm');
      const fileInfo = document.getElementById('fileInfo');

      // Empêcher le comportement par défaut pour tous les événements drag
      ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
      });

      function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
      }

      // Gérer les effets visuels
      ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
      });

      ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
      });

      function highlight(e) {
        dropZone.classList.add('dragover');
      }

      function unhighlight(e) {
        dropZone.classList.remove('dragover');
      }

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

      // Gérer le drop
      dropZone.addEventListener('drop', handleDrop, false);

      function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;

        fileInput.files = files;
        updateFileInfo(files);
        // Ne pas soumettre automatiquement pour permettre à l'utilisateur de vérifier
        // form.submit();
      }

      dropZone.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', () => {
        if(fileInput.files.length > 0) {
          updateFileInfo(fileInput.files);
        }
      });

      form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!fileInput.files.length) {
          alert('Veuillez sélectionner un fichier');
          return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('fileToUpload', file);

        const xhr = new XMLHttpRequest();
        const progressDiv = document.getElementById('uploadProgress');
        const progressBar = progressDiv.querySelector('.progress-bar');
        const progressText = progressDiv.querySelector('.progress-text');
        const speedText = progressDiv.querySelector('.speed-text');
        const timeText = progressDiv.querySelector('.time-text');

        progressDiv.style.display = 'block';
        let startTime = Date.now();
        let lastLoaded = 0;
        let lastTime = startTime;

        xhr.upload.addEventListener('progress', function(e) {
          if (e.lengthComputable) {
            // Calcul du pourcentage
            const percent = (e.loaded / e.total) * 100;
            progressBar.style.width = percent + '%';
            progressText.textContent = Math.round(percent) + '%';

            // Calcul de la vitesse
            const currentTime = Date.now();
            const timeInterval = (currentTime - lastTime) / 1000; // en secondes
            const loadInterval = e.loaded - lastLoaded;
            const speed = loadInterval / timeInterval / (1024 * 1024); // en MB/s
            speedText.textContent = `Vitesse : ${speed.toFixed(2)} MB/s`;

            // Calcul du temps restant
            const remainingBytes = e.total - e.loaded;
            const timeRemaining = remainingBytes / (loadInterval / timeInterval);
            const minutes = Math.floor(timeRemaining / (1024 * 1024) / 60);
            const seconds = Math.floor((timeRemaining / (1024 * 1024)) % 60);
            timeText.textContent = `Temps restant : ${minutes}:${seconds.toString().padStart(2, '0')}`;

            lastLoaded = e.loaded;
            lastTime = currentTime;
          }
        });

        xhr.onload = function() {
          if (xhr.status === 200) {
            progressDiv.style.display = 'none';
            const serverResponse = document.getElementById('uploadResult');
            serverResponse.innerHTML = xhr.responseText;
            // Réinitialiser le formulaire
            form.reset();
            fileInfo.style.display = 'none';
            dropZone.style.borderColor = '#ED501C';
          } else {
            alert('Une erreur est survenue lors de l\'upload');
          }
        };

        xhr.onerror = function() {
          alert('Une erreur est survenue lors de l\'upload');
        };

        xhr.open('POST', 'uploads.php', true);
        xhr.send(formData);
      });
    </script>
  </body>
</html>