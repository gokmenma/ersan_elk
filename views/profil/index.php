<?php
require_once "vendor/autoload.php";
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\SystemLogModel;
use App\Model\UserNotificationPreferenceModel;
use App\Helper\Form;

$User = new UserModel();
$SystemLog = new SystemLogModel();

$userId = $_SESSION["user_id"] ?? $_SESSION["id"] ?? 0;
// Oturumdaki kullanıcının bilgilerini alalım
$currentUser = $User->find((int)$userId);

// Fetch recent 50 logs of user
$logsQuery = "SELECT * FROM system_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $SystemLog->db->prepare($logsQuery);
$stmt->execute([$userId]);
$loginLogs = $stmt->fetchAll(PDO::FETCH_OBJ);

$showFavoritesBar = (int) ($currentUser->show_favorites_bar ?? 1);
$notificationPreferences = new UserNotificationPreferenceModel();
$userNotificationPreferences = $notificationPreferences->getPreferences((int) $userId);
$notificationOptions = [
    UserNotificationPreferenceModel::TYPE_KACAK_CREATED => ['Kaçak İşlemleri', 'Yeni kaçak tutanağı bildirildiğinde haber alın.', 'shield', 'text-info'],
    UserNotificationPreferenceModel::TYPE_IHBAR_CREATED => ['İhbarlar', 'Yeni bir kaçak su ihbarı oluşturulduğunda haber alın.', 'alert-triangle', 'text-danger'],
    UserNotificationPreferenceModel::TYPE_ADVANCE_REQUEST => ['Avans Talepleri', 'Yeni avans taleplerinden haberdar olun.', 'dollar-sign', 'text-success'],
    UserNotificationPreferenceModel::TYPE_LEAVE_REQUEST => ['İzin Talepleri', 'Yeni izin taleplerinden haberdar olun.', 'calendar', 'text-warning'],
    UserNotificationPreferenceModel::TYPE_GENERAL_REQUEST => ['Genel Talepler', 'Öneri, şikâyet, istek ve diğer talepler için bildirim alın.', 'message-square', 'text-primary'],
    UserNotificationPreferenceModel::TYPE_FAULT_REQUEST => ['Arıza Talepleri', 'Yeni arıza taleplerinden haberdar olun.', 'tool', 'text-danger'],
    UserNotificationPreferenceModel::TYPE_SUPPORT => ['Destek Talepleri', 'Destek talepleri, yanıtları ve durum değişiklikleri için bildirim alın.', 'help-circle', 'text-info'],
    UserNotificationPreferenceModel::TYPE_KM => ['KM Bildirimleri', 'KM hatırlatmaları ve manuel onay bildirimlerini alın.', 'truck', 'text-primary'],
    UserNotificationPreferenceModel::TYPE_TASK => ['Görev Bildirimleri', 'Görev zamanı ve görev süreçleri için bildirim alın.', 'check-square', 'text-warning'],
    UserNotificationPreferenceModel::TYPE_DOCUMENT => ['Evrak Bildirimleri', 'Tarafınıza evrak zimmetlendiğinde bildirim alın.', 'file-text', 'text-secondary'],
    UserNotificationPreferenceModel::TYPE_SHIFT => ['Nöbet Bildirimleri', 'Nöbet talebi, değişim ve mazeret bildirimlerini alın.', 'clock', 'text-primary'],
];
?>

