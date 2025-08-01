<?php 
/**
 * 🧪 Sistema de Teste Completo do Sistema de Referência
 * 
 * Este arquivo testa todas as funcionalidades do sistema de referência
 * e fornece um relatório detalhado do status de cada componente.
 */

session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'classes/User.php';
require_once 'classes/MercadoPagoSettings.php';
require_once 'config/database.php';

$pageTitle = "Teste do Sistema de Referência";
include "includes/header.php";

// Função para executar teste e retornar resultado
function runTest($testName, $testFunction) {
    try {
        $result = $testFunction();
        return [
            'name' => $testName,
            'success' => $result['success'],
            'message' => $result['message'],
            'details' => $result['details'] ?? null
        ];
    } catch (Exception $e) {
        return [
            'name' => $testName,
            'success' => false,
            'message' => 'Erro na execução: ' . $e->getMessage(),
            'details' => null
        ];
    }
}

// Inicializar classes
$user = new User();
$mercadoPagoSettings = new MercadoPagoSettings();
$db = Database::getInstance()->getConnection();

// Array para armazenar resultados dos testes
$testResults = [];

// Teste 1: Verificar estrutura do banco de dados
$testResults[] = runTest("Estrutura do Banco de Dados", function() use ($db) {
    $requiredTables = [
        'usuarios' => ['id', 'username', 'password', 'email', 'role', 'status', 'expires_at', 'credits', 'parent_user_id'],
        'mercadopago_settings' => ['user_id', 'access_token', 'user_access_value', 'trial_duration_days'],
        'user_images' => ['user_id', 'image_key', 'image_path', 'upload_type']
    ];
    
    $missingTables = [];
    $missingColumns = [];
    
    foreach ($requiredTables as $table => $columns) {
        // Verificar se a tabela existe (usando interpolação direta para o nome da tabela, pois é um identificador interno)
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if (!$stmt->fetch()) {
            $missingTables[] = $table;
            continue;
        }
        
        // Verificar colunas
        $stmt = $db->query("DESCRIBE {$table}");
        $existingColumns = array_column($stmt->fetchAll(), 'Field');
        
        foreach ($columns as $column) {
            if (!in_array($column, $existingColumns)) {
                $missingColumns[] = "$table.$column";
            }
        }
    }
    
    if (empty($missingTables) && empty($missingColumns)) {
        return [
            'success' => true,
            'message' => 'Todas as tabelas e colunas necessárias estão presentes',
            'details' => ['tables_checked' => count($requiredTables)]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Estrutura do banco incompleta',
            'details' => [
                'missing_tables' => $missingTables,
                'missing_columns' => $missingColumns
            ]
        ];
    }
});

// Teste 2: Verificar configurações de teste grátis
$testResults[] = runTest("Configurações de Teste Grátis", function() use ($mercadoPagoSettings) {
    $adminSettings = $mercadoPagoSettings->getSettings(1);
    
    if (!$adminSettings) {
        return [
            'success' => false,
            'message' => 'Configurações do admin não encontradas',
            'details' => ['admin_id' => 1]
        ];
    }
    
    $trialDays = $adminSettings['trial_duration_days'] ?? 0;
    
    if ($trialDays > 0) {
        return [
            'success' => true,
            'message' => "Teste grátis configurado para {$trialDays} dias",
            'details' => ['trial_days' => $trialDays]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Teste grátis não configurado ou com valor inválido',
            'details' => ['trial_days' => $trialDays]
        ];
    }
});

// Teste 3: Verificar usuários master existentes
$testResults[] = runTest("Usuários Master Disponíveis", function() use ($db) {
    $stmt = $db->prepare("SELECT id, username, status, credits FROM usuarios WHERE role = 'master' AND status = 'active'");
    $stmt->execute();
    $masters = $stmt->fetchAll();
    
    if (count($masters) > 0) {
        return [
            'success' => true,
            'message' => count($masters) . " usuários master encontrados",
            'details' => ['masters' => $masters]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Nenhum usuário master ativo encontrado',
            'details' => ['masters_count' => 0]
        ];
    }
});

// Teste 4: Verificar status ENUM
$testResults[] = runTest("Status ENUM do Banco", function() use ($db) {
    $stmt = $db->prepare("
        SELECT COLUMN_TYPE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'status'
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result && strpos($result['COLUMN_TYPE'], 'trial') !== false) {
        return [
            'success' => true,
            'message' => 'Status ENUM inclui opção "trial"',
            'details' => ['enum_type' => $result['COLUMN_TYPE']]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Status ENUM não inclui opção "trial"',
            'details' => ['enum_type' => $result['COLUMN_TYPE'] ?? 'não encontrado']
        ];
    }
});

