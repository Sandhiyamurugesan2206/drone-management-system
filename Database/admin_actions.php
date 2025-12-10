<?php
// admin_actions.php  (replace your existing file with this)
// Requires: db.php that provides $conn = new mysqli(...)

session_start();
header('Content-Type: application/json; charset=utf-8');

// Only admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated or not admin']);
    exit;
}

require_once __DIR__ . '/db.php'; // must define $conn (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed (check db.php)']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function db_prepare_or_err($conn, $sql) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('DB prepare failed: ' . $conn->error);
    }
    return $stmt;
}

try {
    // --- 1) list_pending_bookings
    if ($action === 'list_pending_bookings') {
        $sql = "SELECT 
                    b.booking_id,
                    b.user_id,
                    b.drone_id, 
                    b.pilot_id,
                    b.start_datetime,
                    b.end_datetime,
                    b.purpose,
                    b.status,
                    b.assigned_at,
                    b.created_at,
                    u.username,
                    u.email,
                    d.name as drone_name
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                LEFT JOIN drones d ON b.drone_id = d.drone_id
                WHERE b.status = 'Pending'
                ORDER BY b.created_at DESC";
        $stmt = db_prepare_or_err($conn, $sql);
        if (!$stmt->execute()) throw new Exception('Query execution failed: ' . $stmt->error);
        $res = $stmt->get_result();
        $bookings = [];
        while ($row = $res->fetch_assoc()) $bookings[] = $row;
        $stmt->close();
        echo json_encode(['success' => true, 'bookings' => $bookings]);
        exit;
    }

    // --- 2) get_booking_details
    if ($action === 'get_booking_details') {
        $booking_id = intval($_GET['booking_id'] ?? 0);
        if (!$booking_id) throw new Exception('Booking ID required');

        $sql = "SELECT 
                    b.booking_id,
                    b.start_datetime,
                    b.end_datetime,
                    b.purpose,
                    b.status,
                    u.username,
                    u.email,
                    d.name as drone_name
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                LEFT JOIN drones d ON b.drone_id = d.drone_id
                WHERE b.booking_id = ?";
        $stmt = db_prepare_or_err($conn, $sql);
        $stmt->bind_param('i', $booking_id);
        if (!$stmt->execute()) throw new Exception('Query failed: ' . $stmt->error);
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$booking) throw new Exception('Booking not found');
        echo json_encode(['success' => true, 'booking' => $booking]);
        exit;
    }

    // --- 3) list_pilots (fetch pilots from users table)
    if ($action === 'list_pilots') {
        $booking_id = intval($_GET['booking_id'] ?? 0);
        if (!$booking_id) throw new Exception('Booking ID required');

        // get booking time
        $time_sql = "SELECT start_datetime, end_datetime FROM bookings WHERE booking_id = ?";
        $time_stmt = db_prepare_or_err($conn, $time_sql);
        $time_stmt->bind_param('i', $booking_id);
        if (!$time_stmt->execute()) throw new Exception('Failed to get booking time: ' . $time_stmt->error);
        $bt = $time_stmt->get_result()->fetch_assoc();
        $time_stmt->close();
        if (!$bt) throw new Exception('Booking not found');

        $start = $bt['start_datetime'];
        $end   = $bt['end_datetime'];

        // get pilots from users table (is_pilot flag)
        $sql = "SELECT id AS pilot_id, username AS name, email, pilot_status AS status
                FROM users
                WHERE is_pilot = 1
                ORDER BY username ASC";
        $stmt = db_prepare_or_err($conn, $sql);
        if (!$stmt->execute()) throw new Exception('Failed to load pilots: ' . $stmt->error);
        $res = $stmt->get_result();
        $pilots = [];
        while ($row = $res->fetch_assoc()) {
            // conflict check: bookings.pilot_id stores users.id
            $conflict_sql = "SELECT COUNT(*) AS conflict_count
                             FROM bookings b
                             WHERE b.pilot_id = ?
                               AND b.status IN ('Approved', 'In Progress')
                               AND NOT (b.end_datetime <= ? OR b.start_datetime >= ?)";
            $conflict_stmt = db_prepare_or_err($conn, $conflict_sql);
            $conflict_stmt->bind_param('iss', $row['pilot_id'], $start, $end);
            $conflict_stmt->execute();
            $conflict = $conflict_stmt->get_result()->fetch_assoc();
            $conflict_stmt->close();

            $row['conflicts'] = intval($conflict['conflict_count'] ?? 0);
            // mark availability derived from pilot_status and conflict_count
            // If pilot_status='Busy' or conflicts>0 => not available
            $row['available'] = ($row['status'] === 'Available' && $row['conflicts'] === 0) ? true : false;
            $pilots[] = $row;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'pilots' => $pilots]);
        exit;
    }

    // --- 4) assign_pilot (sets pilot_id to users.id, updates statuses and creates assignment)
    if ($action === 'assign_pilot') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Method not allowed');

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $pilot_user_id = intval($_POST['pilot_id'] ?? 0); // users.id
        $note = trim($_POST['note'] ?? '');

        if (!$booking_id || !$pilot_user_id) throw new Exception('Booking ID and Pilot ID required');

        $conn->begin_transaction();
        try {
            // get drone_id
            $gsql = "SELECT drone_id FROM bookings WHERE booking_id = ?";
            $gstmt = db_prepare_or_err($conn, $gsql);
            $gstmt->bind_param('i', $booking_id);
            $gstmt->execute();
            $booking = $gstmt->get_result()->fetch_assoc();
            $gstmt->close();
            if (!$booking) throw new Exception('Booking not found');

            $drone_id = $booking['drone_id'];
            $admin_id = $_SESSION['user_id'];

            // update booking with pilot (users.id) and approve
            $up = "UPDATE bookings SET pilot_id = ?, status = 'Approved', assigned_at = NOW() WHERE booking_id = ?";
            $ustmt = db_prepare_or_err($conn, $up);
            $ustmt->bind_param('ii', $pilot_user_id, $booking_id);
            if (!$ustmt->execute()) throw new Exception('Failed to assign pilot: ' . $ustmt->error);
            $ustmt->close();

            // mark pilot busy in users table
            $pup = "UPDATE users SET pilot_status = 'Busy' WHERE id = ?";
            $pstmt = db_prepare_or_err($conn, $pup);
            $pstmt->bind_param('i', $pilot_user_id);
            if (!$pstmt->execute()) throw new Exception('Failed to update pilot status: ' . $pstmt->error);
            $pstmt->close();

            // insert into pilot_assignments (pilot_id stores users.id)
            $ains = "INSERT INTO pilot_assignments (booking_id, pilot_id, drone_id, assigned_by, notes, status, assigned_at)
                     VALUES (?, ?, ?, ?, ?, 'Assigned', NOW())";
            $astmt = db_prepare_or_err($conn, $ains);
            $astmt->bind_param('iiiss', $booking_id, $pilot_user_id, $drone_id, $admin_id, $note);
            if (!$astmt->execute()) throw new Exception('Failed to create assignment record: ' . $astmt->error);
            $new_assignment_id = $astmt->insert_id;
            $astmt->close();

            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Pilot assigned', 'assignment_id' => $new_assignment_id]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        exit;
    }

    throw new Exception('Invalid action: ' . $action);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'action' => $action
    ]);
    exit;
}
