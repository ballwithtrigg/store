<?php
require_once __DIR__ . '/../config/database.php';

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header('Location: /tcs/admin/admin-login.php');
    exit;
}

$db = getDB();
$message = '';
$error = '';
$edit_product = null;
$view = $_GET['view'] ?? 'dashboard';

// ── 1. HANDLE FILE UPLOADS ──
function handleImageUpload($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
    
    $targetDir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFileName = uniqid('prod_', true) . '.' . $fileExt;
    $targetPath = $targetDir . $newFileName;
    
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($fileExt, $allowedExts)) return null;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return '/tcs/assets/uploads/' . $newFileName;
    }
    return null;
}

// ── 2. HANDLE POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add/Update Product
    if (isset($_POST['add_product']) || isset($_POST['update_product'])) {
        $name = trim($_POST['name']);
        $price = (float)$_POST['price'];
        $category_id = (int)$_POST['category_id'];
        $description = trim($_POST['description']);
        $stock_quantity = (int)$_POST['stock_quantity'];
        $sizes = array_map('trim', explode(',', $_POST['sizes'] ?? ''));
        $id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;

        try {
            $db->beginTransaction();
            
            if (isset($_POST['update_product']) && $id) {
                // Main Product Update
                $stmt = $db->prepare("UPDATE products SET name = ?, price = ?, category_id = ?, description = ?, stock_quantity = ? WHERE id = ?");
                $stmt->execute([$name, $price, $category_id, $description, $stock_quantity, $id]);
                $product_id = $id;
                
                // Re-sync sizes
                $db->prepare("DELETE FROM product_sizes WHERE product_id = ?")->execute([$id]);
                $message = 'Product updated successfully.';
            } else {
                // Add Main Product (placeholder image_url for legacy support if needed)
                $stmt = $db->prepare("INSERT INTO products (name, price, image_url, category_id, description, stock_quantity) VALUES (?, ?, '', ?, ?, ?)");
                $stmt->execute([$name, $price, $category_id, $description, $stock_quantity]);
                $product_id = $db->lastInsertId();
                $message = 'Product added successfully.';
            }

            // Handle Multiple Images (Up to 6)
            // If updating, we might want to keep old images or replace them. For simplicity, let's allow adding new ones.
            // If they provided fallback URLs
            $fallbackUrls = $_POST['image_urls'] ?? [];
            
            $allImages = [];
            
            // Process Uploads
            if (!empty($_FILES['product_images']['name'][0])) {
                foreach ($_FILES['product_images']['name'] as $i => $filename) {
                    if ($i >= 6) break;
                    $file = [
                        'name' => $_FILES['product_images']['name'][$i],
                        'type' => $_FILES['product_images']['type'][$i],
                        'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                        'error' => $_FILES['product_images']['error'][$i],
                        'size' => $_FILES['product_images']['size'][$i]
                    ];
                    $path = handleImageUpload($file);
                    if ($path) $allImages[] = $path;
                }
            }
            
            // Process URLs
            foreach ($fallbackUrls as $url) {
                if (!empty(trim($url))) $allImages[] = trim($url);
            }

            if (!empty($allImages)) {
                // If we have new images, we could either append or replace. 
                // Let's replace for now if it's an update and they provided NEW images.
                // Or better, just add them.
                $imgStmt = $db->prepare("INSERT INTO product_images (product_id, image_url, is_main, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($allImages as $idx => $img) {
                    if ($idx >= 6) break;
                    $isMain = ($idx === 0) ? 1 : 0;
                    $imgStmt->execute([$product_id, $img, $isMain, $idx]);
                    
                    // Update main products table image_url for legacy compatibility
                    if ($isMain) {
                        $db->prepare("UPDATE products SET image_url = ? WHERE id = ?")->execute([$img, $product_id]);
                    }
                }
            }

            if ($sizes) {
                $sizeStmt = $db->prepare("INSERT INTO product_sizes (product_id, size_label) VALUES (?, ?)");
                foreach ($sizes as $size) {
                    if ($size) $sizeStmt->execute([$product_id, $size]);
                }
            }
            
            $db->commit();
            header('Location: /tcs/admin/admin.php?view=inventory&msg=success'); exit;
        } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
    }

    // Update Order Status
    if (isset($_POST['update_order_status'])) {
        $orderId = (int)$_POST['order_id'];
        $status  = $_POST['status'];
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        $message = "Order #$orderId updated to $status.";
    }

    // Add Category
    if (isset($_POST['add_category'])) {
        $catName = trim($_POST['cat_name']);
        if ($catName) {
            $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
            try {
                $stmt->execute([$catName]);
                $message = "Category '$catName' added.";
            } catch (Exception $e) { $error = "Category already exists."; }
        }
    }
}

