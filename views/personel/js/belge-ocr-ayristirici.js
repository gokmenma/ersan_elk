(function (window) {
  "use strict";

  var ETIKETLER = {
    tc_kimlik_no: "T.C. Kimlik No", adi_soyadi: "Ad Soyad", dogum_tarihi: "Doğum Tarihi",
    cinsiyet: "Cinsiyet", medeni_durum: "Medeni Durum", kan_grubu: "Kan Grubu",
    anne_adi: "Anne Adı", baba_adi: "Baba Adı", dogum_yeri_il: "Doğum Yeri İl",
    dogum_yeri_ilce: "Doğum Yeri İlçe", ehliyet_sinifi: "Ehliyet Sınıfı",
    cep_telefonu: "Cep Telefonu", cep_telefonu_2: "2. Cep Telefonu",
    email_adresi: "E-posta", adres: "Adres"
  };

  var EVRAK_ADLARI = {
    ehliyet: "Ehliyet", ikametgah: "İkametgah", adli_sicil_kaydi: "Adli Sicil Kaydı",
    nufus_kayit_ornegi: "Nüfus Kayıt Örneği", gizlilik_taahhutnamesi: "Gizlilik Taahhütnamesi",
    sozlesme: "Sözleşme", kimlik: "Kimlik", diploma: "Diploma", cv: "CV",
    saglik_raporu: "Sağlık Raporu", sertifika: "Sertifika", diger: "Diğer Personel Evrakı"
  };

  function temizle(value) {
    return String(value || "").replace(/^[\s:;|_-]+|[\s:;|_-]+$/gu, "").replace(/[ \t]+/gu, " ").trim();
  }

  function belgeTurunuBul(metin) {
    var text = String(metin || "").toLocaleLowerCase("tr-TR");
    var kurallar = {
      ehliyet: ["sürücü belgesi", "driving licence", "driving license"],
      ikametgah: ["yerleşim yeri", "ikametgah", "ikametgâh"],
      adli_sicil_kaydi: ["adli sicil"], nufus_kayit_ornegi: ["nüfus kayıt örneği", "aile nüfus"],
      saglik_raporu: ["sağlık raporu", "hekim raporu"],
      kimlik: ["kimlik kartı", "identity card", "türkiye cumhuriyeti kimlik"],
      diploma: ["diploma", "mezuniyet belgesi"], cv: ["özgeçmiş", "curriculum vitae"],
      sertifika: ["sertifika", "certificate"], sozlesme: ["iş sözleşmesi", "hizmet sözleşmesi"],
      gizlilik_taahhutnamesi: ["gizlilik taahhütnamesi", "gizlilik sözleşmesi"]
    };
    return Object.keys(kurallar).find(function (tur) {
      return kurallar[tur].some(function (ifade) { return text.indexOf(ifade) !== -1; });
    }) || "diger";
  }

  function etiketliDeger(metin, alternatifler) {
    for (var i = 0; i < alternatifler.length; i++) {
      var eslesme = metin.match(new RegExp("(?:" + alternatifler[i] + ")\\s*[:：]?\\s*([^\\r\\n]{2,100})", "iu"));
      if (eslesme) return temizle(eslesme[1]);
    }
    return "";
  }

  function tcDogrula(tc) {
    if (!/^[1-9]\d{10}$/.test(tc)) return false;
    var d = tc.split("").map(Number);
    var onuncu = (((d[0] + d[2] + d[4] + d[6] + d[8]) * 7) - (d[1] + d[3] + d[5] + d[7])) % 10;
    if (onuncu < 0) onuncu += 10;
    return d[9] === onuncu && d[10] === d.slice(0, 10).reduce(function (a, b) { return a + b; }, 0) % 10;
  }

  function adresNormalizeEt(adres) {
    adres = temizle(adres.replace(/\s*\n\s*/gu, " "));
    adres = adres.replace(/\b(?:adres tipi|adres türü|adres no|yerleşim yeri(?: adresi)?|yurtiçi)\b\s*[:|;-]*/giu, " ");
    adres = adres.replace(/^\s*adres\s*[:|;-]\s*/iu, "");
    adres = adres.split(/İŞBU YERLEŞİM YERİ|DİĞER ADRES BELGESİ|KİŞİNİN AİLE KÜTÜĞÜNDEKİ|KAYITLARI ESAS ALINARAK|ESAS ALINARAK/iu)[0];
    var numaradanSonra = adres.match(/\b\d{8,12}\b\s*[|;,\-]*\s*((?:[\p{L}0-9ÇĞİÖŞÜçğıöşü.\/ \-]+)(?:MAH\.?|MAHALLESİ)[\s\S]*)/iu);
    if (numaradanSonra) adres = numaradanSonra[1];
    return temizle(adres.replace(/\s*\|\s*/gu, " ").replace(/\s+/gu, " "));
  }

  function adresGecerliMi(adres) {
    return adres.length >= 12 && !/kimlik\s*no|t\.c\.?\s*kimlik|doğum tarihi/iu.test(adres) &&
      /\b(mah\.?|mahallesi|cad\.?|caddesi|sok\.?|sokağı|sk\.?|bulvarı|no\s*:|köyü)\b/iu.test(adres);
  }

  function adresPuani(adres) {
    var puan = adres.length;
    ["MAH", "SK", "SOK", "CAD", "NO", "KAPI", "/"].forEach(function (isaret) {
      if (adres.toLocaleUpperCase("tr-TR").indexOf(isaret) !== -1) puan += 25;
    });
    if (/(?:İÇ\s+)?KAPI\s+NO\s*:?\s*\d+/iu.test(adres)) puan += 60;
    if (/İŞBU|AİLE KÜTÜĞÜ|ESAS ALINARAK/iu.test(adres)) puan -= 250;
    return puan;
  }

  function adresCikar(metin) {
    var adaylar = [];
    var bolum = metin.match(/(?:yerleşim yeri adresi|adres)\s*[:：]?\s*([\s\S]{10,350}?)(?:\n\s*\n|belge no|düzenleme tarihi|açıklama)/iu);
    if (bolum) {
      var bolumAdresi = adresNormalizeEt(bolum[1]);
      if (adresGecerliMi(bolumAdresi)) adaylar.push(bolumAdresi);
    }
    var satirlar = metin.split(/\r?\n/u);
    satirlar.forEach(function (satir, index) {
      if (!/\b(MAH\.?|MAHALLESİ|CAD\.?|SOK\.?|SK\.?|NO\s*:)/iu.test(satir)) return;
      var birlesik = satir;
      for (var offset = 1; offset <= 3; offset++) {
        var devam = temizle(satirlar[index + offset] || "");
        if (!devam || /belge no|düzenleme tarihi|açıklama|imza/iu.test(devam)) break;
        if (/\d|\/|İLÇE|MAHALLE|KAPI|[A-ZÇĞİÖŞÜ]{4,}/u.test(devam)) birlesik += " " + devam;
      }
      var adres = adresNormalizeEt(birlesik);
      if (adresGecerliMi(adres)) adaylar.push(adres);
    });
    adaylar.sort(function (a, b) { return adresPuani(b) - adresPuani(a); });
    return (adaylar[0] || "").slice(0, 500);
  }

  function alanlariCikar(metin, tur) {
    var sonuc = {};
    var bitisikMetin = metin.replace(/(?<=\d)\s+(?=\d)/gu, "");
    var tc = bitisikMetin.match(/\b[1-9][0-9]{10}\b/u);
    if (tc) sonuc.tc_kimlik_no = tc[0];
    var etiketler = {
      adi_soyadi: ["adı soyadı", "ad soyad", "surname.*name", "soyadı.*adı"],
      anne_adi: ["anne adı", "ana adı", "mother(?:'s)? name"],
      baba_adi: ["baba adı", "father(?:'s)? name"],
      dogum_tarihi: ["doğum tarihi", "date of birth"], dogum_yeri_il: ["doğum yeri", "place of birth"],
      medeni_durum: ["medeni hali", "medeni durum"], kan_grubu: ["kan grubu"],
      email_adresi: ["e-?posta", "email"], cep_telefonu: ["cep telefonu", "telefon(?:u)?", "gsm", "mobile"]
    };
    Object.keys(etiketler).forEach(function (alan) {
      var deger = etiketliDeger(metin, etiketler[alan]);
      if (deger) sonuc[alan] = deger;
    });
    var tarih = String(sonuc.dogum_tarihi || "").match(/(\d{1,2})[./-](\d{1,2})[./-](\d{4})/u);
    if (tarih) sonuc.dogum_tarihi = tarih[1].padStart(2, "0") + "." + tarih[2].padStart(2, "0") + "." + tarih[3];
    var telefon = String(sonuc.cep_telefonu || "").match(/(?:\+?90\s*)?0?5\d{2}[\s.-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}/u);
    if (telefon) {
      var rakam = telefon[0].replace(/\D/g, "");
      if (rakam.length === 12 && rakam.indexOf("90") === 0) rakam = rakam.slice(2);
      if (rakam.length === 10 && rakam[0] === "5") rakam = "0" + rakam;
      sonuc.cep_telefonu = rakam;
    } else delete sonuc.cep_telefonu;
    var email = metin.match(/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u);
    if (email) sonuc.email_adresi = email[0];
    if (tur === "ehliyet") {
      var sinif = metin.match(/(?:\b9|sınıf(?:ı)?|class)\s*[.。,：:;|\-]?\s*([A-GM8])/iu);
      if (sinif) sonuc.ehliyet_sinifi = sinif[1].toLocaleUpperCase("tr-TR") === "8" ? "B" : sinif[1].toLocaleUpperCase("tr-TR");
    }
    if (/\bkadın\b|\bfemale\b/iu.test(metin)) sonuc.cinsiyet = "Kadın";
    else if (/\berkek\b|\bmale\b/iu.test(metin)) sonuc.cinsiyet = "Erkek";
    var medeni = metin.match(/\b(evli|bekar|bekâr)\b/iu);
    if (medeni) sonuc.medeni_durum = medeni[1].toLocaleLowerCase("tr-TR") === "evli" ? "Evli" : "Bekar";
    if (tur === "ikametgah") {
      var adres = adresCikar(metin);
      if (adres) sonuc.adres = adres;
    }
    return sonuc;
  }

  function alanGuveni(alan, deger, ocrGuveni) {
    var puan = Number(ocrGuveni) || 50;
    if (alan === "tc_kimlik_no") puan += tcDogrula(deger) ? 10 : -35;
    else if (alan === "dogum_tarihi") puan += /^\d{2}\.\d{2}\.\d{4}$/.test(deger) ? 7 : -20;
    else if (alan === "email_adresi") puan += /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(deger) ? 8 : -25;
    else if (alan === "cep_telefonu" || alan === "cep_telefonu_2") puan += /^05\d{9}$/.test(deger) ? 8 : -25;
    else if (alan === "adres") puan += adresGecerliMi(deger) ? 3 : -30;
    else if (alan === "ehliyet_sinifi") puan += 20;
    return Math.max(10, Math.min(99, Math.round(puan)));
  }

  function analiz(ocrBelgeleri) {
    var alanlar = [], belgeler = [], uyarilar = [], gorulen = {};
    ocrBelgeleri.forEach(function (belge, index) {
      var metin = temizle(String(belge.metin || "").replace(/[ \t]+/gu, " "));
      var guven = Math.max(1, Math.min(99, Math.round(Number(belge.guven) || 50)));
      var tur = belgeTurunuBul(metin);
      belgeler.push({sira: index + 1, evrak_turu: tur, evrak_adi: EVRAK_ADLARI[tur], guven: tur === "diger" ? Math.max(35, guven - 25) : Math.min(98, guven + 5)});
      Object.entries(alanlariCikar(metin, tur)).forEach(function (entry) {
        var alan = entry[0], deger = temizle(entry[1]);
        var anahtar = alan + "|" + deger.toLocaleLowerCase("tr-TR").replace(/\s+/gu, " ");
        if (!deger || gorulen[anahtar] || !ETIKETLER[alan]) return;
        gorulen[anahtar] = true;
        alanlar.push({alan: alan, etiket: ETIKETLER[alan], deger: deger, kaynak: "Belge " + (index + 1), guven: alanGuveni(alan, deger, guven)});
      });
      if (metin.length < 20) uyarilar.push("Belge " + (index + 1) + " yeterince okunamadı. Daha net veya düz çekilmiş bir belge deneyin.");
    });
    return {alanlar: alanlar, belgeler: belgeler, uyarilar: uyarilar};
  }

  window.PersonelBelgeOcrAyristirici = {analiz: analiz};
})(window);
