<?php
// Database/register.php
session_start();

// Helper: detect AJAX request
function is_ajax() {
    return (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

// Helper: send JSON response for AJAX, or redirect for normal requests
function respond_json_or_redirect($data, $http_code = 200) {
    if (is_ajax()) {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    } else {
        // for normal form submits / direct access: set session messages and redirect back to index.php in project root
        if (isset($data['error'])) {
            $_SESSION['error'] = $data['error'];
        }
        if (isset($data['success'])) {
            $_SESSION['success'] = $data['success'];
        }
        // Redirect to homepage (index.php) in project root (Database/../index.php)
        header('Location: ../index.php');
        exit;
    }
}

// only allow POST for register action
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json_or_redirect(['error' => 'Invalid request method.'], 405);
}

require_once __DIR__ . '/db.php'; // expects mysqli connection in $conn

// fetch and sanitize inputs
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$role = $_POST['role'] ?? 'user';

// basic validation
if ($username === '' || $email === '' || $password === '' || $password_confirm === '') {
    respond_json_or_redirect(['error' => 'Please fill all required fields.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond_json_or_redirect(['error' => 'Invalid email address.'], 422);
}

if (strlen($password) < 6) {
    respond_json_or_redirect(['error' => 'Password must be at least 6 characters.'], 422);
}

if ($password !== $password_confirm) {
    respond_json_or_redirect(['error' => 'Passwords do not match.'], 422);
}

// disallow admin self-registration
if ($role === 'admin') {
    $role = 'user';
}

// check duplicate email
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    respond_json_or_redirect(['error' => 'Database error.'], 500);
}
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    respond_json_or_redirect(['error' => 'Email already registered.'], 409);
}
$stmt->close();

// insert user (plain text password as per your request)
$hash = $password; // NOTE: plain-text (insecure). Replace with password_hash() when ready.

$ins = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
if (!$ins) {
    respond_json_or_redirect(['error' => 'Database error on insert.'], 500);
}
$ins->bind_param('ssss', $username, $email, $hash, $role);

if ($ins->execute()) {
    // optional: auto-login new user
    $_SESSION['user_id'] = $ins->insert_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    session_regenerate_id(true);

    // success: for AJAX return JSON with redirect to project root index.php
    // for normal POST we will redirect to ../index.php via respond_json_or_redirect
    respond_json_or_redirect(['success' => 'Registered', 'redirect' => '../index.php'], 201);
} else {
    respond_json_or_redirect(['error' => 'Registration failed.'], 500);
}
