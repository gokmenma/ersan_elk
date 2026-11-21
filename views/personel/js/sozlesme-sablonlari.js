$(function () {
  const ayar = window.SOZLESME_SABLON || {};
  const $editor = $("#sablonEditor");
  const SAYFA_ARASI = 24;

  function uyari(mesaj) {
    Swal.fire({ toast: true, position: "top-end", icon: "warning", title: mesaj, showConfirmButton: false, timer: 2200 });
  }

  function hata(mesaj) {
    Swal.fire("Hata", mesaj || "İşlem başarısız.", "error");
  }

  $editor.summernote({
    lang: "tr-TR",
    placeholder: "Sözleşme içeriğini buraya yazın...",
    fontNames: ["Times New Roman", "Arial"],
    fontNamesIgnoreCheck: ["Times New Roman", "Arial"],
    fontSizes: ["8", "9", "10", "11", "12", "13", "14", "16", "18", "20", "24"],
    fontSizeUnits: ["pt"],
    toolbar: [
      ["style", ["style"]],
      ["font", ["fontname", "fontsize", "bold", "italic", "underline", "strikethrough", "clear"]],
      ["color", ["color"]],
      ["para", ["ul", "ol", "paragraph"]],
      ["insert", ["link", "picture", "table", "hr"]],
      ["view", ["undo", "redo", "fullscreen", "codeview"]],
    ],
    callbacks: {
      onKeydown: function (e) {
        if (e.key !== "Tab" || e.ctrlKey || e.altKey || e.metaKey) return;
        e.preventDefault();
        $editor.summernote(e.shiftKey ? "editor.untab" : "editor.tab");
      },
      onPaste: function (e) {
        const veri = (e.originalEvent || e).clipboardData;
        const html = veri ? veri.getData("text/html") : "";
        if (!html) return;
        e.preventDefault();
        document.execCommand("insertHTML", false, yapistirmaTemizle(html));
      },
      onChange: function () {
        gorselleriBoyutla();
        sayfalaPlanla();
      },
    },
  });

  const YAPISTIRMA_STILLERI = ["font-size", "font-family", "font-weight", "font-style", "text-decoration", "text-align", "text-indent", "color", "background-color", "margin-left", "padding", "padding-left", "padding-right", "padding-top", "padding-bottom", "vertical-align", "width", "border", "border-top", "border-bottom", "border-left", "border-right", "border-collapse"];

  function yapistirmaTemizle(html) {
    const dok = new DOMParser().parseFromString(html, "text/html");
    dok.querySelectorAll("style, script, meta, link, xml, title, head").forEach((x) => x.remove());
    const yorumlar = [];
    const yurut = document.createNodeIterator ? dok.createNodeIterator(dok.body, NodeFilter.SHOW_COMMENT) : null;
    while (yurut && yurut.nextNode()) yorumlar.push(yurut.referenceNode);
    yorumlar.forEach((x) => x.remove());
    dok.body.querySelectorAll("*").forEach(function (el) {
      if (/^[a-z]+:/i.test(el.tagName)) {
        el.replaceWith(...el.childNodes);
        return;
      }
      el.removeAttribute("class");
      const stil = [];
      (el.getAttribute("style") || "").split(";").forEach(function (parca) {
        const ayrac = parca.indexOf(":");
        if (ayrac < 0) return;
        const ad = parca.slice(0, ayrac).trim().toLowerCase();
        const deger = parca.slice(ayrac + 1).trim();
        if (YAPISTIRMA_STILLERI.indexOf(ad) !== -1 && deger && !/url\(|expression/i.test(deger)) stil.push(ad + ": " + deger);
      });
      if (el.tagName === "IMG") {
        const g = (el.getAttribute("style") || "").match(/(?:^|;)\s*(width|height)\s*:\s*[\d.]+px/gi);
        if (g) g.forEach((x) => stil.push(x.replace(/^;/, "").trim()));
      }
      stil.length ? el.setAttribute("style", stil.join("; ")) : el.removeAttribute("style");
    });
    return dok.body.innerHTML;
  }

  const $cerceveKutusu = $editor.next(".note-editor");
  const $duzenlenebilir = $cerceveKutusu.find(".note-editable");
  const kok = $duzenlenebilir[0];
  $duzenlenebilir.addClass("contract-document");

  function icerikYukle(html) {
    $editor.summernote("code", html || "");
    gorselleriBoyutla();
    sayfalaPlanla();
  }

  function temizKopya() {
    const kopya = kok.cloneNode(true);
    kopya.querySelectorAll("[data-sbp-spacer]").forEach((x) => x.remove());
    return kopya;
  }

  function icerikAl() {
    if ($editor.summernote("codeview.isActivated")) {
      $editor.summernote("codeview.deactivate");
    }
    return temizKopya().innerHTML;
  }

  function gorselleriBoyutla() {
    kok.querySelectorAll("img").forEach(function (img) {
      if (img.getAttribute("width") || /(^|;)\s*width\s*:/.test(img.getAttribute("style") || "")) return;
      const uygula = function () {
        const g = Math.min(img.naturalWidth || 300, 300);
        img.setAttribute("width", String(g));
        img.setAttribute("height", String(Math.round(g * ((img.naturalHeight || g) / (img.naturalWidth || g)))));
      };
      img.complete ? uygula() : img.addEventListener("load", uygula, { once: true });
    });
  }

  function birim(css) {
    const o = document.createElement("div");
    o.style.cssText = "position:absolute;visibility:hidden;width:1px;height:" + css;
    document.body.appendChild(o);
    const h = o.offsetHeight;
    o.remove();
    return h;
  }

  function baslikMi(b) {
    const metin = (b.textContent || "").trim();
    if (!metin || metin.length > 90) return false;
    if (/^H[1-6]$/.test(b.tagName)) return true;
    const kalin = Array.from(b.querySelectorAll("strong, b")).map((x) => x.textContent).join("").trim();
    return kalin.length >= metin.length - 1;
  }

  function sayfaArkaPlani(H, P) {
    const W = Math.round(birim("210mm") || 794);
    const cerceve = $("#sablonCerceve").is(":checked");
    const ic = cerceve ? '<rect x="38" y="30" width="' + (W - 76) + '" height="' + (H - 60) + '" fill="none" stroke="#000" stroke-width="2"/>' : "";
    const svg =
      '<svg xmlns="http://www.w3.org/2000/svg" width="' + W + '" height="' + P + '" viewBox="0 0 ' + W + " " + P + '">' +
      '<rect x=".5" y=".5" width="' + (W - 1) + '" height="' + (H - 1) + '" fill="#fff" stroke="#d5dbe3"/>' + ic + "</svg>";
    return 'url("data:image/svg+xml;utf8,' + encodeURIComponent(svg) + '")';
  }

  function sayfala() {
    const H = birim("297mm");
    const m = birim("1cm");
    const P = H + SAYFA_ARASI;
    const C = H - 2 * m;
    kok.querySelectorAll("[data-sbp-spacer]").forEach((x) => x.remove());
    const bloklar = Array.from(kok.children);
    const ust = kok.getBoundingClientRect().top + kok.clientTop + parseFloat(getComputedStyle(kok).paddingTop || "0");
    const olc = (b) => {
      const r = b.getBoundingClientRect();
      return { t: r.top - ust, h: r.height };
    };
    bloklar.forEach(function (b, i) {
      const o = olc(b);
      const k = Math.floor(o.t / P);
      if (!(o.t + o.h > k * P + C + 0.5 && o.h <= C && o.t > k * P + 0.5)) return;
      let j = i;
      let hedefT = o.t;
      if (i > 0 && baslikMi(bloklar[i - 1])) {
        const on = olc(bloklar[i - 1]);
        if (Math.floor(on.t / P) === k && on.t > k * P + 0.5) {
          j = i - 1;
          hedefT = on.t;
        }
      }
      const bosluk = document.createElement("div");
      bosluk.setAttribute("data-sbp-spacer", "1");
      bosluk.setAttribute("contenteditable", "false");
      bosluk.style.cssText = "height:" + Math.max(0, (k + 1) * P - hedefT) + "px;margin:0;padding:0;pointer-events:none;user-select:none";
      kok.insertBefore(bosluk, bloklar[j]);
      const gercek = olc(bloklar[j]).t;
      bosluk.style.height = Math.max(0, parseFloat(bosluk.style.height) + ((k + 1) * P - gercek)) + "px";
    });
    const sonBlok = bloklar[bloklar.length - 1];
    const son = sonBlok ? olc(sonBlok).t + olc(sonBlok).h : 0;
    const adet = Math.max(1, Math.floor((son - 0.5) / P) + 1);
    kok.style.minHeight = adet * P - SAYFA_ARASI + "px";
    kok.style.backgroundImage = sayfaArkaPlani(H, P);
    kok.style.backgroundSize = "100% " + P + "px";
    kok.style.backgroundRepeat = "repeat-y";
  }

  let sayfalaZamani = null;
  function sayfalaPlanla() {
    clearTimeout(sayfalaZamani);
    sayfalaZamani = setTimeout(sayfala, 150);
  }

  icerikYukle(ayar.icerik);
  if (!ayar.duzenleyebilir) {
    $editor.summernote("disable");
  }
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(sayfalaPlanla);
  setTimeout(sayfalaPlanla, 800);

  $("#sablonCerceve").on("change", sayfalaPlanla);

  $(document).on("mousedown", ".sablon-alan-btn", function (e) {
    e.preventDefault();
  });

  $(document).on("click", ".sablon-alan-btn", function () {
    if (!ayar.duzenleyebilir) return;
    $editor.summernote("editor.restoreRange");
    $editor.summernote("editor.focus");
    $editor.summernote("editor.insertText", $(this).data("kod"));
  });

  $("#btnSablonSifirla").on("click", function () {
    Swal.fire({
      title: "Emin misiniz?",
      text: "Editördeki içerik silinecek ve sistem varsayılan şablonu yüklenecek. Kaydedene kadar kalıcı olmaz.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet, Sıfırla",
      cancelButtonText: "Vazgeç",
    }).then(function (r) {
      if (!r.isConfirmed) return;
      icerikYukle(ayar.varsayilan);
      $("#sablonCerceve").prop("checked", true).trigger("change");
      Swal.fire({ toast: true, position: "top-end", icon: "info", title: "Varsayılan şablon yüklendi. Kaydetmeyi unutmayın.", showConfirmButton: false, timer: 2500 });
    });
  });

  $("#btnSablonKaydet").on("click", function () {
    const $btn = $(this).prop("disabled", true);
    $.post(ayar.api, { action: "sablon-kaydet", icerik: icerikAl(), sayfa_cercevesi: $("#sablonCerceve").is(":checked") ? 1 : 0 }, null, "json")
      .done(function (res) {
        $btn.prop("disabled", false);
        if (res && res.status === "success") {
          Swal.fire("Başarılı", res.message, "success");
        } else {
          hata(res && res.message);
        }
      })
      .fail(function () {
        $btn.prop("disabled", false);
        hata("Sunucu ile iletişim kurulamadı.");
      });
  });

  $("#btnSablonYazdir").on("click", function () {
    const icerik = icerikAl();
    const cerceve = $("#sablonCerceve").is(":checked");
    const pencere = window.open("", "_blank");
    if (!pencere) {
      uyari("Açılır pencere engellendi.");
      return;
    }
    SozlesmeBelge.yaz(pencere.document, icerik, cerceve);
  });
});
