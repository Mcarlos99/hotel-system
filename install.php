<?php
// install.php - Script de instalação e configuração inicial

// Verificar se a instalação já foi feita
if (file_exists('config.php') && !isset($_GET['force'])) {
    die("Sistema já instalado! Para reinstalar, acesse: install.php?force=1");
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Verificar requisitos do sistema
            if (checkSystemRequirements()) {
                header('Location: install.php?step=2');
                exit;
            }
            break;
            
        case 2:
            // Configurar banco de dados
            $dbResult = setupDatabase($_POST);
            if ($dbResult['success']) {
                $_SESSION['db_config'] = $_POST;
                header('Location: install.php?step=3');
                exit;
            } else {
                $errors = $dbResult['errors'];
            }
            break;
            
        case 3:
            // Configurar MikroTik
            $mikrotikResult = testMikroTikConnection($_POST);
            if ($mikrotikResult['success']) {
                $_SESSION['mikrotik_config'] = $_POST;
                header('Location: install.php?step=4');
                exit;
            } else {
                $errors = $mikrotikResult['errors'];
            }
            break;
            
        case 4:
            // Finalizar instalação
            $finalResult = finalizeInstallation($_POST);
            if ($finalResult['success']) {
                header('Location: install.php?step=5');
                exit;
            } else {
                $errors = $finalResult['errors'];
            }
            break;
    }
}

function checkSystemRequirements() {
    global $errors;
    
    // Verificar versão do PHP
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $errors[] = "PHP 7.4 ou superior é necessário. Versão atual: " . PHP_VERSION;
    }
    
    // Verificar extensões necessárias
    $requiredExtensions = ['pdo', 'pdo_mysql', 'sockets', 'json', 'mbstring'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Extensão PHP '{$ext}' não encontrada";
        }
    }
    
    // Verificar permissões de escrita
    $writableDirs = ['logs', 'backups', 'reports', 'uploads'];
    foreach ($writableDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_writable($dir)) {
            $errors[] = "Diretório '{$dir}' não tem permissão de escrita";
        }
    }
    
    return empty($errors);
}

function setupDatabase($config) {
    try {
        $dsn = "mysql:host={$config['db_host']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_username'], $config['db_password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Criar banco de dados se não existir
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Conectar ao banco criado
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_username'], $config['db_password']);
        
        // Criar tabelas
        $sql = "
        CREATE TABLE IF NOT EXISTS hotel_guests (
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
            INDEX idx_dates (checkin_date, checkout_date)
        );
        
        CREATE TABLE IF NOT EXISTS access_logs (
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
        );
        
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        ";
        
        $pdo->exec($sql);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'errors' => ['Erro no banco de dados: ' . $e->getMessage()]
        ];
    }
}

function testMikroTikConnection($config) {
    try {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new Exception("Não foi possível criar socket");
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 5, "usec" => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 5, "usec" => 0));
        
        if (!socket_connect($socket, $config['mikrotik_host'], intval($config['mikrotik_port']))) {
            throw new Exception("Não foi possível conectar ao MikroTik em {$config['mikrotik_host']}:{$config['mikrotik_port']}");
        }
        
        socket_close($socket);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'errors' => ['Erro de conexão com MikroTik: ' . $e->getMessage()]
        ];
    }
}

