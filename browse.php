<?php

session_start();

require_once 'db.php';

function e($value) {

  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}

$categories = [

  'All', 'Mathematics', 'Computer Science', 'Engineering',

  'Business', 'Medicine', 'Pharmacy', 'Physics', 'Chemistry',

  'Arts & Humanities', 'Science', 'Education', 'Other'

];

$category = trim((string)($_GET['category'] ?? 'All'));

if (!in_array($category, $categories, true)) {

  $category = 'All';

}

$search = trim((string)($_GET['search'] ?? ''));

$chapter = trim((string)($_GET['chapter'] ?? ''));

$year = trim((string)($_GET['year'] ?? ''));

$sort = (string)($_GET['sort'] ?? 'newest');

if (!in_array($sort, ['newest', 'oldest'], true)) {

  $sort = 'newest';

}

// Academic years come from existing materials, so the dropdown stays up to date.

$yearStmt = $conn->query(

  "SELECT DISTINCT academic_year FROM materials

  WHERE academic_year IS NOT NULL AND academic_year <> ''

  ORDER BY academic_year DESC"

);

$years = $yearStmt->fetchAll(PDO::FETCH_COLUMN);

$where = [];

$params = [];

if ($category !== 'All') {

  $where[] = 'm.subject = :category';

  $params[':category'] = $category;

}

// Normalize common Arabic spelling differences for search.

function normalizeArabicSearch($value) {

  $value = strtr($value, [

    'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',

    'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ـ' => '',

    'َ' => '', 'ً' => '', 'ُ' => '', 'ٌ' => '',

    'ِ' => '', 'ٍ' => '', 'ْ' => '', 'ّ' => ''

  ]);

  return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

}

// SQL normalization is applied to stored text as well as search terms.

function arabicSqlExpression($column) {

  $replacements = [

    'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',

    'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ـ' => '',

    'َ' => '', 'ً' => '', 'ُ' => '', 'ٌ' => '',

    'ِ' => '', 'ٍ' => '', 'ْ' => '', 'ّ' => ''

  ];

  $expression = "COALESCE($column, '')";

  foreach ($replacements as $from => $to) {

    $expression = "REPLACE($expression, '" . $from . "', '" . $to . "')";

  }

  return $expression;

}

if ($search !== '') {

  $normalized = normalizeArabicSearch($search);

  $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

  $tokens = array_slice(array_unique($tokens), 0, 8);

  $fields = [

    arabicSqlExpression('m.subject'),

    arabicSqlExpression('m.chapter'),

    arabicSqlExpression('m.description'),

    arabicSqlExpression('u.name')

  ];

  foreach ($tokens as $index => $token) {

    $tokenConditions = [];

    foreach ($fields as $fieldIndex => $field) {

      $param = ':search_' . $index . '_' . $fieldIndex;

      $tokenConditions[] = "$field LIKE $param ESCAPE '!'";

      $params[$param] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token) . '%';

    }

    $where[] = '(' . implode(' OR ', $tokenConditions) . ')';

  }

}

if ($chapter !== '') {

  $where[] = 'm.chapter LIKE :chapter';

  $params[':chapter'] = '%' . $chapter . '%';

}

if ($year !== '') {

  $where[] = 'm.academic_year = :year';

  $params[':year'] = $year;

}

$sql = "SELECT m.id, m.subject, m.chapter, m.academic_year,

       m.description, m.file_path, m.created_at,

                       u.name AS uploader_name,

                (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.material_id = m.id) AS avg_rating,

                (SELECT COUNT(*) FROM reviews r WHERE r.material_id = m.id) AS review_count

    FROM materials m

    LEFT JOIN users u ON m.user_id = u.id";

if ($where) {

  $sql .= ' WHERE ' . implode(' AND ', $where);

}

$sql .= $sort === 'oldest'

  ? ' ORDER BY m.created_at ASC, m.id ASC'

  : ' ORDER BY m.created_at DESC, m.id DESC';

// Count matching materials before applying pagination.

$countSql = 'SELECT COUNT(*) FROM materials m LEFT JOIN users u ON m.user_id = u.id';

if ($where) {

    $countSql .= ' WHERE ' . implode(' AND ', $where);

}

$countStmt = $conn->prepare($countSql);

$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();

$perPage = 8;

