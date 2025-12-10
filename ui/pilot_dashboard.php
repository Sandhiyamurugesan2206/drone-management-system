<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pilot') {
    header('Location: ../index.php');
    exit;
}

// Set session data for demo
if (empty($_SESSION['username'])) {
    $_SESSION['username'] = 'John Doe';
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <aside class="col-md-2 sidebar p-4">
    <div class="brand">DroneHub</div>
    <nav class="mt-3">
      <a href="pilot_dashboard.php" class="active">My Assignments</a>
      <a href="../logout.php" class="mt-4 d-block text-danger">Logout</a>
    </nav>
  </aside>

  <section class="col-md-10">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h2>
          <p class="text-muted">View your drone assignments and mission details.</p>
        </div>
      </div>

      <!-- Current Assignments -->
      <div class="card">
        <div class="card-body">
          <h5>My Current Assignments</h5>
          <div id="assignmentsWrapper">
            <table class="table table-striped" id="assignmentsTable">
              <thead> 
                <tr>
                  <th>#</th><th>Drone</th><th>Start Time</th><th>End Time</th><th>Purpose</th><th>Status</th><th>Action</th>
                </tr>
              </thead>
              <tbody id="assignmentsBody">
                <!-- Static data will be loaded here by JavaScript -->
              </tbody>
            </table>
            <div id="noAssignments" class="text-muted" style="display:none;">No current assignments.</div>
          </div>
        </div>
      </div>

      <!-- Assignment History -->                             
      <div class="card mt-4">
        <div class="card-body">
          <h5>Assignment History</h5>                                                                             
          <div id="historyWrapper">
            <table class="table table-striped" id="historyTable">
              <thead>
                <tr>
                  <th>#</th><th>Drone</th><th>Assignment Date</th><th>Mission Period</th><th>Status</th>
                </tr>
              </thead>
              <tbody id="historyBody">
                <!-- Static data will be loaded here by JavaScript -->
              </tbody>
            </table>
            <div id="noHistory" class="text-muted" style="display:none;">No assignment history.</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
// Static mock data
const staticAssignments = {
  current_assignments: [
     {
      assignment_id: 1,
      drone_name: "Sky Rider",
      start_datetime: "2025-12-20 09:00:00",
      end_datetime: "2024-12-20 12:00:00",
      purpose: "Aerial Photography - Real Estate",
      status: "Scheduled",
      assigned_at: "2024-12-15 14:30:00"
    },
    {
      assignment_id: 1,
      drone_name: "DJI Mavic 3 Pro",
      start_datetime: "2025-12-20 09:00:00",
      end_datetime: "2024-12-20 12:00:00",
      purpose: "Aerial Photography - Real Estate",
      status: "Scheduled",
      assigned_at: "2024-12-15 14:30:00"
    },
    {
      assignment_id: 2,
      drone_name: "Autel EVO II Pro",
      start_datetime: "2025-12-21 14:00:00",
      end_datetime: "2024-12-21 16:30:00",
      purpose: "Construction Site Inspection",
      status: "Scheduled",
      assigned_at: "2024-12-16 10:15:00"
    },
    {
      assignment_id: 3,
      drone_name: "Parrot Anafi USA",
      start_datetime: "2025-12-22 08:00:00",
      end_datetime: "2024-12-22 10:00:00",
      purpose: "Agricultural Survey - Farmlands",
      status: "Pending",
      assigned_at: "2024-12-17 09:45:00"
    }
  ],
  assignment_history: [
    {
      assignment_id: 101,
      drone_name: "DJI Phantom 4 RTK",
      start_datetime: "2025-12-10 10:00:00",
      end_datetime: "2024-12-10 13:00:00",
      purpose: "Infrastructure Inspection",
      status: "Completed",
      assigned_at: "2024-12-05 11:20:00"
    },
    {
      assignment_id: 102,
      drone_name: "Skydio X2",
      start_datetime: "2025-12-12 08:30:00",
      end_datetime: "2024-12-12 10:30:00",
      purpose: "Search and Rescue Training",
      status: "Completed",
      assigned_at: "2024-12-07 16:40:00"
    },
    {
      assignment_id: 103,
      drone_name: "DJI Mavic 3 Enterprise",
      start_datetime: "2025-12-08 13:00:00",
      end_datetime: "2024-12-08 15:00:00",
      purpose: "Power Line Inspection",
      status: "Cancelled",
      assigned_at: "2024-12-03 09:10:00"
    },
    {
      assignment_id: 104,
      drone_name: "Yuneec H520",
      start_datetime: "2025-12-05 11:00:00",
      end_datetime: "2024-12-05 14:00:00",
      purpose: "Event Coverage - Marathon",
      status: "Completed",
      assigned_at: "2024-11-30 14:25:00"
    },
    {
      assignment_id: 105,
      drone_name: "DJI Inspire 2",
      start_datetime: "2025-12-03 09:00:00",
      end_datetime: "2024-12-03 12:00:00",
      purpose: "Cinematography - Short Film",
      status: "Completed",
      assigned_at: "2024-11-28 15:30:00"
    }
  ]
};

function loadPilotAssignments() {
    const assignmentsBody = document.querySelector('#assignmentsBody');
    const historyBody = document.querySelector('#historyBody');
    
    assignmentsBody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
    historyBody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';

    // Simulate API delay
    setTimeout(() => {
        const current = staticAssignments.current_assignments || [];
        const history = staticAssignments.assignment_history || [];
        
        // Current Assignments
        if (current.length === 0) {
            document.getElementById('assignmentsTable').style.display = 'none';
            document.getElementById('noAssignments').style.display = 'block';
        } else {
            assignmentsBody.innerHTML = '';
            current.forEach((assignment, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${idx + 1}</td>
                               <td>${escapeHTML(assignment.drone_name || '—')}</td>
                               <td>${formatDateTime(assignment.start_datetime)}</td>
                               <td>${formatDateTime(assignment.end_datetime)}</td>
                               <td>${escapeHTML(assignment.purpose || '—')}</td>
                               <td><span class="badge bg-warning">${assignment.status}</span></td>
                               <td>
                                 <button class="btn btn-sm btn-success" onclick="startMission(${assignment.assignment_id})">Start Mission</button>
                                 <button class="btn btn-sm btn-danger" onclick="cancelAssignment(${assignment.assignment_id})">Cancel</button>
                               </td>`;
                assignmentsBody.appendChild(tr);
            });
        }
        
        // Assignment History
        if (history.length === 0) {
            document.getElementById('historyTable').style.display = 'none';
            document.getElementById('noHistory').style.display = 'block';
        } else {
            historyBody.innerHTML = '';
            history.forEach((assignment, idx) => {
                const tr = document.createElement('tr');
                let badgeClass = 'bg-secondary';
                if (assignment.status === 'Completed') badgeClass = 'bg-success';
                if (assignment.status === 'Cancelled') badgeClass = 'bg-danger';
                
                tr.innerHTML = `<td>${idx + 1}</td>
                               <td>${escapeHTML(assignment.drone_name || '—')}</td>
                               <td>${formatDateTime(assignment.assigned_at)}</td>
                               <td>${formatDateTime(assignment.start_datetime)} to ${formatDateTime(assignment.end_datetime)}</td>
                               <td><span class="badge ${badgeClass}">${assignment.status}</span></td>`;
                historyBody.appendChild(tr);
            });
        }
    }, 500); // 0.5 second delay to simulate API call
}

function escapeHTML(s) { 
    if(!s) return ''; 
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function formatDateTime(dtString) {
    if (!dtString) return '—';
    try {
        const dt = new Date(dtString);
        return dt.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dtString;
    }
}

function startMission(assignmentId) {
    if (!confirm('Are you sure you want to start this mission?')) return;
    
    // Find the assignment
    const assignment = staticAssignments.current_assignments.find(a => a.assignment_id === assignmentId);
    if (assignment) {
        assignment.status = 'In Progress';
        alert(`Mission ${assignmentId} started successfully!\nDrone: ${assignment.drone_name}\nPurpose: ${assignment.purpose}`);
        loadPilotAssignments();
    } else {
        alert('Assignment not found!');
    }
}

function cancelAssignment(assignmentId) {
    if (!confirm('Are you sure you want to cancel this assignment? This action cannot be undone.')) return;
    
    // Find the assignment
    const assignmentIndex = staticAssignments.current_assignments.findIndex(a => a.assignment_id === assignmentId);
    if (assignmentIndex !== -1) {
        const assignment = staticAssignments.current_assignments[assignmentIndex];
        
        // Move to history
        staticAssignments.assignment_history.unshift({
            ...assignment,
            status: 'Cancelled'
        });
        
        // Remove from current assignments
        staticAssignments.current_assignments.splice(assignmentIndex, 1);
        
        alert(`Assignment ${assignmentId} has been cancelled.`);
        loadPilotAssignments();
    } else {
        alert('Assignment not found!');
    }
}

document.addEventListener('DOMContentLoaded', loadPilotAssignments);
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>