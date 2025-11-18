<?php
// api/index.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Basic request routing logic
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path); // Adjust path to be relative to /api

// Simple routing based on the path
switch ($path) {
    case '/upload':
        if ($method == 'POST') {
            include_once __DIR__ . '/controllers/UploadController.php';
            $controller = new UploadController();
            $controller->upload();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case '/object':
        include_once __DIR__ . '/controllers/ObjectController.php';
        $controller = new ObjectController();
        if ($method == 'GET') {
            $controller->get();
        } elseif ($method == 'DELETE') {
            $controller->delete();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case '/list':
        if ($method == 'GET') {
            include_once __DIR__ . '/controllers/ObjectController.php';
            $controller = new ObjectController();
            $controller->list();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case '/generate-signed-url':
        if ($method == 'POST') {
            include_once __DIR__ . '/controllers/UrlSigningController.php';
            $controller = new UrlSigningController();
            $controller->generate();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    case '/get-file':
        if ($method == 'GET') {
            include_once __DIR__ . '/controllers/FileController.php';
            $controller = new FileController();
            $controller->serve();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found."]);
        break;
}
