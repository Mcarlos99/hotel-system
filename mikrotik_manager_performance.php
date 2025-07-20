<?php
/**
 * mikrotik_manager_performance.php - Versão Otimizada para Performance
 * 
 * CORREÇÕES DE PERFORMANCE:
 * ✅ Timeout otimizado e adaptativo
 * ✅ Cache de conexão para operações sequenciais
 * ✅ Parser simplificado e mais rápido
 * ✅ Operações síncronas otimizadas
 * ✅ Redução de iterações desnecessárias
 * ✅ Timeout específico para cada operação
 */

class HotelLoggerOptimized {
    private $logFile;
    private $enabled;
    
    public function __construct($logFile = 'logs/hotel_system.log', $enabled = true) {
        $this->logFile = $logFile;
        $this->enabled = $enabled;
        
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function log($level, $message, $context = []) {
        if (!$this->enabled) return;
        
        $message = $this->cleanMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        error_log("[HOTEL_SYSTEM] [{$level}] {$message}");
    }
    
    private function cleanMessage($message) {
        $replacements = [
            'ç' => 'c', 'Ç' => 'C', 'ã' => 'a', 'Ã' => 'A', 'á' => 'a', 'Á' => 'A',
            'à' => 'a', 'À' => 'A', 'â' => 'a', 'Â' => 'A', 'é' => 'e', 'É' => 'E',
            'ê' => 'e', 'Ê' => 'E', 'í' => 'i', 'Í' => 'I', 'ó' => 'o', 'Ó' => 'O',
            'ô' => 'o', 'Ô' => 'O', 'õ' => 'o', 'Õ' => 'O', 'ú' => 'u', 'Ú' => 'U',
            '✅' => '[OK]', '❌' => '[ERRO]', '⚠️' => '[AVISO]', '🎉' => '[SUCESSO]'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
    
    public function info($message, $context = []) { $this->log('INFO', $message, $context); }
    public function error($message, $context = []) { $this->log('ERROR', $message, $context); }
    public function warning($message, $context = []) { $this->log('WARNING', $message, $context); }
    public function debug($message, $context = []) { $this->log('DEBUG', $message, $context); }
}

/**
 * Classe MikroTik otimizada para performance
 */
class MikroTikHotspotManagerOptimized {
    private $host;
    private $username;
    private $password;
    private $port;
    private $socket;
    private $connected = false;
    private $logger;
    
    // OTIMIZAÇÃO: Timeouts específicos por operação
    private $timeouts = [
        'connect' => 5,      // Conexão rápida
        'list' => 8,         // Listagem moderada
        'remove' => 6,       // Remoção rápida
        'create' => 4,       // Criação rápida
        'active' => 3        // Usuários ativos rápido
    ];
    
    // OTIMIZAÇÃO: Cache de conexão
    private $connectionCache = null;
    private $cacheExpiry = 0;
    
    public function __construct($host, $username, $password, $port = 8728) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
        $this->logger = new HotelLoggerOptimized();
        
        $this->logger->info("MikroTik Manager Otimizado inicializado", [
            'host' => $host,
            'port' => $port,
            'timeouts' => $this->timeouts
        ]);
    }
    
    /**
     * OTIMIZADO: Conexão com cache e timeout adaptativo
     */
    public function connect($operation = 'default') {
        $startTime = microtime(true);
        
        // Usar cache se disponível e válido
        if ($this->connectionCache && time() < $this->cacheExpiry && $this->socket) {
            $this->logger->debug("Usando conexao em cache");
            $this->connected = true;
            return true;
        }
        
        $timeout = $this->timeouts[$operation] ?? 5;
        $this->logger->info("Conectando com timeout otimizado: {$timeout}s para operacao: {$operation}");
        
        if ($this->socket) {
            $this->disconnect();
        }
        
        // OTIMIZAÇÃO: Tentar apenas credenciais mais prováveis primeiro
        $quickAttempts = [
            ['user' => $this->username, 'pass' => $this->password],
            ['user' => 'admin', 'pass' => '']
        ];
        
        foreach ($quickAttempts as $i => $attempt) {
            try {
                if ($this->tryConnectFast($attempt['user'], $attempt['pass'], $timeout)) {
                    $this->connected = true;
                    $this->connectionCache = true;
                    $this->cacheExpiry = time() + 60; // Cache por 1 minuto
                    
                    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                    $this->logger->info("Conectado em {$responseTime}ms - usuario: {$attempt['user']}");
                    return true;
                }
            } catch (Exception $e) {
                $this->logger->debug("Tentativa rapida " . ($i + 1) . " falhou: " . $e->getMessage());
                continue;
            }
        }
        
        throw new Exception("Falha na conexao rapida");
    }
    
    /**
     * OTIMIZADO: Conexão rápida com timeout específico
     */
    private function tryConnectFast($username, $password, $timeout) {
        if (!extension_loaded('sockets')) {
            throw new Exception("Extensao sockets nao disponivel");
        }
        
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            throw new Exception("Erro ao criar socket");
        }
        
        // OTIMIZAÇÃO: Timeouts mais agressivos
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => $timeout, "usec" => 0));
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => $timeout, "usec" => 0));
        
        if (!socket_connect($this->socket, $this->host, $this->port)) {
            socket_close($this->socket);
            $this->socket = null;
            throw new Exception("Conexao TCP falhou");
        }
        
        try {
            // Login otimizado
            $this->writeCommand('/login', [], $timeout);
            $response = $this->readResponse($timeout);
            
            $loginData = ['=name=' . $username];
            if (!empty($password)) {
                $loginData[] = '=password=' . $password;
            }
            
            $this->writeCommand('/login', $loginData, $timeout);
            $response = $this->readResponse($timeout);
            
            if ($this->hasError($response)) {
                throw new Exception("Credenciais invalidas");
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
    
    /**
     * OTIMIZADO: Listagem rápida com parser simplificado
     */
    public function listHotspotUsers() {
        $startTime = microtime(true);
        
        if (!$this->connected) {
            $this->connect('list');
        }
        
        try {
            $this->logger->info("Iniciando listagem otimizada");
            
            $this->writeCommand('/ip/hotspot/user/print', [], $this->timeouts['list']);
            $response = $this->readResponse($this->timeouts['list']);
            
            // OTIMIZAÇÃO: Parser simplificado e mais rápido
            $users = $this->parseUsersOptimized($response);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info("Listagem concluida em {$responseTime}ms - {count($users)} usuarios");
            
            return $users;
            
        } catch (Exception $e) {
            $this->logger->error("Erro na listagem otimizada: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * OTIMIZADO: Parser mais rápido e direto
     */
    private function parseUsersOptimized($response) {
        $users = [];
        $currentUser = [];
        
        foreach ($response as $line) {
            $line = trim($line);
            
            if ($line === '!re') {
                if (!empty($currentUser)) {
                    $users[] = $currentUser;
                }
                $currentUser = [];
                continue;
            }
            
            if ($line === '!done') {
                if (!empty($currentUser)) {
                    $users[] = $currentUser;
                }
                break;
            }
            
            // OTIMIZAÇÃO: Extrair apenas campos essenciais
            if (strpos($line, '=') === 0) {
                $parts = explode('=', substr($line, 1), 2);
                if (count($parts) === 2) {
                    $key = $parts[0];
                    $value = $parts[1];
                    
                    // Só extrair campos necessários para performance
                    if (in_array($key, ['.id', 'name', 'password', 'profile', 'server'])) {
                        $currentUser[$key] = $value;
                    }
                }
            }
        }
        
        return $users;
    }
    
    /**
     * OTIMIZADO: Remoção ultra-rápida com método direto
     */
    public function removeHotspotUser($username) {
        $startTime = microtime(true);
        
        if (!$this->connected) {
            $this->connect('remove');
        }
        
        try {
            $this->logger->info("Iniciando remocao ultra-rapida: {$username}");
            
            // OTIMIZAÇÃO: Tentar remoção direta primeiro
            $removed = $this->fastRemoveByName($username);
            
            if (!$removed) {
                // Fallback: buscar ID e remover
                $this->logger->debug("Remocao direta falhou, buscando ID");
                $removed = $this->fallbackRemoveById($username);
            }
            
            if ($removed) {
                // OTIMIZAÇÃO: Verificação rápida opcional
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $this->logger->info("Usuario {$username} removido em {$responseTime}ms");
                return true;
            }
            
            $this->logger->error("Falha na remocao de {$username}");
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Erro na remocao: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * OTIMIZADO: Remoção direta super rápida
     */
    private function fastRemoveByName($username) {
        try {
            $this->logger->debug("Tentando remocao direta por nome");
            
            // Desconectar primeiro se ativo
            $this->quickDisconnectUser($username);
            
            // Remoção direta
            $this->writeCommand('/ip/hotspot/user/remove', ['?name=' . $username], $this->timeouts['remove']);
            $response = $this->readResponse($this->timeouts['remove']);
            
            if (!$this->hasError($response)) {
                $this->logger->debug("Remocao direta bem-sucedida");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->debug("Remocao direta falhou: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * OTIMIZADO: Fallback com busca rápida de ID
     */
    private function fallbackRemoveById($username) {
        try {
            $this->logger->debug("Buscando ID para remocao");
            
            // Busca rápida do usuário específico
            $this->writeCommand('/ip/hotspot/user/print', ['?name=' . $username], $this->timeouts['remove']);
            $response = $this->readResponse($this->timeouts['remove']);
            
            $userId = null;
            foreach ($response as $line) {
                if (strpos($line, '=.id=') === 0) {
                    $userId = substr($line, 5);
                    break;
                }
            }
            
            if ($userId) {
                $this->logger->debug("ID encontrado: {$userId}");
                
                $this->writeCommand('/ip/hotspot/user/remove', ['=.id=' . $userId], $this->timeouts['remove']);
                $response = $this->readResponse($this->timeouts['remove']);
                
                if (!$this->hasError($response)) {
                    $this->logger->debug("Remocao por ID bem-sucedida");
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->debug("Fallback falhou: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * OTIMIZADO: Desconexão rápida sem verificações extensas
     */
    private function quickDisconnectUser($username) {
        try {
            $this->writeCommand('/ip/hotspot/active/remove', ['?user=' . $username], 2); // Timeout curto
            $this->readResponse(2);
        } catch (Exception $e) {
            // Ignorar erros na desconexão para não atrasar remoção
        }
    }
    
    /**
     * OTIMIZADO: Criação rápida de usuário
     */
    public function createHotspotUser($username, $password, $profile = 'default', $timeLimit = '24:00:00') {
        if (!$this->connected) {
            $this->connect('create');
        }
        
        try {
            $this->logger->info("Criando usuario: {$username}");
            
            $this->writeCommand('/ip/hotspot/user/add', [
                '=name=' . $username,
                '=password=' . $password,
                '=profile=' . $profile,
                '=limit-uptime=' . $timeLimit
            ], $this->timeouts['create']);
            
            $response = $this->readResponse($this->timeouts['create']);
            
            if ($this->hasError($response)) {
                $errorMsg = $this->extractErrorMessage($response);
                throw new Exception("Erro ao criar usuario: {$errorMsg}");
            }
            
            $this->logger->info("Usuario {$username} criado rapidamente");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Erro na criacao: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * OTIMIZADO: Usuários ativos com timeout curto
     */
    public function getActiveUsers() {
        if (!$this->connected) {
            try {
                $this->connect('active');
            } catch (Exception $e) {
                return [];
            }
        }
        
        try {
            $this->writeCommand('/ip/hotspot/active/print', [], $this->timeouts['active']);
            $response = $this->readResponse($this->timeouts['active']);
            
            return $this->parseActiveUsersOptimized($response);
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao obter usuarios ativos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * OTIMIZADO: Parser de usuários ativos simplificado
     */
    private function parseActiveUsersOptimized($response) {
        $sessions = [];
        $currentSession = [];
        
        foreach ($response as $line) {
            $line = trim($line);
            
            if ($line === '!re') {
                if (!empty($currentSession)) {
                    $sessions[] = $currentSession;
                }
                $currentSession = [];
                continue;
            }
            
            if ($line === '!done') {
                if (!empty($currentSession)) {
                    $sessions[] = $currentSession;
                }
                break;
            }
            
            if (strpos($line, '=') === 0) {
                $parts = explode('=', substr($line, 1), 2);
                if (count($parts) === 2) {
                    $key = $parts[0];
                    $value = $parts[1];
                    
                    // Só campos essenciais
                    if (in_array($key, ['.id', 'user', 'address'])) {
                        $currentSession[$key] = $value;
                    }
                }
            }
        }
        
        return $sessions;
    }
    
    /**
     * OTIMIZADO: Estatísticas rápidas
     */
    public function getHotspotStats() {
        try {
            $startTime = microtime(true);
            
            // Obter contadores rapidamente
            $allUsers = $this->listHotspotUsers();
            $activeUsers = $this->getActiveUsers();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'total_users' => count($allUsers),
                'active_users' => count($activeUsers),
                'response_time' => $responseTime,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'total_users' => 0,
                'active_users' => 0,
                'response_time' => 0,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * OTIMIZADO: Teste de conexão ultra-rápido
     */
    public function testConnection() {
        $startTime = microtime(true);
        
        try {
            $this->connect('connect');
            
            // Teste mínimo
            $this->writeCommand('/system/identity/print', [], 3);
            $response = $this->readResponse(3);
            
            $this->disconnect();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'message' => 'Conexao bem-sucedida',
                'response_time' => $responseTime
            ];
            
        } catch (Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response_time' => $responseTime
            ];
        }
    }
    
    /**
     * OTIMIZADO: Comandos de comunicação com timeout específico
     */
    private function writeCommand($command, $arguments = [], $timeout = 5) {
        if (!$this->socket) {
            throw new Exception("Socket nao disponivel");
        }
        
        $data = $this->encodeLength(strlen($command)) . $command;
        
        foreach ($arguments as $arg) {
            $data .= $this->encodeLength(strlen($arg)) . $arg;
        }
        
        $data .= $this->encodeLength(0);
        
        $bytesWritten = socket_write($this->socket, $data);
        if ($bytesWritten === false) {
            throw new Exception("Erro ao escrever");
        }
    }
    
    private function readResponse($timeout = 5) {
        if (!$this->socket) {
            throw new Exception("Socket nao disponivel");
        }
        
        $response = [];
        $startTime = time();
        $maxIterations = 50; // OTIMIZAÇÃO: Reduzido de 200 para 50
        $iterations = 0;
        
        while ($iterations < $maxIterations) {
            if ((time() - $startTime) > $timeout) {
                break; // Timeout suave
            }
            
            try {
                $length = $this->readLength();
                
                if ($length == 0) {
                    break;
                }
                
                if ($length > 0 && $length < 50000) { // OTIMIZAÇÃO: Limite menor
                    $data = $this->readData($length);
                    if ($data !== false && $data !== '') {
                        $response[] = $data;
                    }
                } else {
                    break;
                }
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'timeout') !== false) {
                    break;
                }
                throw $e;
            }
            
            $iterations++;
        }
        
        return $response;
    }
    
    private function readLength() {
        $byte = @socket_read($this->socket, 1);
        
        if ($byte === false || $byte === '') {
            throw new Exception("timeout na leitura");
        }
        
        $length = ord($byte);
        
        if ($length < 0x80) {
            return $length;
        } elseif ($length < 0xC0) {
            $byte = @socket_read($this->socket, 1);
            if ($byte === false) throw new Exception("Erro na leitura");
            return (($length & 0x3F) << 8) + ord($byte);
        } elseif ($length < 0xE0) {
            $bytes = @socket_read($this->socket, 2);
            if ($bytes === false || strlen($bytes) < 2) throw new Exception("Erro na leitura");
            return (($length & 0x1F) << 16) + (ord($bytes[0]) << 8) + ord($bytes[1]);
        }
        
        return 0;
    }
    
    private function readData($length) {
        if ($length <= 0) return '';
        
        $data = '';
        $remaining = $length;
        $attempts = 0;
        $maxAttempts = 20; // OTIMIZAÇÃO: Reduzido de 50 para 20
        
        while ($remaining > 0 && $attempts < $maxAttempts) {
            $chunk = @socket_read($this->socket, $remaining);
            
            if ($chunk === false) {
                throw new Exception("Erro na leitura");
            }
            
            if ($chunk === '') {
                $attempts++;
                usleep(25000); // OTIMIZAÇÃO: Reduzido de 50ms para 25ms
                continue;
            }
            
            $data .= $chunk;
            $remaining -= strlen($chunk);
            $attempts = 0;
        }
        
        if ($remaining > 0) {
            throw new Exception("Dados incompletos");
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
        }
        
        return chr(0xE0 | ($length >> 24)) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
    }
    
    private function hasError($response) {
        foreach ($response as $line) {
            if (strpos($line, '!trap') !== false || strpos($line, '!fatal') !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function extractErrorMessage($response) {
        foreach ($response as $line) {
            if (strpos($line, '=message=') === 0) {
                return substr($line, 9);
            }
        }
        return 'Erro desconhecido';
    }
    
    public function disconnect() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
        $this->connectionCache = null;
        $this->cacheExpiry = 0;
    }
    
    /**
     * NOVO: Health check otimizado
     */
    public function quickHealthCheck() {
        $startTime = microtime(true);
        
        $health = [
            'timestamp' => date('Y-m-d H:i:s'),
            'connection' => false,
            'user_count' => 0,
            'response_time' => 0,
            'status' => 'unknown'
        ];
        
        try {
            $this->connect('connect');
            $health['connection'] = true;
            
            // Teste ultra-rápido - apenas contar usuários
            $users = $this->listHotspotUsers();
            $health['user_count'] = count($users);
            
            $this->disconnect();
            
        } catch (Exception $e) {
            $health['error'] = $e->getMessage();
        }
        
        $health['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
        
        // Determinar status
        if (!$health['connection']) {
            $health['status'] = 'offline';
        } elseif ($health['response_time'] > 5000) {
            $health['status'] = 'slow';
        } elseif ($health['response_time'] > 2000) {
            $health['status'] = 'moderate';
        } else {
            $health['status'] = 'fast';
        }
        
        return $health;
    }
}

/**
 * Classe compatível otimizada - substitui a anterior
 */
class MikroTikHotspotManagerFixed extends MikroTikHotspotManagerOptimized {
    
    public function __construct($host, $username, $password, $port = 8728) {
        parent::__construct($host, $username, $password, $port);
    }
    
    // Todos os métodos herdados da classe otimizada
    public function disconnectUser($username) {
        return $this->quickDisconnectUser($username);
    }
    
    public function healthCheck() {
        return $this->quickHealthCheck();
    }
}

/**
 * FUNÇÕES AUXILIARES OTIMIZADAS
 */

function testMikroTikOptimized($mikrotikConfig) {
    try {
        $mikrotik = new MikroTikHotspotManagerFixed(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        return $mikrotik->testConnection();
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'response_time' => 0
        ];
    }
}

function quickHealthCheck($mikrotikConfig) {
    try {
        $mikrotik = new MikroTikHotspotManagerFixed(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        return $mikrotik->quickHealthCheck();
    } catch (Exception $e) {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'connection' => false,
            'error' => $e->getMessage(),
            'response_time' => 0,
            'status' => 'error'
        ];
    }
}

function fastRemoveUser($mikrotikConfig, $username) {
    try {
        $mikrotik = new MikroTikHotspotManagerFixed(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        $startTime = microtime(true);
        
        $mikrotik->connect('remove');
        $result = $mikrotik->removeHotspotUser($username);
        $mikrotik->disconnect();
        
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        return [
            'success' => $result,
            'response_time' => $responseTime,
            'message' => $result ? 'Removido rapidamente' : 'Falha na remocao'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'response_time' => 0
        ];
    }
}

/**
 * NOVA: Função de benchmark para medir performance
 */
function benchmarkMikroTik($mikrotikConfig, $iterations = 3) {
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'iterations' => $iterations,
        'tests' => [],
        'averages' => []
    ];
    
    $operations = ['connect', 'list', 'health'];
    
    foreach ($operations as $operation) {
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            try {
                $mikrotik = new MikroTikHotspotManagerFixed(
                    $mikrotikConfig['host'],
                    $mikrotikConfig['username'],
                    $mikrotikConfig['password'],
                    $mikrotikConfig['port']
                );
                
                switch ($operation) {
                    case 'connect':
                        $mikrotik->testConnection();
                        break;
                    case 'list':
                        $mikrotik->connect('list');
                        $mikrotik->listHotspotUsers();
                        $mikrotik->disconnect();
                        break;
                    case 'health':
                        $mikrotik->quickHealthCheck();
                        break;
                }
                
                $time = round((microtime(true) - $startTime) * 1000, 2);
                $times[] = $time;
                
            } catch (Exception $e) {
                $times[] = 99999; // Penalidade por erro
            }
        }
        
        $results['tests'][$operation] = $times;
        $results['averages'][$operation] = round(array_sum($times) / count($times), 2);
    }
    
    // Score geral
    $totalAvg = array_sum($results['averages']) / count($results['averages']);
    
    if ($totalAvg < 1000) {
        $results['performance'] = 'excelente';
    } elseif ($totalAvg < 3000) {
        $results['performance'] = 'boa';
    } elseif ($totalAvg < 8000) {
        $results['performance'] = 'moderada';
    } else {
        $results['performance'] = 'lenta';
    }
    
    $results['total_average'] = round($totalAvg, 2);
    
    return $results;
}

/**
 * NOVA: Otimizador automático de configurações
 */
function optimizeConfiguration($mikrotikConfig) {
    $optimizations = [
        'timestamp' => date('Y-m-d H:i:s'),
        'recommendations' => [],
        'applied' => []
    ];
    
    // Testar performance atual
    $currentPerf = benchmarkMikroTik($mikrotikConfig, 2);
    $optimizations['current_performance'] = $currentPerf['total_average'];
    
    // Recomendações baseadas na performance
    if ($currentPerf['total_average'] > 8000) {
        $optimizations['recommendations'][] = [
            'issue' => 'Performance muito lenta (>' . $currentPerf['total_average'] . 'ms)',
            'solutions' => [
                'Verificar latencia de rede com ping',
                'Verificar carga do MikroTik',
                'Considerar conexao local',
                'Verificar configuracao de firewall'
            ]
        ];
    }
    
    if ($currentPerf['averages']['connect'] > 3000) {
        $optimizations['recommendations'][] = [
            'issue' => 'Conexao lenta (' . $currentPerf['averages']['connect'] . 'ms)',
            'solutions' => [
                'Verificar resolucao DNS',
                'Usar IP direto em vez de hostname',
                'Verificar MTU da rede',
                'Testar com cabo direto'
            ]
        ];
    }
    
    if ($currentPerf['averages']['list'] > 5000) {
        $optimizations['recommendations'][] = [
            'issue' => 'Listagem lenta (' . $currentPerf['averages']['list'] . 'ms)',
            'solutions' => [
                'Reduzir numero de usuarios hotspot',
                'Limpar usuarios expirados',
                'Otimizar memoria do MikroTik',
                'Usar filtros especificos'
            ]
        ];
    }
    
    return $optimizations;
}

/**
 * NOVA: Monitor de performance em tempo real
 */
function monitorPerformance($mikrotikConfig, $duration = 60) {
    $monitor = [
        'start_time' => date('Y-m-d H:i:s'),
        'duration' => $duration,
        'samples' => [],
        'stats' => []
    ];
    
    $startTime = time();
    $sampleCount = 0;
    
    while ((time() - $startTime) < $duration) {
        $sampleStart = microtime(true);
        
        try {
            $health = quickHealthCheck($mikrotikConfig);
            
            $sample = [
                'timestamp' => date('H:i:s'),
                'response_time' => $health['response_time'],
                'status' => $health['status'],
                'connection' => $health['connection']
            ];
            
            $monitor['samples'][] = $sample;
            $sampleCount++;
            
        } catch (Exception $e) {
            $monitor['samples'][] = [
                'timestamp' => date('H:i:s'),
                'response_time' => 99999,
                'status' => 'error',
                'connection' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Aguardar próxima amostra (5 segundos)
        sleep(5);
    }
    
    // Calcular estatísticas
    $responseTimes = array_column($monitor['samples'], 'response_time');
    $responseTimes = array_filter($responseTimes, function($time) {
        return $time < 99999; // Remover erros
    });
    
    if (!empty($responseTimes)) {
        $monitor['stats'] = [
            'min' => min($responseTimes),
            'max' => max($responseTimes),
            'avg' => round(array_sum($responseTimes) / count($responseTimes), 2),
            'samples' => count($responseTimes),
            'errors' => $sampleCount - count($responseTimes)
        ];
    }
    
    $monitor['end_time'] = date('Y-m-d H:i:s');
    
    return $monitor;
}

/**
 * COMENTÁRIOS DE OTIMIZAÇÃO:
 * 
 * MELHORIAS DE PERFORMANCE IMPLEMENTADAS:
 * 
 * 1. ✅ TIMEOUTS ESPECÍFICOS: Cada operação tem timeout otimizado
 *    - Conexão: 5s (rápida)
 *    - Listagem: 8s (moderada) 
 *    - Remoção: 6s (rápida)
 *    - Criação: 4s (muito rápida)
 *    - Ativos: 3s (ultra-rápida)
 * 
 * 2. ✅ CACHE DE CONEXÃO: Reutiliza conexão por 1 minuto
 * 
 * 3. ✅ PARSER SIMPLIFICADO: Extrai apenas campos essenciais
 * 
 * 4. ✅ REMOÇÃO DIRETA: Tenta remoção por nome primeiro
 * 
 * 5. ✅ ITERAÇÕES REDUZIDAS: De 200 para 50 iterações máximas
 * 
 * 6. ✅ SLEEP OTIMIZADO: De 50ms para 25ms entre tentativas
 * 
 * 7. ✅ LIMITE DE DADOS: Máximo 50KB por resposta
 * 
 * 8. ✅ DESCONEXÃO RÁPIDA: Não espera confirmação
 * 
 * 9. ✅ TENTATIVAS REDUZIDAS: De 50 para 20 tentativas
 * 
 * 10. ✅ CREDENCIAIS PRIORIZADAS: Testa as mais prováveis primeiro
 * 
 * RESULTADO ESPERADO:
 * - Tempo de resposta: 1-3 segundos (vs 10+ anteriores)
 * - Conexão: < 1 segundo
 * - Listagem: < 2 segundos  
 * - Remoção: < 3 segundos
 * - Health check: < 1 segundo
 * 
 * MONITORAMENTO:
 * - benchmarkMikroTik(): Testa performance
 * - optimizeConfiguration(): Recomendações automáticas
 * - monitorPerformance(): Monitor em tempo real
 * - quickHealthCheck(): Verificação rápida
 */

?>