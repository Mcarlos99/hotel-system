<?php
// index.php - Interface principal com credenciais simplificadas
session_start();

// Incluir arquivos necessários
require_once 'config.php';
require_once 'mikrotik_manager.php';

// Inicializar o sistema
try {
    $hotelSystem = new HotelHotspotSystem($mikrotikConfig, $dbConfig);
} catch (Exception $e) {
    die("Erro ao inicializar sistema: " . $e->getMessage());
}

// Processar ações do formulário
$result = null;
$message = null;

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
            $result = $hotelSystem->removeGuestAccess($_POST['room_number']);
            $message = $result['success'] ? "Acesso removido com sucesso!" : "Erro: " . $result['error'];
            
        } elseif (isset($_POST['cleanup_expired'])) {
            $result = $hotelSystem->cleanupExpiredUsers();
            $message = "Limpeza concluída. Usuários removidos: " . $result['removed'];
        }
        
    } catch (Exception $e) {
        $message = "Erro: " . $e->getMessage();
    }
}

// Obter dados para exibição
$activeGuests = $hotelSystem->getActiveGuests();
$systemStats = $hotelSystem->getSystemStats();

// Função auxiliar para formatar bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// API para atualização via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    header('Content-Type: application/json');
    echo json_encode($hotelSystem->getSystemStats());
    exit;
}

// Classe estendida com credenciais simplificadas
class HotelHotspotSystemSimple extends HotelHotspotSystem {
    
