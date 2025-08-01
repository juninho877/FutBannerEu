<?php
session_start();
if (!isset($_SESSION["usuario"]) && !isset($_SESSION["temp_user_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

require_once 'classes/MercadoPagoPayment.php';
require_once 'classes/CreditTransaction.php';

// Verificar se os parâmetros necessários foram fornecidos
if (!isset($_POST['payment_id']) || empty($_POST['payment_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID do pagamento não fornecido']);
    exit();
}

$paymentId = $_POST['payment_id'];
$months = isset($_POST['months']) ? intval($_POST['months']) : 1;
$userId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : $_SESSION["temp_user_id"];

try {
    $mercadoPagoPayment = new MercadoPagoPayment();
    $creditTransaction = new CreditTransaction();
    
    // Verificar status do pagamento
    $result = $mercadoPagoPayment->checkPaymentStatus($paymentId);
    
    if (!$result['success']) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Erro ao verificar status do pagamento'
        ]);
        exit();
    }
    
    $response = [
        'success' => true,
        'status' => $result['status'],
        'message' => '',
        'is_processed' => $result['is_processed'] ?? false
    ];
    
    // Processar com base no status
    if ($result['status'] === 'approved') {
        // Verificar se o pagamento já foi processado
        if ($result['is_processed']) {
            $response['message'] = "Pagamento já foi processado anteriormente. Sua assinatura já foi renovada.";
        } else {
            // O processamento real (renovação da assinatura) já foi feito no método checkPaymentStatus
            // Aqui apenas informamos ao usuário o que aconteceu
            $newExpiryDate = date('d/m/Y', strtotime("+" . $months . " months"));
            $response['message'] = "Pagamento confirmado! Sua assinatura foi renovada até {$newExpiryDate}.";
            $response['should_clear_session'] = true;
        }
    } elseif ($result['status'] === 'pending') {
        $response['message'] = "Pagamento pendente. Aguardando confirmação do Mercado Pago.";
    } elseif ($result['status'] === 'rejected' || $result['status'] === 'cancelled') {
        $response['message'] = "Pagamento " . ($result['status'] === 'rejected' ? 'rejeitado' : 'cancelado') . ". Por favor, tente novamente com outro método de pagamento.";
        $response['should_clear_session'] = true;
    } else {
        $response['message'] = "Status do pagamento: " . $result['status'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar pagamento: ' . $e->getMessage()
    ]);
    exit();
}
?>