<?php

use App\Model\PersonelModel;
use App\Model\PushSubscriptionModel;
use App\Helper\Form;

$personelModel = new PersonelModel();
$subscriptionModel = new PushSubscriptionModel();

// Tüm aktif personelleri getir
$personeller = $personelModel->all();

// Bildirim aboneliği olan personelleri işaretle
$abonelikler = [];
try {
    $db = $subscriptionModel->getDb();
    $stmt = $db->query("SELECT DISTINCT personel_id FROM push_subscriptions");
    $abonelikler = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $abonelikler = [];
}
?>

<div class="container-fluid">
    <?php
    $maintitle = "Bildirimler";
    $title = "Push Bildirim Gönder";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <!-- Bildirim Gönderme Formu -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-bell me-2"></i>Push Bildirim Gönder
                    </h5>
                </div>
                <div class="card-body">
                    <form id="formBildirimGonder">
                        <div class="mb-3">
                            <label class="form-label">Alıcı Seçimi <span class="text-danger">*</span></label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="alici_tipi" id="aliciTekli"
                                    value="tekli" checked>
                                <label class="form-check-label" for="aliciTekli">
                                    Belirli Personel(ler)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="alici_tipi" id="aliciToplu"
                                    value="toplu">
                                <label class="form-check-label" for="aliciToplu">
                                    Tüm Abonelere (
                                    <?= count($abonelikler) ?> kişi)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="personelSecimContainer">
                            <label class="form-label">Personel Seç</label>
                            <select class="form-select" id="personelSelect" name="personel_ids[]" multiple>
                                <?php foreach ($personeller as $personel): ?>
                                    <?php $hasSubscription = in_array($personel->id, $abonelikler); ?>
                                    <option value="<?= $personel->id ?>" <?= !$hasSubscription ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($personel->adi_soyadi) ?>
                                        <?= $hasSubscription ? '✓' : '(Bildirim Yok)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Sadece bildirim aboneliği olanlar seçilebilir (✓ işaretli
                                olanlar)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bildirim Başlığı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="baslik" placeholder="Örn: Duyuru" required
                                maxlength="50">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bildirim Metni <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="mesaj" rows="3"
                                placeholder="Bildirim içeriğini yazın..." required maxlength="200"></textarea>
                            <small class="text-muted">Maksimum 200 karakter</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tıklandığında Açılacak Sayfa (Opsiyonel)</label>
                            <select class="form-select" name="hedef_sayfa">
                                <option value="">Ana Sayfa</option>
                                <option value="index.php?page=bordro">Avans</option>
                                <option value="index.php?page=izin">İzinler</option>
                                <option value="index.php?page=talep">Talepler</option>
                                <option value="index.php?page=profil">Profil</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bx bx-send me-2"></i>Bildirim Gönder
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bilgi Kartları -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-info-circle me-2"></i>Abonelik Durumu
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="border rounded py-3">
                                <h3 class="text-primary mb-0">
                                    <?= count($personeller) ?>
                                </h3>
                                <small class="text-muted">Toplam Personel</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded py-3">
                                <h3 class="text-success mb-0">
                                    <?= count($abonelikler) ?>
                                </h3>
                                <small class="text-muted">Abone</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded py-3">
                                <h3 class="text-warning mb-0">
                                    <?= count($personeller) - count($abonelikler) ?>
                                </h3>
                                <small class="text-muted">Abone Değil</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h6><i class="bx bx-bulb text-warning me-1"></i>Bilgi</h6>
                    <ul class="small text-muted mb-0">
                        <li>Personel, PWA uygulamasına girip bildirim iznini verdikten sonra abone olur.</li>
                        <li>Bildirim izni verilmezse push bildirim gönderilemez.</li>
                        <li>Personel birden fazla cihazda abone olabilir.</li>
                        <li>Gönderilen bildirimler personelin tüm abone cihazlarına iletilir.</li>
                    </ul>
                </div>
            </div>

            <!-- Test Bildirimi Kartı -->
            <div class="card mt-3">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-test-tube me-2"></i>Test Bildirimi
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Kendinize test bildirimi göndererek sistemin çalıştığını doğrulayın.
                    </p>
                    <button type="button" class="btn btn-warning" id="btnTestBildirim">
                        <i class="bx bx-rocket me-1"></i>Kendime Test Bildirimi Gönder
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const API_URL = 'views/bildirim/api.php';

        // Select2 initialization
        if (typeof $.fn.select2 !== 'undefined') {
            $('#personelSelect').select2({
                placeholder: 'Personel seçin...',
                allowClear: true,
                width: '100%'
            });
        }

        // Alıcı tipi değiştiğinde
        document.querySelectorAll('input[name="alici_tipi"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const container = document.getElementById('personelSecimContainer');
                container.style.display = this.value === 'tekli' ? 'block' : 'none';
            });
        });

        // Form gönderimi
        document.getElementById('formBildirimGonder').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'send-notification');

            // Select2'den seçili değerleri al
            const selectedPersonels = $('#personelSelect').val();
            if (formData.get('alici_tipi') === 'tekli' && (!selectedPersonels || selectedPersonels.length === 0)) {
                Swal.fire('Uyarı', 'Lütfen en az bir personel seçin.', 'warning');
                return;
            }

            // Seçili personelleri ekle
            formData.delete('personel_ids[]');
            if (selectedPersonels) {
                selectedPersonels.forEach(id => formData.append('personel_ids[]', id));
            }

            Swal.fire({
                title: 'Gönderiliyor...',
                text: 'Bildirimler gönderiliyor, lütfen bekleyin.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Başarılı', data.message, 'success');
                        document.getElementById('formBildirimGonder').reset();
                        $('#personelSelect').val(null).trigger('change');
                    } else {
                        Swal.fire('Hata', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Hata', 'Bildirim gönderilirken bir hata oluştu.', 'error');
                });
        });

        // Test bildirimi
        document.getElementById('btnTestBildirim').addEventListener('click', function () {
            Swal.fire({
                title: 'Gönderiliyor...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('action', 'test-notification');

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Başarılı', data.message, 'success');
                    } else {
                        Swal.fire('Hata', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Hata', 'Test bildirimi gönderilemedi.', 'error');
                });
        });
    });
</script>