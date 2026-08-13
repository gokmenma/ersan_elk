/**
 * Ersan Elektrik - Çevrimdışı İşlem Kuyruğu
 *
 * Sahada internet yokken oluşturulan kayıtları fotoğraflarıyla birlikte
 * IndexedDB'de tutar, bağlantı geldiğinde sırayla api.php'ye gönderir.
 * Hem pencere hem de service worker bağlamında çalışacak şekilde yazılmıştır
 * (sw.js içinden importScripts ile yüklenir), bu yüzden window/document
 * erişimleri koşullu yapılır.
 */
(function (global) {
    "use strict";

    var DB_ADI = "ersan-pwa-offline";
    var DB_SURUM = 1;
    var STORE_KUYRUK = "kuyruk";
    var STORE_REFERANS = "referans";
    var SYNC_ETIKETI = "ersan-kuyruk-sync";
    var API_URL = "api.php";
    var BEKLEME_TABANI = 30000;
    var BEKLEME_TAVANI = 1800000;

    var pencerede = typeof window !== "undefined" && typeof document !== "undefined";
    var dbSozu = null;
    var calisiyor = false;

    // ---------- IndexedDB temeli ----------

    function dbAc() {
        if (dbSozu) return dbSozu;

        dbSozu = new Promise(function (resolve, reject) {
            var istek = indexedDB.open(DB_ADI, DB_SURUM);

            istek.onupgradeneeded = function () {
                var db = istek.result;
                if (!db.objectStoreNames.contains(STORE_KUYRUK)) {
                    var s = db.createObjectStore(STORE_KUYRUK, { keyPath: "uuid" });
                    s.createIndex("durum", "durum", { unique: false });
                    s.createIndex("olusturma", "olusturma", { unique: false });
                }
                if (!db.objectStoreNames.contains(STORE_REFERANS)) {
                    db.createObjectStore(STORE_REFERANS, { keyPath: "anahtar" });
                }
            };

            istek.onsuccess = function () { resolve(istek.result); };
            istek.onerror = function () { reject(istek.error); };
        });

        return dbSozu;
    }

    function islem(store, mod, fn) {
        return dbAc().then(function (db) {
            return new Promise(function (resolve, reject) {
                var t = db.transaction(store, mod);
                var istek = fn(t.objectStore(store));
                t.oncomplete = function () { resolve(istek ? istek.result : undefined); };
                t.onerror = function () { reject(t.error); };
                t.onabort = function () { reject(t.error); };
            });
        });
    }

    function uuidUret() {
        if (global.crypto && typeof global.crypto.randomUUID === "function") {
            return global.crypto.randomUUID();
        }
        var rnd = new Uint8Array(16);
        (global.crypto || {}).getRandomValues
            ? global.crypto.getRandomValues(rnd)
            : rnd.forEach(function (_, i) { rnd[i] = Math.floor(Math.random() * 256); });
        rnd[6] = (rnd[6] & 0x0f) | 0x40;
        rnd[8] = (rnd[8] & 0x3f) | 0x80;
        var hex = [];
        for (var i = 0; i < 16; i++) hex.push(("0" + rnd[i].toString(16)).slice(-2));
        return hex.slice(0, 4).join("") + "-" + hex.slice(4, 6).join("") + "-" +
            hex.slice(6, 8).join("") + "-" + hex.slice(8, 10).join("") + "-" + hex.slice(10, 16).join("");
    }

    function yerelZamanDamgasi(d) {
        d = d || new Date();
        var p = function (n) { return ("0" + n).slice(-2); };
        return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate()) + " " +
            p(d.getHours()) + ":" + p(d.getMinutes()) + ":" + p(d.getSeconds());
    }

    // ---------- Kuyruk işlemleri ----------

    function listele() {
        return islem(STORE_KUYRUK, "readonly", function (s) { return s.getAll(); })
            .then(function (kayitlar) {
                return (kayitlar || []).sort(function (a, b) {
                    return a.olusturma < b.olusturma ? -1 : (a.olusturma > b.olusturma ? 1 : 0);
                });
            })
            .catch(function () { return []; });
    }

    function oku(uuid) {
        return islem(STORE_KUYRUK, "readonly", function (s) { return s.get(uuid); });
    }

    function yaz(kayit) {
        return islem(STORE_KUYRUK, "readwrite", function (s) { return s.put(kayit); })
            .then(function () { return kayit; });
    }

    function sil(uuid) {
        return islem(STORE_KUYRUK, "readwrite", function (s) { return s.delete(uuid); })
            .then(function () { duyur({ sebep: "silindi", uuid: uuid }); });
    }

    /**
     * Kuyruğa yeni bir işlem ekler.
     * @param {string} action    api.php action adı
     * @param {object} alanlar   düz metin form alanları
     * @param {Array}  dosyalar  [{alan, ad, tip, blob}] ana istekle giden dosyalar
     * @param {object} ozet      listede gösterim için serbest alanlar
     * @param {object} ek        {action, alan, dosyalar[]} ana istekten sonra
     *                           her biri ayrı istekle gönderilecek dosyalar
     */
    function ekle(action, alanlar, dosyalar, ozet, ek) {
        ek = ek || {};

        var kayit = {
            uuid: uuidUret(),
            action: action,
            alanlar: alanlar || {},
            dosyalar: dosyalar || [],
            ozet: ozet || {},
            ekAction: ek.action || "",
            ekAlan: ek.alan || "",
            ekDosyalar: ek.dosyalar || [],
            anaGonderildi: false,
            ekGonderilen: 0,
            durum: "bekliyor",
            deneme: 0,
            hata: "",
            olusturma: new Date().toISOString(),
            olusturmaYerel: yerelZamanDamgasi(),
        };

        kayit.alanlar.client_uuid = kayit.uuid;
        kayit.alanlar.offline_olusturma = kayit.olusturmaYerel;
        if (action === "saveKacakBildirim") {
            kayit.alanlar.beklenen_foto_sayisi = (kayit.dosyalar || []).length + (kayit.ekDosyalar || []).length;
        }

        return yaz(kayit).then(function () {
            duyur({ sebep: "eklendi", uuid: kayit.uuid });
            syncKaydet();
            return kayit;
        });
    }

    function guncelle(uuid, alanlar, yeniDosyalar, yeniOzet, ekDosyalar) {
        return oku(uuid).then(function (kayit) {
            if (!kayit) return null;
            if (alanlar) {
                Object.keys(alanlar).forEach(function (k) {
                    kayit.alanlar[k] = alanlar[k];
                });
            }
            if (yeniDosyalar && yeniDosyalar.length > 0) {
                kayit.dosyalar = yeniDosyalar;
            }
            // Düzenlemede eklenen fotoğraflar sıranın sonuna yazılır; gönderilmiş
            // olanların sırası kaymadığı için sunucu mükerreri ayırt edebilir.
            if (ekDosyalar && ekDosyalar.length > 0) {
                kayit.ekDosyalar = (kayit.ekDosyalar || []).concat(ekDosyalar);
                kayit.alanlar.beklenen_foto_sayisi =
                    (kayit.dosyalar || []).length + kayit.ekDosyalar.length;
            }
            if (yeniOzet) {
                kayit.ozet = kayit.ozet || {};
                Object.keys(yeniOzet).forEach(function (k) {
                    kayit.ozet[k] = yeniOzet[k];
                });
            }
            kayit.durum = "bekliyor";
            kayit.hata = "";
            kayit.deneme = 0;
            return yaz(kayit).then(function () {
                duyur({ sebep: "guncellendi", uuid: uuid });
                return flush({ elle: true });
            });
        });
    }

    function tekrarDene(uuid) {
        return oku(uuid).then(function (kayit) {
            if (!kayit) return null;
            kayit.durum = "bekliyor";
            kayit.hata = "";
            kayit.deneme = 0;
            return yaz(kayit).then(function () {
                duyur({ sebep: "guncellendi", uuid: uuid });
                return flush({ elle: true });
            });
        });
    }

    // ---------- Gönderim ----------

    /**
     * Yanıtı üç gruba ayırır:
     *  - tamam   : kayıt sunucuya işlendi, kuyruktan silinir
     *  - bekle   : ağ/oturum kaynaklı geçici sorun, kayıt kuyrukta kalır
     *  - kalici  : sunucu kaydı reddetti (mükerrer, doğrulama), kullanıcı müdahalesi gerekir
     */
    function istekGonder(action, alanlar, dosyalar, etiket) {
        var fd = new FormData();
        fd.append("action", action);

        Object.keys(alanlar || {}).forEach(function (k) {
            fd.append(k, alanlar[k]);
        });
        // Çekim anı, dosya adıyla değil alan sırasıyla eşleştirilir; bilinmeyen
        // fotoğraflar için boş değer gönderilerek dizin hizası korunur.
        (dosyalar || []).forEach(function (d) {
            fd.append(d.alan, d.blob, d.ad);
            fd.append(
                d.alan.indexOf("[]") > -1 ? d.alan.replace("[]", "_cekim[]") : d.alan + "_cekim",
                d.cekim || ""
            );
        });

        // action hem gövdede hem adreste gönderilir: gövde ayrıştırılamazsa
        // sunucu isteği yine de doğru uca yönlendirebilir, ayrıca erişim
        // kayıtlarında hangi işlemin denendiği görünür.
        return fetch(API_URL + "?action=" + encodeURIComponent(action), {
            method: "POST",
            body: fd,
            credentials: "same-origin",
            cache: "no-store",
        }).then(function (yanit) {
            if (!yanit.ok) {
                return { sonuc: "bekle", mesaj: "Sunucu HTTP " + yanit.status + " döndü [" + etiket + "]" };
            }
            return yanit.json().then(function (json) {
                // Service worker çevrimdışıyken sahte yanıt üretiyor olabilir.
                // Sunucu isteği almış ama yanıt yolda kopmuş olabilir; bunu
                // istemciden ayırt etmek mümkün değil, mesaj buna göre yazılır.
                if (json && json.offline) {
                    return { sonuc: "bekle", mesaj: "Sunucudan yanıt alınamadı [" + etiket + "]" };
                }
                if (json && json.success) {
                    return { sonuc: "tamam", veri: json.data };
                }

                var mesaj = (json && (json.message || json.error)) || "Kayıt gönderilemedi.";
                var oturumSorunu = (json && json.redirect) || /oturum/i.test(mesaj);
                return { sonuc: oturumSorunu ? "bekle" : "kalici", mesaj: mesaj + " [" + etiket + "]" };
            }).catch(function () {
                // JSON yerine login sayfası gibi bir HTML dönmüşse oturum düşmüştür.
                return { sonuc: "bekle", mesaj: "Oturum doğrulanamadı [" + etiket + "]" };
            });
        }).catch(function (e) {
            var sebep = (e && e.message) || "bilinmeyen";
            return { sonuc: "bekle", mesaj: "Ağ hatası: " + sebep + " [" + etiket + "]" };
        });
    }

    function mbMetni(blob) {
        return (((blob && blob.size) || 0) / 1048576).toFixed(1) + " MB";
    }

    /**
     * Sunucudaki GD yalnızca JPEG/PNG çözebiliyor. Kuyruğa daha önce WebP
     * olarak yazılmış görseller gönderimden önce JPEG'e çevrilir; böylece
     * eski kayıtlar kullanıcı müdahalesi olmadan geçer.
     */
    function dosyaNormalize(d) {
        var blob = d && d.blob;
        var tip = (blob && blob.type) || d.tip || "";
        if (!blob || tip.indexOf("image/") !== 0) return Promise.resolve(d);
        if (tip === "image/jpeg" || tip === "image/png") return Promise.resolve(d);

        return fotografKucult(blob, 2200, 0.82).then(function (yeni) {
            if (!yeni || !yeni.blob || yeni.blob === blob) return d;
            return {
                alan: d.alan,
                ad: (d.ad || "foto").replace(/\.[^.]+$/, "") + ".jpg",
                tip: "image/jpeg",
                blob: yeni.blob,
                cekim: d.cekim || yeni.cekim || "",
            };
        }).catch(function () { return d; });
    }

    function kayitDosyalariniNormalize(kayit) {
        var hedefler = (kayit.dosyalar || []).concat(kayit.ekDosyalar || []);
        var duzeltilecek = hedefler.some(function (d) {
            var tip = (d && d.blob && d.blob.type) || (d && d.tip) || "";
            return tip.indexOf("image/") === 0 && tip !== "image/jpeg" && tip !== "image/png";
        });
        if (!duzeltilecek) return Promise.resolve(kayit);

        return Promise.all((kayit.dosyalar || []).map(dosyaNormalize)).then(function (ana) {
            return Promise.all((kayit.ekDosyalar || []).map(dosyaNormalize)).then(function (ek) {
                kayit.dosyalar = ana;
                kayit.ekDosyalar = ek;
                return yaz(kayit).then(function () { return kayit; });
            });
        }).catch(function () { return kayit; });
    }

    function gonder(kayit) {
        return kayitDosyalariniNormalize(kayit).then(function () { return gonderimeBasla(kayit); });
    }

    /**
     * Önce ana istek (kayıt + zorunlu dosya), ardından ek dosyalar teker teker
     * gönderilir. Tek büyük istek zayıf sahada gövde ve süre limitlerine
     * takılıyordu; parçalara bölününce her istek küçük kalıyor.
     *
     * Her adımın sonucu diske yazıldığı için uygulama kapanırsa kaldığı
     * fotoğraftan devam edilir, tamamlananlar ikinci kez yüklenmez.
     */
    function gonderimeBasla(kayit) {
        var ilk = Promise.resolve({ sonuc: "tamam" });

        if (!kayit.anaGonderildi) {
            var anaBoyut = (kayit.dosyalar || []).reduce(function (t, d) {
                return t + ((d.blob && d.blob.size) || 0);
            }, 0);

            ilk = istekGonder(
                kayit.action,
                kayit.alanlar,
                kayit.dosyalar,
                "kayıt, " + (anaBoyut / 1048576).toFixed(1) + " MB"
            ).then(function (cevap) {
                if (cevap.sonuc !== "tamam") return cevap;
                kayit.anaGonderildi = true;
                return yaz(kayit).then(function () { return cevap; });
            });
        }

        return ilk.then(function (cevap) {
            if (cevap.sonuc !== "tamam") return cevap;
            return ekDosyalariGonder(kayit);
        });
    }

    function ekDosyalariGonder(kayit) {
        var toplam = (kayit.ekDosyalar || []).length;

        if (!kayit.ekAction || kayit.ekGonderilen >= toplam) {
            return Promise.resolve({ sonuc: "tamam" });
        }

        var sira = kayit.ekGonderilen;
        var dosya = kayit.ekDosyalar[sira];

        return istekGonder(
            kayit.ekAction,
            { client_uuid: kayit.uuid, sira: sira, toplam: toplam },
            [{ alan: kayit.ekAlan, ad: dosya.ad, tip: dosya.tip, blob: dosya.blob, cekim: dosya.cekim || "" }],
            "fotoğraf " + (sira + 1) + "/" + toplam + ", " + mbMetni(dosya.blob)
        ).then(function (cevap) {
            if (cevap.sonuc !== "tamam") return cevap;
            kayit.ekGonderilen = sira + 1;
            return yaz(kayit).then(function () { return ekDosyalariGonder(kayit); });
        });
    }

    /**
     * Gönderim sırasında uygulama kapatılırsa kayıt "gonderiliyor" durumunda asılı
     * kalır ve bir daha sıraya girmez; her tur başında bunlar geri alınır.
     * Aynı kaydın sunucuya ikinci kez düşmesini client_uuid engeller.
     */
    function askidaKalanlariKurtar() {
        return listele().then(function (kayitlar) {
            var asili = kayitlar.filter(function (k) { return k.durum === "gonderiliyor"; });
            return Promise.all(asili.map(function (k) {
                k.durum = "bekliyor";
                return yaz(k);
            }));
        }).catch(function () { return null; });
    }

    /**
     * Parçalı yükleme öncesinde oluşmuş kayıtlar tüm fotoğrafları tek istekte
     * taşıyor; o boyutla sahada hiç gönderilemiyorlar. Sıraya girmeden önce
     * yeni biçime çevrilir, böylece cihazda bekleyen tutanaklar kurtarılır.
     */
    function eskiKayitlariDonustur() {
        return listele().then(function (kayitlar) {
            var donusecek = kayitlar.filter(function (k) {
                if (k.ekAction || k.anaGonderildi) return false;
                return (k.dosyalar || []).some(function (d) { return d.alan === "saha_fotolari[]"; });
            });

            return Promise.all(donusecek.map(function (k) {
                var ana = [];
                var ek = [];

                (k.dosyalar || []).forEach(function (d) {
                    if (d.alan === "saha_fotolari[]") {
                        ek.push({ ad: d.ad, tip: d.tip, blob: d.blob });
                    } else {
                        ana.push(d);
                    }
                });

                k.dosyalar = ana;
                k.ekAction = "addKacakSahaFoto";
                k.ekAlan = "foto";
                k.ekDosyalar = ek;
                k.ekGonderilen = 0;
                k.anaGonderildi = false;
                k.deneme = 0;
                k.sonDeneme = 0;
                k.hata = "";
                // Biçim değiştiği için eski başarısızlık geçersiz; kayıt yeniden sıraya alınır.
                k.durum = "bekliyor";

                return yaz(k);
            }));
        }).catch(function () { return null; });
    }

    /**
     * Sunucunun açamadığı biçimde (WebP) gönderildiği için "hata" durumunda
     * kalmış kayıtlar artık gönderim öncesi JPEG'e çevriliyor. Kullanıcının
     * elle "Tekrar Dene" demesini beklememek için bu kayıtlar bir kez
     * otomatik olarak sıraya geri alınır.
     */
    function cevrilebilirHatalariKurtar() {
        return listele().then(function (kayitlar) {
            var kurtarilacak = kayitlar.filter(function (k) {
                if (k.durum !== "hata" || k.bicimKurtarildi) return false;
                return (k.dosyalar || []).concat(k.ekDosyalar || []).some(function (d) {
                    var tip = (d && d.blob && d.blob.type) || (d && d.tip) || "";
                    return tip.indexOf("image/") === 0 && tip !== "image/jpeg" && tip !== "image/png";
                });
            });

            return Promise.all(kurtarilacak.map(function (k) {
                k.bicimKurtarildi = true;
                k.durum = "bekliyor";
                k.deneme = 0;
                k.sonDeneme = 0;
                k.hata = "";
                return yaz(k);
            }));
        }).catch(function () { return null; });
    }

    /**
     * Başarısız kayıt her turda yeniden denenirse mobil veri boşa gider
     * (sahada 100'ü aşkın denemeyle yüzlerce MB harcandığı görüldü).
     * Deneme sayısı arttıkça bekleme uzar; "Şimdi Gönder" bunu atlar.
     */
    function backoffBekliyor(kayit, elle) {
        if (elle || !kayit.sonDeneme || !kayit.deneme) return false;
        var bekleme = Math.min(kayit.deneme * BEKLEME_TABANI, BEKLEME_TAVANI);
        return (Date.now() - kayit.sonDeneme) < bekleme;
    }

    /**
     * Bekleyen kayıtları sırayla gönderir. Aynı anda tek gönderim çalışır.
     */
    function flush(secenekler) {
        secenekler = secenekler || {};

        if (calisiyor) {
            return Promise.resolve({ gonderildi: 0, kalan: null, atlandi: true });
        }
        if (typeof navigator !== "undefined" && navigator.onLine === false) {
            return Promise.resolve({ gonderildi: 0, kalan: null, cevrimdisi: true });
        }

        calisiyor = true;

        return askidaKalanlariKurtar()
            .then(eskiKayitlariDonustur)
            .then(cevrilebilirHatalariKurtar)
            .then(listele)
            .then(function (kayitlar) {
            var sira = kayitlar.filter(function (k) {
                if (backoffBekliyor(k, secenekler.elle)) return false;
                if (k.durum === "bekliyor") return true;
                return secenekler.elle && k.durum === "hata";
            });

            var gonderildi = 0;
            var basarisiz = 0;
            var agKesik = false;

            var zincir = sira.reduce(function (onceki, kayit) {
                return onceki.then(function () {
                    // Bağlantı ya da oturum sorunu tüm kuyruğu ilgilendirir; ilk
                    // takılmada kalan kayıtlar boşuna denenmez, sıradaki tura kalır.
                    if (agKesik) return null;

                    kayit.durum = "gonderiliyor";
                    return yaz(kayit)
                        .then(function () { return gonder(kayit); })
                        .then(function (cevap) {
                            if (cevap.sonuc === "tamam") {
                                gonderildi++;
                                return islem(STORE_KUYRUK, "readwrite", function (s) {
                                    return s.delete(kayit.uuid);
                                });
                            }

                            kayit.deneme = (kayit.deneme || 0) + 1;
                            kayit.sonDeneme = Date.now();
                            kayit.hata = cevap.mesaj || "";

                            // Ağ ve oturum sorunları süresiz yeniden denenir; kayıt yalnızca
                            // sunucu içeriği reddettiğinde kullanıcı müdahalesine bırakılır.
                            if (cevap.sonuc === "kalici") {
                                kayit.durum = "hata";
                                basarisiz++;
                            } else {
                                kayit.durum = "bekliyor";
                                agKesik = true;
                            }
                            return yaz(kayit);
                        });
                });
            }, Promise.resolve());

            return zincir.then(function () {
                return { gonderildi: gonderildi, basarisiz: basarisiz };
            });
        }).then(function (sonuc) {
            calisiyor = false;
            if (sonuc.gonderildi > 0 || sonuc.basarisiz > 0) {
                duyur({ sebep: "gonderim", gonderildi: sonuc.gonderildi, basarisiz: sonuc.basarisiz });
            }
            return sonuc;
        }).catch(function (e) {
            calisiyor = false;
            return { gonderildi: 0, hata: (e && e.message) || "" };
        });
    }

    // ---------- Referans (çevrimdışı görüntüleme) verisi ----------

    function referansKaydet(anahtar, veri) {
        return islem(STORE_REFERANS, "readwrite", function (s) {
            return s.put({ anahtar: anahtar, veri: veri, guncelleme: new Date().toISOString() });
        }).catch(function () { return null; });
    }

    function referansOku(anahtar) {
        return islem(STORE_REFERANS, "readonly", function (s) { return s.get(anahtar); })
            .then(function (kayit) { return kayit ? kayit.veri : null; })
            .catch(function () { return null; });
    }

    function temizle() {
        var silinecek = [STORE_KUYRUK, STORE_REFERANS].map(function (store) {
            return islem(store, "readwrite", function (s) { return s.clear(); });
        });
        return Promise.all(silinecek).catch(function () { return null; });
    }

    // ---------- Fotoğraf küçültme ----------

    function tuvalBlobu(tuval, kalite) {
        if (typeof tuval.convertToBlob === "function") {
            return tuval.convertToBlob({ type: "image/jpeg", quality: kalite });
        }
        return new Promise(function (resolve) {
            tuval.toBlob(function (b) { resolve(b); }, "image/jpeg", kalite);
        });
    }

    function tuvalOlustur(g, y) {
        if (typeof OffscreenCanvas === "function") return new OffscreenCanvas(g, y);
        if (!pencerede) return null;
        var c = document.createElement("canvas");
        c.width = g;
        c.height = y;
        return c;
    }

    function dosyaTarihiMetni(dosya) {
        if (!dosya || !dosya.lastModified) return "";
        var t = new Date(dosya.lastModified);
        if (isNaN(t.getTime())) return "";
        var iki = function (s) { return (s < 10 ? "0" : "") + s; };
        return "dosya|" + t.getFullYear() + "-" + iki(t.getMonth() + 1) + "-" + iki(t.getDate())
            + " " + iki(t.getHours()) + ":" + iki(t.getMinutes()) + ":" + iki(t.getSeconds());
    }

    /**
     * Çekim anı EXIF'te durur ve küçültmede yok olur; ham dosyadan önceden okunur.
     * EXIF okuyucu yüklenmemişse (servis worker bağlamı, dosya sunucuya ulaşmamış)
     * en azından cihazdaki dosya tarihi gönderilir; böylece alan hiç boş kalmaz.
     */
    function cekimBilgisi(dosya) {
        if (typeof ExifCekim === "undefined" || !ExifCekim || typeof ExifCekim.oku !== "function") {
            return Promise.resolve(dosyaTarihiMetni(dosya));
        }
        return ExifCekim.oku(dosya)
            .then(function (deger) { return deger || dosyaTarihiMetni(dosya); })
            .catch(function () { return dosyaTarihiMetni(dosya); });
    }

    function fotografKucult(dosya, maxKenar, kalite) {
        return cekimBilgisi(dosya).then(function (cekim) {
            return fotografiIndirge(dosya, maxKenar, kalite).then(function (sonuc) {
                sonuc.cekim = cekim;
                return sonuc;
            });
        });
    }

    /**
     * Saha fotoğraflarını uzun kenarı maxKenar olacak şekilde JPEG'e indirger.
     * Küçültme mümkün değilse dosya olduğu gibi döner.
     *
     * Sunucudaki GD yalnızca JPEG/PNG çözebildiği için WebP gibi diğer
     * biçimler boyutu küçük olsa da her zaman JPEG'e çevrilir.
     */
    function fotografiIndirge(dosya, maxKenar, kalite) {
        maxKenar = maxKenar || 1600;
        kalite = kalite || 0.7;

        var varsayilan = { blob: dosya, ad: dosya.name, tip: dosya.type };

        if (!dosya || !dosya.type || dosya.type.indexOf("image/") !== 0) return Promise.resolve(varsayilan);
        if (dosya.type === "image/gif") return Promise.resolve(varsayilan);
        if (typeof createImageBitmap !== "function") return Promise.resolve(varsayilan);

        var sunucuDestekli = dosya.type === "image/jpeg" || dosya.type === "image/png";

        // imageOrientation desteklenmeyen tarayıcılarda seçeneksiz tekrar denenir,
        // aksi halde telefonla dik çekilen fotoğraflar yan kaydedilir.
        var bitmapSozu = createImageBitmap(dosya, { imageOrientation: "from-image" })
            .catch(function () { return createImageBitmap(dosya); });

        return bitmapSozu.then(function (bitmap) {
            var oran = Math.min(1, maxKenar / Math.max(bitmap.width, bitmap.height));

            if (sunucuDestekli && oran >= 1 && dosya.size <= 900 * 1024) {
                if (bitmap.close) bitmap.close();
                return varsayilan;
            }

            var g = Math.max(1, Math.round(bitmap.width * oran));
            var y = Math.max(1, Math.round(bitmap.height * oran));
            var tuval = tuvalOlustur(g, y);
            if (!tuval) {
                if (bitmap.close) bitmap.close();
                return varsayilan;
            }
            tuval.width = g;
            tuval.height = y;

            var ctx = tuval.getContext("2d");
            ctx.drawImage(bitmap, 0, 0, g, y);
            if (bitmap.close) bitmap.close();

            return tuvalBlobu(tuval, kalite).then(function (blob) {
                if (!blob) return varsayilan;
                if (sunucuDestekli && blob.size >= dosya.size) return varsayilan;
                var ad = (dosya.name || "foto").replace(/\.[^.]+$/, "") + ".jpg";
                return { blob: blob, ad: ad, tip: "image/jpeg" };
            });
        }).catch(function () { return varsayilan; });
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

            // Metadata hiç gelmezse (bozuk dosya, desteklenmeyen codec) onerror da
            // tetiklenmeyebilir; bu durumda söz hiç sonuçlanmaz ve seçilen video
            // arayüzde görünmez. Emniyet için orijinal dosyayla devam edilir.
            var basladi = false;
            var metadataZamanAsimi = setTimeout(function () {
                if (!basladi) {
                    basladi = true;
                    temizle();
                    coz(dosya);
                }
            }, 20000);

            ["onloadedmetadata", "onerror"].forEach(function (olay) {
                var asilIsleyici = video[olay];
                video[olay] = function () {
                    if (basladi) return;
                    basladi = true;
                    clearTimeout(metadataZamanAsimi);
                    asilIsleyici.call(video);
                };
            });

            video.src = url;
        });
    }

    // Videonun süresini okur, boyutunu düşürür ve ilk karesinden kapak görseli üretir.
    // Sıkıştırma yeni bir File ürettiği için çekim anı ham dosyadan alınır;
    // aksi halde videonun tarihi sıkıştırma anı olarak görünür.
    function videoIncele(dosya, maxSure, maxByte, otosikistir) {
        var cekim = dosyaTarihiMetni(dosya);

        return videoyuIncele(dosya, maxSure, maxByte, otosikistir).then(function (sonuc) {
            sonuc.cekim = cekim;
            return sonuc;
        });
    }

    function videoyuIncele(dosya, maxSure, maxByte, otosikistir) {
        otosikistir = otosikistir !== false;
        return new Promise(function (coz, ret) {
            if (!dosya || !dosya.type || dosya.type.indexOf("video/") !== 0) {
                return ret(new Error("Yalnızca video dosyası yükleyebilirsiniz."));
            }
            var hamMaxByte = 100 * 1024 * 1024;
            if (dosya.size > hamMaxByte) {
                return ret(new Error(
                    "Seçtiğiniz video çok büyük (en fazla 100 MB olabilir). Seçtiğiniz dosya " +
                    (dosya.size / 1048576).toFixed(1) + " MB."
                ));
            }
            if (typeof document === "undefined") return coz({ dosya: dosya, sure: null, kapak: null });

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
                        "Video en fazla " + maxSure + " saniye olabilir. Seçtiğiniz video " + Math.round(sure) + " saniye."
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

    // ---------- Bağlam yardımcıları ----------

    function duyur(detay) {
        if (pencerede) {
            window.dispatchEvent(new CustomEvent("kuyruk-degisti", { detail: detay }));
            return;
        }
        if (global.clients && typeof global.clients.matchAll === "function") {
            global.clients.matchAll({ includeUncontrolled: true, type: "window" }).then(function (liste) {
                liste.forEach(function (c) { c.postMessage({ tip: "kuyruk-degisti", detay: detay }); });
            });
        }
    }

    function syncKaydet() {
        if (!pencerede || !("serviceWorker" in navigator)) return Promise.resolve(false);
        return navigator.serviceWorker.ready.then(function (reg) {
            if (!reg.sync) return false;
            return reg.sync.register(SYNC_ETIKETI).then(function () { return true; });
        }).catch(function () { return false; });
    }

    /**
     * Tarayıcı bellek baskısı altında IndexedDB'yi temizleyebilir; kuyruktaki
     * tutanakların uçmaması için kalıcı depolama izni istenir.
     */
    function kaliciDepolamaIste() {
        if (!pencerede || !navigator.storage || !navigator.storage.persist) return Promise.resolve(false);
        return navigator.storage.persisted().then(function (varMi) {
            return varMi ? true : navigator.storage.persist();
        }).catch(function () { return false; });
    }

    var Kuyruk = {
        SYNC_ETIKETI: SYNC_ETIKETI,
        uuid: uuidUret,
        istekGonder: istekGonder,
        ekle: ekle,
        guncelle: guncelle,
        listele: listele,
        oku: oku,
        sil: sil,
        tekrarDene: tekrarDene,
        flush: flush,
        temizle: temizle,
        referansKaydet: referansKaydet,
        referansOku: referansOku,
        fotografKucult: fotografKucult,
        videoIncele: videoIncele,
        videoSikistir: videoSikistir,
        sureBicimle: sureBicimle,
        kaliciDepolamaIste: kaliciDepolamaIste,
        syncKaydet: syncKaydet,
        cevrimici: function () { return typeof navigator === "undefined" || navigator.onLine !== false; },
    };

    global.OfflineQueue = Kuyruk;

    if (pencerede) {
        kaliciDepolamaIste();

        window.addEventListener("online", function () { flush(); });
        window.addEventListener("load", function () { flush(); });
        document.addEventListener("visibilitychange", function () {
            if (!document.hidden) flush();
        });
        setInterval(function () {
            if (navigator.onLine) flush();
        }, 60000);

        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.addEventListener("message", function (e) {
                if (e.data && e.data.tip === "kuyruk-degisti") {
                    window.dispatchEvent(new CustomEvent("kuyruk-degisti", { detail: e.data.detay }));
                }
            });
        }
    }
})(typeof self !== "undefined" ? self : this);
