<?php
/**
 * 🤖 Classe para integração com Telegram Bot API
 * 
 * Esta classe gerencia o envio de mensagens e arquivos via Telegram
 */

class TelegramBot {
    private $botToken;
    private $chatId;
    private $apiUrl;
    
    public function __construct($botToken = null, $chatId = null) {
        if ($botToken) {
            $this->setBotToken($botToken);
        }
        if ($chatId) {
            $this->setChatId($chatId);
        }
    }
    
    /**
     * Definir token do bot
     */
    public function setBotToken($token) {
        $this->botToken = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
        return $this;
    }
    
    /**
     * Definir chat ID
     */
    public function setChatId($chatId) {
        $this->chatId = $chatId;
        return $this;
    }
    
    /**
     * Enviar mensagem de texto
     */
    public function sendMessage($message, $parseMode = 'HTML') {
        if (!$this->botToken || !$this->chatId) {
            return ['success' => false, 'error' => 'Bot token ou chat ID não configurados'];
        }
        
        $data = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => $parseMode
        ];
        
        return $this->makeRequest('sendMessage', $data);
    }
    
    /**
     * Enviar foto/imagem
     */
    public function sendPhoto($photoPath, $caption = '', $filename = null) {
        if (!$this->botToken || !$this->chatId) {
            return ['success' => false, 'error' => 'Bot token ou chat ID não configurados'];
        }
        
        if (!file_exists($photoPath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado: ' . $photoPath];
        }
        
        // Verificar tamanho do arquivo (máximo 50MB para Telegram)
        $fileSize = filesize($photoPath);
        if ($fileSize > 50 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Arquivo muito grande (máximo 50MB)'];
        }
        
        $filename = $filename ?: basename($photoPath);
        
        $data = [
            'chat_id' => $this->chatId,
            'photo' => new CURLFile($photoPath, mime_content_type($photoPath), $filename)
        ];
        
        if (!empty($caption)) {
            $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
        }
        
        return $this->makeRequest('sendPhoto', $data, true);
    }
    
    /**
     * Enviar documento
     */
    public function sendDocument($documentPath, $caption = '', $filename = null) {
        if (!$this->botToken || !$this->chatId) {
            return ['success' => false, 'error' => 'Bot token ou chat ID não configurados'];
        }
        
        if (!file_exists($documentPath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado: ' . $documentPath];
        }
        
        $filename = $filename ?: basename($documentPath);
        
        $data = [
            'chat_id' => $this->chatId,
            'document' => new CURLFile($documentPath, mime_content_type($documentPath), $filename)
        ];
        
        if (!empty($caption)) {
            $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
        }
        
        return $this->makeRequest('sendDocument', $data, true);
    }
    
    /**
     * Testar conexão com o bot
     */
    public function testConnection() {
        if (!$this->botToken) {
            return ['success' => false, 'error' => 'Bot token não configurado'];
        }
        
        $result = $this->makeRequest('getMe');
        
        if ($result['success']) {
            return [
                'success' => true,
                'bot_info' => $result['data']['result'] ?? null
            ];
        }
        
        return $result;
    }
    
    /**
     * Obter informações do chat
     */
    public function getChatInfo() {
        if (!$this->botToken || !$this->chatId) {
            return ['success' => false, 'error' => 'Bot token ou chat ID não configurados'];
        }
        
        $data = ['chat_id' => $this->chatId];
        return $this->makeRequest('getChat', $data);
    }
    
    /**
     * Fazer requisição para a API do Telegram
     */
    private function makeRequest($method, $data = [], $isMultipart = false) {
        if (!$this->apiUrl) {
            return ['success' => false, 'error' => 'URL da API não configurada'];
        }
        
        $url = $this->apiUrl . $method;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FutBanner Telegram Bot/1.0'
        ]);
        
        // Para upload de arquivos, não definir Content-Type
        if (!$isMultipart) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Erro cURL: ' . $error
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => "HTTP Error: $httpCode",
                'response' => $response
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Erro ao decodificar resposta JSON',
                'response' => $response
            ];
        }
        
        if (!isset($decodedResponse['ok']) || !$decodedResponse['ok']) {
            return [
                'success' => false,
                'error' => $decodedResponse['description'] ?? 'Erro desconhecido da API',
                'error_code' => $decodedResponse['error_code'] ?? null
            ];
        }
        
        return [
            'success' => true,
            'data' => $decodedResponse,
            'message_id' => $decodedResponse['result']['message_id'] ?? null
        ];
    }
    
    /**
     * Validar token do bot
     */
    public static function validateBotToken($token) {
        if (empty($token)) {
            return false;
        }
        
        // Formato básico do token: número:string
        if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validar chat ID
     */
    public static function validateChatId($chatId) {
        if (empty($chatId)) {
            return false;
        }
        
        // Chat ID pode ser número positivo, negativo ou string começando com @
        if (is_numeric($chatId) || (is_string($chatId) && strpos($chatId, '@') === 0)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Formatar mensagem com informações do banner
     */
    public static function formatBannerMessage($bannerName, $userMessage = '', $additionalInfo = []) {
        $message = "🎨 <b>Novo Banner Gerado!</b>\n\n";
        
        if (!empty($userMessage)) {
            $message .= "📝 <b>Mensagem:</b> " . htmlspecialchars($userMessage) . "\n\n";
        }
        
        $message .= "📄 <b>Arquivo:</b> " . htmlspecialchars($bannerName) . "\n";
        
        if (!empty($additionalInfo['type'])) {
            $message .= "🎯 <b>Tipo:</b> " . htmlspecialchars($additionalInfo['type']) . "\n";
        }
        
        if (!empty($additionalInfo['theme'])) {
            $message .= "🎨 <b>Tema:</b> " . htmlspecialchars($additionalInfo['theme']) . "\n";
        }
        
        $message .= "⏰ <b>Gerado em:</b> " . date('d/m/Y H:i:s') . "\n";
        
        $message .= "\n🤖 <i>Enviado automaticamente pelo FutBanner</i>";
        
        return $message;
    }
}
?>