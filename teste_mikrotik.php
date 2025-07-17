<?php
// teste_mikrotik.php - Teste completo de conexão com MikroTik
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configurações se existir
if (file_exists('config.php')) {
    require_once 'config.php';
    $defaultHost = $mikrotikConfig['host'] ?? '192.168.1.1';
    $defaultUser = $mikrotikConfig['username'] ?? 'admin';
    $defaultPass = $mikrotikConfig['password'] ?? '';
    $defaultPort = $mikrotikConfig['port'] ?? 8728;
} else {
    $defaultHost = '192.168.1.1';
    $defaultUser = 'admin';
    $defaultPass = '';
    $defaultPort = 8728;
}

// Processar formulário
$testResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? $defaultHost;
    $username = $_POST['username'] ?? $defaultUser;
    $password = $_POST['password'] ?? $defaultPass;
    $port = (int)($_POST['port'] ?? $defaultPort);
    
    $testResults = performCompleteTest($host, $username, $password, $port);
}

function performCompleteTest($host, $username, $password, $port) {
    $results = [
        'tests' => [],
        'overall' => 'pending',
        'summary' => ''
    ];
    
    // Teste 1: Validação de entrada
    $results['tests']['validation'] = validateInputs($host, $username, $port);
    
    // Teste 2: Teste de ping/conectividade
    $results['tests']['ping'] = testPing($host);
    
    // Teste 3: Teste de porta (socket básico)
    $results['tests']['port'] = testPort($host, $port);
    
    // Teste 4: Teste de extensões PHP
    $results['tests']['extensions'] = testPHPExtensions();
    
    // Teste 5: Teste de API MikroTik
    $results['tests']['api'] = testMikroTikAPI($host, $username, $password, $port);
    
    // Teste 6: Teste de comandos básicos
    if ($results['tests']['api']['status'] === 'success') {
        $results['tests']['commands'] = testBasicCommands($host, $username, $password, $port);
    }
    
    // Determinar resultado geral
    $results['overall'] = calculateOverallResult($results['tests']);
    $results['summary'] = generateSummary($results['tests']);
    
    return $results;
}

function validateInputs($host, $username, $port) {
    $test = [
        'name' => 'Validação de Parâmetros',
        'status' => 'success',
        'message' => 'Todos os parâmetros são válidos',
        'details' => []
    ];
    
    // Validar IP
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        $test['status'] = 'warning';
        $test['details'][] = "⚠️ IP '{$host}' pode não ser um endereço IP válido";
    } else {
        $test['details'][] = "✅ IP válido: {$host}";
    }
    
    // Validar porta
    if ($port < 1 || $port > 65535) {
        $test['status'] = 'error';
        $test['details'][] = "❌ Porta inválida: {$port}";
    } else {
        $test['details'][] = "✅ Porta válida: {$port}";
    }
    
    // Validar usuário
    if (empty($username)) {
        $test['status'] = 'warning';
        $test['details'][] = "⚠️ Usuário vazio - usando login anônimo";
    } else {
        $test['details'][] = "✅ Usuário: {$username}";
    }
    
    return $test;
}

function testPing($host) {
    $test = [
        'name' => 'Teste de Conectividade (Ping)',
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    $startTime = microtime(true);
    
    // Tenta ping usando diferentes métodos
    $methods = [
        'exec_ping' => "ping -c 1 -W 3 {$host} 2>&1",
        'exec_ping_win' => "ping -n 1 -w 3000 {$host} 2>&1"
    ];
    
    foreach ($methods as $method => $command) {
        if (function_exists('exec')) {
            $output = [];
            $returnCode = 0;
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $test['status'] = 'success';
                $test['message'] = 'Host responde ao ping';
                $test['details'][] = "✅ Ping bem-sucedido via {$method}";
                break;
            } else {
                $test['details'][] = "❌ Falha no ping via {$method}";
            }
        }
    }
    
    // Se exec não funcionou, tentar fsockopen como alternativa
    if ($test['status'] === 'pending') {
        $socket = @fsockopen($host, 80, $errno, $errstr, 3);
        if ($socket) {
            fclose($socket);
            $test['status'] = 'success';
            $test['message'] = 'Host acessível via HTTP';
            $test['details'][] = "✅ Host responde na porta 80";
        } else {
            $test['status'] = 'error';
            $test['message'] = 'Host não responde';
            $test['details'][] = "❌ Host não acessível";
        }
    }
    
    $endTime = microtime(true);
    $test['details'][] = "⏱️ Tempo de resposta: " . round(($endTime - $startTime) * 1000, 2) . "ms";
    
    return $test;
}

