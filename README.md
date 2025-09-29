# WooCommerce SMM Provider API

Bu depo, WooCommerce mağazanızdaki servisleri Perfect Panel uyumlu SMM panellerine sağlayabilmeniz için hazırlanmış bir WordPress eklentisi içerir. Eklenti sayesinde mağazanızdan API URL ve anahtarları üretip bayilerinizin kendi panellerine ekleyebileceği tam fonksiyonel bir sağlayıcı API sunabilirsiniz.

## Özellikler

- Bayi bazlı API anahtarı üretme ve tek tıkla iptal edebilme
- WooCommerce müşteri hesabında otomatik oluşan **API Anahtarı** menüsü ile kullanıcıların kendi anahtarlarını yönetebilmesi
- API uç noktası adresini otomatik oluşturma ve yönetim panelinden paylaşma
- WooCommerce ürünleri için minimum / maksimum adet ve 1000 başına fiyat tanımlama
- Perfect Panel uyumlu `services`, `add`, `status` ve `balance` aksiyonlarını destekleme
- API üzerinden gelen siparişleri otomatik olarak WooCommerce siparişlerine dönüştürme
- API siparişleri için varsayılan sipariş durumu ve müşteri bilgileri belirleme
- WooCommerce ürün listesinde hangi ürünlerin API üzerinden yayınlandığını gösterme

## Kurulum

1. Depoyu bilgisayarınıza klonlayın veya zip olarak indirin.
2. `wp-content/plugins/smm-panel-connector` klasörünü WordPress kurulumunuzdaki `wp-content/plugins` dizinine kopyalayın.
3. WordPress yönetim panelinden **Eklentiler > Yüklü Eklentiler** sayfasına gidin ve **WooCommerce SMM Provider API** eklentisini etkinleştirin.
4. WooCommerce menüsü altında yer alan **SMM Provider API** sayfasına girerek genel ayarlarınızı yapın ve bayi anahtarları oluşturun.
5. API üzerinden paylaşmak istediğiniz ürünlerde ürün düzenleme ekranındaki **Expose via SMM API** alanını aktif edin ve gerekli servis değerlerini doldurun.

## API Kullanımı

Reseller’larınız, ayarlar sayfasında gösterilen uç noktaya `key` ve `action` parametreleri ile istekte bulunabilir. Desteklenen aksiyonlar:

- `services`: Aktif servisleri döndürür.
- `add`: Yeni sipariş oluşturur. `service`, `quantity` ve `link` parametreleri zorunludur.
- `status`: Sipariş durumunu sorgular. `order` parametresi zorunludur.
- `balance`: Bakiye bilgisi döndürür (pay-as-you-go mantığında `0`).

Tüm cevaplar JSON formatındadır ve Perfect Panel ile uyumlu alan isimleri içerir.

## Geliştirme Notları

- Eklenti ayarları `smmpw_provider_settings` opsiyonunda, API anahtarları `smmpw_api_keys` opsiyonunda saklanır.
- Müşteri API anahtarları kullanıcı meta alanı `_smmpw_api_key` içerisinde tutulur ve My Account sayfasından yenilenebilir.
- API üzerinden oluşturulan siparişler `_smmpw_api_order` meta değeriyle işaretlenir ve istenirse ek aksiyonlara bağlanabilir.
- Kod standartları WordPress PHP kod standartlarını takip eder.

Katkıda bulunurken kod stilini korumaya ve gereksiz değişikliklerden kaçınmaya özen gösterin.
