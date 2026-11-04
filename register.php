<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'includes/store.php';
require_once 'components/banner.php';

if (isAuthenticated()) {
    header('Location: ' . roleHome());
    exit();
}

$username = '';
$usernameEmpty = false;
$passwordEmpty = false;
$registrationError = false;
$registrationMessage = 'Nama pengguna sudah digunakan atau akun tidak dapat dibuat.';
$passwordInvalid = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $registrationError = true;
        $registrationMessage = 'Sesi formulir kedaluwarsa. Silakan coba kembali.';
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $usernameEmpty = $username === '';
    if (strlen($username) > 100) {
        $registrationError = true;
        $registrationMessage = 'Username maksimal 100 karakter.';
    }
    $passwordEmpty = $password === '';
    $passwordInvalid = !$passwordEmpty && strlen($password) < 8;

    if (verifyCsrfToken() && !$usernameEmpty && !$passwordEmpty && !$passwordInvalid && !$registrationError) {
        $existingStmt = $conn->prepare('SELECT id FROM user WHERE name = ? LIMIT 1');
        $existingStmt->bind_param('s', $username);
        $existingStmt->execute();

        if ($existingStmt->get_result()->num_rows > 0) {
            $registrationError = true;
            $registrationMessage = 'Nama pengguna sudah digunakan.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO user (name, password, role) VALUES (?, ?, 'consumer')");
            $stmt->bind_param('ss', $username, $hashedPassword);

            if ($stmt->execute()) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $conn->insert_id;
                $_SESSION['user_name'] = $username;
                $_SESSION['user_role'] = 'consumer';
                mergeGuestCartToDatabase($conn, (int) $_SESSION['user_id']);

                header('Location: explore.php');
                exit();
            }

            $registrationError = true;
            $registrationMessage = 'Tidak dapat membuat akun. Periksa kembali data yang dimasukkan.';
        }
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
        <?php
        if ($registrationError) {
            echo renderBanner($registrationMessage, "ERROR");
        }
        ?>
        <main class="flex flex-col lg:flex-row container items-center justify-center lg:justify-between gap-12 px-6 lg:px-24 xl:px-40 min-h-screen mx-auto">
            <div class="flex">
                <img src="images/logo-text.png" class="w-64 lg:w-80">
            </div>
            <form method="POST" class="border-4 border-yellow-600 p-8 md:p-10 py-12 md:py-20 w-full max-w-xl">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <h1 class="text-4xl mb-8 font-bold tracking-wider">REGISTER</h1>
                <div class="flex flex-col gap-4 mb-12">

                    <div class="group w-full flex-shrink-0 ">
                        <label for="input-username" class="block mb-2 cursor-pointer">Username<sup class="">*</sup></label>
                        <input id="input-username" name="username" class="block w-full sm:w-80 py-[0.05rem] border-b-1 border-white focus:outline-none" type="text" placeholder="Momoi Saiba" value="<?= escapeHtml($username) ?>" autocomplete="username" required>

                        <?php if ($usernameEmpty): ?>
                            <div class="mt-1 text-xs text-red-200">
                                Nama tidak boleh kosong
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="group w-full flex-shrink-0">
                        <label for="input-password" class="block mb-2 cursor-pointer">Password<sup class="">*</sup></label>
                        <input id="input-password" name="password" class="block w-full sm:w-80 py-[0.05rem] border-b-1 border-white focus:outline-none" type="password" placeholder="Minimal 8 karakter" autocomplete="new-password" minlength="8" required>

                        <?php if ($passwordEmpty): ?>
                            <div class="mt-1 text-xs text-red-200">Password tidak boleh kosong</div>
                        <?php elseif ($passwordInvalid): ?>
                            <div class="mt-1 text-xs text-red-200">Password minimal 8 karakter</div>
                        <?php endif; ?>
                    </div>

                </div>

                <span class="text-xs text-neutral-400">
                    Dengan membuat akun, Anda menyatakan menyetujui ketentuan layanan dan kebijakan privasi Kopipiro.
                </span>

                <button type="submit" class="w-fit flex mt-4 items-center gap-4 text-lg font-bold border-2 px-8 py-2  duration-300 hover:bg-orange-950 cursor-pointer focus:outline-none focus:bg-orange-950">
                    <svg class="inline h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"class="w-6 h-6 stroke-2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Daftar
                </button>

                <div class="mt-8 text-sm">
                    Sudah punya akun? <a href="login.php" class="text-yellow-200">Log in</a>.
                </div>
            </form>
        </main>
    </body>
</html>
