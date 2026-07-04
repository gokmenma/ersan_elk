<div id="sheet-content-arac" class="app-sheet-content hidden">
    <form id="aracForm" class="space-y-5 px-1">
        <input type="hidden" name="action" value="arac-kaydet">
        <input type="hidden" name="id" value="">

        <!-- Genel Bilgiler -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-teal-500 text-lg align-middle">info</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Genel Bilgiler</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Plaka *</label>
                    <input type="text" name="plaka" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm font-bold uppercase focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none transition-all dark:text-white" placeholder="34 ABC 123" required>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Marka</label>
                        <input type="text" name="marka" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Örn: Ford">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Model</label>
                        <input type="text" name="model" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Örn: Focus">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Yıl/Renk</label>
                        <div class="flex gap-2">
                            <input type="number" name="model_yili" value="<?= date('Y') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Yıl">
                            <input type="text" name="renk" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Renk">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Durum</label>
                        <select name="durum" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white">
                            <option value="1">Aktif</option>
                            <option value="0">Pasif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Araç / Yakıt Tipi</label>
                    <div class="grid grid-cols-2 gap-3">
                        <select name="arac_tipi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white">
                            <option value="">Seçiniz</option>
                            <option value="binek">Binek</option>
                            <option value="kamyonet">Kamyonet</option>
                            <option value="kamyon">Kamyon</option>
                            <option value="minibus">Minibüs</option>
                            <option value="otobus">Otobüs</option>
                            <option value="motosiklet">Motosiklet</option>
                            <option value="diger">Diğer</option>
                        </select>
                        <select name="yakit_tipi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white">
                            <option value="">Seçiniz</option>
                            <option value="dizel">Dizel</option>
                            <option value="benzin">Benzin</option>
                            <option value="lpg">LPG</option>
                            <option value="elektrik">Elektrik</option>
                            <option value="hibrit">Hibrit</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teknik Bilgiler -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-500 text-lg align-middle">build</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Teknik & KM</span>
            </div>
            <div class="p-4 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Başlangıç KM</label>
                        <input type="number" name="baslangic_km" value="0" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white" min="0">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Güncel KM</label>
                        <input type="number" name="guncel_km" value="0" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white font-bold text-indigo-700" min="0">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Şase No</label>
                        <input type="text" name="sase_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Ruhsat No</label>
                        <input type="text" name="ruhsat_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white">
                    </div>
                </div>
            </div>
        </div>

        <!-- Evrak - Tarihler -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-orange-500 text-lg align-middle">calendar_month</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Evrak Bitiş Tarihleri</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"><span class="material-symbols-outlined text-[12px] align-middle mr-1">verified</span>Muayene</label>
                    <input type="date" name="muayene_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"><span class="material-symbols-outlined text-[12px] align-middle mr-1">shield</span>Sigorta</label>
                    <input type="date" name="sigorta_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"><span class="material-symbols-outlined text-[12px] align-middle mr-1">gpp_good</span>Kasko</label>
                    <input type="date" name="kasko_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all dark:text-white">
                </div>
            </div>
        </div>

        <!-- Ruhsat Görseli -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-teal-500 text-lg align-middle">badge</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Ruhsat Görseli</span>
                <span class="ml-auto text-[9px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-2 py-0.5 rounded-full font-bold border border-emerald-500/20 flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-[10px]">lock</span> Şifreli
                </span>
            </div>
            <div class="p-4 space-y-3" id="ruhsatGorselAlani">
                <!-- Ruhsat Yeni Araç Uyarısı -->
                <div id="ruhsatYeniAracUyari" class="text-xs text-slate-400 dark:text-slate-500 flex items-center gap-1.5 py-2">
                    <span class="material-symbols-outlined text-base">info</span>
                    <span>Ruhsat görseli yükleyebilmek için önce aracı kaydedin.</span>
                </div>

                <!-- Ruhsat Mevcut Alanı -->
                <div id="ruhsatMevcutAlani" class="hidden space-y-3">
                    <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800/30 rounded-xl border border-slate-100 dark:border-slate-800">
                        <span class="material-symbols-outlined text-3xl text-teal-500" id="ruhsatDosyaIkon">description</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate" id="ruhsatDosyaAdi">-</div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500" id="ruhsatDosyaMeta">-</div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 py-2 px-3 bg-teal-50 hover:bg-teal-100 text-teal-600 dark:bg-teal-500/10 dark:text-teal-400 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1" id="btnRuhsatGoruntule">
                            <span class="material-symbols-outlined text-base">visibility</span> Görüntüle
                        </button>
                        <button type="button" class="flex-1 py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1" id="btnRuhsatDegistir">
                            <span class="material-symbols-outlined text-base">sync</span> Değiştir
                        </button>
                        <button type="button" class="py-2 px-3 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400 rounded-xl text-xs font-bold transition-all flex items-center justify-center" id="btnRuhsatSil">
                            <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                    </div>
                </div>

                <!-- Ruhsat Yükleme Alanı -->
                <div id="ruhsatYuklemeAlani" class="hidden space-y-2">
                    <label class="flex flex-col items-center justify-center border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl p-4 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-all">
                        <span class="material-symbols-outlined text-3xl text-slate-400 dark:text-slate-500 mb-1">cloud_upload</span>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Ruhsat Seç veya Çek</span>
                        <span class="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WEBP veya PDF (Maks. 8MB)</span>
                        <input type="file" class="hidden" id="ruhsatDosyaInput" accept="image/jpeg,image/png,image/webp,application/pdf">
                    </label>
                    
                    <div class="hidden" id="ruhsatUploadProgress">
                        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-teal-500 h-1.5 rounded-full transition-all duration-150 progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" onclick="saveArac()" class="w-full py-3.5 bg-teal-500 hover:bg-teal-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">save</span> Kaydet
        </button>
    </form>
