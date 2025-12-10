<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}
include_once __DIR__ . '/../includes/header.php';
?>
<div class="row">
  <aside class="col-md-2 sidebar p-4">
    <div class="brand">DroneHub</div>
    <nav class="mt-3">
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="admin_dashboard.php" class="active">Booking Requests</a>
      <a href="#">Pilots</a>
      <a href="../logout.php" class="mt-4 d-block text-danger">Logout</a>
    </nav>
  </aside>

  <section class="col-md-10">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2>Booking Requests</h2>
          <p class="text-muted">View pending bookings and assign pilots.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5>Pending Bookings</h5>
          <div id="bookingsWrapper">
            <table class="table table-striped" id="pendingTable">
              <thead>
                <tr>
                  <th>S No.</th><th>User</th><th>Drone</th><th>Start</th><th>End</th><th>Purpose</th><th>Pilot</th><th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
            <div id="noPending" class="text-muted" style="display:none;">No pending bookings.</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Assign Pilot Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="assignForm">
        <div class="modal-header">
          <h5 class="modal-title" id="assignModalLabel">Assign Pilot</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="m_booking_id" name="booking_id">
          <div id="bookingInfo" class="mb-3"></div>
          <div class="mb-3">
            <label class="form-label">Select Pilot</label>
            <select id="pilotSelect" name="pilot_id" class="form-select" required>
              <option value="">Loading pilots...</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Note (optional)</label>
            <textarea id="assignNote" name="note" class="form-control" rows="2"></textarea>
          </div>
          <div id="assignError" class="text-danger" style="display:none;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button id="assignSubmit" type="submit" class="btn btn-primary">Assign & Approve</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const pendingTbody = document.querySelector('#pendingTable tbody');
  const noPending = document.getElementById('noPending');

  async function loadPending() {
    pendingTbody.innerHTML = '<tr><td colspan="8">Loading...</td></tr>';
    try {
      console.log('Loading pending bookings...');
      
      const response = await fetch('../Database/admin_actions.php?action=list_pending_bookings', { 
        credentials: 'same-origin',
        headers: { 
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      });
      
      // Check if response is JSON
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Non-JSON response:', text.substring(0, 200));
        throw new Error('Server returned non-JSON response. Check for PHP errors.');
      }
      
      const data = await response.json();
      console.log('API Response:', data);
      
      if (!response.ok) {
        throw new Error(data.error || `HTTP ${response.status}: Failed to load`);
      }
      
      if (!data.success) {
        throw new Error(data.error || 'Request failed');
      }
      
      const rows = data.bookings || [];
      console.log('Bookings found:', rows.length);
      
      if (!rows.length) {
        document.getElementById('pendingTable').style.display = 'none';
        noPending.style.display = 'block';
        return;
      }
      
      document.getElementById('pendingTable').style.display = '';
      noPending.style.display = 'none';
      pendingTbody.innerHTML = '';
      
      rows.forEach((r, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${idx + 1}</td>
                        <td>${escapeHTML(r.username || r.email || '—')}</td>
                        <td>${escapeHTML(r.drone_name || '—')}</td>
                        <td>${formatDateTime(r.start_datetime)}</td>
                        <td>${formatDateTime(r.end_datetime)}</td>
                        <td>${escapeHTML(r.purpose || '—')}</td>
                        <td>${r.pilot_id ? 'Assigned' : '—'}</td>
                        <td>
                          <button class="btn btn-sm btn-primary" onclick="openAssignModal(${r.booking_id})">
                            Assign Pilot
                          </button>
                        </td>`;
        pendingTbody.appendChild(tr);
      });
      
    } catch (err) {
      console.error('Error loading bookings:', err);
      pendingTbody.innerHTML = `<tr><td colspan="8" class="text-danger">
        <strong>Error:</strong> ${escapeHTML(err.message)}<br>
        <small>Check browser console for details</small>
      </td></tr>`;
    }
  }

  function escapeHTML(s) { 
    if(!s) return ''; 
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function formatDateTime(dtString) {
    if (!dtString) return '—';
    try {
      const dt = new Date(dtString);
      return dt.toLocaleString();
    } catch (e) {
      return dtString;
    }
  }

  let assignModal = null;
  document.addEventListener('DOMContentLoaded', function() {
    assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
    loadPending();
  });

  async function openAssignModal(bookingId) {
    console.log('Opening assign modal for booking:', bookingId);
    document.getElementById('m_booking_id').value = bookingId;
    
    // Load booking details
    try {
      const response = await fetch(`../Database/admin_actions.php?action=get_booking_details&booking_id=${bookingId}`);
      const data = await response.json();
      
      if (response.ok && data.success) {
        const booking = data.booking;
        document.getElementById('bookingInfo').innerHTML = `
          <div class="alert alert-info">
            <strong>Booking Details:</strong><br>
            <strong>Drone:</strong> ${escapeHTML(booking.drone_name || '—')}<br>
            <strong>User:</strong> ${escapeHTML(booking.username || booking.email || '—')}<br>
            <strong>Time:</strong> ${formatDateTime(booking.start_datetime)} to ${formatDateTime(booking.end_datetime)}<br>
            <strong>Purpose:</strong> ${escapeHTML(booking.purpose || '—')}
          </div>
        `;
      } else {
        document.getElementById('bookingInfo').innerHTML = `
          <div class="alert alert-warning">
            Could not load booking details: ${escapeHTML(data.error || 'Unknown error')}
          </div>
        `;
      }
    } catch (err) {
      console.error('Error loading booking details:', err);
      document.getElementById('bookingInfo').innerHTML = `
        <div class="alert alert-danger">
          Error loading booking details: ${escapeHTML(err.message)}
        </div>
      `;
    }

    // Load pilots
    const select = document.getElementById('pilotSelect');
    select.innerHTML = '<option value="">Loading pilots...</option>';
    
    try {
      const response = await fetch(`../Database/admin_actions.php?action=list_pilots&booking_id=${bookingId}`);
      const data = await response.json();
      
      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Failed to load pilots');
      }
      
      const pilots = data.pilots || [];
      
      if (pilots.length === 0) {
        select.innerHTML = '<option value="">No available pilots</option>';
      } else {
        select.innerHTML = '<option value="">Select Pilot</option>';
        pilots.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.pilot_id;
          let text = p.name;
          if (p.conflicts > 0) {
            text += ` (Conflict - ${p.conflicts} booking(s))`;
            opt.disabled = true;
          }
          opt.textContent = text;
          select.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Error loading pilots:', err);
      select.innerHTML = '<option value="">Error loading pilots</option>';
    }

    document.getElementById('assignNote').value = '';
    document.getElementById('assignError').style.display = 'none';
    assignModal.show();
  }

  document.getElementById('assignForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const booking_id = document.getElementById('m_booking_id').value;
    const pilot_id = document.getElementById('pilotSelect').value;
    const note = document.getElementById('assignNote').value;
    const errorDiv = document.getElementById('assignError');
    const submitBtn = document.getElementById('assignSubmit');

    if (!pilot_id) {
      errorDiv.textContent = 'Please select a pilot.';
      errorDiv.style.display = 'block';
      return;
    }

    submitBtn.disabled = true;
    errorDiv.style.display = 'none';

    try {
      const formData = new URLSearchParams();
      formData.append('booking_id', booking_id);
      formData.append('pilot_id', pilot_id);
      formData.append('note', note);

      const response = await fetch('../Database/admin_actions.php?action=assign_pilot', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Assignment failed');
      }

      assignModal.hide();
      await loadPending();
      alert('Pilot assigned successfully!');
      
    } catch (err) {
      errorDiv.textContent = err.message;
      errorDiv.style.display = 'block';
      console.error('Assignment error:', err);
    } finally {
      submitBtn.disabled = false;
    }
  });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>