<?php
use App\Helper\Form;

$transactions = [];

// Onaylanmış avansları ekle
if (!empty($avanslar)) {
    foreach ($avanslar as $avans) {
        if ($avans->durum === 'onaylandi') {
            $transactions[] = [
                'tarih' => $avans->onay_tarihi ?? $avans->talep_tarihi,
                'islem_turu' => 'Avans',
                'aciklama' => $avans->aciklama ?? 'Avans Ödemesi',
                'tutar' => -(float) $avans->tutar,
                'durum' => '<span class="badge bg-success">Onaylandı</span>',
                'raw_date' => $avans->onay_tarihi ?? $avans->talep_tarihi
            ];
        }
    }
}

// Kapatılmış bordro ödemelerini ekle
if (!empty($bordrolar)) {
    foreach ($bordrolar as $bordro) {
        if (isset($bordro->kapali_mi) && $bordro->kapali_mi == 1) {
            $transactions[] = [
                'id' => $bordro->id,
                'personel_id' => $bordro->personel_id,
                'tarih' => $bordro->baslangic_tarihi,
                'islem_turu' => 'Maaş Ödemesi',
                'aciklama' => ($bordro->donem_adi ?? '') . ' Maaş Ödemesi',
                'tutar' => (float) $bordro->net_maas,
                'durum' => '<span class="badge bg-primary">Ödendi</span>',
                'raw_date' => $bordro->baslangic_tarihi
            ];
        }
    }
}

// Manuel Ek Ödemeleri ekle (Tek seferlik olanlar)
if (!empty($ek_odemeler)) {
    foreach ($ek_odemeler as $ek) {
        // Otomatik oluşturulan (puantaj vb.) ek ödemeleri atla
        if (strpos($ek->aciklama ?? '', '[') === 0)
            continue;

        if (($ek->tekrar_tipi ?? '') == 'tek_sefer') {
            $status_badge = '';
            switch ($ek->durum ?? 'onaylandi') {
                case 'onaylandi':
                    $status_badge = '<span class="badge bg-success">Onaylandı</span>';
                    break;
                case 'beklemede':
                    $status_badge = '<span class="badge bg-warning">Beklemede</span>';
                    break;
                case 'reddedildi':
                    $status_badge = '<span class="badge bg-danger">Reddedildi</span>';
                    break;
            }
            $transactions[] = [
                'tarih' => $ek->created_at,
                'islem_turu' => 'Gelir',
                'aciklama' => $ek->aciklama,
                'tutar' => (float) $ek->tutar,
                'durum' => $status_badge,
                'raw_date' => $ek->created_at
            ];
        }
    }
}

// Manuel Kesintileri ekle (Tek seferlik olanlar ve avans olmayanlar)
if (!empty($kesintiler)) {
    foreach ($kesintiler as $ks) {
        // Otomatik oluşturulan (maaş ile hesaplanan) kesintileri atla
        if (strpos($ks->aciklama ?? '', '[') === 0)
            continue;

        // Avanslar zaten yukarıda ekleniyor, mükerrer olmasın diye tur != 'avans' kontrolü yapıyoruz
        if (($ks->tekrar_tipi ?? '') == 'tek_sefer' && ($ks->tur ?? '') != 'avans') {
            $status_badge = '';
            switch ($ks->durum ?? 'onaylandi') {
                case 'onaylandi':
                    $status_badge = '<span class="badge bg-success">Onaylandı</span>';
                    break;
                case 'beklemede':
                    $status_badge = '<span class="badge bg-warning">Beklemede</span>';
                    break;
                case 'reddedildi':
                    $status_badge = '<span class="badge bg-danger">Reddedildi</span>';
                    break;
            }
            $transactions[] = [
                'tarih' => $ks->olusturma_tarihi,
                'islem_turu' => 'Kesinti',
                'aciklama' => $ks->aciklama,
                'tutar' => -(float) $ks->tutar,
                'durum' => $status_badge,
                'raw_date' => $ks->olusturma_tarihi
            ];
        }
    }
}

// Tarihe göre sırala (en yeni en üstte)
usort($transactions, function ($a, $b) {
    return strtotime($b['raw_date']) - strtotime($a['raw_date']);
});
?>

