<?php
// Configuração do banco de dados MySQL
class Database {
    private static $instance = null;
    private $connection;
    
    // Configurações do MySQL - ALTERE ESTAS CONFIGURAÇÕES
    private $host = 'localhost';
    private $dbname = 'admin_futbanner';
    private $username = 'admin_futbanner';
    private $password = 'r5mCUI&~wld4alt7';
    private $charset = 'utf8mb4';
    
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Método para criar as tabelas necessárias
    public function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE,
            role ENUM('admin', 'master', 'user') DEFAULT 'user',
            status ENUM('active', 'inactive', 'trial') DEFAULT 'active',
            expires_at DATE NULL,
            credits INT DEFAULT 0,
            parent_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            FOREIGN KEY (parent_user_id) REFERENCES usuarios(id) ON DELETE SET NULL
        );
        
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS user_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            image_key VARCHAR(50) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            upload_type ENUM('file', 'url', 'default') DEFAULT 'file',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_image (user_id, image_key),
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_user_image_key (user_id, image_key)
        );
        
        CREATE TABLE IF NOT EXISTS user_telegram_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bot_token VARCHAR(255) NOT NULL,
            chat_id VARCHAR(50) NOT NULL,
            football_message TEXT,
            movie_series_message TEXT,
            scheduled_time VARCHAR(5),
            scheduled_football_theme INT DEFAULT 1,
            scheduled_delivery_enabled TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_telegram (user_id),
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_user_telegram (user_id),
            INDEX idx_scheduled_delivery (scheduled_delivery_enabled, scheduled_time)
        );

        CREATE TABLE IF NOT EXISTS mercadopago_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            access_token VARCHAR(255) NOT NULL,
            user_access_value DECIMAL(10, 2) NOT NULL,
            whatsapp_number VARCHAR(20),
            discount_3_months_percent DECIMAL(5, 2) DEFAULT 5.00,
            discount_6_months_percent DECIMAL(5, 2) DEFAULT 10.00,
            discount_12_months_percent DECIMAL(5, 2) DEFAULT 15.00,
            credit_price DECIMAL(10, 2) DEFAULT 1.00,
            min_credit_purchase INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS credit_purchases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount INT NOT NULL,
            payment_id VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_payment_id (payment_id)
        );
        
        CREATE TABLE IF NOT EXISTS mercadopago_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            payment_id VARCHAR(255),
            preference_id VARCHAR(255) NOT NULL,
            external_reference VARCHAR(255),
            status VARCHAR(50) NOT NULL,
            status_detail VARCHAR(255),
            payment_method VARCHAR(50),
            payment_type VARCHAR(50),
            transaction_amount DECIMAL(10, 2) NOT NULL,
            payment_purpose VARCHAR(50) DEFAULT 'subscription',
            related_quantity INT DEFAULT 1,
            is_processed BOOLEAN DEFAULT FALSE,
            payment_date TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_preference_id (preference_id),
            INDEX idx_payment_id (payment_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        );
        ";
        
        $this->connection->exec($sql);
        
        // Adicionar colunas necessárias se não existirem
        $this->addColumnsIfNotExist();
        
        // Inserir imagens padrão para usuários existentes
        $this->insertDefaultImages();
    }
    
    // Método para adicionar colunas de desconto e WhatsApp se não existirem
    private function addColumnsIfNotExist() {
        try {
            // Verificar se a coluna whatsapp_number existe
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mercadopago_settings' AND COLUMN_NAME = 'whatsapp_number'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar coluna whatsapp_number
                $this->connection->exec("
                    ALTER TABLE mercadopago_settings 
                    ADD COLUMN whatsapp_number VARCHAR(20) AFTER user_access_value
                ");
            }
            
            // Verificar se a coluna discount_3_months_percent existe
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mercadopago_settings' AND COLUMN_NAME = 'discount_3_months_percent'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar colunas de desconto
                $this->connection->exec("
                    ALTER TABLE mercadopago_settings 
                    ADD COLUMN discount_3_months_percent DECIMAL(5, 2) DEFAULT 5.00 AFTER whatsapp_number,
                    ADD COLUMN discount_6_months_percent DECIMAL(5, 2) DEFAULT 10.00 AFTER discount_3_months_percent,
                    ADD COLUMN discount_12_months_percent DECIMAL(5, 2) DEFAULT 15.00 AFTER discount_6_months_percent
                ");
            }
            
            // Verificar se a coluna credit_price existe
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mercadopago_settings' AND COLUMN_NAME = 'credit_price'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar colunas para o sistema de créditos
                $this->connection->exec("
                    ALTER TABLE mercadopago_settings 
                    ADD COLUMN credit_price DECIMAL(10, 2) DEFAULT 1.00 AFTER discount_12_months_percent,
                    ADD COLUMN min_credit_purchase INT DEFAULT 1 AFTER credit_price
                ");
            }
            
            // Verificar se a coluna trial_duration_days existe
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mercadopago_settings' AND COLUMN_NAME = 'trial_duration_days'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar coluna para duração do teste grátis
                $this->connection->exec("
                    ALTER TABLE mercadopago_settings 
                    ADD COLUMN trial_duration_days INT DEFAULT 7 AFTER min_credit_purchase
                ");
            }
            
            // Verificar se a coluna credits existe na tabela usuarios
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'credits'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar coluna credits
                $this->connection->exec("
                    ALTER TABLE usuarios 
                    ADD COLUMN credits INT DEFAULT 0 AFTER expires_at
                ");
            }
            
            // Verificar se a coluna parent_user_id existe na tabela usuarios
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'parent_user_id'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar coluna parent_user_id
                $this->connection->exec("
                    ALTER TABLE usuarios 
                    ADD COLUMN parent_user_id INT NULL AFTER credits,
                    ADD FOREIGN KEY (parent_user_id) REFERENCES usuarios(id) ON DELETE SET NULL
                ");
            }
            
            // Verificar se o valor 'master' existe no ENUM role
            $stmt = $this->connection->prepare("
                SELECT COLUMN_TYPE 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'role'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result && strpos($result['COLUMN_TYPE'], 'master') === false) {
                // Adicionar 'master' ao ENUM role
                $this->connection->exec("
                    ALTER TABLE usuarios 
                    MODIFY COLUMN role ENUM('admin', 'master', 'user') DEFAULT 'user'
                ");
            }
            
            // Verificar se o valor 'trial' existe no ENUM status
            $stmt = $this->connection->prepare("
                SELECT COLUMN_TYPE 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'status'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result && strpos($result['COLUMN_TYPE'], 'trial') === false) {
                // Adicionar 'trial' ao ENUM status
                $this->connection->exec("
                    ALTER TABLE usuarios 
                    MODIFY COLUMN status ENUM('active', 'inactive', 'trial') DEFAULT 'active'
                ");
            }
            
            // Verificar se a tabela credit_purchases existe
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as table_exists 
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credit_purchases'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['table_exists'] == 0) {
                // Criar tabela credit_purchases
                $this->connection->exec("
                    CREATE TABLE IF NOT EXISTS credit_purchases (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        amount INT NOT NULL,
                        payment_id VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                        INDEX idx_user_id (user_id),
                        INDEX idx_payment_id (payment_id)
                    )
                ");
            }
            
            // Verificar se as colunas payment_purpose e related_quantity existem na tabela mercadopago_payments
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mercadopago_payments' AND COLUMN_NAME = 'payment_purpose'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar colunas payment_purpose e related_quantity
                $this->connection->exec("
                    ALTER TABLE mercadopago_payments 
                    ADD COLUMN payment_purpose VARCHAR(50) DEFAULT 'subscription' AFTER transaction_amount,
                    ADD COLUMN related_quantity INT DEFAULT 1 AFTER payment_purpose
                ");
            }
            
            // Verificar se a coluna is_processed existe na tabela mercadopago_payments
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as column_exists 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mercadopago_payments' AND COLUMN_NAME = 'is_processed'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['column_exists'] == 0) {
                // Adicionar coluna is_processed
                $this->connection->exec("
                    ALTER TABLE mercadopago_payments 
                    ADD COLUMN is_processed BOOLEAN DEFAULT FALSE AFTER related_quantity
                ");
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar/adicionar colunas: " . $e->getMessage());
        }
    }
    
    // Método para inserir imagens padrão para usuários existentes
    private function insertDefaultImages() {
        $defaultImages = [
            'logo_banner_1' => 'wtec/logo/logo_banner_1.png',
            'logo_banner_2' => 'wtec/logo/logo_banner_2.png',
            'logo_banner_3' => 'wtec/logo/logo_banner_3.png',
            'logo_banner_4' => 'wtec/logo/logo_banner_4.png',
            'logo_movie_banner' => 'wtec/logo/logo_banner_movie.png', 
            'background_banner_1' => 'wtec/Img/background_banner_1.jpg',
            'background_banner_2' => 'wtec/Img/background_banner_2.jpg',
            'background_banner_3' => 'wtec/Img/background_banner_3.jpg',
            'background_banner_4' => 'wtec/Img/background_banner_4.jpg', 
            'card_banner_1' => 'wtec/card/card_banner_1.png',
            'card_banner_2' => 'wtec/card/card_banner_2.png',
            'card_banner_3' => 'wtec/card/card_banner_3.png',
            'card_banner_4' => 'wtec/card/card_banner_4.png' 
        ];
        
        // Buscar usuários que não têm imagens configuradas
        $stmt = $this->connection->prepare("
            SELECT DISTINCT u.id 
            FROM usuarios u 
            LEFT JOIN user_images ui ON u.id = ui.user_id 
            WHERE ui.user_id IS NULL
        ");
        $stmt->execute();
        $usersWithoutImages = $stmt->fetchAll();
        
        foreach ($usersWithoutImages as $user) {
            foreach ($defaultImages as $imageKey => $imagePath) {
                $stmt = $this->connection->prepare("
                    INSERT IGNORE INTO user_images (user_id, image_key, image_path, upload_type) 
                    VALUES (?, ?, ?, 'default')
                ");
                $stmt->execute([$user['id'], $imageKey, $imagePath]);
            }
        }
        
        // Adicionar logo_movie_banner para usuários existentes que não têm essa configuração
        $stmt = $this->connection->prepare("
            SELECT u.id 
            FROM usuarios u 
            LEFT JOIN user_images ui ON u.id = ui.user_id AND ui.image_key = 'logo_movie_banner'
            WHERE ui.user_id IS NULL
        ");
        $stmt->execute();
        $usersWithoutMovieLogo = $stmt->fetchAll();
        
        foreach ($usersWithoutMovieLogo as $user) {
            $stmt = $this->connection->prepare("
                INSERT IGNORE INTO user_images (user_id, image_key, image_path, upload_type) 
                VALUES (?, 'logo_movie_banner', 'wtec/logo/logo_banner_movie.png', 'default')
            ");
            $stmt->execute([$user['id']]);
        }
        
        // Adicionar novas imagens do tema 4 para usuários existentes
        $newImages = [
            'logo_banner_4' => 'wtec/logo/logo_banner_4.png',
            'background_banner_4' => 'wtec/Img/background_banner_4.png',
            'card_banner_4' => 'wtec/card/card_banner_4.png'
        ];
        
        foreach ($newImages as $imageKey => $imagePath) {
            $stmt = $this->connection->prepare("
                SELECT u.id 
                FROM usuarios u 
                LEFT JOIN user_images ui ON u.id = ui.user_id AND ui.image_key = ?
                WHERE ui.user_id IS NULL
            ");
            $stmt->execute([$imageKey]);
            $usersWithoutImage = $stmt->fetchAll();
            
            foreach ($usersWithoutImage as $user) {
                $stmt = $this->connection->prepare("
                    INSERT IGNORE INTO user_images (user_id, image_key, image_path, upload_type) 
                    VALUES (?, ?, ?, 'default')
                ");
                $stmt->execute([$user['id'], $imageKey, $imagePath]);
            }
        }
    }
}
?>
