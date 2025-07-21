<?php
/**
 * index.php - Sistema Hotel v4.3 - COM PRG (POST-Redirect-GET)
 * 
 * VERSÃO: 4.3 - POST-Redirect-GET Pattern
 * DATA: 2025-01-18
 * 
 * CORREÇÕES v4.3:
 * ✅ Implementação do padrão PRG (POST-Redirect-GET)
 * ✅ Prevenção de resubmissão de formulários
 * ✅ Mensagens via SESSION para persistir após redirect
 * ✅ Botão "Limpar Tela" funcional
 * ✅ URLs limpas após operações
 * ✅ Melhor UX com confirmações
 * ✅ Histórico de operações
 * ✅ Flash messages otimizadas
 */

// Configurações de erro e encoding otimizadas
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
session_start();

// Encoding UTF-8 otimizado
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

// Headers de performance
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Verificar se config.php existe
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

// Verificar se mikrotik_manager.php existe
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

// Logger simples se HotelLogger não existir
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
 * Classe de Flash Messages para PRG
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

/**
 * Classe do Sistema Hotel v4.3 - COM PRG
 */
class HotelSystemV43 {
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
        
        $this->logger->info("Hotel System v4.3 PRG iniciando...");
        
        // Conectar ao banco
        $this->connectToDatabase();
        
        // Conectar ao MikroTik
        $this->connectToMikroTik();
        
        // Log final
        $initTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->logger->info("Sistema Hotel v4.3 PRG inicializado em {$initTime}ms");
    }
    
    /**
     * Conexão robusta com banco de dados
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
     * Conexão robusta com MikroTik
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
        if (!isset($this->mikrotikConfig['host']) || empty($this->mikrotikConfig['host'])) {
            $this->logger->warning("Host do MikroTik não configurado");
            return false;
        }
        
        $host = $this->mikrotikConfig['host'];
        $port = $this->mikrotikConfig['port'] ?? 8728;
        
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
    
    /**
     * Criação de tabelas
     */
    protected function createTables() {
        if (!$this->db) {
            return false;
        }
        
        try {
            // Tabela principal
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
            
            // Verificar se colunas adicionais existem
            $this->addMissingColumns();
            
            // Tabela de logs
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
            
            // Tabela de histórico de operações (NOVA para PRG)
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
     * NOVA FUNCIONALIDADE: Remover acesso do hóspede
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
            
            $this->logger->info("Removendo acesso do hóspede: {$guestName} (Quarto: {$roomNumber}, Usuário: {$username})");
            
            $dbSuccess = false;
            $mikrotikSuccess = false;
            $mikrotikMessage = "MikroTik não conectado";
            
            // Passo 1: Remover do banco de dados
            try {
                $stmt = $this->db->prepare("UPDATE hotel_guests SET status = 'disabled', updated_at = NOW() WHERE id = ?");
                $dbSuccess = $stmt->execute([$guestId]);
                
                if ($dbSuccess) {
                    $this->logger->info("Hóspede removido do banco de dados com sucesso");
                    
                    // Log da ação
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
            
            // NOVO: Salvar operação no histórico
            $this->saveOperationHistory('remove', [
                'guest_id' => $guestId,
                'username' => $username,
                'room_number' => $roomNumber,
                'guest_name' => $guestName
            ], $result, $dbSuccess, $totalTime);
            
            $this->logger->info("Remoção de acesso concluída", $result);
            
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
     * Verifica se uma coluna existe
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
     * Log de ações
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
                $ipAddress,
                $userAgent,
                $responseTime,
                $errorMessage
            ]);
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao log de ação: " . $e->getMessage());
        }
    }
    
    /**
     * NOVO: Salvar operação no histórico (para PRG)
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
     * Gerar credenciais (método original mantido)
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
            
            // Inserir no banco
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
            
            // NOVO: Salvar operação no histórico
            $this->saveOperationHistory('create', [
                'room_number' => $roomNumber,
                'guest_name' => $guestName,
                'profile_type' => $profileType,
                'checkin_date' => $checkinDate,
                'checkout_date' => $checkoutDate
            ], $result, true, $totalTime);
            
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
    
    private function generateSimpleUsername($roomNumber) {
        return '' . preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber) . '-' . rand(10, 99);
    }
    
    private function generateSimplePassword() {
        return rand(100, 999);
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
    
    /**
     * Diagnóstico do sistema
     */
    public function getSystemDiagnostic() {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.3',
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
}

