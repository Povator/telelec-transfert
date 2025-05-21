<?php
header('Content-Type: application/json');

// Fonction pour générer un code de téléchargement
function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';

    if (!$filename) {
        echo json_encode(['success' => false, 'error' => 'Nom de fichier manquant']);
        exit;
    }

    try {
        $pdo = new PDO('mysql:host=db;dbname=telelec;charset=utf8', 'telelecuser', 'userpassword');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $downloadCode = generateDownloadCode();

        // Modification de la requête pour inclure le champ 'company'
        $stmt = $pdo->prepare("INSERT INTO files (filename, download_code, company) VALUES (?, ?, 'TeLelec')");
        $stmt->execute([$filename, $downloadCode]);

        echo json_encode([
            'success' => true,
            'code' => $downloadCode,
            'url' => "/download.php?code=" . $downloadCode
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>