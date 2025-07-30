<?php
/**
 * ultra_simple_redirect_test.php - Teste Ultra Simples SEM AJAX
 * 
 * OBJETIVO: Teste que SEMPRE funciona, sem JavaScript, sem AJAX, só PHP puro
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 60);

require_once 'config.php';

// Verificar se os arquivos existem
$configOK = file_exists('config.php');
$mikrotikOK = file_exists('mikrotik_manager.php');

if ($mikrotikOK) {
    require_once 'mikrotik_manager.php';
}

/**
 * Classe de teste ultra simples
 */
class UltraSimpleTester {
    private $mikrotikConfig;
    private $results = [];
    
    public function __construct($mikrotikConfig) {
        $this->mikrotikConfig = $mikrotikConfig;
    }
    
    public function runAllTests() {
        $this->results = [];
        
        // Teste 1: Arquivos essenciais
        $this->results['files'] = $this->testFiles();
        
        // Teste 2: Configuração básica
        $this->results['config'] = $this->testConfig();
        
        // Teste 3: Conectividade MikroTik
        $this->results['connection'] = $this->testConnection();
        
        // Teste 4: Comandos básicos (se conectado)
        if ($this->results['connection']['status'] === 'SUCCESS') {
            $this->results['hotspot'] = $this->testHotspot();
            $this->results['dns'] = $this->testDNS();
            $this->results['services'] = $this->testServices();
            $this->results['walled_garden'] = $this->testWalledGarden();
        } else {
            // Se não conectou, marcar outros como não testados
            $this->results['hotspot'] = ['status' => 'NOT_TESTED', 'message' => 'Conexão falhou'];
            $this->results['dns'] = ['status' => 'NOT_TESTED', 'message' => 'Conexão falhou'];
            $this->results['services'] = ['status' => 'NOT_TESTED', 'message' => 'Conexão falhou'];
            $this->results['walled_garden'] = ['status' => 'NOT_TESTED', 'message' => 'Conexão falhou'];
        }
        
        return $this->results;
    }
    
    private function testFiles() {
        $missing = [];
        
        if (!file_exists('config.php')) $missing[] = 'config.php';
        if (!file_exists('mikrotik_manager.php')) $missing[] = 'mikrotik_manager.php';
        
        if (empty($missing)) {
            return ['status' => 'SUCCESS', 'message' => 'Todos os arquivos essenciais encontrados'];
        } else {
            return ['status' => 'ERROR', 'message' => 'Arquivos faltando: ' . implode(', ', $missing)];
        }
    }
    
    private function testConfig() {
        if (empty($this->mikrotikConfig['host'])) {
            return ['status' => 'ERROR', 'message' => 'Host do MikroTik não configurado'];
        }
        
        if (empty($this->mikrotikConfig['username'])) {
            return ['status' => 'ERROR', 'message' => 'Usuário do MikroTik não configurado'];
        }
        
        // Validar IP
        if (!filter_var($this->mikrotikConfig['host'], FILTER_VALIDATE_IP)) {
            return ['status' => 'WARNING', 'message' => 'IP do MikroTik pode ser inválido: ' . $this->mikrotikConfig['host']];
        }
        
        return [
            'status' => 'SUCCESS', 
            'message' => 'Configuração OK - ' . $this->mikrotikConfig['host'] . ':' . ($this->mikrotikConfig['port'] ?? 8728)
        ];
    }
    
    private function testConnection() {
        try {
            // Verificar se a classe existe
            if (!class_exists('MikroTikRawDataParser')) {
                return ['status' => 'ERROR', 'message' => 'Classe MikroTikRawDataParser não encontrada'];
            }
            
            // Teste de socket básico
            $socket = @fsockopen($this->mikrotikConfig['host'], $this->mikrotikConfig['port'] ?? 8728, $errno, $errstr, 5);
            
            if (!$socket) {
                return [
                    'status' => 'ERROR',
                    'message' => "Não foi possível conectar via TCP: $errstr ($errno)",
                    'solution' => 'Verificar se MikroTik está ligado e acessível'
                ];
            }
            
            fclose($socket);
            
            // Tentar conexão API
            try {
                $mikrotik = new MikroTikRawDataParser(
                    $this->mikrotikConfig['host'],
                    $this->mikrotikConfig['username'],
                    $this->mikrotikConfig['password'],
                    $this->mikrotikConfig['port'] ?? 8728
                );
                
                $mikrotik->connect();
                
                return ['status' => 'SUCCESS', 'message' => 'Conexão MikroTik estabelecida com sucesso'];
                
            } catch (Exception $e) {
                return [
                    'status' => 'ERROR',
                    'message' => 'Erro na autenticação: ' . $e->getMessage(),
                    'solution' => 'Verificar usuário e senha no config.php'
                ];
            }
            
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Erro geral: ' . $e->getMessage()];
        }
    }
    
