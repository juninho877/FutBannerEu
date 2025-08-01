<?php
/**
 * 🧹 Script de limpeza automática do cache
 * 
 * EXECUÇÃO:
 * - Via cron job: 0 0 * * * /usr/bin/php /caminho/para/admin/cache_cleanup.php
 * - Via web (apenas admins): https://seusite.com/admin/cache_cleanup.php
 * - Manual: php cache_cleanup.php
 */

require_once 'classes/BannerCache.php';

// Verificar se é execução via linha de comando ou web
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    // Se for via web, verificar autenticação

    header('Content-Type: application/json');
}

try {
    $bannerCache = new BannerCache();
    
    // Verificar se é limpeza diária completa (às 00h) ou limpeza normal
    $currentHour = (int)date('H');
    $isDailyCleanup = $isCLI && $currentHour === 0; // Limpeza diária às 00h via cron
    
    // 🔥 FORÇAR LIMPEZA DIÁRIA se solicitado via parâmetro
    if (isset($_GET['daily']) && $_GET['daily'] === '1') {
        $isDailyCleanup = true;
    }
    
    if ($isDailyCleanup) {
        // 🧹 LIMPEZA DIÁRIA COMPLETA
        error_log("🧹 INICIANDO LIMPEZA DIÁRIA COMPLETA DO CACHE - " . date('Y-m-d H:i:s'));
        
        $result = $bannerCache->dailyCleanup();
        
        if ($result) {
            $message = "Limpeza diária completa realizada com sucesso";
            $logMessage = "✅ LIMPEZA DIÁRIA CONCLUÍDA - Removidos: {$result['total_removed']} arquivos";
        } else {
            $message = "Erro na limpeza diária";
            $logMessage = "❌ ERRO NA LIMPEZA DIÁRIA";
        }
        
        error_log($logMessage);
        
        $response = [
            'success' => $result !== false,
            'message' => $message,
            'type' => 'daily_cleanup',
            'details' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } else {
        // 🧽 LIMPEZA NORMAL (apenas expirados)
        $removedCount = $bannerCache->cleanExpiredCache();
        
        $response = [
            'success' => true,
            'message' => "Limpeza normal concluída com sucesso",
            'type' => 'normal_cleanup',
            'removed_files' => $removedCount,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // Obter estatísticas atualizadas
    $stats = $bannerCache->getCacheStats();
    $response['stats'] = $stats;
    
    if ($isCLI) {
        // Output para linha de comando
        echo "=== FUTBANNER CACHE CLEANUP ===\n";
        echo "Tipo: " . ($isDailyCleanup ? "LIMPEZA DIÁRIA COMPLETA" : "Limpeza Normal") . "\n";
        echo "Status: " . ($response['success'] ? "SUCESSO" : "ERRO") . "\n";
        echo "Mensagem: " . $response['message'] . "\n";
        
        if (isset($response['details'])) {
            echo "Detalhes:\n";
            echo "  - Total removido: " . $response['details']['total_removed'] . " arquivos\n";
            echo "  - Expirados: " . $response['details']['expired_removed'] . "\n";
            echo "  - Todo cache: " . $response['details']['all_cache_removed'] . "\n";
            echo "  - Antigos: " . $response['details']['old_removed'] . "\n";
        } else {
            echo "Arquivos removidos: " . $response['removed_files'] . "\n";
        }
        
        if ($stats) {
            echo "Estatísticas atuais:\n";
            echo "  - Cache válido: " . $stats['valid_cached'] . " arquivos\n";
            echo "  - Cache total: " . $stats['total_cached'] . " arquivos\n";
            if (isset($stats['total_size_mb'])) {
                echo "  - Tamanho total: " . $stats['total_size_mb'] . " MB\n";
            }
        }
        
        echo "Timestamp: " . $response['timestamp'] . "\n";
        echo "================================\n";
    } else {
        // Output JSON para web
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    $error = [
        'success' => false,
        'message' => 'Erro na limpeza do cache: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("❌ ERRO NA LIMPEZA DO CACHE: " . $e->getMessage());
    
    if ($isCLI) {
        echo "ERRO: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode($error);
    }
}
?>