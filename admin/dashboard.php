<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/store.php';
requireRole(['owner']);

$period = (string) ($_GET['period'] ?? '30');
$days = in_array($period, ['7', '30', '90'], true) ? (int) $period : 30;
$dateCondition = "created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";

$statsResult = $conn->query("
    SELECT
        (SELECT COALESCE(SUM(amount), 0) FROM `order` WHERE status = 'completed') AS total_sales,
        (SELECT COUNT(*) FROM `order`) AS total_orders,
        (SELECT COUNT(*) FROM `order` WHERE status = 'pending') AS pending_orders,
        (SELECT COUNT(*) FROM user WHERE role IN ('consumer', 'customer', 'pelanggan')) AS total_customers,
        (SELECT COUNT(*) FROM product WHERE deleted_at IS NULL) AS total_products
");
$stats = $statsResult->fetch_assoc();

$chartStmt = $conn->prepare("
    SELECT DATE(created_at) AS sale_date, SUM(amount) AS daily_sales
    FROM `order`
    WHERE status = 'completed' AND {$dateCondition}
    GROUP BY DATE(created_at)
    ORDER BY sale_date
");
$chartStmt->execute();
$chartResult = $chartStmt->get_result();
$chartLabels = [];
$chartValues = [];
while ($row = $chartResult->fetch_assoc()) {
    $chartLabels[] = date('d M', strtotime($row['sale_date']));
    $chartValues[] = (float) $row['daily_sales'];
}

$orders = $conn->query("
    SELECT o.id, o.amount, o.status, o.created_at,
           COALESCE(customer.name, 'Guest') AS customer_name,
           COALESCE(cashier.name, '-') AS cashier_name,
           GROUP_CONCAT(CONCAT(p.name, ' × ', op.quantity) ORDER BY p.name SEPARATOR ', ') AS items
    FROM `order` o
    LEFT JOIN user customer ON customer.id = o.id_user
    LEFT JOIN user cashier ON cashier.id = o.id_cashier
    LEFT JOIN orderproduct op ON op.id_order = o.id
    LEFT JOIN product p ON p.id = op.id_product
    GROUP BY o.id, o.amount, o.status, o.created_at, customer.name, cashier.name
    ORDER BY o.created_at DESC
");
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard | Kopipiro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../css/font.css" rel="stylesheet">
</head>
<body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="max-w-7xl mx-auto px-6 md:px-10 py-10">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <p class="text-yellow-400 text-sm tracking-widest uppercase">Kopipiro / Owner</p>
                <h1 class="text-3xl font-bold mt-1">Dashboard</h1>
                <p class="text-neutral-400 mt-2">Ringkasan operasional dan penjualan Kopipiro.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="products.php" class="border border-yellow-600 px-4 py-2 hover:bg-orange-950">Kelola produk</a>
                <a href="users.php" class="border border-white/40 px-4 py-2 hover:bg-orange-950">Kelola pengguna</a>
                <a href="export.php" class="border border-white/40 px-4 py-2 hover:bg-orange-950">Unduh Excel (CSV)</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="mb-6 border px-4 py-3 <?= $flash['type'] === 'error' ? 'border-red-500 text-red-200' : 'border-yellow-600 text-yellow-100' ?>"><?= escapeHtml($flash['message']) ?></div>
        <?php endif; ?>

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-10">
            <?php
            $statCards = [
                ['Penjualan selesai', formatRupiah($stats['total_sales'])],
                ['Total pesanan', number_format((int) $stats['total_orders'], 0, ',', '.')],
                ['Menunggu kasir', number_format((int) $stats['pending_orders'], 0, ',', '.')],
                ['Customer', number_format((int) $stats['total_customers'], 0, ',', '.')],
                ['Produk aktif', number_format((int) $stats['total_products'], 0, ',', '.')]
            ];
            foreach ($statCards as [$label, $value]): ?>
                <article class="border border-white/20 p-5 bg-[#21180f]">
                    <p class="text-sm text-neutral-400"><?= escapeHtml($label) ?></p>
                    <p class="text-2xl font-bold text-yellow-300 mt-2"><?= escapeHtml($value) ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="border border-white/20 bg-[#21180f] p-5 md:p-7 mb-10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold">Grafik penjualan</h2>
                    <p class="text-sm text-neutral-400 mt-1">Hanya pesanan berstatus selesai.</p>
                </div>
                <form method="GET" class="flex items-center gap-2 text-sm">
                    <label for="period">Periode</label>
                    <select id="period" name="period" onchange="this.form.submit()" class="bg-[#2a2017] border border-white/30 px-3 py-2">
                        <option value="7" <?= $period === '7' ? 'selected' : '' ?>>7 hari</option>
                        <option value="30" <?= $period === '30' ? 'selected' : '' ?>>30 hari</option>
                        <option value="90" <?= $period === '90' ? 'selected' : '' ?>>90 hari</option>
                    </select>
                </form>
            </div>
            <div class="h-72"><canvas id="sales-chart"></canvas></div>
        </section>

        <section class="border border-white/20 bg-[#21180f]">
            <div class="flex items-center justify-between gap-4 p-5 border-b border-white/20">
                <div>
                    <h2 class="text-xl font-bold">Semua pesanan</h2>
                    <p class="text-sm text-neutral-400 mt-1">Seluruh pesanan, termasuk pesanan guest.</p>
                </div>
                <a href="export.php" class="text-sm text-yellow-200 underline">Ekspor data</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[760px]">
                    <thead class="text-left text-neutral-400 bg-[#2a2017]">
                        <tr><th class="p-4">ID</th><th class="p-4">Customer</th><th class="p-4">Item</th><th class="p-4">Kasir</th><th class="p-4">Tanggal</th><th class="p-4">Total</th><th class="p-4">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td class="p-4">#<?= (int) $order['id'] ?></td>
                                <td class="p-4"><?= escapeHtml($order['customer_name']) ?></td>
                                <td class="p-4 max-w-xs"><?= escapeHtml($order['items'] ?: '-') ?></td>
                                <td class="p-4"><?= escapeHtml($order['cashier_name']) ?></td>
                                <td class="p-4"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                <td class="p-4 text-yellow-300"><?= formatRupiah($order['amount']) ?></td>
                                <td class="p-4 capitalize"><?= escapeHtml($order['status']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($orders->num_rows === 0): ?><tr><td colspan="7" class="p-6 text-center text-neutral-400">Belum ada pesanan.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script>
        const salesLabels = <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const salesValues = <?= json_encode($chartValues, JSON_NUMERIC_CHECK) ?>;
        new Chart(document.getElementById('sales-chart'), {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Penjualan',
                    data: salesValues,
                    borderColor: '#d6a547',
                    backgroundColor: 'rgba(214, 165, 71, 0.15)',
                    fill: true,
                    tension: 0.25
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#e5e7eb' } } },
                scales: {
                    x: { ticks: { color: '#a3a3a3' }, grid: { color: 'rgba(255,255,255,0.08)' } },
                    y: { beginAtZero: true, ticks: { color: '#a3a3a3' }, grid: { color: 'rgba(255,255,255,0.08)' } }
                }
            }
        });
    </script>
</body>
</html>
