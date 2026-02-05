<?php
/**
 * Personel Takip - Yönetici Paneli
 * Saha personellerinin konum bazlı giriş-çıkış takibi
 */

use App\Helper\Helper;
use App\Service\Gate;

// Yetki kontrolü
// if (Gate::canWithMessage("personel_takip")) {

$maintitle = "Personel Takip";
$title = "Saha Personel Takibi";
?>

<div class="container-fluid">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Özet Kartları -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted mb-3 lh-1 d-block text-truncate">Şu An Görevde</span>
                            <h4 class="mb-0">
                                <span class="counter-value" id="stat-gorevde">0</span>
                            </h4>
                        </div>
                        <div class="flex-shrink-0 text-end">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-success text-success rounded-circle fs-3">
                                    <i class="bx bx-run"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted mb-3 lh-1 d-block text-truncate">Görevi Tamamladı</span>
                            <h4 class="mb-0">
                                <span class="counter-value" id="stat-tamamladi">0</span>
                            </h4>
                        </div>
                        <div class="flex-shrink-0 text-end">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-primary text-primary rounded-circle fs-3">
                                    <i class="bx bx-check-circle"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted mb-3 lh-1 d-block text-truncate">Henüz Başlamadı</span>
                            <h4 class="mb-0">
                                <span class="counter-value" id="stat-baslamadi">0</span>
                            </h4>
                        </div>
                        <div class="flex-shrink-0 text-end">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-warning text-warning rounded-circle fs-3">
                                    <i class="bx bx-time"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted mb-3 lh-1 d-block text-truncate">Toplam Personel</span>
                            <h4 class="mb-0">
                                <span class="counter-value" id="stat-toplam">0</span>
                            </h4>
                        </div>
                        <div class="flex-shrink-0 text-end">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-info text-info rounded-circle fs-3">
                                    <i class="bx bx-group"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personel Listesi -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Bugünkü Personel Durumları</h4>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-soft-primary btn-sm" onclick="yenile()">
                            <i class="bx bx-refresh"></i> Yenile
                        </button>
                        <button type="button" class="btn btn-soft-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#raporModal">
                            <i class="bx bx-file"></i> Rapor Al
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="personelTakipTable" class="table table-bordered table-hover nowrap w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">Foto</th>
                                    <th>Personel Adı</th>
                                    <th style="width: 120px;">Durum</th>
                                    <th style="width: 80px;">Başlama</th>
                                    <th style="width: 80px;">Bitiş</th>
                                    <th style="width: 80px;">Konum</th>
                                    <th style="width: 100px;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody id="personelTakipBody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Yükleniyor...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Hareket Geçmişi Modalı -->
<div class="modal fade" id="gecmisModal" tabindex="-1" aria-labelledby="gecmisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="gecmisModalLabel">
                    <i class="bx bx-history me-2"></i>Hareket Geçmişi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Personel Bilgisi -->
                <div class="d-flex align-items-center mb-4 p-3 bg-light rounded">
                    <div class="flex-shrink-0">
                        <img id="gecmisPersonelFoto" src="assets/images/users/user-dummy-img.jpg" class="rounded-circle"
                            width="60" height="60" style="object-fit: cover;">
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-1" id="gecmisPersonelAd">-</h5>
                        <p class="text-muted mb-0" id="gecmisPersonelTarih">Son 7 günlük hareketler</p>
                    </div>
                </div>

                <!-- Tarih Filtresi -->
                <div class="row mb-3">
                    <div class="col-md-5">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" class="form-control" id="gecmisBaslangic">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" class="form-control" id="gecmisBitis">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-primary w-100" onclick="filtreGecmis()">
                            <i class="bx bx-filter-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Hareketler Tablosu -->
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-striped">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Tarih</th>
                                <th>Saat</th>
                                <th>İşlem</th>
                                <th>Konum</th>
                                <th>Hassasiyet</th>
                            </tr>
                        </thead>
                        <tbody id="gecmisTabloBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Yükleniyor...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Rapor Modalı -->
<div class="modal fade" id="raporModal" tabindex="-1" aria-labelledby="raporModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="raporModalLabel">
                    <i class="bx bx-file me-2"></i>Hareket Raporu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="raporBaslangic"
                        value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="raporBitis" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" onclick="raporIndir()">
                    <i class="bx bx-download me-1"></i>Excel İndir
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 48px;
        height: 48px;
    }

    .avatar-title {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }

    .card-h-100 {
        height: calc(100% - 24px);
    }

    .btn-soft-primary {
        background-color: rgba(85, 110, 230, 0.1);
        color: #556ee6;
    }

    .btn-soft-primary:hover {
        background-color: #556ee6;
        color: #fff;
    }

    .btn-soft-success {
        background-color: rgba(52, 195, 143, 0.1);
        color: #34c38f;
    }

    .btn-soft-success:hover {
        background-color: #34c38f;
        color: #fff;
    }

    .btn-soft-info {
        background-color: rgba(80, 165, 241, 0.1);
        color: #50a5f1;
    }

    .btn-soft-info:hover {
        background-color: #50a5f1;
        color: #fff;
    }

    #personelTakipTable tbody tr:hover {
        background-color: rgba(85, 110, 230, 0.05);
    }
</style>

