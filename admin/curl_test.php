<?php
/**
 * Script para testar a funcionalidade do cURL
 * Este script ajuda a diagnosticar problemas com a extensão cURL
 */

// Definir cabeçalhos para evitar cache
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Verificar se cURL está disponível
$curlEnabled = function_exists('curl_init');

// Informações do ambiente
$phpVersion = phpversion();
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido';
$sslVersion = '';
$curlVersion = '';

if ($curlEnabled) {
    $curlInfo = curl_version();
    $sslVersion = $curlInfo['ssl_version'] ?? 'Desconhecido';
    $curlVersion = $curlInfo['version'] ?? 'Desconhecido';
}

// Testar conexão com a API do Telegram
$telegramApiTest = [
    'success' => false,
    'message' => 'Teste não executado',
    'details' => []
];

if ($curlEnabled) {
    try {
        // URL de teste da API do Telegram
        $url = "https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/getMe";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FutBanner/1.0',
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => $verbose = fopen('php://temp', 'w+')
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        
        // Obter informações detalhadas
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        
        curl_close($ch);
        
        // Analisar resultado
        if ($response === false) {
            $telegramApiTest = [
                'success' => false,
                'message' => "Erro cURL: " . $error,
                'details' => [
                    'info' => $info,
                    'verbose' => $verboseLog
                ]
            ];
        } else {
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $telegramApiTest = [
                    'success' => false,
                    'message' => "Erro ao decodificar resposta JSON: " . json_last_error_msg(),
                    'details' => [
                        'response' => $response,
                        'info' => $info,
                        'verbose' => $verboseLog
                    ]
                ];
            } else {
                // A API retornará erro porque o token é inválido, mas isso é esperado
                // O importante é que conseguimos conectar e receber uma resposta JSON válida
                $telegramApiTest = [
                    'success' => true,
                    'message' => "Conexão com API do Telegram bem-sucedida (resposta esperada: " . ($data['description'] ?? 'N/A') . ")",
                    'details' => [
                        'http_code' => $info['http_code'],
                        'total_time' => $info['total_time'],
                        'response' => $data
                    ]
                ];
            }
        }
    } catch (Exception $e) {
        $telegramApiTest = [
            'success' => false,
            'message' => "Exceção: " . $e->getMessage(),
            'details' => []
        ];
    }
}

// Testar conexão SSL genérica
$sslTest = [
    'success' => false,
    'message' => 'Teste não executado',
    'details' => []
];

if ($curlEnabled) {
    try {
        $url = "https://www.google.com";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_USERAGENT => 'FutBanner/1.0'
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        
        curl_close($ch);
        
        if ($response === false) {
            $sslTest = [
                'success' => false,
                'message' => "Erro SSL: " . $error,
                'details' => $info
            ];
        } else {
            $sslTest = [
                'success' => true,
                'message' => "Conexão SSL bem-sucedida",
                'details' => [
                    'http_code' => $info['http_code'],
                    'total_time' => $info['total_time']
                ]
            ];
        }
    } catch (Exception $e) {
        $sslTest = [
            'success' => false,
            'message' => "Exceção: " . $e->getMessage(),
            'details' => []
        ];
    }
}

// Testar criação de arquivo temporário
$tempFileTest = [
    'success' => false,
    'message' => 'Teste não executado',
    'details' => []
];

try {
    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . '/futbanner_test_' . uniqid() . '.txt';
    
    if (file_put_contents($tempFile, 'Test content')) {
        $tempFileTest = [
            'success' => true,
            'message' => "Arquivo temporário criado com sucesso",
            'details' => [
                'path' => $tempFile,
                'dir' => $tempDir,
                'writable' => is_writable($tempDir)
            ]
        ];
        
        // Limpar o arquivo de teste
        @unlink($tempFile);
    } else {
        $tempFileTest = [
            'success' => false,
            'message' => "Falha ao criar arquivo temporário",
            'details' => [
                'dir' => $tempDir,
                'writable' => is_writable($tempDir),
                'error' => error_get_last()
            ]
        ];
    }
} catch (Exception $e) {
    $tempFileTest = [
        'success' => false,
        'message' => "Exceção: " . $e->getMessage(),
        'details' => []
    ];
}

