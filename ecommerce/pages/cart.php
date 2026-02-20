<?php
// pages/cart.php - improved cart: merge, clear, DB/session fallback, prettier UI
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// adjust if your db include lives elsewhere
include '../includes/db.php';

// ---------- helpers ----------
function fetch_product($conn, $id) {
    $stmt = $conn->prepare("SELECT id, name, price, image, description FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function db_add_item($conn, $user_id, $product_id, $qty = 1) {
    $check = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1");
    $check->execute([(int)$user_id, (int)$product_id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $upd = $conn->prepare("UPDATE cart SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([(int)$qty, (int)$row['id']]);
    } else {
        $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $ins->execute([(int)$user_id, (int)$product_id, (int)$qty]);
    }
}

function db_update_qty($conn, $cart_id, $qty) {
    if ((int)$qty <= 0) {
        $del = $conn->prepare("DELETE FROM cart WHERE id = ?");
        $del->execute([(int)$cart_id]);
    } else {
        $upd = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([(int)$qty, (int)$cart_id]);
    }
}

function db_remove($conn, $cart_id) {
    $del = $conn->prepare("DELETE FROM cart WHERE id = ?");
    $del->execute([(int)$cart_id]);
}

function clear_db_cart_for_user($conn, $user_id) {
    $del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $del->execute([(int)$user_id]);
}

// ---------- MERGE session cart into DB if user logged in ----------
// This ensures guest cart items are preserved after login.
if (!empty($_SESSION['user_id']) && !empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $pid => $it) {
        $qty = isset($it['qty']) ? (int)$it['qty'] : (int)($it['quantity'] ?? 1);
        if ($qty > 0) db_add_item($conn, (int)$_SESSION['user_id'], (int)$pid, $qty);
    }
    // After merging, clear session cart to avoid duplication
    unset($_SESSION['cart']);
}

// ---------- Handle POST actions ----------
// Add to cart (from product listing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $qty = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $return = $_POST['_return'] ?? '/ecommerce/index.php';

    if ($product_id > 0) {
        if (!empty($_SESSION['user_id'])) {
            db_add_item($conn, (int)$_SESSION['user_id'], $product_id, $qty);
        } else {
            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['qty'] += $qty;
            } else {
                $prod = fetch_product($conn, $product_id);
                $_SESSION['cart'][$product_id] = [
                    'id' => $product_id,
                    'name' => $prod ? $prod['name'] : 'Item',
                    'price' => $prod ? (float)$prod['price'] : 0.0,
                    'image' => $prod['image'] ?? '',
                    'qty' => $qty
                ];
            }
        }
    }
    header('Location: ' . $return);
    exit;
}

// Update quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    if (!empty($_SESSION['user_id']) && !empty($_POST['cart_id'])) {
        db_update_qty($conn, (int)$_POST['cart_id'], $qty);
    } else {
        $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        if (isset($_SESSION['cart'][$pid])) {
            if ($qty <= 0) unset($_SESSION['cart'][$pid]);
            else $_SESSION['cart'][$pid]['qty'] = $qty;
        }
    }
    header('Location: /ecommerce/pages/cart.php');
    exit;
}

// Remove single item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    if (!empty($_SESSION['user_id']) && !empty($_POST['cart_id'])) {
        db_remove($conn, (int)$_POST['cart_id']);
    } else {
        $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        if (isset($_SESSION['cart'][$pid])) unset($_SESSION['cart'][$pid]);
    }
    header('Location: /ecommerce/pages/cart.php');
    exit;
}

// Clear whole cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cart'])) {
    if (!empty($_SESSION['user_id'])) {
        clear_db_cart_for_user($conn, (int)$_SESSION['user_id']);
    }
    unset($_SESSION['cart']);
    header('Location: /ecommerce/pages/cart.php');
    exit;
}