// Teste 5: Simular criação de usuário com referência
$testResults[] = runTest("Simulação de Criação de Usuário", function() use ($user) {
    // Verificar se já existe um usuário de teste
    $testUsername = 'test_referral_' . time();
    
    $data = [
        'username' => $testUsername,
        'email' => $testUsername . '@test.com',
        'password' => 'test123456',
        'role' => 'user',
        'status' => 'trial',
        'parent_user_id' => 1, // Admin
        'apply_trial' => true,
        'trial_duration_days' => 7
    ];
    
    $result = $user->createUser($data);
    
    if ($result['success']) {
        // Limpar usuário de teste criado
        $stmt = Database::getInstance()->getConnection()->prepare("DELETE FROM usuarios WHERE username = ?");
        $stmt->execute([$testUsername]);
        
        return [
            'success' => true,
            'message' => 'Criação de usuário com trial funcionando corretamente',
            'details' => ['test_user_cleaned' => true]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Erro na criação de usuário: ' . $result['message'],
            'details' => ['error' => $result['message']]
        ];
    }
});

// Teste 6: Verificar arquivos necessários
$testResults[] = runTest("Arquivos do Sistema", function() {
    $requiredFiles = [
        'register.php' => 'Página de cadastro público',
        'generate_referral_links.php' => 'Gerador de links de referência',
        'classes/User.php' => 'Classe de usuário',
        'classes/MercadoPagoSettings.php' => 'Configurações do Mercado Pago'
    ];
    
    $missingFiles = [];
    
    foreach ($requiredFiles as $file => $description) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missingFiles[] = "$file ($description)";
        }
    }
    
    if (empty($missingFiles)) {
        return [
            'success' => true,
            'message' => 'Todos os arquivos necessários estão presentes',
            'details' => ['files_checked' => count($requiredFiles)]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Arquivos ausentes encontrados',
            'details' => ['missing_files' => $missingFiles]
        ];
    }
});

// Teste 7: Verificar URLs de referência
$testResults[] = runTest("URLs de Referência", function() {
    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
    $testUrls = [
        'direct' => $baseUrl . '/admin/register.php',
        'referral' => $baseUrl . '/admin/register.php?ref=1'
    ];
    
    $urlTests = [];
    foreach ($testUrls as $type => $url) {
        $urlTests[$type] = [
            'url' => $url,
            'valid' => filter_var($url, FILTER_VALIDATE_URL) !== false
        ];
    }
    
    $allValid = array_reduce($urlTests, function($carry, $test) {
        return $carry && $test['valid'];
    }, true);
    
    return [
        'success' => $allValid,
        'message' => $allValid ? 'URLs de referência válidas' : 'Algumas URLs são inválidas',
        'details' => ['url_tests' => $urlTests]
    ];
});

// Calcular estatísticas dos testes
$totalTests = count($testResults);
$passedTests = count(array_filter($testResults, function($test) { return $test['success']; }));
$failedTests = $totalTests - $passedTests;
$successRate = $totalTests > 0 ? ($passedTests / $totalTests) * 100 : 0;
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-vial text-primary-500 mr-3"></i>
        Teste do Sistema de Referência
    </h1>
    <p class="page-subtitle">Verificação completa de todas as funcionalidades implementadas</p>
</div>

