<?php
// Configurações otimizadas
define('CLOUDINARY_CLOUD_NAME', 'dwrikepvg');
define('LOGO_OVERRIDES', [
    'St. Louis City' => 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/soccer/500/21812.png',
    'Guarulhos' => 'https://upload.wikimedia.org/wikipedia/pt/d/d5/GuarulhosGRU.png',
    'Estados Unidos' => 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/countries/500/usa.png',
    'Tupa' => 'https://static.flashscore.com/res/image/data/8SqNKfdM-27lsDqoa.png',
    'Guadeloupe' => 'https://static.flashscore.com/res/image/data/z7uwX5e5-Qw31eZbP.png',
    'Tanabi' => 'https://ssl.gstatic.com/onebox/media/sports/logos/_0PCb1YBKcxp8eXBCCtZpg_96x96.png',
    'Mundial de Clubes FIFA' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ad/2025_FIFA_Club_World_Cup.svg/1200px-2025_FIFA_Club_World_Cup.svg.png',
]);

// Cache simples
$imageCache = [];

// Incluir a classe UserImage
require_once __DIR__ . '/../classes/UserImage.php';

function truncateText($text, $fontSize, $fontPath, $maxWidth) {
    // Mede a largura inicial do texto
    $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
    $textWidth = $bbox[2] - $bbox[0];
    
    // Se o texto já cabe, retorna o original
    if ($textWidth <= $maxWidth) {
        return $text;
    }
    
    // Reduz o texto caractere por caractere até caber
    while ($textWidth > $maxWidth && mb_strlen($text, 'UTF-8') > 1) {
        $text = mb_substr($text, 0, -1, 'UTF-8');
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text . '...');
        $textWidth = $bbox[2] - $bbox[0];
    }
    
    return $text . '...';
}
function imagefilledroundedrect($im, $x1, $y1, $x2, $y2, $radius, $color) {
    // Desenha os retângulos do meio
    imagefilledrectangle($im, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    // Desenha os arcos dos cantos
    imagefilledarc($im, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, IMG_ARC_PIE);
    imagefilledarc($im, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color, IMG_ARC_PIE);
}
function removerAcentos($texto) {
    // Passo 1: Remove acentos, como antes (usando o método que funciona no seu servidor)
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

    // Passo 2: Remove os parênteses usando uma expressão regular.
    // O padrão /[()]/ significa "encontre o caractere '(' ou o caractere ')'"
    // e substitua por nada ('').
    $texto = preg_replace('/[()]/', '', $texto);

    return $texto;
}
function desenhar_retangulo_arredondado($image, $x, $y, $width, $height, $radius, $color) {
    $x1 = $x; $y1 = $y; $x2 = $x + $width; $y2 = $y + $height;
    if ($radius > $width / 2) $radius = $width / 2;
    if ($radius > $height / 2) $radius = $height / 2;

    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledarc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, IMG_ARC_PIE);
    imagefilledarc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, IMG_ARC_PIE);
    imagefilledarc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, IMG_ARC_PIE);
    imagefilledarc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color, IMG_ARC_PIE);
}
function limitarTexto($texto, $limite, $sufixo = '...') {
    // Verifica se o texto já é menor ou igual ao limite. Usa mb_strlen para contar caracteres corretamente.
    if (mb_strlen($texto) <= $limite) {
        return $texto;
    }

    // Corta o texto, garantindo espaço para o sufixo.
    // Usa mb_substr para cortar a string sem quebrar caracteres especiais.
    $textoCortado = mb_substr($texto, 0, $limite - mb_strlen($sufixo));
    
    // Retorna o texto cortado com o sufixo no final.
    return $textoCortado . $sufixo;
}
function carregarImagemDeUrl(string $url, int $maxSize) {
    global $imageCache;
    
    $cacheKey = md5($url . $maxSize);
    if (isset($imageCache[$cacheKey])) {
        return $imageCache[$cacheKey];
    }

    $urlParaCarregar = $url;
    $extensao = strtolower(pathinfo($url, PATHINFO_EXTENSION));

    if ($extensao === 'svg') {
        $cloudinaryCloudName = CLOUDINARY_CLOUD_NAME;
        if (empty($cloudinaryCloudName)) return $imageCache[$cacheKey] = false;
        $urlParaCarregar = "https://res.cloudinary.com/{$cloudinaryCloudName}/image/fetch/f_png/" . urlencode($url);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $urlParaCarregar,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FutBanner/1.0)',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);
    
    $imageContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($imageContent === false || $httpCode >= 400) {
        return $imageCache[$cacheKey] = false;
    }
    
    $img = @imagecreatefromstring($imageContent);
    if (!$img) {
        return $imageCache[$cacheKey] = false;
    }

    $w = imagesx($img); $h = imagesy($img);
    if ($w == 0 || $h == 0) {
        imagedestroy($img);
        return $imageCache[$cacheKey] = false;
    }
    
    $scale = min($maxSize / $w, $maxSize / $h, 1.0);
    $newW = (int)($w * $scale); $newH = (int)($h * $scale);
    $imgResized = imagecreatetruecolor($newW, $newH);
    imagealphablending($imgResized, false); 
    imagesavealpha($imgResized, true);
    imagecopyresampled($imgResized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($img);
    
    return $imageCache[$cacheKey] = $imgResized;
}

function carregarLogoCanalComAlturaFixa(string $url, int $alturaFixa = 50) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FutBanner/1.0)'
    ]);
    
    $imageContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($imageContent === false || $httpCode >= 400) return false;
    
    $img = @imagecreatefromstring($imageContent);
    if (!$img) return false;
    
    $origW = imagesx($img); $origH = imagesy($img);
    if ($origH == 0) { imagedestroy($img); return false; }
    
    $ratio = $origW / $origH;
    $newW = (int)($alturaFixa * $ratio);
    $newH = $alturaFixa;
    $imgResized = imagecreatetruecolor($newW, $newH);
    imagealphablending($imgResized, false); 
    imagesavealpha($imgResized, true);
    $transparent = imagecolorallocatealpha($imgResized, 0, 0, 0, 127);
    imagefill($imgResized, 0, 0, $transparent);
    imagecopyresampled($imgResized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($img);
    return $imgResized;
}

