<?php
require_once 'config/database.php';

class SystemSettings {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createSystemSettingsTable();
    }
    
    /**
     * Criar tabela de configurações do sistema se não existir
     */
    private function createSystemSettingsTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('text', 'file', 'boolean', 'number') DEFAULT 'text',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        );
        ";
        
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela de configurações do sistema: " . $e->getMessage());
        }
    }
    
    /**
     * Salvar uma configuração
     * @param string $key Chave da configuração
     * @param mixed $value Valor da configuração
     * @param string $type Tipo da configuração (text, file, boolean, number)
     * @return bool Sucesso da operação
     */
    public function setSetting($key, $value, $type = 'text') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                setting_type = VALUES(setting_type),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$key, $value, $type]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao salvar configuração: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter uma configuração
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão se não encontrado
     * @return mixed Valor da configuração ou valor padrão
     */
    public function getSetting($key, $default = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT setting_value, setting_type 
                FROM system_settings 
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result) {
                $value = $result['setting_value'];
                
                // Converter valor baseado no tipo
                switch ($result['setting_type']) {
                    case 'boolean':
                        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    case 'number':
                        return is_numeric($value) ? (float)$value : $default;
                    default:
                        return $value;
                }
            }
            
            return $default;
        } catch (PDOException $e) {
            error_log("Erro ao obter configuração: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Obter todas as configurações
     * @return array Array associativo com todas as configurações
     */
    public function getAllSettings() {
        try {
            $stmt = $this->db->prepare("
                SELECT setting_key, setting_value, setting_type 
                FROM system_settings 
                ORDER BY setting_key
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $settings = [];
            foreach ($results as $result) {
                $value = $result['setting_value'];
                
                // Converter valor baseado no tipo
                switch ($result['setting_type']) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'number':
                        $value = is_numeric($value) ? (float)$value : 0;
                        break;
                }
                
                $settings[$result['setting_key']] = $value;
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log("Erro ao obter todas as configurações: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Deletar uma configuração
     * @param string $key Chave da configuração
     * @return bool Sucesso da operação
     */
    public function deleteSetting($key) {
        try {
            $stmt = $this->db->prepare("DELETE FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao deletar configuração: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salvar favicon personalizado
     * @param array $file Array do arquivo $_FILES
     * @return array Resultado da operação
     */
    public function saveFavicon($file) {
        try {
            // Validar arquivo
            $allowedTypes = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/ico'];
            $allowedExtensions = ['png', 'ico'];
            
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Tipo de arquivo inválido. Use PNG ou ICO.'];
            }
            
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                return ['success' => false, 'message' => 'Extensão de arquivo inválida. Use .png ou .ico.'];
            }
            
            // Verificar tamanho (máximo 1MB)
            if ($file['size'] > 1024 * 1024) {
                return ['success' => false, 'message' => 'Arquivo muito grande. Máximo 1MB.'];
            }
            
            // Criar diretório se não existir
            $uploadDir = __DIR__ . '/../assets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Nome do arquivo
            $fileName = 'favicon.' . $extension;
            $destination = $uploadDir . $fileName;
            
            // Remover favicon anterior se existir
            $oldFavicon = $this->getSetting('favicon_path');
            if ($oldFavicon && file_exists(__DIR__ . '/../' . $oldFavicon)) {
                unlink(__DIR__ . '/../' . $oldFavicon);
            }
            
            // Mover arquivo
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $relativePath = 'assets/' . $fileName;
                
                // Salvar configuração
                $this->setSetting('favicon_path', $relativePath, 'file');
                $this->setSetting('favicon_updated_at', date('Y-m-d H:i:s'), 'text');
                
                return [
                    'success' => true, 
                    'message' => 'Favicon atualizado com sucesso!',
                    'path' => $relativePath
                ];
            } else {
                return ['success' => false, 'message' => 'Erro ao salvar o arquivo.'];
            }
        } catch (Exception $e) {
            error_log("Erro ao salvar favicon: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()];
        }
    }
    
    /**
     * Restaurar favicon padrão
     * @return array Resultado da operação
     */
    public function restoreDefaultFavicon() {
        try {
            // Remover arquivo personalizado se existir
            $currentFavicon = $this->getSetting('favicon_path');
            if ($currentFavicon && file_exists(__DIR__ . '/../' . $currentFavicon)) {
                unlink(__DIR__ . '/../' . $currentFavicon);
            }
            
            // Remover configurações
            $this->deleteSetting('favicon_path');
            $this->deleteSetting('favicon_updated_at');
            
            return [
                'success' => true, 
                'message' => 'Favicon padrão restaurado com sucesso!'
            ];
        } catch (Exception $e) {
            error_log("Erro ao restaurar favicon padrão: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obter URL do favicon atual
     * @return string URL do favicon
     */
    public function getFaviconUrl() {
        $customFavicon = $this->getSetting('favicon_path');
        
        if ($customFavicon && file_exists(__DIR__ . '/../' . $customFavicon)) {
            return $customFavicon . '?v=' . time(); // Cache busting
        }
        
        // Favicon padrão (ícone de futebol via data URI)
        return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233b82f6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.94-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>';
    }
}
?>