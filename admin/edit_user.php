<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';

$userClass = new User();
$message = '';
$messageType = '';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user_management.php");
    exit();
}

$userId = (int)$_GET['id'];
$userData = $userClass->getUserById($userId);

if (!$userData) {
    header("Location: user_management.php?error=user_not_found");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'role' => $_POST['role'],
        'status' => $_POST['status'],
        'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null
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

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="form-group">
                            <label for="role" class="form-label required">
                                <i class="fas fa-user-tag mr-2"></i>
                                Função
                            </label>
                            <select id="role" name="role" class="form-input form-select" required>
                                <option value="user" <?php echo ($_POST['role'] ?? $userData['role']) === 'user' ? 'selected' : ''; ?>>Usuário</option>
                                <option value="master" <?php echo ($_POST['role'] ?? $userData['role']) === 'master' ? 'selected' : ''; ?>>Master</option>
                                <option value="admin" <?php echo ($_POST['role'] ?? $userData['role']) === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label required">
                                <i class="fas fa-toggle-on mr-2"></i>
                                Status
                            </label>
                            <select id="status" name="status" class="form-input form-select" required>
                                <option value="active" <?php echo ($_POST['status'] ?? $userData['status']) === 'active' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inactive" <?php echo ($_POST['status'] ?? $userData['status']) === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="expires_at" class="form-label">
                                <i class="fas fa-calendar mr-2"></i>
                                Data de Expiração
                            </label>
                            <input type="date" id="expires_at" name="expires_at" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['expires_at'] ?? $userData['expires_at'] ?? ''); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>">
                            <p class="text-xs text-muted mt-1">Deixe em branco para nunca expirar</p>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Salvar Alterações
                        </button>
                        <a href="user_management.php" class="btn btn-secondary">
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
                        <span class="text-muted">Função:</span>
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
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted">Status:</span>
                        <span class="status-badge status-<?php echo $userData['status']; ?>">
                            <?php echo $userData['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted">Criado em:</span>
                        <span><?php echo date('d/m/Y', strtotime($userData['created_at'])); ?></span>
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
                    <?php if ($userData['role'] === 'master'): ?>
                    <div class="flex justify-between">
                        <span class="text-muted">Créditos:</span>
                        <span class="font-semibold text-success-500"><?php echo $userData['credits']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Security Warning -->
        <?php if ($userData['id'] == $_SESSION['user_id']): ?>
        <div class="card border-warning-200">
            <div class="card-header">
                <h3 class="card-title text-warning-600">⚠️ Atenção</h3>
            </div>
            <div class="card-body">
                <p class="text-sm text-warning-600">
                    Você está editando sua própria conta. Tenha cuidado ao alterar suas permissões ou status.
                </p>
            </div>
        </div>
        <?php endif; ?>

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
                    
                    <?php if ($userData['role'] === 'master'): ?>
                        <button class="btn btn-primary w-full text-sm add-credits" data-user-id="<?php echo $userData['id']; ?>">
                            <i class="fas fa-coins"></i>
                            Adicionar Créditos
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($userData['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-danger w-full text-sm delete-user" data-user-id="<?php echo $userData['id']; ?>" data-username="<?php echo htmlspecialchars($userData['username']); ?>">
                            <i class="fas fa-trash"></i>
                            Excluir Usuário
                        </button>
                    <?php endif; ?>
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

    .border-warning-200 {
        border-color: rgba(245, 158, 11, 0.3);
    }

    .text-warning-600 {
        color: var(--warning-600);
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

    [data-theme="dark"] .border-gray-200 {
        border-color: var(--border-color);
    }

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

    [data-theme="dark"] .text-warning-600 {
        color: var(--warning-400);
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
                    addCredits(userId, result.value);
                }
            });
        });
    });

    function changeUserStatus(userId, status) {
        fetch('../user_management.php', {
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
        fetch('../user_management.php', {
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
                    window.location.href = 'user_management.php';
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
    
    function addCredits(userId, credits) {
        fetch('../user_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_credits&user_id=${userId}&credits=${credits}`
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
});
</script>

<?php include "includes/footer.php"; ?>