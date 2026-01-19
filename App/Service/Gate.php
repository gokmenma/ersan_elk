<?php

namespace App\Services;

use App\Controllers\AuthController;
use Model\PermissionsModel;

class Gate
{
    /**
     * Mevcut kullanıcının belirli bir role sahip olup olmadığını kontrol eder.
     * 
     * @param string|array $roles Kontrol edilecek rol(ler)in adı. 
     *                            Tek bir rol için string, birden fazla rol için dizi verilebilir.
     * @return bool Kullanıcı belirtilen rollerden birine sahipse true, değilse false döner.
     */
    public static function hasRole(string|array $roles): bool
    {
        $user = AuthController::user();
        if (!$user || !isset($user->role_name)) {
            return false;
        }

        $userRole = $user->role_name;

        if (is_array($roles)) {
            // Eğer bir rol dizisi verilmişse, kullanıcının rolü bu dizide var mı diye bak.
            return in_array($userRole, $roles);
        }

        // Eğer tek bir rol (string) verilmişse, doğrudan karşılaştır.
        return $userRole === $roles;
    }

    /**
     * Mevcut kullanıcının belirli bir izne (permission) sahip olup olmadığını kontrol eder.
     * Bu metot, rolün sahip olduğu izinleri kontrol eder.
     * 
     * @param string $permissionName Kontrol edilecek iznin adı (örn: 'kullanici_ekle').
     * @return bool Kullanıcının izni varsa true, yoksa false döner.
     */
    public static function allows(string $permissionName): bool
    {
        $user = AuthController::user();
        if (!$user) {
            return false;
        }

        // Süper admin her şeye izinli olmalı (örn: rol adı 'Super Admin' ise)
        // Bu, veritabanına sürekli sorgu atmayı engeller.
        if (isset($user->role_name) && $user->role_name === 'Super Admin') {
            return true;
        }

        // Kullanıcının izinlerini alalım.
        $permissionModel = new PermissionsModel();
        // Bu metodu bir sonraki adımda güncelleyeceğiz.
        $userPermissions = $permissionModel->getPermissionsForUser($user->id);

        return in_array($permissionName, $userPermissions) ? true : false;
    }



    /**
     * Belirtilen izni kontrol eder. Eğer kullanıcının izni yoksa,
     * bir uyarı mesajı basar ve betiği sonlandırır (exit).
     * Bu metot, bir sayfanın veya işlemin en başında "gatekeeper" (kapı bekçisi)
     * olarak kullanılmak üzere tasarlanmıştır.
     * 
     * @param string $permissionName Gerekli olan iznin adı (örn: 'kullanici_ekle').
     * @param string|null $customMessage (İsteğe bağlı) Varsayılan mesaj yerine gösterilecek özel HTML mesajı.
     * @return void Metot, izin varsa hiçbir şey yapmaz, yoksa betiği sonlandırır.
     */
    public static function authorizeOrDie(string $permissionName, ?string $customMessage = null, bool $redirectUrl = true): void
    {
        // Temel yetki kontrolünü `allows()` metodu ile yapıyoruz.
        // Bu, kod tekrarını önler.
        if (self::allows($permissionName)) {
            // Yetkisi var, hiçbir şey yapma ve kodun akışına devam etmesine izin ver.
            return;
        }

        // --- YETKİ YOKSA BURADAN AŞAĞISI ÇALIŞIR ---

        // Loglama yapmak iyi bir pratiktir.
        $user = AuthController::user();
        \getLogger()->warning("Yetkisiz erişim denemesi engellendi.", [
            'user_id' => $user->id ?? 'Bilinmiyor',
            'email' => $user->email ?? 'Giriş yapılmamış',
            'ip' => $_SERVER['REMOTE_ADDR'],
            'required_permission' => $permissionName,
            'url' => $_SERVER['REQUEST_URI']
        ]);

        // Gösterilecek mesajı belirle.
        if ($customMessage == null) {
            $customMessage = "Bu işlemi gerçekleştirmek veya bu sayfayı görüntülemek için gerekli yetkiye sahip değilsiniz.";
        };

        if ($redirectUrl) {
            // Belirtilen URL'ye yönlendir
            header("Location: /unauthorize");
        } else {


            // Sayfayı yönlendir

            echo '<div class="p-5">
                <div class="alert alert-dismissible mb-4 p-4 d-flex alert-soft-danger-message" role="alert">
                    <div class="me-4 d-none d-md-block">
                        <i class="feather feather-alert-triangle text-danger fs-1"></i>
                    </div>
                    <div>
                        <p class="fw-bold mb-0 text-truncate-1-line">Yetkisiz Erişim</p>
                        <p class="text-truncate-3-line mt-2 mb-4">
                            ' . $customMessage . '
                        </p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>';

            // Betiğin daha fazla çalışmasını engelle.
            // Genellikle bir sayfanın altındaki footer veya diğer bileşenlerin
            // yüklenmesini önlemek için bu gereklidir.
            exit;
        };
    }



/**
 * API'ler için yetki kontrolü yapar, yetkisi yoksa JSON döndürüp exit yapar
 * @param string $permissionName
 */
public static function can(string $permissionName): void
{
    if (!self::allows($permissionName)) {
        $res = [
            "status" => "error",
            "message" => "Bu işlemi yapmaya yetkiniz yok.",
            "data" => []
        ];
        echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

   /** Site sakini mi değil mi ?
     * 
     * @return boolean
     */
    public static function isResident()
    {
        if($_SESSION["user"]->roles == 3){
            return true;
        }
        return false;
    }



}