function testPort($host, $port) {
    $test = [
        'name' => "Teste de Porta {$port}",
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    $startTime = microtime(true);
    
    // Teste com fsockopen
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    
    if ($socket) {
        $test['status'] = 'success';
        $test['message'] = "Porta {$port} está aberta e acessível";
        $test['details'][] = "✅ Conexão estabelecida na porta {$port}";
        fclose($socket);
    } else {
        $test['status'] = 'error';
        $test['message'] = "Porta {$port} inacessível";
        $test['details'][] = "❌ Erro: {$errstr} (Código: {$errno})";
    }
    
    $endTime = microtime(true);
    $test['details'][] = "⏱️ Tempo de conexão: " . round(($endTime - $startTime) * 1000, 2) . "ms";
    
    return $test;
}

function testPHPExtensions() {
    $test = [
        'name' => 'Extensões PHP Necessárias',
        'status' => 'success',
        'message' => 'Todas as extensões necessárias estão disponíveis',
        'details' => []
    ];
    
    $requiredExtensions = [
        'sockets' => 'Necessária para comunicação com API MikroTik',
        'json' => 'Necessária para processamento de dados',
        'mbstring' => 'Necessária para manipulação de strings'
    ];
    
    foreach ($requiredExtensions as $ext => $description) {
        if (extension_loaded($ext)) {
            $test['details'][] = "✅ {$ext}: Disponível - {$description}";
        } else {
            $test['status'] = 'error';
            $test['message'] = 'Extensões necessárias não encontradas';
            $test['details'][] = "❌ {$ext}: NÃO ENCONTRADA - {$description}";
        }
    }
    
    // Verificar versão do PHP
    $phpVersion = phpversion();
    if (version_compare($phpVersion, '7.4', '>=')) {
        $test['details'][] = "✅ PHP {$phpVersion}: Versão compatível";
    } else {
        $test['status'] = 'warning';
        $test['details'][] = "⚠️ PHP {$phpVersion}: Versão pode ser incompatível (recomendado 7.4+)";
    }
    
    return $test;
}

function testMikroTikAPI($host, $username, $password, $port) {
    $test = [
        'name' => 'Teste de API MikroTik',
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    if (!extension_loaded('sockets')) {
        $test['status'] = 'error';
        $test['message'] = 'Extensão sockets não disponível';
        $test['details'][] = "❌ Extensão 'sockets' é necessária para API MikroTik";
        return $test;
    }
    
    try {
        $test['details'][] = "🔄 Iniciando conexão com {$host}:{$port}";
        
        // Criar socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new Exception("Falha ao criar socket: " . socket_strerror(socket_last_error()));
        }
        
        $test['details'][] = "✅ Socket criado com sucesso";
        
        // Configurar timeouts
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 5, "usec" => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 5, "usec" => 0));
        
        // Conectar
        if (!socket_connect($socket, $host, $port)) {
            $error = socket_strerror(socket_last_error($socket));
            throw new Exception("Falha na conexão: {$error}");
        }
        
        $test['details'][] = "✅ Conexão TCP estabelecida";
        
        // Testar protocolo MikroTik
        $loginSuccess = testMikroTikLogin($socket, $username, $password, $test);
        
        if ($loginSuccess) {
            $test['status'] = 'success';
            $test['message'] = 'Conexão com API MikroTik bem-sucedida';
            $test['details'][] = "✅ Login realizado com sucesso";
        } else {
            $test['status'] = 'error';
            $test['message'] = 'Falha na autenticação';
        }
        
        socket_close($socket);
        
    } catch (Exception $e) {
        $test['status'] = 'error';
        $test['message'] = 'Erro na conexão com API: ' . $e->getMessage();
        $test['details'][] = "❌ " . $e->getMessage();
        
        if (isset($socket) && is_resource($socket)) {
            socket_close($socket);
        }
    }
    
    return $test;
}

