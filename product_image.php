<?php
session_start();

if (($_SESSION['loggedIn'] ?? false) !== true) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit;
}

$stmt = $conn->prepare('SELECT image_data, image_mime FROM catalog_products WHERE id = ? AND is_deleted = 0 LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['image_data']) || empty($row['image_mime'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $row['image_mime']);
header('Content-Length: ' . strlen($row['image_data']));
header('Cache-Control: public, max-age=86400');
echo $row['image_data'];
