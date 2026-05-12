<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$cart  = $_SESSION['cart'] ?? [];
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($cart)) {
        $error = 'Your cart is empty.';
    } elseif (!$name || !$phone || !$address) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $db->beginTransaction();

            $userId = $_SESSION['user_id'] ?? null;

            $stmt = $db->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, shipping_address, total) VALUES (:uid, :name, :phone, :addr, :total)");
            $stmt->execute([
                'uid'   => $userId,
                'name'  => $name,
                'phone' => $phone,
                'addr'  => $address,
                'total' => $total,
            ]);
            $orderId = $db->lastInsertId();

            $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, size, quantity, unit_price) VALUES (:oid, :pid, :pname, :pimg, :size, :qty, :price)");
            foreach ($cart as $item) {
                $itemStmt->execute([
                    'oid'   => $orderId,
                    'pid'   => $item['id'],
                    'pname' => $item['name'],
                    'pimg'  => $item['image'],
                    'size'  => $item['size'],
                    'qty'   => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            $db->commit();
            $_SESSION['cart'] = [];
            $success = true;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Checkout';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-title"><h1>Checkout</h1></div>

    <?php if ($success): ?>
        <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p style="color:#4ade80;font-size:18px;font-family:var(--font-heading);letter-spacing:3px;text-transform:uppercase">Order Placed Successfully!</p>
            <p>Thank you for your purchase.</p>
            <a href="/tcs/index.php" class="btn btn--sm">Continue Shopping</a>
        </div>
    <?php elseif (empty($cart)): ?>
        <div class="empty-state">
            <p>Your cart is empty.</p>
            <a href="/tcs/index.php" class="btn btn--sm">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div style="max-width:560px;margin:0 auto">
            <?php if ($error): ?>
                <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-card" style="max-width:100%">
                <h1>Shipping Details</h1>
                <p>Total: GH₵<?= number_format($total, 2) ?> — <?= array_sum(array_column($cart, 'quantity')) ?> item(s)</p>

                <form method="POST">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+1 234 567 890">
                    </div>
                    <div class="form-group">
                        <label for="address">Shipping Address</label>
                        <textarea id="address" name="address" rows="4" required placeholder="123 Street, City, Country"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn--filled" style="width:100%;text-align:center">Place Order</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
