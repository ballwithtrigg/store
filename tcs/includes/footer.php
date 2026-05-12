<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__brand">
                <h3>YOURSTORE</h3>
                <p>Premium streetwear and fashion essentials. Crafted for those who dare to stand out.</p>
            </div>
            <div>
                <h4>Shop</h4>
                <ul>
                    <li><a href="/tcs/index.php">All Products</a></li>
                    <?php
                    $footerCats = getDB()->query("SELECT name FROM categories ORDER BY id")->fetchAll();
                    foreach ($footerCats as $fc): ?>
                        <li><a href="/tcs/index.php?category=<?= urlencode($fc['name']) ?>"><?= htmlspecialchars($fc['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4>Account</h4>
                <ul>
                    <li><a href="/tcs/login.php">Login</a></li>
                    <li><a href="/tcs/signup.php">Sign Up</a></li>
                    <li><a href="/tcs/cart.php">Cart</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <ul>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Terms &amp; Conditions</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer__bottom">
            <p>&copy; <?= date('Y') ?> YOURSTORE. All rights reserved.</p>
            <div class="footer__socials">
                <a href="#" title="TikTok">TikTok</a>
                <a href="#" title="Instagram">Instagram</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
