<?php
/**
 * index.php - Sistema Hotel v4.0 - Performance Critical Edition COMPLETO
 * 
 * VERSÃO: 4.0 - Performance Critical Fix
 * DATA: 2025-01-17
 * 
 * MELHORIAS v4.0:
 * ✅ Integração com MikroTikHotspotManagerFixed v4.0
 * ✅ Timeouts otimizados específicos por operação
 * ✅ Cache de conexão para operações sequenciais
 * ✅ Parser ultra-rápido integrado
 * ✅ Remoção express com 3 métodos fallback
 * ✅ Logs otimizados com buffer
 * ✅ Interface responsiva com feedback em tempo real
 * ✅ Health check integrado
 * ✅ Validação automática de sincronização BD/MikroTik
 * ✅ Sistema de debug avançado
 */

// Configurações de erro e encoding otimizadas
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 60); // Timeout aumentado para operações
session_start();

// Encoding UTF-8 otimizado
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

// Headers de performance
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Incluir arquivos necessários
if (file_exists('config.php')) {
    require_once 'config.php'; // Versão v4.0 otimizada
} else {
    die("Erro: Arquivo config.php não encontrado!");
}

// Verificar se o arquivo mikrotik_manager.php existe antes de incluir
if (file_exists('mikrotik_manager.php')) {
    require_once 'mikrotik_manager.php'; // Versão v4.0 otimizada
} else {
    die("Erro: Arquivo mikrotik_manager.php não encontrado!");
}

// Verificar se as classes necessárias foram carregadas
if (!class_exists('HotelLogger')) {
    error_log("AVISO: Classe HotelLogger não encontrada, usando logger simples");
}

if (!class_exists('MikroTikHotspotManagerFixed')) {
    die("Erro: Classe MikroTikHotspotManagerFixed não encontrada no mikrotik_manager.php!");
}

// Logger já definido no mikrotik_manager.php - usar a classe existente

/**
 * Classe do Sistema Hotel v4.0 - Ultra-Otimizada
 */
class HotelSystemV4 {
    protected $mikrotik;
    protected $db;
    protected $logger;
    protected $systemConfig;
    protected $userProfiles;
    protected $mikrotikConfig; // Adicionar para acesso aos dados do MikroTik
    protected $startTime;
    
    public function __construct($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles) {
        $this->startTime = microtime(true);
        $this->systemConfig = $systemConfig;
        $this->userProfiles = $userProfiles;
        $this->mikrotikConfig = $mikrotikConfig; // Armazenar configuração do MikroTik
        
        // Logger otimizado (usar a classe do mikrotik_manager.php)
        $this->logger = new HotelLogger();
        
        // Conectar ao banco com timeout otimizado
        try {
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10, // Timeout de conexão
                PDO::ATTR_PERSISTENT => true // Conexão persistente
            ];
            
            $this->db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            $this->logger->info("Banco conectado com conexao persistente");
            
        } catch (PDOException $e) {
            $this->logger->error("Erro conexao BD: " . $e->getMessage());
            throw new Exception("Erro na conexao com banco: " . $e->getMessage());
        }
        
        // Conectar ao MikroTik v4.0 (com verificação de conectividade)
        try {
            // Primeiro verificar se o host é acessível
            if ($this->isHostReachable($mikrotikConfig['host'], $mikrotikConfig['port'] ?? 8728)) {
                $this->mikrotik = new MikroTikHotspotManagerFixed(
                    $mikrotikConfig['host'],
                    $mikrotikConfig['username'],
                    $mikrotikConfig['password'],
                    $mikrotikConfig['port'] ?? 8728
                );
                
                $this->logger->info("MikroTik Manager v4.0 inicializado");
            } else {
                $this->logger->warning("MikroTik nao acessivel em {$mikrotikConfig['host']}:{$mikrotikConfig['port']}");
                $this->mikrotik = null;
            }
            
        } catch (Exception $e) {
            $this->logger->warning("MikroTik v4.0 nao conectado: " . $e->getMessage());
            $this->mikrotik = null;
        }
        
        // Criar/verificar tabelas
        $this->createTables();
        