function criarPlaceholderComNome(string $nomeTime, int $size = 68) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false); 
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    $textColor = imagecolorallocate($img, 80, 80, 80);
    $fontePath = __DIR__ . '/../fonts/RobotoCondensed-Bold.ttf';
    
    if (!file_exists($fontePath)) { 
        imagestring($img, 2, 2, $size/2 - 5, "No Logo", $textColor); 
        return $img; 
    }
    
    $nomeLimpo = trim(preg_replace('/\s*\([^)]*\)/', '', $nomeTime));
    $palavras = explode(' ', $nomeLimpo);
    $linhas = []; $linhaAtual = '';
    
    foreach ($palavras as $palavra) {
        $testeLinha = $linhaAtual . ($linhaAtual ? ' ' : '') . $palavra;
        $bbox = imagettfbbox(10.5, 0, $fontePath, $testeLinha);
        if (($bbox[2] - $bbox[0]) > ($size - 8) && $linhaAtual !== '') { 
            $linhas[] = $linhaAtual; 
            $linhaAtual = $palavra; 
        } else { 
            $linhaAtual = $testeLinha; 
        }
    }
    $linhas[] = $linhaAtual;
    
    $bbox = imagettfbbox(10.5, 0, $fontePath, "A");
    $alturaLinha = abs($bbox[7] - $bbox[1]);
    $alturaTotalTexto = (count($linhas) * $alturaLinha) + ((count($linhas) - 1) * 2);
    $y = ($size - $alturaTotalTexto) / 2 + $alturaLinha;
    
    foreach ($linhas as $linha) {
        $bboxLinha = imagettfbbox(10.5, 0, $fontePath, $linha);
        $x = ($size - ($bboxLinha[2] - $bboxLinha[0])) / 2;
        imagettftext($img, 10.5, 0, (int)$x, (int)$y, $textColor, $fontePath, $linha);
        $y += $alturaLinha + 2;
    }
    return $img;
}

function carregarEscudo(string $nomeTime, ?string $url, int $maxSize = 60) {
    if (!empty($url)) {
        $imagem = carregarImagemDeUrl($url, $maxSize);
        if ($imagem) return $imagem;
    }
    return criarPlaceholderComNome($nomeTime, $maxSize);
}

function getChaveRemota() {
    $url = base64_decode('aHR0cHM6Ly9hcGlmdXQucHJvamVjdHguY2xpY2svQXV0b0FwaS9BRVMvY29uZmlna2V5LnBocA==');
    $auth = base64_decode('dmFxdW9UQlpFb0U4QmhHMg==');
    $postData = json_encode(['auth' => $auth]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($postData)]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ? json_decode($response, true)['chave'] ?? null : null;
}

function descriptografarURL($urlCodificada, $chave) {
    $parts = explode('::', base64_decode($urlCodificada), 2);
    if (count($parts) < 2) return null;
    list($url_criptografada, $iv) = $parts;
    return openssl_decrypt($url_criptografada, 'aes-256-cbc', $chave, 0, $iv);
}

function desenharTexto($im, $texto, $x, $y, $cor, $tamanho=12, $angulo=0, $fonteCustom = null) {
    $fontPath = __DIR__ . '/../fonts/CalSans-Regular.ttf';
    $fonteUsada = $fonteCustom ?? $fontPath;
    if (file_exists($fonteUsada)) {
        $bbox = imagettfbbox($tamanho, $angulo, $fonteUsada, $texto);
        $alturaTexto = abs($bbox[7] - $bbox[1]);
        imagettftext($im, $tamanho, $angulo, $x, $y + $alturaTexto, $cor, $fonteUsada, $texto);
    } else {
        imagestring($im, 5, $x, $y, $texto, $cor);
    }
}

/**
 * Nova função para buscar configuração de imagem do usuário
 * Substitui a antiga função getImageFromJson
 * @param int $userId ID do usuário
 * @param string $imageKey Chave da imagem (ex: logo_banner_1, background_banner_2, etc.)
 * @return string|false Conteúdo da imagem ou false se não encontrado
 */
function getUserImageConfig($userId, $imageKey) {
    static $userImageInstance = null;
    
    if ($userImageInstance === null) {
        $userImageInstance = new UserImage();
    }
    
    return $userImageInstance->getImageContent($userId, $imageKey);
}

/**
 * Função para carregar imagem do usuário como resource GD
 * @param int $userId ID do usuário
 * @param string $imageKey Chave da imagem
 * @return resource|false Resource da imagem ou false se não encontrado
 */
function loadUserImage($userId, $imageKey) {
    $imageContent = getUserImageConfig($userId, $imageKey);
    
    if ($imageContent === false) {
        return false;
    }
    
    return @imagecreatefromstring($imageContent);
}

function centralizarTextoX($larguraImagem, $tamanhoFonte, $fonte, $texto) { 
    if (!file_exists($fonte)) return $larguraImagem / 2;
    $caixa = imagettfbbox($tamanhoFonte, 0, $fonte, $texto); 
    return ($larguraImagem - ($caixa[2] - $caixa[0])) / 2; 
}

function centralizarTexto($larguraImagem, $tamanhoFonte, $fonte, $texto) {
    if (!file_exists($fonte)) return 0;
    $caixa = imagettfbbox($tamanhoFonte, 0, $fonte, $texto);
    $larguraTexto = $caixa[2] - $caixa[0];
    return ($larguraImagem - $larguraTexto) / 2;
}

function obterJogosDeHoje() {
    $chave_secreta = getChaveRemota();
    $parametro_criptografado = 'SVI0Sjh1MTJuRkw1bmFyeFdPb3cwOXA2TFo3RWlSQUxLbkczaGE4MXBiMWhENEpOWkhkSFZoeURaWFVDM1lTZzo6RNBu5BBhzmFRkTPPSikeJg==';
    //$json_url = $chave_secreta ? descriptografarURL($parametro_criptografado, $chave_secreta) : null;
	$json_url = 'https://apisafe.fun/api.php?token=tok_0407e9e6fc6cff135e1267878ff5bc5c339f1df4&api=jogos_hoje';
		
    $jogos = [];
    if ($json_url) {
        $ch = curl_init($json_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $json_content = curl_exec($ch);
        curl_close($ch);
        
        if ($json_content) {
            $todos_jogos = json_decode($json_content, true);
            if (is_array($todos_jogos)) {
                foreach ($todos_jogos as $jogo) {
                    if (isset($jogo['data_jogo']) && $jogo['data_jogo'] === 'hoje') {
                        $jogos[] = $jogo;
                    }
                }
            }
        }
    }
    
    return $jogos;
}

/**
 * Processa os canais de transmissão para exibição no banner
 * @param array $lista Lista de canais
 * @return string String formatada com os canais
 */
function tratarCanaisTransmissao($lista) {
    if (!is_array($lista)) return '';

    $canais = [];
    foreach ($lista as $canal) {
        $nome = strtoupper($canal['nome'] ?? '');
        if (strpos($nome, 'YOUTUBE(') === 0) {
            $canais[] = 'YOUTUBE';
        } else {
            $canais[] = $canal['nome'];
        }
    }

    // Remover duplicados
    $canais = array_unique($canais);

    // Retornar apenas os 3 primeiros
    return implode(', ', array_slice($canais, 0, 3));
}

/**
 * Carrega uma imagem PNG local e a redimensiona
 * @param string $path Caminho para a imagem
 * @param int $width Largura desejada
 * @param int $height Altura desejada
 * @return resource Imagem redimensionada
 */
function carregarImagem($path, $width, $height) {
    if (!file_exists($path)) {
        // Criar uma imagem placeholder se o arquivo não existir
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 200, 200, 200);
        imagefill($img, 0, 0, $bg);
        return $img;
    }
    
    $original = imagecreatefrompng($path);
    $resized = imagecreatetruecolor($width, $height);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $original, 0, 0, 0, 0, 
                      $width, $height, imagesx($original), imagesy($original));
    imagedestroy($original);
    return $resized;
}

