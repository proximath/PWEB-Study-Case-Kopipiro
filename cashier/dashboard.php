<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/store.php';
requireRole(['cashier']);

$userId = currentUserId();
$queueStmt = $conn->prepare("
    SELECT o.id, o.amount, o.status, o.created_at,
           COALESCE(customer.name, 'Guest') AS customer_name,
           GROUP_CONCAT(CONCAT(p.name, ' × ', op.quantity) ORDER BY p.name SEPARATOR ', ') AS items
    FROM `order` o
    LEFT JOIN user customer ON customer.id = o.id_user
    LEFT JOIN orderproduct op ON op.id_order = o.id
    LEFT JOIN product p ON p.id = op.id_product
    WHERE o.id_cashier IS NULL AND o.status = 'pending'
    GROUP BY o.id, o.amount, o.status, o.created_at, customer.name
    ORDER BY o.created_at ASC
");
$queueStmt->execute();
$queue = $queueStmt->get_result();

$assignedStmt = $conn->prepare("
    SELECT o.id, o.amount, o.status, o.created_at,
           COALESCE(customer.name, 'Guest') AS customer_name,
           GROUP_CONCAT(CONCAT(p.name, ' × ', op.quantity) ORDER BY p.name SEPARATOR ', ') AS items
    FROM `order` o
    LEFT JOIN user customer ON customer.id = o.id_user
    LEFT JOIN orderproduct op ON op.id_order = o.id
    LEFT JOIN product p ON p.id = op.id_product
    WHERE o.id_cashier = ?
    GROUP BY o.id, o.amount, o.status, o.created_at, customer.name
    ORDER BY FIELD(o.status, 'pending', 'processing', 'completed', 'cancelled'), o.created_at ASC
");
$assignedStmt->bind_param('i', $userId);
$assignedStmt->execute();
$assigned = $assignedStmt->get_result();
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>Cashier Dashboard | Kopipiro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/font.css" rel="stylesheet">
</head>
<body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="max-w-7xl mx-auto px-6 md:px-10 py-10">
        <p class="text-yellow-400 text-sm tracking-widest uppercase">Kopipiro / Kasir</p>
        <h1 class="text-3xl font-bold mt-1 mb-2">Dashboard kasir</h1>
        <p class="text-neutral-400 mb-8"></p>
        <?php if ($flash): ?><div class="mb-6 border px-4 py-3 <?= $flash['type'] === 'error' ? 'border-red-500 text-red-200' : 'border-yellow-600 text-yellow-100' ?>"><?= escapeHtml($flash['message']) ?></div><?php endif; ?>

        <section class="mb-12">
            <div class="flex items-end justify-between gap-4 mb-5"><div><h2 class="text-xl font-bold">Pesanan baru</h2><p class="text-sm text-neutral-400 mt-1"></p></div><span class="text-yellow-300 font-bold"><?= $queue->num_rows ?> pesanan</span></div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php while ($order = $queue->fetch_assoc()): ?>
                    <article class="border border-yellow-700/60 bg-[#21180f] p-5">
                        <div class="flex items-start justify-between gap-4"><div><p class="text-yellow-300 font-bold">Pesanan #<?= (int) $order['id'] ?></p><p class="text-sm text-neutral-400 mt-1"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> · <?= escapeHtml($order['customer_name']) ?></p></div><span class="text-xs border border-yellow-700 px-2 py-1">Menunggu</span></div>
                        <p class="text-sm mt-4"><?= escapeHtml($order['items'] ?: 'Tidak ada detail produk') ?></p>
                        <div class="flex items-center justify-between mt-5"><b class="text-yellow-300"><?= formatRupiah($order['amount']) ?></b><form method="POST" action="order_action.php"><input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="claim"><button type="submit" class="border border-yellow-600 px-4 py-2 font-bold hover:bg-orange-950">Ambil pesanan</button></form></div>
                    </article>
                <?php endwhile; ?>
                <?php if ($queue->num_rows === 0): ?><div class="border border-white/20 p-8 text-neutral-400 lg:col-span-2">Belum ada pesanan baru di antrean.</div><?php endif; ?>
            </div>
        </section>

        <section>
            <div class="flex items-end justify-between gap-4 mb-5"><div><h2 class="text-xl font-bold">Pesanan yang kamu kelola</h2><p class="text-sm text-neutral-400 mt-1"></p></div><span class="text-yellow-300 font-bold"><?= $assigned->num_rows ?> pesanan</span></div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php while ($order = $assigned->fetch_assoc()): ?>
                    <article class="border border-white/20 bg-[#21180f] p-5">
                        <div class="flex items-start justify-between gap-4"><div><p class="font-bold">Pesanan #<?= (int) $order['id'] ?></p><p class="text-sm text-neutral-400 mt-1"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> · <?= escapeHtml($order['customer_name']) ?></p></div><span class="text-xs border border-white/30 px-2 py-1 capitalize"><?= escapeHtml($order['status']) ?></span></div>
                        <p class="text-sm mt-4"><?= escapeHtml($order['items'] ?: 'Tidak ada detail produk') ?></p>
                        <div class="flex items-center justify-between mt-5"><b class="text-yellow-300"><?= formatRupiah($order['amount']) ?></b>
                            <?php if (in_array($order['status'], ['pending', 'processing'], true)): ?>
                                <form method="POST" action="order_action.php" class="flex flex-wrap items-center gap-2"><input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="status">
                                    <?php if ($order['status'] === 'pending'): ?><button name="status" value="processing" class="border border-yellow-600 px-3 py-2 text-sm hover:bg-orange-950">Proses</button><?php endif; ?>
                                    <button name="status" value="completed" class="border border-green-700 px-3 py-2 text-sm hover:bg-green-950">Selesai</button>
                                    <button name="status" value="cancelled" class="border border-red-700 px-3 py-2 text-sm hover:bg-red-950">Batalkan</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
                <?php if ($assigned->num_rows === 0): ?><div class="border border-white/20 p-8 text-neutral-400 lg:col-span-2">Kamu belum mengambil pesanan.</div><?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
