<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !isset($_SESSION['favorite_csrf']) ||
    !hash_equals($_SESSION['favorite_csrf'], $token)) {
    http_response_code(403);
    exit('Invalid request. Refresh the Browse page and try again.');
}

$materialId = filter_input(INPUT_POST, 'material_id', FILTER_VALIDATE_INT);
if (!$materialId || $materialId < 1) {
    http_response_code(400);
    exit('Invalid material.');
}

$userId = (int)$_SESSION['user_id'];
$check = $conn->prepare('SELECT id FROM materials WHERE id = ?');
$check->execute([$materialId]);
if (!$check->fetchColumn()) {
    http_response_code(404);
    exit('Material not found.');
}

$exists = $conn->prepare('SELECT id FROM favorites WHERE user_id = ? AND material_id = ?');
$exists->execute([$userId, $materialId]);
if ($exists->fetchColumn()) {
    $stmt = $conn->prepare('DELETE FROM favorites WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
} else {
    $stmt = $conn->prepare('INSERT IGNORE INTO favorites (user_id, material_id) VALUES (?, ?)');
    $stmt->execute([$userId, $materialId]);
}

// Return to the Favorites page when removing a saved item there.
if (($_POST['return_page'] ?? '') === 'favorites') {
    header('Location: favorites.php', true, 303);
    exit;
}

// Keep only the recognized Browse filters; never redirect to an arbitrary URL.
$returnQuery = $_POST['return_query'] ?? '';
$filters = [];
if (is_string($returnQuery) && strlen($returnQuery) <= 2000) {
    parse_str($returnQuery, $raw);
    foreach (['search', 'category', 'chapter', 'year', 'sort'] as $key) {
        if (isset($raw[$key]) && is_string($raw[$key])) {
            $filters[$key] = $raw[$key];
        }
    }
}
header('Location: browse.php' . ($filters ? '?' . http_build_query($filters) : ''), true, 303);
exit;
