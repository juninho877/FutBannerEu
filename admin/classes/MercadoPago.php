<?php
require_once 'config/database.php';
require_once 'MercadoPagoSettings.php';

class MercadoPago {
    private $db;
    private $mercadoPagoSettings;
    
    /**
     * Construtor da classe
     * Inicializa a conexão com o banco de dados e as configurações do Mercado Pago
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->mercadoPagoSettings = new MercadoPagoSettings();
        $this->createPaymentTable();
    }
    
    /**
     * Criar tabela de pagamentos se não existir
     */
    private function createPaymentTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS mercadopago_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            payment_id VARCHAR(255),
            preference_id VARCHAR(255) NOT NULL,
            external_reference VARCHAR(255),
            status VARCHAR(50) NOT NULL,
            status_detail VARCHAR(255),
            payment_method VARCHAR(50),
            payment_type VARCHAR(50),
            transaction_amount DECIMAL(10, 2) NOT NULL,
            payment_purpose VARCHAR(50) DEFAULT 'subscription',
            related_quantity INT DEFAULT 1,
            is_processed BOOLEAN DEFAULT FALSE,
            payment_date TIMESTAMP,
            owner_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (owner_user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_preference_id (preference_id),
            INDEX idx_payment_id (payment_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        );
        ";
        
        try {
            $this->db->exec($sql);
            
            // Check if owner_user_id column exists, add it if not
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mercadopago_payments' AND COLUMN_NAME = 'owner_user_id'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                $this->db->exec("
                    ALTER TABLE mercadopago_payments 
                    ADD COLUMN owner_user_id INT NULL AFTER payment_date,
                    ADD FOREIGN KEY (owner_user_id) REFERENCES usuarios(id) ON DELETE SET NULL
                ");
            }
        } catch (PDOException $e) {
            // Silently handle error
        }
    }
    
    /**
     * Criar um pagamento para assinatura no Mercado Pago
     * 
     * @param int $userId ID do usuário
     * @param float $amount Valor do pagamento
     * @param int $months Número de meses de assinatura
     * @param int|null $ownerUserId ID do usuário dono do pagamento (admin ou master)
     * @return array Resultado da operação
     */
    public function createSubscriptionPayment($userId, $amount, $months = 1, $ownerUserId = null) {
        try {
            // Determine the owner of the payment (who receives the money)
            if ($ownerUserId === null) {
                // If no owner specified, check if user has a parent (master)
                $stmt = $this->db->prepare("SELECT parent_user_id FROM usuarios WHERE id = ?");
                $stmt->execute([$userId]);
                $parentId = $stmt->fetchColumn();
                
                // If user has a parent, use parent's Mercado Pago settings
                // Otherwise, use admin (ID 1) settings
                $ownerUserId = $parentId ?: 1;
            }
            
            // Buscar configurações do dono do pagamento (admin ou master)
            $ownerSettings = $this->mercadoPagoSettings->getSettings($ownerUserId);
            
            if (!$ownerSettings || empty($ownerSettings['access_token'])) {
                return [
                    'success' => false, 
                    'message' => 'Configurações do Mercado Pago não encontradas'
                ];
            }
            
            $accessToken = $ownerSettings['access_token'];
            
            // Buscar dados do usuário
            $stmt = $this->db->prepare("SELECT username, email FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            if (!$userData) {
                return [
                    'success' => false, 
                    'message' => 'Usuário não encontrado'
                ];
            }
            
            $username = $userData['username'];
            $userEmail = $userData['email'] ?? "usuario{$userId}@futbanner.com";
            
            // Criar referência externa única
            $externalReference = "USER_{$userId}_" . time();
            
            // Descrição do pagamento
            $description = "Assinatura FutBanner - {$months} " . ($months > 1 ? "meses" : "mês") . " - Usuário: {$username}";
            
            // Criar pagamento Pix
            $url = "https://api.mercadopago.com/v1/payments";
            
            $paymentData = [
                "transaction_amount" => floatval($amount),
                "description" => $description,
                "external_reference" => $externalReference,
                "payment_method_id" => "pix",
                "payer" => [
                    "email" => $userEmail,
                    "first_name" => $username,
                    "last_name" => "FutBanner"
                ],
                "date_of_expiration" => date('Y-m-d\TH:i:s.000P', strtotime('+1 day'))
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($paymentData),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_VERBOSE => true,
                CURLOPT_STDERR => $verbose = fopen('php://temp', 'w+')
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($response === false) {
                return [
                    'success' => false, 
                    'message' => 'Erro na conexão com o Mercado Pago: ' . $error
                ];
            }
            
            $payment = json_decode($response, true);
            
            if ($httpCode !== 201) {
                $errorMessage = isset($payment['message']) ? $payment['message'] : 'Erro desconhecido';
                return [
                    'success' => false, 
                    'message' => 'Erro ao criar pagamento: ' . $errorMessage,
                    'http_code' => $httpCode
                ];
            }
            
            if (!isset($payment['id']) || !isset($payment['point_of_interaction']['transaction_data']['qr_code_base64'])) {
                return [
                    'success' => false, 
                    'message' => 'Resposta inválida do Mercado Pago'
                ];
            }
            
            // Extrair dados do QR Code
            $qrCodeBase64 = $payment['point_of_interaction']['transaction_data']['qr_code_base64'];
            $qrCode = "data:image/png;base64," . $qrCodeBase64;
            $pixCode = $payment['point_of_interaction']['transaction_data']['qr_code'] ?? '';
            
            // Registrar o pagamento no banco de dados
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_payments 
                (user_id, payment_id, preference_id, external_reference, status, transaction_amount, payment_purpose, related_quantity, owner_user_id) 
                VALUES (?, ?, ?, ?, ?, ?, 'subscription', ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $payment['id'],
                $payment['id'], // Usando payment_id como preference_id
                $externalReference,
                $payment['status'],
                $amount,
                $months,
                $ownerUserId
            ]);
            
            $result = [
                'success' => true,
                'payment_id' => $payment['id'],
                'qr_code' => $qrCode,
                'pix_code' => $pixCode,
                'amount' => $amount
            ];
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false, 
                'message' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar um pagamento para compra de créditos no Mercado Pago
     * 
     * @param int $userId ID do usuário
     * @param string $description Descrição do pagamento
     * @param float $amount Valor do pagamento
     * @param int $credits Quantidade de créditos sendo comprados
     * @param int|null $ownerUserId ID do usuário dono do pagamento (admin)
     * @return array Resultado da operação
     */
    public function createCreditPayment($userId, $description, $amount, $credits = 1, $ownerUserId = null) {
        try {
            // For credit purchases, the owner is always admin (ID 1) if not specified
            if ($ownerUserId === null) {
                $ownerUserId = 1; // Admin ID
            }
            
            // Buscar configurações do admin
            $ownerSettings = $this->mercadoPagoSettings->getSettings($ownerUserId);
            
            if (!$ownerSettings || empty($ownerSettings['access_token'])) {
                return [
                    'success' => false, 
                    'message' => 'Configurações do Mercado Pago não encontradas'
                ];
            }
            
            $accessToken = $ownerSettings['access_token'];
            
            // Buscar dados do usuário
            $stmt = $this->db->prepare("SELECT username, email FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            if (!$userData) {
                return [
                    'success' => false, 
                    'message' => 'Usuário não encontrado'
                ];
            }
            
            $username = $userData['username'];
            $userEmail = $userData['email'] ?? "usuario{$userId}@futbanner.com";
            
            // Criar referência externa única
            $externalReference = "CREDIT_{$userId}_" . time();
            
            // Criar pagamento Pix
            $url = "https://api.mercadopago.com/v1/payments";
            
            $paymentData = [
                "transaction_amount" => floatval($amount),
                "description" => $description,
                "external_reference" => $externalReference,
                "payment_method_id" => "pix",
                "payer" => [
                    "email" => $userEmail,
                    "first_name" => $username,
                    "last_name" => "FutBanner"
                ],
                "date_of_expiration" => date('Y-m-d\TH:i:s.000P', strtotime('+1 day'))
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($paymentData),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_VERBOSE => true,
                CURLOPT_STDERR => $verbose = fopen('php://temp', 'w+')
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($response === false) {
                return [
                    'success' => false, 
                    'message' => 'Erro na conexão com o Mercado Pago: ' . $error
                ];
            }
            
            $payment = json_decode($response, true);
            
            if ($httpCode !== 201) {
                $errorMessage = isset($payment['message']) ? $payment['message'] : 'Erro desconhecido';
                return [
                    'success' => false, 
                    'message' => 'Erro ao criar pagamento: ' . $errorMessage,
                    'http_code' => $httpCode
                ];
            }
            
            if (!isset($payment['id']) || !isset($payment['point_of_interaction']['transaction_data']['qr_code_base64'])) {
                return [
                    'success' => false, 
                    'message' => 'Resposta inválida do Mercado Pago'
                ];
            }
            
            // Extrair dados do QR Code
            $qrCodeBase64 = $payment['point_of_interaction']['transaction_data']['qr_code_base64'];
            $qrCode = "data:image/png;base64," . $qrCodeBase64;
            $pixCode = $payment['point_of_interaction']['transaction_data']['qr_code'] ?? '';
            
            // Registrar o pagamento no banco de dados
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_payments 
                (user_id, payment_id, preference_id, external_reference, status, transaction_amount, payment_purpose, related_quantity, owner_user_id) 
                VALUES (?, ?, ?, ?, ?, ?, 'credit_purchase', ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $payment['id'],
                $payment['id'], // Usando payment_id como preference_id
                $externalReference,
                $payment['status'],
                $amount,
                $credits,
                $ownerUserId
            ]);
            
            $result = [
                'success' => true,
                'payment_id' => $payment['id'],
                'qr_code' => $qrCode,
                'pix_code' => $pixCode,
                'amount' => $amount
            ];
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false, 
                'message' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status de um pagamento
     * 
     * @param string $paymentId ID do pagamento
     * @return array Resultado da operação
     */
    public function checkPaymentStatus($paymentId) {
        try {
            // First, get the owner_user_id from the payment record
            $stmt = $this->db->prepare("
                SELECT owner_user_id 
                FROM mercadopago_payments 
                WHERE payment_id = ? OR preference_id = ?
            ");
            $stmt->execute([$paymentId, $paymentId]);
            $paymentRecord = $stmt->fetch();
            
            $ownerUserId = $paymentRecord ? $paymentRecord['owner_user_id'] : 1; // Default to admin if not found
            
            // Get the Mercado Pago settings for the owner
            $ownerSettings = $this->mercadoPagoSettings->getSettings($ownerUserId);
            
            if (!$ownerSettings || empty($ownerSettings['access_token'])) {
                return [
                    'success' => false, 
                    'message' => 'Configurações do Mercado Pago não encontradas'
                ];
            }
            
            $accessToken = $ownerSettings['access_token'];
            
            // Buscar pagamento diretamente pelo ID
            $url = "https://api.mercadopago.com/v1/payments/{$paymentId}";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_VERBOSE => true,
                CURLOPT_STDERR => $verbose = fopen('php://temp', 'w+')
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($response === false) {
                return [
                    'success' => false, 
                    'message' => 'Erro na conexão com o Mercado Pago: ' . $error
                ];
            }
            
            if ($httpCode !== 200) {
                return [
                    'success' => false, 
                    'message' => 'Erro ao buscar pagamento: ' . $response,
                    'http_code' => $httpCode
                ];
            }
            
            $payment = json_decode($response, true);
            
            return [
                'success' => true,
                'payment' => $payment
            ];
        } catch (Exception $e) {
            return [
                'success' => false, 
                'message' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter histórico de pagamentos de um usuário ou lista de usuários
     * 
     * @param int|array $userIds ID do usuário ou array de IDs de usuários
     * @param int $limit Limite de registros
     * @return array Lista de pagamentos
     */
    public function getUserPaymentHistory($userIds, $limit = 5) {
        try {
            // Verificar se é um único ID ou um array de IDs
            $isArray = is_array($userIds);
            
            if ($isArray && empty($userIds)) {
                return [];
            }
            
            // Construir a consulta SQL com base no tipo de entrada
            if ($isArray) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $sql = "
                    SELECT 
                        id,
                        user_id,
                        payment_id,
                        preference_id,
                        status,
                        transaction_amount,
                        payment_purpose,
                        related_quantity,
                        payment_date,
                        owner_user_id,
                        created_at
                    FROM mercadopago_payments 
                    WHERE user_id IN ({$placeholders})
                    ORDER BY created_at DESC
                    LIMIT ?
                ";
                $params = array_merge($userIds, [$limit]);
            } else {
                $sql = "
                    SELECT 
                        id,
                        user_id,
                        payment_id,
                        preference_id,
                        status,
                        transaction_amount,
                        payment_purpose,
                        related_quantity,
                        payment_date,
                        owner_user_id,
                        created_at
                    FROM mercadopago_payments 
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT ?
                ";
                $params = [$userIds, $limit];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }
}
?>