<script>
    var currentPersonelId = null;

    document.addEventListener('DOMContentLoaded', function () {
        // İstatistikleri yükle
        loadOzet();
        // Personel listesini yükle
        loadPersonelDurumlari();

        // Varsayılan tarih aralıkları
        var today = new Date();
        var weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);

        document.getElementById('gecmisBaslangic').value = weekAgo.toISOString().split('T')[0];
        document.getElementById('gecmisBitis').value = today.toISOString().split('T')[0];

        // Her 60 saniyede otomatik yenile
        setInterval(function () {
            loadOzet();
            loadPersonelDurumlari();
        }, 60000);
    });

    async function loadOzet() {
        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getOzet'
            });
            const result = await response.json();

            if (result.success && result.data) {
                document.getElementById('stat-gorevde').textContent = result.data.gorevde;
                document.getElementById('stat-tamamladi').textContent = result.data.tamamladi;
                document.getElementById('stat-baslamadi').textContent = result.data.baslamadi;
                document.getElementById('stat-toplam').textContent = result.data.toplam;
            }
        } catch (error) {
            console.error('Özet yüklenirken hata:', error);
        }
    }

    async function loadPersonelDurumlari() {
        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getPersonelDurumlari'
            });
            const result = await response.json();

            const tbody = document.getElementById('personelTakipBody');

            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(function (p) {
                    html += '<tr>';
                    html += '<td class="text-center">' + p.foto + '</td>';
                    html += '<td><strong>' + p.adi_soyadi + '</strong></td>';
                    html += '<td>' + p.durum + '</td>';
                    html += '<td class="text-center">' + p.baslama + '</td>';
                    html += '<td class="text-center">' + p.bitis + '</td>';
                    html += '<td class="text-center">' + p.konum + '</td>';
                    html += '<td>' + p.islemler + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;

                // Detay butonlarına event listener ekle
                document.querySelectorAll('.btn-detay').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const personelId = this.getAttribute('data-id');
                        showGecmis(personelId);
                    });
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Henüz kayıt bulunmuyor</td></tr>';
            }
        } catch (error) {
            console.error('Personel durumları yüklenirken hata:', error);
            document.getElementById('personelTakipBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Yüklenirken hata oluştu</td></tr>';
        }
    }

    async function showGecmis(personelId) {
        currentPersonelId = personelId;

        // Modal göster
        var modal = new bootstrap.Modal(document.getElementById('gecmisModal'));
        modal.show();

        // Loading göster
        document.getElementById('gecmisTabloBody').innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';

        await loadGecmis();
    }

    async function loadGecmis() {
        if (!currentPersonelId) return;

        const baslangic = document.getElementById('gecmisBaslangic').value;
        const bitis = document.getElementById('gecmisBitis').value;

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getHareketGecmisi&personel_id=' + encodeURIComponent(currentPersonelId) +
                    '&baslangic=' + baslangic + '&bitis=' + bitis
            });
            const result = await response.json();

            if (result.success && result.data) {
                // Personel bilgilerini güncelle
                const personel = result.data.personel;
                document.getElementById('gecmisPersonelAd').textContent = personel.adi_soyadi;
                if (personel.foto) {
                    document.getElementById('gecmisPersonelFoto').src = 'uploads/personel/' + personel.foto;
                }

                // Hareketleri listele
                const hareketler = result.data.hareketler;
                const tbody = document.getElementById('gecmisTabloBody');

                if (hareketler.length > 0) {
                    let html = '';
                    hareketler.forEach(function (h) {
                        html += '<tr>';
                        html += '<td>' + h.tarih + '</td>';
                        html += '<td>' + h.saat + '</td>';
                        html += '<td>' + h.islem + '</td>';
                        html += '<td>' + h.konum + '</td>';
                        html += '<td>' + h.hassasiyet + '</td>';
                        html += '</tr>';
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Bu tarih aralığında hareket bulunamadı</td></tr>';
                }
            } else {
                document.getElementById('gecmisTabloBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">' + (result.message || 'Hata oluştu') + '</td></tr>';
            }
        } catch (error) {
            console.error('Geçmiş yüklenirken hata:', error);
            document.getElementById('gecmisTabloBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Yüklenirken hata oluştu</td></tr>';
        }
    }

    function filtreGecmis() {
        loadGecmis();
    }

    function yenile() {
        loadOzet();
        loadPersonelDurumlari();

        // Yenile butonunda görsel feedback
        const btn = document.querySelector('[onclick="yenile()"]');
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Yenileniyor...';
        btn.disabled = true;

        setTimeout(function () {
            btn.innerHTML = '<i class="bx bx-refresh"></i> Yenile';
            btn.disabled = false;
        }, 1000);
    }

    async function raporIndir() {
        const baslangic = document.getElementById('raporBaslangic').value;
        const bitis = document.getElementById('raporBitis').value;

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getRapor&baslangic=' + baslangic + '&bitis=' + bitis
            });
            const result = await response.json();

            if (result.success && result.data) {
                // Excel formatında indir (CSV)
                let csv = 'Personel;Tarih;Saat;İşlem;Enlem;Boylam\n';
                result.data.forEach(function (row) {
                    // HTML taglarını temizle
                    const islem = row.islem.replace(/<[^>]*>/g, '');
                    csv += row.personel + ';' + row.tarih + ';' + row.saat + ';' + islem + ';' + row.enlem + ';' + row.boylam + '\n';
                });

                // Download
                const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'personel_hareketleri_' + baslangic + '_' + bitis + '.csv';
                link.click();

                // Modal kapat
                bootstrap.Modal.getInstance(document.getElementById('raporModal')).hide();
            } else {
                alert(result.message || 'Rapor oluşturulamadı');
            }
        } catch (error) {
            console.error('Rapor indirilirken hata:', error);
            alert('Rapor indirilirken hata oluştu');
        }
    }
</script>

<?php // } ?>