// INICIALIZAÇÃO DO SISTEMA v4.3
$systemInitStart = microtime(true);
$hotelSystem = null;
$initializationError = null;

try {
    $hotelSystem = new HotelSystemV43($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles);
} catch (Exception $e) {
    $initializationError = $e->getMessage();
    error_log("[HOTEL_SYSTEM_v4.3] ERRO DE INICIALIZAÇÃO: " . $e->getMessage());
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
        <title>Sistema Hotel - Diagnóstico v4.3</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
            .container { max-width: 1000px; margin: 0 auto; }
            .error-box { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #e74c3c; }
            .info-box { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #17a2b8; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏨 Sistema Hotel v4.3 - Diagnóstico</h1>
            
            <div class="error-box">
                <h3>❌ Erro na Inicialização do Sistema</h3>
                <p><strong>Erro:</strong> <?php echo htmlspecialchars($initializationError); ?></p>
                <p><strong>Tempo de inicialização:</strong> <?php echo $systemInitTime; ?>ms</p>
            </div>
            
            <div class="info-box">
                <h3>🔧 Soluções</h3>
                <ul>
                    <li>Verifique se o arquivo config.php está configurado corretamente</li>
                    <li>Verifique se o MySQL está rodando</li>
                    <li>Verifique se o MikroTik está acessível</li>
                    <li>Verifique as permissões dos arquivos</li>
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

// PROCESSAMENTO PRG v4.3
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
                FlashMessages::error("Todos os campos são obrigatórios");
            } else {
                $result = $hotelSystem->generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType);
                
                if ($result['success']) {
                    $responseTime = $result['response_time'] ?? 0;
                    $syncStatus = $result['sync_status'] ?? 'unknown';
                    FlashMessages::success("Credenciais geradas em {$responseTime}ms! Sync: " . strtoupper($syncStatus), $result);
                } else {
                    FlashMessages::error("Erro ao gerar credenciais: " . $result['error']);
                }
            }
            
        } elseif (isset($_POST['remove_access'])) {
            // Remoção de acesso
            $guestId = intval($_POST['guest_id'] ?? 0);
            
            if ($guestId <= 0) {
                FlashMessages::error("ID do hóspede inválido");
            } else {
                $removalResult = $hotelSystem->removeGuestAccess($guestId);
                
                if ($removalResult['success']) {
                    $responseTime = $removalResult['response_time'] ?? 0;
                    $dbStatus = $removalResult['database_success'] ? 'BD: ✅' : 'BD: ❌';
                    $mtStatus = $removalResult['mikrotik_success'] ? 'MT: ✅' : 'MT: ❌';
                    FlashMessages::success("Acesso removido em {$responseTime}ms! {$dbStatus} | {$mtStatus}", $removalResult);
                } else {
                    FlashMessages::error("Erro na remoção: " . $removalResult['error']);
                }
            }
            
        } elseif (isset($_POST['get_diagnostic'])) {
            // Diagnóstico do sistema
            $debugInfo = $hotelSystem->getSystemDiagnostic();
            FlashMessages::info("Diagnóstico executado", $debugInfo);
            
        } elseif (isset($_POST['clear_screen'])) {
            // Limpar tela - apenas redireciona
            FlashMessages::info("Tela limpa com sucesso");
        }
        
    } catch (Exception $e) {
        $actionTime = round((microtime(true) - $actionStart) * 1000, 2);
        FlashMessages::error("Erro crítico em {$actionTime}ms: " . $e->getMessage());
        error_log("[HOTEL_SYSTEM_v4.3] ERRO CRÍTICO: " . $e->getMessage());
    }
    
    // REDIRECT para implementar PRG
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
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

