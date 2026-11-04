<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireRole(['owner']);

$result = $conn->query("
    SELECT o.id, o.created_at, o.status, o.amount,
           COALESCE(customer.name, 'Guest') AS customer_name,
           COALESCE(cashier.name, '-') AS cashier_name,
           GROUP_CONCAT(CONCAT(p.name, ' × ', op.quantity, ' @ ', op.unit_price) ORDER BY p.name SEPARATOR '; ') AS order_items
    FROM `order` o
    LEFT JOIN user customer ON customer.id = o.id_user
    LEFT JOIN user cashier ON cashier.id = o.id_cashier
    LEFT JOIN orderproduct op ON op.id_order = o.id
    LEFT JOIN product p ON p.id = op.id_product
    GROUP BY o.id, o.created_at, o.status, o.amount, customer.name, cashier.name
    ORDER BY o.created_at DESC, o.id DESC
");

$filename = 'kopipiro-penjualan-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

function spreadsheetText($value): string {
    $value = (string) $value;
    if (preg_match('/^[\s\x00-\x20]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }
    return $value;
}

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Order ID', 'Tanggal', 'Status', 'Customer', 'Kasir', 'Item (nama × qty @ harga)', 'Total Order']);
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'], $row['created_at'], spreadsheetText($row['status']), spreadsheetText($row['customer_name']),
        spreadsheetText($row['cashier_name']), spreadsheetText($row['order_items'] ?? '-'), $row['amount']
    ]);
}
fclose($output);
exit();
?>
