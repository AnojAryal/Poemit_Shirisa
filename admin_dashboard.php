<?php
session_start();
require_once __DIR__ . "/config/config.php";

// 🔒 Not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🔒 Not admin
if ($_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

$conn = (new Database())->getConnection();

// =============================
// FUNCTIONS
// =============================

function getTotalUsers($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getPendingPoemsCount($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM poems WHERE status='pending'");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getPendingPoems($conn) {
    $stmt = $conn->prepare("
        SELECT poems.*, users.username
        FROM poems
        JOIN users ON poems.user_id = users.id
        WHERE poems.status='pending'
        ORDER BY poems.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updatePoemStatus($conn, $id, $status) {
    $stmt = $conn->prepare("UPDATE poems SET status=? WHERE id=?");
    $stmt->execute([$status, $id]);
}

// =============================
// HANDLE APPROVE / REJECT
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['poem_id'], $_POST['status'])) {
    $poem_id = intval($_POST['poem_id']);
    $status = in_array($_POST['status'], ['approved','rejected']) ? $_POST['status'] : 'pending';
    updatePoemStatus($conn, $poem_id, $status);
    header("Location: admin_dashboard.php");
    exit;
}

// =============================
// FETCH DATA
// =============================
$totalUsers = getTotalUsers($conn);
$pendingCount = getPendingPoemsCount($conn);
$pendingPoems = getPendingPoems($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px 50px; }
h1,h2,h3 { margin: 0 0 10px; }
.nav { margin-bottom: 30px; }
.nav a { margin-right: 15px; text-decoration: none; color: #007bff; }
.card { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
.stats { display: flex; gap: 20px; margin-bottom: 40px; }
.stat-box { flex:1; text-align:center; }
button { padding:6px 12px; margin-right:10px; cursor:pointer; border:none; border-radius:4px; font-weight:bold; }
.btn-approve { background-color:#28a745; color:white; }
.btn-reject { background-color:#dc3545; color:white; }
.poem-content { margin:10px 0; white-space:pre-wrap; }
.poem-image img { max-width:100%; height: 100px;; display:block; margin:10px 0; }
</style>
</head>
<body>

<div class="nav">
    <a href="index.php">Home</a>
    <a href="logout.php">Logout</a>
</div>

<h1>Admin Dashboard</h1>

<div class="stats">
    <div class="card stat-box">
        <h3>Total Users</h3>
        <p><?php echo $totalUsers; ?></p>
    </div>

    <div class="card stat-box">
        <h3>Pending Poems</h3>
        <p><?php echo $pendingCount; ?></p>
    </div>
</div>

<h2>Pending Poem Requests</h2>

<?php if(empty($pendingPoems)): ?>
    <p>No pending poems.</p>
<?php else: ?>
    <?php foreach($pendingPoems as $poem): ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($poem['title']); ?> 
                <span style="font-size:0.8em; color:orange;">(Pending)</span>
            </h3>

            <!-- Show content based on format -->
            <?php if($poem['format'] === 'text'): ?>
                <p class="poem-content"><?php echo nl2br(htmlspecialchars($poem['content'])); ?></p>
            <?php elseif($poem['format'] === 'image'): ?>
                <div class="poem-image">
                    <img src="<?php echo htmlspecialchars($poem['file_url']); ?>" alt="<?php echo htmlspecialchars($poem['title']); ?>">
                </div>
            <?php else: ?>
                <p>📄 <a href="<?php echo htmlspecialchars($poem['file_url']); ?>" download>Download Document</a></p>
            <?php endif; ?>

            <p><strong>By:</strong> <?php echo htmlspecialchars($poem['username']); ?></p>

            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="poem_id" value="<?php echo $poem['id']; ?>">
                <input type="hidden" name="status" value="">
                <button type="submit" class="btn-approve" onclick="this.form.status.value='approved'">Approve</button>
                <button type="submit" class="btn-reject" onclick="this.form.status.value='rejected'">Reject</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
