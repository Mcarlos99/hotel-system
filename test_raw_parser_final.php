<?php
/**
 * test_raw_parser_final.php - Teste para versão v4.0 otimizada
 * 
 * CORRIGIDO: Atualizado para usar a nova classe MikroTikHotspotManagerFixed v4.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir arquivos necessários
require_once 'config.php';
require_once 'mikrotik_manager.php'; // Versão v4.0 otimizada

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Parser v4.0 - Sistema Hotel</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            margin: 0;
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
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
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
            border-left: 5px solid #3498db;
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
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-success { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); }
        .btn-warning { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 5px solid #f39c12;
        }
        
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .perf-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #3498db;
        }
        
        .perf-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .perf-number.excellent { color: #27ae60; }
        .perf-number.good { color: #f39c12; }
        .perf-number.moderate { color: #e67e22; }
        .perf-number.slow { color: #e74c3c; }
        
        .perf-label {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        .test-results {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
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
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .version-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .comparison-table th,
        .comparison-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .comparison-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-excellent { color: #27ae60; font-weight: bold; }
        .status-good { color: #f39c12; font-weight: bold; }
        .status-moderate { color: #e67e22; font-weight: bold; }
        .status-slow { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Teste Parser v4.0</h1>
            <span class="version-badge">Performance Critical Fix</span>
            <p>Teste da versão ultra-otimizada do sistema MikroTik</p>
        </div>
        
        <div class="content">
            <?php
            $message = null;
            $testResults = null;
            $performanceTest = null;
            $userList = null;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    if (isset($_POST['test_connection'])) {
                        $startTime = microtime(true);
                        
                        $result = testMikroTikRawParser($mikrotikConfig);
                        
                        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
                        
                        if ($result['success']) {
                            $responseTime = $result['response_time'];
                            
                            if ($responseTime < 1000) {
                                $message = "🎉 EXCELENTE! Conexão ultra-rápida em {$responseTime}ms (Total: {$totalTime}ms)";
                            } elseif ($responseTime < 3000) {
                                $message = "✅ BOM! Conexão rápida em {$responseTime}ms (Total: {$totalTime}ms)";
                            } elseif ($responseTime < 8000) {
                                $message = "⚠️ MODERADO. Conexão em {$responseTime}ms (Total: {$totalTime}ms)";
                            } else {
                                $message = "❌ LENTO! Conexão em {$responseTime}ms (Total: {$totalTime}ms)";
                            }
                        } else {
                            $message = "❌ FALHA NA CONEXÃO: " . $result['message'];
                        }
                        
                    } elseif (isset($_POST['test_performance'])) {
                        $iterations = (int)($_POST['iterations'] ?? 3);
                        $performanceTest = benchmarkMikroTik($mikrotikConfig, $iterations);
                        
                        $performance = $performanceTest['performance'];
                        $avgTime = $performanceTest['total_average'];
                        
                        switch ($performance) {
                            case 'excelente':
                                $message = "🚀 PERFORMANCE EXCELENTE! Média: {$avgTime}ms";
                                break;
                            case 'boa':
                                $message = "✅ BOA PERFORMANCE! Média: {$avgTime}ms";
                                break;
                            case 'moderada':
                                $message = "⚠️ PERFORMANCE MODERADA. Média: {$avgTime}ms";
                                break;
                            case 'lenta':
                                $message = "❌ PERFORMANCE LENTA! Média: {$avgTime}ms - Verificar rede";
                                break;
                        }
                        
                    } elseif (isset($_POST['list_users'])) {
                        $startTime = microtime(true);
                        
                        $mikrotik = new MikroTikHotspotManagerFixed(
                            $mikrotikConfig['host'],
                            $mikrotikConfig['username'],
                            $mikrotikConfig['password'],
                            $mikrotikConfig['port']
                        );
                        
                        $mikrotik->connect('list');
                        $users = $mikrotik->listHotspotUsers();
                        $mikrotik->disconnect();
                        
                        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                        
                        $userList = [
                            'users' => $users,
                            'count' => count($users),
                            'response_time' => $responseTime
                        ];
                        
                        if ($responseTime < 2000) {
                            $message = "🚀 LISTAGEM ULTRA-RÁPIDA! {count($users)} usuários em {$responseTime}ms";
                        } elseif ($responseTime < 5000) {
                            $message = "✅ LISTAGEM RÁPIDA! {count($users)} usuários em {$responseTime}ms";
                        } else {
                            $message = "⚠️ LISTAGEM LENTA! {count($users)} usuários em {$responseTime}ms";
                        }
                        
                    } elseif (isset($_POST['health_check'])) {
                        $startTime = microtime(true);
                        
                        $health = checkSystemHealth($mikrotikConfig);
                        
                        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
                        
                        $testResults = [
                            'health' => $health,
                            'total_time' => $totalTime
                        ];
                        
                        $status = $health['status'];
                        $responseTime = $health['response_time'];
                        
                        switch ($status) {
                            case 'excellent':
                                $message = "🎉 SISTEMA EXCELENTE! Health check em {$responseTime}ms";
                                break;
                            case 'good':
                                $message = "✅ SISTEMA BOM! Health check em {$responseTime}ms";
                                break;
                            case 'moderate':
                                $message = "⚠️ SISTEMA MODERADO. Health check em {$responseTime}ms";
                                break;
                            default:
                                $message = "❌ SISTEMA COM PROBLEMAS! Health check em {$responseTime}ms";
                                break;
                        }
                        
                    } elseif (isset($_POST['test_removal'])) {
                        $username = trim($_POST['username']);
                        
                        if (empty($username)) {
                            $message = "❌ Nome de usuário é obrigatório";
                        } else {
                            $removalResult = fastRemoveUser($mikrotikConfig, $username);
                            
                            if ($removalResult['success']) {
                                $time = $removalResult['response_time'];
                                $message = "🎉 REMOÇÃO EXPRESS! Usuário removido em {$time}ms";
                            } else {
                                $message = "❌ FALHA NA REMOÇÃO: " . ($removalResult['error'] ?? 'Erro desconhecido');
                            }
                        }
                        
                    } elseif (isset($_POST['create_test_user'])) {
                        $testUsername = 'teste-' . rand(100, 999);
                        $testPassword = rand(1000, 9999);
                        
                        $startTime = microtime(true);
                        
                        $mikrotik = new MikroTikHotspotManagerFixed(
                            $mikrotikConfig['host'],
                            $mikrotikConfig['username'],
                            $mikrotikConfig['password'],
                            $mikrotikConfig['port']
                        );
                        
                        $mikrotik->connect('create');
                        $result = $mikrotik->createHotspotUser($testUsername, $testPassword, 'default');
                        $mikrotik->disconnect();
                        
                        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                        
                        if ($result) {
                            $message = "✅ USUÁRIO CRIADO EXPRESS! {$testUsername} | {$testPassword} em {$responseTime}ms";
                        } else {
                            $message = "❌ FALHA ao criar usuário de teste";
                        }
                    }
                    
                } catch (Exception $e) {
                    $message = "❌ ERRO: " . $e->getMessage();
                }
            }
            ?>
            
            <!-- Mensagem de Status -->
            <?php if ($message): ?>
                <div class="alert <?php 
                    echo strpos($message, '❌') !== false ? 'alert-error' : 
                        (strpos($message, '⚠️') !== false ? 'alert-warning' : 
                        (strpos($message, '✅') !== false || strpos($message, '🎉') !== false || strpos($message, '🚀') !== false ? 'alert-success' : 'alert-info')); 
                ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Testes Básicos v4.0 -->
            <div class="test-section">
                <h2>🚀 Testes Performance v4.0</h2>
                
                <div class="performance-grid">
                    <div class="perf-card">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="test_connection" class="btn">⚡ Teste Conexão</button>
                        </form>
                        <div class="perf-label">Target: &lt; 1s</div>
                    </div>
                    
                    <div class="perf-card">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="health_check" class="btn btn-success">💓 Health Check</button>
                        </form>
                        <div class="perf-label">Target: &lt; 1s</div>
                    </div>
                    
                    <div class="perf-card">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="list_users" class="btn btn-warning">📋 Listar Usuários</button>
                        </form>
                        <div class="perf-label">Target: &lt; 2s</div>
                    </div>
                    
                    <div class="perf-card">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="create_test_user" class="btn btn-success">➕ Criar Teste</button>
                        </form>
                        <div class="perf-label">Target: &lt; 3s</div>
                    </div>
                </div>
            </div>
            
            <!-- Benchmark Avançado -->
            <div class="test-section">
                <h2>📊 Benchmark Avançado</h2>
                <p>Execute um benchmark completo para medir performance em múltiplas iterações.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="iterations">Número de Iterações:</label>
                        <select name="iterations" id="iterations" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="3">3 (Rápido)</option>
                            <option value="5" selected>5 (Recomendado)</option>
                            <option value="10">10 (Detalhado)</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="test_performance" class="btn btn-warning">📊 Executar Benchmark</button>
                </form>
            </div>
            
            <!-- Resultados do Benchmark -->
            <?php if ($performanceTest): ?>
            <div class="test-section">
                <h2>📈 Resultados do Benchmark v4.0</h2>
                
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Operação</th>
                            <th>Tempo Médio</th>
                            <th>Melhor</th>
                            <th>Pior</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performanceTest['averages'] as $operation => $avgTime): ?>
                        <tr>
                            <td><?php echo ucfirst($operation); ?></td>
                            <td><?php echo $avgTime; ?>ms</td>
                            <td><?php echo min($performanceTest['tests'][$operation]); ?>ms</td>
                            <td><?php echo max($performanceTest['tests'][$operation]); ?>ms</td>
                            <td class="status-<?php 
                                echo $avgTime < 1000 ? 'excellent' : ($avgTime < 2500 ? 'good' : ($avgTime < 5000 ? 'moderate' : 'slow'));
                            ?>">
                                <?php 
                                echo $avgTime < 1000 ? 'EXCELENTE' : ($avgTime < 2500 ? 'BOM' : ($avgTime < 5000 ? 'MODERADO' : 'LENTO'));
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="test-results">Performance Geral v4.0: <?php echo strtoupper($performanceTest['performance']); ?>
Tempo Médio Total: <?php echo $performanceTest['total_average']; ?>ms
Iterações Executadas: <?php echo $performanceTest['iterations']; ?>

Targets v4.0:
- Conexão: < 1000ms
- Listagem: < 2500ms  
- Health: < 1000ms

Detalhes por Operação:
<?php foreach ($performanceTest['tests'] as $operation => $times): ?>
<?php echo ucfirst($operation); ?>: <?php echo implode('ms, ', $times); ?>ms
<?php endforeach; ?>

Timestamp: <?php echo $performanceTest['timestamp']; ?>
Versão: <?php echo $performanceTest['version']; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Lista de Usuários -->
            <?php if ($userList): ?>
            <div class="test-section">
                <h2>👥 Usuários Encontrados (<?php echo $userList['count']; ?>)</h2>
                <p><strong>Tempo de Resposta:</strong> <?php echo $userList['response_time']; ?>ms</p>
                
                <?php if (empty($userList['users'])): ?>
                    <div class="alert alert-info">
                        📝 Nenhum usuário encontrado no MikroTik.
                    </div>
                <?php else: ?>
                    <div class="user-list">
                        <?php foreach ($userList['users'] as $user): ?>
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
                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                                <button type="submit" name="test_removal" class="btn btn-danger" 
                                        onclick="return confirm('⚠️ Testar remoção rápida do usuário <?php echo htmlspecialchars($user['name'] ?? ''); ?>?');">
                                    🗑️ Teste Remoção
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Teste de Remoção Manual -->
            <div class="test-section">
                <h2>🧪 Teste de Remoção Express</h2>
                <p>Teste a velocidade da remoção otimizada (Target: &lt; 3 segundos).</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Nome do Usuário:</label>
                        <input type="text" id="username" name="username" 
                               placeholder="Ex: teste-123, guest-101" required>
                    </div>
                    
                    <button type="submit" name="test_removal" class="btn btn-danger"
                            onclick="return confirm('⚠️ Confirma o teste de remoção express?');">
                        🗑️ Teste Remoção Express
                    </button>
                </form>
            </div>
            
            <!-- Health Check Results -->
            <?php if ($testResults): ?>
            <div class="test-section">
                <h2>💓 Resultados Health Check v4.0</h2>
                
                <div class="performance-grid">
                    <div class="perf-card">
                        <div class="perf-number <?php 
                            $responseTime = $testResults['health']['response_time'];
                            echo $responseTime < 1000 ? 'excellent' : ($responseTime < 2500 ? 'good' : ($responseTime < 5000 ? 'moderate' : 'slow'));
                        ?>">
                            <?php echo $testResults['health']['response_time']; ?>ms
                        </div>
                        <div class="perf-label">Tempo de Resposta</div>
                    </div>
                    
                    <div class="perf-card">
                        <div class="perf-number <?php echo $testResults['health']['connection'] ? 'excellent' : 'slow'; ?>">
                            <?php echo $testResults['health']['connection'] ? 'ON' : 'OFF'; ?>
                        </div>
                        <div class="perf-label">Conexão</div>
                    </div>
                    
                    <div class="perf-card">
                        <div class="perf-number excellent">
                            <?php echo $testResults['health']['user_count']; ?>
                        </div>
                        <div class="perf-label">Usuários</div>
                    </div>
                    
                    <div class="perf-card">
                        <div class="perf-number <?php 
                            $status = $testResults['health']['status'];
                            echo $status == 'excellent' ? 'excellent' : ($status == 'good' ? 'good' : ($status == 'moderate' ? 'moderate' : 'slow'));
                        ?>">
                            <?php echo strtoupper($testResults['health']['status']); ?>
                        </div>
                        <div class="perf-label">Status Geral</div>
                    </div>
                </div>
                
                <div class="test-results">Health Check v4.0 Detalhado:
Status: <?php echo $testResults['health']['status']; ?>
Conexão: <?php echo $testResults['health']['connection'] ? 'Ativa' : 'Inativa'; ?>
Usuários: <?php echo $testResults['health']['user_count']; ?>
Tempo de Resposta: <?php echo $testResults['health']['response_time']; ?>ms
Tempo Total: <?php echo $testResults['total_time']; ?>ms
Timestamp: <?php echo $testResults['health']['timestamp']; ?>

Avaliação v4.0:
<?php if ($testResults['health']['response_time'] < 1000): ?>
🎉 EXCELENTE - Sistema ultra-rápido
<?php elseif ($testResults['health']['response_time'] < 2500): ?>
✅ BOM - Sistema rápido  
<?php elseif ($testResults['health']['response_time'] < 5000): ?>
⚠️ MODERADO - Sistema aceitável
<?php else: ?>
❌ LENTO - Sistema precisa otimização
<?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Comparação de Performance -->
            <div class="test-section">
                <h2>📊 Comparação v4.0 vs Anterior</h2>
                
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Operação</th>
                            <th>Versão Anterior</th>
                            <th>v4.0 Target</th>
                            <th>Melhoria Esperada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Conexão</td>
                            <td>3-8 segundos</td>
                            <td>&lt; 1 segundo</td>
                            <td class="status-excellent">85% mais rápido</td>
                        </tr>
                        <tr>
                            <td>Listagem</td>
                            <td>8-15 segundos</td>
                            <td>&lt; 2.5 segundos</td>
                            <td class="status-excellent">80% mais rápido</td>
                        </tr>
                        <tr>
                            <td>Remoção</td>
                            <td>10+ segundos</td>
                            <td>&lt; 3 segundos</td>
                            <td class="status-excellent">70% mais rápido</td>
                        </tr>
                        <tr>
                            <td>Health Check</td>
                            <td>10+ segundos</td>
                            <td>&lt; 1 segundo</td>
                            <td class="status-excellent">90% mais rápido</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Informações do Sistema -->
            <div class="test-section">
                <h2>ℹ️ Informações v4.0</h2>
                
                <table class="comparison-table">
                    <tr>
                        <td><strong>Versão do Sistema:</strong></td>
                        <td>v4.0 - Performance Critical Fix</td>
                    </tr>
                    <tr>
                        <td><strong>PHP Sockets:</strong></td>
                        <td><?php echo extension_loaded('sockets') ? '✅ Disponível' : '❌ Não disponível'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Versão PHP:</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Host MikroTik:</strong></td>
                        <td><?php echo htmlspecialchars($mikrotikConfig['host']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Porta:</strong></td>
                        <td><?php echo htmlspecialchars($mikrotikConfig['port']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Usuário:</strong></td>
                        <td><?php echo htmlspecialchars($mikrotikConfig['username']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Otimizações v4.0:</strong></td>
                        <td>
                            ✅ Timeouts ultra-agressivos<br>
                            ✅ Cache global de conexão<br>
                            ✅ Parser direto otimizado<br>
                            ✅ Remoção express com fallback<br>
                            ✅ Logging com buffer
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Configurações Performance:</strong></td>
                        <td>
                            🔹 Máx 30 iterações (vs 200)<br>
                            🔹 Sleep 20ms (vs 50ms)<br>
                            🔹 Cache 60s<br>
                            🔹 Dados máx 30KB
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Otimizações Aplicadas -->
            <div class="alert alert-success">
                <h3>🚀 Otimizações v4.0 Aplicadas</h3>
                
                <div class="performance-grid">
                    <div class="perf-card">
                        <div class="perf-number excellent">4-6s</div>
                        <div class="perf-label">Timeouts Específicos</div>
                    </div>
                    
                    <div class="perf-card">
                        <div class="perf-number excellent">60s</div>
                        <div class="perf-label">Cache Conexão</div>
                    </div>
                    
                    <div class="perf-card">
                        <div class="perf-number excellent">30</div>
                        <div class="perf-label">Máx Iterações</div>
                    </div>
                    
                    <div class="perf-card">
                        <div class="perf-number excellent">3</div>
                        <div class="perf-label">Métodos Remoção</div>
                    </div>
                </div>
                
                <p><strong>Principais Melhorias:</strong></p>
                <ul>
                    <li>🔹 <strong>Timeouts Agressivos:</strong> Conexão 4s, Listagem 6s, Remoção 5s</li>
                    <li>🔹 <strong>Cache Global:</strong> Reutilização de conexão por 60 segundos</li>
                    <li>🔹 <strong>Parser Direto:</strong> Extração apenas de campos essenciais</li>
                    <li>🔹 <strong>Remoção Express:</strong> 3 métodos fallback automáticos</li>
                    <li>🔹 <strong>Configurações Extremas:</strong> Máximo 30 iterações vs 200 anterior</li>
                    <li>🔹 <strong>Logging Otimizado:</strong> Buffer de 10 entradas, zero impacto</li>
                </ul>
            </div>
            
            <!-- Validação de Sucesso -->
            <div class="alert alert-info">
                <h3>✅ Checklist de Validação v4.0</h3>
                
                <p>Para confirmar que as otimizações estão funcionando:</p>
                <ol>
                    <li>🧪 <strong>Teste Conexão:</strong> Deve completar em &lt; 1 segundo</li>
                    <li>💓 <strong>Health Check:</strong> Deve completar em &lt; 1 segundo</li>
                    <li>📋 <strong>Listar Usuários:</strong> Deve completar em &lt; 2.5 segundos</li>
                    <li>🗑️ <strong>Remoção Express:</strong> Deve completar em &lt; 3 segundos</li>
                    <li>📊 <strong>Benchmark:</strong> Performance 'boa' ou 'excelente'</li>
                </ol>
                
                <p><strong>Se algum teste falhar:</strong></p>
                <ul>
                    <li>🔍 Verificar latência de rede (ping)</li>
                    <li>🔍 Verificar carga do MikroTik</li>
                    <li>🔍 Verificar credenciais e permissões</li>
                    <li>🔍 Verificar logs em logs/hotel_system.log</li>
                </ul>
            </div>
            
            <!-- Links de Navegação -->
            <div class="alert alert-warning">
                <h3>🔗 Links Úteis</h3>
                
                <a href="index.php" class="btn btn-success">🏠 Sistema Principal</a>
                <a href="test_performance.php" class="btn btn-warning">📊 Teste Performance</a>
                <a href="?clear_logs=1" class="btn btn-danger">🗑️ Limpar Logs</a>
                <a href="?export_debug=1" class="btn">📁 Exportar Debug</a>
                
                <?php if (isset($_GET['clear_logs'])): ?>
                    <?php 
                    $logFile = 'logs/hotel_system.log';
                    if (file_exists($logFile)) {
                        file_put_contents($logFile, '');
                        echo '<div class="alert alert-success" style="margin-top: 15px;">✅ Logs limpos com sucesso!</div>';
                    }
                    ?>
                <?php endif; ?>
                
                <?php if (isset($_GET['export_debug'])): ?>
                    <?php
                    $debugData = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'version' => '4.0',
                        'system_info' => [
                            'php_version' => PHP_VERSION,
                            'sockets' => extension_loaded('sockets'),
                            'mikrotik_config' => [
                                'host' => $mikrotikConfig['host'],
                                'port' => $mikrotikConfig['port'],
                                'username' => $mikrotikConfig['username']
                            ]
                        ],
                        'diagnostic' => runFullDiagnostic($mikrotikConfig)
                    ];
                    
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="debug_v4_' . date('Y-m-d_H-i-s') . '.json"');
                    echo json_encode($debugData, JSON_PRETTY_PRINT);
                    exit;
                    ?>
                <?php endif; ?>
            </div>
            
            <!-- Status Final -->
            <div class="alert <?php 
                // Determinar status geral baseado nos testes
                if ($performanceTest) {
                    echo $performanceTest['performance'] === 'excelente' ? 'alert-success' : 
                         ($performanceTest['performance'] === 'boa' ? 'alert-success' : 'alert-warning');
                } elseif ($testResults) {
                    echo $testResults['health']['status'] === 'excellent' ? 'alert-success' :
                         ($testResults['health']['status'] === 'good' ? 'alert-success' : 'alert-warning');
                } else {
                    echo 'alert-info';
                }
            ?>">
                <h3>🎯 Status do Sistema v4.0</h3>
                
                <?php if ($performanceTest || $testResults): ?>
                    <?php if ($performanceTest): ?>
                        <p><strong>Performance Geral:</strong> <?php echo strtoupper($performanceTest['performance']); ?></p>
                        <p><strong>Tempo Médio:</strong> <?php echo $performanceTest['total_average']; ?>ms</p>
                    <?php endif; ?>
                    
                    <?php if ($testResults): ?>
                        <p><strong>Health Status:</strong> <?php echo strtoupper($testResults['health']['status']); ?></p>
                        <p><strong>Conexão:</strong> <?php echo $testResults['health']['connection'] ? 'Ativa' : 'Inativa'; ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Recomendação:</strong> 
                    <?php 
                    if (($performanceTest && $performanceTest['performance'] === 'excelente') || 
                        ($testResults && $testResults['health']['status'] === 'excellent')) {
                        echo "🎉 Sistema funcionando perfeitamente! Pode usar em produção.";
                    } elseif (($performanceTest && $performanceTest['performance'] === 'boa') || 
                             ($testResults && $testResults['health']['status'] === 'good')) {
                        echo "✅ Sistema funcionando bem. Monitorar performance regularmente.";
                    } else {
                        echo "⚠️ Sistema funcionando mas pode ser otimizado. Verificar rede e configurações.";
                    }
                    ?>
                    </p>
                <?php else: ?>
                    <p><strong>Status:</strong> Sistema v4.0 carregado e pronto para testes.</p>
                    <p><strong>Próximo Passo:</strong> Execute o "Teste Conexão" para validar o funcionamento.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Loading nos botões
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('button[type="submit"]');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<span class="loading"></span>' + originalText;
                    this.disabled = true;
                    
                    // Restaurar após 30 segundos
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 30000);
                });
            });
        });
        
        // Função para mostrar notificação
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
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }
        
        // Auto-validação de performance
        function validatePerformance() {
            const performanceElements = document.querySelectorAll('.perf-number');
            let excellent = 0;
            let total = 0;
            
            performanceElements.forEach(element => {
                if (element.classList.contains('excellent')) {
                    excellent++;
                }
                total++;
            });
            
            if (total > 0) {
                const ratio = excellent / total;
                if (ratio >= 0.8) {
                    showNotification('🎉 Performance excelente detectada!', 'success');
                } else if (ratio >= 0.6) {
                    showNotification('✅ Boa performance detectada!', 'success');
                } else if (ratio >= 0.4) {
                    showNotification('⚠️ Performance moderada detectada', 'warning');
                } else {
                    showNotification('❌ Performance baixa detectada', 'error');
                }
            }
        }
        
        // Executar validação após carregamento
        setTimeout(validatePerformance, 1000);
        
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
            
            .perf-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            }
            
            .alert {
                animation: fadeIn 0.5s ease-in;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
        
        console.log('🧪 Sistema de Teste v4.0 carregado');
        console.log('🚀 Execute os testes para validar otimizações');
        console.log('🎯 Targets: Conexão <1s, Listagem <2.5s, Remoção <3s');
        
        // Debug da versão
        <?php if ($performanceTest): ?>
        console.log('📊 Benchmark executado:', <?php echo json_encode($performanceTest); ?>);
        <?php endif; ?>
        
        <?php if ($testResults): ?>
        console.log('💓 Health check executado:', <?php echo json_encode($testResults); ?>);
        <?php endif; ?>
        
        // Detectar se é primeira execução
        if (!localStorage.getItem('v4_first_run')) {
            setTimeout(() => {
                showNotification('🚀 Bem-vindo ao Sistema v4.0! Execute os testes para validar as otimizações.', 'info');
                localStorage.setItem('v4_first_run', 'true');
            }, 2000);
        }
    </script>
</body>
</html>

<?php
// Funções auxiliares corrigidas para v4.0

function testRawDataParser($mikrotikConfig) {
    return testMikroTikRawParser($mikrotikConfig);
}

// Cleanup final
if (function_exists('opcache_reset')) {
    opcache_reset();
}
?>