function finalizeInstallation($config) {
    try {
        session_start();
        
        $mikrotikConfig = $_SESSION['mikrotik_config'] ?? [];
        $dbConfig = $_SESSION['db_config'] ?? [];
        
        // Gerar arquivo de configuração
        $configContent = "<?php\n";
        $configContent .= "// Configuração gerada automaticamente em " . date('Y-m-d H:i:s') . "\n\n";
        
        // Configuração do MikroTik
        $configContent .= "\$mikrotikConfig = [\n";
        $configContent .= "    'host' => '{$mikrotikConfig['mikrotik_host']}',\n";
        $configContent .= "    'username' => '{$mikrotikConfig['mikrotik_username']}',\n";
        $configContent .= "    'password' => '{$mikrotikConfig['mikrotik_password']}',\n";
        $configContent .= "    'port' => {$mikrotikConfig['mikrotik_port']}\n";
        $configContent .= "];\n\n";
        
        // Configuração do banco
        $configContent .= "\$dbConfig = [\n";
        $configContent .= "    'host' => '{$dbConfig['db_host']}',\n";
        $configContent .= "    'database' => '{$dbConfig['db_name']}',\n";
        $configContent .= "    'username' => '{$dbConfig['db_username']}',\n";
        $configContent .= "    'password' => '{$dbConfig['db_password']}',\n";
        $configContent .= "    'charset' => 'utf8mb4'\n";
        $configContent .= "];\n\n";
        
        // Configurações do sistema
        $configContent .= "\$systemConfig = [\n";
        $configContent .= "    'hotel_name' => '{$config['hotel_name']}',\n";
        $configContent .= "    'default_profile' => 'hotel-guest',\n";
        $configContent .= "    'max_concurrent_sessions' => 3,\n";
        $configContent .= "    'default_bandwidth' => '10M/2M',\n";
        $configContent .= "    'password_length' => 8,\n";
        $configContent .= "    'auto_cleanup' => true,\n";
        $configContent .= "    'timezone' => '{$config['timezone']}'\n";
        $configContent .= "];\n\n";
        
        // Perfis de usuário
        $configContent .= "\$userProfiles = [\n";
        $configContent .= "    'hotel-guest' => [\n";
        $configContent .= "        'name' => 'Hóspede Padrão',\n";
        $configContent .= "        'rate_limit' => '10M/2M',\n";
        $configContent .= "        'session_timeout' => '24:00:00',\n";
        $configContent .= "        'idle_timeout' => '00:30:00',\n";
        $configContent .= "        'shared_users' => 3\n";
        $configContent .= "    ],\n";
        $configContent .= "    'hotel-vip' => [\n";
        $configContent .= "        'name' => 'Hóspede VIP',\n";
        $configContent .= "        'rate_limit' => '50M/10M',\n";
        $configContent .= "        'session_timeout' => '24:00:00',\n";
        $configContent .= "        'idle_timeout' => '01:00:00',\n";
        $configContent .= "        'shared_users' => 5\n";
        $configContent .= "    ]\n";
        $configContent .= "];\n\n";
        
        // Configuração de logging
        $configContent .= "\$logConfig = [\n";
        $configContent .= "    'enable_logging' => true,\n";
        $configContent .= "    'log_file' => 'logs/hotel_system.log',\n";
        $configContent .= "    'log_level' => 'INFO',\n";
        $configContent .= "    'max_log_size' => 10485760,\n";
        $configContent .= "    'backup_logs' => true\n";
        $configContent .= "];\n\n";
        
        $configContent .= "date_default_timezone_set(\$systemConfig['timezone']);\n";
        $configContent .= "?>";
        
        // Salvar arquivo de configuração
        if (file_put_contents('config.php', $configContent) === false) {
            throw new Exception("Não foi possível criar o arquivo config.php");
        }
        
        // Criar arquivo .htaccess para segurança
        $htaccessContent = "# Proteger arquivos de configuração\n";
        $htaccessContent .= "<Files \"config.php\">\n";
        $htaccessContent .= "    Require all denied\n";
        $htaccessContent .= "</Files>\n\n";
        $htaccessContent .= "<Files \"install.php\">\n";
        $htaccessContent .= "    Require all denied\n";
        $htaccessContent .= "</Files>\n\n";
        $htaccessContent .= "# Proteger logs\n";
        $htaccessContent .= "<Directory \"logs\">\n";
        $htaccessContent .= "    Require all denied\n";
        $htaccessContent .= "</Directory>\n";
        
        file_put_contents('.htaccess', $htaccessContent);
        
        // Limpar sessão
        session_destroy();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'errors' => ['Erro na instalação: ' . $e->getMessage()]
        ];
    }
}

