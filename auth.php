<?php
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function currentUserId(): ?int {
    return isAuthenticated() ? (int) $_SESSION['user_id'] : null;
}

function currentUserRole(): string {
    $role = strtolower((string) ($_SESSION['user_role'] ?? ''));

    if (in_array($role, ['customer', 'pelanggan'], true)) {
        return 'consumer';
    }

    if (in_array($role, ['kasir'], true)) {
        return 'cashier';
    }

    if (in_array($role, ['admin', 'administrator'], true)) {
        return 'owner';
    }

    return $role;
}

function databaseRoleValue(mysqli $conn, string $role): string {
    $candidates = [
        'owner' => ['owner', 'admin', 'administrator'],
        'consumer' => ['consumer', 'customer', 'pelanggan'],
        'cashier' => ['cashier', 'kasir']
    ];

    if (!isset($candidates[$role])) {
        throw new InvalidArgumentException('Role tidak valid.');
    }

    $result = $conn->query("SHOW COLUMNS FROM `user` LIKE 'role'");
    $column = $result->fetch_assoc();
    $type = (string) ($column['Type'] ?? '');

    if (stripos($type, 'enum(') === 0 && preg_match_all("/'((?:\\'|[^'])*)'/", $type, $matches)) {
        $available = array_map(static function ($value) {
            return str_replace("\\'", "'", $value);
        }, $matches[1]);

        foreach ($candidates[$role] as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Role ' . $role . ' tidak tersedia pada ENUM kolom user.role.');
    }

    return $candidates[$role][0];
}

function isCustomer(): bool {
    return isAuthenticated() && currentUserRole() === 'consumer';
}

function isOwner(): bool {
    return isAuthenticated() && currentUserRole() === 'owner';
}

function isCashier(): bool {
    return isAuthenticated() && currentUserRole() === 'cashier';
}

function appPath(string $path = ''): string {
    $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $lastDirectory = basename(rtrim($scriptDirectory, '/'));
    $prefix = in_array($lastDirectory, ['admin', 'cashier'], true) ? '../' : '';

    return $prefix . ltrim($path, '/');
}

function roleHome(): string {
    if (isOwner()) {
        return appPath('admin/dashboard.php');
    }

    if (isCashier()) {
        return appPath('cashier/dashboard.php');
    }

    return appPath('explore.php');
}

function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: ' . appPath('login.php'));
        exit();
    }
}

function requireRole(array $roles): void {
    requireAuth();

    if (!in_array(currentUserRole(), $roles, true)) {
        header('Location: ' . roleHome());
        exit();
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function redirectWithMessage(string $url, string $message, string $type = 'success'): void {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit();
}

function getFlashMessage(): ?array {
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $flash = [
        'message' => (string) $_SESSION['flash_message'],
        'type' => (string) ($_SESSION['flash_type'] ?? 'success')
    ];

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return $flash;
}
?>
