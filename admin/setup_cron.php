<?php
/**
 * 🕐 Script para configurar o cron job de limpeza automática
 * Execute este arquivo UMA VEZ para configurar a limpeza automática às 00h
 */

session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    die("Acesso negado");
}

$pageTitle = "Configurar Limpeza Automática";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clock text-primary-500 mr-3"></i>
        Configurar Limpeza Automática do Cache
    </h1>
    <p class="page-subtitle">Configure a limpeza automática diária do cache às 00h</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Instruções -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 Instruções de Configuração</h3>
        </div>
        <div class="card-body">
            <div class="space-y-4">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p class="font-medium">Limpeza Automática Necessária</p>
                        <p class="text-sm mt-1">
                            Como os jogos mudam todo dia às 00h, o cache deve ser limpo automaticamente 
                            neste horário para garantir que os banners sempre usem os jogos atuais.
                        </p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">1</div>
                    <div>
                        <h4 class="font-semibold">Acesse o cPanel/Painel de Controle</h4>
                        <p class="text-sm text-muted">Entre no painel de controle do seu servidor/hospedagem</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">2</div>
                    <div>
                        <h4 class="font-semibold">Encontre "Cron Jobs" ou "Tarefas Agendadas"</h4>
                        <p class="text-sm text-muted">Procure pela seção de cron jobs no seu painel</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">3</div>
                    <div>
                        <h4 class="font-semibold">Adicione o Cron Job</h4>
                        <p class="text-sm text-muted">Use as configurações abaixo</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configurações -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">⚙️ Configurações do Cron</h3>
        </div>
        <div class="card-body">
            <div class="space-y-4">
                <div class="config-item">
                    <label class="config-label">Frequência:</label>
                    <div class="config-value">
                        <code>0 0 * * *</code>
                        <span class="config-desc">Todo dia às 00:00 (meia-noite)</span>
                    </div>
                </div>

                <div class="config-item">
                    <label class="config-label">Comando:</label>
                    <div class="config-value">
                        <div class="code-block">
                            <code id="cronCommand">/usr/bin/php <?php echo realpath(__DIR__); ?>/cache_cleanup.php</code>
                            <button class="copy-btn" onclick="copyToClipboard('cronCommand')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <span class="config-desc">Ajuste o caminho do PHP se necessário</span>
                    </div>
                </div>

                <div class="config-item">
                    <label class="config-label">Comando Alternativo:</label>
                    <div class="config-value">
                        <div class="code-block">
                            <code id="cronCommandAlt">php <?php echo realpath(__DIR__); ?>/cache_cleanup.php</code>
                            <button class="copy-btn" onclick="copyToClipboard('cronCommandAlt')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <span class="config-desc">Para servidores que não precisam do caminho completo</span>
                    </div>
                </div>

                <div class="config-item">
                    <label class="config-label">Comando Completo:</label>
                    <div class="config-value">
                        <div class="code-block">
                            <code id="cronCommandFull">0 0 * * * /usr/bin/php <?php echo realpath(__DIR__); ?>/cache_cleanup.php >/dev/null 2>&1</code>
                            <button class="copy-btn" onclick="copyToClipboard('cronCommandFull')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <span class="config-desc">Comando completo com supressão de output</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Teste Manual -->
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">🧪 Teste Manual</h3>
        <p class="card-subtitle">Teste a limpeza antes de configurar o cron</p>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button class="btn btn-secondary" onclick="testCleanup('normal')">
                <i class="fas fa-broom"></i>
                Teste Limpeza Normal
            </button>
            
            <button class="btn btn-warning" onclick="testCleanup('daily')">
                <i class="fas fa-calendar-day"></i>
                Teste Limpeza Diária
            </button>
            
            <button class="btn btn-info" onclick="showStats()">
                <i class="fas fa-chart-bar"></i>
                Ver Estatísticas
            </button>
        </div>
        
        <div id="testResults" class="mt-4" style="display: none;">
            <div class="alert alert-info">
                <i class="fas fa-spinner fa-spin"></i>
                Executando teste...
            </div>
        </div>
    </div>
</div>

<!-- Verificação -->
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">✅ Verificação</h3>
    </div>
    <div class="card-body">
        <div class="space-y-3">
            <div class="check-item">
                <i class="fas fa-check-circle text-success-500"></i>
                <span>Arquivo cache_cleanup.php existe e está acessível</span>
            </div>
            <div class="check-item">
                <i class="fas fa-check-circle text-success-500"></i>
                <span>Permissões de execução configuradas</span>
            </div>
            <div class="check-item">
                <i class="fas fa-clock text-warning-500"></i>
                <span>Cron job precisa ser configurado manualmente no painel de controle</span>
            </div>
        </div>
    </div>