// ---------- Build items to display ----------
$cart_items = [];
if (!empty($_SESSION['user_id'])) {
    // load DB cart for this user
    $stmt = $conn->prepare("SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.price, p.image
                            FROM cart c
                            JOIN products p ON p.id = c.product_id
                            WHERE c.user_id = ?");
    $stmt->execute([ (int)$_SESSION['user_id'] ]);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $r) {
        $cart_items[] = [
            'cart_id' => (int)$r['cart_id'],
            'product_id' => (int)$r['product_id'],
            'name' => $r['name'],
            'price' => (float)$r['price'],
            'image' => $r['image'],
            'qty' => (int)$r['quantity']
        ];
    }
} else {
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    foreach ($_SESSION['cart'] as $pid => $it) {
        $cart_items[] = [
            'cart_id' => null,
            'product_id' => (int)$pid,
            'name' => $it['name'],
            'price' => (float)$it['price'],
            'image' => $it['image'] ?? '',
            'qty' => (int)$it['qty']
        ];
    }
}

// Totals
$subtotal = 0.0;
foreach ($cart_items as $it) $subtotal += ($it['price'] * $it['qty']);
$shipping = (count($cart_items) > 0) ? 9.99 : 0.00;
$tax = round($subtotal * 0.07, 2);
$total = $subtotal + $shipping + $tax;

