<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

// Incluir funções necessárias
require_once 'includes/banner_functions.php';

// Obter dados reais dos jogos
$jogos = obterJogosDeHoje();
$totalJogosHoje = count($jogos);

// Função para determinar o status do jogo
function getGameStatus($jogo) {
    $status = $jogo['status'] ?? '';
    $horario = $jogo['horario'] ?? '';
    
    // Se tem status específico na API, usar ele
    if (!empty($status)) {
        switch (strtoupper($status)) {
            case 'ADIADO':
                return ['text' => 'ADIADO', 'class' => 'status-postponed', 'icon' => 'fa-pause'];
            case 'CANCELADO':
                return ['text' => 'CANCELADO', 'class' => 'status-cancelled', 'icon' => 'fa-times'];
            case 'FINALIZADO':
                return ['text' => 'FINALIZADO', 'class' => 'status-finished', 'icon' => 'fa-flag-checkered'];
            case 'AO_VIVO':
            case 'LIVE':
                return ['text' => 'AO VIVO', 'class' => 'status-live', 'icon' => 'fa-circle'];
            case 'INTERVALO':
                return ['text' => 'INTERVALO', 'class' => 'status-halftime', 'icon' => 'fa-pause-circle'];
        }
    }
    
    // Se não tem status específico, determinar baseado no horário
    if (!empty($horario)) {
        $agora = new DateTime();
        $horaJogo = DateTime::createFromFormat('H:i', $horario);
        
        if ($horaJogo) {
            $horaJogo->setDate($agora->format('Y'), $agora->format('m'), $agora->format('d'));
            
            $diffMinutos = ($agora->getTimestamp() - $horaJogo->getTimestamp()) / 60;
            
            if ($diffMinutos < -30) {
                return ['text' => 'AGENDADO', 'class' => 'status-scheduled', 'icon' => 'fa-clock'];
            } elseif ($diffMinutos >= -30 && $diffMinutos < 0) {
                return ['text' => 'EM BREVE', 'class' => 'status-soon', 'icon' => 'fa-hourglass-half'];
            } elseif ($diffMinutos >= 0 && $diffMinutos < 120) {
                return ['text' => 'AO VIVO', 'class' => 'status-live', 'icon' => 'fa-circle'];
            } else {
                return ['text' => 'FINALIZADO', 'class' => 'status-finished', 'icon' => 'fa-flag-checkered'];
            }
        }
    }
    
    return ['text' => 'AGENDADO', 'class' => 'status-scheduled', 'icon' => 'fa-clock'];
}

// Função para encontrar o próximo jogo
function getNextGame($jogos) {
    $agora = new DateTime();
    $proximoJogo = null;
    $menorDiferenca = PHP_INT_MAX;
    
    foreach ($jogos as $jogo) {
        $horario = $jogo['horario'] ?? '';
        $status = $jogo['status'] ?? '';
        
        // Pular jogos adiados, cancelados ou finalizados
        if (in_array(strtoupper($status), ['ADIADO', 'CANCELADO', 'FINALIZADO'])) {
            continue;
        }
        
        if (!empty($horario)) {
            $horaJogo = DateTime::createFromFormat('H:i', $horario);
            if ($horaJogo) {
                $horaJogo->setDate($agora->format('Y'), $agora->format('m'), $agora->format('d'));
                
                // Se o jogo é no futuro
                $diferenca = $horaJogo->getTimestamp() - $agora->getTimestamp();
                if ($diferenca > 0 && $diferenca < $menorDiferenca) {
                    $menorDiferenca = $diferenca;
                    $proximoJogo = $jogo;
                }
            }
        }
    }
    
    return $proximoJogo;
}

$pageTitle = "Jogos de Hoje";
include "includes/header.php";
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-futbol text-success-500 mr-3"></i>
        Jogos de Hoje
    </h1>
    <p class="page-subtitle">
        <?php if ($totalJogosHoje > 0): ?>
            <?php echo $totalJogosHoje; ?> jogos disponíveis - Atualizado em <?php echo date('H:i'); ?>
        <?php else: ?>
            Nenhum jogo programado para hoje
        <?php endif; ?>
    </p>
</div>

