# CüzdanAsistanı - Finansal Bütçe Takip Sistemi

Bu proje, kullanıcıların gelir ve giderlerini dinamik olarak takip edebilecekleri, birden fazla banka kartı/hesap entegre edebilecekleri ve harcama alışkanlıklarını grafiklerle görebilecekleri modern bir FinTech web uygulamasıdır.

## Özellikler
- **Çoklu Kart Desteği:** İstenilen sayıda sanal kart/hesap eklenebilir.
- **Dinamik Pasta Grafiği:** Harcamalar kategorilerine göre otomatik olarak Chart.js ile çizilir.
- **Akıllı Limit Uyarısı:** Aylık bütçe limitinin %80'ine ulaşıldığında kullanıcıyı uyarır.
- **Güvenli Giriş Sistemi:** Şifreler `password_hash` ile şifrelenir ve `session` kontrolü yapılır.

## Teknolojiler
- PHP (PDO - İlişkisel Veritabanı Mimarisi)
- MySQL
- HTML5 & Modern CSS (Karanlık Tema)
- Chart.js (Veri Görselleştirme)