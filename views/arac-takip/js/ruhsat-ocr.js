(function (window) {
  "use strict";

  var OCR_TABAN = "assets/libs/tesseract.js";

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

  function olcekle(canvas, hedefUzunKenar) {
    var uzun = Math.max(canvas.width, canvas.height);
    var oran = hedefUzunKenar / uzun;
    if (oran > 0.95 && oran < 1.05) return canvas;
    if (oran > 2.5) oran = 2.5;
    var hedef = bosCanvas(canvas.width * oran, canvas.height * oran);
    var ctx = hedef.getContext("2d", { alpha: false });
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(canvas, 0, 0, hedef.width, hedef.height);
    return hedef;
  }

  function griVeKontrast(canvas) {
    var ctx = canvas.getContext("2d", { alpha: false });
    var goruntu = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var piksel = goruntu.data;
    var histogram = new Uint32Array(256);
    var i;
    for (i = 0; i < piksel.length; i += 4) {
      var gri = (piksel[i] * 0.15 + piksel[i + 1] * 0.25 + piksel[i + 2] * 0.6) | 0;
      piksel[i] = piksel[i + 1] = piksel[i + 2] = gri;
      histogram[gri]++;
    }
    var toplam = canvas.width * canvas.height;
    var alt = 0, ust = 255, birikim = 0, k;
    for (k = 0; k < 256; k++) {
      birikim += histogram[k];
      if (birikim > toplam * 0.01) { alt = k; break; }
    }
    birikim = 0;
    for (k = 255; k >= 0; k--) {
      birikim += histogram[k];
      if (birikim > toplam * 0.01) { ust = k; break; }
    }
    if (ust - alt >= 40) {
      var carpan = 255 / (ust - alt);
      for (i = 0; i < piksel.length; i += 4) {
        var v = Math.max(0, Math.min(255, Math.round((piksel[i] - alt) * carpan)));
        piksel[i] = piksel[i + 1] = piksel[i + 2] = v;
      }
    }
    ctx.putImageData(goruntu, 0, 0);
    return canvas;
  }

  async function gorselCanvas(file) {
    var bitmap;
    try {
      bitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
    } catch (e) {
      throw new Error("Görsel tarayıcı tarafından açılamadı. JPG, PNG veya PDF kullanın.");
    }
    var canvas = bosCanvas(bitmap.width, bitmap.height);
    canvas.getContext("2d", { alpha: false }).drawImage(bitmap, 0, 0);
    bitmap.close();
    return griVeKontrast(olcekle(canvas, 2600));
  }

  async function pdfCanvas(file) {
    if (!window.pdfjsLib) throw new Error("PDF okuyucu yüklenemedi.");
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = "assets/libs/pdfjs/pdf.worker.min.js";
    var pdf = await window.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
    var sayfa = await pdf.getPage(1);
    var birim = sayfa.getViewport({ scale: 1 });
    var olcek = Math.min(5, Math.max(1.5, 2600 / Math.max(birim.width, birim.height)));
    var viewport = sayfa.getViewport({ scale: olcek });
    var canvas = bosCanvas(viewport.width, viewport.height);
    await sayfa.render({ canvasContext: canvas.getContext("2d", { alpha: false }), viewport: viewport, background: "white" }).promise;
    if (typeof pdf.destroy === "function") await pdf.destroy();
    return griVeKontrast(canvas);
  }

  function yerelEsikle(canvas) {
    var ctx = canvas.getContext("2d", { alpha: false });
    var goruntu = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var piksel = goruntu.data;
    var en = canvas.width, boy = canvas.height;
    var integral = new Float64Array((en + 1) * (boy + 1));
    var x, y;
    for (y = 0; y < boy; y++) {
      var satir = 0;
      for (x = 0; x < en; x++) {
        satir += piksel[(y * en + x) * 4];
        integral[(y + 1) * (en + 1) + x + 1] = integral[y * (en + 1) + x + 1] + satir;
      }
    }
    var yaricap = Math.max(15, Math.round(Math.min(en, boy) / 40));
    for (y = 0; y < boy; y++) {
      var y1 = Math.max(0, y - yaricap), y2 = Math.min(boy, y + yaricap + 1);
      for (x = 0; x < en; x++) {
        var x1 = Math.max(0, x - yaricap), x2 = Math.min(en, x + yaricap + 1);
        var toplam = integral[y2 * (en + 1) + x2] - integral[y1 * (en + 1) + x2] - integral[y2 * (en + 1) + x1] + integral[y1 * (en + 1) + x1];
        var ort = toplam / ((x2 - x1) * (y2 - y1));
        var idx = (y * en + x) * 4;
        var v = piksel[idx] < ort * 0.86 ? 0 : 255;
        piksel[idx] = piksel[idx + 1] = piksel[idx + 2] = v;
      }
    }
    ctx.putImageData(goruntu, 0, 0);
    return canvas;
  }

  function egiklikBul(canvas) {
    var kucuk = olcekle(canvas, 900);
    var ctx = kucuk.getContext("2d", { alpha: false });
    var veri = ctx.getImageData(0, 0, kucuk.width, kucuk.height).data;
    var en = kucuk.width, boy = kucuk.height;
    var histogram = new Uint32Array(256);
    var k;
    for (k = 0; k < veri.length; k += 4) histogram[veri[k]]++;
    var hedef = en * boy * 0.12, birikim = 0, esik = 90;
    for (k = 0; k < 256; k++) {
      birikim += histogram[k];
      if (birikim > hedef) { esik = k; break; }
    }
    var xler = [], yler = [];
    for (var y = 0; y < boy; y++) {
      for (var x = 0; x < en; x++) {
        if (veri[(y * en + x) * 4] <= esik) { xler.push(x); yler.push(y); }
      }
    }
    if (xler.length < 500) return 0;
    var adim = Math.max(1, Math.floor(xler.length / 40000));
    var puan = function (derece) {
      var r = (derece * Math.PI) / 180, c = Math.cos(r), sn = Math.sin(r);
      var bin = new Uint32Array(en + boy + 4);
      for (var n = 0; n < xler.length; n += adim) {
        var yy = Math.round(yler[n] * c - xler[n] * sn) + en;
        if (yy >= 0 && yy < bin.length) bin[yy]++;
      }
      var toplam = 0;
      for (var b = 0; b < bin.length; b++) toplam += bin[b] * bin[b];
      return toplam;
    };
    var en_iyi = 0, en_iyi_puan = puan(0), d;
    for (d = -15; d <= 15; d += 1) {
      var pz = puan(d);
      if (pz > en_iyi_puan) { en_iyi_puan = pz; en_iyi = d; }
    }
    var merkez = en_iyi;
    for (d = merkez - 1; d <= merkez + 1; d += 0.25) {
      var pr = puan(d);
      if (pr > en_iyi_puan) { en_iyi_puan = pr; en_iyi = d; }
    }
    return en_iyi;
  }

  function dondur(canvas, derece) {
    if (Math.abs(derece) < 0.4) return canvas;
    var r = (derece * Math.PI) / 180;
    var c = Math.abs(Math.cos(r)), sn = Math.abs(Math.sin(r));
    var hedef = bosCanvas(canvas.width * c + canvas.height * sn, canvas.width * sn + canvas.height * c);
    var ctx = hedef.getContext("2d", { alpha: false });
    ctx.translate(hedef.width / 2, hedef.height / 2);
    ctx.rotate(-r);
    ctx.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);
    return hedef;
  }

  function kes(canvas, x, y, en, boy) {
    var hedef = bosCanvas(en, boy);
    hedef.getContext("2d", { alpha: false }).drawImage(canvas, x, y, en, boy, 0, 0, en, boy);
    return hedef;
  }

  function parcala(canvas) {
    var yatay = canvas.width / canvas.height >= 1.25;
    var sayfalar = yatay
      ? [kes(canvas, 0, 0, canvas.width * 0.54, canvas.height), kes(canvas, canvas.width * 0.46, 0, canvas.width * 0.54, canvas.height)]
      : [canvas];
    var bantSayisi = 3;
    var parcalar = [];
    sayfalar.forEach(function (sayfa) {
      var duz = yerelEsikle(dondur(sayfa, egiklikBul(sayfa)));
      var bantBoyu = duz.height / bantSayisi;
      var pay = bantBoyu * 0.25;
      for (var b = 0; b < bantSayisi; b++) {
        var ust = Math.max(0, b * bantBoyu - pay);
        var alt = Math.min(duz.height, (b + 1) * bantBoyu + pay);
        parcalar.push(kes(duz, 0, ust, duz.width, alt - ust));
      }
    });
    return parcalar;
  }

  function cizgileriSil(canvas) {
    var ctx = canvas.getContext("2d", { alpha: false });
    var goruntu = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var piksel = goruntu.data;
    var en = canvas.width, boy = canvas.height;
    var x, y;
    for (var tur = 0; tur < 3; tur++) {
      var enIyi = null;
      for (var aci = -4; aci <= 4; aci += 0.25) {
        var egim = Math.tan((aci * Math.PI) / 180);
        var kutu = new Uint32Array(boy + en + 8);
        var kayma = Math.ceil(en * 0.08) + 4;
        for (y = 0; y < boy; y++) {
          for (x = 0; x < en; x++) {
            if (piksel[(y * en + x) * 4] !== 0) continue;
            var c = Math.round(y - x * egim) + kayma;
            if (c >= 0 && c < kutu.length) kutu[c]++;
          }
        }
        for (var n = 2; n < kutu.length - 2; n++) {
          var kalin = kutu[n - 2] + kutu[n - 1] + kutu[n] + kutu[n + 1] + kutu[n + 2];
          if (!enIyi || kalin > enIyi.puan) enIyi = { puan: kalin, c: n - kayma, egim: egim, tepe: kutu[n], kutu: kutu, kayma: kayma, n: n };
        }
      }
      if (!enIyi || enIyi.puan < en * 1.6) break;
      var yari = enIyi.tepe * 0.45;
      var alt = enIyi.n, ust = enIyi.n;
      while (alt > 0 && enIyi.kutu[alt - 1] >= yari) alt--;
      while (ust < enIyi.kutu.length - 1 && enIyi.kutu[ust + 1] >= yari) ust++;
      var altSinir = alt - enIyi.kayma - 1, ustSinir = ust - enIyi.kayma + 1;
      for (y = 0; y < boy; y++) {
        for (x = 0; x < en; x++) {
          var d = Math.round(y - x * enIyi.egim);
          if (d >= altSinir && d <= ustSinir) {
            var idx = (y * en + x) * 4;
            piksel[idx] = piksel[idx + 1] = piksel[idx + 2] = 255;
          }
        }
      }
    }
    ctx.putImageData(goruntu, 0, 0);
    return canvas;
  }

  function kelimeleriTopla(data) {
    var liste = [];
    (data.blocks || []).forEach(function (blok) {
      (blok.paragraphs || []).forEach(function (par) {
        (par.lines || []).forEach(function (satir) {
          (satir.words || []).forEach(function (kelime) { liste.push({ text: kelime.text, bbox: kelime.bbox }); });
        });
      });
    });
    return liste;
  }

  function kelimeNormal(metin) {
    return String(metin || "").toUpperCase().replace(/İ/g, "I").replace(/[^A-Z]/g, "");
  }

  function uzaklik(a, b) {
    var onceki = [];
    var i, j;
    for (j = 0; j <= b.length; j++) onceki[j] = j;
    for (i = 1; i <= a.length; i++) {
      var simdiki = [i];
      for (j = 1; j <= b.length; j++) {
        simdiki[j] = Math.min(onceki[j] + 1, simdiki[j - 1] + 1, onceki[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
      }
      onceki = simdiki;
    }
    return onceki[b.length];
  }

  function kelimeBul(parca, hedef, tolerans) {
    return (parca.kelimeler || []).find(function (k) {
      var n = kelimeNormal(k.text);
      if (n.length < hedef.length - 1) return false;
      return n.indexOf(hedef) !== -1 || uzaklik(n, hedef) <= tolerans;
    });
  }

  function bolgeKes(parca, x0, y0, x1, y1, cizgiSil) {
    x0 = Math.max(0, x0); y0 = Math.max(0, y0);
    x1 = Math.min(parca.tuval.width, x1); y1 = Math.min(parca.tuval.height, y1);
    if (x1 - x0 < 20 || y1 - y0 < 20) return null;
    var kutu = kes(parca.tuval, x0, y0, x1 - x0, y1 - y0);
    return olcekle(cizgiSil ? cizgileriSil(kutu) : kutu, 1200);
  }

  function plakaBolgeleri(parca) {
    var liste = [];
    var etiket = kelimeBul(parca, "PLAKA", 1);
    if (etiket) {
      var w = Math.max(30, etiket.bbox.x1 - etiket.bbox.x0);
      var h = Math.max(10, etiket.bbox.y1 - etiket.bbox.y0);
      [[5.5, 5, true], [5.5, 5, false], [8, 6.5, true], [3.5, 4, true]].forEach(function (v) {
        liste.push(bolgeKes(parca, etiket.bbox.x0 - w * 0.6, etiket.bbox.y1 - h * 0.3, etiket.bbox.x0 + w * v[1], etiket.bbox.y1 + h * v[0], v[2]));
      });
    }
    var sira = kelimeBul(parca, "TESCIL", 1);
    var siraNo = kelimeBul(parca, "SIRA", 1);
    var alt = sira && siraNo ? sira : null;
    if (alt) {
      var aw = Math.max(30, alt.bbox.x1 - alt.bbox.x0);
      var ah = Math.max(10, alt.bbox.y1 - alt.bbox.y0);
      [true, false].forEach(function (sil) {
        liste.push(bolgeKes(parca, alt.bbox.x0 - aw * 0.6, alt.bbox.y0 - ah * 4.2, alt.bbox.x0 + aw * 5, alt.bbox.y0 + ah * 0.2, sil));
      });
    }
    return liste.filter(Boolean);
  }

  function plakaVarMi(metin) {
    var a = window.AracRuhsatAyristirici;
    return !!(a && a.plakaAdaylari && a.plakaAdaylari(metin).length);
  }

  async function hamMetinler(file, ilerleme) {
    if (!window.Tesseract) throw new Error("Yerel OCR bileşeni yüklenemedi. Sayfayı yenileyip tekrar deneyin.");
    var rapor = function (yuzde, mesaj) { if (typeof ilerleme === "function") ilerleme(yuzde, mesaj); };

    rapor(3, "Belge hazırlanıyor");
    var canvas = pdfMi(file) ? await pdfCanvas(file) : await gorselCanvas(file);

    rapor(6, "Belge hizalanıyor");
    var parcalar = parcala(canvas);

    rapor(8, "OCR motoru hazırlanıyor");
    var asama = 0;
    var toplamAsama = parcalar.length;
    var worker = await window.Tesseract.createWorker(["tur", "eng"], window.Tesseract.OEM.LSTM_ONLY, {
      workerPath: OCR_TABAN + "/worker.min.js",
      corePath: OCR_TABAN + "/core",
      langPath: OCR_TABAN + "/lang",
      logger: function (m) {
        if (m.status !== "recognizing text") return;
        rapor(12 + ((asama + (Number(m.progress) || 0)) / toplamAsama) * 83, "Ruhsat okunuyor");
      }
    });
    var metinler = [];
    try {
      await worker.setParameters({
        tessedit_pageseg_mode: window.Tesseract.PSM.AUTO,
        preserve_interword_spaces: "1",
        user_defined_dpi: "300"
      });
      for (asama = 0; asama < parcalar.length; asama++) {
        var sonuc = await worker.recognize(parcalar[asama], {}, { blocks: true, text: true });
        metinler.push({
          metin: String(sonuc.data.text || ""),
          guven: Number(sonuc.data.confidence) || 0,
          kelimeler: kelimeleriTopla(sonuc.data),
          tuval: parcalar[asama]
        });
      }
      await worker.setParameters({
        tessedit_pageseg_mode: window.Tesseract.PSM.SPARSE_TEXT,
        tessedit_char_whitelist: "0123456789ABCDEFGHIJKLMNOPRSTUVYZ"
      });
      var anaMetindeVar = metinler.some(function (m) { return plakaVarMi(m.metin); });
      var bolgedeBulundu = false;
      metinler.forEach(function (m) { m.plakaAdaylari = []; });
      rapor(95, "Plaka doğrulanıyor");
      for (var m = 0; m < metinler.length && !bolgedeBulundu; m++) {
        var bolgeler = plakaBolgeleri(metinler[m]);
        for (var b = 0; b < bolgeler.length && !bolgedeBulundu; b++) {
          var plakaSonuc = await worker.recognize(bolgeler[b]);
          var plakaMetni = String(plakaSonuc.data.text || "");
          metinler[m].plakaAdaylari.push(plakaMetni);
          if (plakaVarMi(plakaMetni)) bolgedeBulundu = true;
        }
      }
      var tumuBulundu = bolgedeBulundu || anaMetindeVar;
      for (var t = 0; t < metinler.length && !tumuBulundu; t++) {
        var tam = [metinler[t].tuval, cizgileriSil(kes(metinler[t].tuval, 0, 0, metinler[t].tuval.width, metinler[t].tuval.height))];
        for (var v = 0; v < tam.length && !tumuBulundu; v++) {
          var tamSonuc = await worker.recognize(tam[v]);
          var tamMetin = String(tamSonuc.data.text || "");
          metinler[t].plakaAdaylari.push(tamMetin);
          if (plakaVarMi(tamMetin)) tumuBulundu = true;
        }
      }
    } finally {
      await worker.terminate().catch(function () {});
    }
    metinler.forEach(function (m) { delete m.tuval; delete m.kelimeler; });
    canvas.width = canvas.height = 1;
    rapor(97, "Bilgiler ayrıştırılıyor");
    return metinler;
  }

  window.AracRuhsatOcr = { hamMetinler: hamMetinler };
})(window);
