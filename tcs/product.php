<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /tcs/index.php'); exit; }

$stmt = $db->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) { header('Location: /tcs/index.php'); exit; }

// Get sizes
$sizes = $db->prepare("SELECT size_label FROM product_sizes WHERE product_id = :id ORDER BY id");
$sizes->execute(['id' => $id]);
$sizes = $sizes->fetchAll(PDO::FETCH_COLUMN);

// Get images
$images = $db->prepare("SELECT image_url FROM product_images WHERE product_id = :id ORDER BY sort_order");
$images->execute(['id' => $id]);
$images = $images->fetchAll(PDO::FETCH_COLUMN);

// If no images in product_images, use the main one from products table
if (empty($images)) {
    $images = [$product['image_url']];
}

// Handle add-to-cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: /tcs/login.php?msg=auth_required');
        exit;
    }

    $size = $_POST['size'] ?? ($sizes[0] ?? 'One Size');
    $qty  = (int)($_POST['quantity'] ?? 1);
    if ($qty < 1) $qty = 1;
    
    $cart = $_SESSION['cart'] ?? [];
    $key  = $id . '_' . $size;

    if (isset($cart[$key])) {
        $cart[$key]['quantity'] += $qty;
    } else {
        $cart[$key] = [
            'id'       => $product['id'],
            'name'     => $product['name'],
            'price'    => $product['price'],
            'image'    => $product['image_url'],
            'size'     => $size,
            'quantity' => $qty,
        ];
    }
    $_SESSION['cart'] = $cart;
    $successMsg = $product['name'] . ' (Size: ' . htmlspecialchars($size) . ') added to cart!';
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (isset($_SESSION['user_id'])) {
        $rating  = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        $userId  = $_SESSION['user_id'];
        
        // Final eligibility check (server-side)
        $check = $db->prepare("SELECT COUNT(*) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('shipped', 'delivered')");
        $check->execute([$userId, $id]);
        
        if ($check->fetchColumn() > 0) {
            $stmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?, comment = ?");
            $stmt->execute([$id, $userId, $rating, $comment, $rating, $comment]);
            $successMsg = "Thank you! Your review has been submitted.";
        }
    }
}

// Fetch reviews
$reviewStmt = $db->prepare("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$reviewStmt->execute([$id]);
$reviews = $reviewStmt->fetchAll();

// Check if user can review
$canReview = false;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $check = $db->prepare("SELECT COUNT(*) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('shipped', 'delivered')");
    $check->execute([$userId, $id]);
    $canReview = $check->fetchColumn() > 0;
}

// Related products
$related = $db->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.category_id = :cid AND p.id != :id LIMIT 4");
$related->execute(['cid' => $product['category_id'], 'id' => $id]);
$relatedProducts = $related->fetchAll();

