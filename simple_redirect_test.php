<?php
/**
 * simple_redirect_test.php - Teste Simples e Funcional de Redirecionamento
 * 
 * OBJETIVO: Teste básico que sempre funciona, focado em identificar
 * problemas de redirecionamento sem complexidade desnecessária
 */

require_once 'config.php';
require_once 'mikrotik_manager.php';

// Inicializar sessão
session_start();

class SimpleRedirectTester {
    private $mikrotik;
    private $results = [];
    
    public function __construct($mikrotikConfig) {
        try {
            $this->mikrotik = new MikroTikRawDataParser(
                $mikrotikConfig['host'],
                $mikrotikConfig['username'],
                $mikrotikConfig['password'],
                $mikrotikConfig['port']
            );
        } catch (Exception $e) {
            $this->mikrotik = null;
        }
    }
    
    public function runTest($testName) {
        switch ($testName) {
            case 'connection_test':
                return $this->testConnection();
            case 'hotspot_test':
                return $this->testHotspot();
            case 'dns_test':
                return $this->testDNS();
            case 'walled_garden_test':
                return $this->testWalledGarden();
            case 'firewall_test':
                return $this->testFirewall();
            case 'service_test':
                return $this->testServices();
            case 'user_test':
                return $this->testUserOperations();
            case 'final_test':
                return $this->testFinalCheck();
            default:
                return ['status' => 'ERROR', 'message' => 'Teste desconhecido'];
        }
    }
    
    private function testConnection() {
        if (!$this->mikrotik) {
            return [
                'status' => 'ERROR',
                'message' => 'Não foi possível conectar ao MikroTik',
                'solution' => 'Verifique IP, usuário e senha no config.php'
            ];
        }
        
        try {
            $this->mikrotik->connect();
            
            return [
                'status' => 'SUCCESS',
                'message' => 'Conexão com MikroTik estabelecida com sucesso'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'ERROR',
                'message' => 'Erro na conexão: ' . $e->getMessage(),
                'solution' => 'Verificar conectividade de rede e credenciais'
            ];
        }
    }
    
    private function testHotspot() {
        if (!$this->mikrotik) {
            return ['status' => 'ERROR', 'message' => 'MikroTik não conectado'];
        }
        
        try {
            $this->mikrotik->writeRaw('/ip/hotspot/print');
            $rawData = $this->mikrotik->captureAllRawData();
            
            $serverCount = substr_count($rawData, '!re');
            
            if ($serverCount == 0) {
                return [
                    'status' => 'CRITICAL',
                    'message' => 'Nenhum servidor hotspot configurado',
                    'solution' => 'Execute /ip/hotspot/setup no Winbox'
                ];
            }
            
            // Verificar se há servidores ativos
            $hasActive = (strpos($rawData, '=disabled=false') !== false || 
                         strpos($rawData, '=disabled=') === false);
            
            if (!$hasActive) {
                return [
                    'status' => 'WARNING',
                    'message' => 'Servidor hotspot pode estar desabilitado',
                    'solution' => 'Verificar se servidor está habilitado'
                ];
            }
            
            return [
                'status' => 'SUCCESS',
                'message' => "Encontrados {$serverCount} servidor(es) hotspot ativo(s)"
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar hotspot: ' . $e->getMessage()
            ];
        }
    }
    
    private function testDNS() {
        if (!$this->mikrotik) {
            return ['status' => 'ERROR', 'message' => 'MikroTik não conectado'];
        }
        
        try {
            $this->mikrotik->writeRaw('/ip/dns/print');
            $rawData = $this->mikrotik->captureAllRawData();
            
            $issues = [];
            $solutions = [];
            
            // Verificar allow-remote-requests
            if (strpos($rawData, '=allow-remote-requests=true') === false) {
                $issues[] = 'DNS remoto não habilitado';
                $solutions[] = '/ip/dns/set allow-remote-requests=yes';
            }
            
            // Verificar servidores DNS
            if (strpos($rawData, '=servers=') === false || 
                strpos($rawData, '=servers=""') !== false) {
                $issues[] = 'Servidores DNS não configurados';
                $solutions[] = '/ip/dns/set servers=8.8.8.8,1.1.1.1';
            }
            
            if (empty($issues)) {
                return [
                    'status' => 'SUCCESS',
                    'message' => 'DNS configurado corretamente'
                ];
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => 'Problemas na configuração DNS: ' . implode(', ', $issues),
                    'solutions' => $solutions
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar DNS: ' . $e->getMessage()
            ];
        }
    }
    
