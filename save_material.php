
<?php
session_start();
require_once 'db.php';

function uploadError(string $message): void
{
    $_SESSION['upload_error'] = $message;
    header('Location: upload.php', true, 303);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: upload.php');
    exit;
}

// CSRF protection: validate the token sent by upload.php
$sessionToken = $_SESSION['upload_csrf'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;

if (
    !is_string($sessionToken) ||
    !is_string($submittedToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    uploadError('Security verification failed. Please refresh the upload page and try again.');
}

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if (!$userId || $userId < 1) {
    uploadError('Please log in again.');
}

// Check that the user account still exists
try {
    $userStmt = $conn->prepare(
        'SELECT id FROM users WHERE id = ? LIMIT 1'
    );

    $userStmt->execute([$userId]);

    if (!$userStmt->fetchColumn()) {
        uploadError(
            'Your account is no longer available. Please log in again.'
        );
    }
} catch (PDOException $e) {
    error_log('Upload user lookup: ' . $e->getMessage());
    uploadError('Unable to verify your account right now.');
}

$subject = trim((string)($_POST['subject'] ?? ''));
$chapter = trim((string)($_POST['chapter'] ?? ''));
$academicYear = trim((string)($_POST['academic_year'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

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

if (
    !in_array($subject, $allowedSubjects, true) ||
    $chapter === '' ||
    $academicYear === '' ||
    $description === ''
) {
    uploadError('Please complete all required fields.');
}

if (
    mb_strlen($chapter, 'UTF-8') > 200 ||
    mb_strlen($academicYear, 'UTF-8') > 100 ||
    mb_strlen($description, 'UTF-8') > 5000
) {
    uploadError(
        'Some fields are too long (description: max 5000 characters).'
    );
}

if (
    !isset($_FILES['material_file']) ||
    !is_array($_FILES['material_file'])
) {
    uploadError('Please select a file.');
}

$file = $_FILES['material_file'];

if (
    !isset($file['error']) ||
    is_array($file['error'])
) {
    uploadError('Invalid file upload.');
}

switch ($file['error']) {
    case UPLOAD_ERR_OK:
        break;

    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        uploadError(
            'The file exceeds the server upload limit. Maximum allowed: 10 MB.'
        );

    case UPLOAD_ERR_NO_FILE:
        uploadError('Please select a file.');

    default:
        uploadError('File upload failed. Please try again.');
}

$maxSize = 10 * 1024 * 1024;

$tmpName = $file['tmp_name'] ?? null;
$size = $file['size'] ?? null;

if (
    !is_string($tmpName) ||
    !is_int($size) ||
    $size < 1 ||
    $size > $maxSize ||
    !is_uploaded_file($tmpName)
) {
    uploadError(
        'Choose a valid PDF, JPG or PNG file no larger than 10 MB.'
    );
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmpName);

$allowedTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

if (
    !is_string($mime) ||
    !isset($allowedTypes[$mime])
) {
    uploadError('Only PDF, JPG and PNG files are allowed.');
}

// Verify image content or PDF signature
if (
    $mime === 'image/jpeg' ||
    $mime === 'image/png'
) {
    $imageInfo = @getimagesize($tmpName);

    $expected = $mime === 'image/jpeg'
        ? IMAGETYPE_JPEG
        : IMAGETYPE_PNG;

    if (
        $imageInfo === false ||
        ($imageInfo[2] ?? null) !== $expected
    ) {
        uploadError('The image file is not valid.');
    }
} else {
    $handle = @fopen($tmpName, 'rb');

    if ($handle === false) {
        uploadError('Unable to read the PDF file.');
    }

    $signature = fread($handle, 5);
    fclose($handle);

    if ($signature !== '%PDF-') {
        uploadError('The PDF file is not valid.');
    }
}

// Store uploaded files outside the public website folder
$uploadDir = dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'share_knowledge_private_uploads';

if (
    !is_dir($uploadDir) &&
    !@mkdir($uploadDir, 0755, true) &&
    !is_dir($uploadDir)
) {
    uploadError('Could not prepare the upload directory.');
}

if (
    !is_dir($uploadDir) ||
    !is_writable($uploadDir)
) {
    uploadError('The upload directory is not writable.');
}

// Generate a random filename
try {
    $fileName = bin2hex(random_bytes(16))
        . '.'
        . $allowedTypes[$mime];
} catch (Throwable $e) {
    error_log(
        'Upload filename generation: ' . $e->getMessage()
    );

    uploadError('Could not prepare the uploaded file.');
}

$destination = $uploadDir
    . DIRECTORY_SEPARATOR
    . $fileName;

if (!move_uploaded_file($tmpName, $destination)) {
    uploadError('Failed to save the uploaded file.');
}

// Save material information in the database
try {
    $stmt = $conn->prepare(
        'INSERT INTO materials
         (user_id, subject, chapter, academic_year, description, file_path)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $subject,
        $chapter,
        $academicYear,
        $description,
        $fileName
    ]);

    // Rotate CSRF token after a successful upload
    $_SESSION['upload_csrf'] = bin2hex(random_bytes(32));

    $_SESSION['upload_success'] =
        'Material uploaded successfully!';

    header('Location: upload.php', true, 303);
    exit;

} catch (Throwable $e) {
    error_log(
        'Upload database save: ' . $e->getMessage()
    );

    if (
        is_file($destination) &&
        !@unlink($destination)
    ) {
        error_log(
            'Could not remove orphaned upload: ' . $destination
        );
    }

    uploadError(
        'Could not save the material. Please try again.'
    );
}

