
<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$materialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$materialId || $materialId < 1) {
    header('Location: my_uploads.php');
    exit;
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$allowedSubjects = [
    'Mathematics',
    'Computer Science',
    'Engineering',
    'Business',
    'Medicine',
    'Pharmacy',
    'Physics',
    'Chemistry',
    'Other'
];

try {
    $stmt = $conn->prepare(
        'SELECT id, subject, chapter, academic_year, description
         FROM materials
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$materialId, $userId]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log('Edit material lookup: ' . $ex->getMessage());
    http_response_code(500);
    exit('Unable to load this material right now.');
}

if (!$material) {
    http_response_code(404);
    exit('Material not found or you do not have permission to edit it.');
}

if (empty($_SESSION['edit_material_csrf'])) {
    $_SESSION['edit_material_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? null;
    $sessionToken = $_SESSION['edit_material_csrf'] ?? null;

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        $error = 'Security verification failed. Please refresh and try again.';
    } else {

        $subject = trim((string)($_POST['subject'] ?? ''));
        $chapter = trim((string)($_POST['chapter'] ?? ''));
        $academicYear = trim((string)($_POST['academic_year'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $material['subject'] = $subject;
        $material['chapter'] = $chapter;
        $material['academic_year'] = $academicYear;
        $material['description'] = $description;

        if (
            !in_array($subject, $allowedSubjects, true) ||
            $chapter === '' ||
            $academicYear === '' ||
            $description === ''
        ) {
            $error = 'Please complete all required fields.';
        } elseif (
            mb_strlen($chapter, 'UTF-8') > 200 ||
            mb_strlen($academicYear, 'UTF-8') > 100 ||
            mb_strlen($description, 'UTF-8') > 5000
        ) {
            $error = 'Some fields are too long.';
        } else {
            try {
                $update = $conn->prepare(
                    'UPDATE materials
                     SET subject = ?,
                         chapter = ?,
                         academic_year = ?,
                         description = ?
                     WHERE id = ? AND user_id = ?'
                );

                $update->execute([
                    $subject,
                    $chapter,
                    $academicYear,
                    $description,
                    $materialId,
                    $userId
                ]);

                $_SESSION['edit_material_csrf'] = bin2hex(random_bytes(32));
                $_SESSION['material_edit_success'] =
                    'Material updated successfully!';

                header('Location: my_uploads.php', true, 303);
                exit;

            } catch (PDOException $ex) {
                error_log('Edit material update: ' . $ex->getMessage());
                $error = 'Unable to save changes. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Material - Share Knowledge</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/edit_material.css">
</head>

<body>

<header class="edit-navbar">
    <div class="edit-logo">🎓 Share Knowledge</div>

    <nav class="edit-links">
        <a href="index.php">Home</a>
        <a href="browse.php">Browse</a>
        <a href="my_uploads.php">My Uploads</a>
        <a href="profile.php">Profile</a>
    </nav>
</header>

<main class="edit-container">
    <div class="edit-card">

        <h1>✏️ Edit Material</h1>

        <p class="edit-subtitle">
            Update your material details below.
        </p>

        <?php if ($error !== ''): ?>
            <div class="edit-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="edit_material.php?id=<?= (int)$materialId ?>">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['edit_material_csrf']) ?>">

            <div class="edit-group">
                <label for="subject">Subject *</label>

                <select name="subject" id="subject" required>
                    <?php foreach ($allowedSubjects as $option): ?>
                        <option
                            value="<?= e($option) ?>"
                            <?= $material['subject'] === $option ? 'selected' : '' ?>
                        >
                            <?= e($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="edit-group">
                <label for="chapter">Chapter / Topic *</label>

                <input
                    type="text"
                    name="chapter"
                    id="chapter"
                    maxlength="200"
                    value="<?= e($material['chapter']) ?>"
                    required
                >
            </div>

            <div class="edit-group">
                <label for="academic_year">
                    Academic Year / Level *
                </label>

                <input
                    type="text"
                    name="academic_year"
                    id="academic_year"
                    maxlength="100"
                    value="<?= e($material['academic_year']) ?>"
                    required
                >
            </div>

            <div class="edit-group">
                <label for="description">Description *</label>

                <textarea
                    name="description"
                    id="description"
                    maxlength="5000"
                    required
                ><?= e($material['description']) ?></textarea>
            </div>

            <div class="edit-actions">
                <button type="submit" class="edit-save">
                    💾 Save Changes
                </button>

                <a href="my_uploads.php" class="edit-cancel">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</main>

</body>
</html>

