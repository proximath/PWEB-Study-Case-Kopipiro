<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/store.php';
requireRole(['owner']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        redirectWithMessage('users.php', 'Sesi formulir kedaluwarsa.', 'error');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $roleInput = (string) ($_POST['role'] ?? 'cashier');

        if ($name === '' || strlen($name) > 100 || strlen($password) < 8 || !in_array($roleInput, ['consumer', 'cashier'], true)) {
            redirectWithMessage('users.php', 'Nama, role, atau password tidak valid. Password minimal 8 karakter.', 'error');
        }

        $check = $conn->prepare('SELECT id FROM user WHERE name = ? LIMIT 1');
        $check->bind_param('s', $name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            redirectWithMessage('users.php', 'Username tersebut sudah digunakan.', 'error');
        }

        try {
            $dbRole = databaseRoleValue($conn, $roleInput);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO user (name, password, role) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $name, $hash, $dbRole);
            $stmt->execute();
            redirectWithMessage('users.php', 'Pengguna berhasil dibuat.');
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            redirectWithMessage('users.php', 'Tidak dapat membuat pengguna. Periksa nilai ENUM role pada database.', 'error');
        }
    }

    if ($action === 'update_role') {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $roleInput = (string) ($_POST['role'] ?? '');
        if (!$userId || !in_array($roleInput, ['owner', 'consumer', 'cashier'], true)) {
            redirectWithMessage('users.php', 'Data pengguna tidak valid.', 'error');
        }
        if ($userId === currentUserId() && $roleInput !== 'owner') {
            redirectWithMessage('users.php', 'Kamu tidak dapat menurunkan role akun sendiri.', 'error');
        }

        try {
            $dbRole = databaseRoleValue($conn, $roleInput);
            $stmt = $conn->prepare('UPDATE user SET role = ? WHERE id = ?');
            $stmt->bind_param('si', $dbRole, $userId);
            $stmt->execute();
            redirectWithMessage('users.php', 'Role pengguna berhasil diperbarui.');
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            redirectWithMessage('users.php', 'Role tidak dapat diperbarui. Periksa nilai ENUM role pada database.', 'error');
        }
    }
}

$users = $conn->query('SELECT id, name, role FROM user ORDER BY id DESC');
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna | Kopipiro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/font.css" rel="stylesheet">
</head>
<body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="max-w-7xl mx-auto px-6 md:px-10 py-10">
        <a href="dashboard.php" class="text-sm text-yellow-200 underline">← Dashboard</a>
        <h1 class="text-3xl font-bold mt-3 mb-8">Manajemen pengguna</h1>
        <?php if ($flash): ?><div class="mb-6 border px-4 py-3 <?= $flash['type'] === 'error' ? 'border-red-500 text-red-200' : 'border-yellow-600 text-yellow-100' ?>"><?= escapeHtml($flash['message']) ?></div><?php endif; ?>

        <section class="border border-white/20 bg-[#21180f] p-6 mb-10 max-w-3xl">
            <h2 class="text-xl font-bold mb-5">Tambah pengguna</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <input type="hidden" name="action" value="create">
                <label class="text-sm">Username
                    <input name="name" maxlength="100" required class="block w-full mt-2 bg-[#2a2017] border border-white/30 px-3 py-2" placeholder="Nama pengguna">
                </label>
                <label class="text-sm">Password awal
                    <input name="password" type="password" minlength="8" required class="block w-full mt-2 bg-[#2a2017] border border-white/30 px-3 py-2" placeholder="Minimal 8 karakter">
                </label>
                <label class="text-sm">Role
                    <select name="role" class="block w-full mt-2 bg-[#2a2017] border border-white/30 px-3 py-2">
                        <option value="cashier">Kasir</option>
                        <option value="consumer">Customer</option>
                    </select>
                </label>
                <button type="submit" class="md:col-span-3 justify-self-start border border-yellow-600 px-5 py-2 font-bold hover:bg-orange-950">Buat pengguna</button>
            </form>
        </section>

        <section class="border border-white/20 bg-[#21180f]">
            <div class="p-5 border-b border-white/20"><h2 class="text-xl font-bold">Semua pengguna</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[650px] text-sm">
                    <thead class="text-left text-neutral-400 bg-[#2a2017]"><tr><th class="p-4">ID</th><th class="p-4">Username</th><th class="p-4">Role</th><th class="p-4">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                    <?php while ($user = $users->fetch_assoc()):
                        $rawRole = strtolower((string) $user['role']);
                        $uiRole = in_array($rawRole, ['owner', 'admin', 'administrator'], true) ? 'owner' : (in_array($rawRole, ['cashier', 'kasir'], true) ? 'cashier' : 'consumer');
                    ?>
                        <tr>
                            <td class="p-4">#<?= (int) $user['id'] ?></td>
                            <td class="p-4"><?= escapeHtml($user['name']) ?></td>
                            <td class="p-4 capitalize"><?= escapeHtml($uiRole === 'consumer' ? 'Customer' : ($uiRole === 'cashier' ? 'Kasir' : 'Owner')) ?></td>
                            <td class="p-4">
                                <?php if ((int) $user['id'] !== currentUserId()): ?>
                                    <form method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <select name="role" class="bg-[#2a2017] border border-white/30 px-2 py-1">
                                            <option value="consumer" <?= $uiRole === 'consumer' ? 'selected' : '' ?>>Customer</option>
                                            <option value="cashier" <?= $uiRole === 'cashier' ? 'selected' : '' ?>>Kasir</option>
                                            <option value="owner" <?= $uiRole === 'owner' ? 'selected' : '' ?>>Owner</option>
                                        </select>
                                        <button type="submit" class="text-yellow-200 underline">Simpan</button>
                                    </form>
                                <?php else: ?><span class="text-neutral-500">Akun aktif</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
