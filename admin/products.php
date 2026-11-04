<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/store.php';
requireRole(['owner']);

function storeProductImage(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Gambar gagal diunggah atau ukurannya melebihi 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }

    $directory = __DIR__ . '/../uploads/products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Gambar tidak dapat disimpan.');
    }

    return 'uploads/products/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        redirectWithMessage('products.php', 'Sesi formulir kedaluwarsa.', 'error');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $productId = (int) ($_POST['product_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $type = (string) ($_POST['type'] ?? 'coffee');
            $priceInput = (string) ($_POST['base_price'] ?? '');
            if ($name === '' || !in_array($type, ['coffee', 'other'], true) || !is_numeric($priceInput) || (float) $priceInput < 0) {
                throw new RuntimeException('Nama, kategori, atau harga produk tidak valid.');
            }

            $uploadedImage = storeProductImage($_FILES['image'] ?? []);
            $price = round((float) $priceInput, 2);
            if ($productId > 0) {
                if ($uploadedImage !== null) {
                    $stmt = $conn->prepare('UPDATE product SET name = ?, description = ?, type = ?, base_price = ?, image_url = ? WHERE id = ? AND deleted_at IS NULL');
                    $stmt->bind_param('sssdsi', $name, $description, $type, $price, $uploadedImage, $productId);
                } else {
                    $stmt = $conn->prepare('UPDATE product SET name = ?, description = ?, type = ?, base_price = ? WHERE id = ? AND deleted_at IS NULL');
                    $stmt->bind_param('sssdi', $name, $description, $type, $price, $productId);
                }
                $stmt->execute();
                redirectWithMessage('products.php', 'Produk berhasil diperbarui.');
            }

            $stmt = $conn->prepare('INSERT INTO product (name, image_url, description, type, base_price) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssd', $name, $uploadedImage, $description, $type, $price);
            $stmt->execute();
            redirectWithMessage('products.php', 'Produk berhasil ditambahkan.');
        }

        if ($action === 'delete') {
            $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            if (!$productId) {
                throw new RuntimeException('Produk tidak valid.');
            }
            $stmt = $conn->prepare('UPDATE product SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL');
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            redirectWithMessage('products.php', 'Produk diarsipkan dan tidak lagi tampil di katalog.');
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        redirectWithMessage('products.php', $exception instanceof RuntimeException ? $exception->getMessage() : 'Produk tidak dapat disimpan.', 'error');
    }
}

function adminProductImageUrl(?string $imageUrl): string {
    if (!$imageUrl) {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $imageUrl) || str_starts_with($imageUrl, '/')) {
        return $imageUrl;
    }
    return '../' . ltrim($imageUrl, '/');
}

$editProduct = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $stmt = $conn->prepare('SELECT id, name, image_url, description, type, base_price FROM product WHERE id = ? AND deleted_at IS NULL');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editProduct = $stmt->get_result()->fetch_assoc();
}
$products = $conn->query('SELECT id, name, image_url, description, type, base_price FROM product WHERE deleted_at IS NULL ORDER BY created_at DESC');
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk | Kopipiro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="../css/font.css" rel="stylesheet">
</head>
<body class="bg-[#1b140e] text-gray-200 inter min-h-screen">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
    <main class="max-w-7xl mx-auto px-6 md:px-10 py-10">
        <a href="dashboard.php" class="text-sm text-yellow-200 underline">← Dashboard</a>
        <h1 class="text-3xl font-bold mt-3 mb-8">Kelola produk</h1>
        <?php if ($flash): ?><div class="mb-6 border px-4 py-3 <?= $flash['type'] === 'error' ? 'border-red-500 text-red-200' : 'border-yellow-600 text-yellow-100' ?>"><?= escapeHtml($flash['message']) ?></div><?php endif; ?>

        <section class="border border-yellow-700/70 bg-[#21180f] p-6 mb-10 max-w-4xl">
            <h2 class="text-xl font-bold mb-5"><?= $editProduct ? 'Edit produk' : 'Tambah produk' ?></h2>
            <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="product_id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
                <label class="text-sm">Nama produk
                    <input name="name" required maxlength="255" value="<?= escapeHtml($editProduct['name'] ?? '') ?>" class="block w-full mt-2 bg-[#2a2017] border border-white/30 px-3 py-2">
                </label>
                <label class="text-sm">Harga (Rp)
                    <input name="base_price" type="number" min="0" step="0.01" required value="<?= escapeHtml($editProduct['base_price'] ?? '') ?>" class="block w-full mt-2 bg-[#2a2017] border border-white/30 px-3 py-2">
                </label>
                <label class="text-sm">Kategori
                    <select name="type" class="block w-full mt-2 bg-[#2a2017] border border-white/30 px-3 py-2">
                        <option value="coffee" <?= ($editProduct['type'] ?? 'coffee') === 'coffee' ? 'selected' : '' ?>>Kopi</option>
                        <option value="other" <?= ($editProduct['type'] ?? '') === 'other' ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                </label>
                <label class="text-sm">Gambar produk (JPG, PNG, WEBP; maks. 5 MB)
                    <input name="image" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full mt-2 text-sm">
                </label>
                <label class="text-sm md:col-span-2">Deskripsi
                    <textarea name="description" rows="3" class="block w-full mt-2 bg-[#2a2017] border border-white/30 px-3 py-2"><?= escapeHtml($editProduct['description'] ?? '') ?></textarea>
                </label>
                <div class="md:col-span-2 flex gap-3">
                    <button type="submit" class="border-2 border-yellow-600 px-6 py-2 font-bold hover:bg-orange-950"><?= $editProduct ? 'Simpan perubahan' : 'Tambah produk' ?></button>
                    <?php if ($editProduct): ?><a href="products.php" class="border border-white/40 px-6 py-2">Batal</a><?php endif; ?>
                </div>
            </form>
            <?php if (!empty($editProduct['image_url'])): ?><img src="<?= escapeHtml(adminProductImageUrl($editProduct['image_url'])) ?>" alt="Gambar saat ini" class="mt-4 w-24 h-24 object-cover"><?php endif; ?>
        </section>

        <section class="border border-white/20 bg-[#21180f]">
            <div class="p-5 border-b border-white/20"><h2 class="text-xl font-bold">Produk aktif</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[750px] text-sm">
                    <thead class="text-left text-neutral-400 bg-[#2a2017]"><tr><th class="p-4">Produk</th><th class="p-4">Kategori</th><th class="p-4">Harga</th><th class="p-4">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <tr>
                            <td class="p-4"><div class="flex items-center gap-3"><?php if ($product['image_url']): ?><img src="<?= escapeHtml(adminProductImageUrl($product['image_url'])) ?>" class="w-12 h-12 object-cover" alt=""><?php endif; ?><div><b><?= escapeHtml($product['name']) ?></b><p class="text-xs text-neutral-400"><?= escapeHtml($product['description']) ?></p></div></div></td>
                            <td class="p-4"><?= $product['type'] === 'coffee' ? 'Kopi' : 'Lainnya' ?></td>
                            <td class="p-4 text-yellow-300"><?= formatRupiah($product['base_price']) ?></td>
                            <td class="p-4"><div class="flex items-center gap-4"><a href="products.php?edit=<?= (int) $product['id'] ?>" class="text-yellow-200 underline">Edit</a><form method="POST" onsubmit="return confirm('Arsipkan produk ini?')"><input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button class="text-red-300 underline">Arsipkan</button></form></div></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($products->num_rows === 0): ?><tr><td colspan="4" class="p-6 text-center text-neutral-400">Belum ada produk aktif.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
