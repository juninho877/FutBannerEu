<?php
require_once 'config/database.php';

class MercadoPagoSettings {
    private $db;
    
    /**
     * Construtor da classe
     * Inicializa a conexão com o banco de dados
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Salvar ou atualizar as configurações do Mercado Pago para um usuário
     * 
     * @param int $userId ID do usuário
     * @param string $accessToken Token de acesso do Mercado Pago
     * @param float $userAccessValue Valor do acesso do usuário
     * @param string $whatsappNumber Número do WhatsApp para suporte
     * @param float $discount3Months Porcentagem de desconto para 3 meses
     * @param float $discount6Months Porcentagem de desconto para 6 meses
     * @param float $discount12Months Porcentagem de desconto para 12 meses
     * @param float $creditPrice Preço por crédito
     * @param int $minCreditPurchase Compra mínima de créditos
     * @return array Resultado da operação
     */
    public function saveSettings($userId, $accessToken, $userAccessValue, $whatsappNumber = null, $discount3Months = 5.00, $discount6Months = 10.00, $discount12Months = 15.00, $creditPrice = 1.00, $minCreditPurchase = 1) {
        try {
            // Validar parâmetros
            if (empty($accessToken)) {
                return ['success' => false, 'message' => 'Token de acesso é obrigatório'];
            }
            
            if (!is_numeric($userAccessValue) || $userAccessValue <= 0) {
                return ['success' => false, 'message' => 'Valor do acesso deve ser um número positivo'];
            }
            
            // Validar descontos
            if (!is_numeric($discount3Months) || $discount3Months < 0 || $discount3Months > 100) {
                return ['success' => false, 'message' => 'Desconto para 3 meses deve ser um número entre 0 e 100'];
            }
            
            if (!is_numeric($discount6Months) || $discount6Months < 0 || $discount6Months > 100) {
                return ['success' => false, 'message' => 'Desconto para 6 meses deve ser um número entre 0 e 100'];
            }
            
            if (!is_numeric($discount12Months) || $discount12Months < 0 || $discount12Months > 100) {
                return ['success' => false, 'message' => 'Desconto para 12 meses deve ser um número entre 0 e 100'];
            }
            
            // Validar preço do crédito e compra mínima
            if (!is_numeric($creditPrice) || $creditPrice <= 0) {
                return ['success' => false, 'message' => 'Preço por crédito deve ser um número positivo'];
            }
            
            if (!is_numeric($minCreditPurchase) || $minCreditPurchase < 1) {
                return ['success' => false, 'message' => 'Compra mínima de créditos deve ser pelo menos 1'];
            }
            
            // Validar número de WhatsApp (formato básico)
            if (!empty($whatsappNumber) && !preg_match('/^\d{10,15}$/', preg_replace('/\D/', '', $whatsappNumber))) {
                return ['success' => false, 'message' => 'Número de WhatsApp inválido. Use apenas números.'];
            }
            
            // Verificar se o usuário existe
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }
            
            // Inserir ou atualizar as configurações
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_settings (
                    user_id, 
                    access_token, 
                    user_access_value, 
                    whatsapp_number, 
                    discount_3_months_percent, 
                    discount_6_months_percent, 
                    discount_12_months_percent,
                    credit_price,
                    min_credit_purchase
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token), 
                user_access_value = VALUES(user_access_value),
                whatsapp_number = VALUES(whatsapp_number),
                discount_3_months_percent = VALUES(discount_3_months_percent),
                discount_6_months_percent = VALUES(discount_6_months_percent),
                discount_12_months_percent = VALUES(discount_12_months_percent),
                credit_price = VALUES(credit_price),
                min_credit_purchase = VALUES(min_credit_purchase),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $userId, 
                $accessToken, 
                $userAccessValue,
                $whatsappNumber,
                $discount3Months,
                $discount6Months,
                $discount12Months,
                $creditPrice,
                $minCreditPurchase
            ]);
            
            return ['success' => true, 'message' => 'Configurações do Mercado Pago salvas com sucesso'];
        } catch (PDOException $e) {
            error_log("Erro ao salvar configurações do Mercado Pago: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()];
        }
    }
    
    /**
     * Buscar as configurações do Mercado Pago de um usuário
     * 
     * @param int $userId ID do usuário
     * @return array|false Configurações do usuário ou false se não encontrado
     */
    public function getSettings($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id, 
                    access_token, 
                    user_access_value,
                    whatsapp_number,
                    discount_3_months_percent,
                    discount_6_months_percent,
                    discount_12_months_percent,
                    credit_price,
                    min_credit_purchase,
                    created_at, 
                    updated_at 
                FROM mercadopago_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar configurações do Mercado Pago: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Excluir as configurações do Mercado Pago de um usuário
     * 
     * @param int $userId ID do usuário
     * @return array Resultado da operação
     */
    public function deleteSettings($userId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM mercadopago_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            return ['success' => true, 'message' => 'Configurações do Mercado Pago removidas com sucesso'];
        } catch (PDOException $e) {
            error_log("Erro ao excluir configurações do Mercado Pago: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao remover configurações: ' . $e->getMessage()];
        }
    }
    
    /**
     * Testar a conexão com a API do Mercado Pago
     * 
     * @param string $accessToken Token de acesso do Mercado Pago
     * @return array Resultado do teste
     */
    public function testConnection($accessToken) {
        try {
            $url = "https://api.mercadopago.com/users/me";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($response === false) {
                return ['success' => false, 'message' => 'Erro na conexão: ' . $error];
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                return [
                    'success' => false, 
                    'message' => 'Erro na API do Mercado Pago: ' . ($data['message'] ?? 'Erro desconhecido'),
                    'http_code' => $httpCode
                ];
            }
            
            return [
                'success' => true, 
                'message' => 'Conexão com Mercado Pago estabelecida com sucesso',
                'user_info' => [
                    'id' => $data['id'] ?? 'N/A',
                    'nickname' => $data['nickname'] ?? 'N/A',
                    'email' => $data['email'] ?? 'N/A',
                    'site_status' => $data['site_status'] ?? 'N/A',
                    'country_id' => $data['country_id'] ?? 'N/A'
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro no teste: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obter estatísticas de uso do Mercado Pago
     * 
     * @return array Estatísticas
     */
    public function getUsageStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_users_configured,
                    AVG(user_access_value) as avg_access_value,
                    MIN(user_access_value) as min_access_value,
                    MAX(user_access_value) as max_access_value
                FROM mercadopago_settings
            ");
            $stmt->execute();
            $stats = $stmt->fetch();
            
            return $stats ?: [
                'total_users_configured' => 0,
                'avg_access_value' => 0,
                'min_access_value' => 0,
                'max_access_value' => 0
            ];
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas do Mercado Pago: " . $e->getMessage());
            return [
                'total_users_configured' => 0,
                'avg_access_value' => 0,
                'min_access_value' => 0,
                'max_access_value' => 0
            ];
        }
    }
    
    /**
     * Validar token de acesso do Mercado Pago
     * 
     * @param string $token Token a ser validado
     * @return bool True se o token for válido
     */
    public static function validateAccessToken($token) {
        if (empty($token)) {
            return false;
        }
        
        // Formato básico do token: APP_USR-XXXXXXXXXXXX-XXXXXX-XXXXXXXXX-XXXXXXXXX
        if (!preg_match('/^(APP_USR|TEST)-[A-Za-z0-9_-]+$/', $token)) {
            return false;
        }
        
        return true;
    }
}
?>