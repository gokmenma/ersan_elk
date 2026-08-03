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
     * @param {Array}  dosyalar  [{alan, ad, tip, blob}]
     * @param {object} ozet      listede gösterim için serbest alanlar
     */
    function ekle(action, alanlar, dosyalar, ozet) {
        var kayit = {
            uuid: uuidUret(),
            action: action,
            alanlar: alanlar || {},
            dosyalar: dosyalar || [],
            ozet: ozet || {},
            durum: "bekliyor",
            deneme: 0,
            hata: "",
            olusturma: new Date().toISOString(),
            olusturmaYerel: yerelZamanDamgasi(),
        };

        kayit.alanlar.client_uuid = kayit.uuid;
        kayit.alanlar.offline_olusturma = kayit.olusturmaYerel;

        return yaz(kayit).then(function () {
            duyur({ sebep: "eklendi", uuid: kayit.uuid });
            syncKaydet();
            return kayit;
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
    function gonder(kayit) {
        var fd = new FormData();
        fd.append("action", kayit.action);

        Object.keys(kayit.alanlar || {}).forEach(function (k) {
            fd.append(k, kayit.alanlar[k]);
        });
        (kayit.dosyalar || []).forEach(function (d) {
            fd.append(d.alan, d.blob, d.ad);
        });

        return fetch(API_URL, {
            method: "POST",
            body: fd,
            credentials: "same-origin",
            cache: "no-store",
        }).then(function (yanit) {
            if (!yanit.ok) {
                return { sonuc: "bekle", mesaj: "Sunucu yanıtı: " + yanit.status };
            }
            return yanit.json().then(function (json) {
                // Service worker çevrimdışıyken sahte yanıt üretiyor olabilir.
                if (json && json.offline) {
                    return { sonuc: "bekle", mesaj: "Bağlantı yok" };
                }
                if (json && json.success) {
                    return { sonuc: "tamam", veri: json.data };
                }

                var mesaj = (json && (json.message || json.error)) || "Kayıt gönderilemedi.";
                var oturumSorunu = (json && json.redirect) || /oturum/i.test(mesaj);
                return { sonuc: oturumSorunu ? "bekle" : "kalici", mesaj: mesaj };
            }).catch(function () {
                // JSON yerine login sayfası gibi bir HTML dönmüşse oturum düşmüştür.
                return { sonuc: "bekle", mesaj: "Oturum doğrulanamadı" };
            });
        }).catch(function (e) {
            return { sonuc: "bekle", mesaj: (e && e.message) || "Bağlantı hatası" };
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

        return askidaKalanlariKurtar().then(listele).then(function (kayitlar) {
            var sira = kayitlar.filter(function (k) {
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

    /**
     * Saha fotoğraflarını uzun kenarı maxKenar olacak şekilde JPEG'e indirger.
     * Küçültme mümkün değilse dosya olduğu gibi döner.
     */
    function fotografKucult(dosya, maxKenar, kalite) {
        maxKenar = maxKenar || 1600;
        kalite = kalite || 0.7;

        var varsayilan = { blob: dosya, ad: dosya.name, tip: dosya.type };

        if (!dosya || !dosya.type || dosya.type.indexOf("image/") !== 0) return Promise.resolve(varsayilan);
        if (dosya.type === "image/gif") return Promise.resolve(varsayilan);
        if (typeof createImageBitmap !== "function") return Promise.resolve(varsayilan);

        // imageOrientation desteklenmeyen tarayıcılarda seçeneksiz tekrar denenir,
        // aksi halde telefonla dik çekilen fotoğraflar yan kaydedilir.
        var bitmapSozu = createImageBitmap(dosya, { imageOrientation: "from-image" })
            .catch(function () { return createImageBitmap(dosya); });

        return bitmapSozu.then(function (bitmap) {
            var oran = Math.min(1, maxKenar / Math.max(bitmap.width, bitmap.height));

            if (oran >= 1 && dosya.size <= 900 * 1024) {
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
                if (!blob || blob.size >= dosya.size) return varsayilan;
                var ad = (dosya.name || "foto").replace(/\.[^.]+$/, "") + ".jpg";
                return { blob: blob, ad: ad, tip: "image/jpeg" };
            });
        }).catch(function () { return varsayilan; });
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
        ekle: ekle,
        listele: listele,
        oku: oku,
        sil: sil,
        tekrarDene: tekrarDene,
        flush: flush,
        temizle: temizle,
        referansKaydet: referansKaydet,
        referansOku: referansOku,
        fotografKucult: fotografKucult,
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
