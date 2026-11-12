(function (window) {
  "use strict";

  var OCR_TABAN = "assets/libs/tesseract.js";
  var MAKS_SAYFA = 3;

  function pdfMi(file) {
    return file.type === "application/pdf" || /\.pdf$/i.test(file.name || "");
  }

  function bosCanvas(en, boy) {
    var canvas = document.createElement("canvas");
    canvas.width = Math.max(1, Math.round(en));
    canvas.height = Math.max(1, Math.round(boy));
    var ctx = canvas.getContext("2d", { alpha: false });
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    return canvas;
  }

  function pdfAc(file) {
    if (!window.pdfjsLib) throw new Error("PDF okuyucu yüklenemedi.");
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = "assets/libs/pdfjs/pdf.worker.min.js";
    return file.arrayBuffer().then(function (veri) {
      return window.pdfjsLib.getDocument({ data: veri }).promise;
    });
  }

  function sayfaMetni(sayfa) {
    return sayfa.getTextContent().then(function (icerik) {
      var ogeler = icerik.items.filter(function (o) { return o.str && o.str.trim() !== ""; }).map(function (o) {
        return { s: o.str, x: o.transform[4], y: o.transform[5] };
      });
      ogeler.sort(function (a, b) { return b.y - a.y || a.x - b.x; });
      var satirlar = [];
      ogeler.forEach(function (o) {
        var son = satirlar[satirlar.length - 1];
        if (son && Math.abs(son.y - o.y) <= 3) {
          son.parcalar.push(o);
        } else {
          satirlar.push({ y: o.y, parcalar: [o] });
        }
      });
      return satirlar.map(function (satir) {
        satir.parcalar.sort(function (a, b) { return a.x - b.x; });
        return satir.parcalar.map(function (p) { return p.s; }).join("   ");
      }).join("\n");
    });
  }

  async function pdfMetniOku(pdf) {
    var metin = "";
    var adet = Math.min(pdf.numPages, MAKS_SAYFA);
    for (var i = 1; i <= adet; i++) {
      metin += (await sayfaMetni(await pdf.getPage(i))) + "\n";
    }
    return metin;
  }

  async function pdfCanvaslari(pdf) {
    var liste = [];
    var adet = Math.min(pdf.numPages, MAKS_SAYFA);
    for (var i = 1; i <= adet; i++) {
      var sayfa = await pdf.getPage(i);
      var birim = sayfa.getViewport({ scale: 1 });
      var olcek = Math.min(4, Math.max(1.5, 2400 / Math.max(birim.width, birim.height)));
      var viewport = sayfa.getViewport({ scale: olcek });
      var canvas = bosCanvas(viewport.width, viewport.height);
      await sayfa.render({ canvasContext: canvas.getContext("2d", { alpha: false }), viewport: viewport, background: "white" }).promise;
      liste.push(canvas);
    }
    return liste;
  }

  async function gorselCanvasi(file) {
    var bitmap;
    try {
      bitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
    } catch (e) {
      throw new Error("Görsel tarayıcı tarafından açılamadı. JPG, PNG veya PDF kullanın.");
    }
    var oran = Math.min(2.5, 2400 / Math.max(bitmap.width, bitmap.height));
    if (oran < 1) oran = Math.max(oran, 0.4);
    var canvas = bosCanvas(bitmap.width * oran, bitmap.height * oran);
    canvas.getContext("2d", { alpha: false }).drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();
    return [canvas];
  }

  async function ocrOku(canvaslar, ilerleme) {
    if (!window.Tesseract) throw new Error("Yerel OCR bileşeni yüklenemedi. Sayfayı yenileyip tekrar deneyin.");
    var asama = 0;
    ilerleme(10, "OCR motoru hazırlanıyor");
    var worker = await window.Tesseract.createWorker(["tur", "eng"], window.Tesseract.OEM.LSTM_ONLY, {
      workerPath: OCR_TABAN + "/worker.min.js",
      corePath: OCR_TABAN + "/core",
      langPath: OCR_TABAN + "/lang",
      logger: function (m) {
        if (m.status !== "recognizing text") return;
        ilerleme(15 + ((asama + (Number(m.progress) || 0)) / canvaslar.length) * 80, "Belge okunuyor");
      }
    });
    var metin = "";
    try {
      await worker.setParameters({
        tessedit_pageseg_mode: window.Tesseract.PSM.AUTO,
        preserve_interword_spaces: "1",
        user_defined_dpi: "300"
      });
      for (asama = 0; asama < canvaslar.length; asama++) {
        var sonuc = await worker.recognize(canvaslar[asama]);
        metin += String(sonuc.data.text || "") + "\n";
      }
    } finally {
      await worker.terminate().catch(function () {});
    }
    return metin;
  }

  async function oku(file, ilerleme) {
    var rapor = typeof ilerleme === "function" ? ilerleme : function () {};
    rapor(4, "Belge açılıyor");
    if (pdfMi(file)) {
      var pdf = await pdfAc(file);
      try {
        var metin = await pdfMetniOku(pdf);
        if (metin.replace(/\s+/g, "").length >= 120) {
          rapor(95, "Belge metni okundu");
          return { metin: metin, yontem: "pdf" };
        }
        rapor(8, "Taranmış belge, OCR uygulanıyor");
        var sonuc = await ocrOku(await pdfCanvaslari(pdf), rapor);
        return { metin: sonuc, yontem: "ocr" };
      } finally {
        if (typeof pdf.destroy === "function") await pdf.destroy();
      }
    }
    return { metin: await ocrOku(await gorselCanvasi(file), rapor), yontem: "ocr" };
  }

  function tr(metin) {
    return String(metin || "")
      .replace(/İ/g, "I").replace(/ı/g, "i")
      .replace(/Ş/g, "S").replace(/ş/g, "s")
      .replace(/Ğ/g, "G").replace(/ğ/g, "g")
      .replace(/Ü/g, "U").replace(/ü/g, "u")
      .replace(/Ö/g, "O").replace(/ö/g, "o")
      .replace(/Ç/g, "C").replace(/ç/g, "c");
  }

  function sayiyaCevir(ham) {
    var deger = String(ham || "").replace(/\s/g, "");
    if (!deger) return null;
    if (deger.indexOf(",") !== -1) {
      deger = deger.replace(/\./g, "").replace(",", ".");
    } else if (/^\d{1,3}(\.\d{3})+$/.test(deger)) {
      deger = deger.replace(/\./g, "");
    }
    var sayi = parseFloat(deger);
    return isFinite(sayi) && sayi > 0 ? sayi : null;
  }

  function analiz(metin) {
    var duz = tr(metin);
    var satirlar = String(metin || "").split(/\r?\n/).map(function (s) { return s.replace(/\s{2,}/g, " ").trim(); }).filter(Boolean);
    var duzSatirlar = satirlar.map(tr);
    var sonuc = { trafik: false, kurum: "", tarih: "", plaka: "", tutar: null, tutanakNo: "" };

    sonuc.trafik = /TRAFIK\s+IDARI\s+PARA\s+CEZASI/i.test(duz) || (/KARAR\s+TUTANAGI/i.test(duz) && /K\.?T\.?K/i.test(duz));

    var birimIdx = -1;
    duzSatirlar.forEach(function (s, i) {
      if (birimIdx === -1 && /DUZENLEYEN\s+BIRIM/i.test(s)) birimIdx = i;
    });
    if (sonuc.trafik && birimIdx > 0) {
      for (var i = birimIdx - 1; i >= 0; i--) {
        if (/(MUDURLUGU|BASKANLIGI|AMIRLIGI|KOMUTANLIGI)/i.test(duzSatirlar[i])) {
          var orijinal = satirlar[i];
          var kes = duzSatirlar[i].search(/MUDURLUGU/i);
          sonuc.kurum = kes !== -1 ? orijinal.substr(0, kes + 9).trim() : orijinal;
          break;
        }
      }
    }
    if (!sonuc.kurum) {
      for (var j = 0; j < Math.min(duzSatirlar.length, 15); j++) {
        if (/(MUDURLUGU|BASKANLIGI|MAHKEMESI|SAVCILIGI|BAKANLIGI|KAYMAKAMLIGI|VALILIGI|BELEDIYESI)/i.test(duzSatirlar[j])) {
          sonuc.kurum = satirlar[j];
          break;
        }
      }
    }

    var tarih = duz.match(/Tarihi\s*\(Date\)[^\d]{0,120}(\d{2})[./](\d{2})[./](\d{4})/i);
    if (tarih) sonuc.tarih = tarih[1] + "." + tarih[2] + "." + tarih[3];

    var plaka = duz.match(/Plaka\s+No[^\n\d]{0,120}?(\d{2})\s?([A-Z]{1,3})\s?(\d{2,4})\b/i);
    if (plaka) sonuc.plaka = (plaka[1] + plaka[2] + plaka[3]).toUpperCase();

    var tutar = duz.match(/Toplam\s+Ceza\s+Tutari[^\d\n]{0,60}?(\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?)/i)
      || duz.match(/Ceza\s+Tutari\s*TL[^\d\n]{0,60}?(\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?)/i);
    if (tutar) sonuc.tutar = sayiyaCevir(tutar[1]);

    var seri = duz.match(/Seri\s+No[^\n]{0,20}?\)\s*([A-Z]{1,4})\b/i);
    var sira = duz.match(/Sira\s+No[^\n]{0,25}?\)\s*(\d{4,})/i);
    if (sira) sonuc.tutanakNo = ((seri ? seri[1] : "") + " " + sira[1]).trim();

    return sonuc;
  }

  window.EvrakOcr = { oku: oku, analiz: analiz };
})(window);
