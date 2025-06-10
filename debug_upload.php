<?php
echo "=== DEBUG UPLOAD PROBLEM ===\n\n";

// Test de connexion à la base de données
try {
    $pdo = new PDO('mysql:host=db;dbname=telelec;charset=utf8', 'telelecuser', 'userpassword');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion BDD réussie\n\n";
    
    // Voir les derniers fichiers uploadés
    $stmt = $pdo->query("SELECT id, filename, upload_date FROM files ORDER BY id DESC LIMIT 10");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📁 Derniers fichiers en base :\n";
    foreach ($files as $file) {
        echo "  - ID: {$file['id']}, Nom: '{$file['filename']}', Date: {$file['upload_date']}\n";
    }
    echo "\n";
    
    // Tester la fonction sanitizeFilename
    require_once 'includes/file_utils.php';
    
    $testNames = [
        'Facture N°24.pdf',
        'Document test.docx',
        'Fichier avec espaces.txt'
    ];
    
    echo "🔧 Test de sanitizeFilename :\n";
    foreach ($testNames as $name) {
        $sanitized = sanitizeFilename($name);
        echo "  - Original: '{$name}' -> Sanitized: '{$sanitized}'\n";
    }
    echo "\n";
    
    // Vérifier les fichiers physiques
    $uploadDir = __DIR__ . '/uploads/';
    if (is_dir($uploadDir)) {
        $physicalFiles = scandir($uploadDir);
        $physicalFiles = array_filter($physicalFiles, function($file) {
            return !in_array($file, ['.', '..']);
        });
        
        echo "📂 Fichiers physiques dans /uploads :\n";
        foreach ($physicalFiles as $file) {
            echo "  - '{$file}'\n";
        }
    } else {
        echo "❌ Répertoire /uploads introuvable\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur BDD: " . $e->getMessage() . "\n";
}
?>