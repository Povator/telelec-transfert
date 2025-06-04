<?php
function scanFile($filepath) {
    // Définition des motifs de code malveillant connus
    $maliciousPatterns = [
        '/(<\?php|<\?)/i',  // Détecte le code PHP
        '/(system|exec|shell_exec|passthru|eval|assert)/i',  // Détecte les fonctions système dangereuses
        '/(base64_decode|gzinflate|str_rot13)/i',  // Détecte les fonctions d'encodage suspectes
        '/(\$_GET|\$_POST|\$_REQUEST|\$_FILES|\$_COOKIE|\$_SERVER)/i'  // Détecte l'utilisation des variables superglobales
    ];

    // Lecture du contenu du fichier
    $content = file_get_contents($filepath);
    
    // Vérifie chaque motif suspect dans le contenu du fichier
    foreach ($maliciousPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return ['clean' => false, 'reason' => 'Contenu malveillant détecté'];
        }
    }

    // Si aucun motif suspect n'est trouvé
    return ['clean' => true];
}
?>
