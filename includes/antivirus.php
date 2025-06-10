<?php
/**
 * Système d'analyse antivirus optimisé avec ClamAV
 * 
 * @author  TeleLec
 * @version 2.2
 */

/**
 * Analyse un fichier à la recherche de virus ou malwares
 * 
 * @param string $filepath Chemin vers le fichier à analyser
 * @return array Tableau contenant [status => true/false/warning, message => string]
 */
function scanFile($filepath) {
    // Vérifier si le fichier existe
    if (!file_exists($filepath)) {
        return ['status' => false, 'message' => 'Fichier introuvable'];
    }
    
    // Utiliser ClamAV avec fallback intelligent
    return scanFileWithClamAV($filepath);
}

/**
 * Analyse un fichier avec ClamAV ultra-optimisé
 * 
 * @param string $filepath Chemin vers le fichier à analyser
 * @return array Résultat du scan
 */
function scanFileWithClamAV($filepath) {
    // Vérifier si ClamAV est installé
    exec("which clamscan 2>/dev/null", $output, $returnCode);
    
    if ($returnCode !== 0) {
        return scanFileBasic($filepath);
    }
    
    $fileSize = filesize($filepath);
    
    // Pour les fichiers volumineux, utiliser le scan basique directement
    if ($fileSize > 5 * 1024 * 1024) { // Plus de 5 MB
        return scanFileBasic($filepath);
    }
    
    // Commande ClamAV ultra-optimisée avec timeout très court
    $escapedPath = escapeshellarg($filepath);
    
    // Options pour la vitesse maximale :
    // --no-summary : pas de récapitulatif
    // --stdout : sortie vers stdout
    // --max-filesize=5M : limite stricte de taille
    // --max-scansize=10M : limite de données scannées
    // --max-recursion=2 : récursion minimale
    // --max-dir-recursion=1 : pas de récursion de répertoire
    // --max-files=10 : limite le nombre de fichiers dans les archives
    $command = "timeout 5 clamscan --no-summary --stdout --max-filesize=5M --max-scansize=10M --max-recursion=2 --max-dir-recursion=1 --max-files=10 {$escapedPath} 2>&1";
    
    $startTime = microtime(true);
    exec($command, $scanOutput, $scanCode);
    $executionTime = microtime(true) - $startTime;
    
    // Analyser le résultat
    if ($scanCode === 0) {
        return [
            'status' => true, 
            'message' => 'Aucune menace détectée (ClamAV)',
            'execution_time' => round($executionTime, 2)
        ];
    } elseif ($scanCode === 1) {
        // Virus détecté !
        $virusInfo = implode(' ', $scanOutput);
        return [
            'status' => false, 
            'message' => "🚨 VIRUS DÉTECTÉ par ClamAV: " . $virusInfo,
            'execution_time' => round($executionTime, 2)
        ];
    } else {
        // Timeout ou erreur - utiliser le scan basique amélioré
        $basicResult = scanFileBasic($filepath);
        $basicResult['message'] .= ' (ClamAV indisponible: ' . round($executionTime, 1) . 's)';
        return $basicResult;
    }
}

/**
 * Scan basique de sécurité avec détection EICAR
 * 
 * @param string $filepath Chemin vers le fichier à analyser
 * @return array Résultat du scan
 */
