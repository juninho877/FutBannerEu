<?php
/**
 * Script de diagnóstico para identificar problemas com o envio agendado via Telegram
 */
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

// Definir cabeçalhos para evitar cache
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Incluir classes necessárias
require_once 'classes/TelegramSettings.php';
require_once 'classes/TelegramService.php';
require_once 'includes/banner_functions.php';

$pageTitle = "Diagnóstico do Telegram";
include "includes/header.php";

// Função para testar uma condição e retornar o resultado formatado
function testCondition($description, $test, $successMessage = "OK", $errorMessage = "Falha") {
    $result = [
        'description' => $description,
        'success' => $test,
        'message' => $test ? $successMessage : $errorMessage
    ];
    
    return $result;
}

// Função para testar conexão com a API do Telegram
function testTelegramAPI($botToken) {
    try {
        $url = "https://api.telegram.org/bot{$botToken}/getMe";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FutBanner/1.0'
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        if ($response === false) {
            return [
                'success' => false,
                'message' => "Erro cURL: " . $error,
                'details' => $info
            ];
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => "Erro ao decodificar resposta JSON: " . json_last_error_msg(),
                'response' => $response
            ];
        }
        
        if (!isset($data['ok']) || $data['ok'] !== true) {
            return [
                'success' => false,
                'message' => "API retornou erro: " . ($data['description'] ?? 'Erro desconhecido'),
                'response' => $data
            ];
        }
        
        return [
            'success' => true,
            'message' => "Conexão com API do Telegram bem-sucedida",
            'bot_info' => $data['result']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Exceção: " . $e->getMessage()
        ];
    }
}

// Iniciar diagnóstico
$diagnosticResults = [];
$userId = $_SESSION['user_id'];

// 1. Verificar se o PHP tem as extensões necessárias
$diagnosticResults[] = testCondition(
    "Extensão cURL habilitada", 
    function_exists('curl_init'),
    "cURL está disponível",
    "cURL não está disponível - necessário para comunicação com a API do Telegram"
);

$diagnosticResults[] = testCondition(
    "Extensão JSON habilitada", 
    function_exists('json_encode'),
    "JSON está disponível",
    "JSON não está disponível - necessário para processar respostas da API"
);

$diagnosticResults[] = testCondition(
    "Extensão GD habilitada", 
    function_exists('imagecreatetruecolor'),
    "GD está disponível",
    "GD não está disponível - necessário para gerar banners"
);

// 2. Verificar permissões de arquivos
$scheduledDeliveryPath = __DIR__ . '/scheduled_delivery.php';
$diagnosticResults[] = testCondition(
    "Arquivo scheduled_delivery.php existe", 
    file_exists($scheduledDeliveryPath),
    "Arquivo encontrado",
    "Arquivo não encontrado - verifique o caminho"
);

if (file_exists($scheduledDeliveryPath)) {
    $diagnosticResults[] = testCondition(
        "Permissões de scheduled_delivery.php", 
        is_readable($scheduledDeliveryPath),
        "Arquivo é legível",
        "Arquivo não é legível - verifique permissões"
    );
}

// 3. Verificar configurações do Telegram
$telegramSettings = new TelegramSettings();
$settings = $telegramSettings->getSettings($userId);
$hasSettings = ($settings !== false);

$diagnosticResults[] = testCondition(
    "Configurações do Telegram", 
    $hasSettings,
    "Configurações encontradas",
    "Configurações não encontradas - configure seu bot primeiro"
);

// 4. Testar conexão com a API do Telegram se tiver configurações
$apiTestResult = null;
if ($hasSettings && !empty($settings['bot_token'])) {
    $apiTestResult = testTelegramAPI($settings['bot_token']);
    $diagnosticResults[] = testCondition(
        "Conexão com API do Telegram", 
        $apiTestResult['success'],
        $apiTestResult['message'],
        $apiTestResult['message']
    );
}

// 5. Verificar se há jogos disponíveis
$jogos = obterJogosDeHoje();
$diagnosticResults[] = testCondition(
    "Jogos disponíveis", 
    !empty($jogos),
    "Encontrados " . count($jogos) . " jogos",
    "Nenhum jogo encontrado - verifique a API de jogos"
);

// 6. Verificar diretório temporário
$tempDir = sys_get_temp_dir();
$diagnosticResults[] = testCondition(
    "Diretório temporário", 
    is_writable($tempDir),
    "Diretório temporário é gravável: " . $tempDir,
    "Diretório temporário não é gravável: " . $tempDir
);

// 7. Verificar diretório de logs
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$diagnosticResults[] = testCondition(
    "Diretório de logs", 
    is_dir($logDir) && is_writable($logDir),
    "Diretório de logs é gravável",
    "Diretório de logs não existe ou não é gravável"
);

