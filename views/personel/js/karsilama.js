(function () {
  var API = "views/personel/api.php";
  var state = { kayitId: null, onKapat: null, gonderildi: false, personelId: null };

  function esc(t) {
    return $("<div>").text(t).html();
  }

  function normalizeTelefon(v) {
    var d = String(v || "").replace(/\D/g, "");
    if (!d) return "";
    if (d.indexOf("90") === 0 && d.length === 12) return d;
    if (d.charAt(0) === "0" && d.length === 11) return "9" + d;
    if (d.length === 10 && d.charAt(0) === "5") return "90" + d;
    return d.length >= 11 && d.length <= 15 ? d : "";
  }

  function onizlemeGuncelle() {
    var metin = $("#karsilama_mesaj").val() || "";
    var link = state.link || "";
    var html = esc(metin);
    if (link) {
      html = html.split(esc(link)).join('<span style="color:#027eb5;text-decoration:underline;">' + esc(link) + "</span>");
    }
    $("#karsilamaOnizleme").html(html);
    $("#karsilamaLinkKart").toggleClass("d-none", !(link && metin.indexOf(link) !== -1));
  }

  function telefonKontrol() {
    var n = normalizeTelefon($("#karsilama_telefon").val());
    $("#karsilamaTelefonUyari").text(n ? "Gönderilecek numara: +" + n : "Geçerli bir cep telefonu girin (5XX XXX XX XX).").toggleClass("text-danger", !n).toggleClass("text-muted", !!n);
    $("#btnKarsilamaGonder").prop("disabled", !n);
    return n;
  }

  function modal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById("karsilamaModal"));
  }

  function durumBildir(durum, cb) {
    if (!state.kayitId) {
      if (cb) cb();
      return;
    }
    $.post(API, { action: "karsilama-mesaji-durum", kayit_id: state.kayitId, durum: durum, telefon: $("#karsilama_telefon").val() })
      .always(function () {
        if (cb) cb();
      });
  }

  function kapat(sonraki) {
    var cb = state.onKapat;
    state.onKapat = null;
    modal().hide();
    if (sonraki !== false && typeof cb === "function") cb();
  }

  function sablonPaneli(goster) {
    $("#karsilamaMesajPaneli, #karsilamaAksiyonlar, #btnKarsilamaSablon").toggleClass("d-none", goster);
    $("#karsilamaSablonPaneli, #btnKarsilamaSablonKaydet, #btnKarsilamaSablonGeri").toggleClass("d-none", !goster);
  }

  function ac(personelId, opts) {
    opts = opts || {};
    state = { kayitId: null, onKapat: opts.onKapat || null, gonderildi: false, personelId: personelId, link: "" };
    $.post(API, { action: "karsilama-mesaji-hazirla", personel_id: personelId }, null, "json")
      .done(function (res) {
        if (!res || res.status !== "success") {
          Swal.fire("Uyarı", (res && res.message) || "Karşılama mesajı hazırlanamadı.", "warning").then(function () {
            if (state.onKapat) state.onKapat();
          });
          return;
        }
        var d = res.data;
        state.kayitId = d.kayit_id;
        state.link = d.link;
        sablonPaneli(false);
        $("#karsilamaPersonelAdi").text(d.adi_soyadi);
        $("#karsilama_telefon").val(d.telefon);
        $("#karsilama_mesaj").val(d.mesaj);
        var bilgi = $("#karsilamaDurumBilgi").addClass("d-none");
        if (d.durum === "gonderildi" && d.gonderim_tarihi) {
          bilgi.removeClass("d-none").text("Bu personele daha önce gönderildi (" + d.gonderim_tarihi + "). Gerekirse tekrar gönderebilirsiniz.");
        }
        telefonKontrol();
        onizlemeGuncelle();
        modal().show();
      })
      .fail(function () {
        Swal.fire("Hata", "Sunucuya ulaşılamadı.", "error").then(function () {
          if (state.onKapat) state.onKapat();
        });
      });
  }

  window.KarsilamaMesaji = { ac: ac };

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.getElementById("karsilamaModal")) return;

    $("#karsilama_telefon").on("input", telefonKontrol);
    $("#karsilama_mesaj").on("input", onizlemeGuncelle);

    $("#btnKarsilamaGonder").on("click", function () {
      var n = telefonKontrol();
      if (!n) return;
      var url = "https://wa.me/" + n + "?text=" + encodeURIComponent($("#karsilama_mesaj").val());
      window.open(url, "_blank", "noopener");
      state.gonderildi = true;
      durumBildir("gonderildi", function () {
        kapat();
      });
    });

    $("[data-karsilama-kapat]").on("click", function () {
      if (state.gonderildi) return kapat();
      durumBildir("atlandi", kapat);
    });

    $("#btnKarsilamaSablon").on("click", function () {
      $.post(API, { action: "karsilama-sablon-getir" }, null, "json").done(function (res) {
        if (!res || res.status !== "success") {
          Swal.fire("Uyarı", (res && res.message) || "Şablon alınamadı.", "warning");
          return;
        }
        $("#sablon_mesaj_metni").val(res.data.mesaj_metni);
        $("#sablon_sayfa_baslik").val(res.data.sayfa_baslik);
        $("#sablon_evraklar").val(res.data.evraklar);
        $("#sablon_banka_adimi").val(res.data.banka_adimi);
        $("#sablon_kase_bilgisi").val(res.data.kase_bilgisi);
        sablonPaneli(true);
      });
    });

    $("#btnKarsilamaSablonGeri").on("click", function () {
      sablonPaneli(false);
    });

    $("#btnKarsilamaSablonKaydet").on("click", function () {
      var $b = $(this).prop("disabled", true);
      $.post(
        API,
        {
          action: "karsilama-sablon-kaydet",
          mesaj_metni: $("#sablon_mesaj_metni").val(),
          sayfa_baslik: $("#sablon_sayfa_baslik").val(),
          evraklar: $("#sablon_evraklar").val(),
          banka_adimi: $("#sablon_banka_adimi").val(),
          kase_bilgisi: $("#sablon_kase_bilgisi").val(),
        },
        null,
        "json",
      )
        .done(function (res) {
          if (res && res.status === "success") {
            Swal.fire({ icon: "success", title: "Şablon kaydedildi", timer: 1200, showConfirmButton: false });
            var onKapat = state.onKapat;
            var pid = state.personelId;
            $.post(API, { action: "karsilama-mesaji-hazirla", personel_id: pid }, null, "json").done(function (r) {
              if (r && r.status === "success") {
                $("#karsilama_mesaj").val(r.data.mesaj);
                state.link = r.data.link;
                onizlemeGuncelle();
              }
              state.onKapat = onKapat;
              sablonPaneli(false);
            });
          } else {
            Swal.fire("Uyarı", (res && res.message) || "Kaydedilemedi.", "warning");
          }
        })
        .always(function () {
          $b.prop("disabled", false);
        });
    });
  });
})();
