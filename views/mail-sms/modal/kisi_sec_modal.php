<?php

require_once "../../../vendor/autoload.php";

use App\Model\UyeModel;

$Uye = new UyeModel();

$uyeler = $Uye->getAllMembers();


?>


<div class="modal-header">
    <h5 class="modal-title" id="kisilerdenSecModalLabel">Kişilerden Seç</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="mb-3">
        <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllMembers">Tümünü Seç</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="selectActiveMembers">Aktif Üyeleri Seç</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="selectPassiveMembers">Pasif Üyeleri Seç</button>
    <button type="button" id="kisilerdenSecSaveButton"
        class="btn btn-primary ms-auto float-end">Seçimleri
        Ekle</button>
    </div>
    
    <style>
        .fixed-header-table {
            width: 100%;
            border-collapse: separate;
        }
        .fixed-header-table thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 2;
        }
        .fixed-header-scroll {
            max-height: calc(100vh - 300px);
            overflow-y: auto;
            width: 100%;
        }
    </style>
    <div class="table-responsive">
        <div class="fixed-header-scroll">
            <table class="table table-bordered table-striped mb-0 datatable fixed-header-table" id="kisilerTable">
                <thead>
                    <tr>
                        <th>Seç</th>
                        <th>Üye No</th>
                        <th>Adı Soyadı</th>
                        <th>Telefon</th>
                        <th>Email</th>
                        <th>Üyelik Durumu</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- JS ile yüklenecek -->
                    <?php foreach ($uyeler as $uye) { ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input kisi-sec-checkbox"
                                    value="<?php echo htmlspecialchars($uye->id); ?>"
                                    data-adi-soyadi="<?php echo htmlspecialchars($uye->adi_soyadi); ?>"
                                    data-telefon="<?php echo htmlspecialchars($uye->telefon); ?>"
                                    data-email="<?php echo htmlspecialchars($uye->email); ?>">
                            </td>
                            <td><?php echo htmlspecialchars($uye->uye_no); ?></td>
                            <td><?php echo htmlspecialchars($uye->adi_soyadi); ?></td>
                            <td><?php echo htmlspecialchars($uye->telefon); ?></td>
                            <td><?php echo htmlspecialchars($uye->email); ?></td>
                            <td>
                                <?php
                                if (is_null($uye->istifa_tarihi)) {
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
<script>
    $(document).ready(function() {
        var kisilerTable = $('#kisilerTable').DataTable({
            paging: true,
            searching: true,
            dom: '<"top"f>rt<"bottom d-flex justify-content-between"l p><"clear">',
            drawCallback: function() {
                // Paging kısmına padding ekle
                $('#kisilerTable_paginate').css('padding', '10px 0');
            },
            order: [
                [1, 'desc']
            ]
        });

        // Tümünü Seç (tüm sayfalar)
        $('#selectAllMembers').on('click', function() {
            kisilerTable.rows().every(function() {
                var row = $(this.node());
                row.find('.kisi-sec-checkbox').prop('checked', true);
            });
        });
        // Aktif Üyeleri Seç (tüm sayfalar)
        $('#selectActiveMembers').on('click', function() {
            kisilerTable.rows().every(function() {
                var row = $(this.node());
                var isActive = row.find('td:last-child .badge').hasClass('bg-success');
                row.find('.kisi-sec-checkbox').prop('checked', isActive);
            });
        });
        // Pasif Üyeleri Seç (tüm sayfalar)
        $('#selectPassiveMembers').on('click', function() {
            kisilerTable.rows().every(function() {
                var row = $(this.node());
                var isPassive = row.find('td:last-child .badge').hasClass('bg-danger');
                row.find('.kisi-sec-checkbox').prop('checked', isPassive);
            });
        });

        // Seçilenleri Ekle butonu
        $('#kisilerdenSecSaveButton').on('click', function() {
            var selectedNumbers = [];
            kisilerTable.rows().every(function() {
                var row = $(this.node());
                var checkbox = row.find('.kisi-sec-checkbox');
                if (checkbox.prop('checked')) {
                    var tel = checkbox.data('telefon');
                    if (tel) {
                        // Sadece rakamları al
                        var cleaned = String(tel).replace(/\D/g, '');
                        // Başında 0 varsa at
                        if (cleaned.length === 11 && cleaned.startsWith('0')) {
                            cleaned = cleaned.substring(1);
                        }
                        selectedNumbers.push(cleaned);
                    }
                }
            });
            // Ana pencereye gönder
            window.parent.postMessage({ type: 'addRecipients', numbers: selectedNumbers }, '*');
            // Modalı kapat (isteğe bağlı)

            if (window.parent.$) {
                window.parent.$('.modal').modal('hide');
            }
        });
    });
</script>