<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';
require_once 'config/database.php';

$db = Database::getInstance()->getConnection();

// Filtros
$filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$filter_type = isset($_GET['type']) ? $_GET['type'] : null;
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : null;
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : null;

// Construir a consulta SQL com base nos filtros
$sql = "
    SELECT 
        t.id,
        t.transaction_date,
        t.transaction_type,
        t.amount,
        t.description,
        t.related_entity_id,
        t.related_payment_id,
        u1.username as user_username,
        u1.id as user_id,
        u2.username as related_username,
        u2.id as related_user_id
    FROM credit_transactions t
    LEFT JOIN usuarios u1 ON t.user_id = u1.id
    LEFT JOIN usuarios u2 ON t.related_entity_id = u2.id
    WHERE 1=1
";

$params = [];

if ($filter_user) {
    $sql .= " AND (t.user_id = ? OR t.related_entity_id = ?)";
    $params[] = $filter_user;
    $params[] = $filter_user;
}

if ($filter_type) {
    $sql .= " AND t.transaction_type = ?";
    $params[] = $filter_type;
}

if ($filter_date_start) {
    $sql .= " AND DATE(t.transaction_date) >= ?";
    $params[] = $filter_date_start;
}

if ($filter_date_end) {
    $sql .= " AND DATE(t.transaction_date) <= ?";
    $params[] = $filter_date_end;
}

$sql .= " ORDER BY t.transaction_date DESC LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Obter lista de usuários para o filtro
$userStmt = $db->prepare("
    SELECT id, username, role 
    FROM usuarios 
    WHERE role IN ('master', 'admin') 
    ORDER BY username
");
$userStmt->execute();
$users = $userStmt->fetchAll();

// Obter estatísticas
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN transaction_type = 'purchase' THEN 1 ELSE 0 END) as purchase_count,
        SUM(CASE WHEN transaction_type = 'admin_add' THEN 1 ELSE 0 END) as admin_add_count,
        SUM(CASE WHEN transaction_type = 'user_creation' THEN 1 ELSE 0 END) as user_creation_count,
        SUM(CASE WHEN transaction_type = 'user_renewal' THEN 1 ELSE 0 END) as user_renewal_count
    FROM credit_transactions
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

$pageTitle = "Transações de Créditos";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-exchange-alt text-primary-500 mr-3"></i>
        Transações de Créditos
    </h1>
    <p class="page-subtitle">Histórico completo de todas as movimentações de créditos no sistema</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Total de Transações</p>
                    <p class="text-2xl font-bold text-primary"><?php echo $stats['total_transactions']; ?></p>
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
                    <p class="text-sm font-medium text-muted">Compras</p>
                    <p class="text-2xl font-bold text-success-500"><?php echo $stats['purchase_count']; ?></p>
                </div>
                <div class="w-12 h-12 bg-success-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-success-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Adições Manuais</p>
                    <p class="text-2xl font-bold text-warning-500"><?php echo $stats['admin_add_count']; ?></p>
                </div>
                <div class="w-12 h-12 bg-warning-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-plus-circle text-warning-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Criações/Renovações</p>
                    <p class="text-2xl font-bold text-info-500"><?php echo $stats['user_creation_count'] + $stats['user_renewal_count']; ?></p>
                </div>
                <div class="w-12 h-12 bg-info-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-plus text-info-500"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Filtros</h3>
        <p class="card-subtitle">Refine os resultados da busca</p>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="form-group">
                <label for="user_id" class="form-label">Usuário</label>
                <select name="user_id" id="user_id" class="form-input form-select">
                    <option value="">Todos os usuários</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?> (<?php echo $user['role'] === 'admin' ? 'Admin' : 'Master'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="type" class="form-label">Tipo de Transação</label>
                <select name="type" id="type" class="form-input form-select">
                    <option value="">Todos os tipos</option>
                    <option value="purchase" <?php echo $filter_type === 'purchase' ? 'selected' : ''; ?>>Compra</option>
                    <option value="admin_add" <?php echo $filter_type === 'admin_add' ? 'selected' : ''; ?>>Adição Manual</option>
                    <option value="user_creation" <?php echo $filter_type === 'user_creation' ? 'selected' : ''; ?>>Criação de Usuário</option>
                    <option value="user_renewal" <?php echo $filter_type === 'user_renewal' ? 'selected' : ''; ?>>Renovação de Usuário</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date_start" class="form-label">Data Inicial</label>
                <input type="date" name="date_start" id="date_start" class="form-input" value="<?php echo $filter_date_start; ?>">
            </div>
            
            <div class="form-group">
                <label for="date_end" class="form-label">Data Final</label>
                <input type="date" name="date_end" id="date_end" class="form-input" value="<?php echo $filter_date_end; ?>">
            </div>
            
            <div class="form-actions md:col-span-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
                
                <a href="credit_transactions.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Limpar Filtros
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Histórico de Transações</h3>
        <p class="card-subtitle">Mostrando até 500 transações mais recentes</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                        <th>Descrição</th>
                        <th>Relacionado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Nenhuma transação encontrada</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo $transaction['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $transaction['user_id']; ?>" class="user-link">
                                        <?php echo htmlspecialchars($transaction['user_username']); ?>
                                    </a>
                                </td>
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
                                        <a href="edit_user.php?id=<?php echo $transaction['related_user_id']; ?>" class="user-link">
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

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }

    /* Dark theme adjustments */
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date range validation
    const dateStartInput = document.getElementById('date_start');
    const dateEndInput = document.getElementById('date_end');
    
    if (dateStartInput && dateEndInput) {
        dateEndInput.addEventListener('change', function() {
            if (dateStartInput.value && this.value && this.value < dateStartInput.value) {
                alert('A data final não pode ser anterior à data inicial');
                this.value = dateStartInput.value;
            }
        });
        
        dateStartInput.addEventListener('change', function() {
            if (dateEndInput.value && this.value && this.value > dateEndInput.value) {
                dateEndInput.value = this.value;
            }
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>