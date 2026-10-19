<!-- KEP İletisi ve Resmi Delil Detayı Modalı -->
<div class="modal fade" id="modalKepOnizleme" tabindex="-1" aria-labelledby="modalKepOnizlemeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <!-- Modal Header -->
            <div class="modal-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bx bx-mail-send fs-4 text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-size-15 fw-bold text-dark mb-0" id="modalKepOnizlemeTitle">
                            KEP İletisi ve Delil Detayı
                        </h5>
                        <span class="text-muted font-size-11" id="kepPreviewSubTitle">PTT Kayıtlı Elektronik Posta (KEP) Gönderisi</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="kepPreviewDurumBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 font-size-12 rounded-pill fw-semibold">
                        Yükleniyor...
                    </span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4">
                <!-- Yükleniyor Göstergesi -->
                <div id="kepPreviewLoader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                    <div class="text-muted font-size-13 mt-2">KEP iletisi ve PTT delilleri yükleniyor...</div>
                </div>

                <!-- Asıl İçerik Konteyneri -->
                <div id="kepPreviewContent" class="d-none">
                    <!-- Üst Bilgi Kartı -->
                    <div class="card border rounded-3 bg-light shadow-none mb-3">
                        <div class="card-body p-3">
                            <div class="row g-3 font-size-12">
                                <div class="col-md-6">
                                    <div class="text-muted font-size-11 mb-1"><i class="bx bx-user me-1"></i> Gönderen KEP Adresi:</div>
                                    <div class="font-monospace fw-bold text-dark font-size-13" id="kepPreviewGonderen">-</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted font-size-11 mb-1"><i class="bx bx-send me-1"></i> Alıcı KEP Adresi:</div>
                                    <div id="kepPreviewAlicilar" class="d-flex flex-wrap gap-1"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted font-size-11 mb-1"><i class="bx bx-barcode me-1"></i> PTT Takip Kodu (KepId):</div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="font-monospace text-primary fw-bold font-size-12 text-truncate" style="max-width: 380px;" id="kepPreviewKepId">-</span>
                                        <button type="button" class="btn btn-sm btn-link text-muted p-0 ms-1" id="btnCopyKepId" title="Kodu Kopyala">
                                            <i class="bx bx-copy font-size-14"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-muted font-size-11 mb-1"><i class="bx bx-calendar me-1"></i> Gönderim Tarihi:</div>
                                    <div class="fw-semibold text-dark" id="kepPreviewTarih">-</div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-muted font-size-11 mb-1"><i class="bx bx-package me-1"></i> İleti / Paket Türü:</div>
                                    <span class="badge bg-secondary-subtle text-secondary border font-size-11" id="kepPreviewPaketTuru">Standart</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sekmeler -->
                    <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active font-size-13" id="tabKepIleti-tab" data-bs-toggle="tab" data-bs-target="#tabKepIleti" type="button" role="tab" aria-controls="tabKepIleti" aria-selected="true">
                                <i class="bx bx-file-blank me-1"></i> İleti Metni ve Ekler
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-size-13" id="tabKepDeliller-tab" data-bs-toggle="tab" data-bs-target="#tabKepDeliller" type="button" role="tab" aria-controls="tabKepDeliller" aria-selected="false">
                                <i class="bx bx-shield-quarter me-1 text-primary"></i> PTT Resmi Yasal Delilleri
                                <span class="badge bg-success rounded-pill ms-1" id="kepPreviewDelilSayisiBadge">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- 1. Sekme: İleti Metni ve Ekler -->
                        <div class="tab-pane fade show active" id="tabKepIleti" role="tabpanel" aria-labelledby="tabKepIleti-tab">
                            <div class="mb-3">
                                <label class="text-muted font-size-11 fw-semibold mb-1">KONU</label>
                                <h5 class="fw-bold text-dark font-size-15 mb-0" id="kepPreviewKonu">-</h5>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted font-size-11 fw-semibold mb-1">İLETİ İÇERİĞİ</label>
                                <div id="kepPreviewIcerik" class="bg-white p-3 rounded-3 border text-dark font-size-13" style="min-height: 160px; max-height: 420px; overflow-y: auto; line-height: 1.6;">
                                    -
                                </div>
                            </div>

                            <!-- Ekler Bölümü -->
                            <div id="kepPreviewEklerWrapper" class="d-none">
                                <label class="text-muted font-size-11 fw-semibold mb-2"><i class="bx bx-paperclip me-1"></i> EKLİ DOSYALAR (<span id="kepPreviewEkSayisi">0</span>)</label>
                                <div id="kepPreviewEklerListesi" class="row g-2"></div>
                            </div>
                        </div>

                        <!-- 2. Sekme: PTT Delilleri -->
                        <div class="tab-pane fade" id="tabKepDeliller" role="tabpanel" aria-labelledby="tabKepDeliller-tab">
                            <div class="alert alert-info py-2 px-3 rounded-3 d-flex align-items-center justify-content-between mb-3 font-size-12">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bx bx-info-circle fs-5 flex-shrink-0 text-info"></i>
                                    <span>PTT KEP yasal delilleri ETSI TS 02640 ve REM standardında zaman damgalı resmi ispat belgeleridir.</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" id="btnKepDelilYenile">
                                    <i class="bx bx-refresh me-1"></i> PTT'den Delilleri Güncelle
                                </button>
                            </div>

                            <!-- Delil Timeline Akışı -->
                            <div id="kepPreviewDelillerListesi" class="d-flex flex-column gap-2">
                                <div class="text-center py-4 text-muted font-size-13">Delil kaydı bulunamadı.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between align-items-center">
                <div class="font-size-12 text-muted">
                    <span id="kepPreviewFooterEvrakNo" class="fw-semibold"></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="btnKepPreviewPdf">
                        <i class="bx bx-file me-1"></i> Resmi Evrak PDF'i
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
