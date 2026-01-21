<?php

use App\Model\PersonelModel;
use App\Model\PushSubscriptionModel;
use App\Model\MesajLogModel;
use App\Helper\Form;

$personelModel = new PersonelModel();
$subscriptionModel = new PushSubscriptionModel();
$mesajLogModel = new MesajLogModel();

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

// Abonelik olan personelleri filtrele
$abonePersoneller = [];
foreach ($personeller as $personel) {
    if (in_array($personel->id, $abonelikler)) {
        $abonePersoneller[$personel->id] = $personel->adi_soyadi;
    }
}

// Logları getir
$logs = $mesajLogModel->getLogs(['type' => 'push']);
?>

<style>
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1rem;
        color: white;
        text-align: center;
    }

    .stat-card.success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .stat-card.warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stat-card h2 {
        font-size: 2rem;
        margin: 0;
        font-weight: 700;
    }

    .stat-card small {
        opacity: 0.9;
        font-size: 0.75rem;
    }

    .card {
        border: none;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        border-radius: 12px;
    }

    .card-header {
        border-radius: 12px 12px 0 0 !important;
        padding: 0.75rem 1rem;
    }

    .card-body {
        padding: 1rem;
    }

    .btn-send {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 0.75rem 2rem;
        font-weight: 600;
    }

    .btn-send:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }

    .table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #6c757d;
    }

    .table td {
        font-size: 0.85rem;
        vertical-align: middle;
    }

    .info-list {
        font-size: 0.8rem;
        padding-left: 1.2rem;
        margin: 0;
    }

    .info-list li {
        margin-bottom: 0.25rem;
    }
</style>

<div class="container-fluid">
    <?php
    $maintitle = "Bildirimler";
    $title = "Push Bildirim Gönder";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row g-3">
        <!-- Sağ Kolon: İstatistikler -->
        <div class="col-lg-5">
            <div class="row g-3">
                <div class="col-4">
                    <div class="stat-card">
                        <h2><?= count($personeller) ?></h2>
                        <small>Toplam</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card success">
                        <h2><?= count($abonelikler) ?></h2>
                        <small>Abone</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card warning">
                        <h2><?= count($personeller) - count($abonelikler) ?></h2>
                        <small>Abone Değil</small>
                    </div>
                </div>
            </div>

            <!-- Bilgi Kartı -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white py-2">
                    <h6 class="card-title mb-0"><i class="bx bx-info-circle me-2"></i>Bilgi</h6>
                </div>
                <div class="card-body py-2">
                    <ul class="info-list text-muted">
                        <li>PWA'da bildirim izni veren personeller abone olur</li>
                        <li>Personel birden fazla cihazda abone olabilir</li>
                        <li>Bildirimler tüm abone cihazlarına iletilir</li>
                    </ul>
                </div>
            </div>



        </div>


        <!-- Sol Kolon: Form -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-primary text-white py-2">
                    <h6 class="card-title mb-0"><i class="bx bx-send me-2"></i>Bildirim Gönder</h6>
                </div>
                <div class="card-body">
                    <form id="formBildirimGonder">
                        <div class="row g-2">
                            <!-- Alıcı Tipi -->
                            <div class="col-12">
                                <div class="d-flex gap-3 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="alici_tipi" id="aliciTekli"
                                            value="tekli" checked>
                                        <label class="form-check-label" for="aliciTekli">Belirli
                                            Personel(ler)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="alici_tipi" id="aliciToplu"
                                            value="toplu">
                                        <label class="form-check-label" for="aliciToplu">Tüm Abonelere
                                            (<?= count($abonelikler) ?>)</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Personel Seçimi -->
                            <div class="col-12" id="personelSecimContainer">
                                <?= Form::FormMultipleSelect2(
                                    'personel_ids',
                                    $abonePersoneller,
                                    [],
                                    'Personel Seç',
                                    'users',
                                    'key',
                                    '',
                                    'form-select select2',
                                    false
                                ) ?>
                            </div>

                            <!-- Başlık -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput(
                                    'text',
                                    'baslik',
                                    '',
                                    'Bildirim başlığı...',
                                    'Başlık',
                                    'home',
                                    'form-control',
                                    true,
                                    50
                                ) ?>
                            </div>

                            <!-- Hedef Sayfa -->
                            <div class="col-md-6">
                                <?= Form::FormSelect2(
                                    'hedef_sayfa',
                                    [
                                        '' => 'Ana Sayfa',
                                        'index.php?page=bordro' => 'Bordro',
                                        'index.php?page=izin' => 'İzinler',
                                        'index.php?page=talep' => 'Talepler',
                                        'index.php?page=profil' => 'Profil'
                                    ],
                                    '',
                                    'Tıklandığında Açılacak Sayfa',
                                    'home',
                                    'key',
                                    '',
                                    'form-select select2',
                                    false
                                ) ?>
                            </div>

                            <!-- Resim -->
                            <div class="col-12">
                                <label for="resim" class="form-label text-muted small">Bildirim Resmi (İsteğe
                                    Bağlı)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bx bx-image"></i></span>
                                    <input type="file" class="form-control" id="resim" name="resim" accept="image/*">
                                </div>
                                <div class="form-text">Önerilen boyut: 512x256px. Max: 2MB.</div>
                            </div>

                            <!-- Mesaj -->
                            <div class="col-12">
                                <?= Form::FormFloatTextarea(
                                    'mesaj',
                                    '',
                                    'Bildirim içeriğini yazın...',
                                    'Mesaj',
                                    'file-text',
                                    'form-control',
                                    true,
                                    '80px',
                                    3
                                ) ?>
                            </div>

                            <!-- Gönder Butonu -->
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary btn-send">
                                    <i class="bx bx-send me-1"></i>Gönder
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>


        </div>


    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const API_URL = 'views/bildirim/api.php';

        // Select2 initialization
        // if (typeof $.fn.select2 !== 'undefined') {
        //     $('#personel_ids').select2({
        //         allowClear: true,
        //         width: '100%'
        //     });
        // }

        // Alıcı tipi değiştiğinde
        document.querySelectorAll('input[name="alici_tipi"]').forEach(radio => {
            radio.addEventListener('change', function () {
                document.getElementById('personelSecimContainer').style.display =
                    this.value === 'tekli' ? 'block' : 'none';
            });
        });

        // Form gönderimi
        document.getElementById('formBildirimGonder').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'send-notification');

            const selectedPersonels = $('#personel_ids').val();
            if (formData.get('alici_tipi') === 'tekli' && (!selectedPersonels || selectedPersonels.length === 0)) {
                Swal.fire('Uyarı', 'Lütfen en az bir personel seçin.', 'warning');
                return;
            }

            formData.delete('personel_ids[]');
            if (selectedPersonels) {
                selectedPersonels.forEach(id => formData.append('personel_ids[]', id));
            }

            Swal.fire({
                title: 'Gönderiliyor...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(API_URL, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Başarılı', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Hata', data.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Hata', 'Bir hata oluştu.', 'error'));
        });

        // Test bildirimi

    });
</script>