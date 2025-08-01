<?php
/**
 * 🕐 Cron Job para verificar o status de pagamentos pendentes do Mercado Pago.
 * 
 * Este script deve ser executado periodicamente (ex: a cada 5 minutos)
 * para atualizar o status de pagamentos que ainda estão como 'pending'
 * e renovar o acesso dos usuários quando o pagamento for aprovado.
 * 
 * Configuração de Cron (exemplo para execução a cada 5 minutos):
 *  *//*5 * * * * /usr/bin/php /caminho/completo/para/seu/projeto/admin/check_mp_payments_cron.php >> /caminho/completo/para/seu/projeto/admin/logs/mp_cron.log 2>&1
 */

// Configurar error reporting e logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir erros no output do cron
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/mp_cron_errors.log'); // Log de erros específicos do cron

date_default_timezone_set('America/Sao_Paulo');

// Função de log personalizada para o cron
function log_mp_cron_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    $logFile = __DIR__ . '/logs/mp_cron.log';
    // Criar diretório de logs se não existir
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

log_mp_cron_message("=== INICIANDO VERIFICAÇÃO DE PAGAMENTOS PENDENTES ===");

try {
    // Incluir classes necessárias
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/classes/MercadoPagoPayment.php';

    $db = Database::getInstance()->getConnection();
    $mercadoPagoPayment = new MercadoPagoPayment();

    // Buscar pagamentos pendentes
    // Limitar a pagamentos criados nas últimas 24 horas para evitar reprocessar pagamentos muito antigos
    // e para focar nos que ainda podem ser aprovados.
    $stmt = $db->prepare("
        SELECT preference_id, payment_id, user_id, payment_purpose, related_quantity, is_processed
        FROM mercadopago_payments
        WHERE status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendingPayments)) {
        log_mp_cron_message("Nenhum pagamento pendente encontrado para verificação.");
    } else {
        log_mp_cron_message("Encontrados " . count($pendingPayments) . " pagamentos pendentes para verificar.");
        foreach ($pendingPayments as $payment) {
            $preferenceId = $payment['preference_id'];
            $paymentId = $payment['payment_id'] ?: $preferenceId; // Usar preference_id se payment_id estiver vazio
            $userId = $payment['user_id'];
            $paymentPurpose = $payment['payment_purpose'];
            $relatedQuantity = $payment['related_quantity'];
            $isProcessed = $payment['is_processed'];
            
            log_mp_cron_message("Verificando pagamento para user_id: $userId, preference_id: $preferenceId, payment_purpose: $paymentPurpose, quantity: $relatedQuantity");
            
            $result = $mercadoPagoPayment->checkPaymentStatus($paymentId);
            
            if ($result['success']) {
                log_mp_cron_message("Status atualizado para preference_id: $preferenceId. Novo status: " . $result['status']);
                
                // O processamento (renovação de assinatura, adição de créditos) é feito automaticamente
                // dentro do método checkPaymentStatus da classe MercadoPagoPayment
            } else {
                log_mp_cron_message("Falha ao verificar preference_id: $preferenceId. Erro: " . $result['message'], 'ERROR');
            }
        }
    }

    log_mp_cron_message("=== VERIFICAÇÃO DE PAGAMENTOS CONCLUÍDA COM SUCESSO ===");

} catch (Exception $e) {
    log_mp_cron_message("ERRO FATAL no cron de pagamentos: " . $e->getMessage(), 'CRITICAL');
}
?>