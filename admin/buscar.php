<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

// Configuração da API
$apiKey = 'ec8237f367023fbadd38ab6a1596b40c';
$language = 'pt-BR';

$pageTitle = "Resultados da Busca";
include "includes/header.php";

// Verificar se há parâmetros de busca
if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
    ?>
    <div class="page-header">
        <h1 class="page-title">Erro na Busca</h1>
        <p class="page-subtitle">Parâmetros de busca inválidos</p>
    </div>

    <div class="card">
        <div class="card-body text-center py-12">
            <div class="mb-4">
                <i class="fas fa-exclamation-triangle text-6xl text-warning-500"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2">Busca inválida</h3>
            <p class="text-muted mb-6">Por favor, realize uma busca válida na página anterior.</p>
            <a href="painel.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Voltar para Busca
            </a>
        </div>
    </div>
    <?php
    include "includes/footer.php";
    exit();
}

try {
    $query = urlencode(trim($_GET['query']));
    $type = isset($_GET['type']) && $_GET['type'] == 'serie' ? 'serie' : 'filme';
    $ano = isset($_GET['ano_lancamento']) && !empty($_GET['ano_lancamento']) ? intval($_GET['ano_lancamento']) : null;
    
    // Determinar tipo da API
    if ($type == 'serie') {
        $api_type = 'tv';
        $url = "https://api.themoviedb.org/3/search/tv?api_key=$apiKey&language=$language&query=$query";
        if ($ano) { 
            $url .= "&first_air_date_year=$ano"; 
        }
    } else {
        $api_type = 'movie';
        $url = "https://api.themoviedb.org/3/search/movie?api_key=$apiKey&language=$language&query=$query";
        if ($ano) { 
            $url .= "&primary_release_year=$ano"; 
        }
    }

    // Fazer requisição à API
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (compatible; FutBanner/1.0)'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("Erro ao conectar com a API do TMDB");
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta da API");
    }

    ?>
    <div class="page-header">
        <h1 class="page-title">Resultados da Busca</h1>
        <p class="page-subtitle">Encontramos os seguintes resultados para: <strong>"<?php echo htmlspecialchars(urldecode($_GET['query'])); ?>"</strong></p>
    </div>

    <div class="mb-6">
        <a href="painel.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Nova Busca
        </a>
    </div>

    <?php if ($data && isset($data['results']) && !empty($data['results'])): ?>
		<div class="grid-responsivo">
            <?php foreach ($data['results'] as $item): 
                $id = isset($item['id']) ? $item['id'] : 0;
                $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'Título não disponível');
                $posterPath = isset($item['poster_path']) && $item['poster_path'] ? "https://image.tmdb.org/t/p/w500" . $item['poster_path'] : null;
                $releaseDate = isset($item['release_date']) ? $item['release_date'] : (isset($item['first_air_date']) ? $item['first_air_date'] : '');
                $year = $releaseDate ? substr($releaseDate, 0, 4) : '';
                $overview = isset($item['overview']) ? $item['overview'] : '';
                $rating = isset($item['vote_average']) ? $item['vote_average'] : 0;
            ?>
                <div class="card hover:shadow-xl transition-all duration-300">
                    <div class="relative overflow-hidden">
                        <?php if ($posterPath): ?>
                            <img src="<?php echo htmlspecialchars($posterPath); ?>" 
                                 alt="Poster de <?php echo htmlspecialchars($title); ?>" 
                                 class="w-full h-80 object-cover hover:scale-105 transition-transform duration-300"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="w-full h-80 bg-gray-200 flex items-center justify-center">
                                <i class="fas fa-image text-4xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Rating Badge -->
                        <?php if ($rating > 0): ?>
                            <div class="absolute top-3 right-3 bg-black bg-opacity-75 text-white px-2 py-1 rounded-lg text-sm font-semibold">
                                <i class="fas fa-star text-yellow-400 mr-1"></i>
                                <?php echo number_format($rating, 1); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Type Badge -->
                        <div class="absolute top-3 left-3 bg-primary-500 text-white px-2 py-1 rounded-lg text-xs font-semibold uppercase">
                            <?php echo $type === 'serie' ? 'Série' : 'Filme'; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <h3 class="font-semibold text-lg mb-2 line-clamp-2"><?php echo htmlspecialchars($title); ?></h3>
                        
                        <?php if ($releaseDate): ?>
                            <p class="text-sm text-muted mb-2">
                                <i class="fas fa-calendar mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($releaseDate)); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($overview): ?>
                            <p class="text-sm text-muted mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($overview, 0, 120)) . '...'; ?></p>
                        <?php endif; ?>
                        
                        <form method="GET" class="mt-auto">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>">
                            <input type="hidden" name="type" value="<?php echo $type === 'serie' ? 'serie' : 'filme'; ?>">
                            <input type="hidden" name="year" value="<?php echo htmlspecialchars($year, ENT_QUOTES); ?>">
                            <button type="button" class="btn btn-primary w-full" onclick="openThemeSelectionModal(event, this.form)">
                                <i class="fas fa-magic"></i>
                                Gerar Banner
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-12">
                <div class="mb-4">
                    <i class="fas fa-search text-6xl text-gray-300"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Nenhum resultado encontrado</h3>
                <p class="text-muted mb-6">Tente buscar com termos diferentes ou verifique a ortografia.</p>
                <a href="painel.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Fazer Nova Busca
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php
} catch (Exception $e) {
    ?>
    <div class="page-header">
        <h1 class="page-title">Erro na Busca</h1>
        <p class="page-subtitle">Ocorreu um problema ao processar sua solicitação</p>
    </div>

    <div class="card">
        <div class="card-body text-center py-12">
            <div class="mb-4">
                <i class="fas fa-exclamation-triangle text-6xl text-danger-500"></i>
            </div>
            <h3 class="text-xl font-semibold mb-2">Erro no Sistema</h3>
            <p class="text-muted mb-6"><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <div class="flex gap-4 justify-center">
                <a href="painel.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Voltar para Busca
                </a>
                <button onclick="location.reload()" class="btn btn-secondary">
                    <i class="fas fa-redo"></i>
                    Tentar Novamente
                </button>
            </div>
        </div>
    </div>
    <?php
}
?>

