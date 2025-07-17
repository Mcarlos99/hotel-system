<?php
// index.php - Sistema Hotel v3.0 - VERSÃO COMPLETA FINAL
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Definir encoding UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

// Incluir arquivos necessários
require_once 'config.php';
require_once 'mikrotik_manager.php';

/**
 * Classe de logging melhorada sem caracteres especiais
 */
class HotelLoggerFixed {
    private $logFile;
    private $enabled;
    
    public function __construct($logFile = 'logs/hotel_system.log', $enabled = true) {
        $this->logFile = $logFile;
        $this->enabled = $enabled;
        
        // Criar diretório se não existir
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function log($level, $message, $context = []) {
        if (!$this->enabled) return;
        
        // Limpar mensagem removendo caracteres especiais
        $message = $this->cleanMessage($message);
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        // Log no arquivo
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Log no PHP error log limpo
        error_log("[HOTEL_SYSTEM] [{$level}] {$message}");
    }
    
    private function cleanMessage($message) {
        // Substituir caracteres especiais por equivalentes ASCII
        $replacements = [
            'ç' => 'c', 'Ç' => 'C', 'ã' => 'a', 'Ã' => 'A', 'á' => 'a', 'Á' => 'A',
            'à' => 'a', 'À' => 'A', 'â' => 'a', 'Â' => 'A', 'é' => 'e', 'É' => 'E',
            'ê' => 'e', 'Ê' => 'E', 'í' => 'i', 'Í' => 'I', 'ó' => 'o', 'Ó' => 'O',
            'ô' => 'o', 'Ô' => 'O', 'õ' => 'o', 'Õ' => 'O', 'ú' => 'u', 'Ú' => 'U',
            '✅' => '[OK]', '❌' => '[ERRO]', '⚠️' => '[AVISO]', '🎉' => '[SUCESSO]',
            '🔄' => '[PROC]', '🔍' => '[DEBUG]', '📡' => '[CONN]', '💾' => '[BD]'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $message);
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
    
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }
}

/**
 * Sistema Hotel com Parser de Dados Brutos integrado - VERSÃO COMPLETA
 */
class HotelSystemV3Complete {
    protected $mikrotik;
    protected $db;
    protected $logger;
    protected $systemConfig;
    protected $userProfiles;
    
    public function __construct($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles) {
        $this->logger = new HotelLoggerFixed();
        $this->systemConfig = $systemConfig;
        $this->userProfiles = $userProfiles;
        
        // Conectar ao banco
        try {
            $this->db = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            $this->logger->info("Conectado ao banco de dados com sucesso");
            
        } catch (PDOException $e) {
            $this->logger->error("Erro na conexao com banco: " . $e->getMessage());
            throw new Exception("Erro na conexao com banco de dados");
        }
        
        // Conectar ao MikroTik
        try {
            $this->mikrotik = new MikroTikHotspotManagerFixed(
                $mikrotikConfig['host'],
                $mikrotikConfig['username'],
                $mikrotikConfig['password'],
                $mikrotikConfig['port'] ?? 8728
            );
            
            $this->logger->info("MikroTik Parser v3 inicializado com sucesso");
            
        } catch (Exception $e) {
            $this->logger->warning("MikroTik nao conectado: " . $e->getMessage());
            $this->mikrotik = null;
        }
        
        $this->createTables();
    }
    
    /**
     * Gera credenciais com novo sistema
     */
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        $this->logger->info("Gerando credenciais para quarto {$roomNumber}");
        
