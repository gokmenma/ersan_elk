<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';

use App\Service\Gate;

$maintitle = 'Ana Sayfa';
$title = 'Görevler';
?>

<link rel="stylesheet" href="views/gorevler/css/gorevler.css?v=<?php echo time(); ?>">

<div class="container-fluid">
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="gorevler-wrapper">
        <!-- ═══ SOL SIDEBAR ═══ -->
        <div class="gorevler-sidebar">
            <div class="gorevler-sidebar-header">
                <button class="btn-olustur" id="btnYeniListe">
                    <i class="bx bx-plus"></i>
                    <span>Oluştur</span>
                </button>
                <button class="btn-sidebar-toggle" id="btnSidebarToggle" title="Menüyü daralt">
                    <i class="bx bx-chevron-left"></i>
                </button>
            </div>

            <div class="gorevler-sidebar-nav" id="sidebarNav">
                <div class="nav-item nav-tum-gorevler active">
                    <i class="bx bx-check-circle"></i>
                    <span>Tüm görevler</span>
                    <span class="badge"></span>
                </div>
                <div class="nav-item nav-yildizli">
                    <i class="bx bx-star"></i>
                    <span>Yıldızlı</span>
                </div>

                <div class="sidebar-divider"></div>
                <div class="sidebar-section-title">Listeler</div>
                <div class="liste-items">
                    <!-- JS ile doldurulacak -->
                </div>
            </div>

            <div class="gorevler-sidebar-footer">
                <button class="btn-yeni-liste" id="btnGorevAyarlar">
                    <i class="bx bx-cog"></i>
                    Ayarlar
                </button>
            </div>
        </div>

        <!-- ═══ SAĞ İÇERİK ═══ -->
        <div class="gorevler-content" id="gorevlerContent">
            <!-- Preloader -->
            <div class="gt-preloader" id="pagePreloader">
                <div class="gt-loader-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>

            <!-- JS ile doldurulacak -->
            <div class="gorevler-empty">
                <i class="bx bx-task"></i>
                <h4>Yükleniyor...</h4>
            </div>
        </div>
    </div>
</div>

<!-- ═══ GÖREV AYARLAR MODAL ═══ -->
<div class="yeni-liste-modal" id="gorevAyarlarModal">
    <div class="yeni-liste-content">
        <h4>Görev Ayarları</h4>

        <div style="margin-bottom: 16px;">
            <label class="form-label">Bildirim Süresi (Dakika)</label>
            <div class="input-group">
                <input type="number" class="form-control-gt" id="set_gorev_bildirim_dakika" min="0"
                    placeholder="Örn: 15">
                <span class="input-group-text"
                    style="background: transparent; border: 1px solid #dadce0; border-left: none; border-radius: 0 4px 4px 0; color: #5f6368; font-size: 13px;">dakika
                    önce</span>
            </div>
            <small class="text-muted" style="font-size: 11px;">Görev saatinden kaç dakika önce bildirim
                gönderilsin?</small>
        </div>

        <div style="margin-bottom: 16px;">
            <label class="form-label">Bildirim Alacak Kullanıcılar</label>
            <select id="set_gorev_bildirim_kullanicilar" class="form-control-gt select2-kullanici" multiple="multiple"
                style="width: 100%;">
                <!-- JS ile doldurulacak -->
            </select>
            <small class="text-muted" style="font-size: 11px;">Bildirim ve mail gönderilecek personelleri seçin.</small>
        </div>

        <div class="yeni-liste-footer">
            <button class="btn-iptal btn btn-light" id="gorevAyarlarIptal">İptal</button>
            <button class="btn-bitti btn btn-primary" id="gorevAyarlarKaydet">Kaydet</button>
        </div>
    </div>
</div>

<!-- ═══ GÖREV KULLANICI SEÇ MODAL ═══ -->
<div class="yeni-liste-modal" id="gorevKullaniciSecModal">
    <div class="yeni-liste-content">
        <h4>Talebe Özel Bildirim Alacak Kullanıcılar</h4>

        <div style="margin-bottom: 16px;">
            <label class="form-label">Kullanıcılar</label>
            <select id="set_gorev_ozel_kullanicilar" class="form-control-gt select2-kullanici-ozel" multiple="multiple"
                style="width: 100%;">
                <!-- JS ile doldurulacak -->
            </select>
            <small class="text-muted" style="font-size: 11px;">Eğer seçim yapılmazsa ayarlardaki genel bildirim
                kullanıcılarına gönderilir.</small>
            <input type="hidden" id="gorevKullaniciSecGorevId">
        </div>

        <div class="yeni-liste-footer">
            <button class="btn-iptal btn btn-light" id="gorevKullaniciSecIptal">İptal</button>
            <button class="btn-bitti btn btn-primary" id="gorevKullaniciSecKaydet">Kaydet</button>
        </div>
    </div>