<style>
    .grid-responsivo {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
        
    @media (min-width: 640px) {
        .grid-responsivo {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
        
    @media (min-width: 768px) {
        .grid-responsivo {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
        
    @media (min-width: 1024px) {
        .grid-responsivo {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
	
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .relative {
        position: relative;
    }
    
    .absolute {
        position: absolute;
    }
    
    .top-3 {
        top: 0.75rem;
    }
    
    .right-3 {
        right: 0.75rem;
    }
    
    .left-3 {
        left: 0.75rem;
    }
    
    .bg-black {
        background-color: rgb(0 0 0);
    }
    
    .bg-opacity-75 {
        background-color: rgb(0 0 0 / 0.75);
    }
    
    .text-white {
        color: rgb(255 255 255);
    }
    
    .px-2 {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    .py-1 {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }
    
    .rounded-lg {
        border-radius: var(--border-radius);
    }
    
    .text-sm {
        font-size: 0.875rem;
        line-height: 1.25rem;
    }
    
    .text-xs {
        font-size: 0.75rem;
        line-height: 1rem;
    }
    
    .font-semibold {
        font-weight: 600;
    }
    
    .uppercase {
        text-transform: uppercase;
    }
    
    .text-yellow-400 {
        color: rgb(250 204 21);
    }
    
    .mr-1 {
        margin-right: 0.25rem;
    }
    
    .mb-2 {
        margin-bottom: 0.5rem;
    }
    
    .mb-4 {
        margin-bottom: 1rem;
    }
    
    .mb-6 {
        margin-bottom: 1.5rem;
    }
    
    .mt-auto {
        margin-top: auto;
    }
    
    .w-full {
        width: 100%;
    }
    
    .h-80 {
        height: 20rem;
    }
    
    .object-cover {
        object-fit: cover;
    }
    
    .overflow-hidden {
        overflow: hidden;
    }
    
    .transition-transform {
        transition-property: transform;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }
    
    .duration-300 {
        transition-duration: 300ms;
    }
    
    .hover\:scale-105:hover {
        transform: scale(1.05);
    }
    
    .text-lg {
        font-size: 1.125rem;
        line-height: 1.75rem;
    }
    
    .text-xl {
        font-size: 1.25rem;
        line-height: 1.75rem;
    }
    
    .text-6xl {
        font-size: 3.75rem;
        line-height: 1;
    }
    
    .text-4xl {
        font-size: 2.25rem;
        line-height: 2.5rem;
    }
    
    .py-12 {
        padding-top: 3rem;
        padding-bottom: 3rem;
    }
    
    .bg-gray-200 {
        background-color: var(--bg-tertiary);
    }
    
    .text-gray-300 {
        color: var(--text-muted);
    }
    
    .text-gray-400 {
        color: var(--text-muted);
    }
    
    .flex {
        display: flex;
    }
    
    .items-center {
        align-items: center;
    }
    
    .justify-center {
        justify-content: center;
    }
    
    .text-center {
        text-align: center;
    }
    
    .bg-primary-500 {
        background-color: var(--primary-500);
    }
    
    .text-danger-500 {
        color: var(--danger-500);
    }
    
    .gap-4 {
        gap: 1rem;
    }

    /* Estilos para o modal de seleção de tema */
    .theme-selection-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .theme-selection-modal.active {
        opacity: 1;
        visibility: visible;
    }

    .theme-modal-content {
        background: var(--bg-primary);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-xl);
        border: 1px solid var(--border-color);
        width: 95%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .theme-modal-header {
        padding: 2rem 2rem 1rem;
        text-align: center;
        border-bottom: 1px solid var(--border-color);
    }

    .theme-modal-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }

    .theme-modal-title i {
        color: var(--primary-500);
    }

    .theme-modal-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
    }

    .theme-modal-body {
        padding: 2rem;
    }

    .theme-options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .theme-option {
        background: var(--bg-secondary);
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .theme-option:hover {
        border-color: var(--primary-500);
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .theme-option.selected {
        border-color: var(--primary-500);
        background: var(--primary-50);
        box-shadow: var(--shadow-md);
    }

    [data-theme="dark"] .theme-option.selected {
        background: rgba(59, 130, 246, 0.1);
    }

    .theme-preview {
        width: 100%;
        height: 150px;
        background: var(--bg-tertiary);
        border-radius: var(--border-radius-sm);
        margin-bottom: 1rem;
        overflow: hidden;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .theme-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .theme-option:hover .theme-preview img {
        transform: scale(1.05);
    }

    .theme-preview-placeholder {
        color: var(--text-muted);
        font-size: 2rem;
    }

    .theme-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .theme-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .theme-select-btn {
        width: 100%;
        padding: 0.75rem 1.5rem;
        background: var(--primary-500);
        color: white;
        border: none;
        border-radius: var(--border-radius-sm);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .theme-select-btn:hover {
        background: var(--primary-600);
        transform: translateY(-1px);
    }

    .theme-modal-footer {
        padding: 1.5rem 2rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: center;
    }

    .close-modal-btn {
        padding: 0.75rem 2rem;
        background: var(--bg-tertiary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        cursor: pointer;
        transition: var(--transition);
        font-weight: 500;
    }

    .close-modal-btn:hover {
        background: var(--bg-secondary);
    }

    /* Loading overlay para o modal */
    .theme-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .theme-loading-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .theme-loading-spinner {
        width: 48px;
        height: 48px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top: 4px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .theme-modal-content {
            width: 98%;
            margin: 1rem;
        }

        .theme-modal-header,
        .theme-modal-body,
        .theme-modal-footer {
            padding: 1.5rem 1rem;
        }

        .theme-options-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .theme-modal-title {
            font-size: 1.5rem;
        }
    }
    
    /* Dark theme adjustments */
    [data-theme="dark"] .bg-gray-200 {
        background-color: var(--bg-tertiary);
    }
    
    [data-theme="dark"] .text-gray-300 {
        color: var(--text-muted);
    }
    
    [data-theme="dark"] .text-gray-400 {
        color: var(--text-muted);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Função para abrir o modal de seleção de tema
    function openThemeSelectionModal(event, form) {
        event.preventDefault();
        
        // Extrair dados do formulário
        const formData = new FormData(form);
        const movieData = {
            name: formData.get('name'),
            type: formData.get('type'),
            year: formData.get('year')
        };
        
        // Criar o modal usando SweetAlert2
        Swal.fire({
            title: '',
            html: `
                <div class="theme-modal-header">
                    <h2 class="theme-modal-title">
                        <i class="fas fa-palette"></i>
                        Escolha o Tema do Banner
                    </h2>
                    <p class="theme-modal-subtitle">Selecione o estilo que melhor se adequa ao seu projeto</p>
                </div>
                
                <div class="theme-modal-body">
                    <div class="theme-options-grid">
                        <!-- Tema 1 -->
                        <div class="theme-option" data-theme="1" onclick="selectTheme(1)">
                            <div class="theme-preview">
                                <img src="https://i.ibb.co/MJCWzXj/8966-media-3.png" alt="Tema 1" loading="lazy">
                            </div>
                            <h3 class="theme-name">Tema Clássico</h3>
                            <p class="theme-description">Tema 1 Descrição</p>
                            <button class="theme-select-btn" onclick="generateBanner(1, '${encodeURIComponent(JSON.stringify(movieData))}')">
                                <i class="fas fa-check"></i>
                                Selecionar Tema
                            </button>
                        </div>
                        
                        <!-- Tema 2 -->
                        <div class="theme-option" data-theme="2" onclick="selectTheme(2)">
                            <div class="theme-preview">
                                <img src="https://i.ibb.co/6R7F9Y09/8966-media-2.png" alt="Tema 2" loading="lazy">
                            </div>
                            <h3 class="theme-name">Tema Moderno</h3>
                            <p class="theme-description">Tema 2 Descrição</p>
                            <button class="theme-select-btn" onclick="generateBanner(2, '${encodeURIComponent(JSON.stringify(movieData))}')">
                                <i class="fas fa-check"></i>
                                Selecionar Tema
                            </button>
                        </div>
                        
                        <!-- Tema 3 -->
                        <!-- <div class="theme-option" data-theme="3" onclick="selectTheme(3)">
                            <div class="theme-preview">
                                <img src="https://i.ibb.co/x8PCQM3r/8966-media-1.png" alt="Tema 3" loading="lazy">
                            </div>
                            <h3 class="theme-name">Tema Premium</h3>
                            <p class="theme-description">Tema 3 Descrição</p>
                            <button class="theme-select-btn" onclick="generateBanner(3, '${encodeURIComponent(JSON.stringify(movieData))}')">
                                <i class="fas fa-check"></i>
                                Selecionar Tema
                            </button>
                        </div>
                    </div> -->
                    
                    <!-- Loading overlay -->
                    <div class="theme-loading-overlay" id="themeLoadingOverlay">
                        <div class="theme-loading-spinner"></div>
                        <p>Gerando seu banner personalizado...</p>
                        <p style="font-size: 0.875rem; opacity: 0.8; margin-top: 0.5rem;">Isso pode levar alguns segundos</p>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            width: '900px',
            customClass: {
                popup: 'theme-selection-popup',
                htmlContainer: 'theme-selection-container'
            },
            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
            didOpen: () => {
                // Adicionar estilos CSS ao modal
                const style = document.createElement('style');
                style.textContent = `
                    .theme-selection-popup {
                        padding: 0 !important;
                    }
                    .theme-selection-container {
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                `;
                document.head.appendChild(style);
            }
        });
    }
    
    // Função para selecionar um tema (visual feedback)
    function selectTheme(themeNumber) {
        // Remover seleção anterior
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Adicionar seleção ao tema clicado
        const selectedOption = document.querySelector(`[data-theme="${themeNumber}"]`);
        if (selectedOption) {
            selectedOption.classList.add('selected');
        }
    }
    
    // Função para gerar o banner com o tema selecionado
    function generateBanner(themeNumber, encodedMovieData) {
        // Mostrar loading overlay
        const loadingOverlay = document.getElementById('themeLoadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.add('active');
        }
        
        // Decodificar dados do filme
        const movieData = JSON.parse(decodeURIComponent(encodedMovieData));
        
        // Determinar o arquivo de geração baseado no tema
        let generatorFile;
        switch (themeNumber) {
            case 1:
                generatorFile = 'gerar_banner.php'; // Tema Premium (com cantos arredondados)
                break;
            case 2:
                generatorFile = 'gerar_banner2.php'; // Tema Moderno (horizontal)
                break;
            case 3:
                generatorFile = 'gerar_banner3.php';  // Tema Clássico (vertical)
                break;
            default:
                generatorFile = 'gerar_banner.php';
        }
        
        // Construir URL com parâmetros
        const params = new URLSearchParams({
            name: movieData.name,
            type: movieData.type,
            year: movieData.year || ''
        });
        
        const url = `${generatorFile}?${params.toString()}`;
        
        // Simular um pequeno delay para mostrar o loading
        setTimeout(() => {
            // Fechar o modal
            Swal.close();
            
            // Mostrar modal de carregamento final
            Swal.fire({
                title: 'Gerando Banner',
                text: `Criando seu banner com o Tema ${themeNumber}...`,
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Redirecionar após um breve delay
            setTimeout(() => {
                window.location.href = url;
            }, 1500);
        }, 800);
    }

    // Função original renomeada para evitar conflitos
    function showSearchLoading(event, form) {
        event.preventDefault();
        
        Swal.fire({
            title: 'Buscando Conteúdo',
            text: 'Por favor, aguarde enquanto buscamos os resultados...',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
            color: document.body.getAttribute('data-theme') === 'dark' ? '#f1f5f9' : '#1e293b',
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        setTimeout(() => {
            form.submit();
        }, 1000);
    }
</script>

<?php include "includes/footer.php"; ?>
