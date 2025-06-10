<?php
/**
 * Utilitaires pour la gestion des fichiers
 * 
 * @author  TeleLec
 * @version 1.0
 */

/**
 * Nettoie et sécurise le nom d'un fichier de manière cohérente
 * Cette fonction doit être utilisée partout pour garantir la cohérence
 * 
 * @param string $filename Nom du fichier à nettoyer
 * @return string Nom du fichier nettoyé
 */
function sanitizeFilename($filename) {
    // Récupérer le nom de base et l'extension
    $pathinfo = pathinfo($filename);
    $name = $pathinfo['filename'];
    $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
    
    // Nettoyer le nom de fichier de manière cohérente
    $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    
    // Éviter les underscores multiples
    $cleanName = preg_replace('/_+/', '_', $cleanName);
    
    // Éviter les underscores en début/fin
    $cleanName = trim($cleanName, '_');
    
    // Si le nom devient vide, utiliser un nom par défaut
    if (empty($cleanName)) {
        $cleanName = 'file_' . time();
    }
    
    return $cleanName . $extension;
}

/**
 * Génère un nom de fichier unique en gérant les doublons
 * 
 * @param string $targetDir Répertoire de destination
 * @param string $filename Nom de fichier souhaité
 * @return array ['filename' => string, 'filepath' => string]
 */
function generateUniqueFilename($targetDir, $filename) {
    $safeName = sanitizeFilename($filename);
    $fileInfo = pathinfo($safeName);
    $baseName = $fileInfo['filename'];
    $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
    
    // Gestion des doublons
    $finalName = $baseName . $extension;
    $targetFile = $targetDir . $finalName;
    $counter = 1;
    
    while (file_exists($targetFile)) {
        $finalName = $baseName . '_' . $counter . $extension;
        $targetFile = $targetDir . $finalName;
        $counter++;
    }
    
    return [
        'filename' => $finalName,
        'filepath' => $targetFile
    ];
}
?>