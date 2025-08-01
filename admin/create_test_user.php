<?php
/**
 * 🧪 Utilitário para criar usuários de teste
 * Usado pelo sistema de testes para criar usuários com diferentes cenários
 */

session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

require_once 'classes/User.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Método ou ação inválida']);
    exit();
}

$user = new User();
$db = Database::getInstance()->getConnection();

try {
    switch ($_POST['action']) {
        case 'create_expired_trial':
            // Criar usuário com trial expirado para testes
            $timestamp = time();
            $testUsername = 'trial_expired_' . $timestamp;
            $testPassword = 'test123';
            
            $data = [
                'username' => $testUsername,
                'email' => $testUsername . '@test.com',
                'password' => $testPassword,
                'role' => 'user',
                'status' => 'trial',
                'expires_at' => date('Y-m-d', strtotime('-1 day')), // Expirado ontem
                'parent_user_id' => 1 // Admin
            ];
            
            $result = $user->createUser($data);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuário trial expirado criado com sucesso',
                    'username' => $testUsername,
                    'password' => $testPassword,
                    'expires_at' => $data['expires_at']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar usuário: ' . $result['message']
                ]);
            }
            break;
            
        case 'create_active_trial':
            // Criar usuário com trial ativo para testes
            $timestamp = time();
            $testUsername = 'trial_active_' . $timestamp;
            $testPassword = 'test123';
            
            $data = [
                'username' => $testUsername,
                'email' => $testUsername . '@test.com',
                'password' => $testPassword,
                'role' => 'user',
                'status' => 'trial',
                'expires_at' => date('Y-m-d', strtotime('+7 days')), // Expira em 7 dias
                'parent_user_id' => 1 // Admin
            ];
            
            $result = $user->createUser($data);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuário trial ativo criado com sucesso',
                    'username' => $testUsername,
                    'password' => $testPassword,
                    'expires_at' => $data['expires_at']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar usuário: ' . $result['message']
                ]);
            }
            break;
            
        case 'cleanup_test_users':
            // Limpar usuários de teste
            $stmt = $db->prepare("
                DELETE FROM usuarios 
                WHERE username LIKE 'trial_%' 
                OR username LIKE 'test_referral_%'
                OR email LIKE '%@test.com'
            ");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Limpeza concluída: {$deletedCount} usuários de teste removidos",
                'deleted_count' => $deletedCount
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>