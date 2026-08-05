(function (global) {
    "use strict";

    function tuvalOlustur(g, y) {
        if (typeof OffscreenCanvas === "function") {
            try {
                return new OffscreenCanvas(g, y);
            } catch (e) {}
        }
        if (typeof document === "undefined") return null;
        return document.createElement("canvas");
    }

    function tuvalBlobu(tuval, kalite) {
        if (typeof tuval.convertToBlob === "function") {
            return tuval.convertToBlob({ type: "image/jpeg", quality: kalite });
        }
        return new Promise(function (coz) {
            tuval.toBlob(function (blob) { coz(blob); }, "image/jpeg", kalite);
        });
    }

    // Ham telefon fotoğrafını yüklemeden önce küçültür. Desteklenmeyen tarayıcıda
    // ya da küçültme kazanç sağlamadığında dosya olduğu gibi geri döner.
    function kucult(dosya, maxKenar, kalite) {
        maxKenar = maxKenar || 1600;
        kalite = kalite || 0.75;

        if (!dosya || !dosya.type || dosya.type.indexOf("image/") !== 0) return Promise.resolve(dosya);
        if (dosya.type === "image/gif") return Promise.resolve(dosya);
        if (typeof createImageBitmap !== "function") return Promise.resolve(dosya);

        // imageOrientation desteklenmeyen tarayıcılarda seçeneksiz tekrar denenir,
        // aksi halde telefonla dik çekilen fotoğraflar yan kaydedilir.
        var bitmapSozu = createImageBitmap(dosya, { imageOrientation: "from-image" })
            .catch(function () { return createImageBitmap(dosya); });

        return bitmapSozu.then(function (bitmap) {
            var oran = Math.min(1, maxKenar / Math.max(bitmap.width, bitmap.height));

            if (oran >= 1 && dosya.size <= 900 * 1024) {
                if (bitmap.close) bitmap.close();
                return dosya;
            }

            var g = Math.max(1, Math.round(bitmap.width * oran));
            var y = Math.max(1, Math.round(bitmap.height * oran));
            var tuval = tuvalOlustur(g, y);
            if (!tuval) {
                if (bitmap.close) bitmap.close();
                return dosya;
            }
            tuval.width = g;
            tuval.height = y;

            var ctx = tuval.getContext("2d");
            ctx.fillStyle = "#ffffff";
            ctx.fillRect(0, 0, g, y);
            ctx.drawImage(bitmap, 0, 0, g, y);
            if (bitmap.close) bitmap.close();

            return tuvalBlobu(tuval, kalite).then(function (blob) {
                if (!blob || blob.size >= dosya.size) return dosya;
                var ad = (dosya.name || "foto").replace(/\.[^.]+$/, "") + ".jpg";
                return new File([blob], ad, { type: "image/jpeg", lastModified: Date.now() });
            });
        }).catch(function () { return dosya; });
    }

    function listeyiKucult(dosyalar, maxKenar, kalite) {
        var sira = Promise.resolve();
        var sonuc = [];

        Array.prototype.forEach.call(dosyalar || [], function (dosya) {
            sira = sira.then(function () {
                return kucult(dosya, maxKenar, kalite).then(function (kucuk) { sonuc.push(kucuk); });
            });
        });

        return sira.then(function () { return sonuc; });
    }

    // Videonun süresini okur ve ilk karesinden kapak görseli üretir.
    // Süre/boyut sınırı aşılırsa hata döner; sunucu tarafında tekrar doğrulanır.
    function videoIncele(dosya, maxSure, maxByte) {
        return new Promise(function (coz, ret) {
            if (!dosya || !dosya.type || dosya.type.indexOf("video/") !== 0) {
                return ret(new Error("Yalnızca video dosyası yükleyebilirsiniz."));
            }
            if (dosya.size > maxByte) {
                return ret(new Error(
                    "Video boyutu en fazla " + Math.round(maxByte / 1048576) + " MB olabilir. " +
                    "Seçtiğiniz dosya " + (dosya.size / 1048576).toFixed(1) + " MB."
                ));
            }

            var url = URL.createObjectURL(dosya);
            var video = document.createElement("video");
            var bitti = false;

            function temizle() {
                URL.revokeObjectURL(url);
                video.removeAttribute("src");
                video.load();
            }

            function basarisiz(mesaj) {
                if (bitti) return;
                bitti = true;
                temizle();
                ret(new Error(mesaj));
            }

            var zamanAsimi = setTimeout(function () {
                basarisiz("Video okunamadı. Farklı bir dosya deneyin.");
            }, 15000);

            video.preload = "metadata";
            video.muted = true;
            video.playsInline = true;

            video.onerror = function () {
                clearTimeout(zamanAsimi);
                basarisiz("Video formatı bu cihazda okunamadı. MP4 olarak deneyin.");
            };

            video.onloadedmetadata = function () {
                var sure = video.duration;
                if (!isFinite(sure) || sure <= 0) {
                    clearTimeout(zamanAsimi);
                    return basarisiz("Video süresi okunamadı.");
                }
                if (sure > maxSure + 0.5) {
                    clearTimeout(zamanAsimi);
                    return basarisiz(
                        "Video en fazla " + maxSure + " saniye olabilir. " +
                        "Seçtiğiniz video " + Math.round(sure) + " saniye."
                    );
                }

                video.onseeked = function () {
                    if (bitti) return;
                    bitti = true;
                    clearTimeout(zamanAsimi);

                    var kapak = null;
                    try {
                        var oran = Math.min(1, 320 / Math.max(video.videoWidth, video.videoHeight));
                        var tuval = document.createElement("canvas");
                        tuval.width = Math.max(1, Math.round(video.videoWidth * oran));
                        tuval.height = Math.max(1, Math.round(video.videoHeight * oran));
                        tuval.getContext("2d").drawImage(video, 0, 0, tuval.width, tuval.height);
                        kapak = tuval.toDataURL("image/jpeg", 0.7);
                    } catch (e) {
                        kapak = null;
                    }

                    temizle();
                    coz({ dosya: dosya, sure: sure, kapak: kapak });
                };

                try {
                    video.currentTime = Math.min(0.1, sure / 2);
                } catch (e) {
                    clearTimeout(zamanAsimi);
                    bitti = true;
                    temizle();
                    coz({ dosya: dosya, sure: sure, kapak: null });
                }
            };

            video.src = url;
        });
    }

    function sureBicimle(saniye) {
        var s = Math.round(saniye || 0);
        return Math.floor(s / 60) + ":" + String(s % 60).padStart(2, "0");
    }

    global.ResimSikistir = { kucult: kucult, listeyiKucult: listeyiKucult };
    global.VideoKontrol = { incele: videoIncele, sureBicimle: sureBicimle };
})(window);
