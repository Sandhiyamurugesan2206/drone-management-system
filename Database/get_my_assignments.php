<?php
// get_my_assignments.php
// Returns JSON: { current_assignments: [...], assignment_history: [...] }

session_start();
header('Content-Type: application/json; charset=utf-8');

// require DB connection file (must set $conn or $mysqli as mysqli instance)
require_once __DIR__ . '/db_connect.php';

// find mysqli handle (support $conn or $mysqli)
if (isset($conn) && ($conn instanceof mysqli)) {
    $db = $conn;
} elseif (isset($mysqli) && ($mysqli instanceof mysqli)) {
    $db = $mysqli;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection not found. Make sure db_connect.php provides $conn or $mysqli (mysqli instance).']);
    exit;
}

// ensure user is logged in and is pilot
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}
$pilot_id = (int) $_SESSION['user_id'];

// current: statuses considered active (adjust if your app uses different status strings)
$current_statuses = ['Assigned','Accepted','In Progress'];

// HISTORY statuses (completed/cancelled/rejected)
$history_statuses = ['Completed','Cancelled','Rejected'];

try {
    // --- current assignments
    $cur_sql = "
        SELECT pa.assignment_id,
               pa.status AS assignment_status,
               b.booking_id,
               d.name AS drone_name,
               DATE_FORMAT(b.start_datetime, '%Y-%m-%d %H:%i') AS start_time,
               DATE_FORMAT(b.end_datetime,   '%Y-%m-%d %H:%i') AS end_time,
               b.purpose,
               pa.notes,
               pa.assigned_at
        FROM pilot_assignments pa
        LEFT JOIN bookings b ON pa.booking_id = b.booking_id
        LEFT JOIN drones d ON pa.drone_id = d.drone_id
        WHERE pa.pilot_id = ?
          AND pa.status IN ('Assigned','Accepted','In Progress')
        ORDER BY b.start_datetime ASC
    ";
    $cur_stmt = $db->prepare($cur_sql);
    if (!$cur_stmt) throw new Exception('Prepare failed (current): '.$db->error);
    $cur_stmt->bind_param('i', $pilot_id);
    $cur_stmt->execute();
    $cur_res = $cur_stmt->get_result();
    $current_assignments = $cur_res->fetch_all(MYSQLI_ASSOC);
    $cur_stmt->close();

    // --- assignment history
    $hist_sql = "
        SELECT pa.assignment_id,
               pa.status AS assignment_status,
               b.booking_id,
               d.name AS drone_name,
               DATE_FORMAT(b.start_datetime, '%Y-%m-%d') AS assignment_date,
               CONCAT(DATE_FORMAT(b.start_datetime, '%Y-%m-%d'), ' to ', DATE_FORMAT(b.end_datetime, '%Y-%m-%d')) AS mission_period,
               pa.notes,
               pa.assigned_at
        FROM pilot_assignments pa
        LEFT JOIN bookings b ON pa.booking_id = b.booking_id
        LEFT JOIN drones d ON pa.drone_id = d.drone_id
        WHERE pa.pilot_id = ?
          AND pa.status IN ('Completed','Cancelled','Rejected')
        ORDER BY b.start_datetime DESC
        LIMIT 50
    ";
    $hist_stmt = $db->prepare($hist_sql);
    if (!$hist_stmt) throw new Exception('Prepare failed (history): '.$db->error);
    $hist_stmt->bind_param('i', $pilot_id);
    $hist_stmt->execute();
    $hist_res = $hist_stmt->get_result();
    $assignment_history = $hist_res->fetch_all(MYSQLI_ASSOC);
    $hist_stmt->close();

    echo json_encode([
        'success' => true,
        'current_assignments' => $current_assignments,
        'assignment_history'  => $assignment_history
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
