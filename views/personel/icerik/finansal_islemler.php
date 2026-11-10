<?php
use App\Helper\Form;

$transactions = [];
$BordroPersonelModel = new \App\Model\BordroPersonelModel();
$bordroDetayYetkisi = \App\Service\Gate::allows('personel_detay') || \App\Service\Gate::allows('personel_duzenle');
$bordroYazdirYetkisi = \App\Service\Gate::allows('bordro/raporlar') || $bordroDetayYetkisi;

if (!empty($bordrolar)) {
    foreach ($bordrolar as $bordro) {
        if (isset($bordro->kapali_mi) && $bordro->kapali_mi == 1) {
            $ozet = null;
            try {
                $ozet = $BordroPersonelModel->getResmiAlacakOzeti((int) $bordro->id);
            } catch (\Throwable $e) {
                error_log('Hesap hareketleri resmi alacak: ' . $e->getMessage());
            }
            $transactions[] = [
                'id' => $bordro->id,
                'personel_id' => $bordro->personel_id,
                'donem_id' => $bordro->donem_id,
                'tarih' => $bordro->baslangic_tarihi,
                'islem_turu' => 'Maaş Ödemesi',
                'aciklama' => ($bordro->donem_adi ?? '') . ' Maaş Ödemesi',
                'resmi_alacak' => $ozet['resmi_alacak'] ?? null,
                'tutar' => (float) $bordro->net_maas,
                'durum' => '<span class="badge bg-primary">Ödendi</span>',
                'raw_date' => $bordro->baslangic_tarihi
            ];
        }
    }
}

usort($transactions, function ($a, $b) {
    return strtotime($b['raw_date']) - strtotime($a['raw_date']);
});
?>

