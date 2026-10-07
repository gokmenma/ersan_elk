(function ($) {
  "use strict";

  var url = "views/kullanici/api.php";
  var row = null;
  var userTable = null;
  var activeDurumFilter = "all";

  $(document).ready(function () {
    // DataTables Özel Durum Filtresi
    if ($.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.search) {
      $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== "usersTable") return true;
        if (activeDurumFilter === "all") return true;

        const tr = $(settings.aoData[dataIndex].nTr);
        if (activeDurumFilter === "Aktif") {
          return tr.attr("data-durum") === "Aktif";
        }
        if (activeDurumFilter === "Pasif") {
          return tr.attr("data-durum") === "Pasif";
        }
        if (activeDurumFilter === "izin_onay") {
          return tr.attr("data-izin-onayi") === "Evet";
        }

        return true;
      });
    }

    // DataTable Başlatma
    if ($("#usersTable").length > 0) {
      const baseOptions = typeof getDatatableOptions === "function" ? getDatatableOptions() : { language: { url: "assets/libs/datatables.net/js/tr.json" } };
      const dtConfig = typeof applyLengthStateSave === "function" ? applyLengthStateSave({
        ...baseOptions,
        order: [[0, "asc"]],
        columnDefs: [
          { targets: [0, 9], orderable: false }
        ]
      }) : {
        order: [[0, "asc"]],
        columnDefs: [
          { targets: [0, 9], orderable: false }
        ]
      };

      userTable = $("#usersTable").DataTable(dtConfig);
    }

    // KPI Hızlı Filtre Butonları
    $(".status-quick-filter").on("click", function () {
      $(".status-quick-filter").removeClass("active");
      $(this).addClass("active");
      activeDurumFilter = $(this).data("filter-durum") || "all";
      if (userTable) {
        userTable.draw();
      }
    });

    // Excel ve Yazdır Butonları
    $("#btnHeaderExportExcel").on("click", function () {
      const tableEl = document.getElementById("usersTable");
      if (!tableEl) return;

      const clone = tableEl.cloneNode(true);
      $(clone).find("th:last-child, td:last-child").remove();

      const html = clone.outerHTML;
      const blob = new Blob(["\ufeff", html], { type: "application/vnd.ms-excel" });
      const fileUrl = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = fileUrl;
      a.download = "kullanici_listesi_" + new Date().toISOString().slice(0, 10) + ".xls";
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(fileUrl);
    });

    $("#btnHeaderPrint").on("click", function () {
      window.print();
    });

    // ---------- Özet kartlarını gizle/göster ----------
    const SUMMARY_STATE_KEY = "kullanici_summary_cards_state";

    function setSummaryCardsVisibility(visible) {
      document.documentElement.classList.toggle("kullanici-summary-hidden", !visible);
      $("#btnToggleSummaryCards")
        .attr("aria-expanded", visible ? "true" : "false")
        .attr("title", visible ? "Özet Kartları Gizle" : "Özet Kartları Göster")
        .find("i")
        .attr("class", visible ? "bx bx-chevron-up" : "bx bx-chevron-down");
    }

    $("#btnToggleSummaryCards").on("click", function (e) {
      e.preventDefault();
      const isCurrentlyHidden = document.documentElement.classList.contains("kullanici-summary-hidden");
      const shouldShow = isCurrentlyHidden;
      setSummaryCardsVisibility(shouldShow);
      try {
        localStorage.setItem(SUMMARY_STATE_KEY, shouldShow ? "visible" : "hidden");
      } catch (e) {}
    });

    (function initSummaryCardsState() {
      const isHidden = localStorage.getItem(SUMMARY_STATE_KEY) === "hidden";
      setSummaryCardsVisibility(!isHidden);
    })();
  });

  // Modal Açma / Kapama İşlemleri
  $(document).on("click", "#userAddBtn", function (e) {
    e.preventDefault();
    row = null;
    getUserModal();
  });

  $(document).on("click", ".kullanici-duzenle", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var id = $(this).data("id");
    if (userTable) {
      row = userTable.row($(this).closest("tr"));
    }
    getUserModal(id);
  });

  // Satıra tıklayınca düzenleme modalı açılsın
  $(document).on("click", "#usersTable tbody tr", function (e) {
    if ($(e.target).closest("button, a, .dropdown-menu, .durum-degistir, .table-action-btn").length) {
      return;
    }

    var id = $(this).data("id");
    if (id) {
      if (userTable) {
        row = userTable.row($(this));
      }
      getUserModal(id);
    }
  });

  function getUserModal(id = 0) {
    var modalUrl = "views/kullanici/modal/user-modal.php";

    $.get(
      modalUrl,
      { id: id },
      function (data) {
        $(".user-modal-content").html(data);
        if (typeof feather !== "undefined") feather.replace();

        var $selects = $(".select2");
        $selects.select2({
          dropdownParent: $("#userModal .modal-content"),
          closeOnSelect: false,
          width: "100%"
        });

        // Show summary for multiple selects
        $selects.each(function() {
          var $this = $(this);
          if ($this.prop("multiple")) {
            $this.on("change.select2-summary", function() {
              var count = $(this).val() ? $(this).val().length : 0;
              var label = $(this).data("selection-label") || "öğe";
              var $container = $(this).next(".select2").find(".select2-selection--multiple");
              var $rendered = $container.find(".select2-selection__rendered");

              $rendered.find(".selection-summary-container").remove();

              if (count > 0) {
                $container.addClass("has-summary");
                $rendered.prepend('<span class="selection-summary-container">' + count + " " + label + " seçildi</span>");
              } else {
                $container.removeClass("has-summary");
              }
            }).trigger("change.select2-summary");

            $this.on("change", function() {
              if ($(this).closest("form").length && typeof $(this).valid === "function") {
                $(this).valid();
              }
            });
          }
        });

        toggleIzinOnaySirasi();
      }
    ).fail(function () {
      $(".user-modal-content").html(
        "<div class='alert alert-danger m-3'>Modal içeriği yüklenemedi.</div>"
      );
    });
    $("#userModal").modal("show");
  }

  function toggleIzinOnaySirasi() {
    var val = $("#izin_onayi_yapacakmi").val();
    var $input = $("input[name='izin_onay_sirasi']");
    if (val === "Evet") {
      $input.prop("disabled", false);
      $("#izinOnaySirasiWrapper").css("opacity", "1");
    } else {
      $input.prop("disabled", true).val("");
      $("#izinOnaySirasiWrapper").css("opacity", "0.45");
    }
  }

  $(document).on("change", "#izin_onayi_yapacakmi", function () {
    toggleIzinOnaySirasi();
  });

  // Şifre göster / gizle butonu
  $(document).on("click", ".btn-toggle-password", function (e) {
    e.preventDefault();
    var $input = $(this).closest(".password-field-wrapper").find("input[name='password']");
    var $icon = $(this).find("i");
    if ($input.attr("type") === "password") {
      $input.attr("type", "text");
      $icon.removeClass("mdi-eye-outline").addClass("mdi-eye-off-outline");
    } else {
      $input.attr("type", "password");
      $icon.removeClass("mdi-eye-off-outline").addClass("mdi-eye-outline");
    }
  });

  // Bildirim kartları tıklandığında aktiflik sınıfını güncelle
  $(document).on("change", ".notif-checkbox", function () {
    var isChecked = $(this).is(":checked");
    $(this).closest(".notification-tile").toggleClass("is-active", isChecked);
  });

  // Kaydet Butonu
  $(document).on("click", "#userSaveBtn", function () {
    var form = $("#userForm");
    if (!form.length) return;

    var userId = form.find("input[name='user_id']").val();
    var isUpdateMode = userId && userId != 0;

    // Şube ve Rol seçimlerini açıkça kontrol et
    var selectedFirms = form.find("select[name='user_firms[]']").val();
    if (!selectedFirms || (Array.isArray(selectedFirms) && selectedFirms.length === 0)) {
      Swal.fire({
        title: "Eksik Bilgi",
        text: "Lütfen kullanıcının yetkili olduğu en az bir şube / firma seçiniz.",
        icon: "warning",
        confirmButtonText: "Tamam"
      });
      return false;
    }

    var selectedRoles = form.find("select[name='roles[]']").val();
    if (!selectedRoles || (Array.isArray(selectedRoles) && selectedRoles.length === 0)) {
      Swal.fire({
        title: "Eksik Bilgi",
        text: "Lütfen en az bir yetki grubu (rol) seçiniz.",
        icon: "warning",
        confirmButtonText: "Tamam"
      });
      return false;
    }

    form.validate({
      ignore: [],
      rules: {
        user_name: {
          required: true,
          minlength: 3,
          maxlength: 50,
        },
        adi_soyadi: {
          required: true,
          minlength: 3,
          maxlength: 50,
        },
        email_adresi: {
          required: true,
          email: true,
        },
        "user_firms[]": {
          required: true,
        },
        telefon: {
          required: true,
        },
        password: {
          required: function (element) {
            if (!isUpdateMode) {
              return true;
            }
            return $(element).val().trim() !== "";
          },
          minlength: 8,
        },
        "roles[]": {
          required: true,
        },
        gorevi: {
          required: true,
        },
      },
      messages: {
        user_name: {
          required: "Kullanıcı adı zorunludur.",
          minlength: "Kullanıcı adı en az 3 karakter olmalıdır.",
          maxlength: "Kullanıcı adı en fazla 50 karakter olmalıdır.",
        },
        adi_soyadi: {
          required: "Adı ve soyadı zorunludur.",
          minlength: "Adı ve soyadı en az 3 karakter olmalıdır.",
          maxlength: "Adı ve soyadı en fazla 50 karakter olmalıdır.",
        },
        email_adresi: {
          required: "E-posta adresi zorunludur.",
          email: "Lütfen geçerli bir e-posta adresi giriniz.",
        },
        telefon: {
          required: "Telefon numarası zorunludur.",
        },
        "user_firms[]": {
          required: "Firma seçimi zorunludur.",
        },
        password: {
          required: "Parola zorunludur.",
          minlength: "Parola en az 8 karakter olmalıdır.",
        },
        "roles[]": {
          required: "Rol seçimi zorunludur.",
        },
        gorevi: {
          required: "Görevi zorunludur.",
        },
      },
      errorPlacement: function (error, element) {
        if (element.hasClass("select2") || element.next().hasClass("select2")) {
          error.insertAfter(element.next(".select2"));
        } else {
          error.insertAfter(element);
        }
      },
    });

    if (!form.valid()) {
      return false;
    }

    var formData = new FormData(form[0]);
    formData.append("action", "kullanici-kaydet");

    fetch(url, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        var title = data.status == "success" ? "Başarılı" : "Hata";

        Swal.fire({
          title: title,
          text: data.message,
          icon: data.status,
          confirmButtonText: "Tamam",
        }).then((result) => {
          if (result.isConfirmed) {
            if (data.status == "success") {
              location.reload();
            }
          }
        });
      })
      .catch((err) => {
        Swal.fire({
          title: "Hata",
          text: "İşlem sırasında bir hata oluştu.",
          icon: "error",
          confirmButtonText: "Tamam",
        });
      });
  });

  // Silme Butonu
  $(document).on("click", ".kullanici-sil", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var id = $(this).data("id");
    var userName = $(this).data("name") || "Bu kullanıcı";

    Swal.fire({
      title: "Silmek istediğinize emin misiniz?",
      text: userName + " kullanıcısı sistemden tamamen silinecektir!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#f43f5e",
      cancelButtonColor: "#64748b",
      confirmButtonText: "Evet, Sil!",
      cancelButtonText: "Vazgeç",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          url,
          {
            action: "kullanici-sil",
            id: id,
          },
          function (data) {
            if (data.status == "success") {
              Swal.fire("Silindi!", data.message, "success").then(() => {
                location.reload();
              });
            } else {
              Swal.fire("Hata!", data.message, "error");
            }
          },
          "json"
        );
      }
    });
  });

  // Durum Değiştirme
  $(document).on("click", ".durum-degistir", function (e) {
    e.preventDefault();
    e.stopPropagation();
    var id = $(this).data("id");
    var status = $(this).data("status");

    Swal.fire({
      title: "Durum Değiştirilsin mi?",
      text: "Kullanıcı durumu " + status + " olarak güncellenecektir.",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#0ea5e9",
      cancelButtonColor: "#64748b",
      confirmButtonText: "Evet, Değiştir",
      cancelButtonText: "Vazgeç",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          url,
          {
            action: "kullanici-durum-degistir",
            id: id,
            status: status,
          },
          function (data) {
            if (data.status == "success") {
              Swal.fire("Başarılı!", data.message, "success").then(() => {
                location.reload();
              });
            } else {
              Swal.fire("Hata!", data.message, "error");
            }
          },
          "json"
        );
      }
    });
  });
})(jQuery);
