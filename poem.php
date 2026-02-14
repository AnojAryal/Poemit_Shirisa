<?php
require_once 'config/config.php';

$poem_id = intval($_GET['id'] ?? 0);
if (!$poem_id) redirect('/');

$db = (new Database())->getConnection();

// Get poem + author + stats
$stmt = $db->prepare("
    SELECT p.*, u.username, u.avatar_url, u.bio,
           ps.likes_count, ps.comments_count
    FROM poems p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN poem_stats ps ON p.id = ps.poem_id
    WHERE p.id = :id AND p.status = 'approved'
    LIMIT 1
");
$stmt->bindParam(':id', $poem_id, PDO::PARAM_INT);
$stmt->execute();
$poem = $stmt->fetch();
if (!$poem) redirect('/');

// Increment views
$db->prepare("UPDATE poems SET views_count = views_count + 1 WHERE id = :id")
   ->execute([':id' => $poem_id]);

// Check if user liked
$user_liked = false;
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT id FROM likes WHERE user_id = :u AND poem_id = :p");
    $stmt->execute([':u' => $_SESSION['user_id'], ':p' => $poem_id]);
    $user_liked = $stmt->fetch() !== false;
}

// Get comments
$stmt = $db->prepare("
    SELECT c.*, u.username
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.poem_id = :p
    ORDER BY c.created_at DESC
");
$stmt->execute([':p' => $poem_id]);
$comments = $stmt->fetchAll();

$page_title = escape($poem['title']) . ' - ' . SITE_NAME;
include 'includes/header.php';
?>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
</script>

<div class="container poem-detail-container">
    <h1 class="poem-title"><?php echo escape($poem['title']); ?></h1>
    <p class="poem-meta">
        By <a href="profile.php?user=<?php echo escape($poem['username']); ?>" style="font-weight:600; color:var(--text);"><?php echo escape($poem['username']); ?></a>
        &middot; <?php echo date('M j, Y', strtotime($poem['created_at'])); ?>
        &middot; <?php echo $poem['views_count']; ?> views
    </p>

    <div class="poem-content">
        <?php if ($poem['format'] === 'text'): ?>
            <div><?php echo nl2br(escape($poem['content'])); ?></div>
        <?php elseif ($poem['format'] === 'image'): ?>
            <img src="<?php echo escape($poem['file_url']); ?>" alt="<?php echo escape($poem['title']); ?>" style="width:100%; border-radius:var(--radius); height:auto;">
        <?php else: ?>
            <a href="<?php echo escape($poem['file_url']); ?>" download class="btn btn-primary">Download Document</a>
        <?php endif; ?>
    </div>

    <!-- Likes & Comments Stats -->
    <div class="poem-actions">
        <?php if (isLoggedIn()): ?>
            <button id="likeBtn" data-poem-id="<?php echo $poem['id']; ?>" class="btn like-btn <?php echo $user_liked ? 'liked' : 'not-liked'; ?>">
                ❤️ <span id="likeCount"><?php echo $poem['likes_count'] ?? 0; ?></span> Likes
            </button>
        <?php else: ?>
            <a href="login.php" class="btn like-btn not-liked">
                ❤️ <?php echo $poem['likes_count'] ?? 0; ?> Likes
            </a>
        <?php endif; ?>
        <span class="comment-count">💬 <?php echo $poem['comments_count'] ?? 0; ?> Comments</span>
    </div>

    <!-- Comments Section -->
    <div class="comments-section">
        <h2>Comments</h2>
        <?php if (isLoggedIn()): ?>
            <form class="comment-form" data-poem-id="<?php echo $poem['id']; ?>">
                <textarea id="commentContent" placeholder="Share your thoughts..." required></textarea>
                <button type="submit" class="btn btn-primary">Post Comment</button>
            </form>
        <?php else: ?>
            <p class="alert alert-info"><a href="login.php">Login</a> to comment.</p>
        <?php endif; ?>

        <div id="commentsList" class="comments-list">
            <?php foreach ($comments as $c): ?>
                <div class="comment-card">
                    <div class="comment-header">
                        <div class="comment-avatar"><?php echo strtoupper(substr($c['username'], 0, 1)); ?></div>
                        <div class="comment-meta">
                            <span class="comment-author"><?php echo escape($c['username']); ?></span>
                            <span class="comment-date"><?php echo date('M j, Y \a\t g:i A', strtotime($c['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="comment-body"><?php echo nl2br(escape($c['content'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Wait until DOM loads
document.addEventListener("DOMContentLoaded", () => {
    const likeBtn = document.getElementById("likeBtn");
    const commentForm = document.querySelector(".comment-form");

    // Like toggle
    if (likeBtn) {
        likeBtn.addEventListener("click", () => {
            const poemId = likeBtn.dataset.poemId;
            toggleLike(poemId);
        });
    }

    // Comment submit
    if (commentForm) {
        commentForm.addEventListener("submit", (e) => {
            const poemId = commentForm.dataset.poemId;
            submitComment(e, poemId);
        });
    }
});

// Toggle like
function toggleLike(poemId){
    const likeBtn = document.getElementById("likeBtn");
    const likeCount = document.getElementById("likeCount");

    fetch(BASE_URL + '/api/likes.php', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({ poem_id: poemId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            likeCount.textContent = data.likes_count;
            if(data.liked){
                likeBtn.classList.remove('not-liked');
                likeBtn.classList.add('liked');
            } else {
                likeBtn.classList.remove('liked');
                likeBtn.classList.add('not-liked');
            }
        } else alert(data.error || 'Error updating like');
    })
    .catch(console.error);
}

// Submit comment
function submitComment(e, poemId){
    e.preventDefault();
    const textarea = document.getElementById('commentContent');
    const content = textarea.value.trim();
    if(!content) return;

    fetch(BASE_URL + '/api/comments.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ poem_id:poemId, content: content })
    })
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            textarea.value='';
            const c = data.comment;
            const html = `
            <div class="comment-card">
                <div class="comment-header">
                    <div class="comment-avatar">${c.username.charAt(0).toUpperCase()}</div>
                    <div class="comment-meta">
                        <span class="comment-author">${c.username}</span>
                        <span class="comment-date">${new Date(c.created_at).toLocaleString()}</span>
                    </div>
                </div>
                <div class="comment-body">${escapeHtml(c.content).replace(/\n/g,'<br>')}</div>
            </div>`;
            document.getElementById('commentsList').insertAdjacentHTML('afterbegin', html);
        } else alert(data.error || 'Error posting comment');
    })
    .catch(console.error);
}

function escapeHtml(text){
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>
