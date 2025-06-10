<?php
require_once '../includes/auth.php';
require_once '../includes/antivirus.php';
require_once '../includes/db.php';

// Vérifier l'authentification admin
checkAdminAuth();

// Récupérer le statut de ClamAV
$clamavStatus = getClamAVStatus();

// Récupérer les statistiques des analyses
$stats = [];
try {
    $pdo = getDbConnection();
    
    // Total des fichiers
    $stmt = $pdo->query("SELECT COUNT(*) FROM files WHERE deleted = 0");
    $stats['total'] = $stmt->fetchColumn();
    
    // Fichiers sains
    $stmt = $pdo->query("SELECT COUNT(*) FROM files WHERE (antivirus_status = 'true' OR antivirus_status = '1') AND deleted = 0");
    $stats['clean'] = $stmt->fetchColumn();
    
    // Fichiers avec avertissement
    $stmt = $pdo->query("SELECT COUNT(*) FROM files WHERE antivirus_status = 'warning' AND deleted = 0");
    $stats['warning'] = $stmt->fetchColumn();
    
    // Fichiers infectés
    $stmt = $pdo->query("SELECT COUNT(*) FROM files WHERE (antivirus_status = 'false' OR antivirus_status = '0') AND deleted = 0");
    $stats['virus'] = $stmt->fetchColumn();
    
    // Derniers virus détectés
    $stmt = $pdo->query("SELECT id, filename, upload_date, antivirus_message FROM files 
                         WHERE (antivirus_status = 'false' OR antivirus_status = '0') AND deleted = 0 
                         ORDER BY upload_date DESC LIMIT 5");
    $latestViruses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}

// Inclure l'en-tête
include '../includes/admin_header.php';
?>

<div class="container my-4">
    <h1>Statut Antivirus</h1>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Statut ClamAV</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Installé :</strong> <?= $clamavStatus['installed'] ? 'Oui' : 'Non' ?></p>
                    <p><strong>Version :</strong> <?= $clamavStatus['version'] ?? 'Inconnue' ?></p>
                </div>
                <div class="col-md-6">
                    <p>
                        <strong>Définitions à jour :</strong> 
                        <?php if ($clamavStatus['updated']): ?>
                            <span class="text-success">Oui</span>
                        <?php else: ?>
                            <span class="text-danger">Non</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Dernière mise à jour :</strong> <?= $clamavStatus['last_update'] ?? 'Inconnue' ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total des fichiers</h5>
                    <p class="card-text display-4"><?= $stats['total'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Fichiers sains</h5>
                    <p class="card-text display-4"><?= $stats['clean'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body text-center">
                    <h5 class="card-title">Avertissements</h5>
                    <p class="card-text display-4"><?= $stats['warning'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Virus détectés</h5>
                    <p class="card-text display-4"><?= $stats['virus'] ?? 0 ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($latestViruses)): ?>
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Derniers virus détectés</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fichier</th>
                            <th>Date</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestViruses as $virus): ?>
                        <tr>
                            <td><?= $virus['id'] ?></td>
                            <td><?= htmlspecialchars($virus['filename']) ?></td>
                            <td><?= $virus['upload_date'] ?></td>
                            <td><?= htmlspecialchars($virus['antivirus_message']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Test d'antivirus manuel</h5>
        </div>
        <div class="card-body">
            <form action="scan_file.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="fileToScan" class="form-label">Sélectionner un fichier</label>
                    <input class="form-control" type="file" id="fileToScan" name="fileToScan" required>
                </div>
                <button type="submit" class="btn btn-primary">Analyser</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>