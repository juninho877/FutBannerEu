<?php ob_start(); // Start output buffering at the very beginning
session_start();
if (!isset($_SESSION["usuario"]) && !isset($_SESSION["temp_user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';
require_once 'classes/MercadoPago.php';
require_once 'classes/MercadoPagoSettings.php';
require_once 'config/database.php'; // Ensure database is available for logging

// Verificar se há mensagem de sucesso
$successMessage = '';
if (isset($_SESSION['payment_success']) && $_SESSION['payment_success']) {
    $successMessage = $_SESSION['payment_message'] ?? "Pagamento confirmado com sucesso!";
    unset($_SESSION['payment_success']);
    unset($_SESSION['payment_message']);
    
    // Se o pagamento foi bem-sucedido e era um usuário com conta expirada, redirecionar para login
    if (isset($_SESSION["temp_user_id"])) {
        // Limpar dados temporários
        unset($_SESSION["temp_user_id"]);
        unset($_SESSION["temp_username"]);
        
        // Redirecionar para login com mensagem de sucesso
        $_SESSION['login_success'] = true;
        $_SESSION['login_message'] = "Sua assinatura foi renovada com sucesso! Faça login para continuar.";
        header("Location: login.php");
        exit();
    }
}

$user = new User();
$mercadoPago = new MercadoPago();
$mercadoPagoSettings = new MercadoPagoSettings();

// Determinar o ID do usuário (usuário logado ou usuário temporário com conta expirada)
$userId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : (isset($_SESSION["temp_user_id"]) ? $_SESSION["temp_user_id"] : null);
$userData = $user->getUserById($userId);

// Verificar se a conta está expirada
$isExpired = false;
$isExpiredRedirect = isset($_GET['expired']) && $_GET['expired'] === 'true';

if ($userData['expires_at'] && strtotime($userData['expires_at']) < time()) {
    $isExpired = true;
}

// Determine the owner of the payment (who receives the money)
// If user has a parent (master), use parent's Mercado Pago settings
// Otherwise, use admin (ID 1) settings
$parentUserId = $userData['parent_user_id'] ?? null;
$ownerUserId = $parentUserId ?: 1;

// Buscar configurações do dono do pagamento
$ownerSettings = $mercadoPagoSettings->getSettings($ownerUserId);
if (!$ownerSettings || empty($ownerSettings['access_token'])) {
    // Fallback to admin settings if owner has no settings
    $ownerSettings = $mercadoPagoSettings->getSettings(1);
    $ownerUserId = 1;
}

$basePrice = $ownerSettings['user_access_value'] ?? 29.90;
$whatsappNumber = $ownerSettings['whatsapp_number'] ?? '5511999999999';
$discount3Months = $ownerSettings['discount_3_months_percent'] ?? 5.00;
$discount6Months = $ownerSettings['discount_6_months_percent'] ?? 10.00;
$discount12Months = $ownerSettings['discount_12_months_percent'] ?? 15.00;

// Calcular preços com desconto
$price1Month = $basePrice;
$price3Months = ($basePrice * 3) * (1 - ($discount3Months / 100));
$price6Months = ($basePrice * 6) * (1 - ($discount6Months / 100));
$price12Months = ($basePrice * 12) * (1 - ($discount12Months / 100));

// Verificar se há um pagamento em andamento
$paymentInProgress = isset($_SESSION['payment_qr_code']) && !empty($_SESSION['payment_qr_code']);
$paymentCreatedAt = isset($_SESSION['payment_created_at']) ? $_SESSION['payment_created_at'] : null;

// Verificar se o QR code expirou (aumentado para 30 minutos)
$qrCodeExpired = false;
if ($paymentCreatedAt && (time() - $paymentCreatedAt > 1800)) { // 30 minutos em segundos
    $qrCodeExpired = true;
    // Limpar dados do pagamento expirado
    unset($_SESSION['payment_qr_code']);
    unset($_SESSION['payment_created_at']);
    unset($_SESSION['payment_id']);
    unset($_SESSION['payment_months']);
    unset($_SESSION['payment_amount']);
    $paymentInProgress = false;
}

// Automatically generate payment for expired users
if (($isExpired || $isExpiredRedirect) && !$paymentInProgress && !isset($successMessage)) {
    // Simulate form submission for 1-month subscription
    $_POST['action'] = 'create_payment';
    $_POST['amount'] = $price1Month;
    $_POST['months'] = 1;
    
    // Create payment
    $result = $mercadoPago->createSubscriptionPayment($userId, $price1Month, 1, $ownerUserId);
    
    if ($result['success']) {
        $_SESSION['payment_qr_code'] = $result['qr_code'];
        $_SESSION['payment_id'] = $result['payment_id'];
        $_SESSION['payment_created_at'] = time();
        $_SESSION['payment_months'] = 1;
        $_SESSION['payment_amount'] = $price1Month;
        
        $paymentInProgress = true;
    } else {
        $errorMessage = $result['message'];
    }
}

// Processar solicitação de pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_payment') {
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : $basePrice; // Valor padrão
        $months = isset($_POST['months']) ? intval($_POST['months']) : 1; // Meses padrão
        
        // Calcular valor total
        $totalAmount = $amount;
        
        // Criar pagamento
        $result = $mercadoPago->createSubscriptionPayment($userId, $totalAmount, $months, $ownerUserId);
        
        if ($result['success']) {
            $_SESSION['payment_qr_code'] = $result['qr_code'];
            $_SESSION['payment_id'] = $result['payment_id'];
            $_SESSION['payment_created_at'] = time();
            $_SESSION['payment_months'] = $months;
            $_SESSION['payment_amount'] = $totalAmount;
            
            // Redirecionar para evitar reenvio do formulário
            header('Location: payment.php' . ($isExpiredRedirect ? '?expired=true' : ''));
            exit;
        } else {
            $errorMessage = $result['message'];
        }
    } elseif ($_POST['action'] === 'cancel_payment') {
        // Limpar dados do pagamento
        unset($_SESSION['payment_qr_code']);
        unset($_SESSION['payment_created_at']);
        unset($_SESSION['payment_id']);
        unset($_SESSION['payment_months']);
        unset($_SESSION['payment_amount']);
        
        // Redirecionar para evitar reenvio do formulário
        header('Location: payment.php' . ($isExpiredRedirect ? '?expired=true' : ''));
        exit;
    }
}

$pageTitle = "Pagamento";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-credit-card text-primary-500 mr-3"></i>
        <?php echo $isExpired ? 'Renovar Assinatura' : 'Gerenciar Assinatura'; ?>
    </h1>
    <p class="page-subtitle">
        <?php echo $isExpired ? 'Sua assinatura expirou. Renove para continuar usando o sistema.' : 'Gerencie sua assinatura e métodos de pagamento'; ?>
    </p>
</div>

<?php if ($isExpired || $isExpiredRedirect): ?>
<div class="alert alert-warning mb-6">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <p class="font-medium">Sua assinatura expirou em <?php echo date('d/m/Y', strtotime($userData['expires_at'])); ?></p>
        <p class="text-sm mt-1">Renove sua assinatura para continuar utilizando o sistema.</p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success mb-6">
    <i class="fas fa-check-circle"></i>
    <div>
        <p class="font-medium">Pagamento Confirmado!</p>
        <p class="text-sm mt-1"><?php echo $successMessage; ?></p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
<div class="alert alert-error mb-6">
    <i class="fas fa-exclamation-circle"></i>
    <div>
        <p class="font-medium">Erro no Pagamento</p>
        <p class="text-sm mt-1"><?php echo $errorMessage; ?></p>
    </div>
</div>
<?php endif; ?>

<div id="payment-status-message" style="display: none;"></div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Informações da Assinatura -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informações da Assinatura</h3>
                <p class="card-subtitle">Detalhes da sua assinatura atual</p>
            </div>
            <div class="card-body">
                <div class="subscription-info">
                    <div class="subscription-status <?php echo $isExpired ? 'expired' : 'active'; ?>">
                        <div class="status-icon">
                            <i class="fas fa-<?php echo $isExpired ? 'times' : 'check'; ?>"></i>
                        </div>
                        <div class="status-text">
                            <h4><?php echo $isExpired ? 'Assinatura Expirada' : 'Assinatura Ativa'; ?></h4>
                            <p>
                                <?php 
                                if ($isExpired) {
                                    echo "Expirou em " . date('d/m/Y', strtotime($userData['expires_at']));
                                } elseif (!empty($userData['expires_at'])) {
                                    echo "Válida até " . date('d/m/Y', strtotime($userData['expires_at']));
                                    
                                    // Calcular dias restantes
                                    $expiryDate = new DateTime($userData['expires_at']);
                                    $today = new DateTime();
                                    $daysRemaining = $today->diff($expiryDate)->days;
                                    
                                    echo " ({$daysRemaining} dias restantes)";
                                } else {
                                    echo "Sem data de expiração definida";
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="subscription-details">
                        <div class="detail-item">
                            <span class="detail-label">Usuário:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($userData['username']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Plano:</span>
                            <span class="detail-value">Acesso Premium</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value <?php echo $isExpired ? 'text-danger-500' : 'text-success-500'; ?>">
                                <?php echo $isExpired ? 'Expirado' : 'Ativo'; ?>
                            </span>
                        </div>
                        <?php if (!empty($userData['last_login'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Último acesso:</span>
                            <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($userData['last_login'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($paymentInProgress && !$qrCodeExpired): ?>
        <!-- QR Code de Pagamento -->
        <div class="card mt-6">
            <div class="card-header">
                <h3 class="card-title">Pagamento em Andamento</h3>
                <p class="card-subtitle">Escaneie o QR Code para finalizar o pagamento</p>
            </div>
            <div class="card-body">
                <div class="qr-code-container">
                    <div class="qr-code">
                        <img src="<?php echo $_SESSION['payment_qr_code']; ?>" alt="QR Code de Pagamento">
                    </div>
                    <div class="qr-code-info">
                        <p class="qr-code-amount">R$ <?php echo number_format($_SESSION['payment_amount'] ?? 0, 2, ',', '.'); ?></p>
                        <p class="qr-code-description">
                            Assinatura por <?php echo $_SESSION['payment_months'] ?? 1; ?> 
                            <?php echo ($_SESSION['payment_months'] ?? 1) > 1 ? 'meses' : 'mês'; ?>
                        </p>
                        <p class="qr-code-expiry">
                            <i class="fas fa-clock"></i>
                            QR Code válido por 30 minutos
                        </p>
                        <div class="qr-code-actions">
                            <button type="button" id="check-payment-btn" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i>
                                Verificar Pagamento
                            </button>
                            <form method="post" action="">
                                <input type="hidden" name="action" value="cancel_payment">
                                <?php if ($isExpiredRedirect): ?>
                                <input type="hidden" name="expired" value="true">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Cancelar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Formulário de Pagamento -->
        <div class="card mt-6">
            <div class="card-header">
                <h3 class="card-title"><?php echo $isExpired ? 'Renovar Assinatura' : 'Estender Assinatura'; ?></h3>
                <p class="card-subtitle">Escolha o período e método de pagamento</p>
            </div>
            <div class="card-body">
                <form method="post" action="" id="paymentForm">
                    <input type="hidden" name="action" value="create_payment">
                    
                    <div class="form-group">
                        <label for="months" class="form-label">Período de Assinatura</label>
                        <select id="months" name="months" class="form-input form-select">
                            <option value="1" data-price="<?php echo number_format($price1Month, 2, '.', ''); ?>">
                                1 mês - R$ <?php echo number_format($price1Month, 2, ',', '.'); ?>
                            </option>
                            <option value="3" data-price="<?php echo number_format($price3Months, 2, '.', ''); ?>" data-regular="<?php echo number_format($price1Month * 3, 2, '.', ''); ?>" data-discount="<?php echo number_format($discount3Months, 1); ?>">
                                3 meses - R$ <?php echo number_format($price3Months, 2, ',', '.'); ?> (<?php echo number_format($discount3Months, 1); ?>% de desconto)
                            </option>
                            <option value="6" data-price="<?php echo number_format($price6Months, 2, '.', ''); ?>" data-regular="<?php echo number_format($price1Month * 6, 2, '.', ''); ?>" data-discount="<?php echo number_format($discount6Months, 1); ?>">
                                6 meses - R$ <?php echo number_format($price6Months, 2, ',', '.'); ?> (<?php echo number_format($discount6Months, 1); ?>% de desconto)
                            </option>
                            <option value="12" data-price="<?php echo number_format($price12Months, 2, '.', ''); ?>" data-regular="<?php echo number_format($price1Month * 12, 2, '.', ''); ?>" data-discount="<?php echo number_format($discount12Months, 1); ?>">
                                12 meses - R$ <?php echo number_format($price12Months, 2, ',', '.'); ?> (<?php echo number_format($discount12Months, 1); ?>% de desconto)
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method" class="form-label">Método de Pagamento</label>
                        <div class="payment-methods">
                            <div class="payment-method active">
                                <input type="radio" name="payment_method" id="pix" value="pix" checked>
                                <label for="pix">
                                    <img src="https://logopng.com.br/logos/pix-106.png" alt="PIX">
                                    <span>PIX</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-summary">
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span id="subtotal">R$ <?php echo number_format($price1Month, 2, ',', '.'); ?></span>
                        </div>
                        <div class="summary-item discount">
                            <span>Desconto:</span>
                            <span id="discount">R$ 0,00</span>
                        </div>
                        <div class="summary-item total">
                            <span>Total:</span>
                            <span id="total">R$ <?php echo number_format($price1Month, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <input type="hidden" name="amount" id="amount" value="<?php echo number_format($price1Month, 2, '.', ''); ?>">
                    
                    <button type="submit" class="btn btn-primary w-full mt-4">
                        <i class="fas fa-lock"></i>
                        Gerar QR Code para Pagamento
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Benefícios -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Benefícios da Assinatura</h3>
            </div>
            <div class="card-body">
                <div class="benefits-list">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Acesso Completo</h4>
                            <p>Todos os geradores de banners</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Sem Limites</h4>
                            <p>Gere quantos banners quiser</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Personalização</h4>
                            <p>Logos e fundos personalizados</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Suporte Prioritário</h4>
                            <p>Atendimento exclusivo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Perguntas Frequentes</h3>
            </div>
            <div class="card-body">
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Como funciona o pagamento?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>O pagamento é feito via PIX. Após gerar o QR Code, você pode escaneá-lo com seu aplicativo bancário para finalizar o pagamento.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Quanto tempo leva para confirmar o pagamento?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Pagamentos via PIX são confirmados em poucos minutos. Após o pagamento, clique em "Verificar Pagamento" para atualizar seu status.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Posso cancelar minha assinatura?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Não é necessário cancelar, pois não há renovação automática. Sua assinatura simplesmente expira na data indicada.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suporte -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Precisa de Ajuda?</h3>
            </div>
            <div class="card-body">
                <p class="mb-4">Se você tiver dúvidas ou problemas com o pagamento, entre em contato com nosso suporte:</p>
                <a href="https://wa.me/<?php echo $whatsappNumber; ?>" target="_blank" class="btn btn-success w-full">
                    <i class="fab fa-whatsapp"></i>
                    Suporte via WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .alert {
        padding: 1rem;
        border-radius: 12px;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
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
    
    .alert-info {
        background: var(--primary-50);
        color: var(--primary-600);
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .subscription-info {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .subscription-status {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        border-radius: var(--border-radius);
    }
    
    .subscription-status.active {
        background: var(--success-50);
    }
    
    .subscription-status.expired {
        background: var(--danger-50);
    }
    
    .status-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .subscription-status.active .status-icon {
        background: var(--success-500);
        color: white;
    }
    
    .subscription-status.expired .status-icon {
        background: var(--danger-500);
        color: white;
    }
    
    .status-text h4 {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .subscription-status.active .status-text h4 {
        color: var(--success-700);
    }
    
    .subscription-status.expired .status-text h4 {
        color: var(--danger-700);
    }
    
    .status-text p {
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
    
    .subscription-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .detail-item {
        padding: 1rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .detail-label {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    
    .detail-value {
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .payment-methods {
        display: flex;
        gap: 1rem;
        margin-top: 0.5rem;
    }
    
    .payment-method {
        flex: 1;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1rem;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .payment-method.active {
        border-color: var(--primary-500);
        background: var(--primary-50);
    }
    
    .payment-method input {
        display: none;
    }
    
    .payment-method label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }
    
    .payment-method img {
        height: 40px;
        object-fit: contain;
    }
    
    .payment-summary {
        margin-top: 1.5rem;
        padding: 1.5rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .summary-item:last-child {
        border-bottom: none;
    }
    
    .summary-item.total {
        font-weight: 700;
        font-size: 1.125rem;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 2px solid var(--border-color);
    }
    
    .summary-item.discount {
        color: var(--success-600);
    }
    
    .qr-code-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5rem;
    }
    
    @media (min-width: 768px) {
        .qr-code-container {
            flex-direction: row;
            align-items: flex-start;
        }
    }
    
    .qr-code {
        padding: 1.5rem;
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        text-align: center;
    }
    
    .qr-code img {
        max-width: 200px;
        height: auto;
    }
    
    .qr-code-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .qr-code-amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-600);
    }
    
    .qr-code-description {
        color: var(--text-secondary);
    }
    
    .qr-code-expiry {
        color: var(--warning-600);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .qr-code-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .benefits-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .benefit-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .benefit-icon {
        width: 24px;
        height: 24px;
        background: var(--primary-500);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
    }
    
    .benefit-text h4 {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .benefit-text p {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    
    .faq-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .faq-item {
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        overflow: hidden;
    }
    
    .faq-question {
        padding: 1rem;
        background: var(--bg-secondary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        font-weight: 500;
    }
    
    .faq-question i {
        transition: transform 0.3s ease;
    }
    
    .faq-item.active .faq-question i {
        transform: rotate(180deg);
    }
    
    .faq-answer {
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .faq-item.active .faq-answer {
        padding: 1rem;
        max-height: 200px;
    }
    
    .faq-answer p {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    
    .text-danger-500 {
        color: var(--danger-500);
    }
    
    .text-success-500 {
        color: var(--success-500);
    }
    
    .mt-1 {
        margin-top: 0.25rem;
    }
    
    .mt-4 {
        margin-top: 1rem;
    }
    
    .mt-6 {
        margin-top: 1.5rem;
    }
    
    .mb-4 {
        margin-bottom: 1rem;
    }
    
    .mb-6 {
        margin-bottom: 1.5rem;
    }
    
    .w-full {
        width: 100%;
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
    
    [data-theme="dark"] .alert-info {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .subscription-status.active {
        background: rgba(34, 197, 94, 0.1);
    }
    
    [data-theme="dark"] .subscription-status.expired {
        background: rgba(239, 68, 68, 0.1);
    }
    
    [data-theme="dark"] .subscription-status.active .status-text h4 {
        color: var(--success-400);
    }
    
    [data-theme="dark"] .subscription-status.expired .status-text h4 {
        color: var(--danger-400);
    }
    
    [data-theme="dark"] .payment-method.active {
        background: rgba(59, 130, 246, 0.1);
    }
    
    [data-theme="dark"] .qr-code {
        background: var(--bg-secondary);
    }
    
    [data-theme="dark"] .summary-item.discount {
        color: var(--success-400);
    }
    
    [data-theme="dark"] .qr-code-amount {
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .qr-code-expiry {
        color: var(--warning-400);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar valores do formulário de pagamento
    const monthsSelect = document.getElementById('months');
    const subtotalElement = document.getElementById('subtotal');
    const discountElement = document.getElementById('discount');
    const totalElement = document.getElementById('total');
    const amountInput = document.getElementById('amount');
    
    if (monthsSelect) {
        monthsSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = parseFloat(selectedOption.getAttribute('data-price'));
            const months = parseInt(this.value);
            
            // Calcular valores
            let regularPrice = <?php echo $price1Month; ?> * months;
            let discount = 0;
            
            if (months > 1) {
                const regularPriceAttr = selectedOption.getAttribute('data-regular');
                if (regularPriceAttr) {
                    regularPrice = parseFloat(regularPriceAttr);
                }
                discount = regularPrice - price;
            }
            
            // Atualizar elementos
            subtotalElement.textContent = `R$ ${regularPrice.toFixed(2).replace('.', ',')}`;
            discountElement.textContent = `R$ ${discount.toFixed(2).replace('.', ',')}`;
            totalElement.textContent = `R$ ${price.toFixed(2).replace('.', ',')}`;
            amountInput.value = price.toFixed(2);
            
            console.log("Updated payment form values:", {
                months: months,
                regularPrice: regularPrice,
                discount: discount,
                finalPrice: price,
                amountInputValue: amountInput.value
            });
        });
        
        // Trigger change event to initialize values
        monthsSelect.dispatchEvent(new Event('change'));
    }
    
    // Seleção de método de pagamento
    const paymentMethods = document.querySelectorAll('.payment-method');
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            // Remover classe active de todos
            paymentMethods.forEach(m => m.classList.remove('active'));
            
            // Adicionar classe active ao clicado
            this.classList.add('active');
            
            // Marcar radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
        });
    });
    
    // Toggle FAQ
    window.toggleFaq = function(element) {
        const faqItem = element.parentElement;
        faqItem.classList.toggle('active');
    };
    
    // Verificar pagamento via AJAX
    const checkPaymentBtn = document.getElementById('check-payment-btn');
    if (checkPaymentBtn) {
        checkPaymentBtn.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
            
            const statusMessageContainer = document.getElementById('payment-status-message');
            
            // Fazer requisição AJAX para verificar o pagamento
            fetch('check_payment_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'payment_id=<?php echo urlencode($_SESSION['payment_id'] ?? ''); ?>&months=<?php echo intval($_SESSION['payment_months'] ?? 1); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.status === 'approved') {
                        // Pagamento aprovado
                        Swal.fire({
                            title: 'Pagamento Aprovado!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonText: 'OK',
                            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                        }).then(() => {
                            // Redirecionar para atualizar a página
                            window.location.href = 'payment.php?success=1<?php echo $isExpiredRedirect ? '&expired=true' : ''; ?>';
                        });
                        
                        // Armazenar mensagem de sucesso na sessão via AJAX
                        fetch('store_payment_success.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `message=${encodeURIComponent(data.message)}`
                        });
                        
                    } else if (data.status === 'pending') {
                        // Pagamento pendente
                        statusMessageContainer.style.display = 'block';
                        statusMessageContainer.innerHTML = `
                            <div class="alert alert-info mb-6">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <p class="font-medium">Pagamento Pendente</p>
                                    <p class="text-sm mt-1">${data.message}</p>
                                </div>
                            </div>
                        `;
                        
                        // Reativar botão
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-sync-alt"></i> Verificar Pagamento';
                        
                    } else if (data.status === 'rejected' || data.status === 'cancelled') {
                        // Pagamento rejeitado
                        Swal.fire({
                            title: 'Pagamento Rejeitado',
                            text: data.message,
                            icon: 'error',
                            confirmButtonText: 'OK',
                            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                        }).then(() => {
                            // Redirecionar para atualizar a página
                            window.location.href = 'payment.php<?php echo $isExpiredRedirect ? '?expired=true' : ''; ?>';
                        });
                    } else {
                        // Outro status
                        statusMessageContainer.style.display = 'block';
                        statusMessageContainer.innerHTML = `
                            <div class="alert alert-warning mb-6">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <p class="font-medium">Status: ${data.status}</p>
                                    <p class="text-sm mt-1">${data.message}</p>
                                </div>
                            </div>
                        `;
                        
                        // Reativar botão
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-sync-alt"></i> Verificar Pagamento';
                    }
                } else {
                    // Erro na verificação
                    statusMessageContainer.style.display = 'block';
                    statusMessageContainer.innerHTML = `
                        <div class="alert alert-error mb-6">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>
                                <p class="font-medium">Erro na Verificação</p>
                                <p class="text-sm mt-1">${data.message}</p>
                            </div>
                        </div>
                    `;
                    
                    // Reativar botão
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-sync-alt"></i> Verificar Pagamento';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusMessageContainer.style.display = 'block';
                statusMessageContainer.innerHTML = `
                    <div class="alert alert-error mb-6">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <p class="font-medium">Erro na Comunicação</p>
                            <p class="text-sm mt-1">Ocorreu um erro ao verificar o pagamento. Tente novamente.</p>
                        </div>
                    </div>
                `;
                
                // Reativar botão
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-sync-alt"></i> Verificar Pagamento';
            });
        });
    }
    
    // Auto-refresh para verificar pagamento a cada 30 segundos
    <?php if ($paymentInProgress && !$qrCodeExpired): ?>
    const checkPaymentInterval = setInterval(function() {
        // Fazer verificação via AJAX em vez de submeter o formulário
        fetch('check_payment_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'payment_id=<?php echo urlencode($_SESSION['payment_id'] ?? ''); ?>&months=<?php echo intval($_SESSION['payment_months'] ?? 1); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.status === 'approved') {
                    // Pagamento aprovado - redirecionar
                    clearInterval(checkPaymentInterval);
                    
                    // Armazenar mensagem de sucesso na sessão via AJAX
                    fetch('store_payment_success.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `message=${encodeURIComponent(data.message)}`
                    }).then(() => {
                        window.location.href = 'payment.php?success=1<?php echo $isExpiredRedirect ? '&expired=true' : ''; ?>';
                    });
                } else if (data.status === 'rejected' || data.status === 'cancelled') {
                    // Pagamento rejeitado - redirecionar
                    clearInterval(checkPaymentInterval);
                    window.location.href = 'payment.php<?php echo $isExpiredRedirect ? '?expired=true' : ''; ?>';
                }
                // Se for pending, não faz nada e continua verificando
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }, 30000); // Verificar a cada 30 segundos
    
    // Limpar intervalo quando a página for fechada
    window.addEventListener('beforeunload', function() {
        clearInterval(checkPaymentInterval);
    });
    <?php endif; ?>
    
    // Log form submission for debugging
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            console.log("Payment form submitted with values:", {
                months: document.getElementById('months').value,
                amount: document.getElementById('amount').value,
                paymentMethod: document.querySelector('input[name="payment_method"]:checked').value
            });
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>
<?php ob_end_flush(); // End output buffering and send content ?>
