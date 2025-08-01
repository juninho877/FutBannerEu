<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

// Armazenar mensagem de sucesso na sessão
if (isset($_POST['message']) && !empty($_POST['message'])) {
    $_SESSION['payment_success'] = true;
    $_SESSION['payment_message'] = $_POST['message'];
    
    // Limpar dados do pagamento
    unset($_SESSION['payment_qr_code']);
    unset($_SESSION['payment_created_at']);
    unset($_SESSION['payment_id']);
    unset($_SESSION['payment_months']);
    unset($_SESSION['payment_amount']);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Mensagem não fornecida']);
}
?>