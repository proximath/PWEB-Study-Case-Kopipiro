<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "kopipiro";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    error_log($exception->getMessage());
    die("Tidak dapat terhubung ke database Kopipiro.");
}

unset($servername, $username, $password, $dbname);
?>
