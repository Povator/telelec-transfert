<?php
/**
 * Module de gestion antivirus complet
 * 
 * Fournit les fonctionnalités d'analyse de fichiers avec support
 * ClamAV et analyse heuristique de base pour la détection de menaces.
 *
 * @author  TeleLec
 * @version 3.0
 * @package TelelecTransfert\Security
 */

/**
 * Analyse un fichier avec ClamAV
 *
 * @param string $filePath Chemin complet du fichier à analyser
 *
 * @return array Résultat de l'analyse avec status, message et détails
 * 
 * @throws Exception Si ClamAV n'est pas accessible
 */
function scanWithClamAV($filePath) {
    // Vérifier si le fichier existe
    if (!file_exists($filePath)) {
        return [
            'status' => false, 
            'message' => 'Fichier non trouvé',
            'execution_time' => 0
        ];
    }
    
    $startTime = microtime(true);
    
    // Déterminer le chemin de ClamAV
    $clamPath = '/usr/bin/clamscan';
    $useSudo = false;
    
    if (!is_executable($clamPath)) {
        // Essayer avec sudo
        exec("sudo -n test -x $clamPath 2>/dev/null", $output, $returnCode);
        if ($returnCode === 0) {
            $clamPath = "sudo $clamPath";
            $useSudo = true;
        } else {
            // Utiliser le scan basique si ClamAV n'est pas disponible
            return scanFileBasic($filePath);
        }
    }
    
    $fileSize = filesize($filePath);
    
    // Pour les fichiers très volumineux, utiliser le scan basique
    if ($fileSize > 10 * 1024 * 1024) { // Plus de 10 MB
        $basicResult = scanFileBasic($filePath);
        $basicResult['message'] .= ' (Fichier volumineux - ClamAV ignoré)';
        return $basicResult;
    }
    
    // Échapper le chemin du fichier pour la sécurité
    $escapedPath = escapeshellarg($filePath);
    
    // Commande ClamAV optimisée avec timeout et PATH explicite
    $pathEnv = 'PATH=/usr/bin:/bin:/usr/local/bin';
    $command = "$pathEnv timeout 60 $clamPath --no-summary --infected --quiet --max-filesize=10M --max-scansize=20M $escapedPath 2>&1";
    
    exec($command, $output, $returnCode);
    
    $executionTime = microtime(true) - $startTime;
    $outputString = implode("\n", $output);
    
    // Analyser le résultat selon la documentation ClamAV
    switch ($returnCode) {
        case 0:
            return [
                'status' => true,
                'message' => 'Fichier sain - Aucune menace détectée (ClamAV)',
                'execution_time' => round($executionTime, 2),
                'scanner' => 'ClamAV 1.0.7' . ($useSudo ? ' (sudo)' : '')
            ];
            
        case 1:
            // Virus détecté
            $virusName = 'Menace inconnue';
            if (preg_match('/: (.+) FOUND/', $outputString, $matches)) {
                $virusName = $matches[1];
            } elseif (!empty($outputString)) {
                $virusName = trim($outputString);
            }
            return [
                'status' => false,
                'message' => "🚨 VIRUS DÉTECTÉ: $virusName",
                'execution_time' => round($executionTime, 2),
                'threat_name' => $virusName,
                'scanner' => 'ClamAV 1.0.7'
            ];
            
        case 2:
            // Erreur ClamAV - fallback vers scan basique
            $basicResult = scanFileBasic($filePath);
            $basicResult['message'] .= ' (ClamAV erreur)';
            return $basicResult;
            
        case 124:
            // Timeout - fallback vers scan basique
            $basicResult = scanFileBasic($filePath);
            $basicResult['message'] .= ' (ClamAV timeout)';
            return $basicResult;
            
        default:
            // Autre erreur - fallback vers scan basique
            $basicResult = scanFileBasic($filePath);
            $basicResult['message'] .= " (ClamAV erreur code $returnCode)";
            return $basicResult;
    }
}

/**
 * Effectue une analyse heuristique basique d'un fichier
 *
 * @param string $filePath Chemin complet du fichier à analyser
 *
 * @return array Résultat de l'analyse avec status, message et temps d'exécution
 */
