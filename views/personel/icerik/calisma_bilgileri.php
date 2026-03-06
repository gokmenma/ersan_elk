<?php
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;

/** Ekip Geçmişini Getir */
$gecmis = $id > 0 ? $PersonelModel->getEkipGecmisi($id) : [];
$ekip_kodlari_raw = $TanimlamalarModel->getMusaitEkipKodlari();

/** Ekip Bölgeleri */
$ekip_bolgeleri_raw = $TanimlamalarModel->getEkipBolgeleri();
$ekip_bolge_options = ['' => 'Tüm Bölgeler'];
foreach ($ekip_bolgeleri_raw as $bolge) {
    if ($bolge)
        $ekip_bolge_options[$bolge] = $bolge;
}
?>

<div class="row">
    <!-- Sol Kolon: Pozisyon ve Durum -->
    <div class="col-md-12 mb-3">
        <div class="card border h-100 mb-2">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-briefcase me-2"></i>Pozisyon & Durum</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <?php echo Form::FormFloatInput("text", "ise_giris_tarihi", Date::dmY($personel->ise_giris_tarihi ?? Date::today()), "İşe Giriş", "İşe Giriş Tarihi", "calendar", "form-control flatpickr"); ?>
                    </div>
                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormFloatInput("text", "isten_cikis_tarihi", Date::dmY($personel->isten_cikis_tarihi ?? null), "İşten Çıkış", "İşten Çıkış Tarihi", "calendar", "form-control flatpickr"); ?>
                    </div>

                    <div class="col-md-2 mb-2" id="isten_ayrilis_belge_container" style="display:none;">
                        <div class="d-flex align-items-center gap-1">
                            <div class="flex-grow-1">
                                <?php echo Form::FormFileInput("isten_ayrilis_belge_yolu", "İşten Ayrılış Belgesi", "file", "form-control"); ?>
                            </div>
                            <?php if (!empty($personel->isten_ayrilis_belge_yolu)): ?>
                                <a href="<?= htmlspecialchars($personel->isten_ayrilis_belge_yolu) ?>" target="_blank" class="btn btn-outline-primary p-0" title="Mevcut Belgeyi Görüntüle" style="height: 58px; width: 58px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="bx bx-download fs-4"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormSelect2("personel_sinifi", ['Beyaz Yaka' => 'Beyaz Yaka', 'Mavi Yaka' => 'Mavi Yaka'], $personel->personel_sinifi ?? '', "Sınıf", "users"); ?>
                    </div>
                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormSelect2("saha_takibi", ['1' => 'Evet', '0' => 'Hayır'], $personel->saha_takibi ?? '0', "Saha Takibi", "map-pin"); ?>
                    </div>
                    <div class="col-md-2 mb-2">
                        <?php echo Form::FormSelect2("arac_kullanim", ['Yok' => 'Yok', 'Kendi Aracı' => 'Kendi Aracı', 'Şirket aracı' => 'Şirket aracı'], $personel->arac_kullanim ?? 'Yok', "Araç Kullanım", "truck"); ?>
                    </div>
                    <div class="col-md-2 mb-2">
                        <?php

                        $firma_adi = $FirmaModel->find($_SESSION['firma_id'])->firma_adi;
                        $firma_option = [
                            $firma_adi => $firma_adi,
                            "İŞKUR" => "İŞKUR",
                            "Dışarıdan Sigortalı" => "Dışarıdan Sigortalı"
                        ];


                        echo Form::FormSelect2("sgk_yapilan_firma", $firma_option, $personel->sgk_yapilan_firma ?? 'Yok', "SGK Yapılan Firma", "book-open"); ?>

                    </div>
                </div>

                <div class="row mt-2" id="gorunum_modulleri_row"
                    style="<?= ($personel->sgk_yapilan_firma ?? '') === 'Dışarıdan Sigortalı' ? '' : 'display:none;' ?>">
                    <div class="col-md-12 mb-2">
                        <?php
                        $modul_options = [
                            'bordro' => 'Bordro',
                            'personel' => 'Personel Listesi',
                            'puantaj' => 'Puantaj',
                            'nobet' => 'Nöbet',
                            'demirbas' => 'Demirbaş',
                            'arac' => 'Araç Takip',
                            'evrak' => 'Evrak Takip',
                            'mail' => 'Mail/SMS',
                            'takip' => 'Personel Takip',
                            'dashboard' => 'Dashboard'
                        ];
                        // Varsayılan olarak ikisi her zaman olsun
                        $selected_modules = !empty($personel->gorunum_modulleri) ? explode(',', $personel->gorunum_modulleri) : ['bordro', 'personel'];
                        if (!in_array('bordro', $selected_modules))
                            $selected_modules[] = 'bordro';
                        if (!in_array('personel', $selected_modules))
                            $selected_modules[] = 'personel';

                        echo Form::FormMultipleSelect2("gorunum_modulleri", $modul_options, $selected_modules, "Dışarıdan Sigortalı Olduğu İçin Görüneceği Modüller", "eye");
                        ?>
                        <small class="text-muted"><i class="bx bx-info-circle"></i> SGK yapılan firma ana firma değilse
                            personelin hangi modüllerde görüneceğini seçebilirsiniz. <strong class="text-danger">Bordro
                                ve Personel Listesi görünümü zorunludur.</strong></small>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var sgkSelect = document.getElementById('sgk_yapilan_firma');
                        var wrapper = document.getElementById('gorunum_modulleri_row');
                        var firmaAdi = "<?= addslashes($firma_adi) ?>";

                        function toggleGorunum() {
                            var val = sgkSelect.value;
                            if (val === 'Dışarıdan Sigortalı') {
                                wrapper.style.display = '';
                            } else {
                                wrapper.style.display = 'none';
                            }
                        }

                        if (sgkSelect) {
                            sgkSelect.addEventListener('change', toggleGorunum);
                            if (typeof $ !== 'undefined' && $(sgkSelect).data('select2')) {
                                $(sgkSelect).on('select2:select', toggleGorunum);
                                $(sgkSelect).on('select2:unselect', toggleGorunum);
                            }
                        }

                        // İşten çıkış tarihi kontrolü
                        var txtIstenCikis = document.getElementById('isten_cikis_tarihi');
                        var containerBelge = document.getElementById('isten_ayrilis_belge_container');
                        var inputFile = document.getElementById('isten_ayrilis_belge_yolu');
                        var hasExistingFile = <?= !empty($personel->isten_ayrilis_belge_yolu) ? 'true' : 'false' ?>;

                        function checkIstenCikis() {
                            if (!txtIstenCikis) return;
                            
                            if (txtIstenCikis.value.trim() !== '') {
                                // Tarih dolu
                                containerBelge.style.display = '';
                                if (!hasExistingFile && inputFile) {
                                    inputFile.setAttribute('required', 'required');
                                } else if (inputFile) {
                                    inputFile.removeAttribute('required');
                                }
                            } else {
                                // Tarih boş
                                containerBelge.style.display = 'none';
                                if (inputFile) {
                                    inputFile.removeAttribute('required');
                                    inputFile.value = ''; // Seçili dosyayı temizle
                                }
                            }
                        }

                        if (txtIstenCikis) {
                            txtIstenCikis.addEventListener('change', checkIstenCikis);
                            txtIstenCikis.addEventListener('keyup', checkIstenCikis);
                            txtIstenCikis.addEventListener('input', checkIstenCikis);
                            
                            // Flatpickr initialize edilmişse eventlerine ekle
                            setTimeout(function() {
                                if (txtIstenCikis._flatpickr) {
                                    txtIstenCikis._flatpickr.config.onChange.push(checkIstenCikis);
                                    txtIstenCikis._flatpickr.config.onClear.push(checkIstenCikis);
                                }
                            }, 1000);
                            
                            // Duruma göre garantiye almak için periyodik kontrol kullanabiliriz
                            setInterval(checkIstenCikis, 500);
                            
                            // İlk yükleme çalıştır
                            checkIstenCikis();
                        }

                        // Select2'de 'bordro' ve 'personel' seçimlerini zorunlu yap
                        setTimeout(function () {
                            var $gm = $('#gorunum_modulleri');
                            if ($gm.length) {
                                $gm.on('select2:unselecting', function (e) {
                                    if (e.params.args.data.id === 'bordro' || e.params.args.data.id === 'personel') {
                                        e.preventDefault();
                                        toastr.warning(e.params.args.data.text + ' modülünde görünüm zorunludur.', 'Uyarı');
                                    }
                                });
                            }
                        }, 500);

                    });
                </script>



            </div>
        </div>
    </div>

    <!-- Sağ Kolon: Ekip Bilgileri -->
    <div class="col-md-12">
        <div class="card border h-100">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-group me-2"></i>Ekip Kodu İşlemleri</h5>
                <?php if ($id > 0): ?>
                    <button type="button" class="btn btn-sm btn-primary" id="btnOpenEkipGecmisiModal">
                        <i class="bx bx-plus"></i> Yeni Ekip Tanımla
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($id > 0): ?>
                    <div class="table-responsive">
                        <table id="tblEkipGecmisi" class="table table-hover mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Ekip Adı / Kodu</th>
                                    <th>Başlangıç Tarihi</th>
                                    <th>Bitiş Tarihi</th>
                                    <th>Ekip Şefi</th>
                                    <th>Durum</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($gecmis)): ?>
                                    <?php foreach ($gecmis as $g): ?>
                                        <tr>
                                            <td><span class="fw-bold text-dark"><?= htmlspecialchars($g->ekip_adi ?? '') ?></span>
                                            </td>
                                            <td><?= date('d.m.Y', strtotime($g->baslangic_tarihi)) ?></td>
                                            <td><?= $g->bitis_tarihi ? date('d.m.Y', strtotime($g->bitis_tarihi)) : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>' ?>
                                            </td>
                                            <td>
                                                <?= isset($g->ekip_sefi_mi) && $g->ekip_sefi_mi == 1 ? '<span class="badge bg-success">Evet</span>' : '<span class="badge bg-secondary">Hayır</span>' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $bugun = date('Y-m-d');
                                                if ($g->baslangic_tarihi <= $bugun && ($g->bitis_tarihi === null || $g->bitis_tarihi >= $bugun)) {
                                                    echo '<span class="badge bg-success">Aktif</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">Pasif</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <button type="button"
                                                    class="btn btn-sm btn-soft-primary btn-ekip-gecmisi-duzenle me-1"
                                                    data-id="<?= $g->id ?>" title="Düzenle">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-ekip-gecmisi-sil"
                                                    data-id="<?= $g->id ?>" title="Sil">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bx bx-info-circle fs-2 mb-2 d-block"></i>
                        Yeni personel eklerken önce personeli kaydedin, ardından ekip tanımlaması yapabilirsiniz.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>