$(document).ready(function () {
    var zimmetUrl = "views/demirbas/api.php";
    var localServisTable;

    function getDatatableOptions() {
        return {
            responsive: true,
            processing: true,
            language: {
                url: "assets/libs/datatables.net/tr.json",
                emptyTable: '<div class="text-center text-muted py-4"><i class="bx bx-wrench display-4 d-block mb-2"></i>Kayıt bulunamadı.</div>'
            }
        };
    }

    if ($("#servisTable").length) {
        localServisTable = $("#servisTable").DataTable({
            ...getDatatableOptions(),
            serverSide: true,
            ajax: {
                url: zimmetUrl,
                type: "POST",
                data: function (d) {
                    d.action = "servis-listesi";
                    d.baslangic = $("#servis_filtre_baslangic").val();
                    d.bitis = $("#servis_filtre_bitis").val();
                }
            },
            columns: [
                { data: "sira", className: "text-center" },
                { data: "demirbas_adi" },
                { data: "servis_tarihi", className: "text-center" },
                { data: "iade_tarihi", className: "text-center" },
                { data: "servis_adi" },
                { data: "teslim_eden" },
                { data: "islem_detay" },
                { data: "tutar", className: "text-end" },
                { data: "islemler", className: "text-center", orderable: false }
            ],
            order: [[2, "desc"]]
        });
    }

    $('#btnServisFiltrele').on('click', function() {
        if (localServisTable) {
            localServisTable.ajax.reload();
        }
    });

    // Make local variable available globally if demirbas.js expects it
    window.servisTable = localServisTable;
});
