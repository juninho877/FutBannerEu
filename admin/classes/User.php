<?php
require_once 'config/database.php';
require_once 'CreditTransaction.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Autenticar usuário
    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("
            SELECT id, username, password, role, status, expires_at 
            FROM usuarios 
            WHERE username = ? AND status = 'active'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Verificar se a conta não expirou
            if ($user['expires_at'] && $user['expires_at'] < date('Y-m-d')) {
                return ['success' => false, 'message' => 'Conta expirada'];
            }
            
            // Atualizar último login
            $this->updateLastLogin($user['id']);
            
            return [
                'success' => true, 
                'user' => $user
            ];
        }
        
        return ['success' => false, 'message' => 'Credenciais inválidas'];
    }
    
    // Atualizar último login
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    // Listar todos os usuários com filtros
    public function getAllUsers($filters = []) {
        $sql = "
            SELECT id, username, email, role, status, expires_at, credits, parent_user_id, created_at, last_login 
            FROM usuarios 
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filtro por nome de usuário ou email
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filtro por role
        if (!empty($filters['role']) && $filters['role'] !== 'all') {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        // Filtro por status
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'expired') {
                $sql .= " AND expires_at IS NOT NULL AND expires_at < CURDATE()";
            } elseif ($filters['status'] !== 'all') {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Buscar usuário por ID
    public function getUserById($id) {
        $stmt = $this->db->prepare("
            SELECT id, username, email, role, status, expires_at, credits, parent_user_id, created_at, last_login 
            FROM usuarios 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Criar novo usuário
    public function createUser($data) {
        try {
            // Verificar se username já existe
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Nome de usuário já existe'];
            }
            
            // Verificar se email já existe (se fornecido)
            if (!empty($data['email'])) {
                $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$data['email']]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'Email já está em uso'];
                }
            }
            
            // Se for um usuário criado por um master, verificar se o master tem créditos suficientes
            $parentUserId = $data['parent_user_id'] ?? null;
            if ($parentUserId && $data['role'] === 'user') {
                // Verificar se o parent_user_id existe e é um master
                $stmt = $this->db->prepare("SELECT id, role, credits FROM usuarios WHERE id = ? AND role = 'master'");
                $stmt->execute([$parentUserId]);
                $masterUser = $stmt->fetch();
                
                if (!$masterUser) {
                    return ['success' => false, 'message' => 'Usuário master não encontrado ou não tem permissão'];
                }
                
                // Verificar se o master tem créditos suficientes
                if ($masterUser['credits'] < 1) {
                    return ['success' => false, 'message' => 'O usuário master não tem créditos suficientes'];
                }
                
                // Deduzir um crédito do master
                $this->deductCredits($parentUserId, 1);
                
                // Registrar a transação
                $creditTransaction = new CreditTransaction();
                $creditTransaction->recordTransaction(
                    $parentUserId,
                    'user_creation',
                    -1,
                    "Criação do usuário {$data['username']}",
                    null,
                    null
                );
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (username, password, email, role, status, expires_at, parent_user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;
            
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['email'] ?? null,
                $data['role'] ?? 'user',
                $data['status'] ?? 'active',
                $expiresAt,
                $parentUserId
            ]);
            
            return ['success' => true, 'message' => 'Usuário criado com sucesso'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao criar usuário: ' . $e->getMessage()];
        }
    }
    
    // Atualizar usuário
    public function updateUser($id, $data) {
        try {
            // Verificar se username já existe (exceto para o próprio usuário)
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
            $stmt->execute([$data['username'], $id]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Nome de usuário já existe'];
            }
            
            // Verificar se email já existe (se fornecido e exceto para o próprio usuário)
            if (!empty($data['email'])) {
                $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $id]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'Email já está em uso'];
                }
            }
            
            // Buscar dados atuais do usuário para verificar se a data de expiração está sendo estendida
            $stmt = $this->db->prepare("SELECT expires_at, parent_user_id FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $currentUser = $stmt->fetch();
            
            // Verificar se a data de expiração está sendo estendida e se o usuário tem um parent_user_id
            if ($currentUser && $currentUser['parent_user_id'] && !empty($data['expires_at'])) {
                $currentExpiryDate = $currentUser['expires_at'] ? new DateTime($currentUser['expires_at']) : null;
                $newExpiryDate = new DateTime($data['expires_at']);
                
                // Se a data atual é nula ou a nova data é posterior à atual
                if (!$currentExpiryDate || $newExpiryDate > $currentExpiryDate) {
                    // Calcular quantos meses foram adicionados (aproximadamente)
                    $monthsAdded = 0;
                    
                    if ($currentExpiryDate) {
                        $diff = $currentExpiryDate->diff($newExpiryDate);
                        $monthsAdded = ($diff->y * 12) + $diff->m;
                        
                        // Se a diferença é de pelo menos 15 dias, considerar como um mês adicional
                        if ($diff->d >= 15) {
                            $monthsAdded++;
                        }
                    } else {
                        // Se não tinha data de expiração, considerar como 1 mês
                        $monthsAdded = 1;
                    }
                    
                    // Se foram adicionados meses, deduzir créditos do master
                    if ($monthsAdded > 0) {
                        // Verificar se o master tem créditos suficientes
                        $stmt = $this->db->prepare("SELECT credits FROM usuarios WHERE id = ?");
                        $stmt->execute([$currentUser['parent_user_id']]);
                        $masterUser = $stmt->fetch();
                        
                        if ($masterUser && $masterUser['credits'] < $monthsAdded) {
                            return ['success' => false, 'message' => 'O usuário master não tem créditos suficientes para esta extensão'];
                        }
                        
                        // Deduzir créditos do master
                        $this->deductCredits($currentUser['parent_user_id'], $monthsAdded);
                        
                        // Registrar a transação
                        $creditTransaction = new CreditTransaction();
                        $creditTransaction->recordTransaction(
                            $currentUser['parent_user_id'],
                            'user_renewal',
                            -$monthsAdded,
                            "Renovação do usuário ID {$id} por {$monthsAdded} " . ($monthsAdded > 1 ? 'meses' : 'mês'),
                            $id,
                            null
                        );
                    }
                }
            }
            
            $sql = "UPDATE usuarios SET username = ?, email = ?, role = ?, status = ?, expires_at = ?";
            $params = [
                $data['username'],
                $data['email'] ?? null,
                $data['role'],
                $data['status'],
                !empty($data['expires_at']) ? $data['expires_at'] : null
            ];
            
            // Se uma nova senha foi fornecida
            if (!empty($data['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Usuário atualizado com sucesso'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()];
        }
    }
    
    // Alterar status do usuário
    public function changeStatus($id, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            $statusText = $status === 'active' ? 'ativado' : 'desativado';
            return ['success' => true, 'message' => "Usuário {$statusText} com sucesso"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao alterar status: ' . $e->getMessage()];
        }
    }
    
    // Excluir usuário
    public function deleteUser($id) {
        try {
            // Não permitir excluir o próprio usuário
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
                return ['success' => false, 'message' => 'Você não pode excluir sua própria conta'];
            }
            
            $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Usuário excluído com sucesso'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao excluir usuário: ' . $e->getMessage()];
        }
    }
    
    // Contar usuários por status
    public function getUserStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN role = 'master' THEN 1 ELSE 0 END) as masters,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < CURDATE() THEN 1 ELSE 0 END) as expired
            FROM usuarios
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    // Adicionar créditos a um usuário
    public function addCredits($userId, $amount, $description = '') {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'A quantidade de créditos deve ser maior que zero'];
            }
            
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("UPDATE usuarios SET credits = credits + ? WHERE id = ?");
            $stmt->execute([$amount, $userId]);
            
            // Registrar a transação
            $creditTransaction = new CreditTransaction();
            $creditTransaction->recordTransaction(
                $userId,
                'admin_add',
                $amount,
                $description ?: "Adição manual de {$amount} créditos",
                null,
                null
            );
            
            $this->db->commit();
            
            return ['success' => true, 'message' => "{$amount} créditos adicionados com sucesso"];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Erro ao adicionar créditos: ' . $e->getMessage()];
        }
    }
    
    // Deduzir créditos de um usuário
    public function deductCredits($userId, $amount) {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'A quantidade de créditos deve ser maior que zero'];
            }
            
            // Verificar se o usuário tem créditos suficientes
            $stmt = $this->db->prepare("SELECT credits FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }
            
            if ($user['credits'] < $amount) {
                return ['success' => false, 'message' => 'Créditos insuficientes'];
            }
            
            // Deduzir créditos
            $stmt = $this->db->prepare("UPDATE usuarios SET credits = credits - ? WHERE id = ?");
            $stmt->execute([$amount, $userId]);
            
            return ['success' => true, 'message' => "{$amount} créditos deduzidos com sucesso"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao deduzir créditos: ' . $e->getMessage()];
        }
    }
    
    // Obter créditos de um usuário
    public function getUserCredits($userId) {
        try {
            $stmt = $this->db->prepare("SELECT credits FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return 0;
            }
            
            return $user['credits'];
        } catch (PDOException $e) {
            error_log("Erro ao obter créditos do usuário: " . $e->getMessage());
            return 0;
        }
    }
    
    // Obter usuários criados por um master com filtros
    public function getUsersByParentId($parentId, $filters = []) {
        $sql = "
            SELECT id, username, email, role, status, expires_at, created_at, last_login 
            FROM usuarios 
            WHERE parent_user_id = ?
        ";
        
        $params = [$parentId];
        
        // Filtro por nome de usuário ou email
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filtro por status
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'expired') {
                $sql .= " AND expires_at IS NOT NULL AND expires_at < CURDATE()";
            } elseif ($filters['status'] !== 'all') {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao obter usuários do master: " . $e->getMessage());
            return [];
        }
    }
    
    // Comprar créditos para um usuário master
    public function purchaseCredits($userId, $amount, $paymentId = null) {
        try {
            $this->db->beginTransaction();
            
            // Verificar se o usuário existe e é um master
            $stmt = $this->db->prepare("SELECT id, role FROM usuarios WHERE id = ? AND role = 'master'");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Usuário não encontrado ou não é um master'];
            }
            
            // Adicionar créditos
            $stmt = $this->db->prepare("UPDATE usuarios SET credits = credits + ? WHERE id = ?");
            $stmt->execute([$amount, $userId]);
            
            // Registrar a compra de créditos
            if ($paymentId) {
                $stmt = $this->db->prepare("
                    INSERT INTO credit_purchases (user_id, amount, payment_id, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$userId, $amount, $paymentId]);
            }
            
            // Registrar a transação
            $creditTransaction = new CreditTransaction();
            $creditTransaction->recordTransaction(
                $userId,
                'purchase',
                $amount,
                "Compra de {$amount} créditos via Mercado Pago",
                null,
                $paymentId
            );
            
            $this->db->commit();
            return ['success' => true, 'message' => "{$amount} créditos adicionados com sucesso"];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Erro ao comprar créditos: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao comprar créditos: ' . $e->getMessage()];
        }
    }
}
?>