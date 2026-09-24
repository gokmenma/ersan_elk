<!-- AI Bordro Denetimi ve Risk Analizi Modalı -->
<div class="modal fade" id="modalAiBordroAudit" tabindex="-1" aria-labelledby="modalAiBordroAuditLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 42px; height: 42px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);">
                        <i class="mdi mdi-robot fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="modalAiBordroAuditLabel" style="letter-spacing: -0.3px;">Yapay Zeka Bordro Denetimi & Risk Analizi</h5>
                        <small class="text-white-50 fs-xs" id="aiAuditDonemTitle">Seçili bordro dönemi akıllı denetim raporu</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-light btn-re-audit d-flex align-items-center gap-1 shadow-sm fw-medium px-3" id="btnReAudit">
                        <i class="mdi mdi-refresh"></i> Yeniden Tara
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
            </div>

            <div class="modal-body p-4 bg-light bg-opacity-50">
                <!-- Yükleniyor Spinner -->
                <div id="aiAuditLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem;">
                        <span class="visually-hidden">Analiz ediliyor...</span>
                    </div>
                    <h5 class="mt-4 fw-semibold text-dark">Yapay Zeka Bordro Verilerini Denetliyor...</h5>
                    <p class="text-muted mb-0">Mevzuat kuralları, dağıtım dengeleri, yemek istisnaları ve anomaliler taranıyor.</p>
                </div>

                <!-- Sonuç Alanı -->
                <div id="aiAuditContent" style="display: none;">
                    <!-- Üst Skor ve Metrik Kartları -->
                    <div class="row g-3 mb-4">
                        <!-- Sağlık Skoru -->
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-sm rounded-3 bg-white">
                                <div class="card-body p-3 text-center d-flex flex-column justify-content-center">
                                    <span class="text-muted fw-semibold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">Bordro Sağlık Skoru</span>
                                    <div class="d-flex align-items-center justify-content-center gap-2 my-1">
                                        <div id="aiHealthScoreCircle" class="display-6 fw-bold text-success">100</div>
                                        <span class="fs-4 fw-bold text-muted">/ 100</span>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px; border-radius: 4px;">
                                        <div id="aiHealthProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                    </div>
                                    <span class="text-muted small mt-2 fw-medium" id="aiHealthStatusText">Güvenli & Stabil</span>
                                </div>
                            </div>
                        </div>

                        <!-- Kritik Hatalar -->
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-sm rounded-3 bg-white border-start border-danger border-4">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-danger fw-semibold small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Kritik Risk / Hata</span>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1"><i class="mdi mdi-alert-octagon"></i> Acil</span>
                                    </div>
                                    <h3 class="fw-bold mb-1 text-danger" id="aiCriticalCount">0</h3>
                                    <p class="text-muted small mb-0">Eksi bakiye, dağıtım kaçağı, gün aşımı</p>
                                </div>
                            </div>
                        </div>

                        <!-- Mevzuat & Dağıtım Uyarıları -->
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-sm rounded-3 bg-white border-start border-warning border-4">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-warning fw-semibold small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">İnceleme & Uyarı</span>
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1"><i class="mdi mdi-alert"></i> Dikkat</span>
                                    </div>
                                    <h3 class="fw-bold mb-1 text-warning" id="aiWarningCount">0</h3>
                                    <p class="text-muted small mb-0">Yemek tavanı aşımı, asgari altı dağıtım</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tahmini Finansal Risk -->
                        <div class="col-md-3">
                            <div class="card h-100 border-0 shadow-sm rounded-3 bg-white border-start border-primary border-4">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-primary fw-semibold small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Finansal Risk Tutarı</span>
                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1"><i class="mdi mdi-cash-multiple"></i> Risk</span>
                                    </div>
                                    <h3 class="fw-bold mb-1 text-primary" id="aiRiskAmount">0,00 ₺</h3>
                                    <p class="text-muted small mb-0">Potansiyel fazla/eksik ödeme hacmi</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Yönetici Özeti ve Analizi -->
                    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white" style="border-left: 4px solid #4f46e5 !important;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="text-white rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                                    <i class="mdi mdi-brain fs-6"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-0">Yapay Zeka Yönetici Denetim Raporu</h6>
                            </div>
                            <div id="aiExecutiveReportText" class="text-secondary lh-lg" style="font-size: 0.92rem;"></div>
                        </div>
                    </div>

                    <!-- Sorunlu Personeller & Tespit Edilen Bulgular -->
                    <div class="card border-0 shadow-sm rounded-3 bg-white">
                        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <i class="mdi mdi-account-search text-primary fs-5"></i> Tespit Edilen Riskler ve Personel Bulguları
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="aiIssuePersonCount">0 Personel</span>
                            </h6>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary fw-medium" id="btnFilterRiskliInTable">
                                    <i class="mdi mdi-filter-variant me-1"></i> Ana Tabloda Sadece Riskli Kayıtları Göster
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="aiAuditIssuesTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 22%;">Personel</th>
                                            <th style="width: 18%;">Ödeme Dağılımı</th>
                                            <th style="width: 38%;">Tespit Edilen Risk & Açıklama</th>
                                            <th style="width: 22%;">Önerilen Çözüm / Aksiyon</th>
                                        </tr>
                                    </thead>
                                    <tbody id="aiAuditIssuesTableBody">
                                        <!-- JS ile dinamik dolacak -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white py-3 px-4 border-top">
                <button type="button" class="btn btn-secondary px-4 fw-medium" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
