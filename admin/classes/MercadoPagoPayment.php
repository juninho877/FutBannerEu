<?php
require_once 'config/database.php';
require_once 'MercadoPagoSettings.php';

class MercadoPagoPayment {
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
     * Criar um pagamento Pix no Mercado Pago
     * 
     * @param int $userId ID do usuário
     * @param string $description Descrição do pagamento
     * @param float $amount Valor do pagamento
     * @return array Resultado da operação
     */
    public function createPixPayment($userId, $description, $amount) {
        try {
            // First, determine the owner of the payment
            $stmt = $this->db->prepare("SELECT parent_user_id FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $parentId = $stmt->fetchColumn();
            
            // If user has a parent, use parent's Mercado Pago settings
            // Otherwise, use admin (ID 1) settings
            $ownerUserId = $parentId ?: 1;
            
            // Buscar configurações do dono do pagamento
            $ownerSettings = $this->mercadoPagoSettings->getSettings($ownerUserId);
            
            if (!$ownerSettings || empty($ownerSettings['access_token'])) {
                return [
                    'success' => false, 
                    'message' => 'Configurações do Mercado Pago não encontradas. Configure primeiro em Mercado Pago > Configurações.'
                ];
            }
            
            $accessToken = $ownerSettings['access_token'];
            
            // Buscar dados do usuário
            $stmt = $this->db->prepare("SELECT email FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            $userEmail = $userData['email'] ?? "usuario{$userId}@futbanner.com";
            
            // Criar referência externa única
            $externalReference = "USER_{$userId}_" . time();
            
            // Criar pagamento Pix
            $url = "https://api.mercadopago.com/v1/payments";
            
            $paymentData = [
                "transaction_amount" => floatval($amount),
                "description" => $description,
                "external_reference" => $externalReference,
                "payment_method_id" => "pix",
                "payer" => [
                    "email" => $userEmail,
                    "first_name" => "Usuario",
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
                CURLOPT_SSL_VERIFYPEER => true
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
            
            if ($httpCode !== 201) {
                return [
                    'success' => false, 
                    'message' => 'Erro ao criar pagamento: ' . $response,
                    'http_code' => $httpCode
                ];
            }
            
            $payment = json_decode($response, true);
            
            if (!isset($payment['id'])) {
                return [
                    'success' => false, 
                    'message' => 'Resposta inválida do Mercado Pago'
                ];
            }
            
            // Extrair dados do QR Code
            $qrCodeBase64 = $payment['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
            $qrCode = $payment['point_of_interaction']['transaction_data']['qr_code'] ?? '';
            
            if (empty($qrCodeBase64) || empty($qrCode)) {
                return [
                    'success' => false,
                    'message' => 'Erro ao obter QR Code do Mercado Pago'
                ];
            }
            
            // Registrar o pagamento no banco de dados
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_payments 
                (user_id, payment_id, preference_id, external_reference, status, transaction_amount, payment_purpose, related_quantity, owner_user_id) 
                VALUES (?, ?, ?, ?, 'pending', ?, 'subscription', 1, ?)
            ");
            
            $stmt->execute([
                $userId,
                $payment['id'], // payment_id
                $payment['id'], // preference_id (usando payment_id como solução temporária)
                $externalReference,
                $amount,
                $ownerUserId
            ]);
            
            return [
                'success' => true,
                'preference_id' => $payment['id'], // Retorna o payment_id como preference_id para uso no payment.php
                'external_reference' => $externalReference,
                'qr_code_base64' => $qrCodeBase64,
                'qr_code' => $qrCode,
                'amount' => $amount
            ];
            
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
     * @param string $preferenceId ID da preferência de pagamento (ou payment_id no caso de Pix)
     * @return array Resultado da operação
     */
    public function checkPaymentStatus($preferenceId) {
        try {
            // First, get the owner_user_id from the payment record
            $stmt = $this->db->prepare("
                SELECT owner_user_id, user_id, payment_purpose, related_quantity, is_processed 
                FROM mercadopago_payments 
                WHERE payment_id = ? OR preference_id = ?
            ");
            $stmt->execute([$preferenceId, $preferenceId]);
            $paymentRecord = $stmt->fetch();
            
            if (!$paymentRecord) {
                return [
                    'success' => false, 
                    'message' => 'Pagamento não encontrado no sistema'
                ];
            }
            
            $ownerUserId = $paymentRecord['owner_user_id'] ?: 1; // Default to admin if not set
            
            // Buscar configurações do dono do pagamento
            $ownerSettings = $this->mercadoPagoSettings->getSettings($ownerUserId);
            
            if (!$ownerSettings || empty($ownerSettings['access_token'])) {
                return [
                    'success' => false, 
                    'message' => 'Configurações do Mercado Pago não encontradas'
                ];
            }
            
            $accessToken = $ownerSettings['access_token'];
            
            // Buscar pagamento diretamente pelo ID
            $url = "https://api.mercadopago.com/v1/payments/{$preferenceId}";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken
                ],
                CURLOPT_SSL_VERIFYPEER => true
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
            
            $userId = $paymentRecord['user_id'];
            $paymentPurpose = $paymentRecord['payment_purpose'];
            $relatedQuantity = $paymentRecord['related_quantity'];
            $isProcessed = $paymentRecord['is_processed'];
            
            // Atualizar o status do pagamento no banco de dados
            $stmt = $this->db->prepare("
                UPDATE mercadopago_payments 
                SET 
                    status = ?, 
                    status_detail = ?, 
                    payment_method = ?, 
                    payment_type = ?, 
                    payment_date = ?
                WHERE payment_id = ? OR preference_id = ?
            ");
            
            $stmt->execute([
                $payment['status'],
                $payment['status_detail'] ?? null,
                $payment['payment_method_id'] ?? null,
                $payment['payment_type_id'] ?? null,
                date('Y-m-d H:i:s', strtotime($payment['date_approved'] ?? $payment['date_created'])),
                $preferenceId,
                $preferenceId
            ]);
            
            // Verificar se o pagamento foi aprovado e ainda não foi processado
            if ($payment['status'] === 'approved' && !$isProcessed) {
                // Processar com base no tipo de pagamento
                if ($paymentPurpose === 'subscription') {
                    // Renovar acesso do usuário
                    $this->renewUserAccess($userId, $relatedQuantity);
                } elseif ($paymentPurpose === 'credit_purchase') {
                    // Adicionar créditos ao usuário
                    require_once 'User.php';
                    $user = new User();
                    $user->purchaseCredits($userId, $relatedQuantity, $preferenceId);
                }
                
                // Marcar como processado
                $stmt = $this->db->prepare("
                    UPDATE mercadopago_payments 
                    SET is_processed = TRUE
                    WHERE payment_id = ? OR preference_id = ?
                ");
                $stmt->execute([$preferenceId, $preferenceId]);
            }
            
            return [
                'success' => true,
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'] ?? null,
                'payment_method' => $payment['payment_method_id'] ?? null,
                'payment_type' => $payment['payment_type_id'] ?? null,
                'date' => $payment['date_approved'] ?? $payment['date_created'],
                'payment_purpose' => $paymentPurpose,
                'related_quantity' => $relatedQuantity,
                'is_processed' => $isProcessed
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false, 
                'message' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Renovar acesso do usuário
     * 
     * @param int $userId ID do usuário
     * @return bool Sucesso da operação
     */
    public function renewUserAccess($userId, $months = 1) {
        try {
            // Buscar dados atuais do usuário
            $stmt = $this->db->prepare("
                SELECT expires_at FROM usuarios 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            // Calcular nova data de expiração
            $newExpiryDate = new DateTime();
            
            // Se o usuário já tem uma data de expiração e ela é futura, adicionar 30 dias a partir dela
            if ($userData && !empty($userData['expires_at'])) {
                $currentExpiry = new DateTime($userData['expires_at']);
                $today = new DateTime();
                
                if ($currentExpiry > $today) {
                    $newExpiryDate = $currentExpiry;
                }
            }
            
            // Adicionar meses
            $newExpiryDate->modify("+{$months} months");
            
            // Atualizar usuário
            $stmt = $this->db->prepare("
                UPDATE usuarios 
                SET 
                    status = 'active',
                    expires_at = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $newExpiryDate->format('Y-m-d'),
                $userId
            ]);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obter histórico de pagamentos de um usuário
     * 
     * @param int $userId ID do usuário
     * @param int $limit Limite de registros
     * @return array Lista de pagamentos
     */
    public function getUserPaymentHistory($userId, $limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    payment_id,
                    preference_id,
                    status,
                    transaction_amount,
                    payment_date,
                    created_at
                FROM mercadopago_payments 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obter estatísticas de pagamentos
     * 
     * @return array Estatísticas
     */
    public function getPaymentStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_payments,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_payments,
                    SUM(CASE WHEN status = 'approved' THEN transaction_amount ELSE 0 END) as total_amount,
                    COUNT(DISTINCT user_id) as total_users
                FROM mercadopago_payments
            ");
            
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            return [
                'total_payments' => 0,
                'approved_payments' => 0,
                'pending_payments' => 0,
                'rejected_payments' => 0,
                'total_amount' => 0,
                'total_users' => 0
            ];
        }
    }
    
    /**
     * Processar notificação de pagamento (webhook)
     * 
     * @param array $data Dados da notificação
     * @return array Resultado do processamento
     */
    public function processPaymentNotification($data) {
        try {
            // Verificar se é uma notificação de pagamento
            if (!isset($data['action']) || $data['action'] !== 'payment.created') {
                return [
                    'success' => false,
                    'message' => 'Tipo de notificação não suportado'
                ];
            }
            
            // Obter detalhes do pagamento
            $paymentId = $data['data']['id'];
            
            // First, check if this payment already exists in our database
            $stmt = $this->db->prepare("
                SELECT owner_user_id, user_id, payment_purpose, related_quantity, is_processed 
                FROM mercadopago_payments 
                WHERE payment_id = ? OR preference_id = ?
            ");
            $stmt->execute([$paymentId, $paymentId]);
            $paymentRecord = $stmt->fetch();
            
            // If payment exists, use the owner_user_id from the record
            if ($paymentRecord) {
                $ownerUserId = $paymentRecord['owner_user_id'] ?: 1; // Default to admin if not set
            } else {
                // If payment doesn't exist yet, we'll need to determine the owner later
                $ownerUserId = 1; // Default to admin
            }
            
            // Buscar configurações do dono do pagamento
            $ownerSettings = $this->mercadoPagoSettings->getSettings($ownerUserId);
            
            if (!$ownerSettings || empty($ownerSettings['access_token'])) {
                return [
                    'success' => false, 
                    'message' => 'Configurações do Mercado Pago não encontradas'
                ];
            }
            
            $accessToken = $ownerSettings['access_token'];
            
            // Obter detalhes do pagamento
            $url = "https://api.mercadopago.com/v1/payments/{$paymentId}";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken
                ],
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return [
                    'success' => false, 
                    'message' => 'Erro ao obter detalhes do pagamento',
                    'http_code' => $httpCode
                ];
            }
            
            $payment = json_decode($response, true);
            
            if (!isset($payment['preference_id'])) {
                return [
                    'success' => false, 
                    'message' => 'Resposta inválida do Mercado Pago'
                ];
            }
            
            // If payment doesn't exist in our database yet, try to determine the owner and user
            if (!$paymentRecord) {
                // Pagamento não encontrado, tentar buscar pelo external_reference
                if (isset($payment['external_reference'])) {
                    $externalRef = $payment['external_reference'];
                    
                    // Determinar o tipo de pagamento com base na referência
                    $paymentPurpose = 'subscription';
                    $relatedQuantity = 1;
                    
                    // Formato esperado: USER_123_1234567890 ou CREDIT_123_1234567890
                    if (preg_match('/^USER_(\d+)_/', $externalRef, $matches)) {
                        $userId = $matches[1];
                        $paymentPurpose = 'subscription';
                        
                        // Determine the owner (parent user or admin)
                        $stmt = $this->db->prepare("SELECT parent_user_id FROM usuarios WHERE id = ?");
                        $stmt->execute([$userId]);
                        $parentId = $stmt->fetchColumn();
                        $ownerUserId = $parentId ?: 1; // Use parent if exists, otherwise admin
                        
                    } elseif (preg_match('/^CREDIT_(\d+)_/', $externalRef, $matches)) {
                        $userId = $matches[1];
                        $paymentPurpose = 'credit_purchase';
                        $ownerUserId = 1; // Credit purchases always go to admin
                    } else {
                        return [
                            'success' => false, 
                            'message' => 'Referência externa inválida'
                        ];
                    }
                    
                    // Registrar o pagamento
                    $stmt = $this->db->prepare("
                        INSERT INTO mercadopago_payments 
                        (user_id, payment_id, preference_id, external_reference, status, status_detail, payment_method, payment_type, transaction_amount, payment_purpose, related_quantity, payment_date, owner_user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $userId,
                        $payment['id'],
                        $payment['preference_id'],
                        $payment['external_reference'],
                        $payment['status'],
                        $payment['status_detail'] ?? null,
                        $payment['payment_method_id'] ?? null,
                        $payment['payment_type_id'] ?? null,
                        $payment['transaction_amount'],
                        $paymentPurpose,
                        $relatedQuantity,
                        date('Y-m-d H:i:s', strtotime($payment['date_approved'] ?? $payment['date_created'])),
                        $ownerUserId
                    ]);
                    
                    // Se o pagamento foi aprovado, processar
                    if ($payment['status'] === 'approved') {
                        if ($paymentPurpose === 'subscription') {
                            $this->renewUserAccess($userId, $relatedQuantity);
                        } elseif ($paymentPurpose === 'credit_purchase') {
                            require_once 'User.php';
                            $user = new User();
                            $user->purchaseCredits($userId, $relatedQuantity, $payment['id']);
                        }
                        
                        // Marcar como processado
                        $stmt = $this->db->prepare("
                            UPDATE mercadopago_payments 
                            SET is_processed = TRUE
                            WHERE payment_id = ?
                        ");
                        $stmt->execute([$payment['id']]);
                    }
                    
                    return [
                        'success' => true,
                        'message' => 'Pagamento registrado com sucesso',
                        'status' => $payment['status'],
                        'user_id' => $userId,
                        'payment_purpose' => $paymentPurpose
                    ];
                }
                
                return [
                    'success' => false, 
                    'message' => 'Pagamento não encontrado no sistema'
                ];
            }
            
            $userId = $paymentRecord['user_id'];
            $paymentPurpose = $paymentRecord['payment_purpose'];
            $relatedQuantity = $paymentRecord['related_quantity'];
            $isProcessed = $paymentRecord['is_processed'];
            
            // Atualizar o status do pagamento
            $stmt = $this->db->prepare("
                UPDATE mercadopago_payments 
                SET 
                    payment_id = ?, 
                    status = ?, 
                    status_detail = ?, 
                    payment_method = ?, 
                    payment_type = ?, 
                    payment_date = ?
                WHERE preference_id = ? OR payment_id = ?
            ");
            
            $stmt->execute([
                $payment['id'],
                $payment['status'],
                $payment['status_detail'] ?? null,
                $payment['payment_method_id'] ?? null,
                $payment['payment_type_id'] ?? null,
                date('Y-m-d H:i:s', strtotime($payment['date_approved'] ?? $payment['date_created'])),
                $payment['preference_id'],
                $payment['id']
            ]);
            
            // Se o pagamento foi aprovado e ainda não foi processado, processar
            if ($payment['status'] === 'approved' && !$isProcessed) {
                if ($paymentPurpose === 'subscription') {
                    // Renovar acesso do usuário
                    $this->renewUserAccess($userId, $relatedQuantity);
                } elseif ($paymentPurpose === 'credit_purchase') {
                    // Adicionar créditos ao usuário
                    require_once 'User.php';
                    $user = new User();
                    $user->purchaseCredits($userId, $relatedQuantity, $payment['id']);
                }
                
                // Marcar como processado
                $stmt = $this->db->prepare("
                    UPDATE mercadopago_payments 
                    SET is_processed = TRUE
                    WHERE preference_id = ? OR payment_id = ?
                ");
                $stmt->execute([$payment['preference_id'], $payment['id']]);
            }
            
            return [
                'success' => true,
                'message' => 'Pagamento atualizado com sucesso',
                'status' => $payment['status'],
                'user_id' => $userId,
                'payment_purpose' => $paymentPurpose,
                'is_processed' => $isProcessed
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false, 
                'message' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
}
?>