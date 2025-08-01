<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

require_once 'classes/MercadoPagoPayment.php';

// Verificar se o ID da preferência foi fornecido
if (!isset($_POST['preference_id']) || empty($_POST['preference_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID da preferência não fornecido']);
    exit();
}

$preferenceId = $_POST['preference_id'];

try {
    $mercadoPagoPayment = new MercadoPagoPayment();
    $result = $mercadoPagoPayment->checkPaymentStatus($preferenceId);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao verificar pagamento: ' . $e->getMessage()
    ]);
    exit();
}
?>