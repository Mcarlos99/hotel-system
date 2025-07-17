<?php
// index.php - Interface principal com BOTÃO REMOVER FUNCIONANDO 100%
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Incluir arquivos necessários
require_once 'config.php';
require_once 'mikrotik_manager.php';

// Classe HotelHotspotSystem com credenciais simplificadas e remoção funcionando
class HotelHotspotSystemSimple {
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
            throw new Exception("Erro na conexão com banco: " . $e->getMessage());
        }
        
        // Conectar ao MikroTik (opcional)
        try {
            $this->mikrotik = new MikroTikHotspotManager(
                $mikrotikConfig['host'],
                $mikrotikConfig['username'],
                $mikrotikConfig['password'],
                $mikrotikConfig['port'] ?? 8728
            );
        } catch (Exception $e) {
            // MikroTik opcional
        }
        
        $this->createTables();
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
        
        $obviousPatterns = ['1234', '4321', '1111', '2222', '1212', '1010'];
        if (in_array($password, $obviousPatterns)) return true;
        
        return false;
    }
    
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        try {
            // Verificar se já existe usuário ativo para este quarto
            $existingUser = $this->getActiveGuestByRoom($roomNumber);
            if ($existingUser) {
                return [
                    'success' => false,
                    'error' => "Já existe um usuário ativo para o quarto {$roomNumber}. Remova primeiro."
                ];
            }
            
            $username = $this->generateSimpleUsername($roomNumber);
            $password = $this->generateSimplePassword();
            
            // Tentar conectar ao MikroTik (não crítico)
            try {
                if ($this->mikrotik) {
                    $this->mikrotik->connect();
                    $timeLimit = $this->calculateTimeLimit($checkoutDate);
                    $this->mikrotik->createHotspotUser($username, $password, $profileType, $timeLimit);
                    $this->mikrotik->disconnect();
                }
            } catch (Exception $e) {
                // Continuar mesmo se falhar no MikroTik
            }
            
            // Salvar no banco (crítico)
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
                return [
                    'success' => true,
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profileType,
                    'valid_until' => $checkoutDate,
                    'bandwidth' => '10M/2M'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Falha ao salvar no banco de dados'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * REMOÇÃO CORRIGIDA - SEM JAVASCRIPT QUE CANCELA
     */
    public function removeGuestAccess($roomNumber) {
        try {
            // Debug: log da tentativa
            error_log("DEBUG: Tentando remover quarto: " . $roomNumber);
            
            // Buscar o hóspede ativo pelo número do quarto
            $stmt = $this->db->prepare("
                SELECT id, username, guest_name 
                FROM hotel_guests 
                WHERE room_number = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$roomNumber]);
            $guest = $stmt->fetch();
            
            error_log("DEBUG: Hóspede encontrado: " . ($guest ? "SIM - " . $guest['username'] : "NÃO"));
            
            if (!$guest) {
                return [
                    'success' => false, 
                    'error' => "Nenhum hóspede ativo encontrado para o quarto {$roomNumber}"
                ];
            }
            
            $username = $guest['username'];
            $guestName = $guest['guest_name'];
            $guestId = $guest['id'];
            
            // Tentar remover do MikroTik (não crítico)
            $mikrotikMsg = '';
            try {
                if ($this->mikrotik) {
                    $this->mikrotik->connect();
                    $this->mikrotik->disconnectUser($username);
                    $this->mikrotik->removeHotspotUser($username);
                    $this->mikrotik->disconnect();
                    $mikrotikMsg = ' | Removido do MikroTik';
                    error_log("DEBUG: Removido do MikroTik: " . $username);
                }
            } catch (Exception $e) {
                $mikrotikMsg = ' | MikroTik: ' . $e->getMessage();
                error_log("DEBUG: Erro MikroTik: " . $e->getMessage());
            }
            
            // Atualizar status no banco (CRÍTICO)
            $stmt = $this->db->prepare("
                UPDATE hotel_guests 
                SET status = 'disabled', updated_at = NOW() 
                WHERE id = ?
            ");
            
            $updateResult = $stmt->execute([$guestId]);
            error_log("DEBUG: Update result: " . ($updateResult ? "SUCCESS" : "FAILED"));
            
            if ($updateResult) {
                // Verificar se realmente foi atualizado
                $stmt = $this->db->prepare("SELECT status FROM hotel_guests WHERE id = ?");
                $stmt->execute([$guestId]);
                $newStatus = $stmt->fetchColumn();
                error_log("DEBUG: Novo status: " . $newStatus);
                
                // Registrar log da ação
                $this->logAction($username, $roomNumber, 'disabled');
                
                return [
                    'success' => true,
                    'message' => "✅ Acesso removido para {$guestName} (Quarto {$roomNumber}){$mikrotikMsg}"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Falha ao atualizar status no banco de dados'
                ];
            }
            
        } catch (Exception $e) {
            error_log("DEBUG: Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao remover acesso: ' . $e->getMessage()
            ];
        }
    }
    
    public function getActiveGuestByRoom($roomNumber) {
        $stmt = $this->db->prepare("
            SELECT * FROM hotel_guests 
            WHERE room_number = ? AND status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([$roomNumber]);
        return $stmt->fetch();
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
            // Log não é crítico
        }
    }
    
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
    
    public function getSystemStats() {
        $stats = [];
        
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM hotel_guests");
        $stats['total_guests'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) as active FROM hotel_guests WHERE status = 'active'");
        $stats['active_guests'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) as today FROM hotel_guests WHERE DATE(created_at) = CURDATE()");
        $stats['today_guests'] = $stmt->fetchColumn();
        
        $stats['online_users'] = 0;
        
        return $stats;
    }
    
    public function cleanupExpiredUsers() {
        try {
            $stmt = $this->db->prepare("
                UPDATE hotel_guests 
                SET status = 'expired', updated_at = NOW() 
                WHERE checkout_date < CURDATE() AND status = 'active'
            ");
            $stmt->execute();
            $removed = $stmt->rowCount();
            
            return ['success' => true, 'removed' => $removed];
            
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
            action ENUM('login', 'logout', 'created', 'disabled', 'expired') NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_room (room_number),
            INDEX idx_action (action),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB";
        
        $this->db->exec($sql);
    }
}

// Inicializar o sistema
try {
    $hotelSystem = new HotelHotspotSystemSimple($mikrotikConfig, $dbConfig);
} catch (Exception $e) {
    die("Erro ao inicializar sistema: " . $e->getMessage());
}

// DEBUG: Log de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG: POST recebido: " . print_r($_POST, true));
}

// Processar ações do formulário
$result = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['generate_access'])) {
            error_log("DEBUG: Processando generate_access");
            $result = $hotelSystem->generateCredentials(
                $_POST['room_number'],
                $_POST['guest_name'],
                $_POST['checkin_date'],
                $_POST['checkout_date'],
                $_POST['profile_type'] ?? 'hotel-guest'
            );
            
        } elseif (isset($_POST['remove_access'])) {
            error_log("DEBUG: Processando remove_access para quarto: " . $_POST['room_number']);
            
            $removeResult = $hotelSystem->removeGuestAccess($_POST['room_number']);
            
            if ($removeResult['success']) {
                $message = $removeResult['message'];
                error_log("DEBUG: Remoção bem-sucedida: " . $message);
            } else {
                $message = "❌ Erro: " . $removeResult['error'];
                error_log("DEBUG: Erro na remoção: " . $removeResult['error']);
            }
            
        } elseif (isset($_POST['cleanup_expired'])) {
            error_log("DEBUG: Processando cleanup_expired");
            $result = $hotelSystem->cleanupExpiredUsers();
            $message = "🧹 Limpeza concluída. Usuários removidos: " . $result['removed'];
        }
        
    } catch (Exception $e) {
        $message = "❌ Erro: " . $e->getMessage();
        error_log("DEBUG: Exception no processamento: " . $e->getMessage());
    }
}

