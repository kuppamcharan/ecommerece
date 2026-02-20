<?php
// pages/register.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// If already logged in, redirect to root index
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// DB include (pages/ -> go up one)
include '../includes/db.php';

$errors = [];
$success = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Basic validation
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $password2) {
        $errors[] = 'Passwords do not match.';
    }

    // If no validation errors, try to insert
    if (empty($errors)) {
        try {
            // Check duplicate email
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'An account with that email already exists.';
            } else {
                // Insert user with hashed password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $ins->execute([$email, $hash]);

                $success = true;
                // Redirect to login page with success flag
                header('Location: ../pages/login.php?registered=1');
                exit;
            }
        } catch (Exception $e) {
            error_log("Register DB error: " . $e->getMessage());
            $errors[] = 'An internal error occurred — try again later.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Create account — Online Store</title>
  <link rel="stylesheet" href="../css/style.css">
  <!-- ensure external CSS loads; inline fallback if it doesn't (temporary) -->
<link rel="stylesheet" href="../css/style.css">
<style>
/* minimal auth card fallback (temporary) */
.auth-wrap{max-width:980px;margin:42px auto;padding:18px}
.auth-card{width:100%;max-width:720px;margin:0 auto;background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));border-radius:14px;padding:28px;box-shadow:0 18px 40px rgba(2,6,23,0.6);color:#eaf6ff}
.auth-card h2{margin:0 0 14px 0;font-size:1.6rem;color:#fff;font-weight:800}
.auth-label{display:block;margin:8px 0 6px 0;color:#9fb0c8;font-weight:700}
.auth-input{width:100%;padding:10px 12px;margin-top:6px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);background:rgba(255,255,255,0.02);color:#eaf6ff}
.auth-btn{background:linear-gradient(90deg,#12c26b,#00d08a);color:#021322;border:0;padding:10px 16px;border-radius:10px;font-weight:800;cursor:pointer;box-shadow:0 10px 30px rgba(0,208,138,0.12)}
.auth-link{color:#00d08a;font-weight:700;text-decoration:underline}
.auth-error{background:rgba(255,99,71,0.06);color:#ffb6a7;padding:10px;border-radius:8px;margin-bottom:12px;border:1px solid rgba(255,99,71,0.08)}
@media(max-width:720px){.auth-card{padding:18px;margin:8px}.auth-wrap{padding:12px}}
</style>

</head>
<body>

<header>
  <div class="header-container">
    <div class="welcome-section"><h1>Welcome to Our Store</h1></div>
    <nav>
      <a href="../index.php">Home</a>
      <a href="login.php">Login</a>
    </nav>
  </div>
</header>

<main class="products-container">
  <section class="auth-wrap">
    <div class="auth-card" role="region" aria-labelledby="reg-title">
      <h2 id="reg-title">Create your account</h2>

      <?php if (!empty($errors)): ?>
        <div class="auth-error" role="alert">
          <?php foreach ($errors as $err): ?>
            <div><?= htmlspecialchars($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="auth-success">Registration successful. Redirecting to login…</div>
      <?php endif; ?>

      <form method="POST" autocomplete="on" novalidate>
        <label class="auth-label">
          Email
          <input class="auth-input" type="email" name="email" required value="<?= htmlspecialchars($email) ?>" placeholder="you@domain.com" />
        </label>

        <label class="auth-label">
          Password
          <input class="auth-input" type="password" name="password" required placeholder="At least 6 characters" />
        </label>

        <label class="auth-label">
          Confirm password
          <input class="auth-input" type="password" name="password2" required placeholder="Repeat password" />
        </label>

        <div style="display:flex; gap:12px; margin-top:18px; align-items:center;">
          <button type="submit" class="auth-btn">Create account</button>
          <a href="login.php" class="auth-link">Back to sign in</a>
        </div>
      </form>

    </div>
  </section>
</main>

</body>
</html>
