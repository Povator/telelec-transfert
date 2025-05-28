<?php
class FileLogger {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function log($fileId, $actionType, $status = 'success', $details = '') {
        try {
            $sql = "INSERT INTO file_logs (file_id, action_type, user_ip, user_agent, city, status, details) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $userIp = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $city = $this->getCity($userIp);

            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                $fileId,
                $actionType,
                $userIp,
                $userAgent,
                $city,
                $status,
                $details
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