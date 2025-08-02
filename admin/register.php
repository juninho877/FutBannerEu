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

// Determinar o estágio atual do registro
$stage = 'whatsapp_input'; // Padrão: solicitar WhatsApp

if (isset($_SESSION['whatsapp_verification_stage'])) {
    $stage = $_SESSION['whatsapp_verification_stage'];
}

// Função para enviar código via WhatsApp
function sendWhatsAppCode($number, $code) {
    $url = 'https://bolt-teste.lenwap.easypanel.host/send-code';
    
    $data = [
        'number' => $number,
        'code' => $code,
        'message' => "*FutBanner*\nOlá! Seu código de Validação é: #code#.\nNão compartilhe com ninguém."
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: 8350e5a3e24c153df2275c9f80692773'
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        return ['success' => false, 'message' => 'Erro na conexão: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Erro no envio do código. Tente novamente.'];
    }
    
    return ['success' => true, 'message' => 'Código enviado com sucesso!'];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_whatsapp_code') {
        // Etapa 1: Enviar código para WhatsApp
        $whatsapp = trim($_POST['whatsapp']);
        
        // Validar número de WhatsApp
        if (empty($whatsapp)) {
            $message = 'Número de WhatsApp é obrigatório';
            $messageType = 'error';
        } elseif (!preg_match('/^\d{10,15}$/', preg_replace('/\D/', '', $whatsapp))) {
            $message = 'Número de WhatsApp inválido. Use apenas números (10-15 dígitos)';
            $messageType = 'error';
        } else {
            // Verificar se o WhatsApp já está em uso
            $stmt = $db->getConnection()->prepare("SELECT id FROM usuarios WHERE whatsapp = ?");
            $stmt->execute([$whatsapp]);
            if ($stmt->fetch()) {
                $message = 'Este número de WhatsApp já está cadastrado no sistema';
                $messageType = 'error';
            } else {
                // Gerar código de 6 dígitos
                $verificationCode = sprintf('%06d', mt_rand(0, 999999));
                
                // Enviar código via WhatsApp
                $sendResult = sendWhatsAppCode($whatsapp, $verificationCode);
                
                if ($sendResult['success']) {
                    // Armazenar dados de verificação na sessão
                    $_SESSION['whatsapp_verification'] = [
                        'number' => $whatsapp,
                        'code' => $verificationCode,
                        'expires_at' => time() + (10 * 60), // 10 minutos
                        'attempts' => 0
                    ];
                    $_SESSION['whatsapp_verification_stage'] = 'code_input';
                    
                    $message = 'Código enviado para seu WhatsApp! Verifique suas mensagens.';
                    $messageType = 'success';
                    $stage = 'code_input';
                } else {
                    $message = $sendResult['message'];
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'verify_whatsapp_code') {
        // Etapa 2: Verificar código do WhatsApp
        $inputCode = trim($_POST['verification_code']);
        
        if (!isset($_SESSION['whatsapp_verification'])) {
            $message = 'Sessão de verificação expirada. Solicite um novo código.';
            $messageType = 'error';
            $stage = 'whatsapp_input';
            unset($_SESSION['whatsapp_verification_stage']);
        } else {
            $verification = $_SESSION['whatsapp_verification'];
            
            // Verificar se não expirou
            if (time() > $verification['expires_at']) {
                $message = 'Código expirado. Solicite um novo código.';
                $messageType = 'error';
                $stage = 'whatsapp_input';
                unset($_SESSION['whatsapp_verification']);
                unset($_SESSION['whatsapp_verification_stage']);
            } elseif (empty($inputCode)) {
                $message = 'Digite o código de verificação';
                $messageType = 'error';
            } elseif ($verification['attempts'] >= 3) {
                $message = 'Muitas tentativas incorretas. Solicite um novo código.';
                $messageType = 'error';
                $stage = 'whatsapp_input';
                unset($_SESSION['whatsapp_verification']);
                unset($_SESSION['whatsapp_verification_stage']);
            } elseif ($inputCode !== $verification['code']) {
                // Incrementar tentativas
                $_SESSION['whatsapp_verification']['attempts']++;
                $remainingAttempts = 3 - $_SESSION['whatsapp_verification']['attempts'];
                $message = "Código incorreto. Você tem {$remainingAttempts} tentativas restantes.";
                $messageType = 'error';
            } else {
                // Código correto!
                $_SESSION['validated_whatsapp'] = $verification['number'];
                $_SESSION['whatsapp_verification_stage'] = 'registration_form';
                unset($_SESSION['whatsapp_verification']);
                
                $message = 'WhatsApp verificado com sucesso! Complete seu cadastro.';
                $messageType = 'success';
                $stage = 'registration_form';
            }
        }
    } elseif ($action === 'resend_code') {
        // Reenviar código
        if (isset($_SESSION['whatsapp_verification'])) {
            $whatsapp = $_SESSION['whatsapp_verification']['number'];
            $verificationCode = sprintf('%06d', mt_rand(0, 999999));
            
            $sendResult = sendWhatsAppCode($whatsapp, $verificationCode);
            
            if ($sendResult['success']) {
                $_SESSION['whatsapp_verification']['code'] = $verificationCode;
                $_SESSION['whatsapp_verification']['expires_at'] = time() + (10 * 60);
                $_SESSION['whatsapp_verification']['attempts'] = 0;
                
                $message = 'Novo código enviado para seu WhatsApp!';
                $messageType = 'success';
            } else {
                $message = $sendResult['message'];
                $messageType = 'error';
            }
        }
    } elseif ($action === 'complete_registration') {
        // Etapa 3: Completar registro
        if (!isset($_SESSION['validated_whatsapp'])) {
            $message = 'WhatsApp não foi verificado. Reinicie o processo.';
            $messageType = 'error';
            $stage = 'whatsapp_input';
            unset($_SESSION['whatsapp_verification_stage']);
        } else {
            $data = [
                'username' => trim($_POST['username']),
                'email' => trim($_POST['email']),
                'whatsapp' => $_SESSION['validated_whatsapp'],
                'password' => trim($_POST['password']),
                'role' => 'user',
                'status' => 'active',
                'parent_user_id' => $referralUser,
                'apply_trial' => true,
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
                    // Limpar todas as sessões de verificação
                    unset($_SESSION['referral_code']);
                    unset($_SESSION['validated_whatsapp']);
                    unset($_SESSION['whatsapp_verification_stage']);
                    unset($_SESSION['whatsapp_verification']);
                    
                    // Redirecionar para login com mensagem de sucesso
                    $_SESSION['registration_success'] = true;
                    $_SESSION['registration_message'] = "Conta criada com sucesso! Você tem {$trialDurationDays} dias de teste grátis. Faça login para começar.";
                    header("Location: login.php");
                    exit();
                }
            }
        }
    } elseif ($action === 'restart_verification') {
        // Reiniciar processo de verificação
        unset($_SESSION['whatsapp_verification']);
        unset($_SESSION['whatsapp_verification_stage']);
        unset($_SESSION['validated_whatsapp']);
        $stage = 'whatsapp_input';
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
        
        .register-logo-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 50%;
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

        .verification-step {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .step.active {
            background: var(--primary-500);
            color: white;
        }

        .step.completed {
            background: var(--success-500);
            color: white;
        }

        .step.pending {
            background: var(--bg-tertiary);
            color: var(--text-muted);
        }

        .step-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .step-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
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

        .verification-code-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5rem;
            font-family: monospace;
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

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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

        .submit-btn:hover:not(:disabled)::before {
            left: 100%;
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover:not(:disabled) {
            background: var(--bg-secondary);
            transform: translateY(-1px);
        }

        .message {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .message.success {
            background: var(--success-50);
            color: var(--success-600);
            border-color: rgba(34, 197, 94, 0.2);
        }

        .message.error {
            background: var(--danger-50);
            color: var(--danger-600);
            border-color: rgba(239, 68, 68, 0.2);
            animation: shake 0.5s ease-in-out;
        }

        [data-theme="dark"] .message.success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success-400);
        }

        [data-theme="dark"] .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-400);
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

        .verification-info {
            background: var(--warning-50);
            border: 1px solid var(--warning-200);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .verification-info h4 {
            color: var(--warning-700);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .verification-info p {
            color: var(--warning-600);
        }

        [data-theme="dark"] .verification-info {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.2);
        }

        [data-theme="dark"] .verification-info h4 {
            color: var(--warning-400);
        }

        [data-theme="dark"] .verification-info p {
            color: var(--warning-300);
        }

        .countdown {
            font-weight: 600;
            color: var(--primary-600);
        }

        [data-theme="dark"] .countdown {
            color: var(--primary-400);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .action-buttons .submit-btn {
            flex: 1;
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

            .action-buttons {
                flex-direction: column;
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

        [data-theme="dark"] .verification-step {
            background: var(--bg-tertiary);
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
                <?php
                $customLogoUrl = $systemSettings->getSystemLogoUrl();
                if ($customLogoUrl): ?>
                    <img src="<?php echo htmlspecialchars($customLogoUrl); ?>" alt="FutBanner" class="register-logo-img">
                <?php else: ?>
                    <i class="fas fa-futbol"></i>
                <?php endif; ?>
            </div>
            <h1 class="register-title">Criar Conta</h1>
            <p class="register-subtitle">
                <?php
                switch ($stage) {
                    case 'whatsapp_input':
                        echo 'Primeiro, vamos verificar seu WhatsApp';
                        break;
                    case 'code_input':
                        echo 'Digite o código que enviamos para seu WhatsApp';
                        break;
                    case 'registration_form':
                        echo 'Complete seu cadastro no FutBanner';
                        break;
                }
                ?>
            </p>
        </div>

        <div class="register-form">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $stage === 'whatsapp_input' ? 'active' : ($stage === 'code_input' || $stage === 'registration_form' ? 'completed' : 'pending'); ?>">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div class="step <?php echo $stage === 'code_input' ? 'active' : ($stage === 'registration_form' ? 'completed' : 'pending'); ?>">
                    <i class="fas fa-key"></i>
                </div>
                <div class="step <?php echo $stage === 'registration_form' ? 'active' : 'pending'; ?>">
                    <i class="fas fa-user-plus"></i>
                </div>
            </div>

            <!-- Referral Info -->
            <?php if ($referralUserData && $stage === 'registration_form'): ?>
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

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Stage 1: WhatsApp Input -->
            <?php if ($stage === 'whatsapp_input'): ?>
            <div class="verification-step">
                <div class="step-title">
                    <i class="fab fa-whatsapp text-success-500"></i>
                    Verificação por WhatsApp
                </div>
                <div class="step-description">
                    Para garantir a segurança, precisamos verificar seu número de WhatsApp
                </div>
            </div>

            <form method="POST" action="" id="whatsappForm">
                <input type="hidden" name="action" value="send_whatsapp_code">
                <?php if ($referralCode): ?>
                    <input type="hidden" name="referral_code" value="<?php echo htmlspecialchars($referralCode); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="whatsapp" class="form-label">
                        <i class="fab fa-whatsapp"></i>
                        Número do WhatsApp
                    </label>
                    <div class="input-wrapper">
                        <i class="fab fa-whatsapp input-icon-left"></i>
                        <input type="tel" id="whatsapp" name="whatsapp" class="form-input" 
                               placeholder="11999999999" required 
                               value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>"
                               pattern="[0-9]{10,15}">
                    </div>
                    <p class="text-xs text-muted mt-1">
                        Digite apenas números (ex: 5511999999999)
                    </p>
                </div>

                <button type="submit" class="submit-btn" id="sendCodeBtn">
                    <i class="fab fa-whatsapp"></i>
                    <span>Enviar Código de Verificação</span>
                </button>
            </form>
            <?php endif; ?>

            <!-- Stage 2: Code Verification -->
            <?php if ($stage === 'code_input'): ?>
            <div class="verification-step">
                <div class="step-title">
                    <i class="fas fa-key text-primary-500"></i>
                    Código de Verificação
                </div>
                <div class="step-description">
                    Enviamos um código de 6 dígitos para: <strong><?php echo htmlspecialchars($_SESSION['whatsapp_verification']['number'] ?? ''); ?></strong>
                </div>
            </div>

            <div class="verification-info">
                <h4>
                    <i class="fas fa-clock"></i>
                    Código válido por <span class="countdown" id="countdown">10:00</span>
                </h4>
                <p>Verifique suas mensagens no WhatsApp e digite o código de 6 dígitos</p>
            </div>

            <form method="POST" action="" id="verifyCodeForm">
                <input type="hidden" name="action" value="verify_whatsapp_code">

                <div class="form-group">
                    <label for="verification_code" class="form-label">
                        <i class="fas fa-key"></i>
                        Código de Verificação
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-key input-icon-left"></i>
                        <input type="text" id="verification_code" name="verification_code" class="form-input verification-code-input" 
                               placeholder="123456" required maxlength="6" pattern="[0-9]{6}">
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-check"></i>
                        <span>Verificar Código</span>
                    </button>
                    
                    <button type="button" class="submit-btn btn-secondary" id="resendCodeBtn">
                        <i class="fas fa-redo"></i>
                        <span>Reenviar Código</span>
                    </button>
                </div>

                <div class="action-buttons mt-3">
                    <button type="button" class="submit-btn btn-secondary" onclick="restartVerification()">
                        <i class="fas fa-arrow-left"></i>
                        <span>Alterar Número</span>
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <!-- Stage 3: Complete Registration -->
            <?php if ($stage === 'registration_form'): ?>
            <div class="verification-step">
                <div class="step-title">
                    <i class="fas fa-check-circle text-success-500"></i>
                    WhatsApp Verificado
                </div>
                <div class="step-description">
                    Número verificado: <strong><?php echo htmlspecialchars($_SESSION['validated_whatsapp'] ?? ''); ?></strong>
                </div>
            </div>

            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="action" value="complete_registration">
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

                <div class="action-buttons mt-3">
                    <button type="button" class="submit-btn btn-secondary" onclick="restartVerification()">
                        <i class="fas fa-arrow-left"></i>
                        <span>Alterar WhatsApp</span>
                    </button>
                </div>
            </form>
            <?php endif; ?>

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

        // WhatsApp number formatting
        const whatsappInput = document.getElementById('whatsapp');
        if (whatsappInput) {
            whatsappInput.addEventListener('input', function(e) {
                // Remove non-numeric characters
                let value = e.target.value.replace(/\D/g, '');
                
                // Limit to 15 digits
                if (value.length > 15) {
                    value = value.substring(0, 15);
                }
                
                e.target.value = value;
            });

            // Auto-focus
            whatsappInput.focus();
        }

        // Verification code formatting
        const verificationCodeInput = document.getElementById('verification_code');
        if (verificationCodeInput) {
            verificationCodeInput.addEventListener('input', function(e) {
                // Remove non-numeric characters
                let value = e.target.value.replace(/\D/g, '');
                
                // Limit to 6 digits
                if (value.length > 6) {
                    value = value.substring(0, 6);
                }
                
                e.target.value = value;
                
                // Auto-submit when 6 digits are entered
                if (value.length === 6) {
                    document.getElementById('verifyCodeForm').submit();
                }
            });

            // Auto-focus
            verificationCodeInput.focus();
        }

        // Countdown timer for code expiration
        <?php if ($stage === 'code_input' && isset($_SESSION['whatsapp_verification'])): ?>
        const expiresAt = <?php echo $_SESSION['whatsapp_verification']['expires_at']; ?>;
        const countdownElement = document.getElementById('countdown');
        
        function updateCountdown() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = expiresAt - now;
            
            if (remaining <= 0) {
                countdownElement.textContent = 'Expirado';
                countdownElement.style.color = 'var(--danger-500)';
                
                // Disable form
                const form = document.getElementById('verifyCodeForm');
                const inputs = form.querySelectorAll('input, button');
                inputs.forEach(input => input.disabled = true);
                
                // Show restart button
                setTimeout(() => {
                    location.reload();
                }, 2000);
                
                return;
            }
            
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateCountdown();
        const countdownInterval = setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // Resend code functionality
        const resendCodeBtn = document.getElementById('resendCodeBtn');
        if (resendCodeBtn) {
            resendCodeBtn.addEventListener('click', function() {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Reenviando...</span>';
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="resend_code">';
                document.body.appendChild(form);
                form.submit();
            });
        }

        // Password toggle functionality
        function setupPasswordToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            
            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        }

        setupPasswordToggle('togglePassword', 'password');
        setupPasswordToggle('toggleConfirmPassword', 'confirm_password');

        // Password confirmation validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        if (passwordInput && confirmPasswordInput) {
            function checkPasswordMatch() {
                if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('As senhas não coincidem');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            }

            passwordInput.addEventListener('input', checkPasswordMatch);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }

        // Form submission loading states
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    const originalContent = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Processando...</span>';
                    
                    // Re-enable after 5 seconds to prevent permanent lock
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalContent;
                    }, 5000);
                }
            });
        });

        // Restart verification function
        function restartVerification() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="restart_verification">';
            document.body.appendChild(form);
            form.submit();
        }

        // Auto-focus on page load
        document.addEventListener('DOMContentLoaded', function() {
            const stage = '<?php echo $stage; ?>';
            
            if (stage === 'whatsapp_input') {
                const whatsappInput = document.getElementById('whatsapp');
                if (whatsappInput) whatsappInput.focus();
            } else if (stage === 'code_input') {
                const codeInput = document.getElementById('verification_code');
                if (codeInput) codeInput.focus();
            } else if (stage === 'registration_form') {
                const usernameInput = document.getElementById('username');
                if (usernameInput) usernameInput.focus();
            }
        });
    </script>
</body>
</html>