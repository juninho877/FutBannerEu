<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'master') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';
require_once 'classes/MercadoPago.php';
require_once 'classes/MercadoPagoSettings.php';

$user = new User();
$mercadoPago = new MercadoPago();
$mercadoPagoSettings = new MercadoPagoSettings();

$userId = $_SESSION['user_id'];
$userData = $user->getUserById($userId);
$userCredits = $user->getUserCredits($userId);

// Buscar configurações do admin (ID 1)
$adminSettings = $mercadoPagoSettings->getSettings(1) ?: [];
$creditPrice = $adminSettings['credit_price'] ?? 1.00;
$minCreditPurchase = $adminSettings['min_credit_purchase'] ?? 1;
$whatsappNumber = $adminSettings['whatsapp_number'] ?? '5511999999999';

// Verificar se há um pagamento em andamento
$paymentInProgress = isset($_SESSION['credit_payment_qr_code']) && !empty($_SESSION['credit_payment_qr_code']);
$paymentCreatedAt = isset($_SESSION['credit_payment_created_at']) ? $_SESSION['credit_payment_created_at'] : null;

// Verificar se o QR code expirou (30 minutos)
$qrCodeExpired = false;
if ($paymentCreatedAt && (time() - $paymentCreatedAt > 1800)) { // 30 minutos em segundos
    $qrCodeExpired = true;
    // Limpar dados do pagamento expirado
    unset($_SESSION['credit_payment_qr_code']);
    unset($_SESSION['credit_payment_pix_code']);
    unset($_SESSION['credit_payment_created_at']);
    unset($_SESSION['credit_payment_id']);
    unset($_SESSION['credit_payment_amount']);
    unset($_SESSION['credit_payment_credits']);
    $paymentInProgress = false;
}

// Processar solicitação de pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_payment') {
        $creditsToBuy = isset($_POST['credits']) ? intval($_POST['credits']) : $minCreditPurchase;
        
        // Validar quantidade mínima
        if ($creditsToBuy < $minCreditPurchase) {
            $creditsToBuy = $minCreditPurchase;
        }
        
        // Calcular valor total
        $totalAmount = $creditsToBuy * $creditPrice;
        
        // Criar descrição
        $description = "Compra de {$creditsToBuy} créditos - Usuário: {$userData['username']}";
        
        // Criar pagamento - always use admin (ID 1) as the owner for credit purchases
        $result = $mercadoPago->createCreditPayment($userId, $description, $totalAmount, $creditsToBuy, 1);
        
        if ($result['success']) {
            $_SESSION['credit_payment_qr_code'] = $result['qr_code'];
            $_SESSION['credit_payment_id'] = $result['payment_id'];
            $_SESSION['credit_payment_created_at'] = time();
            $_SESSION['credit_payment_amount'] = $totalAmount;
            $_SESSION['credit_payment_credits'] = $creditsToBuy;
            
            // Redirecionar para evitar reenvio do formulário
            header('Location: buy_credits.php');
            exit;
        } else {
            $errorMessage = $result['message'];
        }
    } elseif ($_POST['action'] === 'cancel_payment') {
        // Limpar dados do pagamento
        unset($_SESSION['credit_payment_qr_code']);
        unset($_SESSION['credit_payment_pix_code']);
        unset($_SESSION['credit_payment_created_at']);
        unset($_SESSION['credit_payment_id']);
        unset($_SESSION['credit_payment_amount']);
        unset($_SESSION['credit_payment_credits']);
        
        // Redirecionar para evitar reenvio do formulário
        header('Location: buy_credits.php');
        exit;
    }
}

// Verificar se há mensagem de sucesso
$successMessage = '';
if (isset($_SESSION['credit_payment_success']) && $_SESSION['credit_payment_success']) {
    $successMessage = $_SESSION['credit_payment_message'] ?? "Pagamento confirmado com sucesso!";
    unset($_SESSION['credit_payment_success']);
    unset($_SESSION['credit_payment_message']);
}

$pageTitle = "Comprar Créditos";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-coins text-warning-500 mr-3"></i>
        Comprar Créditos
    </h1>
    <p class="page-subtitle">
        Adquira créditos para criar e renovar usuários
    </p>
