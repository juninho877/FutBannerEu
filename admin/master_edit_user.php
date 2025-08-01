<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'master') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';

$userClass = new User();
$message = '';
$messageType = '';
$masterId = $_SESSION['user_id'];
$masterCredits = $userClass->getUserCredits($masterId);

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: master_users.php");
    exit();
}

$userId = (int)$_GET['id'];
$userData = $userClass->getUserById($userId);

// Verificar se o usuário existe e pertence ao master atual
if (!$userData || $userData['parent_user_id'] != $masterId) {
    header("Location: master_users.php?error=user_not_found");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'role' => 'user', // Master só pode editar usuários comuns
        'status' => $_POST['status']
        // Não permitimos que o master edite a data de expiração diretamente
    ];
    
    // Se uma nova senha foi fornecida
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) < 6) {
            $message = 'A senha deve ter pelo menos 6 caracteres';
            $messageType = 'error';
        } elseif ($_POST['password'] !== $_POST['confirm_password']) {
            $message = 'As senhas não coincidem';
            $messageType = 'error';
        } else {
            $data['password'] = $_POST['password'];
        }
    }
    
    if (empty($message)) {
        if (empty($data['username'])) {
            $message = 'Nome de usuário é obrigatório';
            $messageType = 'error';
        } elseif (empty($data['email'])) {
            $message = 'Email é obrigatório';
            $messageType = 'error';
        } else {
            $result = $userClass->updateUser($userId, $data);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            
            if ($result['success']) {
                // Recarregar dados do usuário
                $userData = $userClass->getUserById($userId);
            }
        }
    }
}