function testMikroTikLogin($socket, $username, $password, &$test) {
    try {
        // Enviar comando de login
        $test['details'][] = "🔄 Enviando comando de login";
        mikrotikWrite($socket, '/login');
        
        $response = mikrotikRead($socket);
        $test['details'][] = "✅ Resposta de login recebida";
        
        if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
            $test['details'][] = "❌ Erro no protocolo de login";
            return false;
        }
        
        // Enviar credenciais
        $loginData = ['=name=' . $username];
        if (!empty($password)) {
            $loginData[] = '=password=' . $password;
        }
        
        $test['details'][] = "🔄 Enviando credenciais";
        mikrotikWrite($socket, '/login', $loginData);
        
        $response = mikrotikRead($socket);
        
        if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
            $test['details'][] = "❌ Credenciais inválidas";
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        $test['details'][] = "❌ Erro durante login: " . $e->getMessage();
        return false;
    }
}

function testBasicCommands($host, $username, $password, $port) {
    $test = [
        'name' => 'Teste de Comandos Básicos',
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    try {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 5, "usec" => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 5, "usec" => 0));
        socket_connect($socket, $host, $port);
        
        // Login
        mikrotikWrite($socket, '/login');
        mikrotikRead($socket);
        
        $loginData = ['=name=' . $username];
        if (!empty($password)) {
            $loginData[] = '=password=' . $password;
        }
        
        mikrotikWrite($socket, '/login', $loginData);
        mikrotikRead($socket);
        
        // Testar comando básico: /system/identity/print
        $test['details'][] = "🔄 Testando comando: /system/identity/print";
        mikrotikWrite($socket, '/system/identity/print');
        $response = mikrotikRead($socket);
        
        if (!empty($response)) {
            $test['details'][] = "✅ Comando executado com sucesso";
            
            // Tentar extrair o nome do sistema
            foreach ($response as $line) {
                if (strpos($line, '=name=') !== false) {
                    $name = substr($line, 6);
                    $test['details'][] = "📋 Nome do sistema: {$name}";
                    break;
                }
            }
        }
        
        // Testar comando: /system/resource/print
        $test['details'][] = "🔄 Testando comando: /system/resource/print";
        mikrotikWrite($socket, '/system/resource/print');
        $response = mikrotikRead($socket);
        
        if (!empty($response)) {
            $test['details'][] = "✅ Comando de recursos executado";
            
            // Extrair algumas informações
            foreach ($response as $line) {
                if (strpos($line, '=version=') !== false) {
                    $version = substr($line, 9);
                    $test['details'][] = "📋 Versão RouterOS: {$version}";
                } elseif (strpos($line, '=platform=') !== false) {
                    $platform = substr($line, 10);
                    $test['details'][] = "📋 Plataforma: {$platform}";
                }
            }
        }
        
        // Testar se hotspot está disponível
        $test['details'][] = "🔄 Verificando módulo hotspot";
        mikrotikWrite($socket, '/ip/hotspot/print');
        $response = mikrotikRead($socket);
        
        if (!empty($response) && !isset($response[0]) || strpos($response[0], '!trap') === false) {
            $test['details'][] = "✅ Módulo hotspot disponível";
        } else {
            $test['details'][] = "⚠️ Módulo hotspot pode não estar configurado";
        }
        
        $test['status'] = 'success';
        $test['message'] = 'Comandos básicos executados com sucesso';
        
        socket_close($socket);
        
    } catch (Exception $e) {
        $test['status'] = 'error';
        $test['message'] = 'Erro ao executar comandos: ' . $e->getMessage();
        $test['details'][] = "❌ " . $e->getMessage();
        
        if (isset($socket) && is_resource($socket)) {
            socket_close($socket);
        }
    }
    
    return $test;
}

// Funções auxiliares para protocolo MikroTik
function mikrotikWrite($socket, $command, $arguments = []) {
    $data = mikrotikEncodeLength(strlen($command)) . $command;
    
    foreach ($arguments as $arg) {
        $data .= mikrotikEncodeLength(strlen($arg)) . $arg;
    }
    
    $data .= mikrotikEncodeLength(0);
    
    $result = socket_write($socket, $data);
    if ($result === false) {
        throw new Exception("Erro ao escrever no socket");
    }
}

