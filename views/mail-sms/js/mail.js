document.addEventListener("DOMContentLoaded", function () {
  // --- ELEMENTLERİ SEÇME ---
  const senderAccountSelect = document.getElementById("senderAccount");
  const recipientsInput = document.getElementById("recipients");
  const recipientsContainer = document.getElementById("recipients-container");
  const subjectInput = document.getElementById("subject");
  const mailForm = document.getElementById("mailForm");
  const attachmentInput = document.getElementById("mailAttachments");
  const attachmentList = document.getElementById("attachment-list");

  // Önizleme elementleri
  const previewFrom = document.getElementById("preview-from");
  const previewTo = document.getElementById("preview-to");
  const previewSubject = document.getElementById("preview-subject");
  const previewBody = document.getElementById("preview-body");

  // --- OLAY DİNLEYİCİLERİ ---

  // Gönderen hesap değiştiğinde önizlemeyi güncelle
  senderAccountSelect.addEventListener("change", function () {
    previewFrom.textContent =
      senderAccountSelect.options[senderAccountSelect.selectedIndex].text;
  });

  // Konu değiştiğinde önizlemeyi güncelle
  subjectInput.addEventListener("input", function () {
    previewSubject.textContent =
      subjectInput.value || "Konu burada görünecek...";
  });

  // Alıcı input'una tıklandığında input'u odakla
  recipientsContainer.addEventListener("click", (e) => {
    if (
      e.target === recipientsContainer ||
      e.target.classList.contains("tag-input-wrapper")
    ) {
      recipientsInput.focus();
    }
  });

  // Alıcı input'unda tuşa basıldığında (Enter veya Virgül) etiketi oluştur
  recipientsInput.addEventListener("keydown", handleRecipientInput);

  // Alıcıları temizle
  document
    .getElementById("clear-recipients")
    .addEventListener("click", function () {
      const tags = recipientsContainer.querySelectorAll(".tag");
      tags.forEach((tag) => tag.remove());
      updateRecipientsPreview();
    });

  // Dosya ekleme
  attachmentInput.addEventListener("change", handleFileSelect);

  // Form gönderimi
  mailForm.addEventListener("submit", handleFormSubmit);

  // --- MODALDAN SEÇİLENLERİ EKLEME ---
  window.addEventListener("message", function (event) {
    if (
      event.data &&
      event.data.type === "addRecipients" &&
      Array.isArray(event.data.emails)
    ) {
      event.data.emails.forEach(function (email) {
        if (!isEmailAlreadyAdded(email)) {
          createTag(email);
        }
      });
      updateRecipientsPreview();
    }
  });

  // --- FONKSİYONLAR ---

  function handleRecipientInput(e) {
    if (e.key === "Enter" || e.key === ",") {
      e.preventDefault();
      const email = recipientsInput.value.trim();
      if (isValidEmail(email)) {
        if (!isEmailAlreadyAdded(email)) {
          createTag(email);
          recipientsInput.value = "";
          updateRecipientsPreview();
        } else {
          Toastify({
            text: "Bu e-posta zaten eklenmiş.",
            backgroundColor: "#ffc107",
          }).showToast();
        }
      } else if (email !== "") {
        Toastify({
          text: "Geçersiz bir e-posta adresi girdiniz.",
          backgroundColor: "#dc3545",
        }).showToast();
      }
    }
  }

  function createTag(text) {
    const tag = document.createElement("span");
    tag.className = "tag";
    tag.textContent = text;

    const closeBtn = document.createElement("span");
    closeBtn.className = "close-tag";
    closeBtn.innerHTML = "×";
    closeBtn.onclick = function () {
      tag.remove();
      updateRecipientsPreview();
    };

    tag.appendChild(closeBtn);
    recipientsContainer.querySelector(".tag-input-wrapper").before(tag);
  }

  function isEmailAlreadyAdded(email) {
    const tags = recipientsContainer.querySelectorAll(".tag");
    return Array.from(tags).some(
      (tag) => tag.textContent.replace("×", "").trim() === email,
    );
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function updateRecipientsPreview() {
    const tags = recipientsContainer.querySelectorAll(".tag");
    const emails = Array.from(tags).map((tag) =>
      tag.textContent.replace("×", "").trim(),
    );
    previewTo.textContent =
      emails.length > 0 ? emails.join(", ") : "Alıcılar...";
  }

  function handleFileSelect(e) {
    const files = e.target.files;
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      const item = document.createElement("div");
      item.className = "attachment-item";
      item.innerHTML = `
                <i class="fas fa-file"></i>
                <span>${file.name} (${formatBytes(file.size)})</span>
                <i class="fas fa-times remove-attachment"></i>
            `;
      item.querySelector(".remove-attachment").onclick = function () {
        item.remove();
        // Not: Input'tan dosyayı silmek zordur, genellikle FormData ile gönderirken bu listeyi baz alırız
      };
      attachmentList.appendChild(item);
    }
  }

  function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
  }

  function handleFormSubmit(e) {
    e.preventDefault();

    const tags = recipientsContainer.querySelectorAll(".tag");
    const recipients = Array.from(tags).map((tag) =>
      tag.textContent.replace("×", "").trim(),
    );
    const message = $("#mailMessage").summernote("code");

    if (recipients.length === 0) {
      Toastify({
        text: "Lütfen en az bir alıcı girin.",
        backgroundColor: "#dc3545",
      }).showToast();
      return;
    }

    if (!subjectInput.value.trim()) {
      Toastify({
        text: "Lütfen bir konu girin.",
        backgroundColor: "#dc3545",
      }).showToast();
      return;
    }

    if ($("#mailMessage").summernote("isEmpty")) {
      Toastify({
        text: "Lütfen bir mesaj yazın.",
        backgroundColor: "#dc3545",
      }).showToast();
      return;
    }

    const formData = new FormData(mailForm);
    formData.append("recipients", JSON.stringify(recipients));
    formData.append("message", message);

    const submitBtn = mailForm.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    swal.fire({
      title: "Gönderiliyor...",
      text: "Lütfen bekleyin.",
      allowOutsideClick: false,
      didOpen: () => {
        swal.showLoading();
      },
    });

    fetch("views/mail-sms/api/mail.php", {
      method: "POST",
      body: formData,
    })
      .then(async (response) => {
        const isJson = response.headers
          .get("content-type")
          ?.includes("application/json");
        const data = isJson ? await response.json() : null;

        if (!response.ok) {
          const error = (data && data.message) || response.statusText;
          return Promise.reject(error);
        }

        if (!data) {
          const text = await response.text();
          console.error("Non-JSON response:", text);
          return Promise.reject("Sunucudan geçersiz yanıt alındı.");
        }

        return data;
      })
      .then((data) => {
        if (data.status) {
          swal
            .fire({
              title: "Başarılı!",
              text: "E-posta başarıyla gönderildi.",
              icon: "success",
            })
            .then((result) => {
              if (result.isConfirmed) {
                location.reload();
              }
            });
        } else {
          if (submitBtn) submitBtn.disabled = false;
          swal.fire("Hata!", data.message || "E-posta gönderilemedi.", "error");
        }
      })
      .catch((error) => {
        if (submitBtn) submitBtn.disabled = false;
        console.error("Mail send error:", error);
        swal.fire(
          "Hata!",
          error.toString() || "Bir sistem hatası oluştu.",
          "error",
        );
      });
  }

  // Modal İşlemleri
  $(".kisilerden-sec").on("click", function (e) {
    e.preventDefault();
    $.get(
      "/views/mail-sms/modal/kisi_sec_modal.php?type=mail",
      function (data) {
        $(".kisilerdenSecModalContent").html(data);
        $("#kisilerdenSecModal").modal("show");
      },
    );
  });

  $(".sablon-kullan").on("click", function (e) {
    e.preventDefault();
    $.get(
      "/views/mail-sms/modal/sablondan_sec_modal.php?type=mail",
      function (data) {
        $(".sablondanSecModalContent").html(data);
        $("#sablondanSecModal").modal("show");
      },
    );
  });

  $("#save-as-template").on("click", function () {
    $("#sablonKaydetModal").modal("show");
  });

  $("#sablonKaydetButton").on("click", function () {
    const name = $("#sablonAdi").val();
    const content = $("#mailMessage").summernote("code");

    if (!name) {
      swal.fire("Uyarı!", "Lütfen şablon adı girin.", "warning");
      return;
    }

    $.ajax({
      url: "views/mail-sms/api/mail.php",
      type: "POST",
      data: {
        action: "save_template",
        name: name,
        content: content,
      },
      success: function (response) {
        swal.fire("Başarılı!", "Şablon kaydedildi.", "success");
        $("#sablonKaydetModal").modal("hide");
      },
    });
  });
});
