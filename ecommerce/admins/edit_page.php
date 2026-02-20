<?php
// pages/edit_product.php
// Edit product page & handler (drop-in).
// Usage: /ecommerce/pages/edit_product.php?id=123
// After POST it redirects to /ecommerce/admin/dashboard.php (PRG).

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// must include DB (adjust path if needed)
require_once __DIR__ . '/../includes/db.php';

// require admin
if (empty($_SESSION['admin_id'])) {
    header('Location: /ecommerce/admins/login.php');
    exit;
}

// helper: escape
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// product id (from GET or POST)
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid product id'];
    header('Location: /ecommerce/admins/dashboard.php');
    exit;
}

// POST = update action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // basic validation
    if ($name === '' || $price <= 0) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Name and valid price are required.'];
        header('Location: /ecommerce/pages/edit_product.php?id=' . $id);
        exit;
    }

    try {
        // fetch current product (to know current image)
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Product not found'];
            header('Location: /ecommerce/admins/dashboard.php');
            exit;
        }
        $currentImage = $row['image'] ?? '';

        // Handle image upload (optional replace)
        $newImageName = $currentImage;
        if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $allowedExt = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid image type. Allowed: jpg,png,webp.'];
                header('Location: /ecommerce/pages/edit_product.php?id=' . $id);
                exit;
            }

            // create images dir if missing
            $imagesDir = realpath(__DIR__ . '/../images') ?: (__DIR__ . '/../images');
            if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);

            $newImageName = uniqid('p_', true) . '.' . $ext;
            $tmpPath = $_FILES['image']['tmp_name'];
            $targetPath = rtrim($imagesDir, '/\\') . '/' . $newImageName;

            if (!move_uploaded_file($tmpPath, $targetPath)) {
                $_SESSION['flash'] = ['type'=>'error','msg'=>'Failed to move uploaded image.'];
                header('Location: /ecommerce/pages/edit_product.php?id=' . $id);
                exit;
            }
        }

        // Update DB (prepared)
        $upd = $conn->prepare("UPDATE products SET name = ?, price = ?, description = ?, image = ? WHERE id = ?");
        $upd->execute([$name, $price, $description, $newImageName, $id]);

        // If image replaced, try to remove old file (best-effort, non-fatal)
        if ($newImageName !== $currentImage && !empty($currentImage)) {
            $imagesDir = realpath(__DIR__ . '/../images');
            $oldPath = $imagesDir ? $imagesDir . '/' . basename($currentImage) : __DIR__ . '/../images/' . basename($currentImage);
            if ($oldPath && file_exists($oldPath) && is_writable($oldPath)) {
                @unlink($oldPath);
            }
        }

        $_SESSION['flash'] = ['type'=>'success','msg'=>'Product updated'];
        header('Location: /ecommerce/admins/dashboard.php');
        exit;

    } catch (Exception $e) {
        error_log('Edit product error: ' . $e->getMessage());
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Server error while updating product'];
        header('Location: /ecommerce/pages/edit_product.php?id=' . $id);
        exit;
    }
}

// GET = show current product in form
try {
    $stmt = $conn->prepare("SELECT id, name, price, description, image FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Product not found'];
        header('Location: /ecommerce/admins/dashboard.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Fetch product error: ' . $e->getMessage());
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Server error'];
    header('Location: /ecommerce/admins/dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Edit Product — <?= esc($product['name']) ?></title>
<style>
:root{--bg:#041021;--card:#071426;--muted:#9fb0c8;--accent:#00d08a}
body{margin:0;background:linear-gradient(180deg,#051126,#02101b);color:#eaf6ff;font-family:Inter,Arial,Helvetica,sans-serif}
.container{max-width:820px;margin:36px auto;padding:20px}
.card{background:rgba(255,255,255,0.03);padding:20px;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,0.6)}
h1{margin:0 0 12px;color:#00d08a}
.form-row{display:flex;gap:12px;align-items:center;margin-bottom:12px}
.input, textarea{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:#eaf6ff}
textarea{min-height:100px;resize:vertical}
.prod-img{width:130px;height:90px;object-fit:cover;border-radius:8px;background:#ffffff08}
.btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:800}
.btn-primary{background:linear-gradient(90deg,#12c26b,var(--accent));color:#02141a}
.btn-muted{background:#ffffff07;color:#cfe2f5}
.small{color:var(--muted)}
.flash{padding:10px;border-radius:8px;margin-bottom:12px}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Edit Product</h1>

    <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="flash" style="background:<?= $f['type']==='success' ? '#052d18' : '#3a0f12' ?>;color:<?= $f['type']==='success' ? '#9fffae' : '#ffb7b0' ?>;font-weight:700;">
        <?= esc($f['msg']) ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

      <label class="small">Name</label>
      <input class="input" type="text" name="name" required value="<?= esc($product['name']) ?>">

      <div class="form-row">
        <div style="flex:1">
          <label class="small">Price</label>
          <input class="input" type="number" step="0.01" name="price" required value="<?= esc($product['price']) ?>">
        </div>
        <div style="width:160px;text-align:center">
          <label class="small">Current Image</label><br>
          <?php if (!empty($product['image'])): ?>
            <img src="/ecommerce/images/<?= esc($product['image']) ?>" class="prod-img" alt="">
          <?php else: ?>
            <div class="prod-img"></div>
          <?php endif; ?>
        </div>
      </div>

      <label class="small">Description</label>
      <textarea name="description" class="input"><?= esc($product['description']) ?></textarea>

      <label class="small">Replace Image (optional)</label>
      <input type="file" name="image" accept="image/*" class="input">

      <div style="margin-top:14px;display:flex;gap:10px;justify-content:flex-end">
        <a href="/ecommerce/admin/dashboard.php" class="btn btn-muted" style="text-decoration:none;padding:10px 14px;border-radius:8px;">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
