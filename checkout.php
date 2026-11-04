<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/store.php';

if (isAuthenticated() && !isCustomer()) {
    header('Location: ' . roleHome());
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        redirectWithMessage('checkout.php', 'Sesi formulir kedaluwarsa. Silakan coba lagi.', 'error');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update') {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        if (!$productId || $quantity === false || $quantity === null || $quantity > 99) {
            redirectWithMessage('checkout.php', 'Jumlah produk tidak valid.', 'error');
        }

        if ($quantity > 0) {
            $productStmt = $conn->prepare('SELECT id FROM product WHERE id = ? AND deleted_at IS NULL');
            $productStmt->bind_param('i', $productId);
            $productStmt->execute();
            if (!$productStmt->get_result()->fetch_assoc()) {
                redirectWithMessage('checkout.php', 'Produk tidak tersedia lagi.', 'error');
            }
        }
        setCartQuantity($conn, $productId, $quantity);
        redirectWithMessage('checkout.php', 'Keranjang berhasil diperbarui.');
    }

    if ($action === 'remove') {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($productId && $productId > 0) {
            setCartQuantity($conn, $productId, 0);
        }
        redirectWithMessage('checkout.php', 'Produk dihapus dari keranjang.');
    }

    if ($action === 'place_order') {
        $items = getCartItems($conn);
        if (!$items) {
            redirectWithMessage('checkout.php', 'Keranjang masih kosong.', 'error');
        }

        $discountEligible = isCustomer();
        $total = 0;
        foreach ($items as $item) {
            $unitPrice = (float) $item['base_price'];
            if ($discountEligible) {
                $unitPrice = round($unitPrice * 0.9, 2);
            }
            $total += $unitPrice * (int) $item['quantity'];
        }
        $total = round($total, 2);

        try {
            $conn->begin_transaction();

            if ($discountEligible) {
                $userId = currentUserId();
                $orderStmt = $conn->prepare("INSERT INTO `order` (id_user, amount, status) VALUES (?, ?, 'pending')");
                $orderStmt->bind_param('id', $userId, $total);
            } else {
                $orderStmt = $conn->prepare("INSERT INTO `order` (id_user, amount, status) VALUES (NULL, ?, 'pending')");
                $orderStmt->bind_param('d', $total);
            }
            $orderStmt->execute();
            $orderId = (int) $conn->insert_id;

            $lineStmt = $conn->prepare('
                INSERT INTO orderproduct (id_order, id_product, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ');
            foreach ($items as $item) {
                $productId = (int) $item['id'];
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $item['base_price'];
                if ($discountEligible) {
                    $unitPrice = round($unitPrice * 0.9, 2);
                }
                $lineStmt->bind_param('iiid', $orderId, $productId, $quantity, $unitPrice);
                $lineStmt->execute();
            }

            if ($discountEligible) {
                $clearStmt = $conn->prepare('DELETE FROM cart WHERE id_user = ?');
                $clearStmt->bind_param('i', $userId);
                $clearStmt->execute();
            }

            $conn->commit();
            if (!$discountEligible) {
                unset($_SESSION['cart']);
            }
            $_SESSION['last_order_id'] = $orderId;
            redirectWithMessage('checkout.php?success=1', 'Pesanan berhasil dibuat.');
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log($exception->getMessage());
            redirectWithMessage('checkout.php', 'Pesanan tidak dapat dibuat.', 'error');
        }
    }
}

$items = getCartItems($conn);
$discountEligible = isCustomer();
$subtotal = 0;
$total = 0;
foreach ($items as $item) {
    $quantity = (int) $item['quantity'];
    $basePrice = (float) $item['base_price'];
    $subtotal += $basePrice * $quantity;
    $unitPrice = $discountEligible ? round($basePrice * 0.9, 2) : $basePrice;
    $total += $unitPrice * $quantity;
}
$subtotal = round($subtotal, 2);
$total = round($total, 2);
$discount = round($subtotal - $total, 2);
$flash = getFlashMessage();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Kopipiro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="css/font.css" rel="stylesheet">
</head>
<body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
    <?php include 'components/navbar.php'; ?>
    <main class="container max-w-6xl mx-auto px-6 md:px-12 py-12">
        <h1 class="text-3xl font-bold mb-2">Keranjang & Checkout</h1>
        <p class="text-neutral-400 mb-8">Periksa pesanan sebelum dikirim ke Kopipiro.</p>

        <?php if ($flash): ?>
            <div class="mb-6 border px-4 py-3 <?= $flash['type'] === 'error' ? 'border-red-500 text-red-200' : 'border-yellow-600 text-yellow-100' ?>">
                <?= escapeHtml($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <section class="border border-white/30 p-10 text-center">
                <p class="text-lg">Keranjang kamu masih kosong.</p>
                <a href="explore.php" class="inline-block mt-6 border border-yellow-600 px-6 py-2 font-bold hover:bg-orange-950">Jelajahi produk</a>
            </section>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_22rem] gap-10 items-start">
                <section class="border border-white/20 divide-y divide-white/15">
                    <?php foreach ($items as $item):
                        $basePrice = (float) $item['base_price'];
                        $unitPrice = $discountEligible ? round($basePrice * 0.9, 2) : $basePrice;
                    ?>
                        <article class="p-4 flex flex-col sm:flex-row gap-4">
                            <img src="<?= escapeHtml($item['image_url'] ?: 'images/logo.png') ?>" alt="<?= escapeHtml($item['name']) ?>" class="w-24 h-24 object-cover bg-[#2a2017]">
                            <div class="flex-1">
                                <h2 class="font-bold text-lg"><?= escapeHtml($item['name']) ?></h2>
                                <?php if ($discountEligible): ?>
                                    <p class="text-sm text-neutral-400 line-through"><?= formatRupiah($basePrice) ?></p>
                                    <p class="text-yellow-300"><?= formatRupiah($unitPrice) ?> <span class="text-xs">(diskon 10%)</span></p>
                                <?php else: ?>
                                    <p class="text-yellow-300"><?= formatRupiah($unitPrice) ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-neutral-400 mt-1">Subtotal: <?= formatRupiah($unitPrice * (int) $item['quantity']) ?></p>
                            </div>
                            <div class="flex sm:flex-col justify-between sm:items-end gap-3">
                                <form method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <input type="text" name="quantity" min="0" max="99" value="<?= (int) $item['quantity'] ?>" class="w-16 bg-[#2a2017] border border-white/30 px-2 py-1 text-center" aria-label="Jumlah <?= escapeHtml($item['name']) ?>">
                                    <button type="submit" class="text-xs underline text-yellow-200 cursor-pointer">Ubah</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" class="text-xs text-red-300 underline cursor-pointer">Hapus</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <aside class="border border-yellow-700/70 p-6">
                    <h2 class="text-xl font-bold mb-6">Ringkasan pesanan</h2>
                    <div class="flex justify-between text-sm mb-3"><span>Subtotal</span><span><?= formatRupiah($subtotal) ?></span></div>
                    <div class="flex justify-between text-sm mb-4"><span>Diskon member</span><span class="text-yellow-300">-<?= formatRupiah($discount) ?></span></div>
                    <div class="border-t border-white/20 pt-4 flex justify-between font-bold text-lg"><span>Total</span><span class="text-yellow-300"><?= formatRupiah($total) ?></span></div>
                    <?php if (!$discountEligible): ?>
                        <p class="text-xs text-neutral-400 mt-4">Harga normal berlaku untuk pesanan tanpa login. <a href="login.php" class="text-yellow-200 underline">Login</a> untuk mendapatkan diskon 10%.</p>
                    <?php else: ?>
                        <p class="text-xs text-yellow-200 mt-4">Diskon member 10% sudah diterapkan.</p>
                    <?php endif; ?>
                    <form method="POST" class="mt-6">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                        <input type="hidden" name="action" value="place_order">
                        <button type="submit" class="w-full border-2 border-yellow-600 py-3 font-bold hover:bg-orange-950 duration-300">Buat pesanan</button>
                    </form>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
