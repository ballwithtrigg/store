<?php
require_once __DIR__ . '/config/database.php';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = $_SESSION['cart'] ?? [];
    $key  = $_POST['key'] ?? '';

    if (isset($_POST['increase']) && isset($cart[$key])) {
        $cart[$key]['quantity']++;
    }
    if (isset($_POST['decrease']) && isset($cart[$key])) {
        if ($cart[$key]['quantity'] > 1) {
            $cart[$key]['quantity']--;
        }
    }
    if (isset($_POST['remove']) && isset($cart[$key])) {
        unset($cart[$key]);
    }

    $_SESSION['cart'] = $cart;
    header('Location: /tcs/cart.php');
    exit;
}

$pageTitle = 'Cart';
require_once __DIR__ . '/includes/header.php';

$cart  = $_SESSION['cart'] ?? [];
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>

<div class="container">
    <div class="page-title"><h1>Shopping Cart</h1></div>

    <?php if (empty($cart)): ?>
        <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            <p>Your cart is empty.</p>
            <a href="/tcs/index.php" class="btn btn--sm">Continue Shopping</a>
        </div>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th></th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cart as $key => $item): ?>
                <tr>
                    <td><img src="<?= htmlspecialchars($item['image']) ?>" alt="" class="cart-item__image"></td>
                    <td>
                        <div class="cart-item__name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="cart-item__size">Size: <?= htmlspecialchars($item['size']) ?></div>
                    </td>
                    <td>GH₵<?= number_format($item['price'], 2) ?></td>
                    <td>
                        <div class="qty-control">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                                <button type="submit" name="decrease">−</button>
                            </form>
                            <span><?= $item['quantity'] ?></span>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                                <button type="submit" name="increase">+</button>
                            </form>
                        </div>
                    </td>
                    <td>GH₵<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                            <button type="submit" name="remove" class="btn-icon" title="Remove">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary">
            <div class="cart-summary__box">
                <div class="cart-summary__total">Total: GH₵<?= number_format($total, 2) ?></div>
                <div class="cart-summary__note">Shipping calculated at checkout</div>
                <a href="/tcs/checkout.php" class="btn btn--filled">Proceed to Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
