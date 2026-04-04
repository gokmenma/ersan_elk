$(document).ready(function () {
    var zimmetUrl = "views/demirbas/api.php";
    var localZimmetTable;

    function getDatatableOptions() {
        return {
            responsive: true,
            processing: true,
            language: {
                url: "assets/libs/datatables.net/tr.json",
                emptyTable: '<div class="text-center text-muted py-4"><i class="bx bx-transfer display-4 d-block mb-2"></i>Kayıt bulunamadı.</div>'
            }
        };
    }

    if ($("#zimmetTable").length) {
        localZimmetTable = $("#zimmetTable").DataTable({
            ...getDatatableOptions(),
            serverSide: true,
            ajax: {
                url: zimmetUrl,
                type: "POST",
                data: function (d) {
                    d.action = "zimmet-listesi";
                    d.filter_type = $('input[name="zimmetFilter"]:checked').val() || "all";
                    d.personel_id = $("#zimmet_personel_filtre").val() || "all";
                    d.sayac_kat_ids = typeof sayacKatIds !== "undefined" ? sayacKatIds : [];
                    d.aparat_kat_ids = typeof aparatKatIds !== "undefined" ? aparatKatIds : [];
                }
            },
            columns: [
                {
                    data: "checkbox",
                    className: "text-center",
                    orderable: false,
                    searchable: false
                },
                { data: "id", className: "text-center" },
                { data: "kategori_adi" },
                { data: "demirbas_adi" },
                { data: "marka_model" },
                { data: "personel_adi" },
                { data: "teslim_miktar", className: "text-center" },
                { data: "teslim_tarihi" },
                { data: "durum", className: "text-center" },
                { data: "islemler", className: "text-center", orderable: false }
            ],
            order: [[1, "desc"]],
            createdRow: function (row, data, dataIndex) {
                $(row).attr("data-id", data.enc_id);
            }
        });
    }

    // Filtre değişikliklerinde tabloyu yenile
    $('input[name="zimmetFilter"]').on('change', function() {
        if (localZimmetTable) {
            localZimmetTable.ajax.reload();
        }
    });

    $('#zimmet_personel_filtre').on('change', function() {
        if (localZimmetTable) {
            localZimmetTable.ajax.reload();
        }
    });
    
    if ($('#zimmet_personel_filtre').length) {
        $('#zimmet_personel_filtre').select2({
            placeholder: "Personel seçin...",
            allowClear: true,
            width: "100%"
        });
    }

    // Toplu seçim "Hepsi" checkbox'ı
    $('#checkAllZimmet').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.zimmet-select:not(:disabled)').prop('checked', isChecked);
    });

    // Make local variable available globally if demirbas.js expects it
    window.zimmetTable = localZimmetTable;
});
