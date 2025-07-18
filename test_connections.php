<?php
/**
 * test_connections.php - Teste de Conexões do Sistema Hotel v4.1
 * 
 * Este arquivo testa individualmente cada conexão para identificar problemas específicos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configurações
if (!file_exists('config.php')) {
    die("❌ Arquivo config.php não encontrado!");
}

require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Conexões - Sistema Hotel v4.1</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .test-card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .status-online { background: #28a745; }
        .status-offline { background: #dc3545; }
        .status-warning { background: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Teste de Conexões - Sistema Hotel v4.1</h1>
        <p>Este teste verifica individualmente cada componente do sistema para identificar problemas específicos.</p>
        
        <!-- Teste 1: Arquivos do Sistema -->
        <div class="test-card">
            <h2>📁 Teste 1: Arquivos do Sistema</h2>
            <?php
            $files = [
                'config.php' => file_exists('config.php'),
                'mikrotik_manager.php' => file_exists('mikrotik_manager.php'),
                'index.php' => file_exists('index.php')
            ];
            
            foreach ($files as $file => $exists) {
                echo "<p>";
                echo "<span class='status-indicator " . ($exists ? 'status-online' : 'status-offline') . "'></span>";
                echo "<strong>{$file}:</strong> ";
                echo $exists ? "<span class='success'>✅ Encontrado</span>" : "<span class='error'>❌ Não encontrado</span>";
                echo "</p>";
            }
            ?>
        </div>
        
        <!-- Teste 2: Extensões PHP -->
        <div class="test-card">
            <h2>🔌 Teste 2: Extensões PHP</h2>
            <p><strong>Versão PHP:</strong> <span class="info"><?php echo PHP_VERSION; ?></span></p>
            <?php
            $extensions = [
                'pdo' => 'PDO (Conexão com banco)',
                'pdo_mysql' => 'PDO MySQL (Driver MySQL)',
                'sockets' => 'Sockets (Conexão MikroTik)',
                'json' => 'JSON (Processamento dados)',
                'mbstring' => 'Multibyte String (UTF-8)',
                'curl' => 'cURL (HTTP requests)',
                'openssl' => 'OpenSSL (Criptografia)'
            ];
            
            foreach ($extensions as $ext => $description) {
                $loaded = extension_loaded($ext);
                echo "<p>";
                echo "<span class='status-indicator " . ($loaded ? 'status-online' : 'status-offline') . "'></span>";
                echo "<strong>{$ext}:</strong> {$description} - ";
                echo $loaded ? "<span class='success'>✅ Carregada</span>" : "<span class='error'>❌ Não carregada</span>";
                echo "</p>";
            }
            ?>
        </div>
        
        <!-- Teste 3: Configurações -->
        <div class="test-card">
            <h2>⚙️ Teste 3: Configurações</h2>
            <h3>Banco de Dados:</h3>
            <ul>
                <li><strong>Host:</strong> <?php echo htmlspecialchars($dbConfig['host']); ?></li>
                <li><strong>Banco:</strong> <?php echo htmlspecialchars($dbConfig['database']); ?></li>
                <li><strong>Usuário:</strong> <?php echo htmlspecialchars($dbConfig['username']); ?></li>
                <li><strong>Senha:</strong> <?php echo empty($dbConfig['password']) ? '<span class="warning">⚠️ Vazia</span>' : '<span class="success">✅ Definida</span>'; ?></li>
            </ul>
            
            <h3>MikroTik:</h3>
            <ul>
                <li><strong>Host:</strong> <?php echo htmlspecialchars($mikrotikConfig['host']); ?></li>
                <li><strong>Porta:</strong> <?php echo htmlspecialchars($mikrotikConfig['port']); ?></li>
                <li><strong>Usuário:</strong> <?php echo htmlspecialchars($mikrotikConfig['username']); ?></li>
                <li><strong>Senha:</strong> <?php echo empty($mikrotikConfig['password']) ? '<span class="warning">⚠️ Vazia</span>' : '<span class="success">✅ Definida</span>'; ?></li>
            </ul>
        </div>
        
        <!-- Teste 4: Conectividade MySQL -->
        <div class="test-card">
            <h2>💾 Teste 4: Conectividade MySQL</h2>
            <?php
            echo "<h3>Teste de Porta MySQL</h3>";
            $mysqlReachable = false;
            $socket = @fsockopen($dbConfig['host'], 3306, $errno, $errstr, 5);
            if ($socket) {
                fclose($socket);
                $mysqlReachable = true;
                echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Porta 3306 acessível</span></p>";
            } else {
                echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Porta 3306 inacessível: {$errstr} (Erro: {$errno})</span></p>";
            }
            
            if ($mysqlReachable) {
                echo "<h3>Teste de Conexão MySQL</h3>";
                
                // Teste sem especificar banco
                try {
                    $hostOnlyDsn = "mysql:host={$dbConfig['host']};charset=utf8mb4";
                    $testPdo = new PDO($hostOnlyDsn, $dbConfig['username'], $dbConfig['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 10
                    ]);
                    
                    echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Conexão MySQL bem-sucedida</span></p>";
                    
                    // Verificar versão
                    $stmt = $testPdo->query("SELECT VERSION() as version");
                    $version = $stmt->fetchColumn();
                    echo "<p><strong>Versão MySQL:</strong> {$version}</p>";
                    
                    // Verificar se banco existe
                    $stmt = $testPdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
                    $stmt->execute([$dbConfig['database']]);
                    $dbExists = $stmt->fetchColumn();
                    
                    if ($dbExists) {
                        echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Banco '{$dbConfig['database']}' existe</span></p>";
                        
                        // Testar conexão com banco específico
                        $fullDsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
                        $fullPdo = new PDO($fullDsn, $dbConfig['username'], $dbConfig['password'], [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 10
                        ]);
                        
                        echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Conexão com banco específico bem-sucedida</span></p>";
                        
                        // Verificar tabelas
                        $stmt = $fullPdo->query("SHOW TABLES");
                        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (empty($tables)) {
                            echo "<p><span class='status-indicator status-warning'></span><span class='warning'>⚠️ Nenhuma tabela encontrada (banco vazio)</span></p>";
                        } else {
                            echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Tabelas encontradas: " . implode(', ', $tables) . "</span></p>";
                            
                            // Verificar tabela hotel_guests
                            if (in_array('hotel_guests', $tables)) {
                                $stmt = $fullPdo->query("SELECT COUNT(*) FROM hotel_guests");
                                $count = $stmt->fetchColumn();
                                echo "<p><strong>Registros em hotel_guests:</strong> {$count}</p>";
                            }
                        }
                        
                    } else {
                        echo "<p><span class='status-indicator status-warning'></span><span class='warning'>⚠️ Banco '{$dbConfig['database']}' não existe (será criado automaticamente)</span></p>";
                    }
                    
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Access denied') !== false) {
                        echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Acesso negado - Credenciais inválidas</span></p>";
                        echo "<p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                        
                        // Tentar com root sem senha
                        echo "<h4>Tentativa com usuário root:</h4>";
                        try {
                            $rootPdo = new PDO($hostOnlyDsn, 'root', '', [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_TIMEOUT => 10
                            ]);
                            echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Conexão com root sem senha funcionou</span></p>";
                            echo "<p><span class='info'>💡 Sugestão: Ajuste as credenciais no config.php</span></p>";
                        } catch (PDOException $e2) {
                            echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Root também falhou: " . htmlspecialchars($e2->getMessage()) . "</span></p>";
                        }
                    } else {
                        echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Erro de conexão MySQL</span></p>";
                        echo "<p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                }
            } else {
                echo "<p><span class='info'>💡 Verifique se o MySQL/MariaDB está rodando</span></p>";
                echo "<p><span class='info'>💡 Comandos úteis:</span></p>";
                echo "<ul>";
                echo "<li>Windows: <code>net start mysql</code></li>";
                echo "<li>Linux: <code>sudo systemctl start mysql</code> ou <code>sudo service mysql start</code></li>";
                echo "<li>macOS: <code>brew services start mysql</code></li>";
                echo "</ul>";
            }
            ?>
        </div>
        
        <!-- Teste 5: Conectividade MikroTik -->
        <div class="test-card">
            <h2>📡 Teste 5: Conectividade MikroTik</h2>
            <?php
            echo "<h3>Teste de Porta MikroTik</h3>";
            $mikrotikReachable = false;
            $socket = @fsockopen($mikrotikConfig['host'], $mikrotikConfig['port'], $errno, $errstr, 5);
            if ($socket) {
                fclose($socket);
                $mikrotikReachable = true;
                echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Porta {$mikrotikConfig['port']} acessível</span></p>";
            } else {
                echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Porta {$mikrotikConfig['port']} inacessível: {$errstr} (Erro: {$errno})</span></p>";
            }
            
            // Teste de ping (se disponível)
            if (function_exists('exec')) {
                echo "<h3>Teste de Ping</h3>";
                $pingOutput = [];
                $pingReturn = 0;
                @exec("ping -c 1 -W 3 {$mikrotikConfig['host']} 2>&1", $pingOutput, $pingReturn);
                
                if ($pingReturn === 0) {
                    echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Ping bem-sucedido</span></p>";
                } else {
                    echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Ping falhou</span></p>";
                    echo "<pre>" . htmlspecialchars(implode("\n", $pingOutput)) . "</pre>";
                }
            }
            
            if ($mikrotikReachable && file_exists('mikrotik_manager.php')) {
                echo "<h3>Teste de API MikroTik</h3>";
                
                require_once 'mikrotik_manager.php';
                
                if (class_exists('MikroTikHotspotManagerFixed')) {
                    try {
                        $mikrotik = new MikroTikHotspotManagerFixed(
                            $mikrotikConfig['host'],
                            $mikrotikConfig['username'],
                            $mikrotikConfig['password'],
                            $mikrotikConfig['port']
                        );
                        
                        $testResult = $mikrotik->testConnection();
                        
                        if ($testResult['success']) {
                            echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Conexão API MikroTik bem-sucedida</span></p>";
                            echo "<p><strong>Resposta:</strong> " . htmlspecialchars($testResult['message']) . "</p>";
                            
                            // Testar listagem de usuários
                            try {
                                $mikrotik->connect();
                                $users = $mikrotik->listHotspotUsers();
                                $mikrotik->disconnect();
                                
                                echo "<p><span class='status-indicator status-online'></span><span class='success'>✅ Listagem de usuários funcionou</span></p>";
                                echo "<p><strong>Usuários encontrados:</strong> " . count($users) . "</p>";
                                
                                if (!empty($users)) {
                                    echo "<h4>Primeiros 3 usuários:</h4>";
                                    echo "<ul>";
                                    foreach (array_slice($users, 0, 3) as $user) {
                                        echo "<li>" . htmlspecialchars($user['name'] ?? 'N/A') . " (ID: " . htmlspecialchars($user['id'] ?? 'N/A') . ")</li>";
                                    }
                                    echo "</ul>";
                                }
                                
                            } catch (Exception $e) {
                                echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Erro na listagem: " . htmlspecialchars($e->getMessage()) . "</span></p>";
                            }
                            
                        } else {
                            echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Falha na conexão API</span></p>";
                            echo "<p><strong>Erro:</strong> " . htmlspecialchars($testResult['message']) . "</p>";
                        }
                        
                    } catch (Exception $e) {
                        echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Erro ao instanciar MikroTik: " . htmlspecialchars($e->getMessage()) . "</span></p>";
                    }
                } else {
                    echo "<p><span class='status-indicator status-offline'></span><span class='error'>❌ Classe MikroTikHotspotManagerFixed não encontrada</span></p>";
                }
            } else {
                echo "<p><span class='info'>💡 Verifique se o MikroTik está ligado e acessível na rede</span></p>";
                echo "<p><span class='info'>💡 Verifique se a API está habilitada no MikroTik</span></p>";
                echo "<p><span class='info'>💡 Comando RouterOS: <code>/ip service enable api</code></span></p>";
            }
            ?>
        </div>
        
        <!-- Teste 6: Informações do Servidor -->
        <div class="test-card">
            <h2>🖥️ Teste 6: Informações do Servidor</h2>
            <ul>
                <li><strong>Sistema:</strong> <?php echo PHP_OS; ?></li>
                <li><strong>Servidor Web:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido'; ?></li>
                <li><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Desconhecido'; ?></li>
                <li><strong>Script Atual:</strong> <?php echo $_SERVER['SCRIPT_FILENAME'] ?? 'Desconhecido'; ?></li>
                <li><strong>Memória PHP:</strong> <?php echo ini_get('memory_limit'); ?></li>
                <li><strong>Tempo Execução:</strong> <?php echo ini_get('max_execution_time'); ?>s</li>
                <li><strong>Upload Max:</strong> <?php echo ini_get('upload_max_filesize'); ?></li>
                <li><strong>Post Max:</strong> <?php echo ini_get('post_max_size'); ?></li>
            </ul>
        </div>
        
        <!-- Resumo -->
        <div class="test-card" style="background: #e9ecef;">
            <h2>📋 Resumo dos Testes</h2>
            <?php
            $tests = [
                'Arquivos' => file_exists('config.php') && file_exists('mikrotik_manager.php'),
                'PHP Extensions' => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
                'MySQL' => $mysqlReachable ?? false,
                'MikroTik' => $mikrotikReachable ?? false
            ];
            
            $passedTests = array_sum($tests);
            $totalTests = count($tests);
            
            echo "<p><strong>Testes Aprovados:</strong> {$passedTests}/{$totalTests}</p>";
            
            foreach ($tests as $test => $passed) {
                echo "<p>";
                echo "<span class='status-indicator " . ($passed ? 'status-online' : 'status-offline') . "'></span>";
                echo "<strong>{$test}:</strong> " . ($passed ? "<span class='success'>✅ OK</span>" : "<span class='error'>❌ Falha</span>");
                echo "</p>";
            }
            
            if ($passedTests === $totalTests) {
                echo "<p style='color: #28a745; font-weight: bold; font-size: 1.2em;'>🎉 Todos os testes passaram! O sistema deve funcionar corretamente.</p>";
            } elseif ($passedTests >= 2) {
                echo "<p style='color: #ffc107; font-weight: bold; font-size: 1.2em;'>⚠️ Sistema parcialmente funcional. Alguns recursos podem não funcionar.</p>";
            } else {
                echo "<p style='color: #dc3545; font-weight: bold; font-size: 1.2em;'>❌ Sistema com problemas críticos. Corrija os erros antes de usar.</p>";
            }
            ?>
        </div>
        
        <!-- Soluções Comuns -->
        <div class="test-card">
            <h2>🛠️ Soluções para Problemas Comuns</h2>
            
            <h3>❌ MySQL não conecta:</h3>
            <ul>
                <li><strong>Verificar serviço:</strong>
                    <ul>
                        <li>Windows: <code>net start mysql</code> ou iniciar via XAMPP/WAMP</li>
                        <li>Linux: <code>sudo systemctl start mysql</code></li>
                        <li>macOS: <code>brew services start mysql</code></li>
                    </ul>
                </li>
                <li><strong>Credenciais:</strong> Verifique usuário/senha no <code>config.php</code></li>
                <li><strong>Porta:</strong> MySQL geralmente usa porta 3306</li>
                <li><strong>Permissões:</strong> O usuário precisa de permissões no banco</li>
            </ul>
            
            <h3>❌ MikroTik não conecta:</h3>
            <ul>
                <li><strong>Verificar IP:</strong> Confirme o IP correto no <code>config.php</code></li>
                <li><strong>Habilitar API:</strong> No RouterOS: <code>/ip service enable api</code></li>
                <li><strong>Porta API:</strong> Padrão é 8728 (não criptografada) ou 8729 (SSL)</li>
                <li><strong>Firewall:</strong> Liberar porta 8728 no firewall do MikroTik</li>
                <li><strong>Usuário:</strong> Criar usuário com permissões adequadas</li>
            </ul>
            
            <h3>❌ Extensões PHP faltando:</h3>
            <ul>
                <li><strong>XAMPP/WAMP:</strong> Ativar extensões no painel de controle</li>
                <li><strong>Linux:</strong> <code>sudo apt install php-mysql php-sockets</code></li>
                <li><strong>Manual:</strong> Editar <code>php.ini</code> e descomentar extensões</li>
            </ul>
            
            <h3>🔧 Como criar usuário MikroTik:</h3>
            <pre>/user add name=hotel-system password=hotel123 group=full</pre>
            
            <h3>🔧 Como criar banco MySQL:</h3>
            <pre>CREATE DATABASE hotel_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'root'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON hotel_system.* TO 'root'@'localhost';
FLUSH PRIVILEGES;</pre>
        </div>
        
        <!-- Ações -->
        <div class="test-card" style="text-align: center;">
            <h2>🎯 Próximos Passos</h2>
            
            <a href="?" class="btn">🔄 Executar Testes Novamente</a>
            <a href="index.php" class="btn">🏠 Voltar ao Sistema</a>
            
            <?php if (file_exists('test_raw_parser_final.php')): ?>
                <a href="test_raw_parser_final.php" class="btn">🧪 Testar Parser MikroTik</a>
            <?php endif; ?>
            
            <?php if (function_exists('phpinfo')): ?>
                <a href="javascript:void(0)" onclick="window.open('data:text/html,<?php echo urlencode('<?php phpinfo(); ?>'); ?>', '_blank')" class="btn">📋 Ver PHP Info</a>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <h3>📖 Documentação Rápida</h3>
                <p><strong>Arquivo de configuração:</strong> <code>config.php</code></p>
                <p><strong>Logs do sistema:</strong> <code>logs/hotel_system.log</code></p>
                <p><strong>Versão atual:</strong> Sistema Hotel v4.1</p>
            </div>
        </div>
        
        <!-- Debug Info -->
        <div class="test-card">
            <h2>🔍 Informações de Debug</h2>
            <p>Use estas informações para reportar problemas:</p>
            
            <h3>Configurações Atuais:</h3>
            <pre><?php
            $debugInfo = [
                'php_version' => PHP_VERSION,
                'php_os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
                'extensions' => [
                    'pdo' => extension_loaded('pdo'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'sockets' => extension_loaded('sockets'),
                    'json' => extension_loaded('json'),
                    'mbstring' => extension_loaded('mbstring'),
                    'curl' => extension_loaded('curl'),
                    'openssl' => extension_loaded('openssl')
                ],
                'ini_settings' => [
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ],
                'config_status' => [
                    'mysql_host' => $dbConfig['host'],
                    'mysql_database' => $dbConfig['database'],
                    'mysql_user' => $dbConfig['username'],
                    'mikrotik_host' => $mikrotikConfig['host'],
                    'mikrotik_port' => $mikrotikConfig['port'],
                    'mikrotik_user' => $mikrotikConfig['username']
                ],
                'file_permissions' => [
                    'config.php' => file_exists('config.php') ? (is_readable('config.php') ? 'readable' : 'not_readable') : 'not_found',
                    'mikrotik_manager.php' => file_exists('mikrotik_manager.php') ? (is_readable('mikrotik_manager.php') ? 'readable' : 'not_readable') : 'not_found',
                    'logs_dir' => is_dir('logs') ? (is_writable('logs') ? 'writable' : 'not_writable') : 'not_found'
                ],
                'test_timestamp' => date('Y-m-d H:i:s'),
                'test_results' => $tests ?? []
            ];
            
            echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            ?></pre>
            
            <h3>Como usar esta informação:</h3>
            <ul>
                <li>Copie as informações acima ao reportar problemas</li>
                <li>Verifique se todas as extensões necessárias estão carregadas</li>
                <li>Confirme se as configurações estão corretas</li>
                <li>Verifique as permissões de arquivos e diretórios</li>
            </ul>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; margin: 40px 0; color: #6c757d;">
            <p>Sistema Hotel v4.1 - Teste de Conexões</p>
            <p>Desenvolvido para identificar e resolver problemas de conectividade</p>
            <p>Timestamp: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // JavaScript para funcionalidades extras
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Teste de conexões carregado');
            
            // Adicionar tooltips nos indicadores de status
            const indicators = document.querySelectorAll('.status-indicator');
            indicators.forEach(indicator => {
                indicator.style.cursor = 'help';
                
                if (indicator.classList.contains('status-online')) {
                    indicator.title = 'Status: Online/Funcionando';
                } else if (indicator.classList.contains('status-offline')) {
                    indicator.title = 'Status: Offline/Com Problemas';
                } else if (indicator.classList.contains('status-warning')) {
                    indicator.title = 'Status: Atenção/Parcialmente Funcionando';
                }
            });
            
            // Destacar seções com problemas
            const errorElements = document.querySelectorAll('.error');
            errorElements.forEach(element => {
                const card = element.closest('.test-card');
                if (card && !card.classList.contains('highlighted')) {
                    card.style.borderLeft = '5px solid #dc3545';
                    card.classList.add('highlighted');
                }
            });
            
            // Adicionar scroll suave aos links internos
            const links = document.querySelectorAll('a[href^="#"]');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
        
        // Função para copiar debug info
        function copyDebugInfo() {
            const debugPre = document.querySelector('pre');
            if (debugPre) {
                navigator.clipboard.writeText(debugPre.textContent).then(function() {
                    alert('Informações de debug copiadas para a área de transferência!');
                }).catch(function(err) {
                    console.error('Erro ao copiar: ', err);
                    alert('Erro ao copiar informações. Use Ctrl+C manualmente.');
                });
            }
        }
        
        // Auto-refresh a cada 30 segundos (opcional)
        // setInterval(() => {
        //     if (confirm('Executar testes novamente?')) {
        //         location.reload();
        //     }
        // }, 30000);
    </script>
</body>
</html>