<?php
/**
 * Canlı Destek Ayarları Tab İçeriği
 */

$Settings = $Settings ?? new \App\Model\SettingsModel();
$firma_id = $_SESSION["firma_id"] ?? null;
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

$canliDestekAktif = $allSettings['canli_destek_aktif'] ?? '1';
$yetkiliKullanicilar = $allSettings['canli_destek_yetkili_kullanicilar'] ?? '';
$yetkiliIds = array_filter(explode(',', $yetkiliKullanicilar));

$canliDestekGunler = $allSettings['canli_destek_gunler'] ?? '1,2,3,4,5,6';
$canliDestekGunDizisi = explode(',', $canliDestekGunler);
$canliDestekBaslama = $allSettings['canli_destek_baslama_saati'] ?? '08:00';
$canliDestekBitis = $allSettings['canli_destek_bitis_saati'] ?? '18:00';

// Tüm kullanıcıları getir
$db = (new \App\Model\Model('users'))->getDb();
$stmtUsers = $db->query("SELECT id, adi_soyadi, user_name FROM users WHERE silinme_tarihi IS NULL ORDER BY adi_soyadi");
$tumKullanicilar = $stmtUsers->fetchAll(PDO::FETCH_OBJ);
?>

<div class="row">
    <div class="col-lg-8">
        <form id="formCanliDestekAyarlari">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="firma_id" value="<?php echo $_SESSION['firma_id'] ?? ''; ?>">

            <!-- Canlı Destek Durumu -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white d-flex align-items-center gap-2">
                    <i class='bx bx-support text-primary fs-4'></i>
                    <h5 class="mb-0">Canlı Destek Sistemi</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">Canlı Destek</h6>
                            <p class="text-muted mb-0 font-size-13">
                                Canlı destek sistemini açıp kapatabilirsiniz. Kapalı olduğunda
                                personel PWA'sında chat butonu gizlenir ve yönetici panelinde
                                destek widget'ı devre dışı kalır.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="form-check form-switch form-switch-lg d-inline-block">
                                <input class="form-check-input" type="checkbox" role="switch" id="canli_destek_aktif"
                                    name="canli_destek_aktif" value="1" <?= $canliDestekAktif === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="canli_destek_aktif">
                                    <span id="destek-durum-label"
                                        class="badge <?= $canliDestekAktif === '1' ? 'bg-success' : 'bg-danger' ?> font-size-12">
                                        <?= $canliDestekAktif === '1' ? 'Açık' : 'Kapalı' ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Yetkili Kullanıcılar -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white d-flex align-items-center gap-2">
                    <i class='bx bx-user-check text-success fs-4'></i>
                    <h5 class="mb-0">Yetkili Kullanıcılar</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted font-size-13 mb-3">
                        Canlı destek widget'ını görecek yönetici kullanıcıları seçin.
                        <strong>Hiç kullanıcı seçilmezse tüm yöneticiler</strong> widget'ı görebilir.
                    </p>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white"><i class='bx bx-search'></i></span>
                        <input type="text" class="form-control" id="yetkiliUserSearch" 
                               placeholder="Kullanıcı ara..." onkeyup="filterYetkiliUsers(this.value)">
                    </div>
                    <div class="list-group" id="yetkiliUserList" style="max-height:280px; overflow-y:auto;">
                        <?php foreach ($tumKullanicilar as $user): 
                            $isSelected = in_array($user->id, $yetkiliIds);
                            $initial = mb_strtoupper(mb_substr($user->adi_soyadi, 0, 1));
                            $colors = ['#135bec','#22c55e','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
                            $color = $colors[$user->id % count($colors)];
                        ?>
                        <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 px-3 yetkili-user-row" 
                               for="yetkili_user_<?= $user->id ?>" 
                               data-name="<?= htmlspecialchars(mb_strtolower($user->adi_soyadi)) ?>"
                               data-username="<?= htmlspecialchars(mb_strtolower($user->user_name)) ?>"
                               style="cursor:pointer; border-radius:8px; margin-bottom:4px;">
                            <div style="width:36px; height:36px; border-radius:50%; background:<?= $color ?>15; color:<?= $color ?>; 
                                        display:flex; align-items:center; justify-content:center; font-weight:700; font-size:15px; flex-shrink:0;">
                                <?= $initial ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="font-size:14px"><?= htmlspecialchars($user->adi_soyadi) ?></div>
                                <div class="text-muted" style="font-size:12px">@<?= htmlspecialchars($user->user_name) ?></div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input yetkili-user-cb" type="checkbox" role="switch"
                                       id="yetkili_user_<?= $user->id ?>" value="<?= $user->id ?>"
                                       <?= $isSelected ? 'checked' : '' ?>>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Mesai Ayarları -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white d-flex align-items-center gap-2">
                    <i class='bx bx-time-five text-warning fs-4'></i>
                    <h5 class="mb-0">Mesai Saatleri ve Günleri</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted font-size-13 mb-3">
                        Canlı destek sisteminin aktif olacağı günleri ve saatleri belirleyin. Belirlenen mesai dışındaki mesajlarda personele otomatik <b>"Mesai Dışı"</b> yanıtı iletilir.
                    </p>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Aktif Günler</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                $gunler = [
                                    1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 
                                    4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'
                                ];
                                foreach($gunler as $val => $ad):
                                ?>
                                <div class="form-check form-check-inline form-check-primary me-2">
                                    <input class="form-check-input canli-destek-gun-cb" type="checkbox" id="gun_<?= $val ?>" value="<?= $val ?>" <?= in_array((string)$val, $canliDestekGunDizisi) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="gun_<?= $val ?>"><?= $ad ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label" for="canli_destek_baslama_saati">Mesai Başlama Saati</label>
                            <input type="time" class="form-control" name="canli_destek_baslama_saati" id="canli_destek_baslama_saati" value="<?= htmlspecialchars($canliDestekBaslama) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="canli_destek_bitis_saati">Mesai Bitiş Saati</label>
                            <input type="time" class="form-control" name="canli_destek_bitis_saati" id="canli_destek_bitis_saati" value="<?= htmlspecialchars($canliDestekBitis) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bilgi Kartı -->
            <div class="card border shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class='bx bx-info-circle text-info me-1'></i> Bilgilendirme</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-soft-info mb-0">
                        <h6><i class='bx bx-time-five me-1'></i> Mesai Saatleri</h6>
                        <p class="mb-2">Canlı destek sistemi mesai saatleri dışında (08:00-18:00 ve hafta sonları)
                            otomatik olarak personellere mesai dışı mesajı gönderir.</p>
                        <h6><i class='bx bx-image me-1'></i> Dosya Gönderimi</h6>
                        <p class="mb-0">Şu an sadece resim dosyaları (JPEG, PNG, GIF, WebP) gönderilebilir.
                            Maksimum dosya boyutu 5MB'dır.</p>
                    </div>
                </div>
            </div>

            <!-- Kaydet Butonu -->
            <div class="text-end mb-3">
                <button type="submit" class="btn btn-primary px-4">
                    <i class='bx bx-save me-1'></i> Kaydet
                </button>
            </div>
        </form>
    </div>

    <!-- Sağ Panel - Özet -->
    <div class="col-lg-4">
        <div class="card border shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class='bx bx-bar-chart-alt-2 text-primary me-1'></i> Destek İstatistikleri</h5>
            </div>
            <div class="card-body" id="destek-istatistikler">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="text-muted mt-2 mb-0 font-size-13">Yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Toggle label güncelle
        $('#canli_destek_aktif').on('change', function () {
            const label = $('#destek-durum-label');
            if (this.checked) {
                label.text('Açık').removeClass('bg-danger').addClass('bg-success');
            } else {
                label.text('Kapalı').removeClass('bg-success').addClass('bg-danger');
            }
        });

        // Form kaydet
        $('#formCanliDestekAyarlari').on('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Checkbox kapalıysa 0 gönder
            if (!$('#canli_destek_aktif').is(':checked')) {
                formData.set('canli_destek_aktif', '0');
            }

            // Yetkili kullanıcıları checkbox'lardan topla
            const selectedUsers = [];
            $('.yetkili-user-cb:checked').each(function() {
                selectedUsers.push($(this).val());
            });
            formData.set('canli_destek_yetkili_kullanicilar', selectedUsers.join(','));

            // Günleri topla
            const selectedDays = [];
            $('.canli-destek-gun-cb:checked').each(function() {
                selectedDays.push($(this).val());
            });
            formData.set('canli_destek_gunler', selectedDays.join(','));

            $.ajax({
                url: 'views/ayarlar/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: 'Canlı destek ayarları kaydedildi.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Hata', res.message || 'Kayıt başarısız', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Hata', 'Sunucu hatası oluştu.', 'error');
                }
            });
        });

        // İstatistikleri yükle
        loadDestekStats();
    });

    function loadDestekStats() {
        $.post('views/destek/api.php', { action: 'get-all-conversations' }, function (res) {
            if (res.status === 'success') {
                const convs = res.conversations || [];
                const acik = convs.filter(c => c.durum === 'acik' || c.durum === 'beklemede').length;
                const cozuldu = convs.filter(c => c.durum === 'cozuldu').length;
                const kapali = convs.filter(c => c.durum === 'kapali').length;

                $('#destek-istatistikler').html(`
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center">
                                <i class='bx bx-message-dots fs-4'></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-muted mb-0">Toplam Konuşma</p>
                            <h5 class="mb-0">${convs.length}</h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm rounded-circle bg-soft-warning text-warning d-flex align-items-center justify-content-center">
                                <i class='bx bx-time-five fs-4'></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-muted mb-0">Açık / Beklemede</p>
                            <h5 class="mb-0">${acik}</h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm rounded-circle bg-soft-success text-success d-flex align-items-center justify-content-center">
                                <i class='bx bx-check-circle fs-4'></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-muted mb-0">Çözüldü</p>
                            <h5 class="mb-0">${cozuldu}</h5>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm rounded-circle bg-soft-danger text-danger d-flex align-items-center justify-content-center">
                                <i class='bx bx-x-circle fs-4'></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-muted mb-0">Kapalı</p>
                            <h5 class="mb-0">${kapali}</h5>
                        </div>
                    </div>
                `);
            }
        }, 'json').fail(function () {
            $('#destek-istatistikler').html('<p class="text-muted text-center">Veri yüklenemedi</p>');
        });
    }
    function filterYetkiliUsers(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll('.yetkili-user-row').forEach(row => {
            const name = row.dataset.name || '';
            const username = row.dataset.username || '';
            row.style.display = (!q || name.includes(q) || username.includes(q)) ? '' : 'none';
        });
    }
</script>
