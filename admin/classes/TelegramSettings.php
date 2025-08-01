<?php
require_once 'config/database.php';

class TelegramSettings {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Salvar ou atualizar configurações do Telegram para um usuário
     * @param int $userId ID do usuário
     * @param string $botToken Token do bot do Telegram
     * @param string $chatId ID do chat/grupo do Telegram
     * @param string $footballMessage Mensagem personalizada para banners de futebol
     * @param string $movieSeriesMessage Mensagem personalizada para banners de filmes/séries
     * @param string $scheduledTime Horário agendado para envio automático (formato HH:MM)
     * @param int $scheduledFootballTheme Tema de futebol para envio agendado (1, 2, 3 ou 4)
     * @param bool $scheduledDeliveryEnabled Flag para habilitar/desabilitar envio agendado
     * @return array Resultado da operação
     */
    public function saveSettings($userId, $botToken, $chatId, $footballMessage = null, $movieSeriesMessage = null, $scheduledTime = null, $scheduledFootballTheme = 1, $scheduledDeliveryEnabled = false) {
        try {
            // Validar parâmetros
            if (empty($botToken) || empty($chatId)) {
                return ['success' => false, 'message' => 'Token do bot e Chat ID são obrigatórios'];
            }
            
            // Validar formato do token (deve ter formato: 123456789:AAAA...)
            if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $botToken)) {
                return ['success' => false, 'message' => 'Formato do token inválido. Use o formato: 123456789:AAAA...'];
            }
            
            // Validar Chat ID (pode ser número negativo para grupos)
            if (!preg_match('/^-?\d+$/', $chatId)) {
                return ['success' => false, 'message' => 'Chat ID deve ser um número (positivo para chat privado, negativo para grupos)'];
            }
            
