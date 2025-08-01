<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'master') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';

$user = new User();
$userId = $_SESSION['user_id'];
$userData = $user->getUserById($userId);

$pageTitle = "Links de Referência";
include "includes/header.php";

// Gerar URLs de referência
$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
$referralUrl = $baseUrl . 'admin/register.php?ref=' . $userId;
$directUrl = $baseUrl . 'admin/register.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-link text-primary-500 mr-3"></i>
        Links de Referência
    </h1>
    <p class="page-subtitle">Compartilhe seus links para que novos usuários se cadastrem em sua rede</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Link de Referência Principal -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-friends text-primary-500 mr-2"></i>
                Seu Link de Referência
            </h3>
            <p class="card-subtitle">Usuários que se cadastrarem por este link serão seus usuários</p>
        </div>
        <div class="card-body">
            <div class="link-container">
                <label class="form-label">Link de Referência:</label>
                <div class="link-input-group">
                    <input type="text" id="referralLink" class="form-input link-input" value="<?php echo $referralUrl; ?>" readonly>
                    <button class="copy-btn" onclick="copyToClipboard('referralLink')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            <div class="link-actions mt-4">
                <button class="btn btn-primary" onclick="copyToClipboard('referralLink')">
                    <i class="fas fa-copy"></i>
                    Copiar Link
                </button>
                <button class="btn btn-success" onclick="shareWhatsApp('<?php echo urlencode($referralUrl); ?>')">
                    <i class="fab fa-whatsapp"></i>
                    Compartilhar no WhatsApp
                </button>
            </div>

            <div class="link-info mt-4">
                <div class="info-item">
                    <i class="fas fa-info-circle text-primary-500"></i>
                    <p>Usuários que se cadastrarem por este link serão automaticamente associados à sua conta</p>
                </div>
                <div class="info-item">
                    <i class="fas fa-gift text-success-500"></i>
                    <p>Novos usuários recebem teste grátis automaticamente</p>
                </div>
                <div class="info-item">
                    <i class="fas fa-coins text-warning-500"></i>
                    <p>Você não gasta créditos durante o período de teste dos usuários</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-bar text-primary-500 mr-2"></i>
                Estatísticas de Referência
            </h3>
            <p class="card-subtitle">Acompanhe o desempenho dos seus links</p>
        </div>
        <div class="card-body">
            <?php
            // Obter estatísticas dos usuários referenciados
            $subUsers = $user->getUsersByParentId($userId);
            $totalReferrals = count($subUsers);
            $activeReferrals = count(array_filter($subUsers, function($u) {
                return $u['status'] === 'active' && (!$u['expires_at'] || $u['expires_at'] >= date('Y-m-d'));
            }));
            $expiredReferrals = count(array_filter($subUsers, function($u) {
                return $u['expires_at'] && $u['expires_at'] < date('Y-m-d');
            }));
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $totalReferrals; ?></div>
                        <div class="stat-label">Total de Referências</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $activeReferrals; ?></div>
                        <div class="stat-label">Usuários Ativos</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $expiredReferrals; ?></div>
                        <div class="stat-label">Usuários Expirados</div>
                    </div>
                </div>
            </div>

            <div class="conversion-rate mt-4">
                <div class="rate-container">
                    <div class="rate-label">Taxa de Conversão:</div>
                    <div class="rate-value">
                        <?php 
                        if ($totalReferrals > 0) {
                            $conversionRate = ($activeReferrals / $totalReferrals) * 100;
                            echo number_format($conversionRate, 1) . '%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </div>
                </div>
                <div class="rate-description">
                    Porcentagem de usuários referenciados que estão ativos
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Como Usar -->
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-question-circle text-primary-500 mr-2"></i>
            Como Usar o Sistema de Referência
        </h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-semibold mb-3">📋 Passo a Passo:</h4>
                <ol class="space-y-2 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="step-number">1</span>
                        <span>Copie seu link de referência usando o botão "Copiar Link"</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="step-number">2</span>
                        <span>Compartilhe o link com pessoas interessadas no FutBanner</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="step-number">3</span>
                        <span>Quando alguém se cadastrar pelo seu link, será automaticamente seu usuário</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="step-number">4</span>
                        <span>O novo usuário recebe teste grátis e você não gasta créditos durante o teste</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="step-number">5</span>
                        <span>Após o teste, você pode renovar o usuário usando seus créditos</span>
                    </li>
                </ol>
            </div>
            <div>
                <h4 class="font-semibold mb-3">💡 Dicas de Sucesso:</h4>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-lightbulb text-warning-500 mt-0.5"></i>
                        <span>Compartilhe em grupos de WhatsApp relacionados a futebol</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-lightbulb text-warning-500 mt-0.5"></i>
                        <span>Explique os benefícios do teste grátis</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-lightbulb text-warning-500 mt-0.5"></i>
                        <span>Ofereça suporte aos seus usuários referenciados</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-lightbulb text-warning-500 mt-0.5"></i>
                        <span>Monitore as estatísticas para otimizar sua estratégia</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .link-container {
        margin-bottom: 1rem;
    }

    .link-input-group {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .link-input {
        flex: 1;
        font-family: monospace;
        font-size: 0.875rem;
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
    }

    .copy-btn {
        background: var(--primary-500);
        color: white;
        border: none;
        padding: 0.75rem 1rem;
        border-radius: var(--border-radius-sm);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 48px;
    }

    .copy-btn:hover {
        background: var(--primary-600);
    }

    .link-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .link-info {
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        padding: 1rem;
    }

    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .info-item:last-child {
        margin-bottom: 0;
    }

    .info-item i {
        margin-top: 0.125rem;
        flex-shrink: 0;
    }

    .info-item p {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(1, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    @media (min-width: 640px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    .stat-card {
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        background: var(--primary-50);
        color: var(--primary-500);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .stat-icon.success {
        background: var(--success-50);
        color: var(--success-500);
    }

    .stat-icon.warning {
        background: var(--warning-50);
        color: var(--warning-500);
    }

    .stat-content {
        flex: 1;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .conversion-rate {
        background: var(--bg-tertiary);
        border-radius: var(--border-radius);
        padding: 1rem;
        text-align: center;
    }

    .rate-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .rate-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .rate-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-600);
    }

    .rate-description {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        background: var(--primary-500);
        color: white;
        border-radius: 50%;
        font-size: 0.75rem;
        font-weight: 600;
        flex-shrink: 0;
    }

    .space-y-2 > * + * {
        margin-top: 0.5rem;
    }

    .mt-4 {
        margin-top: 1rem;
    }

    .mt-6 {
        margin-top: 1.5rem;
    }

    .mb-3 {
        margin-bottom: 0.75rem;
    }

    .mr-2 {
        margin-right: 0.5rem;
    }

    .mr-3 {
        margin-right: 0.75rem;
    }

    .mt-0.5 {
        margin-top: 0.125rem;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .stat-icon {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }

    [data-theme="dark"] .stat-icon.success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .stat-icon.warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }

    [data-theme="dark"] .rate-value {
        color: var(--primary-400);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.value;
    
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            title: 'Copiado!',
            text: 'Link copiado para a área de transferência',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
        });
    }).catch(() => {
        // Fallback para navegadores mais antigos
        element.select();
        document.execCommand('copy');
        
        Swal.fire({
            title: 'Copiado!',
            text: 'Link copiado para a área de transferência',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

function shareWhatsApp(url) {
    const message = `🎨 Olá! Convido você para conhecer o FutBanner, o melhor sistema para criar banners de futebol e filmes/séries!

✨ Cadastre-se pelo meu link e ganhe teste grátis:
${decodeURIComponent(url)}

🎯 Com o FutBanner você pode:
• Criar banners profissionais de futebol
• Gerar banners de filmes e séries
• Personalizar logos e fundos
• Enviar automaticamente para o Telegram

🎁 Teste grátis por tempo limitado!

#FutBanner #Banners #Futebol #Design`;

    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, '_blank');
}
</script>

<?php include "includes/footer.php"; ?>