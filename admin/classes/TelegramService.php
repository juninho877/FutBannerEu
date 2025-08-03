<?php
require_once 'TelegramSettings.php';
require_once __DIR__ . '/../includes/banner_functions.php';

class TelegramService
{
    /**
     * @var TelegramSettingsService
     * A dependência para o serviço de configurações do Telegram.
     */
    private $telegramSettings;

    /**
     * Construtor da classe.
     */
    public function __construct()
    {
        $this->telegramSettings = new TelegramSettings();
    }

    /**
     * Envia um álbum de fotos para um chat do Telegram.
     * Se houver mais de 10 imagens, elas são divididas em múltiplos álbuns.
     *
     * @param int $userId O ID do usuário associado às configurações do Telegram.
     * @param array $imagePaths Um array de caminhos de arquivo para as imagens.
     * @param string $caption A legenda para a primeira imagem do primeiro álbum.
     * @return array Um array indicando o sucesso da operação e uma mensagem.
     */
    public function sendImageAlbum(string $userId, array $imagePaths, string $caption = ''): array
    {
        try {
            // 1. Validar e obter configurações do usuário.
            $settings = $this->telegramSettings->getSettings($userId);
            if (empty($settings['bot_token']) || empty($settings['chat_id'])) {
                return ['success' => false, 'message' => 'Configurações do Telegram não encontradas. Configure primeiro em Telegram > Configurações.'];
            }

            $botToken = $settings['bot_token'];
            $chatId = $settings['chat_id'];

            // 2. Validar se há imagens para enviar.
            if (empty($imagePaths)) {
                return ['success' => false, 'message' => 'Nenhuma imagem fornecida para envio.'];
            }

            // 3. Se houver apenas uma imagem, enviar como foto simples e retornar.
            if (count($imagePaths) === 1) {
                return $this->sendSinglePhoto($botToken, $chatId, $imagePaths[0], $caption);
            }

            // 4. Dividir as imagens em grupos de no máximo 10 para criar múltiplos álbuns.
            $imageChunks = array_chunk($imagePaths, 10);
            $results = [];

            // 5. Iterar sobre cada grupo (álbum) de imagens.
            foreach ($imageChunks as $chunkIndex => $imageChunk) {
                $media = [];
                $validImagesInChunk = [];
                
                // Preparar a mídia para o álbum atual, validando cada arquivo.
                foreach ($imageChunk as $imageIndex => $imagePath) {
                    if (!file_exists($imagePath)) {
                        error_log("Erro: Arquivo não encontrado - " . $imagePath);
                        continue;
                    }

                    $validImagesInChunk[] = $imagePath;
                    $currentCaption = '';

                    // A legenda é adicionada apenas à primeira imagem do primeiro álbum.
                    if ($chunkIndex === 0 && $imageIndex === 0 && !empty($caption)) {
                        $currentCaption = $caption;
                    }
                    
                    $media[] = [
                        'type' => 'photo',
                        'media' => 'attach://photo' . $imageIndex,
                        'caption' => $currentCaption
                    ];
                }

                // Se o álbum atual não tiver imagens válidas, continuar para o próximo.
                if (empty($media)) {
                    $results[] = ['success' => false, 'message' => 'Nenhuma imagem válida encontrada no chunk ' . ($chunkIndex + 1)];
                    continue;
                }

                // 6. Enviar o álbum atual para o Telegram.
                $response = $this->sendMediaGroup($botToken, $chatId, $validImagesInChunk, $media);
                $results[] = $response;
            }

            // 7. Consolidar os resultados de todos os álbuns.
            $allSuccess = true;
            $messages = [];
            foreach ($results as $result) {
                if (!$result['success']) {
                    $allSuccess = false;
                }
                $messages[] = $result['message'];
            }
            
            return [
                'success' => $allSuccess,
                'message' => implode(' | ', $messages)
            ];

        } catch (Exception $e) {
            error_log("Erro no TelegramService::sendImageAlbum: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()];
        }
    }
    
    /**
     * Enviar uma única foto
     */
    private function sendSinglePhoto($botToken, $chatId, $imagePath, $caption)
    {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
            
            // Verificar se o arquivo existe e é legível
            if (!file_exists($imagePath) || !is_readable($imagePath)) {
                error_log("Arquivo não existe ou não é legível: " . $imagePath);
                return ['success' => false, 'message' => 'Arquivo não existe ou não é legível: ' . $imagePath];
            }
            
            // Verificar tamanho do arquivo
            $fileSize = filesize($imagePath);
            if ($fileSize === false) {
                error_log("Não foi possível obter o tamanho do arquivo: " . $imagePath);
                return ['success' => false, 'message' => 'Não foi possível obter o tamanho do arquivo'];
            }
            
            if ($fileSize > 10 * 1024 * 1024) { // 10MB
                error_log("Arquivo muito grande (> 10MB): " . $imagePath . " - " . $fileSize . " bytes");
                return ['success' => false, 'message' => 'Arquivo muito grande (> 10MB)'];
            }
            
            // Criar CURLFile
            $curlFile = new CURLFile($imagePath);
            if (!$curlFile) {
                error_log("Falha ao criar CURLFile para: " . $imagePath);
                return ['success' => false, 'message' => 'Falha ao criar CURLFile'];
            }
            
            $postFields = [
                'chat_id' => $chatId,
                'photo' => $curlFile,
                'caption' => $caption,
                'parse_mode' => 'HTML'
            ];
            
            // Inicializar cURL
            $ch = curl_init();
            if (!$ch) {
                error_log("Falha ao inicializar cURL");
                return ['success' => false, 'message' => 'Falha ao inicializar cURL'];
            }
            
            // Configurar opções do cURL
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'FutBanner/1.0',
                CURLOPT_VERBOSE => false,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            
            // Executar cURL
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            
            if ($response === false) {
                curl_close($ch);
                error_log("Erro cURL ao enviar foto: " . $error . " (código: " . $errno . ")");
                return ['success' => false, 'message' => 'Erro na conexão com o Telegram: ' . $error . ' (código: ' . $errno . ')'];
            }
            
            curl_close($ch);
            
            // Decodificar resposta JSON
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Erro ao decodificar resposta JSON: " . json_last_error_msg() . "\nResposta: " . $response);
                return ['success' => false, 'message' => 'Erro ao decodificar resposta do Telegram: ' . json_last_error_msg()];
            }
            
            if (!isset($data['ok']) || $data['ok'] !== true) {
                error_log("Erro da API do Telegram: " . ($data['description'] ?? 'Erro desconhecido') . "\nCódigo: " . $httpCode);
                return ['success' => false, 'message' => 'Erro do Telegram: ' . ($data['description'] ?? 'Erro desconhecido')];
            }
            
            return ['success' => true, 'message' => 'Imagem enviada com sucesso para o Telegram'];
            
        } catch (Exception $e) {
            error_log("Exceção ao enviar foto: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Erro no envio: ' . $e->getMessage()];
        }
    }
    
