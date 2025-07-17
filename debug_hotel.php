<?php
// debug_hotel.php - Script para diagnosticar problemas no sistema
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Debug do Sistema Hotel</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { background: #d4edda; padding: 10px; border-radius: 5px; color: #155724; margin: 10px 0; }
    .error { background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24; margin: 10px 0; }
    .warning { background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404; margin: 10px 0; }
    .info { background: #d1ecf1; padding: 10px; border-radius: 5px; color: #0c5460; margin: 10px 0; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
</style>";

// 1. Verificar arquivos necessários
echo "<h2>📁 1. Verificação de Arquivos</h2>";
$files = [
    'config.php' => 'Arquivo de configuração',
    'mikrotik_manager.php' => 'Classe do MikroTik',
    'index.php' => 'Interface principal'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ {$file} - {$desc} existe</div>";
        echo "<div class='info'>Tamanho: " . number_format(filesize($file)) . " bytes | Modificado: " . date('Y-m-d H:i:s', filemtime($file)) . "</div>";
    } else {
        echo "<div class='error'>❌ {$file} - {$desc} NÃO ENCONTRADO</div>";
    }
}

// 2. Verificar configurações do PHP
echo "<h2>⚙️ 2. Configurações do PHP</h2>";
echo "<table>";
echo "<tr><th>Configuração</th><th>Valor</th><th>Status</th></tr>";

$phpChecks = [
    'PHP Version' => phpversion(),
    'Display Errors' => ini_get('display_errors') ? 'ON' : 'OFF',
    'Error Reporting' => error_reporting(),
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution Time' => ini_get('max_execution_time'),
    'Post Max Size' => ini_get('post_max_size'),
    'Upload Max Size' => ini_get('upload_max_filesize')
];

foreach ($phpChecks as $setting => $value) {
    $status = '✅';
    if ($setting == 'Display Errors' && $value == 'OFF') {
        $status = '⚠️ Recomendado ON para debug';
    }
    echo "<tr><td>{$setting}</td><td>{$value}</td><td>{$status}</td></tr>";
}
echo "</table>";

// 3. Verificar extensões PHP
echo "<h2>🔌 3. Extensões PHP</h2>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'sockets', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='success'>✅ {$ext} - Carregada</div>";
    } else {
        echo "<div class='error'>❌ {$ext} - NÃO CARREGADA</div>";
    }
}

// 4. Testar conexão com banco de dados
echo "<h2>🗄️ 4. Teste de Banco de Dados</h2>";
if (file_exists('config.php')) {
    require_once 'config.php';
    
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo "<div class='success'>✅ Conexão com banco de dados bem-sucedida</div>";
        
        // Verificar tabelas
        $tables = ['hotel_guests', 'access_logs', 'system_settings'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("DESCRIBE {$table}");
                echo "<div class='success'>✅ Tabela {$table} existe</div>";
                
                // Contar registros
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                echo "<div class='info'>📊 {$table}: {$count} registros</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Tabela {$table}: " . $e->getMessage() . "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erro na conexão com banco: " . $e->getMessage() . "</div>";
        echo "<div class='warning'>Verifique as configurações em config.php</div>";
    }
} else {
    echo "<div class='error'>❌ Arquivo config.php não encontrado</div>";
}

// 5. Testar conexão com MikroTik
echo "<h2>📡 5. Teste de Conexão MikroTik</h2>";
if (isset($mikrotikConfig)) {
    echo "<div class='info'>Host: {$mikrotikConfig['host']}:{$mikrotikConfig['port']}</div>";
    echo "<div class='info'>Usuário: {$mikrotikConfig['username']}</div>";
    
    // Teste de ping
    $output = [];
    $return_var = 0;
    
    if (function_exists('exec')) {
        // Teste ping Windows
        exec("ping -n 1 -w 3000 {$mikrotikConfig['host']} 2>&1", $output, $return_var);
        if ($return_var === 0) {
            echo "<div class='success'>✅ MikroTik responde ao ping</div>";
        } else {
            // Teste ping Linux
            exec("ping -c 1 -W 3 {$mikrotikConfig['host']} 2>&1", $output, $return_var);
            if ($return_var === 0) {
                echo "<div class='success'>✅ MikroTik responde ao ping</div>";
            } else {
                echo "<div class='error'>❌ MikroTik não responde ao ping</div>";
                echo "<pre>" . implode("\n", $output) . "</pre>";
            }
        }
    }
    
    // Teste de porta
    $socket = @fsockopen($mikrotikConfig['host'], $mikrotikConfig['port'], $errno, $errstr, 5);
    if ($socket) {
        echo "<div class='success'>✅ Porta {$mikrotikConfig['port']} acessível</div>";
        fclose($socket);
    } else {
        echo "<div class='error'>❌ Porta {$mikrotikConfig['port']} inacessível: {$errstr}</div>";
    }
    
} else {
    echo "<div class='error'>❌ Configuração do MikroTik não encontrada</div>";
}