/**
 * Gera um recurso de imagem GD para um banner de futebol
 * @param int $userId ID do usuário
 * @param int $bannerModel Modelo do banner (1, 2, 3 ou 4)
 * @param int $grupoIndex Índice do grupo de jogos
 * @param array $allJogos Todos os jogos disponíveis
 * @return resource|false Recurso GD da imagem ou false em caso de erro
 */
function generateFootballBannerResource($userId, $bannerModel, $grupoIndex, $allJogos) {
    // Verificar parâmetros
    if (empty($allJogos)) {
        return false;
    }
    
    // Dividir jogos em grupos
    $jogosPorBanner = 5;
    $gruposDeJogos = array_chunk(array_keys($allJogos), $jogosPorBanner);
    
    // Verificar se o grupo solicitado existe
    if (!isset($gruposDeJogos[$grupoIndex])) {
        return false;
    }
    
    $grupoJogos = $gruposDeJogos[$grupoIndex];
    
    // Configurações comuns
    $padding = 15;
    $fontLiga = __DIR__ . '/../fonts/MANDATOR.ttf';
    
    // Configurações específicas por modelo
    switch ($bannerModel) {
        case 1:
            $width = 1440;
            $heightPorJogo = 240;
            $espacoExtra = 649;
            $height = max(count($grupoJogos) * $heightPorJogo + $padding * 2 + $espacoExtra, 2030);
            return _gerarBannerModel1($userId, $allJogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga);
            
        case 2:
            $width = 720;
            $heightPorJogo = 140;
            $espacoExtra = 200;
            $height = max(count($grupoJogos) * $heightPorJogo + $padding * 2 + $espacoExtra, 1015);
            return _gerarBannerModel2($userId, $allJogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga);
            
        case 3:
            $width = 1080;
            $heightPorJogo = 240;
            $espacoExtra = 340;
            $height = max(count($grupoJogos) * $heightPorJogo + $padding * 2 + $espacoExtra, 1750);
            return _gerarBannerModel3($userId, $allJogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga);
            
        case 4:
            $width = 1080;
            $heightPorJogo = 240;
            $espacoExtra = 300;
            $height = max(count($grupoJogos) * $heightPorJogo + $padding * 2 + $espacoExtra, 1900);
            return _gerarBannerModel4($userId, $allJogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga);
            
        default:
            return false;
    }
}

/**
 * Gera banner modelo 1 (gerar_fut.php)
 */
