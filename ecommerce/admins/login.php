<?php
// admin_login.php  - drop-in replacement
// Backup your original file before replacing.

session_start();
include '../includes/db.php'; // adjust path only if your includes folder is elsewhere

// Helpful: do not display errors on production. Enable during debugging by setting to 1.
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Friendly message holder
$error = '';

if (isset($_POST['login'])) {
    // basic sanitization
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        try {
            // Prepared statement to fetch admin user by email and role
            $stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && isset($user['role']) && $user['role'] === 'admin') {
                $dbpass = $user['password'] ?? '';

                // Accept either bcrypt hashed or legacy plain-text:
                $ok = false;
                if ($dbpass !== '') {
                    // prefer password_verify for hashed password
                    if (password_verify($password, $dbpass)) {
                        $ok = true;
                    } elseif ($password === $dbpass) {
                        // fallback for legacy plain-text (temporary compatibility)
                        $ok = true;
                    }
                }

                if ($ok) {
                    // success: set admin session and redirect intelligently
                    $_SESSION['admin_id'] = (int)$user['id'];
                    $_SESSION['admin_email'] = $user['email'];

                    // try to redirect to a dashboard file that actually exists
                    $redirect = null;
                    // same directory dashboard.php?
                    if (file_exists(__DIR__ . '/dashboard.php')) {
                        $redirect = 'dashboard.php';
                    }
                    // one level up pages/dashboard.php?
                    elseif (file_exists(__DIR__ . '/../pages/dashboard.php')) {
                        $redirect = '../pages/dashboard.php';
                    }
                    // absolute path in project (common)
                    elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/ecommerce/pages/dashboard.php')) {
                        $redirect = '/ecommerce/pages/dashboard.php';
                    }
                    // fallback: index
                    else {
                        $redirect = '/ecommerce/index.php';
                    }

                    header('Location: ' . $redirect);
                    exit();
                } else {
                    $error = 'Invalid credentials.';
                }
            } else {
                $error = 'Invalid credentials or you are not an admin.';
            }
        } catch (Exception $e) {
            // Log the error on server (don't echo raw error in production)
            error_log('Admin login db error: ' . $e->getMessage());
            $error = 'An internal error occurred. Try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<style>
  :root{--bg:#06121f;--card:rgba(255,255,255,0.03);--accent:#00d08a;--err:#ff6b62}
  body{margin:0;background:radial-gradient(circle at top, rgba(0,180,255,0.06), #041020 60%);font-family:Inter,Arial,Helvetica,sans-serif;color:#eaf6ff}
  .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px}
  .card{width:380px;background:var(--card);border-radius:14px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.6);border:1px solid rgba(255,255,255,0.04)}
  h1{margin:0 0 12px;font-size:1.3rem;text-align:center;color:#eaf6ff}
  label{display:block;margin:12px 0 6px;color:#cfe2f5;font-weight:600}
  input[type=email],input[type=password]{width:100%;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);color:#eaf6ff;box-sizing:border-box}
  .btn{width:100%;margin-top:18px;padding:12px;border-radius:10px;border:0;background:linear-gradient(90deg,#12c26b,var(--accent));color:#02141a;font-weight:800;cursor:pointer}
  .err{background:var(--err);color:#fff;padding:10px;border-radius:8px;margin-bottom:12px;text-align:center;font-weight:700}
  .small{margin-top:10px;text-align:center;color:#9fb0c8;font-size:0.95rem}
</style>
</head>
<body>
<div class="wrap">
  <div class="card" role="main" aria-labelledby="admin-title">
    <h1 id="admin-title">Admin Login</h1>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input id="password" type="password" name="password" required>

      <button class="btn" type="submit" name="login">Login</button>
    </form>

    <div class="small">If login fails, ensure admin user exists and password is correct.</div>
  </div>
</div>
</body>
</html>