$pageTitle = $product['name'];
$pageDesc  = substr($product['description'], 0, 160);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if (!empty($successMsg)): ?>
        <div class="alert alert--success" style="margin-top:24px"><?= $successMsg ?></div>
    <?php endif; ?>

    <div class="product-detail">
        <div class="product-detail__gallery">
            <div class="main-image">
                <img id="featured-image" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            </div>
            <?php if (count($images) > 1): ?>
            <div class="thumbnails" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-top: 15px;">
                <?php foreach ($images as $img): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="" style="width: 100%; height: 80px; object-fit: cover; cursor: pointer; border: 1px solid var(--border);" 
                        onclick="document.getElementById('featured-image').src = this.src; document.querySelectorAll('.thumbnails img').forEach(el=>el.style.borderColor='var(--border)'); this.style.borderColor='var(--text-primary)';">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="product-detail__info">
            <div class="product-card__category"><?= htmlspecialchars($product['category_name']) ?></div>
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <div class="product-detail__price">GH₵<?= number_format($product['price'], 2) ?></div>
            <div class="product-detail__desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>

            <form method="POST">
                <?php if ($sizes): ?>
                <div class="size-selector">
                    <label>Size</label>
                    <div class="size-options">
                        <?php foreach ($sizes as $i => $s): ?>
                            <label class="size-option <?= $i === 0 ? 'active' : '' ?>">
                                <input type="radio" name="size" value="<?= htmlspecialchars($s) ?>" <?= $i === 0 ? 'checked' : '' ?> style="display:none"
                                    onchange="document.querySelectorAll('.size-option').forEach(el=>el.classList.remove('active'));this.parentElement.classList.add('active');">
                                <?= htmlspecialchars($s) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label>Quantity</label>
                    <div class="qty-control" style="width: 140px;">
                        <button type="button" onclick="const q=this.nextElementSibling; if(q.innerText > 1) { q.innerText--; document.getElementById('qty-input').value = q.innerText; }">−</button>
                        <span id="qty-display">1</span>
                        <button type="button" onclick="const q=this.previousElementSibling; q.innerText++; document.getElementById('qty-input').value = q.innerText;">+</button>
                        <input type="hidden" name="quantity" id="qty-input" value="1">
                    </div>
                </div>
                <button type="submit" name="add_to_cart" class="btn btn--filled" style="width:100%;text-align:center">Add to Cart</button>
            </form>
        </div>
    </div>

    <?php if ($relatedProducts): ?>
    <div class="section-header"><h2>Related Items</h2></div>
    <div class="product-grid">
        <?php foreach ($relatedProducts as $rp): ?>
            <a href="/tcs/product.php?id=<?= $rp['id'] ?>" class="product-card">
                <div class="product-card__image">
                    <img src="<?= htmlspecialchars($rp['image_url']) ?>" alt="<?= htmlspecialchars($rp['name']) ?>" loading="lazy">
                </div>
                <div class="product-card__info">
                    <div class="product-card__category"><?= htmlspecialchars($rp['category_name']) ?></div>
                    <div class="product-card__name"><?= htmlspecialchars($rp['name']) ?></div>
                    <div class="product-card__price">GH₵<?= number_format($rp['price'], 2) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Reviews Section -->
    <section class="reviews-section" id="reviews">
        <div class="section-header">
            <h2>Customer Reviews</h2>
            <p><?= count($reviews) ?> review<?= count($reviews) !== 1 ? 's' : '' ?></p>
        </div>

        <?php if ($canReview): ?>
            <div class="review-form">
                <h3 style="font-family:var(--font-heading); letter-spacing:2px; text-transform:uppercase; margin-bottom:20px">Leave a Review</h3>
                <form method="POST">
                    <div class="rating-input">
                        <input type="radio" id="star5" name="rating" value="5" checked><label for="star5">★</label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                    </div>
                    <div class="form-group">
                        <label>Your Comment</label>
                        <textarea name="comment" rows="4" placeholder="Tell us what you think..."></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn btn--filled">Submit Review</button>
                </form>
            </div>
        <?php elseif(isset($_SESSION['user_id'])): ?>
            <p style="color:var(--text-muted); margin-bottom:40px; font-size:14px">Only verified purchasers who have received their items can leave a review.</p>
        <?php else: ?>
            <p style="color:var(--text-muted); margin-bottom:40px; font-size:14px">Please <a href="/tcs/login.php" style="text-decoration:underline">login</a> to leave a review.</p>
        <?php endif; ?>

        <div class="reviews-list">
            <?php if (empty($reviews)): ?>
                <p style="color:var(--text-muted); text-align:center; padding:40px 0">No reviews yet. Be the first to review!</p>
            <?php else: ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="review-item">
                        <div class="review-item__header">
                            <div class="review-item__user"><?= htmlspecialchars($rev['full_name']) ?></div>
                            <div class="review-item__date"><?= date('M d, Y', strtotime($rev['created_at'])) ?></div>
                        </div>
                        <div class="stars">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <span style="color: <?= $i <= $rev['rating'] ? '#facc15' : '#333' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <p style="font-size:14px; color:var(--text-secondary); line-height:1.6"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