function _gerarBannerModel1($userId, $jogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga) {
    // Criar imagem
    $im = imagecreatetruecolor($width, $height);
    $preto = imagecolorallocate($im, 0, 0, 0);
    $branco = imagecolorallocate($im, 255, 255, 255);
    
    // Carregar fundo do usuário
    $fundo = loadUserImage($userId, 'background_banner_1');
    if ($fundo) {
        imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
        imagedestroy($fundo);
    } else {
        imagefill($im, 0, 0, $branco);
    }
    
    // Cache para imagens estáticas
    static $fundoJogo = null;
    static $logoLiga = null;
    
    if ($fundoJogo === null) {
        $fundoJogo = loadUserImage($userId, 'card_banner_1');
        if (!$fundoJogo) {
            // Fallback para imagem padrão
            $fundoJogoPath = __DIR__ . '/../wtec/card/card_banner_1.png';
            $fundoJogo = file_exists($fundoJogoPath) ? imagecreatefrompng($fundoJogoPath) : false;
        }
    }
    
    $yAtual = $padding + 570;

    foreach ($grupoJogos as $idx) {
        if (!isset($jogos[$idx])) continue;

        $jogo = $jogos[$idx];
        if ($fundoJogo) {
            imagecopyresampled($im, $fundoJogo, 15, $yAtual, 0, 0, $width - ($padding * 2), $heightPorJogo - 8, imagesx($fundoJogo), imagesy($fundoJogo));
        }

        $time1 = $jogo['time1'] ?? 'Time 1';
        $time2 = $jogo['time2'] ?? 'Time 2';
        $liga = $jogo['competicao'] ?? 'Liga';
        $hora = $jogo['horario'] ?? '';
        
        $escudo1_url = LOGO_OVERRIDES[$time1] ?? ($jogo['img_time1_url'] ?? '');
        $escudo2_url = LOGO_OVERRIDES[$time2] ?? ($jogo['img_time2_url'] ?? '');
        $liga_img_url = LOGO_OVERRIDES[$liga] ?? ($jogo['img_competicao_url'] ?? '');
        
        $imgliga = carregarEscudo($liga, $liga_img_url, 140);
        $imgEscudo1 = carregarEscudo($time1, $escudo1_url, 140);
        $imgEscudo2 = carregarEscudo($time2, $escudo2_url, 140);
        
        $yTop = $yAtual + 20;
        if($imgliga) imagecopy($im, $imgliga, 165, $yTop + 22, 0, 0, imagesx($imgliga), imagesy($imgliga));
        if($imgEscudo1) imagecopy($im, $imgEscudo1, 365, $yTop + 35, 0, 0, imagesx($imgEscudo1), imagesy($imgEscudo1));
        if($imgEscudo2) imagecopy($im, $imgEscudo2, 650, $yTop + 35, 0, 0, imagesx($imgEscudo2), imagesy($imgEscudo2));
        
        // Limpar memória apenas se não estiver no cache
        if($imgliga && !isset($GLOBALS['imageCache'][md5($liga_img_url . 140)])) imagedestroy($imgliga);
        if($imgEscudo1 && !isset($GLOBALS['imageCache'][md5($escudo1_url . 140)])) imagedestroy($imgEscudo1);
        if($imgEscudo2 && !isset($GLOBALS['imageCache'][md5($escudo2_url . 140)])) imagedestroy($imgEscudo2);
        
        $fonteNomes = __DIR__ . '/../fonts/CalSans-Regular.ttf';
        $tamanhoNomes = 30; $corNomes = $preto;
        $textoLinha1 = "$time1 X"; $textoLinha2 = $time2;
        $eixoCentralColuna = 1040;
        
        $bbox1 = imagettfbbox($tamanhoNomes, 0, $fonteNomes, $textoLinha1);
        $xPos1 = $eixoCentralColuna - (($bbox1[2] - $bbox1[0]) / 2);
        $bbox2 = imagettfbbox($tamanhoNomes, 0, $fonteNomes, $textoLinha2);
        $xPos2 = $eixoCentralColuna - (($bbox2[2] - $bbox2[0]) / 2);
        
        desenharTexto($im, $textoLinha1, $xPos1, $yTop + 5, $corNomes, $tamanhoNomes);
        desenharTexto($im, $textoLinha2, $xPos2, $yTop + 45, $corNomes, $tamanhoNomes);
        desenharTexto($im, $hora, 850, $yTop + 115, $branco, 60);
        
        // Canais (limitado a 2 para performance)
        $canaisDoJogo = array_slice($jogo['canais'] ?? [], 0, 2);
        if (!empty($canaisDoJogo)) {
            $logosParaDesenhar = [];
            $larguraTotalBloco = 0; $espacoEntreLogos = 5;
            
            foreach ($canaisDoJogo as $canal) {
                if (!empty($canal['img_url'])) {
                    $logoCanal = carregarLogoCanalComAlturaFixa($canal['img_url'], 85);
                    if ($logoCanal) {
                        $logosParaDesenhar[] = $logoCanal;
                        $larguraTotalBloco += imagesx($logoCanal);
                    }
                }
            }
            
            if (!empty($logosParaDesenhar)) {
                $larguraTotalBloco += (count($logosParaDesenhar) - 1) * $espacoEntreLogos;
                $xAtual = (($width - $larguraTotalBloco) / 2) + 430;
                foreach ($logosParaDesenhar as $logo) {
                    imagecopy($im, $logo, (int)$xAtual, (int)($yTop + 105), 0, 0, imagesx($logo), imagesy($logo));
                    $xAtual += imagesx($logo) + $espacoEntreLogos;
                    imagedestroy($logo);
                }
            }
        }
        $yAtual += $heightPorJogo;
    }

    // Elementos estáticos (cache)
    $fonteTitulo = __DIR__ . '/../fonts/BebasNeue-Regular.ttf';
    $fonteData = __DIR__ . '/../fonts/RobotoCondensed-VariableFont_wght.ttf';
    $corBranco = imagecolorallocate($im, 255, 255, 255);
    $titulo1 = "DESTAQUES DE HOJE";
    
    setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR.UTF-8', 'pt_BR', 'portuguese');
    $dataTexto = mb_strtoupper(strftime('%A - %d de %B'));
    imagettftext($im, 82, 0, centralizarTextoX($width, 82, $fonteTitulo, $titulo1), 140, $corBranco, $fonteTitulo, $titulo1);
    
    $corBranco2 = imagecolorallocate($im, 236, 240, 243);
    $corTexto = imagecolorallocate($im, 0, 0, 0);
    $retanguloLargura = 1135;
    $retanguloAltura = 130;
    $cantoRaio = 15; 
    $retanguloX = ($width - $retanguloLargura) / 2;
    $retanguloY = 348; 
    
    //desenhar_retangulo_arredondado($im, $retanguloX, $retanguloY, $retanguloLargura, $retanguloAltura, $cantoRaio, $corBranco2);
    
    $tamanhoFonte = 55;
    $textoX = centralizarTextoX($width, $tamanhoFonte, $fonteData, $dataTexto) - 33;
    $textoY_preciso = 165;
    
    desenharTexto($im, $dataTexto, $textoX, $textoY_preciso, $corBranco, $tamanhoFonte);

    // Logo das ligas (cache)
    if ($logoLiga === null) {
        //$ligas_url = 'https://i.ibb.co/Cp8ck2H3/Rodape-liga-1440.png';
        //$logoLiga = @imagecreatefrompng($ligas_url);
    }
    
    if ($logoLiga) {
        imagecopy($im, $logoLiga, 0, 1740, 0, 0, imagesx($logoLiga), imagesy($logoLiga));
    }

    // Logo do usuário
    $logoUsuario = loadUserImage($userId, 'logo_banner_1');
    if ($logoUsuario) {
        $w = imagesx($logoUsuario); $h = imagesy($logoUsuario);
        if ($w > 0 && $h > 0) {
            $scale = min(350 / $w, 350 / $h, 1.0);
            $newW = (int)($w * $scale); $newH = (int)($h * $scale);
            $logoRedimensionada = imagecreatetruecolor($newW, $newH);
            imagealphablending($logoRedimensionada, false); 
            imagesavealpha($logoRedimensionada, true);
            imagecopyresampled($logoRedimensionada, $logoUsuario, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagecopy($im, $logoRedimensionada, 555, 235, 0, 0, $newW, $newH);
            imagedestroy($logoRedimensionada);
        }
        imagedestroy($logoUsuario);
    }
    
    return $im;
}

/**
 * Gera banner modelo 2 (gerar_fut_2.php)
 */
function _gerarBannerModel2($userId, $jogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga) {
    // Criar imagem
    $im = imagecreatetruecolor($width, $height);
    $preto = imagecolorallocate($im, 0, 0, 0);
    $branco = imagecolorallocate($im, 255, 255, 255);
    
    // Carregar fundo do usuário
    $fundo = loadUserImage($userId, 'background_banner_2');
    if ($fundo) {
        imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
        imagedestroy($fundo);
    } else {
        imagefill($im, 0, 0, $branco);
    }
    
    // Cache para imagens estáticas
    static $fundoJogo = null;
    static $logoLiga = null;
    
    if ($fundoJogo === null) {
        $fundoJogo = loadUserImage($userId, 'card_banner_2');
        if (!$fundoJogo) {
            // Fallback para imagem padrão
            $fundoJogoPath = __DIR__ . '/../wtec/card/card_banner_2.png';
            $fundoJogo = file_exists($fundoJogoPath) ? imagecreatefrompng($fundoJogoPath) : false;
        }
    }
    
    $yAtual = $padding + 150;
    $offsetEsquerda = 50;
    $posX = 15;
    
    foreach ($grupoJogos as $idx) {
        if (!isset($jogos[$idx])) continue;
        
        if ($fundoJogo) {
            $alturaCard = $heightPorJogo - 8;
            $larguraCard = $width - $padding * 2;
            $cardResized = imagecreatetruecolor($larguraCard, $alturaCard);
            imagealphablending($cardResized, false); imagesavealpha($cardResized, true);
            imagecopyresampled($cardResized, $fundoJogo, 0, 0, 0, 0, $larguraCard, $alturaCard, imagesx($fundoJogo), imagesy($fundoJogo));
            imagecopy($im, $cardResized, $posX, $yAtual, 0, 0, $larguraCard, $alturaCard);
            imagedestroy($cardResized);
        }
        
        $jogo = $jogos[$idx];
        $time1 = $jogo['time1'] ?? 'Time 1';
        $time2 = $jogo['time2'] ?? 'Time 2';
        $liga = $jogo['competicao'] ?? 'Liga';
        $hora = $jogo['horario'] ?? '';
        $canais = implode(', ', array_slice(array_column($jogo['canais'] ?? [], 'nome'), 0, 3));
        
        $escudo1_url = LOGO_OVERRIDES[$time1] ?? $jogo['img_time1_url'] ?? '';
        $escudo2_url = LOGO_OVERRIDES[$time2] ?? $jogo['img_time2_url'] ?? '';
        
        $tamEscudo = 78;
        $imgEscudo1 = carregarEscudo($time1, $escudo1_url, $tamEscudo);
        $imgEscudo2 = carregarEscudo($time2, $escudo2_url, $tamEscudo);
        
        $xBase = $offsetEsquerda;
        $yTop = $yAtual + 20;
        $fontSizeLiga = 12;
        
        $bboxLiga = imagettfbbox($fontSizeLiga, 0, $fontLiga, $liga);
        $textWidthLiga = $bboxLiga[2] - $bboxLiga[0];
        $centerX_Liga = ($width / 2) - ($textWidthLiga / 2);
        desenharTexto($im, $liga, $centerX_Liga, $yTop + 21, $branco, $fontSizeLiga, 0, $fontLiga);
        
        $yEscudos = $yTop - 35;
        imagecopy($im, $imgEscudo1, $xBase + 70, $yEscudos, 0, 0, imagesx($imgEscudo1), imagesy($imgEscudo1));
        imagecopy($im, $imgEscudo2, $xBase + 470, $yEscudos, 0, 0, imagesx($imgEscudo2), imagesy($imgEscudo2));
        
        desenharTexto($im, "$time1", $xBase + 70, $yTop + 50, $branco, 14);
        desenharTexto($im, "$time2", $xBase + 450, $yTop + 50, $branco, 14);
        desenharTexto($im, $hora, 345, $yTop + 0, $branco, 12);
        
        $fontSize = 12; $fontFile = $fontLiga;
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $canais);
        $textWidth = $bbox[2] - $bbox[0];
        $centerX = ($width / 2) - ($textWidth / 2);
        desenharTexto($im, $canais, $centerX, $yTop + 90, $branco, $fontSize, 0);
        
        // Limpar memória apenas se não estiver no cache
        if (!isset($GLOBALS['imageCache'][md5($escudo1_url . $tamEscudo)])) imagedestroy($imgEscudo1);
        if (!isset($GLOBALS['imageCache'][md5($escudo2_url . $tamEscudo)])) imagedestroy($imgEscudo2);
        
        $yAtual += $heightPorJogo;
    }
    
    // Logo das ligas
    if ($logoLiga === null) {
        //$ligas_url = 'https://i.ibb.co/ycxpN2rc/Rodape-liga-720.png';
        //$logoLiga = @imagecreatefrompng($ligas_url);
    }
    
    if ($logoLiga) {
        imagecopy($im, $logoLiga, 40, 870, 0, 0, imagesx($logoLiga), imagesy($logoLiga));
    }
    
    $fonteTitulo = __DIR__ . '/../fonts/BebasNeue-Regular.ttf';
    $fonteData = __DIR__ . '/../fonts/RobotoCondensed-VariableFont_wght.ttf';
    $corBranco = imagecolorallocate($im, 255, 255, 255);
    $titulo1 = "DESTAQUES DE HOJE";
    
    setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR.UTF-8', 'pt_BR', 'portuguese');
    $dataTexto = mb_strtoupper(strftime('%A - %d de %B'), 'UTF-8');
    
    $xTitulo1 = centralizarTextoX($width, 36, $fonteTitulo, $titulo1);
    $xData = centralizarTextoX($width, 17, $fonteData, $dataTexto);
    
    imagettftext($im, 36, 0, $xTitulo1, 65, $corBranco, $fonteTitulo, $titulo1);
    imagettftext($im, 17, 0, $xData, 90, $corBranco, $fonteData, $dataTexto);
    
    // Logo do usuário
    $logoUsuario = loadUserImage($userId, 'logo_banner_2');
    if ($logoUsuario) {
        $logoLarguraDesejada = 150;
        $logoPosX = 6; $logoPosY = 10;
        $logoWidthOriginal = imagesx($logoUsuario);
        $logoHeightOriginal = imagesy($logoUsuario);
        $logoHeight = (int)($logoHeightOriginal * ($logoLarguraDesejada / $logoWidthOriginal));
        $logoRedimensionada = imagecreatetruecolor($logoLarguraDesejada, $logoHeight);
        imagealphablending($logoRedimensionada, false); imagesavealpha($logoRedimensionada, true);
        imagecopyresampled($logoRedimensionada, $logoUsuario, 0, 0, 0, 0, $logoLarguraDesejada, $logoHeight, $logoWidthOriginal, $logoHeightOriginal);
        imagecopy($im, $logoRedimensionada, $logoPosX, $logoPosY, 0, 0, $logoLarguraDesejada, $logoHeight);
        imagedestroy($logoUsuario); imagedestroy($logoRedimensionada);
    }
    
    return $im;
}