// Exibir resultados
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de cURL - FutBanner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
            color: #333;
        }
        
        h1, h2, h3 {
            color: #2563eb;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 200px;
        }
        
        .value {
            display: inline-block;
        }
        
        .success {
            color: #16a34a;
        }
        
        .error {
            color: #dc2626;
        }
        
        .warning {
            color: #ca8a04;
        }
        
        .test-result {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
        }
        
        .test-result.success {
            background-color: #f0fdf4;
            border: 1px solid #dcfce7;
            color: #166534;
        }
        
        .test-result.error {
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            color: #991b1b;
        }
        
        .details {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8fafc;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 14px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .back-link:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Diagnóstico de cURL - FutBanner</h1>
        
        <div class="section">
            <h2>Informações do Ambiente</h2>
            
            <div class="info-item">
                <span class="label">Versão do PHP:</span>
                <span class="value"><?php echo $phpVersion; ?></span>
            </div>
            
            <div class="info-item">
                <span class="label">Servidor Web:</span>
                <span class="value"><?php echo $serverSoftware; ?></span>
            </div>
            
            <div class="info-item">
                <span class="label">cURL Habilitado:</span>
                <span class="value <?php echo $curlEnabled ? 'success' : 'error'; ?>">
                    <?php echo $curlEnabled ? 'Sim' : 'Não'; ?>
                </span>
            </div>
            
            <?php if ($curlEnabled): ?>
            <div class="info-item">
                <span class="label">Versão do cURL:</span>
                <span class="value"><?php echo $curlVersion; ?></span>
            </div>
            
            <div class="info-item">
                <span class="label">Versão SSL:</span>
                <span class="value"><?php echo $sslVersion; ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!$curlEnabled): ?>
        <div class="test-result error">
            <h3>⚠️ cURL não está habilitado</h3>
            <p>A extensão cURL é necessária para o funcionamento do envio via Telegram. Entre em contato com o administrador do servidor para habilitar esta extensão.</p>
        </div>
        <?php else: ?>
        
        <div class="section">
            <h2>Teste de Conexão com API do Telegram</h2>
            
            <div class="test-result <?php echo $telegramApiTest['success'] ? 'success' : 'error'; ?>">
                <h3><?php echo $telegramApiTest['success'] ? '✅ Sucesso' : '❌ Falha'; ?></h3>
                <p><?php echo $telegramApiTest['message']; ?></p>
                
                <?php if (!empty($telegramApiTest['details'])): ?>
                <div class="details">
<?php echo print_r($telegramApiTest['details'], true); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="section">
            <h2>Teste de Conexão SSL</h2>
            
            <div class="test-result <?php echo $sslTest['success'] ? 'success' : 'error'; ?>">
                <h3><?php echo $sslTest['success'] ? '✅ Sucesso' : '❌ Falha'; ?></h3>
                <p><?php echo $sslTest['message']; ?></p>
                
                <?php if (!empty($sslTest['details'])): ?>
                <div class="details">
<?php echo print_r($sslTest['details'], true); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; ?>
        
        <div class="section">
            <h2>Teste de Arquivos Temporários</h2>
            
            <div class="test-result <?php echo $tempFileTest['success'] ? 'success' : 'error'; ?>">
                <h3><?php echo $tempFileTest['success'] ? '✅ Sucesso' : '❌ Falha'; ?></h3>
                <p><?php echo $tempFileTest['message']; ?></p>
                
                <?php if (!empty($tempFileTest['details'])): ?>
                <div class="details">
<?php echo print_r($tempFileTest['details'], true); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="section">
            <h2>Recomendações</h2>
            
            <?php if (!$curlEnabled): ?>
            <p class="error">⚠️ A extensão cURL não está habilitada. Solicite ao administrador do servidor para habilitar esta extensão.</p>
            <?php elseif (!$telegramApiTest['success']): ?>
            <p class="warning">⚠️ Não foi possível conectar à API do Telegram. Verifique:</p>
            <ul>
                <li>Se o servidor tem acesso à internet</li>
                <li>Se o firewall permite conexões externas</li>
                <li>Se a configuração SSL está correta</li>
                <li>Se há algum proxy bloqueando a conexão</li>
            </ul>
            <?php elseif (!$sslTest['success']): ?>
            <p class="warning">⚠️ Problemas com a conexão SSL. Verifique:</p>
            <ul>
                <li>Se os certificados CA estão atualizados no servidor</li>
                <li>Se a versão do OpenSSL é recente</li>
                <li>Se há algum proxy ou firewall interferindo</li>
            </ul>
            <?php elseif (!$tempFileTest['success']): ?>
            <p class="warning">⚠️ Problemas com arquivos temporários. Verifique:</p>
            <ul>
                <li>Se o diretório temporário tem permissões de escrita</li>
                <li>Se há espaço em disco suficiente</li>
                <li>Se o caminho temporário está configurado corretamente</li>
            </ul>
            <?php else: ?>
            <p class="success">✅ Todos os testes foram bem-sucedidos! O sistema deve funcionar corretamente.</p>
            <?php endif; ?>
        </div>
        
        <a href="telegram.php" class="back-link">Voltar para Configurações do Telegram</a>
    </div>
</body>
</html>