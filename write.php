<?php
require_once 'config/config.php';

// Require authentication
if (!isLoggedIn()) {
    redirect('/login');
}

// Initialize
$error = '';
$success = false;
$file_url = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $format = $_POST['format'] ?? 'text';
    $tags = trim($_POST['tags'] ?? '');
    
    // Validation
    if (empty($title)) {
        $error = 'Title is required';
    } elseif ($format === 'text' && empty($content)) {
        $error = 'Content is required for text poems';
    } elseif (($format === 'image' || $format === 'document') && (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE)) {
        $error = 'File is required for ' . $format . ' poems';
    }

    // Handle file upload for image/document
    if (empty($error) && $format !== 'text' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $allowed_extensions = $format === 'image'
            ? ['jpg','jpeg','png','gif','webp']
            : ['pdf','doc','docx'];

        if (!in_array($extension, $allowed_extensions)) {
            $error = 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions);
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $error = 'File size exceeds 5MB limit';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload error code: ' . $file['error'];
        } else {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

            $filename = uniqid() . '_' . time() . '.' . $extension;
            $upload_path = UPLOAD_DIR . $filename;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $file_url = UPLOAD_URL . $filename;
            } else {
                $error = 'Failed to save file. Check uploads folder permissions.';
            }
        }
    }

    // Save to database
    if (empty($error)) {
        $database = new Database();
        $db = $database->getConnection();

        $query = "INSERT INTO poems (user_id, title, content, format, file_url, tags) 
                  VALUES (:user_id, :title, :content, :format, :file_url, :tags)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':format', $format);
        $stmt->bindParam(':file_url', $file_url);
        $stmt->bindParam(':tags', $tags);

        if ($stmt->execute()) {
            $poem_id = $db->lastInsertId();
            redirect('/poem?id=' . $poem_id);
        } else {
            $error = 'Failed to create poem';
        }
    }
}

$page_title = 'Write a Poem - ' . SITE_NAME;
include 'includes/header.php';
?>

<div class="container write-container">
    <h1 class="page-title">Write Your Poem</h1>
    <p class="page-subtitle">Share your creativity with the world.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo escape($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="poem-form">
        <div class="form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" required placeholder="Give your poem a title"
                   value="<?php echo escape($_POST['title'] ?? ''); ?>">
        </div>

        <!-- Format Tabs -->
        <div class="form-group">
            <label>Format *</label>
            <div class="format-tabs">
                <button type="button" class="format-tab <?php if(($_POST['format'] ?? '')==='text') echo 'active'; ?>" data-format="text">Text</button>
                <button type="button" class="format-tab <?php if(($_POST['format'] ?? '')==='image') echo 'active'; ?>" data-format="image">Image</button>
                <button type="button" class="format-tab <?php if(($_POST['format'] ?? '')==='document') echo 'active'; ?>" data-format="document">Document</button>
            </div>
            <input type="hidden" name="format" id="format" value="<?php echo escape($_POST['format'] ?? 'text'); ?>">
        </div>

        <div class="form-group format-content" id="textDiv">
            <label for="content">Your Poem *</label>
            <textarea id="content" name="content" rows="12" placeholder="Write your poem here..."><?php echo escape($_POST['content'] ?? ''); ?></textarea>
        </div>

        <div class="form-group format-content" id="fileDiv">
            <label for="fileInput">Upload File *</label>
            <input type="file" name="file" id="fileInput">
            <small class="form-text">
                Max size: 5MB. Images: JPG, PNG, GIF, WebP. Documents: PDF, DOC, DOCX.
            </small>
        </div>

        <div class="form-group">
            <label for="tags">Tags</label>
            <input type="text" id="tags" name="tags" placeholder="e.g., nature, love, haiku"
                   value="<?php echo escape($_POST['tags'] ?? ''); ?>">
            <small class="form-text">Separate tags with commas</small>
        </div>

        <div class="form-actions">
            <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Publish Poem</button>
        </div>
    </form>
</div>

<script>
// Format tab switching
const tabs = document.querySelectorAll('.format-tab');
const hiddenFormat = document.getElementById('format');
const textDiv = document.getElementById('textDiv');
const fileDiv = document.getElementById('fileDiv');

function updateFormatDisplay(format) {
    if(format === 'text') {
        textDiv.style.display = 'block';
        fileDiv.style.display = 'none';
    } else {
        textDiv.style.display = 'none';
        fileDiv.style.display = 'block';
    }
}

tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        const format = tab.dataset.format;
        hiddenFormat.value = format;
        updateFormatDisplay(format);
    });
});

// Initial display
updateFormatDisplay(hiddenFormat.value);
</script>

<?php include 'includes/footer.php'; ?>
