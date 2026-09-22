<!-- Right Sidebar -->
<div class="right-bar">
    <div data-simplebar class="h-100">
        <div class="rightbar-title d-flex align-items-center p-3">

            <h5 class="m-0 me-2">Tema Özelleştirici</h5>

            <a href="javascript:void(0);" class="right-bar-toggle ms-auto">
                <i class="mdi mdi-close noti-icon"></i>
            </a>
        </div>

        <!-- Settings -->
        <hr class="m-0" />

        <div class="p-4">
            <h6 class="mb-3 d-flex align-items-center justify-content-between">
                <span>Hazır Temalar (Ön Tanımlı)</span>
                <span class="badge bg-primary-subtle text-primary font-size-11">Tek Tıkla Uygula</span>
            </h6>
            <div class="theme-preset-grid mb-4">
                <div class="theme-preset-card" data-preset="kode" role="button" title="Kode Teması (Mavi Üst Bar, Koyu Menü, Inter Font)">
                    <div class="preset-preview">
                        <div class="preset-topbar" style="background: #399bff;"></div>
                        <div class="preset-body">
                            <div class="preset-sidebar" style="background: #282e38;"></div>
                            <div class="preset-content" style="background: #f4f6f9;">
                                <div class="preset-accent-bar" style="background: #399bff;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="preset-name">Kode</span>
                </div>

                <div class="theme-preset-card" data-preset="ersan" role="button" title="Ersan Gold (Açık Üst Bar, Koyu Menü, Altın Vurgu)">
                    <div class="preset-preview">
                        <div class="preset-topbar" style="background: #ffffff; border-bottom: 1px solid #e2e8f0;"></div>
                        <div class="preset-body">
                            <div class="preset-sidebar" style="background: #1e293b;"></div>
                            <div class="preset-content" style="background: #f8fafc;">
                                <div class="preset-accent-bar" style="background: #e2bd61;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="preset-name">Ersan Gold</span>
                </div>

                <div class="theme-preset-card" data-preset="midnight-emerald" role="button" title="Midnight Emerald (Zümrüt Üst Bar, Koyu Menü)">
                    <div class="preset-preview">
                        <div class="preset-topbar" style="background: #10b981;"></div>
                        <div class="preset-body">
                            <div class="preset-sidebar" style="background: #15241f;"></div>
                            <div class="preset-content" style="background: #f0fdf4;">
                                <div class="preset-accent-bar" style="background: #10b981;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="preset-name">Zümrüt</span>
                </div>

                <div class="theme-preset-card" data-preset="royal-purple" role="button" title="Royal Purple (Mor Üst Bar, Koyu Menü)">
                    <div class="preset-preview">
                        <div class="preset-topbar" style="background: #5156be;"></div>
                        <div class="preset-body">
                            <div class="preset-sidebar" style="background: #1e1b2e;"></div>
                            <div class="preset-content" style="background: #faf5ff;">
                                <div class="preset-accent-bar" style="background: #5156be;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="preset-name">Kraliyet Moru</span>
                </div>

                <div class="theme-preset-card" data-preset="crimson-rose" role="button" title="Crimson Rose (Rose Üst Bar, Koyu Menü)">
                    <div class="preset-preview">
                        <div class="preset-topbar" style="background: #ec003f;"></div>
                        <div class="preset-body">
                            <div class="preset-sidebar" style="background: #232125;"></div>
                            <div class="preset-content" style="background: #fff1f2;">
                                <div class="preset-accent-bar" style="background: #ec003f;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="preset-name">Rose</span>
                </div>

                <div class="theme-preset-card" data-preset="minimalist" role="button" title="Minimalist Pure (Açık Üst Bar, Beyaz Menü, Sade)">
                    <div class="preset-preview">
                        <div class="preset-topbar" style="background: #ffffff; border-bottom: 1px solid #e2e8f0;"></div>
                        <div class="preset-body">
                            <div class="preset-sidebar" style="background: #ffffff; border-right: 1px solid #e2e8f0;"></div>
                            <div class="preset-content" style="background: #fcfcfd;">
                                <div class="preset-accent-bar" style="background: #18181b;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="preset-name">Sade Beyaz</span>
                </div>

                <div class="theme-preset-card" data-preset="dark-pro" role="button" title="Dark Pro (Tam Gece Modu, Cyan Vurgu)">
                    <div class="preset-preview">
                        <div class="preset-topbar" style="background: #191e22; border-bottom: 1px solid #303840;"></div>
                        <div class="preset-body">
                            <div class="preset-sidebar" style="background: #191e22; border-right: 1px solid #303840;"></div>
                            <div class="preset-content" style="background: #121619;">
                                <div class="preset-accent-bar" style="background: #06b6d4;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="preset-name">Koyu Gece</span>
                </div>
            </div>

            <hr class="my-4" />

            <h6 class="mb-3">Tema Rengi Seçin</h6>
            <div class="color-selector-group">
                <div class="color-picker-wrapper" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Özel Renk Seç">
                    <input type="color" id="custom-theme-picker" value="#1c84ee">
                    <i class="mdi mdi-palette"></i>
                </div>
                <input class="color-selector-btn color-default" type="radio" name="theme-mode" id="theme-default"
                    value="default" checked data-bs-toggle="tooltip" data-bs-placement="top" title="Varsayılan">
                <input class="color-selector-btn color-red" type="radio" name="theme-mode" id="theme-red" value="red"
                    data-bs-toggle="tooltip" data-bs-placement="top" title="Kırmızı">
                <input class="color-selector-btn color-purple" type="radio" name="theme-mode" id="theme-purple"
                    value="purple" data-bs-toggle="tooltip" data-bs-placement="top" title="Mor">
                <input class="color-selector-btn color-slate" type="radio" name="theme-mode" id="theme-slate"
                    value="slate" data-bs-toggle="tooltip" data-bs-placement="top" title="Slate">
                <input class="color-selector-btn color-emerald" type="radio" name="theme-mode" id="theme-emerald"
                    value="emerald" data-bs-toggle="tooltip" data-bs-placement="top" title="Zümrüt">
                <input class="color-selector-btn color-orange" type="radio" name="theme-mode" id="theme-orange"
                    value="orange" data-bs-toggle="tooltip" data-bs-placement="top" title="Turuncu">
                <input class="color-selector-btn color-rose" type="radio" name="theme-mode" id="theme-rose" value="rose"
                    data-bs-toggle="tooltip" data-bs-placement="top" title="Rose">
                <input class="color-selector-btn color-ersan" type="radio" name="theme-mode" id="theme-ersan"
                    value="ersan" data-bs-toggle="tooltip" data-bs-placement="top" title="Ersan">
                <input class="color-selector-btn color-teal" type="radio" name="theme-mode" id="theme-teal" value="teal"
                    data-bs-toggle="tooltip" data-bs-placement="top" title="Teal">
                <input class="color-selector-btn color-cyan" type="radio" name="theme-mode" id="theme-cyan" value="cyan"
                    data-bs-toggle="tooltip" data-bs-placement="top" title="Cyan">
            </div>

            <h6 class="mt-4 mb-3 pt-2">Yazı Tipi (Font)</h6>
            <div class="font-selector-group">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="font-family" id="font-geist" value="Geist"
                        checked>
                    <label class="form-check-label font-geist" for="font-geist"
                        style="font-family: 'Geist', sans-serif;">Geist (Varsayılan)</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="font-family" id="font-inter" value="Inter">
                    <label class="form-check-label font-inter" for="font-inter"
                        style="font-family: 'Inter', sans-serif;">Inter</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="font-family" id="font-outfit" value="Outfit">
                    <label class="form-check-label font-outfit" for="font-outfit"
                        style="font-family: 'Outfit', sans-serif;">Outfit</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="font-family" id="font-poppins" value="Poppins">
                    <label class="form-check-label font-poppins" for="font-poppins"
                        style="font-family: 'Poppins', sans-serif;">Poppins</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="font-family" id="font-jakarta"
                        value="Plus Jakarta Sans">
                    <label class="form-check-label font-jakarta" for="font-jakarta"
                        style="font-family: 'Plus Jakarta Sans', sans-serif;">Plus Jakarta Sans</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="font-family" id="font-lexend" value="Lexend">
                    <label class="form-check-label font-lexend" for="font-lexend"
                        style="font-family: 'Lexend', sans-serif;">Lexend</label>
                </div>
            </div>


            <h6 class="mt-4 mb-3 pt-2">Görünüm (Mobil Dönüşüm)</h6>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout" id="layout-vertical" value="vertical">
                <label class="form-check-label" for="layout-vertical">Dikey</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout" id="layout-horizontal" value="horizontal">
                <label class="form-check-label" for="layout-horizontal">Yatay</label>
            </div>

            <h6 class="mt-4 mb-3 pt-2">Görünüm Modu</h6>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-mode" id="layout-mode-light" value="light">
                <label class="form-check-label" for="layout-mode-light">Açık</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-mode" id="layout-mode-dark" value="dark">
                <label class="form-check-label" for="layout-mode-dark">Koyu</label>
            </div>

            <h6 class="mt-4 mb-3 pt-2">Masaüstü / Mobil Görünüm</h6>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="device-view" id="device-view-desktop" value="desktop">
                <label class="form-check-label" for="device-view-desktop">Masaüstü</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="device-view" id="device-view-mobile" value="mobile">
                <label class="form-check-label" for="device-view-mobile">Mobil</label>
            </div>

            <h6 class="mt-4 mb-3 pt-2">Sayfa Genişliği</h6>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-width" id="layout-width-fuild" value="fuild">
                <label class="form-check-label" for="layout-width-fuild">Akışkan</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-width" id="layout-width-boxed" value="boxed">
                <label class="form-check-label" for="layout-width-boxed">Kutulu</label>
            </div>

            <h6 class="mt-4 mb-3 pt-2">Sayfa Pozisyonu</h6>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-position" id="layout-position-fixed"
                    value="fixed">
                <label class="form-check-label" for="layout-position-fixed">Sabit</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-position" id="layout-position-scrollable"
                    value="scrollable">
                <label class="form-check-label" for="layout-position-scrollable">Kaydırılabilir</label>
            </div>

            <h6 class="mt-4 mb-3 pt-2">Üst Bar Rengi</h6>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="topbar-color" id="topbar-color-light" value="light">
                <label class="form-check-label" for="topbar-color-light">Açık</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="topbar-color" id="topbar-color-dark" value="dark">
                <label class="form-check-label" for="topbar-color-dark">Koyu</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="topbar-color" id="topbar-color-brand" value="brand">
                <label class="form-check-label" for="topbar-color-brand">Marka Rengi</label>
            </div>
            <div class="color-selector-group mt-2">
                <div class="color-picker-wrapper" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Özel Üst Bar Rengi">
                    <input type="color" id="custom-topbar-picker" value="#1c84ee">
                    <i class="mdi mdi-palette"></i>
                </div>
                <input class="color-selector-btn color-default" type="radio" name="topbar-color" id="topbar-default"
                    value="default" data-bs-toggle="tooltip" data-bs-placement="top" title="Varsayılan">
                <input class="color-selector-btn color-red" type="radio" name="topbar-color" id="topbar-red" value="red"
                    data-bs-toggle="tooltip" data-bs-placement="top" title="Kırmızı">
                <input class="color-selector-btn color-purple" type="radio" name="topbar-color" id="topbar-purple"
                    value="purple" data-bs-toggle="tooltip" data-bs-placement="top" title="Mor">
                <input class="color-selector-btn color-slate" type="radio" name="topbar-color" id="topbar-slate"
                    value="slate" data-bs-toggle="tooltip" data-bs-placement="top" title="Slate">
                <input class="color-selector-btn color-emerald" type="radio" name="topbar-color" id="topbar-emerald"
                    value="emerald" data-bs-toggle="tooltip" data-bs-placement="top" title="Zümrüt">
                <input class="color-selector-btn color-orange" type="radio" name="topbar-color" id="topbar-orange"
                    value="orange" data-bs-toggle="tooltip" data-bs-placement="top" title="Turuncu">
                <input class="color-selector-btn color-rose" type="radio" name="topbar-color" id="topbar-rose"
                    value="rose" data-bs-toggle="tooltip" data-bs-placement="top" title="Rose">
                <input class="color-selector-btn color-ersan" type="radio" name="topbar-color" id="topbar-ersan"
                    value="ersan" data-bs-toggle="tooltip" data-bs-placement="top" title="Ersan">
                <input class="color-selector-btn color-teal" type="radio" name="topbar-color" id="topbar-teal"
                    value="teal" data-bs-toggle="tooltip" data-bs-placement="top" title="Teal">
                <input class="color-selector-btn color-cyan" type="radio" name="topbar-color" id="topbar-cyan"
                    value="cyan" data-bs-toggle="tooltip" data-bs-placement="top" title="Cyan">
            </div>

            <h6 class="mt-4 mb-3 pt-2 sidebar-setting">Yan Menü Boyutu</h6>

            <div class="form-check sidebar-setting">
                <input class="form-check-input" type="radio" name="sidebar-size" id="sidebar-size-default"
                    value="default">
                <label class="form-check-label" for="sidebar-size-default">Varsayılan</label>
            </div>
            <div class="form-check sidebar-setting">
                <input class="form-check-input" type="radio" name="sidebar-size" id="sidebar-size-compact"
                    value="compact">
                <label class="form-check-label" for="sidebar-size-compact">Kompakt</label>
            </div>
            <div class="form-check sidebar-setting">
                <input class="form-check-input" type="radio" name="sidebar-size" id="sidebar-size-small" value="small">
                <label class="form-check-label" for="sidebar-size-small">Küçük (Sadece İkon)</label>
            </div>

            <h6 class="mt-4 mb-3 pt-2 sidebar-setting">Yan Menü Rengi</h6>

            <div class="form-check sidebar-setting d-inline-block me-2">
                <input class="form-check-input" type="radio" name="sidebar-color" id="sidebar-color-light"
                    value="light">
                <label class="form-check-label" for="sidebar-color-light">Açık</label>
            </div>
            <div class="form-check sidebar-setting d-inline-block me-2">
                <input class="form-check-input" type="radio" name="sidebar-color" id="sidebar-color-dark" value="dark">
                <label class="form-check-label" for="sidebar-color-dark">Koyu</label>
            </div>
            <div class="form-check sidebar-setting d-inline-block">
                <input class="form-check-input" type="radio" name="sidebar-color" id="sidebar-color-brand"
                    value="brand">
                <label class="form-check-label" for="sidebar-color-brand">Marka Rengi</label>
            </div>
            <div class="color-selector-group mt-2 sidebar-setting">
                <div class="color-picker-wrapper" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="Özel Yan Menü Rengi">
                    <input type="color" id="custom-sidebar-picker" value="#1c84ee">
                    <i class="mdi mdi-palette"></i>
                </div>
                <input class="color-selector-btn color-default" type="radio" name="sidebar-color" id="sidebar-default"
                    value="default" data-bs-toggle="tooltip" data-bs-placement="top" title="Varsayılan">
                <input class="color-selector-btn color-red" type="radio" name="sidebar-color" id="sidebar-red"
                    value="red" data-bs-toggle="tooltip" data-bs-placement="top" title="Kırmızı">
                <input class="color-selector-btn color-purple" type="radio" name="sidebar-color" id="sidebar-purple"
                    value="purple" data-bs-toggle="tooltip" data-bs-placement="top" title="Mor">
                <input class="color-selector-btn color-slate" type="radio" name="sidebar-color" id="sidebar-slate"
                    value="slate" data-bs-toggle="tooltip" data-bs-placement="top" title="Slate">
                <input class="color-selector-btn color-emerald" type="radio" name="sidebar-color" id="sidebar-emerald"
                    value="emerald" data-bs-toggle="tooltip" data-bs-placement="top" title="Zümrüt">
                <input class="color-selector-btn color-orange" type="radio" name="sidebar-color" id="sidebar-orange"
                    value="orange" data-bs-toggle="tooltip" data-bs-placement="top" title="Turuncu">
                <input class="color-selector-btn color-rose" type="radio" name="sidebar-color" id="sidebar-rose"
                    value="rose" data-bs-toggle="tooltip" data-bs-placement="top" title="Rose">
                <input class="color-selector-btn color-ersan" type="radio" name="sidebar-color" id="sidebar-ersan"
                    value="ersan" data-bs-toggle="tooltip" data-bs-placement="top" title="Ersan">
                <input class="color-selector-btn color-teal" type="radio" name="sidebar-color" id="sidebar-teal"
                    value="teal" data-bs-toggle="tooltip" data-bs-placement="top" title="Teal">
                <input class="color-selector-btn color-cyan" type="radio" name="sidebar-color" id="sidebar-cyan"
                    value="cyan" data-bs-toggle="tooltip" data-bs-placement="top" title="Cyan">
            </div>

            <h6 class="mt-4 mb-3 pt-2">Yön</h6>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-direction" id="layout-direction-ltr"
                    value="ltr">
                <label class="form-check-label" for="layout-direction-ltr">Soldan Sağa</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="layout-direction" id="layout-direction-rtl"
                    value="rtl">
                <label class="form-check-label" for="layout-direction-rtl">Sağdan Sola</label>
            </div>

        </div>

    </div> <!-- end slimscroll-menu-->
</div>
<!-- /Right-bar -->

<!-- Right bar overlay-->
<div class="rightbar-overlay"></div>