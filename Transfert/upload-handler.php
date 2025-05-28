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

    // D'abord essayer avec le nom original
    $finalName = $baseName . $extension;
    $targetFile = $targetDir . $finalName;

    // Si le fichier existe déjà, alors utiliser un compteur
    $counter = 1;
    while (file_exists($targetFile)) {
        $finalName = $baseName . '_' . $counter . $extension;
        $targetFile = $targetDir . $finalName;
        $counter++;
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