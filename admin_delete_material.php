
<?php
session_start();
require_once 'db.php';

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access Denied');
}

// Check admin role from database
$stmt = $conn->prepare(
    'SELECT role FROM users WHERE id = ?'
);
$stmt->execute([(int) $_SESSION['user_id']]);

if ($stmt->fetchColumn() !== 'admin') {
    http_response_code(403);
    exit('Admins only');
}

// Check CSRF token
$sessionToken = $_SESSION['admin_delete_csrf'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($postedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $postedToken)
) {
    http_response_code(403);
    exit('Invalid security token');
}

// Validate material ID
$materialId = filter_input(
    INPUT_POST,
    'material_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$materialId) {
    http_response_code(400);
    exit('Invalid material ID');
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare(
        'SELECT file_path FROM materials WHERE id = ? FOR UPDATE'
    );
    $stmt->execute([$materialId]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        $conn->rollBack();
        http_response_code(404);
        exit('Material not found');
    }

    $fileName = $material['file_path'];

    // Only allow filenames, not arbitrary paths
    if (
        !is_string($fileName) ||
        !preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $fileName)
    ) {
        $conn->rollBack();
        http_response_code(400);
        exit('Invalid stored file reference');
    }

    $uploadDir = dirname(__DIR__)
        . DIRECTORY_SEPARATOR
        . 'share_knowledge_private_uploads';

    $filePath = $uploadDir
        . DIRECTORY_SEPARATOR
        . $fileName;

    // Delete database record
    // Related favorites and reviews use ON DELETE CASCADE
    $deleteStmt = $conn->prepare(
        'DELETE FROM materials WHERE id = ?'
    );
    $deleteStmt->execute([$materialId]);

    $conn->commit();
    // Check if another material still uses the same file
$referenceStmt = $conn->prepare(
    'SELECT COUNT(*) FROM materials WHERE file_path = ?'
);

$referenceStmt->execute([$fileName]);

$remainingReferences = (int) $referenceStmt->fetchColumn();

// Delete the physical file only if no other material uses it
if ($remainingReferences === 0 && is_file($filePath)) {
    if (!@unlink($filePath)) {
        error_log('Could not remove material file: ' . $fileName);
    }
}


    $_SESSION['admin_material_success'] =
        'Material deleted successfully.';

    header('Location: admin_materials.php');
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);
    exit('Could not delete material.');
}
