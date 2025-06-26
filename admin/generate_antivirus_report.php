<?php
session_start();

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';

try {
    // Récupérer les données antivirus
    $stmt = $pdo->prepare("
        SELECT 
            f.nom_fichier,
            f.taille_fichier,
            f.date_upload,
            a.resultat_scan,
            a.details_menace,
            a.date_scan,
            a.moteur_antivirus
        FROM fichiers f 
        LEFT JOIN antivirus_scans a ON f.id = a.fichier_id 
        ORDER BY f.date_upload DESC
    ");
    $stmt->execute();
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Générer le rapport
    $filename = 'rapport_antivirus_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    
    // En-têtes CSV
    fputcsv($output, [
        'Nom du fichier',
        'Taille',
        'Date upload',
        'Résultat scan',
        'Détails menace',
        'Date scan',
        'Moteur antivirus'
    ]);

    // Données
    foreach ($scans as $scan) {
        fputcsv($output, [
            $scan['nom_fichier'],
            formatFileSize($scan['taille_fichier']),
            $scan['date_upload'],
            $scan['resultat_scan'] ?: 'Non scanné',
            $scan['details_menace'] ?: '-',
            $scan['date_scan'] ?: '-',
            $scan['moteur_antivirus'] ?: '-'
        ]);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("Erreur génération rapport: " . $e->getMessage());
    die("Erreur lors de la génération du rapport.");
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>