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

    // Videonun kalitesini ve boyutunu istemcide (HTML5 MediaRecorder + Canvas) düşürür.
    // Dosya yine de belirlenen sınırı (maxByte) aşıyorsa videoyu otomatik olarak sonundan kırpar.
    function videoSikistir(dosya, ayarlar, ilerlemeCallback) {
        ayarlar = ayarlar || {};
        var maxEn = ayarlar.maxEn || 1280;
        var maxBoy = ayarlar.maxBoy || 720;
        var hedefBitrate = ayarlar.hedefBitrate || 1200000; // 1.2 Mbps
        var fps = ayarlar.fps || 25;
        var maxByteLimit = ayarlar.maxByte || (15 * 1024 * 1024);

        if (!dosya || !dosya.type || dosya.type.indexOf("video/") !== 0) {
            return Promise.resolve(dosya);
        }

        if (dosya.size <= 2 * 1024 * 1024 && !ayarlar.maxSureKirp) {
            return Promise.resolve(dosya);
        }

        if (typeof window === "undefined" || !window.MediaRecorder || !HTMLCanvasElement.prototype.captureStream) {
            return Promise.resolve(dosya);
        }

        var mime = "";
        var mimes = [
            "video/webm;codecs=vp8,opus",
            "video/webm;codecs=vp9,opus",
            "video/webm",
            "video/mp4;codecs=avc1.42E01E,mp4a.40.2",
            "video/mp4"
        ];
        for (var i = 0; i < mimes.length; i++) {
            if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(mimes[i])) {
                mime = mimes[i];
                break;
            }
        }
        if (!mime) {
            return Promise.resolve(dosya);
        }

        return new Promise(function (coz) {
            var url = URL.createObjectURL(dosya);
            var video = document.createElement("video");
            video.muted = true;
            video.playsInline = true;
            video.preload = "auto";

            function temizle() {
                URL.revokeObjectURL(url);
                video.removeAttribute("src");
                video.load();
            }

            video.onerror = function () {
                temizle();
                coz(dosya);
            };

            video.onloadedmetadata = function () {
                var sure = video.duration;
                if (!isFinite(sure) || sure <= 0) {
                    temizle();
                    return coz(dosya);
                }

                var g = video.videoWidth;
                var y = video.videoHeight;
                if (!g || !y) {
                    temizle();
                    return coz(dosya);
                }

                var oran = Math.min(1, maxEn / g, maxBoy / y);
                var hedefG = Math.max(2, Math.round((g * oran) / 2) * 2);
                var hedefY = Math.max(2, Math.round((y * oran) / 2) * 2);

                var tuval = document.createElement("canvas");
                tuval.width = hedefG;
                tuval.height = hedefY;
                var ctx = tuval.getContext("2d");

                var stream;
                try {
                    stream = tuval.captureStream(fps);
                } catch (e) {
                    temizle();
                    return coz(dosya);
                }

                var recorderOptions = { videoBitsPerSecond: hedefBitrate };
                if (mime) recorderOptions.mimeType = mime;

                var mediaRecorder;
                try {
                    mediaRecorder = new MediaRecorder(stream, recorderOptions);
                } catch (e) {
                    temizle();
                    return coz(dosya);
                }

                var parcalar = [];
                mediaRecorder.ondataavailable = function (e) {
                    if (e.data && e.data.size > 0) {
                        parcalar.push(e.data);
                    }
                };

                var bitti = false;
                function bitir() {
                    if (bitti) return;
                    bitti = true;
                    if (mediaRecorder.state !== "inactive") {
                        try { mediaRecorder.stop(); } catch (err) {}
                    }
                    video.pause();
                    temizle();
                }

                var hedefSureLimit = ayarlar.maxSureKirp || sure;

                mediaRecorder.onstop = function () {
                    var blob = new Blob(parcalar, { type: mime || "video/webm" });
                    var uzanti = (mime.indexOf("mp4") !== -1) ? ".mp4" : ".webm";
                    var ad = (dosya.name || "video").replace(/\.[^.]+$/, "") + "_opt" + uzanti;

                    // Eğer üretilen dosya hala sınırı aşıyorsa ve henüz kırpma denenmediyse sonundan kırp
                    if (blob && blob.size > maxByteLimit && !ayarlar._kirpildi) {
                        var oran = (maxByteLimit * 0.93) / blob.size;
                        var yeniKirpSure = Math.max(2, sure * oran);
                        var yeniAyarlar = Object.assign({}, ayarlar, {
                            maxSureKirp: yeniKirpSure,
                            _kirpildi: true
                        });
                        videoSikistir(dosya, yeniAyarlar, ilerlemeCallback).then(coz).catch(function () {
                            var yeniDosya = new File([blob], ad, { type: blob.type, lastModified: Date.now() });
                            coz(yeniDosya);
                        });
                        return;
                    }

                    if (blob && blob.size > 0 && blob.size < dosya.size) {
                        var yeniDosya = new File([blob], ad, { type: blob.type, lastModified: Date.now() });
                        coz(yeniDosya);
                    } else {
                        coz(dosya);
                    }
                };

                video.currentTime = 0;
                video.play().then(function () {
                    mediaRecorder.start(100);

                    function ciz() {
                        if (video.currentTime >= hedefSureLimit || video.ended || video.paused || bitti) {
                            bitir();
                            return;
                        }
                        ctx.drawImage(video, 0, 0, hedefG, hedefY);
                        if (ilerlemeCallback && sure > 0) {
                            var yuzde = Math.min(99, Math.round((video.currentTime / sure) * 100));
                            ilerlemeCallback(yuzde);
                        }
                        requestAnimationFrame(ciz);
                    }
                    ciz();
                }).catch(function () {
                    bitir();
                    coz(dosya);
                });

                setTimeout(function () {
                    if (!bitti) {
                        bitir();
                    }
                }, (sure + 5) * 1000);
            };
        });
    }

    // Videonun süresini okur, boyutunu düşürür ve ilk karesinden kapak görseli üretir.
    function videoIncele(dosya, maxSure, maxByte, otosikistir) {
        otosikistir = otosikistir !== false;
        return new Promise(function (coz, ret) {
            if (!dosya || !dosya.type || dosya.type.indexOf("video/") !== 0) {
                return ret(new Error("Yalnızca video dosyası yükleyebilirsiniz."));
            }
            // Sıkıştırma yapılacağı için ham dosya seçimine 100 MB'a kadar izin verilir
            var hamMaxByte = 100 * 1024 * 1024;
            if (dosya.size > hamMaxByte) {
                return ret(new Error(
                    "Seçtiğiniz video çok büyük (en fazla 100 MB olabilir). " +
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

                    var videoIsleme = (otosikistir && dosya.size > 2 * 1024 * 1024)
                        ? videoSikistir(dosya)
                        : Promise.resolve(dosya);

                    videoIsleme.then(function (islenmisDosya) {
                        if (islenmisDosya.size > maxByte) {
                            return ret(new Error(
                                "Video yükleme boyutu en fazla " + Math.round(maxByte / 1048576) + " MB olabilir. " +
                                "Dosya boyutu: " + (islenmisDosya.size / 1048576).toFixed(1) + " MB."
                            ));
                        }
                        coz({
                            dosya: islenmisDosya,
                            sure: sure,
                            kapak: kapak,
                            hamBoyut: dosya.size,
                            yeniBoyut: islenmisDosya.size,
                            sikistirildi: islenmisDosya.size < dosya.size
                        });
                    }).catch(function (hata) {
                        if (dosya.size > maxByte) {
                            return ret(new Error(
                                "Video boyutu en fazla " + Math.round(maxByte / 1048576) + " MB olabilir. " +
                                "Seçtiğiniz dosya " + (dosya.size / 1048576).toFixed(1) + " MB."
                            ));
                        }
                        coz({ dosya: dosya, sure: sure, kapak: kapak, hamBoyut: dosya.size, yeniBoyut: dosya.size, sikistirildi: false });
                    });
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
    global.VideoKontrol = { incele: videoIncele, sikistir: videoSikistir, sureBicimle: sureBicimle };
})(window);
