<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/store.php';

requireRole(['consumer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken()) {
    redirectWithMessage(appPath('explore.php'), 'Permintaan rating tidak valid.', 'error');
}

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$star = filter_input(INPUT_POST, 'star', FILTER_VALIDATE_INT);
if (!$productId || !$star || $star < 1 || $star > 5) {
    redirectWithMessage(appPath('explore.php'), 'Rating harus bernilai 1 sampai 5.', 'error');
}

$productStmt = $conn->prepare('SELECT id FROM product WHERE id = ? AND deleted_at IS NULL');
$productStmt->bind_param('i', $productId);
$productStmt->execute();
if (!$productStmt->get_result()->fetch_assoc()) {
    redirectWithMessage(appPath('explore.php'), 'Produk tidak ditemukan.', 'error');
}

$userId = currentUserId();
$stmt = $conn->prepare('
    INSERT INTO rating (id_product, id_user, star, removed_at)
    VALUES (?, ?, ?, NULL)
    ON DUPLICATE KEY UPDATE star = VALUES(star), removed_at = NULL, created_at = CURRENT_TIMESTAMP
');
$stmt->bind_param('iii', $productId, $userId, $star);
$stmt->execute();

redirectWithMessage(appPath('explore.php'), 'Terima kasih, rating kamu berhasil disimpan.');
?>
