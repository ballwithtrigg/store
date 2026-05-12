<?php
require_once __DIR__ . '/config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';

    if ($email && $password && $confirm_password) {
        if ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (email, password, role) VALUES (:email, :password, 'customer')");
                $stmt->execute(['email' => $email, 'password' => $hashedPassword]);
                
                header('Location: /tcs/login.php?registered=1');
                exit;
            }
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$pageTitle = 'Sign Up';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="padding: 80px 0;">
        <div class="form-card">
            <h1>Sign Up</h1>
            <p>Create an account to track your orders</p>

            <?php if ($error): ?>
                <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="name@example.com">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm-password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn--filled">Create Account</button>
            </form>

            <div class="form-link">
                Already have an account? <a href="/tcs/login.php">Login</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