<div class="container-fluid">
    <!-- start page title -->
    <?php
    $maintitle = "Ayarlar";
    $title = "Profil Düzenleme";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs nav-tabs-custom card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#profil-bilgileri" role="tab">
                                <i data-feather="user" class="icon-sm me-1"></i> Profil Bilgileri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#sistem-ayarlari" role="tab">
                                <i data-feather="settings" class="icon-sm me-1"></i> Ayarlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#giris-kayitlari" role="tab">
                                <i data-feather="log-in" class="icon-sm me-1"></i> Giriş Kayıtları
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <!-- Tab panes -->
                    <div class="tab-content">
                        <!-- Profil Bilgileri Tab -->
                        <div class="tab-pane active" id="profil-bilgileri" role="tabpanel">
                            <h4 class="card-title">Profil Bilgileri</h4>
                            <p class="card-title-desc">Kişisel bilgilerinizi buradan güncelleyebilirsiniz.</p>
                            <form id="profileForm">
                                <input type="hidden" name="action" value="profil-guncelle">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <?php echo Form::FormFloatInput("text", "adi_soyadi", $currentUser->adi_soyadi ?? '', "Adı Soyadı Giriniz", "Adı Soyadı", "user", "form-control", true); ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <?php echo Form::FormFloatInput("text", "user_name", $currentUser->user_name ?? '', "Kullanıcı Adı Giriniz", "Kullanıcı Adı", "at-sign", "form-control", true); ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <?php echo Form::FormFloatInput("email", "email_adresi", $currentUser->email_adresi ?? '', "E-Posta Adresi Giriniz", "E-Posta Adresi", "mail", "form-control", false); ?>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <?php echo Form::FormFloatInput("text", "telefon", $currentUser->telefon ?? '', "Telefon Numarası Giriniz", "Telefon", "phone", "form-control phone-mask", false); ?>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <?php echo Form::FormFloatInput("password", "password", "", "Yeni Şifre Giriniz (Değiştirmek istemiyorsanız boş bırakın)", "Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın)", "lock", "form-control", false, null, "new-password"); ?>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="bx bx-save font-size-16 align-middle me-2"></i> Bilgileri Güncelle</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Ayarlar Tab -->
                        <div class="tab-pane" id="sistem-ayarlari" role="tabpanel">
                            <h4 class="card-title">Arayüz ve Sistem Tercihleri</h4>
                            <p class="card-title-desc">Kişisel arayüz tercihlerinizi ve görünüm seçeneklerinizi buradan özelleştirebilirsiniz.</p>

                            <form id="settingsForm">
                                <input type="hidden" name="action" value="ayarlari-guncelle">

                                <div class="card border shadow-none mb-3">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <h5 class="font-size-14 mb-1"><i data-feather="star" class="icon-sm text-warning me-1"></i> Sık Kullanılanlar Çubuğu</h5>
                                                <p class="text-muted font-size-13 mb-0">Tüm sayfalarda üst kısımda görüntülenen Sık Kullanılanlar çubuğunu açıp kapatabilirsiniz.</p>
                                            </div>
                                            <div class="form-check form-switch form-switch-md mb-0">
                                                <input class="form-check-input" type="checkbox" name="show_favorites_bar" value="1" id="showFavoritesBarSwitch" <?php echo ($showFavoritesBar === 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="showFavoritesBarSwitch"></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="font-size-15 mt-4 mb-1">Bildirim Tercihleri</h5>
                                <p class="text-muted font-size-13 mb-3">Almak istediğiniz bildirim türlerini kullanıcı hesabınıza özel olarak seçebilirsiniz.</p>

                                <div class="row g-3 mb-3">
                                    <?php foreach ($notificationOptions as $notificationType => [$label, $description, $icon, $iconClass]): ?>
                                        <?php $inputId = 'notification_' . $notificationType; ?>
                                        <div class="col-12 col-xl-6">
                                            <div class="card border shadow-none h-100 mb-0">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center justify-content-between gap-3">
                                                        <div>
                                                            <h5 class="font-size-14 mb-1"><i data-feather="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" class="icon-sm <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?> me-1"></i> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h5>
                                                            <p class="text-muted font-size-13 mb-0"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                                                        </div>
                                                        <div class="form-check form-switch form-switch-md mb-0 flex-shrink-0">
                                                            <input class="form-check-input" type="checkbox" name="notification_preferences[<?= htmlspecialchars($notificationType, ENT_QUOTES, 'UTF-8') ?>]" value="1" id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" <?= ($userNotificationPreferences[$notificationType] ?? true) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"></label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <h5 class="font-size-15 mt-4 mb-1">Tarama Verileri ve Önbellek</h5>
                                <p class="text-muted font-size-13 mb-3">Sistem güncellemeleri sonrasında eski tarayıcı dosyalarını silmek ve güncel sürüme geçmek için önbelleği temizleyebilirsiniz.</p>

                                <div class="card border shadow-none mb-3">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <h5 class="font-size-14 mb-1"><i data-feather="refresh-cw" class="icon-sm text-info me-1"></i> Tarama Verilerini & Önbelleği Temizle</h5>
                                                <p class="text-muted font-size-13 mb-0">Tarayıcıdaki eski statik dosyaları (JS/CSS) ve sayfa önbelleklerini temizler.</p>
                                            </div>
                                            <button type="button" class="btn btn-outline-danger waves-effect waves-light" onclick="clearDesktopAppCache()">
                                                <i class="bx bx-trash font-size-16 align-middle me-1"></i> Önbelleği Temizle
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="bx bx-save font-size-16 align-middle me-2"></i> Ayarları Kaydet</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Giriş Kayıtları Tab -->
                        <div class="tab-pane" id="giris-kayitlari" role="tabpanel">
                            <h4 class="card-title">Son Giriş Kayıtlarınız</h4>
                            <p class="card-title-desc">Hesabınıza yapılan son 50 sistemsel işlemi ve giriş kaydını aşağıdan inceleyebilirsiniz.</p>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-nowrap align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>İşlem Tipi</th>
                                            <th>Açıklama</th>
                                            <th>Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($loginLogs) > 0): ?>
                                            <?php foreach($loginLogs as $index => $log): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td>
                                                        <?php 
                                                            $badgeClass = 'bg-info';
                                                            if (strpos($log->action_type, 'Başarısız') !== false) {
                                                                $badgeClass = 'bg-danger';
                                                            } elseif (strpos($log->action_type, 'Başarılı') !== false) {
                                                                $badgeClass = 'bg-success';
                                                            }
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($log->action_type); ?></span>
                                                    </td>
                                                    <td style="white-space: wrap;"><?php echo htmlspecialchars($log->description); ?></td>
                                                    <td><?php echo date('d.m.Y H:i:s', strtotime($log->created_at)); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">Kayıt bulunamadı.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        if ($.fn.inputmask && $('.phone-mask').length > 0) {
            $('.phone-mask').inputmask('(999) 999-9999');
        }

        $('#profileForm').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true);
            
            $.ajax({
                url: 'views/profil/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Başarılı!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'Tamam',
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Hata!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'Tamam'
                        });
                        submitBtn.prop('disabled', false);
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Hata!',
                        text: 'Sunucu ile iletişim kurulamadı.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                    submitBtn.prop('disabled', false);
                }
            });
        });

        $('#settingsForm').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true);
            
            $.ajax({
                url: 'views/profil/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Başarılı!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'Tamam',
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Hata!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'Tamam'
                        });
                        submitBtn.prop('disabled', false);
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Hata!',
                        text: 'Sunucu ile iletişim kurulamadı.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                    submitBtn.prop('disabled', false);
                }
            });
        });
    });

    function clearDesktopAppCache() {
        Swal.fire({
            title: 'Tarama Verilerini Temizle',
            text: 'Tarayıcı önbelleği ve geçici sistem verileri temizlenecektir. Güncellenmiş dosyaların yüklenebilmesi için uygulama yeniden başlatılacak. Onaylıyor musunuz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Temizle',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#135bec'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    if ('caches' in window) {
                        const keys = await caches.keys();
                        await Promise.all(keys.map(k => caches.delete(k)));
                    }
                    if ('serviceWorker' in navigator) {
                        const regs = await navigator.serviceWorker.getRegistrations();
                        for (const reg of regs) {
                            await reg.unregister();
                        }
                    }
                    localStorage.removeItem('cevrimdisiSayfaZamani');
                    sessionStorage.clear();
                    
                    Swal.fire({
                        title: 'Başarılı!',
                        text: 'Tarama verileri ve önbellek temizlendi. Sayfa yenileniyor...',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        const url = new URL(window.location.href);
                        url.searchParams.set('cache_bust', Date.now().toString());
                        window.location.href = url.toString();
                    });
                } catch(e) {
                    console.error(e);
                    Swal.fire('Hata!', 'Önbellek temizlenirken bir sorun oluştu.', 'error');
                }
            }
        });
    }
</script>