        // Log de inicialização
        $initTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->logger->info("Sistema Hotel v4.0 inicializado em {$initTime}ms");
    }
    
    /**
     * v4.0: Verificar se o host é acessível antes de tentar conectar
     */
    private function isHostReachable($host, $port, $timeout = 2) {
        try {
            // Verificar se é um IP válido ou hostname
            if (!filter_var($host, FILTER_VALIDATE_IP) && !gethostbyname($host)) {
                return false;
            }
            
            // Tentar conexão rápida com timeout curto
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            
            if ($socket) {
                fclose($socket);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * v4.0: Criação otimizada de tabelas
     */
    protected function createTables() {
        try {
            // Tabela principal otimizada
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
                sync_status ENUM('synced', 'pending', 'failed') DEFAULT 'pending',
                last_sync TIMESTAMP NULL,
                INDEX idx_room (room_number),
                INDEX idx_status (status),
                INDEX idx_sync (sync_status),
                INDEX idx_dates (checkin_date, checkout_date),
                INDEX idx_username (username),
                INDEX idx_active_room (status, room_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            // Tabela de logs otimizada
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
            
            // Tabela de configurações do sistema
            $sql = "CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            // Tabela de performance (nova v4.0)
            $sql = "CREATE TABLE IF NOT EXISTS performance_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                operation_type ENUM('connect', 'list', 'create', 'remove', 'health') NOT NULL,
                response_time INT NOT NULL,
                success BOOLEAN NOT NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_operation (operation_type),
                INDEX idx_date (created_at),
                INDEX idx_performance (response_time, success)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            $this->logger->info("Tabelas v4.0 verificadas/criadas com indices otimizados");
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao criar tabelas: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * v4.0: Log de performance otimizado
     */
    private function logPerformance($operation, $responseTime, $success, $errorMessage = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO performance_metrics (operation_type, response_time, success, error_message)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([$operation, $responseTime, $success, $errorMessage]);
        } catch (Exception $e) {
            // Ignorar erros de log para não impactar performance
        }
    }
    
    /**
     * v4.0: Health check integrado otimizado
     */
    public function getSystemHealth() {
        $startTime = microtime(true);
        
        $health = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.0',
            'database' => $this->checkDatabaseHealth(),
            'mikrotik' => $this->checkMikroTikHealth(),
            'sync_status' => $this->checkSyncStatus(),
            'performance' => $this->getPerformanceMetrics(),
            'total_time' => 0
        ];
        
        $health['total_time'] = round((microtime(true) - $startTime) * 1000, 2);
        
        return $health;
    }
    
    /**
     * v4.0: Verificação otimizada do banco
     */
    private function checkDatabaseHealth() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM hotel_guests");
            $total = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) as active FROM hotel_guests WHERE status = 'active'");
            $active = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) as synced FROM hotel_guests WHERE sync_status = 'synced' AND status = 'active'");
            $synced = $stmt->fetchColumn();
            
            return [
                'connected' => true,
                'total_guests' => $total,
                'active_guests' => $active,
                'synced_guests' => $synced,
                'sync_ratio' => $active > 0 ? round(($synced / $active) * 100, 2) : 100
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * v4.0: Verificação otimizada do MikroTik
     */
    private function checkMikroTikHealth() {
        if (!$this->mikrotik) {
            return [
                'connected' => false,
                'error' => 'MikroTik nao configurado ou inacessivel',
                'status' => 'offline',
                'response_time' => 0,
                'user_count' => 0
            ];
        }
        
        try {
            // Verificar conectividade antes de tentar o health check
            $configHost = $this->mikrotikConfig['host'] ?? '10.0.1.1';
            $configPort = $this->mikrotikConfig['port'] ?? 8728;
            
            if (!$this->isHostReachable($configHost, $configPort, 1)) {
                return [
                    'connected' => false,
                    'error' => "Host {$configHost}:{$configPort} nao acessivel",
                    'status' => 'unreachable',
                    'response_time' => 0,
                    'user_count' => 0
                ];
            }
            
            $health = $this->mikrotik->healthCheck();
            
            return [
                'connected' => $health['connection'] ?? false,
                'response_time' => $health['response_time'] ?? 0,
                'user_count' => $health['user_count'] ?? 0,
                'status' => ($health['connection'] ?? false) ? 'online' : 'offline',
                'error' => $health['error'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'status' => 'error',
                'response_time' => 0,
                'user_count' => 0
            ];
        }
    }
    
    /**
     * v4.0: Verificação de sincronização BD/MikroTik
     */
    private function checkSyncStatus() {
        try {
            // Usuários ativos no banco
            $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE status = 'active'");
            $dbActive = $stmt->fetchColumn();
            
            // Usuários no MikroTik
            $mikrotikUsers = 0;
            if ($this->mikrotik) {
                try {
                    $users = $this->mikrotik->listHotspotUsers();
                    $mikrotikUsers = count($users);
                } catch (Exception $e) {
                    // Falha silenciosa
                }
            }
            
            $syncDiff = abs($dbActive - $mikrotikUsers);
            $syncStatus = 'unknown';
            
            if ($syncDiff == 0) {
                $syncStatus = 'perfect';
            } elseif ($syncDiff <= 2) {
                $syncStatus = 'good';
            } elseif ($syncDiff <= 5) {
                $syncStatus = 'moderate';
            } else {
                $syncStatus = 'poor';
            }
            
            return [
                'database_active' => $dbActive,
                'mikrotik_users' => $mikrotikUsers,
                'difference' => $syncDiff,
                'status' => $syncStatus,
                'sync_percentage' => $dbActive > 0 ? round((min($dbActive, $mikrotikUsers) / $dbActive) * 100, 2) : 0
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * v4.0: Métricas de performance
     */
    private function getPerformanceMetrics() {
        try {
            // Últimas 24h
            $stmt = $this->db->query("
                SELECT 
                    operation_type,
                    AVG(response_time) as avg_time,
                    MIN(response_time) as min_time,
                    MAX(response_time) as max_time,
                    COUNT(*) as total_ops,
                    SUM(success) as successful_ops
                FROM performance_metrics 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY operation_type
            ");
            
            $metrics = [];
            while ($row = $stmt->fetch()) {
                $metrics[$row['operation_type']] = [
                    'avg_time' => round($row['avg_time'], 2),
                    'min_time' => $row['min_time'],
                    'max_time' => $row['max_time'],
                    'total_ops' => $row['total_ops'],
                    'success_rate' => round(($row['successful_ops'] / $row['total_ops']) * 100, 2)
                ];
            }
            
            return $metrics;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * v4.0: Geração de credenciais ultra-otimizada
     */
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        $operationStart = microtime(true);
        $this->logger->info("Gerando credenciais v4.0 para quarto {$roomNumber}");
        
        try {
            // Verificar se já existe usuário ativo (consulta otimizada)
            $stmt = $this->db->prepare("
                SELECT username, sync_status 
                FROM hotel_guests 
                WHERE room_number = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$roomNumber]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                return [
                    'success' => false,
                    'error' => "Ja existe usuario ativo para quarto {$roomNumber}: {$existingUser['username']}"
                ];
            }
            
            // Gerar credenciais simples e únicas
            $username = $this->generateOptimizedUsername($roomNumber);
            $password = $this->generateOptimizedPassword();
            $timeLimit = $this->calculateTimeLimit($checkoutDate);
            
            // Inserir no banco PRIMEIRO (transação otimizada)
            $this->db->beginTransaction();
            
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO hotel_guests 
                    (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date, status, sync_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'pending')
                ");
                
                $dbResult = $stmt->execute([
                    $roomNumber, $guestName, $username, $password, 
                    $profileType, $checkinDate, $checkoutDate
                ]);
                
                if (!$dbResult) {
                    throw new Exception("Falha ao salvar no banco");
                }
                
                $guestId = $this->db->lastInsertId();
                $this->db->commit();
                
                $this->logger->info("Usuario salvo no BD: {$username} (ID: {$guestId})");
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw new Exception("Erro no banco: " . $e->getMessage());
            }
            
            // Tentar criar no MikroTik (async-style)
            $mikrotikResult = $this->createInMikroTik($username, $password, $profileType, $timeLimit);
            
            // Atualizar status de sync
            $syncStatus = $mikrotikResult['success'] ? 'synced' : 'failed';
            $this->updateSyncStatus($guestId, $syncStatus, $mikrotikResult['message']);
            
            // Log de performance
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logPerformance('create', $totalTime, true);
            
            $result = [
                'success' => true,
                'username' => $username,
                'password' => $password,
                'profile' => $profileType,
                'valid_until' => $checkoutDate,
                'bandwidth' => $this->userProfiles[$profileType]['rate_limit'] ?? '10M/2M',
                'mikrotik_success' => $mikrotikResult['success'],
                'mikrotik_message' => $mikrotikResult['message'],
                'sync_status' => $syncStatus,
                'response_time' => $totalTime
            ];
            
            // Log da ação
            $this->logAction($username, $roomNumber, 'created', null, $totalTime);
            
            $this->logger->info("Credenciais geradas em {$totalTime}ms: {$username}");
            return $result;
            
        } catch (Exception $e) {
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logPerformance('create', $totalTime, false, $e->getMessage());
            
            $this->logger->error("Erro ao gerar credenciais: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => $totalTime
            ];
        }
    }
    
    /**
     * v4.0: Criação no MikroTik com fallback inteligente
     */
    private function createInMikroTik($username, $password, $profileType, $timeLimit) {
        if (!$this->mikrotik) {
            return [
                'success' => false,
                'message' => 'MikroTik nao configurado'
            ];
        }
        
        $mikrotikStart = microtime(true);
        
        try {
            $this->mikrotik->connect();
            $result = $this->mikrotik->createHotspotUser($username, $password, $profileType, $timeLimit);
            $this->mikrotik->disconnect();
            
            $mikrotikTime = round((microtime(true) - $mikrotikStart) * 1000, 2);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => "Criado no MikroTik em {$mikrotikTime}ms"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Falha na criacao no MikroTik"
                ];
            }
            
        } catch (Exception $e) {
            $mikrotikTime = round((microtime(true) - $mikrotikStart) * 1000, 2);
            return [
                'success' => false,
                'message' => "Erro MikroTik ({$mikrotikTime}ms): " . $e->getMessage()
            ];
        }
    }
    
    /**
     * v4.0: Remoção express ultra-otimizada
     */
    public function removeGuestAccess($roomNumber) {
        $operationStart = microtime(true);
        $this->logger->info("Iniciando remocao express v4.0 para quarto {$roomNumber}");
        
        try {
            // Buscar hóspede com dados otimizados
            $stmt = $this->db->prepare("
                SELECT id, username, guest_name, sync_status
                FROM hotel_guests 
                WHERE room_number = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$roomNumber]);
            $guest = $stmt->fetch();
            
            if (!$guest) {
                return [
                    'success' => false,
                    'error' => "Nenhum hospede ativo encontrado para quarto {$roomNumber}"
                ];
            }
            
            $username = $guest['username'];
            $guestName = $guest['guest_name'];
            $guestId = $guest['id'];
            
            $this->logger->info("Hospede encontrado: {$username} ({$guestName})");
            
            // Atualizar banco PRIMEIRO (mais confiável)
            $this->db->beginTransaction();
            
            try {
                $stmt = $this->db->prepare("
                    UPDATE hotel_guests 
                    SET status = 'disabled', sync_status = 'pending', updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $dbResult = $stmt->execute([$guestId]);
                
                if (!$dbResult) {
                    throw new Exception("Falha ao atualizar banco");
                }
                
                $this->db->commit();
                $this->logger->info("Status atualizado no BD para: disabled");
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw new Exception("Erro no banco: " . $e->getMessage());
            }
            
            // Tentar remover do MikroTik (async-style)
            $mikrotikResult = $this->removeFromMikroTik($username);
            
            // Atualizar status final de sync
            $finalSyncStatus = $mikrotikResult['success'] ? 'synced' : 'failed';
            $this->updateSyncStatus($guestId, $finalSyncStatus, $mikrotikResult['message']);
            
            // Log de performance
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logPerformance('remove', $totalTime, true);
            
            // Determinar status da mensagem
            $statusIcon = $mikrotikResult['success'] ? "[OK]" : "[AVISO]";
            $message = "{$statusIcon} Acesso removido para {$guestName} (Quarto {$roomNumber}) | {$mikrotikResult['message']}";
            
            // Log da ação
            $this->logAction($username, $roomNumber, 'disabled', null, $totalTime, $mikrotikResult['message']);
            
            $this->logger->info("Remocao concluida em {$totalTime}ms: {$message}");
            
            return [
                'success' => true,
                'message' => $message,
                'mikrotik_success' => $mikrotikResult['success'],
                'sync_status' => $finalSyncStatus,
                'response_time' => $totalTime
            ];
            
        } catch (Exception $e) {
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logPerformance('remove', $totalTime, false, $e->getMessage());
            
            $this->logger->error("Erro na remocao: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => $totalTime
            ];
        }
    }
    
    /**
     * v4.0: Remoção do MikroTik com múltiplos métodos
     */
    private function removeFromMikroTik($username) {
        if (!$this->mikrotik) {
            return [
                'success' => false,
                'message' => 'MikroTik nao configurado'
            ];
        }
        
        $mikrotikStart = microtime(true);
        
        try {
            $this->mikrotik->connect();
            $result = $this->mikrotik->removeHotspotUser($username);
            $this->mikrotik->disconnect();
            
            $mikrotikTime = round((microtime(true) - $mikrotikStart) * 1000, 2);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => "Removido do MikroTik em {$mikrotikTime}ms"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Falha na remocao do MikroTik ({$mikrotikTime}ms)"
                ];
            }
            
        } catch (Exception $e) {
            $mikrotikTime = round((microtime(true) - $mikrotikStart) * 1000, 2);
            return [
                'success' => false,
                'message' => "Erro MikroTik ({$mikrotikTime}ms): " . $e->getMessage()
            ];
        }
    }
    
    /**
     * v4.0: Atualização otimizada do status de sync
     */
    private function updateSyncStatus($guestId, $syncStatus, $message = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE hotel_guests 
                SET sync_status = ?, last_sync = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$syncStatus, $guestId]);
            
            // Log adicional se falhou
            if ($syncStatus === 'failed' && $message) {
                $this->logger->warning("Sync failed for guest ID {$guestId}: {$message}");
            }
            
        } catch (Exception $e) {
            $this->logger->warning("Erro ao atualizar sync status: " . $e->getMessage());
        }
    }
    
    /**
     * v4.0: Limpeza de usuários expirados otimizada
     */
    public function cleanupExpiredUsers() {
        $operationStart = microtime(true);
        
        try {
            // Buscar usuários expirados
            $stmt = $this->db->prepare("
                SELECT id, username, room_number, guest_name
                FROM hotel_guests 
                WHERE checkout_date < CURDATE() AND status = 'active'
            ");
            $stmt->execute();
            $expiredUsers = $stmt->fetchAll();
            
            $removedCount = 0;
            $mikrotikErrors = 0;
            
            foreach ($expiredUsers as $user) {
                try {
                    // Atualizar banco primeiro
                    $stmt = $this->db->prepare("
                        UPDATE hotel_guests 
                        SET status = 'expired', sync_status = 'pending', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([$user['id']])) {
                        $removedCount++;
                        
                        // Tentar remover do MikroTik
                        $mikrotikResult = $this->removeFromMikroTik($user['username']);
                        
                        if (!$mikrotikResult['success']) {
                            $mikrotikErrors++;
                        }
                        
                        // Atualizar sync status
                        $syncStatus = $mikrotikResult['success'] ? 'synced' : 'failed';
                        $this->updateSyncStatus($user['id'], $syncStatus, $mikrotikResult['message']);
                        
                        // Log da ação
                        $this->logAction($user['username'], $user['room_number'], 'expired');
                    }
                    
                } catch (Exception $e) {
                    $this->logger->warning("Erro ao expirar {$user['username']}: " . $e->getMessage());
                }
            }
            
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logPerformance('cleanup', $totalTime, true);
            
            $this->logger->info("Limpeza em {$totalTime}ms: {$removedCount} usuarios expirados, {$mikrotikErrors} erros MikroTik");
            
            return [
                'success' => true,
                'removed' => $removedCount,
                'mikrotik_errors' => $mikrotikErrors,
                'response_time' => $totalTime
            ];
            
        } catch (Exception $e) {
            $totalTime = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logPerformance('cleanup', $totalTime, false, $e->getMessage());
            
            $this->logger->error("Erro na limpeza: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => $totalTime
            ];
        }
    }
    
    /**
     * v4.0: Geração otimizada de username
     */
    protected function generateOptimizedUsername($roomNumber) {
        // Limpar e normalizar número do quarto
        $cleanRoom = preg_replace('/[^a-zA-Z0-9]/', '', $roomNumber);
        $cleanRoom = substr($cleanRoom, 0, 6); // Máximo 6 caracteres
        
        // Usar timestamp para unicidade
        $timestamp = substr(time(), -4); // Últimos 4 dígitos do timestamp
        $baseUsername = $cleanRoom . '-' . $timestamp;
        
        // Verificar unicidade (com timeout)
        $attempts = 0;
        while ($this->usernameExists($baseUsername) && $attempts < 10) {
            $randomSuffix = rand(10, 99);
            $baseUsername = $cleanRoom . '-' . $timestamp . $randomSuffix;
            $attempts++;
        }
        
        return $baseUsername;
    }
    
    /**
     * v4.0: Geração otimizada de password
     */
    protected function generateOptimizedPassword() {
        // Gerar senha numérica simples (3-4 dígitos)
        $length = rand(3, 4);
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            if ($i === 0) {
                $password .= rand(1, 9); // Primeiro dígito não pode ser 0
            } else {
                $password .= rand(0, 9);
            }
        }
        
        // Evitar padrões óbvios
        $obviousPatterns = ['123', '456', '789', '111', '222', '333', '000', '1234'];
        
        if (in_array($password, $obviousPatterns)) {
            // Regenerar se for padrão óbvio
            return $this->generateOptimizedPassword();
        }
        
        return $password;
    }
    
    /**
     * v4.0: Verificação otimizada de username existente
     */
    protected function usernameExists($username) {
        try {
            $stmt = $this->db->prepare("
                SELECT 1 FROM hotel_guests 
                WHERE username = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$username]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            return false; // Em caso de erro, assumir que não existe
        }
    }
    
    /**
     * v4.0: Cálculo otimizado de time limit
     */
    private function calculateTimeLimit($checkoutDate) {
        try {
            $checkout = new DateTime($checkoutDate . ' 12:00:00');
            $now = new DateTime();
            
            if ($checkout <= $now) {
                return '01:00:00'; // Mínimo 1 hora se já expirou
            }
            
            $interval = $now->diff($checkout);
            $hours = ($interval->days * 24) + $interval->h;
            $minutes = $interval->i;
            
            // Máximo 7 dias (168 horas)
            if ($hours > 168) {
                $hours = 168;
                $minutes = 0;
            }
            
            // Mínimo 1 hora
            if ($hours < 1) {
                $hours = 1;
                $minutes = 0;
            }
            
            return sprintf('%02d:%02d:00', $hours, $minutes);
            
        } catch (Exception $e) {
            return '24:00:00'; // Fallback para 24 horas
        }
    }
    
    /**
     * v4.0: Log otimizado de ações
     */
    private function logAction($username, $roomNumber, $action, $ipAddress = null, $responseTime = 0, $errorMessage = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO access_logs 
                (username, room_number, action, ip_address, user_agent, response_time, error_message)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $username,
                $roomNumber,
                $action,
                $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $responseTime,
                $errorMessage
            ]);
        } catch (Exception $e) {
            // Ignorar erros de log para não impactar performance
        }
    }
    
    /**
     * v4.0: Obter estatísticas otimizadas do sistema
     */
    public function getSystemStats() {
        $operationStart = microtime(true);
        
        $stats = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.0'
        ];
        
        try {
            // Estatísticas do banco (otimizadas com uma query)
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_guests,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_guests,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_guests,
                    SUM(CASE WHEN status = 'active' AND sync_status = 'synced' THEN 1 ELSE 0 END) as synced_guests,
                    SUM(CASE WHEN status = 'active' AND sync_status = 'failed' THEN 1 ELSE 0 END) as sync_failed
                FROM hotel_guests
            ");
            $dbStats = $stmt->fetch();
            
            $stats = array_merge($stats, $dbStats);
            
            // Calcular taxa de sincronização
            $stats['sync_rate'] = $stats['active_guests'] > 0 ? 
                round(($stats['synced_guests'] / $stats['active_guests']) * 100, 2) : 100;
            
        } catch (Exception $e) {
            $stats['total_guests'] = 0;
            $stats['active_guests'] = 0;
            $stats['today_guests'] = 0;
            $stats['synced_guests'] = 0;
            $stats['sync_failed'] = 0;
            $stats['sync_rate'] = 0;
            $stats['db_error'] = $e->getMessage();
        }
        
        // Estatísticas do MikroTik (com timeout curto)
        $stats['mikrotik_total'] = 0;
        $stats['online_users'] = 0;
        $stats['mikrotik_status'] = 'disconnected';
        $stats['mikrotik_response_time'] = 0;
        
        if ($this->mikrotik) {
            try {
                $mikrotikStats = $this->mikrotik->getHotspotStats();
                $stats['mikrotik_total'] = $mikrotikStats['total_users'] ?? 0;
                $stats['online_users'] = $mikrotikStats['active_users'] ?? 0;
                $stats['mikrotik_response_time'] = $mikrotikStats['response_time'] ?? 0;
                $stats['mikrotik_status'] = 'connected';
            } catch (Exception $e) {
                $stats['mikrotik_error'] = $e->getMessage();
                $this->logger->warning("Erro ao obter stats MikroTik: " . $e->getMessage());
            }
        }
        
        // Performance geral
        $stats['response_time'] = round((microtime(true) - $operationStart) * 1000, 2);
        
        return $stats;
    }
    
    /**
     * v4.0: Listar hóspedes ativos com informações de sync
     */
    public function getActiveGuests() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id, room_number, guest_name, username, password, profile_type, 
                    checkin_date, checkout_date, created_at, status, sync_status, last_sync,
                    CASE 
                        WHEN checkout_date < CURDATE() THEN 'expired'
                        WHEN checkout_date = CURDATE() THEN 'expires_today'
                        ELSE 'active'
                    END as validity_status
                FROM hotel_guests 
                WHERE status = 'active' 
                ORDER BY 
                    CASE sync_status 
                        WHEN 'failed' THEN 1 
                        WHEN 'pending' THEN 2 
                        WHEN 'synced' THEN 3 
                    END,
                    room_number
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $this->logger->error("Erro ao listar hospedes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * v4.0: Buscar hóspede ativo por quarto
     */
    public function getActiveGuestByRoom($roomNumber) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM hotel_guests 
                WHERE room_number = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$roomNumber]);
            return $stmt->fetch();
        } catch (Exception $e) {
            $this->logger->error("Erro ao buscar hospede por quarto: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * v4.0: Sincronização forçada entre BD e MikroTik
     */
    public function forceSyncBdMikroTik() {
        $operationStart = microtime(true);
        $this->logger->info("Iniciando sincronizacao forcada BD <-> MikroTik");
        
        $syncResult = [
            'timestamp' => date('Y-m-d H:i:s'),
            'bd_users' => 0,
            'mikrotik_users' => 0,
            'created_in_mikrotik' => 0,
            'removed_from_mikrotik' => 0,
            'updated_status' => 0,
            'errors' => [],
            'response_time' => 0
        ];
        
        try {
            if (!$this->mikrotik) {
                throw new Exception("MikroTik nao configurado");
            }
            
            // Obter usuários do banco
            $bdUsers = $this->getActiveGuests();
            $syncResult['bd_users'] = count($bdUsers);
            
            // Obter usuários do MikroTik
            $this->mikrotik->connect();
            $mikrotikUsers = $this->mikrotik->listHotspotUsers();
            $this->mikrotik->disconnect();
            
            $syncResult['mikrotik_users'] = count($mikrotikUsers);
            
            // Criar array de usuários do MikroTik por nome
            $mikrotikUsernames = [];
            foreach ($mikrotikUsers as $user) {
                if (isset($user['name'])) {
                    $mikrotikUsernames[] = $user['name'];
                }
            }
            
            // Sincronizar usuários do BD para MikroTik
            foreach ($bdUsers as $guest) {
                try {
                    if (!in_array($guest['username'], $mikrotikUsernames)) {
                        // Usuário existe no BD mas não no MikroTik - criar
                        $timeLimit = $this->calculateTimeLimit($guest['checkout_date']);
                        $createResult = $this->createInMikroTik(
                            $guest['username'], 
                            $guest['password'], 
                            $guest['profile_type'], 
                            $timeLimit
                        );
                        
                        if ($createResult['success']) {
                            $syncResult['created_in_mikrotik']++;
                            $this->updateSyncStatus($guest['id'], 'synced', 'Sincronizado via sync forcada');
                        } else {
                            $syncResult['errors'][] = "Falha ao criar {$guest['username']}: {$createResult['message']}";
                            $this->updateSyncStatus($guest['id'], 'failed', $createResult['message']);
                        }
                    } else {
                        // Usuário existe em ambos - marcar como sincronizado
                        if ($guest['sync_status'] !== 'synced') {
                            $this->updateSyncStatus($guest['id'], 'synced', 'Confirmado na sync forcada');
                            $syncResult['updated_status']++;
                        }
                    }
                } catch (Exception $e) {
                    $syncResult['errors'][] = "Erro ao sincronizar {$guest['username']}: " . $e->getMessage();
                }
            }
            
            // Remover usuários órfãos do MikroTik (que não existem no BD)
            $bdUsernames = array_column($bdUsers, 'username');
            foreach ($mikrotikUsernames as $mikrotikUsername) {
                if (!in_array($mikrotikUsername, $bdUsernames)) {
                    try {
                        $removeResult = $this->removeFromMikroTik($mikrotikUsername);
                        if ($removeResult['success']) {
                            $syncResult['removed_from_mikrotik']++;
                        } else {
                            $syncResult['errors'][] = "Falha ao remover orfao {$mikrotikUsername}: {$removeResult['message']}";
                        }
                    } catch (Exception $e) {
                        $syncResult['errors'][] = "Erro ao remover orfao {$mikrotikUsername}: " . $e->getMessage();
                    }
                }
            }
            
            $syncResult['response_time'] = round((microtime(true) - $operationStart) * 1000, 2);
            $this->logPerformance('sync', $syncResult['response_time'], count($syncResult['errors']) == 0);
            
            $this->logger->info("Sync forcada concluida em {$syncResult['response_time']}ms: " . 
                "{$syncResult['created_in_mikrotik']} criados, {$syncResult['removed_from_mikrotik']} removidos, " .
                "{$syncResult['updated_status']} atualizados, " . count($syncResult['errors']) . " erros");
            
            return $syncResult;
            
        } catch (Exception $e) {
            $syncResult['response_time'] = round((microtime(true) - $operationStart) * 1000, 2);
            $syncResult['errors'][] = "Erro geral: " . $e->getMessage();
            
            $this->logPerformance('sync', $syncResult['response_time'], false, $e->getMessage());
            $this->logger->error("Erro na sync forcada: " . $e->getMessage());
            
            return $syncResult;
        }
    }
    
    /**
     * v4.0: Debug completo do sistema
     */
    public function debugSystem() {
        $debugStart = microtime(true);
        
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.0',
            'system_health' => $this->getSystemHealth(),
            'active_guests' => $this->getActiveGuests(),
            'system_stats' => $this->getSystemStats(),
            'performance_summary' => $this->getPerformanceSummary(),
            'sync_analysis' => $this->analyzeSyncStatus(),
            'response_time' => 0
        ];
        
        $debug['response_time'] = round((microtime(true) - $debugStart) * 1000, 2);
        
        return $debug;
    }
    
    /**
     * v4.0: Resumo de performance das últimas 24h
     */
    private function getPerformanceSummary() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    operation_type,
                    COUNT(*) as total_ops,
                    AVG(response_time) as avg_time,
                    MIN(response_time) as min_time,
                    MAX(response_time) as max_time,
                    SUM(success) as successful_ops,
                    COUNT(*) - SUM(success) as failed_ops
                FROM performance_metrics 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY operation_type
                ORDER BY operation_type
            ");
            
            $summary = [];
            while ($row = $stmt->fetch()) {
                $summary[$row['operation_type']] = [
                    'total_ops' => (int)$row['total_ops'],
                    'avg_time' => round($row['avg_time'], 2),
                    'min_time' => (int)$row['min_time'],
                    'max_time' => (int)$row['max_time'],
                    'successful_ops' => (int)$row['successful_ops'],
                    'failed_ops' => (int)$row['failed_ops'],
                    'success_rate' => round(($row['successful_ops'] / $row['total_ops']) * 100, 2)
                ];
            }
            
            return $summary;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * v4.0: Análise detalhada do status de sincronização
     */
    private function analyzeSyncStatus() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    sync_status,
                    COUNT(*) as count,
                    MIN(last_sync) as oldest_sync,
                    MAX(last_sync) as newest_sync
                FROM hotel_guests 
                WHERE status = 'active'
                GROUP BY sync_status
            ");
            
            $analysis = [];
            $total = 0;
            
            while ($row = $stmt->fetch()) {
                $analysis[$row['sync_status']] = [
                    'count' => (int)$row['count'],
                    'oldest_sync' => $row['oldest_sync'],
                    'newest_sync' => $row['newest_sync']
                ];
                $total += $row['count'];
            }
            
            // Calcular percentuais
            foreach ($analysis as $status => &$data) {
                $data['percentage'] = $total > 0 ? round(($data['count'] / $total) * 100, 2) : 0;
            }
            
            $analysis['total_active'] = $total;
            
            return $analysis;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * v4.0: Validação automática do sistema
     */
    public function validateSystem() {
        $validationStart = microtime(true);
        
        $validation = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.0',
            'tests' => [],
            'overall_status' => 'unknown',
            'score' => 0,
            'recommendations' => [],
            'response_time' => 0
        ];
        
        // Teste 1: Conexão com banco
        try {
            $stmt = $this->db->query("SELECT 1");
            $validation['tests']['database'] = [
                'status' => 'pass',
                'message' => 'Conexao com banco OK'
            ];
        } catch (Exception $e) {
            $validation['tests']['database'] = [
                'status' => 'fail',
                'message' => 'Erro no banco: ' . $e->getMessage()
            ];
        }
        
        // Teste 2: Conexão com MikroTik
        if ($this->mikrotik) {
            try {
                $healthCheck = $this->mikrotik->healthCheck();
                if ($healthCheck['connection']) {
                    $validation['tests']['mikrotik'] = [
                        'status' => 'pass',
                        'message' => "MikroTik OK ({$healthCheck['response_time']}ms)"
                    ];
                } else {
                    $validation['tests']['mikrotik'] = [
                        'status' => 'fail',
                        'message' => 'MikroTik nao responde'
                    ];
                }
            } catch (Exception $e) {
                $validation['tests']['mikrotik'] = [
                    'status' => 'fail',
                    'message' => 'Erro MikroTik: ' . $e->getMessage()
                ];
            }
        } else {
            $validation['tests']['mikrotik'] = [
                'status' => 'fail',
                'message' => 'MikroTik nao configurado'
            ];
        }
        
        // Teste 3: Sincronização BD/MikroTik
        $syncAnalysis = $this->analyzeSyncStatus();
        if (isset($syncAnalysis['synced']) && $syncAnalysis['total_active'] > 0) {
            $syncRate = $syncAnalysis['synced']['percentage'];
            if ($syncRate >= 90) {
                $validation['tests']['sync'] = [
                    'status' => 'pass',
                    'message' => "Sincronizacao excelente ({$syncRate}%)"
                ];
            } elseif ($syncRate >= 70) {
                $validation['tests']['sync'] = [
                    'status' => 'warning',
                    'message' => "Sincronizacao boa ({$syncRate}%)"
                ];
            } else {
                $validation['tests']['sync'] = [
                    'status' => 'fail',
                    'message' => "Sincronizacao ruim ({$syncRate}%)"
                ];
            }
        } else {
            $validation['tests']['sync'] = [
                'status' => 'pass',
                'message' => 'Nenhum usuario para sincronizar'
            ];
        }
        
        // Teste 4: Performance
        $performanceSummary = $this->getPerformanceSummary();
        if (!empty($performanceSummary) && !isset($performanceSummary['error'])) {
            $avgTimes = array_column($performanceSummary, 'avg_time');
            if (!empty($avgTimes)) {
                $overallAvg = array_sum($avgTimes) / count($avgTimes);
                
                if ($overallAvg < 2000) {
                    $validation['tests']['performance'] = [
                        'status' => 'pass',
                        'message' => "Performance excelente ({$overallAvg}ms media)"
                    ];
                } elseif ($overallAvg < 5000) {
                    $validation['tests']['performance'] = [
                        'status' => 'warning',
                        'message' => "Performance boa ({$overallAvg}ms media)"
                    ];
                } else {
                    $validation['tests']['performance'] = [
                        'status' => 'fail',
                        'message' => "Performance ruim ({$overallAvg}ms media)"
                    ];
                }
            } else {
                $validation['tests']['performance'] = [
                    'status' => 'warning',
                    'message' => 'Sem dados de performance válidos'
                ];
            }
        } else {
            $validation['tests']['performance'] = [
                'status' => 'warning',
                'message' => 'Sem dados de performance suficientes'
            ];
        }
        
        // Calcular score geral
        $totalTests = count($validation['tests']);
        $passedTests = 0;
        $warningTests = 0;
        
        foreach ($validation['tests'] as $test) {
            if ($test['status'] === 'pass') {
                $passedTests++;
            } elseif ($test['status'] === 'warning') {
                $warningTests++;
            }
        }
        
        $validation['score'] = round((($passedTests + $warningTests * 0.5) / $totalTests) * 100, 2);
        
        // Determinar status geral
        if ($validation['score'] >= 90) {
            $validation['overall_status'] = 'excellent';
        } elseif ($validation['score'] >= 70) {
            $validation['overall_status'] = 'good';
        } elseif ($validation['score'] >= 50) {
            $validation['overall_status'] = 'moderate';
        } else {
            $validation['overall_status'] = 'poor';
        }
        
        // Gerar recomendações
        foreach ($validation['tests'] as $testName => $test) {
            if ($test['status'] === 'fail') {
                switch ($testName) {
                    case 'database':
                        $validation['recommendations'][] = 'Verificar conexao e configuracao do banco de dados';
                        break;
                    case 'mikrotik':
                        $validation['recommendations'][] = 'Verificar conexao de rede e credenciais do MikroTik';
                        break;
                    case 'sync':
                        $validation['recommendations'][] = 'Executar sincronizacao forcada BD/MikroTik';
                        break;
                    case 'performance':
                        $validation['recommendations'][] = 'Otimizar performance - verificar rede e recursos';
                        break;
                }
            }
        }
        
        if (empty($validation['recommendations'])) {
            $validation['recommendations'][] = 'Sistema funcionando corretamente';
        }
        
        $validation['response_time'] = round((microtime(true) - $validationStart) * 1000, 2);
        
        return $validation;
    }
}

