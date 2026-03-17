<?php

namespace App\Service;

use App\Helper\Helper;
use App\Helper\Alert;
use App\Controllers\AuthController;
use App\Model\PermissionsModel;
use App\Service\RequestPerformanceProfiler;
use Exception;

class Gate
{
    private static array $requestPermissionSetCache = [];
    private static array $requestAllowsCache = [];
    private static ?bool $requestSuperAdminCache = null;

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

        $userId = (int) ($user->id ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if (isset(self::$requestAllowsCache[$userId][$permissionName])) {
            return self::$requestAllowsCache[$userId][$permissionName];
        }

        /**Süper Admin daha sonra açılacak */
        // if (self::isSuperAdmin()) {
        //     self::$requestAllowsCache[$userId][$permissionName] = true;
        //     return true;
        // }

        if (!isset(self::$requestPermissionSetCache[$userId])) {
            $sessionCache = $_SESSION['permission_cache'][$userId] ?? null;
            $hasValidSessionCache = is_array($sessionCache)
                && !empty($sessionCache['expires_at'])
                && ($sessionCache['expires_at'] > time())
                && is_array($sessionCache['permissions'] ?? null);

            if ($hasValidSessionCache) {
                self::$requestPermissionSetCache[$userId] = $sessionCache['permissions'];
            } else {
                $permissionModel = new PermissionsModel();
                $permissionNames = RequestPerformanceProfiler::measure(
                    'gate.permissions_for_user',
                    fn() => $permissionModel->getPermissionsForUser($userId),
                    2
                );

                $permissionSet = array_flip(array_filter($permissionNames));
                self::$requestPermissionSetCache[$userId] = $permissionSet;
                $_SESSION['permission_cache'][$userId] = [
                    'expires_at' => time() + 300,
                    'permissions' => $permissionSet
                ];
            }
        }

        $allowed = isset(self::$requestPermissionSetCache[$userId][$permissionName]);
        self::$requestAllowsCache[$userId][$permissionName] = $allowed;

        return $allowed;
    }

    /**
     * Belirtilen izni kontrol eder. Eğer kullanıcının izni yoksa,
     * bir uyarı mesajı basar ve betiği sonlandırır (exit).
     * Bu metot, bir sayfanın veya işlemin en başında "gatekeeper" (kapı bekçisi)
     * olarak kullanılmak üzere tasarlanmıştır.
     * 
     * @param string $permissionName Gerekli olan iznin adı (örn: 'kullanici_ekle').
     * param string|null $customMessage (İsteğe bağlı) Varsayılan mesaj yerine gösterilecek özel HTML mesajı.
     * @return void Metot, izin varsa hiçbir şey yapmaz, yoksa betiği sonlandırır.
     */
    public static function authorizeOrDie(string $permissionName, ?string $customMessage = null, bool $redirectUrl = false): void
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


        // Gösterilecek mesajı belirle.
        if ($customMessage == null) {
            $customMessage = "Bu işlemi gerçekleştirmek veya bu sayfayı görüntülemek için gerekli yetkiye sahip değilsiniz.";
        }
        ;

        if ($redirectUrl) {
            // Belirtilen URL'ye yönlendir

            echo "<script> window.location.href = '/unauthorize.php'; </script>";
            exit;
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
        }
        ;
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


    /**
     * Yetki yoksa alert mesaj basar
     */
    public static function canWithMessage(string $permissionName): bool
    {
        try {
            self::allows($permissionName);
            return true;

        } catch (Exception $e) {

            Alert::danger($e->getMessage());

            return false;
        }
    }


    /**
     * Süper admin kontrolü,roles alanı birden fazla id içerir
     */
    public static function isSuperAdmin(): bool
    {
        if (self::$requestSuperAdminCache !== null) {
            return self::$requestSuperAdminCache;
        }

        $user = AuthController::user();
        
        // Kullanıcının roles alanını alalım (uyumluluk için role_id'ye de bakıyoruz)
        $rolesStr = $user->roles ?? $user->role_id ?? null;

        if (!$user || empty($rolesStr)) {
            self::$requestSuperAdminCache = false;
            return false;
        }

        $userId = (int) ($user->id ?? 0);
        $sessionCache = $_SESSION['is_super_admin_cache'][$userId] ?? null;
        if (is_array($sessionCache)
            && !empty($sessionCache['expires_at'])
            && ($sessionCache['expires_at'] > time())
            && isset($sessionCache['value'])
        ) {
            self::$requestSuperAdminCache = (bool) $sessionCache['value'];
            return self::$requestSuperAdminCache;
        }

        // Virgülle ayrılmış id'leri diziye çevirelim
        $rolesArray = array_filter(array_map('trim', explode(',', (string)$rolesStr)));
        if (empty($rolesArray)) {
            self::$requestSuperAdminCache = false;
            return false;
        }

        // Veritabanı bağlantısı almak için Model sınıfından yararlanalım
        $db = (new \App\Model\Model())->db;
        
        $placeholders = implode(',', array_fill(0, count($rolesArray), '?'));
        
        // Kullanıcının roles alanındaki id'lerden herhangi biri superadmin=1 olarak işaretlenmiş mi kontrol et
        $sql = "SELECT COUNT(*) FROM user_roles WHERE superadmin = 1 AND id IN ($placeholders)";
        $isSuperAdmin = RequestPerformanceProfiler::measure(
            'gate.superadmin_check',
            function () use ($db, $sql, $rolesArray) {
                $stmt = $db->prepare($sql);
                $stmt->execute($rolesArray);
                return $stmt->fetchColumn() > 0;
            },
            1
        );

        self::$requestSuperAdminCache = (bool) $isSuperAdmin;

        if ($userId > 0) {
            $_SESSION['is_super_admin_cache'][$userId] = [
                'expires_at' => time() + 300,
                'value' => self::$requestSuperAdminCache
            ];
        }

        return self::$requestSuperAdminCache;
    }




}

