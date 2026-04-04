$(document).ready(function () {
    var personellerTable = $('#sayacPersonelTable').DataTable({
        processing: true,
        serverSide: false, // We will load all at once since it's personnel aggregated
        ajax: {
            url: 'views/demirbas/api.php',
            type: 'POST',
            data: { 
                action: 'sayac-personel-listesi',
                kategori: 'sayac'
            },
            dataSrc: function (json) {
                if (json.totals) {
                    $('#footerToplamVerilen').text(json.totals.toplam_verilen);
                    $('#footerToplamIade').text(json.totals.toplam_iade);
                    $('#footerElindeKalan').text(json.totals.elinde_kalan);
                }
                return json.data || [];
            }
        },
        columns: [
            { data: 'personel_adi' },
            { data: 'toplam_verilen' },
            { data: 'toplam_iade' },
            { data: 'elinde_kalan' },
            { data: 'islemler', orderable: false, searchable: false }
        ],
        language: {
            url: "assets/libs/datatables.net/tr.json"
        },
        order: [[0, 'asc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"info><"col-sm-12 col-md-7"p>>',
    });

    var detayTable = null;

    $('#sayacPersonelTable').on('click', '.btn-personel-detay', function() {
        var personelId = $(this).data('id');
        var personelAdi = $(this).data('name');

        $('#detayPersonelAdi').text(personelAdi);

        if (detayTable) {
            detayTable.destroy();
        }

        detayTable = $('#sayacPersonelDetayTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'views/demirbas/api.php',
                type: 'POST',
                data: {
                    action: 'sayac-personel-detay',
                    personel_id: personelId,
                    kategori: 'sayac'
                }
            },
            columns: [
                { data: 'tarih' },
                { data: 'islem_tipi' },
                { data: 'demirbas' },
                { data: 'seri_no' },
                { data: 'adet' },
                { data: 'kategori' },
                { data: 'aciklama' }
            ],
            language: {
                url: "assets/libs/datatables.net/tr.json"
            },
            order: [[0, 'desc']],
            pageLength: 10
        });

        $('#sayacPersonelDetayModal').modal('show');
    });

    // Excel indirme butonu
    $('#sayacPersonelTable').on('click', '.elinde-kalan-indir', function() {
        var personelId = $(this).data('id');
        
        // Yeni bir gizli iframe veya window açarak excel indirmeyi tetikle
        var form = $('<form action="views/demirbas/export-personel-xls.php" method="POST" target="_blank"></form>');
        form.append('<input type="hidden" name="personel_id" value="' + personelId + '">');
        form.append('<input type="hidden" name="kategori" value="sayac">');
        $('body').append(form);
        form.submit();
        form.remove();
    });

    // Personele sayaç ver butonu -> Eğer zimmet-modal eklendiyse onu tetikle
    $('#btnPersonelAta').on('click', function() {
        if ($('#zimmetModal').length) {
            // Zimmet modalını bul ve aç. Standart demirbaşları yükle vs..
            $('#zimmetModal').modal('show');
        } else {
            console.log("Zimmet modal tanımlı değil. (Zimmet Ver Modal'ını sayac-deposu.php'ye eklemelisiniz.)");
        }
    });

});
