<?php
/**
 * Outil de diagnostic ClamAV
 * 
 * Effectue un diagnostic complet de l'installation ClamAV
 * avec suggestions de résolution des problèmes.
 *
 * @author  TeleLec
 * @version 1.2
 * @requires Session admin active
 */

/**
 * Affiche une sortie formatée pour le diagnostic
 *
 * @param string $message Message à afficher
 */
function output($message) {
    global $isCLI;
    if ($isCLI) {
        echo strip_tags($message) . "\n";
    } else {
        echo $message;
    }
}

// Détection si on est en mode CLI ou web
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    session_start();
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        header('Location: /admin/login.php');
        exit;
    }
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug ClamAV</title></head><body>";
}

output("<h1>🔍 Diagnostic ClamAV</h1>");

// Test 1: Vérifier si ClamAV est installé
output("<h2>1. Test d'installation</h2>");

// Test which
exec("which clamscan 2>/dev/null", $whichOutput, $whichCode);
output("which clamscan - Code retour: $whichCode<br>");
output("Sortie: " . (empty($whichOutput) ? "VIDE" : implode("<br>", $whichOutput)) . "<br><br>");

// Test whereis
exec("whereis clamscan 2>/dev/null", $whereisOutput, $whereisCode);
output("whereis clamscan - Code retour: $whereisCode<br>");
output("Sortie: " . (empty($whereisOutput) ? "VIDE" : implode("<br>", $whereisOutput)) . "<br><br>");

// Test direct des chemins
output("<h3>Test des chemins directs:</h3>");
$possiblePaths = ['/usr/bin/clamscan', '/usr/local/bin/clamscan', '/opt/clamav/bin/clamscan'];
foreach ($possiblePaths as $path) {
    $exists = file_exists($path);
    $executable = is_executable($path);
    output("$path - Existe: " . ($exists ? "OUI" : "NON") . " - Exécutable: " . ($executable ? "OUI" : "NON") . "<br>");
}

// Test 2: Version
output("<h2>2. Test de version</h2>");
exec("clamscan --version 2>&1", $versionOutput, $versionCode);
output("clamscan --version - Code retour: $versionCode<br>");
output("Sortie: " . (empty($versionOutput) ? "VIDE" : implode("<br>", $versionOutput)) . "<br><br>");

// Test 3: Chemins des définitions
output("<h2>3. Test des définitions antivirus</h2>");
$dbPaths = [
    '/var/lib/clamav/daily.cvd',
    '/var/lib/clamav/daily.cld', 
    '/var/lib/clamav/main.cvd',
    '/var/lib/clamav/main.cld',
    '/var/lib/clamav/bytecode.cvd',
    '/var/lib/clamav/bytecode.cld',
    '/usr/local/share/clamav/',
    '/opt/clamav/share/'
];

foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        output("✅ $path EXISTE<br>");
        if (is_file($path)) {
            $size = filesize($path);
            $modified = filemtime($path);
            output("   Dernière modification: " . date('Y-m-d H:i:s', $modified) . "<br>");
            output("   Taille: " . number_format($size) . " bytes<br>");
            output("   Age: " . round((time() - $modified) / 86400, 1) . " jours<br>");
        }
    } else {
        output("❌ $path N'EXISTE PAS<br>");
    }
}

// Test 4: Permissions et utilisateur
output("<h2>4. Test des permissions</h2>");
$webUser = exec('whoami');
output("Utilisateur actuel: $webUser<br>");

// Test des permissions sur les différents chemins
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $owner = posix_getpwuid(fileowner($path))['name'] ?? 'inconnu';
        $group = posix_getgrgid(filegroup($path))['name'] ?? 'inconnu';
        output("$path - Permissions: $perms - Propriétaire: $owner:$group<br>");
    }
}

// Test 5: Variables d'environnement
output("<h2>5. Variables d'environnement</h2>");
$path = getenv('PATH') ?: $_ENV['PATH'] ?? $_SERVER['PATH'] ?? 'NON DÉFINI';
output("PATH: $path<br>");

