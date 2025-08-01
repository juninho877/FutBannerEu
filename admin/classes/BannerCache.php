<?php
require_once 'config/database.php';

class BannerCache {
    private $db;
    private $cacheDir;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cacheDir = sys_get_temp_dir() . '/futbanner_cache/';
        
        // Criar diretório de cache se não existir
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        $this->createCacheTable();
    }
    
    /**
     * Criar tabela de cache se não existir
     */
    private function createCacheTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS banner_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            cache_key VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            banner_type ENUM('football_1', 'football_2', 'football_3', 'football_4', 'movie_1', 'movie_2', 'movie_3') NOT NULL,
            grupo_index INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            
            UNIQUE KEY unique_user_cache (user_id, cache_key),
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_expires (expires_at),
            INDEX idx_user_type (user_id, banner_type),
            INDEX idx_created_date (DATE(created_at))
        );
        ";
        
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Erro ao criar tabela de cache: " . $e->getMessage());
        }
    }
    
    /**
     * 🔧 MELHORADA: Gerar chave de cache mais específica e estável
     */
    public function generateCacheKey($userId, $bannerType, $grupoIndex, $jogos) {
        // Incluir configurações do usuário na chave para invalidar quando mudarem
        $userConfigHash = $this->getUserConfigHash($userId);
        
        // Criar hash mais específico dos jogos (apenas dados relevantes)
        $jogosRelevantes = [];
        foreach ($jogos as $jogo) {
            $jogosRelevantes[] = [
                'time1' => $jogo['time1'] ?? '',
                'time2' => $jogo['time2'] ?? '',
                'horario' => $jogo['horario'] ?? '',
                'competicao' => $jogo['competicao'] ?? '',
                'placar_time1' => $jogo['placar_time1'] ?? '',
                'placar_time2' => $jogo['placar_time2'] ?? '',
                'status' => $jogo['status'] ?? ''
            ];
        }
        
        $dataString = serialize([
            'user_id' => $userId,
            'banner_type' => $bannerType,
            'grupo_index' => $grupoIndex,
            'jogos_hash' => md5(serialize($jogosRelevantes)),
            'user_config' => $userConfigHash,
            'date' => date('Y-m-d'), // Cache válido apenas para o dia atual
            'version' => '2.0' // Versão do cache para forçar regeneração quando necessário
        ]);
        
        return md5($dataString);
    }
    
    /**
     * 🆕 Obter hash das configurações do usuário (logos, fundos, cards)
     */
    private function getUserConfigHash($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT image_key, image_path, upload_type, updated_at
                FROM user_images 
                WHERE user_id = ? 
                ORDER BY image_key
            ");
            $stmt->execute([$userId]);
            $configs = $stmt->fetchAll();
            
            return md5(serialize($configs));
        } catch (Exception $e) {
            return 'default';
        }
    }
    
    /**
     * 🔧 MELHORADA: Verificar cache com validação mais rigorosa
     */
    public function getCachedBanner($userId, $cacheKey) {
        try {
            $stmt = $this->db->prepare("
                SELECT file_path, original_name, created_at, expires_at
                FROM banner_cache 
                WHERE user_id = ? AND cache_key = ? AND expires_at > NOW()
            ");
            $stmt->execute([$userId, $cacheKey]);
            $result = $stmt->fetch();
            
            if ($result && file_exists($result['file_path'])) {
                // Verificar se o arquivo não está corrompido
                $fileSize = filesize($result['file_path']);
                if ($fileSize > 1000) { // Arquivo deve ter pelo menos 1KB
                    return $result;
                }
            }
            
            // Se não existe, está corrompido ou arquivo foi removido, limpar do banco
            if ($result) {
                $this->removeCachedBanner($userId, $cacheKey);
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Erro ao buscar cache: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🔧 MELHORADA: Salvar banner no cache com validação
     */
    public function saveBannerToCache($userId, $cacheKey, $imageResource, $bannerType, $grupoIndex = null, $originalName = null) {
        try {
            // Gerar nome único para o arquivo
            $fileName = 'banner_' . $bannerType . '_' . $userId . '_' . substr($cacheKey, 0, 8) . '_' . time() . '.png';
            $filePath = $this->cacheDir . $fileName;
            
            // Salvar imagem
            if (!imagepng($imageResource, $filePath, 6)) { // Compressão 6 para equilibrar qualidade/tamanho
                return false;
            }
            
            // Verificar se o arquivo foi criado corretamente
            if (!file_exists($filePath) || filesize($filePath) < 1000) {
                if (file_exists($filePath)) unlink($filePath);
                return false;
            }
            
            // Nome original padrão se não fornecido
            if (!$originalName) {
                $originalName = 'banner_' . $bannerType . '_' . date('Y-m-d') . '.png';
                if ($grupoIndex !== null) {
                    $originalName = 'banner_' . $bannerType . '_parte_' . ($grupoIndex + 1) . '_' . date('Y-m-d') . '.png';
                }
            }
            
            // 🔧 AJUSTADO: Expiração até às 00h do próximo dia (quando os jogos mudam)
            $tomorrow = new DateTime('tomorrow');
            $expiresAt = $tomorrow->format('Y-m-d H:i:s');
            
            // Remover cache anterior se existir
            $this->removeCachedBanner($userId, $cacheKey);
            
            // Salvar no banco
            $stmt = $this->db->prepare("
                INSERT INTO banner_cache (user_id, cache_key, file_path, original_name, banner_type, grupo_index, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $cacheKey, $filePath, $originalName, $bannerType, $grupoIndex, $expiresAt]);
            
            return [
                'file_path' => $filePath,
                'original_name' => $originalName,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("Erro ao salvar cache: " . $e->getMessage());
            // Limpar arquivo se foi criado mas houve erro no banco
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
            return false;
        }
    }
    
    /**
     * Remover banner do cache
     */
    public function removeCachedBanner($userId, $cacheKey) {
        try {
            // Buscar arquivo para remover
            $stmt = $this->db->prepare("
                SELECT file_path FROM banner_cache 
                WHERE user_id = ? AND cache_key = ?
            ");
            $stmt->execute([$userId, $cacheKey]);
            $result = $stmt->fetch();
            
            if ($result && file_exists($result['file_path'])) {
                unlink($result['file_path']);
            }
            
            // Remover do banco
            $stmt = $this->db->prepare("
                DELETE FROM banner_cache 
                WHERE user_id = ? AND cache_key = ?
            ");
            $stmt->execute([$userId, $cacheKey]);
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao remover cache: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🔧 MELHORADA: Limpar cache expirado e órfãos
     */
    public function cleanExpiredCache() {
        try {
            // Buscar arquivos expirados
            $stmt = $this->db->prepare("
                SELECT file_path FROM banner_cache 
                WHERE expires_at <= NOW()
            ");
            $stmt->execute();
            $expiredFiles = $stmt->fetchAll();
            
            $removedCount = 0;
            
            // Remover arquivos físicos expirados
            foreach ($expiredFiles as $file) {
                if (file_exists($file['file_path'])) {
                    if (unlink($file['file_path'])) {
                        $removedCount++;
                    }
                }
            }
            
            // Remover registros expirados do banco
            $stmt = $this->db->prepare("
                DELETE FROM banner_cache 
                WHERE expires_at <= NOW()
            ");
            $stmt->execute();
            
            // 🆕 LIMPAR ARQUIVOS ÓRFÃOS (arquivos que existem mas não estão no banco)
            $orphanCount = $this->cleanOrphanFiles();
            
            return $removedCount + $orphanCount;
        } catch (Exception $e) {
            error_log("Erro ao limpar cache: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 🆕 Limpar arquivos órfãos do diretório de cache
     */
    private function cleanOrphanFiles() {
        try {
            if (!is_dir($this->cacheDir)) {
                return 0;
            }
            
            // Obter todos os arquivos válidos do banco
            $stmt = $this->db->prepare("SELECT file_path FROM banner_cache");
            $stmt->execute();
            $validFiles = array_column($stmt->fetchAll(), 'file_path');
            $validFilesSet = array_flip($validFiles);
            
            $orphanCount = 0;
            $files = glob($this->cacheDir . 'banner_*.png');
            
            foreach ($files as $file) {
                if (!isset($validFilesSet[$file])) {
                    // Arquivo órfão - não está no banco
                    if (unlink($file)) {
                        $orphanCount++;
                    }
                }
            }
            
            return $orphanCount;
        } catch (Exception $e) {
            error_log("Erro ao limpar arquivos órfãos: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 🆕 LIMPEZA DIÁRIA COMPLETA (para ser executada às 00h)
     */
    public function dailyCleanup() {
        try {
            $totalRemoved = 0;
            
            // 1. Limpar cache expirado
            $expiredRemoved = $this->cleanExpiredCache();
            $totalRemoved += $expiredRemoved;
            
            // 2. 🔥 FORÇAR LIMPEZA DE TODOS OS CACHES (já que os jogos mudaram)
            $allCacheRemoved = $this->forceCleanAllCache();
            $totalRemoved += $allCacheRemoved;
            
            // 3. Limpar arquivos muito antigos (mais de 7 dias)
            $oldRemoved = $this->cleanOldFiles();
            $totalRemoved += $oldRemoved;
            
            error_log("🧹 LIMPEZA DIÁRIA COMPLETA - Total removido: {$totalRemoved} arquivos");
            
            return [
                'total_removed' => $totalRemoved,
                'expired_removed' => $expiredRemoved,
                'all_cache_removed' => $allCacheRemoved,
                'old_removed' => $oldRemoved,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("❌ Erro na limpeza diária: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 🆕 FORÇAR LIMPEZA DE TODO CACHE (para limpeza diária às 00h)
     */
    private function forceCleanAllCache() {
        try {
            // Buscar TODOS os arquivos de cache
            $stmt = $this->db->prepare("SELECT file_path FROM banner_cache");
            $stmt->execute();
            $allFiles = $stmt->fetchAll();
            
            $removedCount = 0;
            
            // Remover TODOS os arquivos físicos
            foreach ($allFiles as $file) {
                if (file_exists($file['file_path'])) {
                    if (unlink($file['file_path'])) {
                        $removedCount++;
                    }
                }
            }
            
            // Limpar TODA a tabela de cache
            $stmt = $this->db->prepare("DELETE FROM banner_cache");
            $stmt->execute();
            
            error_log("🔥 LIMPEZA FORÇADA - Removidos: {$removedCount} arquivos de cache");
            
            return $removedCount;
        } catch (Exception $e) {
            error_log("Erro na limpeza forçada: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 🆕 Limpar arquivos muito antigos
     */
    private function cleanOldFiles() {
        try {
            $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
            
            // Buscar arquivos antigos
            $stmt = $this->db->prepare("
                SELECT file_path FROM banner_cache 
                WHERE DATE(created_at) < ?
            ");
            $stmt->execute([$sevenDaysAgo]);
            $oldFiles = $stmt->fetchAll();
            
            $removedCount = 0;
            
            // Remover arquivos físicos
            foreach ($oldFiles as $file) {
                if (file_exists($file['file_path'])) {
                    if (unlink($file['file_path'])) {
                        $removedCount++;
                    }
                }
            }
            
            // Remover registros do banco
            $stmt = $this->db->prepare("
                DELETE FROM banner_cache 
                WHERE DATE(created_at) < ?
            ");
            $stmt->execute([$sevenDaysAgo]);
            
            return $removedCount;
        } catch (Exception $e) {
            error_log("Erro ao limpar arquivos antigos: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 🔥 LIMPAR TODO CACHE DE UM USUÁRIO (INVALIDAÇÃO AUTOMÁTICA)
     */
    public function clearUserCache($userId) {
        try {
            // Log para debug
            error_log("🔥 INVALIDANDO CACHE para usuário ID: " . $userId);
            
            // Buscar arquivos do usuário
            $stmt = $this->db->prepare("
                SELECT file_path, cache_key FROM banner_cache 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $userFiles = $stmt->fetchAll();
            
            $removedCount = 0;
            
            // Remover arquivos físicos
            foreach ($userFiles as $file) {
                if (file_exists($file['file_path'])) {
                    if (unlink($file['file_path'])) {
                        $removedCount++;
                        error_log("🗑️ Arquivo removido: " . basename($file['file_path']));
                    }
                }
            }
            
            // Remover registros do banco
            $stmt = $this->db->prepare("
                DELETE FROM banner_cache 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $dbRemovedCount = $stmt->rowCount();
            
            error_log("✅ Cache limpo - Arquivos: {$removedCount}, Registros DB: {$dbRemovedCount}");
            
            return $removedCount;
        } catch (Exception $e) {
            error_log("❌ Erro ao limpar cache do usuário: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 🔧 MELHORADA: Obter estatísticas do cache
     */
    public function getCacheStats($userId = null) {
        try {
            if ($userId) {
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(*) as total_cached,
                        COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as valid_cached,
                        COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_cached
                    FROM banner_cache 
                    WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(*) as total_cached,
                        COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as valid_cached,
                        COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_cached,
                        COUNT(DISTINCT user_id) as users_with_cache
                    FROM banner_cache
                ");
                $stmt->execute();
            }
            
            $stats = $stmt->fetch();
            
            // Calcular tamanho total dos arquivos
            $totalSize = 0;
            if (is_dir($this->cacheDir)) {
                $files = glob($this->cacheDir . 'banner_*.png');
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $totalSize += filesize($file);
                    }
                }
            }
            
            $stats['total_size_mb'] = round($totalSize / 1024 / 1024, 2);
            
            return $stats;
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas do cache: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Servir arquivo do cache
     */
    public function serveCachedFile($filePath, $originalName, $download = false) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=3600'); // Cache por 1 hora no navegador
        
        if ($download) {
            header('Content-Disposition: attachment; filename="' . $originalName . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        } else {
            header('Content-Disposition: inline; filename="' . $originalName . '"');
        }
        
        return readfile($filePath);
    }
}
?>
