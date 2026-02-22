<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Sözleşmeler ve Hakedişler</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="?p=home">Ana Sayfa</a></li>
                    <li class="breadcrumb-item active">Sözleşmeler</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title">Sözleşme Listesi</h4>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#yeniSozlesmeModal">
                            <i class="bx bx-plus me-1"></i> Yeni Sözleşme
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="sozlesmeTable"
                        class="table table-bordered dt-responsive nowrap w-100 table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>İdare Adı</th>
                                <th>İşin Adı</th>
                                <th>Sözleşme Tarihi</th>
                                <th>Bitiş Tarihi</th>
                                <th>Sözleşme Bedeli</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via DataTables AJAX -->
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Yeni Sözleşme Modal -->
<div class="modal fade" id="yeniSozlesmeModal" tabindex="-1" aria-labelledby="yeniSozlesmeModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="yeniSozlesmeModalLabel">Yeni Sözleşme Tanımla</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="yeniSozlesmeForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İdare Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="idare_adi" required
                                placeholder="T.C. KASKİ GENEL MÜDÜRLÜĞÜ">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İşin Yüklenicisi</label>
                            <input type="text" class="form-control" name="isin_yuklenicisi"
                                value="ER-SAN ELEKTRİK İNŞ. TAAH.TİC.LTD.ŞTİ." required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">İşin Adı <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="isin_adi" rows="2" required
                                placeholder="Sözleşmede geçen tam iş adı"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">İhale Kayıt No</label>
                            <input type="text" class="form-control" name="ihale_kayit_no"
                                placeholder="2025/1219715 vb.">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">İhale Tarihi</label>
                            <input type="date" class="form-control" name="ihale_tarihi">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Yer Teslim Tarihi</label>
                            <input type="date" class="form-control" name="yer_teslim_tarihi">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sözleşme Tarihi</label>
                            <input type="date" class="form-control" name="sozlesme_tarihi">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">İşin Biteceği Tarih</label>
                            <input type="date" class="form-control" name="isin_bitecegi_tarih">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">İşin Süresi (Gün)</label>
                            <input type="number" class="form-control" name="isin_suresi" placeholder="422">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="durum">
                                <option value="aktif">Aktif</option>
                                <option value="pasif">Pasif</option>
                                <option value="tamamlandi">Tamamlandı</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Keşif Bedeli (TL)</label>
                            <input type="number" step="0.01" class="form-control" name="kesif_bedeli">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">İhale Tenzilatı (%)</label>
                            <input type="number" step="0.000001" class="form-control" name="ihale_tenzilati"
                                placeholder="Örn: 0.168769">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sözleşme Bedeli (TL) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="sozlesme_bedeli" required>
                        </div>

                        <hr class="mt-3">
                        <h5 class="mb-3">İmza Yetkilileri (Kontrol ve Onay)</h5>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kontrol Teşkilatı (İsim - Unvan)</label>
                            <textarea class="form-control" name="kontrol_teskilati" rows="3"
                                placeholder="ÖMER FARUK YAŞAR - İDARİ İŞLER SORUMLUSU&#10;HARUN KAZANCI - OKUMA YÖNETİCİSİ"></textarea>
                            <small class="text-muted">Her yetkiliyi yeni bir satıra yazın</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İdare Adına Onaylayan (İsim)</label>
                            <input type="text" class="form-control" name="idare_onaylayan"
                                placeholder="KEMALETTİN GÜNEN">
                            <label class="form-label mt-2">Unvanı</label>
                            <input type="text" class="form-control" name="idare_onaylayan_unvan"
                                placeholder="ABONE İŞLERİ DAİRE BAŞKANI">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveSozlesme">
                        <i class="bx bx-save me-1"></i> Sözleşmeyi Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/hakedisler/js/sozlesmeler.js?v=<?= time() ?>"></script>