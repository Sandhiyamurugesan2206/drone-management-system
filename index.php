<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Drone Management System — DroneHub</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style/style.css" />
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand brand-logo" href="#">Drone<span style="color:var(--accent)">Hub</span></a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-3 me-auto">
                    <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Fleet</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Missions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
                </ul>

                <form class="d-flex align-items-center me-3" role="search" onsubmit="return false;">
                    <input class="form-control form-control-sm" type="search" placeholder="Search drones, missions..."
                        aria-label="Search">
                </form>
                <!-- TRIGGER (replace header Login link with this button if you want modal) -->
                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#authModal">
                    Login / Register
                </button>

                <!-- AUTH MODAL (Login + Register tabs) -->
                <?php
// If this file is index.php ensure PHP is enabled. This snippet expects db.php at /Database/db.php
$roleOptions = ['user','pilot']; // fallback
try {
  if (file_exists(__DIR__ . '/Database/db.php')) {
    require_once __DIR__ . '/Database/db.php'; // sets $conn (mysqli)
    $q = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($q && $row = $q->fetch_assoc()) {
      if (preg_match("/^enum\('(.*)'\)$/", $row['Type'], $m)) {
        $roles = explode("','", $m[1]);
        $roleOptions = array_values(array_filter($roles, function($r){ return $r !== 'admin'; }));
      }
    }
  }
} catch(Throwable $e) {
  // fallback to defaults
}
?>
                <div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-md">
                        <div class="modal-content" style="border-radius:14px;">
                            <div class="modal-header border-0 pb-0">
                                <div>
                                    <h5 class="modal-title mb-0">Welcome to DroneHub</h5>
                                    <small class="text-muted">Sign in or create an account to manage drones &
                                        missions</small>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>

                            <div class="modal-body pt-3">
                                <!-- Nav tabs -->
                                <ul class="nav nav-tabs nav-justified mb-3" id="authTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab"
                                            data-bs-target="#loginPane" type="button" role="tab"
                                            aria-controls="loginPane" aria-selected="true">Login</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="register-tab" data-bs-toggle="tab"
                                            data-bs-target="#registerPane" type="button" role="tab"
                                            aria-controls="registerPane" aria-selected="false">Register</button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <!-- LOGIN TAB -->
                                    <div class="tab-pane fade show active" id="loginPane" role="tabpanel"
                                        aria-labelledby="login-tab">
                                        <form id="loginForm" method="POST" action="Database/login.php" novalidate>
                                            <div class="mb-3">
                                                <label class="form-label small">I am a</label>
                                                <select name="role" class="form-select" required>
                                                    <option value="">Choose role</option>
                                                    <option value="user">User</option>
                                                    <option value="pilot">Pilot</option>
                                                    <option value="admin">Admin</option>
                                                </select>
                                                <div class="invalid-feedback">Select your role.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small">Email</label>
                                                <input name="email" type="email" class="form-control"
                                                    placeholder="you@example.com" required>
                                                <div class="invalid-feedback">Enter a valid email.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small">Password</label>
                                                <input name="password" type="password" class="form-control"
                                                    placeholder="••••••••" required minlength="6">
                                                <div class="invalid-feedback">Password required (min 6 chars).</div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="remember"
                                                        id="remember">
                                                    <label class="form-check-label small" for="remember">Remember
                                                        me</label>
                                                </div>
                                                <a href="#" class="small">Forgot password?</a>
                                            </div>

                                            <div class="d-grid">
                                                <button id="loginBtn" type="submit" class="btn btn-primary">Sign
                                                    in</button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- REGISTER TAB -->
                                    <div class="tab-pane fade" id="registerPane" role="tabpanel"
                                        aria-labelledby="register-tab">
                                        <form id="registerForm" method="POST" action="Database/register.php" novalidate>
                                            <div class="mb-3">
                                                <label class="form-label small">Full name</label>
                                                <input name="username" type="text" class="form-control"
                                                    placeholder="Your full name" required>
                                                <div class="invalid-feedback">Enter your name.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small">Email</label>
                                                <input name="email" type="email" class="form-control"
                                                    placeholder="you@example.com" required>
                                                <div class="invalid-feedback">Enter a valid email.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small">Password</label>
                                                <input id="regPass" name="password" type="password" class="form-control"
                                                    placeholder="At least 6 characters" required minlength="6">
                                                <div class="form-text small">Use a strong password. Include letters &
                                                    numbers.</div>
                                                <div class="invalid-feedback">Password must be at least 6 characters.
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small">Confirm password</label>
                                                <input id="regPass2" name="password_confirm" type="password"
                                                    class="form-control" placeholder="Repeat password" required>
                                                <div class="invalid-feedback">Passwords must match.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small">You are a</label>
                                                <select name="role" class="form-select" required>
                                                    <option value="user">User</option>
                                                    <option value="pilot">Pilot</option>
                                                </select>
                                            </div>


                                            <div class="d-grid">
                                                <button id="registerBtn" type="submit" class="btn btn-success">Create
                                                    account</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer border-0 pt-0">
                                <small class="text-muted">By continuing you agree to our <a href="#">Terms</a> & <a
                                        href="#">Privacy</a>.</small>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Client-side JS for validation and confirm password -->

                <!-- Optional modal demonstration (keeps link to login.php for real login) -->
                <button class="btn btn-sm btn-primary-cta" data-bs-toggle="modal" data-bs-target="#demoLoginModal">
                    Quick Demo
                </button>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <header class="hero" style="background-image: url('images/hero.jpeg');">
        <div class="hero-overlay"></div>

        <div class="container hero-content" data-aos="fade-up" data-aos-duration="900">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1>Find the Perfect Drone for Every Mission</h1>
                    <p class="lead">Manage your drone fleet, schedules, missions, and
                        reports — all from one smart dashboard.</p>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="#features" class="btn btn-primary-cta btn-lg">Explore
                            Fleet</a>
                        <a href="#how" class="btn btn-light btn-lg" style="border-radius:12px;">How it works</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN -->
    <main class="container">

        <!-- Features -->
        <section id="features" class="text-center mb-4">
            <h2 class="section-title">Find the Best Drone For You</h2>
            <p class="section-sub">From long-endurance survey drones to nimble
                inspection copters — manage them all in one dashboard.</p>
        </section>

        <section class="row g-4 mb-4">
            <div class="col-md-4" data-aos="fade-right" data-aos-delay="50">
                <div class="feature-card">
                    <h5>Real-time Tracking</h5>
                    <p class="muted mb-0">Live telemetry, geofencing alerts, and secure
                        telemetry streaming for situational awareness.</p>
                </div>
            </div>

            <div class="col-md-4" data-aos="fade-up" data-aos-delay="120">
                <div class="feature-card">
                    <h5>Fleet Scheduling</h5>
                    <p class="muted mb-0">Assign pilots, avoid conflicts, manage
                        batteries & maintenance windows with a single calendar.</p>
                </div>
            </div>

            <div class="col-md-4" data-aos="fade-left" data-aos-delay="170">
                <div class="feature-card">
                    <h5>Reports & Compliance</h5>
                    <p class="muted mb-0">Detailed flight logs, inspection reports, and
                        exportable compliance-ready records.</p>
                </div>
            </div>
        </section>

        <!-- CTA strip -->
        <section class="cta-strip d-flex align-items-center justify-content-between gap-3 mb-4" data-aos="zoom-in">
            <div>
                <h5 class="mb-1" style="font-weight:700;">Ready to scale your drone
                    ops?</h5>
                <div class="muted small">Start managing drones, pilots and missions
                    with a single platform.</div>
            </div>
            <div class="d-flex align-items-center">
                <a href="#" class="btn btn-primary-cta">Request a Demo</a>
                <a href="#" class="btn btn-link ms-3">Contact Sales</a>
            </div>
        </section>

        <!-- Cards grid -->
        <section class="row row-cols-1 row-cols-md-3 g-4 mb-5">
            <div class="col" data-aos="flip-left" data-aos-delay="60">
                <div class="feature-card">
                    <h6 class="mb-2">Delivery Drones</h6>
                    <p class="muted mb-0">End-to-end routing, payload & battery
                        optimization for last-mile delivery.</p>
                </div>
            </div>

            <div class="col" data-aos="flip-left" data-aos-delay="120">
                <div class="feature-card">
                    <h6 class="mb-2">Survey & Mapping</h6>
                    <p class="muted mb-0">Automated waypoint missions, RTK-ready capture
                        and high-quality stitched outputs.</p>
                </div>
            </div>

            <div class="col" data-aos="flip-left" data-aos-delay="180">
                <div class="feature-card">
                    <h6 class="mb-2">Inspection</h6>
                    <p class="muted mb-0">Guided capture templates, thermal & visual
                        analytics for infrastructure inspections.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="row gy-4">
                <div class="col-md-4">
                    <div class="footer-title">DroneHub</div>
                    <div class="muted small mt-2">All-in-one drone management for
                        operations, safety and reporting. Built to scale from a single
                        pilot to enterprise fleets.</div>

                    <div class="mt-3">
                        <a class="social-btn" href="#" aria-label="twitter"><i class="bi bi-twitter"></i></a>
                        <a class="social-btn" href="#" aria-label="linkedin"><i class="bi bi-linkedin"></i></a>
                        <a class="social-btn" href="#" aria-label="github"><i class="bi bi-github"></i></a>
                    </div>
                </div>

                <div class="col-md-2 footer-links">
                    <div class="footer-title">Product</div>
                    <a href="#">Features</a>
                    <a href="#">Integrations</a>
                    <a href="#">Pricing</a>
                </div>

                <div class="col-md-2 footer-links">
                    <div class="footer-title">Company</div>
                    <a href="#">About</a>
                    <a href="#">Careers</a>
                    <a href="#">Contact</a>
                </div>

                <div class="col-md-4">
                    <div class="footer-title">Newsletter</div>
                    <div class="muted small mb-2">Get updates about new features and
                        beta releases.</div>

                    <form class="d-flex gap-2" onsubmit="event.preventDefault(); alert('Subscribed! (demo)');">
                        <input class="form-control form-control-sm" placeholder="Your email" type="email" required>
                        <button class="btn btn-primary-cta btn-sm" type="submit">Subscribe</button>
                    </form>
                </div>
            </div>

            <hr style="border-color: rgba(255,255,255,0.06); margin:1.75rem 0;" />

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small muted">
                <div>© <span id="year"></span> DroneHub. All rights reserved.</div>
                <div class="mt-2 mt-md-0">Made with <span style="color:#ff7b7b">❤</span> for safer flights</div>
            </div>
        </div>
    </footer>


    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

    <script>
    // AOS init
    AOS.init({
        easing: 'ease-out-quad',
        duration: 700,
        once: true,
        offset: 100
    });

    // footer year
    document.getElementById('year').textContent = new Date().getFullYear();

    // simple parallax on mouse move for hero bg (subtle)
    const hero = document.querySelector('.hero');
    const heroBg = document.getElementById('heroBg');
    if (hero && heroBg) {
        hero.addEventListener('mousemove', (e) => {
            const w = hero.clientWidth,
                h = hero.clientHeight;
            const x = (e.clientX / w) - 0.5;
            const y = (e.clientY / h) - 0.5;
            heroBg.style.transform = `scale(1.04) translate(${x * 6}px, ${y * 6}px)`;
        });
        hero.addEventListener('mouseleave', () => {
            heroBg.style.transform = 'scale(1.03) translate(0,0)';
        });
    }
    //script for login and register form validation

    (function() {
        // Bootstrap 5 uses native validation styles
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');

        [loginForm, registerForm].forEach(form => {
            form.addEventListener('submit', function(e) {
                // additional check for register password match
                if (form === registerForm) {
                    const p1 = document.getElementById('regPass').value;
                    const p2 = document.getElementById('regPass2').value;
                    if (p1 !== p2) {
                        e.preventDefault();
                        e.stopPropagation();
                        document.getElementById('regPass2').classList.add('is-invalid');
                        return;
                    } else {
                        document.getElementById('regPass2').classList.remove('is-invalid');
                    }
                }

                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                } else {
                    // show simple loading state
                    const btn = form.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    const txt = btn.innerHTML;
                    btn.innerHTML = 'Please wait...';
                    // allow form to submit normally
                }

                form.classList.add('was-validated');
            }, false);
        });
    })();
    </script>
</body>

</html>