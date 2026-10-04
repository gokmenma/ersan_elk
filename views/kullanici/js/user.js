let url = "views/kullanici/api.php";
let row;
let userTable = null;
let activeDurumFilter = "all";

$(document).ready(function () {
  // DataTables Özel Durum Filtresi
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

  // DataTable Başlatma
  if ($("#usersTable").length > 0) {
    const baseOptions = typeof getDatatableOptions === "function" ? getDatatableOptions() : { language: { url: "assets/libs/datatables.net/js/tr.json" } };
    const dtConfig = typeof applyLengthStateSave === "function" ? applyLengthStateSave({
      ...baseOptions,
      order: [[0, "asc"]],
      columnDefs: [
        { targets: [0, 8], orderable: false }
      ]
    }) : {
      order: [[0, "asc"]],
      columnDefs: [
        { targets: [0, 8], orderable: false }
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
  var userId = form.find("input[name='user_id']").val();
  var isUpdateMode = userId && userId != 0;

  form.validate({
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
      user_firms: {
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
      roles: {
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
      user_firms: {
        required: "Firma seçimi zorunludur.",
      },
      password: {
        required: "Parola zorunludur.",
        minlength: "Parola en az 8 karakter olmalıdır.",
      },
      roles: {
        required: "Rol seçimi zorunludur.",
      },
      gorevi: {
        required: "Görevi zorunludur.",
      },
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