    private function testWalledGarden() {
        if (!$this->mikrotik) {
            return ['status' => 'ERROR', 'message' => 'MikroTik não conectado'];
        }
        
        try {
            // Verificar IPs liberados
            $this->mikrotik->writeRaw('/ip/hotspot/walled-garden/ip/print');
            $ipData = $this->mikrotik->captureAllRawData();
            
            $essentialIPs = ['8.8.8.8', '1.1.1.1'];
            $missingIPs = [];
            
            foreach ($essentialIPs as $ip) {
                if (strpos($ipData, "=dst-address={$ip}") === false) {
                    $missingIPs[] = $ip;
                }
            }
            
            if (!empty($missingIPs)) {
                return [
                    'status' => 'WARNING',
                    'message' => 'Servidores DNS não liberados: ' . implode(', ', $missingIPs),
                    'solutions' => [
                        '/ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8',
                        '/ip/hotspot/walled-garden/ip/add dst-address=1.1.1.1'
                    ]
                ];
            }
            
            return [
                'status' => 'SUCCESS',
                'message' => 'Walled Garden configurado com servidores DNS essenciais'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar Walled Garden: ' . $e->getMessage()
            ];
        }
    }
    
    private function testFirewall() {
        if (!$this->mikrotik) {
            return ['status' => 'ERROR', 'message' => 'MikroTik não conectado'];
        }
        
        try {
            // Verificar regras que podem bloquear HTTP
            $this->mikrotik->writeRaw('/ip/firewall/filter/print');
            $filterData = $this->mikrotik->captureAllRawData();
            
            // Procurar regras que bloqueiam porta 80/443
            $blockingRules = 0;
            if (strpos($filterData, '=action=drop') !== false && 
                (strpos($filterData, '=dst-port=80') !== false || 
                 strpos($filterData, '=dst-port=443') !== false)) {
                $blockingRules++;
            }
            
            if ($blockingRules > 0) {
                return [
                    'status' => 'WARNING',
                    'message' => 'Possíveis regras de firewall bloqueando HTTP/HTTPS',
                    'solution' => 'Revisar regras de firewall para portas 80 e 443'
                ];
            }
            
            return [
                'status' => 'SUCCESS',
                'message' => 'Firewall não parece estar bloqueando tráfego web'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'WARNING',
                'message' => 'Não foi possível verificar firewall completamente'
            ];
        }
    }
    
    private function testServices() {
        if (!$this->mikrotik) {
            return ['status' => 'ERROR', 'message' => 'MikroTik não conectado'];
        }
        
        try {
            $this->mikrotik->writeRaw('/ip/service/print');
            $serviceData = $this->mikrotik->captureAllRawData();
            
            $httpEnabled = false;
            $httpsEnabled = false;
            
            // Verificar serviço HTTP (www)
            if (strpos($serviceData, '=name=www') !== false) {
                if (strpos($serviceData, '=disabled=false') !== false || 
                    strpos($serviceData, '=disabled=') === false) {
                    $httpEnabled = true;
                }
            }
            
            // Verificar serviço HTTPS (www-ssl)
            if (strpos($serviceData, '=name=www-ssl') !== false) {
                if (strpos($serviceData, '=disabled=false') !== false || 
                    strpos($serviceData, '=disabled=') === false) {
                    $httpsEnabled = true;
                }
            }
            
            if (!$httpEnabled) {
                return [
                    'status' => 'CRITICAL',
                    'message' => 'Serviço HTTP está desabilitado',
                    'solution' => '/ip/service/enable www'
                ];
            }
            
            return [
                'status' => 'SUCCESS',
                'message' => 'Serviços web habilitados: HTTP' . ($httpsEnabled ? ' e HTTPS' : '')
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'WARNING',
                'message' => 'Não foi possível verificar serviços web'
            ];
        }
    }
    
