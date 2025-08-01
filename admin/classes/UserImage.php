<?php
require_once 'config/database.php';

class UserImage {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Buscar configuração de imagem para um usuário específico
     * @param int $userId ID do usuário
     * @param string $imageKey Chave da imagem (ex: logo_banner_1, background_banner_2, etc.)
     * @return array|false Retorna array com image_path e upload_type ou false se não encontrado
     */
    public function getUserImageConfig($userId, $imageKey) {
        $stmt = $this->db->prepare("
            SELECT image_path, upload_type 
            FROM user_images 
            WHERE user_id = ? AND image_key = ?
        ");
        $stmt->execute([$userId, $imageKey]);
        return $stmt->fetch();
    }
    
    /**
     * Salvar ou atualizar configuração de imagem para um usuário
     * @param int $userId ID do usuário
     * @param string $imageKey Chave da imagem
     * @param string $imagePath Caminho da imagem
     * @param string $uploadType Tipo de upload (file, url, default)
     * @return bool Sucesso da operação
     */
    public function saveUserImage($userId, $imageKey, $imagePath, $uploadType = 'file') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_images (user_id, image_key, image_path, upload_type) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                image_path = VALUES(image_path), 
                upload_type = VALUES(upload_type),
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId, $imageKey, $imagePath, $uploadType]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao salvar imagem do usuário: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar todas as imagens de um usuário
     * @param int $userId ID do usuário
     * @return array Array com todas as configurações de imagem do usuário
     */
    public function getAllUserImages($userId) {
        $stmt = $this->db->prepare("
            SELECT image_key, image_path, upload_type, created_at, updated_at 
            FROM user_images 
            WHERE user_id = ? 
            ORDER BY image_key
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Excluir configuração de imagem de um usuário
     * @param int $userId ID do usuário
     * @param string $imageKey Chave da imagem
     * @return bool Sucesso da operação
     */
    public function deleteUserImage($userId, $imageKey) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_images 
                WHERE user_id = ? AND image_key = ?
            ");
            $stmt->execute([$userId, $imageKey]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao excluir imagem do usuário: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Inicializar imagens padrão para um novo usuário
     * @param int $userId ID do usuário
     * @return bool Sucesso da operação
     */
    public function initializeDefaultImages($userId) {
        $defaultImages = [
            'logo_banner_1' => 'wtec/logo/logo_banner_1.png',
            'logo_banner_2' => 'wtec/logo/logo_banner_2.png',
            'logo_banner_3' => 'wtec/logo/logo_banner_3.png',
            'logo_banner_4' => 'wtec/logo/logo_banner_4.png',
            'background_banner_1' => 'wtec/Img/background_banner_1.jpg',
            'background_banner_2' => 'wtec/Img/background_banner_2.jpg',
            'background_banner_3' => 'wtec/Img/background_banner_3.jpg',
            'background_banner_4' => 'wtec/Img/background_banner_4.jpg',
            'card_banner_1' => 'wtec/card/card_banner_1.png',
            'card_banner_2' => 'wtec/card/card_banner_2.png',
            'card_banner_3' => 'wtec/card/card_banner_3.png',
            'card_banner_4' => 'wtec/card/card_banner_4.png'
        ];
        
        try {
            $this->db->beginTransaction();
            
            foreach ($defaultImages as $imageKey => $imagePath) {
                $this->saveUserImage($userId, $imageKey, $imagePath, 'default');
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao inicializar imagens padrão: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se um arquivo de imagem existe no sistema
     * @param string $imagePath Caminho da imagem
     * @return bool True se o arquivo existe
     */
    public function imageFileExists($imagePath) {
        // Se for uma URL, verificar se é válida
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return true; // Assumir que URLs são válidas
        }
        
        // Se for um arquivo local, verificar se existe
        $fullPath = __DIR__ . '/../' . $imagePath;
        return file_exists($fullPath);
    }
    
    /**
     * Obter o conteúdo de uma imagem (para uso nos geradores de banner)
     * @param int $userId ID do usuário
     * @param string $imageKey Chave da imagem
     * @return string|false Conteúdo da imagem ou false se não encontrado
     */
    public function getImageContent($userId, $imageKey) {
        $config = $this->getUserImageConfig($userId, $imageKey);
        
        if (!$config) {
            return false;
        }
        
        $imagePath = $config['image_path'];
        $uploadType = $config['upload_type'];
        
        // Se for uma URL, baixar o conteúdo
        if ($uploadType === 'url' && filter_var($imagePath, FILTER_VALIDATE_URL)) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; FutBanner/1.0)'
                ]
            ]);
            return @file_get_contents($imagePath, false, $context);
        }
        
        // Se for um arquivo local
        $fullPath = __DIR__ . '/../' . $imagePath;
        if (file_exists($fullPath)) {
            return @file_get_contents($fullPath);
        }
        
        return false;
    }
    
    /**
     * Obter estatísticas de uso de imagens
     * @return array Estatísticas de uso
     */
    public function getImageStats() {
        $stmt = $this->db->prepare("
            SELECT 
                upload_type,
                COUNT(*) as count
            FROM user_images 
            GROUP BY upload_type
        ");
        $stmt->execute();
        $stats = $stmt->fetchAll();
        
        $result = [
            'file' => 0,
            'url' => 0,
            'default' => 0,
            'total' => 0
        ];
        
        foreach ($stats as $stat) {
            $result[$stat['upload_type']] = (int)$stat['count'];
            $result['total'] += (int)$stat['count'];
        }
        
        return $result;
    }
}
?>
