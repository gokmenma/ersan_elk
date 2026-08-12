(function ($) {
  "use strict";

  var analizSonucu = [];
  var belgeEslesmeleri = [];
  var analizDosyalari = [];
  var ocrProgressTimer = null;
  var ocrProgressValue = 0;

  function ocrIlerlemeGuncelle(deger, durum, aciklama) {
    ocrProgressValue = Math.max(0, Math.min(100, Math.round(deger)));
    $("#personelOcrYuzde").text(ocrProgressValue + "%");
    $("#personelOcrProgressBar").css("width", ocrProgressValue + "%")
      .closest(".progress").attr("aria-valuenow", ocrProgressValue);
    if (durum) $("#personelOcrDurum").text(durum);
    if (aciklama) $("#personelOcrAciklama").text(aciklama);
  }

  function ocrIlerlemeBaslat(belgeSayisi) {
    clearInterval(ocrProgressTimer);
    $("#personelOcrBelgeSayisi").text(belgeSayisi + " belge");
    ocrIlerlemeGuncelle(7, "Belgeler hazırlanıyor", "Dosyalar güvenli RAM çalışma alanına alınıyor.");
    ocrProgressTimer = setInterval(function () {
      var artis = ocrProgressValue < 35 ? 7 : (ocrProgressValue < 68 ? 4 : 2);
      var sonraki = Math.min(92, ocrProgressValue + artis);
      var durum = "Metin alanları okunuyor";
      var aciklama = "Tesseract belgeleri bu sunucuda işliyor.";
      if (sonraki >= 42) {
        durum = "Bilgiler ayrıştırılıyor";
        aciklama = "Kimlik, adres ve belge alanları kontrol ediliyor.";
      }
      if (sonraki >= 72) {
        durum = "Sonuçlar doğrulanıyor";
        aciklama = "Alan güvenleri ve belge eşleşmeleri hesaplanıyor.";
      }
      ocrIlerlemeGuncelle(sonraki, durum, aciklama);
    }, 650);
  }

  function ocrIlerlemeDurdur() {
    clearInterval(ocrProgressTimer);
    ocrProgressTimer = null;
  }

  function modal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById("modalPersonelBelgeAnaliz"));
  }

  function secimEkrani() {
    ocrIlerlemeDurdur();
    analizSonucu = [];
    belgeEslesmeleri = [];
    analizDosyalari = [];
    $("#personelBelgeArsivListesi").empty();
    $("#personelBelgeArsivAlani").addClass("d-none");
    $("#personelBelgeSecimAlani").removeClass("d-none");
    $("#personelBelgeAnalizYukleniyor, #personelBelgeAnalizSonuc, #btnPersonelBelgeAlanlariniUygula").addClass("d-none");
    $("#btnPersonelBelgeleriAnalizEt").removeClass("d-none").prop("disabled", false);
  }

  function htmlKacir(value) {
    return $("<div>").text(value == null ? "" : String(value)).html();
  }

  function dosyalariGoster(files) {
    var $liste = $("#personelBelgeDosyaListesi").empty();
    if (!files.length) return;
    var html = '<div class="list-group list-group-flush border rounded">';
    Array.from(files).forEach(function (file, index) {
      html += '<div class="list-group-item d-flex align-items-center justify-content-between py-2">' +
        '<span class="text-truncate"><i class="bx bx-file me-2 text-primary"></i>' + htmlKacir(file.name) + '</span>' +
        '<span class="badge bg-light text-dark ms-2">' + (file.size / 1048576).toFixed(1) + ' MB</span></div>';
    });
    $liste.html(html + "</div>");
  }

  function sonuclariGoster(data) {
    if (Array.isArray(data.alanlar)) {
      analizSonucu = data.alanlar;
    } else {
      analizSonucu = Object.keys(data.alanlar || {}).map(function (alan) {
        return $.extend({alan: alan}, data.alanlar[alan]);
      });
    }
    var hamAlanSayilari = {};
    analizSonucu.forEach(function (bilgi) { hamAlanSayilari[bilgi.alan] = (hamAlanSayilari[bilgi.alan] || 0) + 1; });
    var elenenDusukGuvenSayisi = analizSonucu.filter(function (bilgi) {
      return hamAlanSayilari[bilgi.alan] > 1 && Number(bilgi.guven) < 70;
    }).length;
    analizSonucu = analizSonucu.filter(function (bilgi) {
      return hamAlanSayilari[bilgi.alan] === 1 || Number(bilgi.guven) >= 70;
    });
    belgeEslesmeleri = Array.isArray(data.belgeler) ? data.belgeler : [];
    var $tbody = $("#personelBelgeAlanlar").empty();
    var alanSayilari = {};
    analizSonucu.forEach(function (bilgi) { alanSayilari[bilgi.alan] = (alanSayilari[bilgi.alan] || 0) + 1; });
    var otomatikAdaylar = {};
    Object.keys(alanSayilari).forEach(function (alan) {
      var adaylar = analizSonucu.map(function (bilgi, index) { return {bilgi: bilgi, index: index}; })
        .filter(function (aday) { return aday.bilgi.alan === alan; })
        .sort(function (a, b) { return Number(b.bilgi.guven) - Number(a.bilgi.guven); });
      if (adaylar.length === 1) {
        otomatikAdaylar[alan] = Number(adaylar[0].bilgi.guven) >= 60 ? adaylar[0].index : -1;
      } else if (Number(adaylar[0].bilgi.guven) > 80 && Number(adaylar[1].bilgi.guven) < 70) {
        otomatikAdaylar[alan] = adaylar[0].index;
      } else {
        otomatikAdaylar[alan] = -1;
      }
    });
    analizSonucu.forEach(function (bilgi, adayIndex) {
      var alan = bilgi.alan;
      var mevcut = $("#personelForm [name='" + alan + "']").val();
      var cakisma = mevcut && String(mevcut).trim() !== "" && String(mevcut).trim() !== String(bilgi.deger).trim();
      var birdenFazlaAday = alanSayilari[alan] > 1;
      var guvenRenk = bilgi.guven >= 85 ? "success" : (bilgi.guven >= 60 ? "warning" : "danger");
      var $tr = $("<tr>", {class: "personel-belge-aday-satiri", tabindex: 0});
      var guvenli = Number(bilgi.guven) >= 60;
      $tr.append($("<td>").append($("<input>", {
        type: "checkbox",
        class: "form-check-input personel-belge-alan",
        value: adayIndex,
        "data-alan": alan,
        checked: otomatikAdaylar[alan] === adayIndex && !cakisma && guvenli
      })));
      $tr.append($("<td>").append($("<span>", {class: "fw-semibold", text: bilgi.etiket || alan}))
        .append(birdenFazlaAday ? $("<small>", {class: "d-block text-info", text: "Birden fazla belgede bulundu"}) : "")
        .append(cakisma ? $("<small>", {class: "d-block text-warning", text: "Mevcut değer farklı"}) : "")
        .append(!guvenli ? $("<small>", {class: "d-block text-danger", text: "Düşük güven — otomatik seçilmedi"}) : ""));
      var $degerAlani = alan === "adres"
        ? $("<textarea>", {
            class: "form-control form-control-sm personel-belge-duzenlenebilir",
            rows: 4,
            "data-aday-index": adayIndex,
            "aria-label": (bilgi.etiket || alan) + " bulunan değer"
          }).val(bilgi.deger)
        : $("<input>", {
            type: "text",
            class: "form-control form-control-sm personel-belge-duzenlenebilir",
            value: bilgi.deger,
            "data-aday-index": adayIndex,
            "aria-label": (bilgi.etiket || alan) + " bulunan değer"
          });
      $tr.append($("<td>").append($degerAlani));
      $tr.append($("<td>").append($("<small>", {class: "text-muted", text: bilgi.kaynak || "Belge"})));
      $tr.append($("<td>").append($("<span>", {class: "badge bg-" + guvenRenk, text: "%" + bilgi.guven})));
      $tbody.append($tr);
    });
    if (!analizSonucu.length) {
      $tbody.append('<tr><td colspan="5" class="text-center text-muted py-4">Belgelerde forma aktarılabilecek bir bilgi bulunamadı.</td></tr>');
    }

    var uyarilar = Array.isArray(data.uyarilar) ? data.uyarilar : [];
    if (elenenDusukGuvenSayisi > 0) {
      uyarilar.unshift(elenenDusukGuvenSayisi + " mükerrer düşük güvenli aday (%70 altı) sonuçlardan çıkarıldı.");
    }
    $("#personelBelgeUyarilar").html(uyarilar.length
      ? '<div class="alert alert-warning py-2"><i class="bx bx-info-circle me-1"></i>' + uyarilar.map(htmlKacir).join("<br>") + "</div>"
      : "");
    arsivEslesmeleriniGoster();
    $("#personelBelgeAnalizYukleniyor").addClass("d-none");
    $("#personelBelgeAnalizSonuc").removeClass("d-none");
    $("#btnPersonelBelgeAlanlariniUygula").toggleClass("d-none", !analizSonucu.length && !belgeEslesmeleri.length);
  }

  function arsivEslesmeleriniGoster() {
    var turAdlari = {
      ehliyet: "Ehliyet", ikametgah: "İkametgah", adli_sicil_kaydi: "Adli Sicil Kaydı",
      nufus_kayit_ornegi: "Nüfus Kayıt Örneği", gizlilik_taahhutnamesi: "Gizlilik Taahhütnamesi",
      sozlesme: "Sözleşme", kimlik: "Kimlik", diploma: "Diploma", cv: "CV",
      saglik_raporu: "Sağlık Raporu", sertifika: "Sertifika", diger: "Diğer"
    };
    var $liste = $("#personelBelgeArsivListesi").empty();
    analizDosyalari.forEach(function (file, index) {
      var belge = belgeEslesmeleri.find(function (item) { return Number(item.sira) === index + 1; }) || {
        sira: index + 1, evrak_turu: "diger", evrak_adi: "Türü belirlenemeyen personel evrakı", guven: 0
      };
      var guvenRenk = belge.guven >= 85 ? "success" : (belge.guven >= 60 ? "warning" : "secondary");
      var $satir = $("<label>", {class: "list-group-item d-flex align-items-center gap-3"});
      $satir.append($("<input>", {type: "checkbox", class: "form-check-input personel-belge-arsiv", value: index, checked: belge.evrak_turu !== "diger"}));
      var $metin = $("<span>", {class: "flex-grow-1 text-truncate"});
      $metin.append($("<span>", {class: "d-block fw-semibold text-truncate", text: file.name}));
      $metin.append($("<small>", {class: "text-muted", text: belge.evrak_adi || "Personel Evrakı"}));
      $satir.append($metin);
      $satir.append($("<span>", {class: "badge bg-soft-primary text-primary", text: turAdlari[belge.evrak_turu] || "Diğer"}));
      $satir.append($("<span>", {class: "badge bg-" + guvenRenk, text: belge.guven > 0 ? "%" + belge.guven : "Kontrol"}));
      $liste.append($satir);
    });
    $("#personelBelgeArsivAlani").toggleClass("d-none", !$liste.children().length);
    $("#personelBelgeTumunuArsivle").prop("checked", $(".personel-belge-arsiv:not(:checked)").length === 0);
  }

  $(document).on("click", "#btnPersonelBelgeAnalizAc", function () {
    secimEkrani();
    $("#personelBelgeDosyalari").val("");
    $("#personelBelgeDosyaListesi").empty();
    modal().show();
  });

  $(document).on("hidden.bs.modal", "#modalPersonelBelgeAnaliz", function () {
    ocrIlerlemeDurdur();
  });

  $(document).on("change", "#personelBelgeTumunuArsivle", function () {
    $(".personel-belge-arsiv").prop("checked", this.checked);
  });

  $(document).on("change", ".personel-belge-arsiv", function () {
    $("#personelBelgeTumunuArsivle").prop("checked", $(".personel-belge-arsiv:not(:checked)").length === 0);
  });

  $(document).on("focus input", ".personel-belge-duzenlenebilir", function () {
    var $secim = $(this).closest("tr").find(".personel-belge-alan");
    $(".personel-belge-alan[data-alan='" + $secim.data("alan") + "']").not($secim).prop("checked", false);
    $secim.prop("checked", true);
  });

  $(document).on("change", ".personel-belge-alan", function () {
    if (this.checked) {
      $(".personel-belge-alan[data-alan='" + $(this).data("alan") + "']").not(this).prop("checked", false);
    }
  });

  $(document).on("click", ".personel-belge-aday-satiri", function (event) {
    if ($(event.target).is("input, textarea, select, button, a")) return;
    var $secim = $(this).find(".personel-belge-alan");
    var yeniDurum = !$secim.prop("checked");
    if (yeniDurum) {
      $(".personel-belge-alan[data-alan='" + $secim.data("alan") + "']").not($secim).prop("checked", false);
    }
    $secim.prop("checked", yeniDurum).trigger("change");
  });

  $(document).on("keydown", ".personel-belge-aday-satiri", function (event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      var $secim = $(this).find(".personel-belge-alan");
      var yeniDurum = !$secim.prop("checked");
      if (yeniDurum) {
        $(".personel-belge-alan[data-alan='" + $secim.data("alan") + "']").not($secim).prop("checked", false);
      }
      $secim.prop("checked", yeniDurum).trigger("change");
    }
  });

  $(document).on("change", "#personelBelgeDosyalari", function () {
    dosyalariGoster(this.files || []);
  });

  $(document).on("click", "#btnPersonelBelgeYeniAnaliz", secimEkrani);

  $(document).on("click", "#btnPersonelBelgeleriAnalizEt", function () {
    var input = document.getElementById("personelBelgeDosyalari");
    var files = input.files || [];
    if (!files.length) {
      Swal.fire("Belge Seçiniz", "Analiz için en az bir belge yükleyin.", "warning");
      return;
    }
    if (files.length > 6) {
      Swal.fire("Belge Sınırı", "Tek seferde en fazla 6 belge seçebilirsiniz.", "warning");
      return;
    }
    var buyukDosya = Array.from(files).find(function (file) { return file.size > 10 * 1024 * 1024; });
    if (buyukDosya) {
      Swal.fire("Dosya Boyutu", buyukDosya.name + " 10 MB sınırını geçiyor.", "warning");
      return;
    }
    var data = new FormData();
    analizDosyalari = Array.from(files);
    Array.from(files).forEach(function (file) { data.append("personel_belgeleri[]", file, file.name); });
    data.append("personel_id", $("#personel_id").val() || "0");
    data.append("csrf_token", $("#personelBelgeCsrf").val() || "");
    $("#personelBelgeSecimAlani, #btnPersonelBelgeleriAnalizEt").addClass("d-none");
    $("#personelBelgeAnalizYukleniyor").removeClass("d-none");
    ocrIlerlemeBaslat(files.length);

    $.ajax({
      url: "views/personel/ajax/personel-evrak-ai-analiz.php",
      method: "POST",
      data: data,
      processData: false,
      contentType: false,
      dataType: "json"
    }).done(function (response) {
      if (response.status !== "success") throw new Error(response.message || "Analiz yapılamadı.");
      ocrIlerlemeDurdur();
      ocrIlerlemeGuncelle(100, "Okuma tamamlandı", "Sonuçlar gösterime hazırlanıyor.");
      setTimeout(function () { sonuclariGoster(response.data || {}); }, 350);
    }).fail(function (xhr) {
      ocrIlerlemeDurdur();
      secimEkrani();
      var response = xhr.responseJSON || {};
      Swal.fire("Analiz Başarısız", response.message || "Belgeler analiz edilemedi.", "error");
    });
  });

  $(document).on("click", "#btnPersonelBelgeAlanlariniUygula", function () {
    var sayi = 0;
    $(".personel-belge-alan:checked").each(function () {
      var bilgi = analizSonucu[Number(this.value)];
      if (!bilgi) return;
      var alan = bilgi.alan;
      var $input = $("#personelForm [name='" + alan + "']");
      if (!$input.length || !bilgi) return;
      var duzenlenmisDeger = $(".personel-belge-duzenlenebilir[data-aday-index='" + Number(this.value) + "']").val();
      duzenlenmisDeger = String(duzenlenmisDeger == null ? "" : duzenlenmisDeger).trim();
      if (!duzenlenmisDeger) return;
      bilgi.deger = duzenlenmisDeger;
      $input.val(duzenlenmisDeger).trigger("change");
      $input.addClass("border-success");
      setTimeout(function () { $input.removeClass("border-success"); }, 2500);
      sayi++;
    });
    var arsivSayisi = $(".personel-belge-arsiv:checked").length;
    if (!sayi && !arsivSayisi) {
      Swal.fire("Seçim Yapınız", "Forma aktarılacak veya evraklara kaydedilecek en az bir seçim yapın.", "warning");
      return;
    }
    modal().hide();
    var mesaj = sayi ? sayi + " bilgi forma aktarıldı." : "";
    if (arsivSayisi) mesaj += (mesaj ? " " : "") + arsivSayisi + " evrak personel kaydında arşivlenecek.";
    Swal.fire({
      title: "Belgeler Yerel OCR ile Okundu",
      text: mesaj + " Kaydetmeden önce bilgileri kontrol edin.",
      icon: "success",
      confirmButtonText: "Tamam"
    });
  });

  window.personelAiEvraklariniFormDataEkle = function (formData) {
    var eklenen = 0;
    $(".personel-belge-arsiv:checked").each(function () {
      var index = Number(this.value);
      var file = analizDosyalari[index];
      var eslesme = belgeEslesmeleri.find(function (item) { return Number(item.sira) === index + 1; });
      if (!file || !eslesme) return;
      formData.append("ai_evrak_dosyalari[]", file, file.name);
      formData.append("ai_evrak_turleri[]", eslesme.evrak_turu);
      formData.append("ai_evrak_adlari[]", eslesme.evrak_adi || file.name);
      eklenen++;
    });
    if (eklenen > 0) formData.append("ai_evrak_csrf", $("#personelBelgeCsrf").val() || "");
    return eklenen;
  };
})(jQuery);