    private function testUserOperations() {
        if (!$this->mikrotik) {
            return ['status' => 'ERROR', 'message' => 'MikroTik não conectado'];
        }
        
        try {
            // Criar usuário de teste simples
            $testUser = 'test-' . rand(100, 999);
            $testPass = rand(1000, 9999);
            
            $createResult = $this->mikrotik->createHotspotUser($testUser, $testPass, 'default', '01:00:00');
            
            if ($createResult) {
                // Tentar remover o usuário
                sleep(1);
                $removeResult = $this->mikrotik->removeHotspotUser($testUser);
                
                if ($removeResult) {
                    return [
                        'status' => 'SUCCESS',
                        'message' => 'Criação e remoção de usuários funcionando'
                    ];
                } else {
                    return [
                        'status' => 'WARNING',
                        'message' => 'Usuário criado mas falha na remoção',
                        'solution' => 'Remover usuário manualmente: ' . $testUser
                    ];
                }
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => 'Falha na criação de usuário teste',
                    'solution' => 'Verificar perfil padrão em /ip/hotspot/user/profile'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'WARNING',
                'message' => 'Erro no teste de usuário: ' . $e->getMessage()
            ];
        }
    }
    
    private function testFinalCheck() {
        if (!$this->mikrotik) {
            return ['status' => 'ERROR', 'message' => 'MikroTik não conectado'];
        }
        
        try {
            // Verificar usuários ativos
            $this->mikrotik->writeRaw('/ip/hotspot/active/print');
            $activeData = $this->mikrotik->captureAllRawData();
            $activeUsers = substr_count($activeData, '!re');
            
            // Verificar logs recentes do hotspot
            $this->mikrotik->writeRaw('/log/print', ['=count=5']);
            $logData = $this->mikrotik->captureAllRawData();
            $hotspotLogs = substr_count($logData, 'hotspot');
            
            $message = "Sistema hotspot operacional. ";
            $message .= "Usuários ativos: {$activeUsers}. ";
            $message .= "Logs recentes: {$hotspotLogs} entradas.";
            
            return [
                'status' => 'SUCCESS',
                'message' => $message,
                'details' => [
                    'active_users' => $activeUsers,
                    'recent_logs' => $hotspotLogs
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'WARNING',
                'message' => 'Verificação final parcial: ' . $e->getMessage()
            ];
        }
    }
}

// PROCESSAMENTO AJAX
if (isset($_GET['action']) && $_GET['action'] === 'run_test') {
    header('Content-Type: application/json');
    
    $testName = $_GET['test'] ?? '';
    
    if (empty($testName)) {
        echo json_encode(['status' => 'ERROR', 'message' => 'Nome do teste não especificado']);
        exit;
    }
    
    try {
        $tester = new SimpleRedirectTester($mikrotikConfig);
        $result = $tester->runTest($testName);
        
        // Armazenar resultado na sessão
        $_SESSION['test_results'][$testName] = $result;
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'ERROR',
            'message' => 'Erro ao executar teste: ' . $e->getMessage()
        ]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_summary') {
    header('Content-Type: application/json');
    
    $results = $_SESSION['test_results'] ?? [];
    
    if (empty($results)) {
        echo json_encode([
            'success_rate' => 0,
            'message' => 'Nenhum teste executado',
            'recommendations' => []
        ]);
        exit;
    }
    
    $total = count($results);
    $success = 0;
    $warnings = 0;
    $errors = 0;
    $recommendations = [];
    
    foreach ($results as $testName => $result) {
        switch ($result['status']) {
            case 'SUCCESS':
                $success++;
                break;
            case 'WARNING':
                $warnings++;
                if (isset($result['solution'])) {
                    $recommendations[] = $result['solution'];
                }
                if (isset($result['solutions'])) {
                    $recommendations = array_merge($recommendations, $result['solutions']);
                }
                break;
            case 'ERROR':
            case 'CRITICAL':
                $errors++;
                if (isset($result['solution'])) {
                    $recommendations[] = $result['solution'];
                }
                break;
        }
    }
    
    $successRate = round(($success / $total) * 100, 2);
    
    $message = '';
    if ($successRate >= 80) {
        $message = 'Excelente! Sistema hotspot configurado corretamente para redirecionamento.';
    } elseif ($successRate >= 60) {
        $message = 'Boa configuração, alguns ajustes recomendados.';
    } else {
        $message = 'Problemas críticos encontrados. Redirecionamento pode não funcionar.';
    }
    
    echo json_encode([
        'success_rate' => $successRate,
        'success_tests' => $success,
        'warning_tests' => $warnings,
        'error_tests' => $errors,
        'total_tests' => $total,
        'message' => $message,
        'recommendations' => array_unique($recommendations),
        'results' => $results
    ]);
    exit;
}

// Limpar resultados se solicitado
if (isset($_GET['action']) && $_GET['action'] === 'clear_results') {
    $_SESSION['test_results'] = [];
    echo json_encode(['success' => true]);
    exit;
}

// Se não for AJAX, mostrar interface web
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Simples de Redirecionamento - MikroTik Hotspot</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.2em;
        }
        
        .test-container {
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
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
            transition: all 0.3s ease;
        }
        
        .test-card.running {
            border-color: #2196f3;
            background: #e3f2fd;
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
        
        .test-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .test-status {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ddd;
        }
        
        .test-status.running {
            background: #2196f3;
            animation: pulse 1.5s infinite;
        }
        
        .test-status.success { background: #4caf50; }
        .test-status.warning { background: #ff9800; }
        .test-status.error { background: #f44336; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .progress-container {
            display: none;
            margin: 30px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.5s ease;
        }
        
        .results-summary {
            display: none;
            margin: 30px 0;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
        }
        
        .command-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .command {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 10px 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            position: relative;
        }
        
        .copy-btn {
            position: absolute;
            top: 5px;
            right: 10px;
            background: #34495e;
            color: white;
            border: none;
            padding: 3px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
        }
        
        .recommendations {
            background: #f0f8ff;
            border-left: 5px solid #2196f3;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Teste Simples de Redirecionamento</h1>
            <p>Diagnóstico rápido e funcional para problemas de hotspot</p>
        </div>
        
        <div class="test-container">
            <div class="alert alert-info">
                <h4>🎯 Sobre este teste:</h4>
                <p>Este é um teste <strong>simplificado e confiável</strong> que verifica os componentes essenciais para o redirecionamento automático funcionar:</p>
                <ul>
                    <li>✅ <strong>Conexão</strong> - Comunicação com o MikroTik</li>
                    <li>✅ <strong>Hotspot</strong> - Servidor configurado e ativo</li>
                    <li>✅ <strong>DNS</strong> - Resolução de nomes funcionando</li>
                    <li>✅ <strong>Walled Garden</strong> - Sites essenciais liberados</li>
                    <li>✅ <strong>Firewall</strong> - Não bloqueando tráfego web</li>
                    <li>✅ <strong>Serviços</strong> - HTTP habilitado</li>
                    <li>✅ <strong>Usuários</strong> - Criação/remoção funcionando</li>
                    <li>✅ <strong>Status</strong> - Verificação final do sistema</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <button class="btn btn-large" onclick="startTest()" id="startBtn">
                    🚀 Iniciar Teste Completo
                </button>
                <button class="btn" onclick="clearResults()" style="background: #6c757d;">
                    🧹 Limpar Resultados
                </button>
            </div>
            
            <div class="progress-container" id="progressContainer">
                <h4>Progresso do Teste:</h4>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <p id="progressText">Preparando testes...</p>
            </div>
            
            <div class="test-grid">
                <div class="test-card" id="card-connection">
                    <div class="test-header">
                        <h3>📡 Conexão MikroTik</h3>
                        <div class="test-status" id="status-connection"></div>
                    </div>
                    <p>Verificando conectividade com o MikroTik</p>
                    <div class="test-result" id="result-connection"></div>
                </div>
                
                <div class="test-card" id="card-hotspot">
                    <div class="test-header">
                        <h3>🏨 Servidor Hotspot</h3>
                        <div class="test-status" id="status-hotspot"></div>
                    </div>
                    <p>Verificando configuração do servidor hotspot</p>
                    <div class="test-result" id="result-hotspot"></div>
                </div>
                
                <div class="test-card" id="card-dns">
                    <div class="test-header">
                        <h3>🌐 Configuração DNS</h3>
                        <div class="test-status" id="status-dns"></div>
                    </div>
                    <p>Testando resolução de nomes</p>
                    <div class="test-result" id="result-dns"></div>
                </div>
                
                <div class="test-card" id="card-walled-garden">
                    <div class="test-header">
                        <h3>🧱 Walled Garden</h3>
                        <div class="test-status" id="status-walled-garden"></div>
                    </div>
                    <p>Verificando sites liberados</p>
                    <div class="test-result" id="result-walled-garden"></div>
                </div>
                
                <div class="test-card" id="card-firewall">
                    <div class="test-header">
                        <h3>🛡️ Firewall</h3>
                        <div class="test-status" id="status-firewall"></div>
                    </div>
                    <p>Analisando regras de firewall</p>
                    <div class="test-result" id="result-firewall"></div>
                </div>
                
                <div class="test-card" id="card-service">
                    <div class="test-header">
                        <h3>⚙️ Serviços Web</h3>
                        <div class="test-status" id="status-service"></div>
                    </div>
                    <p>Verificando serviços HTTP/HTTPS</p>
                    <div class="test-result" id="result-service"></div>
                </div>
                
                <div class="test-card" id="card-user">
                    <div class="test-header">
                        <h3>👤 Operações de Usuário</h3>
                        <div class="test-status" id="status-user"></div>
                    </div>
                    <p>Testando criação/remoção de usuários</p>
                    <div class="test-result" id="result-user"></div>
                </div>
                
                <div class="test-card" id="card-final">
                    <div class="test-header">
                        <h3>✅ Verificação Final</h3>
                        <div class="test-status" id="status-final"></div>
                    </div>
                    <p>Status geral do sistema</p>
                    <div class="test-result" id="result-final"></div>
                </div>
            </div>
            
            <div class="results-summary" id="resultsSummary">
                <h3>📊 Resultado Final</h3>
                <div id="summaryContent"></div>
            </div>
            
            <div class="recommendations" id="recommendationsBox" style="display: none;">
                <h4>💡 Comandos Recomendados para Winbox:</h4>
                <div id="recommendationsList"></div>
            </div>
            
            <div class="command-list">
                <h4>🔧 Comandos Essenciais (clique para copiar):</h4>
                
                <div class="command" onclick="copyCommand('/ip/hotspot/print')">
                    /ip/hotspot/print
                    <button class="copy-btn">📋</button>
                </div>
                
                <div class="command" onclick="copyCommand('/ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1')">
                    /ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1
                    <button class="copy-btn">📋</button>
                </div>
                
                <div class="command" onclick="copyCommand('/ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8')">
                    /ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8
                    <button class="copy-btn">📋</button>
                </div>
                
                <div class="command" onclick="copyCommand('/ip/service/enable www')">
                    /ip/service/enable www
                    <button class="copy-btn">📋</button>
                </div>
                
                <div class="command" onclick="copyCommand('/log/print follow where topics~\"hotspot\"')">
                    /log/print follow where topics~"hotspot"
                    <button class="copy-btn">📋</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const tests = [
            'connection_test',
            'hotspot_test', 
            'dns_test',
            'walled_garden_test',
            'firewall_test',
            'service_test',
            'user_test',
            'final_test'
        ];
        
        const testNames = [
            'connection',
            'hotspot',
            'dns', 
            'walled-garden',
            'firewall',
            'service',
            'user',
            'final'
        ];
        
        let currentTest = 0;
        let testRunning = false;
        
        function startTest() {
            if (testRunning) return;
            
            testRunning = true;
            currentTest = 0;
            
            // Preparar UI
            document.getElementById('startBtn').disabled = true;
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('resultsSummary').style.display = 'none';
            document.getElementById('recommendationsBox').style.display = 'none';
            
            // Resetar cards
            testNames.forEach(name => {
                const card = document.getElementById('card-' + name);
                const status = document.getElementById('status-' + name);
                const result = document.getElementById('result-' + name);
                
                card.className = 'test-card';
                status.className = 'test-status';
                result.innerHTML = '';
            });
            
            runNextTest();
        }
        
        function runNextTest() {
            if (currentTest >= tests.length) {
                finishTest();
                return;
            }
            
            const testName = tests[currentTest];
            const cardName = testNames[currentTest];
            
            // Atualizar progresso
            const progress = ((currentTest + 1) / tests.length) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
            document.getElementById('progressText').textContent = `Executando: ${testName} (${currentTest + 1}/${tests.length})`;
            
            // Marcar como executando
            document.getElementById('card-' + cardName).className = 'test-card running';
            document.getElementById('status-' + cardName).className = 'test-status running';
            
            // Executar teste
            fetch(`?action=run_test&test=${testName}`)
                .then(response => response.json())
                .then(data => {
                    updateTestResult(cardName, data);
                    currentTest++;
                    setTimeout(runNextTest, 1000);
                })
                .catch(error => {
                    updateTestResult(cardName, {
                        status: 'ERROR',
                        message: 'Erro na requisição: ' + error
                    });
                    currentTest++;
                    setTimeout(runNextTest, 1000);
                });
        }
        
        function updateTestResult(cardName, result) {
            const card = document.getElementById('card-' + cardName);
            const status = document.getElementById('status-' + cardName);
            const resultDiv = document.getElementById('result-' + cardName);
            
            const statusClass = result.status.toLowerCase();
            
            card.className = 'test-card ' + statusClass;
            status.className = 'test-status ' + statusClass;
            
            let resultHtml = `<strong>${result.status}:</strong> ${result.message}`;
            
            if (result.solution) {
                resultHtml += `<br><small><strong>Solução:</strong> ${result.solution}</small>`;
            }
            
            if (result.solutions && result.solutions.length > 0) {
                resultHtml += '<br><small><strong>Soluções:</strong></small><ul>';
                result.solutions.forEach(sol => {
                    resultHtml += `<li><small>${sol}</small></li>`;
                });
                resultHtml += '</ul>';
            }
            
            resultDiv.innerHTML = resultHtml;
        }
        
        function finishTest() {
            testRunning = false;
            document.getElementById('startBtn').disabled = false;
            document.getElementById('progressText').textContent = 'Gerando resumo final...';
            
            fetch('?action=get_summary')
                .then(response => response.json())
                .then(data => {
                    showSummary(data);
                })
                .catch(error => {
                    console.error('Erro ao obter resumo:', error);
                });
        }
        
        function showSummary(data) {
            const summary = document.getElementById('resultsSummary');
            const content = document.getElementById('summaryContent');
            
            let summaryClass = 'results-summary';
            if (data.success_rate >= 80) {
                summaryClass += ' alert-success';
            } else if (data.success_rate >= 60) {
                summaryClass += ' alert-warning';
            } else {
                summaryClass += ' alert-danger';
            }
            
            summary.className = summaryClass;
            
            content.innerHTML = `
                <div style="font-size: 3em; margin: 15px 0;">${data.success_rate}%</div>
                <p style="font-size: 1.2em; margin: 15px 0;">${data.message}</p>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 25px;">
                    <div>
                        <div style="font-size: 2em; color: #4caf50;">${data.success_tests}</div>
                        <div>Sucessos</div>
                    </div>
                    <div>
                        <div style="font-size: 2em; color: #ff9800;">${data.warning_tests}</div>
                        <div>Avisos</div>
                    </div>
                    <div>
                        <div style="font-size: 2em; color: #f44336;">${data.error_tests}</div>
                        <div>Erros</div>
                    </div>
                    <div>
                        <div style="font-size: 2em; color: #2196f3;">${data.total_tests}</div>
                        <div>Total</div>
                    </div>
                </div>
            `;
            
            summary.style.display = 'block';
            
            // Mostrar recomendações se houver
            if (data.recommendations && data.recommendations.length > 0) {
                const recBox = document.getElementById('recommendationsBox');
                const recList = document.getElementById('recommendationsList');
                
                recList.innerHTML = data.recommendations.map(rec => 
                    `<div class="command" onclick="copyCommand('${rec}')">${rec} <button class="copy-btn">📋</button></div>`
                ).join('');
                
                recBox.style.display = 'block';
            }
            
            document.getElementById('progressText').textContent = '✅ Teste concluído!';
        }
        
        function clearResults() {
            fetch('?action=clear_results')
                .then(() => {
                    location.reload();
                });
        }
        
        function copyCommand(command) {
            navigator.clipboard.writeText(command).then(() => {
                // Feedback visual
                const notification = document.createElement('div');
                notification.innerHTML = `✅ Comando copiado: <code>${command}</code>`;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #4caf50;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    z-index: 1000;
                    font-size: 14px;
                    max-width: 400px;
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 3000);
            }).catch(err => {
                alert('Erro ao copiar comando. Copie manualmente: ' + command);
            });
        }
    </script>
</body>
</html>