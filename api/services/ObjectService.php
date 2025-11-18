<?php
// api/services/ObjectService.php

class ObjectService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getObject($project_id, $bucket_name, $filename) {
        $object = $this->getObjectData($project_id, $bucket_name, $filename);
        if (!$object) {
            http_response_code(404);
            echo json_encode(["message" => "File not found or access denied."]);
            return;
        }

        $filepath = $object['path'];
        if (file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $object['mime_type']);
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
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

    public function deleteObject($project_id, $bucket_name, $filename) {
        $object = $this->getObjectData($project_id, $bucket_name, $filename);
        if (!$object) {
            http_response_code(404);
            echo json_encode(["message" => "File not found or access denied."]);
            return;
        }

        // Delete from storage
        unlink($object['path']);

        // Delete from database
        $this->deleteObjectData($object['id']);

        http_response_code(200);
        echo json_encode(["message" => "File deleted successfully."]);
    }

    public function listObjects($project_id, $bucket_name) {
        $bucket_id = $this->getBucketId($project_id, $bucket_name);
        if (!$bucket_id) {
            http_response_code(400);
            echo json_encode(["message" => "Bucket not found or access denied."]);
            return;
        }

        $query = "SELECT filename, size, created_at, mime_type FROM objects WHERE bucket_id = :bucket_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':bucket_id', $bucket_id);
        $stmt->execute();

        $objects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($objects);
    }

    private function getObjectData($project_id, $bucket_name, $filename) {
        $query = "SELECT o.* FROM objects o JOIN buckets b ON o.bucket_id = b.id WHERE b.project_id = :project_id AND b.name = :bucket_name AND o.filename = :filename";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':project_id', $project_id);
        $stmt->bindParam(':bucket_name', $bucket_name);
        $stmt->bindParam(':filename', $filename);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    private function deleteObjectData($object_id) {
        $query = "DELETE FROM objects WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $object_id);
        $stmt->execute();
    }

    private function getBucketId($project_id, $bucket_name) {
        $query = "SELECT id FROM buckets WHERE project_id = :project_id AND name = :bucket_name";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':project_id', $project_id);
        $stmt->bindParam(':bucket_name', $bucket_name);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['id'];
        }
        return false;
    }
}
