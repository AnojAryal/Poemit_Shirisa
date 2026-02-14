<?php
require_once 'config/config.php';

$username = trim($_GET['user'] ?? '');
if (empty($username)) redirect('/');

$db = (new Database())->getConnection();

/* ---------------- USER INFO ---------------- */
$stmt = $db->prepare("SELECT id, username, full_name, bio, avatar_url, created_at FROM users WHERE username = :username");
$stmt->execute([':username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) redirect('/');

/* ---------------- CHECK OWNER ---------------- */
$is_owner = isLoggedIn() && $_SESSION['user_id'] == $user['id'];

/* ---------------- USER POEMS ---------------- */
$stmt = $db->prepare("
    SELECT p.*, ps.likes_count, ps.comments_count
    FROM poems p
    LEFT JOIN poem_stats ps ON p.id = ps.poem_id
    WHERE p.user_id = :user_id
    ORDER BY p.created_at DESC
");
$stmt->execute([':user_id' => $user['id']]);
$poems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- USER STATS ---------------- */
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT p.id) AS poems_count,
           COALESCE(SUM(ps.likes_count),0) AS total_likes
    FROM poems p
    LEFT JOIN poem_stats ps ON p.id = ps.poem_id
    WHERE p.user_id = :user_id
");
$stmt->execute([':user_id' => $user['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = escape($user['username']) . ' - ' . SITE_NAME;
include 'includes/header.php';
?>

<div class="container profile-container">

    <!-- PROFILE HEADER -->
    <div class="profile-header">
        <div class="profile-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
        <div class="profile-info">
            <h1><?php echo escape($user['full_name'] ?: $user['username']); ?></h1>
            <p>@<?php echo escape($user['username']); ?></p>
            <?php if ($user['bio']): ?>
                <p style="color: var(--text-secondary); margin-bottom: 0;"><?php echo nl2br(escape($user['bio'])); ?></p>
            <?php endif; ?>
            <div class="profile-stats">
                <span><strong><?php echo $stats['poems_count']; ?></strong> poems</span>
                <span><strong><?php echo $stats['total_likes']; ?></strong> likes</span>
                <span>Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- POEMS -->
    <h2>Poems by <?php echo escape($user['username']); ?></h2>

    <?php if (empty($poems)): ?>
        <p>No poems yet.</p>
    <?php else: ?>
        <div class="poems-grid">
            <?php foreach ($poems as $poem): ?>
                <article class="poem-card" id="poem-<?php echo $poem['id']; ?>">

                    <!-- Title + status -->
                    <h3 class="poem-title" id="title-<?php echo $poem['id']; ?>">
                        <span class="title-text"><?php echo escape($poem['title']); ?></span>
                        <?php if(!empty($poem['status'])): ?>
                            <span class="poem-status status-<?php echo $poem['status']; ?>">
                                <?php echo ucfirst($poem['status']); ?>
                            </span>
                        <?php endif; ?>
                    </h3>

                    <!-- Poem content -->
                    <?php if ($poem['format'] === 'text'): ?>
                        <p class="poem-content" id="content-<?php echo $poem['id']; ?>"><?php echo escape($poem['content']); ?></p>
                    <?php elseif ($poem['format'] === 'image'): ?>
                        <div class="poem-image">
                            <img src="<?php echo escape($poem['file_url']); ?>" alt="<?php echo escape($poem['title']); ?>">
                        </div>
                    <?php else: ?>
                        <p>📄 Document poem</p>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div class="poem-card-footer">
                        <span>❤️ <?php echo $poem['likes_count'] ?? 0; ?></span>
                        <span>💬 <?php echo $poem['comments_count'] ?? 0; ?></span>

                        <?php if ($is_owner): ?>
                            <div class="owner-actions">
                                <button onclick="enableEdit(<?php echo $poem['id']; ?>)" class="btn btn-edit">Edit</button>
                                <button onclick="deletePoem(<?php echo $poem['id']; ?>)" class="btn btn-delete">Delete</button>
                                <button onclick="savePoem(<?php echo $poem['id']; ?>)" class="btn btn-save" style="display:none;">Save</button>
                                <input type="file" class="file-input" data-id="<?php echo $poem['id']; ?>" style="display:none;">
                            </div>
                        <?php endif; ?>
                    </div>

                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function deletePoem(id){
    if(!confirm("Delete this poem permanently?")) return;

    fetch("api/delete-poem.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ poem_id: id })
    })
    .then(r => r.json())
    .then(d => {
        if(d.success) location.reload();
        else alert(d.message || "Delete failed");
    })
    .catch(()=>alert("Server error"));
}

function enableEdit(id){
    const titleEl = document.querySelector('#title-' + id + ' .title-text');
    const contentEl = document.getElementById('content-' + id);

    // Title input without status
    const titleInput = document.createElement('input');
    titleInput.type = 'text';
    titleInput.value = titleEl.textContent.trim();
    titleInput.id = 'title-input-' + id;
    titleEl.replaceWith(titleInput);

    if(contentEl){
        const contentInput = document.createElement('textarea');
        contentInput.value = contentEl.textContent;
        contentInput.id = 'content-input-' + id;
        contentEl.replaceWith(contentInput);
    }

    document.querySelector(`#poem-${id} .file-input`).style.display = 'inline-block';
    document.querySelector(`#poem-${id} .btn-save`).style.display = 'inline-block';
    document.querySelector(`#poem-${id} .btn-edit`).style.display = 'none';
}

function savePoem(id){
    const title = document.getElementById('title-input-' + id)?.value || '';
    const content = document.getElementById('content-input-' + id)?.value || '';
    const fileInput = document.querySelector(`.file-input[data-id='${id}']`);

    const formData = new FormData();
    formData.append('poem_id', id);
    formData.append('title', title);
    formData.append('content', content);
    if(fileInput && fileInput.files[0]) formData.append('file', fileInput.files[0]);

    fetch('api/patch-poem.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if(d.success) location.reload();
        else alert(d.message || 'Update failed');
    })
    .catch(()=>alert('Server error'));
}
</script>

<?php include 'includes/footer.php'; ?>
