<?php
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user info from session
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_name = $_SESSION['username'] ?? null;
$is_admin = $_SESSION['is_admin'] ?? 0; // 1 if admin, 0 if not
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">

    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";
        const SESSION_USER = {
            id: <?php echo json_encode($current_user_id); ?>,
            username: <?php echo json_encode($current_user_name); ?>,
            is_admin: <?php echo json_encode($is_admin); ?>
        };
    </script>
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-content">
            <a href="<?php echo BASE_URL; ?>" class="navbar-brand">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.jpeg" alt="PoemIT! Logo" class="logo-image">
            </a>
            
            <div class="navbar-menu">
                <?php if ($current_user_id): ?>
                    <a href="<?php echo BASE_URL; ?>/write" class="nav-link">Write</a>
                    <a href="<?php echo BASE_URL; ?>/profile?user=<?php echo $current_user_name; ?>" class="nav-link">Profile</a>

                    <!-- Admin icon visible only if user is admin -->
                    <?php if ($is_admin == 1): ?>
                        <a href="<?php echo BASE_URL; ?>/admin_dashboard.php" class="admin-link">⚡ Admin Panel</a>
                    <?php endif; ?>

                    <a href="<?php echo BASE_URL; ?>/logout" class="nav-link" id="logoutLink">Logout</a>

                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login" class="nav-link">Login</a>
                    <a href="<?php echo BASE_URL; ?>/signup" class="btn btn-primary btn-sm">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main>
