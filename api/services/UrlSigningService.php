<?php
// api/services/UrlSigningService.php

class UrlSigningService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function generateSignedUrl($project_id, $bucket_name, $filename, $expires) {
        $project_api_key = $this->getProjectApiKey($project_id);
        if (!$project_api_key) {
            return null;
        }

        $expires_timestamp = time() + $expires;
        $token = hash_hmac('sha256', $bucket_name . $filename . $expires_timestamp, $project_api_key);

        $url = sprintf(
            "%s://%s/api/get-file?bucket=%s&file=%s&token=%s&expires=%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http",
            $_SERVER['HTTP_HOST'],
            urlencode($bucket_name),
            urlencode($filename),
            $token,
            $expires_timestamp
        );

        return $url;
    }

    private function getProjectApiKey($project_id) {
        $query = "SELECT api_key FROM projects WHERE id = :project_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $project_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['api_key'];
        }
        return null;
    }
}
