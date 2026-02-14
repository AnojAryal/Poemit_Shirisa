<?php
session_start();
header('Content-Type: application/json');
require_once '../config/config.php';

// Require authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle like
    $data = json_decode(file_get_contents('php://input'), true);
    $poem_id = intval($data['poem_id'] ?? 0);
    
    if (!$poem_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid poem ID']);
        exit;
    }

    // Check if already liked
    $query = "SELECT id FROM likes WHERE user_id = :user_id AND poem_id = :poem_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':poem_id', $poem_id);
    $stmt->execute();
    $existing_like = $stmt->fetch();

    if ($existing_like) {
        // Unlike
        $query = "DELETE FROM likes WHERE user_id = :user_id AND poem_id = :poem_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':poem_id', $poem_id);
        $stmt->execute();
        
        $liked = false;
    } else {
        // Like
        $query = "INSERT INTO likes (user_id, poem_id) VALUES (:user_id, :poem_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':poem_id', $poem_id);
        $stmt->execute();
        
        $liked = true;
    }

    // Get updated like count
    $query = "SELECT COUNT(*) as count FROM likes WHERE poem_id = :poem_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':poem_id', $poem_id);
    $stmt->execute();
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likes_count' => $result['count']
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
