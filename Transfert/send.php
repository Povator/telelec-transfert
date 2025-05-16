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
      </style>
  </head>
  <body>
    <?php include '../Present/header.php'; ?>

    <main>
      <h2>Glissez un fichier ici ou cliquez pour le sélectionner</h2>
      
      <form id="uploadForm" action="uploads.php" method="post" enctype="multipart/form-data">
        <div id="dropZone" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">
          <p>Déposez un fichier ici</p>
          <input type="file" name="fileToUpload" id="fileToUpload" hidden>
        </div>
        <div id="fileInfo"></div>
        <input type="submit" value="Envoyer le fichier" name="submit">
      </form>    
    </main>

    <?php include '../Present/footer.php'; ?>

    <script>
      const dropZone = document.getElementById('dropZone');
      const fileInput = document.getElementById('fileToUpload');
      const form = document.getElementById('uploadForm');
      const fileInfo = document.getElementById('fileInfo');

      // Empêcher le comportement par défaut
      function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('dragover');
      }

      function updateFileInfo(files) {
        if (files.length > 0) {
          const file = files[0];
          fileInfo.style.display = 'block';
          fileInfo.innerHTML = `Fichier sélectionné: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
          dropZone.style.borderColor = '#4CAF50';
        } else {
          fileInfo.style.display = 'none';
          dropZone.style.borderColor = '#ED501C';  // Changé de #007bff à #ED501C
        }
      }

      function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('dragover');
        
        const dt = e.dataTransfer;
        const files = dt.files;

        fileInput.files = files;
        updateFileInfo(files);
        // Optionnel : soumettre automatiquement
        form.submit();
      }

      dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
      });

      dropZone.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', () => {
        if(fileInput.files.length > 0) {
          updateFileInfo(fileInput.files);
          // Optionnel : soumettre automatiquement
          form.submit();
        }
      });
    </script>
  </body>
</html>