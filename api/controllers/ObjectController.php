<?php
// api/controllers/ObjectController.php

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../services/AuthService.php';
include_once __DIR__ . '/../services/ObjectService.php';

class ObjectController {
    private $db;
    private $authService;
    private $objectService;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->authService = new AuthService($this->db);
        $this->objectService = new ObjectService($this->db);
    }

    public function get() {
        $project = $this->authService->validateApiKey();
        if (!$project) return;

        if (!isset($_GET['bucket']) || !isset($_GET['file'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing bucket or file parameter."]);
            return;
        }

        $this->objectService->getObject($project['id'], $_GET['bucket'], $_GET['file']);
    }

    public function delete() {
        $project = $this->authService->validateApiKey();
        if (!$project) return;

        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->bucket) || !isset($data->file)) {
            http_response_code(400);
            echo json_encode(["message" => "Missing bucket or file parameter."]);
            return;
        }

        $this->objectService->deleteObject($project['id'], $data->bucket, $data->file);
    }

    public function list() {
        $project = $this->authService->validateApiKey();
        if (!$project) return;

        if (!isset($_GET['bucket'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing bucket parameter."]);
            return;
        }

        $this->objectService->listObjects($project['id'], $_GET['bucket']);
    }
}
