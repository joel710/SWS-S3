<?php
// api/services/UploadService.php
include_once __DIR__ . '/../config/config.php';
include_once __DIR__ . '/../services/UrlSigningService.php';

class UploadService {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function upload($project_id, $bucket_name, $file) {
        // 1. Validate bucket
        $bucket_id = $this->getBucketId($project_id, $bucket_name);
        if (!$bucket_id) {
            http_response_code(400);
            echo json_encode(["message" => "Bucket not found or access denied."]);
            return false;
        }

        // 2. File validation
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["message" => "File upload error."]);
            return false;
        }

        // Security checks
        if (!in_array($file['type'], ALLOWED_MIME_TYPES)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid file type."]);
            return false;
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            http_response_code(400);
            echo json_encode(["message" => "File size exceeds the limit of 5MB."]);
            return false;
        }

        // 3. Prepare storage path
        $project_dir = "storage/" . $project_id;
        $bucket_dir = $project_dir . "/" . $bucket_name;
        if (!is_dir($bucket_dir)) {
            mkdir($bucket_dir, 0777, true);
        }

        // 4. Generate unique filename
        $filename = $file['name'];
        $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_extension;
        $target_path = $bucket_dir . "/" . $new_filename;

        // 5. Move file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            http_response_code(500);
            echo json_encode(["message" => "Failed to move uploaded file."]);
            return false;
        }

        // 6. Save to database
        $this->saveObjectData($bucket_id, $new_filename, $target_path, $file['type'], $file['size']);

        // 7. Return success response
        $urlSigningService = new UrlSigningService($this->conn);
        // Generate a URL that is valid for a long time.
        $url = $urlSigningService->generateSignedUrl($project_id, $bucket_name, $new_filename, 31536000); // 1 year

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "url" => $url
        ]);

        return true;
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

    private function saveObjectData($bucket_id, $filename, $path, $mime_type, $size) {
        $query = "INSERT INTO objects (bucket_id, filename, path, mime_type, size) VALUES (:bucket_id, :filename, :path, :mime_type, :size)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':bucket_id', $bucket_id);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':path', $path);
        $stmt->bindParam(':mime_type', $mime_type);
        $stmt->bindParam(':size', $size);

        $stmt->execute();
    }
}