// 6. Verificar logs de erro
echo "<h2>📋 6. Logs de Erro</h2>";
$logFiles = [
    'logs/hotel_system.log',
    'error.log',
    '../logs/error.log'
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "<div class='success'>✅ Log encontrado: {$logFile}</div>";
        $lines = file($logFile);
        if (count($lines) > 0) {
            echo "<div class='info'>Últimas 5 linhas:</div>";
            echo "<pre>";
            for ($i = max(0, count($lines) - 5); $i < count($lines); $i++) {
                echo htmlspecialchars($lines[$i]);
            }
            echo "</pre>";
        }
    }
}

// 7. Teste manual de geração
echo "<h2>🧪 7. Teste Manual de Geração</h2>";
if (isset($pdo) && isset($mikrotikConfig)) {
    try {
        // Incluir classe se existir
        if (file_exists('mikrotik_manager.php')) {
            require_once 'mikrotik_manager.php';
            
            echo "<div class='info'>🔄 Testando geração de credenciais...</div>";
            
            $testRoom = 'TEST' . rand(100, 999);
            $testGuest = 'Teste Debug';
            $checkin = date('Y-m-d');
            $checkout = date('Y-m-d', strtotime('+1 day'));
            
            // Simular geração sem MikroTik
            $cleanRoom = preg_replace('/[^a-zA-Z0-9]/', '', $testRoom);
            $randomNumbers = rand(10, 999);
            $username = $cleanRoom . '-' . $randomNumbers;
            
            $password = '';
            do {
                $password = rand(100, 9999);
            } while (preg_match('/123|234|345|456|567|678|789/', $password));
            
            echo "<div class='success'>✅ Credenciais geradas:</div>";
            echo "<div class='info'>Quarto: {$testRoom}</div>";
            echo "<div class='info'>Usuário: {$username}</div>";
            echo "<div class='info'>Senha: {$password}</div>";
            
            // Tentar inserir no banco
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO hotel_guests (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date)
                    VALUES (?, ?, ?, ?, 'hotel-guest', ?, ?)
                ");
                
                $stmt->execute([$testRoom, $testGuest, $username, $password, $checkin, $checkout]);
                
                echo "<div class='success'>✅ Registro inserido no banco com sucesso</div>";
                echo "<div class='info'>ID inserido: " . $pdo->lastInsertId() . "</div>";
                
                // Verificar se aparece na listagem
                $stmt = $pdo->prepare("SELECT * FROM hotel_guests WHERE room_number = ? AND status = 'active'");
                $stmt->execute([$testRoom]);
                $guest = $stmt->fetch();
                
                if ($guest) {
                    echo "<div class='success'>✅ Registro encontrado na consulta</div>";
                    echo "<pre>" . print_r($guest, true) . "</pre>";
                } else {
                    echo "<div class='error'>❌ Registro não encontrado na consulta</div>";
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Erro ao inserir no banco: " . $e->getMessage() . "</div>";
            }
            
        } else {
            echo "<div class='error'>❌ mikrotik_manager.php não encontrado</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erro no teste: " . $e->getMessage() . "</div>";
    }
}

// 8. Verificar configurações do servidor web
echo "<h2>🌐 8. Configurações do Servidor Web</h2>";
if (isset($_SERVER['SERVER_SOFTWARE'])) {
    echo "<div class='info'>Servidor: {$_SERVER['SERVER_SOFTWARE']}</div>";
}
echo "<div class='info'>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</div>";
echo "<div class='info'>Script Path: " . $_SERVER['SCRIPT_FILENAME'] . "</div>";
echo "<div class='info'>HTTP Host: " . $_SERVER['HTTP_HOST'] . "</div>";

// 9. Verificar permissões de arquivos
echo "<h2>🔒 9. Permissões de Arquivos</h2>";
$checkDirs = ['logs', 'backups', 'uploads', '.'];
foreach ($checkDirs as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $writable = is_writable($dir) ? '✅ Escrita' : '❌ Sem escrita';
        echo "<div class='info'>📁 {$dir}: {$perms} - {$writable}</div>";
    }
}

