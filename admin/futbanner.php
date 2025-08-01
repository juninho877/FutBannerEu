<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

$pageTitle = isset($_GET['banner']) ? "Gerador de Banner" : "Selecionar Modelo de Banner";
include "includes/header.php";
// Incluir funções necessárias
require_once 'includes/banner_functions.php';

$jogos = obterJogosDeHoje();
$jogosPorBanner = 5;
$gruposDeJogos = array_chunk(array_keys($jogos), $jogosPorBanner);

// Verificar se o usuário tem configurações do Telegram
require_once 'classes/TelegramSettings.php';
$telegramSettings = new TelegramSettings();
$userId = $_SESSION['user_id'];
$hasTelegramSettings = $telegramSettings->hasSettings($userId);

if (isset($_GET['banner'])) {
    // Tela de visualização dos banners
    $tipo_banner = $_GET['banner'];
    $geradorScript = '';

    switch ($tipo_banner) {
        case '1': $geradorScript = 'gerar_fut.php'; break;
        case '2': $geradorScript = 'gerar_fut_2.php'; break;
        case '3': $geradorScript = 'gerar_fut_3.php'; break;
        //case '4': $geradorScript = 'gerar_fut_4.php'; break;
        default:
            echo "<div class='card'><div class='card-body text-center'><p class='text-danger'>Tipo de banner inválido!</p></div></div>";
            include "includes/footer.php";
            exit();
    }
?>

<!-- Modal de Progresso -->
<div id="progressModal" class="progress-modal">
    <div class="progress-modal-content">
        <div class="progress-header">
            <h3 class="progress-title">
                <i class="fas fa-magic"></i>
                Gerando Banners
            </h3>
            <p class="progress-subtitle">Aguarde enquanto criamos seus banners...</p>
        </div>
        
        <div class="progress-body">
            <div class="progress-bar-container">
                <div class="progress-bar">
                    <div id="progressBarFill" class="progress-bar-fill"></div>
                </div>
                <div class="progress-text">
                    <span id="progressPercent">0%</span>
                    <span id="progressStatus">Iniciando...</span>
                </div>
            </div>
            
            <div class="banners-status">
                <?php foreach ($gruposDeJogos as $index => $grupo): ?>
                    <div id="banner-status-<?php echo $index; ?>" class="banner-status-item">
                        <div class="status-icon">
                            <i class="fas fa-clock text-muted"></i>
                        </div>
                        <span class="status-text">Banner Parte <?php echo $index + 1; ?></span>
                        <div class="status-indicator">
                            <div class="status-spinner" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-futbol text-primary-500 mr-3"></i>
        Banners de Jogos de Hoje
    </h1>
    <p class="page-subtitle">Modelo <?php echo $tipo_banner; ?> - <?php echo count($jogos); ?> jogos disponíveis</p>
</div>

<div class="banner-actions mb-6">
    <a href="<?php echo basename(__FILE__); ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Voltar para Seleção
    </a>
    <?php if (!empty($jogos)): ?>
        <a href="<?php echo $geradorScript; ?>?download_all=1" class="btn btn-success" target="_blank">
            <i class="fas fa-download"></i>
            Baixar Todos (ZIP)
        </a>
        <?php if ($hasTelegramSettings): ?>
        <button id="sendTelegramBtn" class="btn btn-primary" data-banner-type="football_<?php echo $tipo_banner; ?>">
            <i class="fab fa-telegram"></i>
            Enviar para Telegram
        </button>
        <?php else: ?>
        <a href="telegram.php" class="btn btn-primary">
            <i class="fab fa-telegram"></i>
            Configurar Telegram
        </a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (empty($jogos)): ?>
    <div class="card">
        <div class="card-body text-center py-12">
            <div class="mb-4">
                <i class="fas fa-futbol text-6xl text-gray-300"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2">Nenhum jogo disponível</h3>
            <p class="text-muted">Não há jogos programados para hoje no momento.</p>
        </div>
    </div>
<?php else: ?>
    <div class="banners-grid">
        <?php foreach ($gruposDeJogos as $index => $grupo): ?>
            <div class="banner-card">
                <div class="banner-card-header">
                    <div class="banner-info">
                        <h3 class="banner-title">Banner Parte <?php echo $index + 1; ?></h3>
                        <p class="banner-subtitle"><?php echo count($grupo); ?> jogos</p>
                    </div>
                    <div class="banner-status" id="status-<?php echo $index; ?>">
                        <div class="status-loading">
                            <i class="fas fa-clock text-muted"></i>
                        </div>
                    </div>
                </div>
                
                <div class="banner-preview-container">
                    <img id="banner-img-<?php echo $index; ?>" 
                         src="" 
                         alt="Banner Parte <?php echo $index + 1; ?>" 
                         class="banner-preview-image"
                         data-grupo="<?php echo $index; ?>"
                         data-script="<?php echo $geradorScript; ?>"
                         style="display: none;">
                    
                    <div id="loading-<?php echo $index; ?>" class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p class="loading-text">Carregando banner...</p>
                        <div class="loading-progress">
                            <div class="loading-bar">
                                <div class="loading-bar-fill"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="error-<?php echo $index; ?>" class="error-placeholder" style="display: none;">
                        <i class="fas fa-exclamation-triangle text-4xl text-danger-500 mb-3"></i>
                        <p class="error-text">Erro ao carregar banner</p>
                        <button class="btn btn-secondary btn-sm mt-3" onclick="retryBanner(<?php echo $index; ?>)">
                            <i class="fas fa-redo"></i> Tentar Novamente
                        </button>
                    </div>
                </div>
                
                <div class="banner-actions">
                    <a href="<?php echo $geradorScript; ?>?grupo=<?php echo $index; ?>&download=1" 
                       class="btn btn-primary w-full" target="_blank">
                        <i class="fas fa-download"></i>
                        Baixar Banner
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
    /* Layout Principal dos Banners */
    .banners-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Responsivo: 2 colunas em telas maiores */
    @media (min-width: 992px) {
        .banners-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 2.5rem;
        }
    }

    /* Card do Banner */
    .banner-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .banner-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    /* Header do Card */
    .banner-card-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg-secondary);
    }

    .banner-info {
        flex: 1;
    }

    .banner-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .banner-title::before {
        content: '';
        width: 8px;
        height: 8px;
        background: var(--primary-500);
        border-radius: 50%;
    }

    .banner-subtitle {
        color: var(--text-secondary);
        font-size: 0.875rem;
        margin: 0;
    }

    .banner-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-loading i {
        color: var(--text-muted);
        animation: pulse 2s infinite;
    }

    .status-success i {
        color: var(--success-500);
    }

    .status-error i {
        color: var(--danger-500);
    }

    /* Container da Prévia - DIMENSÕES AUMENTADAS */
    .banner-preview-container {
        position: relative;
        width: 100%;
        height: 450px; /* Aumentado de 300px para 450px */
        background: var(--bg-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    /* Responsivo para diferentes tamanhos */
    @media (min-width: 1200px) {
        .banner-preview-container {
            height: 500px; /* Ainda maior em telas grandes */
        }
    }

    @media (max-width: 991px) {
        .banner-preview-container {
            height: 400px; /* Menor em tablets */
        }
    }

    @media (max-width: 768px) {
        .banner-preview-container {
            height: 350px; /* Menor em mobile */
        }
    }

    @media (max-width: 480px) {
        .banner-preview-container {
            height: 300px; /* Mínimo em mobile pequeno */
        }
    }

    .banner-preview-image {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: opacity 0.3s ease;
        background: var(--bg-secondary);
    }

    /* Estados de Loading e Error */
    .loading-placeholder,
    .error-placeholder {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--text-muted);
        padding: 2rem;
        background: var(--bg-secondary);
    }

    .loading-spinner {
        width: 48px;
        height: 48px;
        border: 4px solid var(--border-color);
        border-top: 4px solid var(--primary-500);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1.5rem;
    }

    .loading-text,
    .error-text {
        font-size: 0.875rem;
        color: var(--text-muted);
        margin: 0 0 1rem 0;
        font-weight: 500;
    }

    .loading-progress {
        width: 100%;
        max-width: 200px;
        margin-top: 1rem;
    }

    .loading-bar {
        width: 100%;
        height: 4px;
        background: var(--bg-tertiary);
        border-radius: 2px;
        overflow: hidden;
    }

    .loading-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
        border-radius: 2px;
        width: 0%;
        animation: loadingProgress 3s ease-in-out infinite;
    }

    @keyframes loadingProgress {
        0%, 100% { width: 0%; }
        50% { width: 100%; }
    }

    /* Ações do Banner */
    .banner-actions {
        padding: 1.5rem;
        background: var(--bg-primary);
        margin-top: auto;
    }

    .banner-actions .btn {
        font-weight: 600;
        padding: 0.875rem 1.5rem;
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }

    .banner-actions .btn:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    /* Ações Principais */
    .banner-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 2rem;
    }

    /* Modal de Progresso */
    .progress-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .progress-modal.active {
        opacity: 1;
        visibility: visible;
    }

    .progress-modal-content {
        background: var(--bg-primary);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-xl);
        border: 1px solid var(--border-color);
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .progress-header {
        padding: 2rem 2rem 1rem;
        text-align: center;
        border-bottom: 1px solid var(--border-color);
    }

    .progress-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .progress-title i {
        color: var(--primary-500);
    }

    .progress-subtitle {
        color: var(--text-secondary);
        font-size: 0.875rem;
    }

    .progress-body {
        padding: 2rem;
    }

    .progress-bar-container {
        margin-bottom: 2rem;
    }

    .progress-bar {
        width: 100%;
        height: 12px;
        background: var(--bg-tertiary);
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 1rem;
        position: relative;
    }

    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
        border-radius: 6px;
        width: 0%;
        transition: width 0.5s ease;
        position: relative;
        overflow: hidden;
    }

    .progress-bar-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    .progress-text {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
    }

    #progressPercent {
        font-weight: 600;
        color: var(--primary-500);
        font-size: 1rem;
    }

    #progressStatus {
        color: var(--text-secondary);
    }

    .banners-status {
        space-y: 0.75rem;
    }

    .banner-status-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius-sm);
        transition: var(--transition);
    }

    .banner-status-item.loading {
        background: var(--primary-50);
        border-left: 3px solid var(--primary-500);
    }

    .banner-status-item.success {
        background: var(--success-50);
        border-left: 3px solid var(--success-500);
    }

    .banner-status-item.error {
        background: var(--danger-50);
        border-left: 3px solid var(--danger-500);
    }

    [data-theme="dark"] .banner-status-item.loading {
        background: rgba(59, 130, 246, 0.1);
    }

    [data-theme="dark"] .banner-status-item.success {
        background: rgba(34, 197, 94, 0.1);
    }

    [data-theme="dark"] .banner-status-item.error {
        background: rgba(239, 68, 68, 0.1);
    }

    .status-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .status-text {
        flex: 1;
        font-weight: 500;
        color: var(--text-primary);
    }

    .status-indicator {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .status-spinner {
        color: var(--primary-500);
    }

    /* Animações */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Utilitários */
    .mr-3 {
        margin-right: 0.75rem;
    }

    .mb-3 {
        margin-bottom: 0.75rem;
    }

    .mb-6 {
        margin-bottom: 1.5rem;
    }

    .mt-3 {
        margin-top: 0.75rem;
    }

    .w-full {
        width: 100%;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }

    .py-12 {
        padding-top: 3rem;
        padding-bottom: 3rem;
    }

    .text-6xl {
        font-size: 3.75rem;
        line-height: 1;
    }

    .text-xl {
        font-size: 1.25rem;
        line-height: 1.75rem;
    }

    .text-4xl {
        font-size: 2.25rem;
        line-height: 2.5rem;
    }

    .font-semibold {
        font-weight: 600;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    .mb-4 {
        margin-bottom: 1rem;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .text-gray-300 {
        color: var(--text-muted);
    }

    [data-theme="dark"] .text-danger-500 {
        color: #ef4444;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let retryCount = {};
const maxRetries = 3;
let totalBanners = 0;
let loadedBanners = 0;
let failedBanners = 0;
let loadingAborted = false;
let activeTimeouts = [];
let activeImages = [];

function showProgressModal() {
    const modal = document.getElementById('progressModal');
    modal.classList.add('active');
}

function hideProgressModal() {
    const modal = document.getElementById('progressModal');
    modal.classList.remove('active');
}

function updateProgress() {
    const percent = Math.round((loadedBanners / totalBanners) * 100);
    const progressBar = document.getElementById('progressBarFill');
    const progressPercent = document.getElementById('progressPercent');
    const progressStatus = document.getElementById('progressStatus');
    
    progressBar.style.width = percent + '%';
    progressPercent.textContent = percent + '%';
    
    if (loadedBanners === totalBanners) {
        progressStatus.textContent = 'Concluído!';
        setTimeout(() => {
            hideProgressModal();
        }, 1500);
    } else {
        progressStatus.textContent = `${loadedBanners}/${totalBanners} banners carregados`;
    }
}

function updateBannerStatus(index, status) {
    const statusItem = document.getElementById(`banner-status-${index}`);
    const statusIcon = statusItem.querySelector('.status-icon i');
    const statusSpinner = statusItem.querySelector('.status-spinner');
    const cardStatus = document.getElementById(`status-${index}`);
    
    // Remove todas as classes de status
    statusItem.classList.remove('loading', 'success', 'error');
    cardStatus.className = 'banner-status';
    
    switch (status) {
        case 'loading':
            statusItem.classList.add('loading');
            cardStatus.classList.add('status-loading');
            statusIcon.className = 'fas fa-clock text-primary-500';
            if (statusSpinner) statusSpinner.style.display = 'block';
            cardStatus.innerHTML = '<div class="status-loading"><i class="fas fa-spinner fa-spin text-primary-500"></i></div>';
            break;
        case 'success':
            statusItem.classList.add('success');
            cardStatus.classList.add('status-success');
            statusIcon.className = 'fas fa-check-circle text-success-500';
            if (statusSpinner) statusSpinner.style.display = 'none';
            cardStatus.innerHTML = '<div class="status-success"><i class="fas fa-check-circle text-success-500"></i></div>';
            break;
        case 'error':
            statusItem.classList.add('error');
            cardStatus.classList.add('status-error');
            statusIcon.className = 'fas fa-times-circle text-danger-500';
            if (statusSpinner) statusSpinner.style.display = 'none';
            cardStatus.innerHTML = '<div class="status-error"><i class="fas fa-times-circle text-danger-500"></i></div>';
            break;
    }
}

function abortAllOperations() {
    loadingAborted = true;
    console.log('🛑 Abortando todas as operações de carregamento...');
    
    // Limpar todos os timeouts ativos
    activeTimeouts.forEach(timeout => {
        clearTimeout(timeout);
    });
    activeTimeouts = [];
    
    // Abortar carregamento de todas as imagens ativas
    activeImages.forEach(img => {
        if (img && img.src) {
            img.onload = null;
            img.onerror = null;
            img.src = '';
        }
    });
    activeImages = [];
    
    // Fechar modal se estiver aberto
    hideProgressModal();
    
    console.log('✅ Todas as operações foram abortadas');
}

function loadBanner(index, script) {
    if (loadingAborted) return;
    
    const img = document.getElementById(`banner-img-${index}`);
    const loading = document.getElementById(`loading-${index}`);
    const error = document.getElementById(`error-${index}`);
    
    if (!img || !loading || !error) return;
    
    // Adicionar à lista de imagens ativas
    activeImages.push(img);
    
    // Atualizar status no modal e card
    updateBannerStatus(index, 'loading');
    
    // Reset estado
    img.style.display = 'none';
    loading.style.display = 'flex';
    error.style.display = 'none';
    
    // Criar URL com cache busting
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(7);
    const url = `${script}?grupo=${index}&_t=${timestamp}&_r=${random}`;
    
    console.log(`🔄 Carregando banner ${index}: ${url}`);
    
    // Timeout aumentado para 60 segundos
    const timeout = setTimeout(() => {
        if (loadingAborted) return;
        console.log(`⏰ Timeout para banner ${index} após 60 segundos`);
        showError(index, 'Timeout ao carregar banner');
        updateBannerStatus(index, 'error');
        failedBanners++;
        loadedBanners++;
        updateProgress();
    }, 60000); // 60 segundos
    
    // Adicionar à lista de timeouts ativos
    activeTimeouts.push(timeout);
    
    img.onload = function() {
        if (loadingAborted) return;
        
        // Remover timeout da lista ativa
        const timeoutIndex = activeTimeouts.indexOf(timeout);
        if (timeoutIndex > -1) {
            clearTimeout(timeout);
            activeTimeouts.splice(timeoutIndex, 1);
        }
        
        console.log(`✅ Banner ${index} carregado com sucesso`);
        
        // Verificar se a imagem realmente carregou
        if (this.naturalWidth === 0 || this.naturalHeight === 0) {
            console.log(`❌ Banner ${index} carregou mas tem dimensões inválidas`);
            showError(index, 'Imagem inválida');
            updateBannerStatus(index, 'error');
            failedBanners++;
        } else {
            // Mostrar imagem
            this.style.display = 'block';
            loading.style.display = 'none';
            error.style.display = 'none';
            updateBannerStatus(index, 'success');
            
            // Reset retry count
            retryCount[index] = 0;
        }
        
        loadedBanners++;
        updateProgress();
    };
    
    img.onerror = function() {
        if (loadingAborted) return;
        
        // Remover timeout da lista ativa
        const timeoutIndex = activeTimeouts.indexOf(timeout);
        if (timeoutIndex > -1) {
            clearTimeout(timeout);
            activeTimeouts.splice(timeoutIndex, 1);
        }
        
        console.log(`❌ Erro ao carregar banner ${index}`);
        showError(index, 'Erro ao carregar imagem');
        updateBannerStatus(index, 'error');
        failedBanners++;
        loadedBanners++;
        updateProgress();
    };
    
    // Iniciar carregamento
    img.src = url;
}

function showError(index, message) {
    const img = document.getElementById(`banner-img-${index}`);
    const loading = document.getElementById(`loading-${index}`);
    const error = document.getElementById(`error-${index}`);
    
    if (img) img.style.display = 'none';
    if (loading) loading.style.display = 'none';
    if (error) {
        error.style.display = 'flex';
        const errorText = error.querySelector('.error-text');
        if (errorText) {
            errorText.textContent = `${message} (Tentativa ${retryCount[index] || 0}/${maxRetries})`;
        }
    }
}

function retryBanner(index) {
    retryCount[index] = (retryCount[index] || 0) + 1;
    
    if (retryCount[index] > maxRetries) {
        showError(index, 'Máximo de tentativas excedido');
        updateBannerStatus(index, 'error');
        return;
    }
    
    const img = document.getElementById(`banner-img-${index}`);
    const script = img.getAttribute('data-script');
    
    console.log(`🔄 Tentativa ${retryCount[index]} para banner ${index}`);
    
    // Delay progressivo (mais tempo entre tentativas)
    const delay = retryCount[index] * 2000; // 2, 4, 6 segundos
    setTimeout(() => {
        if (loadingAborted) return;
        // Decrementar loadedBanners para reprocessar
        loadedBanners--;
        loadBanner(index, script);
    }, delay);
}

// Interceptar navegação e abortar operações
function setupNavigationInterception() {
    // Interceptar cliques em links
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a');
        if (target && target.href && !target.target) {
            console.log('🔗 Navegação detectada, abortando operações...');
            abortAllOperations();
        }
    });
    
    // Interceptar mudanças de página via JavaScript
    const originalPushState = history.pushState;
    const originalReplaceState = history.replaceState;
    
    history.pushState = function() {
        console.log('📍 PushState detectado, abortando operações...');
        abortAllOperations();
        return originalPushState.apply(history, arguments);
    };
    
    history.replaceState = function() {
        console.log('📍 ReplaceState detectado, abortando operações...');
        abortAllOperations();
        return originalReplaceState.apply(history, arguments);
    };
    
    // Interceptar evento beforeunload
    window.addEventListener('beforeunload', function() {
        console.log('🚪 Página sendo fechada, abortando operações...');
        abortAllOperations();
    });
    
    // Interceptar evento pagehide
    window.addEventListener('pagehide', function() {
        console.log('👋 Página sendo escondida, abortando operações...');
        abortAllOperations();
    });
}

// Carregar banners quando a página estiver pronta
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando carregamento dos banners...');
    
    // Configurar interceptação de navegação
    setupNavigationInterception();
    
    <?php if (!empty($gruposDeJogos)): ?>
        const banners = [
            <?php foreach ($gruposDeJogos as $index => $grupo): ?>
                {index: <?php echo $index; ?>, script: '<?php echo $geradorScript; ?>'},
            <?php endforeach; ?>
        ];
        
        totalBanners = banners.length;
        loadedBanners = 0;
        failedBanners = 0;
        loadingAborted = false;
        
        // Mostrar modal de progresso
        showProgressModal();
        
        // Carregar banners com delay escalonado (mais tempo entre cada um)
        banners.forEach((banner, i) => {
            const delay = i * 2000; // 2 segundos entre cada banner
            const timeoutId = setTimeout(() => {
                if (!loadingAborted) {
                    loadBanner(banner.index, banner.script);
                }
            }, delay);
            
            // Adicionar à lista de timeouts ativos
            activeTimeouts.push(timeoutId);
        });
    <?php endif; ?>
    
    // Configurar botão de envio para Telegram
    const sendTelegramBtn = document.getElementById('sendTelegramBtn');
    if (sendTelegramBtn) {
        sendTelegramBtn.addEventListener('click', function() {
            const bannerType = this.getAttribute('data-banner-type');
            
            Swal.fire({
                title: 'Enviar para Telegram?',
                text: 'Todos os banners serão enviados para o seu chat configurado no Telegram.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, enviar',
                cancelButtonText: 'Cancelar',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
                confirmButtonColor: '#3b82f6'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendBannersToTelegram(bannerType);
                }
            });
        });
    }
});