// 8. Verificar conexão com o banco de dados
try {
    require_once 'config/database.php';
    $db = Database::getInstance()->getConnection();
    $dbConnected = ($db !== null);
    $dbError = "";
} catch (Exception $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

$diagnosticResults[] = testCondition(
    "Conexão com o banco de dados", 
    $dbConnected,
    "Conectado ao banco de dados",
    "Erro na conexão com o banco: " . $dbError
);

// 9. Verificar tabela de configurações do Telegram
if ($dbConnected) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'user_telegram_settings'");
        $stmt->execute();
        $tableExists = ($stmt->rowCount() > 0);
    } catch (Exception $e) {
        $tableExists = false;
    }
    
    $diagnosticResults[] = testCondition(
        "Tabela user_telegram_settings", 
        $tableExists,
        "Tabela existe",
        "Tabela não existe - execute a migração"
    );
}

// 10. Verificar se o PHP pode executar processos em segundo plano
$canExecuteBackground = true;
$backgroundError = "";
try {
    $testFile = $tempDir . '/futbanner_bg_test_' . uniqid() . '.txt';
    $cmd = "echo 'test' > " . escapeshellarg($testFile) . " 2>&1";
    
    if (function_exists('exec')) {
        @exec($cmd, $output, $returnVar);
        $canExecuteBackground = ($returnVar === 0 && file_exists($testFile));
        if (!$canExecuteBackground) {
            $backgroundError = "Erro ao executar comando: " . implode("\n", $output);
        }
    } else {
        $canExecuteBackground = false;
        $backgroundError = "Função exec() não está disponível";
    }
    
    if (file_exists($testFile)) {
        @unlink($testFile);
    }
} catch (Exception $e) {
    $canExecuteBackground = false;
    $backgroundError = $e->getMessage();
}

$diagnosticResults[] = testCondition(
    "Execução em segundo plano", 
    $canExecuteBackground,
    "PHP pode executar processos em segundo plano",
    "PHP não pode executar processos em segundo plano: " . $backgroundError
);

// 11. Verificar configurações de fuso horário
$timezoneSet = date_default_timezone_get();
$diagnosticResults[] = testCondition(
    "Configuração de fuso horário", 
    $timezoneSet === 'America/Sao_Paulo',
    "Fuso horário configurado: " . $timezoneSet,
    "Fuso horário incorreto: " . $timezoneSet . " (deveria ser America/Sao_Paulo)"
);

// 12. Verificar se o script pode ser acessado via URL
$scriptUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/scheduled_delivery.php';
$diagnosticResults[] = [
    'description' => "URL do script de envio agendado",
    'success' => null, // Não podemos testar isso automaticamente
    'message' => $scriptUrl,
    'info_only' => true
];

// Exibir resultados
?>

