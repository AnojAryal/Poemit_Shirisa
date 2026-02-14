<?php
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

// Get search query
$search = trim($_GET['search'] ?? '');

// Build query
if ($search) {
    $query = "
        SELECT p.*, u.username, u.avatar_url,
               ps.likes_count, ps.comments_count
        FROM poems p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN poem_stats ps ON p.id = ps.poem_id
        WHERE p.status = 'approved'
          AND (p.title LIKE :search OR p.content LIKE :search OR p.tags LIKE :search)
        ORDER BY p.created_at DESC
    ";
    $search_param = "%$search%";
} else {
    $query = "
        SELECT p.*, u.username, u.avatar_url,
               ps.likes_count, ps.comments_count
        FROM poems p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN poem_stats ps ON p.id = ps.poem_id
        WHERE p.status = 'approved'
        ORDER BY p.created_at DESC
    ";
}

$stmt = $db->prepare($query);
if ($search) {
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$poems = $stmt->fetchAll();

$page_title = 'Home - ' . SITE_NAME;
include 'includes/header.php';
?>

<div class="hero">
    <div class="container">
        <h1 class="hero-title">An Open Stage for Poetry</h1>
        <p class="hero-subtitle">Write, share, and discover beautiful poetry from creators around the world</p>
        <?php if (!isLoggedIn()): ?>
            <a href="signup.php" class="btn btn-primary btn-lg">Start Writing</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="search-section">
        <form method="GET" action="index.php" class="search-form" role="search">
            <input 
                type="text" 
                name="search" 
                placeholder="Search poems by title, content, or tag..." 
                value="<?php echo escape($search); ?>"
                class="search-input"
                aria-label="Search poems"
            >
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <?php if (empty($poems)): ?>
        <div class="empty-state">
            <h3>No poems found</h3>
            <p><?php echo $search ? 'Try a different search term' : 'Be the first to share a poem!'; ?></p>
        </div>
    <?php else: ?>
        <div class="poems-grid">
            <?php foreach ($poems as $poem): ?>
                <article class="poem-card">
                    <div class="poem-card-header">
                        <div class="author-info">
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($poem['username'], 0, 1)); ?>
                            </div>
                            <div>
                                <a href="profile.php?user=<?php echo escape($poem['username']); ?>" class="author-name">
                                    <?php echo escape($poem['username']); ?>
                                </a>
                                <div class="poem-date">
                                    <?php echo date('M j, Y', strtotime($poem['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="poem.php?id=<?php echo $poem['id']; ?>" class="poem-link">
                        <h2 class="poem-title"><?php echo escape($poem['title']); ?></h2>
                        
                        <?php if ($poem['format'] === 'text'): ?>
                            <div class="poem-content">
                                <?php 
                                $content = escape($poem['content']);
                                echo strlen($content) > 200 ? substr($content, 0, 200) . '...' : $content;
                                ?>
                            </div>
                        <?php elseif ($poem['format'] === 'image'): ?>
                            <div class="poem-image">
                                <img src="<?php echo escape($poem['file_url']); ?>" alt="<?php echo escape($poem['title']); ?>">
                            </div>
                        <?php else: ?>
                            <div class="poem-document">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <span>Document poem</span>
                            </div>
                        <?php endif; ?>
                    </a>

                    <div class="poem-card-footer">
                        <span class="poem-stat">
                            ❤️ <?php echo $poem['likes_count'] ?? 0; ?>
                        </span>
                        <span class="poem-stat">
                            💬 <?php echo $poem['comments_count'] ?? 0; ?>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
