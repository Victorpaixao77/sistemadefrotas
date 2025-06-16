<?php
// Authentication API

// Include necessary files first
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check for action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch ($action) {
    case 'login':
        handleLogin();
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    case 'status':
        checkAuthStatus();
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

/**
 * Handle login requests
 */
function handleLogin() {
    // Only accept POST requests for login
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Check if username and password were provided
    if (!$data || !isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        return;
    }
    
    $username = $data['username'];
    $password = $data['password'];
    
    // In a real implementation, you would verify against a database
    // For this demo, we'll use hardcoded credentials
    if ($username === 'admin' && $password === 'password') {
        // Set session variables
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = $username;
        $_SESSION['name'] = 'Victor Hugo';
        $_SESSION['role'] = 'Admin';
        
        // Return success with user info
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => 1,
                'username' => $username,
                'name' => 'Victor Hugo',
                'role' => 'Admin',
                'avatar' => 'V'
            ]
        ]);
    } else {
        // Return error for invalid credentials
        http_response_code(401);
        echo json_encode([
            'error' => 'Invalid username or password'
        ]);
    }
}

/**
 * Handle logout requests
 */
function handleLogout() {
    // Unset all session variables
    $_SESSION = [];
    
    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session
    session_destroy();
    
    // Return success message
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful'
    ]);
}

/**
 * Check authentication status
 */
function checkAuthStatus() {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'name' => $_SESSION['name'],
                'role' => $_SESSION['role'],
                'avatar' => substr($_SESSION['name'], 0, 1)
            ]
        ]);
    } else {
        echo json_encode([
            'authenticated' => false
        ]);
    }
}
