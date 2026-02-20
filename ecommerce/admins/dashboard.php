<?php
// admin/dashboard.php (replace your current dashboard.php with this file)
// -- expects: ../includes/db.php and admin session stored in $_SESSION['admin_id']

session_start();
include '../includes/db.php';

// require admin session
if (empty($_SESSION['admin_id'])) {
    header('Location: ../admins/login.php'); // adjust if your admin login path differs
    exit;
}

// flash message (one-time)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Fetch DB stats & products
try {
    $total_products = (int)$conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $total_users = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_cart = (int)$conn->query("SELECT COUNT(*) FROM cart")->fetchColumn();

    $stmt = $conn->prepare("SELECT id, name, price, image, description FROM products ORDER BY id DESC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Dashboard DB error: ' . $e->getMessage());
    $total_products = $total_users = $total_cart = 0;
    $products = [];
}

function esc($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; ?>
  <div style="padding:10px;border-radius:8px;margin:12px 0;background:<?= $f['type']==='success' ? '#052d18' : '#3a0f12' ?>;color:<?= $f['type']==='success' ? '#9fffae' : '#ffb7b0' ?>;">
    <?= htmlspecialchars($f['msg']) ?>
  </div>
<?php unset($_SESSION['flash']); endif; ?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Dashboard</title>
<style>
:root{--bg:#041021;--card:#071426;--muted:#9fb0c8;--accent:#00d08a}
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(180deg,#051126,#02101b);color:#eaf6ff;font-family:Inter,Arial,Helvetica,sans-serif}
.header{height:72px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;background:linear-gradient(90deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));border-bottom:1px solid rgba(255,255,255,0.03)}
.brand{font-weight:800;font-size:1.15rem}
.top-nav{display:flex;gap:12px;align-items:center;color:var(--muted)}
.container{display:flex;gap:20px;padding:22px}
.sidebar{width:220px;background:var(--card);border-radius:12px;padding:14px}
.nav-item{display:block;color:#cfe2f5;padding:8px;border-radius:8px;text-decoration:none;margin-bottom:8px;font-weight:700}
.main{flex:1}
.stats{display:flex;gap:14px;margin-bottom:18px;flex-wrap:wrap}
.stat{flex:1;min-width:150px;background:rgba(255,255,255,0.02);padding:14px;border-radius:10px}
.card{background:var(--card);padding:16px;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,0.6)}
.table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:12px 10px;text-align:left;color:#cfe2f5}
th{color:var(--muted);font-weight:700}
.prod-thumb{width:72px;height:48px;object-fit:cover;border-radius:8px;background:#ffffff10}
.form-row{display:flex;gap:10px;align-items:center}
.input{padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:#eaf6ff}
.btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:800}
.btn-primary{background:linear-gradient(90deg,#12c26b,var(--accent));color:#02141a}
.btn-danger{background:#ff6b62;color:#fff}
.flash{padding:12px;border-radius:10px;margin:10px 0;font-weight:700}
</style>
</head>
<body>

<header class="header">
  <div class="brand">Admin Dashboard</div>
  <div class="top-nav">
    Signed in as <strong style="margin-left:8px;color:#bfeedd"><?= esc($_SESSION['admin_email'] ?? 'admin') ?></strong>
    <form method="POST" action="../admins/logout.php" style="margin-left:16px;">
      <button class="btn" name="logout" type="submit">Logout</button>
    </form>
  </div>
</header>

<div class="container">
  <aside class="sidebar">
    <a class="nav-item" href="dashboard.php">Overview</a>
    <a class="nav-item" href="/ecommerce/pages/cart.php">Orders</a>
    <a class="nav-item" href="/ecommerce/index.php">Shop</a>
    <div style="height:14px"></div>
    <a class="nav-item" href="/ecommerce/admins/addProduct.php">Quick Add Product</a>
  </aside>

  <main class="main">
    <?php if ($flash): ?>
      <div class="flash" style="background:<?= $flash['type']==='success' ? '#052d18' : '#3a0f12' ?>; color:<?= $flash['type']==='success' ? '#9fffae' : '#ffb7b0' ?>;">
        <?= esc($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <div class="stats">
      <div class="stat card"><div style="color:#9fb0c8">Products</div><div style="font-weight:900;font-size:1.3rem"><?= $total_products ?></div></div>
      <div class="stat card"><div style="color:#9fb0c8">Users</div><div style="font-weight:900;font-size:1.3rem"><?= $total_users ?></div></div>
      <div class="stat card"><div style="color:#9fb0c8">Cart Rows</div><div style="font-weight:900;font-size:1.3rem"><?= $total_cart ?></div></div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Products</h2>
        <a href="manageProducts.php" class="btn btn-primary">Manage Products</a>
      </div>

      <table class="table" aria-live="polite">
        <thead>
          <tr>
            <th style="width:80px">Image</th>
            <th>Name</th>
            <th style="width:120px">Price</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr><td colspan="4" style="padding:18px;color:#9fb0c8">No products found.</td></tr>
          <?php else: foreach ($products as $p): ?>
            <tr>
              <td>
                <?php if (!empty($p['image'])): ?>
                  <img src="../images/<?= esc($p['image']) ?>" alt="<?= esc($p['name']) ?>" class="prod-thumb">
                <?php else: ?>
                  <div class="prod-thumb" style="background:#ffffff0a"></div>
                <?php endif; ?>
              </td>
              <td><?= esc($p['name']) ?></td>
              <td>$<?= number_format((float)$p['price'],2) ?></td>
              <td><?= esc(strlen($p['description'])>120 ? substr($p['description'],0,120).'...' : $p['description']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- QUICK ADD FORM (posts to pages/add_product.php handler; handler does PRG) -->
    <div id="quick-add" class="card" style="margin-top:18px">
      <h3 style="margin:0 0 12px">Quick Add Product</h3>
      <form id="quick-add-form" method="POST" action="../admins/addProduct.php" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px">
        <div class="form-row">
          <input class="input" type="text" name="name" placeholder="Product name" required>
          <input class="input" type="number" step="0.01" name="price" placeholder="Price" required style="width:140px">
        </div>

        <textarea class="input" name="description" placeholder="Short description" rows="3"></textarea>

        <input type="file" name="image" accept="image/*">

        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button id="quick-add-btn" class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>

  </main>
</div>

<script>
  // disable button after submit - prevents double-click
  (function(){
    var f = document.getElementById('quick-add-form');
    if (!f) return;
    f.addEventListener('submit', function(){
      var b = document.getElementById('quick-add-btn');
      if (b) { b.disabled = true; b.textContent = 'Saving...'; }
    }, {passive:true});
  })();
</script>

</body>
</html>