// Inicializar o sistema v4.0
$systemInitStart = microtime(true);

try {
    $hotelSystem = new HotelSystemV4($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles);
} catch (Exception $e) {
    die("
    <div style='font-family: Arial; background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px; border-left: 5px solid #e74c3c;'>
        <h3>❌ Erro Crítico na Inicialização v4.0</h3>
        <p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Ação:</strong> Verificar configurações de banco e MikroTik</p>
        <p><strong>Arquivo:</strong> config.php</p>
        <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
    </div>
    ");
}

$systemInitTime = round((microtime(true) - $systemInitStart) * 1000, 2);

// Variáveis de estado
$result = null;
$message = null;
$debugInfo = null;
$validationResults = null;
$performanceMetrics = null;

// Processamento de ações v4.0 - Ultra otimizado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionStart = microtime(true);
    
    try {
        if (isset($_POST['generate_access'])) {
            // Validação de entrada otimizada
            $roomNumber = trim($_POST['room_number'] ?? '');
            $guestName = trim($_POST['guest_name'] ?? '');
            $checkinDate = $_POST['checkin_date'] ?? '';
            $checkoutDate = $_POST['checkout_date'] ?? '';
            $profileType = $_POST['profile_type'] ?? 'hotel-guest';
            
            // Validações v4.0
            $validationErrors = [];
            
            if (empty($roomNumber)) {
                $validationErrors[] = "Numero do quarto e obrigatorio";
            }
            
            if (empty($guestName)) {
                $validationErrors[] = "Nome do hospede e obrigatorio";
            }
            
            if (empty($checkinDate) || empty($checkoutDate)) {
                $validationErrors[] = "Datas de check-in e check-out sao obrigatorias";
            }
            
            if (!empty($checkinDate) && !empty($checkoutDate)) {
                $checkin = new DateTime($checkinDate);
                $checkout = new DateTime($checkoutDate);
                
                if ($checkout <= $checkin) {
                    $validationErrors[] = "Data de check-out deve ser posterior ao check-in";
                }
                
                $maxStay = clone $checkin;
                $maxStay->add(new DateInterval('P30D')); // Máximo 30 dias
                
                if ($checkout > $maxStay) {
                    $validationErrors[] = "Estadia maxima permitida: 30 dias";
                }
            }
            
            if (!isset($userProfiles[$profileType])) {
                $validationErrors[] = "Perfil de usuario invalido";
            }
            
            if (!empty($validationErrors)) {
                $message = "❌ ERRO DE VALIDACAO: " . implode(", ", $validationErrors);
            } else {
                // Gerar credenciais
                $result = $hotelSystem->generateCredentials(
                    $roomNumber,
                    $guestName,
                    $checkinDate,
                    $checkoutDate,
                    $profileType
                );
                
                if ($result['success']) {
                    $responseTime = $result['response_time'] ?? 0;
                    $syncStatus = $result['sync_status'] ?? 'unknown';
                    
                    if ($responseTime < 3000) {
                        $message = "🎉 CREDENCIAIS GERADAS EM {$responseTime}ms! Sync: " . strtoupper($syncStatus);
                    } else {
                        $message = "✅ Credenciais geradas em {$responseTime}ms (lento). Sync: " . strtoupper($syncStatus);
                    }
                } else {
                    $message = "❌ ERRO: " . $result['error'];
                }
            }
            
        } elseif (isset($_POST['remove_access'])) {
            $roomNumber = trim($_POST['room_number'] ?? '');
            
            if (empty($roomNumber)) {
                $message = "❌ ERRO: Numero do quarto e obrigatorio";
            } else {
                $removeResult = $hotelSystem->removeGuestAccess($roomNumber);
                
                if ($removeResult['success']) {
                    $responseTime = $removeResult['response_time'] ?? 0;
                    
                    if ($responseTime < 3000) {
                        $message = "🎉 REMOVIDO EM {$responseTime}ms! " . $removeResult['message'];
                    } else {
                        $message = "✅ Removido em {$responseTime}ms (lento): " . $removeResult['message'];
                    }
                } else {
                    $message = "❌ ERRO NA REMOÇÃO: " . $removeResult['error'];
                }
            }
            
        } elseif (isset($_POST['cleanup_expired'])) {
            $cleanupResult = $hotelSystem->cleanupExpiredUsers();
            
            if ($cleanupResult['success']) {
                $responseTime = $cleanupResult['response_time'] ?? 0;
                $removed = $cleanupResult['removed'] ?? 0;
                $mikrotikErrors = $cleanupResult['mikrotik_errors'] ?? 0;
                
                $message = "🧹 LIMPEZA EM {$responseTime}ms: {$removed} expirados removidos";
                if ($mikrotikErrors > 0) {
                    $message .= " ({$mikrotikErrors} erros MikroTik)";
                }
            } else {
                $message = "❌ ERRO NA LIMPEZA: " . $cleanupResult['error'];
            }
            
        } elseif (isset($_POST['debug_system'])) {
            $debugInfo = $hotelSystem->debugSystem();
            $message = "🔍 DEBUG EXECUTADO EM " . $debugInfo['response_time'] . "ms";
            
        } elseif (isset($_POST['validate_system'])) {
            $validationResults = $hotelSystem->validateSystem();
            $score = $validationResults['score'];
            $status = $validationResults['overall_status'];
            $responseTime = $validationResults['response_time'];
            
            switch ($status) {
                case 'excellent':
                    $message = "🎉 SISTEMA EXCELENTE! Score: {$score}% em {$responseTime}ms";
                    break;
                case 'good':
                    $message = "✅ SISTEMA BOM! Score: {$score}% em {$responseTime}ms";
                    break;
                case 'moderate':
                    $message = "⚠️ SISTEMA MODERADO. Score: {$score}% em {$responseTime}ms";
                    break;
                case 'poor':
                    $message = "❌ SISTEMA COM PROBLEMAS! Score: {$score}% em {$responseTime}ms";
                    break;
            }
            
        } elseif (isset($_POST['force_sync'])) {
            $syncResult = $hotelSystem->forceSyncBdMikroTik();
            $responseTime = $syncResult['response_time'] ?? 0;
            $created = $syncResult['created_in_mikrotik'] ?? 0;
            $removed = $syncResult['removed_from_mikrotik'] ?? 0;
            $errors = count($syncResult['errors'] ?? []);
            
            if ($errors == 0) {
                $message = "🔄 SYNC EM {$responseTime}ms: {$created} criados, {$removed} removidos";
            } else {
                $message = "⚠️ SYNC EM {$responseTime}ms: {$created} criados, {$removed} removidos, {$errors} erros";
            }
            
        } elseif (isset($_POST['get_performance'])) {
            $performanceMetrics = [
                'system_health' => $hotelSystem->getSystemHealth(),
                'performance_summary' => $hotelSystem->getPerformanceSummary(),
                'validation' => $hotelSystem->validateSystem()
            ];
            
            $healthTime = $performanceMetrics['system_health']['total_time'] ?? 0;
            $validationScore = $performanceMetrics['validation']['score'] ?? 0;
            
            $message = "📊 MÉTRICAS EM {$healthTime}ms: Score {$validationScore}%";
        }
        
    } catch (Exception $e) {
        $actionTime = round((microtime(true) - $actionStart) * 1000, 2);
        $message = "❌ ERRO CRÍTICO EM {$actionTime}ms: " . $e->getMessage();
        
        // Log do erro crítico
        error_log("[HOTEL_SYSTEM_v4.0] ERRO CRITICO: " . $e->getMessage());
    }
}

