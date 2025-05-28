<?php
function scanFile($filepath) {
    // Liste de signatures malveillantes connues
    $maliciousPatterns = [
        '/(<\?php|<\?)/i',  // Code PHP
        '/(system|exec|shell_exec|passthru|eval|assert)/i',  // Fonctions dangereuses
        '/(base64_decode|gzinflate|str_rot13)/i',  // Encodage suspect
        '/(\$_GET|\$_POST|\$_REQUEST|\$_FILES|\$_COOKIE|\$_SERVER)/i'  // Variables super globales
    ];

    // Lire le contenu du fichier
    $content = file_get_contents($filepath);
    
    // Vérifier chaque pattern
    foreach ($maliciousPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return ['clean' => false, 'reason' => 'Contenu malveillant détecté'];
        }
    }

    return ['clean' => true];
}
?>
