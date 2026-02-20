<?php
// pages/delete_product.php
// Safe product delete handler — drop-in replacement.
// Put this file at /ecommerce/pages/delete_product.php (adjust paths if different)

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// adjust include path if needed
require_once __DIR__ . '/../includes/db.php';

// require admin
if (empty($_SESSION['admin_id'])) {
    // AJAX -> JSON, otherwise redirect to admin login
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
    } else {
        header('Location: /ecommerce/pages/login.php');
    }
    exit;
}

// allow id via POST (preferred) or GET
$product_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
} else {
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
}

if ($product_id <= 0) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid product id']);
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid product id'];
        header('Location: /ecommerce/admins/dashboard.php');
    }
    exit;
}

try {
    // Begin transaction
    $conn->beginTransaction();

    // Fetch product to get image filename
    $stmt = $conn->prepare("SELECT id, image FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // nothing to delete
        $conn->rollBack();
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Product not found'];
            header('Location: /ecommerce/admins/dashboard.php');
        }
        exit;
    }

    // Delete any cart rows that reference this product (prevents orphans)
    $delCart = $conn->prepare("DELETE FROM cart WHERE product_id = ?");
    $delCart->execute([$product_id]);

    // Delete product row
    $del = $conn->prepare("DELETE FROM products WHERE id = ?");
    $del->execute([$product_id]);

    // Commit DB change
    $conn->commit();

    // Remove image file (best effort)
    if (!empty($product['image'])) {
        $imageFile = realpath(__DIR__ . '/../images/' . basename($product['image']));
        // safety: ensure imageFile is inside images folder
        $imagesDir = realpath(__DIR__ . '/../images/');
        if ($imageFile && strpos($imageFile, $imagesDir) === 0 && is_file($imageFile) && is_writable($imageFile)) {
            @unlink($imageFile);
        }
    }

    // Response: AJAX or redirect with flash
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        echo json_encode(['success' => true, 'message' => 'Product deleted']);
        exit;
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Product deleted'];
        header('Location: /ecommerce/admins/dashboard.php');
        exit;
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Delete product error: ' . $e->getMessage());

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Server error deleting product'];
        header('Location: /ecommerce/admins/dashboard.php');
    }
    exit;
}
