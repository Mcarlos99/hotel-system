<?php
// mikrotik_manager_robusto.php - Versão robusta com múltiplos fallbacks

class MikroTikRobustManager {
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
    
    // Conectar com múltiplas tentativas e configurações
    public function connect() {
        $attempts = [
            ['user' => $this->username, 'pass' => $this->password],
            ['user' => 'admin', 'pass' => ''],
            ['user' => 'admin', 'pass' => $this->password],
            ['user' => $this->username, 'pass' => '']
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
                $this->log('WARNING', "Falha na tentativa de conexão", [
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
        
        // Criar socket com timeout menor
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            throw new Exception("Não foi possível criar socket");
        }
        
        // Configurar timeouts mais baixos
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 3, "usec" => 0));
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 3, "usec" => 0));
        
        if (!socket_connect($this->socket, $this->host, $this->port)) {
            $error = socket_strerror(socket_last_error($this->socket));
            socket_close($this->socket);
            throw new Exception("Falha na conexão: " . $error);
        }
        
        // Tentar login
        try {
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
            throw new Exception("Não conectado ao MikroTik");
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
            
            $this->log('INFO', "Usuário criado com sucesso", [
                'username' => $username,
                'profile' => $profile
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Erro ao criar usuário", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            // Tentar reconectar e repetir
            if ($this->reconnect()) {
                return $this->createHotspotUser($username, $password, $profile, $timeLimit);
            }
            
            throw $e;
        }
    }
    
    public function removeHotspotUser($username) {
        if (!$this->connected) {
            throw new Exception("Não conectado ao MikroTik");
        }
        
        try {
            // Buscar usuário
            $this->write('/ip/hotspot/user/print', ['?name=' . $username]);
            $users = $this->read();
            
            if (empty($users) || isset($users[0]['!trap'])) {
                throw new Exception("Usuário não encontrado");
            }
            
            if (!isset($users[0]['!re']['.id'])) {
                throw new Exception("ID do usuário não encontrado");
            }
            
            $userId = $users[0]['!re']['.id'];
            
            // Remover usuário
            $this->write('/ip/hotspot/user/remove', ['=.id=' . $userId]);
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                throw new Exception("Erro ao remover usuário");
            }
            
            $this->log('INFO', "Usuário removido", ['username' => $username]);
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Erro ao remover usuário", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            // Tentar reconectar e repetir
            if ($this->reconnect()) {
                return $this->removeHotspotUser($username);
            }
            
            throw $e;
        }
    }
    
    public function getActiveUsers() {
        if (!$this->connected) {
            return [];
        }
        
        try {
            $this->write('/ip/hotspot/active/print');
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                return [];
            }
            
            return $response;
            
        } catch (Exception $e) {
            $this->log('WARNING', "Erro ao obter usuários ativos", ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    public function listHotspotUsers() {
        if (!$this->connected) {
            return [];
        }
        
        try {
            $this->write('/ip/hotspot/user/print');
            $response = $this->read();
            
            if (isset($response[0]['!trap'])) {
                return [];
            }
            
            return $response;
            
        } catch (Exception $e) {
            $this->log('WARNING', "Erro ao listar usuários", ['error' => $e->getMessage()]);
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
            $this->log('WARNING', "Erro ao desconectar usuário", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function reconnect() {
        try {
            $this->disconnect();
            $this->connected = false;
            
            // Aguardar um pouco antes de reconectar
            sleep(1);
            
            $this->connect();
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', "Falha na reconexão", ['error' => $e->getMessage()]);
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
            throw new Exception("Erro ao escrever no socket: " . socket_strerror(socket_last_error($this->socket)));
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
                    throw new Exception("Erro ao ler do socket: " . socket_strerror(socket_last_error($this->socket)));
                }
                
                $response[] = $data;
            }
        } catch (Exception $e) {
            // Se houve erro na leitura, tentar reconectar
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

// Classe sistema hotel adaptada para usar o gerenciador robusto
class HotelHotspotSystemRobust extends HotelHotspotSystem {
    public function __construct($mikrotikConfig, $dbConfig) {
        // Inicializar banco e logger primeiro
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
        
        $this->logger = new Logger([
            'log_file' => 'logs/hotel_system.log',
            'log_level' => 'INFO',
            'max_log_size' => 10485760,
            'backup_logs' => true
        ]);
        
        // Usar gerenciador robusto
        $this->mikrotik = new MikroTikRobustManager(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port'] ?? 8728
        );
        
        $this->mikrotik->setLogger($this->logger);
        
        $this->createTables();
        $this->logger->info("Sistema iniciado com gerenciador robusto");
    }
}
?>