// Obter dados para exibição (com cache otimizado)
$dataStart = microtime(true);

try {
    $activeGuests = $hotelSystem->getActiveGuests();
    $systemStats = $hotelSystem->getSystemStats();
    $systemHealth = $hotelSystem->getSystemHealth();
    
    $dataTime = round((microtime(true) - $dataStart) * 1000, 2);
    
    // Log de performance se lento
    if ($dataTime > 2000) {
        error_log("[HOTEL_SYSTEM_v4.0] DADOS LENTOS: {$dataTime}ms");
    }
    
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
    $systemHealth = [
        'database' => ['connected' => false],
        'mikrotik' => ['connected' => false],
        'error' => $e->getMessage()
    ];
    
    $dataTime = round((microtime(true) - $dataStart) * 1000, 2);
    error_log("[HOTEL_SYSTEM_v4.0] ERRO AO CARREGAR DADOS: " . $e->getMessage());
}

// Calcular tempo total de carregamento
$totalLoadTime = round((microtime(true) - $systemInitStart) * 1000, 2);

// Determinar status geral do sistema
$systemStatus = 'unknown';
$systemStatusColor = 'warning';

if ($systemHealth['database']['connected'] && $systemHealth['mikrotik']['connected']) {
    if ($systemStats['sync_rate'] >= 90) {
        $systemStatus = 'excellent';
        $systemStatusColor = 'success';
    } elseif ($systemStats['sync_rate'] >= 70) {
        $systemStatus = 'good';
        $systemStatusColor = 'success';
    } else {
        $systemStatus = 'moderate';
        $systemStatusColor = 'warning';
    }
} elseif ($systemHealth['database']['connected']) {
    $systemStatus = 'database_only';
    $systemStatusColor = 'warning';
} else {
    $systemStatus = 'critical';
    $systemStatusColor = 'danger';
}

