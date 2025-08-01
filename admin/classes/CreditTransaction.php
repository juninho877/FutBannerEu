<?php
require_once 'config/database.php';

class CreditTransaction {
    private $db;
    
    /**
     * Construtor da classe
     * Inicializa a conexão com o banco de dados
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTransactionTable();
    }
    
    /**
     * Criar tabela de transações se não existir
     */
    private function createTransactionTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS credit_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            transaction_type ENUM('purchase', 'admin_add', 'user_creation', 'user_renewal') NOT NULL,
            amount INT NOT NULL,
            description TEXT,
            related_entity_id INT NULL,
            related_payment_id VARCHAR(255) NULL,
            
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (related_entity_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_transaction_date (transaction_date),
            INDEX idx_transaction_type (transaction_type)
        );
        ";
        
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela de transações de créditos: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar uma transação de crédito
     * 
     * @param int $userId ID do usuário
     * @param string $transactionType Tipo de transação (purchase, admin_add, user_creation, user_renewal)
     * @param int $amount Quantidade de créditos (positivo para adição, negativo para dedução)
     * @param string $description Descrição da transação
     * @param int|null $relatedEntityId ID de entidade relacionada (ex: usuário criado/renovado)
     * @param string|null $relatedPaymentId ID de pagamento relacionado
     * @return bool Sucesso da operação
     */
    public function recordTransaction($userId, $transactionType, $amount, $description = '', $relatedEntityId = null, $relatedPaymentId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO credit_transactions 
                (user_id, transaction_type, amount, description, related_entity_id, related_payment_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $transactionType,
                $amount,
                $description,
                $relatedEntityId,
                $relatedPaymentId
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao registrar transação de crédito: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter histórico de transações de um usuário
     * 
     * @param int $userId ID do usuário
     * @param int $limit Limite de registros
     * @return array Lista de transações
     */
    public function getUserTransactions($userId, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    t.*,
                    u.username as related_username
                FROM credit_transactions t
                LEFT JOIN usuarios u ON t.related_entity_id = u.id
                WHERE t.user_id = ?
                ORDER BY t.transaction_date DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar transações do usuário: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter todas as transações do sistema
     * 
     * @param int $limit Limite de registros
     * @return array Lista de transações
     */
    public function getAllTransactions($limit = 100) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    t.*,
                    u1.username as user_username,
                    u2.username as related_username
                FROM credit_transactions t
                JOIN usuarios u1 ON t.user_id = u1.id
                LEFT JOIN usuarios u2 ON t.related_entity_id = u2.id
                ORDER BY t.transaction_date DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar todas as transações: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter estatísticas de transações
     * 
     * @return array Estatísticas
     */
    public function getTransactionStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN transaction_type = 'purchase' THEN 1 ELSE 0 END) as purchase_count,
                    SUM(CASE WHEN transaction_type = 'admin_add' THEN 1 ELSE 0 END) as admin_add_count,
                    SUM(CASE WHEN transaction_type = 'user_creation' THEN 1 ELSE 0 END) as user_creation_count,
                    SUM(CASE WHEN transaction_type = 'user_renewal' THEN 1 ELSE 0 END) as user_renewal_count,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_added,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_deducted
                FROM credit_transactions
            ");
            
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas de transações: " . $e->getMessage());
            return [
                'total_transactions' => 0,
                'purchase_count' => 0,
                'admin_add_count' => 0,
                'user_creation_count' => 0,
                'user_renewal_count' => 0,
                'total_added' => 0,
                'total_deducted' => 0
            ];
        }
    }
}
?>