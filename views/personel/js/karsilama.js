(function () {
  var API = "views/personel/api.php";
  var ORNEK_LINK = "https://…/karsilama.php?t=ornek";
  var state = { kayitId: null, onKapat: null, gonderildi: false, personelId: null, link: "", orijinalMesaj: "", adSoyad: "" };

  function esc(t) {
    return $("<div>").text(t == null ? "" : t).html();
  }

  function bashar(ad) {
    var p = String(ad || "").trim().split(/\s+/);
    return ((p[0] || "?").charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : "")).toLocaleUpperCase("tr-TR");
  }

  function normalizeTelefon(v) {
    var d = String(v || "").replace(/\D/g, "");
    if (!d) return "";
    if (d.indexOf("90") === 0 && d.length === 12) return d;
    if (d.charAt(0) === "0" && d.length === 11) return "9" + d;
    if (d.length === 10 && d.charAt(0) === "5") return "90" + d;
    return d.length >= 11 && d.length <= 15 ? d : "";
  }

  function linkluHtml(metin, link) {
    var html = esc(metin);
    if (link) {
      html = html.split(esc(link)).join('<span style="color:#027eb5;text-decoration:underline;">' + esc(link) + "</span>");
    }
    return html;
  }

  function kopyala(metin, $btn) {
    var etiket = $btn.find("span").text();
    var bitti = function (ok) {
      $btn.toggleClass("km-ok", ok).find("span").text(ok ? "Kopyalandı ✓" : "Kopyalanamadı");
      setTimeout(function () {
        $btn.removeClass("km-ok").find("span").text(etiket);
      }, 1600);
    };
    var yedek = function () {
      var ta = document.createElement("textarea");
      ta.value = metin;
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.getElementById("karsilamaModal").appendChild(ta);
      ta.select();
      var ok = false;
      try {
        ok = document.execCommand("copy");
      } catch (e) {}
      ta.remove();
      bitti(ok);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(metin).then(function () {
        bitti(true);
      }, yedek);
    } else {
      yedek();
    }
  }

  function onizlemeGuncelle() {
    var metin = $("#karsilama_mesaj").val() || "";
    $("#karsilamaOnizleme").html(linkluHtml(metin, state.link));
    $("#karsilamaLinkKart").toggleClass("d-none", !(state.link && metin.indexOf(state.link) !== -1));
    $("#karsilamaKarakter").text(metin.length + " karakter");
  }

  function telefonKontrol() {
    var n = normalizeTelefon($("#karsilama_telefon").val());
    $("#karsilamaTelefonUyari")
      .html(n ? '<i class="bx bx-check-circle text-success"></i> Gönderilecek numara: +' + n : '<i class="bx bx-error-circle"></i> Geçerli bir cep telefonu girin (5XX XXX XX XX).')
      .toggleClass("text-danger", !n);
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
    $.post(API, { action: "karsilama-mesaji-durum", kayit_id: state.kayitId, durum: durum, telefon: $("#karsilama_telefon").val() }).always(function () {
      if (cb) cb();
    });
  }

  function kapat() {
    var cb = state.onKapat;
    state.onKapat = null;
    modal().hide();
    if (typeof cb === "function") cb();
  }

  function sablonPaneli(goster) {
    $("#karsilamaMesajPaneli, #karsilamaAksiyonlar, #btnKarsilamaSablon").toggleClass("d-none", goster);
    $("#karsilamaSablonPaneli, #karsilamaSablonAksiyonlar, #btnKarsilamaSablonGeri, #btnKarsilamaSablonVarsayilan").toggleClass("d-none", !goster);
    $("#karsilamaBaslik").text(goster ? "Mesaj Şablonu" : "Karşılama Mesajı");
    $("#karsilamaAltBilgi").text(goster ? "Şablondaki değişiklikler yeni mesajlara uygulanır" : "WhatsApp ile işe alım bilgilendirmesi");
    $("#karsilamaDurumBilgi").toggleClass("d-none", goster || !$("#karsilamaDurumBilgi").text());
  }

  function sablonMesajOnizleme() {
    var m = $("#sablon_mesaj_metni").val() || "";
    var g = m.split("{ad_soyad}").join(state.adSoyad || "Ahmet Yılmaz").split("{firma_adi}").join("Firma Adı").split("{link}").join(ORNEK_LINK);
    if (m.indexOf("{link}") === -1) g = g.replace(/\s+$/, "") + "\n\n" + ORNEK_LINK;
    $("#kmSablonOnizleme").html(linkluHtml(g, ORNEK_LINK));
  }

  function fotografMi(ad) {
    return /foto[gğ]raf|vesikal[ıi]k|biyometrik/i.test(String(ad || "").replace(/İ/g, "i").replace(/I/g, "ı"));
  }

  function evrakSatiri(ad, not, elden) {
    var $r = $(
      '<div class="km-evrak-row">' +
        '<span class="km-no"></span>' +
        '<div class="flex-grow-1">' +
        '<input type="text" class="form-control form-control-sm km-ad" maxlength="200" placeholder="Evrak adı">' +
        '<input type="text" class="form-control form-control-sm km-not" maxlength="300" placeholder="Açıklama / not (isteğe bağlı)">' +
        '<div class="form-check form-switch mt-2 km-elden-wrap"><input class="form-check-input km-elden" type="checkbox" role="switch"><label class="form-check-label font-size-12 km-elden-label">Elden teslim (dijital yüklenemez)</label></div>' +
        "</div>" +
        '<div class="d-flex flex-column">' +
        '<button type="button" class="km-mini-btn km-up" title="Yukarı taşı"><i class="bx bx-chevron-up"></i></button>' +
        '<button type="button" class="km-mini-btn km-down" title="Aşağı taşı"><i class="bx bx-chevron-down"></i></button>' +
        "</div>" +
        '<button type="button" class="km-mini-btn km-del" title="Sil"><i class="bx bx-trash"></i></button>' +
        "</div>",
    );
    $r.find(".km-ad").val(ad || "");
    $r.find(".km-not").val(not || "");
    $r.find(".km-elden").prop("checked", !!elden);
    return $r;
  }

  function evraklariYukle(metin) {
    var $liste = $("#kmEvrakListe").empty();
    var son = null;
    String(metin || "")
      .split("\n")
      .forEach(function (satir) {
        satir = satir.trim();
        if (!satir) return;
        if (satir.charAt(0) === "-" && son) {
          var n = satir.replace(/^[-\s]+/, "");
          var $n = son.find(".km-not");
          $n.val($n.val() ? $n.val() + "; " + n : n);
        } else {
          var elden = /^\[elden\]/i.test(satir);
          satir = satir.replace(/^\[elden\]\s*/i, "");
          son = evrakSatiri(satir, "", elden).appendTo($liste);
        }
      });
    evrakGuncelle();
  }

  function evrakMetni() {
    var out = [];
    $("#kmEvrakListe .km-evrak-row").each(function () {
      var ad = $(this).find(".km-ad").val().trim();
      var not = $(this).find(".km-not").val().trim();
      if (!ad) return;
      var elden = $(this).find(".km-elden").prop("checked") || fotografMi(ad);
      out.push((elden ? "[elden] " : "") + ad.replace(/^-+\s*/, ""));
      if (not) out.push("- " + not);
    });
    return out.join("\n");
  }

  function evrakGuncelle() {
    var $satirlar = $("#kmEvrakListe .km-evrak-row");
    var html = "";
    var n = 0;
    $satirlar.each(function (i) {
      $(this).find(".km-no").text(i + 1);
      $(this).find(".km-up").prop("disabled", i === 0);
      $(this).find(".km-down").prop("disabled", i === $satirlar.length - 1);
      var ad = $(this).find(".km-ad").val().trim();
      var not = $(this).find(".km-not").val().trim();
      var zorunlu = fotografMi(ad);
      var $sw = $(this).find(".km-elden");
      if (zorunlu) $sw.prop("checked", true);
      $sw.prop("disabled", zorunlu);
      $(this).find(".km-elden-label").text(zorunlu ? "Fotoğraf her zaman elden teslim edilir" : "Elden teslim (dijital yüklenemez)");
      var elden = $sw.prop("checked");
      if (ad) {
        n++;
        html += "<li><i></i><span>" + esc(ad) + (elden ? ' <small style="display:inline;color:#8a5a00;font-weight:700;">· Elden teslim</small>' : ' <small style="display:inline;color:#0f7a6d;font-weight:700;">· Dijital yükleme</small>') + (not ? "<small>" + esc(not) + "</small>" : "") + "</span></li>";
      }
    });
    $("#kmEvrakOnizleme").html(html || '<li class="text-muted">Henüz evrak eklenmedi.</li>');
    $("#kmEvrakSayisi").text(n + " madde");
  }

  function bankaKaseOnizleme() {
    var b = ($("#sablon_banka_adimi").val() || "").trim();
    $("#kmBankaOnizleme").html(b ? "<b>Maaş Hesabı</b><br>" + esc(b).replace(/\n/g, "<br>") : '<span class="text-muted">Banka adımı gösterilmeyecek.</span>');
    var k = ($("#sablon_kase_bilgisi").val() || "").split("\n").map(function (x) { return x.trim(); }).filter(Boolean);
    $("#kmKaseOnizleme").html(k.length ? "<b>" + esc(k[0]) + "</b><br>" + k.slice(1).map(esc).join("<br>") : '<span class="text-muted">Kaşe bilgisi gösterilmeyecek.</span>');
  }

  function sablonDoldur(d) {
    $("#sablon_mesaj_metni").val(d.mesaj_metni);
    $("#sablon_sayfa_baslik").val(d.sayfa_baslik);
    $("#sablon_banka_adimi").val(d.banka_adimi);
    $("#sablon_kase_bilgisi").val(d.kase_bilgisi);
    evraklariYukle(d.evraklar);
    sablonMesajOnizleme();
    bankaKaseOnizleme();
  }

  function sablonGetir(varsayilan, cb) {
    $.post(API, { action: "karsilama-sablon-getir", varsayilan: varsayilan ? "1" : "0" }, null, "json").done(function (res) {
      if (!res || res.status !== "success") {
        Swal.fire("Uyarı", (res && res.message) || "Şablon alınamadı.", "warning");
        return;
      }
      sablonDoldur(res.data);
      if (cb) cb();
    });
  }

  function mesajYukle(d) {
    state.kayitId = d.kayit_id;
    state.link = d.link;
    state.orijinalMesaj = d.mesaj;
    state.adSoyad = d.adi_soyadi;
    $("#karsilamaPersonelAdi").text(d.adi_soyadi);
    $("#karsilamaOnizlemeAd").text(d.adi_soyadi);
    $("#karsilamaAvatar, #karsilamaMiniAvatar").text(bashar(d.adi_soyadi));
    $("#karsilama_mesaj").val(d.mesaj);
    $("#btnKarsilamaSayfaAc").attr("href", d.link);
  }

  function ac(personelId, opts) {
    opts = opts || {};
    state = { kayitId: null, onKapat: opts.onKapat || null, gonderildi: false, personelId: personelId, link: "", orijinalMesaj: "", adSoyad: "" };
    $.post(API, { action: "karsilama-mesaji-hazirla", personel_id: personelId }, null, "json")
      .done(function (res) {
        if (!res || res.status !== "success") {
          Swal.fire("Uyarı", (res && res.message) || "Karşılama mesajı hazırlanamadı.", "warning").then(function () {
            if (state.onKapat) state.onKapat();
          });
          return;
        }
        var d = res.data;
        mesajYukle(d);
        $("#karsilama_telefon").val(d.telefon);
        var bilgi = $("#karsilamaDurumBilgi").addClass("d-none").text("");
        if (d.durum === "gonderildi" && d.gonderim_tarihi) {
          bilgi.removeClass("d-none").text("Bu personele daha önce gönderildi (" + d.gonderim_tarihi + "). Gerekirse tekrar gönderebilirsiniz.");
        }
        sablonPaneli(false);
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
    var $m = $("#karsilamaModal");

    $("#karsilama_telefon").on("input", telefonKontrol);
    $("#karsilama_mesaj").on("input", onizlemeGuncelle);

    $("#btnKarsilamaMesajKopyala").on("click", function () {
      kopyala($("#karsilama_mesaj").val(), $(this));
    });
    $("#btnKarsilamaLinkKopyala").on("click", function () {
      kopyala(state.link, $(this));
    });
    $("#btnKarsilamaMesajSifirla").on("click", function () {
      $("#karsilama_mesaj").val(state.orijinalMesaj);
      onizlemeGuncelle();
    });

    $("#btnKarsilamaGonder").on("click", function () {
      var n = telefonKontrol();
      if (!n) return;
      window.open("https://wa.me/" + n + "?text=" + encodeURIComponent($("#karsilama_mesaj").val()), "_blank", "noopener");
      state.gonderildi = true;
      durumBildir("gonderildi", kapat);
    });

    $m.on("click", "[data-karsilama-kapat]", function () {
      if (state.gonderildi) return kapat();
      durumBildir("atlandi", kapat);
    });

    $("#btnKarsilamaSablon").on("click", function () {
      sablonGetir(false, function () {
        $("#karsilamaSablonPaneli .nav-link").first().tab("show");
        sablonPaneli(true);
      });
    });
    $("#btnKarsilamaSablonGeri").on("click", function () {
      sablonPaneli(false);
    });
    $("#btnKarsilamaSablonVarsayilan").on("click", function () {
      Swal.fire({ title: "Varsayılana dönülsün mü?", text: "Formdaki değişiklikler varsayılan şablonla değişir (kaydedene kadar uygulanmaz).", icon: "question", showCancelButton: true, confirmButtonText: "Evet", cancelButtonText: "Vazgeç" }).then(function (r) {
        if (r.isConfirmed) sablonGetir(true);
      });
    });

    $("#sablon_mesaj_metni").on("input", sablonMesajOnizleme);
    $("#sablon_banka_adimi, #sablon_kase_bilgisi").on("input", bankaKaseOnizleme);

    $m.on("click", "[data-km-var]", function () {
      var el = document.getElementById("sablon_mesaj_metni");
      var v = $(this).data("km-var");
      var bas = el.selectionStart == null ? el.value.length : el.selectionStart;
      var son = el.selectionEnd == null ? bas : el.selectionEnd;
      el.value = el.value.slice(0, bas) + v + el.value.slice(son);
      el.focus();
      el.selectionStart = el.selectionEnd = bas + v.length;
      sablonMesajOnizleme();
    });

    $("#btnKmEvrakEkle").on("click", function () {
      var $s = evrakSatiri("", "").appendTo("#kmEvrakListe");
      evrakGuncelle();
      $s.find(".km-ad").trigger("focus");
    });
    $m.on("input change", "#kmEvrakListe .form-control, #kmEvrakListe .km-elden", evrakGuncelle);
    $m.on("click", "#kmEvrakListe .km-del", function () {
      $(this).closest(".km-evrak-row").remove();
      evrakGuncelle();
    });
    $m.on("click", "#kmEvrakListe .km-up", function () {
      var $r = $(this).closest(".km-evrak-row");
      $r.prev().before($r);
      evrakGuncelle();
    });
    $m.on("click", "#kmEvrakListe .km-down", function () {
      var $r = $(this).closest(".km-evrak-row");
      $r.next().after($r);
      evrakGuncelle();
    });

    $("#btnKarsilamaSablonKaydet").on("click", function () {
      var evraklar = evrakMetni();
      var mesaj = $("#sablon_mesaj_metni").val().trim();
      var baslik = $("#sablon_sayfa_baslik").val().trim();
      if (!mesaj || !baslik || !evraklar) {
        Swal.fire("Eksik bilgi", "Mesaj metni, sayfa başlığı ve en az bir evrak girilmelidir.", "warning");
        return;
      }
      var $b = $(this).prop("disabled", true);
      $.post(
        API,
        { action: "karsilama-sablon-kaydet", mesaj_metni: mesaj, sayfa_baslik: baslik, evraklar: evraklar, banka_adimi: $("#sablon_banka_adimi").val(), kase_bilgisi: $("#sablon_kase_bilgisi").val() },
        null,
        "json",
      )
        .done(function (res) {
          if (!res || res.status !== "success") {
            Swal.fire("Uyarı", (res && res.message) || "Kaydedilemedi.", "warning");
            return;
          }
          $.post(API, { action: "karsilama-mesaji-hazirla", personel_id: state.personelId }, null, "json").done(function (r) {
            if (r && r.status === "success") {
              mesajYukle(r.data);
              onizlemeGuncelle();
            }
            sablonPaneli(false);
            Swal.fire({ icon: "success", title: "Şablon kaydedildi", text: "Mesaj yeni şablona göre yenilendi.", timer: 1600, showConfirmButton: false });
          });
        })
        .always(function () {
          $b.prop("disabled", false);
        });
    });
  });
})();
