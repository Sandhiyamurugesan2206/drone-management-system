<?php
// Database/login.php
// Clean, robust login handler for admin / pilot / user
// Dev notes: this version uses plain-text password comparison (as your DB currently stores).
// Switch to password_hash/password_verify for production.

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) session_start();

// Helper to detect AJAX requests (XHR or Accept header)
function is_ajax() {
    return (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

// Helper to respond JSON for AJAX or redirect for normal form posts
function respond_json_or_redirect($data, $http_code = 200) {
    if (is_ajax()) {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    } else {
        if (!empty($data['redirect'])) {
            header("Location: " . $data['redirect']);
            exit;
        }
        if (!empty($data['error'])) {
            // flash error into session for UI
            $_SESSION['flash'] = $data['error'];
        }
        header("Location: ../index.php");
        exit;
    }
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json_or_redirect(['error' => 'Invalid request method.'], 405);
}

// include DB connection: must provide $mysqli (mysqli instance)
require_once __DIR__ . '/db.php';
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    respond_json_or_redirect(['error' => 'Database connection not found. Check db.php'], 500);
}

// sanitize inputs
$role = trim($_POST['role'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// basic validation
if ($role === '' || $email === '' || $password === '') {
    respond_json_or_redirect(['error' => 'Please fill all fields.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond_json_or_redirect(['error' => 'Invalid email address.'], 422);
}

// fetch user by email
$stmt = $mysqli->prepare("SELECT id, username, email, password, role FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    respond_json_or_redirect(['error' => 'Database error (prepare user).', 'db_error' => $mysqli->error], 500);
}
$stmt->bind_param('s', $email);
if (!$stmt->execute()) {
    respond_json_or_redirect(['error' => 'Database error (execute user).', 'db_error' => $stmt->error], 500);
}
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    respond_json_or_redirect(['error' => 'No account found for this email.'], 404);
}

// password check (plain-text; replace with hashing later)
if ($password !== $user['password']) {
    respond_json_or_redirect(['error' => 'Invalid credentials.'], 401);
}

// role check
if ($user['role'] !== $role) {
    respond_json_or_redirect(['error' => 'Role mismatch. Choose the correct role.'], 403);
}

// SUCCESS: set session values (keeps compatibility with other pages)
$_SESSION['user_id']      = $user['id'];
$_SESSION['username']     = $user['username'];
$_SESSION['user_name']    = $user['username'];
$_SESSION['role']         = $user['role'];
$_SESSION['user_role']    = $user['role'];
$_SESSION['display_name'] = $user['username'];

// regenerate session id for security
session_regenerate_id(true);

// Pilot handling: your new plan uses user.id as pilot identifier (no separate pilot table)
// so set pilot_id in session to user.id when role is 'pilot'
if ($user['role'] === 'pilot') {
    $_SESSION['pilot_id'] = $user['id'];
    // redirect to pilot dashboard (non-AJAX)
    respond_json_or_redirect(['success' => 'Login successful', 'redirect' => '../ui/pilot_dashboard.php'], 200);
}

// Admin user redirects
if ($user['role'] === 'admin') {
    respond_json_or_redirect(['success' => 'Login successful', 'redirect' => '../ui/admin_dashboard.php'], 200);
} elseif ($user['role'] === 'user') {
    respond_json_or_redirect(['success' => 'Login successful', 'redirect' => '../ui/user_dashboard.php'], 200);
}

// fallback (shouldn't reach here, but safe)
respond_json_or_redirect(['success' => 'Login successful', 'redirect' => '../index.php'], 200);
