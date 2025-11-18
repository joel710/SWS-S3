<?php
// api/controllers/FileController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../services/FileService.php';

class FileController {
    private $db;
    private $fileService;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->fileService = new FileService($this->db);
    }

    public function serve() {
        if (!isset($_GET['bucket']) || !isset($_GET['file']) || !isset($_GET['token']) || !isset($_GET['expires'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing required parameters."]);
            return;
        }

        $bucket = $_GET['bucket'];
        $file = $_GET['file'];
        $token = $_GET['token'];
        $expires = $_GET['expires'];

        $this->fileService->serveFile($bucket, $file, $token, $expires);
    }
}
