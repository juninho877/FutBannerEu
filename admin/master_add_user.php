<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'master') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';

$user = new User();
$message = '';
$messageType = '';
$masterId = $_SESSION['user_id'];
$masterCredits = $user->getUserCredits($masterId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'password' => trim($_POST['password']),
        'role' => 'user', // Master só pode criar usuários comuns
        'status' => $_POST['status'],
        'expires_at' => date('Y-m-d', strtotime('+30 days')), // Sempre 30 dias a partir de hoje
        'parent_user_id' => $masterId // Definir o master como pai do usuário
    ];
    
    // Validações
    if (empty($data['username'])) {
        $message = 'Nome de usuário é obrigatório';
        $messageType = 'error';
    } elseif (empty($data['email'])) {
        $message = 'Email é obrigatório';
        $messageType = 'error';
    } elseif (strlen($data['password']) < 6) {
        $message = 'A senha deve ter pelo menos 6 caracteres';
        $messageType = 'error';
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $message = 'As senhas não coincidem';
        $messageType = 'error';
    } elseif ($masterCredits < 1) {
        $message = 'Você não tem créditos suficientes para criar um novo usuário';
        $messageType = 'error';
    } else {
        $result = $user->createUser($data);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            header("Location: master_users.php?success=1");
            exit();
        }
    }
}

$pageTitle = "Adicionar Usuário";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user-plus text-primary-500 mr-3"></i>
        Adicionar Novo Usuário
    </h1>
    <p class="page-subtitle">Crie uma nova conta de usuário no sistema</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informações do Usuário</h3>
                <p class="card-subtitle">Preencha todos os campos obrigatórios</p>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> mb-6">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($masterCredits < 1): ?>
                    <div class="alert alert-warning mb-6">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <p class="font-medium">Créditos insuficientes</p>
                            <p class="text-sm mt-1">Você não tem créditos suficientes para criar um novo usuário. <a href="buy_credits.php" class="text-primary-500 hover:underline">Compre mais créditos</a> para continuar.</p>
                        </div>
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
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                   placeholder="Digite o nome de usuário" required <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label required">
                                <i class="fas fa-envelope mr-2"></i>
                                Email
                            </label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   placeholder="Digite o email" required <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="password" class="form-label required">
                                <i class="fas fa-lock mr-2"></i>
                                Senha
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" class="form-input pr-10" 
                                       placeholder="Mínimo de 6 caracteres" required <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                                <button type="button" class="password-toggle" data-target="password" <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label required">
                                <i class="fas fa-check mr-2"></i>
                                Confirmar Senha
                            </label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input pr-10" 
                                       placeholder="Repita a senha" required <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                                <button type="button" class="password-toggle" data-target="confirm_password" <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label required">
                            <i class="fas fa-toggle-on mr-2"></i>
                            Status
                        </label>
                        <select id="status" name="status" class="form-input form-select" required <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                            <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar mr-2"></i>
                            Data de Expiração
                        </label>
                        <p class="text-sm text-muted">O usuário será criado com validade de 30 dias (consome 1 crédito)</p>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" class="btn btn-primary" <?php echo $masterCredits < 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-save"></i>
                            Criar Usuário
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
                    <p class="text-sm text-muted">Cada novo usuário criado consome 1 crédito</p>
                    <p class="text-sm text-muted">Cada renovação de usuário também consome 1 crédito</p>
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

        <!-- Guidelines -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📋 Diretrizes</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-user text-primary-500 mt-0.5"></i>
                        <div>
                            <p class="font-medium">Nome de usuário único</p>
                            <p class="text-muted">Deve ser único no sistema</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-lock text-success-500 mt-0.5"></i>
                        <div>
                            <p class="font-medium">Senha segura</p>
                            <p class="text-muted">Mínimo de 6 caracteres</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-calendar-alt text-warning-500 mt-0.5"></i>
                        <div>
                            <p class="font-medium">Expiração padrão</p>
                            <p class="text-muted">30 dias a partir de hoje</p>
                        </div>
                    </div>
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
</style>

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
});
</script>

<?php include "includes/footer.php"; ?>