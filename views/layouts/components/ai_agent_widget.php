<?php
/**
 * Yapay Zeka İş Ajanı (AI Work Agent) Accordion Widget Komponenti
 */
?>
<style>
.ai-agent-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
    border: 1px solid #334155 !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3) !important;
    border-radius: 12px !important;
}

.ai-agent-header {
    cursor: pointer !important;
    user-select: none !important;
    transition: background-color 0.2s ease !important;
}

.ai-agent-header:hover {
    background: rgba(255, 255, 255, 0.04) !important;
}

.ai-agent-title {
    color: #ffffff !important;
    font-weight: 700 !important;
    font-size: 15px !important;
}

.ai-agent-subtitle {
    color: #cbd5e1 !important;
    font-size: 13px !important;
}

.ai-chevron-icon {
    transition: transform 0.3s ease !important;
    color: #94a3b8 !important;
}

.ai-agent-header[aria-expanded="true"] .ai-chevron-icon {
    transform: rotate(180deg) !important;
    color: #60a5fa !important;
}

.ai-chip-btn {
    background-color: rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    font-weight: 500 !important;
    font-size: 13px !important;
    padding: 6px 14px !important;
    transition: all 0.2s ease-in-out !important;
}

.ai-chip-btn:hover {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #60a5fa !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4) !important;
}

#aiPromptInput {
    background-color: #020617 !important;
    color: #ffffff !important;
    border: 1px solid #334155 !important;
    font-size: 14px !important;
    border-radius: 8px 0 0 8px !important;
}

#aiPromptInput::placeholder {
    color: #94a3b8 !important;
    opacity: 1 !important;
}

#aiPromptInput:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important;
}

.ai-submit-btn {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border: none !important;
    border-radius: 0 8px 8px 0 !important;
    transition: all 0.2s ease !important;
}

.ai-submit-btn:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.5) !important;
}

.ai-result-box {
    background-color: #020617 !important;
    border: 1px solid #334155 !important;
    border-radius: 8px !important;
    color: #f1f5f9 !important;
}

.ai-result-box h3, .ai-result-box h4, .ai-result-box h5 {
    color: #60a5fa !important;
    font-weight: 700 !important;
}

.ai-result-box strong {
    color: #ffffff !important;
}

.ai-result-box li {
    color: #e2e8f0 !important;
    margin-bottom: 4px;
}
</style>

<div class="card mb-4 ai-agent-card">
    <!-- Accordion Başlığı (Varsayılan olarak kapalıdır, tıklandığında açılır) -->
    <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between py-3 ai-agent-header" 
         data-bs-toggle="collapse" 
         data-bs-target="#aiAgentCollapse" 
         aria-expanded="false" 
         aria-controls="aiAgentCollapse">
        <div class="d-flex align-items-center gap-2">
            <div class="avatar-xs d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="background: #2563eb; color: #ffffff; width: 38px; height: 38px; font-size: 20px;">
                <i class="bx bx-bot"></i>
            </div>
            <div>
                <h5 class="mb-0 ai-agent-title d-flex align-items-center gap-2">
                    🤖 Yapay Zeka İş Ajanı
                    <span class="badge bg-primary text-white border border-primary px-2 py-1 font-size-11 rounded-pill">Tıklayın</span>
                </h5>
                <span class="ai-agent-subtitle">Filo, sürücü davranışı, servis ve ikame araç akıllı analiz sistemi</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <i class="bx bx-chevron-down font-size-22 ai-chevron-icon"></i>
        </div>
    </div>
    
    <!-- Accordion Gövdesi (Varsayılan Kapalı) -->
    <div id="aiAgentCollapse" class="collapse">
        <div class="card-body p-3 pt-0 border-top border-secondary border-opacity-25 mt-2">
            <!-- Hızlı Öneri Chip Butonları -->
            <div class="d-flex flex-wrap gap-2 mb-3 mt-3">
                <button type="button" class="btn rounded-pill ai-chip-btn" data-prompt="Musa Çiftçi araç analiz ve servis raporu nedir?">
                    🚗 Musa Çiftçi Sürücü Raporu
                </button>
                <button type="button" class="btn rounded-pill ai-chip-btn" data-prompt="En çok arıza yapan ve servise giden araçlar kimlerin zimmetinde?">
                    🔧 En Çok Arıza Yapan Araçlar
                </button>
                <button type="button" class="btn rounded-pill ai-chip-btn" data-prompt="İkame araç verilen ve ikame araçta da sorun yaşanan durumları listeleyin.">
                    🚨 İkame Araç Problemleri
                </button>
                <button type="button" class="btn rounded-pill ai-chip-btn" data-prompt="Genel filo durumu ve servis maliyet özet analizi yap.">
                    📊 Filo & Maliyet Özeti
                </button>
            </div>

            <!-- Prompt Girdi Formu -->
            <form id="aiAgentForm" class="position-relative">
                <div class="input-group">
                    <textarea id="aiPromptInput" class="form-control shadow-none" 
                              rows="2" 
                              placeholder="Sürücü, araç veya filonuz hakkında soru yazın (Örn: Musa Çiftçi'nin aracı kaç kez servise gitti? ikame araç durumu ne?)..."></textarea>
                    <button type="submit" id="aiSubmitBtn" class="btn ai-submit-btn px-4 d-flex align-items-center gap-2">
                        <i class="bx bx-send font-size-18"></i>
                        <span>Analiz Et</span>
                    </button>
                </div>
            </form>

            <!-- AI Yanıt Alanı (Yüklenme & Sonuç) -->
            <div id="aiResponseContainer" class="mt-3 p-3 ai-result-box d-none">
                <div id="aiLoadingSpinner" class="d-none text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Analiz Yapılıyor...</span>
                    </div>
                    <p class="mt-2 mb-0 font-size-14 font-weight-medium" style="color: #94a3b8;">Veritabanı kayıtları inceleniyor ve AI İş Ajanı analiz raporu üretiliyor...</p>
                </div>
                
                <div id="aiResultContent" class="markdown-body font-size-14" style="line-height: 1.7;"></div>
            </div>
        </div>
    </div>
</div>
