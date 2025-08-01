<?php
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["role"] !== 'master') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

// Armazenar mensagem de sucesso na sessão
if (isset($_POST['message']) && !empty($_POST['message'])) {
    $_SESSION['credit_payment_success'] = true;
    $_SESSION['credit_payment_message'] = $_POST['message'];
    
    // Limpar dados do pagamento
    unset($_SESSION['credit_payment_qr_code']);
    unset($_SESSION['credit_payment_created_at']);
    unset($_SESSION['credit_payment_id']);
    unset($_SESSION['credit_payment_amount']);
    unset($_SESSION['credit_payment_credits']);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Mensagem não fornecida']);
}
?>