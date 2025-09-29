# WooCommerce SMM Panel Connector

WordPress eklentisi olarak WooCommerce mağazanızı Perfect Panel uyumlu SMM panellerine bağlamak için tasarlandı. Bu depo içinde yer alan `wp-content/plugins/smm-panel-connector` klasörünü WordPress kurulumunuzdaki `wp-content/plugins` dizinine kopyalayarak kullanabilirsiniz.

## Özellikler

- API URL ve API anahtarını yönetim panelinden tanımlayabilme
- Perfect Panel ve yaygın SMM panel API v2 uç noktalarıyla uyumluluk
- Hizmetleri WooCommerce basit ürünleri olarak otomatik senkronizasyon
- Fiyat çarpanı veya sabit tutar ile otomatik fiyatlandırma
- Varsayılan kategori ataması ve stok davranışı ayarları
- Manuel senkronizasyon ve AJAX üzerinden bağlantı testi
- WP-Cron ile planlanmış otomatik senkronizasyon

## Kurulum

1. Depoyu bilgisayarınıza klonlayın veya zip olarak indirin.
2. `wp-content/plugins/smm-panel-connector` klasörünü WordPress kurulumunuzdaki aynı dizine kopyalayın.
3. WordPress yönetim panelinden **Eklentiler > Yüklü Eklentiler** sayfasına gidin ve **WooCommerce SMM Panel Connector** eklentisini etkinleştirin.
4. WooCommerce menüsü altında yer alan **SMM Panel** sayfasından API URL ve API anahtarınızı girin.
5. Senkronizasyon ayarlarını yapıp kaydedin, ardından isteğe bağlı olarak manuel senkronizasyon başlatın.

## API Gereksinimleri

Eklenti Perfect Panel ve yaygın SMM panel servislerinin sunduğu API v2 ile çalışır. Çoğu panel aşağıdaki uç noktaları destekler:

- `balance` – Bağlantı testi ve bakiye sorgusu.
- `services` – Servis listesini döndürür.

Eklenti `services` uç noktasından dönen `id`, `name`, `rate`, `min`, `max`, `type`, `status`, `description` alanlarını kullanır. Bu alanlar paneller arasında farklılık gösterebilir, eksik alanlar otomatik olarak atlanır.

## Geliştirme

- Kod standartları WordPress PHP kod standartlarını takip eder.
- Ayarlar `smmpw_settings` opsiyonunda saklanır.
- Cron zamanlamaları için ek olarak “Her 15 Dakika” ve “Günde İki Kez” seçenekleri tanımlanır.

Pull request gönderirken kod stilini korumaya ve gereksiz değişiklik yapmamaya özen gösterin.