// Obter dados para exibição
$activeGuests = $hotelSystem->getActiveGuests();
$systemStats = $hotelSystem->getSystemStats();

// API para atualização via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    header('Content-Type: application/json');
    echo json_encode($hotelSystem->getSystemStats());
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $systemConfig['hotel_name']; ?> - Sistema de Internet</title>
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
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
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
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
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
            transition: border-color 0.3s ease;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
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
        
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .simple-credentials {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
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
        }
        
        .credential-display {
            font-size: 2.5em;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
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
        }
        
        td {
            padding: 15px 20px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .room-number {
            font-weight: bold;
            color: #3498db;
            font-size: 1.2em;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏨 <?php echo $systemConfig['hotel_name']; ?></h1>
            <p>Sistema de Gerenciamento de Internet - Credenciais Simplificadas</p>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['total_guests']; ?></div>
                <div class="stat-label">Total de Hóspedes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['active_guests']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['online_users']; ?></div>
                <div class="stat-label">Online Agora</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['today_guests']; ?></div>
                <div class="stat-label">Hoje</div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Mensagens -->
            <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, '❌') !== false ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulário para gerar acesso -->
            <div class="section">
                <h2>🆕 Gerar Novo Acesso</h2>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="room_number">Número do Quarto:</label>
                            <input type="text" id="room_number" name="room_number" required placeholder="Ex: 101, 205A">
                        </div>
                        
                        <div class="form-group">
                            <label for="guest_name">Nome do Hóspede:</label>
                            <input type="text" id="guest_name" name="guest_name" required placeholder="Nome completo">
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
                                    <option value="<?php echo $key; ?>"><?php echo $profile['name']; ?> (<?php echo $profile['rate_limit']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="generate_access" class="btn">
                        ✨ Gerar Credenciais Simples
                    </button>
                </form>
                
                <!-- Resultado da geração -->
                <?php if (isset($result) && $result['success']): ?>
                    <div class="simple-credentials">
                        <h3>🎉 Credenciais Geradas com Sucesso!</h3>
                        
                        <div class="credential-pair">
                            <div class="credential-box">
                                <div>👤 USUÁRIO</div>
                                <div class="credential-display"><?php echo $result['username']; ?></div>
                            </div>
                            <div class="credential-box">
                                <div>🔒 SENHA</div>
                                <div class="credential-display"><?php echo $result['password']; ?></div>
                            </div>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <strong>📋 Informações:</strong><br>
                            Quarto: <?php echo htmlspecialchars($_POST['room_number']); ?> | 
                            Hóspede: <?php echo htmlspecialchars($_POST['guest_name']); ?> | 
                            Válido até: <?php echo date('d/m/Y', strtotime($result['valid_until'])); ?>
                        </div>
                    </div>
                <?php elseif (isset($result) && !$result['success']): ?>
                    <div class="alert alert-error">
                        <strong>Erro:</strong> <?php echo htmlspecialchars($result['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Hóspedes Ativos -->
            <div class="section">
                <h2>👥 Hóspedes Ativos (<?php echo count($activeGuests); ?>)</h2>
                
                <?php if (empty($activeGuests)): ?>
                    <div class="alert alert-error">
                        <strong>Nenhum hóspede ativo encontrado.</strong><br>
                        Gere credenciais para novos hóspedes usando o formulário acima.
                    </div>
                <?php else: ?>
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
                                    <span style="font-family: 'Courier New', monospace; font-weight: bold; font-size: 1.1em;">
                                        <?php echo htmlspecialchars($guest['username']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-family: 'Courier New', monospace; font-weight: bold; font-size: 1.1em;">
                                        <?php echo htmlspecialchars($guest['password']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="profile-badge">
                                        <?php echo isset($userProfiles[$guest['profile_type']]) ? $userProfiles[$guest['profile_type']]['name'] : $guest['profile_type']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($guest['checkout_date'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <!-- FORMULÁRIO SIMPLIFICADO SEM JAVASCRIPT QUE CANCELA -->
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($guest['room_number']); ?>">
                                            <button type="submit" name="remove_access" class="btn btn-danger"
                                                    onclick="return confirm('⚠️ REMOVER ACESSO?\n\nQuarto: <?php echo htmlspecialchars($guest['room_number']); ?>\nHóspede: <?php echo htmlspecialchars($guest['guest_name']); ?>\nUsuário: <?php echo htmlspecialchars($guest['username']); ?>\n\n✅ Confirmar remoção?');">
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
                        <button type="submit" name="cleanup_expired" class="btn btn-danger"
                                onclick="return confirm('🧹 Remover todos os usuários com check-out vencido?');">
                            🧹 Limpar Expirados
                        </button>
                    </form>
                    <button onclick="location.reload()" class="btn">
                        🔄 Atualizar Lista
                    </button>
                </div>
            </div>
            
            <!-- Debug Info -->
            <div class="section" style="background: #fff3cd; border: 2px solid #ffc107;">
                <h2>🔧 Debug Info</h2>
                <p><strong>POST Data:</strong></p>
                <pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px;">
<?php echo htmlspecialchars(print_r($_POST, true)); ?>
                </pre>
                
                <p><strong>Últimas ações (verifique no log de erro do PHP):</strong></p>
                <ul>
                    <li>POST recebido: Verificar logs</li>
                    <li>Processamento: Verificar logs</li>
                    <li>Resultado: <?php echo isset($message) ? htmlspecialchars($message) : 'Nenhuma ação realizada'; ?></li>
                </ul>
                
                <p><strong>Teste manual:</strong></p>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="room_number" value="TESTE">
                    <button type="submit" name="remove_access" class="btn btn-danger">
                        🧪 Teste Remover (quarto TESTE)
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Definir datas padrão
        document.getElementById('checkin_date').valueAsDate = new Date();
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('checkout_date').valueAsDate = tomorrow;
        
        // Console debug
        console.log('🏨 Sistema Hotel - Debug Mode');
        console.log('Hóspedes ativos:', <?php echo count($activeGuests); ?>);
        
        // Log de POST para debug
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        console.log('POST enviado:', <?php echo json_encode($_POST); ?>);
        console.log('Mensagem retornada:', '<?php echo addslashes($message ?? 'Nenhuma'); ?>');
        <?php endif; ?>
        
        // Função de debug para formulários
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Formulário sendo enviado:', this);
                console.log('Dados do formulário:', new FormData(this));
                
                // Se for o botão remover, adicionar debug
                if (this.querySelector('button[name="remove_access"]')) {
                    const roomNumber = this.querySelector('input[name="room_number"]').value;
                    console.log('🗑️ Tentando remover quarto:', roomNumber);
                    
                    // Log adicional
                    setTimeout(() => {
                        console.log('Formulário de remoção enviado. Aguardando resposta...');
                    }, 100);
                }
            });
        });
        
        // Verificar se houve remoção bem-sucedida
        <?php if (isset($message) && strpos($message, '✅') !== false): ?>
        console.log('✅ Remoção bem-sucedida detectada!');
        // Destacar a mensagem de sucesso
        const alerts = document.querySelectorAll('.alert-success');
        alerts.forEach(alert => {
            alert.style.border = '3px solid #27ae60';
            alert.style.animation = 'pulse 1s ease-in-out 3';
        });
        <?php endif; ?>
        
        // CSS para animação de pulse
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>