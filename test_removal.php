<?php
/**
 * test_removal.php - Script para testar remoção de usuários
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir arquivos necessários
require_once 'config.php';
require_once 'mikrotik_manager_fixed.php'; // Use o arquivo corrigido

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Remoção - Sistema Hotel</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .content {
            padding: 30px;
        }
        
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #e74c3c;
        }
        
        .btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
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
            border-left: 5px solid #27ae60;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #e74c3c;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 5px solid #17a2b8;
        }
        
        .log-box {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin: 15px 0;
        }
        
        .user-list {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .user-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-info {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .user-details {
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .form-group {
            margin: 15px 0;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }
        
        .step {
            background: #ecf0f1;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .step-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #e74c3c;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Teste de Remoção de Usuários</h1>
            <p>Diagnóstico e Teste do Sistema MikroTik</p>
        </div>
        
        <div class="content">
            <?php
            $message = null;
            $testResults = null;
            $logOutput = null;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    if (isset($_POST['test_connection'])) {
                        $mikrotik = new MikroTikHotspotManagerFixed(
                            $mikrotikConfig['host'],
                            $mikrotikConfig['username'],
                            $mikrotikConfig['password'],
                            $mikrotikConfig['port']
                        );
                        
                        $result = $mikrotik->testConnection();
                        
                        if ($result['success']) {
                            $message = "✅ CONEXÃO BEM-SUCEDIDA! MikroTik respondeu corretamente.";
                        } else {
                            $message = "❌ FALHA NA CONEXÃO: " . $result['message'];
                        }
                        
                    } elseif (isset($_POST['list_users'])) {
                        $mikrotik = new MikroTikHotspotManagerFixed(
                            $mikrotikConfig['host'],
                            $mikrotikConfig['username'],
                            $mikrotikConfig['password'],
                            $mikrotikConfig['port']
                        );
                        
                        $mikrotik->connect();
                        $users = $mikrotik->listHotspotUsers();
                        $mikrotik->disconnect();
                        
                        $testResults = [
                            'users' => $users,
                            'count' => count($users)
                        ];
                        
                        $message = "📋 USUÁRIOS LISTADOS: " . count($users) . " usuários encontrados.";
                        
                    } elseif (isset($_POST['test_removal'])) {
                        $username = trim($_POST['username_to_remove']);
                        
                        if (empty($username)) {
                            $message = "❌ ERRO: Nome de usuário é obrigatório.";
                        } else {
                            // Capturar log em tempo real
                            ob_start();
                            
                            echo "=== TESTE DE REMOÇÃO INICIADO ===\n";
                            echo "Usuário: {$username}\n";
                            echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
                            
                            $mikrotik = new MikroTikHotspotManagerFixed(
                                $mikrotikConfig['host'],
                                $mikrotikConfig['username'],
                                $mikrotikConfig['password'],
                                $mikrotikConfig['port']
                            );
                            
                            echo "1. Conectando ao MikroTik...\n";
                            $mikrotik->connect();
                            echo "   ✅ Conectado com sucesso\n\n";
                            
                            echo "2. Listando usuários antes da remoção...\n";
                            $usersBefore = $mikrotik->listHotspotUsers();
                            echo "   📊 Usuários encontrados: " . count($usersBefore) . "\n";
                            
                            $userExists = false;
                            foreach ($usersBefore as $user) {
                                if (isset($user['name']) && $user['name'] === $username) {
                                    $userExists = true;
                                    echo "   ✅ Usuário '{$username}' encontrado\n";
                                    echo "   📋 ID: " . ($user['id'] ?? 'N/A') . "\n";
                                    echo "   🔒 Perfil: " . ($user['profile'] ?? 'N/A') . "\n\n";
                                    break;
                                }
                            }
                            
                            if (!$userExists) {
                                echo "   ❌ Usuário '{$username}' NÃO encontrado\n";
                                echo "   📝 Usuários disponíveis:\n";
                                foreach ($usersBefore as $user) {
                                    echo "      - " . ($user['name'] ?? 'N/A') . "\n";
                                }
                                echo "\n";
                            } else {
                                echo "3. Executando remoção...\n";
                                $removeResult = $mikrotik->removeHotspotUser($username);
                                
                                if ($removeResult) {
                                    echo "   ✅ Comando de remoção executado\n\n";
                                    
                                    echo "4. Verificando resultado...\n";
                                    $usersAfter = $mikrotik->listHotspotUsers();
                                    echo "   📊 Usuários após remoção: " . count($usersAfter) . "\n";
                                    
                                    $stillExists = false;
                                    foreach ($usersAfter as $user) {
                                        if (isset($user['name']) && $user['name'] === $username) {
                                            $stillExists = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$stillExists) {
                                        echo "   🎉 SUCESSO! Usuário foi REALMENTE removido\n";
                                        $message = "🎉 SUCESSO! Usuário '{$username}' foi removido com sucesso.";
                                    } else {
                                        echo "   ❌ FALHA! Usuário ainda existe após remoção\n";
                                        $message = "❌ FALHA! Usuário '{$username}' ainda existe após tentativa de remoção.";
                                    }
                                } else {
                                    echo "   ❌ Falha na execução do comando de remoção\n";
                                    $message = "❌ FALHA! Erro na execução do comando de remoção.";
                                }
                            }
                            
                            $mikrotik->disconnect();
                            echo "\n5. Desconectado do MikroTik\n";
                            echo "=== TESTE CONCLUÍDO ===\n";
                            
                            $logOutput = ob_get_clean();
                        }
                        
                    } elseif (isset($_POST['create_test_user'])) {
                        $testUsername = 'teste-' . rand(100, 999);
                        $testPassword = rand(1000, 9999);
                        
                        $mikrotik = new MikroTikHotspotManagerFixed(
                            $mikrotikConfig['host'],
                            $mikrotikConfig['username'],
                            $mikrotikConfig['password'],
                            $mikrotikConfig['port']
                        );
                        
                        $mikrotik->connect();
                        $result = $mikrotik->createHotspotUser($testUsername, $testPassword, 'default');
                        $mikrotik->disconnect();
                        
                        if ($result) {
                            $message = "✅ USUÁRIO DE TESTE CRIADO! Usuário: {$testUsername} | Senha: {$testPassword}";
                        } else {
                            $message = "❌ FALHA ao criar usuário de teste.";
                        }
                    }
                    
                } catch (Exception $e) {
                    $message = "❌ ERRO: " . $e->getMessage();
                    if ($logOutput) {
                        $logOutput .= "\n\nERRO CAPTURADO: " . $e->getMessage();
                    }
                }
            }
            ?>
            
            <!-- Mensagens -->
            <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, '❌') !== false ? 'alert-error' : (strpos($message, '✅') !== false || strpos($message, '🎉') !== false ? 'alert-success' : 'alert-info'); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Testes Básicos -->
            <div class="test-section">
                <h2>🔧 Testes Básicos</h2>
                
                <div class="step">
                    <div class="step-title">Passo 1: Testar Conexão</div>
                    <p>Verifica se o sistema consegue conectar ao MikroTik.</p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="test_connection" class="btn">🔗 Testar Conexão</button>
                    </form>
                </div>
                
                <div class="step">
                    <div class="step-title">Passo 2: Listar Usuários</div>
                    <p>Lista todos os usuários hotspot existentes no MikroTik.</p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="list_users" class="btn btn-info">📋 Listar Usuários</button>
                    </form>
                </div>
                
                <div class="step">
                    <div class="step-title">Passo 3: Criar Usuário de Teste</div>
                    <p>Cria um usuário temporário para testes de remoção.</p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="create_test_user" class="btn btn-success">➕ Criar Usuário de Teste</button>
                    </form>
                </div>
            </div>
            
            <!-- Usuários Encontrados -->
            <?php if ($testResults && isset($testResults['users'])): ?>
            <div class="test-section">
                <h2>👥 Usuários Encontrados (<?php echo $testResults['count']; ?>)</h2>
                
                <?php if (empty($testResults['users'])): ?>
                    <div class="alert alert-info">
                        📝 Nenhum usuário encontrado no MikroTik.
                    </div>
                <?php else: ?>
                    <div class="user-list">
                        <?php foreach ($testResults['users'] as $user): ?>
                        <div class="user-item">
                            <div>
                                <div class="user-info">
                                    👤 <?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?>
                                </div>
                                <div class="user-details">
                                    ID: <?php echo htmlspecialchars($user['id'] ?? 'N/A'); ?> | 
                                    Perfil: <?php echo htmlspecialchars($user['profile'] ?? 'N/A'); ?>
                                    <?php if (isset($user['password'])): ?>
                                    | Senha: <?php echo htmlspecialchars($user['password']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="username_to_remove" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                                <button type="submit" name="test_removal" class="btn" 
                                        onclick="return confirm('⚠️ Confirma a remoção do usuário <?php echo htmlspecialchars($user['name'] ?? ''); ?>?');">
                                    🗑️ Testar Remoção
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Teste Manual de Remoção -->
            <div class="test-section">
                <h2>🧪 Teste Manual de Remoção</h2>
                <p>Digite o nome exato do usuário que deseja remover para teste.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username_to_remove">Nome do Usuário:</label>
                        <input type="text" id="username_to_remove" name="username_to_remove" 
                               placeholder="Ex: teste-123, guest-101" required>
                    </div>
                    
                    <button type="submit" name="test_removal" class="btn"
                            onclick="return confirm('⚠️ Confirma o teste de remoção deste usuário?');">
                        🧪 Executar Teste de Remoção
                    </button>
                </form>
            </div>
            
            <!-- Log Output -->
            <?php if ($logOutput): ?>
            <div class="test-section">
                <h2>📋 Log Detalhado do Teste</h2>
                <div class="log-box"><?php echo htmlspecialchars($logOutput); ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Informações de Debug -->
            <div class="test-section">
                <h2>🔍 Informações de Debug</h2>
                <div class="step">
                    <div class="step-title">Configuração MikroTik:</div>
                    <ul>
                        <li><strong>Host:</strong> <?php echo htmlspecialchars($mikrotikConfig['host']); ?></li>
                        <li><strong>Porta:</strong> <?php echo htmlspecialchars($mikrotikConfig['port']); ?></li>
                        <li><strong>Usuário:</strong> <?php echo htmlspecialchars($mikrotikConfig['username']); ?></li>
                        <li><strong>Senha:</strong> <?php echo str_repeat('*', strlen($mikrotikConfig['password'])); ?></li>
                    </ul>
                </div>
                
                <div class="step">
                    <div class="step-title">Arquivos do Sistema:</div>
                    <ul>
                        <li><strong>config.php:</strong> <?php echo file_exists('config.php') ? '✅ Existe' : '❌ Não encontrado'; ?></li>
                        <li><strong>mikrotik_manager.php:</strong> <?php echo file_exists('mikrotik_manager.php') ? '✅ Existe' : '❌ Não encontrado'; ?></li>
                        <li><strong>mikrotik_manager_fixed.php:</strong> <?php echo file_exists('mikrotik_manager_fixed.php') ? '✅ Existe' : '❌ Não encontrado'; ?></li>
                        <li><strong>Extensão Sockets:</strong> <?php echo extension_loaded('sockets') ? '✅ Disponível' : '❌ Não disponível'; ?></li>
                    </ul>
                </div>
                
                <div class="step">
                    <div class="step-title">Instruções para Correção:</div>
                    <ol>
                        <li><strong>Substitua o arquivo mikrotik_manager.php</strong> pelo código corrigido (mikrotik_manager_fixed.php)</li>
                        <li><strong>Verifique as credenciais</strong> no arquivo config.php</li>
                        <li><strong>Teste a conexão</strong> usando o botão "Testar Conexão" acima</li>
                        <li><strong>Liste os usuários</strong> para ver se o sistema está funcionando</li>
                        <li><strong>Crie um usuário de teste</strong> e tente removê-lo</li>
                        <li><strong>Se ainda não funcionar</strong>, verifique os logs em logs/hotel_system.log</li>
                    </ol>
                </div>
            </div>
            
            <!-- Links Úteis -->
            <div class="test-section">
                <h2>🔗 Links Úteis</h2>
                <a href="index.php" class="btn btn-info">🏠 Voltar ao Sistema Principal</a>
                <a href="?clear_logs=1" class="btn" onclick="return confirm('Limpar todos os logs?');">🗑️ Limpar Logs</a>
                
                <?php if (isset($_GET['clear_logs'])): ?>
                    <?php 
                    $logFile = 'logs/hotel_system.log';
                    if (file_exists($logFile)) {
                        file_put_contents($logFile, '');
                        echo '<div class="alert alert-success">✅ Logs limpos com sucesso!</div>';
                    }
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Adicionar loading aos botões
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('button[type="submit"]');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<span class="loading"></span>' + originalText;
                    this.disabled = true;
                    
                    // Restaurar após 30 segundos se não houver resposta
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 30000);
                });
            });
        });
        
        // Auto-refresh para logs em tempo real (opcional)
        <?php if ($logOutput): ?>
        console.log('Log de teste capturado:');
        console.log(<?php echo json_encode($logOutput); ?>);
        <?php endif; ?>
    </script>
</body>
</html>