(function (global) {
    "use strict";

    // Fotoğraflar sunucuya gitmeden önce tuvalde yeniden kodlandığı için EXIF
    // bloğu kayboluyor. Çekim anı bu yüzden ham dosyadan, küçültme öncesinde
    // okunup ayrı bir alanla gönderilir.

    var OKUNACAK_BAYT = 262144;

    function ikiBasamak(sayi) {
        return (sayi < 10 ? "0" : "") + sayi;
    }

    function tarihMetni(tarih) {
        if (!tarih || isNaN(tarih.getTime())) return null;
        return tarih.getFullYear() + "-" + ikiBasamak(tarih.getMonth() + 1) + "-" + ikiBasamak(tarih.getDate())
            + " " + ikiBasamak(tarih.getHours()) + ":" + ikiBasamak(tarih.getMinutes()) + ":" + ikiBasamak(tarih.getSeconds());
    }

    function asciiOku(dv, tiff, sayac, degerAlani, kucukSonlu) {
        var bas = sayac > 4 ? tiff + dv.getUint32(degerAlani, kucukSonlu) : degerAlani;
        var metin = "";
        for (var i = 0; i < sayac - 1; i++) {
            if (bas + i >= dv.byteLength) break;
            var karakter = dv.getUint8(bas + i);
            if (karakter === 0) break;
            metin += String.fromCharCode(karakter);
        }
        return metin;
    }

    function ifdGez(dv, ifd, kucukSonlu, isle) {
        if (ifd < 0 || ifd + 2 > dv.byteLength) return;
        var adet = dv.getUint16(ifd, kucukSonlu);
        for (var i = 0; i < adet; i++) {
            var giris = ifd + 2 + i * 12;
            if (giris + 12 > dv.byteLength) return;
            isle(
                dv.getUint16(giris, kucukSonlu),
                dv.getUint32(giris + 4, kucukSonlu),
                giris + 8
            );
        }
    }

    // "2026:08:12 14:33:20" -> "2026-08-12 14:33:20"
    function exifTarihiCevir(ham) {
        var eslesme = /^(\d{4}):(\d{2}):(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/.exec(String(ham || "").trim());
        if (!eslesme) return null;
        if (eslesme[1] === "0000") return null;
        return eslesme[1] + "-" + eslesme[2] + "-" + eslesme[3] + " " + eslesme[4] + ":" + eslesme[5] + ":" + eslesme[6];
    }

    function tiffOku(dv, tiff) {
        if (tiff + 8 > dv.byteLength) return null;

        var sira = dv.getUint16(tiff);
        var kucukSonlu;
        if (sira === 0x4949) kucukSonlu = true;
        else if (sira === 0x4d4d) kucukSonlu = false;
        else return null;

        if (dv.getUint16(tiff + 2, kucukSonlu) !== 0x002a) return null;

        var ifd0 = tiff + dv.getUint32(tiff + 4, kucukSonlu);
        var exifIfd = 0;
        var ifd0Tarih = null;

        ifdGez(dv, ifd0, kucukSonlu, function (etiket, sayac, degerAlani) {
            if (etiket === 0x8769) {
                exifIfd = tiff + dv.getUint32(degerAlani, kucukSonlu);
            } else if (etiket === 0x0132) {
                ifd0Tarih = asciiOku(dv, tiff, sayac, degerAlani, kucukSonlu);
            }
        });

        var orijinal = null;
        var dijital = null;
        if (exifIfd > 0) {
            ifdGez(dv, exifIfd, kucukSonlu, function (etiket, sayac, degerAlani) {
                if (etiket === 0x9003) {
                    orijinal = asciiOku(dv, tiff, sayac, degerAlani, kucukSonlu);
                } else if (etiket === 0x9004) {
                    dijital = asciiOku(dv, tiff, sayac, degerAlani, kucukSonlu);
                }
            });
        }

        return exifTarihiCevir(orijinal) || exifTarihiCevir(dijital) || exifTarihiCevir(ifd0Tarih);
    }

    function jpegExifBul(tampon) {
        var dv = new DataView(tampon);
        if (dv.byteLength < 4 || dv.getUint16(0) !== 0xffd8) return null;

        var konum = 2;
        while (konum + 4 <= dv.byteLength) {
            if (dv.getUint8(konum) !== 0xff) {
                konum++;
                continue;
            }
            var isaret = dv.getUint8(konum + 1);
            if (isaret === 0xff) {
                konum++;
                continue;
            }
            if (isaret === 0xd8 || isaret === 0x01 || (isaret >= 0xd0 && isaret <= 0xd7)) {
                konum += 2;
                continue;
            }
            if (isaret === 0xda || isaret === 0xd9) break;

            var boyut = dv.getUint16(konum + 2);
            if (boyut < 2) break;

            if (isaret === 0xe1 && konum + 10 <= dv.byteLength
                && dv.getUint32(konum + 4) === 0x45786966 && dv.getUint16(konum + 8) === 0x0000) {
                return tiffOku(dv, konum + 10);
            }

            konum += 2 + boyut;
        }
        return null;
    }

    function tamponaCevir(blob) {
        if (typeof blob.arrayBuffer === "function") {
            return blob.arrayBuffer();
        }
        if (typeof FileReader === "undefined") {
            return Promise.reject(new Error("FileReader yok"));
        }
        return new Promise(function (coz, ret) {
            var okuyucu = new FileReader();
            okuyucu.onload = function () { coz(okuyucu.result); };
            okuyucu.onerror = function () { ret(okuyucu.error || new Error("Dosya okunamadı")); };
            okuyucu.readAsArrayBuffer(blob);
        });
    }

    /**
     * Ham fotoğraftan çekim anını çıkarır.
     * Dönen değer sunucuya gönderilecek "kaynak|Y-m-d H:i:s" biçimidir;
     * hiçbir bilgi bulunamazsa boş metin döner.
     */
    function oku(dosya) {
        if (!dosya) return Promise.resolve("");

        var yedek = "";
        if (dosya.lastModified) {
            var dosyaTarihi = tarihMetni(new Date(dosya.lastModified));
            if (dosyaTarihi) yedek = "dosya|" + dosyaTarihi;
        }

        var tip = dosya.type || "";
        if (tip !== "image/jpeg" && tip !== "image/jpg") {
            return Promise.resolve(yedek);
        }

        var parca = typeof dosya.slice === "function" ? dosya.slice(0, OKUNACAK_BAYT) : dosya;

        return tamponaCevir(parca).then(function (tampon) {
            var tarih = jpegExifBul(tampon);
            return tarih ? "exif|" + tarih : yedek;
        }).catch(function () {
            return yedek;
        });
    }

    global.ExifCekim = { oku: oku };
})(typeof self !== "undefined" ? self : this);
