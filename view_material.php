
<?php
session_start();
require_once "db.php";

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid material ID.");
}

$stmt = $conn->prepare(
    "SELECT file_path FROM materials WHERE id = ?"
);

$stmt->execute([$id]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    http_response_code(404);
    exit("Material not found.");
}

$fileName = $material["file_path"];

// Accept only generated file names
if (!preg_match(
    '/^[a-f0-9]{32}\.(pdf|jpg|png)$/',
    $fileName
)) {
    http_response_code(404);
    exit("Invalid file reference.");
}

$uploadDir = dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    "share_knowledge_private_uploads";

$filePath = $uploadDir .
    DIRECTORY_SEPARATOR .
    $fileName;

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit("File not found on server.");
}

$extension = strtolower(pathinfo(
    $fileName,
    PATHINFO_EXTENSION
));

$mimeTypes = [
    "pdf" => "application/pdf",
    "jpg" => "image/jpeg",
    "png" => "image/png"
];

header("Content-Type: " . $mimeTypes[$extension]);
header("Content-Disposition: inline; filename=\"$fileName\"");
header("X-Content-Type-Options: nosniff");
header("Content-Length: " . filesize($filePath));

readfile($filePath);
exit;
?>
