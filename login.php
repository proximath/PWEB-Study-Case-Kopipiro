<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/store.php';

if (isAuthenticated()) {
    header('Location: ' . roleHome());
    exit();
}

$username = '';
$invalidCreds = false;
$usernameEmpty = false;
$passwordEmpty = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $usernameEmpty = $username === '';
    $passwordEmpty = $password === '';

    if (!verifyCsrfToken()) {
        $invalidCreds = true;
    } elseif (!$usernameEmpty && !$passwordEmpty) {
        $stmt = $conn->prepare('SELECT id, name, password, role FROM user WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_role'] = strtolower((string) $row['role']);

            if (isCustomer()) {
                mergeGuestCartToDatabase($conn, (int) $row['id']);
            }

            header('Location: ' . roleHome());
            exit();
        }

        $invalidCreds = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Kopipiro</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link href="css/font.css" rel="stylesheet">
    </head>
    <body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
        <main class="flex flex-col lg:flex-row container items-center justify-center lg:justify-between gap-12 px-6 lg:px-24 xl:px-40 min-h-screen mx-auto">
            <div class="flex">
                <img src="images/logo-text.png" class="w-64 lg:w-80">
            </div>
            <form method="POST" class="border-4 border-yellow-600 p-8 md:p-10 py-12 md:py-20 w-full max-w-xl">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <h1 class="text-4xl mb-8 font-bold tracking-wider">LOG IN</h1>
                <div class="flex flex-col gap-4 mb-12">

                    <div class="group w-full flex-shrink-0 ">
                        <label for="input-username" class="block mb-2 cursor-pointer">Username<sup class="">*</sup></label>
                        <input id="input-username" name="username" class="block w-full sm:w-80 py-[0.05rem] border-b-1 border-white focus:outline-none" type="text" placeholder="Momoi Saiba" value="<?= escapeHtml($username) ?>" autocomplete="username" required>

                        <?php if ($invalidCreds): ?>
                            <div class="mt-1 text-xs text-red-200">
                                Nama atau password salah
                            </div>
                        <?php elseif ($usernameEmpty): ?>
                            <div class="mt-1 text-xs text-red-200">
                                Nama tidak boleh kosong
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="group w-full flex-shrink-0">
                        <label for="input-password" class="block mb-2 cursor-pointer">Password<sup class="">*</sup></label>
                        <input id="input-password" name="password" class="block w-full sm:w-80 py-[0.05rem] border-b-1 border-white focus:outline-none" type="password" placeholder="momoi123" autocomplete="current-password" required>

                        <?php if ($invalidCreds): ?>
                            <div class="mt-1 text-xs text-red-200">
                                Nama atau password salah
                            </div>
                        <?php elseif ($passwordEmpty): ?>
                            <div class="mt-1 text-xs text-red-200">
                                Password tidak boleh kosong
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <button type="submit" class="w-fit flex mt-4 items-center gap-4 text-lg font-bold border-2 px-8 py-2  duration-300 hover:bg-orange-950 cursor-pointer focus:outline-none focus:bg-orange-950">
                    <svg class="inline h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"class="w-6 h-6 stroke-2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Masuk
                </button>

                <div class="mt-8 text-sm">
                    Belum memiliki akun? <a href="register.php" class="text-yellow-200">Buat akun</a>.
                </div>
            </form>
        </main>
    </body>
</html>