// 10. Formulário de teste direto
echo "<h2>🎯 10. Teste Direto de POST</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_generate'])) {
    echo "<div class='warning'>🔄 Processando dados do formulário...</div>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    try {
        if (isset($pdo)) {
            $room = $_POST['room_number'];
            $guest = $_POST['guest_name'];
            $checkin = $_POST['checkin_date'];
            $checkout = $_POST['checkout_date'];
            
            $cleanRoom = preg_replace('/[^a-zA-Z0-9]/', '', $room);
            $username = $cleanRoom . '-' . rand(10, 999);
            $password = rand(100, 999);
            
            $stmt = $pdo->prepare("
                INSERT INTO hotel_guests (room_number, guest_name, username, password, profile_type, checkin_date, checkout_date)
                VALUES (?, ?, ?, ?, 'hotel-guest', ?, ?)
            ");
            
            if ($stmt->execute([$room, $guest, $username, $password, $checkin, $checkout])) {
                echo "<div class='success'>✅ SUCESSO! Hóspede criado:</div>";
                echo "<div class='info'>Usuário: {$username} | Senha: {$password}</div>";
            } else {
                echo "<div class='error'>❌ Falha ao executar INSERT</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erro: " . $e->getMessage() . "</div>";
    }
}

echo "<form method='POST' style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Teste Direto de Geração</h3>";
echo "<input type='text' name='room_number' placeholder='Número do Quarto' value='DEBUG" . rand(100, 999) . "' required style='margin: 5px; padding: 8px;'><br>";
echo "<input type='text' name='guest_name' placeholder='Nome do Hóspede' value='Teste Debug' required style='margin: 5px; padding: 8px;'><br>";
echo "<input type='date' name='checkin_date' value='" . date('Y-m-d') . "' required style='margin: 5px; padding: 8px;'><br>";
echo "<input type='date' name='checkout_date' value='" . date('Y-m-d', strtotime('+1 day')) . "' required style='margin: 5px; padding: 8px;'><br>";
echo "<button type='submit' name='test_generate' style='margin: 5px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px;'>🧪 Testar Geração</button>";
echo "</form>";

// 11. Comandos de correção
echo "<h2>🔧 11. Comandos de Correção</h2>";
echo "<div class='warning'>";
echo "<h3>Se houver problemas, execute:</h3>";
echo "<pre>";
echo "# Criar diretórios necessários\n";
echo "mkdir -p logs backups uploads reports\n";
echo "chmod 755 logs backups uploads reports\n\n";

echo "# Verificar permissões do Apache/PHP\n";
echo "chown -R www-data:www-data .\n";
echo "chmod 644 *.php\n";
echo "chmod 755 .\n\n";

echo "# Habilitar logs de erro no PHP\n";
echo "ini_set('display_errors', 1);\n";
echo "ini_set('log_errors', 1);\n";
echo "error_reporting(E_ALL);\n\n";

echo "# Testar MySQL manualmente\n";
echo "mysql -u root -p hotel_system\n";
echo "SHOW TABLES;\n";
echo "SELECT * FROM hotel_guests ORDER BY id DESC LIMIT 5;\n";
echo "</pre>";
echo "</div>";

echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>💡 Próximos Passos:</h3>";
echo "<ol>";
echo "<li>Corrija qualquer erro vermelho mostrado acima</li>";
echo "<li>Verifique se os arquivos foram substituídos corretamente</li>";
echo "<li>Teste a geração usando o formulário acima</li>";
echo "<li>Se ainda não funcionar, adicione debug no index.php</li>";
echo "</ol>";
echo "</div>";
?>