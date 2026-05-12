<?php
require_once __DIR__ . '/config/database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: /tcs/login.php');
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        $db->beginTransaction();
        
        // Update basic info
        $updateStmt = $db->prepare("UPDATE users SET email = ?, full_name = ?, phone = ? WHERE id = ?");
        $updateStmt->execute([$email, $fullName, $phone, $userId]);
        
        // Update password if provided
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $passStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $passStmt->execute([$hashedPassword, $userId]);
        }
        
        $db->commit();
        $message = 'Account updated successfully.';
        
        // Refresh user data
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $_SESSION['user_email'] = $user['email'];
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to update account: ' . $e->getMessage();
    }
}

$pageTitle = 'My Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="padding: 60px 0;">
        <div class="form-card" style="max-width: 600px;">
            <h1>My Account</h1>
            <p>View and update your personal information</p>

            <?php if ($message): ?>
                <div class="alert alert--success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="John Doe">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" placeholder="name@example.com">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 234 567 890">
                </div>
                
                <div class="form-group">
                    <label for="password">New Password (leave blank to keep current)</label>
                    <input type="password" id="password" name="password" placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn btn--filled">Save Changes</button>
            </form>
            
            <div style="margin-top: 40px; border-top: 1px solid var(--border); padding-top: 24px;">
                <h2 style="font-family: var(--font-heading); font-size: 18px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 16px;">Order History</h2>
                <?php
                $orderStmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                $orderStmt->execute([$userId]);
                $orders = $orderStmt->fetchAll();
                
                if (empty($orders)): ?>
                    <p style="color: var(--text-muted); font-size: 13px;">You haven't placed any orders yet.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><?= $o['id'] ?></td>
                                    <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                                    <td>$<?= number_format($o['total'], 2) ?></td>
                                    <td><span class="status-badge"><?= htmlspecialchars($o['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
