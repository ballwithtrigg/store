<?php
require_once __DIR__ . '/../config/database.php';

// Cart count from session
$cartItems = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cartItems, 'quantity'));

// Get categories for nav
$db = getDB();
$categories = $db->query("SELECT id, name FROM categories ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'YOURSTORE' ?> - YOURSTORE</title>
    <meta name="description" content="<?= $pageDesc ?? 'Premium streetwear and fashion' ?>">
    <link rel="stylesheet" href="/tcs/assets/css/style.css">
</head>
<body>

<!-- Top Banner -->
<div class="top-banner">Free Shipping on Orders Over $100</div>

<!-- Navbar -->
<nav class="navbar">
    <div class="container">
        <a href="/tcs/index.php" class="navbar__logo">YOURSTORE</a>

        <ul class="navbar__links">
            <?php foreach ($categories as $cat): ?>
                <li><a href="/tcs/index.php?category=<?= urlencode($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>

        <form action="/tcs/index.php" method="GET" class="search-bar">
            <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </button>
        </form>

        <div class="navbar__icons">
            <a href="/tcs/cart.php" title="Cart">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <a href="/tcs/account.php" title="Account">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </a>
                    <div class="dropdown__menu">
                        <a href="/tcs/account.php">My Account</a>
                        <a href="/tcs/logout.php">Log Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/tcs/login.php" title="Account">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>