<div class="row">
    <div class="col-12">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-xs d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-2" style="width:34px;height:34px;font-size:18px;flex-shrink:0;">
                        <i class="bx bx-lira"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 text-dark fw-bold">Hesap Hareketleri (Maaş Ödemeleri)</h5>
                        <div class="small text-muted">Kapanmış dönemlerin maaş ödemeleri, resmî alacak ve bordro detayı.</div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                        data-bs-target="#modalManualGelir"><i class="bx bx-plus"></i> Gelir Ekle</button>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                        data-bs-target="#modalManualKesinti"><i class="bx bx-minus"></i> Kesinti Ekle</button>
                </div>
            </div>
            <div class="card-body p-1">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 datatable w-100">
                        <thead class="table-light">
                            <tr>
                                <th data-filter="date">Tarih</th>
                                <th data-filter="select">İşlem Türü</th>
                                <th data-filter="string">Açıklama</th>
                                <th data-filter="number">Resmî Alacak</th>
                                <th data-filter="number">Net Ödenen</th>
                                <th data-filter="select">Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $trans): ?>
                                <?php $bordroToken = htmlspecialchars(\App\Helper\Security::encrypt($trans['id']), ENT_QUOTES, 'UTF-8'); ?>
                                <tr>
                                    <td data-order="<?= htmlspecialchars(date('Y-m-d', strtotime($trans['tarih'])), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('d.m.Y', strtotime($trans['tarih'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($trans['islem_turu'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($trans['aciklama'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="fw-bold text-primary" data-order="<?= $trans['resmi_alacak'] !== null ? htmlspecialchars((string) $trans['resmi_alacak'], ENT_QUOTES, 'UTF-8') : '0' ?>">
                                        <?= $trans['resmi_alacak'] !== null ? number_format($trans['resmi_alacak'], 2, ',', '.') . ' ₺' : '<span class="text-muted fw-normal">Hesaplanmadı</span>' ?>
                                    </td>
                                    <td class="text-success fw-bold" data-order="<?= htmlspecialchars((string) $trans['tutar'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= number_format($trans['tutar'], 2, ',', '.') . ' ₺' ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?= $trans['durum'] ?>
                                        <?php if ($bordroDetayYetkisi): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary ms-2 btn-bordro-detay-ac"
                                                data-bordro="<?= $bordroToken ?>" title="Bordro Detayı">
                                                <i class="bx bx-show"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($bordroYazdirYetkisi): ?>
                                            <a href="views/bordro/bordro-yazdir.php?donem=<?= htmlspecialchars(\App\Helper\Security::encrypt($trans['donem_id']), ENT_QUOTES, 'UTF-8') ?>&amp;id=<?= $bordroToken ?>"
                                                target="_blank" class="btn btn-sm btn-outline-secondary ms-1"
                                                title="Bordro Yazdır">
                                                <i class="bx bx-printer"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPersonelBordroDetay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-show me-2"></i>Bordro Detayı</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="personelBordroDetayIcerik"></div>
            <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap">
                <div id="personelBordroDetayOzet" class="d-flex flex-wrap align-items-center gap-2 text-start"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
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


        const personelIdBordro = <?= (int) $id ?>;
        const kpiEtiket = function (label, value, cls, popover) {
            const pop = popover ? ' data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true" data-bs-placement="top" data-bs-custom-class="bordro-dark-popover" data-bs-content="' + String(popover).replace(/"/g, '&quot;') + '"' : '';
            return '<div class="d-flex flex-column rounded px-3 py-2 text-center border bg-light" style="min-width:120px;"' + pop + '><div class="small text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;font-weight:700;">' + label + '</div><div class="fw-bold ' + cls + '">' + value + '</div></div>';
        };
        $(document).off('click.bordroDetay').on('click.bordroDetay', '.btn-bordro-detay-ac', function () {
            const token = $(this).data('bordro');
            const modalEl = document.getElementById('modalPersonelBordroDetay');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            $('#personelBordroDetayIcerik').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
            $('#personelBordroDetayOzet').empty();
            modal.show();
            $.ajax({
                url: 'views/personel/api.php',
                type: 'POST',
                data: { action: 'bordro-detay', personel_id: personelIdBordro, bordro_id: token },
                dataType: 'json',
                success: function (r) {
                    if (r.status !== 'success') {
                        $('#personelBordroDetayIcerik').html($('<div class="alert alert-danger text-center m-3"></div>').text(r.message || 'Detay getirilemedi.'));
                        return;
                    }
                    $('#personelBordroDetayIcerik').html(r.html);
                    const o = r.summary || {};
                    let f = kpiEtiket('Brüt Toplamı', o.brut_toplam, 'text-dark', o.brut_toplam_detay)
                        + kpiEtiket('Kesintiler', o.kesinti_toplam, 'text-danger')
                        + kpiEtiket('Net Maaş', o.net_maas, 'text-success');
                    if (o.banka) f += kpiEtiket('Banka', o.banka, 'text-primary', o.banka_detay);
                    if (o.elden) f += kpiEtiket('Elden', o.elden, 'text-warning');
                    if (o.sodexo) f += kpiEtiket('Sodexo', o.sodexo, 'text-success');
                    if (o.diger) f += kpiEtiket('Diğer', o.diger, 'text-secondary');
                    $('#personelBordroDetayOzet').html(f);
                    document.querySelectorAll('#personelBordroDetayOzet [data-bs-toggle="popover"]').forEach(function (el) {
                        new bootstrap.Popover(el, { container: '#modalPersonelBordroDetay', customClass: 'bordro-dark-popover', sanitize: false });
                    });
                },
                error: function () {
                    $('#personelBordroDetayIcerik').html('<div class="alert alert-danger text-center m-3">Detaylar yüklenirken bir hata oluştu.</div>');
                }
            });
        });
        $(document).off('click.bordroPanel').on('click.bordroPanel', '#personelBordroDetayIcerik .calculation-toggle', function (e) {
            e.preventDefault();
            const t = document.querySelector($(this).attr('data-collapse-target'));
            if (!t || t.classList.contains('collapsing')) return;
            const c = bootstrap.Collapse.getOrCreateInstance(t, { toggle: false });
            const open = t.classList.contains('show');
            open ? c.hide() : c.show();
            $(this).attr('aria-expanded', open ? 'false' : 'true');
        });

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
                            if (typeof window.reloadActiveTab === 'function') {
                                window.reloadActiveTab();
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