/**
 * Gera banner modelo 3 (gerar_fut_3.php)
 */
function _gerarBannerModel3($userId, $jogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga) {
    // Criar imagem
    $im = imagecreatetruecolor($width, $height);
    $preto = imagecolorallocate($im, 0, 0, 0);
    $branco = imagecolorallocate($im, 255, 255, 255);
    
    // 1. FUNDO DO BANNER: Lógica original mantida, carrega por UserID
    $fundo = loadUserImage($userId, 'background_banner_3');
    if ($fundo) {
        imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
        imagedestroy($fundo);
    } else {
        imagefill($im, 0, 0, $branco);
    }
    
    // 2. CARD DE JOGO: Lógica original restaurada, carrega por UserID com fallback
    static $fundoJogo = null;
    if ($fundoJogo === null) {
        $fundoJogo = loadUserImage($userId, 'card_banner_3');
        if (!$fundoJogo) {
            // Fallback para imagem padrão do primeiro script
            $fundoJogoPath = __DIR__ . '/../wtec/card/card_banner_3.png';
            $fundoJogo = file_exists($fundoJogoPath) ? imagecreatefrompng($fundoJogoPath) : false;
        }
    }
    
    // Posição Y inicial do novo layout
    $yAtual = $padding + 320;

    // Loop com a NOVA lógica de layout
    foreach ($grupoJogos as $idx) {
        if (!isset($jogos[$idx])) continue;

        $jogo = $jogos[$idx];
        if ($fundoJogo) {
            imagecopyresampled($im, $fundoJogo, 15, $yAtual, 0, 0, $width - ($padding * 2), $heightPorJogo - 8, imagesx($fundoJogo), imagesy($fundoJogo));
        }

        $time1 = $jogo['time1'] ?? 'Time 1';
        $time2 = $jogo['time2'] ?? 'Time 2';
        $liga = $jogo['competicao'] ?? 'Liga';
        $hora = $jogo['horario'] ?? '';
        $encoding = 'UTF-8';
        
        $escudo1_url = LOGO_OVERRIDES[$time1] ?? ($jogo['img_time1_url'] ?? '');
        $escudo2_url = LOGO_OVERRIDES[$time2] ?? ($jogo['img_time2_url'] ?? '');
        
        $imgEscudo1 = carregarEscudo($time1, $escudo1_url, 115);
        $imgEscudo2 = carregarEscudo($time2, $escudo2_url, 115);
        
        $yTop = $yAtual + 20;
        if($imgEscudo1) imagecopy($im, $imgEscudo1, 85, $yTop + 35, 0, 0, imagesx($imgEscudo1), imagesy($imgEscudo1));
        if($imgEscudo2) imagecopy($im, $imgEscudo2, 880, $yTop + 35, 0, 0, imagesx($imgEscudo2), imagesy($imgEscudo2));
        
        // Gerenciamento de memória respeitando o cache (lógica original)
        if($imgEscudo1 && !isset($GLOBALS['imageCache'][md5($escudo1_url . 115)])) imagedestroy($imgEscudo1);
        if($imgEscudo2 && !isset($GLOBALS['imageCache'][md5($escudo2_url . 115)])) imagedestroy($imgEscudo2);
        
        // Seção de texto dos times com o novo layout
        $fonteNomes = __DIR__ . '/../fonts/CalSans-Regular.ttf';
        $corNomes = $branco;
        $tamanhoNomes = 22;
        $limiteCaracteresNome = 14;

        $nomeLimpo1 = removerAcentos(mb_strtoupper($time1, $encoding));
        $nomeLimpo2 = removerAcentos(mb_strtoupper($time2, $encoding));
        $textoFinal1 = limitarTexto($nomeLimpo1, $limiteCaracteresNome);
        $textoFinal2 = limitarTexto($nomeLimpo2, $limiteCaracteresNome);

        $eixoCentralColuna = 345;
        $eixoCentralColuna2 = 745;
        
        $bbox1 = imagettfbbox($tamanhoNomes, 0, $fonteNomes, $textoFinal1);
        $xPos1 = $eixoCentralColuna - (($bbox1[2] - $bbox1[0]) / 2);
        desenharTexto($im, $textoFinal1, $xPos1, $yTop + 80, $corNomes, $tamanhoNomes);
        
        $bbox2 = imagettfbbox($tamanhoNomes, 0, $fonteNomes, $textoFinal2);
        $xPos2 = $eixoCentralColuna2 - (($bbox2[2] - $bbox2[0]) / 2);
        desenharTexto($im, $textoFinal2, $xPos2, $yTop + 80, $corNomes, $tamanhoNomes);

        // Seção Liga e Hora com o novo layout
        $textoLiga = removerAcentos(mb_strtoupper($liga, $encoding));
        desenharTexto($im, $textoLiga, centralizarTextoX($width, 20, $fonteNomes, $textoLiga), $yTop + 35, $preto, 20);
        desenharTexto($im, $hora, centralizarTextoX($width, 38, $fonteNomes, $hora), $yTop + 65, $branco, 38);

        // Seção Canais com o novo layout
        $canaisDoJogo = array_slice($jogo['canais'] ?? [], 0, 4);
        if (!empty($canaisDoJogo)) {
            $logosParaDesenhar = [];
            $larguraTotalBloco = 0; $espacoEntreLogos = 5;
            foreach ($canaisDoJogo as $canal) {
                if (!empty($canal['img_url']) && ($logoCanal = carregarLogoCanalComAlturaFixa($canal['img_url'], 55))) {
                    $logosParaDesenhar[] = $logoCanal;
                    $larguraTotalBloco += imagesx($logoCanal);
                }
            }
            if (!empty($logosParaDesenhar)) {
                $larguraTotalBloco += (count($logosParaDesenhar) - 1) * $espacoEntreLogos;
                $xAtual = (($width - $larguraTotalBloco) / 2);
                foreach ($logosParaDesenhar as $logo) {
                    imagecopy($im, $logo, (int)$xAtual, (int)($yTop + 128), 0, 0, imagesx($logo), imagesy($logo));
                    $xAtual += imagesx($logo) + $espacoEntreLogos;
                    imagedestroy($logo);
                }
            }
        }

        $yAtual += $heightPorJogo;
    }

    // Cabeçalho com o novo layout
    $fonteTitulo = __DIR__ . '/../fonts/BebasNeue-Regular.ttf';
    $corBranco = imagecolorallocate($im, 255, 255, 255);
    setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR.UTF-8', 'pt_BR', 'portuguese');

    $tituloDiaSemana = mb_strtoupper(strftime('%A', time()));
    $tamanhoFonteTitulo = 55;
    $y_texto_titulo = 105;
    $padding_titulo = 10;
    $corFundoAzul = imagecolorallocate($im, 29, 78, 216);
    $raioCanto = 18;
    
    $x_texto_titulo = centralizarTextoX($width, $tamanhoFonteTitulo, $fonteTitulo, $tituloDiaSemana);
    $bbox_titulo = imagettfbbox($tamanhoFonteTitulo, 0, $fonteTitulo, $tituloDiaSemana);
    $x1 = $x_texto_titulo + $bbox_titulo[0] - $padding_titulo;
    $y1 = $y_texto_titulo + $bbox_titulo[7] - $padding_titulo;
    $x2 = $x_texto_titulo + $bbox_titulo[2] + $padding_titulo;
    $y2 = $y_texto_titulo + $bbox_titulo[1] + $padding_titulo;
    
    imagefilledroundedrect($im, $x1, $y1, $x2, $y2, $raioCanto, $corFundoAzul);
    imagettftext($im, $tamanhoFonteTitulo, 0, $x_texto_titulo, $y_texto_titulo, $corBranco, $fonteTitulo, $tituloDiaSemana);
    
    $dataTexto = mb_strtoupper(strftime('%d de %B'));
    imagettftext($im, 55, 0, centralizarTextoX($width, 55, $fonteTitulo, $dataTexto), 200, $corBranco, $fonteTitulo, $dataTexto);

    // 3. LOGO DO USUÁRIO: Lógica original restaurada, carrega por UserID
    $logoUsuario = loadUserImage($userId, 'logo_banner_3');
    if ($logoUsuario) {
        $w = imagesx($logoUsuario); $h = imagesy($logoUsuario);
        if ($w > 0 && $h > 0) {
            // Usando a lógica de redimensionamento original
            $scale = min(350 / $w, 350 / $h, 1.0);
            $newW = (int)($w * $scale); $newH = (int)($h * $scale);
            $logoRedimensionada = imagecreatetruecolor($newW, $newH);
            imagealphablending($logoRedimensionada, false); imagesavealpha($logoRedimensionada, true);
            imagecopyresampled($logoRedimensionada, $logoUsuario, 0, 0, 0, 0, $newW, $newH, $w, $h);
            // Posição do novo layout
            imagecopy($im, $logoRedimensionada, 30, 20, 0, 0, $newW, $newH);
            imagedestroy($logoRedimensionada);
        }
        imagedestroy($logoUsuario);
    }
    
    return $im;
}

