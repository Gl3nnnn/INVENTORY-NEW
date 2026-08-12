  </main>
</div>

<!-- Toast container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer">
  <?php if (isset($_SESSION['success'])): ?>
    <div id="appToast" class="toast align-items-center text-white border-0 show <?= strpos($_SESSION['success'], 'deleted') !== false ? 'bg-danger' : 'bg-success' ?>" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi <?= strpos($_SESSION['success'], 'deleted') !== false ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' ?> me-2"></i>
          <?= h($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
  <?php if (isset($_SESSION['error'])): ?>
    <div id="appToast" class="toast align-items-center text-white border-0 show bg-danger" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <?= h($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();

  document.addEventListener('DOMContentLoaded', function () {
    const toastEl = document.getElementById('appToast');
    if (toastEl) {
      const t = new bootstrap.Toast(toastEl, { delay: 5000 });
      t.show();
    }

    const hamburger = document.getElementById('hamburgerBtn');
    const sidebar   = document.getElementById('appSidebar');
    const backdrop  = document.getElementById('sidebarBackdrop');
    const main      = document.querySelector('.main-content');

    function closeSidebar() {
      if (!sidebar) return;
      sidebar.classList.remove('open');
      document.body.classList.remove('sidebar-open');
      if (hamburger) {
        hamburger.setAttribute('aria-expanded', 'false');
      }
      if (backdrop) {
        backdrop.classList.remove('show');
      }
    }

    function openSidebar() {
      if (!sidebar) return;
      sidebar.classList.add('open');
      document.body.classList.add('sidebar-open');
      if (hamburger) {
        hamburger.setAttribute('aria-expanded', 'true');
      }
      if (backdrop) {
        backdrop.classList.add('show');
      }
    }

    if (hamburger && sidebar && main) {
      hamburger.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', closeSidebar);
    }

    const navLinks = sidebar ? sidebar.querySelectorAll('.nav-link') : [];
    navLinks.forEach(function(link) {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          closeSidebar();
        }
      });
    });

    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
        closeSidebar();
      }
    });
  });
  // Sea loader controls
  (function(){
    let _seaLoaderTimer = null;
    window.showSeaLoader = function(text = 'Processing', duration = 5000) {
      const loader = document.getElementById('seaLoader');
      if (!loader) return;
      const label = loader.querySelector('.sea-text');
      if (label) label.textContent = text;
      loader.classList.add('show');
      document.body.style.pointerEvents = 'none';
      if (_seaLoaderTimer) clearTimeout(_seaLoaderTimer);
      if (duration > 0) {
        _seaLoaderTimer = setTimeout(function(){
          loader.classList.remove('show');
          document.body.style.pointerEvents = '';
          _seaLoaderTimer = null;
        }, duration);
      }
    };
    window.hideSeaLoader = function(){
      const loader = document.getElementById('seaLoader');
      if (!loader) return;
      loader.classList.remove('show');
      document.body.style.pointerEvents = '';
      if (_seaLoaderTimer) { clearTimeout(_seaLoaderTimer); _seaLoaderTimer = null; }
    };
  })();

  // Attach logout handler to show loader before navigating
  document.addEventListener('DOMContentLoaded', function() {
    const logout = document.querySelector('.logout-btn');
    if (logout) {
      logout.addEventListener('click', function(e) {
        // show loader and then allow navigation after 5s
        e.preventDefault();
        const href = logout.getAttribute('href');
        showSeaLoader('Signing out', 5000);
        setTimeout(function(){ window.location.href = href; }, 5000);
      });
    }
  });
</script>
</body>
</html>
