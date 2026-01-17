document.addEventListener("DOMContentLoaded", function () {
  // --- ELEMENTLERİ SEÇME ---
  const senderIdSelect = document.getElementById("senderId");
  const recipientsInput = document.getElementById("recipients");
  const recipientsContainer = document.getElementById("recipients-container");
  const wrapper = document.querySelector(".tag-input-wrapper");
  const messageTextarea = document.getElementById("message");
  const charCounter = document.getElementById("char-counter");
  const smsForm = document.getElementById("smsForm");

  // --- MODALDAN SEÇİLENLERİ EKLEME ---
  window.addEventListener("message", function(event) {
    if (event.data && event.data.type === "addRecipients" && Array.isArray(event.data.numbers)) {
      event.data.numbers.forEach(function(number) {
        // Zaten ekli değilse ekle
        if (!recipientsContainer.textContent.includes(number)) {
          createTag(number);
        }
      });
    }
  });
document.addEventListener("DOMContentLoaded", function () {
  // --- ELEMENTLERİ SEÇME ---
  const senderIdSelect = document.getElementById("senderId");
  const recipientsInput = document.getElementById("recipients");
  const recipientsContainer = document.getElementById("recipients-container");
  const wrapper = document.querySelector(".tag-input-wrapper");
  const messageTextarea = document.getElementById("message");
  const charCounter = document.getElementById("char-counter");
  const smsForm = document.getElementById("smsForm");

  // Önizleme elementleri
  const senderIdPreview = document.querySelector(".sender-id-preview");
  const messagePreview = document.getElementById("message-preview");
  const phoneScreen = document.querySelector(".phone-screen");

  // --- OLAY DİNLEYİCİLERİNİ (EVENT LISTENERS) AYARLAMA ---

  // Gönderen Adı değiştiğinde önizlemeyi güncelle
  senderIdSelect.addEventListener("change", updatePreview);

  // Mesaj yazıldığında önizlemeyi ve sayacı güncelle
  messageTextarea.addEventListener("input", () => {
    updatePreview();
    updateCharCounter();
  });

  // Alıcı input'una tıklandığında input'u odakla
  recipientsContainer.addEventListener("click", () => {
    recipientsInput.focus();
  });

  // Alıcı input'unda tuşa basıldığında (Enter veya Virgül) etiketi oluştur
  recipientsInput.addEventListener("keydown", handleRecipientInput);

  // Form gönderildiğinde verileri topla (ve şimdilik konsola yazdır)
  smsForm.addEventListener("submit", handleFormSubmit);

  // --- FONKSİYONLAR ---

  /**
   * Telefon önizlemesini günceller.
   */
  function updatePreview() {
    // Gönderen Adı Önizlemesi
    senderIdPreview.textContent =
      senderIdSelect.options[senderIdSelect.selectedIndex].text;

    // Mesaj Önizlemesi
    const messageText = messageTextarea.value;
    if (messageText.trim() === "") {
      messagePreview.textContent = "Mesajınız burada görünecek...";
    } else {
      messagePreview.textContent = messageText;
    }

    // Mesaj kutusu doldukça otomatik aşağı kaydır
    phoneScreen.scrollTop = phoneScreen.scrollHeight;
  }

  /**
   * Karakter ve SMS sayacını günceller.
   * Türkçe karakterleri (Unicode) hesaba katar.
   */
  function updateCharCounter() {
    const message = messageTextarea.value;
    const length = message.length;

    // Türkçe karakter içerip içermediğini kontrol et
    const hasUnicode = /[ğüşıöçĞÜŞİÖÇ]/.test(message);

    let smsCount = 0;
    let charLimit = 0;

    if (hasUnicode) {
      // Unicode (Türkçe karakterli) SMS limitleri
      if (length === 0) {
        smsCount = 0;
        charLimit = 70;
      } else if (length <= 70) {
        smsCount = 1;
        charLimit = 70;
      } else if (length <= 134) {
        smsCount = 2;
        charLimit = 134;
      } else if (length <= 201) {
        smsCount = 3;
        charLimit = 201;
      } else {
        // Daha fazlası için bu mantığı genişletebilirsiniz
        smsCount = Math.ceil(length / 67);
        charLimit = smsCount * 67;
      }
    } else {
      // Standart GSM karakterli SMS limitleri
      if (length === 0) {
        smsCount = 0;
        charLimit = 160;
      } else if (length <= 160) {
        smsCount = 1;
        charLimit = 160;
      } else if (length <= 306) {
        smsCount = 2;
        charLimit = 306;
      } else if (length <= 459) {
        smsCount = 3;
        charLimit = 459;
      } else {
        // Daha fazlası için bu mantığı genişletebilirsiniz
        smsCount = Math.ceil(length / 153);
        charLimit = smsCount * 153;
      }
    }

    charCounter.textContent = `${length} / ${charLimit} (${smsCount} SMS)`;
  }

  /**
   * Alıcı input'una girilen numaraları etiket (tag) haline getirir.
   * @param {KeyboardEvent} e - Klavye olayı
   */
  function handleRecipientInput(e) {
    if (e.key === "Enter" || e.key === ",") {
      e.preventDefault(); // Formun gönderilmesini veya virgülün yazılmasını engelle

      const number = recipientsInput.value.trim();
      //numaranın içindeki boşlukları kaldır
      const cleanedNumber = number.replace(/\s+/g, "");
      //numaranın başında +90 varsa kaldır
      const cleanedNumberNoPrefix = cleanedNumber.startsWith("+90")
        ? cleanedNumber.slice(3)
        : cleanedNumber;

      //numarada () - . varsa kaldır
      const finalNumber = cleanedNumberNoPrefix.replace(/[\(\)\-\.\s]/g, "");

      if (isValidPhoneNumber(finalNumber)) {
        //Eğer aynı numara eklenmediyse ekle
        if (!recipientsContainer.textContent.includes(finalNumber)) {
          createTag(finalNumber);
          recipientsInput.value = ""; // Input'u temizle
          return;
        }else{
          Toastify({ text: "Bu numara zaten eklenmiş."}).showToast();
        }
      }else{
        Toastify({ text: "Geçersiz bir numara girdiniz."}).showToast();
      }
    }
  }

  /**
   * Verilen metin için bir etiket (tag) oluşturur ve DOM'a ekler.
   * @param {string} text - Etiketin metni (telefon numarası)
   */
  function createTag(text) {
    const tag = document.createElement("span");
    tag.className = "tag";
    tag.textContent = text;

    const closeBtn = document.createElement("span");
    closeBtn.className = "close-tag";
    closeBtn.innerHTML = "×"; // Çarpı işareti
    closeBtn.onclick = function () {
      recipientsContainer.removeChild(tag);
    };

    tag.appendChild(closeBtn);
    // recipientsContainer.insertBefore(tag, recipientsInput);
    // Input'u içeren ".form-floating" div'ini bul ve etiketi onun önüne ekle.
    recipientsContainer.insertBefore(tag, wrapper);
  }

  /**
   * Basit bir telefon numarası formatı kontrolü yapar.
   * @param {string} number - Kontrol edilecek numara
   * @returns {boolean} - Geçerli olup olmadığı
   */
  function isValidPhoneNumber(number) {
    // Bu regex'i projenizin gereksinimlerine göre daha detaylı hale getirebilirsiniz.
    // Örneğin sadece 10 haneli (5 ile başlayan) numaraları kabul edebilir.
    const phoneRegex = /^\d{10,15}$/;
    return phoneRegex.test(number);
  }

  /**
   * Form gönderildiğinde verileri toplar ve sunucuya gönderir.
   * @param {Event} e - Form gönderme olayı
   */
  function handleFormSubmit(e) {
    e.preventDefault(); // Sayfanın yeniden yüklenmesini engelle

    const tags = recipientsContainer.querySelectorAll(".tag");
    const recipients = Array.from(tags).map((tag) =>
      tag.textContent.slice(0, -1)
    ); // Son karakter olan '×' işaretini kaldır

    const formData = {
      senderId: senderIdSelect.value,
      message: messageTextarea.value,
      recipients: recipients
    };

    // Verilerin kontrolü
    if (formData.recipients.length === 0) {
      Toastify({ text: "Lütfen en az bir alıcı numarası girin."}).showToast();

      return;
    }
    if (formData.message.trim() === "") {
      //alert("Lütfen bir mesaj yazın.");
      Toastify({ text: "Lütfen bir mesaj yazın."}).showToast();

      return;
    }

    // --- AJAX İSTEĞİ BURADA YAPILACAK ---
    // console.log("Gönderilecek Veriler:", formData);
    //alert('SMS Gönderiliyor! (Detaylar için konsolu kontrol edin)');

    fetch("views/mail-sms/api/sms.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(formData)
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status) {
          swal
            .fire({
              title: "Başarılı!",
              text: "SMS başarıyla gönderildi.",
              icon: "success",
              confirmButtonText: "Tamam"
            })
            .then(() => {
              // Formu temizle
              const tags = recipientsContainer.querySelectorAll(".tag");
              tags.forEach((tag) => tag.remove());
              messageTextarea.value = "";
              updatePreview();
              updateCharCounter();
            });
        }
      })
      .catch((error) => {
        //console.error('Hata:', error);
        swal.fire({
          title: "Hata!",
          text: "SMS gönderilirken bir sorun oluştu.",
          icon: "error",
          confirmButtonText: "Tamam"
        });
      });
  }

  // Sayfa ilk yüklendiğinde durumu başlat
  updatePreview();
  updateCharCounter();
});
});