function money($n) { return '$' . number_format((float)$n, 2); }

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Your Cart — Online Store</title>
<link rel="stylesheet" href="../css/style.css">
<style>
/* Cart page styles (keeps with your dark glossy theme) */
body{background:linear-gradient(180deg,#051126 0%, #02101b 100%); color:#eaf6ff; font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin:0}
.header-container{display:flex;justify-content:space-between;align-items:center;padding:20px 36px;background:linear-gradient(90deg,#052233,#0a2a36); border-bottom:1px solid rgba(255,255,255,0.03)}
.header-container h1{margin:0;font-family:Poppins, sans-serif;font-size:2rem}
.cart-page{max-width:1150px;margin:36px auto;padding:0 18px}
.cart-grid{display:flex;gap:28px;align-items:flex-start}
.cart-items{flex:1}
.cart-summary{width:340px;background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); padding:22px;border-radius:12px;box-shadow:0 18px 40px rgba(2,6,23,0.6);color:#eaf6ff}
.cart-row{display:flex;gap:18px;align-items:center;margin-bottom:18px;background:rgba(255,255,255,0.02);padding:18px;border-radius:10px;box-shadow: inset 0 2px 8px rgba(0,0,0,0.4)}
.cart-row img{width:120px;height:80px;object-fit:cover;border-radius:8px}
.cart-meta{flex:1}
.qty-input{width:64px;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:rgba(255,255,255,0.02);color:#eaf6ff}
.btn-small{padding:8px 12px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
.btn-update{background:linear-gradient(90deg,#12c26b,#00d08a);color:#021322}
.btn-remove{background:#ff6b62;color:#fff}
.clear-btn{background:transparent;border:1px dashed rgba(255,255,255,0.06);color:#ff8f8a;padding:8px 12px;border-radius:8px;cursor:pointer}
.empty{color:#bfcbd8;padding:24px;background:rgba(255,255,255,0.02);border-radius:8px}
.checkout-btn{width:100%;padding:12px;border-radius:10px;border:0;background:linear-gradient(90deg,#12c26b,#00d08a);color:#021322;font-weight:900;cursor:pointer}
.small{color:#9fb0c8}
a.cont{color:#00d08a;text-decoration:underline;font-weight:700}
@media (max-width:1000px){ .cart-grid{flex-direction:column} .cart-summary{width:100%} }
</style>
</head>
<body>
  <header class="header-container">
    <h1>Welcome to Our Store</h1>
    <nav style="display:flex;gap:16px;align-items:center">
      <a href="/ecommerce/index.php" style="color:#dfeef3;text-decoration:none;font-weight:700">Home</a>
      <a href="/ecommerce/pages/login.php" style="color:#dfeef3;text-decoration:none;font-weight:700">Login</a>
      <a href="/ecommerce/pages/register.php" style="color:#dfeef3;text-decoration:none;font-weight:700">Register</a>
    </nav>
  </header>

  <main class="cart-page">
    <h2 style="margin-bottom:18px;">Your Cart</h2>

    <!-- Back to shopping button -->
    <div style="margin-bottom:20px;">
      <a href="/ecommerce/index.php" 
        style="
          display:inline-block;
          padding:10px 16px;
          border-radius:8px;
          background:linear-gradient(90deg,#12c26b,#00d08a);
          color:#021322;
          text-decoration:none;
          font-weight:800;
          box-shadow:0 6px 16px rgba(0,0,0,0.4);
        ">
        ← Back to Shopping
      </a>
    </div>

    <div class="cart-grid">
      <div class="cart-items">
        <?php if (empty($cart_items)): ?>
          <div class="empty">Your cart is empty. <a class="cont" href="/ecommerce/index.php">Continue shopping</a></div>
        <?php else: foreach ($cart_items as $item): ?>
          <div class="cart-row" role="article" aria-labelledby="cart-item-<?= (int)$item['product_id'] ?>">
            <?php if (!empty($item['image'])): ?>
              <img src="/ecommerce/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
            <?php else: ?>
              <div style="width:120px;height:80px;background:#ffffff10;border-radius:8px"></div>
            <?php endif; ?>

            <div class="cart-meta">
              <h3 id="cart-item-<?= (int)$item['product_id'] ?>" style="margin:0 0 6px 0; color:#eaf6ff;"><?= htmlspecialchars($item['name']) ?></h3>
              <div style="color:#9fb0c8; font-weight:700;"><?= money($item['price']) ?> × <?= (int)$item['qty'] ?></div>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
              <form method="POST" style="display:flex;gap:8px;align-items:center;">
                <?php if (!empty($item['cart_id'])): ?>
                  <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                <?php else: ?>
                  <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                <?php endif; ?>
                <input class="qty-input" type="number" name="quantity" value="<?= (int)$item['qty'] ?>" min="0">
                <button type="submit" name="update_qty" class="btn-small btn-update">Update</button>
              </form>

              <form method="POST" style="margin-top:6px;">
                <?php if (!empty($item['cart_id'])): ?>
                  <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                <?php else: ?>
                  <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                <?php endif; ?>
                <button type="submit" name="remove_item" class="btn-small btn-remove">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <aside class="cart-summary" aria-labelledby="summary-title">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div class="small">Summary</div>
          <form method="POST" onsubmit="return confirm('Clear entire cart?');">
            <button type="submit" name="clear_cart" class="clear-btn">Clear Cart</button>
          </form>
        </div>

        <div style="margin-bottom:12px; color:#9fb0c8;">Subtotal <strong style="float:right;color:#eaf6ff;"><?= money($subtotal) ?></strong></div>
        <div style="margin-bottom:12px; color:#9fb0c8;">Shipping <strong style="float:right;color:#eaf6ff;"><?= money($shipping) ?></strong></div>
        <div style="margin-bottom:18px; color:#9fb0c8;">Tax <strong style="float:right;color:#eaf6ff;"><?= money($tax) ?></strong></div>

        <div style="font-weight:900; font-size:1.15rem; margin-bottom:12px; color:#fff;">Total <span style="float:right;color:#00d08a;"><?= money($total) ?></span></div>

        <form method="POST" action="/ecommerce/pages/checkout.php" style="margin-top:6px;">
          <button type="submit" class="checkout-btn">Proceed to Checkout</button>
        </form>
      </aside>
    </div>
  </main>

  <footer style="margin-top:60px;padding:18px 36px;text-align:center;color:#9fb0c8;">
    &copy; <?= date('Y') ?> Online Store
  </footer>
</body>
</html>
