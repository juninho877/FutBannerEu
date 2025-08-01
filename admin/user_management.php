<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';
require_once 'classes/CreditTransaction.php';

$user = new User();
$creditTransaction = new CreditTransaction();

// Processar filtros
$filters = [
    'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
    'role' => isset($_GET['role']) ? $_GET['role'] : 'all',
    'status' => isset($_GET['status']) ? $_GET['status'] : 'all'
];

// Obter usuários filtrados
$users = $user->getAllUsers($filters);
$stats = $user->getUserStats();

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'change_status':
            $result = $user->changeStatus($_POST['user_id'], $_POST['status']);
            echo json_encode($result);
            exit;
            
        case 'delete_user':
            $result = $user->deleteUser($_POST['user_id']);
            echo json_encode($result);
            exit;
            
        case 'add_credits':
            $userId = intval($_POST['user_id']);
            $credits = intval($_POST['credits']);
            $description = isset($_POST['description']) ? $_POST['description'] : "Adição manual de créditos";
            
            $result = $user->addCredits($userId, $credits);
            
            if ($result['success']) {
                // Registrar a transação
                $creditTransaction->recordTransaction(
                    1, // Admin ID (assumindo que o admin tem ID 1)
                    'admin_add',
                    $credits,
                    $description,
                    $userId,
                    null
                );
            }
            
            echo json_encode($result);
            exit;
    }
}

