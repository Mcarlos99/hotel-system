<?php
/**
 * index.php - Sistema Hotel v4.1 - CONEXÕES CORRIGIDAS
 * 
 * VERSÃO: 4.1 - Critical Connection Fix
 * DATA: 2025-01-17
 * 
 * CORREÇÕES v4.1:
 * ✅ Verificação e criação automática de banco de dados
 * ✅ Configuração correta de DSN e conexão PDO
 * ✅ Validação de host MikroTik com ping/telnet test
 * ✅ Fallback gracioso quando serviços estão offline
 * ✅ Debug detalhado de conexões
 * ✅ Criação automática de usuário MySQL se necessário
 * ✅ Timeouts otimizados para conexões lentas
 * ✅ Headers de erro adequados
 */

// Configurações de erro e encoding otimizadas
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120); // Aumentado para 2 minutos
session_start();

// Encoding UTF-8 otimizado
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

// Headers de performance
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// CORREÇÃO 1: Verificar se config.php existe
if (!file_exists('config.php')) {
    die("
    <div style='font-family: Arial; background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px; border-left: 5px solid #e74c3c;'>
        <h3>❌ Erro Crítico</h3>
        <p><strong>Arquivo config.php não encontrado!</strong></p>
        <p><strong>Solução:</strong> Verifique se o arquivo config.php está na mesma pasta do index.php</p>
        <p><strong>Caminho esperado:</strong> " . dirname(__FILE__) . "/config.php</p>
    </div>
    ");
}

// Incluir configurações
require_once 'config.php';

// CORREÇÃO 2: Verificar se mikrotik_manager.php existe
if (!file_exists('mikrotik_manager.php')) {
    die("
    <div style='font-family: Arial; background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px; border-left: 5px solid #e74c3c;'>
        <h3>❌ Erro Crítico</h3>
        <p><strong>Arquivo mikrotik_manager.php não encontrado!</strong></p>
        <p><strong>Solução:</strong> Verifique se o arquivo mikrotik_manager.php está na mesma pasta</p>
        <p><strong>Caminho esperado:</strong> " . dirname(__FILE__) . "/mikrotik_manager.php</p>
    </div>
    ");
}

require_once 'mikrotik_manager.php';

// CORREÇÃO 3: Logger simples se HotelLogger não existir
if (!class_exists('HotelLogger')) {
    class SimpleLogger {
        public function info($message, $context = []) {
            error_log("[HOTEL_INFO] " . $message);
        }
        public function error($message, $context = []) {
            error_log("[HOTEL_ERROR] " . $message);
        }
        public function warning($message, $context = []) {
            error_log("[HOTEL_WARNING] " . $message);
        }
        public function debug($message, $context = []) {
            error_log("[HOTEL_DEBUG] " . $message);
        }
    }
}

/**
 * Classe do Sistema Hotel v4.1 - Conexões Corrigidas
 */
class HotelSystemV41 {
    protected $mikrotik;
    protected $db;
    protected $logger;
    protected $systemConfig;
    protected $userProfiles;
    protected $mikrotikConfig;
    protected $dbConfig;
    protected $startTime;
    protected $connectionErrors = [];
    
    public function __construct($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles) {
        $this->startTime = microtime(true);
        $this->systemConfig = $systemConfig;
        $this->userProfiles = $userProfiles;
        $this->mikrotikConfig = $mikrotikConfig;
        $this->dbConfig = $dbConfig;
        $this->connectionErrors = [];
        
        // Verificar se as configurações são válidas
        if (!is_array($mikrotikConfig)) {
            $this->mikrotikConfig = ['host' => '', 'port' => 8728, 'username' => '', 'password' => ''];
        }
        
        if (!is_array($dbConfig)) {
            $this->dbConfig = ['host' => 'localhost', 'database' => '', 'username' => '', 'password' => ''];
        }
        
        // Logger
        if (class_exists('HotelLogger')) {
            $this->logger = new HotelLogger();
        } else {
            $this->logger = new SimpleLogger();
        }
        
        $this->logger->info("Hotel System v4.1 iniciando...");
        
        // CORREÇÃO 4: Conectar ao banco com validação robusta
        $this->connectToDatabase();
        
        // CORREÇÃO 5: Conectar ao MikroTik com validação robusta
        $this->connectToMikroTik();
        
        // Log final
        $initTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->logger->info("Sistema Hotel v4.1 inicializado em {$initTime}ms");
    }
    
    /**
     * CORREÇÃO 4: Conexão robusta com banco de dados
     */
    private function connectToDatabase() {
        try {
            $this->logger->info("Conectando ao banco de dados...");
            
            // Verificar se MySQL está rodando
            if (!$this->isMySQLRunning()) {
                $this->connectionErrors[] = "Serviço MySQL não está rodando";
                $this->logger->error("MySQL não está rodando");
                $this->db = null;
                return;
            }
            
            // Tentar conectar sem especificar banco primeiro
            $hostOnlyDsn = "mysql:host={$this->dbConfig['host']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10
            ];
            
            try {
                $tempDb = new PDO($hostOnlyDsn, $this->dbConfig['username'], $this->dbConfig['password'], $options);
                $this->logger->info("Conexão MySQL estabelecida");
                
                // Verificar se banco existe
                $stmt = $tempDb->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
                $stmt->execute([$this->dbConfig['database']]);
                $dbExists = $stmt->fetchColumn();
                
                if (!$dbExists) {
                    // Criar banco de dados
                    $this->logger->info("Criando banco de dados: {$this->dbConfig['database']}");
                    $tempDb->exec("CREATE DATABASE `{$this->dbConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                
                $tempDb = null; // Fechar conexão temporária
                
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Access denied') !== false) {
                    // Tentar com usuário root sem senha
                    $this->logger->warning("Tentando com credenciais alternativas...");
                    try {
                        $tempDb = new PDO($hostOnlyDsn, 'root', '', $options);
                        
                        // Criar usuário se necessário
                        $this->createDatabaseUser($tempDb);
                        
                        // Criar banco
                        $tempDb->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $tempDb = null;
                        
                    } catch (PDOException $e2) {
                        throw new Exception("Erro de autenticação MySQL: " . $e2->getMessage());
                    }
                } else {
                    throw $e;
                }
            }
            
            // Agora conectar ao banco específico
            $fullDsn = "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['database']};charset=utf8mb4";
            $this->db = new PDO($fullDsn, $this->dbConfig['username'], $this->dbConfig['password'], $options);
            
            $this->logger->info("Conectado ao banco: {$this->dbConfig['database']}");
            
            // Criar tabelas
            $this->createTables();
            
        } catch (Exception $e) {
            $errorMsg = "Erro na conexão BD: " . $e->getMessage();
            $this->connectionErrors[] = $errorMsg;
            $this->logger->error($errorMsg);
            $this->db = null;
        }
    }
    
    /**
     * Verifica se MySQL está rodando
     */
    private function isMySQLRunning() {
        // Tentar conectar na porta 3306
        $socket = @fsockopen($this->dbConfig['host'], 3306, $errno, $errstr, 3);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }
    
    /**
     * Cria usuário do banco se necessário
     */
    private function createDatabaseUser($pdo) {
        try {
            $username = $this->dbConfig['username'];
            $password = $this->dbConfig['password'];
            $database = $this->dbConfig['database'];
            
            if (empty($password)) {
                $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'localhost'");
            } else {
                $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY '{$password}'");
            }
            
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'localhost'");
            $pdo->exec("FLUSH PRIVILEGES");
            
            $this->logger->info("Usuário do banco criado/atualizado: {$username}");
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao criar usuário: " . $e->getMessage());
        }
    }
    
    /**
     * CORREÇÃO 5: Conexão robusta com MikroTik
     */
    private function connectToMikroTik() {
        try {
            $this->logger->info("Conectando ao MikroTik...");
            
            // Garantir que as configurações estão definidas
            if (!isset($this->mikrotikConfig['host']) || empty($this->mikrotikConfig['host'])) {
                $errorMsg = "Host do MikroTik não configurado";
                $this->connectionErrors[] = $errorMsg;
                $this->logger->error($errorMsg);
                $this->mikrotik = null;
                return;
            }
            
            if (!isset($this->mikrotikConfig['port'])) {
                $this->mikrotikConfig['port'] = 8728; // Porta padrão
            }
            
            if (!isset($this->mikrotikConfig['username']) || empty($this->mikrotikConfig['username'])) {
                $errorMsg = "Usuário do MikroTik não configurado";
                $this->connectionErrors[] = $errorMsg;
                $this->logger->error($errorMsg);
                $this->mikrotik = null;
                return;
            }
            
            if (!isset($this->mikrotikConfig['password'])) {
                $this->mikrotikConfig['password'] = ''; // Senha vazia por padrão
            }
            
            // Verificar se host é acessível
            if (!$this->isMikroTikReachable()) {
                $errorMsg = "MikroTik não acessível em {$this->mikrotikConfig['host']}:{$this->mikrotikConfig['port']}";
                $this->connectionErrors[] = $errorMsg;
                $this->logger->warning($errorMsg);
                $this->mikrotik = null;
                return;
            }
            
            // Tentar diferentes classes MikroTik
            $mikrotikClasses = [
                'MikroTikHotspotManagerFixed',
                'MikroTikRawDataParser'
            ];
            
            foreach ($mikrotikClasses as $className) {
                if (class_exists($className)) {
                    try {
                        $this->mikrotik = new $className(
                            $this->mikrotikConfig['host'],
                            $this->mikrotikConfig['username'],
                            $this->mikrotikConfig['password'],
                            $this->mikrotikConfig['port']
                        );
                        
                        // Testar conexão
                        if (method_exists($this->mikrotik, 'testConnection')) {
                            $testResult = $this->mikrotik->testConnection();
                            if ($testResult['success']) {
                                $this->logger->info("MikroTik conectado usando classe: {$className}");
                                return;
                            }
                        } else {
                            // Se não tem método testConnection, assumir que está OK
                            $this->logger->info("MikroTik conectado usando classe: {$className} (sem teste)");
                            return;
                        }
                        
                    } catch (Exception $e) {
                        $this->logger->warning("Falha com classe {$className}: " . $e->getMessage());
                        continue;
                    }
                }
            }
            
            $errorMsg = "Falha em todas as tentativas de conexão MikroTik";
            $this->connectionErrors[] = $errorMsg;
            $this->logger->error($errorMsg);
            $this->mikrotik = null;
            
        } catch (Exception $e) {
            $errorMsg = "Erro na conexão MikroTik: " . $e->getMessage();
            $this->connectionErrors[] = $errorMsg;
            $this->logger->error($errorMsg);
            $this->mikrotik = null;
        }
    }
    
    /**
     * Verifica se MikroTik é acessível
     */
    private function isMikroTikReachable() {
        // Verificar se as configurações estão definidas
        if (!isset($this->mikrotikConfig['host']) || empty($this->mikrotikConfig['host'])) {
            $this->logger->warning("Host do MikroTik não configurado");
            return false;
        }
        
        $host = $this->mikrotikConfig['host'];
        $port = $this->mikrotikConfig['port'] ?? 8728;
        
        // Verificar se o host é um IP válido ou hostname
        if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            $this->logger->warning("Host inválido: {$host}");
            return false;
        }
        
        // Teste 1: Socket TCP
        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if ($socket) {
                fclose($socket);
                $this->logger->info("MikroTik acessível via TCP");
                return true;
            }
        } catch (Exception $e) {
            $this->logger->warning("Erro no teste TCP: " . $e->getMessage());
        }
        
        // Teste 2: Ping (se disponível)
        if (function_exists('exec') && !stripos(PHP_OS, 'win') === 0) {
            try {
                $pingResult = @exec("ping -c 1 -W 3 {$host} 2>&1", $output, $returnCode);
                if ($returnCode === 0) {
                    $this->logger->info("MikroTik acessível via ping");
                    return true;
                }
            } catch (Exception $e) {
                $this->logger->warning("Erro no ping: " . $e->getMessage());
            }
        }
        
        $this->logger->warning("MikroTik não acessível - host: {$host}, porta: {$port}");
        return false;
    }
    
    /**
     * Criação de tabelas (mantida do original)
     */
    protected function createTables() {
        if (!$this->db) {
            return false;
        }
        
        try {
            // Primeiro, criar tabela básica se não existir
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
                INDEX idx_username (username),
                INDEX idx_active_room (status, room_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            // Verificar se colunas adicionais existem e criar se necessário
            $this->addMissingColumns();
            
            // Tabela de logs
            $sql = "CREATE TABLE IF NOT EXISTS access_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                room_number VARCHAR(10) NOT NULL,
                action ENUM('login', 'logout', 'created', 'disabled', 'expired', 'sync_failed', 'sync_success') NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                response_time INT DEFAULT 0,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_room (room_number),
                INDEX idx_action (action),
                INDEX idx_date (created_at),
                INDEX idx_performance (response_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            $this->logger->info("Tabelas verificadas/criadas com sucesso");
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao criar tabelas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Adiciona colunas que podem estar faltando
     */
    private function addMissingColumns() {
        try {
            // Verificar se sync_status existe
            $stmt = $this->db->query("SHOW COLUMNS FROM hotel_guests LIKE 'sync_status'");
            if ($stmt->rowCount() == 0) {
                $this->db->exec("ALTER TABLE hotel_guests ADD COLUMN sync_status ENUM('synced', 'pending', 'failed') DEFAULT 'pending'");
                $this->logger->info("Coluna sync_status adicionada");
            }
            
            // Verificar se last_sync existe
            $stmt = $this->db->query("SHOW COLUMNS FROM hotel_guests LIKE 'last_sync'");
            if ($stmt->rowCount() == 0) {
                $this->db->exec("ALTER TABLE hotel_guests ADD COLUMN last_sync TIMESTAMP NULL");
                $this->logger->info("Coluna last_sync adicionada");
            }
            
            // Adicionar índices se não existirem
            try {
                $this->db->exec("ALTER TABLE hotel_guests ADD INDEX idx_sync (sync_status)");
            } catch (Exception $e) {
                // Índice já existe, ignorar
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao adicionar colunas: " . $e->getMessage());
        }
    }
    
    /**
     * Diagnóstico do sistema
     */
    public function getSystemDiagnostic() {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.1',
            'database' => $this->getDatabaseStatus(),
            'mikrotik' => $this->getMikroTikStatus(),
            'connection_errors' => $this->connectionErrors,
            'php_info' => $this->getPHPInfo(),
            'server_info' => $this->getServerInfo()
        ];
    }
    
    private function getDatabaseStatus() {
        if (!$this->db) {
            return [
                'connected' => false,
                'error' => 'Conexão não estabelecida',
                'host' => $this->dbConfig['host'],
                'database' => $this->dbConfig['database'],
                'mysql_running' => $this->isMySQLRunning()
            ];
        }
        
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM hotel_guests");
            $total = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT VERSION() as version");
            $version = $stmt->fetchColumn();
            
            return [
                'connected' => true,
                'host' => $this->dbConfig['host'],
                'database' => $this->dbConfig['database'],
                'version' => $version,
                'total_guests' => $total,
                'mysql_running' => true
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'host' => $this->dbConfig['host'],
                'database' => $this->dbConfig['database']
            ];
        }
    }
    
    private function getMikroTikStatus() {
        // Sempre retornar estrutura consistente
        $status = [
            'connected' => false,
            'host' => $this->mikrotikConfig['host'] ?? 'N/A',
            'port' => $this->mikrotikConfig['port'] ?? 'N/A',
            'error' => null,
            'message' => null,
            'reachable' => false
        ];
        
        if (!$this->mikrotik) {
            $status['error'] = 'Conexão não estabelecida';
            $status['reachable'] = $this->isMikroTikReachable();
            return $status;
        }
        
        try {
            if (method_exists($this->mikrotik, 'healthCheck')) {
                $healthResult = $this->mikrotik->healthCheck();
                
                // Garantir que as chaves necessárias existam
                $status['connected'] = $healthResult['connection'] ?? false;
                $status['error'] = $healthResult['error'] ?? null;
                $status['message'] = $healthResult['message'] ?? null;
                $status['reachable'] = true;
                
                // Adicionar outras informações se disponíveis
                if (isset($healthResult['user_count'])) {
                    $status['user_count'] = $healthResult['user_count'];
                }
                if (isset($healthResult['response_time'])) {
                    $status['response_time'] = $healthResult['response_time'];
                }
                
                return $status;
            } else {
                $status['connected'] = true;
                $status['message'] = 'Conectado (método healthCheck não disponível)';
                $status['reachable'] = true;
                return $status;
            }
        } catch (Exception $e) {
            $status['connected'] = false;
            $status['error'] = $e->getMessage();
            $status['reachable'] = $this->isMikroTikReachable();
            return $status;
        }
    }
    
    private function getPHPInfo() {
        return [
            'version' => PHP_VERSION,
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'sockets' => extension_loaded('sockets'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring')
            ],
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
    }
    
    private function getServerInfo() {
        return [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Desconhecido',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Desconhecido',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Desconhecido',
            'php_self' => $_SERVER['PHP_SELF'] ?? 'Desconhecido'
        ];
    }
    
    /**
     * Métodos do sistema original (simplificados para funcionar mesmo offline)
     */
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        $operationStart = microtime(true);
        
        try {
            if (!$this->db) {
                throw new Exception("Banco de dados não conectado");
            }
            
            // Verificar se já existe usuário ativo no quarto
            $stmt = $this->db->prepare("SELECT username FROM hotel_guests WHERE room_number = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$roomNumber]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                throw new Exception("Já existe usuário ativo para o quarto {$roomNumber}: {$existingUser['username']}");
            }
            
            // Gerar credenciais simples
            $username = $this->generateSimpleUsername($roomNumber);
            $password = $this->generateSimplePassword();
            
            // Verificar se as colunas sync_status e last_sync existem
            $hasSync = $this->checkColumnExists('hotel_guests', 'sync_status');
            
            // Inserir no banco com ou sem colunas de sync
            if ($hasSync) {
                $stmt = $this->db->prepare("
                    INSERT INTO hotel_guests 
                    (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date, status, sync_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'pending')
                ");
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO hotel_guests 
                    (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                ");
            }
            
            $result = $stmt->execute([
                $roomNumber, $guestName, $username, $password, 
                $profileType, $checkinDate, $checkoutDate
            ]);
            
            if (!$result) {
                throw new Exception("Falha ao salvar no banco");
            }
            
            $guestId = $this->db->lastInsertId();
            
            $mikrotikResult = ['success' => false, 'message' => 'MikroTik offline'];
            
            // Tentar criar no MikroTik se conectado
            if ($this->mikrotik) {
                try {
                    $timeLimit = $this->calculateTimeLimit($checkoutDate);
                    $mikrotikResult = $this->createInMikroTik($username, $password, $profileType, $timeLimit);
                    
                    // Atualizar status de sync se a coluna existir
                    if ($hasSync) {
                        $this->updateSyncStatus($guestId, $mikrotikResult['success'] ? 'synced' : 'failed', $mikrotikResult['message']);
                    }
                    
                } catch (Exception $e) {
                    $mikrotikResult = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
                    
                    if ($hasSync) {
                        $this->updateSyncStatus($guestId, 'failed', $mikrotikResult['message']);
                    }
                }
            }
            
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            
            return [
                'success' => true,
                'username' => $username,
                'password' => $password,
                'profile' => $profileType,
                'valid_until' => $checkoutDate,
                'bandwidth' => $this->userProfiles[$profileType]['rate_limit'] ?? '10M/2M',
                'mikrotik_success' => $mikrotikResult['success'],
                'mikrotik_message' => $mikrotikResult['message'],
                'sync_status' => $mikrotikResult['success'] ? 'synced' : 'pending',
                'response_time' => $totalTime,
                'guest_id' => $guestId
            ];
            
        } catch (Exception $e) {
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logger->error("Erro ao gerar credenciais: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => $totalTime
            ];
        }
    }
    
    /**
     * Verifica se uma coluna existe em uma tabela
     */
    private function checkColumnExists($tableName, $columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Atualiza status de sincronização se a coluna existir
     */
    private function updateSyncStatus($guestId, $syncStatus, $message = null) {
        try {
            if (!$this->checkColumnExists('hotel_guests', 'sync_status')) {
                return; // Coluna não existe, ignorar
            }
            
            if ($this->checkColumnExists('hotel_guests', 'last_sync')) {
                $stmt = $this->db->prepare("
                    UPDATE hotel_guests 
                    SET sync_status = ?, last_sync = NOW()
                    WHERE id = ?
                ");
            } else {
                $stmt = $this->db->prepare("
                    UPDATE hotel_guests 
                    SET sync_status = ?
                    WHERE id = ?
                ");
            }
            
            $stmt->execute([$syncStatus, $guestId]);
            
            if ($syncStatus === 'failed' && $message) {
                $this->logger->warning("Sync failed for guest ID {$guestId}: {$message}");
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao atualizar sync status: " . $e->getMessage());
        }
    }
    
/*     private function generateSimpleUsername($roomNumber) {
        return 'guest_' . preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber) . '_' . rand(100, 999);
    } */
    private function generateSimpleUsername($roomNumber) {
        return '' . preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber) . '-' . rand(10, 99);
    }
    
    private function generateSimplePassword() {
        return rand(10, 99);
    }
    
    private function calculateTimeLimit($checkoutDate) {
        try {
            $checkout = new DateTime($checkoutDate . ' 12:00:00');
            $now = new DateTime();
            $interval = $now->diff($checkout);
            $hours = ($interval->days * 24) + $interval->h;
            return sprintf('%02d:00:00', max(1, min(168, $hours)));
        } catch (Exception $e) {
            return '24:00:00';
        }
    }
    
    private function createInMikroTik($username, $password, $profileType, $timeLimit) {
        if (!$this->mikrotik) {
            return ['success' => false, 'message' => 'MikroTik não conectado'];
        }
        
        try {
            if (method_exists($this->mikrotik, 'createHotspotUser')) {
                $result = $this->mikrotik->createHotspotUser($username, $password, $profileType, $timeLimit);
                return [
                    'success' => $result,
                    'message' => $result ? 'Criado no MikroTik' : 'Falha na criação'
                ];
            } else {
                return ['success' => false, 'message' => 'Método não disponível'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    public function getActiveGuests() {
        if (!$this->db) {
            return [];
        }
        
        try {
            // Verificar se as colunas de sync existem
            $hasSyncStatus = $this->checkColumnExists('hotel_guests', 'sync_status');
            $hasLastSync = $this->checkColumnExists('hotel_guests', 'last_sync');
            
            // Montar query baseada nas colunas disponíveis
            $selectFields = "
                id, room_number, guest_name, username, password, profile_type, 
                checkin_date, checkout_date, created_at, status,
                CASE 
                    WHEN checkout_date < CURDATE() THEN 'expired'
                    WHEN checkout_date = CURDATE() THEN 'expires_today'
                    ELSE 'active'
                END as validity_status
            ";
            
            if ($hasSyncStatus) {
                $selectFields .= ", sync_status";
            }
            
            if ($hasLastSync) {
                $selectFields .= ", last_sync";
            }
            
            $sql = "SELECT {$selectFields} FROM hotel_guests WHERE status = 'active' ORDER BY room_number";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $guests = $stmt->fetchAll();
            
            // Adicionar campos padrão se não existirem
            foreach ($guests as &$guest) {
                if (!$hasSyncStatus) {
                    $guest['sync_status'] = 'unknown';
                }
                if (!$hasLastSync) {
                    $guest['last_sync'] = null;
                }
            }
            
            return $guests;
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao buscar hóspedes: " . $e->getMessage());
            return [];
        }
    }
    
    public function getSystemStats() {
        $stats = [
            'total_guests' => 0,
            'active_guests' => 0,
            'today_guests' => 0,
            'mikrotik_total' => 0,
            'online_users' => 0,
            'sync_rate' => 0,
            'response_time' => 0
        ];
        
        if ($this->db) {
            try {
                $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests");
                $stats['total_guests'] = $stmt->fetchColumn();
                
                $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE status = 'active'");
                $stats['active_guests'] = $stmt->fetchColumn();
                
                $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE DATE(created_at) = CURDATE()");
                $stats['today_guests'] = $stmt->fetchColumn();
            } catch (Exception $e) {
                $this->logger->error("Erro ao obter stats BD: " . $e->getMessage());
            }
        }
        
        if ($this->mikrotik) {
            try {
                if (method_exists($this->mikrotik, 'getHotspotStats')) {
                    $mikrotikStats = $this->mikrotik->getHotspotStats();
                    $stats['mikrotik_total'] = $mikrotikStats['total_users'] ?? 0;
                    $stats['online_users'] = $mikrotikStats['active_users'] ?? 0;
                }
            } catch (Exception $e) {
                $this->logger->error("Erro ao obter stats MikroTik: " . $e->getMessage());
            }
        }
        
        return $stats;
    }
}

// INICIALIZAÇÃO DO SISTEMA v4.1
$systemInitStart = microtime(true);
$hotelSystem = null;
$initializationError = null;

try {
    $hotelSystem = new HotelSystemV41($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles);
} catch (Exception $e) {
    $initializationError = $e->getMessage();
    error_log("[HOTEL_SYSTEM_v4.1] ERRO DE INICIALIZAÇÃO: " . $e->getMessage());
}

$systemInitTime = round((microtime(true) - $systemInitStart) * 1000, 2);

// Se houve erro na inicialização, mostrar página de diagnóstico
if (!$hotelSystem) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sistema Hotel - Diagnóstico v4.1</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
            .container { max-width: 1000px; margin: 0 auto; }
            .error-box { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #e74c3c; }
            .info-box { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #17a2b8; }
            .diagnostic-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
            .diagnostic-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .status-online { color: #28a745; font-weight: bold; }
            .status-offline { color: #dc3545; font-weight: bold; }
            pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
            .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; }
            .btn:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏨 Sistema Hotel v4.1 - Diagnóstico</h1>
            
            <div class="error-box">
                <h3>❌ Erro na Inicialização do Sistema</h3>
                <p><strong>Erro:</strong> <?php echo htmlspecialchars($initializationError); ?></p>
                <p><strong>Tempo de inicialização:</strong> <?php echo $systemInitTime; ?>ms</p>
            </div>
            
            <div class="info-box">
                <h3>🔍 Diagnóstico Automático</h3>
                <p>O sistema detectou problemas e está executando um diagnóstico automático...</p>
            </div>
            
            <?php
            // Realizar diagnóstico básico mesmo sem sistema inicializado
            $diagnostic = [
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'config_file' => file_exists('config.php') ? 'Encontrado' : 'Não encontrado',
                'mikrotik_file' => file_exists('mikrotik_manager.php') ? 'Encontrado' : 'Não encontrado',
                'mysql_test' => false,
                'mikrotik_test' => false,
                'extensions' => []
            ];
            
            // Testar extensões PHP
            $requiredExtensions = ['pdo', 'pdo_mysql', 'sockets', 'json', 'mbstring'];
            foreach ($requiredExtensions as $ext) {
                $diagnostic['extensions'][$ext] = extension_loaded($ext);
            }
            
            // Testar MySQL
            try {
                $testSocket = @fsockopen($dbConfig['host'], 3306, $errno, $errstr, 3);
                if ($testSocket) {
                    fclose($testSocket);
                    $diagnostic['mysql_test'] = 'Porta 3306 acessível';
                } else {
                    $diagnostic['mysql_test'] = "Porta 3306 inacessível: {$errstr}";
                }
            } catch (Exception $e) {
                $diagnostic['mysql_test'] = 'Erro no teste: ' . $e->getMessage();
            }
            
            // Testar MikroTik
            try {
                $testSocket = @fsockopen($mikrotikConfig['host'], $mikrotikConfig['port'], $errno, $errstr, 3);
                if ($testSocket) {
                    fclose($testSocket);
                    $diagnostic['mikrotik_test'] = 'Porta acessível';
                } else {
                    $diagnostic['mikrotik_test'] = "Porta inacessível: {$errstr}";
                }
            } catch (Exception $e) {
                $diagnostic['mikrotik_test'] = 'Erro no teste: ' . $e->getMessage();
            }
            ?>
            
            <div class="diagnostic-grid">
                <div class="diagnostic-card">
                    <h3>📁 Arquivos do Sistema</h3>
                    <p>config.php: <span class="<?php echo file_exists('config.php') ? 'status-online' : 'status-offline'; ?>"><?php echo $diagnostic['config_file']; ?></span></p>
                    <p>mikrotik_manager.php: <span class="<?php echo file_exists('mikrotik_manager.php') ? 'status-online' : 'status-offline'; ?>"><?php echo $diagnostic['mikrotik_file']; ?></span></p>
                    <p>Versão PHP: <span class="status-online"><?php echo $diagnostic['php_version']; ?></span></p>
                </div>
                
                <div class="diagnostic-card">
                    <h3>🔌 Extensões PHP</h3>
                    <?php foreach ($diagnostic['extensions'] as $ext => $loaded): ?>
                        <p><?php echo $ext; ?>: <span class="<?php echo $loaded ? 'status-online' : 'status-offline'; ?>"><?php echo $loaded ? 'Carregada' : 'Não carregada'; ?></span></p>
                    <?php endforeach; ?>
                </div>
                
                <div class="diagnostic-card">
                    <h3>💾 Teste MySQL</h3>
                    <p>Host: <?php echo htmlspecialchars($dbConfig['host']); ?></p>
                    <p>Banco: <?php echo htmlspecialchars($dbConfig['database']); ?></p>
                    <p>Usuário: <?php echo htmlspecialchars($dbConfig['username']); ?></p>
                    <p>Status: <span class="<?php echo $diagnostic['mysql_test'] === 'Porta 3306 acessível' ? 'status-online' : 'status-offline'; ?>"><?php echo $diagnostic['mysql_test']; ?></span></p>
                </div>
                
                <div class="diagnostic-card">
                    <h3>📡 Teste MikroTik</h3>
                    <p>Host: <?php echo htmlspecialchars($mikrotikConfig['host']); ?></p>
                    <p>Porta: <?php echo htmlspecialchars($mikrotikConfig['port']); ?></p>
                    <p>Usuário: <?php echo htmlspecialchars($mikrotikConfig['username']); ?></p>
                    <p>Status: <span class="<?php echo $diagnostic['mikrotik_test'] === 'Porta acessível' ? 'status-online' : 'status-offline'; ?>"><?php echo $diagnostic['mikrotik_test']; ?></span></p>
                </div>
            </div>
            
            <div class="info-box">
                <h3>🛠️ Soluções Recomendadas</h3>
                <ul>
                    <?php if (!file_exists('config.php')): ?>
                        <li>❌ Arquivo config.php não encontrado - Verifique se está na pasta correta</li>
                    <?php endif; ?>
                    
                    <?php if (!file_exists('mikrotik_manager.php')): ?>
                        <li>❌ Arquivo mikrotik_manager.php não encontrado - Faça upload do arquivo</li>
                    <?php endif; ?>
                    
                    <?php if (!extension_loaded('pdo_mysql')): ?>
                        <li>❌ Extensão pdo_mysql não carregada - Instale/ative no PHP</li>
                    <?php endif; ?>
                    
                    <?php if (!extension_loaded('sockets')): ?>
                        <li>❌ Extensão sockets não carregada - Necessária para MikroTik</li>
                    <?php endif; ?>
                    
                    <?php if ($diagnostic['mysql_test'] !== 'Porta 3306 acessível'): ?>
                        <li>❌ MySQL não acessível - Verifique se o serviço está rodando</li>
                    <?php endif; ?>
                    
                    <?php if ($diagnostic['mikrotik_test'] !== 'Porta acessível'): ?>
                        <li>❌ MikroTik não acessível - Verifique IP e porta na configuração</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="diagnostic-card">
                <h3>🔍 Informações Detalhadas</h3>
                <pre><?php echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="?" class="btn">🔄 Tentar Novamente</a>
                <a href="test_raw_parser_final.php" class="btn">🧪 Testar Parser</a>
                <a href="phpinfo.php" class="btn">📋 PHP Info</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Variáveis de estado
$result = null;
$message = null;
$debugInfo = null;
$validationResults = null;

// Processamento de ações v4.1
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionStart = microtime(true);
    
    try {
        if (isset($_POST['generate_access'])) {
            // Geração de acesso
            $roomNumber = trim($_POST['room_number'] ?? '');
            $guestName = trim($_POST['guest_name'] ?? '');
            $checkinDate = $_POST['checkin_date'] ?? '';
            $checkoutDate = $_POST['checkout_date'] ?? '';
            $profileType = $_POST['profile_type'] ?? 'hotel-guest';
            
            // Validações básicas
            if (empty($roomNumber) || empty($guestName) || empty($checkinDate) || empty($checkoutDate)) {
                $message = "❌ ERRO: Todos os campos são obrigatórios";
            } else {
                $result = $hotelSystem->generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType);
                
                if ($result['success']) {
                    $responseTime = $result['response_time'] ?? 0;
                    $syncStatus = $result['sync_status'] ?? 'unknown';
                    $message = "🎉 CREDENCIAIS GERADAS EM {$responseTime}ms! Sync: " . strtoupper($syncStatus);
                } else {
                    $message = "❌ ERRO: " . $result['error'];
                }
            }
            
        } elseif (isset($_POST['get_diagnostic'])) {
            // Diagnóstico do sistema
            $debugInfo = $hotelSystem->getSystemDiagnostic();
            $message = "🔍 DIAGNÓSTICO EXECUTADO";
        }
        
    } catch (Exception $e) {
        $actionTime = round((microtime(true) - $actionStart) * 1000, 2);
        $message = "❌ ERRO CRÍTICO EM {$actionTime}ms: " . $e->getMessage();
        error_log("[HOTEL_SYSTEM_v4.1] ERRO CRÍTICO: " . $e->getMessage());
    }
}

// Obter dados para exibição
$dataStart = microtime(true);

try {
    $activeGuests = $hotelSystem->getActiveGuests();
    $systemStats = $hotelSystem->getSystemStats();
    $systemDiagnostic = $hotelSystem->getSystemDiagnostic();
    
    // Garantir que o diagnóstico tenha estrutura consistente
    if (!isset($systemDiagnostic['mikrotik'])) {
        $systemDiagnostic['mikrotik'] = [
            'connected' => false,
            'host' => $mikrotikConfig['host'] ?? 'N/A',
            'port' => $mikrotikConfig['port'] ?? 'N/A',
            'error' => 'Diagnóstico não disponível'
        ];
    }
    
    if (!isset($systemDiagnostic['database'])) {
        $systemDiagnostic['database'] = [
            'connected' => false,
            'host' => $dbConfig['host'] ?? 'N/A',
            'database' => $dbConfig['database'] ?? 'N/A',
            'error' => 'Diagnóstico não disponível'
        ];
    }
    
    $dataTime = round((microtime(true) - $dataStart) * 1000, 2);
    
} catch (Exception $e) {
    $activeGuests = [];
    $systemStats = [
        'total_guests' => 0,
        'active_guests' => 0,
        'today_guests' => 0,
        'mikrotik_total' => 0,
        'online_users' => 0,
        'sync_rate' => 0,
        'error' => $e->getMessage()
    ];
    
    $systemDiagnostic = [
        'database' => [
            'connected' => false,
            'host' => $dbConfig['host'] ?? 'N/A',
            'database' => $dbConfig['database'] ?? 'N/A',
            'error' => 'Erro ao obter diagnóstico'
        ],
        'mikrotik' => [
            'connected' => false,
            'host' => $mikrotikConfig['host'] ?? 'N/A',
            'port' => $mikrotikConfig['port'] ?? 'N/A',
            'error' => 'Erro ao obter diagnóstico'
        ],
        'error' => $e->getMessage()
    ];
    
    $dataTime = round((microtime(true) - $dataStart) * 1000, 2);
}

// Determinar status do sistema
$systemStatus = 'unknown';
$systemStatusColor = 'warning';

if (isset($systemDiagnostic['database']['connected']) && $systemDiagnostic['database']['connected']) {
    if (isset($systemDiagnostic['mikrotik']['connected']) && $systemDiagnostic['mikrotik']['connected']) {
        $systemStatus = 'excellent';
        $systemStatusColor = 'success';
    } else {
        $systemStatus = 'database_only';
        $systemStatusColor = 'warning';
    }
} else {
    $systemStatus = 'critical';
    $systemStatusColor = 'danger';
}

$totalLoadTime = round((microtime(true) - $systemInitStart) * 1000, 2);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($systemConfig['hotel_name']); ?> - Sistema v4.1</title>
    
    <style>
        /* CSS otimizado para v4.1 */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .header h1 {
            font-size: 2.8em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .version-badge {
            position: absolute;
            top: 15px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        
        .system-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-left: 15px;
            font-weight: 600;
        }
        
        .status-excellent { background: #d4edda; color: #155724; }
        .status-database_only { background: #fff3cd; color: #856404; }
        .status-critical { background: #f8d7da; color: #721c24; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-left: 5px solid #3498db;
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 500;
        }
        
        .main-content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            border-left: 5px solid #3498db;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-title {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.8em;
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 5px solid #27ae60;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 5px solid #e74c3c;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-left: 5px solid #f39c12;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .form-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .btn-secondary { background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .btn-info { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        
        .diagnostic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .diagnostic-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .status-online { color: #28a745; font-weight: bold; }
        .status-offline { color: #dc3545; font-weight: bold; }
        
        .credentials-display {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 35px;
            border-radius: 20px;
            margin: 25px 0;
            text-align: center;
            box-shadow: 0 15px 35px rgba(39, 174, 96, 0.3);
        }
        
        .credential-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin: 25px 0;
        }
        
        .credential-box {
            background: rgba(255,255,255,0.15);
            padding: 25px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .credential-value {
            font-size: 2.8em;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
        }
        
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .credential-pair {
                grid-template-columns: 1fr;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="version-badge">v4.1 Fixed</div>
            <h1>🏨 <?php echo htmlspecialchars($systemConfig['hotel_name']); ?></h1>
            <p>Sistema de Gerenciamento de Internet - Conexões Corrigidas</p>
            <span class="system-status status-<?php echo $systemStatus; ?>">
                <?php 
                switch($systemStatus) {
                    case 'excellent': echo '🎉 Sistema Online'; break;
                    case 'database_only': echo '⚠️ Só Banco Online'; break;
                    case 'critical': echo '❌ Sistema Offline'; break;
                    default: echo '❓ Status Desconhecido'; break;
                }
                ?>
            </span>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['total_guests']; ?></div>
                <div class="stat-label">Total Hóspedes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['active_guests']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['mikrotik_total']; ?></div>
                <div class="stat-label">MikroTik</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalLoadTime; ?>ms</div>
                <div class="stat-label">Carregamento</div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Mensagens -->
            <?php if ($message): ?>
                <div class="alert <?php 
                    echo strpos($message, '❌') !== false ? 'alert-error' : 
                        (strpos($message, '⚠️') !== false ? 'alert-warning' : 'alert-success'); 
                ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulário de Geração -->
            <div class="section">
                <h2 class="section-title">🆕 Gerar Novo Acesso</h2>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div>
                            <label for="room_number">Número do Quarto:</label>
                            <input type="text" id="room_number" name="room_number" required 
                                   placeholder="Ex: 101, 205A" class="form-input">
                        </div>
                        
                        <div>
                            <label for="guest_name">Nome do Hóspede:</label>
                            <input type="text" id="guest_name" name="guest_name" required 
                                   placeholder="Nome completo" class="form-input">
                        </div>
                        
                        <div>
                            <label for="checkin_date">Check-in:</label>
                            <input type="date" id="checkin_date" name="checkin_date" required 
                                   value="<?php echo date('Y-m-d'); ?>" class="form-input">
                        </div>
                        
                        <div>
                            <label for="checkout_date">Check-out:</label>
                            <input type="date" id="checkout_date" name="checkout_date" required 
                                   value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="form-input">
                        </div>
                        
                        <div>
                            <label for="profile_type">Perfil:</label>
                            <select id="profile_type" name="profile_type" class="form-input">
                                <?php foreach ($userProfiles as $key => $profile): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>">
                                        <?php echo htmlspecialchars($profile['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="text-align: center;">
                        <button type="submit" name="generate_access" class="btn">
                            ✨ Gerar Credenciais
                        </button>
                    </div>
                </form>
                
                <!-- Resultado -->
                <?php if (isset($result) && $result['success']): ?>
                    <div class="credentials-display">
                        <h3>🎉 Credenciais Geradas!</h3>
                        <div class="credential-pair">
                            <div class="credential-box" onclick="copyToClipboard('<?php echo $result['username']; ?>')">
                                <div>👤 USUÁRIO</div>
                                <div class="credential-value"><?php echo htmlspecialchars($result['username']); ?></div>
                            </div>
                            <div class="credential-box" onclick="copyToClipboard('<?php echo $result['password']; ?>')">
                                <div>🔒 SENHA</div>
                                <div class="credential-value"><?php echo htmlspecialchars($result['password']); ?></div>
                            </div>
                        </div>
                        <p>Tempo: <?php echo $result['response_time']; ?>ms | MikroTik: <?php echo $result['mikrotik_message']; ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Status do Sistema -->
            <div class="section">
                <h2 class="section-title">🔧 Status do Sistema</h2>
                
                <div class="diagnostic-grid">
                    <div class="diagnostic-card">
                        <h3>💾 Banco de Dados</h3>
                        <?php if (isset($systemDiagnostic['database'])): ?>
                            <p>Status: <span class="<?php echo $systemDiagnostic['database']['connected'] ? 'status-online' : 'status-offline'; ?>">
                                <?php echo $systemDiagnostic['database']['connected'] ? '🟢 Online' : '🔴 Offline'; ?>
                            </span></p>
                            <p>Host: <?php echo $systemDiagnostic['database']['host'] ?? 'N/A'; ?></p>
                            <p>Banco: <?php echo $systemDiagnostic['database']['database'] ?? 'N/A'; ?></p>
                            <?php if (isset($systemDiagnostic['database']['total_guests'])): ?>
                                <p>Hóspedes: <?php echo $systemDiagnostic['database']['total_guests']; ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Status: <span class="status-offline">🔴 Não disponível</span></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="diagnostic-card">
                        <h3>📡 MikroTik</h3>
                        <p>Status: <span class="<?php echo ($systemDiagnostic['mikrotik']['connected'] ?? false) ? 'status-online' : 'status-offline'; ?>">
                            <?php echo ($systemDiagnostic['mikrotik']['connected'] ?? false) ? '🟢 Online' : '🔴 Offline'; ?>
                        </span></p>
                        <p>Host: <?php echo htmlspecialchars($systemDiagnostic['mikrotik']['host'] ?? 'N/A'); ?></p>
                        <p>Porta: <?php echo htmlspecialchars($systemDiagnostic['mikrotik']['port'] ?? 'N/A'); ?></p>
                        
                        <?php if (isset($systemDiagnostic['mikrotik']['error']) && $systemDiagnostic['mikrotik']['error']): ?>
                            <p style="color: #dc3545; font-size: 0.9em;">
                                <strong>Erro:</strong> <?php echo htmlspecialchars($systemDiagnostic['mikrotik']['error']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (isset($systemDiagnostic['mikrotik']['message']) && $systemDiagnostic['mikrotik']['message']): ?>
                            <p style="color: #17a2b8; font-size: 0.9em;">
                                <strong>Info:</strong> <?php echo htmlspecialchars($systemDiagnostic['mikrotik']['message']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (isset($systemDiagnostic['mikrotik']['reachable'])): ?>
                            <p style="font-size: 0.9em;">
                                <strong>Acessível:</strong> <?php echo $systemDiagnostic['mikrotik']['reachable'] ? '✅ Sim' : '❌ Não'; ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (isset($systemDiagnostic['mikrotik']['user_count'])): ?>
                            <p style="font-size: 0.9em;">
                                <strong>Usuários:</strong> <?php echo $systemDiagnostic['mikrotik']['user_count']; ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (isset($systemDiagnostic['mikrotik']['response_time'])): ?>
                            <p style="font-size: 0.9em;">
                                <strong>Tempo:</strong> <?php echo $systemDiagnostic['mikrotik']['response_time']; ?>ms
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="diagnostic-card">
                        <h3>⚙️ PHP</h3>
                        <p>Versão: <?php echo PHP_VERSION; ?></p>
                        <p>PDO MySQL: <span class="<?php echo extension_loaded('pdo_mysql') ? 'status-online' : 'status-offline'; ?>">
                            <?php echo extension_loaded('pdo_mysql') ? '✅' : '❌'; ?>
                        </span></p>
                        <p>Sockets: <span class="<?php echo extension_loaded('sockets') ? 'status-online' : 'status-offline'; ?>">
                            <?php echo extension_loaded('sockets') ? '✅' : '❌'; ?>
                        </span></p>
                    </div>
                    
                    <div class="diagnostic-card">
                        <h3>🔍 Ações</h3>
                        <form method="POST" style="margin-bottom: 10px;">
                            <button type="submit" name="get_diagnostic" class="btn btn-info">
                                🔍 Diagnóstico Completo
                            </button>
                        </form>
                        <a href="test_raw_parser_final.php" class="btn btn-secondary">🧪 Testar Parser</a>
                    </div>
                </div>
            </div>
            
            <!-- Debug Info -->
            <?php if ($debugInfo): ?>
            <div class="section">
                <h2 class="section-title">🔍 Diagnóstico Detalhado</h2>
                <pre><?php echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            </div>
            <?php endif; ?>
            
            <!-- Lista de Hóspedes -->
            <?php if (!empty($activeGuests)): ?>
            <div class="section">
                <h2 class="section-title">👥 Hóspedes Ativos (<?php echo count($activeGuests); ?>)</h2>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden;">
                        <thead style="background: #2c3e50; color: white;">
                            <tr>
                                <th style="padding: 15px; text-align: left;">Quarto</th>
                                <th style="padding: 15px; text-align: left;">Hóspede</th>
                                <th style="padding: 15px; text-align: left;">Usuário</th>
                                <th style="padding: 15px; text-align: left;">Senha</th>
                                <th style="padding: 15px; text-align: left;">Check-out</th>
                                <th style="padding: 15px; text-align: left;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeGuests as $guest): ?>
                            <tr style="border-bottom: 1px solid #ecf0f1;">
                                <td style="padding: 15px; font-weight: bold; color: #3498db;">
                                    <?php echo htmlspecialchars($guest['room_number']); ?>
                                </td>
                                <td style="padding: 15px;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($guest['guest_name']); ?></div>
                                    <div style="font-size: 0.9em; color: #7f8c8d;">
                                        Check-in: <?php echo date('d/m/Y', strtotime($guest['checkin_date'])); ?>
                                    </div>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="font-family: monospace; background: #ecf0f1; padding: 5px 8px; border-radius: 5px; cursor: pointer;" 
                                          onclick="copyToClipboard('<?php echo htmlspecialchars($guest['username']); ?>')" 
                                          title="Clique para copiar">
                                        <?php echo htmlspecialchars($guest['username']); ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="font-family: monospace; background: #ecf0f1; padding: 5px 8px; border-radius: 5px; cursor: pointer;" 
                                          onclick="copyToClipboard('<?php echo htmlspecialchars($guest['password']); ?>')" 
                                          title="Clique para copiar">
                                        <?php echo htmlspecialchars($guest['password']); ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <?php echo date('d/m/Y', strtotime($guest['checkout_date'])); ?>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="display: inline-block; padding: 4px 8px; border-radius: 10px; font-size: 0.8em; font-weight: 600; 
                                                 background: <?php echo ($guest['sync_status'] ?? 'pending') === 'synced' ? '#d4edda' : '#fff3cd'; ?>; 
                                                 color: <?php echo ($guest['sync_status'] ?? 'pending') === 'synced' ? '#155724' : '#856404'; ?>;">
                                        <?php 
                                        switch ($guest['sync_status'] ?? 'pending') {
                                            case 'synced': echo '🟢 Sync'; break;
                                            case 'failed': echo '🔴 Erro'; break;
                                            default: echo '🟡 Pend'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="section">
                <h2 class="section-title">👥 Hóspedes Ativos</h2>
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 4em; margin-bottom: 20px; opacity: 0.7;">📋</div>
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">Nenhum hóspede ativo</h3>
                    <p style="color: #7f8c8d; margin-bottom: 25px;">Gere credenciais para novos hóspedes usando o formulário acima.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div style="background: #2c3e50; color: white; padding: 20px; text-align: center;">
            <p>&copy; 2025 Sistema Hotel v4.1 - Conexões Corrigidas</p>
            <p>Tempo de carregamento: <?php echo $totalLoadTime; ?>ms | Status: <?php echo $systemStatus; ?></p>
            <?php if (!empty($hotelSystem->connectionErrors)): ?>
                <p style="color: #e74c3c; margin-top: 10px;">
                    Erros: <?php echo implode(', ', $hotelSystem->connectionErrors); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // JavaScript para funcionalidades básicas
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Feedback visual simples
                console.log('Copiado: ' + text);
                
                // Mostrar mensagem temporária
                const message = document.createElement('div');
                message.textContent = 'Copiado: ' + text;
                message.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #27ae60;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 5px;
                    z-index: 1000;
                    font-weight: bold;
                `;
                document.body.appendChild(message);
                
                setTimeout(() => {
                    message.remove();
                }, 2000);
                
            }).catch(function(err) {
                console.error('Erro ao copiar: ', err);
                alert('Erro ao copiar: ' + text);
            });
        }
        
        // Auto-definir datas
        document.addEventListener('DOMContentLoaded', function() {
            const checkinInput = document.getElementById('checkin_date');
            const checkoutInput = document.getElementById('checkout_date');
            
            if (checkinInput && !checkinInput.value) {
                checkinInput.value = new Date().toISOString().split('T')[0];
            }
            
            if (checkoutInput && !checkoutInput.value) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                checkoutInput.value = tomorrow.toISOString().split('T')[0];
            }
            
            // Validação de datas
            if (checkinInput && checkoutInput) {
                function validateDates() {
                    const checkin = new Date(checkinInput.value);
                    const checkout = new Date(checkoutInput.value);
                    
                    if (checkout <= checkin) {
                        checkoutInput.setCustomValidity('Data de check-out deve ser posterior ao check-in');
                    } else {
                        checkoutInput.setCustomValidity('');
                    }
                }
                
                checkinInput.addEventListener('change', validateDates);
                checkoutInput.addEventListener('change', validateDates);
            }
            
            console.log('Sistema Hotel v4.1 carregado em <?php echo $totalLoadTime; ?>ms');
        });
        
        // Detectar problemas de conectividade
        window.addEventListener('online', function() {
            console.log('Conexão restaurada');
        });
        
        window.addEventListener('offline', function() {
            console.log('Conexão perdida');
        });
    </script>
</body>
</html>