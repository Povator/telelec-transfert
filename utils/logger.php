<?php
class FileLogger {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

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

    private function getCity($ip) {
        $apiUrl = "http://ip-api.com/json/" . $ip;
        $response = @file_get_contents($apiUrl);
        if ($response) {
            $data = json_decode($response, true);
            return ($data && $data['status'] === 'success') ? $data['city'] : 'Unknown';
        }
        return 'Unknown';
    }
}