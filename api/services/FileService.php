<?php
// api/services/FileService.php

class FileService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function serveFile($bucket_name, $filename, $token, $expires) {
        $object = $this->getObjectData($bucket_name, $filename);

        if (!$object) {
            http_response_code(404);
            echo json_encode(["message" => "File not found."]);
            return;
        }

        if ($object['is_public']) {
            $this->streamFile($object);
            return;
        }

        if (time() > $expires) {
            http_response_code(401);
            echo json_encode(["message" => "URL has expired."]);
            return;
        }

        $project_api_key = $this->getProjectApiKey($object['project_id']);
        $expected_token = hash_hmac('sha256', $bucket_name . $filename . $expires, $project_api_key);

        if (hash_equals($expected_token, $token)) {
            $this->streamFile($object);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid token."]);
        }
    }

    private function getObjectData($bucket_name, $filename) {
        $query = "SELECT o.*, b.is_public, b.project_id FROM objects o JOIN buckets b ON o.bucket_id = b.id WHERE b.name = :bucket_name AND o.filename = :filename";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':bucket_name', $bucket_name);
        $stmt->bindParam(':filename', $filename);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
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

    private function streamFile($object) {
        $filepath = $object['path'];
        if (file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $object['mime_type']);
            header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(["message" => "File not found on server."]);
        }
    }
}
