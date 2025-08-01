<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

$pageTitle = "Banner Filmes e Séries";
include "includes/header.php"; 
?>

<div class="page-header">
    <h1 class="page-title">Gerar Banner - Filme/Série</h1>
    <p class="page-subtitle">Busque por um título para gerar seu banner personalizado</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Search Form -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Buscar Conteúdo</h3>
                <p class="card-subtitle">Digite o nome do filme ou série que deseja criar o banner</p>
            </div>
            <div class="card-body">
                <form action="buscar.php" method="GET" onsubmit="playClickSound()">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="form-group">
                            <label for="query" class="form-label">
                                <i class="fas fa-search mr-2"></i>
                                Nome do Filme ou Série
                            </label>
                            <input type="text" id="query" name="query" class="form-input" placeholder="Ex: Interestelar, Breaking Bad..." required>
                        </div>
                        
                        <div class="form-group">
                            <label for="type" class="form-label">
                                <i class="fas fa-film mr-2"></i>
                                Tipo de Conteúdo
                            </label>
                            <select name="type" id="type" class="form-input form-select">
                                <option value="filme">🎬 Filme</option>
                                <option value="serie">📺 Série</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-6">
                        <label for="ano_lancamento" class="form-label">
                            <i class="fas fa-calendar mr-2"></i>
                            Ano de Lançamento (Opcional)
                        </label>
                        <input type="number" id="ano_lancamento" name="ano_lancamento" class="form-input" placeholder="Ex: 2014" min="1900" max="2030">
                        <p class="text-xs text-muted mt-1">Deixe em branco para buscar sem filtro de ano</p>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fas fa-search"></i>
                        Buscar e Gerar Banner
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Panel -->
    <div class="space-y-6">
        <!-- Tips Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💡 Dicas de Busca</h3>
            </div>
            <div class="card-body">
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-success-500 mt-0.5"></i>
                        <div>
                            <p class="font-medium">Use nomes originais</p>
                            <p class="text-muted">Prefira títulos em inglês para melhores resultados</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-success-500 mt-0.5"></i>
                        <div>
                            <p class="font-medium">Especifique o ano</p>
                            <p class="text-muted">Ajuda a encontrar o conteúdo correto</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-success-500 mt-0.5"></i>
                        <div>
                            <p class="font-medium">Verifique a ortografia</p>
                            <p class="text-muted">Nomes corretos garantem melhores resultados</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Searches -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🕒 Buscas Recentes</h3>
            </div>
            <div class="card-body">
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-film text-primary-500"></i>
                            <span class="text-sm">Interestelar</span>
                        </div>
                        <button class="text-xs text-primary-500 hover:text-primary-600">Usar</button>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-tv text-primary-500"></i>
                            <span class="text-sm">Breaking Bad</span>
                        </div>
                        <button class="text-xs text-primary-500 hover:text-primary-600">Usar</button>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-film text-primary-500"></i>
                            <span class="text-sm">The Matrix</span>
                        </div>
                        <button class="text-xs text-primary-500 hover:text-primary-600">Usar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📊 Estatísticas</h3>
            </div>
            <div class="card-body">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-muted">Banners gerados hoje</span>
                        <span class="font-semibold text-primary-600">12</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-muted">Total este mês</span>
                        <span class="font-semibold text-success-600">156</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-muted">Mais buscado</span>
                        <span class="font-semibold">Filmes</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<audio id="clickSound" src="https://cdn.pixabay.com/audio/2022/03/15/audio_12d3b003b2.mp3" preload="auto"></audio>

<style>
    [data-theme="dark"] .bg-gray-50 {
        background-color: var(--bg-tertiary);
    }
    
    .space-y-2 > * + * {
        margin-top: 0.5rem;
    }
    
    .space-y-3 > * + * {
        margin-top: 0.75rem;
    }
    
    .space-y-4 > * + * {
        margin-top: 1rem;
    }
    
    .space-y-6 > * + * {
        margin-top: 1.5rem;
    }
    
    .mr-2 {
        margin-right: 0.5rem;
    }
    
    .mt-0.5 {
        margin-top: 0.125rem;
    }
    
    .mt-1 {
        margin-top: 0.25rem;
    }
    
    .w-full {
        width: 100%;
    }
</style>

<script>
    function playClickSound() {
        const sound = document.getElementById('clickSound');
        if (sound) {
            sound.play().catch(e => console.log('Audio play failed:', e));
        }
    }

    // Recent searches functionality
    document.addEventListener('DOMContentLoaded', function() {
        const useButtons = document.querySelectorAll('[data-search]');
        const queryInput = document.getElementById('query');
        
        useButtons.forEach(button => {
            button.addEventListener('click', function() {
                const searchTerm = this.getAttribute('data-search');
                if (queryInput && searchTerm) {
                    queryInput.value = searchTerm;
                    queryInput.focus();
                }
            });
        });
    });
</script>

<?php include "includes/footer.php"; ?>
