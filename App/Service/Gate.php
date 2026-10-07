<?php

namespace App\Service;

use App\Helper\Helper;
use App\Helper\Alert;
use App\Controllers\AuthController;
use App\Model\PermissionsModel;
use App\Model\PermissionPolicyModel;
use App\Model\MenuModel;
use App\Model\SystemLogModel;
use App\Model\UserRoleAssignmentModel;
use App\Service\RequestPerformanceProfiler;
use Exception;

class Gate
{
    private static array $requestPermissionSetCache = [];
    private static array $requestAllowsCache = [];
    private static ?bool $requestSuperAdminCache = null;
    private static array $requestPolicyCache = [];
    private static array $loggedSuperAdminPolicies = [];

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

        if (self::isSuperAdmin()) {
            self::$requestAllowsCache[$userId][$permissionName] = true;
            return true;
        }

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

    public static function allowsAny(array $permissionNames): bool
    {
        foreach (array_values(array_unique(array_filter($permissionNames))) as $permissionName) {
            if (self::allows((string) $permissionName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sayfa/API politika kontrolü. Politika tablosu kurulunca tanımsız kaynaklar varsayılan reddedilir.
     * Geçiş SQL'i uygulanmamışsa sayfalarda mevcut menü kontrolüne geri döner.
     */
    public static function allowsPolicy(
        string $scope,
        string $resource,
        string $action = '',
        ?string $httpMethod = null
    ): bool {
        $user = AuthController::user();
        $userId = (int) ($user->id ?? 0);
        $personelId = (int) ($_SESSION['personel_id'] ?? 0);
        if ($userId <= 0 && $personelId <= 0) {
            return false;
        }

        $scope = $scope === 'api' ? 'api' : 'page';
        $resource = trim($resource, '/');
        $action = trim($action);
        $httpMethod = strtoupper($httpMethod ?: ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $subjectKey = $userId > 0 ? 'u' . $userId : 'p' . $personelId;
        $cacheKey = implode('|', [$subjectKey, $scope, $resource, $action, $httpMethod]);
        if (array_key_exists($cacheKey, self::$requestPolicyCache)) {
            return self::$requestPolicyCache[$cacheKey];
        }

        $policyModel = new PermissionPolicyModel();
        if (!$policyModel->isReady()) {
            $legacyResource = match ($resource) {
                'bordro/ai-analiz' => 'bordro/list',
                'kullanici-gruplari/yetki-matrisi',
                'kullanici-gruplari/yetki-denetimi',
                'kullanici-gruplari/yetki-katalogu' => 'kullanici-gruplari/list',
                default => $resource,
            };
            $allowed = $scope === 'page'
                ? (new MenuModel())->userCanAccessMenuLink($userId, $legacyResource)
                : false;
            return self::$requestPolicyCache[$cacheKey] = $allowed;
        }

        $policy = $policyModel->resolve($scope, $resource, $action, $httpMethod);
        if (!$policy) {
            error_log("Tanımsız yetki politikası reddedildi: {$scope} {$resource} {$action} {$httpMethod}");
            return self::$requestPolicyCache[$cacheKey] = false;
        }

        if ($userId > 0 && self::isSuperAdmin()) {
            self::logSuperAdminPolicyPass($userId, $policy);
            return self::$requestPolicyCache[$cacheKey] = true;
        }

        if ((int) ($policy->authenticated_only ?? 0) === 1) {
            return self::$requestPolicyCache[$cacheKey] = true;
        }

        if ($userId <= 0 || (int) $policy->superadmin_only === 1 || empty($policy->permission_key)) {
            return self::$requestPolicyCache[$cacheKey] = false;
        }

        return self::$requestPolicyCache[$cacheKey] = self::allows((string) $policy->permission_key);
    }

    public static function authorizeApiPolicy(string $resource, string $action = ''): void
    {
        $httpMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST');
        $policyModel = new PermissionPolicyModel();
        $policy = $policyModel->resolve('api', $resource, $action, $httpMethod);

        if (\App\Service\ObserverMode::isActive()
            && !\App\Service\ObserverMode::allowsPolicy($policy, $resource, $action, $httpMethod)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'Gözlem modunda veri değiştiren veya sınıflandırılmamış işlemler kullanılamaz.',
                'data' => [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (self::allowsPolicy('api', $resource, $action, $httpMethod)) {
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Bu API işlemini gerçekleştirmek için yetkiniz bulunmamaktadır.',
            'data' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function logSuperAdminPolicyPass(int $userId, object $policy): void
    {
        $policyId = (int) ($policy->id ?? 0);
        if ($policyId <= 0 || isset(self::$loggedSuperAdminPolicies[$policyId])) {
            return;
        }
        self::$loggedSuperAdminPolicies[$policyId] = true;

        try {
            (new SystemLogModel())->logAction(
                $userId,
                'Superadmin Yetki Geçişi',
                sprintf(
                    'Politika #%d üzerinden erişim: %s %s%s',
                    $policyId,
                    (string) ($policy->scope ?? ''),
                    (string) ($policy->resource ?? ''),
                    empty($policy->action) ? '' : ' / ' . (string) $policy->action
                ),
                SystemLogModel::LEVEL_CRITICAL
            );
        } catch (\Throwable $e) {
            error_log('Superadmin politika geçişi loglanamadı: ' . $e->getMessage());
        }
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
     * Süper admin kontrolü
     */
    public static function isSuperAdmin(): bool
    {
        $user = AuthController::user();
        if (!$user) {
            return false;
        }
        if (isset($user->role) && $user->role === 'superadmin') {
            return true;
        }
        $userId = (int) ($user->id ?? 0);
        if ($userId <= 0) {
            return false;
        }
        if (self::$requestSuperAdminCache !== null) {
            return self::$requestSuperAdminCache;
        }
        $roleModel = new UserRoleAssignmentModel();
        $roleIds = $roleModel->activeRoleIdsForUser($userId);
        if (!$roleIds) {
            return self::$requestSuperAdminCache = false;
        }
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $roleModel->getDb()->prepare(
            "SELECT COUNT(*) FROM user_roles
             WHERE id IN ({$placeholders}) AND (superadmin = 1 OR role_type = 'superadmin')"
        );
        $stmt->execute($roleIds);
        return self::$requestSuperAdminCache = (int) $stmt->fetchColumn() > 0;
    }




}

