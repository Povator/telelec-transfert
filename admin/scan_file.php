<?php
/**
 * Interface de scan manuel de fichiers
 * 
 * Permet aux administrateurs de tester l'analyse antivirus
 * sur des fichiers uploadés manuellement.
 *
 * @author  TeleLec
 * @version 1.3
 * @requires Session admin active
 */

/**
 * Traite l'upload et lance l'analyse d'un fichier
 *
 * @param array $uploadedFile Données du fichier uploadé ($_FILES)
 *
 * @return array Résultat de l'analyse avec détails
 *
 * @throws Exception Si erreur lors du traitement
 */
function processFileUploadAndScan($uploadedFile) {
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $tempPath = $uploadedFile['tmp_name'];
        $originalName = $uploadedFile['name'];
        
        // Analyser le fichier
        $result = scanFile($tempPath);
        $result['filename'] = $originalName;
        $result['size'] = filesize($tempPath);
        
        // Supprimer le fichier temporaire
        cleanupTemporaryFile($tempPath);
        
        return $result;
    } else {
        throw new Exception("Erreur lors de l'upload du fichier.");
    }
}

/**
 * Valide le fichier uploadé avant analyse
 *
 * @param array $uploadedFile Données du fichier uploadé
 *
 * @return bool True si fichier valide
 *
 * @throws Exception Si fichier invalide ou erreur d'upload
 */
function validateUploadedFile($uploadedFile) {
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $maxFileSize = 2 * 1024 * 1024; // 2 Mo

    if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
        throw new Exception("Type de fichier non autorisé.");
    }

    if ($uploadedFile['size'] > $maxFileSize) {
        throw new Exception("Le fichier est trop volumineux.");
    }

    return true;
}

/**
 * Nettoie les fichiers temporaires après analyse
 *
 * @param string $tempPath Chemin du fichier temporaire
 *
 * @return bool True si nettoyage réussi
 */
function cleanupTemporaryFile($tempPath) {
    return unlink($tempPath);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat du scan - TeleLec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="admin.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/favicon/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/favicon/favicon.png" type="image/png">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <h1>🧪 Résultat du scan antivirus</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <h3>❌ Erreur</h3>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($result): ?>
            <div class="scan-result">
                <div class="result-header">
                    <h2>📄 Fichier analysé : <?= htmlspecialchars($result['filename']) ?></h2>
                    <p>Taille : <?= number_format($result['size']) ?> bytes</p>
                </div>
                
                <div class="result-status">
                    <?php if ($result['status'] === true): ?>
                        <div class="alert alert-success">
                            <h3>✅ Fichier sain</h3>
                            <p><?= htmlspecialchars($result['message']) ?></p>
                        </div>
                    <?php elseif ($result['status'] === false): ?>
                        <div class="alert alert-danger">
                            <h3>🦠 Menace détectée</h3>
                            <p><?= htmlspecialchars($result['message']) ?></p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h3>⚠️ Avertissement</h3>
                            <p><?= htmlspecialchars($result['message']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="scan-details">
                    <p><strong>Temps d'exécution :</strong> <?= $result['execution_time'] ?? 'N/A' ?> secondes</p>
                    <p><strong>Date du scan :</strong> <?= date('d/m/Y H:i:s') ?></p>
                    <p><strong>Administrateur :</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="/admin/antivirus_details.php" class="btn btn-primary">
                ← Retour aux détails antivirus
            </a>
            <a href="/admin/dashboard.php" class="btn btn-secondary">
                📊 Retour au dashboard
            </a>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
    
    <style>
    .scan-result {
        background: white;
        border-radius: 8px;
        padding: 30px;
        margin: 20px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .result-header {
        border-bottom: 2px solid #dee2e6;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .result-header h2 {
        color: #495057;
        margin-bottom: 5px;
    }
    
    .scan-details {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 6px;
        margin-top: 20px;
    }
    
    .scan-details p {
        margin: 5px 0;
        color: #6c757d;
    }
    
    .actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }
    
    .actions .btn {
        padding: 12px 20px;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background-color: #007bff;
        color: white;
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    </style>
</body>
</html>