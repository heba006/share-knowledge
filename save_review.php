
<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse.php');
    exit;
}

function reviewError($message, $materialId) {
    $_SESSION['review_error'] = $message;
    header('Location: material_reviews.php?id=' . $materialId);
    exit;
}

$materialId = filter_input(INPUT_POST, 'material_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$comment = trim((string)($_POST['comment'] ?? ''));
$csrfToken = (string)($_POST['csrf_token'] ?? '');

if (!$materialId || $materialId < 1) {
    header('Location: browse.php');
    exit;
}

if (
    empty($_SESSION['review_csrf']) ||
    !hash_equals($_SESSION['review_csrf'], $csrfToken)
) {
    reviewError('Invalid request. Please try again.', $materialId);
}

if (!$rating || $rating < 1 || $rating > 5) {
    reviewError('Please select a rating from 1 to 5.', $materialId);
}

if (mb_strlen($comment, 'UTF-8') > 2000) {
    reviewError('Comment must be 2000 characters or less.', $materialId);
}

$materialStmt = $conn->prepare(
    'SELECT id FROM materials WHERE id = ?'
);
$materialStmt->execute([$materialId]);

if (!$materialStmt->fetch()) {
    header('Location: browse.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$saveStmt = $conn->prepare(
    'INSERT INTO reviews (user_id, material_id, rating, comment)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
         rating = VALUES(rating),
         comment = VALUES(comment)'
);

$saveStmt->execute([
    $userId,
    $materialId,
    $rating,
    $comment
]);

$_SESSION['review_success'] = 'Your review has been saved successfully!';

header('Location: material_reviews.php?id=' . $materialId);
exit;
