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

  var HARF_HARITASI = {
    "Ç": "c", "ç": "c", "Ğ": "g", "ğ": "g", "İ": "i", "I": "i", "ı": "i",
    "Ö": "o", "ö": "o", "Ş": "s", "ş": "s", "Ü": "u", "ü": "u",
    "Â": "a", "â": "a", "Î": "i", "î": "i", "Û": "u", "û": "u",
    "’": "'", "‘": "'", "´": "'", "–": "-", "—": "-"
  };

  var ISIM_ALANLARI = ["adi", "soyadi", "adi_soyadi", "anne_adi", "baba_adi", "dogum_yeri", "dogum_yeri_il", "dogum_yeri_ilce"];

  var INGILIZCE_KALINTI = /^(?:no|nos|name|names|s|si|sr|surname|given|first|date|place|of|birth|gender|sex|nationality|mother|father|valid|until|document|identity|blood|group|type|address|mobile|phone|email|mail)$/u;

  var KURUM_KELIMELERI = /\b(?:bakanlik|bakanligi|mudurluk|mudurlugu|baskanlik|baskanligi|cumhuriyet|cumhuriyeti|valilik|valiligi|kaymakamlik|kaymakamligi|nufus|belediye|genel|daire|kurum|makam|karti|turkiye|republic|identity|issued|imza|signature|isleri|icisleri|noter|okulu|universite|universitesi|hastane|hastanesi|sirket|ltd|a\.s)\b/u;

  function normalize(value) {
    var kaynak = String(value == null ? "" : value);
    var sonuc = "";
    for (var i = 0; i < kaynak.length; i++) {
      var harf = kaynak[i];
      var kucuk = HARF_HARITASI[harf];
      if (kucuk === undefined) {
        kucuk = harf.toLowerCase();
        if (kucuk.length !== 1) kucuk = harf;
      }
      sonuc += kucuk;
    }
    return sonuc;
  }

  function turkceHarfSayisi(deger) {
    return (String(deger).match(/[çğıöşüÇĞİÖŞÜ]/gu) || []).length;
  }

  function temizle(value) {
    return String(value == null ? "" : value)
      .replace(/[ \t]/gu, " ")
      .replace(/^[\s:：;|_.\-\/]+|[\s:：;|_\-]+$/gu, "")
      .replace(/ {2,}/gu, " ")
      .trim();
  }

  function metinSatirlari(metin) {
    return String(metin == null ? "" : metin)
      .replace(/\r\n?/gu, "\n")
      .replace(/[ \t]/gu, " ")
      .split("\n")
      .map(function (satir) { return {ham: satir.replace(/\s+$/u, ""), normal: normalize(satir.replace(/\s+$/u, ""))}; })
      .filter(function (satir) { return satir.ham.trim() !== ""; });
  }

  function ikili(turkce, ingilizce) {
    return "(?:" + turkce + ")(?:\\s*[\\/|,]\\s*(?:" + ingilizce + "))?|(?:" + ingilizce + ")";
  }

  var ETIKET_KURALLARI = [
    {alan: "tc_kimlik_no", kalip: ikili("t\\s*\\.?\\s*c\\s*\\.?\\s*kimlik\\s*(?:no|numarasi|nu)\\s*\\.?", "(?:tr|t\\s*\\.?\\s*c\\s*\\.?)\\s*identity\\s*(?:no|number)\\s*\\.?")},
    {alan: "tc_kimlik_no", kalip: "kimlik\\s*(?:no|numarasi)\\s*\\.?|identity\\s*(?:no|number)\\s*\\.?|tckn|t\\s*\\.\\s*c\\s*\\.\\s*no"},
    {alan: "yoksay", kalip: ikili("seri\\s*(?:no|numarasi)\\s*\\.?", "document\\s*no\\s*\\.?|serial\\s*no")},
    {alan: "yoksay", kalip: "belge\\s*(?:no|numarasi)|cuzdan\\s*no|kayit\\s*no|sira\\s*no|cilt\\s*no|hane\\s*no|birim\\s*adi|barkod"},
    {alan: "yoksay", kalip: ikili("son\\s*gecerlilik(?:\\s*tarihi)?", "valid\\s*until|date\\s*of\\s*expiry")},
    {alan: "yoksay", kalip: ikili("uyrugu?", "nationality")},
    {alan: "yoksay", kalip: "veril(?:is|me)\\s*(?:tarihi|nedeni|yeri)|duzenleme\\s*tarihi|belge\\s*duzenleme|onay\\s*kodu|dogrulama\\s*kodu"},
    {alan: "yoksay", kalip: ikili("veren\\s*makam|imzasi?", "issued\\s*by|signature")},
    {alan: "anne_adi", kalip: ikili("anne\\s*adi|ana\\s*adi", "mother'?\\s*s?\\s*name")},
    {alan: "baba_adi", kalip: ikili("baba\\s*adi", "father'?\\s*s?\\s*name")},
    {alan: "adi_soyadi", kalip: ikili("adi\\s{1,2}(?:ve\\s{1,2})?soyadi|ad\\s?[\\/|]\\s?soyad|isim\\s{1,2}soyisim|soyadi\\s*[,\\/]\\s*adi", "name\\s{1,2}(?:and\\s{1,2})?surname|full\\s{1,2}name")},
    {alan: "soyadi", kalip: ikili("soyadi|soyismi?", "surname|family\\s*name|last\\s*name")},
    {alan: "dogum_tarihi", kalip: ikili("dogum\\s*(?:tarihi|tar\\.?)", "date\\s*of\\s*birth|birth\\s*date")},
    {alan: "dogum_yeri", kalip: ikili("dogum\\s*yeri", "place\\s*of\\s*birth")},
    {alan: "cinsiyet", kalip: ikili("cinsiyeti?", "gender|sex")},
    {alan: "medeni_durum", kalip: ikili("medeni\\s*(?:hali|durumu?|statusu)", "marital\\s*status")},
    {alan: "kan_grubu", kalip: ikili("kan\\s*grubu", "blood\\s*(?:group|type)")},
    {alan: "cep_telefonu", kalip: ikili("cep\\s*(?:telefonu?|tel\\.?)|telefon(?:u|\\s*no)?|gsm", "mobile(?:\\s*phone)?|phone\\s*(?:no|number)?")},
    {alan: "email_adresi", kalip: ikili("e\\s*-?\\s*posta(?:\\s*adresi)?|eposta", "e\\s*-?\\s*mail(?:\\s*address)?|email")},
    {alan: "ehliyet_sinifi", kalip: ikili("(?:belge\\s*)?sinifi?", "class(?:es)?")},
    {alan: "adi", kalip: ikili("adi|adlari|isim", "given\\s*name\\s*\\(?s?\\)?|first\\s*name|name\\s*\\(?s?\\)?")},
    {alan: "adres", kalip: ikili("(?:yerlesim\\s*yeri\\s*)?adresi?", "address")}
  ];

  var DERLENMIS_KURALLAR = ETIKET_KURALLARI.map(function (kural) {
    return {alan: kural.alan, regex: new RegExp("(?:" + kural.kalip + ")", "gu")};
  });

  function satirEtiketleri(normalSatir) {
    var bulunanlar = [];
    DERLENMIS_KURALLAR.forEach(function (kural) {
      kural.regex.lastIndex = 0;
      var eslesme;
      while ((eslesme = kural.regex.exec(normalSatir)) !== null) {
        var kirpik = eslesme[0].replace(/\s+$/u, "");
        if (!kirpik.length) { kural.regex.lastIndex++; continue; }
        var bas = eslesme.index;
        var son = bas + kirpik.length;
        if (bas > 0 && /[a-z0-9]/u.test(normalSatir[bas - 1])) continue;
        if (son < normalSatir.length && /[a-z]/u.test(normalSatir[son])) continue;
        var cakisiyor = bulunanlar.some(function (onceki) { return bas < onceki.son && son > onceki.bas; });
        if (!cakisiyor) bulunanlar.push({alan: kural.alan, bas: bas, son: son});
      }
    });
    return bulunanlar.sort(function (a, b) { return a.bas - b.bas; });
  }

  var INGILIZCE_ETIKET_SONU = new RegExp(
    "^\\s*[\\/|1lI]?\\s*[A-Za-z'()\\s.\\-]{1,40}?\\b(?:name|names|surname|birth|no|number|gender|sex|nationality|until|expiry|by|group|type|address|card|licence|license)\\b\\s*", "iu");

  function ingilizceEtiketiAt(deger) {
    return String(deger).replace(INGILIZCE_ETIKET_SONU, "");
  }

  function sutunlaraBol(hamSatir) {
    var parcalar = [];
    var ayirici = / {2,}/gu;
    var bas = 0;
    var eslesme;
    while ((eslesme = ayirici.exec(hamSatir)) !== null) {
      parcalar.push({bas: bas, ham: hamSatir.slice(bas, eslesme.index)});
      bas = eslesme.index + eslesme[0].length;
    }
    parcalar.push({bas: bas, ham: hamSatir.slice(bas)});
    return parcalar.map(function (parca) {
      var bosluk = parca.ham.length - parca.ham.replace(/^\s+/u, "").length;
      return {bas: parca.bas + bosluk, metin: temizle(ingilizceEtiketiAt(parca.ham))};
    }).filter(function (parca) { return parca.metin !== ""; });
  }

  function sonrakiDegerParcalari(satirlar, index, kullanilan) {
    for (var i = index + 1; i < Math.min(satirlar.length, index + 3); i++) {
      if (kullanilan[i]) continue;
      if (satirEtiketleri(satirlar[i].normal).length) break;
      var parcalar = sutunlaraBol(satirlar[i].ham);
      if (parcalar.length) return {satir: i, parcalar: parcalar};
    }
    return {satir: -1, parcalar: []};
  }

  function isimGecerli(deger) {
    var normal = normalize(deger);
    if (/\d/u.test(deger)) return false;
    if (normal.replace(/[^a-z]/gu, "").length < 2) return false;
    if (KURUM_KELIMELERI.test(normal)) return false;
    if (/'\s?s\b/u.test(normal)) return false;
    var kelimeler = normal.split(/[^a-z']+/u).filter(Boolean);
    if (!kelimeler.length || kelimeler.length > 4) return false;
    if (kelimeler.some(function (kelime) { return kelime.length > 22 || /^(.)\1{2,}$/u.test(kelime); })) return false;
    return !kelimeler.some(function (kelime) { return INGILIZCE_KALINTI.test(kelime); });
  }

  function alanDegeriGecerli(alan, deger) {
    deger = temizle(deger);
    if (!deger || deger.length < 2) return false;
    if (alan === "adres") return adresGecerliMi(deger);
    if (alan === "yoksay") return true;
    return duzenle(alan, deger) !== "";
  }

  function tarihDuzenle(deger) {
    var eslesme = String(deger).match(/(\d{1,2})\s*[.\/\-]\s*(\d{1,2})\s*[.\/\-]\s*(\d{4})/u);
    if (!eslesme) return "";
    var gun = Number(eslesme[1]);
    var ay = Number(eslesme[2]);
    var yil = Number(eslesme[3]);
    if (gun < 1 || gun > 31 || ay < 1 || ay > 12 || yil < 1900 || yil > new Date().getFullYear()) return "";
    return String(gun).padStart(2, "0") + "." + String(ay).padStart(2, "0") + "." + yil;
  }

  function telefonDuzenle(deger) {
    var rakam = String(deger).replace(/\D/gu, "");
    if (rakam.length === 12 && rakam.indexOf("90") === 0) rakam = rakam.slice(2);
    if (rakam.length === 11 && rakam.indexOf("0") === 0) rakam = rakam.slice(1);
    if (rakam.length !== 10 || rakam[0] !== "5") return "";
    return "0" + rakam;
  }

  function kanGrubuDuzenle(deger) {
    var eslesme = normalize(deger).replace(/\s+/gu, "").match(/^(ab|a|b|0|o)(?:rh)?([+\-])?/u);
    if (!eslesme) return "";
    var grup = eslesme[1] === "o" ? "0" : eslesme[1].toUpperCase();
    return eslesme[2] ? grup + " Rh" + eslesme[2] : grup;
  }

  function isimDuzenle(deger) {
    var temiz = temizle(String(deger).replace(/[^\p{L}\s'.\-]/gu, " "))
      .split(/\s+/u)
      .filter(function (kelime) { return normalize(kelime).replace(/[^a-z]/gu, "").length >= 2; })
      .join(" ");
    return isimGecerli(temiz) ? temiz.slice(0, 60) : "";
  }

  function duzenle(alan, deger) {
    var temiz = temizle(deger);
    if (!temiz) return "";
    if (alan === "tc_kimlik_no") {
      var rakam = temiz.replace(/\D/gu, "");
      return /^[1-9]\d{10}$/.test(rakam) ? rakam : "";
    }
    if (alan === "dogum_tarihi") return tarihDuzenle(temiz);
    if (alan === "cep_telefonu" || alan === "cep_telefonu_2") return telefonDuzenle(temiz);
    if (alan === "email_adresi") {
      var eposta = temiz.match(/[\w.+\-]+@[\w.\-]+\.[A-Za-z]{2,}/u);
      return eposta ? eposta[0].toLowerCase() : "";
    }
    if (alan === "cinsiyet") {
      var cinsiyet = normalize(temiz);
      if (/^(?:k|kadin|f|female|bayan)\b/u.test(cinsiyet)) return "Kadın";
      if (/^(?:e|erkek|m|male|bay)\b/u.test(cinsiyet)) return "Erkek";
      return "";
    }
    if (alan === "medeni_durum") {
      var medeni = normalize(temiz);
      if (/\bevli\b|married/u.test(medeni)) return "Evli";
      if (/\bbekar\b|single/u.test(medeni)) return "Bekar";
      return "";
    }
    if (alan === "kan_grubu") return kanGrubuDuzenle(temiz);
    if (alan === "ehliyet_sinifi") {
      var sinif = normalize(temiz).replace(/[^a-z0-9]/gu, "");
      var bulunan = sinif.match(/^(?:m|a1|a2|a|b1|b|be|c1|c|ce|d1|d|de|f|g)\b/u) || sinif.match(/^[abcdefgm]/u);
      return bulunan ? bulunan[0].toUpperCase() : "";
    }
    if (alan === "adres") return adresGecerliMi(temiz) ? temiz.slice(0, 500) : "";
    if (ISIM_ALANLARI.indexOf(alan) !== -1) return isimDuzenle(temiz);
    return temiz;
  }

  function etiketliAlanlariTopla(satirlar) {
    var sonuc = {};
    var kullanilan = {};
    satirlar.forEach(function (satir, index) {
      var etiketler = satirEtiketleri(satir.normal);
      if (!etiketler.length) return;
      var degerler = etiketler.map(function (etiket, sira) {
        var bitis = sira + 1 < etiketler.length ? etiketler[sira + 1].bas : satir.ham.length;
        return temizle(ingilizceEtiketiAt(satir.ham.slice(etiket.son, bitis)));
      });
      var eksikler = [];
      etiketler.forEach(function (etiket, sira) {
        if (etiket.alan !== "yoksay" && !alanDegeriGecerli(etiket.alan, degerler[sira])) eksikler.push(sira);
      });
      if (eksikler.length) {
        var sonraki = sonrakiDegerParcalari(satirlar, index, kullanilan);
        var parcalar = sonraki.parcalar;
        var alinan = {};
        var bulundu = false;

        var ata = function (sira, parca, parcaIndex) {
          if (!parca || !alanDegeriGecerli(etiketler[sira].alan, parca.metin)) return;
          degerler[sira] = parca.metin;
          alinan[parcaIndex] = true;
          bulundu = true;
        };

        if (parcalar.length === etiketler.length) {
          eksikler.forEach(function (sira) { ata(sira, parcalar[sira], sira); });
        } else if (etiketler.length === 1 && parcalar.length) {
          ata(eksikler[0], parcalar[0], 0);
        } else {
          eksikler.forEach(function (sira) {
            var enIyi = -1;
            var enKisa = 16;
            parcalar.forEach(function (parca, parcaIndex) {
              var uzaklik = Math.abs(parca.bas - etiketler[sira].bas);
              if (alinan[parcaIndex] || uzaklik >= enKisa) return;
              enIyi = parcaIndex;
              enKisa = uzaklik;
            });
            if (enIyi >= 0) ata(sira, parcalar[enIyi], enIyi);
          });
        }
        if (bulundu && sonraki.satir >= 0) kullanilan[sonraki.satir] = true;
      }
      etiketler.forEach(function (etiket, sira) {
        if (etiket.alan === "yoksay" || sonuc[etiket.alan]) return;
        if (alanDegeriGecerli(etiket.alan, degerler[sira])) sonuc[etiket.alan] = temizle(degerler[sira]);
      });
    });
    return sonuc;
  }

  function mrzRakam(deger) {
    return String(deger).replace(/[OQD]/gu, "0").replace(/[IL]/gu, "1").replace(/Z/gu, "2")
      .replace(/S/gu, "5").replace(/B/gu, "8").replace(/G/gu, "6");
  }

  function mrzTarih(alti, gecmis) {
    var rakam = mrzRakam(alti);
    if (!/^\d{6}$/u.test(rakam)) return "";
    var yil = 2000 + Number(rakam.slice(0, 2));
    if (gecmis && yil > new Date().getFullYear() - 14) yil -= 100;
    return tarihDuzenle(rakam.slice(4, 6) + "." + rakam.slice(2, 4) + "." + yil);
  }

  function mrzCozumle(satirlar) {
    var mrzSatirlari = [];
    satirlar.forEach(function (satir) {
      var duz = satir.ham.replace(/\s+/gu, "").replace(/[«»‹›≪≫]/gu, "<").toUpperCase();
      if (/^[A-Z0-9<]{18,40}$/u.test(duz) && (duz.match(/</gu) || []).length >= 3) mrzSatirlari.push(duz);
    });
    if (mrzSatirlari.length < 2) return {};
    var sonuc = {};
    mrzSatirlari.forEach(function (satir) {
      if (sonuc.tc_kimlik_no) return;
      var adaylar = (mrzRakam(satir.slice(14)).match(/\d{11}/gu) || []).concat(mrzRakam(satir).match(/\d{11}/gu) || []);
      var gecerli = adaylar.find(tcDogrula);
      if (gecerli) sonuc.tc_kimlik_no = gecerli;
    });
    var isimSatiri = mrzSatirlari.map(function (satir) {
      return satir.replace(/([A-Z])\1{2,}/gu, function (dolgu) { return "<".repeat(dolgu.length); });
    }).filter(function (satir) {
      return /^[A-Z<]+$/u.test(satir) && satir.indexOf("<<") !== -1;
    }).pop();
    if (isimSatiri) {
      var parcalar = isimSatiri.replace(/<+$/u, "").split("<<");
      var soyad = isimDuzenle((parcalar[0] || "").replace(/</gu, " "));
      var ad = isimDuzenle((parcalar.slice(1).join(" ") || "").replace(/</gu, " "));
      if (ad && soyad && normalize(ad) !== normalize(soyad)) sonuc.adi_soyadi = ad + " " + soyad;
    }
    mrzSatirlari.forEach(function (satir) {
      if (sonuc.dogum_tarihi && sonuc.cinsiyet) return;
      var veri = satir.match(/^([A-Z0-9]{6})[A-Z0-9]([MFX<])([A-Z0-9]{6})/u);
      if (!veri) return;
      var dogum = mrzTarih(veri[1], true);
      if (dogum && !sonuc.dogum_tarihi) sonuc.dogum_tarihi = dogum;
      if (!sonuc.cinsiyet && veri[2] === "M") sonuc.cinsiyet = "Erkek";
      if (!sonuc.cinsiyet && veri[2] === "F") sonuc.cinsiyet = "Kadın";
    });
    return sonuc;
  }

  function belgeTurunuBul(metin, mrzVar) {
    var normal = normalize(metin);
    var kurallar = [
      ["ehliyet", ["surucu belgesi", "driving licence", "driving license", "surucu belgesi no"]],
      ["ikametgah", ["yerlesim yeri ve diger adres", "yerlesim yeri belgesi", "ikametgah", "adres bilgileri raporu"]],
      ["adli_sicil_kaydi", ["adli sicil"]],
      ["nufus_kayit_ornegi", ["nufus kayit ornegi", "aile nufus", "vukuatli nufus"]],
      ["saglik_raporu", ["saglik raporu", "hekim raporu", "isbasi saglik"]],
      ["kimlik", ["kimlik karti", "identity card", "turkiye cumhuriyeti kimlik", "nufus cuzdani"]],
      ["diploma", ["diploma", "mezuniyet belgesi", "gecici mezuniyet"]],
      ["cv", ["ozgecmis", "curriculum vitae"]],
      ["gizlilik_taahhutnamesi", ["gizlilik taahhutnamesi", "gizlilik sozlesmesi"]],
      ["sozlesme", ["is sozlesmesi", "hizmet sozlesmesi", "belirsiz sureli"]],
      ["sertifika", ["sertifika", "certificate", "katilim belgesi"]],
      ["yerlesim", ["yerlesim yeri"]]
    ];
    var bulunan = kurallar.find(function (kural) {
      return kural[1].some(function (ifade) { return normal.indexOf(ifade) !== -1; });
    });
    if (bulunan) return bulunan[0] === "yerlesim" ? "ikametgah" : bulunan[0];
    if (mrzVar) return "kimlik";
    var kartIsaretleri = ["surname", "given name", "date of birth", "identity no", "valid until", "republic of turkiye", "turkiye cumhuriyeti", "son gecerlilik"];
    var isaretSayisi = kartIsaretleri.filter(function (ifade) { return normal.indexOf(ifade) !== -1; }).length;
    return isaretSayisi >= 3 ? "kimlik" : "diger";
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
    adres = adres.split(/\b(?:CEP\s*TEL|TELEFON|GSM|E\s*-?\s*POSTA|E\s*-?\s*MA[İI]L|EMAIL|BEYAN\s*TAR[İI]H|BELGE\s*NO|KAYIT\s*NO|D[ÜU]ZENLEME|A[ÇC]IKLAMA|[İI]MZA|BARKOD)\b/iu)[0];
    var numaradanSonra = adres.match(/\b\d{8,12}\b\s*[|;,\-]*\s*((?:[\p{L}0-9ÇĞİÖŞÜçğıöşü.\/ \-]+)(?:MAH\.?|MAHALLESİ)[\s\S]*)/iu);
    if (numaradanSonra) adres = numaradanSonra[1];
    return temizle(adres.replace(/\s*\|\s*/gu, " ").replace(/\s+/gu, " "));
  }

  function adresGecerliMi(adres) {
    var normal = normalize(adres);
    if (String(adres).length < 12) return false;
    if (/kimlik\s*no|dogum\s*tarihi|mahalle\s*\/\s*koy|cilt\s*no|aile\s*sira|hane\s*no|kayit\s*no/u.test(normal)) return false;
    return /\b(?:mah\.?|mahallesi|cad\.?|caddesi|sok\.?|sokagi|sk\.?|bulvari|blv\.?|koyu|apt\.?|sitesi|blok)\b|no\s*:/u.test(normal);
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
    var satirlar = metin.split(/\r?\n/u).map(temizle).filter(Boolean);
    var konumDevamlari = satirlar.filter(function (satir) {
      return /^\d{1,4}\s+[A-ZÇĞİÖŞÜ][A-ZÇĞİÖŞÜ\s.\/-]{3,}$/u.test(satir) &&
        (/\//u.test(satir) || /[A-ZÇĞİÖŞÜ]{5,}/u.test(satir));
    });

    function devaminiEkle(adres) {
      if (!/(?:İÇ\s+)?KAPI\s+NO\s*:?\s*$/iu.test(adres)) return adres;
      var devam = konumDevamlari.find(function (satir) { return adres.indexOf(satir) === -1; });
      return devam ? temizle(adres + " " + devam) : adres;
    }

    var bolum = metin.match(/(?:yerleşim yeri adresi|adres)\s*[:：]?\s*([\s\S]{10,350}?)(?:\n\s*\n|belge no|düzenleme tarihi|açıklama)/iu);
    if (bolum) {
      var bolumAdresi = devaminiEkle(adresNormalizeEt(bolum[1]));
      if (adresGecerliMi(bolumAdresi)) adaylar.push(bolumAdresi);
    }
    satirlar.forEach(function (satir, index) {
      if (!/\b(MAH\.?|MAHALLESİ|CAD\.?|SOK\.?|SK\.?|NO\s*:)/iu.test(satir)) return;
      var birlesik = satir;
      for (var offset = 1; offset <= 3; offset++) {
        var devam = temizle(satirlar[index + offset] || "");
        if (!devam || /belge no|duzenleme tarihi|aciklama|imza|telefon|posta|beyan tarih|kayit no|barkod/u.test(normalize(devam))) break;
        if (/\d|\/|İLÇE|MAHALLE|KAPI|[A-ZÇĞİÖŞÜ]{4,}/u.test(devam)) birlesik += " " + devam;
      }
      var adres = devaminiEkle(adresNormalizeEt(birlesik));
      if (adresGecerliMi(adres)) adaylar.push(adres);
    });
    adaylar.sort(function (a, b) { return adresPuani(b) - adresPuani(a); });
    return (adaylar[0] || "").slice(0, 500);
  }

  function dogumYeriniAyir(deger, sonuc) {
    var parcalar = String(deger).split(/[\/|,]/u).map(temizle).filter(Boolean);
    if (parcalar.length >= 2) {
      if (!sonuc.dogum_yeri_ilce) sonuc.dogum_yeri_ilce = parcalar[0];
      if (!sonuc.dogum_yeri_il) sonuc.dogum_yeri_il = parcalar[parcalar.length - 1];
    } else if (parcalar.length === 1 && !sonuc.dogum_yeri_il) {
      sonuc.dogum_yeri_il = parcalar[0];
    }
  }

  function tumTarihler(metin) {
    return (metin.match(/\b\d{1,2}\s*[.\/\-]\s*\d{1,2}\s*[.\/\-]\s*\d{4}\b/gu) || [])
      .map(tarihDuzenle)
      .filter(Boolean);
  }

  function alanlariCikar(metin, tur, satirlar, mrz) {
    var adaylar = [];
    var eklenen = {};

    function ekle(alan, hamDeger, ek) {
      var deger = duzenle(alan, hamDeger);
      if (!deger || !ETIKETLER[alan]) return;
      var anahtar = alan + "|" + normalize(deger).replace(/\s+/gu, " ");
      var mevcut = eklenen[anahtar];
      if (mevcut) {
        if (turkceHarfSayisi(deger) > turkceHarfSayisi(mevcut.deger)) mevcut.deger = deger;
        mevcut.ek = Math.max(mevcut.ek, Number(ek) || 0);
        return;
      }
      var aday = {alan: alan, deger: deger, ek: Number(ek) || 0};
      eklenen[anahtar] = aday;
      adaylar.push(aday);
    }

    Object.keys(mrz).forEach(function (alan) { ekle(alan, mrz[alan], 32); });

    var etiketli = etiketliAlanlariTopla(satirlar);
    if (!etiketli.adi_soyadi && etiketli.adi && etiketli.soyadi && normalize(etiketli.adi) !== normalize(etiketli.soyadi)) {
      etiketli.adi_soyadi = etiketli.adi + " " + etiketli.soyadi;
    }
    if (etiketli.dogum_yeri) dogumYeriniAyir(etiketli.dogum_yeri, etiketli);
    delete etiketli.adi;
    delete etiketli.soyadi;
    delete etiketli.dogum_yeri;
    var etiketAdresi = etiketli.adres || "";
    delete etiketli.adres;
    Object.keys(etiketli).forEach(function (alan) { ekle(alan, etiketli[alan], 14); });

    var bitisikMetin = metin.replace(/(?<=\d)[ ]+(?=\d)/gu, "");
    (bitisikMetin.match(/\b[1-9]\d{10}\b/gu) || []).forEach(function (aday) {
      if (tcDogrula(aday)) ekle("tc_kimlik_no", aday, 18);
    });
    if (!adaylar.some(function (aday) { return aday.alan === "tc_kimlik_no"; })) {
      var ilkTc = bitisikMetin.match(/\b[1-9]\d{10}\b/u);
      if (ilkTc) ekle("tc_kimlik_no", ilkTc[0], 0);
    }

    var telefonlar = (metin.match(/(?:\+?90[\s.\-]*)?\(?0?5\d{2}\)?[\s.\-]*\d{3}[\s.\-]*\d{2}[\s.\-]*\d{2}/gu) || [])
      .map(telefonDuzenle).filter(Boolean);
    var benzersizTelefonlar = telefonlar.filter(function (telefon, sira) { return telefonlar.indexOf(telefon) === sira; });
    if (benzersizTelefonlar[0]) ekle("cep_telefonu", benzersizTelefonlar[0], 10);
    if (benzersizTelefonlar[1]) ekle("cep_telefonu_2", benzersizTelefonlar[1], 10);

    (metin.match(/[\w.+\-]+@[\w.\-]+\.[A-Za-z]{2,}/gu) || []).forEach(function (eposta) { ekle("email_adresi", eposta, 10); });

    var normalMetin = normalize(metin);
    if (/\bkadin\b|\bfemale\b/u.test(normalMetin)) ekle("cinsiyet", "Kadın", 0);
    else if (/\berkek\b|\bmale\b/u.test(normalMetin)) ekle("cinsiyet", "Erkek", 0);
    if (/\bevli\b/u.test(normalMetin)) ekle("medeni_durum", "Evli", 0);
    else if (/\bbekar\b/u.test(normalMetin)) ekle("medeni_durum", "Bekar", 0);

    if (!adaylar.some(function (aday) { return aday.alan === "dogum_tarihi"; }) && tur !== "diger") {
      var sinirYil = new Date().getFullYear() - 15;
      var tarihler = tumTarihler(metin).filter(function (tarih) { return Number(tarih.slice(6)) <= sinirYil; })
        .sort(function (a, b) { return Number(a.slice(6)) - Number(b.slice(6)); });
      if (tarihler.length) ekle("dogum_tarihi", tarihler[0], 0);
    }

    if (tur === "ehliyet") {
      var numarali = {};
      satirlar.forEach(function (satir) {
        var eslesme = satir.ham.match(/^\s*(\d)\s*[a-cA-C]?\s*[.)]\s*(\S.*)$/u);
        if (eslesme && !numarali[eslesme[1]]) numarali[eslesme[1]] = temizle(eslesme[2]);
      });
      if (numarali["1"] && numarali["2"]) ekle("adi_soyadi", numarali["2"] + " " + numarali["1"], 12);
      if (numarali["3"]) {
        ekle("dogum_tarihi", numarali["3"], 12);
        ekle("dogum_yeri_il", numarali["3"].replace(/\d{1,2}\s*[.\/\-]\s*\d{1,2}\s*[.\/\-]\s*\d{4}/u, " "), 10);
      }
      if (numarali["9"]) ekle("ehliyet_sinifi", numarali["9"], 12);
      var sinif = metin.match(/(?:\b9|sınıf(?:ı)?|class)\s*[.。,：:;|\-]?\s*([A-GM8])/iu);
      if (sinif) ekle("ehliyet_sinifi", sinif[1] === "8" ? "B" : sinif[1], 12);
    }

    if (tur === "ikametgah" || tur === "nufus_kayit_ornegi" || /\b(?:MAH\.?|MAHALLESİ|CAD\.?|SOK\.?|SK\.?)\b/iu.test(metin)) {
      var adresAdaylari = [adresNormalizeEt(etiketAdresi), adresCikar(metin)]
        .filter(adresGecerliMi)
        .sort(function (a, b) { return adresPuani(b) - adresPuani(a); });
      if (adresAdaylari.length) ekle("adres", adresAdaylari[0], 8);
    }

    return adaylar;
  }

  function alanGuveni(alan, deger, ocrGuveni, ek) {
    var puan = (Number(ocrGuveni) || 50) + (Number(ek) || 0);
    if (alan === "tc_kimlik_no") puan += tcDogrula(deger) ? 12 : -35;
    else if (alan === "dogum_tarihi") puan += /^\d{2}\.\d{2}\.\d{4}$/.test(deger) ? 7 : -20;
    else if (alan === "email_adresi") puan += /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(deger) ? 8 : -25;
    else if (alan === "cep_telefonu" || alan === "cep_telefonu_2") puan += /^05\d{9}$/.test(deger) ? 8 : -25;
    else if (alan === "adres") puan += adresGecerliMi(deger) ? 3 : -30;
    else if (alan === "ehliyet_sinifi") puan += 20;
    else if (alan === "cinsiyet" || alan === "medeni_durum") puan += 5;
    return Math.max(10, Math.min(99, Math.round(puan)));
  }

  function analiz(ocrBelgeleri) {
    var alanlar = [], belgeler = [], uyarilar = [], gorulen = {};
    ocrBelgeleri.forEach(function (belge, index) {
      var metin = String(belge.metin || "").replace(/\r\n?/gu, "\n").replace(/[ \t]/gu, " ");
      var guven = Math.max(1, Math.min(99, Math.round(Number(belge.guven) || 50)));
      var satirlar = metinSatirlari(metin);
      var mrz = mrzCozumle(satirlar);
      var tur = belgeTurunuBul(metin, Object.keys(mrz).length > 0);
      belgeler.push({
        sira: index + 1, evrak_turu: tur, evrak_adi: EVRAK_ADLARI[tur],
        guven: tur === "diger" ? Math.max(35, guven - 25) : Math.min(98, guven + 5)
      });
      alanlariCikar(metin, tur, satirlar, mrz).forEach(function (aday) {
        var anahtar = aday.alan + "|" + normalize(aday.deger).replace(/\s+/gu, " ");
        var puan = alanGuveni(aday.alan, aday.deger, guven, aday.ek);
        var mevcut = gorulen[anahtar];
        if (mevcut) {
          if (turkceHarfSayisi(aday.deger) > turkceHarfSayisi(mevcut.deger)) mevcut.deger = aday.deger;
          mevcut.guven = Math.max(mevcut.guven, puan);
          return;
        }
        gorulen[anahtar] = {
          alan: aday.alan, etiket: ETIKETLER[aday.alan], deger: aday.deger,
          kaynak: "Belge " + (index + 1), guven: puan
        };
        alanlar.push(gorulen[anahtar]);
      });
      if (metin.replace(/\s/gu, "").length < 20) {
        uyarilar.push("Belge " + (index + 1) + " yeterince okunamadı. Daha net veya düz çekilmiş bir belge deneyin.");
      }
    });
    return {alanlar: alanlar, belgeler: belgeler, uyarilar: uyarilar};
  }

  window.PersonelBelgeOcrAyristirici = {analiz: analiz, normalize: normalize};
})(window);
