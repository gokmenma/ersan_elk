                        <!-- Üst Bilgi Çubuğu (Dashboard Stili) -->
                        <div class="card border-0 shadow-sm mb-4 bordro-info-bar"
                            style="border-radius: 20px; background: rgba(231, 111, 81, 0.03); border: 1px solid rgba(231, 111, 81, 0.1) !important;">
                            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="bg-white rounded-3 shadow-sm p-2 me-3 d-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bx bx-calendar-event fs-3" style="color: #E76F51;"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <h5 class="mb-0 fw-bold bordro-text-heading" id="displayDonemAdi">
                                                <?= htmlspecialchars($selectedDonem->donem_adi) ?>
                                            </h5>
                                            <?php if (!$donemKapali): ?>
                                                <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-muted"
                                                    id="btnEditDonemAdi" title="Dönem Adını Güncelle">
                                                    <i class="bx bx-edit-alt fs-6"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted fw-medium">
                                            <i class="bx bx-time-five me-1"></i>
                                            <?= date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi)) ?> -
                                            <?= date('d.m.Y', strtotime($selectedDonem->bitis_tarihi)) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="d-none d-md-flex align-items-center gap-3">
                                    <div class="text-end me-2">
                                        <p class="text-muted mb-0 small fw-bold">TOPLAM PERSONEL</p>
                                        <h5 class="mb-0 fw-bold bordro-text-heading"><?= count($personeller) ?> <span
                                                class="small text-muted fw-normal">Kişi</span></h5>
                                    </div>
                                    <div class="vr text-muted opacity-25" style="height: 35px;"></div>
                                    <div class="d-flex align-items-start gap-2">
                                        <span
                                            class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                            style="background: var(--bs-card-bg); color: var(--bs-body-color) !important;">
                                            <span class="rounded-circle me-2"
                                                style="width: 8px; height: 8px; background: <?= $donemKapali ? '#f43f5e' : '#10b981' ?>;"></span>
                                            <?= $donemKapali ? 'KAPALI' : 'AÇIK' ?>
                                        </span>
                                        <?php if ($latestCalculation): ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <span
                                                    class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                                    style="background: var(--bs-card-bg); color: var(--bs-body-color) !important;">
                                                    <span class="rounded-circle me-2"
                                                        style="width: 8px; height: 8px; background: #10b981;"></span>
                                                    HESAPLANDI
                                                </span>
                                                <div class="text-muted mt-1"
                                                    style="font-size: 9px; font-weight: 600; opacity: 0.8;">
                                                    <i
                                                        class="bx bx-check-double me-1"></i><?= date('d.m.Y H:i', strtotime($latestCalculation)) ?><?= !empty($latestCalculator) ? ' | ' . htmlspecialchars($latestCalculator, ENT_QUOTES, 'UTF-8') : '' ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($gorevGecmisiEksikPersoneller)): ?>
                                            <span
                                                class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                                style="background: rgba(245, 158, 11, 0.1); color: #f59e0b !important; cursor: help;"
                                                data-bs-toggle="tooltip" data-bs-html="true"
                                                title="<?= htmlspecialchars(implode(', ', $gorevGecmisiEksikPersoneller)) ?>">
                                                <i class="bx bx-error-circle me-1"></i>
                                                <?= count($gorevGecmisiEksikPersoneller) ?> Görev Geçmişi Eksik
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dönem Toplamları Kartları (Dashboard Stili) -->
                        <div class="row g-3 mb-4">
                            <!-- Toplam Alacağı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #E76F51; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(231, 111, 81, 0.1);">
                                                <i class="bx bx-receipt fs-4" style="color: #E76F51;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold"
                                                style="font-size: 0.65rem;">HAKEDİŞ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM ALACAĞI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamAlacagi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Kesinti Tutarı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                                <i class="bx bx-minus-circle fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold"
                                                style="font-size: 0.65rem;">KESİNTİ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">KESİNTİ TUTARI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamAlacagi - $toplamNetAlacagi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Net Maaş -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                <i class="bx bx-wallet fs-4" style="color: #2a9d8f;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">NET</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">NET MAAŞ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamNetAlacagi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- İcra Kesintisi -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #ef4444; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(239, 68, 68, 0.1);">
                                                <i class="bx bx-shield-x fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İCRA</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">İCRA KESİNTİSİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamIcra, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Banka -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                <i class="bx bxs-bank fs-4 text-info"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">RESMİ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">BANKA ÖDEMESİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamBanka, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Sodexo -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #8b5cf6; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(139, 92, 246, 0.1);">
                                                <i class="bx bx-food-menu fs-4" style="color: #8b5cf6;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">YEMEK</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">SODEXO</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <span id="total-sodexo"><?= number_format($toplamSodexo, 2, ',', '.') ?></span>
                                            <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Elden -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-wallet-alt fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">NAKİT</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">ELDEN ÖDEME</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <span id="total-elden"><?= number_format($toplamElden, 2, ',', '.') ?></span>
                                            <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
