<?php
// Database/user_actions.php
// Improved debug + consistent JSON responses

session_start();
header('Content-Type: application/json; charset=utf-8');

// Dev helper: show DB errors in responses (safe for dev only)
$DEV_SHOW_DB_ERRORS = true;

require_once __DIR__ . '/db.php'; // provides $conn (mysqli)

// read action and session user (may be null)
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

// helper to return DB error when prepare fails
function db_prepare_or_err($conn, $sql) {
    global $DEV_SHOW_DB_ERRORS;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        if ($DEV_SHOW_DB_ERRORS) {
            http_response_code(500);
            echo json_encode(['error' => 'DB prepare failed', 'db_error' => $conn->error, 'sql' => $sql]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
    return $stmt;
}

// --- 1) check_availability?start=...&end=...
// NOTE: availability is public — does not require login
if ($action === 'check_availability') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    if (!$start || !$end) {
        http_response_code(422);
        echo json_encode(['error' => 'Missing start/end']);
        exit;
    }

    // Use correct table name 'drones'
    $sql = "SELECT d.drone_id, d.name, d.model, d.battery_capacity
            FROM drones d
            WHERE d.status = 'Available'
              AND NOT EXISTS (
                SELECT 1 FROM bookings b
                WHERE b.drone_id = d.drone_id
                  AND NOT (b.end_datetime <= ? OR b.start_datetime >= ?)
                  AND b.status IN ('Pending','Approved','In Progress')
              )
            LIMIT 100";

    $stmt = db_prepare_or_err($conn, $sql);
    // overlap condition: NOT (existing_end <= req_start OR existing_start >= req_end)
    // bind in order: start, end
    $stmt->bind_param('ss', $start, $end);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Query execute failed', 'db_error' => $stmt->error]);
        exit;
    }

    $res = $stmt->get_result();
    $drone = [];
    while ($r = $res->fetch_assoc()) $drone[] = $r;

    echo json_encode(['drone' => $drone]);
    exit;
}

// The following actions require authentication
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// --- 2) request_booking (POST)
if ($action === 'request_booking') {
    // expects POST drone_id, start_datetime, end_datetime, purpose
    $drone_id = $_POST['drone_id'] ?? '';
    $start = $_POST['start_datetime'] ?? '';
    $end = $_POST['end_datetime'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');

    if (!$drone_id || !$start || !$end) {
        http_response_code(422);
        echo json_encode(['error' => 'Missing fields']);
        exit;
    }

    // Double-check the drone is available in that time
    $checkSql = "SELECT COUNT(*) as cnt FROM bookings b WHERE b.drone_id = ? AND NOT (b.end_datetime <= ? OR b.start_datetime >= ?) AND b.status IN ('Pending','Approved','In Progress')";
    $cstmt = db_prepare_or_err($conn, $checkSql);
    $cstmt->bind_param('iss', $drone_id, $start, $end);
    if (!$cstmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Availability check failed', 'db_error' => $cstmt->error]);
        exit;
    }
    $cres = $cstmt->get_result()->fetch_assoc();
    if ($cres['cnt'] > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Drone not available for selected time']);
        exit;
    }
    $cstmt->close();

    $ins = db_prepare_or_err($conn, "INSERT INTO bookings (user_id, drone_id, start_datetime, end_datetime, purpose, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $ins->bind_param('iisss', $user_id, $drone_id, $start, $end, $purpose);
    if ($ins->execute()) {
        echo json_encode(['success' => 'Booking requested', 'booking_id' => $ins->insert_id]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Insert failed', 'db_error' => $ins->error]);
        exit;
    }
}

// --- 3) get_bookings (for current user)
if ($action === 'get_bookings') {
    $sql = "SELECT b.booking_id, b.start_datetime, b.end_datetime, b.purpose, b.status, d.name AS drone_name
            FROM bookings b
            LEFT JOIN drones d ON b.drone_id = d.drone_id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT 200";
    $stmt = db_prepare_or_err($conn, $sql);
    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Query execute failed', 'db_error' => $stmt->error]);
        exit;
    }
    $res = $stmt->get_result();
    $bookings = [];
    while ($r = $res->fetch_assoc()) $bookings[] = $r;
    echo json_encode(['bookings' => $bookings]);
    exit;
}

// default
http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