$totalPages = max(1, (int)ceil($total / $perPage));

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

$page = ($page !== false && $page !== null && $page > 0) ? min($page, $totalPages) : 1;

$offset = ($page - 1) * $perPage;

$sql .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

$stmt = $conn->prepare($sql);

$stmt->execute($params);

$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// IDs of materials saved by the logged-in student.

$favoriteIds = [];

if (isset($_SESSION['user_id'])) {

  $favoriteStmt = $conn->prepare('SELECT material_id FROM favorites WHERE user_id = ?');

  $favoriteStmt->execute([(int)$_SESSION['user_id']]);

  $favoriteIds = array_fill_keys(array_map('intval', $favoriteStmt->fetchAll(PDO::FETCH_COLUMN)), true);

}

if (empty($_SESSION['favorite_csrf'])) {

  $_SESSION['favorite_csrf'] = bin2hex(random_bytes(32));

}

function browseUrl($overrides = []) {

  $query = [

    'search' => $_GET['search'] ?? '',

    'category' => $_GET['category'] ?? 'All',

    'chapter' => $_GET['chapter'] ?? '',

    'year' => $_GET['year'] ?? '',

    'sort' => $_GET['sort'] ?? 'newest',

    'page' => $_GET['page'] ?? 1

  ];

  $query = array_merge($query, $overrides);

  return 'browse.php?' . http_build_query($query);

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Explore Library — Share Knowledge</title>

<link rel="stylesheet" href="assets/css/style.css">

<link rel="stylesheet" href="assets/css/browse.css">

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

</head>

<body class="midnight">

<header class="mn-nav">

<a href="index.php" class="mn-brand"><span>✦</span>Share Knowledge</a>

<nav class="mn-links" aria-label="Main navigation">

<a href="index.php">Home</a><a href="browse.php" class="active">Explore</a><a href="upload.php">Upload</a><a href="messages.php">Messages</a><a href="favorites.php">Favorites</a><a href="profile.php">My Profile</a>

<?php if (isset($_SESSION['user_id'])): ?>

<a href="logout.php" class="mn-logout">Log out</a>

<?php else: ?>

<a href="login.php">Login</a><a href="signup.php" class="mn-logout">Sign Up</a>

<?php endif; ?>

</nav>

</header>

<div class="mn-shell">

<section class="mn-hero">

<div class="mn-kicker">✦ Your academic resource hub</div>

<h1>Explore. Discover.<br><span>Learn without limits.</span></h1>

<p>Find notes, slides and study materials shared by students. Search smarter, save your favorites and keep learning.</p>

<div class="mn-hero-pills"><span>📚 Free study resources</span><span>⭐ Student reviews</span><span>💜 Save favorites</span></div>

</section>

<div class="mn-layout">

<aside class="mn-sidebar">

<h2>✦ Categories</h2>

<ul class="mn-categories">

<?php foreach ($categories as $item): ?>

<li><a href="<?= e(browseUrl(['category' => $item, 'page' => 1])) ?>" class="<?= $category === $item ? 'active' : '' ?>"><?= e($item) ?><span>›</span></a></li>

<?php endforeach; ?>

</ul>

</aside>

<main>

<section class="mn-panel">

<div class="mn-panel-head"><h2>🔎 Find your material</h2><span>Search &amp; filter</span></div>

<form method="GET" action="browse.php">

<div class="mn-fields">

<div class="mn-field"><label for="search">Search materials</label><input type="search" name="search" id="search" value="<?= e($search) ?>" placeholder="Subject, chapter, student..."></div>

<div class="mn-field"><label for="category">Subject</label><select name="category" id="category"><?php foreach ($categories as $item): ?><option value="<?= e($item) ?>" <?= $category === $item ? 'selected' : '' ?>><?= e($item === 'All' ? 'All Subjects' : $item) ?></option><?php endforeach; ?></select></div>

<div class="mn-field"><label for="chapter">Chapter</label><input name="chapter" id="chapter" value="<?= e($chapter) ?>" placeholder="e.g. Chapter 1"></div>

<div class="mn-field"><label for="year">Academic Year</label><select name="year" id="year"><option value="">All Years</option><?php foreach ($years as $item): ?><option value="<?= e($item) ?>" <?= $year === (string)$item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div>

</div>

<input type="hidden" name="sort" value="<?= e($sort) ?>">

<div class="mn-actions"><button type="submit" class="mn-primary">⌕ Search Materials</button><a class="mn-reset" href="browse.php">Clear Filters</a></div>

</form>

</section>

<div class="mn-result-bar">

<span><strong><?= $total ?></strong> <?= $total === 1 ? 'material' : 'materials' ?> found</span>

<form method="GET" action="browse.php" class="mn-sort">

<input type="hidden" name="search" value="<?= e($search) ?>">

<input type="hidden" name="category" value="<?= e($category) ?>">

<input type="hidden" name="chapter" value="<?= e($chapter) ?>">

<input type="hidden" name="year" value="<?= e($year) ?>">

<label for="sort">Sort by</label>

<select name="sort" id="sort" onchange="this.form.submit()"><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option><option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option></select>

</form>

</div>

<div class="mn-grid">

<?php if (!$materials): ?>

<div class="mn-empty"><div style="font-size:45px">📚</div><h2>No Materials Found</h2><p>Try a different search or change your filters.</p><a href="browse.php" class="mn-primary">Show All Materials</a></div>

<?php else: ?>

<?php foreach ($materials as $material): ?>

<?php $extension = strtolower(pathinfo((string)$material['file_path'], PATHINFO_EXTENSION)); ?>

<article class="mn-card">

<div class="mn-preview"><span class="mn-type"><?= $extension === 'pdf' ? 'PDF DOCUMENT' : 'IMAGE FILE' ?></span><div class="mn-preview-icon"><?= $extension === 'pdf' ? '📄' : '🖼️' ?></div></div>

<div class="mn-card-body">

<span class="mn-subject"><?= e($material['subject']) ?></span>

<h3><?= e($material['chapter']) ?></h3>

<div class="mn-meta">🎓 <?= e($material['academic_year']) ?> · By <?= e($material['uploader_name'] ?? 'Unknown Student') ?></div>

<p class="mn-desc"><?= nl2br(e($material['description'])) ?></p>

<div class="mn-meta">Uploaded <?= e($material['created_at']) ?></div>

<div class="mn-rating">⭐ <?= $material['avg_rating'] !== null ? e($material['avg_rating']) . ' / 5' : 'Not rated yet' ?> <span style="color:#aeb8d4"> (<?= (int)$material['review_count'] ?> reviews)</span></div>

<div class="mn-card-actions">

<a class="mn-view" href="view_material.php?id=<?= (int)$material['id'] ?>" target="_blank" rel="noopener noreferrer">View Material ↗</a>

<a class="mn-review" href="material_reviews.php?id=<?= (int)$material['id'] ?>">⭐ Reviews</a>

<?php if (isset($_SESSION['user_id'])): ?>

<?php $isFavorite = isset($favoriteIds[(int)$material['id']]); ?>

<form action="toggle_favorite.php" method="POST">

<input type="hidden" name="csrf_token" value="<?= e($_SESSION['favorite_csrf']) ?>">

<input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">

<input type="hidden" name="return_query" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">

<button type="submit" class="mn-favorite <?= $isFavorite ? 'saved' : '' ?>" aria-label="<?= $isFavorite ? 'Remove from Favorites' : 'Add to Favorites' ?>"><?= $isFavorite ? '❤️ Saved' : '🤍 Save' ?></button>

</form>

<?php else: ?><a class="mn-login" href="login.php">Log in to save ♡</a><?php endif; ?>

</div>

</div>

</article>

<?php endforeach; ?>

<?php endif; ?>

</div>

<?php if ($totalPages > 1): ?>

<nav class="mn-pagination" aria-label="Material result pages">

    <?php if ($page > 1): ?>

        <a href="<?= e(browseUrl(['page' => $page - 1])) ?>">← Previous</a>

    <?php endif; ?>

    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>

        <a href="<?= e(browseUrl(['page' => $p])) ?>" class="<?= $p === $page ? 'current' : '' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>

    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>

        <a href="<?= e(browseUrl(['page' => $page + 1])) ?>">Next →</a>

    <?php endif; ?>

</nav>

<?php endif; ?>

</main>

</div>

<div class="mn-footer">✦ Share Knowledge — Learning is better together.</div>

</div>

</body>

</html>



