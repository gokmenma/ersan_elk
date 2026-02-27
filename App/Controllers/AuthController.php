<?php
namespace App\Controllers;

use App\Helper\Date;
use App\Model\UserModel;
use App\Model\SystemLogModel;
use App\Helper\Helper;
use App\Model\SettingsModel;
use App\InterFaces\LoggerInterface;
use App\Services\FlashMessageService;
use App\Services\MailGonderService;

/**
 * AuthController
 * 
 * Hem gelen istekleri işler (handleLoginRequest) hem de uygulama genelinde
 * statik metotlar aracılığıyla kimlik doğrulama hizmetleri sunar.
 */
class AuthController
{
    private $userModel;
    private $settingsModel;
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->settingsModel = new SettingsModel();
        $this->logger = \getLogger();
    }

    // //================================================================
    // // İSTEK İŞLEYEN METOTLAR (Request Handlers)
    // //================================================================





    /*Kullanıcı bilgilerini alır.
     * @return object|null Kullanıcı nesnesi veya null
     */
    public static function user(): ?object
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Eğer oturumda kullanıcı bilgisi varsa, onu döndür
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        // Oturumda kullanıcı bilgisi yoksa null döndür
        return null;
    }

    //================================================================
    // STATİK YARDIMCI METOTLAR (Uygulama Geneli Servisler)
    //================================================================

    /**
     * Kullanıcının oturum açıp açmadığını ve yetkili olup olmadığını kontrol eder.
     * Eğer bir sorun varsa (giriş yapılmamış, demo süresi dolmuş vb.),
     * kullanıcıyı uygun şekilde yönlendirir.
     * Bu metot, korumalı sayfaların en başında çağrılmalıdır.
     */
    public static function checkAuthentication(): void
    {

        //kayit-ol sayfafında oturum kontrolü yapma
        $currentUrl = $_SERVER['REQUEST_URI'];
        if (strpos($currentUrl, 'kayit-ol') !== false) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $logger = \getLogger();

        // 1. Adım: Kullanıcı giriş yapmış mı?
        if (!isset($_SESSION['user'])) {
            // Kullanıcı giriş yapmamışsa veya oturum süresi dolmuşsa, logla ve yönlendir.
            $logger->error("Kullanıcı oturum açmaya çalıştı, ancak oturum bilgisi bulunamadı.", [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'requested_url' => $_SERVER['REQUEST_URI']
            ]);

            session_destroy(); // Oturumu temizle
            session_unset(); // Tüm session değişkenlerini temizle

            FlashMessageService::add(
                'error',
                'Giriş Gerekli',
                'Bu sayfayı görüntülemek için lütfen giriş yapın.',
                'ikaz2.png'
            );


            $returnUrl = urlencode($_SERVER['REQUEST_URI']);
            header("Location: /sign-in.php?returnUrl={$returnUrl}");
            exit();
        }

        //Sesion süresi dolmuş mu?


        // 2. Adım: Kullanıcı verisini al
        $user = $_SESSION['user'];


        // 3. Adım: Demo süresi kontrolünü yap
        // Eğer demo süresi dolmuşsa, bu metot kullanıcıyı yönlendirip programı sonlandıracak.
        self::validateDemoPeriod($user);

        // 4. Adım: Uzun süreli oturumlarda periyodik loglama (Beni Hatırla desteği)
        // Kullanıcı uzun süre aktif kaldıysa, belirli aralıklarla giriş logu atarak takip edilebilirliğini sağla.
        if (isset($_SESSION['user'])) {
            $logInterval = 3600 * 4; // 4 saatte bir log at
            $lastLogTime = $_SESSION['last_login_log_time'] ?? 0;

            if (time() - $lastLogTime > $logInterval) {
                try {
                    $systemLog = new SystemLogModel();
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                    $userName = $user->adi_soyadi ?? $user->full_name ?? 'Bilinmiyor';

                    // Log mesajı, home.php'deki sorgunun yakalayabilmesi için 'Başarılı Giriş' tipinde olmalı
                    // Ancak açıklama kısmında otomatik yenileme olduğunu belirtiyoruz.
                    $systemLog->logAction(
                        $user->id,
                        'Başarılı Giriş',
                        "{$userName} oturumu devam ediyor (Otomatik Log). IP: {$ip}",
                        SystemLogModel::LEVEL_INFO
                    );

                    $_SESSION['last_login_log_time'] = time();
                } catch (\Exception $e) {
                    // Log hatası akışı bozmamalı
                }
            }
        }

        // 5. Adım (İsteğe bağlı - Geleceğe yönelik): Diğer kontroller
        // Örneğin, kullanıcının IP adresi değişmişse tekrar şifre sor, vb.
        // self::validateSessionIntegrity($user);
    }

    /**
     * Bir kullanıcının demo süresinin dolup dolmadığını kontrol eder.
     * Eğer süre dolmuşsa, kullanıcıyı çıkışa zorlar ve programı sonlandırır.
     *
     * @param object $user Kontrol edilecek kullanıcı nesnesi.
     */
    private static function validateDemoPeriod(object $user): void
    {


        // Sadece user_type'ı 1 (demo kullanıcısı) olanları kontrol et
        if (isset($user->user_type) && $user->user_type == 1) {

            // Kullanıcının kayıt tarihi verisinin olduğundan emin ol
            if (!isset($user->created_at)) {
                // Kayıt tarihi yoksa ne yapılacağına karar verin.
                // Belki de bir hata loglayıp devam etmesine izin verebilirsiniz.
                \getLogger()->error("Demo kullanıcısının kayıt tarihi (created_at) bulunamadı.", ['user_id' => $user->id]);
                return;
            }

            try {
                // Date helper'ını kullanarak iki tarih arasındaki farkı gün olarak al
                $daysSinceRegistration = Date::getDateDiff($user->created_at);

                // Tanımlanan demo süresi (örneğin 15 gün)
                $demoLimitInDays = 15;

                if ($daysSinceRegistration >= $demoLimitInDays) {
                    // Demo süresi dolmuş!

                    // Önce logla, sonra çıkış yaptır.
                    \getLogger()->info("Demo süresi dolan kullanıcı sisteme erişmeye çalıştı ve çıkışa yönlendirildi.", [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);

                    FlashMessageService::add(
                        'warning',
                        'Demo Süreniz Doldu!',
                        'Sistemi kullanmaya devam etmek için lütfen bizimle iletişime geçin.',
                        'ikaz2.png'
                    );

                    self::logout(false); // false -> tekrar loglama yapma demek


                }
            } catch (\Exception $e) {
                // Eğer tarih formatı bozuksa veya Date::getDateDiff hata verirse,
                // bu hatayı logla ve sistemin çökmesini engelle.
                \getLogger()->error("Demo süresi kontrolü sırasında tarih hatası.", [
                    'user_id' => $user->id,
                    'created_at_value' => $user->created_at,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }





    /**
     * Gerekli session'ları ayarlar, loglama yapar.
     * Bu metot artık private değil, public static.
     * @param object $user
     */
    public static function performLogin(object $user): void
    {
        self::validateDemoPeriod($user);
        session_regenerate_id(true);

        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = $user->id;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['full_name'] = $user->full_name;
        $_SESSION['user_role'] = $user->roles;
        $_SESSION["owner_id"] = $user->owner_id == 0 ? $user->id : $user->owner_id;

        // Model ve Servisler
        $userModel = new UserModel();
        $logger = \getLogger();




        $logger->info("Kullanıcı başarıyla giriş yaptı.", [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);

        // SystemLogModel ile dashboard'da gösterilecek log
        try {
            $systemLog = new SystemLogModel();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
            $userName = $user->adi_soyadi ?? $user->full_name ?? 'Bilinmiyor';
            $systemLog->logAction(
                $user->id,
                'Kullanıcı Girişi',
                "{$userName} sisteme giriş yaptı. IP: {$ip}",
                SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (\Exception $e) { /* Sessiz geç */
        }


        // SÜPER ADMIN KONTROLÜ
        $rolesArray = explode(',', $user->roles ?? '');
        if (in_array('10', $rolesArray)) {
            header("Location: /superadmin");
            exit();
        }

        // TEMSİLCİ KONTROLÜ (Role ID 15 veya rol adında 'Temsilci' geçiyorsa)
        $roleName = $user->role_name ?? '';
        if (in_array('15', $rolesArray) || stripos($roleName, 'Temsilci') !== false) {
            header("Location: /temsilci-paneli");
            exit();
        }

        $returnUrl = !empty($_GET['returnUrl']) ? $_GET['returnUrl'] : 'ana-sayfa';

        //Helper::dd($returnUrl);
        //eğer site_id oturumda yoksa, siteyi seçmesi için company-list.php sayfasına yönlendir
        if (!isset($_SESSION['site_id'])) {
            // Site seçimi için company-list.php sayfasına yönlendir
            header("Location: company-list.php?returnUrl=" . urlencode($returnUrl));
            exit();

        }

        header("Location: " . $returnUrl);
        exit();
    }

    /**
     * Kullanıcı oturumunu sonlandırır.
     * @param bool $logAction Çıkış işleminin loglanıp loglanmayacağını belirtir.
     */
    public static function logout(bool $logAction = true): void
    {
        if ($logAction) {
            $logger = \getLogger();
            $userId = $_SESSION['user']->id ?? 'Bilinmiyor';

            $logger->info("Kullanıcı oturumu kapattı.", [
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);

            // SystemLogModel ile dashboard'da gösterilecek log
            try {
                $systemLog = new SystemLogModel();
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor';
                $userName = $_SESSION['user']->adi_soyadi ?? $_SESSION['full_name'] ?? 'Bilinmiyor';
                $systemLog->logAction(
                    is_numeric($userId) ? $userId : 0,
                    'Kullanıcı Çıkışı',
                    "{$userName} sistemden çıkış yaptı. IP: {$ip}",
                    SystemLogModel::LEVEL_IMPORTANT
                );
            } catch (\Exception $e) { /* Sessiz geç */
            }

            session_unset();
            session_destroy();
            // --- DEĞİŞİKLİK BURADA ---
            // Normal çıkış için başarı mesajı ekle
            FlashMessageService::add(
                'success',
                'Başarılı',
                'Oturumunuz güvenli bir şekilde kapatıldı.',
                'onay2.png'
            );
        }



        // --- DEĞİŞİKLİK BURADA ---
        // Yönlendirmede artık ?status=... parametresi yok.
        header("Location: sign-in.php");
        exit();
    }


}