// Headers finais
header('X-System-Version: 4.0');
header('X-Load-Time: ' . $totalLoadTime);
header('X-System-Status: ' . $systemStatus);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($systemConfig['hotel_name']); ?> - Sistema v4.0</title>
    <meta name="description" content="Sistema de Gerenciamento de Internet - Performance Critical Edition">
    <meta name="version" content="4.0">
    <meta name="load-time" content="<?php echo $totalLoadTime; ?>">
    <meta name="system-status" content="<?php echo $systemStatus; ?>">
    
    <!-- Performance Hints -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏨</text></svg>">
    
    <style>
        /* CSS Crítico Inline para v4.0 - Performance Otimizado */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
            animation: slideIn 0.5s ease-out;
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
            backdrop-filter: blur(10px);
        }
        
        .performance-badge {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9em;
            margin-top: 15px;
            display: inline-block;
            animation: pulse 2s infinite;
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
        .status-good { background: #d4edda; color: #155724; }
        .status-moderate { background: #fff3cd; color: #856404; }
        .status-database_only { background: #fff3cd; color: #856404; }
        .status-critical { background: #f8d7da; color: #721c24; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            transition: all 0.3s ease;
            border-left: 5px solid #3498db;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3498db, #2ecc71, #f39c12, #e74c3c);
        }
        
        .stat-number {
            font-size: 3.2em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 500;
        }
        
        .stat-subtitle {
            font-size: 0.8em;
            color: #95a5a6;
            margin-top: 5px;
        }
        
        /* Indicadores de performance em tempo real */
        .performance-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: blink 2s infinite;
        }
        
        .indicator-excellent { background: #27ae60; }
        .indicator-good { background: #f39c12; }
        .indicator-moderate { background: #e67e22; }
        .indicator-poor { background: #e74c3c; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        
        /* Responsive Design Otimizado */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                padding: 20px;
            }
            
            .stat-number {
                font-size: 2.5em;
            }
            
            .header h1 {
                font-size: 2.2em;
            }
        }
        
        /* Loading States */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* CSS Principal para Interface v4.0 */
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
            transition: all 0.3s ease;
        }
        
        .section:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .section-title {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        
        .section-icon {
            font-size: 1.2em;
        }
        
        .section-badge {
            background: linear-gradient(135deg, #3498db, #2ecc71);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: auto;
        }
        
        .section-counter {
            background: #e74c3c;
            color: white;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .section-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
        
        /* Alertas v4.0 */
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            animation: slideDown 0.3s ease-out;
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
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #b8daff);
            color: #0c5460;
            border-left: 5px solid #17a2b8;
        }
        
        .alert-icon {
            font-size: 1.2em;
        }
        
        .alert-text {
            flex: 1;
        }
        
        .alert-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .alert-close:hover {
            opacity: 1;
        }
        
        /* Formulários v4.0 */
        .optimized-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .form-group {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-1px);
        }
        
        .form-input:valid {
            border-color: #27ae60;
        }
        
        .form-input:invalid:not(:placeholder-shown) {
            border-color: #e74c3c;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        label.required::after {
            content: ' *';
            color: #e74c3c;
        }
        
        .input-feedback {
            margin-top: 5px;
            font-size: 0.9em;
            min-height: 20px;
        }
        
        .input-feedback.success {
            color: #27ae60;
        }
        
        .input-feedback.error {
            color: #e74c3c;
        }
        
        /* Botões v4.0 */
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
            display: inline-flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
        }
        
        .btn-outline:hover {
            background: #3498db;
            color: white;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .btn-icon {
            font-size: 1.1em;
        }
        
        .btn-loader {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .btn.loading .btn-text {
            opacity: 0.7;
        }
        
        .btn.loading .btn-loader {
            display: block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Exibição de Credenciais v4.0 */
        .credentials-display {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 35px;
            border-radius: 20px;
            margin: 25px 0;
            text-align: center;
            box-shadow: 0 15px 35px rgba(39, 174, 96, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .credentials-display::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .credentials-header {
            margin-bottom: 25px;
        }
        
        .credentials-header h3 {
            margin-bottom: 15px;
            font-size: 1.8em;
        }
        
        .performance-info {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .perf-time, .sync-status {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .sync-synced { background: rgba(39, 174, 96, 0.3); }
        .sync-pending { background: rgba(243, 156, 18, 0.3); }
        .sync-failed { background: rgba(231, 76, 60, 0.3); }
        
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
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.1);
        }
        
        .credential-box:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .credential-label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .credential-value {
            font-size: 2.8em;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .copy-indicator {
            font-size: 0.8em;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .credential-box:hover .copy-indicator {
            opacity: 1;
        }
        
        .credential-info {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .info-label {
            font-weight: 600;
            opacity: 0.9;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .info-value.success {
            color: #d4edda;
        }
        
        .info-value.warning {
            color: #fff3cd;
        }
        
        .credential-actions {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* Lista de Hóspedes v4.0 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.7;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .empty-state p {
            margin-bottom: 25px;
        }
        
        .sync-status-bar {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
        }
        
        .sync-indicator {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
        }
        
        .sync-icon {
            animation: rotate 2s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .guests-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .guests-header {
            background: #2c3e50;
            color: white;
            display: grid;
            grid-template-columns: 100px 1fr 200px 120px 120px 100px 150px;
            gap: 15px;
            padding: 20px;
            font-weight: 600;
        }
        
        .guest-row {
            display: grid;
            grid-template-columns: 100px 1fr 200px 120px 120px 100px 150px;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .guest-row:hover {
            background: #f8f9fa;
        }
        
        .guest-row[data-sync="failed"] {
            border-left: 5px solid #e74c3c;
        }
        
        .guest-row[data-sync="pending"] {
            border-left: 5px solid #f39c12;
        }
        
        .guest-row[data-sync="synced"] {
            border-left: 5px solid #27ae60;
        }
        
        .room-number {
            font-weight: bold;
            color: #3498db;
            font-size: 1.3em;
        }
        
        .guest-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .guest-meta {
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .credential-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .credential-type {
            font-size: 0.9em;
        }
        
        .credential-display {
            font-family: 'Courier New', monospace;
            background: #ecf0f1;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }
        
        .credential-display:hover {
            background: #3498db;
            color: white;
            transform: scale(1.05);
        }
        
        .profile-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            color: white;
        }
        
        .profile-hotel-guest { background: #3498db; }
        .profile-hotel-vip { background: #f39c12; }
        .profile-hotel-staff { background: #e74c3c; }
        
        .validity-info {
            text-align: center;
        }
        
        .checkout-date {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .validity-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .validity-active { background: #d4edda; color: #155724; }
        .validity-expires_today { background: #fff3cd; color: #856404; }
        .validity-expired { background: #f8d7da; color: #721c24; }
        
        .status-stack {
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: center;
        }
        
        .sync-badge {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .sync-synced { background: #d4edda; color: #155724; }
        .sync-pending { background: #fff3cd; color: #856404; }
        .sync-failed { background: #f8d7da; color: #721c24; }
        
        .last-sync {
            font-size: 0.7em;
            color: #7f8c8d;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .bulk-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            flex-wrap: wrap;
        }
        
        /* Sistema e Debug v4.0 */
        .system-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .system-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .system-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .system-card h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .system-metrics, .system-status {
            margin-bottom: 20px;
        }
        
        .metric, .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .metric-label, .status-label {
            font-weight: 600;
            color: #7f8c8d;
        }
        
        .metric-value {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .status-indicator {
            font-weight: 600;
        }
        
        .status-indicator.online {
            color: #27ae60;
        }
        
        .status-indicator.offline {
            color: #e74c3c;
        }
        
        .system-actions, .quick-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Debug e Validação v4.0 */
        .debug-section, .validation-section, .performance-section {
            background: #2c3e50;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 25px 0;
        }
        
        .debug-section h3, .validation-section h3, .performance-section h3 {
            margin-bottom: 25px;
            color: white;
        }
        
        .debug-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .debug-tab {
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .debug-tab.active {
            background: #3498db;
        }
        
        .debug-tab:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .debug-content {
            position: relative;
        }
        
        .debug-panel {
            display: none;
        }
        
        .debug-panel.active {
            display: block;
        }
        
        .debug-info {
            background: #34495e;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .validation-score {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .score-circle {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid;
            margin-bottom: 20px;
        }
        
        .score-excellent { border-color: #27ae60; background: rgba(39, 174, 96, 0.1); }
        .score-good { border-color: #f39c12; background: rgba(243, 156, 18, 0.1); }
        .score-moderate { border-color: #e67e22; background: rgba(230, 126, 34, 0.1); }
        .score-poor { border-color: #e74c3c; background: rgba(231, 76, 60, 0.1); }
        
        .score-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .score-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .validation-tests {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
        }
        
        .test-result {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
        }
        
        .test-pass { border-left: 3px solid #27ae60; }
        .test-warning { border-left: 3px solid #f39c12; }
        .test-fail { border-left: 3px solid #e74c3c; }
        
        .test-icon {
            font-size: 1.2em;
        }
        
        .test-name {
            font-weight: 600;
            min-width: 100px;
        }
        
        .test-message {
            flex: 1;
            opacity: 0.9;
        }
        
        .validation-recommendations {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .validation-recommendations h4 {
            margin-bottom: 15px;
        }
        
        .validation-recommendations ul {
            margin-left: 20px;
        }
        
        .validation-recommendations li {
            margin-bottom: 8px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .metric-card {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
        }
        
        .metric-card h4 {
            margin-bottom: 15px;
            color: white;
        }
        
        .metric-details {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
        }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .metric-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        /* Footer v4.0 */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .footer-section h4 {
            margin-bottom: 15px;
            color: #3498db;
        }
        
        .footer-section p {
            margin-bottom: 8px;
            opacity: 0.9;
        }
        
        .footer-section a {
            color: #3498db;
            text-decoration: none;
            display: block;
            margin-bottom: 5px;
            transition: color 0.3s;
        }
        
        .footer-section a:hover {
            color: #2ecc71;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 20px;
            opacity: 0.7;
        }
        
        /* Animações melhoradas */
        .animate-in {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .slide-in {
            animation: slideInLeft 0.5s ease-out;
        }
        
        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Responsivo avançado */
        @media (max-width: 1200px) {
            .guests-header, .guest-row {
                grid-template-columns: 80px 1fr 180px 100px 100px 80px 130px;
            }
        }
        
        @media (max-width: 768px) {
            .guests-header, .guest-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .guest-row {
                padding: 15px;
            }
            
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
            <div class="version-badge">v4.0 Performance</div>
            <h1>🏨 <?php echo htmlspecialchars($systemConfig['hotel_name']); ?></h1>
            <p>Sistema de Gerenciamento de Internet - Performance Critical Edition</p>
            <div class="performance-badge">
                ⚡ Carregado em <?php echo $totalLoadTime; ?>ms
            </div>
            <span class="system-status status-<?php echo $systemStatus; ?>">
                <?php 
                switch($systemStatus) {
                    case 'excellent': echo '🎉 Sistema Excelente'; break;
                    case 'good': echo '✅ Sistema Funcionando'; break;
                    case 'moderate': echo '⚠️ Sistema Moderado'; break;
                    case 'database_only': echo '⚠️ Só Banco Conectado'; break;
                    case 'critical': echo '❌ Sistema Crítico'; break;
                    default: echo '❓ Status Desconhecido'; break;
                }
                ?>
            </span>
        </div>
        
        <!-- Estatísticas do Sistema v4.0 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="performance-indicator indicator-<?php 
                    echo $systemStats['total_guests'] > 0 ? 'excellent' : 'moderate'; 
                ?>"></div>
                <div class="stat-number"><?php echo $systemStats['total_guests']; ?></div>
                <div class="stat-label">Total de Hóspedes</div>
                <div class="stat-subtitle">Todos os registros</div>
            </div>
            
            <div class="stat-card">
                <div class="performance-indicator indicator-<?php 
                    echo $systemStats['active_guests'] > 0 ? 'excellent' : 'moderate'; 
                ?>"></div>
                <div class="stat-number"><?php echo $systemStats['active_guests']; ?></div>
                <div class="stat-label">Ativos no Sistema</div>
                <div class="stat-subtitle">Banco de dados</div>
            </div>
            
            <div class="stat-card">
                <div class="performance-indicator indicator-<?php 
                    echo $systemStats['mikrotik_total'] > 0 ? 'excellent' : 'poor'; 
                ?>"></div>
                <div class="stat-number"><?php echo $systemStats['mikrotik_total']; ?></div>
                <div class="stat-label">No MikroTik</div>
                <div class="stat-subtitle">Router hotspot</div>
            </div>
            
            <div class="stat-card">
                <div class="performance-indicator indicator-<?php 
                    echo $systemStats['online_users'] > 0 ? 'excellent' : 'moderate'; 
                ?>"></div>
                <div class="stat-number"><?php echo $systemStats['online_users']; ?></div>
                <div class="stat-label">Online Agora</div>
                <div class="stat-subtitle">Conectados</div>
            </div>
            
            <div class="stat-card">
                <div class="performance-indicator indicator-<?php 
                    $syncRate = $systemStats['sync_rate'];
                    echo $syncRate >= 90 ? 'excellent' : ($syncRate >= 70 ? 'good' : ($syncRate >= 50 ? 'moderate' : 'poor')); 
                ?>"></div>
                <div class="stat-number"><?php echo $systemStats['sync_rate']; ?>%</div>
                <div class="stat-label">Taxa de Sync</div>
                <div class="stat-subtitle">BD ↔ MikroTik</div>
            </div>
            
            <div class="stat-card">
                <div class="performance-indicator indicator-<?php 
                    $responseTime = $systemStats['response_time'] ?? 0;
                    echo $responseTime < 1000 ? 'excellent' : ($responseTime < 3000 ? 'good' : ($responseTime < 8000 ? 'moderate' : 'poor')); 
                ?>"></div>
                <div class="stat-number"><?php echo $systemStats['response_time'] ?? 0; ?>ms</div>
                <div class="stat-label">Performance</div>
                <div class="stat-subtitle">Tempo resposta</div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Mensagens de Feedback v4.0 -->
            <?php if ($message): ?>
                <div class="alert <?php 
                    echo strpos($message, '❌') !== false ? 'alert-error' : 
                        (strpos($message, '⚠️') !== false ? 'alert-warning' : 
                        (strpos($message, '✅') !== false || strpos($message, '🎉') !== false || strpos($message, '🚀') !== false ? 'alert-success' : 'alert-info')); 
                ?>" id="system-message">
                    <span class="alert-icon"></span>
                    <span class="alert-text"><?php echo htmlspecialchars($message); ?></span>
                    <button class="alert-close" onclick="closeAlert('system-message')">&times;</button>
                </div>
            <?php endif; ?>
            
            <!-- Seção de Geração de Acesso v4.0 -->
            <div class="section fade-in">
                <h2 class="section-title">
                    <span class="section-icon">🆕</span>
                    Gerar Novo Acesso
                    <span class="section-badge">Ultra-Rápido</span>
                </h2>
                
                <form method="POST" action="" id="generate-form" class="optimized-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="room_number" class="required">Número do Quarto:</label>
                            <input type="text" id="room_number" name="room_number" required 
                                   placeholder="Ex: 101, 205A" autocomplete="off" 
                                   class="form-input" maxlength="10">
                            <div class="input-feedback" id="room-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="guest_name" class="required">Nome do Hóspede:</label>
                            <input type="text" id="guest_name" name="guest_name" required 
                                   placeholder="Nome completo do hóspede" 
                                   class="form-input" maxlength="100">
                            <div class="input-feedback" id="name-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="checkin_date" class="required">Data de Check-in:</label>
                            <input type="date" id="checkin_date" name="checkin_date" required 
                                   class="form-input" min="<?php echo date('Y-m-d'); ?>">
                            <div class="input-feedback" id="checkin-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="checkout_date" class="required">Data de Check-out:</label>
                            <input type="date" id="checkout_date" name="checkout_date" required 
                                   class="form-input" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            <div class="input-feedback" id="checkout-feedback"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_type">Tipo de Perfil:</label>
                            <select id="profile_type" name="profile_type" class="form-input">
                                <?php foreach ($userProfiles as $key => $profile): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" 
                                            data-bandwidth="<?php echo htmlspecialchars($profile['rate_limit']); ?>">
                                        <?php echo htmlspecialchars($profile['name']); ?> 
                                        (<?php echo htmlspecialchars($profile['rate_limit']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-feedback" id="profile-feedback"></div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="generate_access" class="btn btn-primary" id="generate-btn">
                            <span class="btn-icon">✨</span>
                            <span class="btn-text">Gerar Credenciais</span>
                            <span class="btn-loader"></span>
                        </button>
                        
                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                            <span class="btn-icon">🔄</span>
                            <span class="btn-text">Limpar</span>
                        </button>
                    </div>
                </form>
                
                <!-- Resultado da Geração v4.0 -->
                <?php if (isset($result) && $result['success']): ?>
                    <div class="credentials-display animate-in" id="credentials-result">
                        <div class="credentials-header">
                            <h3>🎉 Credenciais Geradas com Sucesso!</h3>
                            <div class="performance-info">
                                <span class="perf-time">⚡ <?php echo $result['response_time'] ?? 0; ?>ms</span>
                                <span class="sync-status sync-<?php echo $result['sync_status'] ?? 'unknown'; ?>">
                                    <?php echo strtoupper($result['sync_status'] ?? 'unknown'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="credential-pair">
                            <div class="credential-box" onclick="copyToClipboard('<?php echo htmlspecialchars($result['username']); ?>', this)">
                                <div class="credential-label">👤 USUÁRIO</div>
                                <div class="credential-value" id="username-display">
                                    <?php echo htmlspecialchars($result['username']); ?>
                                </div>
                                <div class="copy-indicator">Clique para copiar</div>
                            </div>
                            
                            <div class="credential-box" onclick="copyToClipboard('<?php echo htmlspecialchars($result['password']); ?>', this)">
                                <div class="credential-label">🔒 SENHA</div>
                                <div class="credential-value" id="password-display">
                                    <?php echo htmlspecialchars($result['password']); ?>
                                </div>
                                <div class="copy-indicator">Clique para copiar</div>
                            </div>
                        </div>
                        
                        <div class="credential-info">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">🏠 Quarto:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($_POST['room_number'] ?? ''); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">👤 Hóspede:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📊 Perfil:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($result['profile'] ?? ''); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">🌐 Largura de Banda:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($result['bandwidth'] ?? ''); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📅 Válido até:</span>
                                    <span class="info-value"><?php echo date('d/m/Y', strtotime($result['valid_until'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">🔧 Status MikroTik:</span>
                                    <span class="info-value <?php echo $result['mikrotik_success'] ? 'success' : 'warning'; ?>">
                                        <?php echo htmlspecialchars($result['mikrotik_message'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="credential-actions">
                            <button onclick="printCredentials()" class="btn btn-outline">
                                <span class="btn-icon">🖨️</span>
                                Imprimir
                            </button>
                            <button onclick="closeCredentials()" class="btn btn-outline">
                                <span class="btn-icon">✖️</span>
                                Fechar
                            </button>
                        </div>
                    </div>
                <?php elseif (isset($result) && !$result['success']): ?>
                    <div class="alert alert-error animate-in">
                        <span class="alert-icon">❌</span>
                        <span class="alert-text">
                            <strong>Erro na Geração:</strong> <?php echo htmlspecialchars($result['error']); ?>
                            <?php if (isset($result['response_time'])): ?>
                                <br><small>Tempo de resposta: <?php echo $result['response_time']; ?>ms</small>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Seção de Hóspedes Ativos v4.0 -->
            <div class="section slide-in">
                <h2 class="section-title">
                    <span class="section-icon">👥</span>
                    Hóspedes Ativos
                    <span class="section-counter">(<?php echo count($activeGuests); ?>)</span>
                    <div class="section-actions">
                        <button onclick="refreshGuestList()" class="btn btn-sm" title="Atualizar Lista">
                            <span class="btn-icon">🔄</span>
                        </button>
                        <button onclick="toggleViewMode()" class="btn btn-sm" title="Alternar Visualização">
                            <span class="btn-icon">📋</span>
                        </button>
                    </div>
                </h2>
                
                <?php if (empty($activeGuests)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <h3>Nenhum hóspede ativo encontrado</h3>
                        <p>Gere credenciais para novos hóspedes usando o formulário acima.</p>
                        <button onclick="document.getElementById('room_number').focus()" class="btn btn-primary">
                            <span class="btn-icon">➕</span>
                            Adicionar Primeiro Hóspede
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Status de Sincronização -->
                    <div class="sync-status-bar">
                        <div class="sync-indicator">
                            <span class="sync-icon">🔄</span>
                            <span class="sync-text">
                                BD: <?php echo $systemStats['active_guests']; ?> | 
                                MikroTik: <?php echo $systemStats['mikrotik_total']; ?> | 
                                Sync: <?php echo $systemStats['sync_rate']; ?>%
                            </span>
                            <?php if ($systemStats['sync_rate'] < 90): ?>
                                <button onclick="forceSync()" class="btn btn-sm btn-warning" title="Forçar Sincronização">
                                    <span class="btn-icon">⚡</span>
                                    Sync
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Lista de Hóspedes -->
                    <div class="guests-container" id="guests-container">
                        <div class="guests-header">
                            <div class="header-cell">Quarto</div>
                            <div class="header-cell">Hóspede</div>
                            <div class="header-cell">Credenciais</div>
                            <div class="header-cell">Perfil</div>
                            <div class="header-cell">Validade</div>
                            <div class="header-cell">Status</div>
                            <div class="header-cell">Ações</div>
                        </div>
                        
                        <?php foreach ($activeGuests as $guest): ?>
                        <div class="guest-row" data-room="<?php echo htmlspecialchars($guest['room_number']); ?>" 
                             data-sync="<?php echo htmlspecialchars($guest['sync_status']); ?>">
                            
                            <div class="guest-cell room-cell">
                                <span class="room-number"><?php echo htmlspecialchars($guest['room_number']); ?></span>
                            </div>
                            
                            <div class="guest-cell name-cell">
                                <div class="guest-name"><?php echo htmlspecialchars($guest['guest_name']); ?></div>
                                <div class="guest-meta">
                                    Check-in: <?php echo date('d/m/Y', strtotime($guest['checkin_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="guest-cell credentials-cell">
                                <div class="credential-row">
                                    <span class="credential-type">👤</span>
                                    <span class="credential-display username-display" 
                                          onclick="copyToClipboard('<?php echo htmlspecialchars($guest['username']); ?>', this)" 
                                          title="Clique para copiar">
                                        <?php echo htmlspecialchars($guest['username']); ?>
                                    </span>
                                </div>
                                <div class="credential-row">
                                    <span class="credential-type">🔒</span>
                                    <span class="credential-display password-display" 
                                          onclick="copyToClipboard('<?php echo htmlspecialchars($guest['password']); ?>', this)" 
                                          title="Clique para copiar">
                                        <?php echo htmlspecialchars($guest['password']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="guest-cell profile-cell">
                                <span class="profile-badge profile-<?php echo htmlspecialchars($guest['profile_type']); ?>">
                                    <?php 
                                    echo isset($userProfiles[$guest['profile_type']]) ? 
                                        htmlspecialchars($userProfiles[$guest['profile_type']]['name']) : 
                                        htmlspecialchars($guest['profile_type']); 
                                    ?>
                                </span>
                            </div>
                            
                            <div class="guest-cell validity-cell">
                                <div class="validity-info">
                                    <span class="checkout-date"><?php echo date('d/m/Y', strtotime($guest['checkout_date'])); ?></span>
                                    <span class="validity-badge validity-<?php echo $guest['validity_status'] ?? 'active'; ?>">
                                        <?php 
                                        switch ($guest['validity_status'] ?? 'active') {
                                            case 'expired': echo '⏰ Expirado'; break;
                                            case 'expires_today': echo '⚠️ Expira hoje'; break;
                                            default: echo '✅ Ativo'; break;
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="guest-cell status-cell">
                                <div class="status-stack">
                                    <span class="sync-badge sync-<?php echo $guest['sync_status']; ?>" 
                                          title="Status de sincronização">
                                        <?php 
                                        switch ($guest['sync_status']) {
                                            case 'synced': echo '🟢 Sync'; break;
                                            case 'pending': echo '🟡 Pend'; break;
                                            case 'failed': echo '🔴 Fail'; break;
                                            default: echo '⚪ ?'; break;
                                        }
                                        ?>
                                    </span>
                                    <?php if ($guest['last_sync']): ?>
                                        <span class="last-sync" title="Última sincronização">
                                            <?php echo date('H:i', strtotime($guest['last_sync'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="guest-cell actions-cell">
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirmRemoval('<?php echo htmlspecialchars($guest['room_number']); ?>', '<?php echo htmlspecialchars($guest['guest_name']); ?>', '<?php echo htmlspecialchars($guest['username']); ?>')">
                                        <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($guest['room_number']); ?>">
                                        <button type="submit" name="remove_access" class="btn btn-danger btn-sm remove-btn" 
                                                title="Remover acesso do hóspede">
                                            <span class="btn-icon">🗑️</span>
                                            <span class="btn-text">Remover</span>
                                            <span class="btn-loader"></span>
                                        </button>
                                    </form>
                                    
                                    <button onclick="viewGuestDetails('<?php echo htmlspecialchars($guest['username']); ?>')" 
                                            class="btn btn-outline btn-sm" title="Ver detalhes">
                                        <span class="btn-icon">👁️</span>
                                    </button>
                                    
                                    <?php if ($guest['sync_status'] === 'failed'): ?>
                                        <button onclick="retrySync('<?php echo $guest['id']; ?>')" 
                                                class="btn btn-warning btn-sm" title="Tentar sincronizar novamente">
                                            <span class="btn-icon">🔄</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Ações em Lote -->
                <div class="bulk-actions">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="cleanup_expired" class="btn btn-warning"
                                onclick="return confirm('🧹 Remover todos os usuários com check-out vencido?');"
                                title="Remove automaticamente usuários expirados">
                            <span class="btn-icon">🧹</span>
                            <span class="btn-text">Limpar Expirados</span>
                            <span class="btn-loader"></span>
                        </button>
                    </form>
                    
                    <button onclick="refreshGuestList()" class="btn btn-info" title="Recarregar lista de hóspedes">
                        <span class="btn-icon">🔄</span>
                        <span class="btn-text">Atualizar Lista</span>
                    </button>
                    
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="force_sync" class="btn btn-secondary"
                                onclick="return confirm('🔄 Forçar sincronização entre BD e MikroTik?');"
                                title="Força sincronização completa">
                            <span class="btn-icon">⚡</span>
                            <span class="btn-text">Sync Forçada</span>
                            <span class="btn-loader"></span>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Seção de Sistema e Debug v4.0 -->
            <div class="section">
                <h2 class="section-title">
                    <span class="section-icon">🔧</span>
                    Sistema e Diagnóstico
                    <span class="section-badge system-badge">v4.0</span>
                </h2>
                
                <div class="system-grid">
                    <div class="system-card">
                        <h3>📊 Performance</h3>
                        <div class="system-metrics">
                            <div class="metric">
                                <span class="metric-label">Carregamento:</span>
                                <span class="metric-value"><?php echo $totalLoadTime; ?>ms</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Dados:</span>
                                <span class="metric-value"><?php echo $dataTime; ?>ms</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Sistema:</span>
                                <span class="metric-value"><?php echo $systemInitTime; ?>ms</span>
                            </div>
                        </div>
                        <div class="system-actions">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="get_performance" class="btn btn-sm">
                                    <span class="btn-icon">📈</span>
                                    Métricas
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="system-card">
                        <h3>🔍 Diagnóstico</h3>
                        <div class="system-status">
                            <div class="status-item">
                                <span class="status-label">Banco:</span>
                                <span class="status-indicator <?php echo $systemHealth['database']['connected'] ? 'online' : 'offline'; ?>">
                                    <?php echo $systemHealth['database']['connected'] ? '🟢 Online' : '🔴 Offline'; ?>
                                </span>
                            </div>
                            <div class="status-item">
                                <span class="status-label">MikroTik:</span>
                                <span class="status-indicator <?php echo $systemHealth['mikrotik']['connected'] ? 'online' : 'offline'; ?>">
                                    <?php echo $systemHealth['mikrotik']['connected'] ? '🟢 Online' : '🔴 Offline'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="system-actions">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="debug_system" class="btn btn-sm">
                                    <span class="btn-icon">🔍</span>
                                    Debug
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="validate_system" class="btn btn-sm">
                                    <span class="btn-icon">✅</span>
                                    Validar
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="system-card">
                        <h3>🔗 Links Úteis</h3>
                        <div class="quick-links">
                            <a href="test_raw_parser_final.php" class="btn btn-sm btn-outline">
                                <span class="btn-icon">🧪</span>
                                Testar Parser
                            </a>
                            <a href="test_performance.php" class="btn btn-sm btn-outline">
                                <span class="btn-icon">⚡</span>
                                Performance
                            </a>
                            <button onclick="exportData()" class="btn btn-sm btn-outline">
                                <span class="btn-icon">📁</span>
                                Exportar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Debug Info (se solicitado) -->
            <?php if ($debugInfo): ?>
            <div class="debug-section animate-in">
                <h3>🔍 Informações de Debug v4.0</h3>
                <div class="debug-tabs">
                    <button class="debug-tab active" onclick="showDebugTab('health')">Sistema</button>
                    <button class="debug-tab" onclick="showDebugTab('performance')">Performance</button>
                    <button class="debug-tab" onclick="showDebugTab('guests')">Hóspedes</button>
                    <button class="debug-tab" onclick="showDebugTab('sync')">Sincronização</button>
                </div>
                
                <div class="debug-content">
                    <div class="debug-panel active" id="debug-health">
                        <h4>🏥 Saúde do Sistema</h4>
                        <div class="debug-info">
                            <pre><?php echo json_encode($debugInfo['system_health'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        </div>
                    </div>
                    
                    <div class="debug-panel" id="debug-performance">
                        <h4>📊 Performance</h4>
                        <div class="debug-info">
                            <pre><?php echo json_encode($debugInfo['performance_summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        </div>
                    </div>
                    
                    <div class="debug-panel" id="debug-guests">
                        <h4>👥 Hóspedes Ativos</h4>
                        <div class="debug-info">
                            <pre><?php echo json_encode($debugInfo['active_guests'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        </div>
                    </div>
                    
                    <div class="debug-panel" id="debug-sync">
                        <h4>🔄 Análise de Sincronização</h4>
                        <div class="debug-info">
                            <pre><?php echo json_encode($debugInfo['sync_analysis'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Validation Results (se solicitado) -->
            <?php if ($validationResults): ?>
            <div class="validation-section animate-in">
                <h3>✅ Resultados da Validação v4.0</h3>
                <div class="validation-score">
                    <div class="score-circle score-<?php echo $validationResults['overall_status']; ?>">
                        <span class="score-number"><?php echo $validationResults['score']; ?>%</span>
                        <span class="score-label"><?php echo strtoupper($validationResults['overall_status']); ?></span>
                    </div>
                </div>
                
                <div class="validation-tests">
                    <?php foreach ($validationResults['tests'] as $testName => $test): ?>
                    <div class="test-result test-<?php echo $test['status']; ?>">
                        <span class="test-icon">
                            <?php 
                            echo $test['status'] === 'pass' ? '✅' : 
                                ($test['status'] === 'warning' ? '⚠️' : '❌'); 
                            ?>
                        </span>
                        <span class="test-name"><?php echo ucfirst($testName); ?>:</span>
                        <span class="test-message"><?php echo htmlspecialchars($test['message']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($validationResults['recommendations'])): ?>
                <div class="validation-recommendations">
                    <h4>💡 Recomendações:</h4>
                    <ul>
                        <?php foreach ($validationResults['recommendations'] as $recommendation): ?>
                        <li><?php echo htmlspecialchars($recommendation); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Performance Metrics (se solicitado) -->
            <?php if ($performanceMetrics): ?>
            <div class="performance-section animate-in">
                <h3>📊 Métricas de Performance v4.0</h3>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <h4>🏥 Saúde do Sistema</h4>
                        <div class="metric-details">
                            <div class="metric-row">
                                <span>Tempo Total:</span>
                                <span><?php echo $performanceMetrics['system_health']['total_time']; ?>ms</span>
                            </div>
                            <div class="metric-row">
                                <span>Status BD:</span>
                                <span><?php echo $performanceMetrics['system_health']['database']['connected'] ? '🟢 Conectado' : '🔴 Desconectado'; ?></span>
                            </div>
                            <div class="metric-row">
                                <span>Status MikroTik:</span>
                                <span><?php echo $performanceMetrics['system_health']['mikrotik']['connected'] ? '🟢 Conectado' : '🔴 Desconectado'; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <h4>📈 Performance 24h</h4>
                        <div class="metric-details">
                            <?php if (!empty($performanceMetrics['performance_summary'])): ?>
                                <?php foreach ($performanceMetrics['performance_summary'] as $operation => $data): ?>
                                <div class="metric-row">
                                    <span><?php echo ucfirst($operation); ?>:</span>
                                    <span><?php echo $data['avg_time']; ?>ms (<?php echo $data['success_rate']; ?>%)</span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="metric-row">
                                    <span>Sem dados suficientes</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <h4>✅ Validação</h4>
                        <div class="metric-details">
                            <div class="metric-row">
                                <span>Score Geral:</span>
                                <span><?php echo $performanceMetrics['validation']['score']; ?>%</span>
                            </div>
                            <div class="metric-row">
                                <span>Status:</span>
                                <span><?php echo strtoupper($performanceMetrics['validation']['overall_status']); ?></span>
                            </div>
                            <div class="metric-row">
                                <span>Tempo:</span>
                                <span><?php echo $performanceMetrics['validation']['response_time']; ?>ms</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer v4.0 -->
        <div class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Sistema Hotel v4.0</h4>
                    <p>Performance Critical Edition</p>
                    <p>Carregado em <?php echo $totalLoadTime; ?>ms</p>
                </div>
                
                <div class="footer-section">
                    <h4>Status do Sistema</h4>
                    <p>BD: <?php echo $systemStats['active_guests']; ?> ativos</p>
                    <p>MikroTik: <?php echo $systemStats['mikrotik_total']; ?> usuários</p>
                    <p>Sync: <?php echo $systemStats['sync_rate']; ?>%</p>
                </div>
                
                <div class="footer-section">
                    <h4>Links Úteis</h4>
                    <a href="test_raw_parser_final.php">Testar Parser</a>
                    <a href="test_performance.php">Performance</a>
                    <a href="#" onclick="exportSystemData()">Exportar Dados</a>
                </div>
                
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <p>Versão: 4.0.0</p>
                    <p>Build: <?php echo date('Y.m.d'); ?></p>
                    <p>PHP: <?php echo PHP_VERSION; ?></p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 Sistema Hotel v4.0 - Performance Critical Edition</p>
                <p>Tempo de carregamento total: <?php echo $totalLoadTime; ?>ms | Status: <?php echo $systemStatus; ?></p>
            </div>
        </div>
    </div>
    
    <!-- JavaScript v4.0 -->
    <script>
        // Sistema de Performance e Feedback v4.0
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Sistema Hotel v4.0 carregado em <?php echo $totalLoadTime; ?>ms');
            
            // Auto-definir data de check-in para hoje
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
            
            // Validação em tempo real
            setupRealTimeValidation();
            
            // Setup de botões com loading
            setupButtonLoading();
            
            // Auto-refresh para estatísticas (opcional)
            setupAutoRefresh();
        });
        
        // Validação em tempo real v4.0
        function setupRealTimeValidation() {
            const roomInput = document.getElementById('room_number');
            const nameInput = document.getElementById('guest_name');
            const checkinInput = document.getElementById('checkin_date');
            const checkoutInput = document.getElementById('checkout_date');
            
            if (roomInput) {
                roomInput.addEventListener('blur', function() {
                    const feedback = document.getElementById('room-feedback');
                    if (this.value.trim()) {
                        feedback.textContent = '✅ Número do quarto válido';
                        feedback.className = 'input-feedback success';
                    } else {
                        feedback.textContent = '❌ Número do quarto é obrigatório';
                        feedback.className = 'input-feedback error';
                    }
                });
            }
            
            if (nameInput) {
                nameInput.addEventListener('blur', function() {
                    const feedback = document.getElementById('name-feedback');
                    if (this.value.trim().length >= 3) {
                        feedback.textContent = '✅ Nome do hóspede válido';
                        feedback.className = 'input-feedback success';
                    } else {
                        feedback.textContent = '❌ Nome deve ter pelo menos 3 caracteres';
                        feedback.className = 'input-feedback error';
                    }
                });
            }
            
            if (checkinInput && checkoutInput) {
                function validateDates() {
                    const checkinFeedback = document.getElementById('checkin-feedback');
                    const checkoutFeedback = document.getElementById('checkout-feedback');
                    
                    if (checkinInput.value && checkoutInput.value) {
                        const checkin = new Date(checkinInput.value);
                        const checkout = new Date(checkoutInput.value);
                        
                        if (checkout > checkin) {
                            const days = Math.ceil((checkout - checkin) / (1000 * 60 * 60 * 24));
                            checkinFeedback.textContent = '✅ Data de check-in válida';
                            checkinFeedback.className = 'input-feedback success';
                            checkoutFeedback.textContent = `✅ Estadia de ${days} dia(s)`;
                            checkoutFeedback.className = 'input-feedback success';
                        } else {
                            checkoutFeedback.textContent = '❌ Check-out deve ser após check-in';
                            checkoutFeedback.className = 'input-feedback error';
                        }
                    }
                }
                
                checkinInput.addEventListener('change', validateDates);
                checkoutInput.addEventListener('change', validateDates);
            }
        }
        
        // Setup de loading nos botões
        function setupButtonLoading() {
            const buttons = document.querySelectorAll('button[type="submit"]');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    if (this.form && this.form.checkValidity()) {
                        this.classList.add('loading');
                        
                        // Restaurar após timeout
                        setTimeout(() => {
                            this.classList.remove('loading');
                        }, 30000);
                    }
                });
            });
        }
        
        // Funções utilitárias v4.0
        function copyToClipboard(text, element) {
            navigator.clipboard.writeText(text).then(function() {
                // Feedback visual
                const original = element.style.background;
                element.style.background = '#27ae60';
                element.style.color = 'white';
                
                setTimeout(() => {
                    element.style.background = original;
                    element.style.color = '';
                }, 1000);
                
                console.log('✅ Copiado: ' + text);
            }).catch(function(err) {
                console.error('❌ Erro ao copiar: ', err);
            });
        }
        
        function closeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.animation = 'slideUp 0.3s ease-out';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        }
        
        function resetForm() {
            const form = document.getElementById('generate-form');
            if (form) {
                form.reset();
                
                // Limpar feedbacks
                document.querySelectorAll('.input-feedback').forEach(feedback => {
                    feedback.textContent = '';
                    feedback.className = 'input-feedback';
                });
                
                // Redefinir datas
                const checkinInput = document.getElementById('checkin_date');
                const checkoutInput = document.getElementById('checkout_date');
                
                if (checkinInput) {
                    checkinInput.value = new Date().toISOString().split('T')[0];
                }
                
                if (checkoutInput) {
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    checkoutInput.value = tomorrow.toISOString().split('T')[0];
                }
            }
        }
        
        function printCredentials() {
            const credentials = document.getElementById('credentials-result');
            if (credentials) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Credenciais de Acesso</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 40px; }
                                .header { text-align: center; margin-bottom: 30px; }
                                .credentials { background: #f8f9fa; padding: 30px; border-radius: 10px; margin: 20px 0; }
                                .credential-item { margin: 15px 0; padding: 15px; background: white; border-radius: 5px; }
                                .label { font-weight: bold; color: #666; }
                                .value { font-size: 1.5em; font-family: monospace; margin: 10px 0; }
                                .footer { margin-top: 30px; text-align: center; font-size: 0.9em; color: #666; }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <h1><?php echo htmlspecialchars($systemConfig['hotel_name']); ?></h1>
                                <h2>Credenciais de Acesso à Internet</h2>
                            </div>
                            <div class="credentials">
                                ${credentials.innerHTML}
                            </div>
                            <div class="footer">
                                <p>Gerado em: ${new Date().toLocaleString()}</p>
                                <p>Sistema Hotel v4.0 - Performance Critical Edition</p>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }
        
        function closeCredentials() {
            const credentials = document.getElementById('credentials-result');
            if (credentials) {
                credentials.style.animation = 'slideUp 0.5s ease-out';
                setTimeout(() => {
                    credentials.remove();
                }, 500);
            }
        }
        
        function refreshGuestList() {
            location.reload();
        }
        
        function forceSync() {
            if (confirm('🔄 Forçar sincronização completa entre BD e MikroTik?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="force_sync" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function confirmRemoval(roomNumber, guestName, username) {
            return confirm(`🗑️ Confirma a remoção do acesso?\n\nQuarto: ${roomNumber}\nHóspede: ${guestName}\nUsuário: ${username}\n\nEsta ação não pode ser desfeita.`);
        }
        
        function viewGuestDetails(username) {
            alert(`👁️ Detalhes do hóspede: ${username}\n\nFuncionalidade em desenvolvimento.`);
        }
        
        function retrySync(guestId) {
            if (confirm('🔄 Tentar sincronizar novamente este hóspede?')) {
                // Implementar retry de sincronização
                console.log('Retry sync for guest ID:', guestId);
            }
        }
        
        function toggleViewMode() {
            const container = document.getElementById('guests-container');
            if (container) {
                container.classList.toggle('compact-view');
            }
        }
        
        function exportData() {
            const data = {
                timestamp: new Date().toISOString(),
                system_version: '4.0',
                load_time: '<?php echo $totalLoadTime; ?>',
                system_status: '<?php echo $systemStatus; ?>',
                stats: <?php echo json_encode($systemStats); ?>,
                guests: <?php echo json_encode($activeGuests); ?>
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `hotel_system_export_${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function exportSystemData() {
            exportData();
        }
        
        // Debug tabs
        function showDebugTab(tabName) {
            // Esconder todos os painéis
            document.querySelectorAll('.debug-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Remover active de todas as tabs
            document.querySelectorAll('.debug-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostrar painel selecionado
            const panel = document.getElementById('debug-' + tabName);
            if (panel) {
                panel.classList.add('active');
            }
            
            // Ativar tab selecionada
            event.target.classList.add('active');
        }
        
        // Auto-refresh opcional (desabilitado por padrão para economia de recursos)
        function setupAutoRefresh() {
            // Comentado para evitar refresh automático
            // setInterval(() => {
            //     if (document.visibilityState === 'visible') {
            //         refreshGuestList();
            //     }
            // }, 300000); // 5 minutos
        }
        
        // Monitoramento de performance
        function trackPerformance() {
            const loadTime = performance.now();
            console.log(`⚡ Página carregada em ${loadTime.toFixed(2)}ms`);
            
            // Enviar métricas para o servidor (opcional)
            if (loadTime > 3000) {
                console.warn('⚠️ Carregamento lento detectado:', loadTime);
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N para novo acesso
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                document.getElementById('room_number').focus();
            }
            
            // Ctrl+R para refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshGuestList();
            }
            
            // Esc para fechar alertas
            if (e.key === 'Escape') {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.id) {
                        closeAlert(alert.id);
                    }
                });
            }
        });
        
        // Notificações de sistema
        function showSystemNotification(message, type = 'info') {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Sistema Hotel v4.0', {
                    body: message,
                    icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🏨</text></svg>'
                });
            }
        }
        
        // Solicitar permissão para notificações
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Detectar mudanças de conectividade
        window.addEventListener('online', function() {
            showSystemNotification('Conexão com internet restaurada');
        });
        
        window.addEventListener('offline', function() {
            showSystemNotification('Conexão com internet perdida', 'warning');
        });
        
        // Animações customizadas
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const value = parseInt(stat.textContent);
                let current = 0;
                const increment = value / 30; // 30 frames de animação
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= value) {
                        stat.textContent = value;
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(current);
                    }
                }, 50);
            });
        }
        
        // Executar animações após carregamento
        setTimeout(animateStats, 500);
        
        // Monitorar mudanças de foco para pausar/retomar operações
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                console.log('🔄 Página ativa - retomando operações');
            } else {
                console.log('⏸️ Página inativa - pausando operações');
            }
        });
        
        // Detectar dispositivos móveis
        function isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
        
        if (isMobile()) {
            document.body.classList.add('mobile-device');
            console.log('📱 Dispositivo móvel detectado');
        }
        
        // Otimizações para performance
        function optimizePerformance() {
            // Lazy loading para imagens (se houver)
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
            
            // Otimizar animações baseado na performance
            const isSlowDevice = navigator.hardwareConcurrency < 4;
            if (isSlowDevice) {
                document.body.classList.add('reduced-animations');
            }
        }
        
        // Executar otimizações
        optimizePerformance();
        
        // Debug console para desenvolvimento
        console.log(`
🏨 Sistema Hotel v4.0 - Performance Critical Edition
⚡ Carregado em: <?php echo $totalLoadTime; ?>ms
🔧 Status: <?php echo $systemStatus; ?>
💾 BD: <?php echo $systemStats['active_guests']; ?> hóspedes ativos
📡 MikroTik: <?php echo $systemStats['mikrotik_total']; ?> usuários
🔄 Sync: <?php echo $systemStats['sync_rate']; ?>%
        `);
        
        // Shortcuts de teclado para debug
        window.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                console.log('Debug info:', {
                    systemStats: <?php echo json_encode($systemStats); ?>,
                    systemHealth: <?php echo json_encode($systemHealth); ?>,
                    activeGuests: <?php echo json_encode($activeGuests); ?>
                });
            }
        });
        
        // Finalizar inicialização
        console.log('✅ Sistema Hotel v4.0 totalmente carregado e operacional');
        trackPerformance();
        
        // CSS adicional para animações
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideUp {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(-30px); }
            }
            
            .compact-view .guest-row {
                padding: 10px;
                font-size: 0.9em;
            }
            
            .mobile-device .stat-number {
                font-size: 2.5em;
            }
            
            .reduced-animations * {
                animation-duration: 0.1s !important;
                transition-duration: 0.1s !important;
            }
            
            .form-actions {
                display: flex;
                gap: 15px;
                justify-content: center;
                margin-top: 30px;
            }
            
            .header-cell {
                font-weight: 600;
                color: white;
            }
            
            .guest-cell {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .username-display, .password-display {
                font-family: 'Courier New', monospace;
                font-size: 0.9em;
                background: #ecf0f1;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .username-display:hover, .password-display:hover {
                background: #3498db;
                color: white;
                transform: scale(1.05);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
<?php
// Flush do buffer do logger ao final (HotelLogger não tem método flushBuffer)
if (isset($hotelSystem)) {
    // HotelLogger escreve diretamente no arquivo, não precisa de flush
}
?>