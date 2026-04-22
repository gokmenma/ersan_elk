$(function () {
  const apiUrl = "views/tanimlamalar/icra-daireleri/api.php";
  
  if ($.fn.inputmask) {
    $(".mask-iban").inputmask("TR999999999999999999999999", {
      placeholder: "_",
      showMaskOnHover: false,
      showMaskOnFocus: true,
    });
  }
  
  // DataTable otomatik olarak datatables.init.js tarafından başlatılır (.datatable classı ile)
  // Eğer özel ayar gerekirse destroyAndInitDataTable kullanılabilir.

  // Modal Aç (Ekle)
  $("#btnEkle").on("click", function () {
    $("#formIcra")[0].reset();
    $("#icra_id").val(0);
    $("#aktif").prop("checked", true);
    $("#modalTitle").html('<i data-feather="home" class="me-2"></i> İcra Dairesi Ekle');
    if (typeof feather !== "undefined") {
      feather.replace();
      setTimeout(() => { feather.replace(); }, 100);
    }
    $("#modalIcra").modal("show");
  });

  // Kaydet
  $("#btnKaydet").on("click", function () {
    const form = $("#formIcra");
    if (form[0].checkValidity() === false) {
      form[0].reportValidity();
      return;
    }

    const formData = new FormData(form[0]);

    $.ajax({
      url: apiUrl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (res) {
        if (res.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Başarılı",
            text: res.message,
            timer: 1500,
            showConfirmButton: false,
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire("Hata", res.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir sunucu hatası oluştu.", "error");
      },
    });
  });

  // Düzenle
  $(document).on("click", ".duzenle", function () {
    const id = $(this).data("id");
    $.ajax({
      url: apiUrl,
      type: "POST",
      data: { action: "getir", id: id },
      dataType: "json",
      success: function (res) {
        if (res.status === "success") {
          const d = res.data;
          $("#icra_id").val(id);
          $("#daire_adi").val(d.daire_adi);
          $("#daire_kodu").val(d.daire_kodu);
          $("#il").val(d.il);
          $("#ilce").val(d.ilce);
          $("#adres").val(d.adres);
          $("#telefon").val(d.telefon);
          $("#faks").val(d.faks);
          $("#email").val(d.email);
          $("#vergi_dairesi").val(d.vergi_dairesi);
          $("#vergi_no").val(d.vergi_no);
          $("#iban").val(d.iban);
          $("#aktif").prop("checked", d.aktif == 1);

          $("#modalTitle").html('<i data-feather="edit" class="me-2"></i> İcra Dairesi Düzenle');
          if (typeof feather !== "undefined") {
            feather.replace();
            setTimeout(() => { feather.replace(); }, 100);
          }
          $("#modalIcra").modal("show");
        } else {
          Swal.fire("Hata", res.message, "error");
        }
      },
    });
  });

  // Sil
  $(document).on("click", ".sil", function () {
    const id = $(this).data("id");
    Swal.fire({
      title: "Emin misiniz?",
      text: "Bu icra dairesini silmek istediğinize emin misiniz?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet, Sil",
      cancelButtonText: "İptal",
      confirmButtonColor: "#f46a6a",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: apiUrl,
          type: "POST",
          data: { action: "sil", id: id },
          dataType: "json",
          success: function (res) {
            if (res.status === "success") {
              Swal.fire("Silindi!", res.message, "success").then(() => {
                location.reload();
              });
            } else {
              Swal.fire("Hata", res.message, "error");
            }
          },
        });
      }
    });
  });
});
