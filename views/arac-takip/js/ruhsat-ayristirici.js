(function (window) {
  "use strict";

  var ETIKETLER = {
    plaka: "Plaka", marka: "Marka", model: "Model", model_yili: "Model Yılı", renk: "Renk",
    arac_tipi: "Araç Tipi", yakit_tipi: "Yakıt Tipi", sase_no: "Şase No", motor_no: "Motor No",
    ruhsat_sahibi: "Ruhsat Sahibi", muayene_bitis_tarihi: "Muayene Bitiş"
  };

  var RENKLER = {
    BEYAZ: "Beyaz", SIYAH: "Siyah", GRI: "Gri", GUMUS: "Gümüş", KIRMIZI: "Kırmızı", MAVI: "Mavi",
    LACIVERT: "Lacivert", YESIL: "Yeşil", SARI: "Sarı", TURUNCU: "Turuncu", KAHVERENGI: "Kahverengi",
    BORDO: "Bordo", BEJ: "Bej", MOR: "Mor", PEMBE: "Pembe", ALTIN: "Altın", BRONZ: "Bronz",
    TURKUAZ: "Turkuaz", FUME: "Füme", ANTRASIT: "Antrasit", LACI: "Lacivert"
  };

  var HARITA = { "Ç": "C", "ç": "C", "Ğ": "G", "ğ": "G", "İ": "I", "ı": "I", "i": "I", "Ö": "O", "ö": "O", "Ş": "S", "ş": "S", "Ü": "U", "ü": "U", "Â": "A", "â": "A" };

  function yukselt(metin) {
    var cikti = "";
    var kaynak = String(metin || "");
    for (var i = 0; i < kaynak.length; i++) {
      var harf = kaynak[i];
      cikti += HARITA[harf] !== undefined ? HARITA[harf] : harf.toUpperCase();
    }
    return cikti;
  }

  function satirlar(metin) {
    return yukselt(metin).split(/\r?\n/).map(function (s) { return s.replace(/[|_—–“”"~`]/g, " ").replace(/\s+$/, ""); });
  }

  function sonrakiSatirlar(liste, index, adet) {
    var sonuc = [];
    for (var i = index; i < liste.length && sonuc.length < adet + 1; i++) {
      if (liste[i].trim() !== "") sonuc.push(liste[i]);
    }
    return sonuc;
  }

  function etiketiBul(liste, desen) {
    var bulunan = [];
    liste.forEach(function (satir, index) {
      var m = desen.exec(satir);
      if (m) bulunan.push({ index: index, bitis: m.index + m[0].length, satir: satir });
    });
    return bulunan;
  }

  function aralikla(satir) {
    return satir.split(/ {3,}/).map(function (p) { return p.trim(); }).filter(Boolean);
  }

  function etiketMi(parca) {
    return /\([A-Z]\.?\d?\.?\d?\)|\b(?:KG|NO|ADI|TARIHI|CINSI|RENGI|SINIFI)\b/.test(parca) && /\(/.test(parca);
  }

  function degerSatirlari(liste, etiket, adet) {
    var ayni = liste[etiket.index].slice(etiket.bitis).trim();
    var sonuc = [];
    if (ayni && !/^[^A-Z0-9]*\(/.test(ayni)) sonuc.push(ayni);
    var sonrakiler = sonrakiSatirlar(liste, etiket.index + 1, adet);
    return sonuc.concat(sonrakiler.slice(0, adet));
  }

  function ilkParca(satir) {
    var parcalar = aralikla(satir.replace(/^[^A-Z0-9(]+/, ""));
    for (var i = 0; i < parcalar.length; i++) {
      if (!etiketMi(parcalar[i])) return parcalar[i];
    }
    return "";
  }

  function plakaGecerli(il, harf, sayi) {
    var ilNo = Number(il);
    if (ilNo < 1 || ilNo > 81) return false;
    var kural = { 1: [4], 2: [3, 4], 3: [2, 3, 4] }[harf.length];
    return !!kural && kural.indexOf(sayi.length) !== -1;
  }

  var RAKAM_BENZERI = { O: "0", Q: "0", D: "0", N: "0", I: "1", L: "1", Z: "2", S: "5", B: "8", G: "6", T: "7", A: "4" };

  function plakaAdaylari(metin) {
    var sonuc = [];
    var desen = /(?:^|[^A-Z0-9])([0-9OQDNILZSBGTA]{2})\s?([A-Z]{1,3})\s?(\d{2,4})(?![A-Z0-9])/g;
    var m;
    var kaynak = yukselt(metin).replace(/[^A-Z0-9\n ]/g, " ");
    while ((m = desen.exec(kaynak)) !== null) {
      var duzeltildi = false;
      var il = m[1].split("").map(function (h) {
        if (/\d/.test(h)) return h;
        duzeltildi = true;
        return RAKAM_BENZERI[h];
      }).join("");
      if (plakaGecerli(il, m[2], m[3])) sonuc.push({ deger: il + " " + m[2] + " " + m[3], duzeltildi: duzeltildi });
      desen.lastIndex = m.index + m[0].length - 1;
    }
    return sonuc;
  }

  function vinTemizle(deger) {
    return String(deger).replace(/[^A-Z0-9]/g, "").replace(/[OQ]/g, "0").replace(/I/g, "1");
  }

  var VIN_DEGERLER = { A: 1, B: 2, C: 3, D: 4, E: 5, F: 6, G: 7, H: 8, J: 1, K: 2, L: 3, M: 4, N: 5, P: 7, R: 9, S: 2, T: 3, U: 4, V: 5, W: 6, X: 7, Y: 8, Z: 9 };
  var VIN_AGIRLIK = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];
  var VIN_BENZER = { S: "5", "5": "S", B: "8", "8": "B", Z: "2", "2": "Z", G: "6", "6": "G" };

  function vinGecerli(vin) {
    var toplam = 0;
    for (var i = 0; i < 17; i++) {
      var h = vin[i];
      var d = /\d/.test(h) ? Number(h) : VIN_DEGERLER[h];
      if (d === undefined) return false;
      toplam += d * VIN_AGIRLIK[i];
    }
    var kalan = toplam % 11;
    return vin[8] === (kalan === 10 ? "X" : String(kalan));
  }

  function vinOnar(vin) {
    if (vinGecerli(vin)) return { deger: vin, dogrulandi: true };
    var konumlar = [];
    for (var i = 0; i < 17; i++) if (VIN_BENZER[vin[i]] && i !== 8) konumlar.push(i);
    if (konumlar.length === 0 || konumlar.length > 8) return { deger: vin, dogrulandi: false };
    var toplamKombinasyon = 1 << konumlar.length;
    var sayBit = function (n) { var c = 0; while (n) { c += n & 1; n >>= 1; } return c; };
    for (var degisim = 1; degisim <= konumlar.length; degisim++) {
      var gecerliler = [];
      for (var mask = 1; mask < toplamKombinasyon; mask++) {
        if (sayBit(mask) !== degisim) continue;
        var harfler = vin.split("");
        for (var b = 0; b < konumlar.length; b++) {
          if (mask & (1 << b)) harfler[konumlar[b]] = VIN_BENZER[vin[konumlar[b]]];
        }
        var aday = harfler.join("");
        if (vinGecerli(aday)) gecerliler.push(aday);
      }
      if (gecerliler.length === 1) return { deger: gecerliler[0], dogrulandi: true };
      if (gecerliler.length > 1) break;
    }
    return { deger: vin, dogrulandi: false };
  }

  var MARKALAR = ["FORD", "RENAULT", "FIAT", "KIA", "HYUNDAI", "TOYOTA", "HONDA", "NISSAN", "OPEL", "PEUGEOT", "CITROEN", "DACIA",
    "VOLKSWAGEN", "MERCEDES", "BMW", "AUDI", "SKODA", "SEAT", "MAZDA", "SUZUKI", "MITSUBISHI", "ISUZU", "IVECO", "MAN", "SCANIA",
    "VOLVO", "DAF", "TOGG", "CHERY", "MG", "TESLA", "JEEP", "LAND", "MINI", "SUBARU", "LEXUS", "CUPRA", "DS", "ALFA", "LADA",
    "TEMSA", "OTOKAR", "BMC", "KARSAN", "TOFAS", "SSANGYONG", "DFSK", "MAXUS", "FOTON", "JAC", "ISEKI", "YAMAHA", "HARLEY",
    "KYMCO", "MONDIAL", "BAJAJ", "PORSCHE", "SMART", "CHEVROLET", "DODGE", "CHRYSLER", "JAGUAR", "SAAB", "ROVER", "DAEWOO"];

  function tarihAdayi(metin) {
    var tam = yukselt(metin);
    var desen = /MUA[A-Z.]{0,8}\s*GE[A-Z.]{0,6}\s*T[A-Z.]{0,4}\s*[:;.]?/g;
    var sonuc = [];
    var m;
    while ((m = desen.exec(tam)) !== null) {
      var devam = tam.slice(m.index + m[0].length, m.index + m[0].length + 40);
      var rakamlar = devam.replace(/\D/g, "").slice(0, 8);
      if (rakamlar.length !== 8) continue;
      var gun = Number(rakamlar.slice(0, 2)), ay = Number(rakamlar.slice(2, 4)), yil = Number(rakamlar.slice(4, 8));
      if (gun < 1 || gun > 31 || ay < 1 || ay > 12 || yil < 2000 || yil > 2100) continue;
      sonuc.push(rakamlar.slice(0, 2) + "." + rakamlar.slice(2, 4) + "." + rakamlar.slice(4, 8));
    }
    return sonuc;
  }

  function parcadanAlanlar(parca) {
    var liste = satirlar(parca.metin);
    var alan = {};
    var etiket, degerler, i, t;

    var plakalar = [];
    (parca.plakaAdaylari || []).forEach(function (metin) {
      plakaAdaylari(metin).forEach(function (p) { plakalar.push({ deger: p.deger, agirlik: p.duzeltildi ? 2 : 3 }); });
    });
    plakaAdaylari(parca.metin).forEach(function (p) { plakalar.push({ deger: p.deger, agirlik: p.duzeltildi ? 0.5 : 2 }); });
    if (plakalar.length) alan.plaka = plakalar;

    etiket = etiketiBul(liste, /MARKAS[I1]/)[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 2);
      for (i = 0; i < degerler.length; i++) {
        t = ilkParca(degerler[i]).replace(/[^A-Z0-9\- ]/g, "").trim();
        if (/^[A-Z][A-Z0-9\-]{1,14}( [A-Z][A-Z0-9\-]{1,14})?$/.test(t) && !/^(NOSE|TIPI|ADI)$/.test(t)) {
          var markaAdi = t.split(" ")[0];
          alan.marka = [{ deger: markaAdi, agirlik: MARKALAR.indexOf(markaAdi) !== -1 ? 4 : 0.4 }];
          break;
        }
      }
    }

    etiket = etiketiBul(liste, /T[I1]CAR[I1T]?\s*AD[I1]/)[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 2);
      for (i = 0; i < degerler.length; i++) {
        t = ilkParca(degerler[i]).replace(/^[^A-Z0-9]+|[^A-Z0-9.]+$/g, "").replace(/^(?:[A-Z]{1,2}\s+)+(?=[A-Z0-9.]{6,})/, "").trim();
        if (t.length >= 3 && !etiketMi(t) && /[A-Z]{2}/.test(t)) {
          alan.model = [{ deger: t, agirlik: 1 }];
          break;
        }
      }
    }

    etiket = etiketiBul(liste, /MODEL\s*Y[I1L]+/)[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 2);
      for (i = 0; i < degerler.length; i++) {
        var yil = /(?:^|[^\d/.])((?:19[5-9]|20[0-4])\d)(?![\d/])/.exec(degerler[i]);
        if (yil) {
          alan.model_yili = [{ deger: yil[1], agirlik: 1 }];
          break;
        }
      }
    }

    etiket = etiketiBul(liste, /RENG[I1]/)[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 2);
      var renkBulundu = false;
      for (i = 0; i < degerler.length && !renkBulundu; i++) {
        var kelimeler = degerler[i].split(/[^A-Z]+/);
        for (var k = 0; k < kelimeler.length; k++) {
          if (RENKLER[kelimeler[k]]) {
            alan.renk = [{ deger: RENKLER[kelimeler[k]], agirlik: 1 }];
            renkBulundu = true;
            break;
          }
        }
      }
    }

    etiket = etiketiBul(liste, /C[I1]NS[I1]/).filter(function (e) { return !/MOTOR|KULLANIM/.test(e.satir); })[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 3).join(" ");
      var tip = null;
      if (/KAMYONET/.test(degerler)) tip = "kamyonet";
      else if (/KAMYON/.test(degerler)) tip = "kamyon";
      else if (/MIN[I1]BUS/.test(degerler)) tip = "minibus";
      else if (/OTOBUS/.test(degerler)) tip = "otobus";
      else if (/MOTOS[I1]KLET|MOTOR B[I1]S/.test(degerler)) tip = "motosiklet";
      else if (/OTOMOB[I1]L/.test(degerler)) tip = "binek";
      if (tip) alan.arac_tipi = [{ deger: tip, agirlik: 1 }];
    }

    etiket = etiketiBul(liste, /YAK[I1]T\s*C[I1]NS[I1]/)[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 2).join(" ");
      var yakit = null;
      if (/H[I1]BR[I1]T/.test(degerler)) yakit = "hibrit";
      else if (/ELEKTR[I1]K/.test(degerler)) yakit = "elektrik";
      else if (/LPG/.test(degerler)) yakit = "lpg";
      else if (/D[I1]ZEL|MOTOR[I1]N/.test(degerler)) yakit = "dizel";
      else if (/BENZ[I1]N/.test(degerler)) yakit = "benzin";
      if (yakit) alan.yakit_tipi = [{ deger: yakit, agirlik: 1 }];
    }

    etiket = etiketiBul(liste, /[SŞ]ASE\s*NO|SASE\s*NO|SAS[E3]\s*N[O0]/)[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 2);
      var vinAdaylari = [];
      degerler.forEach(function (satir) {
        satir.split(/ +/).forEach(function (parcaMetni) {
          var temiz = vinTemizle(parcaMetni);
          if (temiz.length === 17 && /[A-Z]/.test(temiz) && /\d/.test(temiz)) vinAdaylari.push(temiz);
        });
        var birlesik = vinTemizle(satir);
        if (birlesik.length === 17 && /[A-Z]/.test(birlesik) && /\d/.test(birlesik)) vinAdaylari.push(birlesik);
      });
      if (vinAdaylari.length) {
        var onarilmis = vinOnar(vinAdaylari[0]);
        alan.sase_no = [{ deger: onarilmis.deger, agirlik: onarilmis.dogrulandi ? 2 : 1, dogrulandi: onarilmis.dogrulandi }];
      }
    }

    etiket = etiketiBul(liste, /MOTOR\s*N[O0]/)[0];
    if (etiket) {
      degerler = degerSatirlari(liste, etiket, 2);
      for (i = 0; i < degerler.length; i++) {
        var adaylar = degerler[i].split(/ +/).map(function (x) { return x.replace(/[^A-Z0-9\-]/g, ""); })
          .filter(function (x) { return x.length >= 6 && x.length <= 20 && /\d/.test(x) && /[A-Z]/.test(x) && !/^\(/.test(x); });
        if (adaylar.length) {
          alan.motor_no = [{ deger: adaylar[0], agirlik: 1 }];
          break;
        }
      }
    }

    var tarihler = tarihAdayi(parca.metin);
    if (tarihler.length) alan.muayene_bitis_tarihi = tarihler.map(function (d) { return { deger: d, agirlik: 1 }; });

    etiket = etiketiBul(liste, /UNVAN|SOYAD[I1]/)[0];
    if (etiket) {
      var sahipSatirlari = [];
      var ayni = liste[etiket.index].slice(etiket.bitis).replace(/^[^A-Z0-9]+/, "").trim();
      if (ayni) sahipSatirlari.push(ayni);
      var sonraki = sonrakiSatirlar(liste, etiket.index + 1, 3);
      for (i = 0; i < sonraki.length; i++) {
        if (/\(C\.?1\.?2\)|ADRES/.test(sonraki[i])) break;
        sahipSatirlari.push(sonraki[i].trim());
      }
      var sahip = sahipSatirlari.join(" ").replace(/\([^)]*\)/g, " ").replace(/[^A-Z0-9 .&\-]/g, " ").replace(/ {2,}/g, " ").trim();
      if (sahip.length >= 5 && /(SAN|TIC|LTD|LIM|A\.?S|SIRKET|ORTAK|KOOP|INSAAT|TUR|GIDA|[A-Z]{4,} [A-Z]{2,})/.test(sahip)) {
        alan.ruhsat_sahibi = [{ deger: sahip, agirlik: 1 }];
      }
    }

    return alan;
  }

  function analiz(parcalar) {
    var oylar = {};
    parcalar.forEach(function (parca) {
      var alanlar = parcadanAlanlar(parca);
      Object.keys(alanlar).forEach(function (ad) {
        oylar[ad] = oylar[ad] || {};
        alanlar[ad].forEach(function (aday) {
          var puan = aday.agirlik * (1 + (Number(parca.guven) || 0) / 200);
          oylar[ad][aday.deger] = (oylar[ad][aday.deger] || 0) + puan;
        });
      });
    });
    var sonuc = {};
    Object.keys(oylar).forEach(function (ad) {
      var adaylar = Object.keys(oylar[ad]).sort(function (a, b) { return oylar[ad][b] - oylar[ad][a]; });
      sonuc[ad] = { deger: adaylar[0], aday_sayisi: adaylar.length };
      if (ad === "sase_no" && vinGecerli(adaylar[0])) sonuc[ad].dogrulandi = true;
    });
    return { alanlar: sonuc, etiketler: ETIKETLER };
  }

  window.AracRuhsatAyristirici = { analiz: analiz, etiketler: ETIKETLER, plakaAdaylari: plakaAdaylari };
})(window);
