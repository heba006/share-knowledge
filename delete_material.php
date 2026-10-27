
<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function deleteError(string $message): void
{
    $_SESSION['material_delete_error'] = $message;
    header('Location: my_uploads.php', true, 303);
    exit;
}

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);

if (!$userId || $userId < 1) {
    deleteError('Please log in again.');
}

// Delete requests must use POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_uploads.php', true, 303);
    exit;
}

// Verify CSRF token.
$sessionToken = $_SESSION['delete_material_csrf'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;

if (
    !is_string($sessionToken) ||
    !is_string($submittedToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    deleteError('Security verification failed. Please refresh and try again.');
}

$materialId = filter_var(
    $_POST['material_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$materialId || $materialId < 1) {
    deleteError('Invalid material.');
}

try {
    // Lock the material while checking ownership and deleting.
    $conn->beginTransaction();

    $stmt = $conn->prepare(
        'SELECT file_path
         FROM materials
         WHERE id = ? AND user_id = ?
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([$materialId, $userId]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        $conn->rollBack();
        deleteError('Material not found or you do not have permission to delete it.');
    }

    $fileName = $material['file_path'];

    $deleteStmt = $conn->prepare(
        'DELETE FROM materials
         WHERE id = ? AND user_id = ?'
    );

    $deleteStmt->execute([$materialId, $userId]);

    if ($deleteStmt->rowCount() !== 1) {
        $conn->rollBack();
        deleteError('Could not delete the material.');
    }

    // Check whether another material record references the same file.
    $referenceStmt = $conn->prepare(
        'SELECT COUNT(*)
         FROM materials
         WHERE file_path = ?'
    );

    $referenceStmt->execute([$fileName]);
    $remainingReferences = (int) $referenceStmt->fetchColumn();

    $conn->commit();

    // Remove the physical file only when no other record uses it.
    if ($remainingReferences === 0) {
        $uploadDir = dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . 'share_knowledge_private_uploads';

        // Accept only generated upload filenames, never directory paths.
        if (
            is_string($fileName) &&
            preg_match('/\A[a-f0-9]{32}\.(pdf|jpg|png)\z/D', $fileName)
        ) {
            $filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

            if (is_file($filePath) && !@unlink($filePath)) {
                error_log('Could not remove uploaded file: ' . $fileName);
            }
        }
    }

    $_SESSION['delete_material_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['material_delete_success'] = 'Material deleted successfully!';

    header('Location: my_uploads.php', true, 303);
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Delete material: ' . $e->getMessage());
    deleteError('Could not delete the material. Please try again.');
}