        try {
            // Verificar se já existe usuário ativo
            $existingUser = $this->getActiveGuestByRoom($roomNumber);
            if ($existingUser) {
                return [
                    'success' => false,
                    'error' => "Ja existe um usuario ativo para o quarto {$roomNumber}. Remova primeiro."
                ];
            }
            
            // Gerar credenciais simples
            $username = $this->generateSimpleUsername($roomNumber);
            $password = $this->generateSimplePassword();
            $timeLimit = $this->calculateTimeLimit($checkoutDate);
            
            // Tentar criar no MikroTik
            $mikrotikSuccess = false;
            $mikrotikMessage = '';
            
            if ($this->mikrotik) {
                try {
                    $this->mikrotik->connect();
                    $this->mikrotik->createHotspotUser($username, $password, $profileType, $timeLimit);
                    $this->mikrotik->disconnect();
                    $mikrotikSuccess = true;
                    $mikrotikMessage = 'Criado no MikroTik com sucesso';
                    
                } catch (Exception $e) {
                    $mikrotikMessage = 'Erro MikroTik: ' . $e->getMessage();
                    $this->logger->warning("Erro MikroTik na criacao: " . $e->getMessage());
                }
            } else {
                $mikrotikMessage = 'MikroTik nao configurado';
            }
            
            // Salvar no banco (sempre fazer)
            $stmt = $this->db->prepare("
                INSERT INTO hotel_guests (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
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
                $this->logger->info("Credenciais geradas: {$username} para quarto {$roomNumber}");
                
                return [
                    'success' => true,
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profileType,
                    'valid_until' => $checkoutDate,
                    'bandwidth' => $this->userProfiles[$profileType]['rate_limit'] ?? '10M/2M',
                    'mikrotik_success' => $mikrotikSuccess,
                    'mikrotik_message' => $mikrotikMessage
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Falha ao salvar no banco de dados'
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error("Erro ao gerar credenciais: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Remove acesso usando novo parser
     */
    public function removeGuestAccess($roomNumber) {
        try {
            $this->logger->info("Iniciando remocao para quarto {$roomNumber}");
            
            // Buscar hóspede no banco
            $stmt = $this->db->prepare("
                SELECT id, username, guest_name 
                FROM hotel_guests 
                WHERE room_number = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$roomNumber]);
            $guest = $stmt->fetch();
            
            if (!$guest) {
                return [
                    'success' => false,
                    'error' => "Nenhum hospede ativo encontrado para o quarto {$roomNumber}"
                ];
            }
            
            $username = $guest['username'];
            $guestName = $guest['guest_name'];
            $guestId = $guest['id'];
            
            $this->logger->info("Hospede encontrado: {$username} ({$guestName})");
            
            // Tentar remover do MikroTik
            $mikrotikSuccess = false;
            $mikrotikMessage = '';
            
            if ($this->mikrotik) {
                try {
                    $this->mikrotik->connect();
                    
                    // Desconectar se ativo
                    $this->mikrotik->disconnectUser($username);
                    
                    // Remover usuário (novo parser)
                    $removeResult = $this->mikrotik->removeHotspotUser($username);
                    
                    $this->mikrotik->disconnect();
                    
                    if ($removeResult) {
                        $mikrotikSuccess = true;
                        $mikrotikMessage = 'Removido do MikroTik com sucesso';
                        $this->logger->info("Usuario {$username} removido do MikroTik com sucesso");
                    } else {
                        $mikrotikMessage = 'Falha na remocao do MikroTik';
                        $this->logger->warning("Falha na remocao do MikroTik para {$username}");
                    }
                    
                } catch (Exception $e) {
                    $mikrotikMessage = 'Erro: ' . $e->getMessage();
                    $this->logger->warning("Erro MikroTik na remocao: " . $e->getMessage());
                }
            } else {
                $mikrotikMessage = 'MikroTik nao configurado';
            }
            
            // Atualizar banco (sempre fazer)
            $stmt = $this->db->prepare("
                UPDATE hotel_guests 
                SET status = 'disabled', updated_at = NOW() 
                WHERE id = ?
            ");
            
            $dbResult = $stmt->execute([$guestId]);
            
            if ($dbResult) {
                // Log da ação
                $this->logAction($username, $roomNumber, 'disabled');
                
                $status = $mikrotikSuccess ? "[OK]" : "[AVISO]";
                $message = "{$status} Acesso removido para {$guestName} (Quarto {$roomNumber}) | {$mikrotikMessage}";
                
                $this->logger->info("Remocao concluida: {$message}");
                
                return [
                    'success' => true,
                    'message' => $message,
                    'mikrotik_success' => $mikrotikSuccess
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Falha ao atualizar status no banco'
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error("Erro geral na remocao: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Limpeza de usuários expirados
     */
    public function cleanupExpiredUsers() {
        try {
            $stmt = $this->db->prepare("
                SELECT username, room_number 
                FROM hotel_guests 
                WHERE checkout_date < CURDATE() AND status = 'active'
            ");
            $stmt->execute();
            $expiredUsers = $stmt->fetchAll();
            
            $removedCount = 0;
            
            foreach ($expiredUsers as $user) {
                try {
                    // Remover do MikroTik
                    if ($this->mikrotik) {
                        $this->mikrotik->connect();
                        $this->mikrotik->disconnectUser($user['username']);
                        $this->mikrotik->removeHotspotUser($user['username']);
                        $this->mikrotik->disconnect();
                    }
                } catch (Exception $e) {
                    $this->logger->warning("Erro ao remover {$user['username']} do MikroTik: " . $e->getMessage());
                }
                
                // Atualizar banco
                $stmt = $this->db->prepare("
                    UPDATE hotel_guests 
                    SET status = 'expired', updated_at = NOW() 
                    WHERE username = ?
                ");
                
                if ($stmt->execute([$user['username']])) {
                    $removedCount++;
                    $this->logAction($user['username'], $user['room_number'], 'expired');
                }
            }
            
            $this->logger->info("Limpeza automatica: {$removedCount} usuarios expirados removidos");
            
            return ['success' => true, 'removed' => $removedCount];
            
        } catch (Exception $e) {
            $this->logger->error("Erro na limpeza: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtém estatísticas do sistema
     */
    public function getSystemStats() {
        $stats = [];
        
        // Estatísticas do banco
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests");
            $stats['total_guests'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE status = 'active'");
            $stats['active_guests'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE DATE(created_at) = CURDATE()");
            $stats['today_guests'] = $stmt->fetchColumn();
            
        } catch (Exception $e) {
            $stats['total_guests'] = 0;
            $stats['active_guests'] = 0;
            $stats['today_guests'] = 0;
        }
        
        // Estatísticas do MikroTik
        $stats['online_users'] = 0;
        $stats['mikrotik_total'] = 0;
        
        if ($this->mikrotik) {
            try {
                $mikrotikStats = $this->mikrotik->getHotspotStats();
                $stats['online_users'] = $mikrotikStats['active_users'] ?? 0;
                $stats['mikrotik_total'] = $mikrotikStats['total_users'] ?? 0;
            } catch (Exception $e) {
                $this->logger->warning("Erro ao obter stats do MikroTik: " . $e->getMessage());
            }
        }
        
        return $stats;
    }
    
    /**
     * Lista hóspedes ativos
     */
    public function getActiveGuests() {
        $stmt = $this->db->prepare("
            SELECT id, room_number, guest_name, username, password, profile_type, 
                   checkin_date, checkout_date, created_at, status
            FROM hotel_guests 
            WHERE status = 'active' 
            ORDER BY room_number
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Busca hóspede por quarto
     */
    public function getActiveGuestByRoom($roomNumber) {
        $stmt = $this->db->prepare("
            SELECT * FROM hotel_guests 
            WHERE room_number = ? AND status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([$roomNumber]);
        return $stmt->fetch();
    }
    
    /**
     * Debug do sistema
     */
    public function debugSystem() {
        $debug = [
            'database' => $this->debugDatabase(),
            'mikrotik' => $this->debugMikroTik(),
            'active_guests' => $this->getActiveGuests(),
            'system_stats' => $this->getSystemStats()
        ];
        
        return $debug;
    }
    
    private function debugDatabase() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests");
            $totalGuests = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM hotel_guests WHERE status = 'active'");
            $activeGuests = $stmt->fetchColumn();
            
            return [
                'connected' => true,
                'total_guests' => $totalGuests,
                'active_guests' => $activeGuests
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function debugMikroTik() {
        if (!$this->mikrotik) {
            return [
                'connected' => false,
                'error' => 'MikroTik nao configurado'
            ];
        }
        
        try {
            $connectionTest = $this->mikrotik->testConnection();
            if (!$connectionTest['success']) {
                return [
                    'connected' => false,
                    'error' => $connectionTest['message']
                ];
            }
            
            $this->mikrotik->connect();
            $users = $this->mikrotik->listHotspotUsers();
            $active = $this->mikrotik->getActiveUsers();
            $this->mikrotik->disconnect();
            
            return [
                'connected' => true,
                'total_users' => count($users),
                'active_users' => count($active),
                'users' => $users
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Métodos auxiliares para geração de credenciais
     */
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
        // Sequências crescentes
        if (preg_match('/123|234|345|456|567|678|789/', $password)) return true;
        
        // Sequências decrescentes
        if (preg_match('/987|876|765|654|543|432|321/', $password)) return true;
        
        // Números repetidos
        if (preg_match('/(.)\1\1+/', $password)) return true;
        
        // Padrões óbvios
        $obviousPatterns = [
            '1234', '4321', '1111', '2222', '3333', '4444', '5555',
            '6666', '7777', '8888', '9999', '0000', '1212', '1010'
        ];
        
        return in_array($password, $obviousPatterns);
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
    
    private function logAction($username, $roomNumber, $action) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO access_logs (username, room_number, action, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $username,
                $roomNumber,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            $this->logger->warning("Erro no log da acao: " . $e->getMessage());
        }
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS access_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            room_number VARCHAR(10) NOT NULL,
            action ENUM('login', 'logout', 'created', 'disabled', 'expired') NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_room (room_number),
            INDEX idx_action (action),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        
        $this->logger->info("Tabelas do banco verificadas/criadas com sucesso");
    }
}

// Inicializar o sistema
try {
    $hotelSystem = new HotelSystemV3Complete($mikrotikConfig, $dbConfig, $systemConfig, $userProfiles);
} catch (Exception $e) {
    die("Erro ao inicializar sistema: " . $e->getMessage());
}

// Processar ações do formulário
$result = null;
$message = null;
$debugInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['generate_access'])) {
            $result = $hotelSystem->generateCredentials(
                $_POST['room_number'],
                $_POST['guest_name'],
                $_POST['checkin_date'],
                $_POST['checkout_date'],
                $_POST['profile_type'] ?? 'hotel-guest'
            );
            
        } elseif (isset($_POST['remove_access'])) {
            $roomNumber = $_POST['room_number'];
            
            $removeResult = $hotelSystem->removeGuestAccess($roomNumber);
            
            if ($removeResult['success']) {
                $message = $removeResult['message'];
            } else {
                $message = "[ERRO] Erro: " . $removeResult['error'];
            }
            
        } elseif (isset($_POST['cleanup_expired'])) {
            $result = $hotelSystem->cleanupExpiredUsers();
            $message = "[LIMPEZA] Limpeza concluida. Usuarios removidos: " . $result['removed'];
            
        } elseif (isset($_POST['debug_system'])) {
            $debugInfo = $hotelSystem->debugSystem();
            $message = "[DEBUG] Debug do sistema executado";
        }
        
    } catch (Exception $e) {
        $message = "[ERRO] Erro: " . $e->getMessage();
    }
}

// Obter dados para exibição
$activeGuests = $hotelSystem->getActiveGuests();
$systemStats = $hotelSystem->getSystemStats();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($systemConfig['hotel_name']); ?> - Sistema v3.0</title>
    <style>
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
        }
        
        .container {
            max-width: 1200px;
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
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .version-badge {
            position: absolute;
            top: 15px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }
        
        .parser-badge {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 10px;
            display: inline-block;
        }
        
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
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        }
        
        .main-content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            border-left: 5px solid #3498db;
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.8em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section h2::before {
            content: '';
            width: 4px;
            height: 30px;
            background: #3498db;
            border-radius: 2px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"],
        input[type="date"],
        select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .btn-danger:hover {
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
            border-left: 5px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #27ae60;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #e74c3c;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }
        
        .credentials-display {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 10px 30px rgba(39, 174, 96, 0.3);
        }
        
        .credentials-display h3 {
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .credential-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .credential-box {
            background: rgba(255,255,255,0.15);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .credential-label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .credential-value {
            font-size: 2.5em;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .credential-value:hover {
            transform: scale(1.05);
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        
        .credential-info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 0.9em;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        th {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        td {
            padding: 15px 20px;
            border-bottom: 1px solid #ecf0f1;
            vertical-align: middle;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .room-number {
            font-weight: bold;
            color: #3498db;
            font-size: 1.2em;
        }
        
        .username-display {
            font-family: 'Courier New', monospace;
            background: #ecf0f1;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .username-display:hover {
            background: #3498db;
            color: white;
        }
        
        .password-display {
            font-family: 'Courier New', monospace;
            background: #fff3cd;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .password-display:hover {
            background: #f39c12;
            color: white;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            color: white;
            background: #3498db;
        }
        
        .profile-badge.hotel-guest {
            background: #3498db;
        }
        
        .profile-badge.hotel-vip {
            background: #f39c12;
        }
        
        .profile-badge.hotel-staff {
            background: #e74c3c;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online {
            background: #27ae60;
            animation: pulse 2s infinite;
        }
        
        .status-offline {
            background: #95a5a6;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .debug-section {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .debug-title {
            color: #856404;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }
        
        .footer a {
            color: #3498db;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .connection-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        
        .connection-success {
            background: #d4edda;
            color: #155724;
        }
        
        .connection-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .mikrotik-info {
            background: #e7f3ff;
            border: 1px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .mikrotik-info h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .credential-pair {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
                margin: 2px 0;
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-in {
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="version-badge">v3.0 - Parser de Dados Brutos</div>
            <h1>🏨 <?php echo htmlspecialchars($systemConfig['hotel_name']); ?></h1>
            <p>Sistema de Gerenciamento de Internet</p>
            <div class="parser-badge">
                ✅ Parser de Dados Brutos Ativo
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['total_guests']; ?></div>
                <div class="stat-label">Total de Hóspedes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['active_guests']; ?></div>
                <div class="stat-label">Ativos no Sistema</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['mikrotik_total']; ?></div>
                <div class="stat-label">No MikroTik</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['online_users']; ?></div>
                <div class="stat-label">Online Agora</div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Mensagens -->
            <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, '[ERRO]') !== false ? 'alert-error' : (strpos($message, '[AVISO]') !== false ? 'alert-info' : 'alert-success'); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulário para gerar acesso -->
            <div class="section fade-in">
                <h2>🆕 Gerar Novo Acesso</h2>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="room_number">Número do Quarto:</label>
                            <input type="text" id="room_number" name="room_number" required 
                                   placeholder="Ex: 101, 205A" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="guest_name">Nome do Hóspede:</label>
                            <input type="text" id="guest_name" name="guest_name" required 
                                   placeholder="Nome completo do hóspede">
                        </div>
                        
                        <div class="form-group">
                            <label for="checkin_date">Data de Check-in:</label>
                            <input type="date" id="checkin_date" name="checkin_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="checkout_date">Data de Check-out:</label>
                            <input type="date" id="checkout_date" name="checkout_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_type">Tipo de Perfil:</label>
                            <select id="profile_type" name="profile_type">
                                <?php foreach ($userProfiles as $key => $profile): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>">
                                        <?php echo htmlspecialchars($profile['name']); ?> 
                                        (<?php echo htmlspecialchars($profile['rate_limit']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="generate_access" class="btn">
                        ✨ Gerar Credenciais
                    </button>
                </form>
                
                <!-- Resultado da geração -->
                <?php if (isset($result) && $result['success']): ?>
                    <div class="credentials-display">
                        <h3>🎉 Credenciais Geradas com Sucesso!</h3>
                        
                        <div class="credential-pair">
                            <div class="credential-box">
                                <div class="credential-label">👤 USUÁRIO</div>
                                <div class="credential-value" onclick="copyToClipboard('<?php echo $result['username']; ?>')">
                                    <?php echo htmlspecialchars($result['username']); ?>
                                </div>
                            </div>
                            <div class="credential-box">
                                <div class="credential-label">🔒 SENHA</div>
                                <div class="credential-value" onclick="copyToClipboard('<?php echo $result['password']; ?>')">
                                    <?php echo htmlspecialchars($result['password']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="credential-info">
                            <strong>📋 Informações:</strong><br>
                            Quarto: <?php echo htmlspecialchars($_POST['room_number'] ?? ''); ?> | 
                            Hóspede: <?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?> | 
                            Perfil: <?php echo htmlspecialchars($result['profile'] ?? ''); ?> | 
                            Válido até: <?php echo date('d/m/Y', strtotime($result['valid_until'])); ?>
                            
                            <?php if (isset($result['mikrotik_message'])): ?>
                                <br><strong>🔧 Status MikroTik:</strong> <?php echo htmlspecialchars($result['mikrotik_message']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 15px; font-size: 0.9em; opacity: 0.9;">
                            💡 Clique nas credenciais para copiar
                        </div>
                    </div>
                <?php elseif (isset($result) && !$result['success']): ?>
                    <div class="alert alert-error">
                        <strong>❌ Erro:</strong> <?php echo htmlspecialchars($result['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Hóspedes Ativos -->
            <div class="section slide-in">
                <h2>👥 Hóspedes Ativos (<?php echo count($activeGuests); ?>)</h2>
                
                <?php if (empty($activeGuests)): ?>
                    <div class="alert alert-info">
                        <strong>📋 Nenhum hóspede ativo encontrado.</strong><br>
                        Gere credenciais para novos hóspedes usando o formulário acima.
                    </div>
                <?php else: ?>
                    <div class="mikrotik-info">
                        <h4>🔧 Status do Sistema:</h4>
                        <p>
                            <span class="status-indicator <?php echo $systemStats['mikrotik_total'] > 0 ? 'status-online' : 'status-offline'; ?>"></span>
                            Parser de Dados Brutos: 
                            <span class="connection-status <?php echo $systemStats['mikrotik_total'] > 0 ? 'connection-success' : 'connection-error'; ?>">
                                <?php echo $systemStats['mikrotik_total'] > 0 ? 'Funcionando' : 'Verificar Conexão'; ?>
                            </span>
                        </p>
                        <p>
                            <strong>Sistema:</strong> <?php echo $systemStats['active_guests']; ?> ativos | 
                            <strong>MikroTik:</strong> <?php echo $systemStats['mikrotik_total']; ?> usuários | 
                            <strong>Online:</strong> <?php echo $systemStats['online_users']; ?> conectados
                        </p>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Quarto</th>
                                <th>Hóspede</th>
                                <th>Usuário</th>
                                <th>Senha</th>
                                <th>Perfil</th>
                                <th>Check-out</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeGuests as $guest): ?>
                            <tr>
                                <td class="room-number"><?php echo htmlspecialchars($guest['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                                <td>
                                    <span class="username-display" onclick="copyToClipboard('<?php echo htmlspecialchars($guest['username']); ?>')" title="Clique para copiar">
                                        <?php echo htmlspecialchars($guest['username']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="password-display" onclick="copyToClipboard('<?php echo htmlspecialchars($guest['password']); ?>')" title="Clique para copiar">
                                        <?php echo htmlspecialchars($guest['password']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="profile-badge <?php echo htmlspecialchars($guest['profile_type']); ?>">
                                        <?php echo isset($userProfiles[$guest['profile_type']]) ? htmlspecialchars($userProfiles[$guest['profile_type']]['name']) : htmlspecialchars($guest['profile_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($guest['checkout_date'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" action="" style="display: inline;" 
                                              onsubmit="return confirmRemoval('<?php echo htmlspecialchars($guest['room_number']); ?>', '<?php echo htmlspecialchars($guest['guest_name']); ?>', '<?php echo htmlspecialchars($guest['username']); ?>')">
                                            <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($guest['room_number']); ?>">
                                            <button type="submit" name="remove_access" class="btn btn-danger">
                                                🗑️ Remover
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div class="actions" style="margin-top: 20px;">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="cleanup_expired" class="btn btn-warning"
                                onclick="return confirm('🧹 Remover todos os usuários com check-out vencido?');">
                            🧹 Limpar Expirados
                        </button>
                    </form>
                    <button onclick="location.reload()" class="btn btn-info">
                        🔄 Atualizar Lista
                    </button>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="debug_system" class="btn btn-warning">
                            🔍 Debug Sistema
                        </button>
                    </form>
                    <a href="test_raw_parser_final.php" class="btn btn-success">
                        🧪 Testar Parser
                    </a>
                </div>
            </div>
            
            <!-- Debug Info -->
            <?php if ($debugInfo): ?>
            <div class="debug-section">
                <div class="debug-title">🔍 Informações de Debug do Sistema</div>
                
                <h4>💾 Status do Banco de Dados</h4>
                <div class="debug-info">
<?php 
echo "Conectado: " . ($debugInfo['database']['connected'] ? "✅ SIM" : "❌ NÃO") . "\n";
if ($debugInfo['database']['connected']) {
    echo "Total de hóspedes: " . $debugInfo['database']['total_guests'] . "\n";
    echo "Hóspedes ativos: " . $debugInfo['database']['active_guests'] . "\n";
} else {
    echo "Erro: " . $debugInfo['database']['error'] . "\n";
}
?>
                </div>
                
                <h4>📡 Status do MikroTik</h4>
                <div class="debug-info">
<?php 
echo "Conectado: " . ($debugInfo['mikrotik']['connected'] ? "✅ SIM" : "❌ NÃO") . "\n";
if ($debugInfo['mikrotik']['connected']) {
    echo "Total de usuários no MikroTik: " . $debugInfo['mikrotik']['total_users'] . "\n";
    echo "Usuários ativos no MikroTik: " . $debugInfo['mikrotik']['active_users'] . "\n";
    
    if (!empty($debugInfo['mikrotik']['users'])) {
        echo "\nUsuários encontrados pelo parser:\n";
        foreach ($debugInfo['mikrotik']['users'] as $i => $user) {
            echo ($i + 1) . ". " . ($user['name'] ?? 'N/A') . 
                 " (ID: " . ($user['id'] ?? 'N/A') . ")" . 
                 " [" . ($user['profile'] ?? 'N/A') . "]\n";
        }
    }
} else {
    echo "Erro: " . $debugInfo['mikrotik']['error'] . "\n";
}
?>
                </div>
                
                <h4>📊 Estatísticas do Sistema</h4>
                <div class="debug-info">
<?php 
echo "Estatísticas atuais:\n";
echo "- Total de hóspedes: " . $debugInfo['system_stats']['total_guests'] . "\n";
echo "- Hóspedes ativos: " . $debugInfo['system_stats']['active_guests'] . "\n";
echo "- Usuários no MikroTik: " . $debugInfo['system_stats']['mikrotik_total'] . "\n";
echo "- Usuários online: " . $debugInfo['system_stats']['online_users'] . "\n";
echo "- Criados hoje: " . $debugInfo['system_stats']['today_guests'] . "\n";
echo "\nTimestamp: " . date('Y-m-d H:i:s') . "\n";
?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>&copy; 2025 Sistema Hotel v3.0 - Parser de Dados Brutos</p>
            <p>
                <a href="debug_hotel.php">Debug Completo</a> |
                <a href="test_raw_parser_final.php">Testar Parser</a> |
                <a href="mikrotik_deep_diagnosis.php">Diagnóstico MikroTik</a>
            </p>
        </div>
    </div>
    
    <script>
        // Função para copiar para clipboard
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showNotification('✅ Copiado para a área de transferência!', 'success');
                }).catch(err => {
                    console.error('Erro ao copiar:', err);
                    showNotification('❌ Erro ao copiar', 'error');
                });
            } else {
                // Fallback para navegadores antigos
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showNotification('✅ Copiado para a área de transferência!', 'success');
                } catch (err) {
                    showNotification('❌ Erro ao copiar', 'error');
                }
                document.body.removeChild(textArea);
            }
        }
        
        // Função para mostrar notificações
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                max-width: 300px;
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = message;
            
            document.body.appendChild(notification);
            
            // Remover após 3 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        // Função de confirmação melhorada
        function confirmRemoval(room, guest, username) {
            const message = `⚠️ CONFIRMAR REMOÇÃO?\n\n` +
                          `🏠 Quarto: ${room}\n` +
                          `👤 Hóspede: ${guest}\n` +
                          `🔑 Usuário: ${username}\n\n` +
                          `Esta ação irá:\n` +
                          `• Desconectar o usuário do WiFi\n` +
                          `• Remover o acesso do MikroTik\n` +
                          `• Desativar no sistema\n\n` +
                          `✅ Confirmar remoção?`;
            
            return confirm(message);
        }
        
        // Definir datas padrão
        document.addEventListener('DOMContentLoaded', function() {
            const checkinDate = document.getElementById('checkin_date');
            const checkoutDate = document.getElementById('checkout_date');
            
            if (checkinDate && checkoutDate) {
                // Data atual para check-in
                const today = new Date();
                checkinDate.valueAsDate = today;
                
                // Amanhã para check-out
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                checkoutDate.valueAsDate = tomorrow;
                
                // Validação das datas
                checkinDate.addEventListener('change', function() {
                    const checkin = new Date(this.value);
                    const checkout = new Date(checkoutDate.value);
                    
                    if (checkin >= checkout) {
                        const newCheckout = new Date(checkin);
                        newCheckout.setDate(newCheckout.getDate() + 1);
                        checkoutDate.valueAsDate = newCheckout;
                    }
                });
                
                checkoutDate.addEventListener('change', function() {
                    const checkin = new Date(checkinDate.value);
                    const checkout = new Date(this.value);
                    
                    if (checkout <= checkin) {
                        showNotification('❌ Data de check-out deve ser posterior ao check-in', 'error');
                        const newCheckout = new Date(checkin);
                        newCheckout.setDate(newCheckout.getDate() + 1);
                        this.valueAsDate = newCheckout;
                    }
                });
            }
        });
        
        // Adicionar animações CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .btn:active {
                transform: scale(0.98);
            }
            
            .credential-value:active {
                transform: scale(0.95);
            }
            
            .stat-card:hover .stat-number {
                color: #2980b9;
            }
            
            .username-display:hover,
            .password-display:hover {
                cursor: pointer;
            }
        `;
        document.head.appendChild(style);
        
        // Função para validar formulário
        function validateForm() {
            const roomNumber = document.getElementById('room_number').value.trim();
            const guestName = document.getElementById('guest_name').value.trim();
            const checkinDate = document.getElementById('checkin_date').value;
            const checkoutDate = document.getElementById('checkout_date').value;
            
            if (!roomNumber) {
                showNotification('❌ Número do quarto é obrigatório', 'error');
                return false;
            }
            
            if (!guestName) {
                showNotification('❌ Nome do hóspede é obrigatório', 'error');
                return false;
            }
            
            if (!checkinDate || !checkoutDate) {
                showNotification('❌ Datas de check-in e check-out são obrigatórias', 'error');
                return false;
            }
            
            const checkin = new Date(checkinDate);
            const checkout = new Date(checkoutDate);
            
            if (checkout <= checkin) {
                showNotification('❌ Data de check-out deve ser posterior ao check-in', 'error');
                return false;
            }
            
            return true;
        }
        
        // Adicionar validação ao formulário
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (e.submitter && e.submitter.name === 'generate_access') {
                        if (!validateForm()) {
                            e.preventDefault();
                        }
                    }
                });
            }
        });
        
        // Função para mostrar loading durante operações
        function showLoading(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '⏳ Processando...';
            button.disabled = true;
            
            return function hideLoading() {
                button.innerHTML = originalText;
                button.disabled = false;
            };
        }
        
        // Adicionar loading aos botões
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('button[type="submit"]');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const form = this.closest('form');
                    if (form) {
                        const hideLoading = showLoading(this);
                        
                        // Esconder loading após 10 segundos (timeout)
                        setTimeout(hideLoading, 10000);
                    }
                });
            });
        });
        
        // Console debug
        console.log('🏨 Sistema Hotel v3.0 - Parser de Dados Brutos');
        console.log('Hóspedes ativos:', <?php echo count($activeGuests); ?>);
        console.log('Status MikroTik:', <?php echo $systemStats['mikrotik_total'] > 0 ? 'true' : 'false'; ?>);
        
        // Log de POST para debug
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        console.log('POST enviado:', <?php echo json_encode($_POST); ?>);
        console.log('Mensagem retornada:', '<?php echo addslashes($message ?? 'Nenhuma'); ?>');
        console.log('Timestamp:', '<?php echo date('Y-m-d H:i:s'); ?>');
        <?php endif; ?>
        
        // Verificar se houve operação bem-sucedida
        <?php if (isset($message) && (strpos($message, '[OK]') !== false || strpos($message, 'SUCESSO') !== false)): ?>
        console.log('🎉 OPERAÇÃO BEM-SUCEDIDA!');
        console.log('Mensagem de sucesso:', '<?php echo addslashes($message); ?>');
        
        // Destacar a mensagem de sucesso
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                alert.style.border = '3px solid #27ae60';
                alert.style.animation = 'pulse 1s ease-in-out 3';
            });
        });
        
        // Adicionar animação de pulse
        const pulseStyle = document.createElement('style');
        pulseStyle.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(pulseStyle);
        <?php endif; ?>
        
        // Função para atualizar estatísticas em tempo real (opcional)
        function updateStats() {
            fetch('?ajax=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector('.stat-card:nth-child(1) .stat-number').textContent = data.total_guests;
                        document.querySelector('.stat-card:nth-child(2) .stat-number').textContent = data.active_guests;
                        document.querySelector('.stat-card:nth-child(3) .stat-number').textContent = data.mikrotik_total;
                        document.querySelector('.stat-card:nth-child(4) .stat-number').textContent = data.online_users;
                    }
                })
                .catch(err => console.log('Erro na atualização de stats:', err));
        }
        
        // Atualizar stats a cada 30 segundos (descomente se desejar)
        // setInterval(updateStats, 30000);
        
        // Função para destacar campos com erro
        function highlightError(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.style.borderColor = '#e74c3c';
                field.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
                
                setTimeout(() => {
                    field.style.borderColor = '#ddd';
                    field.style.boxShadow = '';
                }, 3000);
            }
        }
        
        // Adicionar tooltip para elementos com título
        document.addEventListener('DOMContentLoaded', function() {
            const elementsWithTitle = document.querySelectorAll('[title]');
            elementsWithTitle.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = this.title;
                    tooltip.style.cssText = `
                        position: absolute;
                        background: #2c3e50;
                        color: white;
                        padding: 8px 12px;
                        border-radius: 6px;
                        font-size: 12px;
                        z-index: 1000;
                        pointer-events: none;
                        white-space: nowrap;
                    `;
                    
                    document.body.appendChild(tooltip);
                    
                    this.addEventListener('mousemove', function(e) {
                        tooltip.style.left = e.pageX + 10 + 'px';
                        tooltip.style.top = e.pageY - 30 + 'px';
                    });
                    
                    this.addEventListener('mouseleave', function() {
                        if (document.body.contains(tooltip)) {
                            document.body.removeChild(tooltip);
                        }
                    });
                });
            });
        });
        
        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                    case 'n':
                        e.preventDefault();
                        document.getElementById('room_number').focus();
                        break;
                    case 'h':
                        e.preventDefault();
                        showHelp();
                        break;
                }
            }
        });
        
        // Função para mostrar ajuda
        function showHelp() {
            const helpText = `
🔧 SISTEMA HOTEL v3.0 - AJUDA

ATALHOS:
• Ctrl+R: Atualizar página
• Ctrl+N: Foco no campo Quarto
• Ctrl+H: Mostrar esta ajuda

RECURSOS:
• Clique nas credenciais para copiar
• Confirmação antes de remover usuários
• Validação automática de datas
• Notificações em tempo real
• Parser de dados brutos ativo

VERSÃO: 3.0 - Parser de Dados Brutos
STATUS: Sistema funcionando
            `;
            
            alert(helpText);
        }
        
        // Função para exportar dados (opcional)
        function exportData() {
            const data = {
                timestamp: new Date().toISOString(),
                stats: {
                    total: <?php echo $systemStats['total_guests']; ?>,
                    active: <?php echo $systemStats['active_guests']; ?>,
                    mikrotik: <?php echo $systemStats['mikrotik_total']; ?>,
                    online: <?php echo $systemStats['online_users']; ?>
                },
                guests: <?php echo json_encode($activeGuests); ?>
            };
            
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'hotel_data_' + new Date().toISOString().split('T')[0] + '.json';
            link.click();
            URL.revokeObjectURL(url);
            
            showNotification('📁 Dados exportados com sucesso!', 'success');
        }
        
        console.log('🚀 Sistema Hotel v3.0 carregado completamente!');
        console.log('✅ Parser de Dados Brutos ativo');
        console.log('📊 Estatísticas atualizadas');
        console.log('🔧 Debug disponível');
        console.log('💡 Use Ctrl+H para ajuda');
    </script>
</body>
</html>

<?php
// Cleanup e finalização
if (isset($hotelSystem)) {
    // Log final
    if (method_exists($hotelSystem, 'logger')) {
        $hotelSystem->logger->info("Pagina carregada com sucesso - " . count($activeGuests) . " hospedes ativos");
    }
}

// Resposta AJAX para atualização de estatísticas
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total_guests' => $systemStats['total_guests'],
        'active_guests' => $systemStats['active_guests'],
        'mikrotik_total' => $systemStats['mikrotik_total'],
        'online_users' => $systemStats['online_users'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
?>