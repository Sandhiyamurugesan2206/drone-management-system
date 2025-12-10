<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DroneHub</title>

  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* simple neutral styles to mimic sidebar layout */
    body { background:#f5f7fb; }
    .sidebar { background:#243345; color:#fff; min-height:100vh; }
    .sidebar a { color: rgba(255,255,255,0.9); text-decoration:none; display:block; padding:.6rem 0; }
    .sidebar a:hover { color:#fff; text-decoration: none; }
    .brand { font-weight:700; font-size:1.25rem; padding:1rem 0; }z
  </style>
</head>
<body>
  <!-- header (top bar) -->
  <nav class="navbar navbar-expand-lg navbar-dark" style="background:#0b1220;">
    <div class="container-fluid">
      <a class="navbar-brand" href="/index.php">DroneHub</a>

      <div class="d-flex align-items-center ms-auto">
        <div class="me-3">
          <?php if(isset($_SESSION['username'])): ?>
            <span style="color:#fff;">Hello, <?= htmlspecialchars($_SESSION['username']) ?></span>
          <?php endif; ?>
        </div>
        <a class="btn btn-outline-light btn-sm" href="/logout.php">Logout</a>
      </div>
    </div>
  </nav>

  <main class="container-fluid p-4">
