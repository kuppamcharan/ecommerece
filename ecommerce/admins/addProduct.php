<?php
session_start();
include '../includes/db.php';

// Require admin login
if (empty($_SESSION['admin_id'])) {
    header("Location: ../admins/login.php");
    exit();
}

// Handle form
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $price = trim($_POST["price"]);
    $description = trim($_POST["description"]);
    $imageName = "";

    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "../images/";
        $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $imageName = uniqid("prod_", true) . "." . $ext;
        move_uploaded_file($_FILES["image"]["tmp_name"], $targetDir . $imageName);
    }

    $query = $conn->prepare("INSERT INTO products (name, price, description, image, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($query->execute([$name, $price, $description, $imageName])) {
        $message = "✔ Product added successfully!";
    } else {
        $message = "❌ Failed to add product.";
    }
}
?>

<?php
// pages/add_product.php
session_start();
include '../includes/db.php';

// require admin
if (empty($_SESSION['admin_id'])) {
    header('Location: ../admins/login.php'); // adjust if needed
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ecommerce/admins/dashboard.php');
    exit;
}

// sanitize inputs
$name = trim($_POST['name'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$description = trim($_POST['description'] ?? '');
$imageName = '';

// validate
if ($name === '' || $price <= 0) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Name and valid price required.'];
    header('Location: /ecommerce/admins/dashboard.php');
    exit;
}

try {
    // duplicate check by name (adjust if name not unique in your app)
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
    $check->execute([$name]);
    if ($check->fetch()) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Product already exists'];
        header('Location: /ecommerce/admins/dashboard.php');
        exit;
    }

    // handle image upload if present
    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $allowedExt = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid image type'];
            header('Location: /ecommerce/admins/dashboard.php');
            exit;
        }
        $imageName = uniqid('p_', true) . '.' . $ext;
        $targetDir = __DIR__ . '/../images/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        move_uploaded_file($_FILES['image']['tmp_name'], $targetDir . $imageName);
    }

    // insert
    $ins = $conn->prepare("INSERT INTO products (name, price, description, image, created_at) VALUES (?, ?, ?, ?, NOW())");
    $ins->execute([$name, $price, $description, $imageName]);

    $_SESSION['flash'] = ['type'=>'success','msg'=>'Product added'];
    header('Location: /ecommerce/admins/dashboard.php'); // PRG
    exit;

} catch (Exception $e) {
    error_log('Add product error: ' . $e->getMessage());
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Server error'];
    header('Location: /ecommerce/admins/dashboard.php');
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Product</title>

<style>
    body {
        margin: 0;
        font-family: Inter, sans-serif;
        background: linear-gradient(180deg, #051126, #020c17);
        color: #eaf6ff;
    }

    .container {
        max-width: 600px;
        margin: 70px auto;
        background: rgba(255, 255, 255, 0.03);
        padding: 30px;
        border-radius: 18px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        backdrop-filter: blur(12px);
    }

    h2 {
        margin: 0 0 18px;
        font-size: 1.7rem;
        text-align: center;
        font-weight: 800;
        color: #00d08a;
    }

    .message {
        text-align: center;
        font-weight: 700;
        margin-bottom: 18px;
        color: #00d08a;
    }

    label {
        font-weight: 700;
        margin-bottom: 6px;
        display: block;
        color: #cfe2f5;
    }

    input, textarea {
        width: 100%;
        padding: 12px;
        margin-bottom: 16px;
        border-radius: 10px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        color: #eaf6ff;
        font-size: 15px;
        outline: none;
    }

    textarea {
        resize: none;
        height: 100px;
    }

    input[type="file"] {
        padding: 10px;
        background: rgba(255,255,255,0.02);
    }

    button {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        background: linear-gradient(90deg, #12c26b, #00d08a);
        font-size: 1rem;
        font-weight: 800;
        color: #02141a;
        transition: 0.2s;
    }

    button:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }

    .back {
        margin-top: 16px;
        display: block;
        text-align: center;
        font-weight: 700;
        color: #9fb0c8;
        text-decoration: none;
    }

    .back:hover {
        color: #00d08a;
    }
</style>
</head>

<body>
<div class="container">
    <h2>Add New Product</h2>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Product Name</label>
        <input type="text" name="name" required>

        <label>Price</label>
        <input type="number" step="0.01" name="price" required>

        <label>Description</label>
        <textarea name="description" required></textarea>

        <label>Product Image</label>
        <input type="file" name="image">

        <button type="submit">Add Product</button>
    </form>

    <a class="back" href="../admins/dashboard.php">⟵ Back to Dashboard</a>
</div>
</body>
</html>
