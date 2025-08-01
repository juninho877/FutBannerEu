<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Verificação de sessão primeiro
if (!isset($_SESSION["usuario"])) {
    http_response_code(403);
    header('Content-Type: image/png');
    $im = imagecreatetruecolor(600, 100);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagestring($im, 5, 10, 40, "Erro: Acesso Negado.", imagecolorallocate($im, 0, 0, 0));
    imagepng($im);
    imagedestroy($im);
    exit();
}

require_once 'includes/banner_functions.php';
require_once 'classes/BannerStats.php';
require_once 'classes/BannerCache.php';

$jogos = obterJogosDeHoje();

if (empty($jogos)) {
    header('Content-Type: image/png'); 
    $im = imagecreatetruecolor(600, 100);
    $bg = imagecolorallocate($im, 255, 255, 255); imagefill($im, 0, 0, $bg);
    $color = imagecolorallocate($im, 0, 0, 0);
    imagestring($im, 5, 10, 40, "Nenhum jogo disponível.", $color);
    imagepng($im); imagedestroy($im); exit;
}

$jogosPorBanner = 5;
$gruposDeJogos = array_chunk(array_keys($jogos), $jogosPorBanner);
$width = 720;
$heightPorJogo = 140;
$padding = 15;
$espacoExtra = 200;
$fontLiga = __DIR__ . '/fonts/MANDATOR.ttf';

// Inicializar cache
$bannerCache = new BannerCache();
$userId = $_SESSION['user_id'];

if (isset($_GET['download_all']) && $_GET['download_all'] == 1) {
    // Registrar estatística para download de todos os banners
    $bannerStats = new BannerStats();
    $bannerStats->recordBannerGeneration($userId, 'football', 'tema2_all', 'Banners Futebol V2 - Todos');
    
    // Gerar chave de cache para todos os banners
    $allBannersCacheKey = $bannerCache->generateCacheKey($userId, 'football_2_all', 'all', $jogos);
    
    // Verificar se existe cache válido para o ZIP
    $cachedZip = $bannerCache->getCachedBanner($userId, $allBannersCacheKey);
    
    if ($cachedZip && file_exists($cachedZip['file_path'])) {
        // Servir ZIP do cache
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $cachedZip['original_name'] . '"');
        header('Content-Length: ' . filesize($cachedZip['file_path']));
        header('Pragma: no-cache');
        header('Expires: 0');
        
        readfile($cachedZip['file_path']);
        exit;
    }
    
    $zip = new ZipArchive();
    $zipNome = "banners_Top_V3_" . date('Y-m-d') . ".zip";
    $caminhoTempZip = sys_get_temp_dir() . '/' . uniqid('banners_V3_') . '.zip';
    $tempFiles = [];

    if ($zip->open($caminhoTempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($gruposDeJogos as $index => $grupoJogos) {
            // Verificar cache individual primeiro
            $cacheKey = $bannerCache->generateCacheKey($userId, 'football_2', $index, array_intersect_key($jogos, array_flip($grupoJogos)));
            $cachedBanner = $bannerCache->getCachedBanner($userId, $cacheKey);
            
            if ($cachedBanner && file_exists($cachedBanner['file_path'])) {
                // Usar banner do cache
                $zip->addFile($cachedBanner['file_path'], 'banner_V3_parte_' . ($index + 1) . '.png');
            } else {
                // Gerar banner usando a nova função centralizada
                $im = generateFootballBannerResource($userId, 2, $index, $jogos);
                
                if ($im) {
                    // Salvar no cache
                    $bannerCache->saveBannerToCache($userId, $cacheKey, $im, 'football_2', $index);
                    
                    $nomeArquivoTemp = sys_get_temp_dir() . '/banner_V3_parte_' . uniqid() . '.png';
                    imagepng($im, $nomeArquivoTemp);
                    
                    $zip->addFile($nomeArquivoTemp, 'banner_V3_parte_' . ($index + 1) . '.png');
                    $tempFiles[] = $nomeArquivoTemp;
                    imagedestroy($im);
                }
            }
        }
        $zip->close();

        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipNome . '"');
        header('Content-Length: ' . filesize($caminhoTempZip));
        header('Pragma: no-cache');
        header('Expires: 0');

        if(readfile($caminhoTempZip)) {
            foreach ($tempFiles as $file) {
                if (file_exists($file)) unlink($file);
            }
            unlink($caminhoTempZip);
        }
        exit;
    } else {
        die("Erro: Não foi possível criar o arquivo ZIP.");
    }
}

$grupoIndex = isset($_GET['grupo']) ? (int)$_GET['grupo'] : 0;
if (!isset($gruposDeJogos[$grupoIndex])) {
    header('Content-Type: image/png'); 
    $im = imagecreatetruecolor(600, 100);
    $bg = imagecolorallocate($im, 255, 255, 255); imagefill($im, 0, 0, $bg);
    $color = imagecolorallocate($im, 0, 0, 0);
    imagestring($im, 5, 10, 40, "Banner inválido.", $color);
    imagepng($im); imagedestroy($im); exit;
}

$grupoJogos = $gruposDeJogos[$grupoIndex];

// Gerar chave de cache
$jogosDoGrupo = array_intersect_key($jogos, array_flip($grupoJogos));
$cacheKey = $bannerCache->generateCacheKey($userId, 'football_2', $grupoIndex, $jogosDoGrupo);

// Verificar cache
$cachedBanner = $bannerCache->getCachedBanner($userId, $cacheKey);

if ($cachedBanner && file_exists($cachedBanner['file_path'])) {
    // Servir do cache
    $download = isset($_GET['download']) && $_GET['download'] == 1;
    $bannerCache->serveCachedFile($cachedBanner['file_path'], $cachedBanner['original_name'], $download);
    exit;
}

// Gerar banner se não estiver em cache usando a nova função centralizada
$im = generateFootballBannerResource($userId, 2, $grupoIndex, $jogos);

if (!$im) {
    header('Content-Type: image/png');
    $im = imagecreatetruecolor(600, 100);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagestring($im, 5, 10, 40, "Erro ao gerar banner.", imagecolorallocate($im, 0, 0, 0));
    imagepng($im);
    imagedestroy($im);
    exit;
}

// Registrar estatística para banner individual
$bannerStats = new BannerStats();
$bannerStats->recordBannerGeneration($userId, 'football', 'tema2', 'Banner Futebol V2 - Parte ' . ($grupoIndex + 1));

// Salvar no cache
$originalName = "banner_V3_" . date('Y-m-d') . "_parte" . ($grupoIndex + 1) . ".png";
$bannerCache->saveBannerToCache($userId, $cacheKey, $im, 'football_2', $grupoIndex, $originalName);

if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=300');
imagepng($im);
imagedestroy($im);
exit;
?>