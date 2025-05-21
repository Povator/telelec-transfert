<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

function sanitizeFilename($filename) {
    return preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($filename));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetDir = __DIR__ . "/../uploads/";

    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    if (!isset($_FILES["fileToUpload"])) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun fichier reçu']);
        exit;
    }

    $originalName = $_FILES["fileToUpload"]["name"];
    $safeName = sanitizeFilename($originalName);

    $fileInfo = pathinfo($safeName);
    $baseName = $fileInfo['filename'];
    $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';

    $counter = 1;
    $finalName = $baseName . '_' . $counter . $extension;
    $targetFile = $targetDir . $finalName;

    while (file_exists($targetFile)) {
        $counter++;
        $finalName = $baseName . '_' . $counter . $extension;
        $targetFile = $targetDir . $finalName;
    }

    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
        echo json_encode([
            'status' => 'success',
            'filename' => $finalName,
            'original' => $originalName
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'upload']);
    }
    exit;
}
?>
<script>
  // Dans send.php, modifiez cette partie :
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        const result = JSON.parse(xhr.responseText);
        if (result.status === 'success') {
          // Ajouter un console.log pour déboguer
          console.log('Réponse upload:', result);

          progressBar.style.backgroundColor = "#FFA500";
          progressText.textContent = "Finalisation...";
          progressBar.style.width = '99%';

          document.getElementById('uploadResult').innerHTML = `
            <h3>⏳ Traitement du fichier en cours...</h3>
            <p>Veuillez patienter quelques instants</p>
          `;

          fetch('finalize-upload.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `filename=${encodeURIComponent(result.filename)}`
          })
          .then(response => response.json())
          .then(data => {
            // Ajouter un console.log pour déboguer
            console.log('Réponse finale:', data);
            
            if (data.success) {
              progressBar.style.width = '100%';
              progressBar.style.backgroundColor = "#4CAF50";
              progressText.textContent = "Terminé !";

              document.getElementById('uploadResult').innerHTML = `
                <h3>✅ Fichier bien envoyé : ${result.original}</h3>
                <p>Code de téléchargement : ${data.code}</p>
                <a href="${data.url}" target="_blank">${data.url}</a>
              `;
            } else {
              throw new Error('Erreur lors de la finalisation de l\'upload');
            }
          })
          .catch(finalizeError => {
            console.error(finalizeError);
            progressText.textContent = "Erreur lors de la finalisation";
            progressBar.style.backgroundColor = "red";
          });
        } else {
          throw new Error('Erreur lors de l\'upload du fichier');
        }
      } catch (e) {
        console.error('Erreur de parsing JSON:', e);
        document.getElementById('uploadResult').innerHTML = `
          <h3>❌ Erreur lors de l'upload du fichier</h3>
          <p>${e.message}</p>
        `;
      }
    } else {
      document.getElementById('uploadResult').innerHTML = `
        <h3>❌ Erreur lors de l'upload du fichier</h3>
        <p>Statut : ${xhr.status}</p>
      `;
    }
    uploadButton.disabled = false;
  };
</script>