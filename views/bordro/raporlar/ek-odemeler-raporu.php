<?php
/**
 * Ek Ödemeler Raporu
 * Personel ek ödemelerini dönem bazlı listeler ve filtreler.
 */

use App\Model\BordroDonemModel;
use App\Model\PersonelEkOdemelerModel;
use App\Model\BordroParametreModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$EkOdemelerModel = new PersonelEkOdemelerModel();
$BordroParametreModel = new BordroParametreModel();

// Filtre parametreleri
$selectedDonemId = $_GET['donem'] ?? $_SESSION['selectedDonemId'] ?? null;
$selectedTur = $_GET['tur'] ?? '';

$selectedDonem = null;
$ekOdemeler = [];
$toplamTutar = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $ekOdemeler = $EkOdemelerModel->getDonemEkOdemelerRaporu($selectedDonemId, $selectedTur);
        foreach ($ekOdemeler as $k) {
            $toplamTutar += floatval($k->tutar ?? 0);
        }
    }
}

// Yılları ve dönemleri getir (dönem seçimi için)
$selectedYil = $selectedDonem ? date('Y', strtotime($selectedDonem->baslangic_tarihi)) : date('Y');
$donemler = $BordroDonem->getAllDonems($selectedYil);
$yil_option = $BordroDonem->getYearsByDonem();

$donem_option = [];
foreach ($donemler as $donem) {
    $donem_option[$donem->id] = $donem->donem_adi;
}

// Gelir türlerini al (Filtre için)
$gelir_turleri = $BordroParametreModel->getGelirTurleri();
$tur_option = ['' => 'Tüm Ek Ödemeler'];
foreach ($gelir_turleri as $tur) {
    $tur_option[$tur->kod] = $tur->etiket;
}

$kayitSayisi = count($ekOdemeler);
?>

