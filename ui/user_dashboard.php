<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../index.php');
    exit;
}
include_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <!-- Sidebar -->
  <aside class="col-md-2 sidebar p-4">
    <div class="brand">DroneHub</div>
    <nav class="mt-3">
      <a href="user_dashboard.php">Dashboard</a>
      <a href="#" id="nav-check">Check Availability</a>
      <a href="#" id="nav-mybookings">My Bookings</a>
      <a href="logout.php" class="mt-4 d-block text-danger">Logout</a>
    </nav>
  </aside>

  <!-- Main content -->
  <section class="col-md-10">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h2>
          <p class="text-muted">Request a drone, track bookings and manage your missions.</p>
        </div>
        <div>
          <button class="btn btn-primary" id="open-check">Check Availability</button>
        </div>
      </div>

      <!-- Availability form -->
      <div class="card mb-4" id="availability-card" style="display:none;">
        <div class="card-body">
          <h5>Check Drone Availability</h5>
          <form id="availabilityForm" class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Start</label>
              <input type="datetime-local" name="start" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">End</label>
              <input type="datetime-local" name="end" class="form-control" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn btn-success me-2" type="submit">Find Drones</button>
              <button id="availabilityClose" type="button" class="btn btn-outline-secondary">Close</button>
            </div>
          </form>

          <hr>

          <div id="availableList"></div>
        </div>
      </div>

      <!-- My bookings -->
      <div class="card">
        <div class="card-body">
          <h5>My Bookings</h5>
          <div id="bookingsTableWrapper">
            <table class="table table-striped" id="bookingsTable">
              <thead>
                <tr>
                  <th>S No.</th><th>Drone</th><th>Start</th><th>End</th><th>Purpose</th><th>Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
            <div id="noBookings" class="text-muted" style="display:none;">No bookings yet.</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
  // helper: show/hide availability card
  document.getElementById('open-check').addEventListener('click', () => {
    document.getElementById('availability-card').style.display = 'block';
  });
  document.getElementById('availabilityClose').addEventListener('click', () => {
    document.getElementById('availability-card').style.display = 'none';
  });
  document.getElementById('nav-check').addEventListener('click', (e)=>{ e.preventDefault(); document.getElementById('open-check').click(); });
  document.getElementById('nav-mybookings').addEventListener('click', (e)=>{ e.preventDefault(); loadBookings(); });

  // Fetch user's bookings on load
  document.addEventListener('DOMContentLoaded', () => {
    loadBookings();
  });

  // Load bookings
  async function loadBookings(){
    const wrapper = document.querySelector('#bookingsTable tbody');
    wrapper.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
    try {
   const res = await fetch('../Database/user_actions.php?action=get_bookings', { credentials: 'same-origin', headers:{ 'Accept':'application/json'}});

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed to load');
      const rows = data.bookings;
      if (!rows.length) {
        document.getElementById('bookingsTable').style.display = 'none';
        document.getElementById('noBookings').style.display = 'block';
        return;
      }
      document.getElementById('bookingsTable').style.display = '';
      document.getElementById('noBookings').style.display = 'none';
      wrapper.innerHTML = '';
      rows.forEach((r, idx)=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${idx+1}</td>
          <td>${escapeHTML(r.drone_name || '—')}</td>
          <td>${r.start_datetime}</td>
          <td>${r.end_datetime}</td>
          <td>${escapeHTML(r.purpose||'—')}</td>
          <td>${r.status}</td>`;
        wrapper.appendChild(tr);
      });
    } catch(err){
      wrapper.innerHTML = `<tr><td colspan="6">Error loading bookings</td></tr>`;
      console.error(err);
    }
  }

  // Availability form submit -> call API to get available drones
  document.getElementById('availabilityForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    const start = fd.get('start');
    const end = fd.get('end');

    const list = document.getElementById('availableList');
    list.innerHTML = 'Searching...';

    try {
      const res = await fetch(`../Database/user_actions.php?action=check_availability&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`, { headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed');

      if (!data.drone || !data.drone.length) {
        list.innerHTML = '<div class="text-muted">No drones available for the selected time.</div>';
        return;
      }

      // render available drones with "Request Booking" button
      list.innerHTML = '<div class="row g-3">';
      data.drone.forEach(d=>{
        list.innerHTML += `
          <div class="col-md-4">
            <div class="card p-3">
              <h6>${escapeHTML(d.name)} <small class="text-muted">(${escapeHTML(d.model||'')})</small></h6>
              <div>Battery: ${d.battery_capacity}%</div>
              <div class="mt-2">
                <button class="btn btn-sm btn-primary" onclick="requestBooking(${d.drone_id}, '${start}', '${end}')">Request Booking</button>
              </div>
            </div>
          </div>
        `;
      });
      list.innerHTML += '</div>';
    } catch(err){
      list.innerHTML = '<div class="text-danger">Error searching drone.</div>';
      console.error(err);
    }
  });

  // Request booking action
  async function requestBooking(drone_id, start, end) {
    if (!confirm('Request booking for this drone?')) return;
    try {
      const res = await fetch('../Database/user_actions.php?action=request_booking', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new URLSearchParams({ drone_id, start_datetime: start, end_datetime: end, purpose: 'General mission' })
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed');
      alert('Booking requested successfully!');
      document.getElementById('availability-card').style.display = 'none';
      loadBookings();
    } catch(err){
      alert('Error requesting booking: ' + (err.message||''));
      console.error(err);
    }
  }

  // small helper
  function escapeHTML(s){ if(!s) return ''; return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