// Função para enviar banners para o Telegram
function sendBannersToTelegram(bannerType) {
    Swal.fire({
        title: 'Enviando Banners',
        text: 'Aguarde enquanto enviamos os banners para o Telegram...',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
        color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('send_telegram_banners.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `banner_type=${bannerType}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Sucesso!',
                text: data.message,
                icon: 'success',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
                confirmButtonColor: '#3b82f6'
            });
        } else {
            Swal.fire({
                title: 'Erro!',
                text: data.message,
                icon: 'error',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
                confirmButtonColor: '#ef4444'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Erro!',
            text: 'Erro na comunicação com o servidor: ' + error.message,
            icon: 'error',
            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
            confirmButtonColor: '#ef4444'
        });
    });
}

// Expor função globalmente
window.retryBanner = retryBanner;
window.abortAllOperations = abortAllOperations;
</script>

<?php
} else {
    // Tela de seleção de modelo
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-futbol text-primary-500 mr-3"></i>
        Escolha o Modelo de Banner
    </h1>
    <p class="page-subtitle">Selecione o estilo que melhor se adequa às suas necessidades</p>
</div>

<?php if (empty($jogos)): ?>
    <div class="card">
        <div class="card-body text-center py-12">
            <div class="mb-4">
                <i class="fas fa-exclamation-triangle text-6xl text-warning-500"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2">Nenhum jogo disponível</h3>
            <p class="text-muted">Não há jogos programados para hoje para gerar as prévias dos banners.</p>
        </div>
    </div>
<?php else: ?>
    <div class="models-grid">
        <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="model-card group">
                <div class="model-card-header">
                    <div class="model-info">
                        <h3 class="model-title">Banner Modelo <?php echo $i; ?></h3>
                        <p class="model-subtitle">Estilo profissional e moderno</p>
                    </div>
                    <div class="model-status" id="model-status-<?php echo $i; ?>">
                        <div class="status-loading">
                            <i class="fas fa-clock text-muted"></i>
                        </div>
                    </div>
                </div>
                
                <div class="model-preview-container">
                    <img id="model-img-<?php echo $i; ?>" 
                         src="" 
                         alt="Prévia do Banner <?php echo $i; ?>" 
                         class="model-preview-image"
                         data-model="<?php echo $i; ?>"
                         style="display: none;">
                    
                    <div id="model-loading-<?php echo $i; ?>" class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p class="loading-text">Carregando prévia...</p>
                        <div class="loading-progress">
                            <div class="loading-bar">
                                <div class="loading-bar-fill"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="model-error-<?php echo $i; ?>" class="error-placeholder" style="display: none;">
                        <i class="fas fa-exclamation-triangle text-4xl text-warning-500 mb-3"></i>
                        <p class="error-text">Erro ao carregar prévia</p>
                        <button class="btn btn-secondary btn-sm mt-3" onclick="retryModel(<?php echo $i; ?>)">
                            <i class="fas fa-redo"></i> Tentar Novamente
                        </button>
                    </div>
                </div>
                
                <div class="model-actions">
                    <a href="?banner=<?php echo $i; ?>" class="btn btn-primary w-full model-use-btn" data-model="<?php echo $i; ?>">
                        <i class="fas fa-check"></i>
                        Usar este Modelo
                    </a>
                </div>
            </div>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<style>
    /* Grid de Modelos - ATÉ 4 POR LINHA */
    .models-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
        max-width: 1800px;
        margin: 0 auto;
    }

    /* Responsivo: Até 4 colunas em telas muito grandes */
    @media (min-width: 1600px) {
        .models-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }
    }

    @media (min-width: 1200px) and (max-width: 1599px) {
        .models-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }
    }

    @media (min-width: 768px) and (max-width: 1199px) {
        .models-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
    }

    /* Card do Modelo */
    .model-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .model-card:hover {
        box-shadow: var(--shadow-xl);
        transform: translateY(-4px);
        border-color: var(--primary-500);
    }

    .model-card.loading {
        pointer-events: none;
        opacity: 0.8;
    }

    /* Header do Card do Modelo */
    .model-card-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg-secondary);
    }

    .model-info {
        flex: 1;
    }

    .model-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .model-title::before {
        content: '';
        width: 10px;
        height: 10px;
        background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
        border-radius: 50%;
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
    }

    .model-subtitle {
        color: var(--text-secondary);
        font-size: 0.875rem;
        margin: 0;
        font-weight: 500;
    }

    .model-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Container da Prévia do Modelo - DIMENSÕES AUMENTADAS */
    .model-preview-container {
        position: relative;
        width: 100%;
        height: 400px; /* Aumentado significativamente */
        background: var(--bg-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-bottom: 1px solid var(--border-color);
    }

    /* Responsivo para prévias */
    @media (min-width: 1600px) {
        .model-preview-container {
            height: 450px; /* Ainda maior em telas muito grandes */
        }
    }

    @media (min-width: 1200px) and (max-width: 1599px) {
        .model-preview-container {
            height: 420px;
        }
    }

    @media (max-width: 1199px) {
        .model-preview-container {
            height: 380px;
        }
    }

    @media (max-width: 768px) {
        .model-preview-container {
            height: 320px;
        }
    }

    @media (max-width: 480px) {
        .model-preview-container {
            height: 280px;
        }
    }

    .model-preview-image {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: all 0.3s ease;
        background: var(--bg-secondary);
    }

    .model-card:hover .model-preview-image {
        transform: scale(1.02);
    }

    /* Ações do Modelo */
    .model-actions {
        padding: 1.5rem;
        background: var(--bg-primary);
        margin-top: auto;
    }

    .model-use-btn {
        font-weight: 700;
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.875rem;
    }

    .model-use-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s ease;
    }

    .model-use-btn:hover::before {
        left: 100%;
    }

    .model-use-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        background: var(--primary-600);
    }

    .model-use-btn:active {
        transform: translateY(0);
    }

    /* Estados de Loading para Modelos */
    .model-card .loading-placeholder,
    .model-card .error-placeholder {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--text-muted);
        padding: 2rem;
        background: var(--bg-secondary);
    }

    /* Animações especiais */
    @keyframes modelCardSlideIn {
        from {
            opacity: 0;
            transform: translateY(30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .model-card {
        animation: modelCardSlideIn 0.6s ease-out;
    }

    .model-card:nth-child(1) { animation-delay: 0.1s; }
    .model-card:nth-child(2) { animation-delay: 0.2s; }
    .model-card:nth-child(3) { animation-delay: 0.3s; }
    .model-card:nth-child(4) { animation-delay: 0.4s; }

    /* Dark theme adjustments */
    [data-theme="dark"] .text-warning-500 {
        color: #f59e0b;
    }

    /* Utilitários específicos para modelos */
    .group {
        position: relative;
    }

    .transition-all {
        transition: all 0.3s ease;
    }

    .duration-300 {
        transition-duration: 300ms;
    }

    .hover\:shadow-xl:hover {
        box-shadow: var(--shadow-xl);
    }

    .group-hover\:bg-primary-600:hover {
        background-color: var(--primary-600);
    }
</style>

<script>
let modelRetryCount = {};
const maxModelRetries = 2;
let modelsLoaded = 0;
let totalModels = 4;
let modelLoadingAborted = false;
let activeModelTimeouts = [];
let activeModelImages = [];

function abortModelLoading() {
    modelLoadingAborted = true;
    console.log('🛑 Abortando carregamento de prévias dos modelos...');
    
    // Limpar todos os timeouts ativos dos modelos
    activeModelTimeouts.forEach(timeout => {
        clearTimeout(timeout);
    });
    activeModelTimeouts = [];
    
    // Abortar carregamento de todas as imagens ativas dos modelos
    activeModelImages.forEach(img => {
        if (img && img.src) {
            img.onload = null;
            img.onerror = null;
            img.src = '';
        }
    });
    activeModelImages = [];
    
    console.log('✅ Carregamento de prévias abortado');
}

function loadModel(modelNumber) {
    if (modelLoadingAborted) return;
    
    const img = document.getElementById(`model-img-${modelNumber}`);
    const loading = document.getElementById(`model-loading-${modelNumber}`);
    const error = document.getElementById(`model-error-${modelNumber}`);
    const status = document.getElementById(`model-status-${modelNumber}`);
    
    if (!img || !loading || !error) return;
    
    // Adicionar à lista de imagens ativas
    activeModelImages.push(img);
    
    // Atualizar status
    status.innerHTML = '<div class="status-loading"><i class="fas fa-spinner fa-spin text-primary-500"></i></div>';
    
    // Reset estado
    img.style.display = 'none';
    loading.style.display = 'flex';
    error.style.display = 'none';
    
    // Criar URL com cache busting
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(7);
    const script = `gerar_fut${modelNumber > 1 ? '_' + modelNumber : ''}.php`;
    const url = `${script}?grupo=0&_preview=1&_t=${timestamp}&_r=${random}`;
    
    console.log(`🔄 Carregando modelo ${modelNumber}: ${url}`);
    
    // Timeout aumentado para 60 segundos (mesmo que os banners)
    const timeout = setTimeout(() => {
        if (modelLoadingAborted) return;
        console.log(`⏰ Timeout para modelo ${modelNumber} após 60 segundos`);
        showModelError(modelNumber, 'Timeout ao carregar prévia');
    }, 60000); // 60 segundos
    
    // Adicionar à lista de timeouts ativos
    activeModelTimeouts.push(timeout);
    
    img.onload = function() {
        if (modelLoadingAborted) return;
        
        // Remover timeout da lista ativa
        const timeoutIndex = activeModelTimeouts.indexOf(timeout);
        if (timeoutIndex > -1) {
            clearTimeout(timeout);
            activeModelTimeouts.splice(timeoutIndex, 1);
        }
        
        console.log(`✅ Modelo ${modelNumber} carregado com sucesso`);
        
        // Verificar se a imagem realmente carregou
        if (this.naturalWidth === 0 || this.naturalHeight === 0) {
            console.log(`❌ Modelo ${modelNumber} carregou mas tem dimensões inválidas`);
            showModelError(modelNumber, 'Prévia inválida');
        } else {
            // Mostrar imagem
            this.style.display = 'block';
            loading.style.display = 'none';
            error.style.display = 'none';
            status.innerHTML = '<div class="status-success"><i class="fas fa-check-circle text-success-500"></i></div>';
            
            // Reset retry count
            modelRetryCount[modelNumber] = 0;
        }
        
        modelsLoaded++;
        checkAllModelsLoaded();
    };
    
    img.onerror = function() {
        if (modelLoadingAborted) return;
        
        // Remover timeout da lista ativa
        const timeoutIndex = activeModelTimeouts.indexOf(timeout);
        if (timeoutIndex > -1) {
            clearTimeout(timeout);
            activeModelTimeouts.splice(timeoutIndex, 1);
        }
        
        console.log(`❌ Erro ao carregar modelo ${modelNumber}`);
        showModelError(modelNumber, 'Erro ao carregar prévia');
    };
    
    // Iniciar carregamento
    img.src = url;
}

function showModelError(modelNumber, message) {
    const img = document.getElementById(`model-img-${modelNumber}`);
    const loading = document.getElementById(`model-loading-${modelNumber}`);
    const error = document.getElementById(`model-error-${modelNumber}`);
    const status = document.getElementById(`model-status-${modelNumber}`);
    
    if (img) img.style.display = 'none';
    if (loading) loading.style.display = 'none';
    if (error) {
        error.style.display = 'flex';
        const errorText = error.querySelector('.error-text');
        if (errorText) {
            errorText.textContent = `${message} (Tentativa ${modelRetryCount[modelNumber] || 0}/${maxModelRetries})`;
        }
    }
    if (status) {
        status.innerHTML = '<div class="status-error"><i class="fas fa-times-circle text-danger-500"></i></div>';
    }
    
    modelsLoaded++;
    checkAllModelsLoaded();
}

function retryModel(modelNumber) {
    modelRetryCount[modelNumber] = (modelRetryCount[modelNumber] || 0) + 1;
    
    if (modelRetryCount[modelNumber] > maxModelRetries) {
        showModelError(modelNumber, 'Máximo de tentativas excedido');
        return;
    }
    
    console.log(`🔄 Tentativa ${modelRetryCount[modelNumber]} para modelo ${modelNumber}`);
    
    // Delay entre tentativas
    setTimeout(() => {
        if (!modelLoadingAborted) {
            modelsLoaded--;
            loadModel(modelNumber);
        }
    }, 1000);
}

function checkAllModelsLoaded() {
    if (modelsLoaded >= totalModels) {
        console.log('✅ Todos os modelos processados');
        enableFreeNavigation();
    }
}

function enableFreeNavigation() {
    // Remove qualquer bloqueio de navegação
    const useButtons = document.querySelectorAll('.model-use-btn');
    useButtons.forEach(btn => {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
    });
}

// Interceptar navegação na página de seleção de modelos
function setupModelNavigationInterception() {
    // Interceptar cliques em links
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a');
        if (target && target.href && !target.target) {
            console.log('🔗 Navegação detectada na seleção de modelos, abortando prévias...');
            abortModelLoading();
        }
    });
    
    // Interceptar mudanças de página via JavaScript
    const originalPushState = history.pushState;
    const originalReplaceState = history.replaceState;
    
    history.pushState = function() {
        console.log('📍 PushState detectado na seleção, abortando prévias...');
        abortModelLoading();
        return originalPushState.apply(history, arguments);
    };
    
    history.replaceState = function() {
        console.log('📍 ReplaceState detectado na seleção, abortando prévias...');
        abortModelLoading();
        return originalReplaceState.apply(history, arguments);
    };
    
    // Interceptar evento beforeunload
    window.addEventListener('beforeunload', function() {
        console.log('🚪 Página sendo fechada, abortando prévias...');
        abortModelLoading();
    });
    
    // Interceptar evento pagehide
    window.addEventListener('pagehide', function() {
        console.log('👋 Página sendo escondida, abortando prévias...');
        abortModelLoading();
    });
}

// Carregar modelos quando a página estiver pronta
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando carregamento das prévias dos modelos...');
    
    // Configurar interceptação de navegação para modelos
    setupModelNavigationInterception();
    
    <?php if (!empty($jogos)): ?>
        // Habilitar navegação livre imediatamente
        enableFreeNavigation();
        
        // Reset variáveis
        modelLoadingAborted = false;
        modelsLoaded = 0;
        
        // Carregar modelos com delay mínimo
        for (let i = 1; i <= totalModels; i++) {
            const delay = (i - 1) * 500; // 500ms entre cada modelo
            const timeoutId = setTimeout(() => {
                if (!modelLoadingAborted) {
                    loadModel(i);
                }
            }, delay);
            
            // Adicionar à lista de timeouts ativos
            activeModelTimeouts.push(timeoutId);
        }
        
        // Permitir que usuário navegue mesmo durante carregamento
        const useButtons = document.querySelectorAll('.model-use-btn');
        useButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Abortar carregamentos em andamento
                abortModelLoading();
                
                // Permitir navegação imediata
                console.log('🎯 Usuário clicou em usar modelo, abortando carregamentos...');
                
                // Não prevenir o comportamento padrão - deixar navegar
                return true;
            });
        });
    <?php endif; ?>
});

// Expor funções globalmente
window.retryModel = retryModel;
window.abortModelLoading = abortModelLoading;
</script>

<?php
}

include "includes/footer.php";
?>