<!-- Actions Bar -->
<div class="flex justify-between items-center mb-6">
    <div class="flex gap-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Voltar ao Dashboard
        </a>
        <button id="refreshBtn" class="btn btn-secondary" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i>
            Atualizar Jogos
        </button>
    </div>
    <?php if ($totalJogosHoje > 0): ?>
    <a href="futbanner.php" class="btn btn-primary">
        <i class="fas fa-magic"></i>
        Gerar Banners
    </a>
    <?php endif; ?>
</div>

<?php if ($totalJogosHoje > 0): ?>
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted">Total de Jogos</p>
                        <p class="text-2xl font-bold text-primary"><?php echo $totalJogosHoje; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-primary-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-futbol text-primary-500"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Calcular estatísticas dos jogos
        $ligas = [];
        $jogosComCanais = 0;
        $jogosAoVivo = 0;
        $proximoJogo = getNextGame($jogos);

        foreach ($jogos as $jogo) {
            $liga = $jogo['competicao'] ?? 'Liga';
            $ligas[$liga] = ($ligas[$liga] ?? 0) + 1;
            
            if (!empty($jogo['canais'])) {
                $jogosComCanais++;
            }
            
            $status = getGameStatus($jogo);
            if ($status['class'] === 'status-live') {
                $jogosAoVivo++;
            }
        }
        
        $ligasCount = count($ligas);
        $ligaPrincipal = $ligasCount > 0 ? array_keys($ligas, max($ligas))[0] : 'N/A';
        ?>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted">Ligas/Competições</p>
                        <p class="text-2xl font-bold text-success-500"><?php echo $ligasCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-success-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-trophy text-success-500"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-xs text-success-600 font-medium">
                        Principal: <?php echo htmlspecialchars(substr($ligaPrincipal, 0, 15)); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted">Jogos ao Vivo</p>
                        <p class="text-2xl font-bold text-danger-500"><?php echo $jogosAoVivo; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-danger-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-circle text-danger-500 live-pulse"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-xs text-danger-600 font-medium">
                        <?php echo $jogosComCanais; ?> com transmissão
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted">Próximo Jogo</p>
                        <p class="text-2xl font-bold text-info-500"><?php echo $proximoJogo ? $proximoJogo['horario'] : '--:--'; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-info-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-info-500"></i>
                    </div>
                </div>
                <?php if ($proximoJogo): ?>
                <div class="mt-2">
                    <span class="text-xs text-info-600 font-medium">
                        <?php echo htmlspecialchars(substr($proximoJogo['time1'] . ' vs ' . $proximoJogo['time2'], 0, 20)); ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="mt-2">
                    <span class="text-xs text-muted font-medium">
                        Nenhum jogo pendente
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Games Grid -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Todos os Jogos de Hoje</h3>
            <p class="card-subtitle">Acompanhe o status em tempo real de cada partida</p>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php foreach ($jogos as $index => $jogo): 
                    $time1 = $jogo['time1'] ?? 'Time 1';
                    $time2 = $jogo['time2'] ?? 'Time 2';
                    $liga = $jogo['competicao'] ?? 'Liga';
                    $hora = $jogo['horario'] ?? '';
                    $canais = $jogo['canais'] ?? [];
                    $temCanais = !empty($canais);
                    $placar1 = $jogo['placar_time1'] ?? '';
                    $placar2 = $jogo['placar_time2'] ?? '';
                    $temPlacar = !empty($placar1) || !empty($placar2);
                    $status = getGameStatus($jogo);
                    $liga_img = $jogo['img_competicao_url'] ?? '';
                ?>
                    <div class="game-card-detailed">
                        <div class="game-header-detailed">
                            <div class="league-info">
                                <?php if (!empty($liga_img)): ?>
                                <div class="league-logo">
                                    <img src="<?php echo htmlspecialchars($liga_img); ?>" alt="<?php echo htmlspecialchars($liga); ?>" loading="lazy">
                                </div>
                                <?php endif; ?>
                                <span class="league-name-detailed"><?php echo htmlspecialchars($liga); ?></span>
                                <span class="game-status <?php echo $status['class']; ?>">
                                    <i class="fas <?php echo $status['icon']; ?>"></i>
                                    <?php echo $status['text']; ?>
                                </span>
                            </div>
                            <span class="game-time-detailed"><?php echo htmlspecialchars($hora); ?></span>
                        </div>
                        
                        <div class="game-teams-detailed">
                            <div class="team-detailed">
                                <?php if (!empty($jogo['img_time1_url'])): ?>
                                <div class="team-logo">
                                    <img src="<?php echo htmlspecialchars($jogo['img_time1_url']); ?>" alt="<?php echo htmlspecialchars($time1); ?>" loading="lazy">
                                </div>
                                <?php endif; ?>
                                <span class="team-name-detailed"><?php echo htmlspecialchars($time1); ?></span>
                                <?php if ($temPlacar): ?>
                                    <span class="team-score"><?php echo htmlspecialchars($placar1 ?: '0'); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="vs-detailed">
                                <?php if ($temPlacar): ?>
                                    <span class="score-separator">×</span>
                                <?php else: ?>
                                    VS
                                <?php endif; ?>
                            </div>
                            
                            <div class="team-detailed">
                                <?php if (!empty($jogo['img_time2_url'])): ?>
                                <div class="team-logo">
                                    <img src="<?php echo htmlspecialchars($jogo['img_time2_url']); ?>" alt="<?php echo htmlspecialchars($time2); ?>" loading="lazy">
                                </div>
                                <?php endif; ?>
                                <span class="team-name-detailed"><?php echo htmlspecialchars($time2); ?></span>
                                <?php if ($temPlacar): ?>
                                    <span class="team-score"><?php echo htmlspecialchars($placar2 ?: '0'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($temCanais): ?>
                        <div class="channels-info">
                            <div class="channels-list">
                                <?php 
                                $canaisLimitados = array_slice($canais, 0, 3);
                                foreach ($canaisLimitados as $canal): 
                                ?>
                                    <span class="channel-badge">
                                        <?php if (!empty($canal['img_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($canal['img_url']); ?>" alt="<?php echo htmlspecialchars($canal['nome'] ?? 'Canal'); ?>" class="channel-logo">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($canal['nome'] ?? 'Canal'); ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($canais) > 3): ?>
                                    <span class="channel-badge more">+<?php echo count($canais) - 3; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- No Games Available -->
    <div class="card">
        <div class="card-body text-center py-12">
            <div class="mb-4">
                <i class="fas fa-futbol text-6xl text-gray-300"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2">Nenhum jogo disponível</h3>
            <p class="text-muted mb-6">Não há jogos programados para hoje no momento.</p>
            <div class="flex gap-4 justify-center">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Voltar ao Dashboard
                </a>
                <button onclick="location.reload()" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i>
                    Verificar Novamente
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    /* Cores adicionais */
    :root {
        --info-50: #eff6ff;
        --info-500: #3b82f6;
        --info-600: #2563eb;
    }

    /* Cards de jogos detalhados */
    .game-card-detailed {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.25rem;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .game-card-detailed:hover {
        background: var(--bg-tertiary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-500);
    }

    .game-header-detailed {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        gap: 0.5rem;
    }

    .league-info {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    .league-logo {
        width: 24px;
        height: 24px;
        margin-bottom: 0.25rem;
    }

    .league-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .league-name-detailed {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--primary-600);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
        margin-bottom: 0.5rem;
    }

    /* Status dos jogos */
    .game-status {
        font-size: 0.625rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .status-live {
        color: var(--danger-600);
        background: var(--danger-50);
        border: 1px solid var(--danger-200);
        animation: pulse 2s infinite;
    }

    .status-scheduled {
        color: var(--primary-600);
        background: var(--primary-50);
        border: 1px solid var(--primary-200);
    }

    .status-soon {
        color: var(--warning-600);
        background: var(--warning-50);
        border: 1px solid var(--warning-200);
    }

    .status-finished {
        color: var(--text-muted);
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
    }

    .status-postponed {
        color: var(--warning-600);
        background: var(--warning-50);
        border: 1px solid var(--warning-200);
    }

    .status-cancelled {
        color: var(--danger-600);
        background: var(--danger-50);
        border: 1px solid var(--danger-200);
    }

    .status-halftime {
        color: var(--info-600);
        background: var(--info-50);
        border: 1px solid var(--info-200);
    }

    .game-time-detailed {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        background: var(--bg-primary);
        padding: 0.375rem 0.75rem;
        border-radius: var(--border-radius-sm);
        white-space: nowrap;
    }

    .game-teams-detailed {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .team-detailed {
        flex: 1;
        text-align: center;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
    }

    .team-logo {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.25rem;
    }

    .team-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .team-name-detailed {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        display: block;
        word-wrap: break-word;
        line-height: 1.2;
    }

    .team-score {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-600);
        background: var(--primary-50);
        padding: 0.25rem 0.5rem;
        border-radius: var(--border-radius-sm);
        min-width: 2rem;
        text-align: center;
    }

    .vs-detailed {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--bg-primary);
        padding: 0.375rem 0.75rem;
        border-radius: 50%;
        min-width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--border-color);
    }

    .score-separator {
        font-size: 1rem;
        font-weight: 700;
        color: var(--primary-600);
    }

    .channels-info {
        padding-top: 0.75rem;
        border-top: 1px solid var(--border-color);
    }

    .channels-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem;
    }

    .channel-badge {
        font-size: 0.625rem;
        font-weight: 500;
        color: var(--success-600);
        background: var(--success-50);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        border: 1px solid var(--success-200);
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .channel-logo {
        width: 16px;
        height: 16px;
        object-fit: contain;
    }

    .channel-badge.more {
        color: var(--text-muted);
        background: var(--bg-tertiary);
        border-color: var(--border-color);
    }

    /* Animações */
    .live-pulse {
        animation: livePulse 2s infinite;
    }

    @keyframes livePulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.7;
            transform: scale(1.1);
        }
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.7;
        }
    }

    /* Utilitários */
    .text-info-500 {
        color: var(--info-500);
    }

    .text-info-600 {
        color: var(--info-600);
    }

    .bg-info-50 {
        background-color: var(--info-50);
    }

    .text-danger-500 {
        color: var(--danger-500);
    }

    .text-danger-600 {
        color: var(--danger-600);
    }

    .bg-danger-50 {
        background-color: var(--danger-50);
    }

    .text-6xl {
        font-size: 3.75rem;
        line-height: 1;
    }

    .text-xl {
        font-size: 1.25rem;
        line-height: 1.75rem;
    }

    .py-12 {
        padding-top: 3rem;
        padding-bottom: 3rem;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    .mb-6 {
        margin-bottom: 1.5rem;
    }

    .gap-4 {
        gap: 1rem;
    }

    .justify-center {
        justify-content: center;
    }

    /* Dark theme adjustments */
    [data-theme="dark"] .text-gray-300 {
        color: var(--text-muted);
    }

    [data-theme="dark"] .league-name-detailed {
        color: var(--primary-400);
    }

    [data-theme="dark"] .status-live {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
        border-color: rgba(239, 68, 68, 0.2);
    }

    [data-theme="dark"] .status-scheduled {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
        border-color: rgba(59, 130, 246, 0.2);
    }

    [data-theme="dark"] .status-soon {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
        border-color: rgba(245, 158, 11, 0.2);
    }

    [data-theme="dark"] .status-postponed {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-400);
        border-color: rgba(245, 158, 11, 0.2);
    }

    [data-theme="dark"] .status-cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-400);
        border-color: rgba(239, 68, 68, 0.2);
    }

    [data-theme="dark"] .status-halftime {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info-400);
        border-color: rgba(59, 130, 246, 0.2);
    }

    [data-theme="dark"] .team-score {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-400);
    }

    [data-theme="dark"] .channel-badge {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success-400);
        border-color: rgba(34, 197, 94, 0.2);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .game-teams-detailed {
            flex-direction: column;
            gap: 0.75rem;
        }

        .vs-detailed {
            order: 2;
            margin: 0.5rem 0;
        }

        .team-detailed:first-child {
            order: 1;
        }

        .team-detailed:last-child {
            order: 3;
        }

        .team-detailed {
            flex-direction: row;
            justify-content: space-between;
            width: 100%;
        }
    }
</style>

<script>
    // Auto-refresh a cada 2 minutos para manter dados atualizados
    setTimeout(() => {
        location.reload();
    }, 120000); // 2 minutos

    // Mostrar horário da última atualização
    document.addEventListener('DOMContentLoaded', function() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('pt-BR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        // Atualizar subtitle com horário atual se não foi definido no PHP
        const subtitle = document.querySelector('.page-subtitle');
        if (subtitle && !subtitle.textContent.includes('Atualizado em')) {
            subtitle.textContent += ` - Atualizado em ${timeString}`;
        }
    });
</script>

<?php include "includes/footer.php"; ?>