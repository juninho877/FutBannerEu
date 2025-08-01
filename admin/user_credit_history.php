<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';
require_once 'classes/CreditTransaction.php';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user_management.php");
    exit();
}

$userId = (int)$_GET['id'];
$userClass = new User();
$userData = $userClass->getUserById($userId);

if (!$userData) {
    header("Location: user_management.php?error=user_not_found");
    exit();
}

$creditTransaction = new CreditTransaction();
$transactions = $creditTransaction->getUserTransactions($userId, 100); // Obter até 100 transações

$pageTitle = "Histórico de Créditos - " . $userData['username'];
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-history text-primary-500 mr-3"></i>
        Histórico de Créditos
    </h1>
    <p class="page-subtitle">Transações de créditos do usuário <?php echo htmlspecialchars($userData['username']); ?></p>
</div>

<!-- User Info -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Informações do Usuário</h3>
    </div>
    <div class="card-body">
        <div class="flex items-center gap-4">
            <div class="user-avatar-large">
                <?php echo strtoupper(substr($userData['username'], 0, 2)); ?>
            </div>
            <div>
                <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($userData['username']); ?></h2>
                <p class="text-sm text-muted">
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
                </p>
                <?php if ($userData['role'] === 'master'): ?>
                <p class="mt-2">
                    <span class="credit-badge">
                        <i class="fas fa-coins"></i>
                        <?php echo $userData['credits']; ?> créditos
                    </span>
                </p>
                <?php endif; ?>
            </div>
            <div class="ml-auto">
                <a href="edit_user.php?id=<?php echo $userId; ?>" class="btn btn-secondary">
                    <i class="fas fa-user-edit"></i>
                    Editar Usuário
                </a>
                <a href="user_management.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Histórico de Transações</h3>
        <p class="card-subtitle">Mostrando até 100 transações mais recentes</p>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <i class="fas fa-history text-muted"></i>
                <p>Nenhuma transação encontrada para este usuário</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Descrição</th>
                            <th>Relacionado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo $transaction['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                <td>
                                    <span class="transaction-type type-<?php echo $transaction['transaction_type']; ?>">
                                        <?php 
                                        switch ($transaction['transaction_type']) {
                                            case 'purchase':
                                                echo 'Compra';
                                                break;
                                            case 'admin_add':
                                                echo 'Adição Manual';
                                                break;
                                            case 'user_creation':
                                                echo 'Criação de Usuário';
                                                break;
                                            case 'user_renewal':
                                                echo 'Renovação de Usuário';
                                                break;
                                            default:
                                                echo ucfirst($transaction['transaction_type']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $transaction['amount'] > 0 ? 'text-success-500' : 'text-danger-500'; ?>">
                                        <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo $transaction['amount']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td>
                                    <?php if ($transaction['related_entity_id']): ?>
                                        <a href="edit_user.php?id=<?php echo $transaction['related_entity_id']; ?>" class="user-link">
                                            <?php echo htmlspecialchars($transaction['related_username']); ?>
                                        </a>
                                    <?php elseif ($transaction['related_payment_id']): ?>
                                        <span class="payment-id" title="ID do Pagamento">
                                            <?php echo substr($transaction['related_payment_id'], 0, 10) . '...'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Credit Form -->
<?php if ($userData['role'] === 'master'): ?>
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">Adicionar Créditos</h3>
        <p class="card-subtitle">Adicione créditos manualmente a este usuário</p>
    </div>
    <div class="card-body">
        <form id="addCreditForm" class="flex items-end gap-4">
            <div class="form-group flex-1 mb-0">
                <label for="credit_amount" class="form-label">Quantidade de Créditos</label>
                <input type="number" id="credit_amount" name="credit_amount" class="form-input" min="1" value="1" required>
            </div>
            <div class="form-group flex-1 mb-0">
                <label for="credit_description" class="form-label">Descrição (opcional)</label>
                <input type="text" id="credit_description" name="credit_description" class="form-input" placeholder="Motivo da adição">
            </div>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-plus-circle"></i>
                Adicionar
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
    .user-avatar-large {
        width: 64px;
        height: 64px;
        background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1.5rem;
    }
    
    .credit-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--success-50);
        color: var(--success-600);
        border-radius: var(--border-radius);
        font-weight: 600;
    }
    
    .ml-auto {
        margin-left: auto;
    }
    
    .mt-2 {
        margin-top: 0.5rem;
    }
    
    .mb-6 {
        margin-bottom: 1.5rem;
    }
    
    .mt-6 {
        margin-top: 1.5rem;
    }
    
    .text-xl {
        font-size: 1.25rem;
        line-height: 1.75rem;
    }
    
    .table-responsive {
        overflow-x: auto;
    }

    .transactions-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .transactions-table th,
    .transactions-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .transactions-table th {
        background: var(--bg-secondary);
        font-weight: 600;
        color: var(--text-primary);
    }

    .transactions-table tbody tr:hover {
        background: var(--bg-secondary);
    }

    .transaction-type {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .type-purchase {
        background: var(--success-50);
        color: var(--success-600);
    }

    .type-admin_add {
        background: var(--warning-50);
        color: var(--warning-600);
    }

    .type-user_creation, .type-user_renewal {
        background: var(--danger-50);
        color: var(--danger-600);
    }

    .user-link {
        color: var(--primary-500);
        text-decoration: none;
        font-weight: 500;
    }

    .user-link:hover {
        text-decoration: underline;
    }

    .payment-id {
        font-family: monospace;
        background: var(--bg-tertiary);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-muted);
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    
    .flex-1 {
        flex: 1;
    }
    
    .mb-0 {
        margin-bottom: 0;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .credit-badge {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }
    
    [data-theme="dark"] .type-purchase {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
    }

    [data-theme="dark"] .type-admin_add {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
    }

    [data-theme="dark"] .type-user_creation, [data-theme="dark"] .type-user_renewal {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
    }

    [data-theme="dark"] .user-link {
        color: var(--primary-400);
    }

    [data-theme="dark"] .payment-id {
        background: var(--bg-secondary);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add Credit Form
    const addCreditForm = document.getElementById('addCreditForm');
    if (addCreditForm) {
        addCreditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const creditAmount = document.getElementById('credit_amount').value;
            const creditDescription = document.getElementById('credit_description').value;
            
            if (!creditAmount || creditAmount < 1) {
                Swal.fire({
                    title: 'Erro!',
                    text: 'A quantidade de créditos deve ser pelo menos 1',
                    icon: 'error',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
                return;
            }
            
            Swal.fire({
                title: 'Confirmar Adição',
                text: `Deseja adicionar ${creditAmount} créditos para este usuário?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, adicionar',
                cancelButtonText: 'Cancelar',
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar solicitação para adicionar créditos
                    fetch('user_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=add_credits&user_id=<?php echo $userId; ?>&credits=${creditAmount}&description=${encodeURIComponent(creditDescription)}`
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
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Erro!',
                            text: 'Erro de comunicação com o servidor',
                            icon: 'error',
                            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                        });
                    });
                }
            });
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>