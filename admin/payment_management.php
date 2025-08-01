<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'classes/MercadoPagoPayment.php';
require_once 'classes/MercadoPagoSettings.php';
require_once 'classes/User.php';

$mercadoPagoPayment = new MercadoPagoPayment();
$mercadoPagoSettings = new MercadoPagoSettings();
$user = new User();

// Obter estatísticas de pagamentos
$paymentStats = $mercadoPagoPayment->getPaymentStats();

// Obter configurações do Mercado Pago
$adminSettings = $mercadoPagoSettings->getSettings(1); // Assumindo que o admin tem ID 1
$mercadoPagoConfigured = ($adminSettings !== false && !empty($adminSettings['access_token']));

// Buscar todos os pagamentos (limitado a 50 para performance)
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT 
        mp.id,
        mp.user_id,
        u.username,
        mp.payment_id,
        mp.preference_id,
        mp.status,
        mp.status_detail,
        mp.payment_method,
        mp.payment_type,
        mp.transaction_amount,
        mp.payment_date,
        mp.created_at
    FROM mercadopago_payments mp
    JOIN usuarios u ON mp.user_id = u.id
    ORDER BY mp.created_at DESC
    LIMIT 50
");
$stmt->execute();
$payments = $stmt->fetchAll();

$pageTitle = "Gerenciamento de Pagamentos";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-money-check-alt text-primary-500 mr-3"></i>
        Gerenciamento de Pagamentos
    </h1>
    <p class="page-subtitle">Visualize e gerencie os pagamentos do sistema</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Total de Pagamentos</p>
                    <p class="text-2xl font-bold text-primary"><?php echo $paymentStats['total_payments']; ?></p>
                </div>
                <div class="w-12 h-12 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-receipt text-primary-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Pagamentos Aprovados</p>
                    <p class="text-2xl font-bold text-success-500"><?php echo $paymentStats['approved_payments']; ?></p>
                </div>
                <div class="w-12 h-12 bg-success-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-success-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Pagamentos Pendentes</p>
                    <p class="text-2xl font-bold text-warning-500"><?php echo $paymentStats['pending_payments']; ?></p>
                </div>
                <div class="w-12 h-12 bg-warning-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-warning-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Valor Total</p>
                    <p class="text-2xl font-bold text-info-500">R$ <?php echo number_format($paymentStats['total_amount'], 2, ',', '.'); ?></p>
                </div>
                <div class="w-12 h-12 bg-info-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-info-500"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status do Mercado Pago -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Status da Integração</h3>
    </div>
    <div class="card-body">
        <div class="flex items-center gap-4">
            <div class="integration-status <?php echo $mercadoPagoConfigured ? 'active' : 'inactive'; ?>">
                <i class="fas fa-<?php echo $mercadoPagoConfigured ? 'check' : 'times'; ?>"></i>
            </div>
            <div>
                <h4 class="font-semibold"><?php echo $mercadoPagoConfigured ? 'Integração Ativa' : 'Integração Inativa'; ?></h4>
                <p class="text-sm text-muted">
                    <?php echo $mercadoPagoConfigured ? 'O Mercado Pago está configurado e pronto para receber pagamentos.' : 'Configure o Mercado Pago para habilitar pagamentos.'; ?>
                </p>
            </div>
            <div class="ml-auto">
                <a href="mercadopago.php" class="btn btn-primary">
                    <i class="fas fa-cog"></i>
                    <?php echo $mercadoPagoConfigured ? 'Gerenciar Configurações' : 'Configurar Mercado Pago'; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Histórico de Pagamentos</h3>
        <p class="card-subtitle">Últimos 50 pagamentos registrados no sistema</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Método</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhum pagamento registrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['id']; ?></td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar-small">
                                            <?php echo strtoupper(substr($payment['username'], 0, 2)); ?>
                                        </div>
                                        <span class="font-medium"><?php echo htmlspecialchars($payment['username']); ?></span>
                                    </div>
                                </td>
                                <td>R$ <?php echo number_format($payment['transaction_amount'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                        <?php 
                                        switch ($payment['status']) {
                                            case 'approved':
                                                echo 'Aprovado';
                                                break;
                                            case 'pending':
                                                echo 'Pendente';
                                                break;
                                            case 'rejected':
                                                echo 'Rejeitado';
                                                break;
                                            default:
                                                echo ucfirst($payment['status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $payment['payment_method'] ? ucfirst($payment['payment_method']) : '-'; ?></td>
                                <td><?php echo $payment['payment_date'] ? date('d/m/Y H:i', strtotime($payment['payment_date'])) : date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-primary check-payment" data-preference-id="<?php echo $payment['preference_id']; ?>" title="Verificar">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        
                                        <a href="edit_user.php?id=<?php echo $payment['user_id']; ?>" class="btn-action btn-secondary" title="Editar Usuário">
                                            <i class="fas fa-user-edit"></i>
                                        </a>
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

    .payments-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .payments-table th,
    .payments-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .payments-table th {
        background: var(--bg-secondary);
        font-weight: 600;
        color: var(--text-primary);
    }

    .payments-table tbody tr:hover {
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

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-approved {
        background: var(--success-50);
        color: var(--success-600);
    }

    .status-pending {
        background: var(--warning-50);
        color: var(--warning-600);
    }

    .status-rejected {
        background: var(--danger-50);
        color: var(--danger-600);
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

    .btn-action.btn-primary {
        background: var(--primary-50);
        color: var(--primary-600);
    }

    .btn-action.btn-primary:hover {
        background: var(--primary-100);
    }

    .btn-action.btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-secondary);
    }

    .btn-action.btn-secondary:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }

    .integration-status {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .integration-status.active {
        background: var(--success-50);
        color: var(--success-500);
    }

    .integration-status.inactive {
        background: var(--danger-50);
        color: var(--danger-500);
    }

    .ml-auto {
        margin-left: auto;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .status-approved {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }

    [data-theme="dark"] .status-rejected {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }

    [data-theme="dark"] .integration-status.active {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .integration-status.inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }

    [data-theme="dark"] .btn-action.btn-primary {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }

    [data-theme="dark"] .btn-action.btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-muted);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar status de pagamento
    document.querySelectorAll('.check-payment').forEach(button => {
        button.addEventListener('click', function() {
            const preferenceId = this.getAttribute('data-preference-id');
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('check_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `preference_id=${encodeURIComponent(preferenceId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Status Atualizado',
                        text: `Status do pagamento: ${data.status}`,
                        icon: 'success',
                        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro',
                        text: data.message,
                        icon: 'error',
                        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro na comunicação com o servidor',
                    icon: 'error',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-sync-alt"></i>';
            });
        });
    });
});
</script>

<?php include "includes/footer.php"; ?>