<?php
// api/controllers/UrlSigningController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../services/AuthService.php';
include_once __DIR__ . '/../services/UrlSigningService.php';

class UrlSigningController {
    private $db;
    private $authService;
    private $urlSigningService;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->authService = new AuthService($this->db);
        $this->urlSigningService = new UrlSigningService($this->db);
    }

    public function generate() {
        $project = $this->authService->validateApiKey();
        if (!$project) {
            return;
        }

        if (!isset($_POST['bucket']) || !isset($_POST['file']) || !isset($_POST['expires'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing bucket, file, or expires parameter."]);
            return;
        }

        $project_id = $project['id'];
        $bucket_name = $_POST['bucket'];
        $file_name = $_POST['file'];
        $expires = $_POST['expires'];

        $url = $this->urlSigningService->generateSignedUrl($project_id, $bucket_name, $file_name, $expires);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "url" => $url
        ]);
    }
}
