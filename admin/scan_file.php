<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once '../includes/antivirus.php';

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToScan'])) {
    $uploadedFile = $_FILES['fileToScan'];
    
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $tempPath = $uploadedFile['tmp_name'];
        $originalName = $uploadedFile['name'];
        
        // Analyser le fichier
        $result = scanFile($tempPath);
        $result['filename'] = $originalName;
        $result['size'] = filesize($tempPath);
        
        // Supprimer le fichier temporaire
        unlink($tempPath);
    } else {
        $error = "Erreur lors de l'upload du fichier.";
    }
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