</div>

<script>
function saveArac() {
    const form = document.getElementById('aracForm');
    const plaka = form.plaka.value.trim();
    
    if(!plaka) {
        MobileSwal.fire({
            icon: 'warning',
            title: 'Hata',
            text: 'Plaka zorunludur.'
        });
        return;
    }

    const formData = new FormData(form);
    
    MobileSwal.fire({
        title: 'Kaydediliyor...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('../views/arac-takip/api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            MobileSwal.fire({icon: 'success', title: 'Başarılı', text: data.message, timer: 1500}).then(() => location.reload());
        } else {
            MobileSwal.fire('Hata', data.message, 'error');
        }
    })
    .catch(() => MobileSwal.fire('Hata', 'Bağlantı sorunu', 'error'));
}

// Ruhsat Görseli Alanını Güncelle
function ruhsatAlaniniGuncelle(data) {
    const aracId = data.id || "";
    const form = document.getElementById('aracForm');
    form.id.value = aracId;

    const yeniUyari = document.getElementById("ruhsatYeniAracUyari");
    const mevcutAlani = document.getElementById("ruhsatMevcutAlani");
    const yuklemeAlani = document.getElementById("ruhsatYuklemeAlani");

    if (!yeniUyari || !mevcutAlani || !yuklemeAlani) return;

    yeniUyari.classList.add("hidden");
    mevcutAlani.classList.add("hidden");
    yuklemeAlani.classList.add("hidden");

    if (!aracId) {
        yeniUyari.classList.remove("hidden");
        return;
    }

    if (data.ruhsat_var) {
        const boyutKb = data.ruhsat_boyutu ? Math.round(data.ruhsat_boyutu / 1024) : 0;
        const icon = data.ruhsat_mime_tipi === "application/pdf" ? "description" : "image";
        
        const fileIcon = document.getElementById("ruhsatDosyaIkon");
        if (fileIcon) fileIcon.innerText = icon;
        
        const fileNameEl = document.getElementById("ruhsatDosyaAdi");
        if (fileNameEl) fileNameEl.innerText = data.ruhsat_orijinal_ad || "Ruhsat Görseli";
        
        const fileMetaEl = document.getElementById("ruhsatDosyaMeta");
        if (fileMetaEl) fileMetaEl.innerText = boyutKb + " KB";
        
        const btnView = document.getElementById("btnRuhsatGoruntule");
        if (btnView) btnView.setAttribute("data-id", data.ruhsat_gorsel_id || "");
        
        mevcutAlani.classList.remove("hidden");
    } else {
        yuklemeAlani.classList.remove("hidden");
    }
}

