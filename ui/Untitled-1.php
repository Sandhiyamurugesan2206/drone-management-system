// After successful login
if ($user['role'] === 'pilot') {
    // Get pilot_id from pilot table
    $pilot_sql = "SELECT pilot_id FROM pilot WHERE user_id = ?";
    $pilot_stmt = $conn->prepare($pilot_sql);
    $pilot_stmt->bind_param('i', $user['id']);
    $pilot_stmt->execute();
    $pilot_result = $pilot_stmt->get_result();
    $pilot_data = $pilot_result->fetch_assoc();
    
    if ($pilot_data) {
        $_SESSION['pilot_id'] = $pilot_data['pilot_id'];
        // REDIRECT TO PILOT DASHBOARD
        header('Location: pilot_dashboard.php');
        exit;
    } else {
        // Pilot profile not linked
        $_SESSION['error'] = 'Pilot profile not found. Please contact admin.';
        header('Location: ../index.php');
        exit;
    }
}

// For other roles (keep your existing code)
else if ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
} else if ($user['role'] === 'user') {
    header('Location: user_dashboard.php');
    exit;
}