// Obter flash messages
$flashMessages = FlashMessages::get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($systemConfig['hotel_name']); ?> - Sistema v4.3 PRG</title>
    
    <style>
        /* CSS otimizado para v4.3 */
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
        
        /* Flash Messages Styles */
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
            content: "📌 FIXADO - Clique no X para fechar";
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
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        }
        
        .flash-close:hover {
            opacity: 1;
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
        .btn-danger { 
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); 
            font-size: 14px;
            padding: 10px 20px;
        }
        .btn-danger:hover { 
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }
        .btn-info { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
        .btn-clear { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 20px 0;
        }
        
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
        }
        
        .copy-btn:hover {
            background: #bdc3c7;
        }
        
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
            gap: 15px;
            justify-content: center;
        }
        
        .btn-confirm {
            background: #e74c3c;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        
        .prg-notice {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        
        .prg-notice h4 {
            margin-bottom: 10px;
        }
        
        .prg-notice p {
            margin: 5px 0;
            font-size: 0.9em;
        }
        
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="version-badge">v4.3 PRG</div>
            <h1>🏨 <?php echo htmlspecialchars($systemConfig['hotel_name']); ?></h1>
            <p>Sistema de Gerenciamento de Internet - PRG (POST-Redirect-GET)</p>
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
        
        <!-- PRG Notice -->
        <div class="prg-notice">
            <h4>🚀 Novo Sistema PRG (POST-Redirect-GET)</h4>
            <p>✅ Não resubmete formulários ao atualizar | ✅ URLs limpas | ✅ Mensagens via sessão</p>
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
            <!-- Flash Messages -->
            <?php if (!empty($flashMessages)): ?>
            <div class="flash-messages">
            <?php foreach ($flashMessages as $flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?> <?php echo (isset($flash['data']) && (isset($flash['data']['username']) || isset($flash['data']['guest_name']))) ? 'persistent' : ''; ?>">
                <button class="flash-close" onclick="this.parentElement.style.display='none'">&times;</button>
                <?php echo htmlspecialchars($flash['message']); ?>
                
                <?php if (isset($flash['data']) && is_array($flash['data'])): ?>
                    <?php 
                    // CORREÇÃO: Verificar se é uma operação de CRIAÇÃO (tem username E password)
                    $isCredentialCreation = ($flash['type'] === 'success' && 
                                           isset($flash['data']['username']) && 
                                           isset($flash['data']['password']) &&
                                           !empty($flash['data']['password']));
                    
                    // CORREÇÃO: Verificar se é uma operação de REMOÇÃO (tem guest_name mas NÃO tem password)
                    $isUserRemoval = ($flash['type'] === 'success' && 
                                    isset($flash['data']['guest_name']) && 
                                    !isset($flash['data']['password']));
                    ?>
                    
                    <?php if ($isCredentialCreation): ?>
                        <!-- Exibir credenciais geradas -->
                        <div class="credentials-display">
                            <h3>🎉 Credenciais Geradas!</h3>
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
                                <p>⏱️ Tempo: <?php echo $flash['data']['response_time'] ?? 'N/A'; ?>ms</p>
                                <p>📡 MikroTik: <?php echo htmlspecialchars($flash['data']['mikrotik_message'] ?? 'N/A'); ?></p>
                                <p>🔄 Sincronização: <?php echo strtoupper($flash['data']['sync_status'] ?? 'unknown'); ?></p>
                                <p>🏷️ Perfil: <?php echo htmlspecialchars($flash['data']['profile'] ?? 'N/A'); ?></p>
                                <p>📅 Válido até: <?php echo isset($flash['data']['valid_until']) ? date('d/m/Y', strtotime($flash['data']['valid_until'])) : 'N/A'; ?></p>
                            </div>
                            <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 10px;">
                                <p style="font-size: 0.9em; margin: 0;">
                                    <strong>💡 Dica:</strong> Clique nos campos acima para copiar automaticamente. 
                                    Esta mensagem fica visível até você fechá-la manualmente.
                                </p>
                            </div>
                        </div>
                        
                    <?php elseif ($isUserRemoval): ?>
                        <!-- Exibir resultado da remoção -->
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
                        <!-- Dados de debug/diagnóstico -->
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
                    
                    <div class="button-group">
                        <button type="submit" name="generate_access" class="btn">
                            ✨ Gerar Credenciais
                        </button>
                        <button type="submit" name="clear_screen" class="btn btn-clear">
                            🧹 Limpar Tela
                        </button>
                    </div>
                </form>
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
            
            <!-- Lista de Hóspedes COM BOTÃO DE REMOÇÃO -->
            <?php if (!empty($activeGuests)): ?>
            <div class="section">
                <h2 class="section-title">👥 Hóspedes Ativos (<?php echo count($activeGuests); ?>)</h2>
                
                <div style="overflow-x: auto;">
                    <table class="guests-table">
                        <thead>
                            <tr>
                                <th>Quarto</th>
                                <th>Hóspede</th>
                                <th>Credenciais</th>
                                <th>Check-out</th>
                                <th>Status</th>
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
                                </td>
                                <td>
                                    <div style="margin-bottom: 5px;">
                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($guest['username']); ?>')" title="Clique para copiar">
                                            👤 <?php echo htmlspecialchars($guest['username']); ?>
                                        </button>
                                    </div>
                                    <div>
                                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($guest['password']); ?>')" title="Clique para copiar">
                                            🔒 <?php echo htmlspecialchars($guest['password']); ?>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($guest['checkout_date'])); ?>
                                </td>
                                <td>
                                    <span class="sync-badge sync-<?php echo $guest['sync_status'] ?? 'pending'; ?>">
                                        <?php 
                                        switch ($guest['sync_status'] ?? 'pending') {
                                            case 'synced': echo '🟢 Sync'; break;
                                            case 'failed': echo '🔴 Erro'; break;
                                            default: echo '🟡 Pend'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="guest-actions">
                                        <button class="remove-btn" onclick="confirmRemoval(<?php echo $guest['id']; ?>, '<?php echo htmlspecialchars($guest['guest_name']); ?>', '<?php echo htmlspecialchars($guest['room_number']); ?>')">
                                            🗑️ Remover
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
            <p>&copy; 2025 Sistema Hotel v4.3 - PRG (POST-Redirect-GET)</p>
            <p>Tempo de carregamento: <?php echo $totalLoadTime; ?>ms | Status: <?php echo $systemStatus; ?></p>
            <?php if (!empty($hotelSystem->connectionErrors)): ?>
                <p style="color: #e74c3c; margin-top: 10px;">
                    Erros: <?php echo implode(', ', $hotelSystem->connectionErrors); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Confirmação -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>🗑️ Confirmar Remoção</h3>
            <p id="confirmMessage"></p>
            <div class="modal-buttons">
                <button id="confirmBtn" class="btn-confirm">Sim, Remover</button>
                <button id="cancelBtn" class="btn-cancel">Cancelar</button>
            </div>
        </div>
    </div>
    
    <!-- Formulário oculto para remoção -->
    <form id="removeForm" method="POST" style="display: none;">
        <input type="hidden" name="remove_access" value="1">
        <input type="hidden" name="guest_id" id="removeGuestId">
    </form>
    
    <script>
        // JavaScript para funcionalidades v4.3 PRG
        
        // Função para copiar para clipboard com feedback melhorado
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                console.log('Copiado: ' + text);
                
                // Mostrar mensagem temporária mais visível
                const message = document.createElement('div');
                message.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="font-size: 1.2em;">✅</div>
                        <div>
                            <strong>Copiado com sucesso!</strong><br>
                            <span style="font-family: monospace; font-size: 0.9em;">${text}</span>
                        </div>
                    </div>
                `;
                message.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #27ae60, #2ecc71);
                    color: white;
                    padding: 15px 20px;
                    border-radius: 10px;
                    z-index: 2000;
                    font-weight: bold;
                    animation: slideIn 0.3s ease-out;
                    box-shadow: 0 5px 20px rgba(39, 174, 96, 0.4);
                    border-left: 5px solid #ffffff;
                    max-width: 300px;
                `;
                document.body.appendChild(message);
                
                // Animar saída
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateX(100px)';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 3000);
                
                // Destacar o campo copiado
                const allCredentialBoxes = document.querySelectorAll('.credential-box');
                allCredentialBoxes.forEach(box => {
                    if (box.textContent.includes(text)) {
                        box.style.background = 'rgba(255,255,255,0.4)';
                        box.style.transform = 'scale(1.05)';
                        setTimeout(() => {
                            box.style.background = 'rgba(255,255,255,0.15)';
                            box.style.transform = 'scale(1)';
                        }, 1000);
                    }
                });
                
            }).catch(function(err) {
                console.error('Erro ao copiar: ', err);
                
                // Fallback para navegadores antigos
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                alert('📋 Copiado: ' + text);
            });
        }
        
        // Confirmar remoção
        function confirmRemoval(guestId, guestName, roomNumber) {
            const modal = document.getElementById('confirmModal');
            const message = document.getElementById('confirmMessage');
            const confirmBtn = document.getElementById('confirmBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            
            message.innerHTML = `
                <strong>Você tem certeza que deseja remover o acesso?</strong><br><br>
                <strong>Hóspede:</strong> ${guestName}<br>
                <strong>Quarto:</strong> ${roomNumber}<br><br>
                <em>Esta ação irá remover o acesso tanto do banco de dados quanto do MikroTik.</em>
            `;
            
            modal.style.display = 'block';
            
            confirmBtn.onclick = function() {
                document.getElementById('removeGuestId').value = guestId;
                document.getElementById('removeForm').submit();
            };
            
            cancelBtn.onclick = function() {
                modal.style.display = 'none';
            };
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
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
            
            // Auto-fechar flash messages após 30 segundos (EXCETO credenciais)
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(function(message) {
                // NÃO auto-fechar se contém credenciais ou dados de remoção
                const hasCredentials = message.querySelector('.credentials-display');
                const hasRemovalData = message.querySelector('.removal-display');
                
                if (!hasCredentials && !hasRemovalData) {
                    // Só auto-fechar mensagens simples (não credenciais)
                    setTimeout(function() {
                        message.style.opacity = '0';
                        setTimeout(function() {
                            message.style.display = 'none';
                        }, 300);
                    }, 30000); // 30 segundos para mensagens normais
                }
            });
            
            console.log('Sistema Hotel v4.3 PRG carregado em <?php echo $totalLoadTime; ?>ms');
            console.log('✅ PRG implementado: Formulários não resubmetem ao atualizar');
            console.log('✅ Flash messages: Mensagens via sessão');
            console.log('✅ URLs limpas: Sem parâmetros POST na URL');
            console.log('✅ Credenciais persistentes: Não expiram automaticamente');
            console.log('💡 Dica: Credenciais ficam visíveis até você fechá-las manualmente');
        });
        
        // Detectar problemas de conectividade
        window.addEventListener('online', function() {
            console.log('Conexão restaurada');
        });
        
        window.addEventListener('offline', function() {
            console.log('Conexão perdida');
        });
        
        // Função para destacar novos elementos (animação)
        function highlightNewContent() {
            const newElements = document.querySelectorAll('.credentials-display, .removal-display');
            newElements.forEach(function(element) {
                element.style.animation = 'pulse 2s ease-in-out';
            });
        }
        
        // Chamar destaque se houver novos elementos
        if (document.querySelector('.credentials-display, .removal-display')) {
            highlightNewContent();
        }
        
        // Função para limpar formulários
        function clearForms() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                if (form.id !== 'removeForm') {
                    form.reset();
                }
            });
        }
        
        // Atalhos de teclado
        document.addEventListener('keydown', function(event) {
            // Ctrl + L = Limpar tela
            if (event.ctrlKey && event.key === 'l') {
                event.preventDefault();
                const clearButton = document.querySelector('button[name="clear_screen"]');
                if (clearButton) {
                    clearButton.click();
                }
            }
            
            // Ctrl + G = Gerar credenciais
            if (event.ctrlKey && event.key === 'g') {
                event.preventDefault();
                const generateButton = document.querySelector('button[name="generate_access"]');
                if (generateButton) {
                    generateButton.focus();
                }
            }
            
            // Escape = Fechar modal
            if (event.key === 'Escape') {
                const modal = document.getElementById('confirmModal');
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                }
            }
        });
        
        // Função para testar conectividade
        function testConnectivity() {
            fetch(window.location.href, {method: 'HEAD'})
                .then(response => {
                    if (response.ok) {
                        console.log('✅ Conectividade OK');
                    } else {
                        console.warn('⚠️ Conectividade com problemas');
                    }
                })
                .catch(error => {
                    console.error('❌ Erro de conectividade:', error);
                });
        }
        
        // Testar conectividade a cada 5 minutos
        setInterval(testConnectivity, 300000);
        
        // Função para salvar dados do formulário no localStorage (backup)
        function saveFormData() {
            const formData = {
                room_number: document.getElementById('room_number')?.value || '',
                guest_name: document.getElementById('guest_name')?.value || '',
                checkin_date: document.getElementById('checkin_date')?.value || '',
                checkout_date: document.getElementById('checkout_date')?.value || '',
                profile_type: document.getElementById('profile_type')?.value || 'hotel-guest'
            };
            
            localStorage.setItem('hotel_form_backup', JSON.stringify(formData));
        }
        
        // Função para restaurar dados do formulário
        function restoreFormData() {
            const backup = localStorage.getItem('hotel_form_backup');
            if (backup) {
                try {
                    const formData = JSON.parse(backup);
                    
                    // Só restaurar se os campos estiverem vazios
                    if (document.getElementById('room_number')?.value === '') {
                        Object.keys(formData).forEach(key => {
                            const element = document.getElementById(key);
                            if (element && element.value === '') {
                                element.value = formData[key];
                            }
                        });
                    }
                } catch (e) {
                    console.warn('Erro ao restaurar dados do formulário:', e);
                }
            }
        }
        
        // Salvar dados do formulário a cada mudança
        document.addEventListener('input', function(event) {
            if (event.target.matches('#room_number, #guest_name, #checkin_date, #checkout_date, #profile_type')) {
                saveFormData();
            }
        });
        
        // Restaurar dados ao carregar
        restoreFormData();
        
        // Limpar backup após submissão bem-sucedida
        window.addEventListener('beforeunload', function() {
            // Verificar se há flash messages de sucesso
            const successMessages = document.querySelectorAll('.flash-success');
            if (successMessages.length > 0) {
                localStorage.removeItem('hotel_form_backup');
            }
        });
        
        // Adicionar CSS para animação de pulse
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
        
        // Log de informações do sistema
        console.log('🏨 Sistema Hotel v4.3 PRG');
        console.log('📊 Estatísticas:', {
            totalGuests: <?php echo $systemStats['total_guests']; ?>,
            activeGuests: <?php echo $systemStats['active_guests']; ?>,
            loadTime: '<?php echo $totalLoadTime; ?>ms',
            systemStatus: '<?php echo $systemStatus; ?>'
        });
        
        // Verificar se há erros de conexão
        <?php if (!empty($hotelSystem->connectionErrors)): ?>
            console.warn('⚠️ Erros de conexão detectados:', <?php echo json_encode($hotelSystem->connectionErrors); ?>);
        <?php endif; ?>
    </script>
</body>
</html>   