function mikrotikRead($socket) {
    $response = [];
    
    while (true) {
        $length = mikrotikReadLength($socket);
        if ($length == 0) break;
        
        $data = socket_read($socket, $length);
        if ($data === false) {
            throw new Exception("Erro ao ler do socket");
        }
        
        $response[] = $data;
    }
    
    return $response;
}

function mikrotikReadLength($socket) {
    $byte = socket_read($socket, 1);
    if ($byte === false || $byte === '') {
        throw new Exception("Conexão perdida");
    }
    
    $length = ord($byte);
    
    if ($length < 0x80) {
        return $length;
    } elseif ($length < 0xC0) {
        $byte = socket_read($socket, 1);
        if ($byte === false) throw new Exception("Erro na leitura");
        return (($length & 0x3F) << 8) + ord($byte);
    } elseif ($length < 0xE0) {
        $bytes = socket_read($socket, 2);
        if ($bytes === false || strlen($bytes) < 2) throw new Exception("Erro na leitura");
        return (($length & 0x1F) << 16) + (ord($bytes[0]) << 8) + ord($bytes[1]);
    } elseif ($length < 0xF0) {
        $bytes = socket_read($socket, 3);
        if ($bytes === false || strlen($bytes) < 3) throw new Exception("Erro na leitura");
        return (($length & 0x0F) << 24) + (ord($bytes[0]) << 16) + (ord($bytes[1]) << 8) + ord($bytes[2]);
    }
    
    return 0;
}