    /**
     * Enviar grupo de mídia (álbum)
     */
    private function sendMediaGroup($botToken, $chatId, $imagePaths, $media)
    {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendMediaGroup";
            
            $postFields = [
                'chat_id' => $chatId,
                'media' => json_encode($media)
            ];
            
            // Adicionar arquivos
            foreach ($imagePaths as $index => $imagePath) {
                if (file_exists($imagePath) && is_readable($imagePath)) {
                    $postFields['photo' . $index] = new CURLFile($imagePath);
                } else {
                    error_log("Arquivo não existe ou não é legível: " . $imagePath);
                }
            }
            
            $ch = curl_init();
            if (!$ch) {
                error_log("Falha ao inicializar cURL para álbum");
                return ['success' => false, 'message' => 'Falha ao inicializar cURL para álbum'];
            }
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60, // Mais tempo para múltiplas imagens
                CURLOPT_USERAGENT => 'FutBanner/1.0',
                CURLOPT_VERBOSE => false,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            
            if ($response === false) {
                curl_close($ch);
                error_log("Erro cURL ao enviar álbum: " . $error . " (código: " . $errno . ")");
                return ['success' => false, 'message' => 'Erro na conexão com o Telegram: ' . $error . ' (código: ' . $errno . ')'];
            }
            
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Erro ao decodificar resposta JSON do álbum: " . json_last_error_msg() . "\nResposta: " . $response);
                return ['success' => false, 'message' => 'Erro ao decodificar resposta do Telegram: ' . json_last_error_msg()];
            }
            
            if (!isset($data['ok']) || $data['ok'] !== true) {
                error_log("Erro da API do Telegram (álbum): " . ($data['description'] ?? 'Erro desconhecido') . "\nCódigo: " . $httpCode);
                return ['success' => false, 'message' => 'Erro do Telegram: ' . ($data['description'] ?? 'Erro desconhecido')];
            }
            
