(function ($) {
  "use strict";

  var analizSonucu = {};
  var belgeEslesmeleri = [];
  var analizDosyalari = [];

  function modal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById("modalPersonelBelgeAnaliz"));
  }

  function secimEkrani() {
    analizSonucu = {};
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
    analizSonucu = data.alanlar || {};
    belgeEslesmeleri = Array.isArray(data.belgeler) ? data.belgeler : [];
    var $tbody = $("#personelBelgeAlanlar").empty();
    Object.keys(analizSonucu).forEach(function (alan) {
      var bilgi = analizSonucu[alan];
      var mevcut = $("#personelForm [name='" + alan + "']").val();
      var cakisma = mevcut && String(mevcut).trim() !== "" && String(mevcut).trim() !== String(bilgi.deger).trim();
      var guvenRenk = bilgi.guven >= 85 ? "success" : (bilgi.guven >= 60 ? "warning" : "danger");
      var $tr = $("<tr>");
      $tr.append($("<td>").append($("<input>", {type: "checkbox", class: "form-check-input personel-belge-alan", value: alan, checked: !cakisma})));
      $tr.append($("<td>").append($("<span>", {class: "fw-semibold", text: bilgi.etiket || alan})).append(cakisma ? $("<small>", {class: "d-block text-warning", text: "Mevcut değer farklı"}) : ""));
      $tr.append($("<td>").text(bilgi.deger));
      $tr.append($("<td>").append($("<small>", {class: "text-muted", text: bilgi.kaynak || "Belge"})));
      $tr.append($("<td>").append($("<span>", {class: "badge bg-" + guvenRenk, text: "%" + bilgi.guven})));
      $tbody.append($tr);
    });
    if (!Object.keys(analizSonucu).length) {
      $tbody.append('<tr><td colspan="5" class="text-center text-muted py-4">Belgelerde forma aktarılabilecek bir bilgi bulunamadı.</td></tr>');
    }

    var uyarilar = Array.isArray(data.uyarilar) ? data.uyarilar : [];
    $("#personelBelgeUyarilar").html(uyarilar.length
      ? '<div class="alert alert-warning py-2"><i class="bx bx-info-circle me-1"></i>' + uyarilar.map(htmlKacir).join("<br>") + "</div>"
      : "");
    arsivEslesmeleriniGoster();
    $("#personelBelgeAnalizYukleniyor").addClass("d-none");
    $("#personelBelgeAnalizSonuc").removeClass("d-none");
    $("#btnPersonelBelgeAlanlariniUygula").toggleClass("d-none", !Object.keys(analizSonucu).length && !belgeEslesmeleri.length);
  }

  function arsivEslesmeleriniGoster() {
    var turAdlari = {
      ehliyet: "Ehliyet", ikametgah: "İkametgah", adli_sicil_kaydi: "Adli Sicil Kaydı",
      nufus_kayit_ornegi: "Nüfus Kayıt Örneği", gizlilik_taahhutnamesi: "Gizlilik Taahhütnamesi",
      sozlesme: "Sözleşme", kimlik: "Kimlik", diploma: "Diploma", cv: "CV",
      saglik_raporu: "Sağlık Raporu", sertifika: "Sertifika", diger: "Diğer"
    };
    var $liste = $("#personelBelgeArsivListesi").empty();
    belgeEslesmeleri.forEach(function (belge) {
      var index = Number(belge.sira) - 1;
      var file = analizDosyalari[index];
      if (!file) return;
      var guvenRenk = belge.guven >= 85 ? "success" : (belge.guven >= 60 ? "warning" : "secondary");
      var $satir = $("<label>", {class: "list-group-item d-flex align-items-center gap-3"});
      $satir.append($("<input>", {type: "checkbox", class: "form-check-input personel-belge-arsiv", value: index, checked: belge.evrak_turu !== "diger"}));
      var $metin = $("<span>", {class: "flex-grow-1 text-truncate"});
      $metin.append($("<span>", {class: "d-block fw-semibold text-truncate", text: file.name}));
      $metin.append($("<small>", {class: "text-muted", text: belge.evrak_adi || "Personel Evrakı"}));
      $satir.append($metin);
      $satir.append($("<span>", {class: "badge bg-soft-primary text-primary", text: turAdlari[belge.evrak_turu] || "Diğer"}));
      $satir.append($("<span>", {class: "badge bg-" + guvenRenk, text: "%" + belge.guven}));
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

  $(document).on("change", "#personelBelgeTumunuArsivle", function () {
    $(".personel-belge-arsiv").prop("checked", this.checked);
  });

  $(document).on("change", ".personel-belge-arsiv", function () {
    $("#personelBelgeTumunuArsivle").prop("checked", $(".personel-belge-arsiv:not(:checked)").length === 0);
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
    var data = new FormData();
    analizDosyalari = Array.from(files);
    Array.from(files).forEach(function (file) { data.append("personel_belgeleri[]", file, file.name); });
    data.append("personel_id", $("#personel_id").val() || "0");
    $("#personelBelgeSecimAlani, #btnPersonelBelgeleriAnalizEt").addClass("d-none");
    $("#personelBelgeAnalizYukleniyor").removeClass("d-none");

    $.ajax({
      url: "views/personel/ajax/personel-evrak-ai-analiz.php",
      method: "POST",
      data: data,
      processData: false,
      contentType: false,
      dataType: "json"
    }).done(function (response) {
      if (response.status !== "success") throw new Error(response.message || "Analiz yapılamadı.");
      sonuclariGoster(response.data || {});
    }).fail(function (xhr) {
      secimEkrani();
      var response = xhr.responseJSON || {};
      Swal.fire("Analiz Başarısız", response.message || "Belgeler analiz edilemedi.", "error");
    });
  });

  $(document).on("click", "#btnPersonelBelgeAlanlariniUygula", function () {
    var sayi = 0;
    $(".personel-belge-alan:checked").each(function () {
      var alan = this.value;
      var bilgi = analizSonucu[alan];
      var $input = $("#personelForm [name='" + alan + "']");
      if (!$input.length || !bilgi) return;
      $input.val(bilgi.deger).trigger("change");
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
      title: "Belgeler Analiz Edildi",
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
    return eklenen;
  };
})(jQuery);
