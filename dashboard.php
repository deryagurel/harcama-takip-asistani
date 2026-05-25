<?php
include 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ==========================================
// FORM İŞLEMLERİ (CRUD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. YENİ KART/HESAP EKLEME TETİKLENDİYSE
    if (isset($_POST['add_card'])) {
        $bank_name = trim($_POST['bank_name']);
        $card_type = $_POST['card_type'];
        $balance = floatval($_POST['balance']);

        if (!empty($bank_name) && $balance >= 0) {
            $stmt = $db->prepare("INSERT INTO wallets (user_id, bank_name, card_type, balance) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $bank_name, $card_type, $balance]);
        }
    }

    // 2. HARCAMA EKLEME TETİKLENDİYSE
    if (isset($_POST['add_expense'])) {
        $title = trim($_POST['title']);
        $amount = floatval($_POST['amount']);
        $category = $_POST['category'];
        $date = $_POST['date'];
        $wallet_id = intval($_POST['wallet_id']); // Seçilen kartın ID'si

        if (!empty($title) && $amount > 0 && $wallet_id > 0) {
            // Önce kartın adını çekelim (expenses tablosuna yazmak için)
            $wStmt = $db->prepare("SELECT bank_name FROM wallets WHERE id = ? AND user_id = ?");
            $wStmt->execute([$wallet_id, $user_id]);
            $wallet_info = $wStmt->fetch(PDO::FETCH_ASSOC);
            $bank_name = $wallet_info['bank_name'] ?? 'Bilinmeyen Kart';

            $stmt = $db->prepare("INSERT INTO expenses (user_id, title, amount, category, date, bank_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $amount, $category, $date, $bank_name]);
            
            // Seçilen kartın bakiyesinden düş
            $updateWallet = $db->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ? AND user_id = ?");
            $updateWallet->execute([$amount, $wallet_id, $user_id]);
        }
    }
    
    // 3. GELİR EKLEME TETİKLENDİYSE
    if (isset($_POST['add_income'])) {
        $title = trim($_POST['title']);
        $amount = floatval($_POST['amount']);
        $date = $_POST['date'];
        $wallet_id = intval($_POST['wallet_id']); // Paranın geleceği kartın ID'si

        if (!empty($title) && $amount > 0 && $wallet_id > 0) {
            $stmt = $db->prepare("INSERT INTO incomes (user_id, title, amount, date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $amount, $date]);
            
            // Seçilen kartın bakiyesine ekle
            $updateWallet = $db->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ? AND user_id = ?");
            $updateWallet->execute([$amount, $wallet_id, $user_id]);
        }
    }
    header("Location: dashboard.php");
    exit;
}

// ==========================================
// VERİLERİ ÇEKME
// ==========================================

// MULTI-CARD: Kullanıcının eklediği TÜM kartları çekiyoruz
$walletsQuery = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
$walletsQuery->execute([$user_id]);
$all_wallets = $walletsQuery->fetchAll(PDO::FETCH_ASSOC);

// Bu Ayki Toplam Gelir ve Gider
$incQuery = $db->prepare("SELECT SUM(amount) AS total FROM incomes WHERE user_id = ? AND MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$incQuery->execute([$user_id]);
$total_income = floatval($incQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$expQuery = $db->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ? AND MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$expQuery->execute([$user_id]);
$total_expense = floatval($expQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Limit Kontrolü
$setQuery = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$setQuery->execute([$user_id]);
$settings = $setQuery->fetch(PDO::FETCH_ASSOC);
$monthly_limit = floatval($settings['monthly_limit'] ?? 5000);
$expense_percentage = ($monthly_limit > 0) ? ($total_expense / $monthly_limit) * 100 : 0;
$show_alert = ($expense_percentage >= 80);

// Son 5 İşlem
$historyQuery = $db->prepare("
    (SELECT 'Gelir' as type, title, amount, 'Maaş/Burs' as category, date, 'Hesaba Giriş' as bank_name FROM incomes WHERE user_id = ?)
    UNION ALL
    (SELECT 'Gider' as type, title, amount, category, date, bank_name FROM expenses WHERE user_id = ?)
    ORDER BY date DESC LIMIT 5
");
$historyQuery->execute([$user_id, $user_id]);
$history = $historyQuery->fetchAll(PDO::FETCH_ASSOC);

// Grafik Verisi
$chartQuery = $db->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY category");
$chartQuery->execute([$user_id]);
$chartData = $chartQuery->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
$categoryTotals = [];
foreach ($chartData as $row) {
    $categories[] = $row['category'];
    $categoryTotals[] = floatval($row['total']);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - CüzdanAsistanı</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Kartların yan yana taşabilmesi için küçük esneklik ayarı */
        .cards-scroll-container {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .wallet-card {
            min-width: 250px;
            background: #202024;
            border: 1px solid var(--primary);
        }
    </style>
</head>
<body>
    <div class="main-layout">
        <div class="sidebar">
            <h2>CüzdanAsistanı</h2>
            <p style="text-align:center; margin-bottom:20px; color:#a8a8b3;">Hoş geldin, <br><strong style="color:white;"><?= htmlspecialchars($_SESSION['user_name']) ?></strong></p>
            <a href="dashboard.php" class="active">Ana Panel</a>
            <a href="logout.php" style="margin-top:auto; color:var(--danger); font-weight:bold;">Güvenli Çıkış</a>
        </div>

        <div class="content">
            <?php if ($show_alert): ?>
                <div class="alert-box">
                    ⚠️ <strong>Dikkat!</strong> Aylık bütçe limitinizin %<?= round($expense_percentage) ?>'ini harcadınız. Tasarrufa geçin!
                </div>
            <?php endif; ?>

            <h3 style="margin-bottom: 15px; font-size: 16px; color: #a8a8b3;">💳 Bağlı Kartlarınız & Hesaplarınız</h3>
            <div class="cards-scroll-container">
                <?php foreach($all_wallets as $w): ?>
                    <div class="card wallet-card">
                        <h3 style="color: var(--primary);"><?= htmlspecialchars($w['bank_name']) ?></h3>
                        <span style="font-size:12px; color:#a8a8b3;"><?= htmlspecialchars($w['card_type']) ?></span>
                        <div class="amount" style="margin-top:10px; font-size:20px;"><?= number_format($w['balance'], 2, ',', '.') ?> TL</div>
                    </div>
                <?php endforeach; ?>
                
                <div class="card" style="min-width: 220px; border-color: var(--success);">
                    <h3>Bu Ay Toplam Gelir</h3>
                    <div class="amount" style="color: var(--success); font-size:20px;">+<?= number_format($total_income, 2, ',', '.') ?> TL</div>
                </div>
                <div class="card" style="min-width: 220px; border-color: var(--danger);">
                    <h3>Bu Ay Toplam Gider</h3>
                    <div class="amount" style="color: var(--danger); font-size:20px;">-<?= number_format($total_expense, 2, ',', '.') ?> TL</div>
                </div>
            </div>

            <div class="dashboard-grid" style="margin-bottom:30px;">
                <div class="section-box">
                    <h3>Harcama (Gider) Ekle</h3>
                    <form action="" method="POST">
                        <input type="hidden" name="add_expense" value="1">
                        <div class="form-group">
                            <label>Hangi Kart/Hesap Kullanıldı?</label>
                            <select name="wallet_id" required>
                                <?php foreach($all_wallets as $w): ?>
                                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['bank_name']) ?> (Bakiye: <?= $w['balance'] ?> TL)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Harcama Açıklaması</label>
                            <input type="text" name="title" placeholder="Örn: Market Alışverişi" required>
                        </div>
                        <div class="form-group">
                            <label>Miktar (TL)</label>
                            <input type="number" step="0.01" name="amount" required>
                        </div>
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="category">
                                <option value="Market">Market</option>
                                <option value="Yemek">Yemek</option>
                                <option value="Ulaşım">Ulaşım</option>
                                <option value="Eğlence">Eğlence</option>
                                <option value="Fatura">Fatura</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tarih</label>
                            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit">Harcamayı Seçili Karttan Düş</button>
                    </form>
                </div>

                <div>
                    <div class="section-box" style="margin-bottom: 20px;">
                        <h3>Hesaba Gelir Ekle</h3>
                        <form action="" method="POST">
                            <input type="hidden" name="add_income" value="1">
                            <div class="form-group">
                                <label>Hangi Karta/Hesaba Gelecek?</label>
                                <select name="wallet_id" required>
                                    <?php foreach($all_wallets as $w): ?>
                                        <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['bank_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Gelir Kaynağı</label>
                                <input type="text" name="title" placeholder="Örn: Maaş" required>
                            </div>
                            <div class="form-group">
                                <label>Miktar (TL)</label>
                                <input type="number" step="0.01" name="amount" required>
                            </div>
                            <div class="form-group">
                                <label>Tarih</label>
                                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <button type="submit" style="background:var(--success);">Geliri Karta İşle</button>
                        </form>
                    </div>

                    <div class="section-box">
                        <h3>➕ Yeni Kart / Hesap Entegre Et</h3>
                        <form action="" method="POST">
                            <input type="hidden" name="add_card" value="1">
                            <div class="form-group">
                                <label>Banka / Hesap Adı</label>
                                <input type="text" name="bank_name" placeholder="Örn: Garanti BBVA, Akbank" required>
                            </div>
                            <div class="form-group">
                                <label>Kart Türü</label>
                                <select name="card_type">
                                    <option value="Kredi Kartı">Kredi Kartı</option>
                                    <option value="Banka Kartı">Banka Kartı</option>
                                    <option value="Vadeli Hesap">Vadeli Hesap</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Başlangıç Bakiyesi (TL)</label>
                                <input type="number" step="0.01" name="balance" value="0.00" required>
                            </div>
                            <button type="submit" style="background: #3498db;">Sanal Kartı Sisteme Bağla</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="section-box">
                    <h3>Harcama Dağılımı</h3>
                    <?php if(empty($categories)): ?>
                        <p style="color:#a8a8b3; text-align:center; padding-top:60px;">Henüz grafik verisi yok.</p>
                    <?php else: ?>
                        <div style="max-width: 250px; margin: auto;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section-box">
                    <h3>Son İşlemler</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Tür</th>
                                <th>Açıklama</th>
                                <th>Miktar</th>
                                <th>Detay / Kart</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $item): ?>
                            <tr>
                                <td><span class="badge <?= $item['type'] == 'Gelir' ? 'income' : 'expense' ?>"><?= $item['type'] ?></span></td>
                                <td><?= htmlspecialchars($item['title']) ?></td>
                                <td style="font-weight:bold;"><?= number_format($item['amount'], 2, ',', '.') ?> TL</td>
                                <td><span style="font-size: 13px; color:#a8a8b3;"><?= $item['type'] == 'Gelir' ? 'Hesaba Giriş' : htmlspecialchars($item['category'])." (".$item['bank_name'].")" ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('categoryChart');
        if(ctx) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($categories) ?>,
                    datasets: [{
                        data: <?= json_encode($categoryTotals) ?>,
                        backgroundColor: ['#8257e5', '#04d361', '#ff3333', '#f1c40f', '#3498db'],
                        borderWidth: 1,
                        borderColor: '#1a1a1e'
                    }]
                },
                options: { plugins: { legend: { position: 'bottom', labels: { color: '#e1e1e6' } } } }
            });
        }
    </script>
</body>
</html>