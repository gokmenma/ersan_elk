(function (window) {
  "use strict";

  const FIRST_POLL_DELAY = 20000;
  const POLL_INTERVAL = 10000;
  const MAX_NETWORK_ERRORS = 5;

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch]));
  }

  function post(apiUrl, data) {
    const body = new FormData();
    Object.keys(data).forEach(key => body.append(key, data[key]));
    return fetch(apiUrl, { method: "POST", body: body, credentials: "same-origin" }).then(response => response.json());
  }

  function baslat(options) {
    const swal = options.swal || window.Swal;
    const apiUrl = options.apiUrl;
    let token = null;
    let finished = false;
    let pollTimer = null;
    let countdownTimer = null;
    let networkErrors = 0;

    const stop = () => {
      finished = true;
      clearTimeout(pollTimer);
      clearInterval(countdownTimer);
    };

    const fail = (title, message, icon) => {
      stop();
      return swal.fire({ icon: icon || "error", title: title, html: escapeHtml(message) });
    };

    swal.fire({
      title: "Mobil İmza",
      html: '<div class="py-2">İmza isteği hazırlanıyor...</div>',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      showCancelButton: true,
      cancelButtonText: "İptal"
    }).then(result => {
      if (finished || result.dismiss !== swal.DismissReason.cancel) return;
      stop();
      if (token) post(apiUrl, { action: "evrak-mobil-imza-iptal", token: token }).catch(() => null);
    });

    const poll = () => {
      if (finished) return;
      post(apiUrl, { action: "evrak-mobil-imza-durum", token: token })
        .then(response => {
          if (finished) return;
          networkErrors = 0;
          if (response.status !== "success") {
            return fail("Mobil İmza Hatası", response.message || "Mobil imza tamamlanamadı.");
          }
          const data = response.data || {};
          if (data.state === "bekliyor" || data.state === "isleniyor") {
            pollTimer = setTimeout(poll, POLL_INTERVAL);
            return;
          }
          if (data.state === "imzalandi") {
            stop();
            let message = escapeHtml(response.message);
            if (data.completed && data.auto_kep) {
              message += "<br><br><b>KEP gönderimi:</b> Mobil imza ile otomatik KEP paketi imzalanamaz. KEP gönderimini evrak listesinden başlatınız.";
            }
            return swal.fire({ icon: "success", title: "Mobil İmza Doğrulandı", html: message })
              .then(() => { if (typeof options.onSuccess === "function") options.onSuccess(response); });
          }
          const titles = { iptal: "İmza İptal Edildi", zaman_asimi: "Süre Doldu", hata: "Mobil İmza Hatası" };
          return fail(titles[data.state] || "Mobil İmza", data.message || "Mobil imza tamamlanamadı.", data.state === "hata" ? "error" : "warning");
        })
        .catch(() => {
          if (finished) return;
          if (++networkErrors >= MAX_NETWORK_ERRORS) {
            return fail("Bağlantı Hatası", "Sunucu ile iletişim kurulamadı. İmza durumu evrak listesinden kontrol edilebilir.");
          }
          pollTimer = setTimeout(poll, POLL_INTERVAL);
        });
    };

    post(apiUrl, { action: "evrak-mobil-imza-baslat", id: options.id })
      .then(response => {
        if (finished) {
          if (response.status === "success" && response.data && response.data.token) {
            post(apiUrl, { action: "evrak-mobil-imza-iptal", token: response.data.token }).catch(() => null);
          }
          return;
        }
        if (response.status !== "success" || !response.data || !response.data.token) {
          return fail("Mobil İmza Başlatılamadı", response.message || "İmza isteği gönderilemedi.");
        }
        token = response.data.token;
        let remaining = parseInt(response.data.timeout, 10) || 120;
        const container = swal.getHtmlContainer();
        if (container) {
          container.innerHTML =
            '<div class="text-center">' +
            '<div class="mb-2"><b>' + escapeHtml(response.data.msisdn) + '</b> numaralı hattınıza imza isteği gönderildi.</div>' +
            '<div class="mb-2">Telefonunuzdaki mesajı kontrol edip <b>mobil imza PIN</b> kodunuzu giriniz.</div>' +
            '<div class="small opacity-75">Kalan süre: <span data-mobil-imza-sure>' + remaining + '</span> sn</div>' +
            '</div>';
        }
        countdownTimer = setInterval(() => {
          remaining = Math.max(0, remaining - 1);
          const counter = container && container.querySelector("[data-mobil-imza-sure]");
          if (counter) counter.textContent = remaining;
        }, 1000);
        pollTimer = setTimeout(poll, FIRST_POLL_DELAY);
      })
      .catch(() => fail("Bağlantı Hatası", "Sunucu ile iletişim kurulamadı."));
  }

  window.MobilImza = { baslat: baslat };
})(window);
