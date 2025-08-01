<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

require_once 'classes/TelegramSettings.php';

$telegramSettings = new TelegramSettings();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_settings':
                $botToken = trim($_POST['bot_token']);
                $chatId = trim($_POST['chat_id']);
                $footballMessage = trim($_POST['football_message'] ?? '');
                $movieSeriesMessage = trim($_POST['movie_series_message'] ?? '');
                $scheduledTime = trim($_POST['scheduled_time'] ?? '');
                $scheduledFootballTheme = (int)($_POST['scheduled_football_theme'] ?? 1);
                $scheduledDeliveryEnabled = isset($_POST['scheduled_delivery_enabled']);
                
                $result = $telegramSettings->saveSettings(
                    $userId, 
                    $botToken, 
                    $chatId, 
                    $footballMessage, 
                    $movieSeriesMessage,
                    $scheduledTime,
                    $scheduledFootballTheme,
                    $scheduledDeliveryEnabled
                );
                
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'test_bot':
                $botToken = trim($_POST['bot_token']);
                $result = $telegramSettings->testBotConnection($botToken);
                
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
                
            case 'test_chat':
                $botToken = trim($_POST['bot_token']);
                $chatId = trim($_POST['chat_id']);
                $result = $telegramSettings->testChatConnection($botToken, $chatId);
                
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
                
            case 'send_test':
                $botToken = trim($_POST['bot_token']);
                $chatId = trim($_POST['chat_id']);
                $result = $telegramSettings->sendTestMessage($botToken, $chatId);
                
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
                
            case 'delete_settings':
                $result = $telegramSettings->deleteSettings($userId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Buscar configurações atuais
$currentSettings = $telegramSettings->getSettings($userId);
$hasSettings = $currentSettings !== false;

$pageTitle = "Configurações do Telegram";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fab fa-telegram text-primary-500 mr-3"></i>
        Configurações do Telegram
    </h1>
    <p class="page-subtitle">Configure seu bot do Telegram para envio automático de banners</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Formulário de Configuração -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo $hasSettings ? 'Atualizar' : 'Configurar'; ?> Bot do Telegram
                </h3>
                <p class="card-subtitle">
                    <?php echo $hasSettings ? 'Suas configurações atuais' : 'Configure seu bot para enviar banners automaticamente'; ?>
                </p>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> mb-6">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="telegramForm">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="form-group">
                        <label for="bot_token" class="form-label required">
                            <i class="fas fa-robot mr-2"></i>
                            Token do Bot
                        </label>
                        <input type="text" id="bot_token" name="bot_token" class="form-input" 
                               value="<?php echo htmlspecialchars($currentSettings['bot_token'] ?? ''); ?>" 
                               placeholder="123456789:AAAA-BBBB-CCCC-DDDD" required>
                        <p class="text-xs text-muted mt-1">
                            Obtenha o token criando um bot com o @BotFather no Telegram
                        </p>
                        <button type="button" class="btn btn-secondary btn-sm mt-2" id="testBotBtn">
                            <i class="fas fa-vial"></i>
                            Testar Bot
                        </button>
                    </div>

                    <div class="form-group">
                        <label for="chat_id" class="form-label required">
                            <i class="fas fa-comments mr-2"></i>
                            Chat ID
                        </label>
                        <input type="text" id="chat_id" name="chat_id" class="form-input" 
                               value="<?php echo htmlspecialchars($currentSettings['chat_id'] ?? ''); ?>" 
                               placeholder="-1001234567890 ou 123456789" required>
                        <p class="text-xs text-muted mt-1">
                            ID do chat privado (positivo) ou grupo (negativo). Use @userinfobot para descobrir
                        </p>
                        <button type="button" class="btn btn-secondary btn-sm mt-2" id="testChatBtn">
                            <i class="fas fa-search"></i>
                            Verificar Chat
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label for="football_message" class="form-label">
                            <i class="fas fa-futbol mr-2"></i>
                            Mensagem para Banners de Futebol
                        </label>
                        <textarea id="football_message" name="football_message" class="form-input" rows="4" 
                                  placeholder="Mensagem personalizada para banners de futebol"><?php echo htmlspecialchars($currentSettings['football_message'] ?? ''); ?></textarea>
                        <p class="text-xs text-muted mt-1">
                            Variáveis disponíveis: $data (data atual), $hora (hora atual), $jogos (quantidade de jogos)
                        </p>
                        <p class="text-xs text-muted">
                            Deixe em branco para usar a mensagem padrão
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label for="movie_series_message" class="form-label">
                            <i class="fas fa-film mr-2"></i>
                            Mensagem para Banners de Filmes/Séries
                        </label>
                        <textarea id="movie_series_message" name="movie_series_message" class="form-input" rows="4" 
                                  placeholder="Mensagem personalizada para banners de filmes e séries"><?php echo htmlspecialchars($currentSettings['movie_series_message'] ?? ''); ?></textarea>
                        <p class="text-xs text-muted mt-1">
                            Variáveis disponíveis: $data (data atual), $hora (hora atual), $nomedofilme (nome do filme/série)
                        </p>
                        <p class="text-xs text-muted">
                            Deixe em branco para usar a mensagem padrão
                        </p>
                    </div>
                    
                    <!-- Seção de Envio Agendado -->
                    <div class="border-t border-gray-200 my-6 pt-6">
                        <h4 class="text-lg font-semibold mb-4">
                            <i class="fas fa-clock mr-2"></i>
                            Envio Agendado
                        </h4>
                        
                        <div class="form-group">
                            <div class="flex items-center mb-3">
                                <input type="checkbox" id="scheduled_delivery_enabled" name="scheduled_delivery_enabled" class="form-checkbox" 
                                       <?php echo (!empty($currentSettings) && isset($currentSettings['scheduled_delivery_enabled']) && $currentSettings['scheduled_delivery_enabled']) ? 'checked' : ''; ?>>
                                <label for="scheduled_delivery_enabled" class="ml-2 font-medium">
                                    Ativar envio automático diário
                                </label>
                            </div>
                            <p class="text-xs text-muted mb-3">
                                Quando ativado, o sistema enviará automaticamente os banners de futebol no horário especificado
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="scheduled_time" class="form-label">
                                    <i class="fas fa-hourglass-half mr-2"></i>
                                    Horário de Envio
                                </label>
                                <input type="time" id="scheduled_time" name="scheduled_time" class="form-input" 
                                       value="<?php echo htmlspecialchars($currentSettings['scheduled_time'] ?? '08:00'); ?>">
                                <p class="text-xs text-muted mt-1">
                                    Horário em que os banners serão enviados automaticamente (formato 24h)
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label for="scheduled_football_theme" class="form-label">
                                    <i class="fas fa-paint-brush mr-2"></i>
                                    Tema dos Banners
                                </label>
                                <select id="scheduled_football_theme" name="scheduled_football_theme" class="form-input form-select">
                                    <option value="1" <?php echo (!empty($currentSettings) && isset($currentSettings['scheduled_football_theme']) && $currentSettings['scheduled_football_theme'] == 1) ? 'selected' : ''; ?>>
                                        Tema 1 (Clássico)
                                    </option>
                                    <option value="2" <?php echo (!empty($currentSettings) && isset($currentSettings['scheduled_football_theme']) && $currentSettings['scheduled_football_theme'] == 2) ? 'selected' : ''; ?>>
                                        Tema 2 (Moderno)
                                    </option>
                                    <option value="3" <?php echo (!empty($currentSettings) && isset($currentSettings['scheduled_football_theme']) && $currentSettings['scheduled_football_theme'] == 3) ? 'selected' : ''; ?>>
                                        Tema 3 (Premium)
                                    </option>
                                    <option value="4" <?php echo (!empty($currentSettings) && isset($currentSettings['scheduled_football_theme']) && $currentSettings['scheduled_football_theme'] == 4) ? 'selected' : ''; ?>>
                                        Tema 4 (Agenda Esportiva)
                                    </option>
                                </select>
                                <p class="text-xs text-muted mt-1">
                                    Escolha o estilo de banner que será enviado automaticamente
                                </p>
                            </div>
                        </div>
                        
                        <div class="bg-primary-50 p-4 rounded-lg mt-3">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-info-circle text-primary-500 mt-1"></i>
                                <div>
                                    <p class="font-medium text-primary-700">Sobre o envio agendado</p>
                                    <p class="text-sm text-primary-600 mt-1">
                                        O sistema verificará os jogos do dia e enviará automaticamente os banners no horário especificado.
                                        Certifique-se de que seu bot tenha permissão para enviar mensagens no chat configurado.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $hasSettings ? 'Atualizar' : 'Salvar'; ?> Configurações
                        </button>
                        
                        <button type="button" class="btn btn-success" id="sendTestBtn" 
                                <?php echo !$hasSettings ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane"></i>
                            Enviar Teste
                        </button>
                        
                        <?php if ($hasSettings): ?>
                        <button type="button" class="btn btn-danger" id="deleteBtn">
                            <i class="fas fa-trash"></i>
                            Remover Configurações
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Envio Manual de Banners -->
        <?php if ($hasSettings): ?>
        <div class="card mt-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paper-plane text-primary-500 mr-2"></i>
                    Envio Manual de Banners
                </h3>
                <p class="card-subtitle">
                    Envie banners de futebol diretamente para o Telegram
                </p>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="manual-send-card">
                        <div class="manual-send-icon">
                            <i class="fas fa-futbol"></i>
                        </div>
                        <h4 class="manual-send-title">Tema 1 (Clássico)</h4>
                        <p class="manual-send-desc">Banner tradicional com layout vertical</p>
                        <button type="button" class="btn btn-primary w-full mt-3 send-football-btn" data-theme="football_1">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Agora
                        </button>
                    </div>
                    
                    <div class="manual-send-card">
                        <div class="manual-send-icon">
                            <i class="fas fa-futbol"></i>
                        </div>
                        <h4 class="manual-send-title">Tema 2 (Moderno)</h4>
                        <p class="manual-send-desc">Banner compacto com layout horizontal</p>
                        <button type="button" class="btn btn-primary w-full mt-3 send-football-btn" data-theme="football_2">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Agora
                        </button>
                    </div>
                    
                    <div class="manual-send-card">
                        <div class="manual-send-icon">
                            <i class="fas fa-futbol"></i>
                        </div>
                        <h4 class="manual-send-title">Tema 3 (Premium)</h4>
                        <p class="manual-send-desc">Banner premium com design especial</p>
                        <button type="button" class="btn btn-primary w-full mt-3 send-football-btn" data-theme="football_3">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Agora
                        </button>
                    </div>
                    
                    <div class="manual-send-card">
                        <div class="manual-send-icon">
                            <i class="fas fa-futbol"></i>
                        </div>
                        <h4 class="manual-send-title">Tema 4 (Agenda)</h4>
                        <p class="manual-send-desc">Banner com layout de agenda esportiva</p>
                        <button type="button" class="btn btn-primary w-full mt-3 send-football-btn" data-theme="football_4">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Agora
                        </button>
                    </div>
                </div>
                
                <div id="sendResult" class="mt-4" style="display: none;"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Painel de Informações -->
    <div class="space-y-6">
        <!-- Status -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📊 Status</h3>
            </div>
            <div class="card-body">
                <div class="status-item">
                    <div class="status-icon <?php echo $hasSettings ? 'status-success' : 'status-warning'; ?>">
                        <i class="fas fa-<?php echo $hasSettings ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    </div>
                    <div>
                        <p class="font-medium">
                            <?php echo $hasSettings ? 'Configurado' : 'Não Configurado'; ?>
                        </p>
                        <p class="text-sm text-muted">
                            <?php echo $hasSettings ? 'Bot pronto para envio' : 'Configure seu bot primeiro'; ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($hasSettings): ?>
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm font-medium mb-2">Última atualização:</p>
                    <p class="text-xs text-muted">
                        <?php echo date('d/m/Y H:i', strtotime($currentSettings['updated_at'])); ?>
                    </p>
                </div>
                
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm font-medium mb-2">Envio Agendado:</p>
                    <p class="text-xs">
                        <span class="status-badge <?php echo (!empty($currentSettings) && isset($currentSettings['scheduled_delivery_enabled']) && $currentSettings['scheduled_delivery_enabled']) ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo (!empty($currentSettings) && isset($currentSettings['scheduled_delivery_enabled']) && $currentSettings['scheduled_delivery_enabled']) ? 'Ativado' : 'Desativado'; ?>
                        </span>
                        <?php if (!empty($currentSettings) && isset($currentSettings['scheduled_delivery_enabled']) && $currentSettings['scheduled_delivery_enabled'] && !empty($currentSettings['scheduled_time'])): ?>
                            <span class="ml-2">às <?php echo htmlspecialchars($currentSettings['scheduled_time']); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Como Configurar -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🤖 Como Configurar</h3>
            </div>
            <div class="card-body">
                <div class="space-y-4 text-sm">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div>
                            <p class="font-medium">Criar Bot</p>
                            <p class="text-muted">Converse com @BotFather no Telegram e use /newbot</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div>
                            <p class="font-medium">Obter Token</p>
                            <p class="text-muted">Copie o token fornecido pelo BotFather</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div>
                            <p class="font-medium">Descobrir Chat ID</p>
                            <p class="text-muted">Use @userinfobot ou adicione o bot ao grupo</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div>
                            <p class="font-medium">Testar</p>
                            <p class="text-muted">Use os botões de teste antes de salvar</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dicas -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💡 Dicas</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3 text-sm">
                    <div class="tip-item">
                        <i class="fas fa-info-circle text-primary-500"></i>
                        <p>Para grupos, o Chat ID é negativo (ex: -1001234567890)</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-shield-alt text-success-500"></i>
                        <p>Seu token é criptografado e armazenado com segurança</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-users text-warning-500"></i>
                        <p>Para grupos, adicione o bot como administrador</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-clock text-info-500"></i>
                        <p>Teste sempre antes de usar em produção</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Variáveis Disponíveis -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🔤 Variáveis para Mensagens</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3 text-sm">
                    <div class="variable-item">
                        <code>$data</code>
                        <p>Data atual (ex: 25/06/2025)</p>
                    </div>
                    <div class="variable-item">
                        <code>$hora</code>
                        <p>Hora atual (ex: 14:30)</p>
                    </div>
                    <div class="variable-item">
                        <code>$jogos</code>
                        <p>Quantidade de jogos (apenas para banners de futebol)</p>
                    </div>
                    <div class="variable-item">
                        <code>$nomedofilme</code>
                        <p>Nome do filme ou série (apenas para banners de filmes/séries)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .required::after {
        content: ' *';
        color: var(--danger-500);
    }

    .alert {
        padding: 1rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
    }
    
    .alert-success {
        background: var(--success-50);
        color: var(--success-600);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .alert-error {
        background: var(--danger-50);
        color: var(--danger-600);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
        margin-top: 2rem;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }

    .status-item {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .status-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .status-success {
        background: var(--success-50);
        color: var(--success-600);
    }

    .status-warning {
        background: var(--warning-50);
        color: var(--warning-600);
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-active {
        background: var(--success-50);
        color: var(--success-600);
    }
    
    .status-inactive {
        background: var(--danger-50);
        color: var(--danger-600);
    }

    .step-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .step-number {
        width: 24px;
        height: 24px;
        background: var(--primary-500);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.75rem;
        flex-shrink: 0;
    }

    .tip-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .tip-item i {
        margin-top: 0.125rem;
        flex-shrink: 0;
    }
    
    .variable-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem;
        background: var(--bg-tertiary);
        border-radius: var(--border-radius-sm);
    }
    
    .variable-item code {
        font-family: monospace;
        background: var(--bg-primary);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-weight: 600;
        color: var(--primary-600);
        border: 1px solid var(--border-color);
        min-width: 100px;
        display: inline-block;
        text-align: center;
    }
    
    .manual-send-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        text-align: center;
        transition: var(--transition);
    }
    
    .manual-send-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-300);
    }
    
    .manual-send-icon {
        width: 60px;
        height: 60px;
        background: var(--primary-50);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
        color: var(--primary-500);
    }
    
    .manual-send-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .manual-send-desc {
        font-size: 0.875rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }
    
    .form-checkbox {
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 0.25rem;
        border: 2px solid var(--border-color);
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-color: var(--bg-primary);
        cursor: pointer;
        position: relative;
        transition: var(--transition);
    }
    
    .form-checkbox:checked {
        background-color: var(--primary-500);
        border-color: var(--primary-500);
    }
    
    .form-checkbox:checked::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(45deg);
        width: 0.25rem;
        height: 0.5rem;
        border: solid white;
        border-width: 0 2px 2px 0;
    }
    
    .form-checkbox:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    }
    
    .ml-2 {
        margin-left: 0.5rem;
    }
    
    .ml-3 {
        margin-left: 0.75rem;
    }
    
    .bg-primary-50 {
        background-color: var(--primary-50);
    }
    
    .text-primary-600 {
        color: var(--primary-600);
    }
    
    .text-primary-700 {
        color: var(--primary-700);
    }

    .space-y-3 > * + * {
        margin-top: 0.75rem;
    }

    .space-y-4 > * + * {
        margin-top: 1rem;
    }

    .space-y-6 > * + * {
        margin-top: 1.5rem;
    }

    .mt-2 {
        margin-top: 0.5rem;
    }

    .mt-3 {
        margin-top: 0.75rem;
    }

    .mt-4 {
        margin-top: 1rem;
    }

    .mt-6 {
        margin-top: 1.5rem;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    .mb-3 {
        margin-bottom: 0.75rem;
    }

    .mb-4 {
        margin-bottom: 1rem;
    }

    .mb-6 {
        margin-bottom: 1.5rem;
    }

    .mr-2 {
        margin-right: 0.5rem;
    }

    .p-3 {
        padding: 0.75rem;
    }
    
    .p-4 {
        padding: 1rem;
    }

    .bg-gray-50 {
        background-color: var(--bg-tertiary);
    }

    .rounded-lg {
        border-radius: var(--border-radius);
    }
    
    .w-full {
        width: 100%;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }
    
    [data-theme="dark"] .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }

    [data-theme="dark"] .bg-gray-50 {
        background-color: var(--bg-tertiary);
    }

    [data-theme="dark"] .status-success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .status-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
    
    [data-theme="dark"] .variable-item code {
        background: var(--bg-secondary);
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .manual-send-icon {
        background: rgba(59, 130, 246, 0.1);
    }
    
    [data-theme="dark"] .bg-primary-50 {
        background: rgba(59, 130, 246, 0.1);
    }
    
    [data-theme="dark"] .text-primary-600 {
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .text-primary-700 {
        color: var(--primary-300);
    }
    
    [data-theme="dark"] .status-active {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }
    
    [data-theme="dark"] .status-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const testBotBtn = document.getElementById('testBotBtn');
    const testChatBtn = document.getElementById('testChatBtn');
    const sendTestBtn = document.getElementById('sendTestBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    const botTokenInput = document.getElementById('bot_token');
    const chatIdInput = document.getElementById('chat_id');
    const scheduledEnabledCheckbox = document.getElementById('scheduled_delivery_enabled');

    // Testar Bot
    testBotBtn.addEventListener('click', function() {
        const botToken = botTokenInput.value.trim();
        
        if (!botToken) {
            showAlert('error', 'Digite o token do bot primeiro');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';

        fetch('telegram.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=test_bot&bot_token=${encodeURIComponent(botToken)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const botInfo = data.bot_info;
                showAlert('success', `Bot conectado: @${botInfo.username} (${botInfo.first_name})`);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'Erro na conexão: ' + error.message);
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-vial"></i> Testar Bot';
        });
    });

    // Testar Chat
    testChatBtn.addEventListener('click', function() {
        const botToken = botTokenInput.value.trim();
        const chatId = chatIdInput.value.trim();
        
        if (!botToken || !chatId) {
            showAlert('error', 'Digite o token do bot e Chat ID primeiro');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';

        fetch('telegram.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=test_chat&bot_token=${encodeURIComponent(botToken)}&chat_id=${encodeURIComponent(chatId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const chatInfo = data.chat_info;
                showAlert('success', `Chat encontrado: ${chatInfo.title} (${chatInfo.type})`);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'Erro na verificação: ' + error.message);
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-search"></i> Verificar Chat';
        });
    });

    // Enviar Teste
    sendTestBtn.addEventListener('click', function() {
        const botToken = botTokenInput.value.trim();
        const chatId = chatIdInput.value.trim();
        
        if (!botToken || !chatId) {
            showAlert('error', 'Digite o token do bot e Chat ID primeiro');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        fetch('telegram.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=send_test&bot_token=${encodeURIComponent(botToken)}&chat_id=${encodeURIComponent(chatId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Mensagem de teste enviada com sucesso!');
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'Erro no envio: ' + error.message);
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Teste';
        });
    });

    // Deletar Configurações
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Remover Configurações?',
                text: 'Isso irá remover todas as suas configurações do Telegram. Você precisará configurar novamente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="delete_settings">';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    }
    
    // Envio manual de banners de futebol
    const sendFootballBtns = document.querySelectorAll('.send-football-btn');
    if (sendFootballBtns.length > 0) {
        sendFootballBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const bannerType = this.getAttribute('data-theme');
                
                // Desabilitar todos os botões
                sendFootballBtns.forEach(b => {
                    b.disabled = true;
                    b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                });
                
                // Mostrar área de resultado
                const resultArea = document.getElementById('sendResult');
                resultArea.style.display = 'block';
                resultArea.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin"></i>
                        Gerando e enviando banners para o Telegram...
                    </div>
                `;
                
                // Enviar requisição
                fetch('send_telegram_banners.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `banner_type=${encodeURIComponent(bannerType)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultArea.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                ${data.message}
                            </div>
                        `;
                    } else {
                        resultArea.innerHTML = `
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    resultArea.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            Erro na conexão: ${error.message}
                        </div>
                    `;
                })
                .finally(() => {
                    // Reabilitar todos os botões
                    sendFootballBtns.forEach(b => {
                        b.disabled = false;
                        b.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Agora';
                    });
                });
            });
        });
    }

    function showAlert(type, message) {
        Swal.fire({
            title: type === 'success' ? 'Sucesso!' : 'Erro!',
            text: message,
            icon: type,
            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
            confirmButtonColor: type === 'success' ? '#22c55e' : '#ef4444'
        });
    }
    
    // Atualizar UI quando o checkbox de agendamento é alterado
    if (scheduledEnabledCheckbox) {
        scheduledEnabledCheckbox.addEventListener('change', function() {
            const scheduledTimeInput = document.getElementById('scheduled_time');
            const scheduledThemeSelect = document.getElementById('scheduled_football_theme');
            
            if (this.checked) {
                scheduledTimeInput.removeAttribute('disabled');
                scheduledThemeSelect.removeAttribute('disabled');
            } else {
                scheduledTimeInput.setAttribute('disabled', 'disabled');
                scheduledThemeSelect.setAttribute('disabled', 'disabled');
            }
        });
        
        // Trigger the change event on page load
        scheduledEnabledCheckbox.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include "includes/footer.php"; ?>