$pageTitle = "Editar Usuário";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user-edit text-primary-500 mr-3"></i>
        Editar Usuário
    </h1>
    <p class="page-subtitle">Modifique as informações do usuário <?php echo htmlspecialchars($userData['username']); ?></p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informações do Usuário</h3>
                <p class="card-subtitle">Atualize os dados conforme necessário</p>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> mb-6">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="username" class="form-label required">
                                <i class="fas fa-user mr-2"></i>
                                Nome de Usuário
                            </label>
                            <input type="text" id="username" name="username" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? $userData['username']); ?>" 
                                   placeholder="Digite o nome de usuário" required>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label required">
                                <i class="fas fa-envelope mr-2"></i>
                                Email
                            </label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $userData['email'] ?? ''); ?>" 
                                   placeholder="Digite o email" required>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 my-6 pt-6">
                        <h4 class="text-lg font-semibold mb-4">Alterar Senha (Opcional)</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock mr-2"></i>
                                    Nova Senha
                                </label>
                                <div class="relative">
                                    <input type="password" id="password" name="password" class="form-input pr-10" 
                                           placeholder="Deixe em branco para manter a atual">
                                    <button type="button" class="password-toggle" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check mr-2"></i>
                                    Confirmar Nova Senha
                                </label>
                                <div class="relative">
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-input pr-10" 
                                           placeholder="Repita a nova senha">
                                    <button type="button" class="password-toggle" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label required">
                            <i class="fas fa-toggle-on mr-2"></i>
                            Status
                        </label>
                        <select id="status" name="status" class="form-input form-select" required>
                            <option value="active" <?php echo ($_POST['status'] ?? $userData['status']) === 'active' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="trial" <?php echo ($_POST['status'] ?? $userData['status']) === 'trial' ? 'selected' : ''; ?>>Em Teste</option>
                            <option value="inactive" <?php echo ($_POST['status'] ?? $userData['status']) === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar mr-2"></i>
                            Data de Expiração
                        </label>
                        <div class="flex items-center gap-3">
                            <div class="expiry-display">
                                <?php 
                                if ($userData['expires_at']) {
                                    $expiresAt = new DateTime($userData['expires_at']);
                                    $now = new DateTime();
                                    $isExpired = $expiresAt < $now;
                                    echo '<span class="' . ($isExpired ? 'text-danger-500' : 'text-success-500') . ' font-semibold">';
                                    echo $expiresAt->format('d/m/Y');
                                    echo '</span>';
                                    
                                    if ($isExpired) {
                                        echo ' <span class="expiry-badge expired">Expirado</span>';
                                    } else {
                                        $daysRemaining = $now->diff($expiresAt)->days;
                                        echo ' <span class="expiry-badge active">Faltam ' . $daysRemaining . ' dias</span>';
                                    }
                                } else {
                                    echo '<span class="text-muted">Sem data de expiração</span>';
                                }
                                ?>
                            </div>
                            <button type="button" id="renewBtn" class="btn btn-success btn-sm">
                                <i class="fas fa-sync-alt"></i>
                                Renovar
                            </button>
                        </div>
                        <p class="text-xs text-muted mt-1">Para estender a data de expiração, use o botão "Renovar"</p>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Salvar Alterações
                        </button>
                        <a href="master_users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Panel -->
    <div class="space-y-6">
        <!-- User Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informações Atuais</h3>
            </div>
            <div class="card-body">
                <div class="flex items-center gap-3 mb-4">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userData['username'], 0, 2)); ?>
                    </div>
                    <div>
                        <h4 class="font-semibold"><?php echo htmlspecialchars($userData['username']); ?></h4>
                        <p class="text-sm text-muted">ID: <?php echo $userData['id']; ?></p>
                    </div>
                </div>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted">Status:</span>
                        <span class="status-badge status-<?php echo $userData['status']; ?>">
                            <?php 
                            switch ($userData['status']) {
                                case 'active':
                                    echo 'Ativo';
                                    break;
                                case 'trial':
                                    echo 'Em Teste';
                                    break;
                                case 'inactive':
                                    echo 'Inativo';
                                    break;
                                default:
                                    echo ucfirst($userData['status']);
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted">Criado em:</span>
                        <span><?php echo date('d/m/Y', strtotime($userData['created_at'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted">Expira em:</span>
                        <span>
                            <?php 
                            if ($userData['expires_at']) {
                                $expiresAt = new DateTime($userData['expires_at']);
                                $now = new DateTime();
                                $isExpired = $expiresAt < $now;
                                echo '<span class="' . ($isExpired ? 'text-danger-500' : 'text-success-500') . '">';
                                echo $expiresAt->format('d/m/Y');
                                echo '</span>';
                            } else {
                                echo 'Nunca';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted">Último login:</span>
                        <span>
                            <?php 
                            if ($userData['last_login']) {
                                echo date('d/m/Y H:i', strtotime($userData['last_login']));
                            } else {
                                echo 'Nunca';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credit Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-coins text-warning-500 mr-2"></i>
                    Seus Créditos
                </h3>
            </div>
            <div class="card-body">
                <div class="credit-display">
                    <div class="credit-amount"><?php echo $masterCredits; ?></div>
                    <div class="credit-label">créditos disponíveis</div>
                </div>
                
                <div class="credit-info mt-4">
                    <p class="text-sm text-muted">Renovar um usuário consome créditos</p>
                    <p class="text-sm text-muted">Cada mês adicional = 1 crédito</p>
                </div>
                
                <?php if ($masterCredits < 3): ?>
                <div class="mt-4">
                    <a href="buy_credits.php" class="btn btn-warning w-full">
                        <i class="fas fa-shopping-cart"></i>
                        Comprar Mais Créditos
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ações Rápidas</h3>
            </div>
            <div class="card-body">
                <div class="space-y-2">
                    <?php if ($userData['status'] === 'active'): ?>
                        <button class="btn btn-warning w-full text-sm toggle-status" data-user-id="<?php echo $userData['id']; ?>" data-status="inactive">
                            <i class="fas fa-user-times"></i>
                            Desativar Usuário
                        </button>
                    <?php else: ?>
                        <button class="btn btn-success w-full text-sm toggle-status" data-user-id="<?php echo $userData['id']; ?>" data-status="active">
                            <i class="fas fa-user-check"></i>
                            Ativar Usuário
                        </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-danger w-full text-sm delete-user" data-user-id="<?php echo $userData['id']; ?>" data-username="<?php echo htmlspecialchars($userData['username']); ?>">
                        <i class="fas fa-trash"></i>
                        Excluir Usuário
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .required::after {
        content: ' *';
        color: var(--danger-500);
    }

    .alert {
        padding: 1rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
    }
    
    .alert-success {
        background: var(--success-50);
        color: var(--success-600);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .alert-error {
        background: var(--danger-50);
        color: var(--danger-600);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .alert-warning {
        background: var(--warning-50);
        color: var(--warning-600);
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
    
    .password-toggle {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 4px;
        transition: var(--transition);
    }
    
    .password-toggle:hover {
        color: var(--text-primary);
        background: var(--bg-tertiary);
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

    .role-user {
        background: var(--primary-50);
        color: var(--primary-600);
    }

    .status-active {
        background: var(--success-50);
        color: var(--success-600);
    }

    .status-inactive {
        background: var(--danger-50);
        color: var(--danger-600);
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .credit-display {
        text-align: center;
        padding: 1.5rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
    }

    .credit-amount {
        font-size: 3rem;
        font-weight: 700;
        color: var(--success-500);
        line-height: 1;
    }

    .credit-label {
        color: var(--text-secondary);
        margin-top: 0.5rem;
    }

    .credit-info {
        background: var(--bg-tertiary);
        padding: 1rem;
        border-radius: var(--border-radius);
    }
    
    .expiry-display {
        padding: 0.75rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        flex: 1;
    }
    
    .expiry-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .expiry-badge.active {
        background: var(--success-50);
        color: var(--success-600);
    }
    
    .expiry-badge.expired {
        background: var(--danger-50);
        color: var(--danger-600);
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }
    
    [data-theme="dark"] .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }

    [data-theme="dark"] .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }

    [data-theme="dark"] .border-gray-200 {
        border-color: var(--border-color);
    }

    [data-theme="dark"] .role-admin {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }

    [data-theme="dark"] .role-user {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }

    [data-theme="dark"] .status-active {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .status-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }
    
    [data-theme="dark"] .status-trial {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info-400);
    }
    
    [data-theme="dark"] .status-trial {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info-400);
    }
    
    [data-theme="dark"] .expiry-badge.active {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }
    
    [data-theme="dark"] .expiry-badge.expired {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality
    const toggleButtons = document.querySelectorAll('.password-toggle');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });

    // Password confirmation validation
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    function checkPasswordMatch() {
        if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('As senhas não coincidem');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
    
    passwordInput.addEventListener('input', checkPasswordMatch);
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

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
    
    // Renew User
    const renewBtn = document.getElementById('renewBtn');
    if (renewBtn) {
        renewBtn.addEventListener('click', function() {
            const userId = <?php echo $userId; ?>;
            const username = "<?php echo htmlspecialchars($userData['username']); ?>";
            
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
                    <p class="mt-4 text-sm">Créditos disponíveis: <strong>${<?php echo $masterCredits; ?>}</strong></p>
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
                            renewUser(userId, username, months);
                            Swal.close();
                        });
                    });
                }
            });
        });
    }

    function changeUserStatus(userId, status) {
        fetch('master_users.php', {
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
        });
    }

    function deleteUser(userId) {
        fetch('master_users.php', {
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
                    window.location.href = 'master_users.php';
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
        });
    }
    
    function renewUser(userId, username, months) {
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
        fetch('renew_user_ajax.php', {
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