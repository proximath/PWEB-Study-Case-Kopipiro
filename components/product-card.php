<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/store.php';

function renderProductCard($id, $name, $basePrice, $imgUrl, $description, $ratingAverage = 0, $ratingCount = 0, $userRating = null) {
    $image = $imgUrl ? (string) $imgUrl : 'images/logo.png';
    $safeImage = escapeHtml($image);
    $safeName = escapeHtml($name);
    $safeDescription = escapeHtml($description);
    $price = formatRupiah($basePrice);
    $average = (float) $ratingAverage;
    $averageText = number_format($average, 1, ',', '.');
    $filledStars = max(0, min(5, (int) round($average)));
    $starText = str_repeat('★', $filledStars) . str_repeat('☆', 5 - $filledStars);
    $productId = (int) $id;

    ob_start();
    ?>
    <div class="group relative min-w-0">
        <img src="<?= $safeImage ?>" alt="<?= $safeName ?>" class="w-full aspect-square object-cover bg-[#2a2017]">
        <div class="p-4">
            <b class="block font-bold text-lg mb-1"><?= $safeName ?></b>
            <span class="block text-yellow-400 text-sm mb-2"><?= $price ?></span>
            <div class="flex items-center gap-2 text-sm mb-2" aria-label="Rating <?= $averageText ?> dari 5">
                <span class="text-yellow-400 tracking-widest"><?= $starText ?></span>
                <?php if ((int) $ratingCount > 0): ?>
                    <span><?= $averageText ?> <span class="text-neutral-400">(<?= (int) $ratingCount ?>)</span></span>
                <?php else: ?>
                    <span class="text-neutral-400">Belum ada rating</span>
                <?php endif; ?>
            </div>
            <span class="block text-sm text-neutral-300 min-h-10"><?= $safeDescription ?></span>

            <?php if (!isAuthenticated() || isCustomer()): ?>
                <form method="POST" action="<?= escapeHtml(appPath('add_to_cart.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <button type="submit" class="text-xs mt-4 block cursor-pointer hover:text-yellow-200">
                    <svg class="inline-block mr-2 scale-80 h-6 w-6 stroke-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1" />
                        <circle cx="20" cy="21" r="1" />
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                        <path d="M14 8v6" />
                        <path d="M11 11h6" />
                    </svg>
                    <u>Tambah ke keranjang</u>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (isCustomer()): ?>
                <form method="POST" action="<?= escapeHtml(appPath('rate_product.php')) ?>" class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <select id="rating-<?= $productId ?>" name="star" class="bg-[#2a2017] border border-yellow-700 px-2 py-1 text-gray-100">
                        <?php if (!$userRating): ?><option value="" disabled selected>Pilih rating</option><?php endif; ?>
                        <?php for ($star = 5; $star >= 1; $star--): ?>
                            <option value="<?= $star ?>" <?= (int) $userRating === $star ? 'selected' : '' ?>><?= $star ?> ★</option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="underline text-yellow-200 hover:text-yellow-100">Kirim</button>
                </form>
            <?php elseif (!isAuthenticated()): ?>
                <p class="text-xs mt-3 text-neutral-400"><a href="<?= escapeHtml(appPath('login.php')) ?>" class="underline text-yellow-200">Login</a> untuk memberi rating.</p>
            <?php endif; ?>
        </div>
        <div class="-z-1 h-full w-full absolute top-0 left-0 duration-300 group-hover:bg-yellow-700/20 group-hover:scale-105"></div>
    </div>
    <?php
    return ob_get_clean();
}
?>
