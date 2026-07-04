$(document).ready(function () {
  // Modal içeriğini yükle
  function loadModalContent(callback) {
    $(".group-modal-content").load(
      "views/kullanici-gruplari/modal/user_groups_modal.php",
      function () {
        if (callback) callback();
        feather.replace();
        $(".select2").select2({
          dropdownParent: $("#groupModal"),
        });
      },
    );
  }

  // Yeni Ekle butonu
  $("#groupAddBtn").on("click", function () {
    loadModalContent(function () {
      $("#actionModalLabel").text("Yeni Yetki Grubu Ekle");
      $("#group_id").val("0");
      $("#actionForm")[0].reset();
    });
  });

  // Düzenle butonu
  $(document).on("click", ".kullanici-duzenle", function () {
    var id = $(this).data("id");
    loadModalContent(function () {
      $("#actionModalLabel").text("Yetki Grubu Düzenle");
      $("#groupModal").modal("show");

      $.ajax({
        url: "views/kullanici-gruplari/api.php",
        type: "POST",
        data: { action: "getGroup", id: id },
        dataType: "json",
        success: function (res) {
          if (res.status === "success") {
            $("#group_id").val(id);
            $('input[name="role_name"]').val(res.data.role_name);
            $('textarea[name="description"]').val(res.data.description);
            $('select[name="role_color"]')
              .val(res.data.role_color)
              .trigger("change");

            // Floating label aktivasyonu için inputları tetikle
            $('input[name="role_name"], textarea[name="description"]').trigger(
              "change",
            );
          } else {
            Swal.fire("Hata", res.message, "error");
          }
        },
      });
    });
  });

  // Kaydet butonu
  $(document).on("click", "#actionKaydet", function () {
    var form = $("#actionForm");
    var formData = form.serialize();
    formData += "&action=saveGroup";

    var $btn = $(this);
    var originalText = $btn.html();
    $btn
      .prop("disabled", true)
      .html(
        '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...',
      );

    $.ajax({
      url: "views/kullanici-gruplari/api.php",
      type: "POST",
      data: formData,
      dataType: "json",
      success: function (res) {
        if (res.status === "success") {
          Swal.fire("Başarılı", res.message, "success").then(() => {
            location.reload();
          });
        } else {
          Swal.fire("Hata", res.message, "error");
        }
      },
      error: function () {
        Swal.fire("Hata", "Bir sunucu hatası oluştu.", "error");
      },
      complete: function () {
        $btn.prop("disabled", false).html(originalText);
      },
    });
  });

  // Sil butonu
  $(document).on("click", ".kullanici-sil", function () {
    var id = $(this).data("id");
    var name = $(this).data("name");

    Swal.fire({
      title: "Emin misiniz?",
      text: '"' + name + '" yetki grubunu silmek istediğinize emin misiniz?',
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Evet, sil!",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "views/kullanici-gruplari/api.php",
          type: "POST",
          data: { action: "deleteGroup", id: id },
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

  $(document).on("click", ".yetki-kopyala", function () {
    var id = $(this).data("id");
    var rawId = $(this).data("raw-id");
    var name = $(this).data("name");

    $("#target_role_id").val(id);
    $("#target_role_name").text(name);

    // Select2'yi sıfırla ve hedef rolü gizle/devre dışı bırak
    if ($("#source_role_id").hasClass("select2-hidden-accessible")) {
      $("#source_role_id").select2("destroy");
    }

    $("#source_role_id option").prop("disabled", false).show();
    $("#source_role_id option[data-raw-id='" + rawId + "']")
      .prop("disabled", true)
      .hide();
    $("#source_role_id").val("").trigger("change");

    $("#copyPermissionsModal").modal("show");

    $("#source_role_id").select2({
      dropdownParent: $("#copyPermissionsModal"),
    });
  });

  // Yetki Kopyalama İşlemi
  $("#btnCopyPermissions").on("click", function () {
    var target_id = $("#target_role_id").val();
    var source_id = $("#source_role_id").val();

    if (!source_id) {
      Swal.fire("Uyarı", "Lütfen kaynak bir yetki grubu seçiniz.", "warning");
      return;
    }

    Swal.fire({
      title: "Emin misiniz?",
      text: "Seçilen grubun yetkileri hedef gruba kopyalanacaktır. Mevcut yetkiler değişebilir!",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Evet, kopyala",
      cancelButtonText: "İptal",
    }).then((result) => {
      if (result.isConfirmed) {
        var $btn = $(this);
        $btn
          .prop("disabled", true)
          .html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
          url: "views/kullanici-gruplari/api.php",
          type: "POST",
          data: {
            action: "copyPermissions",
            target_role_id: target_id,
            source_role_id: source_id,
          },
          dataType: "json",
          success: function (res) {
            if (res.status === "success") {
              Swal.fire("Başarılı", res.message, "success").then(() => {
                $("#copyPermissionsModal").modal("hide");
              });
            } else {
              Swal.fire("Hata", res.message, "error");
            }
          },
          complete: function () {
            $btn.prop("disabled", false).text("Kopyala");
          },
        });
      }
    });
  });

  // Yetki Özetini Modalda Göster
  $(document).on("click", ".role-summary-trigger", function () {
    var id = $(this).data("id");
    var name = $(this).data("name");

    $("#roleSummaryModalLabel").text('"' + name + '" Yetki Grubu Yetkileri');
    $("#roleSummaryModalDescription").hide().text("");
    $("#roleSummaryContent").html(
      '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div></div>'
    );
    $("#roleSummaryModal").modal("show");

    $.ajax({
      url: "views/kullanici-gruplari/api.php",
      type: "POST",
      data: { action: "getPermissionsSummary", id: id },
      dataType: "json",
      success: function (res) {
        if (res.status === "success") {
          if (res.description) {
            $("#roleSummaryModalDescription").text(res.description).show();
          } else {
            $("#roleSummaryModalDescription").hide();
          }
          var html = "";
          var hasPermissions = false;

          for (var group in res.data) {
            hasPermissions = true;
            html += '<div class="mb-4">';
            html += '<h6 class="fw-bold border-bottom pb-2 text-primary" style="font-size: 14px;"><i class="mdi mdi-circle-double font-size-12 me-1"></i> ' + group + '</h6>';
            html += '<div class="d-flex flex-wrap gap-2 ps-2">';
            
            res.data[group].forEach(function (perm) {
              html += '<span class="badge bg-light text-dark border p-2" style="font-size: 11.5px; font-weight: normal;" title="' + (perm.description || '') + '">' + perm.name + '</span>';
            });

            html += '</div></div>';
          }

          if (!hasPermissions) {
            html = '<div class="text-center py-4 text-muted"><i class="mdi mdi-alert-circle-outline font-size-24 d-block mb-2"></i>Bu gruba atanmış herhangi bir yetki bulunmamaktadır.</div>';
          }

          $("#roleSummaryContent").html(html);
        } else {
          $("#roleSummaryModal").modal("hide");
          Swal.fire("Hata", res.message, "error");
        }
      },
      error: function () {
        $("#roleSummaryContent").html(
          '<div class="alert alert-danger mb-0">Sunucu hatası nedeniyle yetkiler yüklenemedi.</div>'
        );
      }
    });
  });
});
