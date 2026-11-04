<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/store.php';
requireAuth();

$userId = currentUserId();
$stmt = $conn->prepare('SELECT name, role FROM user WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$orders = null;
if (isCustomer()) {
    $orderStmt = $conn->prepare("
        SELECT o.id, o.amount, o.status, o.created_at,
               GROUP_CONCAT(CONCAT(p.name, ' × ', op.quantity) ORDER BY p.name SEPARATOR ', ') AS items
        FROM `order` o
        LEFT JOIN orderproduct op ON op.id_order = o.id
        LEFT JOIN product p ON p.id = op.id_product
        WHERE o.id_user = ?
        GROUP BY o.id, o.amount, o.status, o.created_at
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $orderStmt->bind_param('i', $userId);
    $orderStmt->execute();
    $orders = $orderStmt->get_result();
}
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil | Kopipiro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="css/font.css" rel="stylesheet">
</head>
<body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
    <?php include 'components/navbar.php'; ?>
    <main class="max-w-6xl mx-auto px-6 md:px-10 py-12">
        <h1 class="text-3xl font-bold mb-8">Profil akun</h1>
        <?php if ($flash): ?><div class="mb-6 border px-4 py-3 <?= $flash['type'] === 'error' ? 'border-red-500 text-red-200' : 'border-yellow-600 text-yellow-100' ?>"><?= escapeHtml($flash['message']) ?></div><?php endif; ?>
        <section class="border border-yellow-700/70 bg-[#21180f] p-6 mb-10 max-w-xl">
            <p class="text-sm text-neutral-400">Username</p>
            <p class="text-xl font-bold mt-1"><?= escapeHtml($user['name'] ?? $_SESSION['user_name'] ?? '') ?></p>
            <p class="text-sm text-neutral-400 mt-4">Role</p>
            <p class="capitalize text-yellow-300 mt-1"><?= escapeHtml(currentUserRole() === 'consumer' ? 'Customer' : (currentUserRole() === 'cashier' ? 'Kasir' : 'Owner')) ?></p>
            <?php if (isOwner()): ?><a href="admin/dashboard.php" class="inline-block mt-5 underline text-yellow-200">Buka dashboard owner</a><?php elseif (isCashier()): ?><a href="cashier/dashboard.php" class="inline-block mt-5 underline text-yellow-200">Buka dashboard kasir</a><?php endif; ?>
        </section>

        <?php if (isCustomer()): ?>
            <section class="border border-white/20 bg-[#21180f]">
                <div class="p-5 border-b border-white/20"><h2 class="text-xl font-bold">Riwayat pesanan</h2></div>
                <div class="divide-y divide-white/10">
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <article class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-3">
                            <div><b>Pesanan #<?= (int) $order['id'] ?></b><p class="text-sm text-neutral-400 mt-1"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> · <?= escapeHtml($order['items'] ?: 'Detail produk tidak tersedia') ?></p></div>
                            <div class="md:text-right"><p class="text-yellow-300 font-bold"><?= formatRupiah($order['amount']) ?></p><p class="text-sm capitalize text-neutral-400"><?= escapeHtml($order['status']) ?></p></div>
                        </article>
                    <?php endwhile; ?>
                    <?php if ($orders->num_rows === 0): ?><p class="p-6 text-neutral-400">Belum ada riwayat pesanan.</p><?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
