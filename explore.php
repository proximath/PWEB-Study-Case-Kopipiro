<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/store.php';
require_once 'components/product-card.php';

function getProductsByType(mysqli $conn, string $type, ?int $userId): mysqli_result {
    $stmt = $conn->prepare('
        SELECT p.id, p.name, p.base_price, p.image_url, p.description,
               COALESCE(AVG(r.star), 0) AS rating_average,
               COUNT(r.id_user) AS rating_count,
               MAX(CASE WHEN r.id_user = ? THEN r.star END) AS user_rating
        FROM product p
        LEFT JOIN rating r ON r.id_product = p.id AND r.removed_at IS NULL
        WHERE p.deleted_at IS NULL AND p.type = ?
        GROUP BY p.id, p.name, p.base_price, p.image_url, p.description
        ORDER BY p.created_at DESC
    ');
    $ratingUserId = $userId ?? 0;
    $stmt->bind_param('is', $ratingUserId, $type);
    $stmt->execute();
    return $stmt->get_result();
}

$coffee = getProductsByType($conn, 'coffee', currentUserId());
$other = getProductsByType($conn, 'other', currentUserId());
$flash = getFlashMessage();
?>

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Explore | Kopipiro</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link href="css/font.css" rel="stylesheet">
    </head>
    <body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
        <?php include 'components/navbar.php'; ?>
        <main class="container px-6 md:px-12 lg:px-40 mx-auto pb-24">
            <?php if ($flash): ?>
                <div class="mt-6 border px-4 py-3 <?= $flash['type'] === 'error' ? 'border-red-500 text-red-200' : 'border-yellow-600 text-yellow-100' ?>">
                    <?= escapeHtml($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if (!isAuthenticated()): ?>
                <section class="flex flex-col sm:flex-row gap-8 mt-16 p-4 border-2 border-white max-w-3xl mx-auto justify-between">
                    <div>
                        <h2 class="text-xl font-bold">Promo spesial!</h2>
                        <p class="mt-2 text-sm text-gray-300">Buat akun dan dapatkan potongan harga 10% untuk <b>semua</b> produk di Kopipiro.</p>
                    </div>
                    <div class="flex items-center shrink-0">
                        <a href="login.php" class="flex items-center group font-bold cursor-pointer px-4 focus:outline-none">
                            <span class="flex gap-2 border border-white py-1 px-4 duration-300 group-hover:bg-orange-950 group-focus:bg-orange-950">Login</span>
                        </a>
                    </div>
                </section>
            <?php endif; ?>

            <section class="mt-16 w-full h-min">
                <div class="relative mb-8">
                    <div class="absolute border-b border-gray-300/50 top-1/2 left-0 -z-0 w-full"></div>
                    <h2 class="relative inline-block px-4 ml-4 md:ml-20 text-2xl font-bold bg-[#1b140e]">Produk Kopi</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
                    <?php while ($row = $coffee->fetch_assoc()): ?>
                        <?= renderProductCard($row['id'], $row['name'], $row['base_price'], $row['image_url'], $row['description'], $row['rating_average'], $row['rating_count'], $row['user_rating']) ?>
                    <?php endwhile; ?>
                    <?php if ($coffee->num_rows === 0): ?><p class="text-neutral-400">Belum ada produk kopi.</p><?php endif; ?>
                </div>
            </section>

            <section class="mt-20 w-full h-min">
                <div class="relative mb-8">
                    <div class="absolute border-b border-gray-300/50 top-1/2 left-0 -z-0 w-full"></div>
                    <h2 class="relative inline-block px-4 ml-4 md:ml-20 text-2xl font-bold bg-[#1b140e]">Produk Lainnya</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
                    <?php while ($row = $other->fetch_assoc()): ?>
                        <?= renderProductCard($row['id'], $row['name'], $row['base_price'], $row['image_url'], $row['description'], $row['rating_average'], $row['rating_count'], $row['user_rating']) ?>
                    <?php endwhile; ?>
                    <?php if ($other->num_rows === 0): ?><p class="text-neutral-400">Belum ada produk lainnya.</p><?php endif; ?>
                </div>
            </section>
        </main>
    </body>
</html>