            return [
                'success' => true,
                'message' => 'Álbum com ' . count($imagePaths) . ' imagens enviado com sucesso para o Telegram',
                'sent_count' => count($imagePaths)
            ];
            
        } catch (Exception $e) {
            error_log("Exceção ao enviar álbum: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Erro no envio do álbum: ' . $e->getMessage()];
        }
    }
    
    /**
     * Gerar banners e enviar para o Telegram
     * @param int $userId ID do usuário
     * @param string $bannerType Tipo de banner (football_1, football_2, football_3)
     * @param array $jogos Array com dados dos jogos
     * @return array Resultado da operação
     */
    public function generateAndSendBanners($userId, $bannerType, $jogos)
    {
        try {
            if (empty($jogos)) {
                return ['success' => false, 'message' => 'Nenhum jogo disponível para gerar banners'];
            }
            
            // Determinar modelo de banner baseado no tipo
            $bannerModel = 1; // Padrão
            switch ($bannerType) {
                case 'football_1':
                    $bannerModel = 1;
                    break;
                case 'football_2':
                    $bannerModel = 2;
                    break;
                case 'football_3':
                    $bannerModel = 3;
                    break;
                case 'football_4':
                    $bannerModel = 4;
                    break;
                default:
                    return ['success' => false, 'message' => 'Tipo de banner inválido'];
            }
            
            // Dividir jogos em grupos
            $jogosPorBanner = 5;
            $gruposDeJogos = array_chunk(array_keys($jogos), $jogosPorBanner);
            
            $imagePaths = [];
            $tempFiles = [];
            
            // Gerar cada banner
            foreach ($gruposDeJogos as $index => $grupoJogos) {
                try {
                    // Usar a função para gerar o recurso de imagem diretamente
                    $imageResource = generateFootballBannerResource($userId, $bannerModel, $index, $jogos);
                    
                    if ($imageResource) {
                        // Salvar em arquivo temporário
                        $tempFile = sys_get_temp_dir() . '/futbanner_telegram_' . uniqid() . '_' . $index . '.png';
                        
                        if (imagepng($imageResource, $tempFile)) {
                            $imagePaths[] = $tempFile;
                            $tempFiles[] = $tempFile;
                        } else {
                            error_log("Falha ao salvar imagem temporária: " . $tempFile);
                        }
                        
                        // Liberar memória
                        imagedestroy($imageResource);
                    } else {
                        error_log("Falha ao gerar recurso de imagem para o grupo " . $index);
                    }
                } catch (Exception $e) {
                    error_log("Exceção ao gerar banner para grupo " . $index . ": " . $e->getMessage());
                }
            }
            
            if (empty($imagePaths)) {
                return ['success' => false, 'message' => 'Erro ao gerar banners. Nenhuma imagem foi criada.'];
            }
            
            // Obter configurações do usuário
            $settings = $this->telegramSettings->getSettings($userId);
            if (!$settings) {
                // Limpar arquivos temporários
                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile)) {
                        @unlink($tempFile);
                    }
                }
                return ['success' => false, 'message' => 'Configurações do Telegram não encontradas para o usuário'];
            }
            
            // Preparar legenda personalizada ou usar padrão
            $caption = "🏆 Banners de Futebol - " . date('d/m/Y') . "\n";
            
            if (!empty($settings['football_message'])) {
                // Substituir variáveis na mensagem personalizada
                $customMessage = $settings['football_message'];
                $data = date('d/m/Y');
                $hora = date('H:i');
                $jogosCount = count($jogos);
                
                $customMessage = str_replace('$data', $data, $customMessage);
                $customMessage = str_replace('$hora', $hora, $customMessage);
                $customMessage = str_replace('$jogos', $jogosCount, $customMessage);
                
                $caption = $customMessage;
            } else {
                // Mensagem padrão
                $caption .= "📊 " . count($jogos) . " jogos de hoje\n";
                $caption .= "🎨 Gerado pelo FutBanner";
            }
            
            // Enviar para o Telegram
            $result = $this->sendImageAlbum($userId, $imagePaths, $caption);
            
            // Limpar arquivos temporários
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Erro em generateAndSendBanners: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Erro ao gerar e enviar banners: ' . $e->getMessage()];
        }
    }
    
    /**
     * Enviar banner de filme/série para o Telegram
     * @param int $userId ID do usuário
     * @param string $bannerPath Caminho do arquivo do banner
     * @param string $contentName Nome do filme ou série
     * @param string $contentType Tipo do conteúdo (filme ou série)
     * @return array Resultado da operação
     */
    public function sendMovieSeriesBanner($userId, $bannerPath, $contentName, $contentType = 'filme')
    {
        try {
            if (!file_exists($bannerPath)) {
                return ['success' => false, 'message' => 'Arquivo do banner não encontrado: ' . $bannerPath];
            }
            
            // Obter configurações do usuário
            $settings = $this->telegramSettings->getSettings($userId);
            if (!$settings) {
                return ['success' => false, 'message' => 'Configurações do Telegram não encontradas. Configure primeiro em Telegram > Configurações.'];
            }
            
            // Preparar legenda personalizada ou usar padrão
            $caption = "🎬 Banner: " . $contentName . "\n";
            
            if (!empty($settings['movie_series_message'])) {
                // Substituir variáveis na mensagem personalizada
                $customMessage = $settings['movie_series_message'];
                $data = date('d/m/Y');
                $hora = date('H:i');
                
                $customMessage = str_replace('$data', $data, $customMessage);
                $customMessage = str_replace('$hora', $hora, $customMessage);
                $customMessage = str_replace('$nomedofilme', $contentName, $customMessage);
                
                $caption = $customMessage;
            } else {
                // Mensagem padrão
                $caption .= "📅 Gerado em: " . date('d/m/Y H:i') . "\n";
                $caption .= "🎨 FutBanner";
            }
            
            // Enviar para o Telegram
            $result = $this->sendSinglePhoto($settings['bot_token'], $settings['chat_id'], $bannerPath, $caption);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Erro em sendMovieSeriesBanner: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Erro ao enviar banner: ' . $e->getMessage()];
        }
    }
}
?>
