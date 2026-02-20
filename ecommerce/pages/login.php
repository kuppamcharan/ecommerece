<?php
// pages/login.php - with demo-login (safe for localhost)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// If already logged in, send to root index
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// DB include (pages/ -> go up one)
include '../includes/db.php';

$email = '';
$error = '';

// Only allow demo login on localhost for safety
$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- Demo login (handled first) ----------
    if (isset($_POST['demo_login'])) {
        if (!$is_localhost) {
            $error = 'Demo login only allowed on localhost.';
        } else {
            $demo_email = 'demo@demo.local';
            $demo_pass_plain = 'demo123';

            // Try to find demo user
            $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$demo_email]);
            $demo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$demo) {
                // create demo user (hash password)
                $hash = password_hash($demo_pass_plain, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $ins->execute([$demo_email, $hash]);
                $demo_id = $conn->lastInsertId();
            } else {
                $demo_id = $demo['id'];
            }

            // set session and redirect
            $_SESSION['user_id'] = (int)$demo_id;
            $_SESSION['user_email'] = $demo_email;

            header('Location: ../index.php');
            exit;
        }
    }

    // ---------- Normal login ----------
    if (isset($_POST['email']) || isset($_POST['password'])) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $dbPass = $user['password'];

                    if ((function_exists('password_verify') && password_verify($password, $dbPass)) || ($password === $dbPass)) {
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['user_email'] = $user['email'];

                        if ($remember) {
                            setcookie('remember_email', $user['email'], time() + 60*60*24*30, '/');
                        }

                        header('Location: ../index.php');
                        exit;
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }

            } catch (Exception $e) {
                error_log("Login DB error: " . $e->getMessage());
                $error = 'An internal error occurred — try again later.';
            }
        }
    }
  }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Sign In — Online Store</title>
  <link rel="stylesheet" href="../css/style.css">
  <!-- HEAD: ensure external CSS loaded; fallback inline styles if file missing -->
<link rel="stylesheet" href="../css/style.css">
<!-- Inline fallback in case stylesheet path is wrong (temporary) -->
<style>
/* minimal login CSS fallback (temporary) */
.auth-wrap{max-width:980px;margin:42px auto;padding:18px}
.auth-card{width:100%;max-width:520px;margin:0 auto;background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));border-radius:14px;padding:28px;box-shadow:0 18px 40px rgba(2,6,23,0.6);color:#eaf6ff}
.auth-card h2{margin:0 0 14px 0;font-size:1.4rem;color:#fff;font-weight:800}
.auth-label{display:block;margin:8px 0 6px 0;color:#9fb0c8;font-weight:700}
.auth-input{width:100%;padding:10px 12px;margin-top:6px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);background:rgba(255,255,255,0.02);color:#eaf6ff}
.pw-field{display:flex;gap:8px;align-items:center}
.pw-toggle{border-radius:8px;border:1px solid rgba(255,255,255,0.04);padding:8px 10px;background:transparent;color:#9fb0c8;cursor:pointer}
.auth-row{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
.auth-btn{background:linear-gradient(90deg,#12c26b,#00d08a);color:#021322;border:0;padding:10px 16px;border-radius:10px;font-weight:800;cursor:pointer;box-shadow:0 10px 30px rgba(0,208,138,0.12)}
.auth-link{color:#00d08a;font-weight:700;text-decoration:underline}
.auth-or{display:flex;align-items:center;gap:12px;margin-top:14px;color:#9fb0c8}
.social-btn{display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;border:0;cursor:pointer;background:linear-gradient(90deg,#3b3b3b,#2a2a2a);color:#fff;font-weight:700}
@media(max-width:720px){.auth-card{padding:18px;margin:8px}.auth-wrap{padding:12px}}
</style>

</head>
<body>

<header>
  <div class="header-container">
    <div class="welcome-section"><h1>Welcome to Our Store</h1></div>
    <nav>
      <a href="../index.php">Home</a>
      <a href="register.php">Register</a>
    </nav>
  </div>
</header>

<main class="products-container">
  <section class="auth-wrap">
    <div class="auth-card" role="region" aria-labelledby="auth-title">
      <h2 id="auth-title">Sign in to your account</h2>

      <?php if ($error): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate autocomplete="on">
        <label class="auth-label">
          Email
          <input class="auth-input" type="email" name="email" required value="<?= htmlspecialchars($email) ?>" placeholder="you@domain.com" />
        </label>

        <label class="auth-label">
          Password
          <div class="pw-field">
            <input id="pw" class="auth-input" type="password" name="password" required placeholder="••••••••" />
            <button type="button" id="pw-toggle" class="pw-toggle" aria-label="Toggle password visibility">Show</button>
          </div>
        </label>

        <div class="auth-row">
          <label class="remember">
            <input type="checkbox" name="remember" /> Remember me
          </label>
          <span>
            <!-- no reset page; show disabled link -->
            <a href="#" class="auth-link-small" style="opacity:.9; pointer-events:none; color:var(--accent2);">Forgot?</a>
          </span>
        </div>

        <div style="display:flex; gap:12px; margin-top:18px; align-items:center;">
          <button type="submit" class="auth-btn">Sign In</button>
          <a href="register.php" class="auth-link">Create account</a>
        </div>

        <div class="auth-or"><span>or</span></div>

        <!-- Demo login form -->
        <div class="social-row">
          <form method="POST" style="display:inline;">
            <button type="submit" name="demo_login" class="social-btn">Continue with Demo</button>
          </form>
        </div>
      </form>
    </div>
  </section>
</main>

<script>
(function(){
  const t = document.getElementById('pw-toggle');
  const pw = document.getElementById('pw');
  if (!t || !pw) return;
  t.addEventListener('click', function(){
    if (pw.type === 'password') { pw.type = 'text'; t.textContent = 'Hide'; } 
    else { pw.type = 'password'; t.textContent = 'Show'; }
  });
})();
</script>

</body>
</html>