$pageTitle = "Gerenciamento de Usuários";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users text-primary-500 mr-3"></i>
        Gerenciamento de Usuários
    </h1>
    <p class="page-subtitle">Controle completo dos usuários do sistema</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Total de Usuários</p>
                    <p class="text-2xl font-bold text-primary"><?php echo $stats['total']; ?></p>
                </div>
                <div class="w-12 h-12 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-primary-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Usuários Ativos</p>
                    <p class="text-2xl font-bold text-success-500"><?php echo $stats['active']; ?></p>
                </div>
                <div class="w-12 h-12 bg-success-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-check text-success-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Usuários em Teste</p>
                    <p class="text-2xl font-bold text-info-500"><?php echo $stats['trial']; ?></p>
                </div>
                <div class="w-12 h-12 bg-info-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-clock text-info-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Usuários Inativos</p>
                    <p class="text-2xl font-bold text-warning-500"><?php echo $stats['inactive']; ?></p>
                </div>
                <div class="w-12 h-12 bg-warning-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-times text-warning-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Usuários Expirados</p>
                    <p class="text-2xl font-bold text-danger-500"><?php echo $stats['expired']; ?></p>
                </div>
                <div class="w-12 h-12 bg-danger-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-slash text-danger-500"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Filtrar Usuários</h3>
        <p class="card-subtitle">Refine a lista de usuários</p>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="form-group">
                <label for="search" class="form-label">Buscar por Nome/Email</label>
                <input type="text" id="search" name="search" class="form-input" 
                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                       placeholder="Digite o nome ou email">
            </div>
            
            <div class="form-group">
                <label for="role" class="form-label">Função</label>
                <select id="role" name="role" class="form-input form-select">
                    <option value="all" <?php echo $filters['role'] === 'all' ? 'selected' : ''; ?>>Todas as funções</option>
                    <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    <option value="master" <?php echo $filters['role'] === 'master' ? 'selected' : ''; ?>>Master</option>
                    <option value="user" <?php echo $filters['role'] === 'user' ? 'selected' : ''; ?>>Usuário</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input form-select">
                    <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>Todos os status</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="trial" <?php echo $filters['status'] === 'trial' ? 'selected' : ''; ?>>Em Teste</option>
                    <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                    <option value="expired" <?php echo $filters['status'] === 'expired' ? 'selected' : ''; ?>>Expirado</option>
                </select>
            </div>
            
            <div class="form-actions md:col-span-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
                
                <a href="user_management.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Limpar Filtros
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Actions Bar -->
<div class="flex justify-between items-center mb-6">
    <div class="flex gap-3">
        <button id="refreshBtn" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i>
            Atualizar
        </button>
    </div>
    <a href="add_user.php" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        Adicionar Usuário
    </a>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lista de Usuários</h3>
        <p class="card-subtitle">Gerencie todos os usuários do sistema</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Email</th>
                        <th>Função</th>
                        <th>Status</th>
                        <th>Expira em</th>
                        <th>Créditos</th>
                        <th>Último Login</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Nenhum usuário encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $userData): 
                            // Verificar se o usuário está expirado
                            $isExpired = $userData['expires_at'] && strtotime($userData['expires_at']) < time();
                            $isTrialExpired = $userData['status'] === 'trial' && $isExpired;
                        ?>
                            <tr data-user-id="<?php echo $userData['id']; ?>">
                                <td><?php echo $userData['id']; ?></td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar-small">
                                            <?php echo strtoupper(substr($userData['username'], 0, 2)); ?>
                                        </div>
                                        <span class="font-medium"><?php echo htmlspecialchars($userData['username']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($userData['email'] ?? '-'); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $userData['role']; ?>">
                                        <?php 
                                        switch ($userData['role']) {
                                            case 'admin':
                                                echo 'Administrador';
                                                break;
                                            case 'master':
                                                echo 'Master';
                                                break;
                                            default:
                                                echo 'Usuário';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isTrialExpired): ?>
                                        <span class="status-badge status-trial-expired">Teste Expirado</span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="status-badge status-expired">Expirado</span>
                                    <?php elseif ($userData['status'] === 'trial'): ?>
                                        <span class="status-badge status-trial">Em Teste</span>
                                    <?php else: ?>
                                        <span class="status-badge status-<?php echo $userData['status']; ?>">
                                            <?php echo $userData['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($userData['expires_at']) {
                                        $expiresAt = new DateTime($userData['expires_at']);
                                        $now = new DateTime();
                                        $isExpired = $expiresAt < $now;
                                        echo '<span class="' . ($isExpired ? 'text-danger-500' : 'text-muted') . '">';
                                        echo $expiresAt->format('d/m/Y');
                                        echo '</span>';
                                    } else {
                                        echo '<span class="text-muted">Nunca</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($userData['role'] === 'master'): ?>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium"><?php echo $userData['credits']; ?></span>
                                            <button class="btn-action btn-primary add-credits" data-user-id="<?php echo $userData['id']; ?>" title="Adicionar Créditos">
                                                <i class="fas fa-plus-circle"></i>
                                            </button>
                                            <a href="user_credit_history.php?id=<?php echo $userData['id']; ?>" class="btn-action btn-secondary" title="Ver Histórico">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($userData['last_login']) {
                                        $lastLogin = new DateTime($userData['last_login']);
                                        echo $lastLogin->format('d/m/Y H:i');
                                    } else {
                                        echo '<span class="text-muted">Nunca</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_user.php?id=<?php echo $userData['id']; ?>" class="btn-action btn-edit" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button class="btn-action btn-primary renew-user-admin" data-user-id="<?php echo $userData['id']; ?>" data-username="<?php echo htmlspecialchars($userData['username']); ?>" title="Renovar">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        
                                        <?php if ($userData['status'] === 'active'): ?>
                                            <button class="btn-action btn-warning toggle-status" data-user-id="<?php echo $userData['id']; ?>" data-status="inactive" title="Desativar">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-action btn-success toggle-status" data-user-id="<?php echo $userData['id']; ?>" data-status="active" title="Ativar">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($userData['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn-action btn-danger delete-user" data-user-id="<?php echo $userData['id']; ?>" data-username="<?php echo htmlspecialchars($userData['username']); ?>" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .table-responsive {
        overflow-x: auto;
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .users-table th,
    .users-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .users-table th {
        background: var(--bg-secondary);
        font-weight: 600;
        color: var(--text-primary);
    }

    .users-table tbody tr:hover {
        background: var(--bg-secondary);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-avatar-small {
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.75rem;
    }

    .role-badge,
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .role-admin {
        background: var(--warning-50);
        color: var(--warning-600);
    }
    
    .role-master {
        background: var(--primary-50);
        color: var(--primary-600);
    }

    .role-user {
        background: var(--success-50);
        color: var(--success-600);
    }

    .status-active {
        background: var(--success-50);
        color: var(--success-600);
    }

    .status-inactive {
        background: var(--danger-50);
        color: var(--danger-600);
    }
    
    .status-expired {
        background: var(--warning-50);
        color: var(--warning-600);
    }
    
    .status-trial {
        background: var(--primary-50);
        color: var(--primary-600);
    }
    
    .status-trial-expired {
        background: var(--danger-50);
        color: var(--danger-600);
    }
    
    .text-info-500 {
        color: var(--info-500);
    }
    
    .bg-info-50 {
        background-color: var(--info-50);
    }
    .status-expired {
        background: var(--warning-50);
        color: var(--warning-600);
    }
    
    .status-trial {
        background: var(--info-50);
        color: var(--info-600);
    }
    
    .status-trial-expired {
        background: var(--danger-50);
        color: var(--danger-600);
    }
    
    .text-info-500 {
        color: var(--info-500);
    }
    
    .bg-info-50 {
        background-color: var(--info-50);
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
        text-decoration: none;
    }

    .btn-edit {
        background: var(--primary-50);
        color: var(--primary-600);
    }

    .btn-edit:hover {
        background: var(--primary-100);
    }

    .btn-success {
        background: var(--success-50);
        color: var(--success-600);
    }

    .btn-success:hover {
        background: var(--success-100);
    }

    .btn-warning {
        background: var(--warning-50);
        color: var(--warning-600);
    }

    .btn-warning:hover {
        background: var(--warning-100);
    }

    .btn-danger {
        background: var(--danger-50);
        color: var(--danger-600);
    }

    .btn-danger:hover {
        background: var(--danger-100);
    }
    
    .btn-primary {
        background: var(--primary-50);
        color: var(--primary-600);
    }
    
    .btn-primary:hover {
        background: var(--primary-100);
    }
    
    .btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-secondary);
    }
    
    .btn-secondary:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .role-admin {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
    
    [data-theme="dark"] .role-master {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }

    [data-theme="dark"] .role-user {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .status-active {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .status-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }
    
    [data-theme="dark"] .status-expired {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
    
    [data-theme="dark"] .status-trial {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .status-trial-expired {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }
    
    [data-theme="dark"] .status-expired {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
    
    [data-theme="dark"] .status-trial {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info-400);
    }
    
    [data-theme="dark"] .status-trial-expired {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }
    
    [data-theme="dark"] .btn-primary {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-muted);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Status
    document.querySelectorAll('.toggle-status').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const newStatus = this.getAttribute('data-status');
            const statusText = newStatus === 'active' ? 'ativar' : 'desativar';
            
            Swal.fire({
                title: 'Confirmar Ação',
                text: `Deseja ${statusText} este usuário?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, ' + statusText,
                cancelButtonText: 'Cancelar',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            }).then((result) => {
                if (result.isConfirmed) {
                    changeUserStatus(userId, newStatus);
                }
            });
        });
    });

    // Delete User
    document.querySelectorAll('.delete-user').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            
            Swal.fire({
                title: 'Excluir Usuário',
                text: `Tem certeza que deseja excluir o usuário "${username}"? Esta ação não pode ser desfeita.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteUser(userId);
                }
            });
        });
    });
    
    // Add Credits
    document.querySelectorAll('.add-credits').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            
            Swal.fire({
                title: 'Adicionar Créditos',
                text: 'Quantos créditos deseja adicionar?',
                input: 'number',
                inputAttributes: {
                    min: 1,
                    step: 1
                },
                inputValue: 1,
                showCancelButton: true,
                confirmButtonText: 'Adicionar',
                cancelButtonText: 'Cancelar',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
                inputValidator: (value) => {
                    if (!value || value < 1) {
                        return 'Você precisa adicionar pelo menos 1 crédito!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Pedir descrição
                    Swal.fire({
                        title: 'Descrição',
                        text: 'Informe uma descrição para esta adição de créditos:',
                        input: 'text',
                        inputPlaceholder: 'Descrição (opcional)',
                        showCancelButton: true,
                        confirmButtonText: 'Confirmar',
                        cancelButtonText: 'Cancelar',
                        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                    }).then((descResult) => {
                        if (descResult.isConfirmed) {
                            addCredits(userId, result.value, descResult.value);
                        }
                    });
                }
            });
        });
    });
    
    // Renew User (Admin)
    document.querySelectorAll('.renew-user-admin').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            
            Swal.fire({
                title: 'Renovar Usuário',
                html: `
                    <p class="mb-4">Escolha por quantos meses deseja renovar o usuário <strong>${username}</strong>:</p>
                    <div class="renewal-options">
                        <button type="button" class="renewal-option" data-months="1">1 mês</button>
                        <button type="button" class="renewal-option" data-months="3">3 meses</button>
                        <button type="button" class="renewal-option" data-months="6">6 meses</button>
                        <button type="button" class="renewal-option" data-months="12">12 meses</button>
                    </div>
                `,
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonText: 'Cancelar',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
                didOpen: () => {
                    // Estilizar opções de renovação
                    const options = Swal.getPopup().querySelectorAll('.renewal-option');
                    options.forEach(option => {
                        option.style.margin = '5px';
                        option.style.padding = '10px 15px';
                        option.style.borderRadius = '8px';
                        option.style.border = '1px solid #e2e8f0';
                        option.style.background = document.body.getAttribute('data-theme') === 'dark' ? '#334155' : '#f8fafc';
                        option.style.cursor = 'pointer';
                        option.style.fontWeight = '500';
                        
                        option.addEventListener('mouseover', function() {
                            this.style.background = document.body.getAttribute('data-theme') === 'dark' ? '#475569' : '#f1f5f9';
                        });
                        
                        option.addEventListener('mouseout', function() {
                            this.style.background = document.body.getAttribute('data-theme') === 'dark' ? '#334155' : '#f8fafc';
                        });
                        
                        option.addEventListener('click', function() {
                            const months = parseInt(this.getAttribute('data-months'));
                            adminRenewUser(userId, username, months);
                            Swal.close();
                        });
                    });
                }
            });
        });
    });

    // Refresh Button
    document.getElementById('refreshBtn').addEventListener('click', function() {
        location.reload();
    });

    function changeUserStatus(userId, status) {
        fetch('user_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=change_status&user_id=${userId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Sucesso!',
                    text: data.message,
                    icon: 'success',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erro!',
                    text: data.message,
                    icon: 'error',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Erro!',
                text: 'Erro de comunicação com o servidor',
                icon: 'error',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            });
        });
    }

    function deleteUser(userId) {
        fetch('user_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_user&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Sucesso!',
                    text: data.message,
                    icon: 'success',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erro!',
                    text: data.message,
                    icon: 'error',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Erro!',
                text: 'Erro de comunicação com o servidor',
                icon: 'error',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            });
        });
    }
    
    function addCredits(userId, credits, description = '') {
        fetch('user_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_credits&user_id=${userId}&credits=${credits}&description=${encodeURIComponent(description)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Sucesso!',
                    text: data.message,
                    icon: 'success',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erro!',
                    text: data.message,
                    icon: 'error',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Erro!',
                text: 'Erro de comunicação com o servidor',
                icon: 'error',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            });
        });
    }
    
    function adminRenewUser(userId, username, months) {
        // Mostrar loading
        Swal.fire({
            title: 'Processando...',
            text: 'Aguarde enquanto renovamos o usuário',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
        });
        
        // Enviar solicitação para renovar usuário
        fetch('admin_renew_user_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&months=${months}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Sucesso!',
                    text: data.message,
                    icon: 'success',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erro!',
                    text: data.message,
                    icon: 'error',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Erro!',
                text: 'Erro de comunicação com o servidor',
                icon: 'error',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            });
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>