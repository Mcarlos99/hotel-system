<?php
// mikrotik_deep_diagnosis.php - Diagnóstico profundo do problema
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

echo "<h1>🔬 Diagnóstico Profundo - Investigação da API MikroTik</h1>";

require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
    .step { margin: 15px 0; padding: 15px; border-radius: 8px; border-left: 5px solid; }
    .success { background: #d4edda; border-color: #28a745; color: #155724; }
    .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
    .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
    .debug { background: #f8f9fa; border: 1px solid #dee2e6; font-family: monospace; font-size: 11px; padding: 10px; margin: 5px 0; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 11px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; }
    .hex-dump { font-family: 'Courier New', monospace; font-size: 10px; background: #000; color: #00ff00; padding: 15px; border-radius: 5px; }
    .highlight { background: #ffeb3b; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
    .btn { background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin: 5px; border: none; cursor: pointer; }
    .btn-danger { background: #dc3545; }
    .btn-success { background: #28a745; }
    .raw-data { background: #2d3748; color: #68d391; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 11px; white-space: pre-wrap; word-break: break-all; }
</style>";

echo "<div class='container'>";

class MikroTikDeepDiagnosis {
    private $host;
    private $username;
    private $password;
    private $port;
    private $socket;
    private $connected = false;
    private $rawData = [];
    private $debugInfo = [];
    
    public function __construct($host, $username, $password, $port = 8728) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }
    
    private function log($message, $level = 'info') {
        $this->debugInfo[] = ['level' => $level, 'message' => $message, 'time' => microtime(true)];
        
        $class = $level;
        echo "<div class='debug debug-{$class}'>";
        echo "<strong>[" . date('H:i:s') . "]</strong> " . htmlspecialchars($message);
        echo "</div>";
        flush();
    }
    
    public function getDebugInfo() {
        return $this->debugInfo;
    }
    
    public function getRawData() {
        return $this->rawData;
    }
    
    /**
     * Diagnóstico completo da conexão
     */
    public function fullDiagnosis() {
        $diagnosis = [
            'connection_test' => $this->testConnection(),
            'permission_test' => $this->testPermissions(),
            'api_test' => $this->testAPICapabilities(),
            'raw_response_test' => $this->testRawResponse(),
            'alternative_commands' => $this->testAlternativeCommands()
        ];
        
        return $diagnosis;
    }
    
    /**
     * Teste básico de conexão
     */
    private function testConnection() {
        $this->log("=== TESTE 1: CONEXÃO BÁSICA ===", 'info');
        
        try {
            $this->log("Testando conectividade TCP para {$this->host}:{$this->port}");
            
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
            if (!$socket) {
                return [
                    'success' => false,
                    'error' => "Conexão TCP falhou: {$errstr} ({$errno})"
                ];
            }
            fclose($socket);
            
            $this->log("✅ Conexão TCP bem-sucedida");
            
            // Testar login
            $this->log("Testando login na API...");
            $loginResult = $this->connect();
            
            if ($loginResult) {
                $this->log("✅ Login na API bem-sucedido");
                return ['success' => true, 'message' => 'Conexão e login OK'];
            } else {
                return ['success' => false, 'error' => 'Falha no login da API'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Teste de permissões do usuário
     */
    private function testPermissions() {
        $this->log("=== TESTE 2: PERMISSÕES DO USUÁRIO ===", 'info');
        
        if (!$this->connected) {
            $this->connect();
        }
        
        try {
            $commands = [
                '/system/identity/print' => 'Identidade do sistema',
                '/user/print' => 'Lista de usuários do sistema',
                '/ip/hotspot/print' => 'Configuração do hotspot',
                '/ip/hotspot/user/print' => 'Usuários do hotspot'
            ];
            
            $results = [];
            
            foreach ($commands as $command => $description) {
                $this->log("Testando comando: {$command} ({$description})");
                
                try {
                    $this->write($command);
                    $response = $this->read();
                    
                    $hasError = $this->hasError($response);
                    $results[$command] = [
                        'success' => !$hasError,
                        'response_lines' => count($response),
                        'description' => $description
                    ];
                    
                    if ($hasError) {
                        $this->log("❌ Erro no comando {$command}");
                        $this->log("Resposta: " . implode(" | ", $response));
                    } else {
                        $this->log("✅ Comando {$command} executado (". count($response) ." linhas)");
                    }
                    
                } catch (Exception $e) {
                    $results[$command] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'description' => $description
                    ];
                    $this->log("❌ Exceção no comando {$command}: " . $e->getMessage());
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Teste das capacidades da API
     */
    private function testAPICapabilities() {
        $this->log("=== TESTE 3: CAPACIDADES DA API ===", 'info');
        
        if (!$this->connected) {
            $this->connect();
        }
        
        try {
            $this->log("Testando comando com filtros...");
            
            // Testar diferentes variações do comando
            $variations = [
                '/ip/hotspot/user/print' => 'Comando padrão',
                '/ip/hotspot/user/print =.proplist=' => 'Sem propriedades específicas',
                '/ip/hotspot/user/print =.proplist=.id,name' => 'Apenas ID e nome',
                '/ip/hotspot/user/print =count-only=' => 'Apenas contagem',
                '/ip/hotspot/user/print =detail=' => 'Modo detalhado'
            ];
            
            $results = [];
            
            foreach ($variations as $command => $description) {
                $this->log("Testando: {$description}");
                
                try {
                    if (strpos($command, '=') !== false) {
                        $parts = explode(' ', $command, 2);
                        $cmd = $parts[0];
                        $args = isset($parts[1]) ? [$parts[1]] : [];
                        $this->write($cmd, $args);
                    } else {
                        $this->write($command);
                    }
                    
                    $response = $this->read();
                    
                    $results[$description] = [
                        'success' => !$this->hasError($response),
                        'lines' => count($response),
                        'sample' => array_slice($response, 0, 3)
                    ];
                    
                    $this->log("Resultado: " . count($response) . " linhas");
                    
                } catch (Exception $e) {
                    $results[$description] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $this->log("❌ Erro: " . $e->getMessage());
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Teste de resposta raw (dados brutos)
     */
    private function testRawResponse() {
        $this->log("=== TESTE 4: ANÁLISE DE DADOS BRUTOS ===", 'info');
        
        if (!$this->connected) {
            $this->connect();
        }
        
        try {
            $this->log("Capturando dados brutos da API...");
            
            $this->write('/ip/hotspot/user/print');
            $response = $this->readRaw();
            
            $this->log("Dados brutos capturados: " . strlen($response) . " bytes");
            
            // Análise hexadecimal
            $hex = bin2hex($response);
            $this->log("Dump hexadecimal dos primeiros 200 bytes:");
            
            $analysis = [
                'total_bytes' => strlen($response),
                'hex_dump' => $hex,
                'ascii_dump' => $this->toSafeAscii($response),
                'possible_records' => $this->countPossibleRecords($response),
                'structure_analysis' => $this->analyzeStructure($response)
            ];
            
            return $analysis;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Teste de comandos alternativos
     */
    private function testAlternativeCommands() {
        $this->log("=== TESTE 5: COMANDOS ALTERNATIVOS ===", 'info');
        
        if (!$this->connected) {
            $this->connect();
        }
        
        try {
            $alternatives = [
                // Diferentes formas de listar usuários
                '/ip/hotspot/user/getall' => 'Comando getall',
                '/ip/hotspot/user/print =detail=' => 'Print detalhado', 
                '/ip/hotspot/user/print where' => 'Print com where vazio',
                '/ip/hotspot/user/print ?#' => 'Print com filtro vazio',
                '/ip/hotspot/user/monitor =duration=1' => 'Monitor por 1 segundo'
            ];
            
            $results = [];
            
            foreach ($alternatives as $command => $description) {
                $this->log("Testando: {$description}");
                
                try {
                    $parts = explode(' ', $command);
                    $cmd = array_shift($parts);
                    $args = [];
                    
                    foreach ($parts as $part) {
                        if (!empty($part)) {
                            $args[] = $part;
                        }
                    }
                    
                    $this->write($cmd, $args);
                    $response = $this->read();
                    
                    $results[$description] = [
                        'success' => !$this->hasError($response),
                        'lines' => count($response),
                        'has_user_data' => $this->hasUserData($response)
                    ];
                    
                    $this->log("Resultado: " . count($response) . " linhas" . 
                              ($this->hasUserData($response) ? " (com dados de usuário)" : " (sem dados de usuário)"));
                    
                } catch (Exception $e) {
                    $results[$description] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $this->log("❌ Erro: " . $e->getMessage());
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Métodos auxiliares
     */
    private function connect() {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            throw new Exception("Erro ao criar socket");
        }
        
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 10, "usec" => 0));
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 10, "usec" => 0));
        
        if (!socket_connect($this->socket, $this->host, $this->port)) {
            $error = socket_strerror(socket_last_error($this->socket));
            socket_close($this->socket);
            throw new Exception("Conexão falhou: " . $error);
        }
        
        // Login
        $this->write('/login');
        $response = $this->read();
        
        $loginData = ['=name=' . $this->username];
        if (!empty($this->password)) {
            $loginData[] = '=password=' . $this->password;
        }
        
        $this->write('/login', $loginData);
        $response = $this->read();
        
        if ($this->hasError($response)) {
            throw new Exception("Login falhou");
        }
        
        $this->connected = true;
        return true;
    }
    
    private function write($command, $arguments = []) {
        if (!$this->socket) {
            throw new Exception("Socket não disponível");
        }
        
        $data = $this->encodeLength(strlen($command)) . $command;
        
        foreach ($arguments as $arg) {
            $data .= $this->encodeLength(strlen($arg)) . $arg;
        }
        
        $data .= $this->encodeLength(0);
        
        if (socket_write($this->socket, $data) === false) {
            throw new Exception("Erro ao escrever no socket");
        }
    }
    
    private function read() {
        if (!$this->socket) {
            throw new Exception("Socket não disponível");
        }
        
        $response = [];
        $startTime = time();
        $maxIterations = 100;
        $iterations = 0;
        
        while ($iterations < $maxIterations) {
            if ((time() - $startTime) > 15) {
                $this->log("⚠️ Timeout atingido após 15 segundos");
                break;
            }
            
            $length = $this->readLength();
            
            if ($length == 0) {
                break;
            }
            
            if ($length > 0 && $length < 10000) {
                $data = $this->readData($length);
                if ($data !== false && $data !== '') {
                    $response[] = $data;
                }
            }
            
            $iterations++;
        }
        
        return $response;
    }
    
    private function readRaw() {
        if (!$this->socket) {
            throw new Exception("Socket não disponível");
        }
        
        $rawData = '';
        $startTime = time();
        
        while ((time() - $startTime) < 10) {
            $chunk = socket_read($this->socket, 4096);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $rawData .= $chunk;
        }
        
        $this->rawData[] = $rawData;
        return $rawData;
    }
    
    private function readLength() {
        $byte = socket_read($this->socket, 1);
        if ($byte === false || $byte === '') {
            throw new Exception("Erro na leitura");
        }
        
        $length = ord($byte);
        
        if ($length < 0x80) {
            return $length;
        } elseif ($length < 0xC0) {
            $byte = socket_read($this->socket, 1);
            if ($byte === false) throw new Exception("Erro na leitura 2");
            return (($length & 0x3F) << 8) + ord($byte);
        } elseif ($length < 0xE0) {
            $bytes = socket_read($this->socket, 2);
            if ($bytes === false || strlen($bytes) < 2) throw new Exception("Erro na leitura 3");
            return (($length & 0x1F) << 16) + (ord($bytes[0]) << 8) + ord($bytes[1]);
        } elseif ($length < 0xF0) {
            $bytes = socket_read($this->socket, 3);
            if ($bytes === false || strlen($bytes) < 3) throw new Exception("Erro na leitura 4");
            return (($length & 0x0F) << 24) + (ord($bytes[0]) << 16) + (ord($bytes[1]) << 8) + ord($bytes[2]);
        }
        
        return 0;
    }
    
    private function readData($length) {
        $data = '';
        $remaining = $length;
        $attempts = 0;
        
        while ($remaining > 0 && $attempts < 10) {
            $chunk = socket_read($this->socket, $remaining);
            
            if ($chunk === false) {
                throw new Exception("Erro na leitura de dados");
            }
            
            if ($chunk === '') {
                $attempts++;
                usleep(10000);
                continue;
            }
            
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        
        return $data;
    }
    
    private function encodeLength($length) {
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
    
    private function hasError($response) {
        foreach ($response as $line) {
            if (strpos($line, '!trap') !== false || strpos($line, '!fatal') !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function hasUserData($response) {
        foreach ($response as $line) {
            if (strpos($line, '=name=') !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function toSafeAscii($data) {
        $ascii = '';
        for ($i = 0; $i < min(200, strlen($data)); $i++) {
            $char = ord($data[$i]);
            if ($char >= 32 && $char <= 126) {
                $ascii .= chr($char);
            } else {
                $ascii .= '.';
            }
        }
        return $ascii;
    }
    
    private function countPossibleRecords($data) {
        return substr_count($data, '!re');
    }
    
    private function analyzeStructure($data) {
        $analysis = [
            're_count' => substr_count($data, '!re'),
            'done_count' => substr_count($data, '!done'),
            'name_count' => substr_count($data, '=name='),
            'id_count' => substr_count($data, '=.id='),
            'total_length' => strlen($data)
        ];
        
        return $analysis;
    }
    
    public function disconnect() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }
}

// Executar diagnóstico
$diagnosis = new MikroTikDeepDiagnosis(
    $mikrotikConfig['host'],
    $mikrotikConfig['username'],
    $mikrotikConfig['password'],
    $mikrotikConfig['port']
);

echo "<div class='step info'>";
echo "<h3>🎯 Objetivo do Diagnóstico:</h3>";
echo "<p>Vamos investigar <strong>exatamente</strong> por que a API só retorna 1 usuário quando o Winbox mostra 4.</p>";
echo "<p>Este teste irá verificar:</p>";
echo "<ul>";
echo "<li>✅ Conectividade TCP e login na API</li>";
echo "<li>🔐 Permissões do usuário da API</li>";
echo "<li>⚙️ Capacidades e variações de comandos</li>";
echo "<li>🔬 Análise dos dados brutos (hex dump)</li>";
echo "<li>🔄 Comandos alternativos</li>";
echo "</ul>";
echo "</div>";

$results = $diagnosis->fullDiagnosis();

// Exibir resultados
echo "<div class='step " . ($results['connection_test']['success'] ? 'success' : 'error') . "'>";
echo "<h3>📡 Teste de Conexão:</h3>";
if ($results['connection_test']['success']) {
    echo "<p>✅ " . $results['connection_test']['message'] . "</p>";
} else {
    echo "<p>❌ " . $results['connection_test']['error'] . "</p>";
}
echo "</div>";

echo "<div class='step info'>";
echo "<h3>🔐 Teste de Permissões:</h3>";
foreach ($results['permission_test'] as $command => $result) {
    $status = $result['success'] ? '✅' : '❌';
    $lines = isset($result['response_lines']) ? " ({$result['response_lines']} linhas)" : '';
    echo "<p>{$status} <code>{$command}</code> - {$result['description']}{$lines}</p>";
    
    if (!$result['success'] && isset($result['error'])) {
        echo "<p style='margin-left: 20px; color: #dc3545;'>Erro: {$result['error']}</p>";
    }
}
echo "</div>";

echo "<div class='step info'>";
echo "<h3>⚙️ Teste de Capacidades da API:</h3>";
foreach ($results['api_test'] as $description => $result) {
    $status = $result['success'] ? '✅' : '❌';
    $lines = isset($result['lines']) ? " ({$result['lines']} linhas)" : '';
    echo "<p>{$status} {$description}{$lines}</p>";
    
    if (isset($result['sample']) && !empty($result['sample'])) {
        echo "<div class='debug'>Amostra: " . implode(" | ", array_slice($result['sample'], 0, 2)) . "</div>";
    }
}
echo "</div>";

echo "<div class='step warning'>";
echo "<h3>🔬 Análise de Dados Brutos:</h3>";
$raw = $results['raw_response_test'];
if (isset($raw['total_bytes'])) {
    echo "<p><strong>Total de bytes recebidos:</strong> {$raw['total_bytes']}</p>";
    echo "<p><strong>Possíveis registros (!re):</strong> " . ($raw['possible_records'] ?? 0) . "</p>";
    echo "<p><strong>Análise estrutural:</strong></p>";
    
    if (isset($raw['structure_analysis'])) {
        $struct = $raw['structure_analysis'];
        echo "<ul>";
        echo "<li>Registros (!re): {$struct['re_count']}</li>";
        echo "<li>Fim de dados (!done): {$struct['done_count']}</li>";
        echo "<li>Nomes (=name=): {$struct['name_count']}</li>";
        echo "<li>IDs (=.id=): {$struct['id_count']}</li>";
        echo "</ul>";
    }
    
    if (isset($raw['hex_dump'])) {
        echo "<p><strong>Dump Hexadecimal (primeiros 400 caracteres):</strong></p>";
        echo "<div class='hex-dump'>" . substr($raw['hex_dump'], 0, 400) . "</div>";
    }
    
    if (isset($raw['ascii_dump'])) {
        echo "<p><strong>Representação ASCII:</strong></p>";
        echo "<div class='raw-data'>" . htmlspecialchars($raw['ascii_dump']) . "</div>";
    }
}
echo "</div>";

echo "<div class='step info'>";
echo "<h3>🔄 Comandos Alternativos:</h3>";
foreach ($results['alternative_commands'] as $description => $result) {
    $status = $result['success'] ? '✅' : '❌';
    $lines = isset($result['lines']) ? " ({$result['lines']} linhas)" : '';
    $userData = isset($result['has_user_data']) && $result['has_user_data'] ? " 👤" : "";
    echo "<p>{$status} {$description}{$lines}{$userData}</p>";
}
echo "</div>";

$diagnosis->disconnect();

echo "<div class='step warning'>";
echo "<h3>🧐 Análise dos Resultados:</h3>";

// Determinar o problema baseado nos resultados
$possibleCauses = [];

if (!$results['connection_test']['success']) {
    $possibleCauses[] = "❌ <strong>Problema de conectividade</strong> - A conexão TCP ou login da API está falhando";
}

$hotspotPermission = false;
foreach ($results['permission_test'] as $command => $result) {
    if (strpos($command, 'hotspot/user') !== false && $result['success']) {
        $hotspotPermission = true;
        break;
    }
}

if (!$hotspotPermission) {
    $possibleCauses[] = "🔐 <strong>Problema de permissões</strong> - O usuário não tem acesso aos usuários do hotspot";
}

$raw = $results['raw_response_test'];
if (isset($raw['structure_analysis'])) {
    $struct = $raw['structure_analysis'];
    
    if ($struct['re_count'] > 1 && $struct['name_count'] > 1) {
        $possibleCauses[] = "📊 <strong>Dados estão chegando</strong> - A API retorna múltiplos usuários, mas o parser está falhando";
    } elseif ($struct['re_count'] <= 1) {
        $possibleCauses[] = "📡 <strong>API retorna poucos dados</strong> - O MikroTik está enviando apenas 1 registro";
    }
    
    if ($struct['total_length'] < 100) {
        $possibleCauses[] = "📦 <strong>Resposta muito pequena</strong> - A resposta da API é suspeita (menos de 100 bytes)";
    }
}

$hasWorkingAlternative = false;
foreach ($results['alternative_commands'] as $result) {
    if (isset($result['has_user_data']) && $result['has_user_data'] && isset($result['lines']) && $result['lines'] > 3) {
        $hasWorkingAlternative = true;
        break;
    }
}

if ($hasWorkingAlternative) {
    $possibleCauses[] = "🔄 <strong>Comando alternativo funciona</strong> - Algum comando alternativo retorna mais dados";
}

if (empty($possibleCauses)) {
    $possibleCauses[] = "🤔 <strong>Problema não identificado</strong> - Todos os testes básicos passaram, mas ainda há um problema";
}

foreach ($possibleCauses as $cause) {
    echo "<p>{$cause}</p>";
}
echo "</div>";

echo "<div class='step success'>";
echo "<h3>🛠️ Próximas Ações Recomendadas:</h3>";

if (isset($raw['structure_analysis']['name_count']) && $raw['structure_analysis']['name_count'] > 1) {
    echo "<h4>✅ AÇÃO 1: Problema é no Parser</h4>";
    echo "<p>A API está retornando múltiplos usuários, mas o parser PHP não está interpretando corretamente.</p>";
    echo "<ul>";
    echo "<li>✅ Use um parser de força bruta baseado nos dados hexadecimais</li>";
    echo "<li>✅ Implemente leitura raw e processe manualmente</li>";
    echo "<li>✅ Teste diferentes abordagens de parsing</li>";
    echo "</ul>";
} elseif (!$hotspotPermission) {
    echo "<h4>🔐 AÇÃO 1: Corrigir Permissões</h4>";
    echo "<p>O usuário da API não tem permissões suficientes para acessar usuários do hotspot.</p>";
    echo "<ul>";
    echo "<li>✅ Use o usuário 'admin' com senha vazia</li>";
    echo "<li>✅ Crie um usuário específico com permissões completas</li>";
    echo "<li>✅ Verifique as configurações de grupo do usuário</li>";
    echo "</ul>";
} else {
    echo "<h4>📡 AÇÃO 1: Investigar Configuração do MikroTik</h4>";
    echo "<p>O MikroTik pode estar configurado para mostrar apenas alguns usuários via API.</p>";
    echo "<ul>";
    echo "<li>✅ Verifique se há filtros na configuração do hotspot</li>";
    echo "<li>✅ Teste com diferentes perfis de usuário</li>";
    echo "<li>✅ Verifique logs do MikroTik para erros da API</li>";
    echo "</ul>";
}

if ($hasWorkingAlternative) {
    echo "<h4>🔄 AÇÃO 2: Usar Comando Alternativo</h4>";
    echo "<p>Um dos comandos alternativos retornou mais dados.</p>";
    echo "<ul>";
    echo "<li>✅ Identifique qual comando funcionou melhor</li>";
    echo "<li>✅ Modifique o sistema para usar esse comando</li>";
    echo "<li>✅ Teste a eficácia na remoção de usuários</li>";
    echo "</ul>";
}

echo "<h4>🧪 AÇÃO 3: Teste Manual no Terminal</h4>";
echo "<p>Execute comandos diretamente no MikroTik para comparar:</p>";
echo "<pre>";
echo "/ip hotspot user print\n";
echo "/ip hotspot user print detail\n";
echo "/ip hotspot user print count-only\n";
echo "/ip hotspot user print where\n";
echo "</pre>";

echo "<h4>📋 AÇÃO 4: Verificar Configuração do Hotspot</h4>";
echo "<p>Comandos para verificar no MikroTik:</p>";
echo "<pre>";
echo "/ip hotspot print\n";
echo "/ip hotspot user profile print\n";
echo "/user print\n";
echo "/user group print\n";
echo "/ip service print\n";
echo "</pre>";
echo "</div>";

echo "<div class='step info'>";
echo "<h3>📊 Resumo Executivo:</h3>";

$summary = "Com base no diagnóstico:\n\n";

if (isset($raw['structure_analysis'])) {
    $struct = $raw['structure_analysis'];
    
    if ($struct['name_count'] > 1) {
        $summary .= "✅ PROBLEMA IDENTIFICADO: A API retorna {$struct['name_count']} nomes de usuários nos dados brutos, mas o parser PHP só consegue extrair 1.\n\n";
        $summary .= "🎯 SOLUÇÃO: O problema está no código PHP, não no MikroTik. Precisamos de um parser mais robusto.\n";
    } elseif ($struct['re_count'] > 1) {
        $summary .= "⚠️ DADOS AMBÍGUOS: A API retorna {$struct['re_count']} registros, mas apenas {$struct['name_count']} nomes.\n\n";
        $summary .= "🎯 SOLUÇÃO: Investigar estrutura dos dados e melhorar o parser.\n";
    } else {
        $summary .= "❌ PROBLEMA NO MIKROTIK: A API realmente retorna apenas 1 usuário.\n\n";
        $summary .= "🎯 SOLUÇÃO: Verificar configuração, permissões e filtros no MikroTik.\n";
    }
} else {
    $summary .= "❌ FALHA NA ANÁLISE: Não foi possível capturar dados brutos suficientes.\n\n";
    $summary .= "🎯 SOLUÇÃO: Verificar conectividade e permissões básicas.\n";
}

echo "<pre>" . $summary . "</pre>";
echo "</div>";

echo "<div class='step warning'>";
echo "<h3>🚨 Ação Imediata Recomendada:</h3>";

if (isset($raw['structure_analysis']['name_count']) && $raw['structure_analysis']['name_count'] > 1) {
    echo "<p><strong>🎯 IMPLEMENTAR PARSER DE FORÇA BRUTA</strong></p>";
    echo "<p>Os dados estão chegando, mas o parser está falhando. Vou criar um parser que funciona diretamente com os dados hexadecimais.</p>";
    echo "<p><a href='#' onclick='createBruteForceParser()' class='btn btn-success'>🔧 Gerar Parser de Força Bruta</a></p>";
} else {
    echo "<p><strong>🔐 TESTAR COM USUÁRIO ADMIN</strong></p>";
    echo "<p>O MikroTik pode estar limitando o acesso. Teste com credenciais de administrador.</p>";
    echo "<form method='GET' style='display: inline;'>";
    echo "<input type='hidden' name='test_admin' value='1'>";
    echo "<button type='submit' class='btn btn-warning'>🧪 Testar com Admin</button>";
    echo "</form>";
}
echo "</div>";

// Teste adicional com admin se solicitado
if (isset($_GET['test_admin'])) {
    echo "<div class='step info'>";
    echo "<h3>🔐 Teste com Usuário Admin:</h3>";
    
    try {
        $adminDiagnosis = new MikroTikDeepDiagnosis(
            $mikrotikConfig['host'],
            'admin',
            '',
            $mikrotikConfig['port']
        );
        
        $adminResults = $adminDiagnosis->testRawResponse();
        
        if (isset($adminResults['structure_analysis'])) {
            $adminStruct = $adminResults['structure_analysis'];
            echo "<p><strong>Resultado com admin:</strong></p>";
            echo "<ul>";
            echo "<li>Registros (!re): {$adminStruct['re_count']}</li>";
            echo "<li>Nomes (=name=): {$adminStruct['name_count']}</li>";
            echo "<li>IDs (=.id=): {$adminStruct['id_count']}</li>";
            echo "<li>Total de bytes: {$adminStruct['total_length']}</li>";
            echo "</ul>";
            
            if ($adminStruct['name_count'] > $struct['name_count']) {
                echo "<div class='step success'>";
                echo "<p>✅ <strong>SUCESSO!</strong> O usuário admin tem acesso a mais dados!</p>";
                echo "<p>🎯 <strong>Solução:</strong> Use credenciais de administrador no sistema.</p>";
                echo "</div>";
            } else {
                echo "<div class='step warning'>";
                echo "<p>⚠️ Mesmo com admin, o resultado é similar.</p>";
                echo "<p>O problema pode ser mais profundo na configuração do MikroTik.</p>";
                echo "</div>";
            }
        }
        
        $adminDiagnosis->disconnect();
        
    } catch (Exception $e) {
        echo "<p>❌ Erro ao testar com admin: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

echo "<div class='step info'>";
echo "<h3>📱 Comandos para Testar no MikroTik:</h3>";
echo "<p>Execute estes comandos diretamente no terminal do MikroTik para confirmar:</p>";
echo "<pre>";
echo "# Verificar todos os usuários\n";
echo "/ip hotspot user print\n\n";
echo "# Verificar configuração da API\n";
echo "/ip service print\n\n";
echo "# Verificar permissões do usuário\n";
echo "/user print detail\n\n";
echo "# Testar comando via API manualmente\n";
echo "/system script add name=test-api source=\"/ip hotspot user print\"\n";
echo "/system script run test-api\n";
echo "</pre>";
echo "</div>";

echo "</div>"; // Fechar container

echo "<script>";
echo "function createBruteForceParser() {";
echo "  alert('Parser de força bruta será implementado baseado na análise hexadecimal dos dados.');";
echo "  window.location.href = 'create_brute_force_parser.php';";
echo "}";
echo "</script>";
?>