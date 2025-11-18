<?php
// api/services/AuthService.php

class AuthService {
    private $conn;
    private $table_name = "projects";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function validateApiKey() {
        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(["message" => "Authorization header missing."]);
            return false;
        }

        $auth_header = $headers['Authorization'];
        $arr = explode(" ", $auth_header);
        $token = $arr[1];

        if (!$token) {
            http_response_code(401);
            echo json_encode(["message" => "API key missing."]);
            return false;
        }

        $query = "SELECT id FROM " . $this->table_name . " WHERE api_key = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $token);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        http_response_code(401);
        echo json_encode(["message" => "Invalid API key."]);
        return false;
    }
}
