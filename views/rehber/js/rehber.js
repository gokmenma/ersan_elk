const url = "views/rehber/api.php";

$(document).ready(function () {
    // 1. DataTables Başlatma (AGENTS.md Standardı)
    let tableOptions = {
        processing: true,
        serverSide: true,
        ajax: {
            url: url,
            type: "POST",
            data: function (d) {
                d.action = "rehber-ajax-list";
            }
        },
        columns: [
            { data: "id", className: "text-center", width: "50px", orderable: true },
            { data: "adi_soyadi" },
            { data: "kurum_adi" },
            { data: "telefon", width: "140px" },
            { data: "email" },
            { data: "adres" },
            { data: "aciklama" },
            { data: "kayit_tarihi", className: "text-center", width: "120px" },
            { data: "actions", className: "text-center", width: "110px", orderable: false, searchable: false }
        ],
        createdRow: function (row, data, dataIndex) {
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[1, "asc"]]
    };

    if (typeof getDatatableOptions === "function") {
        tableOptions = { ...getDatatableOptions(), ...tableOptions };
    }

    if (typeof applyLengthStateSave === "function") {
        tableOptions = applyLengthStateSave(tableOptions);
    }

    const table = $("#rehberTable").DataTable(tableOptions);

    // 2. Yeni Kayıt Ekle Butonu
    $(document).on("click", "#yeniEkle", function () {
        $("#rehberForm").trigger("reset");
        $("#id").val(0);
        $("#rehberModalLabel").text("Yeni Rehber Kaydı");
        $("#rehberModalDesc").text("Kişi veya kurum iletişim bilgilerini giriniz.");
    });

    // 3. Düzenle Modalını Aç Fonksiyonu
    function duzenleModalAc(id) {
        if (!id) return;

        const formData = new FormData();
        formData.append("id", id);
        formData.append("action", "kayitGetir");

        fetch(url, {
            method: "POST",
            body: formData
        })
            .then((res) => {
                if (!res.ok) throw new Error("Kayıt bilgileri alınamadı.");
                return res.json();
            })
            .then((data) => {
                $("#rehberForm").trigger("reset");
                $("#id").val(id);
                $("#adi_soyadi").val(data.adi_soyadi || "");
                $("#kurum_adi").val(data.kurum_adi || "");
                $("#telefon").val(data.telefon || "");
                $("#email").val(data.email || "");
                $("#adres").val(data.adres || "");
                $("#aciklama").val(data.aciklama || "");

                $("#rehberModalLabel").text("Rehber Kaydını Düzenle");
                $("#rehberModalDesc").text("Kişi veya kurum bilgilerini güncelleyin.");
                $("#rehberModal").modal("show");
            })
            .catch((err) => {
                console.error("Hata:", err);
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "error",
                        title: "Hata!",
                        text: "Kayıt bilgileri yüklenirken bir problem oluştu."
                    });
                } else {
                    alert("Kayıt bilgileri yüklenirken bir problem oluştu.");
                }
            });
    }

    // Butonla Düzenleme
    $(document).on("click", ".kayit-duzenle", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = $(this).data("id");
        duzenleModalAc(id);
    });

    // Satıra Tıklayınca Düzenleme Modalı Açma (İşlem butonları & linkler hariç)
    $("#rehberTable tbody").on("click", "tr td:not(:last-child)", function (e) {
        if ($(e.target).closest("a, button, input, .dropdown-menu, .action-btn-group").length > 0) return;
        const btn = $(this).closest("tr").find(".kayit-duzenle");
        if (btn.length) {
            duzenleModalAc(btn.data("id"));
        }
    });

    // 4. Kayıt Kaydet (Yeni veya Güncelleme)
    $(document).on("click", "#rehberKaydet", function () {
        const form = $("#rehberForm");
        const adiSoyadi = $.trim($("#adi_soyadi").val());
        const telefon = $.trim($("#telefon").val());

        if (adiSoyadi === "") {
            if (typeof Swal !== "undefined") {
                Swal.fire({ icon: "warning", title: "Uyarı", text: "Lütfen Adı Soyadı alanını doldurunuz." });
            } else {
                alert("Lütfen Adı Soyadı alanını doldurunuz.");
            }
            $("#adi_soyadi").focus();
            return false;
        }

        if (telefon === "") {
            if (typeof Swal !== "undefined") {
                Swal.fire({ icon: "warning", title: "Uyarı", text: "Lütfen Telefon alanını doldurunuz." });
            } else {
                alert("Lütfen Telefon alanını doldurunuz.");
            }
            $("#telefon").focus();
            return false;
        }

        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

        const formData = new FormData(form[0]);
        formData.append("action", "kaydet");

        fetch(url, {
            method: "POST",
            body: formData
        })
            .then((res) => res.json())
            .then((data) => {
                btn.prop("disabled", false).html(originalHtml);
                if (data.status === "success") {
                    $("#rehberModal").modal("hide");
                    table.ajax.reload(null, false);

                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            icon: "success",
                            title: "Başarılı!",
                            text: data.message,
                            timer: 1600,
                            showConfirmButton: false
                        });
                    }
                } else {
                    if (typeof Swal !== "undefined") {
                        Swal.fire({ icon: "error", title: "Hata!", text: data.message || "İşlem başarısız." });
                    } else {
                        alert(data.message || "İşlem başarısız.");
                    }
                }
            })
            .catch((err) => {
                btn.prop("disabled", false).html(originalHtml);
                console.error("Kaydetme hatası:", err);
                if (typeof Swal !== "undefined") {
                    Swal.fire({ icon: "error", title: "Hata!", text: "Kayıt sırasında bir sunucu hatası oluştu." });
                }
            });
    });

    // 5. Kayıt Sil
    $(document).on("click", ".kayit-sil", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = $(this).data("id");
        const name = $(this).data("name") || "Bu kayıt";

        const swalConfig = {
            title: "Emin misiniz?",
            html: `<strong>${name}</strong> rehberden silinecektir. Bu işlem geri alınamaz!`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#f46a6a",
            cancelButtonColor: "#74788d",
            confirmButtonText: '<i class="bx bx-trash me-1"></i> Evet, Sil',
            cancelButtonText: "İptal"
        };

        const doDelete = () => {
            const formData = new FormData();
            formData.append("id", id);
            formData.append("action", "kayitSil");

            fetch(url, {
                method: "POST",
                body: formData
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data.status === "success") {
                        table.ajax.reload(null, false);
                        if (typeof Swal !== "undefined") {
                            Swal.fire({
                                icon: "success",
                                title: "Silindi!",
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    } else {
                        if (typeof Swal !== "undefined") {
                            Swal.fire({ icon: "error", title: "Hata!", text: data.message });
                        }
                    }
                })
                .catch((err) => {
                    console.error("Silme hatası:", err);
                    if (typeof Swal !== "undefined") {
                        Swal.fire({ icon: "error", title: "Hata!", text: "Silme işlemi sırasında hata oluştu." });
                    }
                });
        };

        if (typeof Swal !== "undefined") {
            Swal.fire(swalConfig).then((result) => {
                if (result.isConfirmed) {
                    doDelete();
                }
            });
        } else if (confirm(`${name} silinsin mi?`)) {
            doDelete();
        }
    });

    // 6. Excel Dışa Aktarma
    $("#btnHeaderExportExcel").on("click", function () {
        const searchVal = table.search();
        const exportUrl = `views/rehber/export-excel.php?search=${encodeURIComponent(searchVal)}`;
        window.open(exportUrl, "_blank");
    });

    // 7. Tabloyu Yazdır
    $("#btnHeaderPrint").on("click", function () {
        window.print();
    });
});
