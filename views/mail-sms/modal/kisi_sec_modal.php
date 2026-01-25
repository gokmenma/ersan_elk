<?php

require_once "../../../bootstrap.php";

use App\Model\PersonelModel;
use App\Model\RehberModel;

$type = $_GET['type'] ?? 'sms'; // 'sms' veya 'mail'

$Personel = new PersonelModel();
$personeller = $Personel->all();

$Rehber = new RehberModel();
$rehberListesi = $Rehber->all()->get();

// Filtreleme Fonksiyonu
function filterContacts($contacts, $type, $isPersonel = true)
{
    return array_filter($contacts, function ($contact) use ($type, $isPersonel) {
        if ($type === 'mail') {
            $email = $isPersonel ? ($contact->email_adresi ?? '') : ($contact->email ?? '');
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        } else {
            $phone = $isPersonel ? ($contact->cep_telefonu ?? '') : ($contact->telefon ?? '');
            return !empty($phone);
        }
    });
}

$personeller = filterContacts($personeller, $type, true);
$rehberListesi = filterContacts($rehberListesi, $type, false);

?>

<div class="modal-header">
    <h5 class="modal-title" id="kisilerdenSecModalLabel">Kişilerden Seç
        (<?php echo $type === 'mail' ? 'E-Posta' : 'SMS'; ?>)</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">

    <ul class="nav nav-tabs nav-tabs-custom nav-justified mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabPersonel" role="tab">
                <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                <span class="d-none d-sm-block">Personel Listesi</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabRehber" role="tab">
                <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                <span class="d-none d-sm-block">Rehber Listesi</span>
            </a>
        </li>
    </ul>

    <div class="tab-content text-muted">
        <!-- Personel Tab -->
        <div class="tab-pane active" id="tabPersonel" role="tabpanel">
            <div class="mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm selectAll"
                    data-target="#personelTable">Tümünü Seç</button>
                <button type="button" class="btn btn-outline-success btn-sm selectActive"
                    data-target="#personelTable">Aktifleri Seç</button>
                <button type="button" class="btn btn-outline-danger btn-sm selectPassive"
                    data-target="#personelTable">Pasifleri Seç</button>
            </div>

            <div class="table-responsive">
                <div class="fixed-header-scroll" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-striped mb-0 datatable fixed-header-table"
                        id="personelTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Seç</th>
                                <th>Adı Soyadı</th>
                                <th>Telefon</th>
                                <th>Email</th>
                                <th>Görev</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($personeller as $personel) { ?>
                                <tr class="clickable-row">
                                    <td>
                                        <input type="checkbox" class="form-check-input kisi-sec-checkbox"
                                            value="<?php echo htmlspecialchars($personel->id); ?>"
                                            data-adi-soyadi="<?php echo htmlspecialchars($personel->adi_soyadi); ?>"
                                            data-telefon="<?php echo htmlspecialchars($personel->cep_telefonu); ?>"
                                            data-email="<?php echo htmlspecialchars($personel->email_adresi); ?>">
                                    </td>
                                    <td><?php echo htmlspecialchars($personel->adi_soyadi); ?></td>
                                    <td><?php echo htmlspecialchars($personel->cep_telefonu); ?></td>
                                    <td><?php echo htmlspecialchars($personel->email_adresi); ?></td>
                                    <td><?php echo htmlspecialchars($personel->gorev ?? ''); ?></td>
                                    <td>
                                        <?php
                                        if ($personel->aktif_mi) {
                                            echo '<span class="badge bg-success">Aktif</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">Pasif</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rehber Tab -->
        <div class="tab-pane" id="tabRehber" role="tabpanel">
            <div class="mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm selectAll" data-target="#rehberTable">Tümünü
                    Seç</button>
            </div>

            <div class="table-responsive">
                <div class="fixed-header-scroll" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-striped mb-0 datatable fixed-header-table" id="rehberTable"
                        style="width:100%">
                        <thead>
                            <tr>
                                <th>Seç</th>
                                <th>Adı Soyadı</th>
                                <th>Kurum</th>
                                <th>Telefon</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rehberListesi as $rehber) { ?>
                                <tr class="clickable-row">
                                    <td>
                                        <input type="checkbox" class="form-check-input kisi-sec-checkbox"
                                            value="<?php echo htmlspecialchars($rehber->id); ?>"
                                            data-adi-soyadi="<?php echo htmlspecialchars($rehber->adi_soyadi); ?>"
                                            data-telefon="<?php echo htmlspecialchars($rehber->telefon); ?>"
                                            data-email="<?php echo htmlspecialchars($rehber->email); ?>">
                                    </td>
                                    <td><?php echo htmlspecialchars($rehber->adi_soyadi); ?></td>
                                    <td><?php echo htmlspecialchars($rehber->kurum_adi); ?></td>
                                    <td><?php echo htmlspecialchars($rehber->telefon); ?></td>
                                    <td><?php echo htmlspecialchars($rehber->email); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 border-top pt-3">
        <button type="button" id="kisilerdenSecSaveButton" class="btn btn-primary w-100">Seçimleri Ekle</button>
    </div>
</div>

<script>
    $(document).ready(function () {
        // DataTable Ayarları
        var dtConfig = {
            paging: true,
            searching: true,
            dom: '<"top"f>rt<"bottom d-flex justify-content-between"l p><"clear">',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json'
            },
            drawCallback: function () {
                $('.dataTables_paginate').css('padding', '10px 0');
            },
            pageLength: 10
        };

        var personelTable = $('#personelTable').DataTable(dtConfig);
        var rehberTable = $('#rehberTable').DataTable(dtConfig);

        // Tab değiştiğinde tabloları yeniden çiz
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            personelTable.columns.adjust().draw();
            rehberTable.columns.adjust().draw();
        });

        // Satıra tıklayınca checkbox seçimi
        $(document).on('click', '.clickable-row', function (e) {
            if (e.target.type !== 'checkbox') {
                var checkbox = $(this).find('.kisi-sec-checkbox');
                checkbox.prop('checked', !checkbox.prop('checked'));
            }
        });

        // Tümünü Seç
        $('.selectAll').on('click', function () {
            var targetId = $(this).data('target');
            var table = targetId === '#personelTable' ? personelTable : rehberTable;

            table.rows().every(function () {
                var row = $(this.node());
                row.find('.kisi-sec-checkbox').prop('checked', true);
            });
        });

        // Aktifleri Seç (Sadece Personel)
        $('.selectActive').on('click', function () {
            personelTable.rows().every(function () {
                var row = $(this.node());
                var isActive = row.find('td:last-child .badge').hasClass('bg-success');
                row.find('.kisi-sec-checkbox').prop('checked', isActive);
            });
        });

        // Pasifleri Seç (Sadece Personel)
        $('.selectPassive').on('click', function () {
            personelTable.rows().every(function () {
                var row = $(this.node());
                var isPassive = row.find('td:last-child .badge').hasClass('bg-danger');
                row.find('.kisi-sec-checkbox').prop('checked', isPassive);
            });
        });

        // Seçimleri Kaydet
        $('#kisilerdenSecSaveButton').on('click', function () {
            var selectedNumbers = [];
            var selectedEmails = [];

            function collectData(table) {
                table.rows().every(function () {
                    var row = $(this.node());
                    var checkbox = row.find('.kisi-sec-checkbox');
                    if (checkbox.prop('checked')) {
                        var tel = checkbox.data('telefon');
                        var email = checkbox.data('email');

                        if (tel) {
                            var cleaned = String(tel).replace(/\D/g, '');
                            if (cleaned.length === 11 && cleaned.startsWith('0')) {
                                cleaned = cleaned.substring(1);
                            }
                            if (cleaned && !selectedNumbers.includes(cleaned)) {
                                selectedNumbers.push(cleaned);
                            }
                        }

                        if (email && !selectedEmails.includes(email)) {
                            selectedEmails.push(email);
                        }
                    }
                });
            }

            collectData(personelTable);
            collectData(rehberTable);

            // Ana pencereye gönder
            window.parent.postMessage({
                type: 'addRecipients',
                numbers: selectedNumbers,
                emails: selectedEmails
            }, '*');

            // Modalı kapat
            if (window.parent.$) {
                window.parent.$('#kisilerdenSecModal').modal('hide');
            }
        });
    });
</script>