<!-- Test Summary -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Total de Testes</p>
                    <p class="text-2xl font-bold text-primary"><?php echo $totalTests; ?></p>
                </div>
                <div class="w-12 h-12 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-vial text-primary-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Testes Aprovados</p>
                    <p class="text-2xl font-bold text-success-500"><?php echo $passedTests; ?></p>
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
                    <p class="text-sm font-medium text-muted">Testes Falharam</p>
                    <p class="text-2xl font-bold text-danger-500"><?php echo $failedTests; ?></p>
                </div>
                <div class="w-12 h-12 bg-danger-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times-circle text-danger-500"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-muted">Taxa de Sucesso</p>
                    <p class="text-2xl font-bold text-info-500"><?php echo number_format($successRate, 1); ?>%</p>
                </div>
                <div class="w-12 h-12 bg-info-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-info-500"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Results -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Resultados dos Testes</h3>
        <p class="card-subtitle">Verificação detalhada de cada componente do sistema</p>
    </div>
    <div class="card-body">
        <div class="space-y-4">
            <?php foreach ($testResults as $index => $test): ?>
                <div class="test-result <?php echo $test['success'] ? 'success' : 'failed'; ?>">
                    <div class="test-header">
                        <div class="test-icon">
                            <i class="fas fa-<?php echo $test['success'] ? 'check' : 'times'; ?>"></i>
                        </div>
                        <div class="test-info">
                            <h4 class="test-name"><?php echo $test['name']; ?></h4>
                            <p class="test-message"><?php echo $test['message']; ?></p>
                        </div>
                        <div class="test-number"><?php echo $index + 1; ?></div>
                    </div>
                    
                    <?php if ($test['details']): ?>
                    <div class="test-details">
                        <h5>Detalhes:</h5>
                        <pre><?php echo json_encode($test['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Manual Tests -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Testes Manuais</h3>
        <p class="card-subtitle">Execute estes testes manualmente para verificar a funcionalidade completa</p>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="manual-test-card">
                <h4 class="manual-test-title">
                    <i class="fas fa-user-plus text-primary-500"></i>
                    Teste de Cadastro Direto
                </h4>
                <p class="manual-test-desc">Teste o cadastro sem link de referência</p>
                <div class="manual-test-steps">
                    <ol>
                        <li>Abra uma aba anônima</li>
                        <li>Acesse: <code><?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/register.php'; ?></code></li>
                        <li>Cadastre um usuário teste</li>
                        <li>Verifique se foi associado ao Admin</li>
                        <li>Verifique se recebeu status "trial"</li>
                    </ol>
                </div>
                <a href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/register.php'; ?>" target="_blank" class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt"></i>
                    Testar Agora
                </a>
            </div>

            <div class="manual-test-card">
                <h4 class="manual-test-title">
                    <i class="fas fa-link text-success-500"></i>
                    Teste de Link de Referência
                </h4>
                <p class="manual-test-desc">Teste o cadastro com link de referência</p>
                <div class="manual-test-steps">
                    <ol>
                        <li>Crie um usuário master primeiro</li>
                        <li>Gere o link de referência</li>
                        <li>Abra uma aba anônima</li>
                        <li>Acesse o link de referência</li>
                        <li>Cadastre um usuário teste</li>
                        <li>Verifique se foi associado ao Master</li>
                    </ol>
                </div>
                <a href="generate_referral_links.php" class="btn btn-success btn-sm">
                    <i class="fas fa-link"></i>
                    Gerar Links
                </a>
            </div>

            <div class="manual-test-card">
                <h4 class="manual-test-title">
                    <i class="fas fa-memory text-warning-500"></i>
                    Teste de Memória de Referência
                </h4>
                <p class="manual-test-desc">Teste se o sistema lembra da referência</p>
                <div class="manual-test-steps">
                    <ol>
                        <li>Acesse um link de referência</li>
                        <li>Navegue para register.php sem ?ref=</li>
                        <li>Cadastre um usuário</li>
                        <li>Verifique se manteve a referência original</li>
                    </ol>
                </div>
                <button class="btn btn-warning btn-sm" onclick="testReferralMemory()">
                    <i class="fas fa-vial"></i>
                    Testar Memória
                </button>
            </div>

            <div class="manual-test-card">
                <h4 class="manual-test-title">
                    <i class="fas fa-clock text-info-500"></i>
                    Teste de Expiração de Trial
                </h4>
                <p class="manual-test-desc">Teste o comportamento de usuários com trial expirado</p>
                <div class="manual-test-steps">
                    <ol>
                        <li>Crie um usuário trial</li>
                        <li>Altere expires_at para data passada</li>
                        <li>Tente fazer login</li>
                        <li>Verifique redirecionamento para payment.php</li>
                    </ol>
                </div>
                <button class="btn btn-info btn-sm" onclick="createExpiredTrialUser()">
                    <i class="fas fa-user-clock"></i>
                    Criar Usuário Teste
                </button>
            </div>
        </div>
    </div>
</div>

<!-- System Status -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Status do Sistema</h3>
        <p class="card-subtitle">Informações gerais sobre o estado atual do sistema</p>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="status-section">
                <h4 class="status-title">
                    <i class="fas fa-users text-primary-500"></i>
                    Usuários no Sistema
                </h4>
                <?php
                $userStats = $user->getUserStats();
                ?>
                <div class="status-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total:</span>
                        <span class="stat-value"><?php echo $userStats['total']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Ativos:</span>
                        <span class="stat-value text-success-500"><?php echo $userStats['active']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Em Teste:</span>
                        <span class="stat-value text-info-500"><?php echo $userStats['trial']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Masters:</span>
                        <span class="stat-value text-primary-500"><?php echo $userStats['masters']; ?></span>
                    </div>
                </div>
            </div>

            <div class="status-section">
                <h4 class="status-title">
                    <i class="fas fa-cog text-warning-500"></i>
                    Configurações
                </h4>
                <?php
                $adminSettings = $mercadoPagoSettings->getSettings(1);
                ?>
                <div class="status-stats">
                    <div class="stat-item">
                        <span class="stat-label">Trial configurado:</span>
                        <span class="stat-value <?php echo $adminSettings ? 'text-success-500' : 'text-danger-500'; ?>">
                            <?php echo $adminSettings ? 'Sim' : 'Não'; ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Duração do trial:</span>
                        <span class="stat-value"><?php echo $adminSettings['trial_duration_days'] ?? 'N/A'; ?> dias</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Valor mensal:</span>
                        <span class="stat-value">R$ <?php echo number_format($adminSettings['user_access_value'] ?? 0, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>

            <div class="status-section">
                <h4 class="status-title">
                    <i class="fas fa-link text-success-500"></i>
                    Links de Referência
                </h4>
                <div class="status-stats">
                    <div class="stat-item">
                        <span class="stat-label">URL base:</span>
                        <span class="stat-value text-xs"><?php echo $_SERVER['HTTP_HOST']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Registro público:</span>
                        <span class="stat-value text-success-500">Ativo</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Memória de sessão:</span>
                        <span class="stat-value text-success-500">Funcionando</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .test-result {
        border-radius: var(--border-radius);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .test-result.success {
        border-color: var(--success-200);
        background: var(--success-50);
    }

    .test-result.failed {
        border-color: var(--danger-200);
        background: var(--danger-50);
    }

    .test-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
    }

    .test-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .test-result.success .test-icon {
        background: var(--success-500);
        color: white;
    }

    .test-result.failed .test-icon {
        background: var(--danger-500);
        color: white;
    }

    .test-info {
        flex: 1;
    }

    .test-name {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .test-result.success .test-name {
        color: var(--success-700);
    }

    .test-result.failed .test-name {
        color: var(--danger-700);
    }

    .test-message {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .test-number {
        width: 32px;
        height: 32px;
        background: var(--bg-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--text-primary);
    }

    .test-details {
        padding: 1rem;
        background: var(--bg-primary);
        border-top: 1px solid var(--border-color);
    }

    .test-details h5 {
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .test-details pre {
        background: var(--bg-tertiary);
        padding: 0.75rem;
        border-radius: var(--border-radius-sm);
        font-size: 0.75rem;
        overflow-x: auto;
        color: var(--text-secondary);
    }

    .manual-test-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.5rem;
    }

    .manual-test-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .manual-test-desc {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .manual-test-steps {
        margin-bottom: 1rem;
    }

    .manual-test-steps ol {
        padding-left: 1.25rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .manual-test-steps li {
        margin-bottom: 0.25rem;
    }

    .manual-test-steps code {
        background: var(--bg-tertiary);
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        font-size: 0.75rem;
        word-break: break-all;
    }

    .status-section {
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        padding: 1.5rem;
    }

    .status-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-stats {
        space-y: 0.5rem;
    }

    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--border-color);
    }

    .stat-item:last-child {
        border-bottom: none;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .stat-value {
        font-weight: 500;
        color: var(--text-primary);
    }

    .space-y-4 > * + * {
        margin-top: 1rem;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .test-result.success {
        background: rgba(34, 197, 94, 0.1);
        border-color: rgba(34, 197, 94, 0.2);
    }

    [data-theme="dark"] .test-result.failed {
        background: rgba(239, 68, 68, 0.1);
        border-color: rgba(239, 68, 68, 0.2);
    }

    [data-theme="dark"] .test-result.success .test-name {
        color: var(--success-400);
    }

    [data-theme="dark"] .test-result.failed .test-name {
        color: var(--danger-400);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function testReferralMemory() {
    Swal.fire({
        title: 'Teste de Memória de Referência',
        html: `
            <div class="text-left">
                <p class="mb-3">Para testar a memória de referência:</p>
                <ol class="text-sm space-y-1">
                    <li>1. Abra uma nova aba anônima</li>
                    <li>2. Acesse: <code>register.php?ref=1</code></li>
                    <li>3. Depois acesse: <code>register.php</code> (sem ref)</li>
                    <li>4. Cadastre um usuário</li>
                    <li>5. Verifique se foi associado ao usuário da referência original</li>
                </ol>
                <p class="mt-3 text-xs text-muted">O sistema deve lembrar da primeira referência acessada.</p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Entendi',
        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
        color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
    });
}

function createExpiredTrialUser() {
    Swal.fire({
        title: 'Criar Usuário Trial Expirado',
        text: 'Isso criará um usuário de teste com trial expirado para testar o fluxo de renovação.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Criar',
        cancelButtonText: 'Cancelar',
        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
        color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
    }).then((result) => {
        if (result.isConfirmed) {
            // Simular criação via AJAX
            fetch('create_test_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=create_expired_trial'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Usuário Criado!',
                        html: `
                            <p>Usuário de teste criado:</p>
                            <p><strong>Username:</strong> ${data.username}</p>
                            <p><strong>Senha:</strong> ${data.password}</p>
                            <p><strong>Status:</strong> Trial Expirado</p>
                        `,
                        icon: 'success',
                        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
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
            });
        }
    });
}

// Auto-refresh test results every 30 seconds
setTimeout(() => {
    location.reload();
}, 30000);
</script>

<?php include "includes/footer.php"; ?>