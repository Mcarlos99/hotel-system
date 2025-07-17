<?php
// mikrotik_manager.php - Versão atualizada com credenciais simplificadas

// Classe de logging
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

// Classe MikroTik Robusta com fallbacks
class MikroTikHotspotManager {
    private $host;
    private $username;
    private $password;
    private $port;
    private $socket;
    private $connected = false;
    private $logger;
    
    public function __construct($host, $username, $password, $port = 8728) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }
    
    public function setLogger($logger) {
        $this->logger = $logger;
    }
    
    private function log($level, $message, $context = []) {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
    
    // Conectar com múltiplas tentativas
    public function connect() {
        // Tentativas com diferentes credenciais
        $attempts = [
            ['user' => $this->username, 'pass' => $this->password],
            ['user' => 'admin', 'pass' => ''],
            ['user' => 'admin', 'pass' => $this->password],
            ['user' => $this->username, 'pass' => ''],
            ['user' => 'hotel-system', 'pass' => 'hotel123']
        ];
        
        foreach ($attempts as $attempt) {
            try {
                $this->log('INFO', "Tentando conectar", [
                    'host' => $this->host,
                    'port' => $this->port,
                    'user' => $attempt['user']
                ]);
                
                if ($this->tryConnect($attempt['user'], $attempt['pass'])) {
                    $this->connected = true;
                    $this->log('INFO', "Conexão estabelecida", ['user' => $attempt['user']]);
                    return true;
                }
            } catch (Exception $e) {
                $this->log('WARNING', "Falha na tentativa", [
                    'user' => $attempt['user'],
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        throw new Exception("Não foi possível conectar ao MikroTik após múltiplas tentativas");
    }
    
    private function tryConnect($username, $password) {
        if (!extension_loaded('sockets')) {
            throw new Exception("Extensão 'sockets' não está disponível");
        }
        
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            throw new Exception("Não foi possível criar socket");
        }
        
        // Timeouts mais baixos para evitar travamentos
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 3, "usec" => 0));
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 3, "usec" => 0));
        
        if (!socket_connect($this->socket, $this->host, $this->port)) {
            $error = socket_strerror(socket_last_error($this->socket));
            socket_close($this->socket);
            throw new Exception("Falha na conexão: " . $error);
        }
        
        try {
            // Login simples
            $this->write('/login');
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                throw new Exception("Erro no protocolo de login");
            }
            
            // Enviar credenciais
            $loginData = ['=name=' . $username];
            if (!empty($password)) {
                $loginData[] = '=password=' . $password;
            }
            
            $this->write('/login', $loginData);
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                throw new Exception("Credenciais inválidas");
            }
            
            return true;
            
        } catch (Exception $e) {
            if ($this->socket) {
                socket_close($this->socket);
                $this->socket = null;
            }
            throw $e;
        }
    }
    
    public function createHotspotUser($username, $password, $profile = 'default', $timeLimit = '24:00:00') {
        if (!$this->connected) {
            $this->connect();
        }
        
        try {
            $this->write('/ip/hotspot/user/add', [
                '=name=' . $username,
                '=password=' . $password,
                '=profile=' . $profile,
                '=limit-uptime=' . $timeLimit
            ]);
            
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                $error = isset($response[0]['message']) ? $response[0]['message'] : 'Erro desconhecido';
                throw new Exception("Erro ao criar usuário: " . $error);
            }
            
            $this->log('INFO', "Usuário criado", ['username' => $username]);
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Erro ao criar usuário", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            // Tentar reconectar
            if ($this->reconnect()) {
                return $this->createHotspotUser($username, $password, $profile, $timeLimit);
            }
            
            throw $e;
        }
    }
    
    public function removeHotspotUser($username) {
        if (!$this->connected) {
            $this->connect();
        }
        
        try {
            $this->write('/ip/hotspot/user/print', ['?name=' . $username]);
            $users = $this->read();
            
            if (empty($users) || isset($users[0]['!trap'])) {
                throw new Exception("Usuário não encontrado");
            }
            
            if (!isset($users[0]['!re']['.id'])) {
                throw new Exception("ID do usuário não encontrado");
            }
            
            $userId = $users[0]['!re']['.id'];
            
            $this->write('/ip/hotspot/user/remove', ['=.id=' . $userId]);
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                throw new Exception("Erro ao remover usuário");
            }
            
            return true;
            
        } catch (Exception $e) {
            if ($this->reconnect()) {
                return $this->removeHotspotUser($username);
            }
            throw $e;
        }
    }
    
    public function getActiveUsers() {
        if (!$this->connected) {
            try {
                $this->connect();
            } catch (Exception $e) {
                return [];
            }
        }
        
        try {
            $this->write('/ip/hotspot/active/print');
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                return [];
            }
            
            return $response;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function listHotspotUsers() {
        if (!$this->connected) {
            try {
                $this->connect();
            } catch (Exception $e) {
                return [];
            }
        }
        
        try {
            $this->write('/ip/hotspot/user/print');
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                return [];
            }
            
            return $response;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function disconnectUser($username) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $this->write('/ip/hotspot/active/print', ['?user=' . $username]);
            $activeUsers = $this->read();
            
            if (!empty($activeUsers) && isset($activeUsers[0]['!re']['.id'])) {
                $sessionId = $activeUsers[0]['!re']['.id'];
                $this->write('/ip/hotspot/active/remove', ['=.id=' . $sessionId]);
                $this->read();
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function reconnect() {
        try {
            $this->disconnect();
            $this->connected = false;
            sleep(1);
            $this->connect();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function write($command, $arguments = []) {
        if (!$this->socket) {
            throw new Exception("Socket não disponível");
        }
        
        $data = $this->encodeLength(strlen($command)) . $command;
        
        foreach ($arguments as $arg) {
            $data .= $this->encodeLength(strlen($arg)) . $arg;
        }
        
        $data .= $this->encodeLength(0);
        
        $result = socket_write($this->socket, $data);
        if ($result === false) {
            throw new Exception("Erro ao escrever no socket");
        }
    }
    
    private function read() {
        if (!$this->socket) {
            throw new Exception("Socket não disponível");
        }
        
        $response = [];
        
        try {
            while (true) {
                $length = $this->readLength();
                if ($length == 0) break;
                
                $data = socket_read($this->socket, $length);
                if ($data === false) {
                    throw new Exception("Erro ao ler do socket");
                }
                
                $response[] = $data;
            }
        } catch (Exception $e) {
            $this->connected = false;
            throw $e;
        }
        
        return $this->parseResponse($response);
    }
    
    private function readLength() {
        $byte = socket_read($this->socket, 1);
        if ($byte === false || $byte === '') {
            throw new Exception("Conexão perdida");
        }
        
        $length = ord($byte);
        
        if ($length < 0x80) {
            return $length;
        } elseif ($length < 0xC0) {
            $byte = socket_read($this->socket, 1);
            if ($byte === false) throw new Exception("Erro na leitura");
            return (($length & 0x3F) << 8) + ord($byte);
        } elseif ($length < 0xE0) {
            $bytes = socket_read($this->socket, 2);
            if ($bytes === false || strlen($bytes) < 2) throw new Exception("Erro na leitura");
            return (($length & 0x1F) << 16) + (ord($bytes[0]) << 8) + ord($bytes[1]);
        } elseif ($length < 0xF0) {
            $bytes = socket_read($this->socket, 3);
            if ($bytes === false || strlen($bytes) < 3) throw new Exception("Erro na leitura");
            return (($length & 0x0F) << 24) + (ord($bytes[0]) << 16) + (ord($bytes[1]) << 8) + ord($bytes[2]);
        }
        
        return 0;
    }
    
    private function encodeLength($length) {
        if ($length < 0x80) {
            return chr($length);
        } elseif ($length < 0x4000) {
            return chr(0x80 | ($length >> 8)) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            return chr(0xC0 | ($length >> 16)) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            return chr(0xE0 | ($length >> 24)) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
        
        return chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
    }
    
    private function parseResponse($response) {
        $parsed = [];
        $current = [];
        
        foreach ($response as $line) {
            if (substr($line, 0, 1) == '!') {
                if (!empty($current)) {
                    $parsed[] = $current;
                    $current = [];
                }
                $current['!type'] = substr($line, 1);
            } else {
                $parts = explode('=', $line, 2);
                if (count($parts) == 2) {
                    $current[$parts[0]] = $parts[1];
                }
            }
        }
        
        if (!empty($current)) {
            $parsed[] = $current;
        }
        
        return $parsed;
    }
    
    public function disconnect() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }
    
    public function isConnected() {
        return $this->connected;
    }
}

// Classe principal do sistema hotel com credenciais simplificadas
class HotelHotspotSystem {
    protected $mikrotik;
    protected $db;
    protected $logger;
    
    public function __construct($mikrotikConfig, $dbConfig) {
        // Inicializar logger
        $this->logger = new Logger([
            'log_file' => 'logs/hotel_system.log',
            'log_level' => 'INFO',
            'max_log_size' => 10485760,
            'backup_logs' => true
        ]);
        
        // Conectar ao banco de dados
        try {
            $this->db = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Erro na conexão com banco de dados: " . $e->getMessage());
        }
        
        // Conectar ao MikroTik
        $this->mikrotik = new MikroTikHotspotManager(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port'] ?? 8728
        );
        
        $this->mikrotik->setLogger($this->logger);
        
        $this->createTables();
        $this->logger->info("Sistema iniciado");
    }
    
    /**
     * Gera usuário simples e memorável
     * Formato: quarto + 2-3 números aleatórios
     * Exemplo: 101-45, 205-123
     */
    protected function generateSimpleUsername($roomNumber) {
        // Limpar número do quarto (apenas números e letras)
        $cleanRoom = preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber);
        
        // Garantir que não ultrapasse 6 caracteres para a parte do quarto
        if (strlen($cleanRoom) > 6) {
            $cleanRoom = substr($cleanRoom, 0, 6);
        }
        
        // Gerar 2-3 números aleatórios
        $randomLength = rand(2, 3);
        $randomNumbers = '';
        
        for ($i = 0; $i < $randomLength; $i++) {
            $randomNumbers .= rand(0, 9);
        }
        
        $baseUsername = $cleanRoom . '-' . $randomNumbers;
        
        // Verificar se já existe, se sim, tentar novamente
        $attempts = 0;
        while ($this->usernameExists($baseUsername) && $attempts < 15) {
            $randomNumbers = '';
            $randomLength = rand(2, 3);
            for ($i = 0; $i < $randomLength; $i++) {
                $randomNumbers .= rand(0, 9);
            }
            $baseUsername = $cleanRoom . '-' . $randomNumbers;
            $attempts++;
        }
        
        return $baseUsername;
    }
    
    /**
     * Gera senha simples e memorável
     * Formato: 3-4 números simples
     * Evita sequências obviamente simples como 123, 111, etc.
     */
    protected function generateSimplePassword() {
        $length = rand(3, 4);
        $password = '';
        
        // Gerar senha evitando padrões óbvios
        $attempts = 0;
        do {
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                // Para o primeiro dígito, evitar 0
                if ($i === 0) {
                    $password .= rand(1, 9);
                } else {
                    $password .= rand(0, 9);
                }
            }
            $attempts++;
        } while ($this->isObviousPassword($password) && $attempts < 30);
        
        return $password;
    }
    
    /**
     * Verifica se a senha é muito óbvia
     */
    private function isObviousPassword($password) {
        // Evitar sequências crescentes
        if (preg_match('/123|234|345|456|567|678|789/', $password)) {
            return true;
        }
        
        // Evitar sequências decrescentes
        if (preg_match('/987|876|765|654|543|432|321/', $password)) {
            return true;
        }
        
        // Evitar números repetidos (3 ou mais iguais)
        if (preg_match('/(.)\1\1+/', $password)) {
            return true;
        }
        
        // Evitar padrões simples
        $obviousPatterns = [
            '1234', '4321', '1111', '2222', '3333', '4444', '5555', 
            '6666', '7777', '8888', '9999', '0000', '1212', '1010',
            '2020', '1313', '1414', '1515', '1616', '1717', '1818', '1919'
        ];
        
        if (in_array($password, $obviousPatterns)) {
            return true;
        }
        
        // Evitar datas óbvias
        $year = date('Y');
        $shortYear = substr($year, -2);
        if (strpos($password, $shortYear) !== false) {
            return true;
        }
        
        return false;
    }
    
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        $this->logger->info("Gerando credenciais simplificadas", [
            'room' => $roomNumber,
            'guest' => $guestName,
            'profile' => $profileType
        ]);
        
        try {
            // Gerar credenciais simples
            $username = $this->generateSimpleUsername($roomNumber);
            $password = $this->generateSimplePassword();
            $timeLimit = $this->calculateTimeLimit($checkoutDate);
            
            // Conectar ao MikroTik
            $this->mikrotik->connect();
            
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
                'password' => $password,
                'room' => $roomNumber
            ]);
            
            return [
                'success' => true,
                'username' => $username,
                'password' => $password,
                'profile' => $profileType,
                'valid_until' => $checkoutDate,
                'bandwidth' => '10M/2M'
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao gerar credenciais", [
                'error' => $e->getMessage(),
                'room' => $roomNumber
            ]);
            
            // Em caso de erro, salvar apenas no banco
            try {
                $username = $this->generateSimpleUsername($roomNumber);
                $password = $this->generateSimplePassword();
                
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
                
                return [
                    'success' => true,
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profileType,
                    'valid_until' => $checkoutDate,
                    'bandwidth' => '10M/2M',
                    'warning' => 'Usuário criado no sistema. Criar manualmente no MikroTik: /ip hotspot user add name=' . $username . ' password=' . $password . ' profile=' . $profileType
                ];
                
            } catch (Exception $dbError) {
                return [
                    'success' => false,
                    'error' => 'Erro no MikroTik: ' . $e->getMessage() . ' | Erro no banco: ' . $dbError->getMessage()
                ];
            }
        }
    }
    
    public function removeGuestAccess($roomNumber) {
        try {
            $stmt = $this->db->prepare("SELECT username FROM hotel_guests WHERE room_number = ? AND status = 'active'");
            $stmt->execute([$roomNumber]);
            $guest = $stmt->fetch();
            
            if ($guest) {
                try {
                    $this->mikrotik->connect();
                    $this->mikrotik->disconnectUser($guest['username']);
                    $this->mikrotik->removeHotspotUser($guest['username']);
                    $this->mikrotik->disconnect();
                } catch (Exception $e) {
                    // Continuar mesmo se houver erro no MikroTik
                    $this->logger->warning("Erro ao remover do MikroTik", ['error' => $e->getMessage()]);
                }
                
                $stmt = $this->db->prepare("UPDATE hotel_guests SET status = 'disabled' WHERE username = ?");
                $stmt->execute([$guest['username']]);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Hóspede não encontrado'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getActiveGuests() {
        $stmt = $this->db->prepare("
            SELECT room_number, guest_name, username, password, profile_type, checkin_date, checkout_date, created_at
            FROM hotel_guests 
            WHERE status = 'active' 
            ORDER BY room_number
        ");
        $stmt->execute();
        return $stmt->fetchAll();
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
        
        // Estatísticas do MikroTik (com fallback)
        try {
            $this->mikrotik->connect();
            $activeUsers = $this->mikrotik->getActiveUsers();
            $stats['online_users'] = is_array($activeUsers) ? count($activeUsers) : 0;
            $this->mikrotik->disconnect();
        } catch (Exception $e) {
            $stats['online_users'] = 0;
        }
        
        return $stats;
    }
    
    public function cleanupExpiredUsers() {
        try {
            $stmt = $this->db->prepare("
                SELECT username FROM hotel_guests 
                WHERE checkout_date < CURDATE() AND status = 'active'
            ");
            $stmt->execute();
            $expiredUsers = $stmt->fetchAll();
            
            $removedCount = 0;
            
            if (!empty($expiredUsers)) {
                try {
                    $this->mikrotik->connect();
                    
                    foreach ($expiredUsers as $user) {
                        try {
                            $this->mikrotik->disconnectUser($user['username']);
                            $this->mikrotik->removeHotspotUser($user['username']);
                            $removedCount++;
                        } catch (Exception $e) {
                            // Continuar mesmo se houver erro
                        }
                        
                        $stmt = $this->db->prepare("UPDATE hotel_guests SET status = 'expired' WHERE username = ?");
                        $stmt->execute([$user['username']]);
                    }
                    
                    $this->mikrotik->disconnect();
                } catch (Exception $e) {
                    // Se falhou no MikroTik, pelo menos atualizar banco
                    foreach ($expiredUsers as $user) {
                        $stmt = $this->db->prepare("UPDATE hotel_guests SET status = 'expired' WHERE username = ?");
                        $stmt->execute([$user['username']]);
                    }
                }
            }
            
            return ['success' => true, 'removed' => count($expiredUsers)];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    protected function usernameExists($username) {
        $stmt = $this->db->prepare("SELECT id FROM hotel_guests WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() !== false;
    }
    
    private function calculateTimeLimit($checkoutDate) {
        $checkout = new DateTime($checkoutDate . ' 12:00:00');
        $now = new DateTime();
        
        $interval = $now->diff($checkout);
        $hours = ($interval->days * 24) + $interval->h;
        $minutes = $interval->i;
        
        // Garantir pelo menos 1 hora de limite
        if ($hours < 1) {
            $hours = 1;
            $minutes = 0;
        }
        
        return sprintf('%02d:%02d:00', $hours, $minutes);
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
            INDEX idx_dates (checkin_date, checkout_date),
            INDEX idx_username (username)
        ) ENGINE=InnoDB";
        
        $this->db->exec($sql);
        
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
        ) ENGINE=InnoDB";
        
        $this->db->exec($sql);
        
        // Tabela de configurações do sistema
        $sql = "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB";
        
        $this->db->exec($sql);
        
        $this->logger->info("Tabelas do banco de dados verificadas/criadas");
    }
    
    /**
     * Método para gerar relatório de credenciais geradas
     */
    public function getCredentialsReport($startDate = null, $endDate = null) {
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                room_number,
                guest_name,
                username,
                password,
                profile_type,
                checkin_date,
                checkout_date,
                created_at,
                status,
                CASE 
                    WHEN status = 'active' AND checkout_date >= CURDATE() THEN 'Ativo'
                    WHEN status = 'active' AND checkout_date < CURDATE() THEN 'Expirado'
                    WHEN status = 'disabled' THEN 'Desabilitado'
                    ELSE 'Expirado'
                END as status_display
            FROM hotel_guests 
            WHERE DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Método para validar credenciais antes de gerar
     */
    public function validateCredentialGeneration($roomNumber, $guestName, $checkinDate, $checkoutDate) {
        $errors = [];
        
        // Validar número do quarto
        if (empty(trim($roomNumber))) {
            $errors[] = "Número do quarto é obrigatório";
        } elseif (strlen(trim($roomNumber)) > 10) {
            $errors[] = "Número do quarto deve ter no máximo 10 caracteres";
        }
        
        // Validar nome do hóspede
        if (empty(trim($guestName))) {
            $errors[] = "Nome do hóspede é obrigatório";
        } elseif (strlen(trim($guestName)) > 100) {
            $errors[] = "Nome do hóspede deve ter no máximo 100 caracteres";
        }
        
        // Validar datas
        $checkin = new DateTime($checkinDate);
        $checkout = new DateTime($checkoutDate);
        $today = new DateTime();
        
        if ($checkin > $checkout) {
            $errors[] = "Data de check-in deve ser anterior ao check-out";
        }
        
        if ($checkout < $today) {
            $errors[] = "Data de check-out não pode ser no passado";
        }
        
        // Verificar se já existe usuário ativo para o quarto
        $stmt = $this->db->prepare("
            SELECT username FROM hotel_guests 
            WHERE room_number = ? AND status = 'active'
        ");
        $stmt->execute([trim($roomNumber)]);
        
        if ($stmt->fetch()) {
            $errors[] = "Já existe um usuário ativo para o quarto {$roomNumber}";
        }
        
        return $errors;
    }
}
?>