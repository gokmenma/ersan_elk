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
                <h5 class="modal-title text-white" id="yeniSozlesmeModalLabel">Sözleşme Tanımla/Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="yeniSozlesmeForm">
                <input type="hidden" name="id" id="sozlesme_id">
                <div class="modal-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#sozlesme-bilgileri-tab" role="tab">
                                <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                <span class="d-none d-sm-block">1. Sözleşme Bilgileri</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#birim-fiyat-tab" role="tab">
                                <span class="d-block d-sm-none"><i class="far fa-list-alt"></i></span>
                                <span class="d-none d-sm-block">2. Birim Fiyat Teklif Cetveli</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#fiyat-farki-tab" role="tab">
                                <span class="d-block d-sm-none"><i class="bx bx-calculator"></i></span>
                                <span class="d-none d-sm-block">3. Fiyat Farkı ve Kesintiler</span>
                            </a>
                        </li>
                    </ul>

                    <!-- Tab panes -->
                    <div class="tab-content p-3 text-muted">
                        <!-- SÖZLEŞME BİLGİLERİ TAB -->
                        <div class="tab-pane active" id="sozlesme-bilgileri-tab" role="tabpanel">
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
                                        <label class="form-label">Sözleşme Bedeli (TL) <span
                                                class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" name="sozlesme_bedeli"
                                            required>
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
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BİRİM FİYAT CETVELİ TAB -->
                        <div class="tab-pane" id="birim-fiyat-tab" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Birim Fiyat Teklif Cetveli (Sözleşme Kalemleri)</h6>
                                <button type="button" class="btn btn-sm btn-success" onclick="satirEkle()">
                                    <i class="bx bx-plus me-1"></i> Yeni Satır Ekle
                                </button>
                            </div>
                            <div class="alert alert-warning mb-3">Milyon/Bin ayıracı kullanmayınız, ondalık kısımları
                                nokta (.) ile ayırınız (Örn: 1000.50).</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle" id="birimFiyatTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">Sıra</th>
                                            <th style="width: 120px;">Poz No</th>
                                            <th>İşin Adı</th>
                                            <th style="width: 120px;">Ölçü Birimi</th>
                                            <th style="width: 120px;">Miktarı</th>
                                            <th style="width: 150px;">Teklif Edilen B.Fiyat</th>
                                            <th style="width: 150px;">Tutarı</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="birimFiyatBody">
                                        <!-- Satırlar JS ile eklenecek -->
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light fw-bold">
                                            <td colspan="6" class="text-end">GENEL TOPLAM:</td>
                                            <td id="genelToplamTutar" class="text-end text-primary">0,00 ₺</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- FİYAT FARKI VE KESİNTİLER TAB -->
                        <div class="tab-pane" id="fiyat-farki-tab" role="tabpanel">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle me-1"></i> Sözleşme genelindeki standart katsayıları ve
                                    temel endeksleri buradan tanımlayınız. Bu değerler her yeni hakedişte otomatik
                                    olarak getirilecektir.
                                </div>
                            </div>
                            <div class="col-md-6 border-end">
                                <h6 class="mb-3 text-primary">Fiyat Farkı Katsayıları (P1)</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">a1 (İşçilik) Katsayısı</label>
                                        <input type="number" step="0.00001" class="form-control" name="a1_katsayisi"
                                            value="0.28000">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">b1 (Motorin) Katsayısı</label>
                                        <input type="number" step="0.00001" class="form-control" name="b1_katsayisi"
                                            value="0.22000">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">b2 (Yİ-ÜFE) Katsayısı</label>
                                        <input type="number" step="0.00001" class="form-control" name="b2_katsayisi"
                                            value="0.25000">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">c (Makine-Ekp) Katsayısı</label>
                                        <input type="number" step="0.00001" class="form-control" name="c_katsayisi"
                                            value="0.25000">
                                    </div>
                                </div>

                                <h6 class="mt-4 mb-3 text-danger">Kesinti Oranları</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">KDV Oranı (%)</label>
                                        <input type="number" step="0.01" class="form-control" name="kdv_orani"
                                            value="20.00">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tevkifat Oranı (Örn: 4/10)</label>
                                        <input type="text" class="form-control" name="tevkifat_orani" value="4/10">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="mb-3 text-success">Temel (Sözleşme Ayı) Endeksleri (o)</h6>
                                <div class="mb-3">
                                    <label class="form-label">İşçilik (Asgari Ücret) - Io</label>
                                    <input type="number" step="0.01" class="form-control" name="asgari_ucret_temel"
                                        placeholder="Örn: 26005.50">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Motorin Endeksi - Mo</label>
                                    <input type="number" step="0.00001" class="form-control" name="motorin_temel"
                                        placeholder="Örn: 54.13308">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Yİ-ÜFE Genel Endeksi - ÜFEo</label>
                                    <input type="number" step="0.01" class="form-control" name="ufe_genel_temel"
                                        placeholder="Örn: 4632.89">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Makine-Ekipman Endeksi - Eo</label>
                                    <input type="number" step="0.01" class="form-control" name="makine_ekipman_temel"
                                        placeholder="Örn: 3319.76">
                                </div>
                            </div>
                        </div>
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