function mikrotikEncodeLength($length) {
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

function calculateOverallResult($tests) {
    $hasError = false;
    $hasWarning = false;
    
    foreach ($tests as $test) {
        if ($test['status'] === 'error') {
            $hasError = true;
            break;
        } elseif ($test['status'] === 'warning') {
            $hasWarning = true;
        }
    }
    
    if ($hasError) return 'error';
    if ($hasWarning) return 'warning';
    return 'success';
}

function generateSummary($tests) {
    $total = count($tests);
    $success = 0;
    $warnings = 0;
    $errors = 0;
    
    foreach ($tests as $test) {
        if ($test['status'] === 'success') $success++;
        elseif ($test['status'] === 'warning') $warnings++;
        elseif ($test['status'] === 'error') $errors++;
    }
    
    return "Total: {$total} | Sucesso: {$success} | Avisos: {$warnings} | Erros: {$errors}";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Conexão MikroTik</title>
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
            max-width: 900px;
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
        
        .form-section {
            padding: 30px;
            background: #f8f9fa;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus {
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
        
        .results-section {
            padding: 30px;
        }
        
        .overall-result {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .overall-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .overall-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .overall-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .test-item {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .test-header {
            padding: 20px;
            font-weight: bold;
            font-size: 1.1em;
            border-bottom: 1px solid #eee;
        }
        
        .test-success {
            background: #d4edda;
            color: #155724;
        }
        
        .test-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .test-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .test-pending {
            background: #cce5ff;
            color: #004085;
        }
        
        .test-details {
            padding: 20px;
        }
        
        .test-details p {
            margin-bottom: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        .test-message {
            padding: 15px 20px;
            font-style: italic;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .quick-tests {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .quick-test-btn {
            padding: 10px 15px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .quick-test-btn:hover {
            background: #5a6268;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #004085;
            margin-bottom: 10px;
        }
        
        .tips {
            background: #fff8e1;
            border: 1px solid #ffecb3;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .tips h3 {
            color: #e65100;
            margin-bottom: 10px;
        }
        
        .tips ul {
            margin-left: 20px;
        }
        
        .tips li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Teste de Conexão MikroTik</h1>
            <p>Diagnóstico completo de conectividade e API</p>
        </div>
        
        <div class="form-section">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="host">IP do MikroTik:</label>
                        <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($defaultHost); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="port">Porta da API:</label>
                        <input type="number" id="port" name="port" value="<?php echo $defaultPort; ?>" min="1" max="65535" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Usuário:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($defaultUser); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Senha:</label>
                        <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($defaultPass); ?>">
                    </div>
                </div>
                
                <div class="quick-tests">
                    <button type="button" class="quick-test-btn" onclick="fillCommonSettings('192.168.1.1', 'admin', '')">MikroTik Padrão</button>
                    <button type="button" class="quick-test-btn" onclick="fillCommonSettings('192.168.88.1', 'admin', '')">MikroTik Novo</button>
                    <button type="button" class="quick-test-btn" onclick="fillCommonSettings('10.0.0.1', 'admin', '')">IP Personalizado</button>
                    <button type="button" class="quick-test-btn" onclick="fillCommonSettings('192.168.0.1', 'admin', '')">Router Comum</button>
                </div>
                
                <button type="submit" class="btn">🚀 Executar Teste Completo</button>
            </form>
        </div>
        
        <?php if (!empty($testResults)): ?>
        <div class="results-section">
            <div class="overall-result overall-<?php echo $testResults['overall']; ?>">
                <?php if ($testResults['overall'] === 'success'): ?>
                    ✅ Todos os testes foram bem-sucedidos!
                <?php elseif ($testResults['overall'] === 'warning'): ?>
                    ⚠️ Testes concluídos com avisos
                <?php else: ?>
                    ❌ Falhas detectadas nos testes
                <?php endif; ?>
                <br><small><?php echo $testResults['summary']; ?></small>
            </div>
            
            <?php foreach ($testResults['tests'] as $testKey => $test): ?>
            <div class="test-item">
                <div class="test-header test-<?php echo $test['status']; ?>">
                    <?php
                    $icon = '';
                    switch ($test['status']) {
                        case 'success': $icon = '✅'; break;
                        case 'warning': $icon = '⚠️'; break;
                        case 'error': $icon = '❌'; break;
                        default: $icon = '🔄'; break;
                    }
                    ?>
                    <?php echo $icon; ?> <?php echo $test['name']; ?>
                </div>
                
                <?php if (!empty($test['details'])): ?>
                <div class="test-details">
                    <?php foreach ($test['details'] as $detail): ?>
                        <p><?php echo htmlspecialchars($detail); ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($test['message'])): ?>
                <div class="test-message">
                    <?php echo htmlspecialchars($test['message']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <!-- Seção de diagnóstico adicional -->
            <div class="info-box">
                <h3>📋 Informações do Sistema</h3>
                <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                <p><strong>Sistema Operacional:</strong> <?php echo php_uname(); ?></p>
                <p><strong>Extensões Carregadas:</strong> 
                    <?php 
                    $extensions = get_loaded_extensions();
                    $relevant = array_intersect($extensions, ['sockets', 'json', 'mbstring', 'openssl', 'curl']);
                    echo implode(', ', $relevant);
                    ?>
                </p>
                <p><strong>Limite de Tempo:</strong> <?php echo ini_get('max_execution_time'); ?>s</p>
                <p><strong>Limite de Memória:</strong> <?php echo ini_get('memory_limit'); ?></p>
            </div>
            
            <!-- Recomendações -->
            <?php if ($testResults['overall'] !== 'success'): ?>
            <div class="tips">
                <h3>🔧 Dicas para Resolver Problemas</h3>
                <ul>
                    <?php if (isset($testResults['tests']['ping']) && $testResults['tests']['ping']['status'] === 'error'): ?>
                        <li><strong>Problema de Conectividade:</strong>
                            <ul>
                                <li>Verifique se o IP do MikroTik está correto</li>
                                <li>Teste o ping manualmente: <code>ping <?php echo htmlspecialchars($_POST['host'] ?? $defaultHost); ?></code></li>
                                <li>Verifique se há firewall bloqueando a conexão</li>
                                <li>Certifique-se de que o MikroTik está ligado e acessível na rede</li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isset($testResults['tests']['port']) && $testResults['tests']['port']['status'] === 'error'): ?>
                        <li><strong>Problema na Porta API:</strong>
                            <ul>
                                <li>Verifique se a API está habilitada no MikroTik: <code>/ip service enable api</code></li>
                                <li>Confirme a porta da API: <code>/ip service print</code></li>
                                <li>Teste portas alternativas: 8728, 8729</li>
                                <li>Verifique se há firewall no MikroTik bloqueando a API</li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isset($testResults['tests']['extensions']) && $testResults['tests']['extensions']['status'] === 'error'): ?>
                        <li><strong>Extensões PHP em Falta:</strong>
                            <ul>
                                <li>Instale a extensão sockets: <code>apt-get install php-sockets</code> (Linux)</li>
                                <li>No XAMPP: Descomente <code>extension=sockets</code> no php.ini</li>
                                <li>Reinicie o servidor web após as alterações</li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isset($testResults['tests']['api']) && $testResults['tests']['api']['status'] === 'error'): ?>
                        <li><strong>Problemas de Autenticação:</strong>
                            <ul>
                                <li>Verifique se o usuário existe: <code>/user print</code></li>
                                <li>Confirme a senha do usuário</li>
                                <li>Teste com usuário 'admin' e senha vazia primeiro</li>
                                <li>Verifique se o usuário tem permissões de API</li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <li><strong>Comandos Úteis no MikroTik:</strong>
                        <ul>
                            <li>Habilitar API: <code>/ip service enable api</code></li>
                            <li>Ver serviços: <code>/ip service print</code></li>
                            <li>Ver usuários: <code>/user print</code></li>
                            <li>Criar usuário para API: <code>/user add name=hotel-system password=hotel123 group=full</code></li>
                            <li>Ver conexões ativas: <code>/ip service-port print</code></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            
        </div>
        <?php else: ?>
        <div class="results-section">
            <div class="info-box">
                <h3>🔍 Como usar este teste</h3>
                <p>Este teste verifica a conectividade completa com o MikroTik RouterOS:</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li><strong>Conectividade de Rede:</strong> Ping e teste de porta</li>
                    <li><strong>Extensões PHP:</strong> Verifica se todas as dependências estão instaladas</li>
                    <li><strong>API MikroTik:</strong> Testa conexão e autenticação</li>
                    <li><strong>Comandos Básicos:</strong> Executa comandos de teste no RouterOS</li>
                </ul>
                <p style="margin-top: 15px;">
                    <strong>Dica:</strong> Use os botões de configuração rápida para testar IPs comuns do MikroTik.
                </p>
            </div>
            
            <div class="tips">
                <h3>⚙️ Preparação do MikroTik</h3>
                <p>Antes de executar o teste, certifique-se de que:</p>
                <ul>
                    <li>A API está habilitada: <code>/ip service enable api</code></li>
                    <li>A porta da API está configurada (padrão 8728): <code>/ip service set api port=8728</code></li>
                    <li>Existe um usuário com permissões adequadas</li>
                    <li>O firewall não está bloqueando a conexão</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function fillCommonSettings(host, username, password) {
            document.getElementById('host').value = host;
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
        }
        
        // Auto-refresh para desenvolvimento
        <?php if (!empty($testResults) && $testResults['overall'] === 'success'): ?>
        setTimeout(function() {
            console.log('Teste concluído com sucesso!');
        }, 1000);
        <?php endif; ?>
        
        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const host = document.getElementById('host').value.trim();
            const port = document.getElementById('port').value;
            
            if (!host) {
                alert('Por favor, insira o IP do MikroTik');
                e.preventDefault();
                return false;
            }
            
            if (port < 1 || port > 65535) {
                alert('A porta deve estar entre 1 e 65535');
                e.preventDefault();
                return false;
            }
            
            // Adicionar indicador de carregamento
            const button = e.target.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            button.innerHTML = '🔄 Testando...';
            button.disabled = true;
            
            // Restaurar o botão após 30 segundos (timeout)
            setTimeout(function() {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 30000);
        });
        
        // Função para copiar comandos
        function copyCommand(text) {
            navigator.clipboard.writeText(text).then(function() {
                console.log('Comando copiado: ' + text);
            });
        }
        
        // Adicionar tooltips aos códigos
        document.querySelectorAll('code').forEach(function(code) {
            code.style.cursor = 'pointer';
            code.title = 'Clique para copiar';
            code.addEventListener('click', function() {
                copyCommand(this.textContent);
                
                // Feedback visual
                const original = this.style.backgroundColor;
                this.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    this.style.backgroundColor = original;
                }, 500);
            });
        });
    </script>
</body>
</html>