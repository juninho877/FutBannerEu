<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

require_once 'classes/TelegramSettings.php';
require_once 'classes/TelegramService.php';

// Verificar se os parâmetros necessários foram fornecidos
if (!isset($_GET['banner_path']) || !isset($_GET['banner_name'])) {
    $pageTitle = "Erro - Telegram";
    include "includes/header.php";
    ?>
    <div class="page-header">
        <h1 class="page-title">Erro no Envio para Telegram</h1>
        <p class="page-subtitle">Parâmetros inválidos</p>
    </div>
    
    <div class="card">
        <div class="card-body text-center py-12">
            <div class="mb-4">
                <i class="fas fa-exclamation-triangle text-6xl text-danger-500"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2">Parâmetros Inválidos</h3>
            <p class="text-muted mb-6">Caminho do banner ou nome não foram especificados.</p>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Voltar para Dashboard
            </a>
        </div>
    </div>
    <?php
    include "includes/footer.php";
    exit;
}

$bannerPath = urldecode($_GET['banner_path']);
$bannerName = urldecode($_GET['banner_name']);
$contentName = isset($_GET['content_name']) ? urldecode($_GET['content_name']) : pathinfo($bannerName, PATHINFO_FILENAME);
$contentType = isset($_GET['type']) ? urldecode($_GET['type']) : 'filme';
$userId = $_SESSION['user_id'];

// Inicializar serviço do Telegram
$telegramService = new TelegramService();

// Processar envio
$result = ['success' => false, 'message' => 'Erro ao processar solicitação'];

if (file_exists($bannerPath)) {
    // Determinar se é banner de filme/série ou de futebol
    if (isset($_GET['content_name']) || strpos($bannerName, 'banner_tema') !== false) {
        // Banner de filme/série
        $result = $telegramService->sendMovieSeriesBanner($userId, $bannerPath, $contentName, $contentType);
    } else {
        // Banner de futebol (legado - agora usa send_telegram_banners.php)
        $caption = "🏆 Banner: " . pathinfo($bannerName, PATHINFO_FILENAME) . "\n";
        $caption .= "📅 Gerado em: " . date('d/m/Y H:i') . "\n";
        $caption .= "🎨 FutBanner";
        
        $result = $telegramService->sendImageAlbum($userId, [$bannerPath], $caption);
    }
    
    // Se o envio foi bem-sucedido, remover o arquivo temporário
    if ($result['success'] && isset($_SESSION['current_banner_temp_path']) && $_SESSION['current_banner_temp_path'] === $bannerPath) {
        unlink($bannerPath);
        unset($_SESSION['current_banner_temp_path']);
        unset($_SESSION['current_banner_original_name']);
    }
}

// Exibir página com resultado
$pageTitle = "Envio para Telegram";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fab fa-telegram text-primary-500 mr-3"></i>
        Envio para Telegram
    </h1>
    <p class="page-subtitle">
        <?php echo $result['success'] ? 'Banner enviado com sucesso' : 'Erro no envio do banner'; ?>
    </p>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <?php echo $result['success'] ? 'Sucesso!' : 'Erro!'; ?>
        </h3>
        <p class="card-subtitle">
            <?php echo $result['success'] ? 'Seu banner foi enviado para o Telegram' : 'Não foi possível enviar o banner'; ?>
        </p>
    </div>
    <div class="card-body">
        <div class="result-container <?php echo $result['success'] ? 'success' : 'error'; ?>">
            <div class="result-icon">
                <i class="fas fa-<?php echo $result['success'] ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            </div>
            <div class="result-message">
                <h4 class="result-title">
                    <?php echo $result['success'] ? 'Banner Enviado!' : 'Falha no Envio'; ?>
                </h4>
                <p class="result-description">
                    <?php echo $result['message']; ?>
                </p>
                
                <?php if (!$result['success'] && strpos($result['message'], 'Configurações do Telegram não encontradas') !== false): ?>
                <div class="alert alert-warning mt-4">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p class="font-medium">Configurações não encontradas</p>
                        <p class="text-sm mt-1">
                            Você precisa configurar seu bot do Telegram antes de enviar banners.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="actions-container mt-6">
            <?php if (!$result['success'] && strpos($result['message'], 'Configurações do Telegram não encontradas') !== false): ?>
            <a href="telegram.php" class="btn btn-primary">
                <i class="fab fa-telegram"></i>
                Configurar Telegram
            </a>
            <?php endif; ?>
            
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </a>
            
            <?php if ($result['success']): ?>
            <a href="index.php" class="btn btn-success">
                <i class="fas fa-home"></i>
                Ir para Dashboard
            </a>
            <?php else: ?>
            <button onclick="location.reload()" class="btn btn-warning">
                <i class="fas fa-redo"></i>
                Tentar Novamente
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .result-container {
        display: flex;
        align-items: flex-start;
        gap: 1.5rem;
        padding: 2rem;
        border-radius: var(--border-radius);
    }
    
    .result-container.success {
        background: var(--success-50);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .result-container.error {
        background: var(--danger-50);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .result-icon {
        font-size: 3rem;
        flex-shrink: 0;
    }
    
    .result-container.success .result-icon {
        color: var(--success-500);
    }
    
    .result-container.error .result-icon {
        color: var(--danger-500);
    }
    
    .result-message {
        flex: 1;
    }
    
    .result-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .result-container.success .result-title {
        color: var(--success-700);
    }
    
    .result-container.error .result-title {
        color: var(--danger-700);
    }
    
    .result-description {
        color: var(--text-secondary);
        font-size: 1rem;
    }
    
    .actions-container {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .alert-warning {
        background: var(--warning-50);
        color: var(--warning-600);
        border: 1px solid rgba(245, 158, 11, 0.2);
        padding: 1rem;
        border-radius: var(--border-radius);
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .mt-4 {
        margin-top: 1rem;
    }
    
    .mt-6 {
        margin-top: 1.5rem;
    }
    
    .mt-1 {
        margin-top: 0.25rem;
    }
    
    .text-sm {
        font-size: 0.875rem;
    }
    
    .font-medium {
        font-weight: 500;
    }
    
    /* Dark theme adjustments */
    [data-theme="dark"] .result-container.success {
        background: rgba(34, 197, 94, 0.1);
    }
    
    [data-theme="dark"] .result-container.error {
        background: rgba(239, 68, 68, 0.1);
    }
    
    [data-theme="dark"] .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
</style>

<?php include "includes/footer.php"; ?>