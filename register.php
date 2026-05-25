<?php
include 'config/db.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($name) && !empty($email) && !empty($password)) {
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->rowCount() > 0) {
            $error = "Bu e-posta adresi zaten kayıtlı!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password])) {
                $user_id = $db->lastInsertId();
                
                // MULTI-CARD: İlk kayıt anında kullanıcıya 2 farklı hesap türü tanımlıyoruz
                $stmt_wallet1 = $db->prepare("INSERT INTO wallets (user_id, bank_name, card_type, balance) VALUES (?, 'Nakit Cüzdan', 'Nakit', 2000.00)");
                $stmt_wallet1->execute([$user_id]);

                $stmt_wallet2 = $db->prepare("INSERT INTO wallets (user_id, bank_name, card_type, balance) VALUES (?, 'Ziraat Bankası', 'Banka Kartı', 15000.00)");
                $stmt_wallet2->execute([$user_id]);

                $stmt_settings = $db->prepare("INSERT INTO user_settings (user_id, monthly_limit, alert_threshold) VALUES (?, 5000.00, 80)");
                $stmt_settings->execute([$user_id]);

                $message = "Kayıt başarılı! Giriş sayfasına dönüp giriş yapabilirsiniz.";
            }
        }
    } else {
        $error = "Lütfen tüm alanları doldurun.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kayıt Ol - CüzdanAsistanı</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Hesap Oluştur</h2>
        
        <?php if(!empty($error)): ?>
            <div style="color: var(--danger); text-align:center; margin-bottom:15px; font-weight:bold;"><?= $error ?></div>
        <?php endif; ?>
        <?php if(!empty($message)): ?>
            <div style="color: var(--success); text-align:center; margin-bottom:15px; font-weight:bold;"><?= $message ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <label>Ad Soyad</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>E-posta Adresi</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Hesap Oluştur ve Başlat</button>
        </form>
        <a href="index.php" class="auth-link">Zaten hesabınız var mı? Giriş Yapın</a>
    </div>
</body>
</html>ß