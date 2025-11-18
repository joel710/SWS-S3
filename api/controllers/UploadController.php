<?php
// api/controllers/UploadController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../services/AuthService.php';
include_once __DIR__ . '/../services/UploadService.php';

class UploadController {
    private $db;
    private $authService;
    private $uploadService;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->authService = new AuthService($this->db);
        $this->uploadService = new UploadService($this->db);
    }

    public function upload() {
        $project = $this->authService->validateApiKey();
        if (!$project) {
            return;
        }

        if (!isset($_FILES['file']) || !isset($_POST['bucket'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing file or bucket parameter."]);
            return;
        }

        $project_id = $project['id'];
        $bucket_name = $_POST['bucket'];
        $file = $_FILES['file'];

        $this->uploadService->upload($project_id, $bucket_name, $file);
    }
}
