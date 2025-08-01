<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'master') {
    header("Location: login.php");
    exit();
}

require_once 'classes/MercadoPago.php';
require_once 'classes/User.php';

$mercadoPago = new MercadoPago();
$user = new User();

$userId = $_SESSION['user_id'];
$userCredits = $user->getUserCredits($userId);

// Obter lista de usuários gerenciados pelo master
$subUsers = $user->getUsersByParentId($userId);
$subUserIds = array_column($subUsers, 'id');

// Obter histórico de pagamentos apenas dos usuários gerenciados
$payments = $mercadoPago->getUserPaymentHistory($subUserIds, 100);

$pageTitle = "Histórico de Pagamentos";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-history text-primary-500 mr-3"></i>
        Histórico de Pagamentos
    </h1>
    <p class="page-subtitle">Visualize todos os pagamentos dos seus usuários</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Créditos Atuais</p>
                    <p class="text-2xl font-bold text-success-500"><?php echo $userCredits; ?></p>
                </div>
                <div class="w-12 h-12 bg-success-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-coins text-success-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Total de Transações</p>
                    <p class="text-2xl font-bold text-primary"><?php echo count($payments); ?></p>
                </div>
                <div class="w-12 h-12 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-primary-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Valor Total</p>
                    <p class="text-2xl font-bold text-info-500">
                        <?php 
                        $totalAmount = array_reduce($payments, function($carry, $payment) {
                            return $carry + ($payment['status'] === 'approved' ? $payment['transaction_amount'] : 0);
                        }, 0);
                        echo 'R$ ' . number_format($totalAmount, 2, ',', '.');
                        ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-info-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-info-500"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions Bar -->
<div class="flex justify-between items-center mb-6">
    <div class="flex gap-3">
        <button id="refreshBtn" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i>
            Atualizar
        </button>
    </div>
    <a href="buy_credits.php" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        Comprar Créditos
    </a>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Histórico de Pagamentos</h3>
        <p class="card-subtitle">Pagamentos realizados pelos seus usuários</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Quantidade</th>
                        <th>Status</th>
                        <th>Método</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Nenhum pagamento encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): 
                            // Obter nome do usuário
                            $paymentUsername = '';
                            foreach ($subUsers as $subUser) {
                                if ($subUser['id'] == $payment['user_id']) {
                                    $paymentUsername = $subUser['username'];
                                    break;
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo $payment['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                                <td>
                                    <span class="user-badge">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($paymentUsername); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['payment_purpose'] === 'subscription'): ?>
                                        <span class="type-badge type-subscription">Assinatura</span>
                                    <?php elseif ($payment['payment_purpose'] === 'credit_purchase'): ?>
                                        <span class="type-badge type-credit">Créditos</span>
                                    <?php else: ?>
                                        <span class="type-badge type-other">Outro</span>
                                    <?php endif; ?>
                                </td>
                                <td>R$ <?php echo number_format($payment['transaction_amount'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php 
                                    if ($payment['payment_purpose'] === 'subscription') {
                                        echo $payment['related_quantity'] . ' ' . ($payment['related_quantity'] > 1 ? 'meses' : 'mês');
                                    } elseif ($payment['payment_purpose'] === 'credit_purchase') {
                                        echo $payment['related_quantity'] . ' ' . ($payment['related_quantity'] > 1 ? 'créditos' : 'crédito');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
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
                                <td><?php echo $payment['payment_method'] ? ucfirst($payment['payment_method']) : 'PIX'; ?></td>
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
    
    .type-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .type-subscription {
        background: var(--primary-50);
        color: var(--primary-600);
    }
    
    .type-credit {
        background: var(--warning-50);
        color: var(--warning-600);
    }
    
    .type-other {
        background: var(--bg-tertiary);
        color: var(--text-secondary);
    }
    
    .user-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        background: var(--bg-tertiary);
        color: var(--text-secondary);
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
    
    [data-theme="dark"] .type-subscription {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }
    
    [data-theme="dark"] .type-credit {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh Button
    document.getElementById('refreshBtn').addEventListener('click', function() {
        location.reload();
    });
});
</script>

<?php include "includes/footer.php"; ?>