<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/store.php';

$cartCount = getCartCount($conn);
$dashboardLink = null;
if (isOwner()) {
    $dashboardLink = appPath('admin/dashboard.php');
} elseif (isCashier()) {
    $dashboardLink = appPath('cashier/dashboard.php');
}
?>

<nav class="flex justify-between sticky top-0 z-20 h-12 px-8 md:px-20 lg:px-40 items-center border-yellow-600 border-b-4 bg-[#1b140e]">
    <a href="<?= escapeHtml(appPath('index.php')) ?>" class="flex h-12 items-center">
        <img src="<?= escapeHtml(appPath('images/logo.png')) ?>" class="h-[90%] object-contain" alt="logo" width="64" height="64">
        <h1 class="text-xl font-bold tracking-wider">KOPIPIRO</h1>
    </a>
    <div class="flex h-full gap-1 md:gap-4 items-center text-sm md:text-base">
        <?php if ($dashboardLink !== null): ?>
            <a href="<?= escapeHtml($dashboardLink) ?>" class="flex items-center h-full gap-2 cursor-pointer px-2 md:px-4 duration-300 hover:bg-orange-950 focus:outline-none focus:bg-orange-950">Dashboard</a>
        <?php else: ?>
            <a href="<?= escapeHtml(appPath('explore.php')) ?>" class="flex items-center h-full gap-2 cursor-pointer px-2 md:px-4 duration-300 hover:bg-orange-950 focus:outline-none focus:bg-orange-950">
                <svg class="h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" />
                </svg>
                Explore
            </a>
            <a href="<?= escapeHtml(appPath('checkout.php')) ?>" class="flex items-center h-full gap-2 cursor-pointer px-2 md:px-4 duration-300 hover:bg-orange-950 focus:outline-none focus:bg-orange-950">
                <svg class="h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="8" cy="21" r="1" />
                    <circle cx="19" cy="21" r="1" />
                    <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
                </svg>
                Cart <?php if ($cartCount > 0): ?><span class="text-yellow-300">(<?= $cartCount ?>)</span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (isAuthenticated()): ?>
            <a href="<?= escapeHtml(appPath('profile.php')) ?>" class="flex items-center h-full group font-bold cursor-pointer px-2 md:px-4 focus:outline-none">
                <div class="flex gap-2 border border-white py-1 px-2 md:px-4 duration-300 group-hover:bg-orange-950 group-focus:bg-orange-950">
                    <svg class="inline h-6 w-6 stroke-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    <span class="hidden sm:inline"><?= escapeHtml($_SESSION['user_name'] ?? 'Akun') ?></span>
                </div>
            </a>
            <form method="POST" action="<?= escapeHtml(appPath('logout.php')) ?>" class="h-full">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <button type="submit" class="flex items-center text-red-300 h-full gap-2 cursor-pointer px-2 md:px-4 duration-300 hover:bg-orange-950 focus:outline-none focus:bg-orange-950">
                    Logout
                </button>
            </form>
        <?php else: ?>
            <a href="<?= escapeHtml(appPath('login.php')) ?>" class="flex items-center h-full group font-bold cursor-pointer px-2 md:px-4 focus:outline-none">
                <div class="flex gap-2 border border-white py-1 px-2 md:px-4 duration-300 group-hover:bg-orange-950 group-focus:bg-orange-950">
                    <svg class="inline h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Login
                </div>
            </a>
        <?php endif; ?>
    </div>
</nav>
