<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/store.php';
requireRole(['cashier']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken()) {
    redirectWithMessage('dashboard.php', 'Permintaan tidak valid.', 'error');
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
$userId = currentUserId();

if (!$orderId || $orderId <= 0) {
    redirectWithMessage('dashboard.php', 'Pesanan tidak valid.', 'error');
}

if ($action === 'claim') {
    $stmt = $conn->prepare("UPDATE `order` SET id_cashier = ? WHERE id = ? AND id_cashier IS NULL AND status = 'pending'");
    $stmt->bind_param('ii', $userId, $orderId);
    $stmt->execute();
    if ($stmt->affected_rows === 1) {
        redirectWithMessage('dashboard.php', 'Pesanan berhasil diambil.');
    }
    redirectWithMessage('dashboard.php', 'Pesanan sudah diambil kasir lain atau tidak lagi tersedia.', 'error');
}

if ($action === 'status') {
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, ['processing', 'completed', 'cancelled'], true)) {
        redirectWithMessage('dashboard.php', 'Status pesanan tidak valid.', 'error');
    }

    $stmt = $conn->prepare('UPDATE `order` SET status = ? WHERE id = ? AND id_cashier = ? AND status IN (\'pending\', \'processing\')');
    $stmt->bind_param('sii', $status, $orderId, $userId);
    $stmt->execute();
    if ($stmt->affected_rows === 1) {
        redirectWithMessage('dashboard.php', 'Status pesanan berhasil diperbarui.');
    }
    redirectWithMessage('dashboard.php', 'Pesanan tidak ditemukan atau bukan tanggung jawab kamu.', 'error');
}

redirectWithMessage('dashboard.php', 'Aksi tidak dikenali.', 'error');
?>
