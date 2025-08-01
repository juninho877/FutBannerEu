<?php ob_start(); ?>
<?php
session_start();

// Incluir as classes necessárias
require_once 'config/database.php';
require_once 'classes/User.php';

// Inicializar banco de dados (criar tabelas se não existirem)
try {
    $db = Database::getInstance();
    $db->createTables();
} catch (Exception $e) {
    $erro = "Erro de conexão com o banco de dados. Verifique as configurações.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    try {
        $user = new User();
        $result = $user->authenticate($username, $password);
        
        if ($result['success']) {
            $_SESSION["usuario"] = $result['user']['username'];
            $_SESSION["user_id"] = $result['user']['id'];
            $_SESSION["role"] = $result['user']['role'];
            header("Location: index.php");
            exit();
        } else {
            // Verificar se a conta está expirada
            if ($result['message'] === 'Conta expirada') {
                // Buscar o usuário pelo nome de usuário para obter o ID
                $stmt = $db->getConnection()->prepare("SELECT id FROM usuarios WHERE username = ?");
                $stmt->execute([$username]);
                $userId = $stmt->fetchColumn();
                
                if ($userId) {
                    // Armazenar o ID do usuário na sessão temporariamente para o pagamento
                    $_SESSION["temp_user_id"] = $userId;
                    $_SESSION["temp_username"] = $username;
                    
                    // Redirecionar para a página de pagamento com parâmetro de conta expirada
                    header("Location: payment.php?expired=true");
                    exit();
                }
            }
            $erro = $result['message'];
        }
    } catch (Exception $e) {
        $erro = "Erro interno do sistema. Tente novamente.";
    }
}

// Verificar se existe uma mensagem de sucesso de login após renovação
$loginSuccess = isset($_SESSION['login_success']) && $_SESSION['login_success'];
$loginMessage = isset($_SESSION['login_message']) ? $_SESSION['login_message'] : '';

// Verificar se existe uma mensagem de sucesso de cadastro
$registrationSuccess = isset($_SESSION['registration_success']) && $_SESSION['registration_success'];
$registrationMessage = isset($_SESSION['registration_message']) ? $_SESSION['registration_message'] : '';

// Limpar mensagens da sessão após uso
if (isset($_SESSION['login_success'])) {
    unset($_SESSION['login_success']);
    unset($_SESSION['login_message']);
}

