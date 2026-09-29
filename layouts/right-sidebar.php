<!-- Right Sidebar -->
<div class="right-bar modern-theme-drawer">
    <div data-simplebar class="h-100 theme-drawer-wrapper">
        <!-- Drawer Header -->
        <div class="theme-drawer-header">
            <div class="d-flex align-items-center gap-2">
                <div class="drawer-icon-box">
                    <i class="mdi mdi-palette-swatch-outline"></i>
                </div>
                <div>
                    <h5 class="drawer-title m-0">Görünüm & Tema</h5>
                    <p class="drawer-subtitle m-0">Arayüz tercihlerinizi özelleştirin</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" id="reset-theme-btn" title="Varsayılana Sıfırla" data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="mdi mdi-refresh font-size-18"></i>
                </button>
                <a href="javascript:void(0);" class="right-bar-toggle right-bar-close-btn" title="Kapat">
                    <i class="mdi mdi-close font-size-18"></i>
                </a>
            </div>
        </div>

        <!-- Drawer Segmented Tabs Nav -->
        <div class="theme-drawer-nav">
            <ul class="nav nav-pills custom-theme-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-presets-btn" data-bs-toggle="pill" data-bs-target="#tab-presets" type="button" role="tab" aria-controls="tab-presets" aria-selected="true">
                        <i class="mdi mdi-auto-fix me-1"></i>Temalar
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-colors-btn" data-bs-toggle="pill" data-bs-target="#tab-colors" type="button" role="tab" aria-controls="tab-colors" aria-selected="false">
                        <i class="mdi mdi-palette-outline me-1"></i>Renk & Mod
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-layout-btn" data-bs-toggle="pill" data-bs-target="#tab-layout" type="button" role="tab" aria-controls="tab-layout" aria-selected="false">
                        <i class="mdi mdi-view-dashboard-outline me-1"></i>Menü & Font
                    </button>
                </li>
            </ul>
        </div>

        <!-- Drawer Tab Content -->
        <div class="tab-content theme-drawer-content p-3">

            <!-- TAB 1: HAZIR TEMALAR -->
            <div class="tab-pane fade show active" id="tab-presets" role="tabpanel" aria-labelledby="tab-presets-btn">
                <div class="theme-section-info mb-3">
                    <span class="badge bg-primary-subtle text-primary fw-medium px-2 py-1">
                        <i class="mdi mdi-lightning-bolt-outline me-1"></i>Tek Tıkla Uygula
                    </span>
                    <p class="small text-muted mt-1 mb-0">Uyumlu renk, font ve stil paketlerinden birini seçin.</p>
                </div>

                <div class="theme-preset-grid">
                    <!-- 1. Kode -->
                    <div class="theme-preset-card" data-preset="kode" role="button" title="Kode Teması">
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
                        <span class="preset-sub">Mavi & Koyu</span>
                    </div>

                    <!-- 2. Ersan Gold -->
                    <div class="theme-preset-card" data-preset="ersan" role="button" title="Ersan Gold">
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
                        <span class="preset-sub">Altın & Koyu</span>
                    </div>

                    <!-- 3. Zümrüt -->
                    <div class="theme-preset-card" data-preset="midnight-emerald" role="button" title="Zümrüt">
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
                        <span class="preset-sub">Zümrüt Yeşili</span>
                    </div>

                    <!-- 4. Kraliyet Moru -->
                    <div class="theme-preset-card" data-preset="royal-purple" role="button" title="Kraliyet Moru">
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
                        <span class="preset-sub">Derin Mor</span>
                    </div>

                    <!-- 5. Rose -->
                    <div class="theme-preset-card" data-preset="crimson-rose" role="button" title="Rose">
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
                        <span class="preset-sub">Kırmızı & Koyu</span>
                    </div>

                    <!-- 6. Sade Beyaz -->
                    <div class="theme-preset-card" data-preset="minimalist" role="button" title="Sade Beyaz">
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
                        <span class="preset-sub">Minimal Aydınlık</span>
                    </div>

                    <!-- 7. Koyu Gece -->
                    <div class="theme-preset-card" data-preset="dark-pro" role="button" title="Koyu Gece">
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
                        <span class="preset-sub">Tam Koyu Mod</span>
                    </div>

                    <!-- 8. Okyanus -->
                    <div class="theme-preset-card" data-preset="ocean-deep" role="button" title="Okyanus">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #0284c7;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #0f172a;"></div>
                                <div class="preset-content" style="background: #f0f9ff;">
                                    <div class="preset-accent-bar" style="background: #0284c7;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">Okyanus</span>
                        <span class="preset-sub">Derin Lacivert</span>
                    </div>

                    <!-- 9. Kehribar -->
                    <div class="theme-preset-card" data-preset="sunset-amber" role="button" title="Kehribar">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #f59e0b;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #1c1917;"></div>
                                <div class="preset-content" style="background: #fffbeb;">
                                    <div class="preset-accent-bar" style="background: #f97316;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">Kehribar</span>
                        <span class="preset-sub">Sıcak Amber</span>
                    </div>

                    <!-- 10. Orman Yeşili -->
                    <div class="theme-preset-card" data-preset="forest-moss" role="button" title="Orman Yeşili">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #059669;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #064e3b;"></div>
                                <div class="preset-content" style="background: #ecfdf5;">
                                    <div class="preset-accent-bar" style="background: #10b981;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">Orman Yeşili</span>
                        <span class="preset-sub">Doğal Zümrüt</span>
                    </div>

                    <!-- 11. Siber Mor -->
                    <div class="theme-preset-card" data-preset="cyber-violet" role="button" title="Siber Mor">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #7c3aed;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #180d38;"></div>
                                <div class="preset-content" style="background: #f5f3ff;">
                                    <div class="preset-accent-bar" style="background: #8b5cf6;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">Siber Mor</span>
                        <span class="preset-sub">Neon Violet</span>
                    </div>

                    <!-- 12. İskandinav Gri -->
                    <div class="theme-preset-card" data-preset="nordic-slate" role="button" title="İskandinav Gri">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #475569;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #1e293b;"></div>
                                <div class="preset-content" style="background: #f1f5f9;">
                                    <div class="preset-accent-bar" style="background: #64748b;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">İskandinav</span>
                        <span class="preset-sub">Slate Gri</span>
                    </div>

                    <!-- 13. Yakut Gece -->
                    <div class="theme-preset-card" data-preset="ruby-dark" role="button" title="Yakut Gece">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #9f1239;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #1f0a10;"></div>
                                <div class="preset-content" style="background: #fff1f2;">
                                    <div class="preset-accent-bar" style="background: #e11d48;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">Yakut Gece</span>
                        <span class="preset-sub">Bordo & Koyu</span>
                    </div>

                    <!-- 14. Nane Ferahlığı -->
                    <div class="theme-preset-card" data-preset="mint-fresh" role="button" title="Nane Ferahlığı">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #0d9488;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #132a26;"></div>
                                <div class="preset-content" style="background: #f0fdfa;">
                                    <div class="preset-accent-bar" style="background: #0d9488;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">Nane</span>
                        <span class="preset-sub">Teal & Ferah</span>
                    </div>

                    <!-- 15. Kahve Bronz -->
                    <div class="theme-preset-card" data-preset="mocha-gold" role="button" title="Kahve Bronz">
                        <div class="preset-preview">
                            <div class="preset-topbar" style="background: #78350f;"></div>
                            <div class="preset-body">
                                <div class="preset-sidebar" style="background: #271406;"></div>
                                <div class="preset-content" style="background: #fefce8;">
                                    <div class="preset-accent-bar" style="background: #d97706;"></div>
                                </div>
                            </div>
                        </div>
                        <span class="preset-name">Kahve Bronz</span>
                        <span class="preset-sub">Mocha & Gold</span>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RENK & MOD -->
            <div class="tab-pane fade" id="tab-colors" role="tabpanel" aria-labelledby="tab-colors-btn">
                
                <!-- Görünüm Modu (Açık / Koyu) -->
                <div class="drawer-group-card mb-3">
                    <div class="drawer-group-title">
                        <i class="mdi mdi-theme-light-dark text-primary"></i>
                        <span>Görünüm Modu</span>
                    </div>
                    <div class="segmented-option-grid grid-2">
                        <label class="segmented-card" for="layout-mode-light">
                            <input type="radio" name="layout-mode" id="layout-mode-light" value="light" checked>
                            <div class="segmented-card-inner">
                                <i class="mdi mdi-white-balance-sunny fs-5 text-warning mb-1"></i>
                                <span class="segmented-label">Açık Mod</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="layout-mode-dark">
                            <input type="radio" name="layout-mode" id="layout-mode-dark" value="dark">
                            <div class="segmented-card-inner">
                                <i class="mdi mdi-moon-waning-crescent fs-5 text-info mb-1"></i>
                                <span class="segmented-label">Koyu Mod</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Ana Vurgu Rengi -->
                <div class="drawer-group-card mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="drawer-group-title mb-0">
                            <i class="mdi mdi-palette text-primary"></i>
                            <span>Vurgu Rengi</span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary small px-2 py-1">Ana Renk</span>
                    </div>
                    <div class="color-selector-group">
                        <div class="color-picker-wrapper" data-bs-toggle="tooltip" data-bs-placement="top" title="Özel Renk Seç">
                            <input type="color" id="custom-theme-picker" value="#e2bd61">
                            <i class="mdi mdi-eyedropper-variant"></i>
                        </div>
                        <input class="color-selector-btn color-default" type="radio" name="theme-mode" id="theme-default" value="default" data-bs-toggle="tooltip" data-bs-placement="top" title="Mavi">
                        <input class="color-selector-btn color-ersan" type="radio" name="theme-mode" id="theme-ersan" value="ersan" checked data-bs-toggle="tooltip" data-bs-placement="top" title="Ersan Altın">
                        <input class="color-selector-btn color-emerald" type="radio" name="theme-mode" id="theme-emerald" value="emerald" data-bs-toggle="tooltip" data-bs-placement="top" title="Zümrüt">
                        <input class="color-selector-btn color-purple" type="radio" name="theme-mode" id="theme-purple" value="purple" data-bs-toggle="tooltip" data-bs-placement="top" title="Mor">
                        <input class="color-selector-btn color-rose" type="radio" name="theme-mode" id="theme-rose" value="rose" data-bs-toggle="tooltip" data-bs-placement="top" title="Gül / Kırmızı">
                        <input class="color-selector-btn color-teal" type="radio" name="theme-mode" id="theme-teal" value="teal" data-bs-toggle="tooltip" data-bs-placement="top" title="Teal">
                        <input class="color-selector-btn color-cyan" type="radio" name="theme-mode" id="theme-cyan" value="cyan" data-bs-toggle="tooltip" data-bs-placement="top" title="Camgöbeği">
                        <input class="color-selector-btn color-orange" type="radio" name="theme-mode" id="theme-orange" value="orange" data-bs-toggle="tooltip" data-bs-placement="top" title="Turuncu">
                        <input class="color-selector-btn color-red" type="radio" name="theme-mode" id="theme-red" value="red" data-bs-toggle="tooltip" data-bs-placement="top" title="Mercan Kırmızı">
                        <input class="color-selector-btn color-slate" type="radio" name="theme-mode" id="theme-slate" value="slate" data-bs-toggle="tooltip" data-bs-placement="top" title="Antrasit">
                    </div>
                </div>

                <!-- Üst Bar & Yan Menü Tonları (Sadeleştirilmiş) -->
                <div class="drawer-group-card mb-3">
                    <div class="drawer-group-title">
                        <i class="mdi mdi-page-layout-header text-primary"></i>
                        <span>Üst Bar Tonu</span>
                    </div>
                    <div class="segmented-option-grid grid-3">
                        <label class="segmented-card" for="topbar-color-light">
                            <input type="radio" name="topbar-color" id="topbar-color-light" value="light" checked>
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Açık</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="topbar-color-dark">
                            <input type="radio" name="topbar-color" id="topbar-color-dark" value="dark">
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Koyu</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="topbar-color-brand">
                            <input type="radio" name="topbar-color" id="topbar-color-brand" value="brand">
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Marka</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="drawer-group-card sidebar-setting mb-3">
                    <div class="drawer-group-title">
                        <i class="mdi mdi-page-layout-sidebar-left text-primary"></i>
                        <span>Yan Menü Tonu</span>
                    </div>
                    <div class="segmented-option-grid grid-3">
                        <label class="segmented-card" for="sidebar-color-light">
                            <input type="radio" name="sidebar-color" id="sidebar-color-light" value="light">
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Açık</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="sidebar-color-dark">
                            <input type="radio" name="sidebar-color" id="sidebar-color-dark" value="dark" checked>
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Koyu</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="sidebar-color-brand">
                            <input type="radio" name="sidebar-color" id="sidebar-color-brand" value="brand">
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Marka</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Hidden inputs for backward compatibility -->
                <div class="d-none">
                    <input type="color" id="custom-topbar-picker" value="#1c84ee">
                    <input type="color" id="custom-sidebar-picker" value="#1c84ee">
                    <input type="radio" name="topbar-color" id="topbar-default" value="default">
                    <input type="radio" name="topbar-color" id="topbar-red" value="red">
                    <input type="radio" name="topbar-color" id="topbar-purple" value="purple">
                    <input type="radio" name="topbar-color" id="topbar-slate" value="slate">
                    <input type="radio" name="topbar-color" id="topbar-emerald" value="emerald">
                    <input type="radio" name="topbar-color" id="topbar-orange" value="orange">
                    <input type="radio" name="topbar-color" id="topbar-rose" value="rose">
                    <input type="radio" name="topbar-color" id="topbar-ersan" value="ersan">
                    <input type="radio" name="topbar-color" id="topbar-teal" value="teal">
                    <input type="radio" name="topbar-color" id="topbar-cyan" value="cyan">
                    <input type="radio" name="sidebar-color" id="sidebar-default" value="default">
                    <input type="radio" name="sidebar-color" id="sidebar-red" value="red">
                    <input type="radio" name="sidebar-color" id="sidebar-purple" value="purple">
                    <input type="radio" name="sidebar-color" id="sidebar-slate" value="slate">
                    <input type="radio" name="sidebar-color" id="sidebar-emerald" value="emerald">
                    <input type="radio" name="sidebar-color" id="sidebar-orange" value="orange">
                    <input type="radio" name="sidebar-color" id="sidebar-rose" value="rose">
                    <input type="radio" name="sidebar-color" id="sidebar-ersan" value="ersan">
                    <input type="radio" name="sidebar-color" id="sidebar-teal" value="teal">
                    <input type="radio" name="sidebar-color" id="sidebar-cyan" value="cyan">
                </div>

            </div>

            <!-- TAB 3: MENÜ & FONT -->
            <div class="tab-pane fade" id="tab-layout" role="tabpanel" aria-labelledby="tab-layout-btn">
                
                <!-- Menü Düzeni (Dikey / Yatay) -->
                <div class="drawer-group-card mb-3">
                    <div class="drawer-group-title">
                        <i class="mdi mdi-view-quilt-outline text-primary"></i>
                        <span>Menü Yerleşimi</span>
                    </div>
                    <div class="segmented-option-grid grid-2">
                        <label class="segmented-card" for="layout-vertical">
                            <input type="radio" name="layout" id="layout-vertical" value="vertical">
                            <div class="segmented-card-inner">
                                <i class="mdi mdi-page-layout-sidebar-left fs-5 mb-1 text-primary"></i>
                                <span class="segmented-label">Dikey Menü</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="layout-horizontal">
                            <input type="radio" name="layout" id="layout-horizontal" value="horizontal">
                            <div class="segmented-card-inner">
                                <i class="mdi mdi-page-layout-header fs-5 mb-1 text-primary"></i>
                                <span class="segmented-label">Yatay Menü</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Yan Menü Boyutu -->
                <div class="drawer-group-card sidebar-setting mb-3">
                    <div class="drawer-group-title">
                        <i class="mdi mdi-arrow-expand-horizontal text-primary"></i>
                        <span>Yan Menü Boyutu</span>
                    </div>
                    <div class="segmented-option-grid grid-3">
                        <label class="segmented-card" for="sidebar-size-default">
                            <input type="radio" name="sidebar-size" id="sidebar-size-default" value="default">
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Geniş</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="sidebar-size-compact">
                            <input type="radio" name="sidebar-size" id="sidebar-size-compact" value="compact">
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Kompakt</span>
                            </div>
                        </label>
                        <label class="segmented-card" for="sidebar-size-small">
                            <input type="radio" name="sidebar-size" id="sidebar-size-small" value="small">
                            <div class="segmented-card-inner">
                                <span class="segmented-label">Küçük</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Tipografi (Yazı Tipi) -->
                <div class="drawer-group-card mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="drawer-group-title mb-0">
                            <i class="mdi mdi-format-font text-primary"></i>
                            <span>Yazı Tipi (Font)</span>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary small px-2 py-1">Tipografi</span>
                    </div>

                    <div class="font-preset-grid">
                        <div class="font-preset-card" data-font="Inter" role="button">
                            <div class="font-name" style="font-family: 'Inter', sans-serif;">Inter</div>
                            <div class="font-desc">Modern UI</div>
                        </div>
                        <div class="font-preset-card" data-font="Plus Jakarta Sans" role="button">
                            <div class="font-name" style="font-family: 'Plus Jakarta Sans', sans-serif;">Plus Jakarta</div>
                            <div class="font-desc">Kurumsal & SaaS</div>
                        </div>
                        <div class="font-preset-card" data-font="Outfit" role="button">
                            <div class="font-name" style="font-family: 'Outfit', sans-serif;">Outfit</div>
                            <div class="font-desc">Estetik & Yuvarlak</div>
                        </div>
                        <div class="font-preset-card" data-font="Poppins" role="button">
                            <div class="font-name" style="font-family: 'Poppins', sans-serif;">Poppins</div>
                            <div class="font-desc">Geometrik & Canlı</div>
                        </div>
                        <div class="font-preset-card" data-font="Montserrat" role="button">
                            <div class="font-name" style="font-family: 'Montserrat', sans-serif;">Montserrat</div>
                            <div class="font-desc">Prestij & Şık</div>
                        </div>
                        <div class="font-preset-card" data-font="Geist" role="button">
                            <div class="font-name" style="font-family: 'Geist', sans-serif;">Geist</div>
                            <div class="font-desc">Minimal & Tech</div>
                        </div>
                        <div class="font-preset-card" data-font="Roboto" role="button">
                            <div class="font-name" style="font-family: 'Roboto', sans-serif;">Roboto</div>
                            <div class="font-desc">Klasik & Sade</div>
                        </div>
                        <div class="font-preset-card" data-font="Manrope" role="button">
                            <div class="font-name" style="font-family: 'Manrope', sans-serif;">Manrope</div>
                            <div class="font-desc">Modern & Dengeli</div>
                        </div>
                        <div class="font-preset-card" data-font="DM Sans" role="button">
                            <div class="font-name" style="font-family: 'DM Sans', sans-serif;">DM Sans</div>
                            <div class="font-desc">Zarif & Okunaklı</div>
                        </div>
                        <div class="font-preset-card" data-font="Space Grotesk" role="button">
                            <div class="font-name" style="font-family: 'Space Grotesk', sans-serif;">Space Grotesk</div>
                            <div class="font-desc">Dinamik & Fütüristik</div>
                        </div>
                        <div class="font-preset-card" data-font="Lexend" role="button">
                            <div class="font-name" style="font-family: 'Lexend', sans-serif;">Lexend</div>
                            <div class="font-desc">Göz Dostu & Net</div>
                        </div>
                        <div class="font-preset-card" data-font="Nunito" role="button">
                            <div class="font-name" style="font-family: 'Nunito', sans-serif;">Nunito</div>
                            <div class="font-desc">Samimi & Yumuşak</div>
                        </div>
                    </div>

                    <!-- Hidden radio group for backward compatibility -->
                    <div class="font-selector-group d-none">
                        <input class="form-check-input" type="radio" name="font-family" id="font-geist" value="Geist">
                        <input class="form-check-input" type="radio" name="font-family" id="font-inter" value="Inter">
                        <input class="form-check-input" type="radio" name="font-family" id="font-outfit" value="Outfit">
                        <input class="form-check-input" type="radio" name="font-family" id="font-poppins" value="Poppins">
                        <input class="form-check-input" type="radio" name="font-family" id="font-jakarta" value="Plus Jakarta Sans">
                        <input class="form-check-input" type="radio" name="font-family" id="font-montserrat" value="Montserrat">
                        <input class="form-check-input" type="radio" name="font-family" id="font-roboto" value="Roboto">
                        <input class="form-check-input" type="radio" name="font-family" id="font-manrope" value="Manrope">
                        <input class="form-check-input" type="radio" name="font-family" id="font-dmsans" value="DM Sans">
                        <input class="form-check-input" type="radio" name="font-family" id="font-spacegrotesk" value="Space Grotesk">
                        <input class="form-check-input" type="radio" name="font-family" id="font-lexend" value="Lexend">
                        <input class="form-check-input" type="radio" name="font-family" id="font-nunito" value="Nunito">
                    </div>
                </div>

                <!-- Hidden inputs for backward compatibility -->
                <div class="d-none">
                    <input type="radio" name="layout-width" id="layout-width-fuild" value="fuild" checked>
                    <input type="radio" name="layout-width" id="layout-width-boxed" value="boxed">
                    <input type="radio" name="layout-position" id="layout-position-fixed" value="fixed" checked>
                    <input type="radio" name="layout-position" id="layout-position-scrollable" value="scrollable">
                    <input type="radio" name="device-view" id="device-view-desktop" value="desktop" checked>
                    <input type="radio" name="device-view" id="device-view-mobile" value="mobile">
                    <input type="radio" name="layout-direction" id="layout-direction-ltr" value="ltr" checked>
                    <input type="radio" name="layout-direction" id="layout-direction-rtl" value="rtl">
                </div>

            </div>

        </div> <!-- end tab-content -->

    </div> <!-- end simplebar -->
</div>
<!-- /Right-bar -->

<!-- Right bar overlay-->
<div class="rightbar-overlay"></div>