</div>

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
    <!-- Informações de Créditos -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Seus Créditos</h3>
                <p class="card-subtitle">Informações sobre seus créditos atuais</p>
            </div>
            <div class="card-body">
                <div class="credit-info-large">
                    <div class="credit-amount-large"><?php echo $userCredits; ?></div>
                    <div class="credit-label-large">créditos disponíveis</div>
                </div>

                <div class="credit-details">
                    <div class="detail-item">
                        <span class="detail-label">Valor por crédito:</span>
                        <span class="detail-value">R$ <?php echo number_format($creditPrice, 2, ',', '.'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Compra mínima:</span>
                        <span class="detail-value"><?php echo $minCreditPurchase; ?> créditos</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Uso:</span>
                        <span class="detail-value">1 crédito = 1 mês de acesso para 1 usuário</span>
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
                        <img src="<?php echo $_SESSION['credit_payment_qr_code']; ?>" alt="QR Code de Pagamento">
                    </div>
                    <div class="qr-code-info">
                        <!-- PIX Copy-Paste Code Section -->
                        <?php if (isset($_SESSION['credit_payment_pix_code']) && !empty($_SESSION['credit_payment_pix_code'])): ?>
                        <div class="pix-code-section">
                            <label class="pix-code-label">
                                <i class="fas fa-copy"></i>
                                Código PIX (Copia e Cola):
                            </label>
                            <div class="pix-code-container">
                                <input type="text" id="pixCode" class="pix-code-input" 
                                       value="<?php echo htmlspecialchars($_SESSION['credit_payment_pix_code']); ?>" 
                                       readonly onclick="this.select()">
                                <button type="button" id="copyPixBtn" class="copy-pix-btn">
                                    <i class="fas fa-copy"></i>
                                    Copiar
                                </button>
                            </div>
                            <p class="pix-code-help">
                                <i class="fas fa-info-circle"></i>
                                Copie este código e cole no seu app bancário para pagar via PIX
                            </p>
                            
                            <!-- PIX Copy-Paste Code Section -->
                            <?php if (isset($_SESSION['credit_payment_pix_code']) && !empty($_SESSION['credit_payment_pix_code'])): ?>
                            <div class="pix-code-section">
                                <label class="pix-code-label">
                                    <i class="fas fa-copy"></i>
                                    Código PIX (Copia e Cola):
                                </label>
                                <div class="pix-code-container">
                                    <input type="text" id="pixCode" class="pix-code-input" 
                                           value="<?php echo htmlspecialchars($_SESSION['credit_payment_pix_code']); ?>" 
                                           readonly onclick="this.select()">
                                    <button type="button" id="copyPixBtn" class="copy-pix-btn">
                                        <i class="fas fa-copy"></i>
                                        Copiar
                                    </button>
                                </div>
                                <p class="pix-code-help">
                                    <i class="fas fa-info-circle"></i>
                                    Copie este código e cole no seu app bancário para pagar via PIX
                                </p>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        <?php endif; ?>
                        
                        <p class="qr-code-amount">R$ <?php echo number_format($_SESSION['credit_payment_amount'] ?? 0, 2, ',', '.'); ?></p>
                        <p class="qr-code-description">
                            <?php echo $_SESSION['credit_payment_credits'] ?? 1; ?> créditos
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
        <!-- Formulário de Compra -->
        <div class="card mt-6">
            <div class="card-header">
                <h3 class="card-title">Comprar Créditos</h3>
                <p class="card-subtitle">Escolha a quantidade e método de pagamento</p>
            </div>
            <div class="card-body">
                <form method="post" action="" id="creditForm">
                    <input type="hidden" name="action" value="create_payment">
                    
                    <div class="form-group">
                        <label for="credits" class="form-label">Quantidade de Créditos</label>
                        <div class="credit-selector">
                            <button type="button" class="credit-btn" data-value="<?php echo $minCreditPurchase; ?>">
                                <?php echo $minCreditPurchase; ?>
                            </button>
                            <button type="button" class="credit-btn" data-value="5">
                                5
                            </button>
                            <button type="button" class="credit-btn" data-value="10">
                                10
                            </button>
                            <button type="button" class="credit-btn" data-value="20">
                                20
                            </button>
                            <div class="credit-custom">
                                <input type="number" id="credits" name="credits" class="form-input" 
                                       value="<?php echo $minCreditPurchase; ?>" min="<?php echo $minCreditPurchase; ?>" step="1">
                            </div>
                        </div>
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
                            <span>Quantidade:</span>
                            <span id="credit-quantity"><?php echo $minCreditPurchase; ?> créditos</span>
                        </div>
                        <div class="summary-item">
                            <span>Valor por crédito:</span>
                            <span>R$ <?php echo number_format($creditPrice, 2, ',', '.'); ?></span>
                        </div>
                        <div class="summary-item total">
                            <span>Total:</span>
                            <span id="total">R$ <?php echo number_format($minCreditPurchase * $creditPrice, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    
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
                <h3 class="card-title">Benefícios dos Créditos</h3>
            </div>
            <div class="card-body">
                <div class="benefits-list">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Crie Usuários</h4>
                            <p>Cada crédito permite criar um novo usuário</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Renove Acessos</h4>
                            <p>Estenda o acesso dos seus usuários</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Gere Receita</h4>
                            <p>Defina seu próprio valor de revenda</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="benefit-text">
                            <h4>Controle Total</h4>
                            <p>Gerencie seus usuários como quiser</p>
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
                            <span>Como funcionam os créditos?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Cada crédito permite criar um novo usuário ou renovar o acesso de um usuário existente por 1 mês.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Quanto tempo leva para confirmar o pagamento?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Pagamentos via PIX são confirmados em poucos minutos. Após o pagamento, clique em "Verificar Pagamento" para atualizar seu saldo de créditos.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Os créditos expiram?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Não, seus créditos não expiram e ficam disponíveis em sua conta até que você os utilize.</p>
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
    
    .alert-info {
        background: var(--primary-50);
        color: var(--primary-600);
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .alert-warning {
        background: var(--warning-50);
        color: var(--warning-600);
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
    
    .credit-info-large {
        text-align: center;
        padding: 2rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
    }
    
    .credit-amount-large {
        font-size: 4rem;
        font-weight: 700;
        color: var(--success-500);
        line-height: 1;
    }
    
    .credit-label-large {
        color: var(--text-secondary);
        margin-top: 0.5rem;
        font-size: 1.125rem;
    }
    
    .credit-details {
        background: var(--bg-tertiary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .detail-item:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        color: var(--text-secondary);
    }
    
    .detail-value {
        font-weight: 600;
    }
    
    .credit-selector {
        display: grid;
        grid-template-columns: repeat(4, 1fr) 2fr;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .credit-btn {
        padding: 0.75rem;
        background: var(--bg-tertiary);
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .credit-btn:hover, .credit-btn.active {
        background: var(--primary-50);
        border-color: var(--primary-500);
        color: var(--primary-600);
    }
    
    .credit-custom {
        grid-column: span 2;
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
    
    /* PIX Code Section Styles */
    .pix-code-section {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .pix-code-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
    }
    
    .pix-code-container {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    
    .pix-code-input {
        flex: 1;
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        font-family: monospace;
        font-size: 0.75rem;
        color: var(--text-primary);
        cursor: pointer;
        word-break: break-all;
    }
    
    .pix-code-input:focus {
        outline: none;
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .copy-pix-btn {
        background: var(--success-500);
        color: white;
        border: none;
        padding: 0.75rem 1rem;
        border-radius: var(--border-radius-sm);
        cursor: pointer;
        font-weight: 600;
        font-size: 0.75rem;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }
    
    .copy-pix-btn:hover {
        background: var(--success-600);
        transform: translateY(-1px);
    }
    
    .copy-pix-btn:active {
        transform: translateY(0);
    }
    
    .copy-pix-btn.copied {
        background: var(--primary-500);
        animation: pulse 0.6s ease-in-out;
    }
    
    .pix-code-help {
        font-size: 0.75rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
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
    
    [data-theme="dark"] .alert-info {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
    
    [data-theme="dark"] .credit-btn:hover, 
    [data-theme="dark"] .credit-btn.active {
        background: rgba(59, 130, 246, 0.1);
        border-color: var(--primary-400);
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .payment-method.active {
        background: rgba(59, 130, 246, 0.1);
        border-color: var(--primary-400);
    }
    
    [data-theme="dark"] .qr-code {
        background: var(--bg-secondary);
    }
    
    [data-theme="dark"] .qr-code-amount {
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .qr-code-expiry {
        color: var(--warning-400);
    }
    
    /* Dark theme for PIX code section */
    [data-theme="dark"] .pix-code-input {
        background: var(--bg-secondary);
        border-color: var(--border-color);
        color: var(--text-primary);
    }
    
    [data-theme="dark"] .copy-pix-btn {
        background: var(--success-500);
    }
    
    [data-theme="dark"] .copy-pix-btn:hover {
        background: var(--success-600);
    }
    
    [data-theme="dark"] .copy-pix-btn.copied {
        background: var(--primary-400);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleção de quantidade de créditos
    const creditBtns = document.querySelectorAll('.credit-btn');
    const creditsInput = document.getElementById('credits');
    const creditQuantityDisplay = document.getElementById('credit-quantity');
    const totalDisplay = document.getElementById('total');
    const creditPrice = <?php echo $creditPrice; ?>;
    const minCreditPurchase = <?php echo $minCreditPurchase; ?>;
    
    function updateTotal() {
        const credits = parseInt(creditsInput.value);
        if (isNaN(credits) || credits < minCreditPurchase) {
            creditsInput.value = minCreditPurchase;
            updateTotal();
            return;
        }
        
        const total = credits * creditPrice;
        creditQuantityDisplay.textContent = `${credits} créditos`;
        totalDisplay.textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;
        
        // Atualizar botão ativo
        creditBtns.forEach(btn => {
            if (parseInt(btn.getAttribute('data-value')) === credits) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
    
    if (creditBtns.length > 0 && creditsInput) {
        creditBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const value = parseInt(this.getAttribute('data-value'));
                creditsInput.value = value;
                updateTotal();
            });
        });
        
        creditsInput.addEventListener('input', updateTotal);
        
        // Inicializar valores
        updateTotal();
    }
    
    // Toggle FAQ function
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
            fetch('check_credit_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'payment_id=<?php echo urlencode($_SESSION['credit_payment_id'] ?? ''); ?>&credits=<?php echo intval($_SESSION['credit_payment_credits'] ?? 1); ?>'
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
                            window.location.href = 'buy_credits.php?success=1';
                        });
                        
                        // Armazenar mensagem de sucesso na sessão via AJAX
                        fetch('store_credit_payment_success.php', {
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
                            window.location.href = 'buy_credits.php';
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
    
    // PIX Code Copy Functionality
    const copyPixBtn = document.getElementById('copyPixBtn');
    const pixCodeInput = document.getElementById('pixCode');
    
    if (copyPixBtn && pixCodeInput) {
        copyPixBtn.addEventListener('click', function() {
            const pixCode = pixCodeInput.value;
            
            // Try modern clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(pixCode).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    fallbackCopy();
                });
            } else {
                fallbackCopy();
            }
            
            function fallbackCopy() {
                try {
                    pixCodeInput.select();
                    pixCodeInput.setSelectionRange(0, 99999); // For mobile devices
                    document.execCommand('copy');
                    showCopySuccess();
                } catch (err) {
                    showCopyError();
                }
            }
            
            function showCopySuccess() {
                // Visual feedback
                copyPixBtn.classList.add('copied');
                const originalText = copyPixBtn.innerHTML;
                copyPixBtn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                
                // Reset after 2 seconds
                setTimeout(() => {
                    copyPixBtn.classList.remove('copied');
                    copyPixBtn.innerHTML = originalText;
                }, 2000);
                
                // Show success notification
                Swal.fire({
                    title: 'Código Copiado!',
                    text: 'O código PIX foi copiado para sua área de transferência',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            }
            
            function showCopyError() {
                Swal.fire({
                    title: 'Erro ao Copiar',
                    text: 'Não foi possível copiar automaticamente. Selecione o texto e copie manualmente.',
                    icon: 'error',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            }
        });
        
        // Auto-select text when clicking on input
        pixCodeInput.addEventListener('click', function() {
            this.select();
        });
    }
    
    // Auto-refresh para verificar pagamento a cada 30 segundos
    <?php if ($paymentInProgress && !$qrCodeExpired): ?>
    const checkPaymentInterval = setInterval(function() {
        // Fazer verificação via AJAX em vez de submeter o formulário
        fetch('check_credit_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'payment_id=<?php echo urlencode($_SESSION['credit_payment_id'] ?? ''); ?>&credits=<?php echo intval($_SESSION['credit_payment_credits'] ?? 1); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.status === 'approved') {
                    // Pagamento aprovado - redirecionar
                    clearInterval(checkPaymentInterval);
                    
                    // Armazenar mensagem de sucesso na sessão via AJAX
                    fetch('store_credit_payment_success.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `message=${encodeURIComponent(data.message)}`
                    }).then(() => {
                        window.location.href = 'buy_credits.php?success=1';
                    });
                } else if (data.status === 'rejected' || data.status === 'cancelled') {
                    // Pagamento rejeitado - redirecionar
                    clearInterval(checkPaymentInterval);
                    window.location.href = 'buy_credits.php';
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
});
</script>

<?php include "includes/footer.php"; ?>