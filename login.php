<?php
require __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';

function send_json_response(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$ajaxLogin = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajaxLogin = isset($_POST['ajax']) && $_POST['ajax'] === '1';

    if (!verify_csrf()) {
        $error = 'Invalid session token. Please try again.';
        if ($ajaxLogin) {
            send_json_response(['success' => false, 'error' => $error]);
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $ip       = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
            if ($ajaxLogin) {
                send_json_response(['success' => false, 'error' => $error]);
            }
        } else {
            // Login throttling: max 5 failed attempts per user+IP within 10 minutes
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM login_attempts
                                    WHERE username = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL 10 MINUTE)");
            $stmt->bind_param('ss', $username, $ip);
            $stmt->execute();
            $fails = (int)$stmt->get_result()->fetch_assoc()['c'];
            $stmt->close();

            if ($fails >= 5) {
                $error = 'Too many failed attempts. Please wait 10 minutes and try again.';
                if ($ajaxLogin) {
                    send_json_response(['success' => false, 'error' => $error]);
                }
            } else {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Clear previous attempts on success
                    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ? AND ip = ?");
                    $stmt->bind_param('ss', $username, $ip);
                    $stmt->execute();
                    $stmt->close();

                    session_regenerate_id(true);
                    $_SESSION['user_id']       = (int)$user['id'];
                    $_SESSION['username']      = $user['username'];
                    $_SESSION['display_name']  = $user['display_name'] ?: $user['username'];
                    $_SESSION['role']          = $user['role'];
                    audit_log($conn, 'LOGIN', 'User logged in');

                    if ($ajaxLogin) {
                        send_json_response(['success' => true, 'redirect' => 'index.php']);
                    }
                    redirect('index.php');
                } else {
                    // Record a failed attempt
                    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip) VALUES (?, ?)");
                    $stmt->bind_param('ss', $username, $ip);
                    $stmt->execute();
                    $stmt->close();
                    $error = 'Invalid username or password.';
                    if ($ajaxLogin) {
                        send_json_response(['success' => false, 'error' => $error]);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="icon" href="<?= LOGO_FILE ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
        <!-- Sea loader for login page -->
        <div id="seaLoader" aria-hidden="true">
            <div class="sea-wrap" role="status" aria-live="polite">
                <div class="sea-text">Signing in</div>
                <div class="sea-wave wave-3"></div>
                <div class="sea-wave wave-2"></div>
                <div class="sea-wave wave-1 sea-wave"></div>
                <div class="sea-bubbles">
                    <div class="sea-bubble b1" style="left:22%; bottom:12px;"></div>
                    <div class="sea-bubble b2" style="left:58%; bottom:18px;"></div>
                    <div class="sea-bubble b3" style="left:36%; bottom:8px;"></div>
                </div>
            </div>
        </div>
        <div class="login-trails" aria-hidden="true"></div>
    <div class="login-wrap">
        <div class="waves" aria-hidden="true">
            <div class="wave-layer"></div>
            <div class="wave-layer"></div>
            <div class="wave-layer"></div>
            <div class="sea-bubble sea-bubble-1"></div>
            <div class="sea-bubble sea-bubble-2"></div>
            <div class="sea-bubble sea-bubble-3"></div>
        </div>
        <div class="card login-card shadow-lg">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="<?= LOGO_FILE ?>" alt="<?= APP_NAME ?> logo" class="login-logo">
                    <h1 class="h4 fw-bold text-primary mt-3 mb-1"><?= APP_NAME ?></h1>
                    <p class="text-muted mb-2">Secure access to your asset inventory dashboard</p>
                    <div class="mx-auto" style="width: 45px; height: 4px; border-radius: 999px; background: rgba(67, 97, 238, .55);"></div>
                </div>

                <div id="loginError" aria-live="assertive">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                            <i data-lucide="circle-alert" class="icon-sm"></i>
                            <span><?= h($error) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" autocomplete="on" id="loginForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i data-lucide="user" class="icon-sm"></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= h($_POST['username'] ?? '') ?>" required autofocus autocomplete="username">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group has-show-password">
                            <span class="input-group-text"><i data-lucide="lock" class="icon-sm"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary show-password-btn" id="showPasswordBtn" aria-label="Show password">
                                <span class="password-icon-wrapper">
                                    <i data-lucide="eye" class="icon-sm"></i>
                                    <i data-lucide="eye-off" class="icon-sm d-none"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                    <div class="form-check form-check-inline mb-4">
                        <input class="form-check-input" type="checkbox" id="rememberUsername" name="remember" value="1">
                        <label class="form-check-label" for="rememberUsername">Remember username on this device</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg btn-loading" id="loginBtn">
                        <span class="btn-spinner" aria-hidden="true"></span>
                        <i data-lucide="log-in" class="icon-sm me-2 btn-icon"></i>
                        <span class="btn-text">Sign In</span>
                    </button>
                </form>

                <div class="text-center mt-4 text-muted small">
                    COMPASS Maritime Training Center &copy; <?= date('Y') ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
    <script>
        (function(){
            document.addEventListener('DOMContentLoaded', function(){
                var form = document.getElementById('loginForm');
                var btn = document.getElementById('loginBtn');
                var usernameEl = document.getElementById('username');
                var passwordEl = document.getElementById('password');
                var rememberEl = document.getElementById('rememberUsername');
                var showPasswordBtn = document.getElementById('showPasswordBtn');
                var errorContainer = document.getElementById('loginError');

                var storedUsername = localStorage.getItem('rememberedUsername');
                if (storedUsername) {
                    usernameEl.value = storedUsername;
                    rememberEl.checked = true;
                }

                showPasswordBtn?.addEventListener('click', function() {
                    var isHidden = passwordEl.type === 'password';
                    passwordEl.type = isHidden ? 'text' : 'password';
                    var showIcon = this.querySelector('i[data-lucide="eye"]');
                    var hideIcon = this.querySelector('i[data-lucide="eye-off"]');
                    if (showIcon && hideIcon) {
                        showIcon.classList.toggle('d-none');
                        hideIcon.classList.toggle('d-none');
                    }
                    this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                });

                if (!form || !btn) return;
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    errorContainer.innerHTML = '';
                    btn.classList.add('loading');
                    btn.setAttribute('disabled', 'disabled');
                    if (typeof window.showSeaLoader === 'function') {
                        window.showSeaLoader('Signing in', 0);
                    } else {
                        var loader = document.getElementById('seaLoader');
                        if (loader) loader.classList.add('show');
                    }

                    if (rememberEl.checked) {
                        localStorage.setItem('rememberedUsername', usernameEl.value.trim());
                    } else {
                        localStorage.removeItem('rememberedUsername');
                    }

                    var formData = new FormData(form);
                    formData.set('ajax', '1');

                    fetch(form.action || window.location.href, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            window.location.href = data.redirect || 'index.php';
                        } else {
                            errorContainer.innerHTML = '<div class="alert alert-danger d-flex align-items-center gap-2 py-2"><i data-lucide="circle-alert" class="icon-sm"></i><span>' + (data.error || 'Unable to sign in. Please try again.') + '</span></div>';
                            if (typeof window.hideSeaLoader === 'function') {
                                window.hideSeaLoader();
                            }
                            btn.classList.remove('loading');
                            btn.removeAttribute('disabled');
                            lucide.createIcons();
                        }
                    })
                    .catch(function() {
                        errorContainer.innerHTML = '<div class="alert alert-danger d-flex align-items-center gap-2 py-2"><i data-lucide="circle-alert" class="icon-sm"></i><span>Network error. Please try again.</span></div>';
                        if (typeof window.hideSeaLoader === 'function') {
                            window.hideSeaLoader();
                        }
                        btn.classList.remove('loading');
                        btn.removeAttribute('disabled');
                    });
                });
            });
        })();
    </script>
        <script>
            // local show/hide for login page (fallback when header/footer not included)
            (function(){
                var _loginLoaderTimer = null;
                window.showSeaLoader = function(text, duration){
                    duration = (typeof duration === 'number') ? duration : 5000;
                    var loader = document.getElementById('seaLoader');
                    if (!loader) return;
                    var label = loader.querySelector('.sea-text'); if(label) label.textContent = text || 'Processing';
                    loader.classList.add('show'); document.body.style.pointerEvents = 'none';
                    if (_loginLoaderTimer) clearTimeout(_loginLoaderTimer);
                    if (duration > 0) _loginLoaderTimer = setTimeout(function(){ loader.classList.remove('show'); document.body.style.pointerEvents = ''; _loginLoaderTimer = null; }, duration);
                };
                window.hideSeaLoader = function(){ var loader = document.getElementById('seaLoader'); if(!loader) return; loader.classList.remove('show'); document.body.style.pointerEvents = ''; if(_loginLoaderTimer){ clearTimeout(_loginLoaderTimer); _loginLoaderTimer = null; } };
            })();
        </script>
    <script>
        (function() {
            const container = document.querySelector('.login-trails');
            const body = document.querySelector('.login-body');
            let lastX = 0;
            let lastY = 0;
            let clickFrame = 0;

            function createTrailBubble(x, y) {
                const bubble = document.createElement('span');
                const size = 10 + Math.random() * 14;
                bubble.className = 'login-trail-bubble';
                bubble.style.width = `${size}px`;
                bubble.style.height = `${size}px`;
                bubble.style.left = `${x}px`;
                bubble.style.top = `${y}px`;
                bubble.style.opacity = String(0.75 + Math.random() * 0.2);
                bubble.style.transform = `translate(-50%, -50%) scale(${0.8 + Math.random() * 0.4})`;
                bubble.style.animationDuration = `${0.95 + Math.random() * 0.45}s`;
                container.appendChild(bubble);
                window.setTimeout(() => bubble.remove(), 1400);
            }

            body.addEventListener('mousemove', function(event) {
                const rect = body.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                const dx = Math.abs(x - lastX);
                const dy = Math.abs(y - lastY);

                if (dx + dy > 16 || clickFrame++ % 2 === 0) {
                    createTrailBubble(x, y);
                    if (dx + dy > 30) {
                        createTrailBubble(x + (Math.random() * 18 - 9), y + (Math.random() * 18 - 9));
                    }
                    lastX = x;
                    lastY = y;
                }
            });
        })();
    </script>
</body>
</html>
