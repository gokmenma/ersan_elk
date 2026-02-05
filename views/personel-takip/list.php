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

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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
                            <span class="text-muted mb-3 lh-1 d-block text-truncate">Geç Kalanlar</span>
                            <h4 class="mb-0">
                                <span class="counter-value text-danger" id="stat-gec-kalan">0</span>
                            </h4>
                        </div>
                        <div class="flex-shrink-0 text-end">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-danger text-danger rounded-circle fs-3">
                                    <i class="bx bx-alarm-exclamation"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tabListe" role="tab">
                                <i class="bx bx-list-ul me-1"></i> Personel Listesi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabHarita" role="tab"
                                onclick="setTimeout(initHarita, 200)">
                                <i class="bx bx-map me-1"></i> Harita Görünümü
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabRapor" role="tab"
                                onclick="loadCalismaRaporu()">
                                <i class="bx bx-bar-chart-alt-2 me-1"></i> Çalışma Süreleri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabGecKalanlar" role="tab"
                                onclick="loadGecKalanlar()">
                                <i class="bx bx-alarm-exclamation me-1"></i> Geç Kalanlar
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- PERSONEL LİSTESİ TAB -->
                        <div class="tab-pane fade show active" id="tabListe" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Bugünkü Personel Durumları</h5>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-soft-primary btn-sm" onclick="yenile()">
                                        <i class="bx bx-refresh"></i> Yenile
                                    </button>
                                    <button type="button" class="btn btn-soft-success btn-sm" id="exportExcel">
                                        <i class="bx bx-file"></i> Excel
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="personelTakipTable" class="table table-bordered table-hover nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">Foto</th>
                                            <th>Personel Adı</th>
                                            <th style="width: 120px;">Durum</th>
                                            <th style="width: 100px;">Başlama</th>
                                            <th style="width: 100px;">Bitiş</th>
                                            <th style="width: 100px;">Konum</th>
                                            <th style="width: 100px;">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="personelTakipBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- HARİTA TAB -->
                        <div class="tab-pane fade" id="tabHarita" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-4">
                                    <h5 class="mb-0">Personel Konum Haritası</h5>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="haritaModu" id="modGorev" checked
                                            onchange="loadHaritaVerileri()">
                                        <label class="btn btn-outline-primary" for="modGorev"><i
                                                class="bx bx-briefcase me-1"></i> Görev Konumları</label>

                                        <input type="radio" class="btn-check" name="haritaModu" id="modAnlik"
                                            onchange="loadHaritaVerileri()">
                                        <label class="btn btn-outline-danger ms-2" for="modAnlik"><i
                                                class="bx bx-target-lock me-1"></i> Anlık Konumlar</label>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-success"><i class="bx bx-circle"></i> Görevde</span>
                                    <span class="badge bg-primary"><i class="bx bx-circle"></i> Tamamladı</span>
                                    <span class="badge bg-danger"><i class="bx bx-circle"></i> Başlamadı</span>
                                </div>
                            </div>
                            <div id="personelHarita" style="height: 500px; border-radius: 8px;"></div>
                        </div>

                        <!-- ÇALIŞMA SÜRELERİ TAB -->
                        <div class="tab-pane fade" id="tabRapor" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Haftalık Çalışma Süreleri</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="date" class="form-control form-control-sm" id="raporBaslangic"
                                        style="width: 150px;">
                                    <span>-</span>
                                    <input type="date" class="form-control form-control-sm" id="raporBitis"
                                        style="width: 150px;">
                                    <button class="btn btn-sm btn-primary" onclick="loadCalismaRaporu()">
                                        <i class="bx bx-filter-alt"></i> Filtrele
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="raporExcelIndir()">
                                        <i class="bx bx-download"></i> Excel
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped dt-responsive nowrap"
                                    id="calismaRaporuTable" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Personel</th>
                                            <th class="text-center">Toplam Gün</th>
                                            <th class="text-center">Toplam Saat</th>
                                            <th class="text-center">Ort. Başlama</th>
                                            <th class="text-center">Ort. Bitiş</th>
                                            <th class="text-center">Geç Kalma</th>
                                        </tr>
                                    </thead>
                                    <tbody id="calismaRaporuBody">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Yükleniyor...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- GEÇ KALANLAR TAB -->
                        <div class="tab-pane fade" id="tabGecKalanlar" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="bx bx-alarm-exclamation text-danger me-1"></i>
                                    Bugün Geç Kalan Personeller
                                </h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <label class="form-label mb-0 me-2">Başlama Saati Limiti:</label>
                                    <input type="time" class="form-control form-control-sm" id="gecKalmaSaati"
                                        value="08:30" style="width: 120px;">
                                    <button class="btn btn-sm btn-primary" onclick="loadGecKalanlar()">
                                        <i class="bx bx-filter-alt"></i> Filtrele
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                <i class="bx bx-info-circle me-2 fs-4"></i>
                                <div>
                                    Belirtilen saatten sonra göreve başlayan veya hiç başlamayan personeller
                                    listelenmektedir.
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped dt-responsive nowrap"
                                    id="gecKalanlarTable" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Personel</th>
                                            <th class="text-center">Başlama Saati</th>
                                            <th class="text-center">Gecikme Süresi</th>
                                            <th class="text-center">Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gecKalanlarBody">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Yükleniyor...</td>
                                        </tr>
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

    .avatar-xs {
        width: 32px;
        height: 32px;
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

    .bg-soft-success {
        background-color: rgba(52, 195, 143, 0.18) !important;
    }

    .bg-soft-primary {
        background-color: rgba(85, 110, 230, 0.18) !important;
    }

    .bg-soft-warning {
        background-color: rgba(241, 180, 76, 0.18) !important;
    }

    .bg-soft-danger {
        background-color: rgba(244, 106, 106, 0.18) !important;
    }

    .bg-soft-info {
        background-color: rgba(80, 165, 241, 0.18) !important;
    }

    /* Leaflet popup styles */
    .leaflet-popup-content {
        margin: 10px;
    }

    .marker-popup {
        text-align: center;
        min-width: 150px;
    }

    .marker-popup img {
        border-radius: 50%;
        margin-bottom: 8px;
    }

    .marker-popup h6 {
        margin-bottom: 4px;
    }
</style>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    var currentPersonelId = null;
    var personelTakipDT = null;
    var haritaMap = null;
    var haritaMarkers = [];

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
        document.getElementById('raporBaslangic').value = weekAgo.toISOString().split('T')[0];
        document.getElementById('raporBitis').value = today.toISOString().split('T')[0];

        // Her 60 saniyede otomatik yenile
        setInterval(function () {
            loadOzet();
            loadPersonelDurumlari();
        }, 60000);

        // Sayfa yenilendiğinde aktif olan tabın verisini yükle
        setTimeout(function() {
            const activeTab = document.querySelector('.nav-tabs .nav-link.active');
            if (activeTab) {
                const target = activeTab.getAttribute('href');
                if (target === '#tabHarita') {
                    initHarita();
                } else if (target === '#tabRapor') {
                    loadCalismaRaporu();
                } else if (target === '#tabGecKalanlar') {
                    loadGecKalanlar();
                }
            }
        }, 300);
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
                document.getElementById('stat-gec-kalan').textContent = result.data.gec_kalan || 0;
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

            // Eğer DataTable varsa önce yok et ve DOM'u temizle
            if ($.fn.DataTable.isDataTable('#personelTakipTable')) {
                $('#personelTakipTable').DataTable().destroy();
                $('#personelTakipBody').empty();
            }

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
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Henüz kayıt bulunmuyor</td></tr>';
            }

            // DataTable'ı başlat (init fonksiyonunu kontrol et)
            var options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
            personelTakipDT = $('#personelTakipTable').DataTable(options);

            // Detay butonlarına event listener ekle
            document.querySelectorAll('.btn-detay').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const personelId = this.getAttribute('data-id');
                    showGecmis(personelId);
                });
            });
        } catch (error) {
            console.error('Personel durumları yüklenirken hata:', error);
        }
    }

    // ============ KONUM İSTEĞİ FONKSİYONU ============
    async function konumIste(personelId) {
        const result = await Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu personelden anlık konum talep edilecektir.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f46a6a',
            cancelButtonColor: '#74788d',
            confirmButtonText: 'Evet, Talep Et',
            cancelButtonText: 'İptal'
        });

        if (result.isConfirmed) {
            try {
                // UI feedback
                Swal.fire({
                    title: 'İşlem Yapılıyor...',
                    html: 'Lütfen bekleyiniz, talep iletiliyor.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });

                const response = await fetch('views/personel-takip/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=istekKonum&personel_id=' + personelId
                });
                const apiResult = await response.json();

                if (apiResult.success) {
                    Swal.fire({
                        title: 'Başarılı!',
                        text: apiResult.message || 'Konum talebi iletildi. Cihaz konumu aldığında harita güncellenecektir.',
                        icon: 'success'
                    });
                } else {
                    Swal.fire({
                        title: 'Uyarı',
                        text: apiResult.message || 'Hata oluştu.',
                        icon: 'warning'
                    });
                }
            } catch (error) {
                console.error('Konum isteği hatası:', error);
                Swal.fire({
                    title: 'Hata!',
                    text: 'İstek gönderilirken bir bağlantı hatası oluştu.',
                    icon: 'error'
                });
            }
        }
    }

    // ============ HARİTA FONKSİYONLARI ============
    // Kahramanmaraş merkez koordinatları
    var kahramanmarasLat = 37.5847;
    var kahramanmarasLng = 36.9371;

    function initHarita() {
        if (haritaMap) {
            haritaMap.invalidateSize();
            loadHaritaVerileri();
            return;
        }

        // Kahramanmaraş merkezi
        haritaMap = L.map('personelHarita').setView([kahramanmarasLat, kahramanmarasLng], 11);

        // OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(haritaMap);

        loadHaritaVerileri();
    }

    async function loadHaritaVerileri() {
        try {
            const viewType = document.getElementById('modAnlik').checked ? 'anlik' : 'gorev';

            // Tüm personelleri getir (konum olsun olmasın)
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getHaritaVerileri&tumPersoneller=1&viewType=' + viewType
            });
            const result = await response.json();

            // Mevcut markerları temizle
            haritaMarkers.forEach(m => haritaMap.removeLayer(m));
            haritaMarkers = [];

            if (result.success && result.data && result.data.length > 0) {
                var bounds = [];

                result.data.forEach(function (p) {
                    // Konum yoksa Kahramanmaraş merkez + rastgele offset
                    var lat = p.lat || (kahramanmarasLat + (Math.random() - 0.5) * 0.05);
                    var lng = p.lng || (kahramanmarasLng + (Math.random() - 0.5) * 0.05);
                    var hasLocation = p.lat && p.lng;

                    // Durum rengine göre marker
                    var markerColor = '#f46a6a'; // Varsayılan: kırmızı (başlamadı)
                    var statusIcon = 'bx-time-five';

                    if (p.durum === 'aktif') {
                        markerColor = '#34c38f'; // Yeşil
                        statusIcon = 'bx-run';
                    } else if (p.durum === 'bitti') {
                        markerColor = '#556ee6'; // Mavi
                        statusIcon = 'bx-check';
                    }

                    // Konum yoksa marker'ı farklı göster
                    var markerStyle = hasLocation
                        ? 'border: 3px solid white;'
                        : 'border: 3px dashed white; opacity: 0.7;';

                    var icon = L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background-color: ' + markerColor + '; width: 32px; height: 32px; border-radius: 50%; ' + markerStyle + ' box-shadow: 0 2px 6px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><i class="bx ' + statusIcon + '" style="color: white; font-size: 16px;"></i></div>',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });

                    var marker = L.marker([lat, lng], { icon: icon }).addTo(haritaMap);

                    // Popup içeriği
                    var fotoHtml = p.foto
                        ? '<img src="uploads/personel/' + p.foto + '" width="50" height="50" style="object-fit: cover; border-radius: 50%;">'
                        : '<div style="width:50px;height:50px;background:#556ee6;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:18px;">' + p.adi_soyadi.charAt(0) + '</div>';

                    var konumInfo = hasLocation
                        ? '<small class="text-muted">Son konum: ' + new Date(p.son_zaman || Date.now()).toLocaleTimeString('tr-TR') + '</small>'
                        : '<small class="text-warning"><i class="bx bx-error-circle"></i> Konum bilgisi yok</small>';

                    var badgeClass = p.durum === 'aktif' ? 'bg-success' : (p.durum === 'bitti' ? 'bg-primary' : 'bg-secondary');

                    marker.bindPopup(
                        '<div class="marker-popup" style="text-align:center; min-width: 150px;">' +
                        fotoHtml +
                        '<h6 class="mt-2 mb-1">' + p.adi_soyadi + '</h6>' +
                        '<span class="badge ' + badgeClass + ' mb-1">' + p.durum_text + '</span><br>' +
                        konumInfo +
                        '<div class="mt-2 pt-2 border-top">' +
                        '<button class="btn btn-sm btn-soft-danger w-100" onclick="konumIste(' + p.id + ')">' +
                        '<i class="bx bx-target-lock me-1"></i> Anlık Konum İste' +
                        '</button>' +
                        '</div>' +
                        '</div>'
                    );

                    haritaMarkers.push(marker);
                    bounds.push([lat, lng]);
                });

                // Markerlar varsa bounds'a göre zoom, yoksa Kahramanmaraş'ta kal
                if (bounds.length > 0) {
                    haritaMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
                }
            }
        } catch (error) {
            console.error('Harita verileri yüklenirken hata:', error);
        }
    }

    // ============ ÇALIŞMA RAPORU FONKSİYONLARI ============
    async function loadCalismaRaporu() {
        const baslangic = document.getElementById('raporBaslangic').value;
        const bitis = document.getElementById('raporBitis').value;

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getCalismaRaporu&baslangic=' + baslangic + '&bitis=' + bitis
            });
            const result = await response.json();

            const tbody = document.getElementById('calismaRaporuBody');

            // DataTable'ı temizle
            if ($.fn.DataTable.isDataTable('#calismaRaporuTable')) {
                $('#calismaRaporuTable').DataTable().destroy();
                $('#calismaRaporuBody').empty();
            }

            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(function (p) {
                    html += '<tr>';
                    html += '<td><strong>' + p.adi_soyadi + '</strong></td>';
                    html += '<td class="text-center">' + p.toplam_gun + ' gün</td>';
                    html += '<td class="text-center"><span class="badge bg-soft-primary text-primary">' + p.toplam_saat + ' saat</span></td>';
                    html += '<td class="text-center">' + p.ort_baslama + '</td>';
                    html += '<td class="text-center">' + p.ort_bitis + '</td>';
                    html += '<td class="text-center">' + (p.gec_kalma > 0 ? '<span class="badge bg-soft-danger text-danger">' + p.gec_kalma + ' gün</span>' : '<span class="badge bg-soft-success text-success">0</span>') + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Bu tarih aralığında veri bulunamadı</td></tr>';
            }

            // DataTable'ı başlat
            $('#calismaRaporuTable').DataTable({
                ...getDatatableOptions(), // Genel ayarları al
                order: [[2, 'desc']], // Varsayılan: Toplam saate göre sırala
                pageLength: 25,
                destroy: true // Varsa üzerine yaz
            });

        } catch (error) {
            console.error('Çalışma raporu yüklenirken hata:', error);
            document.getElementById('calismaRaporuBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Yüklenirken hata oluştu</td></tr>';
        }
    }

    function raporExcelIndir() {
        const baslangic = document.getElementById('raporBaslangic').value;
        const bitis = document.getElementById('raporBitis').value;

        // Tabloyu Excel olarak indir
        var table = document.getElementById('calismaRaporuTable');
        var csv = [];

        for (var i = 0; i < table.rows.length; i++) {
            var row = table.rows[i];
            var rowData = [];
            for (var j = 0; j < row.cells.length; j++) {
                rowData.push(row.cells[j].textContent.trim());
            }
            csv.push(rowData.join(';'));
        }

        var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'calisma_raporu_' + baslangic + '_' + bitis + '.csv';
        link.click();
    }

    // ============ GEÇ KALANLAR FONKSİYONLARI ============
    async function loadGecKalanlar() {
        const gecKalmaSaati = document.getElementById('gecKalmaSaati').value;

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getGecKalanlar&limit_saat=' + gecKalmaSaati
            });
            const result = await response.json();

            const tbody = document.getElementById('gecKalanlarBody');

            // DataTable'ı temizle
            if ($.fn.DataTable.isDataTable('#gecKalanlarTable')) {
                $('#gecKalanlarTable').DataTable().destroy();
                $('#gecKalanlarBody').empty();
            }

            // Geç kalan sayısını güncelle
            if (result.success && result.data) {
                document.getElementById('stat-gec-kalan').textContent = result.data.length;
            }

            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(function (p) {
                    html += '<tr>';
                    html += '<td><strong>' + p.adi_soyadi + '</strong></td>';
                    html += '<td class="text-center">' + p.baslama_saati + '</td>';
                    html += '<td class="text-center"><span class="badge bg-soft-danger text-danger">' + p.gecikme + '</span></td>';
                    html += '<td class="text-center">' + p.durum + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-success"><i class="bx bx-check-circle me-1"></i> Bugün geç kalan personel bulunmuyor</td></tr>';
            }

            // DataTable'ı başlat
            $('#gecKalanlarTable').DataTable({
                ...getDatatableOptions(), // Genel ayarları al
                order: [[2, 'desc']], // Varsayılan: Gecikmeye göre
                pageLength: 25,
                destroy: true
            });

        } catch (error) {
            console.error('Geç kalanlar yüklenirken hata:', error);
            document.getElementById('gecKalanlarBody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Yüklenirken hata oluştu</td></tr>';
        }
    }

    // ============ GEÇMİŞ FONKSİYONLARI ============
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
</script>

<?php // } ?>