session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Sistema Hotspot Hotel</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .progress-bar {
            height: 4px;
            background: rgba(255,255,255,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #27ae60;
            width: <?php echo ($step / 5) * 100; ?>%;
            transition: width 0.3s ease;
        }
        
        .content {
            padding: 40px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .step-current {
            background: #3498db;
            color: white;
        }
        
        .step-completed {
            background: #27ae60;
            color: white;
        }
        
        .step-pending {
            background: #ecf0f1;
            color: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="number"],
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
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn-success:hover {
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .requirement-list {
            list-style: none;
            padding: 0;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
        
        .requirement-status {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .status-ok {
            background: #27ae60;
            color: white;
        }
        
        .status-error {
            background: #e74c3c;
            color: white;
        }
        
        .help-text {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .success-message {
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            font-size: 4em;
            color: #27ae60;
            margin-bottom: 20px;
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="header">
            <h1>🏨 Sistema Hotspot Hotel</h1>
            <p>Instalação e Configuração</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        
        <div class="content">
            <div class="step-indicator">
                <div class="step-item <?php echo $step >= 1 ? ($step == 1 ? 'step-current' : 'step-completed') : 'step-pending'; ?>">
                    <span>1</span> Requisitos
                </div>
                <div class="step-item <?php echo $step >= 2 ? ($step == 2 ? 'step-current' : 'step-completed') : 'step-pending'; ?>">
                    <span>2</span> Banco
                </div>
                <div class="step-item <?php echo $step >= 3 ? ($step == 3 ? 'step-current' : 'step-completed') : 'step-pending'; ?>">
                    <span>3</span> MikroTik
                </div>
                <div class="step-item <?php echo $step >= 4 ? ($step == 4 ? 'step-current' : 'step-completed') : 'step-pending'; ?>">
                    <span>4</span> Sistema
                </div>
                <div class="step-item <?php echo $step >= 5 ? 'step-completed' : 'step-pending'; ?>">
                    <span>5</span> Concluído
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Erro:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success[0]); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <h2>Verificação de Requisitos</h2>
                <p>Verificando se o sistema atende aos requisitos mínimos...</p>
                
                <ul class="requirement-list">
                    <li class="requirement-item">
                        <div class="requirement-status <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'status-ok' : 'status-error'; ?>">
                            <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? '✓' : '✗'; ?>
                        </div>
                        <div>
                            <strong>PHP 7.4+</strong> (Atual: <?php echo PHP_VERSION; ?>)
                            <div class="help-text">Versão mínima necessária para o sistema</div>
                        </div>
                    </li>
                    
                    <?php
                    $extensions = ['pdo', 'pdo_mysql', 'sockets', 'json', 'mbstring'];
                    foreach ($extensions as $ext):
                    ?>
                    <li class="requirement-item">
                        <div class="requirement-status <?php echo extension_loaded($ext) ? 'status-ok' : 'status-error'; ?>">
                            <?php echo extension_loaded($ext) ? '✓' : '✗'; ?>
                        </div>
                        <div>
                            <strong>Extensão <?php echo $ext; ?></strong>
                            <div class="help-text">Necessária para funcionamento do sistema</div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    
                    <?php
                    $dirs = ['logs', 'backups', 'reports', 'uploads'];
                    foreach ($dirs as $dir):
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                    ?>
                    <li class="requirement-item">
                        <div class="requirement-status <?php echo is_writable($dir) ? 'status-ok' : 'status-error'; ?>">
                            <?php echo is_writable($dir) ? '✓' : '✗'; ?>
                        </div>
                        <div>
                            <strong>Diretório <?php echo $dir; ?>/</strong>
                            <div class="help-text">Permissão de escrita necessária</div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <form method="POST">
                    <div class="actions">
                        <div></div>
                        <button type="submit" class="btn" <?php echo !empty($errors) ? 'disabled' : ''; ?>>
                            Continuar →
                        </button>
                    </div>
                </form>
                
            <?php elseif ($step == 2): ?>
                <h2>Configuração do Banco de Dados</h2>
                <p>Configure a conexão com o banco de dados MySQL:</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="db_host">Host do Banco:</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                        <div class="help-text">Endereço do servidor MySQL (geralmente localhost)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Nome do Banco:</label>
                        <input type="text" id="db_name" name="db_name" value="hotel_system" required>
                        <div class="help-text">Nome do banco de dados (será criado se não existir)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_username">Usuário:</label>
                        <input type="text" id="db_username" name="db_username" value="root" required>
                        <div class="help-text">Usuário com permissões para criar bancos e tabelas</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_password">Senha:</label>
                        <input type="password" id="db_password" name="db_password">
                        <div class="help-text">Senha do usuário MySQL</div>
                    </div>
                    
                    <div class="actions">
                        <a href="install.php?step=1" class="btn btn-secondary">← Voltar</a>
                        <button type="submit" class="btn">Testar Conexão →</button>
                    </div>
                </form>
                
            <?php elseif ($step == 3): ?>
                <h2>Configuração do MikroTik</h2>
                <p>Configure a conexão com o roteador MikroTik:</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="mikrotik_host">IP do MikroTik:</label>
                        <input type="text" id="mikrotik_host" name="mikrotik_host" value="192.168.1.1" required>
                        <div class="help-text">Endereço IP do roteador MikroTik</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mikrotik_port">Porta da API:</label>
                        <input type="number" id="mikrotik_port" name="mikrotik_port" value="8728" required>
                        <div class="help-text">Porta da API do MikroTik (padrão: 8728)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mikrotik_username">Usuário:</label>
                        <input type="text" id="mikrotik_username" name="mikrotik_username" value="admin" required>
                        <div class="help-text">Usuário com permissões administrativas</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mikrotik_password">Senha:</label>
                        <input type="password" id="mikrotik_password" name="mikrotik_password" required>
                        <div class="help-text">Senha do usuário administrativo</div>
                    </div>
                    
                    <div class="actions">
                        <a href="install.php?step=2" class="btn btn-secondary">← Voltar</a>
                        <button type="submit" class="btn">Testar Conexão →</button>
                    </div>
                </form>
                
            <?php elseif ($step == 4): ?>
                <h2>Configuração do Sistema</h2>
                <p>Configure as informações básicas do sistema:</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="hotel_name">Nome do Hotel:</label>
                        <input type="text" id="hotel_name" name="hotel_name" value="Hotel Paradise" required>
                        <div class="help-text">Nome que aparecerá no sistema</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="timezone">Fuso Horário:</label>
                        <select id="timezone" name="timezone" required>
                            <option value="America/Sao_Paulo">América/São Paulo (UTC-3)</option>
                            <option value="America/New_York">América/Nova York (UTC-5)</option>
                            <option value="Europe/London">Europa/Londres (UTC+0)</option>
                            <option value="Asia/Tokyo">Ásia/Tóquio (UTC+9)</option>
                        </select>
                        <div class="help-text">Fuso horário do sistema</div>
                    </div>
                    
                    <div class="actions">
                        <a href="install.php?step=3" class="btn btn-secondary">← Voltar</a>
                        <button type="submit" class="btn">Finalizar Instalação →</button>
                    </div>
                </form>
                
            <?php elseif ($step == 5): ?>
                <div class="success-message">
                    <div class="success-icon">🎉</div>
                    <h2>Instalação Concluída!</h2>
                    <p>O sistema foi instalado com sucesso. Você pode acessar o painel administrativo agora.</p>
                    
                    <div style="margin-top: 30px;">
                        <a href="index.php" class="btn btn-success">Acessar Sistema →</a>
                    </div>
                    
                    <div class="alert alert-success" style="margin-top: 30px; text-align: left;">
                        <strong>Próximos passos:</strong>
                        <ul style="margin-top: 10px; padding-left: 20px;">
                            <li>Configure os perfis de usuário no MikroTik</li>
                            <li>Teste a criação de usuários</li>
                            <li>Configure um cron job para limpeza automática</li>
                            <li>Faça backup das configurações</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>