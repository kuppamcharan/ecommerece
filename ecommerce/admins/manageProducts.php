<?php
session_start();
include '../includes/db.php';

// Require admin
if (empty($_SESSION['admin_id'])) {
    header("Location: ../admins/login.php");
    exit();
}

// Fetch products
$stmt = $conn->prepare("SELECT * FROM products ORDER BY id DESC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products</title>

    <style>
        body {
            margin: 0;
            font-family: Inter, sans-serif;
            background: linear-gradient(180deg, #051126, #020c17);
            color: #eaf6ff;
        }

        .container {
            max-width: 1100px;
            margin: 50px auto;
            padding: 20px;
        }

        h2 {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: #00d08a;
            margin-bottom: 25px;
        }

        .table-card {
            background: rgba(255, 255, 255, 0.03);
            padding: 25px;
            border-radius: 18px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(12px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            padding: 14px 10px;
            text-align: left;
            font-size: 0.95rem;
            color: #cfe2f5;
        }

        th {
            font-weight: 700;
            color: #9fb0c8;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        tr {
            transition: 0.2s;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .prod-img {
            width: 70px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
        }

        .actions button {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            transition: 0.2s;
        }

        .edit-btn {
            background: linear-gradient(90deg, #12c26b, #00d08a);
            color: #02141a;
        }

        .edit-btn:hover {
            opacity: 0.9;
        }

        .delete-btn {
            background: #ff6b62;
            color: #fff;
        }

        .delete-btn:hover {
            opacity: 0.9;
        }

        .top-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: linear-gradient(90deg, #12c26b, #00d08a);
            color: #02141a;
            border-radius: 10px;
            font-weight: 800;
            text-decoration: none;
            transition: 0.2s;
        }

        .top-btn:hover {
            transform: scale(1.03);
        }

    </style>
</head>

<body>

    <div class="container">

        <h2>Manage Products</h2>

        <a href="/ecommerce/admins/addProduct.php" class="top-btn" id="add-prod-link">+ Add New Product</a>
        <script>
        // quick guard — when clicked, open the absolute URL in a new tab to inspect error if any.
        document.getElementById('add-prod-link')?.addEventListener('click', function(e){
            // uncomment the next line to force new tab during debugging:
            // window.open(this.href, '_blank');
            // otherwise allow normal navigation
        }, {passive:true});
        </script>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">Image</th>
                        <th>Name</th>
                        <th style="width: 120px;">Price</th>
                        <th style="width: 250px;">Description</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:20px; color:#9fb0c8;">No Products Found</td>
                        </tr>

                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="../images/<?= htmlspecialchars($p['image']) ?>" class="prod-img">
                                    <?php else: ?>
                                        <div style="width:70px; height:50px; background:#ffffff09; border-radius:8px;"></div>
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td>$<?= number_format($p['price'], 2) ?></td>
                                <td><?= htmlspecialchars(strlen($p['description']) > 80 ? substr($p['description'], 0, 80) . "..." : $p['description']) ?></td>

                                <td class="actions">
                                    <a href="edit_page.php?id=<?= $p['id'] ?>">
                                        <button class="edit-btn">Edit</button>
                                    </a>
                                    <a href="delete_page.php?id=<?= $p['id'] ?>" onclick="return confirm('Delete this product?')">
                                        <button class="delete-btn">Delete</button>
                                    </a>

                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

            </table>
            

        </div>

    </div>
</body>

</html>
