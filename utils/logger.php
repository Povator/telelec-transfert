<?php
/**
 * Classe de logging avancé pour TeleLec-Transfert
 * 
 * Fournit un système de journalisation complet avec
 * géolocalisation, timestamps et gestion d'erreurs.
 *
 * @author  TeleLec
 * @version 2.0
 */

class FileLogger {
    /**
     * Connexion à la base de données
     * @var PDO
     */
    private $conn;

    /**
     * Constructeur du logger
     *
     * @param PDO $conn Connexion à la base de données
     */
    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Enregistre une activité dans les logs
     *
     * @param int|null $fileId Identifiant du fichier (optionnel)
     * @param string $actionType Type d'action effectuée
     * @param string $status Statut de l'action ('success', 'error', 'warning')
     * @param string $details Détails supplémentaires
     *
     * @return bool True si enregistrement réussi
     *
     * @throws PDOException Si erreur de base de données
     */
    public function log($fileId, $actionType, $status = 'success', $details = '') {
        try {
            // CORRECTION: Définir le fuseau horaire avant toute opération de date
            date_default_timezone_set('Europe/Paris');
            
            $sql = "INSERT INTO file_logs (file_id, action_type, user_ip, user_agent, city, status, details, action_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $userIp = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $city = $this->getCity($userIp);
            $actionDate = date('Y-m-d H:i:s'); // Utiliser PHP au lieu de NOW()

            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                $fileId,
                $actionType,
                $userIp,
                $userAgent,
                $city,
                $status,
                $details,
                $actionDate // Utiliser la date générée en PHP
            ]);
        } catch (Exception $e) {
            error_log("Erreur de logging : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtient la ville à partir d'une adresse IP
     *
     * @param string $ip Adresse IP à géolocaliser
     *
     * @return string Nom de la ville ou 'Inconnue' si échec
     */
    private function getCity($ip) {
        $apiUrl = "http://ip-api.com/json/" . $ip;
        $response = @file_get_contents($apiUrl);
        if ($response) {
            $data = json_decode($response, true);
            return ($data && $data['status'] === 'success') ? $data['city'] : 'Unknown';
        }
        return 'Unknown';
    }

    /**
     * Nettoie les anciens logs selon une politique de rétention
     *
     * @param int $days Nombre de jours à conserver
     *
     * @return int Nombre de logs supprimés
     */
    public function cleanOldLogs($days = 90) {
        try {
            $sql = "DELETE FROM file_logs WHERE action_date < NOW() - INTERVAL ? DAY";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Erreur de nettoyage des logs : " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupère les statistiques de logging
     *
     * @param int $days Période en jours pour les statistiques
     *
     * @return array Statistiques détaillées
     */
    public function getLogStats($days = 30) {
        try {
            $sql = "SELECT action_type, COUNT(*) as count, MIN(action_date) as first_occurrence, MAX(action_date) as last_occurrence
                    FROM file_logs
                    WHERE action_date >= NOW() - INTERVAL ? DAY
                    GROUP BY action_type";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur de récupération des statistiques de logs : " . $e->getMessage());
            return [];
        }
    }
}