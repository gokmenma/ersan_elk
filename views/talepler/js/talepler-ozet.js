/**
 * Talepler Özet Modal Script
 * Dashboard'dan talep detaylarını görüntüleme ve hızlı işlem yapma
 */

document.addEventListener("DOMContentLoaded", function () {
  const TALEPLER_API = "views/talepler/api.php";
  let currentTalepId = null;
  let currentTalepTip = null;

  // Modal HTML'i sayfaya ekle
  if (!document.getElementById("modalTalepOzet")) {
    const modalHtml = `
        <div class="modal fade" id="modalTalepOzet" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bx bx-detail me-2"></i>Talep Detayı</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="talepOzetContent">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="index.php?p=talepler/list" class="btn btn-info">
                            <i class="bx bx-list-ul me-1"></i>Tüm Talepleri Gör
                        </a>
                        <button type="button" class="btn btn-success" id="btnQuickApprove" style="display:none;">
                            <i class="bx bx-check me-1"></i>Onayla
                        </button>
                        <button type="button" class="btn btn-danger" id="btnQuickReject" style="display:none;">
                            <i class="bx bx-x me-1"></i>Reddet
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML("beforeend", modalHtml);
  }

  // İncele butonlarına event listener ekle
  document.querySelectorAll(".btn-talep-incele").forEach((btn) => {
    btn.addEventListener("click", function () {
      const id = this.dataset.id;
      const tip = this.dataset.tip;
      loadTalepOzet(tip, id);
    });
  });

  function loadTalepOzet(tip, id) {
    currentTalepId = id;
    currentTalepTip = tip;

    const content = document.getElementById("talepOzetContent");
    if (content) {
      content.innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div></div>';
    }

    // Butonları güncelle
    const approveBtn = document.getElementById("btnQuickApprove");
    const rejectBtn = document.getElementById("btnQuickReject");
    if (approveBtn)
      approveBtn.style.display =
        tip === "avans" || tip === "izin" ? "inline-block" : "none";
    if (rejectBtn)
      rejectBtn.style.display =
        tip === "avans" || tip === "izin" ? "inline-block" : "none";

    const modalEl = document.getElementById("modalTalepOzet");
    if (modalEl) {
      new bootstrap.Modal(modalEl).show();
    }

    const formData = new FormData();
    formData.append("action", "get-" + tip + "-detay");
    formData.append("id", id);

    fetch(TALEPLER_API, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "success") {
          if (content) {
            content.innerHTML = renderTalepOzet(tip, data.data);
          }
        } else {
          if (content) {
            content.innerHTML =
              '<div class="alert alert-danger">' + data.message + "</div>";
          }
        }
      })
      .catch((error) => {
        if (content) {
          content.innerHTML =
            '<div class="alert alert-danger">Bir hata oluştu</div>';
        }
      });
  }

  function renderTalepOzet(tip, data) {
    let html = '<div class="row">';

    // Personel Bilgileri
    html += '<div class="col-md-4 text-center mb-4">';
    html +=
      '<img src="' +
      (data.resim_yolu || "assets/images/users/user-dummy-img.jpg") +
      '" class="rounded-circle mb-3" style="width:80px;height:80px;object-fit:cover;">';
    html += '<h5 class="mb-1">' + (data.adi_soyadi || "-") + "</h5>";
    html +=
      '<p class="text-muted mb-0 small">' + (data.departman || "") + "</p>";
    if (data.telefon) {
      html +=
        '<p class="mt-2 small"><i class="bx bx-phone me-1"></i>' +
        data.telefon +
        "</p>";
    }
    html += "</div>";

    // Talep Detayları
    html += '<div class="col-md-8">';
    html += '<table class="table table-sm table-bordered">';

    if (tip === "avans") {
      html +=
        '<tr><td class="text-muted bg-light" width="40%">Talep Tarihi</td><td>' +
        formatDate(data.talep_tarihi) +
        "</td></tr>";
      html +=
        '<tr><td class="text-muted bg-light">Tutar</td><td class="fs-5 fw-bold text-success">' +
        formatMoney(data.tutar) +
        "</td></tr>";
      if (data.maas_tutari) {
        html +=
          '<tr><td class="text-muted bg-light">Maaş Tutarı</td><td>' +
          formatMoney(data.maas_tutari) +
          "</td></tr>";
      }
      html +=
        '<tr><td class="text-muted bg-light">Durum</td><td><span class="badge bg-warning">' +
        ucfirst(data.durum) +
        "</span></td></tr>";
      if (data.aciklama) {
        html +=
          '<tr><td class="text-muted bg-light">Açıklama</td><td>' +
          data.aciklama +
          "</td></tr>";
      }
    } else if (tip === "izin") {
      html +=
        '<tr><td class="text-muted bg-light" width="40%">Talep Tarihi</td><td>' +
        formatDate(data.talep_tarihi) +
        "</td></tr>";
      html +=
        '<tr><td class="text-muted bg-light">İzin Türü</td><td><span class="badge bg-primary">' +
        (data.izin_tipi_adi || data.izin_tipi || "-") +
        "</span></td></tr>";
      html +=
        '<tr><td class="text-muted bg-light">Tarih Aralığı</td><td>' +
        formatDateOnly(data.baslangic_tarihi) +
        " - " +
        formatDateOnly(data.bitis_tarihi) +
        "</td></tr>";
      html +=
        '<tr><td class="text-muted bg-light">Gün Sayısı</td><td><span class="badge bg-info">' +
        (data.gun_sayisi || "-") +
        " Gün</span></td></tr>";
      html +=
        '<tr><td class="text-muted bg-light">Durum</td><td><span class="badge bg-warning">' +
        ucfirst(data.onay_durumu) +
        "</span></td></tr>";
      if (data.aciklama) {
        html +=
          '<tr><td class="text-muted bg-light">Açıklama</td><td>' +
          data.aciklama +
          "</td></tr>";
      }
    } else if (tip === "talep") {
      if (data.ref_no) {
        html +=
          '<tr><td class="text-muted bg-light" width="40%">Referans No</td><td><code>' +
          data.ref_no +
          "</code></td></tr>";
      }
      html +=
        '<tr><td class="text-muted bg-light">Tarih</td><td>' +
        formatDate(data.olusturma_tarihi) +
        "</td></tr>";
      html +=
        '<tr><td class="text-muted bg-light">Başlık</td><td><strong>' +
        (data.baslik || "-") +
        "</strong></td></tr>";
      html +=
        '<tr><td class="text-muted bg-light">Durum</td><td><span class="badge bg-warning">' +
        ucfirst(data.durum) +
        "</span></td></tr>";
      if (data.aciklama) {
        html +=
          '<tr><td class="text-muted bg-light">Açıklama</td><td>' +
          data.aciklama +
          "</td></tr>";
      }
    }

    html += "</table>";
    html += "</div>";
    html += "</div>";

    return html;
  }

  // Hızlı Onay
  const approveBtn = document.getElementById("btnQuickApprove");
  if (approveBtn) {
    approveBtn.addEventListener("click", function () {
      if (!currentTalepId || !currentTalepTip) return;

      if (typeof Swal !== "undefined") {
        Swal.fire({
          title: "Onayla",
          text: "Bu talebi onaylamak istediğinize emin misiniz?",
          icon: "question",
          showCancelButton: true,
          confirmButtonColor: "#28a745",
          cancelButtonColor: "#6c757d",
          confirmButtonText: "Evet, Onayla",
          cancelButtonText: "İptal",
        }).then((result) => {
          if (result.isConfirmed) {
            performAction(currentTalepTip + "-onayla");
          }
        });
      } else if (confirm("Bu talebi onaylamak istediğinize emin misiniz?")) {
        performAction(currentTalepTip + "-onayla");
      }
    });
  }

  // Hızlı Red
  const rejectBtn = document.getElementById("btnQuickReject");
  if (rejectBtn) {
    rejectBtn.addEventListener("click", function () {
      if (!currentTalepId || !currentTalepTip) return;

      if (typeof Swal !== "undefined") {
        Swal.fire({
          title: "Reddet",
          input: "textarea",
          inputLabel: "Red Açıklaması",
          inputPlaceholder: "Red sebebini giriniz...",
          showCancelButton: true,
          confirmButtonColor: "#dc3545",
          cancelButtonColor: "#6c757d",
          confirmButtonText: "Reddet",
          cancelButtonText: "İptal",
          inputValidator: (value) => {
            if (!value) {
              return "Red açıklaması zorunludur!";
            }
          },
        }).then((result) => {
          if (result.isConfirmed) {
            performAction(currentTalepTip + "-reddet", result.value);
          }
        });
      } else {
        const aciklama = prompt("Red açıklaması giriniz:");
        if (aciklama) {
          performAction(currentTalepTip + "-reddet", aciklama);
        }
      }
    });
  }

  function performAction(action, aciklama) {
    const formData = new FormData();
    formData.append("action", action);
    formData.append("id", currentTalepId);
    if (aciklama) {
      formData.append("aciklama", aciklama);
    }

    fetch(TALEPLER_API, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        const modalEl = document.getElementById("modalTalepOzet");
        if (modalEl) {
          bootstrap.Modal.getInstance(modalEl)?.hide();
        }

        if (data.status === "success") {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "success",
              title: "Başarılı",
              text: data.message,
              timer: 1500,
              showConfirmButton: false,
            }).then(() => location.reload());
          } else {
            alert(data.message);
            location.reload();
          }
        } else {
          if (typeof Swal !== "undefined") {
            Swal.fire("Hata", data.message, "error");
          } else {
            alert("Hata: " + data.message);
          }
        }
      })
      .catch((error) => {
        if (typeof Swal !== "undefined") {
          Swal.fire("Hata", "Bir hata oluştu", "error");
        } else {
          alert("Bir hata oluştu");
        }
      });
  }

  function formatDate(dateStr) {
    if (!dateStr) return "-";
    const date = new Date(dateStr);
    return (
      date.toLocaleDateString("tr-TR") +
      " " +
      date.toLocaleTimeString("tr-TR", { hour: "2-digit", minute: "2-digit" })
    );
  }

  function formatDateOnly(dateStr) {
    if (!dateStr) return "-";
    const date = new Date(dateStr);
    return date.toLocaleDateString("tr-TR");
  }

  function formatMoney(amount) {
    return (
      parseFloat(amount).toLocaleString("tr-TR", { minimumFractionDigits: 2 }) +
      " ₺"
    );
  }

  function ucfirst(str) {
    if (!str) return "";
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  // Global olarak erişilebilir yapma
  window.loadTalepOzet = loadTalepOzet;
});
