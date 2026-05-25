<?php
// Session (oturum) sistemini tüm sayfalarımızda kullanabilmek için burada başlatıyoruz.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "harcama_takip";

try {
    // PDO kullanarak veritabanına güvenli bir bağlantı açıyoruz
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Bağlantıda bir hata oluşursa uygulamayı durdur ve hatayı ekrana bas
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>