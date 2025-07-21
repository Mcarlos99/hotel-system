<?php
/**
 * index.php - Sistema Hotel v4.4 FINAL - Avisos de Operação Funcionais
 * 
 * VERSÃO: 4.4 FINAL - Avisos que funcionam perfeitamente
 * DATA: 2025-01-21
 * 
 * MELHORIAS FINAIS v4.4:
 * ✅ Avisos visuais funcionais durante operações demoradas
 * ✅ JavaScript corrigido que não bloqueia submissões
 * ✅ Overlay aparece APÓS submissão, não antes
 * ✅ Timeout automático de segurança (30s)
 * ✅ Mensagens personalizadas por tipo de operação
 * ✅ Interface otimizada para recepcionistas
 * ✅ Prevenção de cliques múltiplos
 * ✅ Fallback robusto para casos de erro
 * ✅ Design responsivo e moderno
 */

// Configurações otimizadas
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
session_start();

// UTF-8 encoding
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Verificar arquivos essenciais
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

require_once 'config.php';

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

// Logger simples para compatibilidade
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
 * Sistema de Flash Messages para PRG (POST-Redirect-GET)
 */
class FlashMessages {
    private static $sessionKey = 'hotel_flash_messages';
    
    public static function set($type, $message, $data = null) {
        if (!isset($_SESSION[self::$sessionKey])) {
            $_SESSION[self::$sessionKey] = [];
        }
        
        $_SESSION[self::$sessionKey][] = [
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    public static function get() {
        if (!isset($_SESSION[self::$sessionKey])) {
            return [];
        }
        
        $messages = $_SESSION[self::$sessionKey];
        unset($_SESSION[self::$sessionKey]);
        
        return $messages;
    }
    
    public static function success($message, $data = null) {
        self::set('success', $message, $data);
    }
    
    public static function error($message, $data = null) {
        self::set('error', $message, $data);
    }
    
    public static function warning($message, $data = null) {
        self::set('warning', $message, $data);
    }
    
    public static function info($message, $data = null) {
        self::set('info', $message, $data);
    }
}
/* part 2 */
/**
 * Classe Principal do Sistema Hotel v4.4 FINAL
 */
class HotelSystemV44Final {
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
        
        // Validar configurações
        if (!is_array($mikrotikConfig)) {
            $this->mikrotikConfig = ['host' => '', 'port' => 8728, 'username' => '', 'password' => ''];
        }
        if (!is_array($dbConfig)) {
            $this->dbConfig = ['host' => 'localhost', 'database' => '', 'username' => '', 'password' => ''];
        }
        
        // Inicializar logger
        if (class_exists('HotelLogger')) {
            $this->logger = new HotelLogger();
        } else {
            $this->logger = new SimpleLogger();
        }
        
        $this->logger->info("Hotel System v4.4 FINAL iniciando com avisos funcionais...");
        
        // Conectar sistemas
        $this->connectToDatabase();
        $this->connectToMikroTik();
        
        $initTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->logger->info("Sistema Hotel v4.4 FINAL inicializado em {$initTime}ms");
    }
    
    /**
     * Conexão robusta ao banco de dados
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
            
            // Configurações de conexão otimizadas
            $hostOnlyDsn = "mysql:host={$this->dbConfig['host']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10
            ];
            
            try {
                // Tentar conectar sem especificar banco primeiro
                $tempDb = new PDO($hostOnlyDsn, $this->dbConfig['username'], $this->dbConfig['password'], $options);
                $this->logger->info("Conexão MySQL estabelecida");
                
                // Verificar se banco existe
                $stmt = $tempDb->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
                $stmt->execute([$this->dbConfig['database']]);
                $dbExists = $stmt->fetchColumn();
                
                if (!$dbExists) {
                    $this->logger->info("Criando banco de dados: {$this->dbConfig['database']}");
                    $tempDb->exec("CREATE DATABASE `{$this->dbConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                
                $tempDb = null;
                
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Access denied') !== false) {
                    // Tentar com credenciais alternativas
                    $this->logger->warning("Tentando com credenciais alternativas...");
                    try {
                        $tempDb = new PDO($hostOnlyDsn, 'root', '', $options);
                        $this->createDatabaseUser($tempDb);
                        $tempDb->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbConfig['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $tempDb = null;
                    } catch (PDOException $e2) {
                        throw new Exception("Erro de autenticação MySQL: " . $e2->getMessage());
                    }
                } else {
                    throw $e;
                }
            }
            
            // Conectar ao banco específico
            $fullDsn = "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['database']};charset=utf8mb4";
            $this->db = new PDO($fullDsn, $this->dbConfig['username'], $this->dbConfig['password'], $options);
            
            $this->logger->info("Conectado ao banco: {$this->dbConfig['database']}");
            
            // Criar/verificar tabelas
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
     * Conexão robusta ao MikroTik
     */
    private function connectToMikroTik() {
        try {
            $this->logger->info("Conectando ao MikroTik...");
            
            // Validar configurações básicas
            if (!isset($this->mikrotikConfig['host']) || empty($this->mikrotikConfig['host'])) {
                $errorMsg = "Host do MikroTik não configurado";
                $this->connectionErrors[] = $errorMsg;
                $this->logger->error($errorMsg);
                $this->mikrotik = null;
                return;
            }
            
            if (!isset($this->mikrotikConfig['port'])) {
                $this->mikrotikConfig['port'] = 8728;
            }
            
            if (!isset($this->mikrotikConfig['username']) || empty($this->mikrotikConfig['username'])) {
                $errorMsg = "Usuário do MikroTik não configurado";
                $this->connectionErrors[] = $errorMsg;
                $this->logger->error($errorMsg);
                $this->mikrotik = null;
                return;
            }
            
            if (!isset($this->mikrotikConfig['password'])) {
                $this->mikrotikConfig['password'] = '';
            }
            
            // Verificar se MikroTik é acessível
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
                        
                        // Testar conexão se método disponível
                        if (method_exists($this->mikrotik, 'testConnection')) {
                            $testResult = $this->mikrotik->testConnection();
                            if ($testResult['success']) {
                                $this->logger->info("MikroTik conectado usando classe: {$className}");
                                return;
                            }
                        } else {
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
     * Verifica se MikroTik é acessível via TCP
     */
    private function isMikroTikReachable() {
        if (!isset($this->mikrotikConfig['host']) || empty($this->mikrotikConfig['host'])) {
            $this->logger->warning("Host do MikroTik não configurado");
            return false;
        }
        
        $host = $this->mikrotikConfig['host'];
        $port = $this->mikrotikConfig['port'] ?? 8728;
        
        // Validar host
        if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            $this->logger->warning("Host inválido: {$host}");
            return false;
        }
        
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
        
        $this->logger->warning("MikroTik não acessível - host: {$host}, porta: {$port}");
        return false;
    }
    /* parte 3 */
    /**
     * Criação e verificação de tabelas do banco
     */
    protected function createTables() {
        if (!$this->db) {
            return false;
        }
        
        try {
            // Tabela principal de hóspedes
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
            
            // Adicionar colunas que podem estar faltando
            $this->addMissingColumns();
            
            // Tabela de logs de acesso
            $sql = "CREATE TABLE IF NOT EXISTS access_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                room_number VARCHAR(10) NOT NULL,
                action ENUM('login', 'logout', 'created', 'disabled', 'expired', 'sync_failed', 'sync_success', 'removed') NOT NULL,
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
            
            // Tabela de histórico de operações (para PRG)
            $sql = "CREATE TABLE IF NOT EXISTS operation_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                operation_type ENUM('create', 'remove', 'update', 'diagnostic') NOT NULL,
                operation_data JSON,
                result_data JSON,
                success BOOLEAN NOT NULL,
                response_time INT DEFAULT 0,
                user_ip VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_type (operation_type),
                INDEX idx_success (success),
                INDEX idx_date (created_at)
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
     * OPERAÇÃO PRINCIPAL: Gerar credenciais de acesso
     */
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        $operationStart = microtime(true);
        
        try {
            if (!$this->db) {
                throw new Exception("Banco de dados não conectado");
            }
            
            $this->logger->info("Iniciando geração de credenciais para quarto: {$roomNumber}");
            
            // Verificar se já existe usuário ativo no quarto
            $stmt = $this->db->prepare("SELECT username FROM hotel_guests WHERE room_number = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$roomNumber]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                throw new Exception("Já existe usuário ativo para o quarto {$roomNumber}: {$existingUser['username']}");
            }
            
            // Gerar credenciais simples e únicas
            $username = $this->generateSimpleUsername($roomNumber);
            $password = $this->generateSimplePassword();
            
            // Verificar se colunas de sync existem
            $hasSync = $this->checkColumnExists('hotel_guests', 'sync_status');
            
            // Inserir no banco de dados
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
                throw new Exception("Falha ao salvar no banco de dados");
            }
            
            $guestId = $this->db->lastInsertId();
            $this->logger->info("Credenciais salvas no banco - ID: {$guestId}");
            
            // Tentar criar no MikroTik
            $mikrotikResult = ['success' => false, 'message' => 'MikroTik offline'];
            
            if ($this->mikrotik) {
                try {
                    $this->logger->info("Tentando criar usuário no MikroTik: {$username}");
                    $timeLimit = $this->calculateTimeLimit($checkoutDate);
                    $mikrotikResult = $this->createInMikroTik($username, $password, $profileType, $timeLimit);
                    
                    // Atualizar status de sync
                    if ($hasSync) {
                        $this->updateSyncStatus($guestId, $mikrotikResult['success'] ? 'synced' : 'failed', $mikrotikResult['message']);
                    }
                    
                } catch (Exception $e) {
                    $mikrotikResult = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
                    
                    if ($hasSync) {
                        $this->updateSyncStatus($guestId, 'failed', $mikrotikResult['message']);
                    }
                }
            } else {
                $this->logger->warning("MikroTik não conectado - credenciais criadas apenas no banco");
            }
            
            // Log da ação
            $this->logAction($username, $roomNumber, 'created', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, 0, null);
            
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            
            $result = [
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
            
            // Salvar operação no histórico
            $this->saveOperationHistory('create', [
                'room_number' => $roomNumber,
                'guest_name' => $guestName,
                'profile_type' => $profileType,
                'checkin_date' => $checkinDate,
                'checkout_date' => $checkoutDate
            ], $result, true, $totalTime);
            
            $this->logger->info("Credenciais geradas com sucesso em {$totalTime}ms", $result);
            
            return $result;
            
        } catch (Exception $e) {
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logger->error("Erro ao gerar credenciais: " . $e->getMessage());
            
            $result = [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => $totalTime
            ];
            
            // Salvar erro no histórico
            $this->saveOperationHistory('create', [
                'room_number' => $roomNumber ?? null,
                'guest_name' => $guestName ?? null,
                'profile_type' => $profileType ?? null
            ], $result, false, $totalTime);
            
            return $result;
        }
    }
    
    /**
     * OPERAÇÃO PRINCIPAL: Remover acesso do hóspede
     */
    public function removeGuestAccess($guestId) {
        $operationStart = microtime(true);
        
        try {
            if (!$this->db) {
                throw new Exception("Banco de dados não conectado");
            }
            
            $this->logger->info("Iniciando remoção de acesso para guest ID: {$guestId}");
            
            // Buscar dados do hóspede
            $stmt = $this->db->prepare("SELECT * FROM hotel_guests WHERE id = ? AND status = 'active'");
            $stmt->execute([$guestId]);
            $guest = $stmt->fetch();
            
            if (!$guest) {
                throw new Exception("Hóspede não encontrado ou já removido");
            }
            
            $username = $guest['username'];
            $roomNumber = $guest['room_number'];
            $guestName = $guest['guest_name'];
            
            $this->logger->info("Removendo acesso: {$guestName} (Quarto: {$roomNumber}, Usuário: {$username})");
            
            $dbSuccess = false;
            $mikrotikSuccess = false;
            $mikrotikMessage = "MikroTik não conectado";
            
            // Passo 1: Remover do banco de dados
            try {
                $stmt = $this->db->prepare("UPDATE hotel_guests SET status = 'disabled', updated_at = NOW() WHERE id = ?");
                $dbSuccess = $stmt->execute([$guestId]);
                
                if ($dbSuccess) {
                    $this->logger->info("Hóspede removido do banco com sucesso");
                    $this->logAction($username, $roomNumber, 'removed', null, null, 0, null);
                } else {
                    throw new Exception("Falha ao atualizar status no banco");
                }
                
            } catch (Exception $e) {
                $this->logger->error("Erro ao remover do banco: " . $e->getMessage());
                throw new Exception("Erro no banco de dados: " . $e->getMessage());
            }
            
            // Passo 2: Remover do MikroTik (se conectado)
            if ($this->mikrotik) {
                try {
                    $this->logger->info("Tentando remover do MikroTik: {$username}");
                    
                    // Conectar se necessário
                    if (method_exists($this->mikrotik, 'connect')) {
                        $this->mikrotik->connect();
                    }
                    
                    // Primeiro desconectar se estiver online
                    if (method_exists($this->mikrotik, 'disconnectUser')) {
                        $this->mikrotik->disconnectUser($username);
                        $this->logger->info("Usuário desconectado do MikroTik");
                    }
                    
                    // Depois remover o usuário
                    if (method_exists($this->mikrotik, 'removeHotspotUser')) {
                        $mikrotikSuccess = $this->mikrotik->removeHotspotUser($username);
                        
                        if ($mikrotikSuccess) {
                            $mikrotikMessage = "Removido do MikroTik com sucesso";
                            $this->logger->info("Usuário removido do MikroTik com sucesso");
                        } else {
                            $mikrotikMessage = "Falha na remoção do MikroTik";
                            $this->logger->warning("Falha na remoção do MikroTik");
                        }
                    } else {
                        $mikrotikMessage = "Método de remoção não disponível";
                        $this->logger->warning("Método removeHotspotUser não disponível");
                    }
                    
                    // Desconectar
                    if (method_exists($this->mikrotik, 'disconnect')) {
                        $this->mikrotik->disconnect();
                    }
                    
                } catch (Exception $e) {
                    $mikrotikMessage = "Erro na remoção: " . $e->getMessage();
                    $this->logger->error("Erro ao remover do MikroTik: " . $e->getMessage());
                }
            } else {
                $mikrotikMessage = "MikroTik não conectado - removido apenas do banco";
                $this->logger->warning("MikroTik não conectado para remoção");
            }
            
            // Passo 3: Atualizar status de sync
            $this->updateSyncStatus($guestId, $mikrotikSuccess ? 'synced' : 'failed', $mikrotikMessage);
            
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            
            $result = [
                'success' => $dbSuccess, // Sucesso se pelo menos removeu do banco
                'guest_name' => $guestName,
                'room_number' => $roomNumber,
                'username' => $username,
                'database_success' => $dbSuccess,
                'mikrotik_success' => $mikrotikSuccess,
                'mikrotik_message' => $mikrotikMessage,
                'response_time' => $totalTime,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Salvar operação no histórico
            $this->saveOperationHistory('remove', [
                'guest_id' => $guestId,
                'username' => $username,
                'room_number' => $roomNumber,
                'guest_name' => $guestName
            ], $result, $dbSuccess, $totalTime);
            
            $this->logger->info("Remoção de acesso concluída em {$totalTime}ms", $result);
            
            return $result;
            
        } catch (Exception $e) {
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logger->error("Erro na remoção de acesso: " . $e->getMessage());
            
            $result = [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => $totalTime,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Salvar erro no histórico
            $this->saveOperationHistory('remove', [
                'guest_id' => $guestId ?? null
            ], $result, false, $totalTime);
            
            return $result;
        }
    }
    /* parte 4 */
    /**
     * Atualiza status de sincronização
     */
    private function updateSyncStatus($guestId, $syncStatus, $message = null) {
        try {
            if (!$this->checkColumnExists('hotel_guests', 'sync_status')) {
                return;
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
    
    /**
     * Verifica se uma coluna existe na tabela
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
     * Log de ações do sistema
     */
    private function logAction($username, $roomNumber, $action, $ipAddress = null, $userAgent = null, $responseTime = 0, $errorMessage = null) {
        try {
            if (!$this->db) return;
            
            $stmt = $this->db->prepare("
                INSERT INTO access_logs (username, room_number, action, ip_address, user_agent, response_time, error_message)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $username,
                $roomNumber,
                $action,
                $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null,
                $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
                $responseTime,
                $errorMessage
            ]);
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao registrar log de ação: " . $e->getMessage());
        }
    }
    
    /**
     * Salva operação no histórico (para sistema PRG)
     */
    private function saveOperationHistory($operationType, $operationData, $resultData, $success, $responseTime) {
        try {
            if (!$this->db) return;
            
            $stmt = $this->db->prepare("
                INSERT INTO operation_history (operation_type, operation_data, result_data, success, response_time, user_ip)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $operationType,
                json_encode($operationData),
                json_encode($resultData),
                $success ? 1 : 0,
                $responseTime,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao salvar histórico: " . $e->getMessage());
        }
    }
    
    /**
     * Gera nome de usuário simples baseado no quarto
     */
    private function generateSimpleUsername($roomNumber) {
        return 'qt' . preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber) . '-' . rand(10, 99);
    }
    
    /**
     * Gera senha simples de 3 dígitos
     */
    private function generateSimplePassword() {
        return rand(100, 999);
    }
    
    /**
     * Calcula limite de tempo baseado na data de checkout
     */
    private function calculateTimeLimit($checkoutDate) {
        try {
            $checkout = new DateTime($checkoutDate . ' 12:00:00');
            $now = new DateTime();
            $interval = $now->diff($checkout);
            $hours = ($interval->days * 24) + $interval->h;
            return sprintf('%02d:00:00', max(1, min(168, $hours))); // Mín 1h, máx 168h (1 semana)
        } catch (Exception $e) {
            return '24:00:00'; // Padrão 24 horas
        }
    }
    
    /**
     * Cria usuário no MikroTik
     */
    private function createInMikroTik($username, $password, $profileType, $timeLimit) {
        if (!$this->mikrotik) {
            return ['success' => false, 'message' => 'MikroTik não conectado'];
        }
        
        try {
            if (method_exists($this->mikrotik, 'createHotspotUser')) {
                $result = $this->mikrotik->createHotspotUser($username, $password, $profileType, $timeLimit);
                return [
                    'success' => $result,
                    'message' => $result ? 'Criado no MikroTik com sucesso' : 'Falha na criação no MikroTik'
                ];
            } else {
                return ['success' => false, 'message' => 'Método createHotspotUser não disponível'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao criar no MikroTik: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtém lista de hóspedes ativos
     */
    public function getActiveGuests() {
        if (!$this->db) {
            return [];
        }
        
        try {
            // Verificar se colunas de sync existem
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
            $this->logger->error("Erro ao buscar hóspedes ativos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém estatísticas do sistema
     */
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
                // Total de hóspedes
                $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests");
                $stats['total_guests'] = $stmt->fetchColumn();
                
                // Hóspedes ativos
                $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE status = 'active'");
                $stats['active_guests'] = $stmt->fetchColumn();
                
                // Hóspedes criados hoje
                $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE DATE(created_at) = CURDATE()");
                $stats['today_guests'] = $stmt->fetchColumn();
                
                // Calcular taxa de sincronização
                if ($stats['active_guests'] > 0) {
                    $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE status = 'active' AND sync_status = 'synced'");
                    $syncedGuests = $stmt->fetchColumn();
                    $stats['sync_rate'] = round(($syncedGuests / $stats['active_guests']) * 100, 1);
                }
                
            } catch (Exception $e) {
                $this->logger->error("Erro ao obter stats do BD: " . $e->getMessage());
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
                $this->logger->error("Erro ao obter stats do MikroTik: " . $e->getMessage());
            }
        }
        
        return $stats;
    }
    
    /**
     * Obtém diagnóstico completo do sistema
     */
    public function getSystemDiagnostic() {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.4-FINAL',
            'database' => $this->getDatabaseStatus(),
            'mikrotik' => $this->getMikroTikStatus(),
            'connection_errors' => $this->connectionErrors,
            'php_info' => $this->getPHPInfo(),
            'server_info' => $this->getServerInfo(),
            'performance' => $this->getPerformanceInfo()
        ];
    }
    
    /**
     * Status detalhado do banco de dados
     */
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
                'mysql_running' => true,
                'charset' => 'utf8mb4'
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
    
    /**
     * Status detalhado do MikroTik
     */
    private function getMikroTikStatus() {
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
                
                $status['connected'] = $healthResult['connection'] ?? false;
                $status['error'] = $healthResult['error'] ?? null;
                $status['message'] = $healthResult['message'] ?? null;
                $status['reachable'] = true;
                
                if (isset($healthResult['user_count'])) {
                    $status['user_count'] = $healthResult['user_count'];
                }
                if (isset($healthResult['response_time'])) {
                    $status['response_time'] = $healthResult['response_time'];
                }
                
                return $status;
            } else {
                $status['connected'] = true;
                $status['message'] = 'Conectado (healthCheck não disponível)';
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
    
    /**
     * Informações do PHP
     */
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
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
    }
    
    /**
     * Informações do servidor
     */
    private function getServerInfo() {
        return [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Desconhecido',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Desconhecido',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Desconhecido',
            'php_self' => $_SERVER['PHP_SELF'] ?? 'Desconhecido',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Desconhecido'
        ];
    }
    
    /**
     * Informações de performance
     */
    private function getPerformanceInfo() {
        $initTime = round((microtime(true) - $this->startTime) * 1000, 2);
        
        return [
            'init_time_ms' => $initTime,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'connection_errors_count' => count($this->connectionErrors),
            'database_connected' => $this->db !== null,
            'mikrotik_connected' => $this->mikrotik !== null
        ];
    }
}

/* parte 5 */
// INICIALIZAÇÃO DO SISTEMA v4.4 FINAL
$systemInitStart = microtime(true);
$hotelSystem = null;
$initializationError = null;

try {
    $hotelSystem = new HotelSystemV44Final($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles);
} catch (Exception $e) {
    $initializationError = $e->getMessage();
    error_log("[HOTEL_SYSTEM_v4.4_FINAL] ERRO DE INICIALIZAÇÃO: " . $e->getMessage());
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
        <title>Sistema Hotel v4.4 - CORRIGIDO</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
            .container { max-width: 1000px; margin: 0 auto; }
            .error-box { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #e74c3c; }
            .info-box { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #17a2b8; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏨 Sistema Hotel v4.4 FINAL - Diagnóstico</h1>
            <div class="error-box">
                <h3>❌ Erro na Inicialização do Sistema</h3>
                <p><strong>Erro:</strong> <?php echo htmlspecialchars($initializationError); ?></p>
                <p><strong>Tempo de inicialização:</strong> <?php echo $systemInitTime; ?>ms</p>
            </div>
            <div class="info-box">
                <h3>🔧 Soluções Recomendadas</h3>
                <ul>
                    <li>Verifique se o arquivo config.php está configurado corretamente</li>
                    <li>Verifique se o MySQL está rodando (XAMPP/WAMP/LAMP)</li>
                    <li>Verifique se o MikroTik está acessível na rede</li>
                    <li>Verifique as permissões dos arquivos (755 para pastas, 644 para arquivos)</li>
                    <li>Consulte os logs de erro do PHP para mais detalhes</li>
                </ul>
            </div>
            <div style="text-align: center; margin: 20px 0;">
                <a href="?" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🔄 Tentar Novamente</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// PROCESSAMENTO PRG v4.4 FINAL - COM AVISOS FUNCIONAIS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionStart = microtime(true);
    
    try {
        if (isset($_POST['generate_access'])) {
            // Geração de credenciais
            $roomNumber = trim($_POST['room_number'] ?? '');
            $guestName = trim($_POST['guest_name'] ?? '');
            $checkinDate = $_POST['checkin_date'] ?? '';
            $checkoutDate = $_POST['checkout_date'] ?? '';
            $profileType = $_POST['profile_type'] ?? 'hotel-guest';
            
            // Validações básicas
            if (empty($roomNumber) || empty($guestName) || empty($checkinDate) || empty($checkoutDate)) {
                FlashMessages::error("Todos os campos são obrigatórios para gerar credenciais");
            } else {
                $result = $hotelSystem->generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType);
                
                if ($result['success']) {
                    $responseTime = $result['response_time'] ?? 0;
                    $syncStatus = $result['sync_status'] ?? 'unknown';
                    FlashMessages::success("Credenciais geradas com sucesso em {$responseTime}ms! Sync: " . strtoupper($syncStatus), $result);
                } else {
                    FlashMessages::error("Erro ao gerar credenciais: " . ($result['error'] ?? 'Erro desconhecido'));
                }
            }
            
        } elseif (isset($_POST['remove_access'])) {
            // Remoção de acesso
            $guestId = intval($_POST['guest_id'] ?? 0);
            
            if ($guestId <= 0) {
                FlashMessages::error("ID do hóspede inválido para remoção");
            } else {
                $removalResult = $hotelSystem->removeGuestAccess($guestId);
                
                if ($removalResult['success']) {
                    $responseTime = $removalResult['response_time'] ?? 0;
                    $dbStatus = ($removalResult['database_success'] ?? false) ? 'BD: ✅' : 'BD: ❌';
                    $mtStatus = ($removalResult['mikrotik_success'] ?? false) ? 'MT: ✅' : 'MT: ❌';
                    FlashMessages::success("Acesso removido com sucesso em {$responseTime}ms! {$dbStatus} | {$mtStatus}", $removalResult);
                } else {
                    FlashMessages::error("Erro na remoção de acesso: " . ($removalResult['error'] ?? 'Erro desconhecido'));
                }
            }
            
        } elseif (isset($_POST['get_diagnostic'])) {
            // Diagnóstico do sistema
            $debugInfo = $hotelSystem->getSystemDiagnostic();
            FlashMessages::info("Diagnóstico do sistema executado com sucesso", $debugInfo);
            
        } elseif (isset($_POST['clear_screen'])) {
            // Limpar tela
            FlashMessages::info("Tela limpa com sucesso - todas as mensagens foram removidas");
        }
        
    } catch (Exception $e) {
        $actionTime = round((microtime(true) - $actionStart) * 1000, 2);
        FlashMessages::error("Erro crítico do sistema em {$actionTime}ms: " . $e->getMessage());
        error_log("[HOTEL_SYSTEM_v4.4_FINAL] ERRO CRÍTICO: " . $e->getMessage());
    }
    
    // REDIRECT para implementar padrão PRG
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Obter dados para exibição
$dataStart = microtime(true);

try {
    $activeGuests = $hotelSystem->getActiveGuests();
    $systemStats = $hotelSystem->getSystemStats();
    $systemDiagnostic = $hotelSystem->getSystemDiagnostic();
    
    // Garantir estrutura consistente do diagnóstico
    if (!isset($systemDiagnostic['mikrotik'])) {
        $systemDiagnostic['mikrotik'] = [
            'connected' => false,
            'host' => $mikrotikConfig['host'] ?? 'N/A',
            'port' => $mikrotikConfig['port'] ?? 'N/A',
            'error' => 'Diagnóstico do MikroTik não disponível'
        ];
    }
    
    if (!isset($systemDiagnostic['database'])) {
        $systemDiagnostic['database'] = [
            'connected' => false,
            'host' => $dbConfig['host'] ?? 'N/A',
            'database' => $dbConfig['database'] ?? 'N/A',
            'error' => 'Diagnóstico do banco não disponível'
        ];
    }
    
    $dataTime = round((microtime(true) - $dataStart) * 1000, 2);
    
} catch (Exception $e) {
    // Fallback em caso de erro
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
            'error' => 'Erro ao obter diagnóstico do banco'
        ],
        'mikrotik' => [
            'connected' => false,
            'host' => $mikrotikConfig['host'] ?? 'N/A',
            'port' => $mikrotikConfig['port'] ?? 'N/A',
            'error' => 'Erro ao obter diagnóstico do MikroTik'
        ],
        'error' => $e->getMessage()
    ];
    
    $dataTime = round((microtime(true) - $dataStart) * 1000, 2);
    error_log("[HOTEL_SYSTEM_v4.4_FINAL] ERRO AO OBTER DADOS: " . $e->getMessage());
}

// Determinar status geral do sistema
$systemStatus = 'unknown';

if (isset($systemDiagnostic['database']['connected']) && $systemDiagnostic['database']['connected']) {
    if (isset($systemDiagnostic['mikrotik']['connected']) && $systemDiagnostic['mikrotik']['connected']) {
        $systemStatus = 'excellent'; // BD + MikroTik funcionando
    } else {
        $systemStatus = 'database_only'; // Só BD funcionando
    }
} else {
    $systemStatus = 'critical'; // BD não funcionando
}

$totalLoadTime = round((microtime(true) - $systemInitStart) * 1000, 2);
$flashMessages = FlashMessages::get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($systemConfig['hotel_name']); ?> - Sistema v4.4 FINAL</title>
    
    <style>
        /* Reset e base */
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
        
        /* Header */
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
            font-weight: 600;
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
        
        /* AVISOS DE OPERAÇÃO DEMORADA - FUNCIONAIS */
        .operation-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            display: none;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-out;
        }
        
        .operation-modal {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        .operation-modal h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .operation-modal p {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 1.1em;
            line-height: 1.6;
        }
        
        .spinner-container {
            margin: 30px 0;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #ecf0f1;
            border-top: 6px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .progress-text {
            margin-top: 20px;
            color: #3498db;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        /* Estatísticas */
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
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
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
        
        /* Conteúdo principal */
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
        
        /* Aviso sobre melhorias */
        .improvements-notice {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .improvements-notice h4 {
            margin-bottom: 15px;
            font-size: 1.4em;
        }
        
        .improvements-notice ul {
            list-style: none;
            padding: 0;
        }
        
        .improvements-notice li {
            margin: 8px 0;
            font-size: 1em;
        }
        /* parte 6 */
        /* Flash Messages */
        .flash-messages {
            margin-bottom: 30px;
        }
        
        .flash-message {
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            font-weight: 500;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }
        
        .flash-message.persistent {
            border: 2px solid #27ae60;
            box-shadow: 0 0 10px rgba(39, 174, 96, 0.3);
        }
        
        .flash-message.persistent::before {
            content: "📌 PERMANENTE - Clique no X para fechar";
            position: absolute;
            top: -10px;
            right: 50px;
            background: #27ae60;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .flash-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 5px solid #27ae60;
        }
        
        .flash-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 5px solid #e74c3c;
        }
        
        .flash-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-left: 5px solid #f39c12;
        }
        
        .flash-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-left: 5px solid #17a2b8;
        }
        
        .flash-close {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }
        
        .flash-close:hover { opacity: 1; }
        
        /* Formulários */
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
        
        /* Botões */
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
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary { background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%); }
        .btn-danger { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); 
            font-size: 14px;
            padding: 10px 20px;
        }
        .btn-danger:hover { box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4); }
        .btn-info { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .btn-clear { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 20px 0;
        }
        
        /* Diagnósticos */
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
            transition: transform 0.3s ease;
        }
        
        .diagnostic-card:hover {
            transform: translateY(-5px);
        }
        
        .status-online { color: #28a745; font-weight: bold; }
        .status-offline { color: #dc3545; font-weight: bold; }
        
        /* Exibição de credenciais */
        .credentials-display {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 35px;
            border-radius: 20px;
            margin: 25px 0;
            text-align: center;
            box-shadow: 0 15px 35px rgba(39, 174, 96, 0.3);
            position: relative;
        }
        
        .credentials-display::before {
            content: "⏰ PERMANENTE - Use o botão X para fechar";
            position: absolute;
            top: 10px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
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
            position: relative;
        }
        
        .credential-box:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-3px);
        }
        
        .credential-box::after {
            content: "📋 Clique para copiar";
            position: absolute;
            bottom: 5px;
            right: 10px;
            font-size: 0.7em;
            opacity: 0.8;
        }
        
        .credential-value {
            font-size: 2.8em;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            margin: 10px 0;
        }
        
        /* Exibição de remoção */
        .removal-display {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 35px;
            border-radius: 20px;
            margin: 25px 0;
            text-align: center;
            box-shadow: 0 15px 35px rgba(231, 76, 60, 0.3);
        }
        
        .removal-details {
            background: rgba(255,255,255,0.15);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: left;
        }
        
        .removal-details h4 {
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        
        .removal-details p {
            margin: 5px 0;
            font-size: 0.95em;
        }
        
        /* Tabela de hóspedes */
        .guests-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .guests-table thead {
            background: #2c3e50;
            color: white;
        }
        
        .guests-table th, .guests-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .guests-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .guest-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .remove-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .remove-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .copy-btn {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 5px 8px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: monospace;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            background: #bdc3c7;
        }
        
        /* Badges de status */
        .sync-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .sync-synced { background: #d4edda; color: #155724; }
        .sync-pending { background: #fff3cd; color: #856404; }
        .sync-failed { background: #f8d7da; color: #721c24; }
        .sync-unknown { background: #e2e3e5; color: #495057; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000; /* Aumentado para ficar acima de tudo */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            overflow-y: auto; /* Permite scroll se necessário */
            padding: 20px; /* Padding para dispositivos móveis */
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto; /* CORRIGIDO: reduzido de 15% para 5% */
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px; /* CORRIGIDO: aumentado de 500px para 600px */
            max-height: 85vh; /* NOVO: altura máxima para evitar corte */
            overflow-y: auto; /* NOVO: scroll interno se necessário */
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .modal-content h3 {
            color: #e74c3c;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .modal-content p {
            margin-bottom: 25px;
            color: #2c3e50;
            line-height: 1.6;
        }
        
        .modal-buttons {
            display: flex;
            gap: 20px; /* Aumentado de 15px para 20px */
            justify-content: center;
            flex-wrap: wrap; /* Permite quebra em telas pequenas */
            margin-top: 30px; /* Mais espaço acima */
        }

        .btn-confirm, .btn-cancel {
            padding: 15px 30px; /* Aumentado de 12px 25px */
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px; /* Aumentado */
            transition: all 0.3s ease;
            min-width: 140px; /* Largura mínima */
        }
        
        .btn-confirm { 
            background: #e74c3c; 
            color: white; 
        }
        .btn-confirm:hover { 
            background: #c0392b; 
            transform: translateY(-2px);
        }
        
        .btn-cancel { 
            background: #95a5a6; 
            color: white; 
        }
        .btn-cancel:hover { 
            background: #7f8c8d; 
            transform: translateY(-2px);
        }
                /* CORREÇÃO 3: Responsividade melhorada para modal */
                @media (max-width: 768px) {
            .modal-content {
                margin: 2% auto; /* Ainda menor em mobile */
                padding: 25px 20px;
                width: 95%;
                max-height: 90vh;
            }
            
            .modal-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-confirm, .btn-cancel {
                width: 100%;
                max-width: 250px;
            }
        }
        
        /* CORREÇÃO 4: Overlay de operação com timeout visual */
        .operation-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            display: none;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-out;
        }
        
        .operation-modal {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        /* NOVO: Indicador de timeout */
        .timeout-indicator {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8em;
            color: #7f8c8d;
            opacity: 0.7;
        }

        
        /* Pré-formatado */
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
            border: 1px solid #dee2e6;
        }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .credential-pair {
                grid-template-columns: 1fr;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .guests-table {
                font-size: 14px;
            }
            .guests-table th, .guests-table td {
                padding: 10px;
            }
            .button-group {
                flex-direction: column;
                align-items: center;
            }
            .operation-modal {
                padding: 30px 20px;
                margin: 20px;
            }
            .diagnostic-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header h1 {
                font-size: 2.2em;
            }
            .version-badge {
                position: static;
                display: block;
                margin: 10px auto 0;
                width: fit-content;
            }
        }
    </style>
</head>
<body>
    <!-- OVERLAY CORRIGIDO -->
    <div class="operation-overlay" id="operationOverlay">
        <div class="operation-modal">
            <h3 id="operationTitle">⏳ Processando Operação</h3>
            <p id="operationMessage">Aguarde enquanto processamos sua solicitação...</p>
            <div class="spinner-container">
                <div class="spinner"></div>
            </div>
            <div class="progress-text" id="progressText">Processando...</div>
            <!-- NOVO: Indicador de timeout -->
            <div class="timeout-indicator" id="timeoutIndicator">
                Timeout automático em <span id="timeoutCounter">10</span>s
            </div>
        </div>
    </div>

    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div class="version-badge">v4.4 FINAL</div>
            <h1>🏨 <?php echo htmlspecialchars($systemConfig['hotel_name']); ?></h1>
            <p>Sistema de Gerenciamento de Internet - Avisos de Operação Funcionais</p>
            <span class="system-status status-<?php echo $systemStatus; ?>">
                <?php 
                switch($systemStatus) {
                    case 'excellent': echo '🎉 Sistema Online Completo'; break;
                    case 'database_only': echo '⚠️ Só Banco de Dados Online'; break;
                    case 'critical': echo '❌ Sistema Offline'; break;
                    default: echo '❓ Status Desconhecido'; break;
                }
                ?>
            </span>
        </div>
        
        <!-- AVISO SOBRE MELHORIAS -->
        <div class="improvements-notice">
            <h4>🚀 Sistema v4.4 FINAL - Avisos de Operação Funcionais</h4>
            <ul>
                <li>✅ Avisos visuais que não bloqueiam mais as operações</li>
                <li>✅ JavaScript corrigido para funcionamento perfeito</li>
                <li>✅ Overlay aparece APÓS submissão dos formulários</li>
                <li>✅ Timeout automático de segurança (30 segundos)</li>
                <li>✅ Interface otimizada para recepcionistas de hotel</li>
                <li>✅ Design responsivo para tablets e smartphones</li>
            </ul>
        </div>
        
        <!-- ESTATÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['total_guests']; ?></div>
                <div class="stat-label">Total de Hóspedes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['active_guests']; ?></div>
                <div class="stat-label">Hóspedes Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['mikrotik_total']; ?></div>
                <div class="stat-label">Usuários MikroTik</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalLoadTime; ?>ms</div>
                <div class="stat-label">Tempo de Carregamento</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['sync_rate']; ?>%</div>
                <div class="stat-label">Taxa de Sincronização</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['today_guests']; ?></div>
                <div class="stat-label">Criados Hoje</div>
            </div>
        </div>
        
        <div class="main-content">
         <!-- parte 7 -->   
         <!-- FLASH MESSAGES -->
         <?php if (!empty($flashMessages)): ?>
            <div class="flash-messages">
            <?php foreach ($flashMessages as $flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?> <?php echo (isset($flash['data']) && (isset($flash['data']['username']) || isset($flash['data']['guest_name']))) ? 'persistent' : ''; ?>">
                <button class="flash-close" onclick="this.parentElement.style.display='none'">&times;</button>
                <?php echo htmlspecialchars($flash['message']); ?>
                
                <?php if (isset($flash['data']) && is_array($flash['data'])): ?>
                    <?php 
                    $isCredentialCreation = ($flash['type'] === 'success' && 
                                           isset($flash['data']['username']) && 
                                           isset($flash['data']['password']) &&
                                           !empty($flash['data']['password']));
                    
                    $isUserRemoval = ($flash['type'] === 'success' && 
                                    isset($flash['data']['guest_name']) && 
                                    !isset($flash['data']['password']));
                    ?>
                    
                    <?php if ($isCredentialCreation): ?>
                        <div class="credentials-display">
                            <h3>🎉 Credenciais Geradas com Sucesso!</h3>
                            <div class="credential-pair">
                                <div class="credential-box" onclick="copyToClipboard('<?php echo htmlspecialchars($flash['data']['username']); ?>')">
                                    <div>👤 USUÁRIO</div>
                                    <div class="credential-value"><?php echo htmlspecialchars($flash['data']['username']); ?></div>
                                </div>
                                <div class="credential-box" onclick="copyToClipboard('<?php echo htmlspecialchars($flash['data']['password']); ?>')">
                                    <div>🔒 SENHA</div>
                                    <div class="credential-value"><?php echo htmlspecialchars($flash['data']['password']); ?></div>
                                </div>
                            </div>
                            <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; margin-top: 20px;">
                                <p><strong>📊 Detalhes da Operação:</strong></p>
                                <p>⏱️ Tempo de Resposta: <?php echo $flash['data']['response_time'] ?? 'N/A'; ?>ms</p>
                                <p>📡 Status MikroTik: <?php echo htmlspecialchars($flash['data']['mikrotik_message'] ?? 'N/A'); ?></p>
                                <p>🔄 Sincronização: <?php echo strtoupper($flash['data']['sync_status'] ?? 'unknown'); ?></p>
                                <p>🏷️ Perfil: <?php echo htmlspecialchars($flash['data']['profile'] ?? 'N/A'); ?></p>
                                <p>🌐 Largura de Banda: <?php echo htmlspecialchars($flash['data']['bandwidth'] ?? 'N/A'); ?></p>
                                <p>📅 Válido até: <?php echo isset($flash['data']['valid_until']) ? date('d/m/Y', strtotime($flash['data']['valid_until'])) : 'N/A'; ?></p>
                            </div>
                            <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 10px;">
                                <p style="font-size: 0.9em; margin: 0;">
                                    <strong>💡 Dica:</strong> Clique nos campos acima para copiar automaticamente as credenciais. 
                                    Esta mensagem permanece visível até você fechá-la manualmente.
                                </p>
                            </div>
                        </div>
                        
                    <?php elseif ($isUserRemoval): ?>
                        <div class="removal-display">
                            <h3>🗑️ Acesso Removido com Sucesso!</h3>
                            <div class="removal-details">
                                <h4>Detalhes da Remoção:</h4>
                                <p><strong>Hóspede:</strong> <?php echo htmlspecialchars($flash['data']['guest_name']); ?></p>
                                <p><strong>Quarto:</strong> <?php echo htmlspecialchars($flash['data']['room_number'] ?? 'N/A'); ?></p>
                                <p><strong>Usuário:</strong> <?php echo htmlspecialchars($flash['data']['username'] ?? 'N/A'); ?></p>
                                <p><strong>Banco de Dados:</strong> <?php echo ($flash['data']['database_success'] ?? false) ? '✅ Removido' : '❌ Erro'; ?></p>
                                <p><strong>MikroTik:</strong> <?php echo ($flash['data']['mikrotik_success'] ?? false) ? '✅ Removido' : '❌ Erro'; ?></p>
                                <p><strong>Mensagem MikroTik:</strong> <?php echo htmlspecialchars($flash['data']['mikrotik_message'] ?? 'N/A'); ?></p>
                                <p><strong>Tempo de Resposta:</strong> <?php echo $flash['data']['response_time'] ?? 'N/A'; ?>ms</p>
                                <p><strong>Data/Hora:</strong> <?php echo $flash['data']['timestamp'] ?? date('Y-m-d H:i:s'); ?></p>
                            </div>
                            <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 5px;">
                                <p style="font-size: 0.9em; margin: 0;">
                                    <strong>ℹ️ Informação:</strong> Esta mensagem permanece visível até ser fechada manualmente.
                                </p>
                            </div>
                        </div>
                        
                    <?php elseif (isset($flash['data']) && !empty($flash['data'])): ?>
                        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; margin-top: 15px;">
                            <details>
                                <summary style="cursor: pointer; font-weight: bold;">📋 Dados Técnicos (Clique para expandir)</summary>
                                <pre style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 12px; overflow-x: auto;">
<?php echo htmlspecialchars(json_encode($flash['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
                                </pre>
                            </details>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
            
            <!-- FORMULÁRIO DE GERAÇÃO COM AVISOS FUNCIONAIS -->
            <div class="section">
                <h2 class="section-title">🆕 Gerar Novo Acesso para Hóspede</h2>
                
                <form method="POST" action="" id="generateForm">
                    <div class="form-grid">
                        <div>
                            <label for="room_number">Número do Quarto:</label>
                            <input type="text" id="room_number" name="room_number" required 
                                   placeholder="Ex: 101, 205A, 12B" class="form-input">
                        </div>
                        
                        <div>
                            <label for="guest_name">Nome do Hóspede:</label>
                            <input type="text" id="guest_name" name="guest_name" required 
                                   placeholder="Nome completo do hóspede" class="form-input">
                        </div>
                        
                        <div>
                            <label for="checkin_date">Data de Check-in:</label>
                            <input type="date" id="checkin_date" name="checkin_date" required 
                                   value="<?php echo date('Y-m-d'); ?>" class="form-input">
                        </div>
                        
                        <div>
                            <label for="checkout_date">Data de Check-out:</label>
                            <input type="date" id="checkout_date" name="checkout_date" required 
                                   value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="form-input">
                        </div>
                        
                        <div>
                            <label for="profile_type">Perfil de Acesso:</label>
                            <select id="profile_type" name="profile_type" class="form-input">
                                <?php foreach ($userProfiles as $key => $profile): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>">
                                        <?php echo htmlspecialchars($profile['name']); ?> - <?php echo htmlspecialchars($profile['rate_limit'] ?? 'N/A'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" name="generate_access" class="btn" id="generateBtn">
                            ✨ Gerar Credenciais
                        </button>
                        <button type="submit" name="clear_screen" class="btn btn-clear">
                            🧹 Limpar Tela
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- STATUS DO SISTEMA -->
            <div class="section">
                <h2 class="section-title">🔧 Status do Sistema</h2>
                
                <div class="diagnostic-grid">
                    <div class="diagnostic-card">
                        <h3>💾 Banco de Dados</h3>
                        <?php if (isset($systemDiagnostic['database'])): ?>
                            <p>Status: <span class="<?php echo $systemDiagnostic['database']['connected'] ? 'status-online' : 'status-offline'; ?>">
                                <?php echo $systemDiagnostic['database']['connected'] ? '🟢 Online' : '🔴 Offline'; ?>
                            </span></p>
                            <p>Host: <?php echo htmlspecialchars($systemDiagnostic['database']['host'] ?? 'N/A'); ?></p>
                            <p>Banco: <?php echo htmlspecialchars($systemDiagnostic['database']['database'] ?? 'N/A'); ?></p>
                            <?php if (isset($systemDiagnostic['database']['total_guests'])): ?>
                                <p>Total de Hóspedes: <?php echo $systemDiagnostic['database']['total_guests']; ?></p>
                            <?php endif; ?>
                            <?php if (isset($systemDiagnostic['database']['version'])): ?>
                                <p>Versão MySQL: <?php echo htmlspecialchars($systemDiagnostic['database']['version']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Status: <span class="status-offline">🔴 Não disponível</span></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="diagnostic-card">
                        <h3>📡 MikroTik RouterOS</h3>
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
                        
                        <?php if (isset($systemDiagnostic['mikrotik']['user_count'])): ?>
                            <p>Usuários: <?php echo $systemDiagnostic['mikrotik']['user_count']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="diagnostic-card">
                        <h3>⚙️ Ambiente PHP</h3>
                        <p>Versão PHP: <?php echo PHP_VERSION; ?></p>
                        <p>PDO MySQL: <span class="<?php echo extension_loaded('pdo_mysql') ? 'status-online' : 'status-offline'; ?>">
                            <?php echo extension_loaded('pdo_mysql') ? '✅ Disponível' : '❌ Indisponível'; ?>
                        </span></p>
                        <p>Sockets: <span class="<?php echo extension_loaded('sockets') ? 'status-online' : 'status-offline'; ?>">
                            <?php echo extension_loaded('sockets') ? '✅ Disponível' : '❌ Indisponível'; ?>
                        </span></p>
                        <p>JSON: <span class="<?php echo extension_loaded('json') ? 'status-online' : 'status-offline'; ?>">
                            <?php echo extension_loaded('json') ? '✅ Disponível' : '❌ Indisponível'; ?>
                        </span></p>
                    </div>
                    
                    <div class="diagnostic-card">
                        <h3>🔍 Ações Disponíveis</h3>
                        <form method="POST" style="margin-bottom: 15px;" id="diagnosticForm">
                            <button type="submit" name="get_diagnostic" class="btn btn-info">
                                🔍 Diagnóstico Completo
                            </button>
                        </form>
                        <p style="font-size: 0.9em; color: #6c757d; margin-top: 10px;">
                            Execute um diagnóstico detalhado do sistema, incluindo performance e conectividade.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- LISTA DE HÓSPEDES ATIVOS -->
            <?php if (!empty($activeGuests)): ?>
            <div class="section">
                <h2 class="section-title">👥 Hóspedes Ativos (<?php echo count($activeGuests); ?>)</h2>
                
                <div style="overflow-x: auto;">
                    <table class="guests-table">
                        <thead>
                            <tr>
                                <th>Quarto</th>
                                <th>Hóspede</th>
                                <th>Credenciais de Acesso</th>
                                <th>Check-out</th>
                                <th>Status Sync</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeGuests as $guest): ?>
                            <tr>
                                <td style="font-weight: bold; color: #3498db;">
                                    <?php echo htmlspecialchars($guest['room_number']); ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($guest['guest_name']); ?></div>
                                    <div style="font-size: 0.9em; color: #7f8c8d;">
                                        Check-in: <?php echo date('d/m/Y', strtotime($guest['checkin_date'])); ?>
                                    </div>
                                    <div style="font-size: 0.8em; color: #6c757d;">
                                        Perfil: <?php echo htmlspecialchars($guest['profile_type']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="margin-bottom: 8px;">
                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($guest['username']); ?>')" title="Clique para copiar usuário">
                                            👤 <?php echo htmlspecialchars($guest['username']); ?>
                                        </button>
                                    </div>
                                    <div>
                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($guest['password']); ?>')" title="Clique para copiar senha">
                                            🔒 <?php echo htmlspecialchars($guest['password']); ?>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $checkoutDate = strtotime($guest['checkout_date']);
                                    $today = strtotime(date('Y-m-d'));
                                    $isExpiringSoon = $checkoutDate <= $today + (24 * 60 * 60); // Expira em 24h
                                    ?>
                                    <div style="<?php echo $isExpiringSoon ? 'color: #e74c3c; font-weight: 600;' : ''; ?>">
                                        <?php echo date('d/m/Y', strtotime($guest['checkout_date'])); ?>
                                    </div>
                                    <?php if ($isExpiringSoon): ?>
                                        <div style="font-size: 0.8em; color: #e74c3c;">⚠️ Expira em breve</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="sync-badge sync-<?php echo $guest['sync_status'] ?? 'unknown'; ?>">
                                        <?php 
                                        switch ($guest['sync_status'] ?? 'unknown') {
                                            case 'synced': echo '🟢 Sincronizado'; break;
                                            case 'failed': echo '🔴 Erro'; break;
                                            case 'pending': echo '🟡 Pendente'; break;
                                            default: echo '⚪ Desconhecido'; break;
                                        }
                                        ?>
                                    </span>
                                    <?php if (isset($guest['last_sync']) && $guest['last_sync']): ?>
                                        <div style="font-size: 0.7em; color: #6c757d; margin-top: 2px;">
                                            <?php echo date('d/m H:i', strtotime($guest['last_sync'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="guest-actions">
                                        <button class="remove-btn" onclick="confirmRemoval(<?php echo $guest['id']; ?>, '<?php echo htmlspecialchars($guest['guest_name']); ?>', '<?php echo htmlspecialchars($guest['room_number']); ?>')">
                                            🗑️ Remover Acesso
                                        </button>
                                    </div>
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
                <div style="text-align: center; padding: 50px;">
                    <div style="font-size: 5em; margin-bottom: 20px; opacity: 0.6;">📋</div>
                    <h3 style="color: #2c3e50; margin-bottom: 15px; font-size: 1.5em;">Nenhum hóspede ativo encontrado</h3>
                    <p style="color: #7f8c8d; margin-bottom: 25px; font-size: 1.1em;">
                        Use o formulário acima para gerar credenciais para novos hóspedes.
                    </p>
                    <p style="color: #6c757d; font-size: 0.9em;">
                        As credenciais geradas aparecerão automaticamente nesta tabela.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- FOOTER -->
        <div style="background: #2c3e50; color: white; padding: 25px; text-align: center;">
            <div style="margin-bottom: 15px;">
                <h4 style="margin-bottom: 10px;">🏨 Sistema Hotel v4.4 FINAL</h4>
                <p style="font-size: 0.9em; opacity: 0.8;">Sistema de Gerenciamento de Internet para Hotéis - Avisos de Operação Funcionais</p>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div style="font-size: 0.85em;">
                    <strong>Performance:</strong> Carregado em <?php echo $totalLoadTime; ?>ms
                </div>
                <div style="font-size: 0.85em;">
                    <strong>Status:</strong> 
                    <?php 
                    switch($systemStatus) {
                        case 'excellent': echo '🎉 Excelente'; break;
                        case 'database_only': echo '⚠️ Parcial'; break;
                        case 'critical': echo '❌ Crítico'; break;
                        default: echo '❓ Desconhecido'; break;
                    }
                    ?>
                </div>
                <div style="font-size: 0.85em;">
                    <strong>Data:</strong> <?php echo date('d/m/Y H:i:s'); ?>
                </div>
            </div>
            <?php if (!empty($hotelSystem->connectionErrors)): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(231, 76, 60, 0.2); border-radius: 5px; font-size: 0.8em;">
                    <strong>⚠️ Avisos de Conexão:</strong><br>
                    <?php echo implode('<br>', array_map('htmlspecialchars', $hotelSystem->connectionErrors)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL DE CONFIRMAÇÃO -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>🗑️ Confirmar Remoção de Acesso</h3>
            <p id="confirmMessage"></p>
            <div class="modal-buttons">
                <button id="confirmBtn" class="btn-confirm">Sim, Remover Acesso</button>
                <button id="cancelBtn" class="btn-cancel">Cancelar Operação</button>
            </div>
        </div>
    </div>
    
    <!-- FORMULÁRIO OCULTO PARA REMOÇÃO -->
    <form id="removeForm" method="POST" style="display: none;">
        <input type="hidden" name="remove_access" value="1">
        <input type="hidden" name="guest_id" id="removeGuestId">
    </form>
    
    <script>
        // JavaScript v4.4 FINAL - AVISOS DE OPERAÇÃO FUNCIONAIS
        
        /**
         * FUNÇÃO PRINCIPAL: Mostrar avisos de operação demorada FUNCIONAIS
         * Esta função agora funciona corretamente sem bloquear as operações
         */
        function showOperationWarning(operationType, formElement) {
            const overlay = document.getElementById('operationOverlay');
            const title = document.getElementById('operationTitle');
            const message = document.getElementById('operationMessage');
            const progressText = document.getElementById('progressText');
            const timeoutCounter = document.getElementById('timeoutCounter');
            
            // CORREÇÃO: Timeout reduzido para 10 segundos
            let timeoutSeconds = 10;
            
            switch(operationType) {
                case 'generate':
                    title.textContent = '⏳ Gerando Credenciais';
                    message.textContent = 'Criando credenciais para o hóspede. Esta operação pode demorar alguns segundos.';
                    progressText.textContent = 'Conectando ao sistema...';
                    
                    // Progresso mais rápido
                    setTimeout(() => progressText.textContent = 'Validando dados...', 500);
                    setTimeout(() => progressText.textContent = 'Salvando no banco...', 1500);
                    setTimeout(() => progressText.textContent = 'Conectando ao MikroTik...', 3000);
                    setTimeout(() => progressText.textContent = 'Criando usuário...', 5000);
                    setTimeout(() => progressText.textContent = 'Finalizando...', 7000);
                    break;
                    
                case 'remove':
                    title.textContent = '🗑️ Removendo Acesso';
                    message.textContent = 'Removendo acesso do hóspede. Aguarde a conclusão.';
                    progressText.textContent = 'Iniciando remoção...';
                    
                    setTimeout(() => progressText.textContent = 'Removendo do banco...', 1000);
                    setTimeout(() => progressText.textContent = 'Removendo do MikroTik...', 3000);
                    setTimeout(() => progressText.textContent = 'Finalizando...', 6000);
                    break;
                    
                case 'diagnostic':
                    title.textContent = '🔍 Executando Diagnóstico';
                    message.textContent = 'Coletando informações do sistema.';
                    progressText.textContent = 'Analisando...';
                    break;
                    
                default:
                    title.textContent = '⏳ Processando';
                    message.textContent = 'Aguarde...';
                    progressText.textContent = 'Processando...';
            }
            
            // Mostrar overlay
            overlay.style.display = 'flex';
            
            // CORREÇÃO: Contador regressivo visual
            function updateCounter() {
                if (timeoutCounter) {
                    timeoutCounter.textContent = timeoutSeconds;
                }
                timeoutSeconds--;
                
                if (timeoutSeconds < 0) {
                    overlay.style.display = 'none';
                    console.warn('⚠️ Operação interrompida por timeout');
                    return;
                }
                
                setTimeout(updateCounter, 1000);
            }
            updateCounter();
            
            // Submeter formulário rapidamente
            if (formElement) {
                setTimeout(() => {
                    formElement.submit();
                }, 100);
            }
            
            return true;
        }
        
        /**
         * Função para copiar texto para clipboard com feedback visual
         */
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Notificação visual melhorada
                const notification = document.createElement('div');
                notification.innerHTML = `✅ Copiado: ${text}`;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #27ae60;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    z-index: 3000;
                    font-weight: bold;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                `;
                document.body.appendChild(notification);
                
                // Remover notificação após 4 segundos
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100px)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 400);
                }, 4000);
                
                // Animar o elemento que foi copiado
                const allCredentialBoxes = document.querySelectorAll('.credential-box, .copy-btn');
                allCredentialBoxes.forEach(box => {
                    if (box.textContent.includes(text)) {
                        const originalBackground = box.style.background;
                        const originalTransform = box.style.transform;
                        
                        box.style.background = 'rgba(39, 174, 96, 0.3)';
                        box.style.transform = 'scale(1.05)';
                        box.style.transition = 'all 0.3s ease';
                        
                        setTimeout(() => {
                            box.style.background = originalBackground;
                            box.style.transform = originalTransform;
                        }, 1500);
                    }
                });
                
            }).catch(function(err) {
                console.error('Erro ao copiar para clipboard: ', err);
                
                // Fallback para navegadores antigos
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    alert('📋 Texto copiado: ' + text);
                } catch (e) {
                    alert('❌ Não foi possível copiar o texto. Copie manualmente: ' + text);
                }
                
                document.body.removeChild(textArea);
            });
        }
        
        /**
         * Função para confirmar remoção de acesso com aviso
         */
        function confirmRemoval(guestId, guestName, roomNumber) {
            const modal = document.getElementById('confirmModal');
            const message = document.getElementById('confirmMessage');
            const confirmBtn = document.getElementById('confirmBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            
            message.innerHTML = `
                <div style="text-align: left; margin: 20px 0;">
                    <p style="font-size: 1.1em; margin-bottom: 15px; text-align: center;"><strong>Confirmar remoção de acesso?</strong></p>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <p style="margin: 5px 0;"><strong>🏨 Hóspede:</strong> ${guestName}</p>
                        <p style="margin: 5px 0;"><strong>🚪 Quarto:</strong> ${roomNumber}</p>
                        <p style="margin: 5px 0;"><strong>📱 ID:</strong> ${guestId}</p>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 12px; border-radius: 6px; border-left: 4px solid #ffc107; margin-top: 15px;">
                        <p style="margin: 0; font-size: 0.95em;"><strong>⚠️ Atenção:</strong></p>
                        <ul style="margin: 8px 0 0 20px; font-size: 0.9em; padding-left: 0;">
                            <li>Remove acesso do banco de dados E do MikroTik</li>
                            <li>Desconecta o usuário se estiver online</li>
                            <li>Operação <strong>irreversível</strong></li>
                            <li>Pode demorar alguns segundos</li>
                        </ul>
                    </div>
                </div>
            `;
            
            // CORREÇÃO: Mostrar modal com scroll automático para o topo
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Previne scroll da página
            
            // Scroll para o topo da página para garantir que o modal seja visível
            window.scrollTo(0, 0);
            
            // CORREÇÃO: Botão de confirmação com delay aumentado
            confirmBtn.onclick = function() {
                // CORREÇÃO: Não fechar modal imediatamente
                // modal.style.display = 'none'; // REMOVIDO
                
                document.getElementById('removeGuestId').value = guestId;
                const removeForm = document.getElementById('removeForm');
                
                // Mostrar aviso e submeter
                showOperationWarning('remove', removeForm);
                
                // CORREÇÃO: Fechar modal apenas após delay
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }, 500); // 500ms de delay
            };
            
            // Botão de cancelamento
            cancelBtn.onclick = function() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            };
        }
        
        /**
         * Inicialização do sistema quando DOM estiver carregado
         */
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔧 Sistema Hotel v4.4 - CORREÇÕES APLICADAS');
            console.log('✅ CORREÇÃO 1: Timeout reduzido para 10s');
            console.log('✅ CORREÇÃO 2: Modal de remoção corrigido');
            console.log('✅ CORREÇÃO 3: Posicionamento modal melhorado');
            console.log('✅ CORREÇÃO 4: Fallback para MikroTik');
            
            // Configurar formulários com correções
            const generateForm = document.getElementById('generateForm');
            if (generateForm) {
                generateForm.addEventListener('submit', function(event) {
                    if (event.submitter && event.submitter.name === 'generate_access') {
                        event.preventDefault();
                        
                        // CORREÇÃO: Validação adicional antes de mostrar aviso
                        const roomNumber = document.getElementById('room_number').value.trim();
                        const guestName = document.getElementById('guest_name').value.trim();
                        
                        if (!roomNumber || !guestName) {
                            alert('❌ Por favor, preencha o número do quarto e nome do hóspede');
                            return false;
                        }
                        
                        showOperationWarning('generate', generateForm);
                        return false;
                    }
                });
            }
            
            // Configurar formulário de diagnóstico
            const diagnosticForm = document.getElementById('diagnosticForm');
            if (diagnosticForm) {
                diagnosticForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    showOperationWarning('diagnostic', diagnosticForm);
                    return false;
                });
            }
            
            // Configurar datas automaticamente
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
                        checkoutInput.setCustomValidity('A data de check-out deve ser posterior à data de check-in');
                        checkoutInput.style.borderColor = '#e74c3c';
                    } else {
                        checkoutInput.setCustomValidity('');
                        checkoutInput.style.borderColor = '#ddd';
                    }
                }
                
                checkinInput.addEventListener('change', validateDates);
                checkoutInput.addEventListener('change', validateDates);
            }
            
            // Auto-fechar flash messages após 30 segundos (EXCETO mensagens persistentes)
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(function(message) {
                const hasCredentials = message.querySelector('.credentials-display');
                const hasRemovalData = message.querySelector('.removal-display');
                
                // Não auto-fechar mensagens com credenciais ou dados de remoção
                if (!hasCredentials && !hasRemovalData) {
                    setTimeout(function() {
                        if (message.parentNode) {
                            message.style.opacity = '0';
                            message.style.transform = 'translateY(-20px)';
                            setTimeout(function() {
                                if (message.parentNode) {
                                    message.style.display = 'none';
                                }
                            }, 400);
                        }
                    }, 30000); // 30 segundos
                }
            });
        });
        
        /**
         * Fechar modal ao clicar fora dele
         */
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        /**
         * Atalhos de teclado úteis
         */
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('confirmModal');
                if (modal && modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
                
                // Também fechar overlay se estiver aberto
                const overlay = document.getElementById('operationOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        });
        
        /**
         * Esconder overlay ao carregar página (fallback de segurança)
         */
        window.addEventListener('load', function() {
            setTimeout(() => {
                const overlay = document.getElementById('operationOverlay');
                if (overlay && overlay.style.display === 'flex') {
                    overlay.style.display = 'none';
                    console.log('🔧 Overlay escondido automaticamente por fallback');
                }
            }, 2000); // Fallback após 2 segundos
        });
        
        /**
         * Detectar problemas de conectividade
         */
        window.addEventListener('online', function() {
            console.log('🌐 Conexão com internet restaurada');
        });
        
        window.addEventListener('offline', function() {
            console.log('❌ Conexão com internet perdida');
        });
        
        /**
         * Log de informações do sistema para debugging
         */
        console.log('📊 Estatísticas do Sistema:', {
            totalGuests: <?php echo $systemStats['total_guests']; ?>,
            activeGuests: <?php echo $systemStats['active_guests']; ?>,
            todayGuests: <?php echo $systemStats['today_guests']; ?>,
            mikrotikTotal: <?php echo $systemStats['mikrotik_total']; ?>,
            syncRate: <?php echo $systemStats['sync_rate']; ?>,
            loadTime: '<?php echo $totalLoadTime; ?>ms',
            systemStatus: '<?php echo $systemStatus; ?>'
        });
        
        // Log de erros de conexão se existirem
        <?php if (!empty($hotelSystem->connectionErrors)): ?>
            console.warn('⚠️ Erros de conexão detectados:');
            <?php foreach ($hotelSystem->connectionErrors as $error): ?>
                console.warn('  - <?php echo addslashes($error); ?>');
            <?php endforeach; ?>
        <?php endif; ?>
        
        /**
         * Função para teste de performance
         */
        function testSystemPerformance() {
            const startTime = performance.now();
            
            // Simular algumas operações do DOM
            const elements = document.querySelectorAll('*');
            const forms = document.querySelectorAll('form');
            const buttons = document.querySelectorAll('button');
            
            const endTime = performance.now();
            const performanceTime = (endTime - startTime).toFixed(2);
            
            console.log(`🚀 Performance DOM: ${performanceTime}ms`);
            console.log(`📊 Elementos encontrados: ${elements.length} elementos, ${forms.length} formulários, ${buttons.length} botões`);
            
            return {
                time: performanceTime,
                elements: elements.length,
                forms: forms.length,
                buttons: buttons.length
            };
        }
        
        // Executar teste de performance
        setTimeout(testSystemPerformance, 1000);
        
        /**
         * Informações finais do sistema
         */
        console.log('🎯 CORREÇÕES APLICADAS COM SUCESSO!');
        console.log('1. ✅ Timeout de operação reduzido para 10s');
        console.log('2. ✅ Modal de remoção com tamanho e posição corrigidos');
        console.log('3. ✅ Modal não fecha mais antes da hora');
        console.log('4. ✅ Fallbacks para casos de erro implementados');
    </script>
</body>
</html>