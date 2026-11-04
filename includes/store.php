<?php
require_once __DIR__ . '/../auth.php';

function escapeHtml($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatRupiah($amount): string {
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function getCartMap(mysqli $conn): array {
    if (isCustomer()) {
        $stmt = $conn->prepare('SELECT id_product, quantity FROM cart WHERE id_user = ?');
        $userId = currentUserId();
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $cart = [];
        while ($row = $result->fetch_assoc()) {
            $cart[(int) $row['id_product']] = min(99, (int) $row['quantity']);
        }

        return $cart;
    }

    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        return [];
    }

    $cleanCart = [];
    foreach ($cart as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity = (int) $quantity;
        if ($productId > 0 && $quantity > 0) {
            $cleanCart[$productId] = min($quantity, 99);
        }
    }

    return $cleanCart;
}

function setCartQuantity(mysqli $conn, int $productId, int $quantity): void {
    if (isCustomer()) {
        $userId = currentUserId();

        if ($quantity <= 0) {
            $stmt = $conn->prepare('DELETE FROM cart WHERE id_user = ? AND id_product = ?');
            $stmt->bind_param('ii', $userId, $productId);
            $stmt->execute();
            return;
        }

        $quantity = min($quantity, 99);
        $stmt = $conn->prepare('
            INSERT INTO cart (id_user, id_product, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
        ');
        $stmt->bind_param('iii', $userId, $productId, $quantity);
        $stmt->execute();
        return;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$productId]);
        return;
    }

    $_SESSION['cart'][$productId] = min($quantity, 99);
}

function mergeGuestCartToDatabase(mysqli $conn, int $userId): void {
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return;
    }

    $stmt = $conn->prepare('
        INSERT INTO cart (id_user, id_product, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = LEAST(99, quantity + VALUES(quantity))
    ');

    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity = (int) $quantity;
        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }

        $quantity = min($quantity, 99);
        $stmt->bind_param('iii', $userId, $productId, $quantity);
        $stmt->execute();
    }

    unset($_SESSION['cart']);
}

function clearCart(mysqli $conn): void {
    if (isCustomer()) {
        $userId = currentUserId();
        $stmt = $conn->prepare('DELETE FROM cart WHERE id_user = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }

    unset($_SESSION['cart']);
}

function getCartItems(mysqli $conn): array {
    $cart = getCartMap($conn);
    if (!$cart) {
        return [];
    }

    $items = [];
    if (isCustomer()) {
        $userId = currentUserId();
        $stmt = $conn->prepare('
            SELECT p.id, p.name, p.image_url, p.description, p.type, p.base_price,
                   LEAST(99, c.quantity) AS quantity,
                   MAX(c.added_at) AS last_added,
                   COALESCE(AVG(r.star), 0) AS rating_average,
                   COUNT(r.id_user) AS rating_count
            FROM cart c
            INNER JOIN product p ON p.id = c.id_product
            LEFT JOIN rating r ON r.id_product = p.id AND r.removed_at IS NULL
            WHERE c.id_user = ? AND p.deleted_at IS NULL
            GROUP BY p.id, p.name, p.image_url, p.description, p.type,
                     p.base_price, c.quantity
            ORDER BY last_added DESC
        ');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row['discounted_price'] = round((float) $row['base_price'] * 0.9, 2);
            $row['unit_price'] = $row['discounted_price'];
            $items[] = $row;
        }

        return $items;
    }

    $stmt = $conn->prepare('
        SELECT p.id, p.name, p.image_url, p.description, p.type, p.base_price,
               (SELECT AVG(r.star) FROM rating r WHERE r.id_product = p.id AND r.removed_at IS NULL) AS rating_average,
               (SELECT COUNT(*) FROM rating r WHERE r.id_product = p.id AND r.removed_at IS NULL) AS rating_count
        FROM product p
        WHERE p.id = ? AND p.deleted_at IS NULL
    ');

    foreach ($cart as $productId => $quantity) {
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $row['quantity'] = $quantity;
            $row['discounted_price'] = null;
            $row['unit_price'] = (float) $row['base_price'];
            $items[] = $row;
        }
    }

    return $items;
}

function getCartCount(mysqli $conn): int {
    return array_sum(getCartMap($conn));
}

function getProductRating(mysqli $conn, int $productId, ?int $userId = null): array {
    $stmt = $conn->prepare('
        SELECT COALESCE(AVG(CASE WHEN removed_at IS NULL THEN star END), 0) AS average_rating,
               SUM(CASE WHEN removed_at IS NULL THEN 1 ELSE 0 END) AS rating_count,
               MAX(CASE WHEN id_user = ? AND removed_at IS NULL THEN star END) AS user_rating
        FROM rating
        WHERE id_product = ?
    ');
    $ratingUserId = $userId ?? 0;
    $stmt->bind_param('ii', $ratingUserId, $productId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: [
        'average_rating' => 0,
        'rating_count' => 0,
        'user_rating' => null
    ];
}
?>
