<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['favorite_csrf'])) {
    $_SESSION['favorite_csrf'] = bin2hex(random_bytes(32));
}

$stmt = $conn->prepare(
    'SELECT m.id, m.subject, m.chapter, m.academic_year, m.description,
            m.file_path, m.created_at, u.name AS uploader_name
     FROM favorites f
     INNER JOIN materials m ON m.id = f.material_id
     LEFT JOIN users u ON u.id = m.user_id
     WHERE f.user_id = :user_id
     ORDER BY f.created_at DESC, f.id DESC'
);
$stmt->execute([':user_id' => (int)$_SESSION['user_id']]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($materials);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Favorites — Share Knowledge</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/favorites.css">
</head>
<body class="favorites-midnight">
<header class="mn-nav">
<a href="index.php" class="mn-brand"><span>✦</span>Share Knowledge</a>
<nav class="mn-links" aria-label="Main navigation">
<a href="index.php">Home</a>
<a href="browse.php">Explore</a>
<a href="upload.php">Upload</a>
<a href="messages.php">Messages</a>
<a href="favorites.php" class="active" aria-current="page">Favorites</a>
<a href="profile.php">My Profile</a>
<a href="logout.php" class="mn-logout">Log out</a>
</nav>
</header>
<main class="mn-shell">
<section class="mn-hero">
<div>
<div class="mn-kicker">✦ Your personal study collection</div>
<h1>Saved with <span>love.</span> 💜</h1>
<p>Everything worth revisiting, all in one beautiful place. Pick up where you left off and keep learning.</p>
</div>
<a href="browse.php" class="mn-browse">⌕ Explore Materials ↗</a>
</section>
<div class="mn-summary">
<h2>💗 My Collection</h2>
<div class="mn-count"><strong><?= $total ?></strong> <?= $total === 1 ? 'saved material' : 'saved materials' ?></div>
</div>
<?php if (!$materials): ?>
<section class="mn-empty">
<div class="mn-empty-icon">💜</div>
<h2>No Favorites Yet</h2>
<p>Discover something useful in the library and tap the heart to save it here for later.</p>
<a href="browse.php" class="mn-browse">Explore the Library ↗</a>
</section>
<?php else: ?>
<div class="mn-grid">
<?php foreach ($materials as $material): ?>
<?php $extension = strtolower(pathinfo((string)$material['file_path'], PATHINFO_EXTENSION)); ?>
<article class="mn-card">
<div class="mn-preview">
<span class="mn-type"><?= $extension === 'pdf' ? 'PDF DOCUMENT' : 'IMAGE FILE' ?></span>
<span class="mn-heart" aria-hidden="true">💗</span>
<div class="mn-preview-icon"><?= $extension === 'pdf' ? '📄' : '🖼️' ?></div>
</div>
<div class="mn-card-body">
<span class="mn-subject"><?= e($material['subject']) ?></span>
<h3><?= e($material['chapter']) ?></h3>
<div class="mn-meta">🎓 <?= e($material['academic_year']) ?> · By <?= e($material['uploader_name'] ?? 'Unknown Student') ?></div>
<p class="mn-description"><?= nl2br(e($material['description'])) ?></p>
<small class="mn-date">Uploaded: <?= e($material['created_at']) ?></small>
<div class="mn-actions">
<a class="mn-view" href="view_material.php?id=<?= (int)$material['id'] ?>" target="_blank" rel="noopener noreferrer">View Material ↗</a>
<form action="toggle_favorite.php" method="POST">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['favorite_csrf']) ?>">
<input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
<input type="hidden" name="return_page" value="favorites">
<button type="submit" class="mn-remove">♥ Remove Favorite</button>
</form>
</div>
</div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="mn-footer">✦ Share Knowledge — Learning is better together.</div>
</main>
</body>
</html>
