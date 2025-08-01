<?php
require_once 'config/database.php';

class BannerStats {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Criar tabela de estatísticas de banners se não existir
     */
    public function createBannerStatsTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS banner_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            banner_type ENUM('movie', 'football') NOT NULL,
            banner_theme VARCHAR(50),
            content_name VARCHAR(255),
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, generated_at),
            INDEX idx_date (generated_at),
            INDEX idx_type (banner_type)
        );
        ";
        
        $this->db->exec($sql);
    }
    
    /**
     * Registrar geração de banner
     */
    public function recordBannerGeneration($userId, $bannerType, $bannerTheme = null, $contentName = null) {
        try {
            $this->createBannerStatsTable(); // Garantir que a tabela existe
            
            $stmt = $this->db->prepare("
                INSERT INTO banner_stats (user_id, banner_type, banner_theme, content_name, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt->execute([$userId, $bannerType, $bannerTheme, $contentName, $ipAddress, $userAgent]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao registrar banner: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter estatísticas de banners para um usuário
     */
    public function getUserBannerStats($userId) {
        try {
            $this->createBannerStatsTable(); // Garantir que a tabela existe
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_banners,
                    COUNT(CASE WHEN DATE(generated_at) = CURDATE() THEN 1 END) as today_banners,
                    COUNT(CASE WHEN YEAR(generated_at) = YEAR(CURDATE()) AND MONTH(generated_at) = MONTH(CURDATE()) THEN 1 END) as month_banners,
                    COUNT(CASE WHEN banner_type = 'movie' THEN 1 END) as movie_banners,
                    COUNT(CASE WHEN banner_type = 'football' THEN 1 END) as football_banners
                FROM banner_stats 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            return $result ?: [
                'total_banners' => 0,
                'today_banners' => 0,
                'month_banners' => 0,
                'movie_banners' => 0,
                'football_banners' => 0
            ];
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            return [
                'total_banners' => 0,
                'today_banners' => 0,
                'month_banners' => 0,
                'movie_banners' => 0,
                'football_banners' => 0
            ];
        }
    }
    
    /**
     * Obter estatísticas globais (para admins)
     */
    public function getGlobalBannerStats() {
        try {
            $this->createBannerStatsTable(); // Garantir que a tabela existe
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_banners,
                    COUNT(CASE WHEN DATE(generated_at) = CURDATE() THEN 1 END) as today_banners,
                    COUNT(CASE WHEN YEAR(generated_at) = YEAR(CURDATE()) AND MONTH(generated_at) = MONTH(CURDATE()) THEN 1 END) as month_banners,
                    COUNT(DISTINCT user_id) as active_users
                FROM banner_stats
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result ?: [
                'total_banners' => 0,
                'today_banners' => 0,
                'month_banners' => 0,
                'active_users' => 0
            ];
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas globais: " . $e->getMessage());
            return [
                'total_banners' => 0,
                'today_banners' => 0,
                'month_banners' => 0,
                'active_users' => 0
            ];
        }
    }
    
    /**
     * Obter banners recentes do usuário
     */
    public function getRecentBanners($userId, $limit = 5) {
        try {
            $this->createBannerStatsTable(); // Garantir que a tabela existe
            
            $stmt = $this->db->prepare("
                SELECT banner_type, banner_theme, content_name, generated_at
                FROM banner_stats 
                WHERE user_id = ? 
                ORDER BY generated_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao obter banners recentes: " . $e->getMessage());
            return [];
        }
    }
}
?>