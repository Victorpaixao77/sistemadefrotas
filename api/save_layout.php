<?php
// Save dashboard layout API

// Initialize session and include necessary files
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated (commented out for development)
// if (!isLoggedIn()) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized access']);
//     exit;
// }

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json_method_not_allowed('Method not allowed');
}

// Protege mutações com CSRF e retorno JSON padronizado.
api_require_csrf_json();

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate data
if (!$data || !isset($data['layout']) || !is_array($data['layout'])) {
    api_json_error('Invalid data format', 400, 'invalid_payload');
}

// In a real implementation, this would save the layout to a database
// For this demo, we'll just return success
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$layout = $data['layout'];

// Calculate a hash for the layout (this would be compared when retrieving)
$layoutHash = md5(json_encode($layout));

// Response with success
echo json_encode([
    'success' => true,
    'message' => 'Layout saved successfully',
    'userId' => $userId,
    'layoutHash' => $layoutHash,
    'timestamp' => date('Y-m-d H:i:s')
]);

// In a real implementation, you would save to database:
// 
// $stmt = $conn->prepare("INSERT INTO user_layout (user_id, layout_data, created_at) 
//                        VALUES (?, ?, NOW())
//                        ON DUPLICATE KEY UPDATE layout_data = ?, updated_at = NOW()");
// $layoutJson = json_encode($layout);
// $stmt->bind_param("iss", $userId, $layoutJson, $layoutJson);
// $success = $stmt->execute();
//
// if ($success) {
//     echo json_encode([
//         'success' => true,
//         'message' => 'Layout saved successfully'
//     ]);
// } else {
//     http_response_code(500);
//     echo json_encode([
//         'error' => 'Failed to save layout',
//         'details' => $stmt->error
//     ]);
// }