// ── 3. HANDLE DELETE ACTIONS ──
if (isset($_GET['delete_product'])) {
    $db->prepare("DELETE FROM products WHERE id = ?")->execute([(int)$_GET['delete_product']]);
    header('Location: /tcs/admin/admin.php?view=inventory&msg=deleted'); exit;
}
if (isset($_GET['delete_category'])) {
    try {
        $db->prepare("DELETE FROM categories WHERE id = ?")->execute([(int)$_GET['delete_category']]);
        header('Location: /tcs/admin/admin.php?view=categories&msg=deleted'); exit;
    } catch (Exception $e) { $error = "Cannot delete category with active products."; }
}
if (isset($_GET['delete_image'])) {
    $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([(int)$_GET['delete_image']]);
    header('Location: ' . $_SERVER['HTTP_REFERER']); exit;
}

// ── 4. FETCH DATA ──
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT p.*, GROUP_CONCAT(DISTINCT s.size_label) as sizes FROM products p LEFT JOIN product_sizes s ON p.id = s.product_id WHERE p.id = ? GROUP BY p.id");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_product = $stmt->fetch();
    
    if ($edit_product) {
        $imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
        $imgStmt->execute([$edit_product['id']]);
        $edit_product['images'] = $imgStmt->fetchAll();
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$products   = $db->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
$orders     = $db->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();

// Dashboard Stats
$stats = $db->query("SELECT COUNT(*) as total_orders, SUM(total) as total_revenue, COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders FROM orders")->fetch();
$topProducts = $db->query("SELECT product_name, SUM(quantity) as total_sold, SUM(line_total) as revenue FROM order_items GROUP BY product_id ORDER BY total_sold DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - YOURSTORE</title>
    <link rel="stylesheet" href="/tcs/assets/css/style.css">
    <style>
        .admin-nav { display: flex; gap: 32px; border-bottom: 1px solid var(--border); margin-bottom: 40px; }
        .admin-nav a { padding: 16px 0; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
        .admin-nav a.active { color: var(--text-primary); border-bottom: 2px solid var(--text-primary); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); padding: 24px; text-align: center; }
        .stat-card h3 { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; }
        .stat-card .value { font-family: var(--font-heading); font-size: 32px; font-weight: 700; color: var(--text-primary); }
        .status-badge { font-size: 9px; padding: 4px 8px; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; background: var(--border); }
        .status-pending { color: #facc15; } .status-processing { color: #60a5fa; } .status-shipped { color: #a78bfa; } .status-delivered { color: #4ade80; }
        .order-status-select { background: transparent; border: 1px solid var(--border); color: var(--text-secondary); font-size: 11px; padding: 4px; outline: none; }
        
        .image-grid-form { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .image-slot { background: var(--bg-secondary); border: 1px dashed var(--border); padding: 10px; text-align: center; position: relative; }
        .image-slot img { width: 100%; height: 100px; object-fit: cover; margin-bottom: 5px; }
        .image-slot .remove-img { position: absolute; top: 5px; right: 5px; background: var(--danger); color: white; border: none; padding: 2px 6px; cursor: pointer; font-size: 10px; }
    </style>
</head>
<body style="background: var(--bg-primary);">

<nav class="navbar">
    <div class="container">
        <a href="/tcs/admin/admin.php" class="navbar__logo">ADMIN PANEL</a>
        <div class="navbar__icons">
            <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="/tcs/admin/logout.php" class="btn btn--sm btn--danger">Logout</a>
        </div>
    </div>
</nav>

<div class="container" style="padding-top: 40px;">
    
    <?php if ($message || isset($_GET['msg'])): ?>
        <div class="alert alert--success"><?= $message ?: "Action completed successfully." ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="admin-nav">
        <a href="?view=dashboard" class="<?= $view === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="?view=inventory" class="<?= $view === 'inventory' ? 'active' : '' ?>">Inventory</a>
        <a href="?view=orders" class="<?= $view === 'orders' ? 'active' : '' ?>">Orders</a>
        <a href="?view=categories" class="<?= $view === 'categories' ? 'active' : '' ?>">Categories</a>
    </div>

    <!-- ── VIEW: DASHBOARD ── -->
    <?php if ($view === 'dashboard'): ?>
        <section class="stats-grid">
            <div class="stat-card"><h3>Total Revenue</h3><div class="value">GH₵<?= number_format($stats['total_revenue'] ?? 0, 2) ?></div></div>
            <div class="stat-card"><h3>Total Orders</h3><div class="value"><?= $stats['total_orders'] ?></div></div>
            <div class="stat-card"><h3>Pending Orders</h3><div class="value" style="color: #facc15;"><?= $stats['pending_orders'] ?></div></div>
        </section>
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">
            <div>
                <h3 style="font-family: var(--font-heading); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 20px;">Top Sellers</h3>
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($topProducts as $tp): ?>
                            <tr><td><?= htmlspecialchars($tp['product_name']) ?></td><td><?= $tp['total_sold'] ?></td><td>GH₵<?= number_format($tp['revenue'], 2) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="background: var(--bg-card); padding: 24px; border: 1px solid var(--border);">
                <h3 style="font-family: var(--font-heading); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 16px;">Quick Info</h3>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">You have <?= $stats['pending_orders'] ?> orders awaiting processing. Check the Orders tab to update their status.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── VIEW: INVENTORY ── -->
    <?php if ($view === 'inventory'): ?>
        <section class="admin-form" id="inventory-form">
            <h2><?= $edit_product ? 'Update Product' : 'Add New Product' ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group"><label>Product Name</label><input type="text" name="name" required value="<?= $edit_product ? htmlspecialchars($edit_product['name']) : '' ?>"></div>
                    <div class="form-group"><label>Price (GH₵)</label><input type="number" step="0.01" name="price" required value="<?= $edit_product ? $edit_product['price'] : '' ?>"></div>
                </div>

                <div class="form-group">
                    <label>Product Images (Up to 6)</label>
                    <div class="image-grid-form">
                        <?php if ($edit_product && !empty($edit_product['images'])): ?>
                            <?php foreach ($edit_product['images'] as $img): ?>
                                <div class="image-slot">
                                    <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="">
                                    <a href="?delete_image=<?= $img['id'] ?>" class="remove-img" onclick="return confirm('Remove image?')">Delete</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php for($i = ($edit_product ? count($edit_product['images']) : 0); $i < 6; $i++): ?>
                            <div class="image-slot" style="display:flex; flex-direction:column; justify-content:center; gap:5px;">
                                <input type="file" name="product_images[]" accept="image/*" style="font-size:10px;">
                                <input type="text" name="image_urls[]" placeholder="Or Image URL" style="font-size:10px; padding:4px;">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label>Stock Quantity</label><input type="number" name="stock_quantity" required value="<?= $edit_product ? $edit_product['stock_quantity'] : '0' ?>"></div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" required>
                            <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= ($edit_product && $edit_product['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="3" required><?= $edit_product ? htmlspecialchars($edit_product['description']) : '' ?></textarea></div>
                <div class="form-group"><label>Sizes (comma-separated)</label><input type="text" name="sizes" placeholder="S, M, L, XL" value="<?= $edit_product ? htmlspecialchars($edit_product['sizes']) : '' ?>"></div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="<?= $edit_product ? 'update_product' : 'add_product' ?>" class="btn btn--filled"><?= $edit_product ? 'Save Changes' : 'Add Product' ?></button>
                    <?php if ($edit_product): ?><a href="?view=inventory" class="btn" style="border-color: var(--border);">Cancel</a><?php endif; ?>
                </div>
            </form>
        </section>
        
        <h3 style="font-family: var(--font-heading); letter-spacing: 2px; text-transform: uppercase; margin: 40px 0 20px;">Product List</h3>
        <table class="admin-table">
            <thead><tr><th>Image</th><th>Name</th><th>Stock</th><th>Price</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($p['image_url']) ?>" style="width: 40px; height: 50px; object-fit: cover;"></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= $p['stock_quantity'] ?></td>
                        <td>GH₵<?= number_format($p['price'], 2) ?></td>
                        <td>
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <a href="?view=inventory&edit=<?= $p['id'] ?>#inventory-form" class="btn-icon" title="Edit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                                <a href="?view=inventory&delete_product=<?= $p['id'] ?>" class="btn-icon" title="Delete" onclick="return confirm('Delete this product?')"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ── VIEW: ORDERS ── -->
    <?php if ($view === 'orders'): ?>
        <h3 style="font-family: var(--font-heading); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 24px;">Manage Orders</h3>
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Date</th><th>Status</th><th>Update Status</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td><?= htmlspecialchars($o['customer_name']) ?><br><small style="color: var(--text-muted);"><?= htmlspecialchars($o['customer_phone']) ?></small></td>
                        <td>GH₵<?= number_format($o['total'], 2) ?></td>
                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                        <td><span class="status-badge status-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 8px;">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <select name="status" class="order-status-select">
                                    <option value="pending" <?= $o['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="processing" <?= $o['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="shipped" <?= $o['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="delivered" <?= $o['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                    <option value="cancelled" <?= $o['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_order_status" class="btn btn--sm" style="padding: 4px 8px; font-size: 9px;">Set</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- ── VIEW: CATEGORIES ── -->
    <?php if ($view === 'categories'): ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px;">
            <section class="admin-form">
                <h2>Add New Category</h2>
                <form method="POST">
                    <div class="form-group"><label>Category Name</label><input type="text" name="cat_name" required placeholder="e.g., Streetwear"></div>
                    <button type="submit" name="add_category" class="btn btn--filled">Create Category</button>
                </form>
            </section>
            <section>
                <h3 style="font-family: var(--font-heading); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 24px;">Current Categories</h3>
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><?= htmlspecialchars($c['name']) ?></td>
                                <td><a href="?view=categories&delete_category=<?= $c['id'] ?>" class="btn-icon" onclick="return confirm('Delete category?')"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