<div class="row">
    <div class="col-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-lira me-2"></i>Finansal İşlemler (Avans /
                    Ödeme)</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                        data-bs-target="#modalManualGelir"><i class="bx bx-plus"></i> Gelir Ekle</button>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                        data-bs-target="#modalManualKesinti"><i class="bx bx-minus"></i> Kesinti Ekle</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th>İşlem Türü</th>
                                <th>Açıklama</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center p-3 text-muted">Henüz finansal işlem bulunmuyor.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y', strtotime($trans['tarih'])); ?></td>
                                        <td><?php echo $trans['islem_turu']; ?></td>
                                        <td><?php echo $trans['aciklama']; ?></td>
                                        <td
                                            class="<?php echo $trans['tutar'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                            <?php echo ($trans['tutar'] >= 0 ? '+ ' : '- ') . number_format(abs($trans['tutar']), 2, ',', '.') . ' ₺'; ?>
                                        </td>
                                        <td>
                                            <?php echo $trans['durum']; ?>
                                            <?php if ($trans['islem_turu'] === 'Maaş Ödemesi'): ?>
                                                <a href="views/bordro/bordro-yazdir.php?id=<?php echo $trans['id']; ?>&personel_id=<?php echo $trans['personel_id']; ?>"
                                                    target="_blank" class="btn btn-sm btn-outline-secondary ms-2"
                                                    title="Bordro Yazdır">
                                                    <i class="bx bx-printer"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gelir Ekle Modal -->
<div class="modal fade" id="modalManualGelir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Manuel Gelir Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="formManualGelir">
                <input type="hidden" name="action" value="manual-gelir-ekle">
                <input type="hidden" name="personel_id" value="<?php echo $id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= Form::FormSelect2(
                            name: "donem_id",
                            options: $acik_donemler,
                            selectedValue: array_key_first($acik_donemler) ?? '',
                            label: "Dönem",
                            icon: "calendar",
                            valueField: '',
                            textField: '',
                            required: true
                        ) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput(
                            type: "text",
                            name: "tutar",
                            value: "",
                            placeholder: "0,00",
                            label: "Tutar",
                            icon: "bx bx-lira",
                            class: "form-control money",
                            required: true
                        ) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatTextarea(
                            name: "aciklama",
                            value: "",
                            placeholder: "Açıklama giriniz",
                            label: "Açıklama",
                            icon: "message-square",
                            required: true,
                            minHeight: "100px",
                            rows: 2
                        ) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormSelect2(
                            name: "durum",
                            options: ['onaylandi' => 'Onaylı', 'beklemede' => 'Beklemede'],
                            selectedValue: "onaylandi",
                            label: "Durum",
                            icon: "check-circle",
                            valueField: '',
                            textField: '',
                            required: true
                        ) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-success">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kesinti Ekle Modal -->
<div class="modal fade" id="modalManualKesinti" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Manuel Kesinti Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="formManualKesinti">
                <input type="hidden" name="action" value="manual-kesinti-ekle">
                <input type="hidden" name="personel_id" value="<?php echo $id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <?= Form::FormSelect2(
                            name: "donem_id",
                            options: $acik_donemler,
                            selectedValue: array_key_first($acik_donemler) ?? '',
                            label: "Dönem",
                            icon: "calendar",
                            valueField: '',
                            textField: '',
                            required: true
                        ) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatInput(
                            type: "text",
                            name: "tutar",
                            value: "",
                            placeholder: "0,00",
                            label: "Tutar",
                            icon: "bx bx-lira",
                            class: "form-control money",
                            required: true
                        ) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormFloatTextarea(
                            name: "aciklama",
                            value: "",
                            placeholder: "Açıklama giriniz",
                            label: "Açıklama",
                            icon: "message-square",
                            required: true,
                            minHeight: "100px",
                            rows: 2
                        ) ?>
                    </div>
                    <div class="mb-3">
                        <?= Form::FormSelect2(
                            name: "durum",
                            options: ['onaylandi' => 'Onaylı', 'beklemede' => 'Beklemede'],
                            selectedValue: "onaylandi",
                            label: "Durum",
                            icon: "check-circle",
                            valueField: '',
                            textField: '',
                            required: true
                        ) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-danger">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Para formatı maskesi
        if (typeof Inputmask !== 'undefined') {
            $(".money").inputmask('currency', {
                rightAlign: false,
                prefix: '',
                groupSeparator: '.',
                radixPoint: ',',
                autoGroup: true,
                digits: 2,
                digitsOptional: false,
                placeholder: '0'
            });
        }

        // Form Gönderimi
        $('#formManualGelir, #formManualKesinti').on('submit', function (e) {
            e.preventDefault();
            const form = $(this);
            const formData = new FormData(this);
            const modal = bootstrap.Modal.getInstance(form.closest('.modal')[0]);

            $.ajax({
                url: 'views/personel/api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            modal.hide();
                            // Tab içeriğini yenile
                            const activeTab = $('.nav-link.active[href="#finansal_islemler"]');
                            if (activeTab.length > 0) {
                                activeTab.trigger('shown.bs.tab');
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: response.message
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Hata',
                        text: 'Bir hata oluştu.'
                    });
                }
            });
        });
    });
</script>