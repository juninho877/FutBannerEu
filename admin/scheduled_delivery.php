<?php
/**
 * 📅 Sistema de Envio Agendado de Banners
 * 
 * Este script é executado via cron job para enviar banners automaticamente
 * nos horários agendados pelos usuários.
 * 
 * Configuração do Cron (executar a cada minuto):
 * * * * * * /usr/bin/php /caminho/para/admin/scheduled_delivery.php
 */

// Configurar error reporting e logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/scheduled_delivery.log');
date_default_timezone_set('America/Sao_Paulo');

// Criar diretório de logs se não existir
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Função de log personalizada
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Log no arquivo
    $logFile = __DIR__ . '/logs/scheduled_delivery.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Log no error_log do sistema também
    error_log("SCHEDULED_DELIVERY [$level]: $message");
}

try {
    logMessage("=== INICIANDO EXECUÇÃO DO ENVIO AGENDADO ===");
    
    // Verificar se é execução via linha de comando ou web
    $isCLI = php_sapi_name() === 'cli';
    logMessage("Modo de execução: " . ($isCLI ? "CLI" : "WEB"));
    
    if (!$isCLI) {
        // Se for via web, verificar autenticação básica ou token
        $validTokens = ['cron_token_2024', 'scheduled_delivery_token'];
        $providedToken = $_GET['token'] ?? $_POST['token'] ?? '';
        
        if (!in_array($providedToken, $validTokens)) {
            logMessage("Acesso negado - Token inválido: $providedToken", 'ERROR');
            http_response_code(403);
            die(json_encode(['error' => 'Token inválido']));
        }
        
        logMessage("Acesso via web autorizado com token válido");
        header('Content-Type: application/json');
    }
    
    // Incluir dependências
    logMessage("Carregando dependências...");
    
    require_once __DIR__ . '/classes/TelegramSettings.php';
    require_once __DIR__ . '/classes/TelegramService.php';
    require_once __DIR__ . '/classes/User.php';
    require_once __DIR__ . '/includes/banner_functions.php';
    
    logMessage("Dependências carregadas com sucesso");
    
    // Obter horário atual ou de teste
    $now = new DateTime();
    $currentTime = isset($_GET['test_time']) ? $_GET['test_time'] : $now->format('H:i');
    
    logMessage("Buscando usuários com envio agendado para o horário: $currentTime");
    
    // Inicializar classes
    $telegramSettings = new TelegramSettings();
    $telegramService = new TelegramService();
    $userClass = new User();
    
    // Buscar usuários com envio agendado para este horário
    $usersWithScheduledDelivery = $telegramSettings->getUsersWithScheduledDelivery($currentTime);
    
    if (empty($usersWithScheduledDelivery)) {
        logMessage("Nenhum usuário encontrado com envio agendado para $currentTime");
        
        if (!$isCLI) {
            echo json_encode([
                'success' => true,
                'message' => "Nenhum usuário com envio agendado para $currentTime",
                'processed_users' => 0,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        logMessage("=== EXECUÇÃO FINALIZADA (NENHUM USUÁRIO) ===");
        exit(0);
    }
    
    $totalUsers = count($usersWithScheduledDelivery);
    logMessage("Encontrados " . $totalUsers . " usuários para processamento");
    
    // Obter jogos de hoje
    $jogos = obterJogosDeHoje();
    
    if (empty($jogos)) {
        logMessage("Nenhum jogo disponível para hoje", 'WARNING');
        
        if (!$isCLI) {
            echo json_encode([
                'success' => false,
                'message' => "Nenhum jogo disponível para hoje",
                'processed_users' => 0,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        logMessage("=== EXECUÇÃO FINALIZADA (SEM JOGOS) ===");
        exit(0);
    }
    
    logMessage("Encontrados " . count($jogos) . " jogos para hoje");
    
    // Processar cada usuário
    $processedUsers = 0;
    $successUsers = 0;
    $failedUsers = 0;
    
    foreach ($usersWithScheduledDelivery as $index => $userSettings) {
        $userId = $userSettings['user_id'];
        $theme = $userSettings['scheduled_football_theme'];
        $bannerType = 'football_' . $theme;
        
        // Fixed line - using string concatenation instead of interpolation with arithmetic
        logMessage("Processando usuário ID {$userId} - Tema {$theme} - " . ($index + 1) . "/{$totalUsers}");
        
        try {
            // 🔒 VERIFICAR STATUS E EXPIRAÇÃO DO USUÁRIO
            $userData = $userClass->getUserById($userId);
            
            if (!$userData) {
                logMessage("❌ Usuário ID {$userId} não encontrado no banco de dados", 'WARNING');
                $failedUsers++;
                $processedUsers++;
                continue;
            }
            
            // Verificar se o usuário está ativo ou em trial
            if (!in_array($userData['status'], ['active', 'trial'])) {
                logMessage("❌ Usuário ID {$userId} está inativo (status: {$userData['status']})", 'WARNING');
                $failedUsers++;
                $processedUsers++;
                continue;
            }
            
            // Verificar se a conta não está expirada
            if ($userData['expires_at']) {
                $expiryDate = new DateTime($userData['expires_at']);
                $today = new DateTime();
                
                if ($expiryDate < $today) {
                    logMessage("❌ Usuário ID {$userId} ({$userData['username']}) está com conta expirada (expirou em: {$userData['expires_at']})", 'WARNING');
                    $failedUsers++;
                    $processedUsers++;
                    continue;
                }
            }
            
            logMessage("✅ Usuário ID {$userId} ({$userData['username']}) validado - Status: {$userData['status']}, Expira: " . ($userData['expires_at'] ?: 'Nunca'));
            
            // Prosseguir com o envio dos banners
            $result = $telegramService->generateAndSendBanners($userId, $bannerType, $jogos);
            
            if ($result['success']) {
                logMessage("✅ Banners enviados com sucesso para usuário ID {$userId} ({$userData['username']})");
                $successUsers++;
            } else {
                logMessage("❌ Erro ao enviar banners para usuário ID {$userId} ({$userData['username']}): " . $result['message'], 'ERROR');
                $failedUsers++;
            }
        } catch (Exception $e) {
            logMessage("❌ Exceção ao processar usuário ID {$userId}: " . $e->getMessage(), 'ERROR');
            $failedUsers++;
        }
        
        $processedUsers++;
    }
    
    logMessage("Processamento concluído: $processedUsers usuários processados, $successUsers com sucesso, $failedUsers com falha");
    
    // Resposta final
    $response = [
        'success' => true,
        'message' => "Processamento concluído",
        'processed_users' => $processedUsers,
        'success_users' => $successUsers,
        'failed_users' => $failedUsers,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    logMessage("=== EXECUÇÃO FINALIZADA COM SUCESSO ===");
    
    if (!$isCLI) {
        echo json_encode($response);
    } else {
        echo "Envio agendado processado: $successUsers com sucesso, $failedUsers com falha\n";
    }
    
} catch (Exception $e) {
    $errorMsg = "ERRO FATAL: " . $e->getMessage();
    logMessage($errorMsg, 'FATAL');
    
    if (!$isCLI) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo "ERRO: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>