// Ruhsat Yükle
function ruhsatYukle(aracId, file) {
    const formData = new FormData();
    formData.append("action", "arac-ruhsat-yukle");
    formData.append("arac_id", aracId);
    formData.append("ruhsat_dosyasi", file);

    const progressDiv = document.getElementById("ruhsatUploadProgress");
    const progressBar = progressDiv ? progressDiv.querySelector(".progress-bar") : null;
    
    if (progressDiv) progressDiv.classList.remove("hidden");
    if (progressBar) progressBar.style.width = "0%";

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "../views/arac-takip/api.php", true);

    xhr.upload.addEventListener("progress", function (e) {
        if (e.lengthComputable && progressBar) {
            const pct = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = pct + "%";
        }
    });

    xhr.onload = function () {
        if (progressDiv) progressDiv.classList.add("hidden");
        const fileInput = document.getElementById("ruhsatDosyaInput");
        if (fileInput) fileInput.value = "";
        
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === "success") {
                    MobileSwal.fire({
                        icon: "success",
                        title: "Başarılı",
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    ruhsatAlaniniGuncelle({
                        id: aracId,
                        ruhsat_var: true,
                        ruhsat_orijinal_ad: response.data.ruhsat_orijinal_ad,
                        ruhsat_mime_tipi: response.data.ruhsat_mime_tipi,
                        ruhsat_boyutu: response.data.ruhsat_boyutu,
                        ruhsat_gorsel_id: response.data.ruhsat_gorsel_id,
                    });
                } else {
                    MobileSwal.fire("Hata", response.message, "error");
                }
            } catch (e) {
                MobileSwal.fire("Hata", "Sunucudan geçersiz yanıt alındı.", "error");
            }
        } else {
            MobileSwal.fire("Hata", "Ruhsat yüklenirken bir hata oluştu.", "error");
        }
    };

    xhr.onerror = function () {
        if (progressDiv) progressDiv.classList.add("hidden");
        const fileInput = document.getElementById("ruhsatDosyaInput");
        if (fileInput) fileInput.value = "";
        MobileSwal.fire("Hata", "Ruhsat yüklenirken bağlantı hatası oluştu.", "error");
    };

    xhr.send(formData);
}

// Ruhsat Sil
function ruhsatSil(aracId) {
    MobileSwal.fire({
        title: "Emin misiniz?",
        text: "Ruhsat görseli kalıcı olarak silinecek.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Evet, Sil",
        cancelButtonText: "Vazgeç",
    }).then((result) => {
        if (!result.isConfirmed) return;

        const formData = new URLSearchParams();
        formData.append("action", "arac-ruhsat-sil");
        formData.append("arac_id", aracId);

        fetch("../views/arac-takip/api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: formData.toString()
        })
        .then(r => r.json())
        .then(response => {
            if (response.status === "success") {
                MobileSwal.fire({
                    icon: "success",
                    title: "Silindi",
                    text: response.message,
                    timer: 1500,
                    showConfirmButton: false,
                });
                ruhsatAlaniniGuncelle({ id: aracId, ruhsat_var: false });
            } else {
                MobileSwal.fire("Hata", response.message, "error");
            }
        })
        .catch(() => MobileSwal.fire("Hata", "Bağlantı hatası.", "error"));
    });
}

// Event Listeners
document.addEventListener("DOMContentLoaded", function() {
    const fileInput = document.getElementById("ruhsatDosyaInput");
    if (fileInput) {
        fileInput.addEventListener("change", function() {
            const aracId = document.getElementById('aracForm').id.value;
            const file = this.files[0];
            if (aracId && file) {
                ruhsatYukle(aracId, file);
            }
        });
    }

    const btnDegistir = document.getElementById("btnRuhsatDegistir");
    if (btnDegistir) {
        btnDegistir.addEventListener("click", function() {
            const yuklemeAlani = document.getElementById("ruhsatYuklemeAlani");
            if (yuklemeAlani) yuklemeAlani.classList.remove("hidden");
        });
    }

    const btnSil = document.getElementById("btnRuhsatSil");
    if (btnSil) {
        btnSil.addEventListener("click", function() {
            const aracId = document.getElementById('aracForm').id.value;
            if (aracId) {
                ruhsatSil(aracId);
            }
        });
    }

    const btnGoruntule = document.getElementById("btnRuhsatGoruntule");
    if (btnGoruntule) {
        btnGoruntule.addEventListener("click", function() {
            const encId = this.getAttribute("data-id");
            if (encId) {
                window.open("../views/arac-takip/ruhsat-goruntule.php?id=" + encodeURIComponent(encId), "_blank");
            }
        });
    }
});
</script>
