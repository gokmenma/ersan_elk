<?php 


namespace App\InterFaces;

interface LoggerInterface{
        /**
     * Normal, önemli olayları kaydeder. (Giriş, ekleme, silme vb.)
     * @param string $message  Log mesajı. Örn: "Kullanıcı {user_id} yeni bir ürün ekledi."
     * @param array  $context  Mesajdaki yer tutucuları dolduracak veri. Örn: ['user_id' => 123]
     */
    public function info(string $message, array $context = []): void;

    /**
     * Bir hata oluştuğunda kaydeder.
     * @param string $message  Hata mesajı.
     * @param array  $context  Hatanın oluştuğu yer, IP adresi gibi ek bilgiler.
     */
    public function error(string $message, array $context = []): void;

    /**
     * Uyarı seviyesinde log kaydı yapar.
     * @param string $message  Uyarı mesajı.
     * @param array  $context  Uyarının detayları, örneğin kullanıcı bilgileri.
     */
    public function warning(string $message, array $context = []): void;


    /**
     * Diğer tüm log seviyeleri için genel bir metot.
     * info() ve error() metotları arka planda bunu çağırabilir.
     * @param string $level    'INFO', 'ERROR', 'DEBUG' gibi log seviyesi.
     * @param string $message
     * @param array  $context
     */
    public function log(string $level, string $message, array $context = []): void;
}