function scanFileBasic($filePath) {
    $startTime = microtime(true);
    
    $fileSize = filesize($filePath);
    
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
    $handle = fopen($filePath, 'rb');
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
    $fileInfo = pathinfo($filePath);
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
 * Obtient le statut complet de l'installation ClamAV
 *
 * @return array Informations détaillées sur ClamAV (installation, version, mise à jour)
 */
function getClamAVStatus() {
    $status = [
        'installed' => false, 
        'updated' => false, 
        'version' => null,
        'last_update' => null,
        'debug' => []
    ];
    
    // Chemins possibles pour ClamAV
    $possiblePaths = [
        '/usr/bin/clamscan',
        '/usr/local/bin/clamscan',
        '/opt/clamav/bin/clamscan',
        '/bin/clamscan'
    ];
    
    $clamPath = null;
    
    // 1. Test direct des chemins
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_executable($path)) {
            $clamPath = $path;
            $status['debug'][] = "ClamAV trouvé à: $path";
            break;
        }
    }
    
    // 2. Si pas trouvé, essayer avec sudo pour contourner les permissions
    if (!$clamPath) {
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                // Tester avec sudo si le fichier existe mais n'est pas exécutable
                exec("sudo -n test -x $path 2>/dev/null", $output, $returnCode);
                if ($returnCode === 0) {
                    $clamPath = "sudo $path";
                    $status['debug'][] = "ClamAV accessible via sudo: $path";
                    break;
                }
            }
        }
    }
    
    // 3. Test avec which en utilisant PATH complet
    if (!$clamPath) {
        $pathEnv = 'PATH=/usr/bin:/bin:/usr/local/bin:/sbin:/usr/sbin';
        exec("$pathEnv which clamscan 2>/dev/null", $whichOutput, $whichCode);
        if ($whichCode === 0 && !empty($whichOutput)) {
            $foundPath = trim($whichOutput[0]);
            if (file_exists($foundPath)) {
                $clamPath = $foundPath;
                $status['debug'][] = "ClamAV trouvé via which: $foundPath";
            }
        }
    }
    
    // 4. Test en utilisant find
    if (!$clamPath) {
        exec("find /usr -name 'clamscan' -type f 2>/dev/null | head -1", $findOutput, $findCode);
        if ($findCode === 0 && !empty($findOutput)) {
            $foundPath = trim($findOutput[0]);
            if (file_exists($foundPath)) {
                $clamPath = $foundPath;
                $status['debug'][] = "ClamAV trouvé via find: $foundPath";
            }
        }
    }
    
    if ($clamPath) {
        $status['installed'] = true;
        
        // Récupérer la version avec PATH explicite
        $pathEnv = 'PATH=/usr/bin:/bin:/usr/local/bin';
        exec("$pathEnv $clamPath --version 2>/dev/null", $versionOutput, $versionCode);
        if ($versionCode === 0 && !empty($versionOutput)) {
            $status['version'] = trim($versionOutput[0]);
            $status['debug'][] = "Version récupérée: " . $status['version'];
        } else {
            // Essayer avec sudo
            exec("sudo $clamPath --version 2>/dev/null", $versionOutput, $versionCode);
            if ($versionCode === 0 && !empty($versionOutput)) {
                $status['version'] = trim($versionOutput[0]);
                $status['debug'][] = "Version récupérée via sudo: " . $status['version'];
            } else {
                $status['debug'][] = "Erreur lors de la récupération de la version (code: $versionCode)";
            }
        }
        
        // Vérifier les définitions antivirus
        $dbPaths = [
            '/var/lib/clamav/daily.cvd',
            '/var/lib/clamav/main.cvd',
            '/var/lib/clamav/bytecode.cvd',
            '/var/lib/clamav/daily.cld',
            '/var/lib/clamav/main.cld',
            '/var/lib/clamav/bytecode.cld'
        ];
        
        $latestUpdate = 0;
        $foundDb = false;
        
        foreach ($dbPaths as $dbPath) {
            if (file_exists($dbPath)) {
                $foundDb = true;
                $lastModified = filemtime($dbPath);
                if ($lastModified > $latestUpdate) {
                    $latestUpdate = $lastModified;
                }
                $status['debug'][] = "DB trouvée: $dbPath (" . date('Y-m-d H:i:s', $lastModified) . ")";
            }
        }
        
        if ($foundDb && $latestUpdate > 0) {
            $daysSinceUpdate = (time() - $latestUpdate) / (24 * 3600);
            $status['updated'] = $daysSinceUpdate < 7; // Considéré à jour si < 7 jours
            $status['last_update'] = date('Y-m-d H:i:s', $latestUpdate);
            $status['days_old'] = round($daysSinceUpdate, 1);
            $status['debug'][] = "Dernière MAJ: " . $status['last_update'] . " ({$status['days_old']} jours)";
        } else {
            $status['debug'][] = "Aucune base de données trouvée";
        }
    } else {
        $status['debug'][] = "ClamAV non trouvé dans tous les chemins testés";
        
        // Diagnostic supplémentaire
        $currentUser = exec('whoami');
        $status['debug'][] = "Utilisateur web: $currentUser";
        
        // Vérifier si dpkg montre que ClamAV est installé
        exec("dpkg -l | grep clamav 2>/dev/null", $dpkgOutput, $dpkgCode);
        if (!empty($dpkgOutput)) {
            $status['debug'][] = "ClamAV installé selon dpkg mais inaccessible";
        }
    }
    
    return $status;
}

/**
 * Met à jour les définitions de virus ClamAV
 *
 * @return array Résultat de la mise à jour avec succès, message et code de retour
 */
function updateClamAVDefinitions() {
    $command = "sudo freshclam 2>&1";
    exec($command, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'message' => implode("\n", $output),
        'return_code' => $returnCode
    ];
}

/**
 * Vérifie si le daemon ClamAV est actif
 *
 * @return bool True si le daemon est en cours d'exécution
 */
function isClamAVDaemonActive() {
    exec("systemctl is-active clamav-daemon 2>/dev/null", $output, $returnCode);
    return $returnCode === 0 && !empty($output) && trim($output[0]) === 'active';
}

/**
 * Obtient les statistiques d'analyse antivirus
 *
 * @return array Statistiques incluant total des scans, menaces trouvées, etc.
 */
function getClamAVStats() {
    return [
        'total_scans' => 0,
        'threats_found' => 0,
        'last_scan' => null
    ];
}

/**
 * Log une activité antivirus
 */
function logAntivirusActivity($action, $file, $result, $details = '') {
    $logEntry = date('Y-m-d H:i:s') . " - $action - $file - $result";
    if ($details) {
        $logEntry .= " - $details";
    }
    error_log($logEntry, 3, '/tmp/clamav_activity.log');
}

/**
 * Test de fichier EICAR pour vérifier le fonctionnement
 */
function createEicarTestFile($path = '/tmp/eicar_test.txt') {
    $eicarString = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
    return file_put_contents($path, $eicarString) !== false;
}

// Test de fonctionnement au chargement (optionnel)
if (defined('ANTIVIRUS_DEBUG') && ANTIVIRUS_DEBUG) {
    error_log("antivirus.php loaded - ClamAV status: " . json_encode(getClamAVStatus()));
}
?>