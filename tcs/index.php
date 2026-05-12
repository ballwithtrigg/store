<?php
$pageTitle = 'Home';
$pageDesc  = 'Premium streetwear and fashion essentials';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Build query based on filters
$where  = [];
$params = [];

if (!empty($_GET['category'])) {
    $where[]  = 'c.name = :category';
    $params['category'] = $_GET['category'];
}

if (!empty($_GET['search'])) {
    $where[]  = '(p.name LIKE :search OR p.description LIKE :search2)';
    $params['search']  = '%' . $_GET['search'] . '%';
    $params['search2'] = '%' . $_GET['search'] . '%';
}

$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

// Sorting Logic
$sort = $_GET['sort'] ?? 'newest';
switch ($sort) {
    case 'price_asc':  $sql .= ' ORDER BY p.price ASC'; break;
    case 'price_desc': $sql .= ' ORDER BY p.price DESC'; break;
    case 'newest':     
    default:           $sql .= ' ORDER BY p.created_at DESC'; break;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<!-- Hero Section -->
<?php if (empty($_GET['category']) && empty($_GET['search'])): ?>
<section class="hero">
    <div class="hero__bg"></div>
    <div class="hero__content">
        <p class="hero__subtitle">New Collection 2026</p>
        <h1 class="hero__title">YOURSTORE</h1>
        <p class="hero__tagline">Streetwear &amp; Fashion</p>
        <a href="#products" class="btn">Shop Now</a>
    </div>
</section>
<?php endif; ?>

<!-- Category Navigation -->
<nav class="category-nav">
    <div class="container">
        <ul>
            <li><a href="/tcs/index.php" class="<?= empty($_GET['category']) && empty($_GET['search']) ? 'active' : '' ?>">All</a></li>
            <?php foreach ($categories as $cat): ?>
                <li><a href="/tcs/index.php?category=<?= urlencode($cat['name']) ?>"
                       class="<?= ($_GET['category'] ?? '') === $cat['name'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>

<!-- Products -->
<div class="container">
    <div class="section-header" id="products">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <div style="text-align: left;">
                <h2 style="padding-bottom: 0;"><?php
                    if (!empty($_GET['search'])) {
                        echo 'Search: "' . htmlspecialchars($_GET['search']) . '"';
                    } elseif (!empty($_GET['category'])) {
                        echo htmlspecialchars($_GET['category']);
                    } else {
                        echo 'Our Products';
                    }
                ?></h2>
                <p style="margin-top: 4px;"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></p>
            </div>
            
            <form method="GET" id="sort-form" style="display: flex; align-items: center; gap: 12px;">
                <?php if (!empty($_GET['category'])): ?><input type="hidden" name="category" value="<?= htmlspecialchars($_GET['category']) ?>"><?php endif; ?>
                <?php if (!empty($_GET['search'])): ?><input type="hidden" name="search" value="<?= htmlspecialchars($_GET['search']) ?>"><?php endif; ?>
                
                <label style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted);">Sort By:</label>
                <select name="sort" onchange="this.form.submit()" style="background: var(--bg-card); border: 1px solid var(--border); color: var(--text-primary); padding: 8px 12px; font-size: 12px; outline: none;">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
            </form>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            <p>No products found.</p>
            <a href="/tcs/index.php" class="btn btn--sm">View All</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
                <a href="/tcs/product.php?id=<?= $p['id'] ?>" class="product-card">
                    <div class="product-card__image">
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    </div>
                    <div class="product-card__info">
                        <div class="product-card__category"><?= htmlspecialchars($p['category_name']) ?></div>
                        <div class="product-card__name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="product-card__price">GH₵<?= number_format($p['price'], 2) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