<div class="container-fluid">
    <?php
    $maintitle = "Bordro";
    $subtitle = "Raporlar";
    $title = "Ek Ödemeler Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom bordro-sticky-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <!-- Left side: Filters -->
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                            <div style="min-width: 160px;">
                                <?php echo Form::FormSelect2(
                                    name: 'turSelectEkOdeme',
                                    options: $tur_option,
                                    selectedValue: $selectedTur,
                                    label: 'Ek Ödeme Türü',
                                    icon: 'filter',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <div style="min-width: 100px;">
                                <?php echo Form::FormSelect2(
                                    name: 'yilSelectEkOdeme',
                                    options: $yil_option,
                                    selectedValue: $selectedYil,
                                    label: 'Yıl',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <div style="min-width: 160px;">
                                <?php echo Form::FormSelect2(
                                    name: 'donemSelectEkOdeme',
                                    options: $donem_option,
                                    selectedValue: $selectedDonemId,
                                    label: 'Dönem',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                        </div>

                        <!-- Right side: Buttons -->
                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <button type="button" class="btn btn-success shadow-sm text-nowrap" id="btnExportCustomExcelEkOdeme">
                                <i class="bx bx-file me-1"></i> Excel'e Aktar
                            </button>
                            <a href="index?p=bordro/raporlar&donem=<?= $selectedDonemId ?>" class="btn btn-secondary shadow-sm text-nowrap">
                                <i class="bx bx-arrow-back me-1"></i> Raporlara Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($selectedDonem && !empty($ekOdemeler)): ?>

                        <!-- Özet Kartları -->
                        <!-- <div class="row g-3 mb-4 d-flex justify-content-end"> -->
                            <!-- TOPLAM -->
                            <!-- <div class="col-lg-3 col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-money fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TUTAR</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM EK ÖDEME</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading text-warning">
                                            <?php //number_format($toplamTutar, 2, ',', '.') ?> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div> -->
                            
                            <!-- ADET -->
                            <!-- <div class="col-lg-2 col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #64748b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(100, 116, 139, 0.1);">
                                                <i class="bx bx-list-ul fs-4 text-secondary"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">ADET</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">KAYIT SAYISI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading text-secondary">
                                            <?php //number_format($kayitSayisi, 0, ',', '.') ;?> <span style="font-size: 0.85rem; font-weight: 600;">Kişi</span>
                                        </h4>
                                    </div>
                                </div>
                            </div> -->
                        <!-- </div> -->

                        <!-- Tablo -->
                        <div class="table-responsive">
                            <table id="ekOdemelerRaporuTable" class="table table-hover table-bordered nowrap w-100 align-middle datatable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>Personel Bilgileri</th>
                                        <th>Departman</th>
                                        <th>Ödeme Türü</th>
                                        <th>Açıklama / Detay</th>
                                        <th>Hesaplama</th>
                                        <th class="text-end">Tutar / Oran</th>
                                        <th class="text-center">Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sira = 1;
                                    foreach ($ekOdemeler as $k):
                                        // Tür Rozeti Ayarı
                                        $turAdi = $k->parametre_adi ?? ucfirst($k->tur ?? 'Diğer');
                                        
                                        // Durum Rozeti Ayarı
                                        $durumBg = 'bg-secondary';
                                        $durumIcon = 'bx-time';
                                        $durumMetin = 'Beklemede';
                                        if ($k->durum === 'onaylandi') {
                                            $durumBg = 'bg-success';
                                            $durumIcon = 'bx-check-circle';
                                            $durumMetin = 'Onaylandı';
                                        } elseif ($k->durum === 'reddedildi') {
                                            $durumBg = 'bg-danger';
                                            $durumIcon = 'bx-x-circle';
                                            $durumMetin = 'Reddedildi';
                                        }
                                        ?>
                                        <tr class="<?= $k->durum === 'reddedildi' ? 'text-muted' : '' ?>">
                                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($k->adi_soyadi ?? '-') ?></h6>
                                                        <span class="text-muted small">TC: <code><?= htmlspecialchars($k->tc_kimlik_no ?? '-') ?></code></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($k->departman ?? '-') ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-primary text-white fs-6 px-2 py-1 shadow-sm"><?= htmlspecialchars($turAdi) ?></span>
                                            </td>
                                            <td>
                                                <div class="text-wrap" style="max-width:300px;">
                                                    <span class="fw-medium text-dark d-block">
                                                        <?= htmlspecialchars($k->aciklama ?? '-') ?>
                                                    </span>
                                                    <?php if (($k->tekrar_tipi ?? 'tek_sefer') == 'surekli'): ?>
                                                        <span class="text-muted small d-block mt-1">
                                                            <i class="bx bx-refresh me-1"></i>Sürekli Ödeme
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $hesaplama_labels = [
                                                    'sabit' => '<i class="bx bx-money"></i> Sabit',
                                                    'oran_net' => '<i class="bx bx-percent"></i> Net %',
                                                    'oran_brut' => '<i class="bx bx-percent"></i> Brüt %'
                                                ];
                                                echo $hesaplama_labels[$k->hesaplama_tipi ?? 'sabit'] ?? 'Sabit Tutar';
                                                ?>
                                            </td>
                                            <td class="text-end fw-bold text-success" style="font-size: 1.05rem;">
                                                <span class="<?= $k->durum === 'reddedildi' ? 'text-decoration-line-through' : '' ?>">
                                                    <?php if (($k->hesaplama_tipi ?? 'sabit') == 'sabit'): ?>
                                                        <?= number_format(floatval($k->tutar ?? 0), 2, ',', '.') ?> ₺
                                                    <?php else: ?>
                                                        %<?= number_format(floatval($k->oran ?? 0), 2, ',', '.') ?>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $durumBg ?> px-2 py-1 fs-6">
                                                    <i class="bx <?= $durumIcon ?> me-1 align-middle"></i><?= $durumMetin ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="6" class="text-end font-size-14 fw-bold">Toplam Ek Ödeme Tutarı (Yalnızca Sabit Tutarlar):</th>
                                        <th class="text-end text-success font-size-16 fw-bold">
                                            <?= number_format($toplamTutar, 2, ',', '.') ?> ₺
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php elseif ($selectedDonem): ?>
                        <div class="text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                                    <i class="bx bx-pie-chart-alt-2"></i>
                                </div>
                            </div>
                            <h5 class="mt-3 text-secondary">Personel Bulunamadı</h5>
                            <p class="text-muted">Bu dönemde / kriterde henüz oluşturulmuş ek ödeme kaydı bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                                    <i class="bx bx-calendar-x"></i>
                                </div>
                            </div>
                            <h5 class="mt-3 text-secondary">Dönem Seçimi Bekleniyor</h5>
                            <p class="text-muted">Ek ödemeler raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Yeni bir Excel Aktar fonksiyonu
        $('#btnExportCustomExcelEkOdeme').on('click', function() {
            var table = $('#ekOdemelerRaporuTable').DataTable();
            
            // Eğer tabloda veri yoksa uyar
            if (!table.data().any()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Uyarı',
                    text: 'Dışa aktarılacak veri bulunamadı.',
                    confirmButtonText: 'Tamam'
                });
                return;
            }

            // Sadece filtrelenmiş ("search: applied") satırları alalım
            var rowsData = table.rows({ filter: 'applied' }).data();
            var rowsNodes = table.rows({ filter: 'applied' }).nodes();
            
            // 1) HTML stringi olarak bir tablo başlatalım
            // <meta charset="utf-8"> ile Excel'de Türkçe karakterlerin düzgün görünmesini sağlıyoruz.
            var html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
            html += '<head><meta charset="utf-8"></head>';
            html += '<body>';
            html += '<table border="1">';
            
            // 2) Başlıkları Ekleyelim (Durum kısmı hariç isterseniz çıkarabilirsiniz)
            html += '<tr>';
            html += '<th>SNO</th>';
            html += '<th>TC Kimlik No</th>';
            html += '<th>Ad Soyad</th>';
            html += '<th>Departman</th>';
            html += '<th>Ödeme Türü</th>';
            html += '<th>Açıklama Detay</th>';
            html += '<th>Hesaplama</th>';
            html += '<th>Tutar / Oran</th>';
            html += '<th>Durum</th>';
            html += '</tr>';

            // 3) Satırları dönerek verileri HTML tablo formatına basalım
            var sira = 1;
            table.rows({ filter: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
                var node = this.node();
                var tc = $(node).find('td:eq(1) code').text().trim();
                var adSoyad = $(node).find('td:eq(1) h6').text().trim();
                var departman = $(node).find('td:eq(2)').text().trim();
                var tur = $(node).find('td:eq(3)').text().trim();
                var aciklama = $(node).find('td:eq(4) .fw-medium').text().trim();
                var hesaplama = $(node).find('td:eq(5)').text().trim();
                var tutarStr = $(node).find('td:eq(6)').text().trim();
                var durum = $(node).find('td:eq(7)').text().trim();
                
                html += '<tr>';
                html += '<td>' + sira++ + '</td>';
                // mso-number-format:"\@"; ile TC kimlik numaralarının Excel'de bilimsel (1E+10 gibi) görünmesini engelliyoruz
                html += '<td style="mso-number-format:\'@\';">' + tc + '</td>';
                html += '<td>' + adSoyad + '</td>';
                html += '<td>' + departman + '</td>';
                html += '<td>' + tur + '</td>';
                html += '<td>' + aciklama + '</td>';
                html += '<td>' + hesaplama + '</td>';
                html += '<td>' + tutarStr + '</td>';
                html += '<td>' + durum + '</td>';
                html += '</tr>';
            });
            
            html += '</table>';
            html += '</body>';
            html += '</html>';

            // 4) Blob ve ahref download işlemi
            var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            var url = window.URL.createObjectURL(blob);
            
            var a = document.createElement('a');
            a.href = url;
            a.download = 'Ek_Odemeler_Raporu_<?= $selectedDonem ? htmlspecialchars($selectedDonem->donem_adi) : date("Y_m_d") ?>.xls';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        });

        // Event listener for filters
        function applyFilters() {
            var yil = $('[name="yilSelectEkOdeme"]').val();
            var donem = $('[name="donemSelectEkOdeme"]').val();
            var tur = $('[name="turSelectEkOdeme"]').val();

            var url = 'index?p=bordro/raporlar/ek-odemeler-raporu&donem=' + donem + '&yil=' + yil + '&tur=' + tur;
            window.location.href = url;
        }

        $('[name="yilSelectEkOdeme"]').on('change', applyFilters);
        $('[name="donemSelectEkOdeme"]').on('change', applyFilters);
        $('[name="turSelectEkOdeme"]').on('change', applyFilters);
    });
</script>
