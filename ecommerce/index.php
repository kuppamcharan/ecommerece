<?php
// index.php - corrected, safe, drop-in replacement
session_start();

// logout (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: /ecommerce/pages/login.php');
    exit;
}

// DB connection (adjust path if your includes folder differs)
include __DIR__ . '/includes/db.php';

// Fetch products (simple, read-only)
try {
    $stmt = $conn->query("SELECT id, name, price, image, description FROM products ORDER BY id ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // avoid leaking DB errors to users
    $products = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Online Store</title>

  <!-- fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;800&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  <!-- main stylesheet (use absolute path to avoid relative issues) -->
  <link rel="stylesheet" href="/ecommerce/css/style.css">

  <style>
    /* Small inline fixes to ensure header/nav remain clickable above any hero/banner */
    header { position: relative; z-index: 1200; }
    .welcome-section, .hero, .top-banner { pointer-events: none; } /* avoid overlay blocking nav */
    .site-nav { pointer-events: auto; display:flex; gap:16px; align-items:center; }
    .site-nav a, .site-nav form button { color: #dfeef3; text-decoration:none; font-weight:700; background:transparent; border:0; cursor:pointer; }
  </style>
</head>
<body>

<header>
  <div class="header-container">
    <div class="welcome-section">
      <h1>Welcome to Our Store</h1>
    </div>

    <nav class="site-nav" aria-label="Main navigation">
      <a href="/ecommerce/index.php">Home</a>

      <?php if (empty($_SESSION['user_id'])): ?>
        <a id="nav-login" href="/ecommerce/pages/login.php">Login</a>
        <a id="nav-register" href="/ecommerce/pages/register.php">Register</a>
      <?php else: ?>
        <span style="color:#dfeef3; font-weight:700; margin-right:10px;">Hi, <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></span>
        <form method="POST" action="/ecommerce/index.php" style="display:inline;">
          <button type="submit" name="logout" aria-label="Logout">Logout</button>
        </form>
      <?php endif; ?>

      <a href="/ecommerce/pages/cart.php" style="margin-left:12px;">Cart</a>
    </nav>
  </div>
</header>

<main class="products-container" role="main" aria-labelledby="products-heading">
  <h2 id="products-heading" class="products-title">Products</h2>

  <div class="product-list" role="list">
    <?php if (empty($products)): ?>
      <p style="color: #cbd6df;">No products available right now.</p>
    <?php else: ?>
      <?php foreach ($products as $p): ?>
        <article class="product" role="listitem" aria-labelledby="product-<?= (int)$p['id'] ?>">
          <?php
            $img = !empty($p['image']) ? "/ecommerce/images/" . rawurlencode($p['image']) : '/ecommerce/images/placeholder.png';
          ?>
          <div class="product-media">
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-image" loading="lazy">
          </div>

          <h3 id="product-<?= (int)$p['id'] ?>" class="product-title"><?= htmlspecialchars($p['name']) ?></h3>

          <div class="product-meta">
            <div class="price">Price: $<?= number_format((float)$p['price'], 2) ?></div>
            <?php if (!empty($p['description'])): ?>
              <p class="product-desc"><?= nl2br(htmlspecialchars($p['description'])) ?></p>
            <?php endif; ?>
          </div>

          <form method="POST" action="/ecommerce/pages/cart.php" class="product-form" aria-label="Add <?= htmlspecialchars($p['name']) ?> to cart">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="_return" value="/ecommerce/index.php">
            <button type="submit" name="add_to_cart" class="add-to-cart-button">Add to Cart</button>
          </form>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<footer>
  <p>&copy; <?= date('Y') ?> Online Store. All rights reserved.</p>
</footer>

<script>
/* JS fallback: some overlays on page can still grab clicks — ensure nav links actually navigate */
document.querySelectorAll('#nav-login, #nav-register').forEach(function(el){
  el.addEventListener('click', function(ev){
    // small delay to allow default behavior; if not navigated, force it
    setTimeout(function(){
      var href = el.getAttribute('href');
      if (!href) return;
      if (location.pathname.indexOf(href.replace('/ecommerce','')) === -1) {
        window.location.href = href;
      }
    }, 20);
  }, {passive:true});
});
</script>

</body>
</html>