</div>

<!-- ═══ TARİH PICKER MODAL ═══ -->
<div class="tarih-picker-modal" id="tarihPickerModal">
    <div class="tarih-picker-content">
        <div id="tarihPickerCalendar"></div>

        <div class="tarih-picker-bottom">
            <div class="tarih-picker-option">
                <i class="bx bx-time-five"></i>
                <div class="saat-input-wrapper" style="flex:1">
                    <input type="time" id="tarihSaatInput" placeholder="Saati ayarla">
                </div>
            </div>
            <button class="tarih-picker-option" id="tarihPickerTekrarla">
                <i class="bx bx-repeat"></i>
                <span>Tekrarla</span>
            </button>
        </div>

        <div class="tarih-picker-footer">
            <button class="btn-iptal" id="tarihPickerIptal">İptal</button>
            <button class="btn-bitti" id="tarihPickerBitti">Bitti</button>
        </div>
    </div>
</div>

<!-- ═══ YİNELEME MODAL ═══ -->
<div class="yineleme-modal" id="yinelemeModal">
    <div class="yineleme-content">
        <h4>Yinelenme sıklığı</h4>

        <div class="yineleme-row">
            <input type="number" id="yinelemeSikligi" value="1" min="1" max="99">
            <select id="yinelemeBirimi">
                <option value="gun">gün</option>
                <option value="hafta">hafta</option>
                <option value="ay">ay</option>
                <option value="yil">yıl</option>
            </select>
        </div>

        <!-- Haftanın Günleri (Sadece Hafta seçiliyken görünür) -->
        <div class="yineleme-haftanin-gunleri" id="yinelemeHaftaGunleri"
            style="display: none; margin-bottom: 15px; margin-top: 5px;">
            <div class="gun-daire" data-gun="1">P</div>
            <div class="gun-daire" data-gun="2">S</div>
            <div class="gun-daire" data-gun="3">Ç</div>
            <div class="gun-daire" data-gun="4">P</div>
            <div class="gun-daire" data-gun="5">C</div>
            <div class="gun-daire" data-gun="6">C</div>
            <div class="gun-daire" data-gun="0">P</div>
        </div>

        <div class="yineleme-section">
            <label>Başlangıç</label>
            <input type="date" id="yinelemeBaslangic" style="width:100%">
        </div>

        <div class="yineleme-section">
            <label>Bitiş</label>
            <div class="yineleme-radio-group">
                <label>
                    <input type="radio" name="yinelemeBitisTipi" value="asla" checked>
                    Asla
                </label>
                <label>
                    <input type="radio" name="yinelemeBitisTipi" value="tarih">
                    <span>Şu tarihte:</span>
                    <input type="date" id="yinelemeBitisTarihi" style="width:120px; margin-left: 4px;">
                </label>
                <label>
                    <input type="radio" name="yinelemeBitisTipi" value="adet">
                    <span>Yinele:</span>
                    <input type="number" id="yinelemeBitisAdet" value="30" min="1" max="999"
                        style="width:60px; margin-left: 4px;">
                    <span>kez</span>
                </label>
            </div>
        </div>

        <div class="yineleme-footer">
            <button class="btn-iptal btn btn-light" id="yinelemeIptal">İptal</button>
            <button class="btn-bitti btn btn-primary" id="yinelemeBitti">Bitti</button>
        </div>
    </div>
</div>