// Séparer et analyser le PATH
$pathDirs = explode(':', $path);
output("Répertoires dans PATH contenant 'clam*':<br>");
foreach ($pathDirs as $dir) {
    if (is_dir($dir)) {
        $clamFiles = glob($dir . '/clam*');
        if (!empty($clamFiles)) {
            foreach ($clamFiles as $file) {
                output("   Trouvé: $file<br>");
            }
        }
    }
}

// Test 6: Installation des paquets
output("<h2>6. Test des paquets installés</h2>");

// Test avec dpkg
exec("dpkg -l | grep clam 2>/dev/null", $dpkgOutput, $dpkgCode);
if (!empty($dpkgOutput)) {
    output("Paquets ClamAV installés (dpkg):<br>");
    foreach ($dpkgOutput as $line) {
        output("   $line<br>");
    }
} else {
    output("Aucun paquet ClamAV trouvé avec dpkg<br>");
}

// Test avec which
exec("which clamav-config 2>/dev/null", $configOutput, $configCode);
if ($configCode === 0 && !empty($configOutput)) {
    output("clamav-config trouvé: " . implode(' ', $configOutput) . "<br>");
    
    exec("clamav-config --version 2>/dev/null", $configVersion, $configVersionCode);
    if ($configVersionCode === 0 && !empty($configVersion)) {
        output("Version via clamav-config: " . implode(' ', $configVersion) . "<br>");
    }
}

// Test 7: Services
output("<h2>7. Test des services ClamAV</h2>");

exec("systemctl is-active clamav-daemon 2>/dev/null", $daemonStatus, $daemonCode);
output("Service clamav-daemon: " . (empty($daemonStatus) ? "NON INSTALLÉ" : implode(' ', $daemonStatus)) . "<br>");

exec("systemctl is-active clamav-freshclam 2>/dev/null", $freshclamStatus, $freshclamCode);
output("Service clamav-freshclam: " . (empty($freshclamStatus) ? "NON INSTALLÉ" : implode(' ', $freshclamStatus)) . "<br>");

// Test 8: Test de la fonction getClamAVStatus()
output("<h2>8. Test de la fonction getClamAVStatus()</h2>");
if (file_exists('../includes/antivirus.php')) {
    require_once '../includes/antivirus.php';
    $status = getClamAVStatus();
    output("<pre>");
    output(print_r($status, true));
    output("</pre>");
} else {
    output("❌ Fichier antivirus.php non trouvé<br>");
}

// Test 9: Suggestions d'installation
output("<h2>9. Suggestions</h2>");

if ($versionCode !== 0) {
    output("❌ ClamAV ne semble pas installé ou accessible<br>");
    output("<h3>Pour installer ClamAV :</h3>");
    output("sudo apt update<br>");
    output("sudo apt install clamav clamav-daemon clamav-freshclam<br>");
    output("sudo freshclam<br>");
    output("sudo systemctl enable clamav-daemon<br>");
    output("sudo systemctl start clamav-daemon<br>");
} else {
    output("✅ ClamAV semble être installé et fonctionnel<br>");
}

// Test 10: Test rapide de scan
output("<h2>10. Test rapide de scan</h2>");
if ($versionCode === 0) {
    // Créer un fichier de test temporaire
    $testFile = '/tmp/test_clamav_' . uniqid() . '.txt';
    file_put_contents($testFile, "Test file for ClamAV");
    
    exec("clamscan $testFile 2>&1", $scanOutput, $scanCode);
    output("Test de scan sur fichier temporaire:<br>");
    output("Code retour: $scanCode<br>");
    output("Sortie: " . implode("<br>", $scanOutput) . "<br>");
    
    // Nettoyer
    unlink($testFile);
} else {
    output("Impossible de tester le scan car ClamAV n'est pas accessible<br>");
}

if (!$isCLI) {
    echo "</body></html>";
}
?>