    /**
     * Gera usuário simples e memorável
     * Formato: quarto + 2-3 números aleatórios
     * Exemplo: 101-45, 205-123
     */
    protected function generateSimpleUsername($roomNumber) {
        // Limpar número do quarto (apenas números)
        $cleanRoom = preg_replace('/[^0-9]/', '', $roomNumber);
        
        // Gerar 2-3 números aleatórios
        $randomLength = rand(2, 3);
        $randomNumbers = '';
        
        for ($i = 0; $i < $randomLength; $i++) {
            $randomNumbers .= rand(0, 9);
        }
        
        $baseUsername = $cleanRoom . '-' . $randomNumbers;
        
        // Verificar se já existe, se sim, tentar novamente
        $attempts = 0;
        while ($this->usernameExists($baseUsername) && $attempts < 10) {
            $randomNumbers = '';
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
     * Evita sequências obvias como 123, 111, etc.
     */
    protected function generateSimplePassword() {
        $length = rand(3, 4);
        $password = '';
        
        // Gerar senha evitando padrões óbvios
        $attempts = 0;
        do {
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= rand(1, 9); // Evitar 0 no início
            }
            $attempts++;
        } while ($this->isObviousPassword($password) && $attempts < 20);
        
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
        
        // Evitar números repetidos
        if (preg_match('/111|222|333|444|555|666|777|888|999/', $password)) {
            return true;
        }
        
        // Evitar padrões simples
        $obviousPatterns = ['1234', '4321', '1111', '2222', '1212', '1010'];
        if (in_array($password, $obviousPatterns)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Sobrescreve o método principal para usar credenciais simples
     */
    public function generateCredentials($roomNumber, $guestName, $checkinDate, $checkoutDate, $profileType = 'hotel-guest') {
        if ($this->logger) {
            $this->logger->info("Gerando credenciais simplificadas", [
                'room' => $roomNumber,
                'guest' => $guestName,
                'profile' => $profileType
            ]);
        }
        
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
            
            if ($this->logger) {
                $this->logger->info("Credenciais geradas com sucesso", [
                    'username' => $username,
                    'room' => $roomNumber
                ]);
            }
            
            return [
                'success' => true,
                'username' => $username,
                'password' => $password,
                'profile' => $profileType,
                'valid_until' => $checkoutDate,
                'bandwidth' => '10M/2M'
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Erro ao gerar credenciais", [
                    'error' => $e->getMessage(),
                    'room' => $roomNumber
                ]);
            }
            
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
}

// Usar a versão simplificada
$hotelSystem = new HotelHotspotSystemSimple($mikrotikConfig, $dbConfig);
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
        
        .header .subtitle {
            font-size: 1.2em;
            opacity: 0.9;
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
        
        .credentials-card {
            background: linear-gradient(135deg, #f39c12 0%, #d68910 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        
        .credentials-card h3 {
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .credential-value {
            font-family: 'Courier New', monospace;
            font-size: 2em;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: 2px;
        }
        
        .credential-value:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
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
        
        .simple-credentials h3 {
            font-size: 1.6em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            transition: all 0.3s ease;
        }
        
        .credential-box:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-3px);
        }
        
        .credential-label {
            font-size: 1em;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .credential-display {
            font-size: 2.5em;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .credential-display:hover {
            transform: scale(1.1);
        }
        
        .easy-note {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 0.95em;
            opacity: 0.9;
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
        
        .warning-message {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #ffeaa7;
        }
        
        .copy-indicator {
            position: absolute;
            background: #27ae60;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .tips-section {
            background: linear-gradient(135deg, #e8f5e1 0%, #f0f8f0 100%);
            border: 2px solid #27ae60;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .tips-section h4 {
            color: #27ae60;
            margin-bottom: 15px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tips-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .tip-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95em;
            color: #2d5a27;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions {
                flex-direction: column;
            }
            
            .credential-pair {
                grid-template-columns: 1fr;
            }
            
            .credential-display {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏨 <?php echo $systemConfig['hotel_name']; ?></h1>
            <p class="subtitle">Sistema de Gerenciamento de Internet - Credenciais Simplificadas</p>
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
                <div class="alert <?php echo strpos($message, 'Erro') === 0 ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Dicas sobre credenciais simplificadas -->
            <div class="tips-section">
                <h4>💡 Credenciais Simplificadas para Hóspedes</h4>
                <div class="tips-list">
                    <div class="tip-item">✅ Usuário: Número do quarto + 2-3 dígitos (ex: 101-45)</div>
                    <div class="tip-item">✅ Senha: Apenas 3-4 números simples (ex: 247)</div>
                    <div class="tip-item">✅ Fácil de memorizar e digitar</div>
                    <div class="tip-item">✅ Evita sequências óbvias (123, 111, etc.)</div>
                    <div class="tip-item">✅ Ideal para impressão em cartões</div>
                    <div class="tip-item">✅ Funciona em qualquer dispositivo</div>
                </div>
            </div>
            
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
                            <div class="credential-box" onclick="copyToClipboard('<?php echo $result['username']; ?>')">
                                <div class="credential-label">👤 USUÁRIO</div>
                                <div class="credential-display"><?php echo $result['username']; ?></div>
                            </div>
                            <div class="credential-box" onclick="copyToClipboard('<?php echo $result['password']; ?>')">
                                <div class="credential-label">🔒 SENHA</div>
                                <div class="credential-display"><?php echo $result['password']; ?></div>
                            </div>
                        </div>
                        
                        <div class="easy-note">
                            <strong>📋 Informações Adicionais:</strong><br>
                            Quarto: <?php echo htmlspecialchars($_POST['room_number']); ?> | 
                            Hóspede: <?php echo htmlspecialchars($_POST['guest_name']); ?> | 
                            Válido até: <?php echo date('d/m/Y', strtotime($result['valid_until'])); ?> |
                            Perfil: <?php echo $result['profile']; ?>
                        </div>
                        
                        <?php if (isset($result['warning'])): ?>
                            <div class="warning-message">
                                <strong>⚠️ Atenção:</strong> <?php echo $result['warning']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 20px;">
                            <button onclick="printCredentials()" class="btn btn-success">
                                🖨️ Imprimir Cartão de Acesso
                            </button>
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
                <h2>👥 Hóspedes Ativos</h2>
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
                                <span style="font-family: 'Courier New', monospace; font-weight: bold; font-size: 1.1em; cursor: pointer;" 
                                      onclick="copyToClipboard('<?php echo $guest['username']; ?>')">
                                    <?php echo htmlspecialchars($guest['username']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-family: 'Courier New', monospace; font-weight: bold; font-size: 1.1em; cursor: pointer;" 
                                      onclick="copyToClipboard('<?php echo $guest['password']; ?>')">
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
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="room_number" value="<?php echo $guest['room_number']; ?>">
                                        <button type="submit" name="remove_access" class="btn btn-danger" 
                                                onclick="return confirm('Remover acesso do quarto <?php echo $guest['room_number']; ?>?')">
                                            🗑️ Remover
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="actions" style="margin-top: 20px;">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="cleanup_expired" class="btn btn-danger">
                            🧹 Limpar Expirados
                        </button>
                    </form>
                    <button onclick="location.reload()" class="btn">
                        🔄 Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Definir data atual como padrão para check-in
        document.getElementById('checkin_date').valueAsDate = new Date();
        
        // Definir data de amanhã como padrão para check-out
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('checkout_date').valueAsDate = tomorrow;
        
        // Função para copiar texto com feedback visual melhorado
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Criar indicador visual temporário
                showCopyFeedback(event.target, text);
            }).catch(err => {
                // Fallback para navegadores antigos
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showCopyFeedback(event.target, text);
            });
        }
        
        function showCopyFeedback(element, text) {
            // Feedback visual no elemento
            const originalBg = element.style.backgroundColor;
            const originalTransform = element.style.transform;
            
            element.style.backgroundColor = 'rgba(39, 174, 96, 0.8)';
            element.style.transform = 'scale(1.05)';
            
            // Criar tooltip temporário
            const tooltip = document.createElement('div');
            tooltip.textContent = 'Copiado!';
            tooltip.style.cssText = `
                position: fixed;
                background: #27ae60;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 14px;
                font-weight: bold;
                z-index: 1000;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.3s ease;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            `;
            
            // Posicionar tooltip
            const rect = element.getBoundingClientRect();
            tooltip.style.left = (rect.left + rect.width/2 - 30) + 'px';
            tooltip.style.top = (rect.top - 40) + 'px';
            
            document.body.appendChild(tooltip);
            
            // Animar tooltip
            setTimeout(() => {
                tooltip.style.opacity = '1';
            }, 10);
            
            // Restaurar elemento e remover tooltip
            setTimeout(() => {
                element.style.backgroundColor = originalBg;
                element.style.transform = originalTransform;
                
                tooltip.style.opacity = '0';
                setTimeout(() => {
                    if (tooltip.parentNode) {
                        document.body.removeChild(tooltip);
                    }
                }, 300);
            }, 800);
            
            console.log('Texto copiado:', text);
        }
        
        // Função para imprimir cartão de acesso
        function printCredentials() {
            const username = "<?php echo isset($result['username']) ? $result['username'] : ''; ?>";
            const password = "<?php echo isset($result['password']) ? $result['password'] : ''; ?>";
            const roomNumber = "<?php echo isset($_POST['room_number']) ? $_POST['room_number'] : ''; ?>";
            const guestName = "<?php echo isset($_POST['guest_name']) ? $_POST['guest_name'] : ''; ?>";
            const validUntil = "<?php echo isset($result['valid_until']) ? date('d/m/Y', strtotime($result['valid_until'])) : ''; ?>";
            
            const printContent = `
                <html>
                <head>
                    <title>Cartão de Acesso WiFi</title>
                    <style>
                        @media print {
                            @page { 
                                size: 10cm 6cm; 
                                margin: 0.5cm; 
                            }
                        }
                        
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 20px;
                            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                        }
                        
                        .card {
                            width: 8cm;
                            height: 5cm;
                            background: linear-gradient(135deg, #27ae60, #2ecc71);
                            color: white;
                            border-radius: 12px;
                            padding: 15px;
                            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                            position: relative;
                            overflow: hidden;
                        }
                        
                        .card::before {
                            content: '';
                            position: absolute;
                            top: -50%;
                            right: -50%;
                            width: 100%;
                            height: 100%;
                            background: rgba(255,255,255,0.1);
                            border-radius: 50%;
                        }
                        
                        .hotel-name {
                            font-size: 16px;
                            font-weight: bold;
                            margin-bottom: 8px;
                            text-align: center;
                        }
                        
                        .wifi-icon {
                            text-align: center;
                            font-size: 20px;
                            margin-bottom: 10px;
                        }
                        
                        .credentials {
                            background: rgba(255,255,255,0.15);
                            padding: 8px;
                            border-radius: 6px;
                            margin: 8px 0;
                            text-align: center;
                        }
                        
                        .credential-label {
                            font-size: 10px;
                            opacity: 0.9;
                            margin-bottom: 2px;
                        }
                        
                        .credential-value {
                            font-size: 18px;
                            font-weight: bold;
                            font-family: 'Courier New', monospace;
                            letter-spacing: 2px;
                        }
                        
                        .footer {
                            font-size: 9px;
                            text-align: center;
                            margin-top: 8px;
                            opacity: 0.8;
                        }
                        
                        .guest-info {
                            font-size: 10px;
                            margin-bottom: 5px;
                        }
                    </style>
                </head>
                <body>
                    <div class="card">
                        <div class="hotel-name"><?php echo $systemConfig['hotel_name']; ?></div>
                        <div class="wifi-icon">📶 WiFi Gratuito</div>
                        
                        <div class="guest-info">
                            <strong>Quarto:</strong> ${roomNumber} | <strong>Hóspede:</strong> ${guestName}
                        </div>
                        
                        <div class="credentials">
                            <div class="credential-label">USUÁRIO</div>
                            <div class="credential-value">${username}</div>
                        </div>
                        
                        <div class="credentials">
                            <div class="credential-label">SENHA</div>
                            <div class="credential-value">${password}</div>
                        </div>
                        
                        <div class="footer">
                            Válido até: ${validUntil} | Internet de alta velocidade<br>
                            Suporte: (94) 98170-9809
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                setTimeout(() => {
                    printWindow.close();
                }, 1000);
            }, 500);
        }
        
        // Atualização automática das estatísticas
        setInterval(() => {
            fetch('?ajax=stats')
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.stat-card:nth-child(3) .stat-number').textContent = data.online_users;
                })
                .catch(error => console.log('Erro ao atualizar estatísticas:', error));
        }, 30000); // Atualizar a cada 30 segundos
        
        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const roomNumber = document.getElementById('room_number').value.trim();
            const guestName = document.getElementById('guest_name').value.trim();
            
            if (!roomNumber) {
                alert('Por favor, insira o número do quarto');
                e.preventDefault();
                return false;
            }
            
            if (!guestName) {
                alert('Por favor, insira o nome do hóspede');
                e.preventDefault();
                return false;
            }
            
            // Limpar caracteres especiais do número do quarto para evitar problemas
            const cleanRoom = roomNumber.replace(/[^a-zA-Z0-9]/g, '');
            if (cleanRoom.length === 0) {
                alert('Número do quarto deve conter pelo menos um caractere alfanumérico');
                e.preventDefault();
                return false;
            }
            
            // Feedback visual durante processamento
            const button = e.target.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            button.innerHTML = '🔄 Gerando credenciais...';
            button.disabled = true;
            
            // Restaurar botão após timeout (caso haja erro)
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 10000);
        });
        
        // Adicionar efeitos visuais aos elementos clicáveis
        document.querySelectorAll('[onclick*="copyToClipboard"]').forEach(element => {
            element.style.cursor = 'pointer';
            element.title = 'Clique para copiar';
            
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.transition = 'all 0.2s ease';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
        
        // Animação de entrada para os cartões de credenciais
        const credentialCards = document.querySelectorAll('.simple-credentials, .credentials-card');
        credentialCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100 + (index * 100));
        });
        
        // Destacar campos obrigatórios quando vazios
        document.querySelectorAll('input[required]').forEach(input => {
            input.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.borderColor = '#e74c3c';
                    this.style.boxShadow = '0 0 5px rgba(231, 76, 60, 0.3)';
                } else {
                    this.style.borderColor = '#27ae60';
                    this.style.boxShadow = '0 0 5px rgba(39, 174, 96, 0.3)';
                }
            });
            
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#27ae60';
                    this.style.boxShadow = '0 0 5px rgba(39, 174, 96, 0.3)';
                }
            });
        });
        
        // Função para gerar preview das credenciais
        function previewCredentials() {
            const roomNumber = document.getElementById('room_number').value.trim();
            if (roomNumber) {
                // Simular formato das credenciais que serão geradas
                const cleanRoom = roomNumber.replace(/[^0-9]/g, '');
                const sampleUser = cleanRoom + '-' + Math.floor(Math.random() * 90 + 10);
                const samplePass = Math.floor(Math.random() * 900 + 100);
                
                console.log('Preview das credenciais:');
                console.log('Usuário será algo como:', sampleUser);
                console.log('Senha será algo como:', samplePass);
            }
        }
        
        // Adicionar preview quando o usuário digitar o quarto
        document.getElementById('room_number').addEventListener('input', function() {
            clearTimeout(this.previewTimeout);
            this.previewTimeout = setTimeout(previewCredentials, 1000);
        });
        
        // Mensagem de boas-vindas
        console.log('🏨 Sistema Hotel - Credenciais Simplificadas');
        console.log('✅ Usuários no formato: [quarto]-[2-3 números]');
        console.log('✅ Senhas no formato: [3-4 números simples]');
        console.log('✅ Fácil para o hóspede memorizar e digitar');
    </script>
</body>
</html>