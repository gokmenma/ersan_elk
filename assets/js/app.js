/**
 * Global Toast bildirim fonksiyonu (Toastify.js kullanır)
 * @param {string} message - Gösterilecek mesaj
 * @param {string} type - success, error, warning, info
 */
function showToast(message, type = "success") {
  const bgColors = {
    success: "var(--bs-primary)",
    error: "var(--bs-danger)",
    warning: "var(--bs-warning)",
    info: "var(--bs-info)",
  };

  // Ensure message is a string to prevent "Cannot read properties of undefined (reading 'call')" errors
  // that occur when Toastify tries to parse objects as nodes/text.
  const safeMessage = typeof message === 'string' ? message : String(message || '');

  Toastify({
    text: safeMessage,
    duration: 3000,
    gravity: "top",
    position: "center",
    style: {
      background: bgColors[type] || bgColors.success,
      borderRadius: "6px",
      boxShadow: "0 4px 12px rgba(0, 0, 0, 0.1)",
    },
    stopOnFocus: true,
  }).showToast();
}

function confirmAndDelete(url, formData, buttonElement, tableId) {
  swal
    .fire({
      title: "Emin misiniz?",
      text: "Bu işlem geri alınamaz!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet",
      cancelButtonText: "Hayır",
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
    })
    .then((result) => {
      if (result.isConfirmed) {
        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            const table = $(`#${tableId}`).DataTable();
            table.row(buttonElement.closest("tr")).remove().draw(false);

            showToast(data.message, data.status);
          });
      }
    });
}

/**firma_id'de değişikliği dinle */
$(document).on("change", "#firma_id", function () {
  var firma_id = $(this).val();
  const params = new URLSearchParams(window.location.search);
  const p = params.get("p");
  window.location.href = "/set-session.php?firma_id=" + firma_id + "&p=" + p;
});

//number classına sahip inputlara sadece sayısal değer girilmesini sağlar
//Örnek kullanım: <input type="text" class="number">
var numberInputs = document.querySelectorAll(".number");
numberInputs.forEach(function (input) {
  input.addEventListener("input", function () {
    this.value = this.value.replace(/[^0-9]/g, "");
  });
});

//text classına sahip inputlara sadece harf ve boşluk girilmesini sağlar
//Örnek kullanım: <input type="text" class="text">
var textInputs = document.querySelectorAll(".text");
textInputs.forEach(function (input) {
  input.addEventListener("input", function () {
    this.value = this.value.replace(/[^a-zA-ZğüşöçıİĞÜŞÖÇ\s]/g, "");
  });
});

$.validator.setDefaults({
  errorPlacement: function (error, element) {
    // Hata mesajını input grubunun altına ekle
    error.addClass("text-danger"); // Hata mesajına stil ekleyin
    if (element.closest(".form-floating").length) {
      element.closest(".form-floating").after(error); // Input grubunun altına ekle
    } else {
      element.after(error); // Diğer durumlarda input'un altına ekle
    }
  },
  highlight: function (element) {
    // Hatalı input alanına kırmızı border ekle
    $(element).addClass("is-invalid");
    // Input'un en yakın form-floating kapsayıcısına is-invalid sınıfını ekle
    $(element).closest(".form-floating").addClass("is-invalid");
  },
  unhighlight: function (element) {
    // Hatalı input alanından kırmızı border'ı kaldır
    $(element).removeClass("is-invalid");
    // Input'un en yakın input-group kapsayıcısından is-invalid sınıfını kaldır
    $(element).closest(".form-floating").removeClass("is-invalid");
  },
});

$.validator.addMethod(
  "minAge",
  function (value, element, min) {
    var today = new Date();
    var birthDate = new Date(value);
    var age = today.getFullYear() - birthDate.getFullYear();

    if (age < min + 1) {
      return false;
    }

    return true;
  },
  "Personel 15 yaşından büyük olmalıdır!",
);

// $(".select2").on("change", function () {
//   $(this).valid(); // Sadece bu alanı tekrar valide eder
// });

/**
 * Başlangıç tarihine göre aynı ayın son gününü hesaplar (DD.MM.YYYY)
 * @param {string} baslangicTarihi
 * @returns {string} DD.MM.YYYY
 */
function ayinSonGununuGetir(baslangicTarihi) {
  if (!baslangicTarihi) return "";

  let parts = baslangicTarihi.split(".");
  if (parts.length !== 3) return "";

  let gun = parseInt(parts[0], 10);
  let ay = parseInt(parts[1], 10) - 1;
  let yil = parseInt(parts[2], 10);

  let lastDay = new Date(yil, ay + 1, 0);

  let bitisGun = String(lastDay.getDate()).padStart(2, "0");
  let bitisAy = String(lastDay.getMonth() + 1).padStart(2, "0");
  let bitisYil = lastDay.getFullYear();

  return `${bitisGun}.${bitisAy}.${bitisYil}`;
}

/**
 * Tab persistence via URL query parameter
 * Sayfa yenilendiğinde seçili sekmenin aktif kalmasını sağlar.
 */
