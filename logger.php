<?php
// logger.php - Sistema de logging

class Logger {
    private $logFile;
    private $logLevel;
    private $maxSize;
    private $backupLogs;
    
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    
    private $levels = [
        0 => 'DEBUG',
        1 => 'INFO',
        2 => 'WARNING',
        3 => 'ERROR'
    ];
    
    public function __construct($config) {
        $this->logFile = $config['log_file'];
        $this->logLevel = $this->getLevelCode($config['log_level']);
        $this->maxSize = $config['max_log_size'];
        $this->backupLogs = $config['backup_logs'];
        
        $this->checkLogRotation();
    }
    
    private function getLevelCode($level) {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        return $levels[$level] ?? 1;
    }
    
    private function checkLogRotation() {
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxSize) {
            if ($this->backupLogs) {
                $backup = $this->logFile . '.' . date('YmdHis');
                rename($this->logFile, $backup);
            } else {
                unlink($this->logFile);
            }
        }
    }
    
    public function log($level, $message, $context = []) {
        $levelCode = $this->getLevelCode($level);
        
        if ($levelCode < $this->logLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
}

// Classe aprimorada do sistema do hotel
class AdvancedHotelHotspotSystem extends HotelHotspotSystem {
    private $logger;
    private $systemConfig;
    private $userProfiles;
    
    public function __construct($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles, $logConfig) {
        parent::__construct($mikrotikConfig, $dbConfig);
        
        $this->systemConfig = $systemConfig;
        $this->userProfiles = $userProfiles;
        $this->logger = new Logger($logConfig);
        
        $this->logger->info("Sistema iniciado");
    }
    
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        $this->logger->info("Gerando credenciais", [
            'room' => $roomNumber,
            'guest' => $guestName,
            'profile' => $profileType
        ]);
        
        try {
            // Validar se o perfil existe
            if (!isset($this->userProfiles[$profileType])) {
                throw new Exception("Perfil de usuário '{$profileType}' não encontrado");
            }
            
            $profile = $this->userProfiles[$profileType];
            
            // Gerar credenciais únicas
            $username = $this->generateUniqueUsername($roomNumber);
            $password = $this->generateSecurePassword();
            
            // Calcular limite de tempo
            $timeLimit = $this->calculateTimeLimit($checkoutDate);
            
            // Conectar ao MikroTik
            $this->mikrotik->connect();
            
            // Criar perfil no MikroTik se não existir
            $this->ensureProfileExists($profileType, $profile);
            
            // Criar usuário no MikroTik
            $this->mikrotik->createHotspotUser($username, $password, $profileType, $timeLimit);
            
            // Salvar no banco de dados
            $stmt = $this->db->prepare("
                INSERT INTO hotel_guests (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $roomNumber,
                $guestName,
                $username,
                $password,
                $profileType,
                $checkinDate,
                $checkoutDate
            ]);
            
            $this->mikrotik->disconnect();
            
            $this->logger->info("Credenciais geradas com sucesso", [
                'username' => $username,
                'room' => $roomNumber
            ]);
            
            return [
                'success' => true,
                'username' => $username,
                'password' => $password,
                'profile' => $profile['name'],
                'valid_until' => $checkoutDate,
                'bandwidth' => $profile['rate_limit']
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao gerar credenciais", [
                'error' => $e->getMessage(),
                'room' => $roomNumber
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateUniqueUsername($roomNumber) {
        $baseUsername = 'guest_' . $roomNumber;
        $counter = 1;
        $username = $baseUsername;
        
        // Verificar se o username já existe
        while ($this->usernameExists($username)) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    private function usernameExists($username) {
        $stmt = $this->db->prepare("SELECT id FROM hotel_guests WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() !== false;
    }
    
    private function generateSecurePassword() {
        $length = $this->systemConfig['password_length'];
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    private function ensureProfileExists($profileName, $profileConfig) {
        try {
            // Verificar se o perfil existe
            $this->mikrotik->write('/ip/hotspot/user/profile/print', [
                '?name=' . $profileName
            ]);
            
            $response = $this->mikrotik->read();
            
            // Se o perfil não existir, criar
            if (empty($response) || isset($response[0]['!trap'])) {
                $this->mikrotik->write('/ip/hotspot/user/profile/add', [
                    '=name=' . $profileName,
                    '=rate-limit=' . $profileConfig['rate_limit'],
                    '=session-timeout=' . $profileConfig['session_timeout'],
                    '=idle-timeout=' . $profileConfig['idle_timeout'],
                    '=shared-users=' . $profileConfig['shared_users']
                ]);
                
                $this->logger->info("Perfil criado no MikroTik", ['profile' => $profileName]);
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao verificar/criar perfil", [
                'profile' => $profileName,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function getDetailedActiveGuests() {
        $stmt = $this->db->prepare("
            SELECT room_number, guest_name, username, profile_type, checkin_date, checkout_date, created_at
            FROM hotel_guests 
            WHERE status = 'active' 
            ORDER BY room_number
        ");
        $stmt->execute();
        $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Adicionar informações do MikroTik
        try {
            $this->mikrotik->connect();
            $activeUsers = $this->mikrotik->getActiveUsers();
            $this->mikrotik->disconnect();
            
            foreach ($guests as &$guest) {
                $guest['online'] = false;
                $guest['uptime'] = '';
                $guest['bytes_in'] = 0;
                $guest['bytes_out'] = 0;
                
                foreach ($activeUsers as $activeUser) {
                    if (isset($activeUser['!re']['user']) && $activeUser['!re']['user'] === $guest['username']) {
                        $guest['online'] = true;
                        $guest['uptime'] = $activeUser['!re']['uptime'] ?? '';
                        $guest['bytes_in'] = $activeUser['!re']['bytes-in'] ?? 0;
                        $guest['bytes_out'] = $activeUser['!re']['bytes-out'] ?? 0;
                        break;
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao obter usuários ativos", ['error' => $e->getMessage()]);
        }
        
        return $guests;
    }
    
    public function generateReport($startDate, $endDate, $format = 'array') {
        $this->logger->info("Gerando relatório", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'format' => $format
        ]);
        
        $stmt = $this->db->prepare("
            SELECT 
                room_number,
                guest_name,
                username,
                profile_type,
                checkin_date,
                checkout_date,
                created_at,
                status
            FROM hotel_guests 
            WHERE created_at BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            return $this->generateCSVReport($data);
        } elseif ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        
        return $data;
    }
    
    private function generateCSVReport($data) {
        $output = fopen('php://temp', 'w');
        
        // Cabeçalho
        fputcsv($output, [
            'Quarto',
            'Hóspede',
            'Usuário',
            'Perfil',
            'Check-in',
            'Check-out',
            'Criado em',
            'Status'
        ]);
        
        // Dados
        foreach ($data as $row) {
            fputcsv($output, [
                $row['room_number'],
                $row['guest_name'],
                $row['username'],
                $row['profile_type'],
                $row['checkin_date'],
                $row['checkout_date'],
                $row['created_at'],
                $row['status']
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    public function getSystemStats() {
        $stats = [];
        
        // Estatísticas do banco
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM hotel_guests");
        $stats['total_guests'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) as active FROM hotel_guests WHERE status = 'active'");
        $stats['active_guests'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) as today FROM hotel_guests WHERE DATE(created_at) = CURDATE()");
        $stats['today_guests'] = $stmt->fetchColumn();
        
        // Estatísticas do MikroTik
        try {
            $this->mikrotik->connect();
            $activeUsers = $this->mikrotik->getActiveUsers();
            $stats['online_users'] = count($activeUsers);
            $this->mikrotik->disconnect();
        } catch (Exception $e) {
            $stats['online_users'] = 0;
            $this->logger->error("Erro ao obter estatísticas do MikroTik", ['error' => $e->getMessage()]);
        }
        
        return $stats;
    }
    
    protected function createTables() {
        $sql = "CREATE TABLE IF NOT EXISTS hotel_guests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_number VARCHAR(10) NOT NULL,
            guest_name VARCHAR(100) NOT NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(50) NOT NULL,
            profile_type VARCHAR(50) DEFAULT 'hotel-guest',
            checkin_date DATE NOT NULL,
            checkout_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('active', 'expired', 'disabled') DEFAULT 'active',
            INDEX idx_room (room_number),
            INDEX idx_status (status),
            INDEX idx_dates (checkin_date, checkout_date)
        )";
        
        $this->db->exec($sql);
        
        // Tabela de logs de acesso
        $sql = "CREATE TABLE IF NOT EXISTS access_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            room_number VARCHAR(10) NOT NULL,
            action ENUM('login', 'logout', 'created', 'disabled') NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_room (room_number),
            INDEX idx_action (action),
            INDEX idx_date (created_at)
        )";
        
        $this->db->exec($sql);
        
        $this->logger->info("Tabelas do banco de dados verificadas/criadas");
    }
}
?>