</div>

<style>
    .alert-info {
        background: var(--primary-50);
        color: var(--primary-600);
        border: 1px solid var(--primary-200);
        padding: 1rem;
        border-radius: var(--border-radius);
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .step-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
    }

    .step-number {
        width: 32px;
        height: 32px;
        background: var(--primary-500);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
    }

    .config-item {
        padding: 1rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
    }

    .config-label {
        font-weight: 600;
        color: var(--text-primary);
        display: block;
        margin-bottom: 0.5rem;
    }

    .config-value code {
        background: var(--bg-tertiary);
        padding: 0.5rem;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        display: block;
        margin-bottom: 0.25rem;
    }

    .config-desc {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .code-block {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .code-block code {
        flex: 1;
        margin-bottom: 0;
    }

    .copy-btn {
        background: var(--primary-500);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 4px;
        cursor: pointer;
        transition: var(--transition);
        flex-shrink: 0;
    }

    .copy-btn:hover {
        background: var(--primary-600);
    }

    .check-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
    }

    .space-y-3 > * + * {
        margin-top: 0.75rem;
    }

    .space-y-4 > * + * {
        margin-top: 1rem;
    }

    .mt-6 {
        margin-top: 1.5rem;
    }

    .mt-4 {
        margin-top: 1rem;
    }

    .mt-1 {
        margin-top: 0.25rem;
    }

    /* Dark theme */
    [data-theme="dark"] .alert-info {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
        border-color: rgba(59, 130, 246, 0.2);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;
    
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            title: 'Copiado!',
            text: 'Comando copiado para a área de transferência',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
        });
    }).catch(() => {
        // Fallback para navegadores mais antigos
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        Swal.fire({
            title: 'Copiado!',
            text: 'Comando copiado para a área de transferência',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

function testCleanup(type) {
    const resultsDiv = document.getElementById('testResults');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-spinner fa-spin"></i>
            Executando teste de limpeza ${type === 'daily' ? 'diária' : 'normal'}...
        </div>
    `;
    
    const url = type === 'daily' ? 'cache_cleanup.php?daily=1' : 'cache_cleanup.php';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let details = '';
                if (data.details) {
                    details = `
                        <div class="mt-2 text-sm">
                            <strong>Detalhes:</strong><br>
                            • Total removido: ${data.details.total_removed} arquivos<br>
                            • Expirados: ${data.details.expired_removed}<br>
                            • Todo cache: ${data.details.all_cache_removed}<br>
                            • Antigos: ${data.details.old_removed}
                        </div>
                    `;
                } else {
                    details = `<div class="mt-2 text-sm">Arquivos removidos: ${data.removed_files}</div>`;
                }
                
                resultsDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>${data.message}</strong>
                            ${details}
                            <div class="mt-2 text-xs text-muted">
                                Executado em: ${data.timestamp}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                resultsDiv.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Erro:</strong> ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Erro de conexão:</strong> ${error.message}
                </div>
            `;
        });
}

function showStats() {
    fetch('cache_cleanup.php')
        .then(response => response.json())
        .then(data => {
            if (data.stats) {
                const stats = data.stats;
                Swal.fire({
                    title: 'Estatísticas do Cache',
                    html: `
                        <div class="text-left">
                            <p><strong>Cache válido:</strong> ${stats.valid_cached} arquivos</p>
                            <p><strong>Cache expirado:</strong> ${stats.expired_cached} arquivos</p>
                            <p><strong>Total:</strong> ${stats.total_cached} arquivos</p>
                            ${stats.users_with_cache ? `<p><strong>Usuários com cache:</strong> ${stats.users_with_cache}</p>` : ''}
                            ${stats.total_size_mb ? `<p><strong>Tamanho total:</strong> ${stats.total_size_mb} MB</p>` : ''}
                        </div>
                    `,
                    icon: 'info',
                    background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Erro',
                text: 'Não foi possível obter as estatísticas',
                icon: 'error'
            });
        });
}

// Adicionar estilos para os alertas
const style = document.createElement('style');
style.textContent = `
    .alert-success {
        background: var(--success-50);
        color: var(--success-600);
        border: 1px solid var(--success-200);
        padding: 1rem;
        border-radius: var(--border-radius);
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .alert-error {
        background: var(--danger-50);
        color: var(--danger-600);
        border: 1px solid var(--danger-200);
        padding: 1rem;
        border-radius: var(--border-radius);
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    [data-theme="dark"] .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
        border-color: rgba(34, 197, 94, 0.2);
    }
    
    [data-theme="dark"] .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
        border-color: rgba(239, 68, 68, 0.2);
    }
`;
document.head.appendChild(style);
</script>

<?php include "includes/footer.php"; ?>