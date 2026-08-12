<?php

require_once 'vendor/autoload.php';

use App\Helper\Security;
use App\Model\SettingsModel;
use App\Service\Gate;

if (!Gate::isSuperAdmin()) {
    Gate::authorizeOrDie('superadmin', 'API İstemcisi yalnızca Superadmin kullanıcı tarafından kullanılabilir.');
}

$settings = (new SettingsModel())->getAllSettingsAsKeyValue();
$presets = [
    'endeks' => [
        'label' => 'Endeks Okuma',
        'url' => $settings['api_endeks_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_okuma_secure.php?action=getData',
        'body' => ['start_date' => date('d/m/Y'), 'end_date' => date('d/m/Y'), 'ilk_firma' => 17, 'son_firma' => 17, 'limit' => 100, 'offset' => 0],
    ],
    'kesme_acma' => [
        'label' => 'Kesme / Açma',
        'url' => $settings['api_puantaj_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_isemri_secure.php?action=getIsEmri',
        'body' => ['start_date' => date('d/m/Y'), 'end_date' => date('d/m/Y'), 'ilk_firma' => 17, 'son_firma' => 17, 'limit' => 100, 'offset' => 0],
    ],
    'ozel' => ['label' => 'Özel İstek', 'url' => '', 'body' => new stdClass()],
];

$maintitle = 'İş Takip';
$title = 'API İstemcisi';
?>

<div class="container-fluid api-client-page">
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card request-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="status-filter-group" role="group" aria-label="Hazır endpointler">
                            <?php foreach ($presets as $key => $preset): ?>
                                <input class="btn-check preset-radio" type="radio" name="preset" id="preset-<?= Security::escape($key) ?>" value="<?= Security::escape($key) ?>" <?= $key === 'endeks' ? 'checked' : '' ?>>
                                <label class="btn" for="preset-<?= Security::escape($key) ?>"><?= Security::escape($preset['label']) ?></label>
                            <?php endforeach; ?>
                        </div>
                        <span class="badge bg-danger-subtle text-danger"><i class="bx bx-lock-alt me-1"></i>Yalnızca Superadmin</span>
                    </div>

                    <form id="apiRequestForm" autocomplete="off">
                        <input type="hidden" id="csrfToken" value="<?= Security::escape(Security::csrf()) ?>">
                        <div class="request-line mb-3">
                            <div class="method-switch" aria-label="HTTP metodu">
                                <input class="btn-check" type="radio" name="request_method" id="methodGet" value="GET">
                                <label class="btn" for="methodGet">GET</label>
                                <input class="btn-check" type="radio" name="request_method" id="methodPost" value="POST" checked>
                                <label class="btn" for="methodPost">POST</label>
                            </div>
                            <input type="url" class="form-control" id="requestUrl" placeholder="https://api.example.com/endpoint" required>
                            <button class="btn btn-primary px-4" type="submit" id="sendRequest"><i class="bx bx-send me-1"></i>Gönder</button>
                        </div>

                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requestBody" type="button">Body</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#requestHeaders" type="button">Headers</button></li>
                        </ul>
                        <div class="tab-content border border-top-0 rounded-bottom p-3">
                            <div class="tab-pane fade show active" id="requestBody">
                                <div class="d-flex justify-content-between mb-2"><label class="form-label mb-0">JSON Body</label><button type="button" class="btn btn-link btn-sm p-0" id="formatJson">JSON biçimlendir</button></div>
                                <textarea class="form-control code-editor" id="bodyEditor" spellcheck="false" rows="15"></textarea>
                            </div>
                            <div class="tab-pane fade" id="requestHeaders">
                                <div class="alert alert-info py-2 small"><i class="bx bx-info-circle me-1"></i>Hazır endpoint seçildiğinde Bearer anahtarı sunucuda güvenli biçimde eklenir.</div>
                                <label class="form-label">Her satıra bir header</label>
                                <textarea class="form-control code-editor" id="headersEditor" spellcheck="false" rows="12" placeholder="Accept: application/json&#10;X-Custom-Header: value">Accept: application/json</textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card response-card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bx bx-code-curly me-1"></i>Yanıt</h5>
                        <div class="d-flex align-items-center gap-2">
                            <span id="responseStatus" class="badge bg-secondary">Bekliyor</span>
                            <span id="responseTime" class="text-muted small"></span>
                            <button type="button" class="btn btn-sm btn-light" id="copyResponse" title="Yanıtı kopyala"><i class="bx bx-copy"></i></button>
                        </div>
                    </div>
                    <div id="responseEmpty" class="response-empty flex-grow-1"><i class="bx bx-paper-plane"></i><p>Bir istek gönderdiğinizde yanıt burada görünecek.</p></div>
                    <pre id="responseOutput" class="response-output d-none flex-grow-1"><code></code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.api-client-page .card{border:1px solid #e5e7eb;box-shadow:0 4px 18px rgba(15,23,42,.05)}
.status-filter-group{background:#f8fafc;padding:4px;border-radius:50px;border:1px solid #e2e8f0;display:inline-flex;gap:2px}
.status-filter-group .btn-check+.btn{border:0;border-radius:50px;font-size:.78rem;font-weight:600;padding:7px 15px;color:#64748b}
.status-filter-group .btn-check:checked+.btn{background:#556ee6;color:#fff;box-shadow:0 3px 8px rgba(85,110,230,.25)}
.request-line{display:grid;grid-template-columns:130px minmax(0,1fr) auto;gap:8px}.method-switch{display:flex;border:1px solid #ced4da;border-radius:.25rem;overflow:hidden}.method-switch .btn{display:flex;align-items:center;justify-content:center;width:50%;border:0;border-radius:0;font-size:.75rem;font-weight:700;color:#64748b}.method-switch #methodGet:checked+.btn{background:#dcfce7;color:#15803d}.method-switch #methodPost:checked+.btn{background:#ffedd5;color:#c2410c}
.code-editor,.response-output{font:13px/1.55 SFMono-Regular,Consolas,"Liberation Mono",monospace}
.code-editor{resize:vertical;background:#fbfdff}.response-output{margin:0;background:#111827;color:#d1fae5;border-radius:8px;padding:16px;min-height:505px;max-height:68vh;overflow:auto;white-space:pre-wrap;word-break:break-word}
.response-empty{min-height:505px;border:2px dashed #e2e8f0;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#94a3b8}.response-empty i{font-size:48px;margin-bottom:10px}.response-empty p{max-width:280px}
[data-bs-theme="dark"] .api-client-page .card{border-color:#32394e}[data-bs-theme="dark"] .status-filter-group,[data-bs-theme="dark"] .code-editor{background:#2a3042;border-color:#32394e;color:#dbe4ee}
@media(max-width:767px){.request-line{grid-template-columns:90px 1fr}.request-line .btn{grid-column:1/-1}.response-output,.response-empty{min-height:340px}}
</style>

<script>
(function(){
    const presets = <?= json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const form = document.getElementById('apiRequestForm');
    const url = document.getElementById('requestUrl');
    const body = document.getElementById('bodyEditor');
    const headers = document.getElementById('headersEditor');
    const output = document.querySelector('#responseOutput code');

    function selectedPreset(){ return document.querySelector('.preset-radio:checked').value; }
    function requestMethod(){ return document.querySelector('input[name="request_method"]:checked').value; }
    function syncMethod(){ body.disabled=requestMethod()==='GET'; }
    function loadPreset(key){ const p=presets[key]; url.value=p.url; body.value=JSON.stringify(p.body,null,2); document.getElementById(key==='ozel'?'methodGet':'methodPost').checked=true; syncMethod(); }
    document.querySelectorAll('.preset-radio').forEach(el=>el.addEventListener('change',()=>loadPreset(el.value)));
    document.querySelectorAll('input[name="request_method"]').forEach(el=>el.addEventListener('change',syncMethod));
    document.getElementById('formatJson').addEventListener('click',()=>{ try{body.value=JSON.stringify(JSON.parse(body.value||'{}'),null,2)}catch(e){ if(window.Swal) Swal.fire('Geçersiz JSON',e.message,'warning'); } });

    form.addEventListener('submit',async function(e){
        e.preventDefault(); const button=document.getElementById('sendRequest');
        button.disabled=true; button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Gönderiliyor';
        document.getElementById('responseEmpty').classList.add('d-none'); document.getElementById('responseOutput').classList.remove('d-none'); output.textContent='İstek gönderiliyor…';
        const payload={csrf_token:document.getElementById('csrfToken').value,preset:selectedPreset(),method:requestMethod(),url:url.value.trim(),headers:headers.value,body:body.value};
        try{
            const response=await fetch('views/api-istemcisi/api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
            const result=await response.json();
            if(!response.ok||result.status==='error') throw new Error(result.message||'İstek başarısız.');
            const badge=document.getElementById('responseStatus'); badge.textContent=result.http_code; badge.className='badge '+(result.http_code<300?'bg-success':result.http_code<400?'bg-info':'bg-danger');
            document.getElementById('responseTime').textContent=result.duration_ms+' ms · '+result.size_label;
            output.textContent=result.pretty_body||result.body||'(Boş yanıt)';
        }catch(err){document.getElementById('responseStatus').textContent='Hata';document.getElementById('responseStatus').className='badge bg-danger';output.textContent=err.message;}
        finally{button.disabled=false;button.innerHTML='<i class="bx bx-send me-1"></i>Gönder';}
    });
    document.getElementById('copyResponse').addEventListener('click',()=>navigator.clipboard.writeText(output.textContent||''));
    loadPreset('endeks');
})();
</script>
