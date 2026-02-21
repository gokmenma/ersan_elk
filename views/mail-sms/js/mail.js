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

  // --- AUTOCOMPLETE: API'den verileri çek ve listele ---
  const autocompleteContainer = document.createElement("div");
  autocompleteContainer.className =
    "autocomplete-dropdown list-group shadow position-absolute w-100";
  autocompleteContainer.style.display = "none";
  autocompleteContainer.style.zIndex = "1000";
  autocompleteContainer.style.maxHeight = "250px";
  autocompleteContainer.style.overflowY = "auto";
  autocompleteContainer.style.top = "100%";
  autocompleteContainer.style.left = "0";
  autocompleteContainer.style.marginTop = "2px";

  // Tag input wrapper'ın position ayarını yapalım ki absolute elementler düzgün dursun
  recipientsContainer.style.position = "relative";
  recipientsContainer.appendChild(autocompleteContainer);

  let debounceTimer;

  recipientsInput.addEventListener("input", function () {
    const q = this.value.trim();
    if (q.length < 2) {
      autocompleteContainer.style.display = "none";
      return;
    }

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetch(
        `views/mail-sms/api/get_contacts.php?type=mail&q=${encodeURIComponent(q)}`,
      )
        .then((res) => res.json())
        .then((data) => {
          autocompleteContainer.innerHTML = "";
          if (data.length === 0) {
            autocompleteContainer.style.display = "none";
            return;
          }

          data.forEach((item) => {
            const div = document.createElement("a");
            div.className =
              "list-group-item list-group-item-action cursor-pointer d-flex justify-content-between align-items-center";
            div.style.cursor = "pointer";
            div.innerHTML = `<div><span class="fw-semibold">${item.name}</span><br><small class="text-muted">${item.value}</small></div><span class="badge bg-light text-dark">${item.desc}</span>`;

            div.onclick = function () {
              if (!isEmailAlreadyAdded(item.value)) {
                createTag(item.value);
                updateRecipientsPreview();
              } else {
                Toastify({
                  text: "Bu e-posta zaten eklenmiş.",
                  backgroundColor: "#ffc107",
                }).showToast();
              }
              recipientsInput.value = "";
              autocompleteContainer.style.display = "none";
              recipientsInput.focus();
            };
            autocompleteContainer.appendChild(div);
          });
          autocompleteContainer.style.display = "block";
        })
        .catch((err) => console.error("Autocomplete fetch error: ", err));
    }, 300);
  });

  // Dışarı tıklanınca listeyi gizle
  document.addEventListener("click", function (e) {
    if (!recipientsContainer.contains(e.target)) {
      autocompleteContainer.style.display = "none";
    }
  });

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

    const submitBtn = document.querySelector('button[form="mailForm"]');
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
