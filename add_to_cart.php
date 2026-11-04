<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken()) {
    redirectWithMessage(appPath('explore.php'), 'Permintaan tidak valid.', 'error');
}

if (isAuthenticated() && !isCustomer()) {
    redirectWithMessage(roleHome(), 'Akun ini tidak dapat melakukan pemesanan.', 'error');
}

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;

if (!$productId || $productId <= 0 || $quantity <= 0 || $quantity > 99) {
    redirectWithMessage(appPath('explore.php'), 'Produk atau jumlah tidak valid.', 'error');
}

$stmt = $conn->prepare('SELECT id FROM product WHERE id = ? AND deleted_at IS NULL');
$stmt->bind_param('i', $productId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    redirectWithMessage(appPath('explore.php'), 'Produk tidak ditemukan atau sudah tidak tersedia.', 'error');
}

$cartMap = getCartMap($conn);
setCartQuantity($conn, $productId, ($cartMap[$productId] ?? 0) + $quantity);
header('Location: ' . appPath('checkout.php'));
exit();
?>
