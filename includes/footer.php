    </div> <!-- .container inside main -->
  </main>

  <footer style="background:#081020; color:#cbd6e1; padding:2.5rem 0;">
    <div class="container">
      <div class="row">
        <div class="col-md-4">
          <h5>DroneHub</h5>
          <p class="small">All-in-one drone management for operations, safety and reporting.</p>
          <div class="mt-2">
            <a href="#" class="btn btn-dark btn-sm me-1">Twitter</a>
            <a href="#" class="btn btn-dark btn-sm me-1">LinkedIn</a>
            <a href="#" class="btn btn-dark btn-sm">GitHub</a>
          </div>
        </div>

        <div class="col-md-2">
          <h6>Product</h6>
          <ul class="list-unstyled small">
            <li>Features</li>
            <li>Integrations</li>
            <li>Pricing</li>
          </ul>
        </div>

        <div class="col-md-2">
          <h6>Company</h6>
          <ul class="list-unstyled small">
            <li>About</li>
            <li>Careers</li>
            <li>Contact</li>
          </ul>
        </div>

        <div class="col-md-4">
          <h6>Newsletter</h6>
          <form class="d-flex" action="<?= $rootPrefix ?>/index.php" method="post">
            <input class="form-control form-control-sm me-2" placeholder="Your email">
            <button class="btn btn-primary btn-sm">Subscribe</button>
          </form>
          <div class="text-end small mt-3">© <?= date('Y') ?> DroneHub. All rights reserved.</div>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
