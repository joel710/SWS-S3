<?php
// admin/api.php
// This script will handle admin-related API calls.
include_once 'check_auth.php';
include_once __DIR__ . '/../api/config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->connect();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_projects':
        $query = 'SELECT p.id, p.name, p.api_key, p.created_at, COUNT(b.id) as bucket_count FROM projects p LEFT JOIN buckets b ON p.id = b.project_id GROUP BY p.id';
        $stmt = $db->prepare($query);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'add_project':
        $name = $_POST['name'];
        $api_key = 'sk-' . bin2hex(random_bytes(16)); // Generate a simple API key
        $query = 'INSERT INTO projects (name, api_key) VALUES (:name, :api_key)';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':api_key', $api_key);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Project added.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add project.']);
        }
        break;
    case 'delete_project':
        $id = $_POST['id'];
        $query = 'DELETE FROM projects WHERE id = :id';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Project deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete project.']);
        }
        break;

    case 'get_buckets':
        $project_id = $_GET['project_id'];
        $query = 'SELECT * FROM buckets WHERE project_id = :project_id';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':project_id', $project_id);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'add_bucket':
        $project_id = $_POST['project_id'];
        $name = $_POST['name'];
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $query = 'INSERT INTO buckets (project_id, name, is_public) VALUES (:project_id, :name, :is_public)';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':project_id', $project_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':is_public', $is_public);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Bucket added.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add bucket.']);
        }
        break;

    case 'get_analytics':
        $projects_query = 'SELECT COUNT(*) as total_projects FROM projects';
        $projects_stmt = $db->prepare($projects_query);
        $projects_stmt->execute();
        $total_projects = $projects_stmt->fetch(PDO::FETCH_ASSOC)['total_projects'];

        $buckets_query = 'SELECT COUNT(*) as total_buckets FROM buckets';
        $buckets_stmt = $db->prepare($buckets_query);
        $buckets_stmt->execute();
        $total_buckets = $buckets_stmt->fetch(PDO::FETCH_ASSOC)['total_buckets'];

        $objects_query = 'SELECT COUNT(*) as total_objects, SUM(size) as total_storage_used FROM objects';
        $objects_stmt = $db->prepare($objects_query);
        $objects_stmt->execute();
        $objects_data = $objects_stmt->fetch(PDO::FETCH_ASSOC);
        $total_objects = $objects_data['total_objects'];
        $total_storage_used = $objects_data['total_storage_used'];

        echo json_encode([
            'total_projects' => $total_projects,
            'total_buckets' => $total_buckets,
            'total_objects' => $total_objects,
            'total_storage_used' => $total_storage_used,
        ]);
        break;
}
