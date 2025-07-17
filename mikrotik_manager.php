<?php
// mikrotik_manager.php - Versão CORRIGIDA com timeout e prevenção de loops infinitos

// Classe MikroTik com timeout robusto
class MikroTikHotspotManager {
    private $host;
    private $username;
    private $password;
    private $port;
    private $socket;
    private $connected = false;
    private $logger;
    private $timeout = 10; // Timeout em segundos
    
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
        // Sempre fazer log de erro também
        error_log("MikroTik [{$level}]: {$message}");
    }
    
    // Conectar com timeout e múltiplas tentativas
    public function connect() {
        $this->log('INFO', "Tentando conectar em {$this->host}:{$this->port}");
        
        // Limpar conexão anterior se existir
        if ($this->socket) {
            $this->disconnect();
        }
        
        // Tentativas com diferentes credenciais
        $attempts = [
            ['user' => $this->username, 'pass' => $this->password],
            ['user' => 'admin', 'pass' => ''],
            ['user' => 'admin', 'pass' => $this->password]
        ];
        
        foreach ($attempts as $attempt) {
            try {
                if ($this->tryConnect($attempt['user'], $attempt['pass'])) {
                    $this->connected = true;
                    $this->log('INFO', "Conectado com usuário: {$attempt['user']}");
                    return true;
                }
            } catch (Exception $e) {
                $this->log('WARNING', "Falha na tentativa com {$attempt['user']}: " . $e->getMessage());
                continue;
            }
        }
        
        throw new Exception("Não foi possível conectar após múltiplas tentativas");
    }
    
    private function tryConnect($username, $password) {
        if (!extension_loaded('sockets')) {
            throw new Exception("Extensão 'sockets' não está disponível");
        }
        
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            throw new Exception("Não foi possível criar socket");
        }
        
        // Timeouts agressivos para evitar travamentos
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => $this->timeout, "usec" => 0));
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => $this->timeout, "usec" => 0));
        
        if (!socket_connect($this->socket, $this->host, $this->port)) {
            $error = socket_strerror(socket_last_error($this->socket));
            socket_close($this->socket);
            $this->socket = null;
            throw new Exception("Falha na conexão: " . $error);
        }
        
        try {
            // Login com timeout
            $this->writeWithTimeout('/login');
            $response = $this->readWithTimeout();
            
            if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
                throw new Exception("Erro no protocolo de login");
            }
            
            // Enviar credenciais
            $loginData = ['=name=' . $username];
            if (!empty($password)) {
                $loginData[] = '=password=' . $password;
            }
            
            $this->writeWithTimeout('/login', $loginData);
            $response = $this->readWithTimeout();
            
            if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
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
            $this->writeWithTimeout('/ip/hotspot/user/add', [
                '=name=' . $username,
                '=password=' . $password,
                '=profile=' . $profile,
                '=limit-uptime=' . $timeLimit
            ]);
            
            $response = $this->readWithTimeout();
            
            if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
                $error = isset($response[0]) ? $response[0] : 'Erro desconhecido';
                throw new Exception("Erro ao criar usuário: " . $error);
            }
            
            $this->log('INFO', "Usuário criado: {$username}");
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Erro ao criar usuário {$username}: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * MÉTODO CORRIGIDO: Remove usuário com timeout e verificação robusta
     */
    public function removeHotspotUser($username) {
        if (!$this->connected) {
            $this->connect();
        }
        
        try {
            $this->log('INFO', "Procurando usuário: {$username}");
            
            // Buscar usuário com timeout
            $this->writeWithTimeout('/ip/hotspot/user/print', ['?name=' . $username]);
            $users = $this->readWithTimeout();
            
            $this->log('INFO', "Resposta da busca: " . json_encode($users));
            
            if (empty($users)) {
                $this->log('WARNING', "Usuário {$username} não encontrado (lista vazia)");
                return true; // Consideramos sucesso se não existe
            }
            
            // Verificar se há erro
            if (isset($users[0]) && strpos($users[0], '!trap') !== false) {
                throw new Exception("Erro ao buscar usuário: " . $users[0]);
            }
            
            // Procurar o ID do usuário na resposta
            $userId = null;
            foreach ($users as $line) {
                if (strpos($line, '=.id=') !== false) {
                    $userId = substr($line, 4); // Remove "=.id="
                    break;
                }
            }
            
            if (!$userId) {
                $this->log('WARNING', "ID do usuário {$username} não encontrado");
                return true; // Usuário não existe, consideramos removido
            }
            
            $this->log('INFO', "Removendo usuário ID: {$userId}");
            
            // Remover usuário
            $this->writeWithTimeout('/ip/hotspot/user/remove', ['=.id=' . $userId]);
            $response = $this->readWithTimeout();
            
            if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
                throw new Exception("Erro ao remover usuário: " . $response[0]);
            }
            
            $this->log('INFO', "Usuário {$username} removido com sucesso");
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Erro ao remover usuário {$username}: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function disconnectUser($username) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $this->writeWithTimeout('/ip/hotspot/active/print', ['?user=' . $username]);
            $activeUsers = $this->readWithTimeout();
            
            if (!empty($activeUsers)) {
                foreach ($activeUsers as $line) {
                    if (strpos($line, '=.id=') !== false) {
                        $sessionId = substr($line, 4);
                        $this->writeWithTimeout('/ip/hotspot/active/remove', ['=.id=' . $sessionId]);
                        $this->readWithTimeout();
                        $this->log('INFO', "Usuário {$username} desconectado");
                        return true;
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('WARNING', "Erro ao desconectar usuário {$username}: " . $e->getMessage());
            return false;
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
            $this->writeWithTimeout('/ip/hotspot/active/print');
            $response = $this->readWithTimeout();
            
            if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
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
            $this->writeWithTimeout('/ip/hotspot/user/print');
            $response = $this->readWithTimeout();
            
            if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
                return [];
            }
            
            return $response;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * MÉTODO CORRIGIDO: Write com timeout para evitar travamentos
     */
    private function writeWithTimeout($command, $arguments = []) {
        if (!$this->socket) {
            throw new Exception("Socket não disponível");
        }
        
        $data = $this->encodeLength(strlen($command)) . $command;
        
        foreach ($arguments as $arg) {
            $data .= $this->encodeLength(strlen($arg)) . $arg;
        }
        
        $data .= $this->encodeLength(0);
        
        $startTime = time();
        $result = socket_write($this->socket, $data);
        
        if ($result === false) {
            throw new Exception("Erro ao escrever no socket: " . socket_strerror(socket_last_error($this->socket)));
        }
        
        if ((time() - $startTime) > $this->timeout) {
            throw new Exception("Timeout ao escrever dados");
        }
    }
    
    /**
     * MÉTODO CORRIGIDO: Read com timeout e limite de iterações
     */
    private function readWithTimeout() {
        if (!$this->socket) {
            throw new Exception("Socket não disponível");
        }
        
        $response = [];
        $startTime = time();
        $maxIterations = 100; // Limite máximo de iterações para evitar loop infinito
        $iterations = 0;
        
        try {
            while (true) {
                // Verificar timeout
                if ((time() - $startTime) > $this->timeout) {
                    throw new Exception("Timeout na leitura - {$this->timeout}s excedidos");
                }
                
                // Verificar limite de iterações
                if (++$iterations > $maxIterations) {
                    throw new Exception("Limite de iterações excedido - possível loop infinito");
                }
                
                $length = $this->readLengthWithTimeout();
                
                if ($length == 0) {
                    break; // Fim da resposta
                }
                
                $data = $this->readDataWithTimeout($length);
                if ($data !== false && $data !== '') {
                    $response[] = $data;
                }
            }
        } catch (Exception $e) {
            $this->connected = false;
            throw $e;
        }
        
        return $response;
    }
    
    /**
     * MÉTODO CORRIGIDO: Leitura de comprimento com timeout
     */
    private function readLengthWithTimeout() {
        $byte = socket_read($this->socket, 1);
        
        if ($byte === false || $byte === '') {
            throw new Exception("Conexão perdida ou timeout na leitura de comprimento");
        }
        
        $length = ord($byte);
        
        if ($length < 0x80) {
            return $length;
        } elseif ($length < 0xC0) {
            $byte = socket_read($this->socket, 1);
            if ($byte === false) throw new Exception("Erro na leitura de comprimento 2");
            return (($length & 0x3F) << 8) + ord($byte);
        } elseif ($length < 0xE0) {
            $bytes = socket_read($this->socket, 2);
            if ($bytes === false || strlen($bytes) < 2) throw new Exception("Erro na leitura de comprimento 3");
            return (($length & 0x1F) << 16) + (ord($bytes[0]) << 8) + ord($bytes[1]);
        } elseif ($length < 0xF0) {
            $bytes = socket_read($this->socket, 3);
            if ($bytes === false || strlen($bytes) < 3) throw new Exception("Erro na leitura de comprimento 4");
            return (($length & 0x0F) << 24) + (ord($bytes[0]) << 16) + (ord($bytes[1]) << 8) + ord($bytes[2]);
        }
        
        return 0;
    }
    
    /**
     * MÉTODO CORRIGIDO: Leitura de dados com timeout
     */
    private function readDataWithTimeout($length) {
        if ($length <= 0) {
            return '';
        }
        
        $data = '';
        $remaining = $length;
        $attempts = 0;
        $maxAttempts = 10;
        
        while ($remaining > 0 && $attempts < $maxAttempts) {
            $chunk = socket_read($this->socket, $remaining);
            
            if ($chunk === false) {
                throw new Exception("Erro na leitura de dados");
            }
            
            if ($chunk === '') {
                $attempts++;
                usleep(10000); // Esperar 10ms
                continue;
            }
            
            $data .= $chunk;
            $remaining -= strlen($chunk);
            $attempts = 0; // Reset attempts on successful read
        }
        
        if ($remaining > 0) {
            throw new Exception("Dados incompletos: esperado {$length}, recebido " . strlen($data));
        }
        
        return $data;
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
    
    public function disconnect() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
        $this->log('INFO', "Desconectado do MikroTik");
    }
    
    public function isConnected() {
        return $this->connected && $this->socket !== null;
    }
    
    /**
     * NOVO: Método para testar conexão rapidamente
     */
    public function testConnection() {
        try {
            $this->connect();
            $this->writeWithTimeout('/system/identity/print');
            $response = $this->readWithTimeout();
            $this->disconnect();
            
            return [
                'success' => true,
                'message' => 'Conexão bem-sucedida',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Classe principal do sistema hotel com timeout robusto
class HotelHotspotSystem {
    protected $mikrotik;
    protected $db;
    
    public function __construct($mikrotikConfig, $dbConfig) {
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
        
        $this->createTables();
        error_log("Sistema HotelHotspot iniciado");
    }
    
    protected function generateSimpleUsername($roomNumber) {
        $cleanRoom = preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber);
        if (strlen($cleanRoom) > 6) {
            $cleanRoom = substr($cleanRoom, 0, 6);
        }
        
        $randomLength = rand(2, 3);
        $randomNumbers = '';
        for ($i = 0; $i < $randomLength; $i++) {
            $randomNumbers .= rand(0, 9);
        }
        
        $baseUsername = $cleanRoom . '-' . $randomNumbers;
        
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
    
    protected function generateSimplePassword() {
        $length = rand(3, 4);
        $password = '';
        
        $attempts = 0;
        do {
            $password = '';
            for ($i = 0; $i < $length; $i++) {
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
    
    private function isObviousPassword($password) {
        if (preg_match('/123|234|345|456|567|678|789/', $password)) return true;
        if (preg_match('/987|876|765|654|543|432|321/', $password)) return true;
        if (preg_match('/(.)\1\1+/', $password)) return true;
        
        $obviousPatterns = [
            '1234', '4321', '1111', '2222', '3333', '4444', '5555', 
            '6666', '7777', '8888', '9999', '0000', '1212', '1010',
            '2020', '1313', '1414', '1515', '1616', '1717', '1818', '1919'
        ];
        
        if (in_array($password, $obviousPatterns)) return true;
        
        return false;
    }
    
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        error_log("Gerando credenciais para quarto: {$roomNumber}");
        
        try {
            // Gerar credenciais simples
            $username = $this->generateSimpleUsername($roomNumber);
            $password = $this->generateSimplePassword();
            $timeLimit = $this->calculateTimeLimit($checkoutDate);
            
            // Tentar conectar ao MikroTik com timeout
            $mikrotikSuccess = false;
            try {
                $this->mikrotik->connect();
                $this->mikrotik->createHotspotUser($username, $password, $profileType, $timeLimit);
                $this->mikrotik->disconnect();
                $mikrotikSuccess = true;
                error_log("Usuário criado no MikroTik: {$username}");
            } catch (Exception $e) {
                error_log("Erro MikroTik na criação: " . $e->getMessage());
                // Continuar mesmo se falhar no MikroTik
            }
            
            // Salvar no banco (crítico)
            $stmt = $this->db->prepare("
                INSERT INTO hotel_guests (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $roomNumber,
                $guestName,
                $username,
                $password,
                $profileType,
                $checkinDate,
                $checkoutDate
            ]);
            
            if ($result) {
                $warning = $mikrotikSuccess ? '' : ' (Criado apenas no sistema - adicione manualmente no MikroTik)';
                
                return [
                    'success' => true,
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profileType,
                    'valid_until' => $checkoutDate,
                    'bandwidth' => '10M/2M',
                    'warning' => $warning
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Falha ao salvar no banco de dados'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Erro geral na geração: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro: ' . $e->getMessage()
            ];
        }
    }
    
    public function removeGuestAccess($roomNumber) {
        try {
            $stmt = $this->db->prepare("SELECT username FROM hotel_guests WHERE room_number = ? AND status = 'active'");
            $stmt->execute([$roomNumber]);
            $guest = $stmt->fetch();
            
            if ($guest) {
                $username = $guest['username'];
                error_log("Removendo usuário: {$username}");
                
                // Tentar remover do MikroTik com timeout
                try {
                    $this->mikrotik->connect();
                    $this->mikrotik->disconnectUser($username);
                    $this->mikrotik->removeHotspotUser($username);
                    $this->mikrotik->disconnect();
                    error_log("Removido do MikroTik: {$username}");
                } catch (Exception $e) {
                    error_log("Erro ao remover do MikroTik: " . $e->getMessage());
                    // Continuar mesmo se houver erro no MikroTik
                }
                
                $stmt = $this->db->prepare("UPDATE hotel_guests SET status = 'disabled' WHERE username = ?");
                $stmt->execute([$username]);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Hóspede não encontrado'];
            
        } catch (Exception $e) {
            error_log("Erro geral na remoção: " . $e->getMessage());
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
        
        // Estatísticas do MikroTik (com timeout)
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
                            error_log("Erro ao remover {$user['username']}: " . $e->getMessage());
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
        
        error_log("Tabelas do banco de dados verificadas/criadas");
    }
    
    /**
     * NOVO: Método para testar MikroTik rapidamente
     */
    public function testMikroTikConnection() {
        return $this->mikrotik->testConnection();
    }
    
    /**
     * NOVO: Método para debug de remoção específica
     */
    public function debugRemoveUser($username) {
        $debug = [];
        
        try {
            $debug['step1'] = "Conectando ao MikroTik...";
            $this->mikrotik->connect();
            $debug['step1'] = "✅ Conectado";
            
            $debug['step2'] = "Procurando usuário {$username}...";
            $this->mikrotik->writeWithTimeout('/ip/hotspot/user/print', ['?name=' . $username]);
            $users = $this->mikrotik->readWithTimeout();
            $debug['step2'] = "✅ Resposta recebida: " . json_encode($users);
            
            // Tentar encontrar ID
            $userId = null;
            foreach ($users as $line) {
                if (strpos($line, '=.id=') !== false) {
                    $userId = substr($line, 4);
                    break;
                }
            }
            
            if ($userId) {
                $debug['step3'] = "✅ ID encontrado: {$userId}";
                
                $debug['step4'] = "Removendo usuário...";
                $this->mikrotik->writeWithTimeout('/ip/hotspot/user/remove', ['=.id=' . $userId]);
                $response = $this->mikrotik->readWithTimeout();
                $debug['step4'] = "✅ Resposta da remoção: " . json_encode($response);
            } else {
                $debug['step3'] = "⚠️ Usuário não encontrado no MikroTik";
            }
            
            $this->mikrotik->disconnect();
            $debug['result'] = "✅ Processo concluído";
            
        } catch (Exception $e) {
            $debug['error'] = "❌ Erro: " . $e->getMessage();
            $debug['error_trace'] = $e->getTraceAsString();
        }
        
        return $debug;
    }
}

// Classe simplificada para logging básico
class SimpleLogger {
    private $logFile;
    
    public function __construct($logFile = 'logs/hotel_system.log') {
        $this->logFile = $logFile;
        
        // Criar diretório se não existir
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Também fazer log de erro do PHP
        error_log("[HOTEL_SYSTEM] [{$level}] {$message}");
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
}
?>