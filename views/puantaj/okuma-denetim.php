<?php

use App\Helper\Security;
use App\Model\OkumaDetayModel;

if (!defined('OKUMA_DENETIM_SAYFASI')) {
    define('OKUMA_DENETIM_SAYFASI', true);
}

$kaynak = $_GET['kaynak'] ?? '';
if (!in_array($kaynak, ['api', 'excel'], true)) {
    $kaynak = 'api';
}

$DosyaModel = new OkumaDetayModel();
$yuklenenDosyalar = $DosyaModel->getDosyalar($_SESSION['firma_id'] ?? 0);
$excelAralik = $DosyaModel->getTarihAraligi($_SESSION['firma_id'] ?? 0);
$excelVeriVar = $excelAralik && (int) $excelAralik->toplam > 0;
?>

<div class="container-fluid od-sayfa om-sayfa">
    <?php
    $maintitle = "Puantaj";
    $title = "Okuma Denetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <style>
        .ok-kaynak-kart { border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
        .ok-kaynak-secim { display: flex; gap: 8px; flex-wrap: wrap; }
        .ok-kaynak-dugme {
            border: 1px solid #e2e8f0; border-radius: 10px; background: #fff;
            padding: 12px 16px; text-align: left; min-width: 240px;
            display: flex; gap: 10px; align-items: flex-start;
            color: #0f172a; text-decoration: none; transition: all .15s ease;
        }
        .ok-kaynak-dugme:hover { border-color: #94a3b8; color: #0f172a; }
        .ok-kaynak-dugme.ok-secili { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 1px #2563eb inset; }
        .ok-kaynak-dugme .ok-baslik { font-weight: 700; font-size: 13px; }
        .ok-kaynak-dugme .ok-aciklama { font-size: 11px; color: #64748b; line-height: 1.35; }
        .om-birak {
            display: block; width: 100%;
            border: 2px dashed #cbd5e1; border-radius: 12px; padding: 22px;
            text-align: center; background: #f8fafc; transition: all .15s ease; cursor: pointer;
        }
        .om-birak.om-aktif { border-color: #2563eb; background: #eff6ff; }
        .om-dosya-kutu {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 10px; background: #fff; font-size: 12px;
        }
        .om-dosya-kutu.om-hatali { border-color: #fca5a5; background: #fef2f2; }
    </style>

    <div class="row mb-3">
        <div class="col-12">
            <div class="ok-kaynak-kart p-3">
                <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold mb-2">VERİ KAYNAĞI</div>
                        <div class="ok-kaynak-secim">
                            <a href="index.php?p=puantaj/okuma-denetim&kaynak=api"
                               class="ok-kaynak-dugme <?php echo $kaynak === 'api' ? 'ok-secili' : ''; ?>">
                                <i class="mdi mdi-cloud-download-outline fs-4 text-primary"></i>
                                <div>
                                    <div class="ok-baslik">API verisi</div>
                                    <div class="ok-aciklama">
                                        Gün bazlı okuma hacmi, sayaç durumu ve bölge denetimi.
                                        Otomatik sorgulamayla beslenir, saat bilgisi içermez.
                                    </div>
                                </div>
                            </a>
                            <a href="index.php?p=puantaj/okuma-denetim&kaynak=excel"
                               class="ok-kaynak-dugme <?php echo $kaynak === 'excel' ? 'ok-secili' : ''; ?>">
                                <i class="mdi mdi-file-excel-outline fs-4 text-success"></i>
                                <div>
                                    <div class="ok-baslik">
                                        Excel yükle
                                        <?php if ($excelVeriVar): ?>
                                            <span class="badge bg-success-subtle text-success ms-1">
                                                <?php echo number_format((int) $excelAralik->toplam, 0, ',', '.'); ?> okuma
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ok-aciklama">
                                        Okuma listesinden saat bazlı mesai ve şüpheli boşluk analizi.
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <?php if ($kaynak === 'excel'): ?>
                            <label for="omDosya" class="btn btn-success fw-bold shadow-sm mb-0">
                                <i class="mdi mdi-upload me-1"></i> Excel Dosyası Seç
                            </label>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($kaynak === 'excel'): ?>
                    <div class="mt-3">
                        <label class="om-birak w-100 mb-0" id="omBirak" for="omDosya">
                            <i class="mdi mdi-file-excel-outline fs-2 text-success d-block mb-1"></i>
                            <div class="fw-bold">Okuma listesi Excel dosyalarını buraya sürükleyin</div>
                            <div class="text-muted small mt-1">
                                veya tıklayarak seçin &middot; birden fazla dosya seçilebilir &middot; .xlsx, .xls, .csv
                            </div>
                        </label>
                        <input type="file" id="omDosya" multiple accept=".xlsx,.xls,.csv"
                               style="position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; z-index:-1;">

                        <div id="omYuklemeDurum" class="mt-3 d-none">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                            </div>
                            <div class="text-muted small mt-2">Dosyalar işleniyor, lütfen bekleyin...</div>
                        </div>

                        <div id="omSonucKutusu" class="mt-3 d-none"></div>

                        <?php if (!empty($yuklenenDosyalar)): ?>
                            <div class="mt-3 pt-3 border-top">
                                <div class="text-muted small fw-semibold mb-2">Yüklenen dosyalar</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($yuklenenDosyalar as $dosya): ?>
                                        <div class="om-dosya-kutu <?php echo $dosya->durum === 'hatali' ? 'om-hatali' : ''; ?>">
                                            <i class="mdi <?php echo $dosya->durum === 'hatali' ? 'mdi-alert-circle text-danger' : 'mdi-file-excel text-success'; ?>"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo Security::escape($dosya->orijinal_adi); ?></div>
                                                <div class="text-muted" style="font-size: 11px;">
                                                    <?php if ($dosya->durum === 'hatali'): ?>
                                                        <span class="text-danger"><?php echo Security::escape($dosya->hata_mesaji ?: 'Okunamadı'); ?></span>
                                                    <?php else: ?>
                                                        <?php echo number_format((int) $dosya->mevcut_satir, 0, ',', '.'); ?> satır
                                                        <?php if ($dosya->ilk_tarih): ?>
                                                            &middot; <?php echo Security::escape(\App\Helper\Date::dmY($dosya->ilk_tarih)); ?>
                                                            <?php if ($dosya->son_tarih !== $dosya->ilk_tarih): ?>
                                                                - <?php echo Security::escape(\App\Helper\Date::dmY($dosya->son_tarih)); ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ((int) $dosya->atlanan_tarih > 0): ?>
                                                            &middot; <span class="text-warning"><?php echo (int) $dosya->atlanan_tarih; ?> tarihsiz</span>
                                                        <?php endif; ?>
                                                        <?php if ((int) $dosya->atlanan_tekrar > 0): ?>
                                                            &middot; <span class="text-muted"><?php echo (int) $dosya->atlanan_tekrar; ?> tekrar</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1 om-dosya-sil"
                                                    data-id="<?php echo (int) $dosya->id; ?>"
                                                    data-ad="<?php echo Security::escape($dosya->orijinal_adi); ?>"
                                                    title="Listeden çıkar">
                                                <i class="mdi mdi-close-circle fs-5"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    if ($kaynak === 'excel') {
        include 'views/puantaj/partials/okuma-denetim-saat.php';
    } else {
        include 'views/puantaj/partials/okuma-denetim-gun.php';
    }
    ?>
</div>

<script>
$(document).ready(function () {
    var birak = $('#omBirak');
    var girdi = $('#omDosya');

    birak.on('dragover dragenter', function (e) {
        e.preventDefault(); e.stopPropagation();
        birak.addClass('om-aktif');
    });

    birak.on('dragleave dragend drop', function (e) {
        e.preventDefault(); e.stopPropagation();
        birak.removeClass('om-aktif');
    });

    birak.on('drop', function (e) {
        var dosyalar = e.originalEvent.dataTransfer.files;
        if (dosyalar && dosyalar.length) yukle(dosyalar);
    });

    $(document).on('dragover drop', function (e) {
        e.preventDefault();
    });

    if (girdi.length) {
        $(document).on('drop', function (e) {
            if ($(e.target).closest('#omBirak').length) {
                return;
            }
            var dosyalar = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
            if (dosyalar && dosyalar.length) yukle(dosyalar);
        });
    }

    girdi.on('change', function () {
        if (this.files && this.files.length) {
            yukle(this.files);
            this.value = '';
        }
    });

    function yukle(dosyalar) {
        var form = new FormData();
        form.append('action', 'excel-yukle');
        for (var i = 0; i < dosyalar.length; i++) {
            form.append('excel_dosyalari[]', dosyalar[i]);
        }

        $('#omYuklemeDurum').removeClass('d-none');
        $('#omSonucKutusu').addClass('d-none').empty();

        $.ajax({
            url: 'views/puantaj/okuma-mesai-api.php',
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (cevap) {
            $('#omYuklemeDurum').addClass('d-none');
            var html = '';
            var basarili = 0;

            (cevap.sonuclar || []).forEach(function (s) {
                if (s.durum === 'basarili') basarili++;
                html += '<div class="alert ' + (s.durum === 'basarili' ? 'alert-success' : 'alert-danger') +
                    ' border-0 py-2 px-3 mb-2 small"><strong>' + $('<div>').text(s.dosya).html() + '</strong> — ' +
                    $('<div>').text(s.mesaj).html();
                if (s.durum === 'basarili') {
                    if (s.atlanan_tarih > 0) html += ' &middot; ' + s.atlanan_tarih + ' satır tarihi okunamadığı için atlandı';
                    if (s.atlanan_tekrar > 0) html += ' &middot; ' + s.atlanan_tekrar + ' satır tekrar olduğu için atlandı';
                }
                html += '</div>';
            });

            if (cevap.status !== 'success' && cevap.message) {
                html += '<div class="alert alert-danger border-0 py-2 px-3 mb-2 small">' +
                    $('<div>').text(cevap.message).html() + '</div>';
            }

            $('#omSonucKutusu').removeClass('d-none').html(html);

            if (basarili > 0) {
                setTimeout(function () {
                    window.location.href = 'index.php?p=puantaj/okuma-denetim&kaynak=excel';
                }, 1400);
            }
        }).fail(function () {
            $('#omYuklemeDurum').addClass('d-none');
            $('#omSonucKutusu').removeClass('d-none').html(
                '<div class="alert alert-danger border-0 py-2 px-3 mb-0 small">Yükleme sırasında bir hata oluştu.</div>'
            );
        });
    }

    $('.om-dosya-sil').on('click', function (e) {
        e.stopPropagation();
        var id = $(this).data('id');
        var ad = $(this).data('ad');

        if (!confirm(ad + ' dosyası ve içindeki tüm okuma satırları analizden çıkarılacak. Onaylıyor musunuz?')) {
            return;
        }

        $.post('views/puantaj/okuma-mesai-api.php', { action: 'dosya-sil', dosya_id: id }, null, 'json')
            .done(function () { window.location.reload(); })
            .fail(function () { alert('Dosya silinemedi.'); });
    });
});
</script>
