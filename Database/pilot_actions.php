<?php
// pilot_actions.php  — updated to use pilot table for pilot info
session_start();
require_once __DIR__ . '/db_connect.php'; // must set $conn (mysqli)

// Basic checks
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pilot') {
    header('Location: login.php');
    exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('DB connection error. Check db_connect.php');
}

$session_user_id = (int) $_SESSION['user_id'];

// Resolve pilot_id:
// 1) prefer $_SESSION['pilot_id'] if already set (from login), else
// 2) lookup pilot table by user_id and set session for future use.
$pilot_id = null;
if (!empty($_SESSION['pilot_id'])) {
    $pilot_id = (int) $_SESSION['pilot_id'];
} else {
    $pstmt = $conn->prepare("SELECT pilot_id FROM pilot WHERE user_id = ? LIMIT 1");
    if ($pstmt) {
        $pstmt->bind_param('i', $session_user_id);
        $pstmt->execute();
        $pres = $pstmt->get_result()->fetch_assoc();
        $pstmt->close();
        if ($pres && isset($pres['pilot_id'])) {
            $pilot_id = (int) $pres['pilot_id'];
            $_SESSION['pilot_id'] = $pilot_id;
        }
    }
}

// If still not found, try to match pilot by email in users -> pilot.email
if (!$pilot_id) {
    // get user email
    $ues = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    if ($ues) {
        $ues->bind_param('i', $session_user_id);
        $ues->execute();
        $urow = $ues->get_result()->fetch_assoc();
        $ues->close();
        if ($urow && !empty($urow['email'])) {
            $email = $urow['email'];
            $p2 = $conn->prepare("SELECT pilot_id FROM pilot WHERE email = ? LIMIT 1");
            if ($p2) {
                $p2->bind_param('s', $email);
                $p2->execute();
                $p2r = $p2->get_result()->fetch_assoc();
                $p2->close();
                if ($p2r && isset($p2r['pilot_id'])) {
                    $pilot_id = (int)$p2r['pilot_id'];
                    $_SESSION['pilot_id'] = $pilot_id;
                }
            }
        }
    }
}

// If still not found, fail gracefully with empty arrays (prevents fatal crash)
if (!$pilot_id) {
    $_SESSION['pilot_actions_error'] = 'No pilot profile linked to your account. Please contact admin.';
    $_SESSION['current_assignments_data'] = [];
    $_SESSION['assignment_history_data']  = [];
    header('Location: pilot_dashboard.php');
    exit;
}

// Prepare containers
$current_assignments = [];
$assignment_history  = [];

try {
    // Current assignments (Assigned, Accepted, In Progress)
    $cur_sql = "
        SELECT 
            pa.assignment_id,
            pa.booking_id,
            pa.drone_id,
            pa.assigned_by,
            pa.assigned_at,
            pa.status AS assignment_status,
            pa.notes,
            b.start_datetime,
            b.end_datetime,
            b.purpose,
            d.name AS drone_name,
            d.model AS drone_model
        FROM pilot_assignments pa
        LEFT JOIN bookings b ON pa.booking_id = b.booking_id
        LEFT JOIN drones d ON pa.drone_id = d.drone_id
        WHERE pa.pilot_id = ?
          AND pa.status IN ('Assigned','Accepted','In Progress')
        ORDER BY b.start_datetime ASC
    ";
    $cur_stmt = $conn->prepare($cur_sql);
    if (!$cur_stmt) throw new Exception('Prepare failed (current): ' . $conn->error);
    $cur_stmt->bind_param('i', $pilot_id);
    $cur_stmt->execute();
    $cur_res = $cur_stmt->get_result();
    while ($row = $cur_res->fetch_assoc()) {
        $row['start_datetime'] = $row['start_datetime'] ? date('Y-m-d H:i', strtotime($row['start_datetime'])) : null;
        $row['end_datetime']   = $row['end_datetime']   ? date('Y-m-d H:i', strtotime($row['end_datetime'])) : null;
        $current_assignments[] = $row;
    }
    $cur_stmt->close();

    // Assignment history (Completed, Cancelled, Rejected)
    $hist_sql = "
        SELECT 
            pa.assignment_id,
            pa.booking_id,
            pa.drone_id,
            pa.assigned_by,
            pa.assigned_at,
            pa.status AS assignment_status,
            pa.notes,
            b.start_datetime,
            b.end_datetime,
            b.purpose,
            d.name AS drone_name,
            d.model AS drone_model
        FROM pilot_assignments pa
        LEFT JOIN bookings b ON pa.booking_id = b.booking_id
        LEFT JOIN drones d ON pa.drone_id = d.drone_id
        WHERE pa.pilot_id = ?
          AND pa.status IN ('Completed','Cancelled','Rejected')
        ORDER BY b.start_datetime DESC
        LIMIT 100
    ";
    $hist_stmt = $conn->prepare($hist_sql);
    if (!$hist_stmt) throw new Exception('Prepare failed (history): ' . $conn->error);
    $hist_stmt->bind_param('i', $pilot_id);
    $hist_stmt->execute();
    $hist_res = $hist_stmt->get_result();
    while ($row = $hist_res->fetch_assoc()) {
        $row['start_datetime'] = $row['start_datetime'] ? date('Y-m-d H:i', strtotime($row['start_datetime'])) : null;
        $row['end_datetime']   = $row['end_datetime']   ? date('Y-m-d H:i', strtotime($row['end_datetime'])) : null;
        $assignment_history[] = $row;
    }
    $hist_stmt->close();

    // Save to session (your existing dashboard reads these)
    $_SESSION['current_assignments_data'] = $current_assignments;
    $_SESSION['assignment_history_data']  = $assignment_history;

    // Clear error if success
    unset($_SESSION['pilot_actions_error']);

} catch (Exception $e) {
    $_SESSION['pilot_actions_error'] = 'Error loading assignments: ' . $e->getMessage();
    $_SESSION['current_assignments_data'] = [];
    $_SESSION['assignment_history_data'] = [];
}

// Redirect back to dashboard page
header('Location: pilot_dashboard.php');
exit;
