<?php
// Veritabanı bağlantımızı ve session başlangıcını buraya dahil ediyoruz
include 'config/db.php';

// Eğer kullanıcı zaten giriş yapmışsa, tekrar giriş formunu görmesin, direkt panele gitsin
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

// Form gönderildiğinde (Giriş Yap butonuna basıldığında) burası çalışır
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // Kullanıcıyı e-posta adresine göre veritabanında arıyoruz
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kullanıcı bulunduysa ve şifre doğrulaması başarılıysa
        if ($user && password_verify($password, $user['password'])) {
            // Oturum (Session) verilerini kaydediyoruz
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            
            // Ana panele yönlendiriyoruz
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "E-posta veya şifre hatalı!";
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
    <title>Giriş Yap - CüzdanAsistanı</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-container">
        <h2>CüzdanAsistanı</h2>
        
        <?php if(!empty($error)): ?>
            <div style="color: var(--danger); text-align:center; margin-bottom:15px; font-weight:bold;"><?= $error ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="form-group">
                <label>E-posta Adresi</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Giriş Yap</button>
        </form>
        
        <a href="register.php" class="auth-link">Hesabınız yok mu? Kayıt Olun</a>
    </div>
</body>
</html>