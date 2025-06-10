<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$host = 'db';
$db = 'telelec';
$user = 'telelecuser';
$pass = 'userpassword';

echo "<h1>Correction des statuts antivirus</h1>";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Mettre à jour les statuts numériques en chaînes
    $updateSql = "UPDATE files 
                 SET antivirus_status = 
                    CASE 
                        WHEN antivirus_status = 1 THEN 'true'
                        WHEN antivirus_status = 0 THEN 'false'
                        ELSE antivirus_status
                    END
                 WHERE antivirus_status IN (0, 1)";
    $stmt = $conn->prepare($updateSql);
    $count = $stmt->execute();
    
    echo "<p>✅ Mise à jour effectuée : {$stmt->rowCount()} statuts convertis.</p>";
    
    // Pour les fichiers sans statut, faire une analyse légère
    $nullSql = "SELECT id, filename FROM files WHERE antivirus_status IS NULL";
    $stmt = $conn->prepare($nullSql);
    $stmt->execute();
    $nullFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updatedCount = 0;
    
    foreach ($nullFiles as $file) {
        $filepath = __DIR__ . '/../uploads/' . $file['filename'];
        
        if (file_exists($filepath)) {
            // Analyse basique du fichier
            $fileInfo = pathinfo($filepath);
            $extension = strtolower($fileInfo['extension'] ?? '');
            
            // Déterminer statut par extension
            $dangerousExtensions = ['exe', 'dll', 'bat', 'cmd', 'sh', 'js', 'vbs', 'ps1'];
            $warningExtensions = ['zip', 'rar', '7z', 'jar', 'php', 'py'];
            
            if (in_array($extension, $dangerousExtensions)) {
                $status = 'false';
                $message = "Extension potentiellement dangereuse (.{$extension})";
            } elseif (in_array($extension, $warningExtensions)) {
                $status = 'warning';
                $message = "Extension nécessitant prudence (.{$extension})";
            } else {
                $status = 'true';
                $message = "Fichier considéré comme sûr (analyse rétrospective)";
            }
            
            // Mettre à jour le statut
            $updateSql = "UPDATE files SET antivirus_status = ?, antivirus_message = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$status, $message, $file['id']]);
            $updatedCount++;
        }
    }
    
    echo "<p>🔄 {$updatedCount} fichiers sans statut ont été analysés rétroactivement.</p>";
    echo "<p><a href='/admin/dashboard.php'>Retourner au tableau de bord</a></p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>