$(document).ready(function () {
  // Tab değiştiğinde URL'yi güncelle
  $(document).on(
    "shown.bs.tab",
    '[data-bs-toggle="tab"], [data-bs-toggle="pill"]',
    function (e) {
      var target =
        $(e.target).attr("href") || $(e.target).attr("data-bs-target");
      // Eğer data-no-url-update attribute varsa URL güncelleme
      if ($(e.target).data("no-url-update")) {
        return;
      }

      if (target && target.startsWith("#")) {
        var tabName = target.substring(1).replace("Content", ""); // 'aracContent' -> 'arac'

        var url = new URL(window.location);
        url.searchParams.set("tab", tabName);
        // URL'yi güncelle ama geçmişe ekleme (opsiyonel, pushState de olabilir)
        window.history.replaceState({}, "", url);
      }
    },
  );

  // Sayfa yüklendiğinde URL'de tab varsa ve PHP tarafından aktif edilmemişse JS ile aktif et
  var urlParams = new URLSearchParams(window.location.search);
  var tabParam = urlParams.get("tab");
  if (tabParam) {
    var tabEl =
      document.querySelector(
        '[data-bs-toggle="tab"][href="#' + tabParam + '"]',
      ) ||
      document.querySelector(
        '[data-bs-toggle="tab"][data-bs-target="#' + tabParam + '"]',
      ) ||
      document.querySelector(
        '[data-bs-toggle="tab"][href="#' + tabParam + 'Content"]',
      ) ||
      document.querySelector(
        '[data-bs-toggle="tab"][data-bs-target="#' + tabParam + 'Content"]',
      ) ||
      document.querySelector(
        '[data-bs-toggle="pill"][href="#' + tabParam + '"]',
      ) ||
      document.querySelector(
        '[data-bs-toggle="pill"][data-bs-target="#' + tabParam + '"]',
      );

    if (tabEl && !tabEl.classList.contains("active")) {
      setTimeout(function () {
        var tab = new bootstrap.Tab(tabEl);
        tab.show();
      }, 100);
    }
  }

  /**
   * Premium Modal Header Auto-Upgrade
   * Restructures any standard Bootstrap modal header to the premium design.
   */
  $(document).on("show.bs.modal", ".modal", function () {
    const $modal = $(this);
    const $header = $modal.find(".modal-header");

    // Skip if already upgraded or specially excluded
    if (
      $header.hasClass("premium-modal-header") ||
      $header.find(".modal-title-section").length ||
      $modal.hasClass("no-upgrade")
    ) {
      return;
    }

    const title = $header.find(".modal-title").text() || "İşlem";
    const $closeBtn = $header.find(".btn-close").detach();

    // Get specific data from modal attributes if they exist
    let icon = $modal.data("modal-icon") || "bx bx-layer";
    let subtitle =
      $modal.data("modal-subtitle") || "Lütfen formu eksiksiz doldurun.";
    let iconClass = "";

    // Specific logic for known modals
    const modalId = $modal.attr("id");
    const titleLower = title.toLowerCase();

    // Default variant
    let headerVariant = "modal-header-primary";

    if (
      modalId === "excelImportModal" ||
      titleLower.includes("excel") ||
      titleLower.includes("yükle")
    ) {
      icon = "bx bx-upload";
      subtitle = "Excel dosyanızı seçerek verileri hızlıca aktarın.";
      headerVariant = "modal-header-warning";
    } else if (titleLower.includes("sil") || titleLower.includes("iptal")) {
      icon = "bx bx-trash";
      subtitle = "Bu işlem verilerinizde kalıcı değişiklik yapabilir.";
      headerVariant = "modal-header-danger";
    } else if (
      titleLower.includes("düzenle") ||
      titleLower.includes("güncelle")
    ) {
      icon = "bx bx-edit";
      subtitle = "Kayıt bilgilerini aşağıdan güncelleyebilirsiniz.";
      headerVariant = "modal-header-info";
    } else if (titleLower.includes("ekle") || titleLower.includes("yeni")) {
      icon = "bx bx-plus-circle";
      subtitle = "Yeni kayıt oluşturmak için bilgileri doldurun.";
      headerVariant = "modal-header-success";
    }

    // Clear and restucture
    $header
      .empty()
      .removeClass(function (index, className) {
        return (className.match(/(^|\s)bg-\S+/g) || []).join(" ");
      })
      .removeClass(function (index, className) {
        return (className.match(/(^|\s)text-\S+/g) || []).join(" ");
      })
      .addClass("premium-modal-header")
      .addClass(headerVariant);

    // Clean up close button
    $closeBtn.removeClass("btn-close-white");

    const premiumHeaderHtml = `
            <div class="modal-title-section">
                <div class="modal-icon-box ${iconClass}">
                    <i class="${icon}"></i>
                </div>
                <div class="modal-title-group">
                    <h5 class="modal-title">${title}</h5>
                    <p class="modal-subtitle">${subtitle}</p>
                </div>
            </div>
        `;

    $header.append(premiumHeaderHtml);
    $header.append($closeBtn);
  });
});
