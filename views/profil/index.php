<?php
require_once "vendor/autoload.php";
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\SystemLogModel;
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
            
            // disable the button to prevent multiple submissions
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
</script>