/**
 * Gera banner modelo 4 (gerar_fut_4.php) - Novo tema
 */
function _gerarBannerModel4($userId, $jogos, $grupoJogos, $width, $height, $padding, $heightPorJogo, $fontLiga) {
    // --- INICIALIZAÇÃO E CONFIGURAÇÃO DO NOVO LAYOUT ---
    $im = imagecreatetruecolor($width, $height);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    // Configurações de fonte
    $teams_font_size = 50;
    $comp_font_size = 28;
    $rodape_font_size = 22;

    // Configurações de tamanho de logo
    $logo_teams_size = 160;
    $logo_canal_size = 70;
    $logo_vs_size = 60;

    // Caminhos (usando a estrutura de diretórios do sistema original)
    $font_path = __DIR__ . '/../fonts/BebasNeue-Regular.ttf';
    $vs_icon_path = __DIR__ . '/../imgelementos/vs.png';

    // Cores do novo layout
    $card_color = ['r' => 0, 'g' => 0, 'b' => 0];
    $card_text_color = imagecolorallocate($im, 255, 255, 255);
    $champ_text_color = imagecolorallocate($im, 67, 195, 68);

    // --- CARREGAMENTO DE IMAGENS PERSONALIZADAS (LÓGICA ORIGINAL) ---

    // Fundo do Banner (UserID)
    $fundo = loadUserImage($userId, 'background_banner_4');
    if ($fundo) {
        imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
        imagedestroy($fundo);
    } else {
        $bg_color = imagecolorallocate($im, 30, 30, 30); // Fundo padrão do novo layout
        imagefilledrectangle($im, 0, 0, $width, $height, $bg_color);
    }

    // Logo Superior (UserID)
    $logo_superior = loadUserImage($userId, 'logo_banner_4');
    if ($logo_superior) {
        $logo_superior_width = 500; // Largura máxima do novo layout
        $logo_superior_height = 200; // Altura máxima do novo layout

        $orig_width = imagesx($logo_superior);
        $orig_height = imagesy($logo_superior);
        
        if ($orig_width > 0 && $orig_height > 0) {
            $ratio = $orig_width / $orig_height;
            if ($logo_superior_width / $logo_superior_height > $ratio) {
                $logo_superior_width = $logo_superior_height * $ratio;
            } else {
                $logo_superior_height = $logo_superior_width / $ratio;
            }
            
            $logo_x = 20; $logo_y = 20;
            imagecopyresampled($im, $logo_superior, $logo_x, $logo_y, 0, 0, 
                               $logo_superior_width, $logo_superior_height, 
                               $orig_width, $orig_height);
        }
        imagedestroy($logo_superior);
    }

    // --- CABEÇALHO (LÓGICA DO NOVO LAYOUT) ---
    $timeStamp = time(); // Para os jogos de hoje
    $diasPT = ["DOMINGO", "SEGUNDA", "TERÇA", "QUARTA", "QUINTA", "SEXTA", "SÁBADO"];
    $mesesPT = ["JAN", "FEV", "MAR", "ABR", "MAI", "JUN", "JUL", "AGO", "SET", "OUT", "NOV", "DEZ"];
    
    $textoDiaMes = sprintf('%02d %s', date('d', $timeStamp), $mesesPT[date('n', $timeStamp) - 1]);
    $diaNome = $diasPT[date('w', $timeStamp)];
    
    $y_text = 150;
    $bbox_diaMes = imagettfbbox(85, 0, $font_path, $textoDiaMes);
    imagettftext($im, 85, 0, ($width - ($bbox_diaMes[2] - $bbox_diaMes[0])) / 2, $y_text, $card_text_color, $font_path, $textoDiaMes);

    $bboxDia = imagettfbbox(50, 0, $font_path, $diaNome);
    $textWidthDia = $bboxDia[2] - $bboxDia[0];
    $rect_width = $textWidthDia + 80;
    $rect_height = ($bboxDia[1] - $bboxDia[7]) + 40;
    $x_rect = ($width - $rect_width) / 2;
    $y_rect = $y_text + 30;
    imagefilledrectangle($im, $x_rect, $y_rect, $x_rect + $rect_width, $y_rect + $rect_height, $champ_text_color);
    imagettftext($im, 50, 0, $x_rect + 40, $y_rect + 20 + ($bboxDia[1] - $bboxDia[7]), $card_text_color, $font_path, $diaNome);
    
    $current_y = $y_rect + $rect_height + 40;

    // --- LOOP DE JOGOS (FUSÃO DAS LÓGICAS) ---
    $card_height = 220;
    $card_x = 50;
    $card_width = $width - 100;
    $spacing = 20;

    foreach ($grupoJogos as $idx) {
        if (!isset($jogos[$idx])) continue;
        $jogo = $jogos[$idx];

        // Card com fundo semi-transparente (lógica do novo layout)
        $adm_card_bg_color = imagecolorallocatealpha($im, $card_color['r'], $card_color['g'], $card_color['b'], 60);
        imagefilledrectangle($im, $card_x, $current_y, $card_x + $card_width, $current_y + $card_height, $adm_card_bg_color);

        // Texto da competição e horário
        $competicao = strtoupper($jogo['competicao'] ?? "CAMPEONATO");
        $horario = $jogo['horario'] ?? "00:00";
        $line_top = "$competicao - $horario";
        $bbox_comp = imagettfbbox($comp_font_size, 0, $font_path, $line_top);
        $x_comp = $card_x + ($card_width - ($bbox_comp[2] - $bbox_comp[0])) / 2;
        imagettftext($im, $comp_font_size, 0, $x_comp, $current_y + 40, $champ_text_color, $font_path, $line_top);

        // --- Logos e Nomes dos Times (Lógica de carregamento original + layout novo) ---
        
        // Obter URLs e limpar nomes
        $time1_raw = $jogo['time1'] ?? 'Time 1';
        $time2_raw = $jogo['time2'] ?? 'Time 2';
        $escudo1_url = LOGO_OVERRIDES[$time1_raw] ?? ($jogo['img_time1_url'] ?? '');
        $escudo2_url = LOGO_OVERRIDES[$time2_raw] ?? ($jogo['img_time2_url'] ?? '');

        // Carregar escudos com a função de cache do sistema
        $logo1 = carregarEscudo($time1_raw, $escudo1_url, $logo_teams_size);
        $logo2 = carregarEscudo($time2_raw, $escudo2_url, $logo_teams_size);

        // Posicionar escudos
        if ($logo1) {
            imagecopyresampled($im, $logo1, $card_x + 10, $current_y + $card_height - $logo_teams_size - 10, 0, 0, $logo_teams_size, $logo_teams_size, imagesx($logo1), imagesy($logo1));
        }
        if ($logo2) {
            imagecopyresampled($im, $logo2, $card_x + $card_width - $logo_teams_size - 10, $current_y + $card_height - $logo_teams_size - 10, 0, 0, $logo_teams_size, $logo_teams_size, imagesx($logo2), imagesy($logo2));
        }
        
        // Lógica de centralização dos nomes + VS
        $gap = 20;
        $vs_icon = file_exists($vs_icon_path) ? imagecreatefrompng($vs_icon_path) : null;
        $w_vs = $vs_icon ? $logo_vs_size : (imagettfbbox($teams_font_size, 0, $font_path, 'VS')[2] - imagettfbbox($teams_font_size, 0, $font_path, 'VS')[0]);
        
        $max_text_area_width = $card_width - (($logo_teams_size + 5) * 2) - ($gap * 2);
        $max_team_name_width = ($max_text_area_width - $w_vs) / 2;

        $time1 = truncateText(strtoupper($time1_raw), $teams_font_size, $font_path, $max_team_name_width);
        $time2 = truncateText(strtoupper($time2_raw), $teams_font_size, $font_path, $max_team_name_width);
        
        $w_time1 = imagettfbbox($teams_font_size, 0, $font_path, $time1)[2] - imagettfbbox($teams_font_size, 0, $font_path, $time1)[0];
        $w_time2 = imagettfbbox($teams_font_size, 0, $font_path, $time2)[2] - imagettfbbox($teams_font_size, 0, $font_path, $time2)[0];
        
        $total_w = $w_time1 + $gap + $w_vs + $gap + $w_time2;
        $start_x = $card_x + ($card_width / 2) - ($total_w / 2);
        $names_y = $current_y + ($card_height / 2) + 15;
        
        imagettftext($im, $teams_font_size, 0, $start_x, $names_y, $card_text_color, $font_path, $time1);
        $x_vs = $start_x + $w_time1 + $gap;
        if ($vs_icon) {
            imagecopyresampled($im, $vs_icon, $x_vs, $names_y - ($logo_vs_size / 2) - 25, 0, 0, $logo_vs_size, $logo_vs_size, imagesx($vs_icon), imagesy($vs_icon));
        } else {
            imagettftext($im, $teams_font_size, 0, $x_vs, $names_y, $card_text_color, $font_path, "VS");
        }
        imagettftext($im, $teams_font_size, 0, $x_vs + $w_vs + $gap, $names_y, $card_text_color, $font_path, $time2);

        // Logos dos canais
        if (!empty($jogo['canais']) && is_array($jogo['canais'])) {
            $num_canais = count($jogo['canais']);
            $total_canais_w = ($num_canais * $logo_canal_size) + (10 * ($num_canais - 1));
            $start_canais_x = $card_x + ($card_width - $total_canais_w) / 2;
            $canais_y = $current_y + $card_height - $logo_canal_size - 10;
            
            foreach ($jogo['canais'] as $idx_c => $canal) {
                $canal_url = trim($canal['img_url']);
                $canal_img = carregarEscudo('canal_' . $idx_c, $canal_url, $logo_canal_size); // Usando a função de cache
                if ($canal_img) {
                    $cx = $start_canais_x + ($idx_c * ($logo_canal_size + 10));
                    imagecopyresampled($im, $canal_img, $cx, $canais_y, 0, 0, $logo_canal_size, $logo_canal_size, imagesx($canal_img), imagesy($canal_img));
                    if (!isset($GLOBALS['imageCache'][md5($canal_url . $logo_canal_size)])) {
                        imagedestroy($canal_img);
                    }
                }
            }
        }

        // --- Gerenciamento de Memória CORRETO ---
        if ($logo1 && !isset($GLOBALS['imageCache'][md5($escudo1_url . $logo_teams_size)])) imagedestroy($logo1);
        if ($logo2 && !isset($GLOBALS['imageCache'][md5($escudo2_url . $logo_teams_size)])) imagedestroy($logo2);
        if ($vs_icon) imagedestroy($vs_icon);

        $current_y += $card_height + $spacing;
    }

    // --- RODAPÉ (LÓGICA DO NOVO LAYOUT) ---
    $rodape_text = "Assine já e acompanhe os principais campeonatos do mundo!";
    $bbox_rodape = imagettfbbox($rodape_font_size, 0, $font_path, $rodape_text);
    $x_rodape = ($width - ($bbox_rodape[2] - $bbox_rodape[0])) / 2;
    imagettftext($im, $rodape_font_size, 0, $x_rodape, $height - 30, $card_text_color, $font_path, $rodape_text);

    return $im;
}
?>