function scanFileBasic($filepath) {
    $startTime = microtime(true);
    
    $fileSize = filesize($filepath);
    
    // Vérifier la taille du fichier
    if ($fileSize < 1) {
        return [
            'status' => false,
            'message' => 'Fichier vide ou corrompu',
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
    }
    
    // Lire le contenu du fichier (limité pour les gros fichiers)
    $bytesToRead = min(32768, $fileSize); // Lire max 32 Ko
    $handle = fopen($filepath, 'rb');
    if (!$handle) {
        return [
            'status' => 'warning',
            'message' => 'Impossible de lire le fichier pour analyse',
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
    }
    
    $content = fread($handle, $bytesToRead);
    fclose($handle);
    
    // DÉTECTION EICAR (fichier de test antivirus standard)
    if (strpos($content, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') !== false) {
        return [
            'status' => false,
            'message' => '🚨 VIRUS DE TEST EICAR DÉTECTÉ',
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
    }
    
    // Extensions dangereuses
    $fileInfo = pathinfo($filepath);
    $extension = strtolower($fileInfo['extension'] ?? '');
    
    $dangerousExtensions = [
        'exe', 'dll', 'bat', 'cmd', 'sh', 'bash', 'zsh',
        'js', 'vbs', 'ps1', 'jar', 'php', 'py', 'pl',
        'scr', 'com', 'pif', 'msi', 'reg', 'app', 'deb', 'rpm'
    ];
    
    if (in_array($extension, $dangerousExtensions)) {
        return [
            'status' => false,
            'message' => "🚨 FICHIER DANGEREUX (extension .{$extension})",
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
    }
    
    // Signatures d'exécutables
    if (substr($content, 0, 2) === 'MZ') {
        return [
            'status' => false,
            'message' => '🚨 EXÉCUTABLE WINDOWS DÉTECTÉ',
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
    }
    
    if (substr($content, 0, 4) === "\x7fELF") {
        return [
            'status' => false,
            'message' => '🚨 EXÉCUTABLE LINUX DÉTECTÉ',
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
    }
    
    // Archives suspectes
    if (substr($content, 0, 4) === "PK\x03\x04") {
        if (strpos($content, 'META-INF') !== false) {
            return [
                'status' => 'warning',
                'message' => '⚠️ Archive Java (JAR) détectée',
                'execution_time' => round(microtime(true) - $startTime, 3)
            ];
        }
    }
    
    // Patterns malveillants dans les scripts
    if (in_array($extension, ['php', 'js', 'py', 'pl', 'sh', 'html', 'htm'])) {
        $maliciousPatterns = [
            '/eval\s*\(/i' => 'Code eval() malveillant',
            '/base64_decode\s*\(/i' => 'Décodage base64 suspect',
            '/system\s*\(/i' => 'Appel système dangereux',
            '/exec\s*\(/i' => 'Exécution de commande',
            '/shell_exec\s*\(/i' => 'Exécution shell',
            '/passthru\s*\(/i' => 'Commande passthru',
            '/proc_open\s*\(/i' => 'Ouverture de processus',
            '/file_get_contents\s*\(\s*["\']https?:\/\//i' => 'Téléchargement distant suspect',
            '/<script[^>]*>.*?(document\.write|eval|unescape)/is' => 'JavaScript malveillant'
        ];
        
        foreach ($maliciousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                return [
                    'status' => false,
                    'message' => "🚨 CODE MALVEILLANT: {$description}",
                    'execution_time' => round(microtime(true) - $startTime, 3)
                ];
            }
        }
    }
    
    // Gros fichiers : analyse limitée
    if ($fileSize > 50 * 1024 * 1024) { // Plus de 50 MB
        return [
            'status' => 'warning',
            'message' => '⚠️ Fichier volumineux (' . round($fileSize / (1024 * 1024), 1) . ' MB), analyse limitée',
            'execution_time' => round(microtime(true) - $startTime, 3)
        ];
    }
    
    // Si aucun problème détecté
    return [
        'status' => true,
        'message' => '✅ Aucune menace détectée (analyse basique)',
        'execution_time' => round(microtime(true) - $startTime, 3)
    ];
}

/**
 * Vérifie le statut de ClamAV sur le système
 * 
 * @return array Informations sur l'état de ClamAV
 */
function getClamAVStatus() {
    $status = ['installed' => false, 'updated' => false, 'version' => null];
    
    // Vérifier si ClamAV est installé
    exec("which clamscan 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0) {
        $status['installed'] = true;
        
        // Récupérer la version
        exec("clamscan --version 2>/dev/null", $versionOutput, $versionCode);
        if ($versionCode === 0 && !empty($versionOutput)) {
            $status['version'] = $versionOutput[0];
        }
        
        // Vérifier si les définitions sont récentes
        $dbPaths = ['/var/lib/clamav/daily.cvd', '/var/lib/clamav/daily.cld'];
        foreach ($dbPaths as $dbPath) {
            if (file_exists($dbPath)) {
                $lastUpdate = filemtime($dbPath);
                $daysSinceUpdate = (time() - $lastUpdate) / (24 * 3600);
                $status['updated'] = $daysSinceUpdate < 7;
                $status['last_update'] = date('Y-m-d H:i:s', $lastUpdate);
                break;
            }
        }
    }
    
    return $status;
}
?>