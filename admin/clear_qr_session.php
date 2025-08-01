<?php
session_start();

// Limpar dados de pagamento da sessão
unset($_SESSION['payment_qr_code']);
unset($_SESSION['payment_created_at']);
unset($_SESSION['payment_id']);
unset($_SESSION['payment_months']);
unset($_SESSION['payment_amount']);

// Responder com sucesso se for uma requisição AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
}
?>