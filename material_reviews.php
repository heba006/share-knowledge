<?php
session_start();
require_once 'db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$materialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$materialId || $materialId < 1) {
    http_response_code(400);
    exit('Invalid material ID.');
}

$stmt = $conn->prepare('SELECT m.id, m.subject, m.chapter, m.academic_year,
                              m.description, u.name AS uploader_name
                       FROM materials m
                       LEFT JOIN users u ON m.user_id = u.id
                       WHERE m.id = ?');
$stmt->execute([$materialId]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$material) {
    http_response_code(404);
    exit('Material not found.');
}

$statsStmt = $conn->prepare('SELECT COUNT(*) AS total_reviews, AVG(rating) AS average_rating FROM reviews WHERE material_id = ?');
$statsStmt->execute([$materialId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$reviewsStmt = $conn->prepare('SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name
                              FROM reviews r JOIN users u ON r.user_id = u.id
                              WHERE r.material_id = ? ORDER BY r.created_at DESC, r.id DESC');
$reviewsStmt->execute([$materialId]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

$myReview = null;
if (isset($_SESSION['user_id'])) {
    $myStmt = $conn->prepare('SELECT rating, comment FROM reviews WHERE material_id = ? AND user_id = ?');
    $myStmt->execute([$materialId, (int)$_SESSION['user_id']]);
    $myReview = $myStmt->fetch(PDO::FETCH_ASSOC);
}
if (empty($_SESSION['review_csrf'])) {
    $_SESSION['review_csrf'] = bin2hex(random_bytes(32));
}
$average = $stats['average_rating'] !== null ? number_format((float)$stats['average_rating'], 1) : '0.0';
$totalReviews = (int)$stats['total_reviews'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ratings &amp; Reviews - Share Knowledge</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/material_reviews.css">
</head>
<body class="rv-midnight">
<header class="rv-nav">
  <a href="index.php" class="rv-logo"><span class="rv-logo-icon">🎓</span>Share Knowledge</a>
  <nav class="rv-links" aria-label="Main navigation">
    <a href="index.php">Home</a><a href="browse.php">Browse</a><a href="upload.php">Upload</a><a href="messages.php">Messages</a><a href="profile.php">Profile</a><a href="favorites.php">Favorites ♥</a>
  </nav>
  <div class="rv-account">
    <?php if (isset($_SESSION['user_id'])): ?>
      <span>Welcome, <?= e($_SESSION['user_name'] ?? 'Student') ?></span>
      <a class="rv-logout" href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a><a href="signup.php" class="rv-signup">Sign Up</a>
    <?php endif; ?>
  </div>
</header>
<main class="rv-container">
  <a class="rv-back" href="browse.php">← Back to Materials</a>
  <section class="rv-hero">
    <p class="rv-eyebrow">✦ Community ratings &amp; reviews</p>
    <h1><?= e($material['chapter']) ?></h1>
    <p class="rv-meta">📚 <?= e($material['subject']) ?> &nbsp;·&nbsp; 🎓 <?= e($material['academic_year']) ?></p>
    <p class="rv-meta">Shared by <?= e($material['uploader_name'] ?? 'Unknown Student') ?></p>
    <p class="rv-desc"><?= nl2br(e($material['description'])) ?></p>
    <div class="rv-hero-bottom">
      <div class="rv-rating">
        <span class="rv-rating-number"><?= e($average) ?></span>
        <div><div class="rv-stars" aria-label="Average rating">★★★★★</div><div class="rv-rating-sub">Out of 5 · <?= $totalReviews ?> <?= $totalReviews === 1 ? 'review' : 'reviews' ?></div></div>
      </div>
      <a class="rv-primary" href="view_material.php?id=<?= (int)$materialId ?>" target="_blank" rel="noopener noreferrer">📄 View Material ↗</a>
    </div>
  </section>
  <div class="rv-columns">
    <section class="rv-card">
      <h2>⭐ Rate This Material</h2>
      <p class="rv-card-intro">Your feedback helps other students discover useful resources.</p>
      <?php if (isset($_SESSION['user_id'])): ?>
        <form action="save_review.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['review_csrf']) ?>">
          <input type="hidden" name="material_id" value="<?= (int)$materialId ?>">
          <label class="rv-label" for="rating">Your Rating</label>
          <select class="rv-select" id="rating" name="rating" required>
            <?php for ($i = 5; $i >= 1; $i--): ?>
              <option value="<?= $i ?>" <?= (int)($myReview['rating'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?> <?= $i === 1 ? 'Star' : 'Stars' ?> <?= str_repeat('★', $i) ?></option>
            <?php endfor; ?>
          </select>
          <label class="rv-label" for="comment">Your Comment</label>
          <textarea class="rv-textarea" id="comment" name="comment" maxlength="2000" placeholder="Share your experience with this material..."><?= e($myReview['comment'] ?? '') ?></textarea>
          <button type="submit" class="rv-primary rv-submit"><?= $myReview ? '✦ Update Review' : '✦ Submit Review' ?></button>
        </form>
      <?php else: ?>
        <div class="rv-login-note">Please <a href="login.php">log in</a> to rate this material and share your thoughts.</div>
      <?php endif; ?>
    </section>
    <section class="rv-card">
      <h2>💬 Student Reviews <span class="rv-count"><?= $totalReviews ?></span></h2>
      <p class="rv-card-intro">See what your fellow students think about this resource.</p>
      <?php if (!$reviews): ?>
        <div class="rv-empty"><div class="rv-empty-icon">💭</div><strong>No reviews yet</strong><p>Be the first to share your opinion!</p></div>
      <?php else: ?>
        <div class="rv-review-list">
          <?php foreach ($reviews as $review): ?>
            <article class="rv-review">
              <div class="rv-review-top">
                <div class="rv-review-user"><span class="rv-avatar"><?= e(mb_substr((string)$review['reviewer_name'], 0, 1, 'UTF-8')) ?></span><div><div class="rv-review-name"><?= e($review['reviewer_name']) ?></div><div class="rv-review-date"><?= e($review['created_at']) ?></div></div></div>
                <div class="rv-stars rv-review-stars" aria-label="<?= (int)$review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int)$review['rating']) ?><?= str_repeat('☆', max(0, 5 - (int)$review['rating'])) ?></div>
              </div>
              <?php if (trim((string)$review['comment']) !== ''): ?><p class="rv-review-comment"><?= nl2br(e($review['comment'])) ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
</body>
</html>

