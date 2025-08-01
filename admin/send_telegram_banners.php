<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

require_once 'classes/TelegramService.php';
require_once 'includes/banner_functions.php';

// Verificar se o tipo de banner foi especificado
if (!isset($_POST['banner_type'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tipo de banner não especificado']);
    exit();
}

$bannerType = $_POST['banner_type'];
$userId = $_SESSION['user_id'];

try {
    // Obter dados dos jogos
    $jogos = obterJogosDeHoje();
    
    if (empty($jogos)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nenhum jogo disponível para gerar banners']);
        exit();
    }
    
    // Inicializar serviço do Telegram
    $telegramService = new TelegramService();
    
    // Gerar e enviar banners
    $result = $telegramService->generateAndSendBanners($userId, $bannerType, $jogos);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao enviar banners: ' . $e->getMessage()
    ]);
    exit();
}
?>