    private function testHotspot() {
        try {
            $mikrotik = new MikroTikRawDataParser(
                $this->mikrotikConfig['host'],
                $this->mikrotikConfig['username'],
                $this->mikrotikConfig['password'],
                $this->mikrotikConfig['port'] ?? 8728
            );
            
            $mikrotik->connect();
            $mikrotik->writeRaw('/ip/hotspot/print');
            $data = $mikrotik->captureAllRawData();
            
            $serverCount = substr_count($data, '!re');
            
            if ($serverCount == 0) {
                return [
                    'status' => 'CRITICAL',
                    'message' => 'Nenhum servidor hotspot configurado',
                    'solution' => '/ip/hotspot/setup'
                ];
            }
            
            return ['status' => 'SUCCESS', 'message' => "$serverCount servidor(es) hotspot encontrado(s)"];
            
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Erro ao verificar hotspot: ' . $e->getMessage()];
        }
    }
    
    private function testDNS() {
        try {
            $mikrotik = new MikroTikRawDataParser(
                $this->mikrotikConfig['host'],
                $this->mikrotikConfig['username'],
                $this->mikrotikConfig['password'],
                $this->mikrotikConfig['port'] ?? 8728
            );
            
            $mikrotik->connect();
            $mikrotik->writeRaw('/ip/dns/print');
            $data = $mikrotik->captureAllRawData();
            
            $issues = [];
            
            if (strpos($data, '=allow-remote-requests=true') === false) {
                $issues[] = 'DNS remoto desabilitado';
            }
            
            if (strpos($data, '=servers=') === false || strpos($data, '=servers=""') !== false) {
                $issues[] = 'Servidores DNS não configurados';
            }
            
            if (empty($issues)) {
                return ['status' => 'SUCCESS', 'message' => 'DNS configurado corretamente'];
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => implode(', ', $issues),
                    'solution' => '/ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1'
                ];
            }
            
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Erro ao verificar DNS: ' . $e->getMessage()];
        }
    }
    
    private function testServices() {
        try {
            $mikrotik = new MikroTikRawDataParser(
                $this->mikrotikConfig['host'],
                $this->mikrotikConfig['username'],
                $this->mikrotikConfig['password'],
                $this->mikrotikConfig['port'] ?? 8728
            );
            
            $mikrotik->connect();
            $mikrotik->writeRaw('/ip/service/print');
            $data = $mikrotik->captureAllRawData();
            
            $httpEnabled = (strpos($data, '=name=www') !== false && strpos($data, '=disabled=false') !== false);
            
            if (!$httpEnabled) {
                return [
                    'status' => 'CRITICAL',
                    'message' => 'Serviço HTTP (www) não está habilitado',
                    'solution' => '/ip/service/enable www'
                ];
            }
            
            return ['status' => 'SUCCESS', 'message' => 'Serviço HTTP habilitado'];
            
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Erro ao verificar serviços: ' . $e->getMessage()];
        }
    }
    
    private function testWalledGarden() {
        try {
            $mikrotik = new MikroTikRawDataParser(
                $this->mikrotikConfig['host'],
                $this->mikrotikConfig['username'],
                $this->mikrotikConfig['password'],
                $this->mikrotikConfig['port'] ?? 8728
            );
            
            $mikrotik->connect();
            $mikrotik->writeRaw('/ip/hotspot/walled-garden/ip/print');
            $data = $mikrotik->captureAllRawData();
            
            $entryCount = substr_count($data, '!re');
            
            $essentialIPs = ['8.8.8.8', '1.1.1.1'];
            $missing = [];
            
            foreach ($essentialIPs as $ip) {
                if (strpos($data, "=dst-address=$ip") === false) {
                    $missing[] = $ip;
                }
            }
            
            if (!empty($missing)) {
                return [
                    'status' => 'WARNING',
                    'message' => "$entryCount entradas no walled garden. DNS não liberados: " . implode(', ', $missing),
                    'solution' => '/ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8'
                ];
            }
            
            return ['status' => 'SUCCESS', 'message' => "$entryCount entradas no walled garden incluindo DNS essenciais"];
            
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => 'Erro ao verificar walled garden: ' . $e->getMessage()];
        }
    }
    
    public function getResults() {
        return $this->results;
    }
    
    public function getSummary() {
        $total = count($this->results);
        $success = 0;
        $warnings = 0;
        $errors = 0;
        
        foreach ($this->results as $result) {
            switch ($result['status']) {
                case 'SUCCESS':
                    $success++;
                    break;
                case 'WARNING':
                    $warnings++;
                    break;
                case 'ERROR':
                case 'CRITICAL':
                    $errors++;
                    break;
            }
        }
        
        $successRate = $total > 0 ? round(($success / $total) * 100, 2) : 0;
        
        return [
            'total' => $total,
            'success' => $success,
            'warnings' => $warnings,
            'errors' => $errors,
            'success_rate' => $successRate,
            'status' => $successRate >= 80 ? 'EXCELLENT' : ($successRate >= 60 ? 'GOOD' : 'POOR')
        ];
    }
}