<!-- ═══ YENİ LİSTE MODAL ═══ -->
<div class="yeni-liste-modal" id="yeniListeModal">
    <div class="yeni-liste-content">
        <h4>Yeni Liste Oluştur</h4>

        <div style="margin-bottom: 16px;">
            <label class="form-label">Liste Adı</label>
            <input type="text" class="form-control-gt" id="yeniListeBaslik" placeholder="Liste adı girin">
            <input type="hidden" id="yeniListeRenk">
        </div>

        <div style="margin-bottom: 16px;">
            <label class="form-label">Renk</label>
            <div class="renk-secici">
                <div class="renk-secici-item" data-renk="#4285f4" style="background:#4285f4" title="Mavi"></div>
                <div class="renk-secici-item" data-renk="#ea4335" style="background:#ea4335" title="Kırmızı"></div>
                <div class="renk-secici-item" data-renk="#fbbc04" style="background:#fbbc04" title="Sarı"></div>
                <div class="renk-secici-item" data-renk="#34a853" style="background:#34a853" title="Yeşil"></div>
                <div class="renk-secici-item" data-renk="#ff6d01" style="background:#ff6d01" title="Turuncu"></div>
                <div class="renk-secici-item" data-renk="#46bdc6" style="background:#46bdc6" title="Turkuaz"></div>
                <div class="renk-secici-item" data-renk="#7baaf7" style="background:#7baaf7" title="Açık Mavi"></div>
                <div class="renk-secici-item" data-renk="#a142f4" style="background:#a142f4" title="Mor"></div>
                <div class="renk-secici-item" data-renk="#f538a0" style="background:#f538a0" title="Pembe"></div>
                <div class="renk-secici-item" data-renk="#185abc" style="background:#185abc" title="Koyu Mavi"></div>
                <div class="renk-secici-item" data-renk="#137333" style="background:#137333" title="Koyu Yeşil"></div>
                <div class="renk-secici-item" data-renk="#5f6368" style="background:#5f6368" title="Gri"></div>
            </div>
        </div>

        <div class="yeni-liste-footer">
            <button class="btn-iptal btn btn-light" id="yeniListeIptal">İptal</button>
            <button class="btn-bitti btn btn-primary" id="yeniListeOlustur">Oluştur</button>
        </div>
    </div>
</div>

<!-- ═══ LİSTE YENİDEN ADLANDIR MODAL ═══ -->
<div class="yeni-liste-modal" id="listeRenameModal">
    <div class="yeni-liste-content">
        <h4>Listeyi Yeniden Adlandır</h4>

        <div style="margin-bottom: 16px;">
            <label class="form-label">Liste Adı</label>
            <input type="text" class="form-control-gt" id="listeRenameBaslik" placeholder="Yeni liste adı girin">
            <input type="hidden" id="listeRenameId">
            <input type="hidden" id="listeRenameRenk">
        </div>

        <div style="margin-bottom: 16px;">
            <label class="form-label">Renk</label>
            <div class="renk-secici clr-rename">
                <div class="renk-secici-item" data-renk="#4285f4" style="background:#4285f4" title="Mavi"></div>
                <div class="renk-secici-item" data-renk="#ea4335" style="background:#ea4335" title="Kırmızı"></div>
                <div class="renk-secici-item" data-renk="#fbbc04" style="background:#fbbc04" title="Sarı"></div>
                <div class="renk-secici-item" data-renk="#34a853" style="background:#34a853" title="Yeşil"></div>
                <div class="renk-secici-item" data-renk="#ff6d01" style="background:#ff6d01" title="Turuncu"></div>
                <div class="renk-secici-item" data-renk="#46bdc6" style="background:#46bdc6" title="Turkuaz"></div>
                <div class="renk-secici-item" data-renk="#7baaf7" style="background:#7baaf7" title="Açık Mavi"></div>
                <div class="renk-secici-item" data-renk="#a142f4" style="background:#a142f4" title="Mor"></div>
                <div class="renk-secici-item" data-renk="#f538a0" style="background:#f538a0" title="Pembe"></div>
                <div class="renk-secici-item" data-renk="#185abc" style="background:#185abc" title="Koyu Mavi"></div>
                <div class="renk-secici-item" data-renk="#137333" style="background:#137333" title="Koyu Yeşil"></div>
                <div class="renk-secici-item" data-renk="#5f6368" style="background:#5f6368" title="Gri"></div>
            </div>
        </div>

        <div class="yeni-liste-footer">
            <button class="btn-iptal btn btn-light" id="listeRenameIptal">İptal</button>
            <button class="btn-bitti btn btn-primary" id="listeRenameKaydet">Kaydet</button>
        </div>
    </div>
</div>