<div class="grid grid-cols-1 gap-6">
    <!-- Resultados do Diagnóstico -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-stethoscope text-primary-500 mr-2"></i>
                Resultados do Diagnóstico
            </h3>
            <p class="card-subtitle">
                Verificação de problemas no sistema de envio agendado
            </p>
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <?php foreach ($diagnosticResults as $result): ?>
                    <div class="diagnostic-item <?php echo isset($result['info_only']) && $result['info_only'] ? 'info-only' : ($result['success'] ? 'success' : 'error'); ?>">
                        <div class="diagnostic-icon">
                            <?php if (isset($result['info_only']) && $result['info_only']): ?>
                                <i class="fas fa-info-circle"></i>
                            <?php else: ?>
                                <i class="fas fa-<?php echo $result['success'] ? 'check' : 'times'; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="diagnostic-content">
                            <div class="diagnostic-title"><?php echo htmlspecialchars($result['description']); ?></div>
                            <div class="diagnostic-message"><?php echo htmlspecialchars($result['message']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-6">
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i>
                    Executar Diagnóstico Novamente
                </button>
            </div>
        </div>
    </div>
    
    <!-- Teste de Envio Manual -->
    <?php if ($hasSettings): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-paper-plane text-primary-500 mr-2"></i>
                Teste de Envio Manual
            </h3>
            <p class="card-subtitle">
                Envie uma mensagem de teste para verificar a conexão
            </p>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <button class="btn btn-success w-full" id="testMessageBtn">
                        <i class="fas fa-comment"></i>
                        Enviar Mensagem de Teste
                    </button>
                </div>
                <div>
                    <button class="btn btn-primary w-full" id="testBannerBtn">
                        <i class="fas fa-image"></i>
                        Enviar Banner de Teste
                    </button>
                </div>
            </div>
            
            <div id="testSendResult" class="mt-4" style="display: none;"></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Informações de Depuração -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-bug text-primary-500 mr-2"></i>
                Informações de Depuração
            </h3>
            <p class="card-subtitle">
                Detalhes técnicos para solução de problemas
            </p>
        </div>
        <div class="card-body">
            <div class="space-y-4">
                <div class="debug-section">
                    <h4 class="debug-title">Informações do PHP</h4>
                    <div class="debug-content">
                        <div class="debug-item">
                            <span class="debug-label">Versão do PHP:</span>
                            <span class="debug-value"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Interface SAPI:</span>
                            <span class="debug-value"><?php echo php_sapi_name(); ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Extensões Carregadas:</span>
                            <span class="debug-value"><?php echo implode(', ', array_filter(['curl', 'json', 'gd', 'pdo', 'pdo_mysql'], 'extension_loaded')); ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Limite de Memória:</span>
                            <span class="debug-value"><?php echo ini_get('memory_limit'); ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Tempo Máximo de Execução:</span>
                            <span class="debug-value"><?php echo ini_get('max_execution_time'); ?> segundos</span>
                        </div>
                    </div>
                </div>
                
                <div class="debug-section">
                    <h4 class="debug-title">Informações do Servidor</h4>
                    <div class="debug-content">
                        <div class="debug-item">
                            <span class="debug-label">Sistema Operacional:</span>
                            <span class="debug-value"><?php echo php_uname('s') . ' ' . php_uname('r'); ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Servidor Web:</span>
                            <span class="debug-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido'; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Diretório do Script:</span>
                            <span class="debug-value"><?php echo __DIR__; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Diretório Temporário:</span>
                            <span class="debug-value"><?php echo sys_get_temp_dir(); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($hasSettings): ?>
                <div class="debug-section">
                    <h4 class="debug-title">Configurações do Telegram</h4>
                    <div class="debug-content">
                        <div class="debug-item">
                            <span class="debug-label">Bot Token:</span>
                            <span class="debug-value"><?php echo substr($settings['bot_token'], 0, 8) . '...' . substr($settings['bot_token'], -4); ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Chat ID:</span>
                            <span class="debug-value"><?php echo $settings['chat_id']; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Envio Agendado:</span>
                            <span class="debug-value"><?php echo $settings['scheduled_delivery_enabled'] ? 'Ativado' : 'Desativado'; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Horário Agendado:</span>
                            <span class="debug-value"><?php echo $settings['scheduled_time'] ?: 'Não definido'; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Tema Agendado:</span>
                            <span class="debug-value">Tema <?php echo $settings['scheduled_football_theme']; ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($apiTestResult && $apiTestResult['success'] && isset($apiTestResult['bot_info'])): ?>
                <div class="debug-section">
                    <h4 class="debug-title">Informações do Bot</h4>
                    <div class="debug-content">
                        <div class="debug-item">
                            <span class="debug-label">Nome:</span>
                            <span class="debug-value"><?php echo $apiTestResult['bot_info']['first_name']; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Username:</span>
                            <span class="debug-value">@<?php echo $apiTestResult['bot_info']['username']; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">ID:</span>
                            <span class="debug-value"><?php echo $apiTestResult['bot_info']['id']; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Pode Entrar em Grupos:</span>
                            <span class="debug-value"><?php echo $apiTestResult['bot_info']['can_join_groups'] ? 'Sim' : 'Não'; ?></span>
                        </div>
                        <div class="debug-item">
                            <span class="debug-label">Pode Ler Mensagens de Grupo:</span>
                            <span class="debug-value"><?php echo $apiTestResult['bot_info']['can_read_all_group_messages'] ? 'Sim' : 'Não'; ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Logs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list-alt text-primary-500 mr-2"></i>
                Logs do Sistema
            </h3>
            <p class="card-subtitle">
                Últimas entradas do log de envio agendado
            </p>
        </div>
        <div class="card-body">
            <?php
            $logFile = __DIR__ . '/logs/scheduled_delivery.log';
            if (file_exists($logFile) && is_readable($logFile)) {
                $logContent = file_get_contents($logFile);
                $lines = array_filter(explode(PHP_EOL, $logContent));
                $lastLines = array_slice($lines, -20); // Últimas 20 linhas
                
                if (!empty($lastLines)) {
                    echo '<div class="log-container">';
                    foreach ($lastLines as $line) {
                        $lineClass = '';
                        if (strpos($line, 'ERRO') !== false || strpos($line, 'erro') !== false || strpos($line, 'Erro') !== false) {
                            $lineClass = 'log-error';
                        } elseif (strpos($line, 'Sucesso') !== false || strpos($line, '✅') !== false) {
                            $lineClass = 'log-success';
                        } elseif (strpos($line, 'Iniciando') !== false || strpos($line, 'Processando') !== false) {
                            $lineClass = 'log-info';
                        }
                        
                        echo '<div class="log-line ' . $lineClass . '">' . htmlspecialchars($line) . '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-info">Nenhuma entrada de log encontrada</div>';
                }
            } else {
                echo '<div class="alert alert-warning">Arquivo de log não encontrado ou não é legível</div>';
            }
            ?>
        </div>
    </div>
</div>

<style>
    .diagnostic-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem;
        border-radius: var(--border-radius);
    }
    
    .diagnostic-item.success {
        background: var(--success-50);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .diagnostic-item.error {
        background: var(--danger-50);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .diagnostic-item.info-only {
        background: var(--primary-50);
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .diagnostic-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        flex-shrink: 0;
    }
    
    .diagnostic-item.success .diagnostic-icon {
        background: var(--success-500);
        color: white;
    }
    
    .diagnostic-item.error .diagnostic-icon {
        background: var(--danger-500);
        color: white;
    }
    
    .diagnostic-item.info-only .diagnostic-icon {
        background: var(--primary-500);
        color: white;
    }
    
    .diagnostic-content {
        flex: 1;
    }
    
    .diagnostic-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .diagnostic-item.success .diagnostic-title {
        color: var(--success-700);
    }
    
    .diagnostic-item.error .diagnostic-title {
        color: var(--danger-700);
    }
    
    .diagnostic-item.info-only .diagnostic-title {
        color: var(--primary-700);
    }
    
    .diagnostic-message {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    
    .debug-section {
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        overflow: hidden;
    }
    
    .debug-title {
        font-size: 1rem;
        font-weight: 600;
        padding: 0.75rem 1rem;
        background: var(--bg-tertiary);
        border-bottom: 1px solid var(--border-color);
    }
    
    .debug-content {
        padding: 1rem;
    }
    
    .debug-item {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .debug-item:last-child {
        border-bottom: none;
    }
    
    .debug-label {
        font-weight: 500;
        color: var(--text-secondary);
    }
    
    .debug-value {
        font-family: monospace;
        word-break: break-all;
    }
    
    .log-container {
        max-height: 400px;
        overflow-y: auto;
        background: var(--bg-tertiary);
        border-radius: var(--border-radius);
        padding: 0.5rem;
        font-family: monospace;
        font-size: 0.875rem;
    }
    
    .log-line {
        padding: 0.25rem 0.5rem;
        border-bottom: 1px solid var(--border-color);
        white-space: pre-wrap;
        word-break: break-all;
    }
    
    .log-line:last-child {
        border-bottom: none;
    }
    
    .log-error {
        color: var(--danger-600);
    }
    
    .log-success {
        color: var(--success-600);
    }
    
    .log-info {
        color: var(--primary-600);
    }
    
    .alert-warning {
        background: var(--warning-50);
        color: var(--warning-600);
        border: 1px solid rgba(245, 158, 11, 0.2);
        padding: 1rem;
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    /* Dark theme adjustments */
    [data-theme="dark"] .diagnostic-item.success {
        background: rgba(34, 197, 94, 0.1);
    }
    
    [data-theme="dark"] .diagnostic-item.error {
        background: rgba(239, 68, 68, 0.1);
    }
    
    [data-theme="dark"] .diagnostic-item.info-only {
        background: rgba(59, 130, 246, 0.1);
    }
    
    [data-theme="dark"] .diagnostic-item.success .diagnostic-title {
        color: var(--success-400);
    }
    
    [data-theme="dark"] .diagnostic-item.error .diagnostic-title {
        color: var(--danger-400);
    }
    
    [data-theme="dark"] .diagnostic-item.info-only .diagnostic-title {
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .log-error {
        color: var(--danger-400);
    }
    
    [data-theme="dark"] .log-success {
        color: var(--success-400);
    }
    
    [data-theme="dark"] .log-info {
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Botão de teste de mensagem
    const testMessageBtn = document.getElementById('testMessageBtn');
    if (testMessageBtn) {
        testMessageBtn.addEventListener('click', function() {
            const resultArea = document.getElementById('testSendResult');
            resultArea.style.display = 'block';
            resultArea.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin"></i>
                    Enviando mensagem de teste...
                </div>
            `;
            
            fetch('telegram.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=send_test&bot_token=<?php echo urlencode($settings['bot_token'] ?? ''); ?>&chat_id=<?php echo urlencode($settings['chat_id'] ?? ''); ?>'
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
            });
        });
    }
    
    // Botão de teste de banner
    const testBannerBtn = document.getElementById('testBannerBtn');
    if (testBannerBtn) {
        testBannerBtn.addEventListener('click', function() {
            const resultArea = document.getElementById('testSendResult');
            resultArea.style.display = 'block';
            resultArea.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin"></i>
                    Gerando e enviando banner de teste...
                </div>
            `;
            
            fetch('send_telegram_banners.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'banner_type=football_1'
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
            });
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>