// Executar teste se for solicitado
$testResults = [];
$summary = [];
$showResults = false;

if (isset($_POST['run_test']) || isset($_GET['test'])) {
    $showResults = true;
    
    try {
        $tester = new UltraSimpleTester($mikrotikConfig);
        $testResults = $tester->runAllTests();
        $summary = $tester->getSummary();
    } catch (Exception $e) {
        $testResults = ['error' => ['status' => 'ERROR', 'message' => 'Erro crítico: ' . $e->getMessage()]];
        $summary = ['total' => 1, 'success' => 0, 'warnings' => 0, 'errors' => 1, 'success_rate' => 0, 'status' => 'POOR'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Ultra Simples - MikroTik Hotspot</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
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
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.2em;
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #0d47a1;
            border-color: #2196f3;
        }
        
        .alert-success {
            background: #e8f5e8;
            color: #2e7d32;
            border-color: #4caf50;
        }
        
        .alert-warning {
            background: #fff3e0;
            color: #ef6c00;
            border-color: #ff9800;
        }
        
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border-color: #f44336;
        }
        
        .btn {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(116, 185, 255, 0.4);
        }
        
        .btn-large {
            padding: 20px 40px;
            font-size: 18px;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .test-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 5px solid #ddd;
        }
        
        .test-card.success {
            border-color: #4caf50;
            background: #e8f5e8;
        }
        
        .test-card.warning {
            border-color: #ff9800;
            background: #fff3e0;
        }
        
        .test-card.error {
            border-color: #f44336;
            background: #ffebee;
        }
        
        .test-card.not-tested {
            border-color: #9e9e9e;
            background: #f5f5f5;
        }
        
        .test-header {
            font-weight: 600;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        
        .test-status {
            font-size: 0.9em;
            margin: 10px 0;
        }
        
        .test-solution {
            background: rgba(0,0,0,0.1);
            padding: 10px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            margin-top: 10px;
        }
        
        .summary {
            text-align: center;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
        }
        
        .summary.excellent {
            background: linear-gradient(135deg, #00b894, #00a085);
            color: white;
        }
        
        .summary.good {
            background: linear-gradient(135deg, #fdcb6e, #e17055);
            color: white;
        }
        
        .summary.poor {
            background: linear-gradient(135deg, #fd79a8, #e84393);
            color: white;
        }
        
        .summary h2 {
            margin: 0 0 15px 0;
            font-size: 2.5em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 25px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .commands {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .commands h4 {
            margin-top: 0;
            color: #3498db;
        }
        
        .command {
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            margin: 8px 0;
            cursor: pointer;
        }
        
        .command:hover {
            background: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Teste Ultra Simples</h1>
            <p>Diagnóstico básico sem AJAX - Sempre funciona!</p>
        </div>
        
        <div class="content">
            <?php if (!$showResults): ?>
                
                <div class="alert alert-info">
                    <h4>🎯 Sobre este teste:</h4>
                    <p>Este é um teste <strong>ultra simples</strong> que funciona sem JavaScript e sem AJAX. Ele verifica:</p>
                    <ul>
                        <li>✅ <strong>Arquivos</strong> - Se os arquivos necessários existem</li>
                        <li>✅ <strong>Configuração</strong> - Se o config.php está correto</li>
                        <li>✅ <strong>Conectividade</strong> - Se consegue conectar ao MikroTik</li>
                        <li>✅ <strong>Hotspot</strong> - Se o servidor hotspot está configurado</li>
                        <li>✅ <strong>DNS</strong> - Se o DNS está funcionando</li>
                        <li>✅ <strong>Serviços</strong> - Se o HTTP está habilitado</li>
                        <li>✅ <strong>Walled Garden</strong> - Se os sites essenciais estão liberados</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h4>⚙️ Configuração Atual:</h4>
                    <p><strong>Host:</strong> <?php echo htmlspecialchars($mikrotikConfig['host'] ?? 'NÃO CONFIGURADO'); ?></p>
                    <p><strong>Porta:</strong> <?php echo htmlspecialchars($mikrotikConfig['port'] ?? 'NÃO CONFIGURADO'); ?></p>
                    <p><strong>Usuário:</strong> <?php echo htmlspecialchars($mikrotikConfig['username'] ?? 'NÃO CONFIGURADO'); ?></p>
                    <p><strong>Arquivos:</strong> 
                        config.php <?php echo file_exists('config.php') ? '✅' : '❌'; ?> | 
                        mikrotik_manager.php <?php echo file_exists('mikrotik_manager.php') ? '✅' : '❌'; ?>
                    </p>
                </div>
                
                <div style="text-align: center; margin: 40px 0;">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="run_test" class="btn btn-large">
                            🚀 Executar Teste Completo
                        </button>
                    </form>
                </div>
                
            <?php else: ?>
                
                <!-- MOSTRAR RESULTADOS -->
                <div class="summary <?php echo strtolower($summary['status']); ?>">
                    <h2><?php echo $summary['success_rate']; ?>%</h2>
                    <p style="font-size: 1.3em;">
                        <?php 
                        switch ($summary['status']) {
                            case 'EXCELLENT':
                                echo '🎉 Excelente! Sistema configurado corretamente.';
                                break;
                            case 'GOOD':
                                echo '⚠️ Boa configuração, alguns ajustes necessários.';
                                break;
                            case 'POOR':
                                echo '❌ Problemas críticos encontrados.';
                                break;
                        }
                        ?>
                    </p>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $summary['success']; ?></div>
                            <div>Sucessos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $summary['warnings']; ?></div>
                            <div>Avisos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $summary['errors']; ?></div>
                            <div>Erros</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $summary['total']; ?></div>
                            <div>Total</div>
                        </div>
                    </div>
                </div>
                
                <div class="test-grid">
                    <?php foreach ($testResults as $testName => $result): ?>
                        <div class="test-card <?php echo strtolower($result['status']); ?>">
                            <div class="test-header">
                                <?php
                                $icons = [
                                    'files' => '📁',
                                    'config' => '⚙️',
                                    'connection' => '📡',
                                    'hotspot' => '🏨',
                                    'dns' => '🌐',
                                    'services' => '🔧',
                                    'walled_garden' => '🧱'
                                ];
                                
                                $names = [
                                    'files' => 'Arquivos Essenciais',
                                    'config' => 'Configuração',
                                    'connection' => 'Conexão MikroTik',
                                    'hotspot' => 'Servidor Hotspot',
                                    'dns' => 'Configuração DNS',
                                    'services' => 'Serviços Web',
                                    'walled_garden' => 'Walled Garden'
                                ];
                                
                                echo ($icons[$testName] ?? '🔧') . ' ' . ($names[$testName] ?? ucfirst($testName));
                                ?>
                            </div>
                            
                            <div class="test-status">
                                <strong><?php echo $result['status']; ?>:</strong>
                                <?php echo htmlspecialchars($result['message']); ?>
                            </div>
                            
                            <?php if (isset($result['solution'])): ?>
                                <div class="test-solution">
                                    <strong>💡 Solução:</strong><br>
                                    <?php echo htmlspecialchars($result['solution']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="commands">
                    <h4>🔧 Comandos Essenciais para Winbox:</h4>
                    
                    <div class="command" onclick="copyToClipboard('/ip/hotspot/print')" title="Clique para copiar">
                        /ip/hotspot/print
                    </div>
                    
                    <div class="command" onclick="copyToClipboard('/ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1')" title="Clique para copiar">
                        /ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1
                    </div>
                    
                    <div class="command" onclick="copyToClipboard('/ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8')" title="Clique para copiar">
                        /ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8
                    </div>
                    
                    <div class="command" onclick="copyToClipboard('/ip/service/enable www')" title="Clique para copiar">
                        /ip/service/enable www
                    </div>
                    
                    <div class="command" onclick="copyToClipboard('/ip/hotspot/walled-garden/ip/add dst-address=192.168.0.0/24 comment=\"EQUIPAMENTOS HOTEL\"')" title="Clique para copiar">
                        /ip/hotspot/walled-garden/ip/add dst-address=192.168.0.0/24 comment="EQUIPAMENTOS HOTEL"
                    </div>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="?" class="btn">🔄 Executar Novamente</a>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Feedback visual simples
                const notification = document.createElement('div');
                notification.textContent = '✅ Comando copiado!';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #4caf50;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    z-index: 1000;
                    font-weight: bold;
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 2000);
            }).catch(err => {
                alert('Comando para copiar: ' + text);
            });
        }
    </script>
</body>
</html>