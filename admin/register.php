<?php
session_start();

// Se o usuário já está logado, redirecionar para o painel
if (isset($_SESSION["usuario"])) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/MercadoPagoSettings.php';

// Inicializar banco de dados
try {
    $db = Database::getInstance();
    $db->createTables();
} catch (Exception $e) {
    $erro = "Erro de conexão com o banco de dados. Verifique as configurações.";
}

$user = new User();
$mercadoPagoSettings = new MercadoPagoSettings();
$message = '';
$messageType = '';

// Verificar se há um código de referência na URL
$referralCode = isset($_GET['ref']) ? trim($_GET['ref']) : null;
$referralUser = null;
$referralUserData = null;

// Verificar se já existe um código de referência na sessão (memória de referência)
if (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
    $referralCode = $_SESSION['referral_code'];
}

// Se há um novo código de referência na URL, armazenar na sessão
if (isset($_GET['ref']) && !empty(trim($_GET['ref']))) {
    $_SESSION['referral_code'] = trim($_GET['ref']);
    $referralCode = $_SESSION['referral_code'];
}

if ($referralCode) {
    // Buscar o usuário master pelo código de referência (usando o ID como código)
    $stmt = $db->getConnection()->prepare("
        SELECT id, username, role 
        FROM usuarios 
        WHERE id = ? AND role IN ('master', 'admin') AND status = 'active'
    ");
    $stmt->execute([$referralCode]);
    $referralUserData = $stmt->fetch();
    
    if ($referralUserData) {
        $referralUser = $referralUserData['id'];
    }
}

// Se não há referência válida ou o usuário não foi encontrado, usar o admin (ID 1) como padrão
if (!$referralUser) {
    $referralUser = 1; // Admin ID
    
    // Buscar dados do admin para exibição
    $stmt = $db->getConnection()->prepare("
        SELECT id, username, role 
        FROM usuarios 
        WHERE id = 1
    ");
    $stmt->execute();
    $referralUserData = $stmt->fetch();
    
    // Se o admin não existe, criar dados padrão para exibição
    if (!$referralUserData) {
        $referralUserData = [
            'id' => 1,
            'username' => 'Administração',
            'role' => 'admin'
        ];
    }
}

// Obter configurações de teste grátis
$trialDurationDays = $user->getTrialDurationDays();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username']),
        'email' => trim($_POST['email']),
        'password' => trim($_POST['password']),
        'role' => 'user', // Novos usuários sempre são 'user'
        'status' => 'active',
        'parent_user_id' => $referralUser,
        'apply_trial' => true, // Sempre aplicar teste grátis
        'trial_duration_days' => $trialDurationDays
    ];
    
    // Validações
    if (empty($data['username'])) {
        $message = 'Nome de usuário é obrigatório';
        $messageType = 'error';
    } elseif (strlen($data['username']) < 3) {
        $message = 'Nome de usuário deve ter pelo menos 3 caracteres';
        $messageType = 'error';
    } elseif (empty($data['email'])) {
        $message = 'Email é obrigatório';
        $messageType = 'error';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $message = 'Email inválido';
        $messageType = 'error';
    } elseif (strlen($data['password']) < 6) {
        $message = 'A senha deve ter pelo menos 6 caracteres';
        $messageType = 'error';
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $message = 'As senhas não coincidem';
        $messageType = 'error';
    } elseif (!isset($_POST['terms']) || $_POST['terms'] !== '1') {
        $message = 'Você deve aceitar os termos de uso';
        $messageType = 'error';
    } else {
        $result = $user->createUser($data);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            // Limpar código de referência da sessão após cadastro bem-sucedido
            unset($_SESSION['referral_code']);
            
            // Redirecionar para login com mensagem de sucesso
            $_SESSION['registration_success'] = true;
            $_SESSION['registration_message'] = "Conta criada com sucesso! Você tem {$trialDurationDays} dias de teste grátis. Faça login para começar.";
            header("Location: login.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - FutBanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Light Theme */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            /* Brand Colors */
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --primary-900: #1e3a8a;
            
            /* Status Colors */
            --success-50: #f0fdf4;
            --success-500: #22c55e;
            --success-600: #16a34a;
            --danger-50: #fef2f2;
            --danger-500: #ef4444;
            --danger-600: #dc2626;
            --warning-50: #fffbeb;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            
            /* Layout */
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Dark Theme */
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            --border-color: #334155;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-700) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            transition: var(--transition);
        }

        [data-theme="dark"] body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }

        .register-container {
            width: 100%;
            max-width: 500px;
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid var(--border-color);
            animation: slideIn 0.6s ease-out;
        }

        .register-header {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            color: white;
            position: relative;
        }

        .theme-toggle {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .logo {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.75rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            animation: pulse 2s infinite;
        }

        .register-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .register-subtitle {
            opacity: 0.9;
            font-size: 0.875rem;
            font-weight: 400;
        }

        .register-form {
            padding: 2rem;
        }

        .referral-info {
            background: var(--primary-50);
            border: 1px solid var(--primary-200);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .referral-info.admin {
            background: var(--warning-50);
            border-color: var(--warning-200);
        }

        .referral-info h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-700);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .referral-info.admin h3 {
            color: var(--warning-700);
        }

        .referral-info p {
            font-size: 0.875rem;
            color: var(--primary-600);
        }

        .referral-info.admin p {
            color: var(--warning-600);
        }

        .trial-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--success-50);
            color: var(--success-600);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            background: var(--bg-secondary);
            transition: var(--transition);
            position: relative;
        }

        .input-wrapper:focus-within {
            border-color: var(--primary-500);
            background: var(--bg-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input {
            width: 100%;
            flex-grow: 1;
            padding: 1rem;
            border: none;
            background: transparent;
            outline: none;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .input-icon-left {
            padding-left: 1rem;
            padding-right: 0.75rem;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .input-wrapper:focus-within .input-icon-left {
            color: var(--primary-500);
        }

        .password-toggle-icon {
            padding: 0 1rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }

        .password-toggle-icon:hover {
            color: var(--primary-500);
        }

        .checkbox-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .form-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.25rem;
            border: 2px solid var(--border-color);
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: var(--bg-primary);
            cursor: pointer;
            position: relative;
            transition: var(--transition);
            flex-shrink: 0;
            margin-top: 0.125rem;
        }

        .form-checkbox:checked {
            background-color: var(--primary-500);
            border-color: var(--primary-500);
        }

        .form-checkbox:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(45deg);
            width: 0.25rem;
            height: 0.5rem;
            border: solid white;
            border-width: 0 2px 2px 0;
        }

        .form-checkbox:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .checkbox-label a {
            color: var(--primary-500);
            text-decoration: none;
            font-weight: 500;
        }

        .checkbox-label a:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: var(--danger-50);
            color: var(--danger-600);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
        }

        [data-theme="dark"] .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-500);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .login-link p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .login-link a {
            color: var(--primary-500);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            width: 100%;
            height: 4px;
            background: var(--bg-tertiary);
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-text {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }

        .password-match {
            margin-top: 0.5rem;
        }

        .match-text {
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .register-header,
            .register-form {
                padding: 1.5rem;
            }
            
            .register-title {
                font-size: 1.5rem;
            }
            
            .logo {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }

        /* Dark theme adjustments */
        [data-theme="dark"] .referral-info {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.2);
        }

        [data-theme="dark"] .referral-info.admin {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.2);
        }

        [data-theme="dark"] .referral-info h3 {
            color: var(--primary-400);
        }

        [data-theme="dark"] .referral-info.admin h3 {
            color: var(--warning-400);
        }

        [data-theme="dark"] .referral-info p {
            color: var(--primary-300);
        }

        [data-theme="dark"] .referral-info.admin p {
            color: var(--warning-300);
        }

        [data-theme="dark"] .trial-badge {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success-400);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            
            <div class="logo">
                <i class="fas fa-futbol"></i>
            </div>
            <h1 class="register-title">Criar Conta</h1>
            <p class="register-subtitle">Junte-se ao FutBanner e comece a criar banners incríveis</p>
        </div>

        <div class="register-form">
            <!-- Referral Info -->
            <?php if ($referralUserData): ?>
            <div class="referral-info <?php echo $referralUserData['role'] === 'admin' ? 'admin' : ''; ?>">
                <h3>
                    <i class="fas fa-<?php echo $referralUserData['role'] === 'admin' ? 'crown' : 'user-friends'; ?>"></i>
                    <?php if ($referralUserData['role'] === 'admin'): ?>
                        Cadastro Direto
                    <?php else: ?>
                        Convidado por <?php echo htmlspecialchars($referralUserData['username']); ?>
                    <?php endif; ?>
                </h3>
                <p>
                    <?php if ($referralUserData['role'] === 'admin'): ?>
                        Você será gerenciado diretamente pela administração do sistema
                    <?php else: ?>
                        Você será gerenciado pelo usuário master <?php echo htmlspecialchars($referralUserData['username']); ?>
                    <?php endif; ?>
                </p>
                <div class="trial-badge">
                    <i class="fas fa-gift"></i>
                    <?php echo $trialDurationDays; ?> dias de teste grátis
                </div>
            </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <?php if ($referralCode): ?>
                    <input type="hidden" name="referral_code" value="<?php echo htmlspecialchars($referralCode); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i>
                        Nome de Usuário
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon-left"></i>
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="Digite seu nome de usuário" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i>
                        Email
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon-left"></i>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Digite seu email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Senha
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon-left"></i>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Mínimo de 6 caracteres" required autocomplete="new-password">
                        <i class="fas fa-eye password-toggle-icon" id="togglePassword"></i>
                    </div>
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <p class="strength-text" id="strengthText"></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-check"></i>
                        Confirmar Senha
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-check input-icon-left"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Repita sua senha" required autocomplete="new-password">
                        <i class="fas fa-eye password-toggle-icon" id="toggleConfirmPassword"></i>
                    </div>
                    <div class="password-match" id="passwordMatch" style="display: none;">
                        <p class="match-text" id="matchText"></p>
                    </div>
                </div>

                <div class="checkbox-wrapper">
                    <input type="checkbox" id="terms" name="terms" value="1" class="form-checkbox" required>
                    <label for="terms" class="checkbox-label">
                        Eu aceito os <a href="#" target="_blank">termos de uso</a> e a 
                        <a href="#" target="_blank">política de privacidade</a> do FutBanner
                    </label>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-user-plus"></i>
                    <span>Criar Conta Grátis</span>
                </button>
            </form>

            <div class="login-link">
                <p>Já tem uma conta? <a href="login.php">Faça login aqui</a></p>
            </div>
        </div>
    </div>

    <script>
        // Theme Management
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        body.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Form enhancements
        const registerForm = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');

        // Password toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            // Toggle password visibility
            function setupPasswordToggle(toggleBtn, inputField) {
                if (toggleBtn && inputField) {
                    toggleBtn.addEventListener('click', function() {
                        const type = inputField.getAttribute('type') === 'password' ? 'text' : 'password';
                        inputField.setAttribute('type', type);
                        this.classList.toggle('fa-eye');
                        this.classList.toggle('fa-eye-slash');
                    });
                }
            }

            setupPasswordToggle(togglePassword, passwordInput);
            setupPasswordToggle(toggleConfirmPassword, confirmPasswordInput);

            // Password strength indicator
            const passwordStrength = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            const passwordMatch = document.getElementById('passwordMatch');
            const matchText = document.getElementById('matchText');

            function checkPasswordStrength(password) {
                let strength = 0;
                
                if (password.length >= 6) strength += 1;
                if (password.length >= 8) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                const colors = ['#ef4444', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#16a34a'];
                const texts = ['Muito fraca', 'Fraca', 'Regular', 'Boa', 'Forte', 'Muito forte'];
                
                strengthFill.style.width = `${(strength / 6) * 100}%`;
                strengthFill.style.backgroundColor = colors[strength - 1] || colors[0];
                strengthText.textContent = texts[strength - 1] || texts[0];
                strengthText.style.color = colors[strength - 1] || colors[0];
                
                return strength;
            }

            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword) {
                    passwordMatch.style.display = 'block';
                    if (password === confirmPassword) {
                        matchText.textContent = '✓ Senhas coincidem';
                        matchText.style.color = 'var(--success-600)';
                        confirmPasswordInput.setCustomValidity('');
                    } else {
                        matchText.textContent = '✗ Senhas não coincidem';
                        matchText.style.color = 'var(--danger-600)';
                        confirmPasswordInput.setCustomValidity('As senhas não coincidem');
                    }
                } else {
                    passwordMatch.style.display = 'none';
                    confirmPasswordInput.setCustomValidity('');
                }
            }

            passwordInput.addEventListener('input', function() {
                const password = this.value;
                if (password) {
                    passwordStrength.style.display = 'block';
                    checkPasswordStrength(password);
                } else {
                    passwordStrength.style.display = 'none';
                }
                checkPasswordMatch();
            });

            confirmPasswordInput.addEventListener('input', checkPasswordMatch);

            // Auto-focus no campo de usuário
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.focus();
            }
        });

        // Form submission with loading state
        registerForm.addEventListener('submit', function(e) {
            submitBtn.classList.add('loading');
            const btnText = submitBtn.querySelector('span');
            if (btnText) {
                btnText.textContent = ' Criando conta...';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + T for theme toggle
            if (e.altKey && e.key === 't') {
                e.preventDefault();
                themeToggle.click();
            }
        });
    </script>
</body>
</html>