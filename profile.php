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

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ?');

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    $_SESSION = [];

    session_destroy();

    header('Location: login.php');

    exit;

}

$stmt = $conn->prepare('SELECT id, subject, chapter, academic_year, description, created_at FROM materials WHERE user_id = ? ORDER BY created_at DESC, id DESC');

$stmt->execute([$userId]);

$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare('SELECT m.id, m.subject, m.chapter, m.academic_year, m.description, m.created_at, u.name AS uploader FROM favorites f JOIN materials m ON m.id = f.material_id LEFT JOIN users u ON u.id = m.user_id WHERE f.user_id = ? ORDER BY m.created_at DESC, m.id DESC');

$stmt->execute([$userId]);

$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ?');

$stmt->execute([$userId]);

$totalReviews = (int)$stmt->fetchColumn();

$totalMaterials = count($materials);

$totalFavorites = count($favorites);

$memberSince = !empty($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : '—';

$initial = function_exists('mb_substr') ? mb_substr(trim($user['name']), 0, 1, 'UTF-8') : substr(trim($user['name']), 0, 1);

$roleLabel = ($user['role'] ?? '') === 'admin' ? 'Administrator' : 'Student';

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width,initial-scale=1">

<title>My Space — Share Knowledge</title>

<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/profile.css">

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

</head>

<body>

<header class="topbar">

<a href="index.php" class="brand"><span class="brand-mark">✦</span>Share Knowledge</a>

<nav class="topnav" aria-label="Main navigation">

<a href="index.php">Home</a>

<a href="browse.php">Explore</a>

<a href="upload.php">Upload</a>

<a href="my_uploads.php">My Uploads</a>

<a href="messages.php">Messages</a>

<a href="favorites.php">Favorites</a>

<a href="profile.php" class="current">My Profile</a>

<?php if (($user['role'] ?? '') === 'admin'): ?><a href="admin.php">Admin</a><?php endif; ?>

<a href="logout.php" class="logout">Log out</a>

</nav>

</header>

<main class="page">

<div class="intro"><div><div class="eyebrow">Your student dashboard</div><h1>My Profile <span aria-hidden="true">✦</span></h1><p>Your own corner to learn, share, and grow.</p></div><div class="date-pill">✦ Share Knowledge Community</div></div>

<section class="hero" aria-label="Profile summary">

<div class="hero-inner">

<div class="identity">

<div class="avatar-wrap"><span><?= e($initial) ?></span></div>

<div><div class="hello">Welcome back</div><h2><?= e($user['name']) ?></h2><p>✉ <?= e($user['email']) ?></p><span class="role-pill">✦ <?= e($roleLabel) ?> Member</span></div>

</div>

<div class="hero-cta"><a href="upload.php" class="btn-white">＋ Share a Material</a><a href="my_uploads.php" class="btn-ghost">✎ Manage Uploads</a></div>

</div>

</section>

<div class="stats">

<div class="stat"><div class="stat-emoji">📚</div><div><strong><?= $totalMaterials ?></strong><small>Materials Shared</small></div></div>

<div class="stat"><div class="stat-emoji">💗</div><div><strong><?= $totalFavorites ?></strong><small>Saved Favorites</small></div></div>

<div class="stat"><div class="stat-emoji">⭐</div><div><strong><?= $totalReviews ?></strong><small>Reviews Written</small></div></div>

</div>

<div class="content-grid">

<div>

<section class="panel">

<div class="panel-heading"><div><h2>📚 My Contributions</h2><p>Study resources you've shared with the community</p></div><a href="my_uploads.php" class="text-link">Manage uploads ↗</a></div>

<?php if (!$materials): ?>

<div class="empty"><div class="empty-symbol">📖</div><h3>Your journey starts here</h3><p>You haven't uploaded materials from this account yet. Share your first resource and help someone learn.</p><a href="upload.php" class="purple-btn">＋ Upload a Material</a></div>

<?php else: ?>

<?php foreach ($materials as $material): ?>

<article class="material"><div class="material-head"><span class="chip"><?= e($material['subject']) ?></span><time><?= e(date('M j, Y', strtotime($material['created_at']))) ?></time></div><h3><?= e($material['chapter']) ?></h3><p>🎓 <?= e($material['academic_year']) ?></p><p><?= nl2br(e($material['description'])) ?></p><a class="view" href="view_material.php?id=<?= (int)$material['id'] ?>" target="_blank" rel="noopener noreferrer">View material ↗</a></article>

<?php endforeach; ?>

<?php endif; ?>

</section>

<section class="panel">

<div class="panel-heading"><div><h2>💗 Saved for Later</h2><p>Materials you bookmarked to revisit</p></div><a href="favorites.php" class="text-link">All favorites ↗</a></div>

<?php if (!$favorites): ?>

<div class="empty"><div class="empty-symbol">💜</div><h3>Build your study collection</h3><p>Save useful materials from the library, and they'll appear here automatically.</p><a href="browse.php" class="purple-btn">Explore Materials</a></div>

<?php else: ?>

<?php foreach ($favorites as $material): ?>

<article class="material"><div class="material-head"><span class="chip"><?= e($material['subject']) ?></span><time><?= e(date('M j, Y', strtotime($material['created_at']))) ?></time></div><h3><?= e($material['chapter']) ?></h3><p>Shared by <?= e($material['uploader'] ?? 'Unknown') ?> · <?= e($material['academic_year']) ?></p><a class="view" href="view_material.php?id=<?= (int)$material['id'] ?>" target="_blank" rel="noopener noreferrer">Open saved material ↗</a></article>

<?php endforeach; ?>

<?php endif; ?>

</section>

</div>

<aside class="side-stack">

<section class="panel">

<div class="panel-heading"><div><h2>👤 About Me</h2><p>Your account information</p></div></div>

<div class="detail"><div class="detail-icon">👤</div><div><span>Full name</span><strong><?= e($user['name']) ?></strong></div></div>

<div class="detail"><div class="detail-icon">✉</div><div><span>Email address</span><strong><?= e($user['email']) ?></strong></div></div>

<div class="detail"><div class="detail-icon">🎓</div><div><span>Account type</span><strong><?= e($roleLabel) ?></strong></div></div>

<div class="detail"><div class="detail-icon">📅</div><div><span>Member since</span><strong><?= e($memberSince) ?></strong></div></div>

</section>

<section class="panel">

<div class="panel-heading"><div><h2>⚡ Quick Access</h2><p>Everything you need, one click away</p></div></div>

<div class="quicklinks">

<a href="upload.php"><span>📤 &nbsp;Upload Material</span><span>↗</span></a>

<a href="my_uploads.php"><span>📚 &nbsp;My Uploads</span><span>↗</span></a>

<a href="browse.php"><span>🔎 &nbsp;Browse Library</span><span>↗</span></a>

<a href="favorites.php"><span>💗 &nbsp;My Favorites</span><span>↗</span></a>

<a href="messages.php"><span>💬 &nbsp;Messages</span><span>↗</span></a>

</div>

</section>

</aside>

</div>

<div class="footer">✦ Share Knowledge — Learning is better together.</div>

</main>

</body>

</html>