            // Validar formato do horário agendado (se fornecido)
            if (!empty($scheduledTime) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $scheduledTime)) {
                return ['success' => false, 'message' => 'Formato de horário inválido. Use o formato HH:MM (24h)'];
            }
            
            // Validar tema de futebol
            if (!in_array($scheduledFootballTheme, [1, 2, 3, 4])) {
                $scheduledFootballTheme = 1; // Valor padrão se inválido
            }
            
            // Criar tabela se não existir
            $this->createTelegramSettingsTable();
            
            $stmt = $this->db->prepare("
                INSERT INTO user_telegram_settings (
                    user_id, 
                    bot_token, 
                    chat_id, 
                    football_message, 
                    movie_series_message,
                    scheduled_time,
                    scheduled_football_theme,
                    scheduled_delivery_enabled
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                bot_token = VALUES(bot_token), 
                chat_id = VALUES(chat_id),
                football_message = VALUES(football_message),
                movie_series_message = VALUES(movie_series_message),
                scheduled_time = VALUES(scheduled_time),
                scheduled_football_theme = VALUES(scheduled_football_theme),
                scheduled_delivery_enabled = VALUES(scheduled_delivery_enabled),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $userId, 
                $botToken, 
                $chatId, 
                $footballMessage, 
                $movieSeriesMessage,
                $scheduledTime,
                $scheduledFootballTheme,
                $scheduledDeliveryEnabled ? 1 : 0
            ]);
            
            return ['success' => true, 'message' => 'Configurações do Telegram salvas com sucesso'];
        } catch (PDOException $e) {
            error_log("Erro ao salvar configurações do Telegram: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()];
        }
    }
    
    /**
     * Buscar configurações do Telegram de um usuário
     * @param int $userId ID do usuário
     * @return array|false Configurações do usuário ou false se não encontrado
     */
    public function getSettings($userId) {
        try {
            // Criar tabela se não existir
            $this->createTelegramSettingsTable();
            
            $stmt = $this->db->prepare("
                SELECT 
                    bot_token, 
                    chat_id, 
                    football_message, 
                    movie_series_message, 
                    scheduled_time,
                    scheduled_football_theme,
                    scheduled_delivery_enabled,
                    created_at, 
                    updated_at 
                FROM user_telegram_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar configurações do Telegram: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se a tabela de configurações do Telegram existe e a cria se necessário
     */
    private function createTelegramSettingsTable() {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS user_telegram_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                bot_token VARCHAR(255) NOT NULL,
                chat_id VARCHAR(50) NOT NULL,
                football_message TEXT,
                movie_series_message TEXT,
                scheduled_time VARCHAR(5),
                scheduled_football_theme INT DEFAULT 1,
                scheduled_delivery_enabled TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_telegram (user_id),
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                INDEX idx_user_telegram (user_id),
                INDEX idx_scheduled_delivery (scheduled_delivery_enabled, scheduled_time)
            );
            ";
            
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela de configurações do Telegram: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar se um usuário tem configurações do Telegram
     * @param int $userId ID do usuário
     * @return bool True se tem configurações, false caso contrário
     */
    public function hasSettings($userId) {
        $settings = $this->getSettings($userId);
        return $settings !== false;
    }
    
    /**
     * Excluir configurações do Telegram de um usuário
     * @param int $userId ID do usuário
     * @return array Resultado da operação
     */
    public function deleteSettings($userId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_telegram_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            return ['success' => true, 'message' => 'Configurações do Telegram removidas com sucesso'];
        } catch (PDOException $e) {
            error_log("Erro ao excluir configurações do Telegram: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao remover configurações: ' . $e->getMessage()];
        }
    }
    
    /**
     * Testar conexão com o bot do Telegram
     * @param string $botToken Token do bot
     * @return array Resultado do teste
     */
    public function testBotConnection($botToken) {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/getMe";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'FutBanner/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return ['success' => false, 'message' => 'Erro ao conectar com a API do Telegram'];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Erro ao decodificar resposta da API'];
            }
            
            if (!$data['ok']) {
                return ['success' => false, 'message' => 'Token inválido: ' . ($data['description'] ?? 'Erro desconhecido')];
            }
            
            $botInfo = $data['result'];
            return [
                'success' => true, 
                'message' => 'Bot conectado com sucesso',
                'bot_info' => [
                    'username' => $botInfo['username'] ?? 'N/A',
                    'first_name' => $botInfo['first_name'] ?? 'N/A',
                    'can_join_groups' => $botInfo['can_join_groups'] ?? false,
                    'can_read_all_group_messages' => $botInfo['can_read_all_group_messages'] ?? false
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro na conexão: ' . $e->getMessage()];
        }
    }
    
    /**
     * Testar envio de mensagem para um chat
     * @param string $botToken Token do bot
     * @param string $chatId ID do chat
     * @return array Resultado do teste
     */
    public function testChatConnection($botToken, $chatId) {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/getChat";
            
            $postData = http_build_query([
                'chat_id' => $chatId
            ]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postData,
                    'timeout' => 10,
                    'user_agent' => 'FutBanner/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return ['success' => false, 'message' => 'Erro ao conectar com o chat'];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Erro ao decodificar resposta da API'];
            }
            
            if (!$data['ok']) {
                return ['success' => false, 'message' => 'Chat ID inválido: ' . ($data['description'] ?? 'Erro desconhecido')];
            }
            
            $chatInfo = $data['result'];
            return [
                'success' => true, 
                'message' => 'Chat encontrado com sucesso',
                'chat_info' => [
                    'type' => $chatInfo['type'] ?? 'N/A',
                    'title' => $chatInfo['title'] ?? $chatInfo['first_name'] ?? 'Chat Privado',
                    'username' => $chatInfo['username'] ?? null
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro na conexão: ' . $e->getMessage()];
        }
    }
    
    /**
     * Enviar uma mensagem de teste
     * @param string $botToken Token do bot
     * @param string $chatId ID do chat
     * @return array Resultado do envio
     */
    public function sendTestMessage($botToken, $chatId) {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            
            $message = "🤖 Teste de conexão do FutBanner\n\n";
            $message .= "✅ Bot configurado com sucesso!\n";
            $message .= "📅 " . date('d/m/Y H:i:s') . "\n\n";
            $message .= "Agora você pode enviar seus banners diretamente para este chat.";
            
            $postData = http_build_query([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postData,
                    'timeout' => 10,
                    'user_agent' => 'FutBanner/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return ['success' => false, 'message' => 'Erro ao enviar mensagem de teste'];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Erro ao decodificar resposta da API'];
            }
            
            if (!$data['ok']) {
                return ['success' => false, 'message' => 'Erro ao enviar mensagem: ' . ($data['description'] ?? 'Erro desconhecido')];
            }
            
            return ['success' => true, 'message' => 'Mensagem de teste enviada com sucesso'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro no envio: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obter estatísticas de uso do Telegram
     * @return array Estatísticas
     */
    public function getUsageStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_users_configured,
                    COUNT(CASE WHEN updated_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_last_30_days,
                    COUNT(CASE WHEN scheduled_delivery_enabled = 1 THEN 1 END) as scheduled_delivery_users
                FROM user_telegram_settings
            ");
            $stmt->execute();
            $stats = $stmt->fetch();
            
            return $stats ?: [
                'total_users_configured' => 0,
                'active_last_30_days' => 0,
                'scheduled_delivery_users' => 0
            ];
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas do Telegram: " . $e->getMessage());
            return [
                'total_users_configured' => 0,
                'active_last_30_days' => 0,
                'scheduled_delivery_users' => 0
            ];
        }
    }
    
    /**
     * Obter usuários com envio agendado para um determinado horário
     * @param string $time Horário no formato HH:MM
     * @return array Lista de usuários com envio agendado
     */
    public function getUsersWithScheduledDelivery($time) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    bot_token,
                    chat_id,
                    football_message,
                    scheduled_football_theme
                FROM user_telegram_settings 
                WHERE scheduled_delivery_enabled = 1 
                AND scheduled_time = ?
            ");
            $stmt->execute([$time]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuários com envio agendado: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Atualizar status de envio agendado
     * @param int $userId ID do usuário
     * @param bool $enabled Status de habilitado/desabilitado
     * @return array Resultado da operação
     */
    public function updateScheduledDeliveryStatus($userId, $enabled) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_telegram_settings 
                SET scheduled_delivery_enabled = ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$enabled ? 1 : 0, $userId]);
            
            return [
                'success' => true, 
                'message' => 'Status de envio agendado ' . ($enabled ? 'ativado' : 'desativado') . ' com sucesso'
            ];
        } catch (PDOException $e) {
            error_log("Erro ao atualizar status de envio agendado: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao atualizar status: ' . $e->getMessage()];
        }
    }
}
?>