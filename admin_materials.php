<?php
session_start();
require_once 'db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, name, role FROM users WHERE id = ?');
$stmt->execute([(int)$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    exit('Access Denied. Admins only.');
}

if (empty($_SESSION['admin_delete_csrf'])) {
    $_SESSION['admin_delete_csrf'] = bin2hex(random_bytes(32));
}
$success = $_SESSION['admin_material_success'] ?? '';
unset($_SESSION['admin_material_success']);

$search = trim((string)($_GET['search'] ?? ''));
$sql = 'SELECT m.id, m.subject, m.chapter,
               m.created_at, u.name AS uploader
        FROM materials m
        LEFT JOIN users u ON m.user_id = u.id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE m.subject LIKE ? OR m.chapter LIKE ? OR u.name LIKE ?';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
}
$sql .= ' ORDER BY m.created_at DESC, m.id DESC LIMIT 200';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalMaterials = (int)$conn->query('SELECT COUNT(*) FROM materials')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Materials - Share Knowledge</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/admin_materials.css">
</head>
<body class="mn-admin-materials">
<header class="mn-nav">
  <a class="mn-brand" href="index.php"><span class="mn-brand-icon">🎓</span><span>Share Knowledge</span></a>
  <nav class="mn-navlinks" aria-label="Main navigation">
    <a href="index.php">Home</a><a href="browse.php">Browse</a><a href="upload.php">Upload</a><a href="messages.php">Messages</a><a href="favorites.php">Favorites</a><a href="admin.php" class="active">Admin 👑</a>
  </nav>
  <div class="mn-account"><span>Welcome, <?= e($admin['name']) ?></span><a class="mn-logout" href="logout.php">Logout</a></div>
</header>
<main class="mn-shell">
  <a class="mn-back" href="admin.php">← Back to Admin Dashboard</a>
  <section class="mn-hero">
    <div class="mn-eyebrow">✦ CONTENT MANAGEMENT CENTER</div>
    <h1>Manage <span>Materials.</span> 📚</h1>
    <p>Review, find, and manage shared learning resources across your community.</p>
  </section>
  <div class="mn-metrics">
    <div class="mn-metric"><div class="mn-metric-icon">📚</div><div><strong><?= $totalMaterials ?></strong><span>Total Uploaded Materials</span></div></div>
    <div class="mn-metric"><div class="mn-metric-icon">🔎</div><div><strong><?= count($materials) ?></strong><span><?= $search !== '' ? 'Matching Materials (up to 200)' : 'Recent Materials Shown (up to 200)' ?></span></div></div>
  </div>
  <section class="mn-panel">
    <div class="mn-panel-top"><h2>📁 Materials Library</h2><p>Browse recent uploads or search by subject, chapter, or uploader.</p></div>
    <?php if ($success): ?><div class="mn-success" role="status">✓ <?= e($success) ?></div><?php endif; ?>
    <form class="mn-search" method="get" action="admin_materials.php" role="search">
      <input type="search" name="search" value="<?= e($search) ?>" placeholder="⌕ Search subject, chapter, or uploader..." aria-label="Search materials">
      <button class="mn-btn mn-primary" type="submit">🔎 Search</button>
      <a class="mn-btn mn-secondary" href="admin_materials.php">Clear</a>
    </form>
    <div class="mn-table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Subject</th><th>Chapter</th><th>Uploaded By</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($materials as $material): ?>
            <tr>
              <td class="mn-id">#<?= (int)$material['id'] ?></td>
              <td><span class="mn-subject"><?= e($material['subject']) ?></span></td>
              <td class="mn-chapter"><?= e($material['chapter']) ?></td>
              <td><?= e($material['uploader'] ?? 'Unknown') ?></td>
              <td><?= e($material['created_at']) ?></td>
              <td>
                <div class="mn-actions">
                  <a class="mn-btn mn-primary" href="view_material.php?id=<?= (int)$material['id'] ?>" target="_blank" rel="noopener noreferrer">↗ View</a>
                  <form class="mn-delete-form" action="admin_delete_material.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this material?');">
                    <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_delete_csrf']) ?>">
                    <button class="mn-btn mn-danger" type="submit">🗑 Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$materials): ?><tr><td colspan="6" class="mn-empty">📭 No materials found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="mn-note">Showing up to 200 recent materials. Deleting a material also removes its associated reviews and favorites.</p>
  </section>
  <div class="mn-footerlinks"><a class="mn-btn mn-secondary" href="admin.php">← Admin Dashboard</a><a class="mn-btn mn-secondary" href="admin_users.php">👥 Manage Students</a></div>
</main>
</body>
</html>