if (isset($_SESSION['registration_success'])) {
    unset($_SESSION['registration_success']);
    unset($_SESSION['registration_message']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FutBanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php
    // Favicon dinâmico
    require_once 'classes/SystemSettings.php';
    $systemSettings = new SystemSettings();
    $faviconUrl = $systemSettings->getFaviconUrl();
    ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    
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

        .login-container {
            width: 100%;
            max-width: 420px;
            background: var(--bg-primary);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid var(--border-color);
            animation: slideIn 0.6s ease-out;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            padding: 3rem 2rem 2rem;
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
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            animation: pulse 2s infinite;
        }
        
        .login-logo-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 50%;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .login-subtitle {
            opacity: 0.9;
            font-size: 1rem;
            font-weight: 400;
        }

        .login-form {
            padding: 2.5rem 2rem;
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
        
        /* --- INÍCIO DAS ALTERAÇÕES NO CSS --- */

        /* NOVO: Wrapper para alinhar ícone e input com Flexbox */
        .input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            background: var(--bg-secondary);
            transition: var(--transition);
            position: relative;
        }

        /* NOVO: Efeito de foco no wrapper */
        .input-wrapper:focus-within {
            border-color: var(--primary-500);
            background: var(--bg-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* ALTERADO: Input agora não tem mais borda ou padding próprio */
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
            transition: var(--transition); /* Mantido para outras propriedades se houver */
        }
        
        /* NOVO: Ícone da esquerda */
        .input-icon-left {
            padding-left: 1rem;
            padding-right: 0.75rem;
            color: var(--text-muted);
            transition: var(--transition);
        }

        /* NOVO: Muda cor do ícone da esquerda no foco */
        .input-wrapper:focus-within .input-icon-left {
            color: var(--primary-500);
        }

        /* NOVO: Ícone para mostrar/esconder senha (olho) */
        .password-toggle-icon {
            padding: 0 1rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }
        .password-toggle-icon:hover {
            color: var(--primary-500);
        }

        /* As classes antigas .input-icon e .form-input:focus podem ser removidas se existirem */

        /* --- FIM DAS ALTERAÇÕES NO CSS --- */


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
            color: var(--danger-500); /* Cor ajustada para melhor contraste no tema escuro */
        }

        /* Success message */
        .success-message {
            background: var(--success-50);
            color: var(--success-600);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        [data-theme="dark"] .success-message {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success-400);
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .welcome-text h3 {
            color: var(--text-primary);
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .register-link p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .register-link a {
            color: var(--primary-500);
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
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
            .login-header,
            .login-form {
                padding: 2rem 1.5rem;
            }
            
            .login-title {
                font-size: 1.75rem;
            }
            
            .logo {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }
        }

        /* Focus states for accessibility */
        .theme-toggle:focus,
        .submit-btn:focus {
            outline: 2px solid rgba(255, 255, 255, 0.5);
            outline-offset: 2px;
        }

        /* Loading state */
        .submit-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .submit-btn.loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            
            <div class="logo">
                <?php
                $customLogoUrl = $systemSettings->getSystemLogoUrl();
                if ($customLogoUrl): ?>
                    <img src="<?php echo htmlspecialchars($customLogoUrl); ?>" alt="FutBanner" class="login-logo-img">
                <?php else: ?>
                    <i class="fas fa-futbol"></i>
                <?php endif; ?>
            </div>
            <h1 class="login-title">FutBanner</h1>
            <p class="login-subtitle">Sistema de Geração de Banners</p>
        </div>

        <div class="login-form">
            <?php if ($loginSuccess): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $loginMessage; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($registrationSuccess): ?>
            <div class="success-message">
                <i class="fas fa-user-plus"></i>
                <?php echo $registrationMessage; ?>
            </div>
            <?php endif; ?>
            
            <div class="welcome-text">
                <h3>Bem-vindo de volta!</h3>
                <p>Faça login para acessar o painel administrativo</p>
            </div>

            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i>
                        Usuário
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon-left"></i>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Digite seu usuário" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Senha
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon-left"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Digite sua senha" required autocomplete="current-password">
                        <i class="fas fa-eye password-toggle-icon" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Entrar no Sistema
                </button>

                <?php if (isset($erro)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $erro; ?>
                    </div>
                <?php endif; ?>
                
                <div class="register-link">
                    <p>Não tem uma conta? <a href="register.php">Cadastre-se aqui</a></p>
                </div>
            </form>
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
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const inputs = document.querySelectorAll('.form-input');

        // Input focus animations (mantido como estava)
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                // Animação agora é controlada pelo :focus-within no CSS, mas pode deixar para outras lógicas se quiser
            });
            
            input.addEventListener('blur', function() {
                 // Animação agora é controlada pelo CSS
            });
        });

        // Form submission with loading state
        loginForm.addEventListener('submit', function(e) {
            submitBtn.classList.add('loading');
            
            // Para não remover o ícone do botão, vamos envolvê-lo num span
            const btnText = submitBtn.querySelector('span');
            if(!btnText) { // Adiciona o span se não existir, para o código funcionar
                submitBtn.innerHTML = `<i class="fas fa-sign-in-alt"></i><span>${submitBtn.textContent.trim()}</span>`
            }
            submitBtn.querySelector('span').textContent = ' Entrando...';
        });

        // --- INÍCIO DO NOVO SCRIPT PARA VER SENHA E FOCO ---
        document.addEventListener('DOMContentLoaded', function() {
            // Lógica para mostrar/esconder senha
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);

                    // Troca o ícone (olho aberto / olho fechado)
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            // Auto-focus no campo de usuário
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.focus();
            }

            // Ajuste para o texto do botão no loading state
            const btnTextSpan = document.createElement('span');
            btnTextSpan.textContent = ' Entrar no Sistema';
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i>';
            submitBtn.appendChild(btnTextSpan);
        });
        // --- FIM DO NOVO SCRIPT ---

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
<?php ob_end_flush(); ?>