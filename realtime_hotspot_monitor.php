<?php
/**
 * realtime_hotspot_monitor.php - Monitor em Tempo Real
 * 
 * OBJETIVO: Monitorar em tempo real o que acontece quando um cliente
 * tenta se conectar ao hotspot para identificar onde falha o redirecionamento
 */

require_once 'config.php';
require_once 'mikrotik_manager.php';

class RealtimeHotspotMonitor {
    private $mikrotik;
    private $running = false;
    private $logFile;
    private $monitoringInterval = 2; // segundos
    private $lastActiveUsers = [];
    private $lastLogEntries = [];
    
    public function __construct($mikrotikConfig) {
        $this->mikrotik = new MikroTikRawDataParser(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        $this->logFile = 'logs/realtime_monitor_' . date('Y-m-d_H-i-s') . '.log';
        
        // Criar diretório se não existir
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        $this->log("🔄 MONITOR EM TEMPO REAL INICIADO");
        $this->log("Timestamp: " . date('Y-m-d H:i:s'));
        $this->log("Intervalo de monitoramento: {$this->monitoringInterval}s");
    }
    
    /**
     * INICIAR MONITORAMENTO EM TEMPO REAL
     */
    public function startMonitoring($duration = 300) { // 5 minutos por padrão
        $this->log("\n🚀 INICIANDO MONITORAMENTO EM TEMPO REAL");
        $this->log("Duração: {$duration} segundos");
        $this->log("Conectando ao MikroTik...");
        
        try {
            $this->mikrotik->connect();
            $this->log("✅ Conectado com sucesso ao MikroTik");
            
            $this->running = true;
            $startTime = time();
            $iteration = 0;
            
            // Estado inicial
            $this->captureInitialState();
            
            $this->log("\n📡 INICIANDO LOOP DE MONITORAMENTO...");
            $this->log("Pressione Ctrl+C para parar o monitoramento");
            $this->log(str_repeat("=", 60));
            
            while ($this->running && (time() - $startTime) < $duration) {
                $iteration++;
                $iterationStart = microtime(true);
                
                $this->log("\n⏰ ITERAÇÃO #{$iteration} - " . date('H:i:s'));
                
                // Monitorar componentes críticos
                $this->monitorActiveUsers();
                $this->monitorSystemLogs();
                $this->monitorFirewallConnections();
                $this->monitorDNSQueries();
                $this->monitorInterfaceTraffic();
                
                $iterationTime = round((microtime(true) - $iterationStart) * 1000, 2);
                $this->log("⚡ Iteração concluída em {$iterationTime}ms");
                
                // Aguardar próxima iteração
                sleep($this->monitoringInterval);
            }
            
            $this->log("\n🏁 MONITORAMENTO FINALIZADO");
            $this->log("Total de iterações: {$iteration}");
            $this->log("Duração total: " . (time() - $startTime) . " segundos");
            
            $this->mikrotik->disconnect();
            
        } catch (Exception $e) {
            $this->log("❌ ERRO NO MONITORAMENTO: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * CAPTURAR ESTADO INICIAL
     */
    private function captureInitialState() {
        $this->log("\n📋 CAPTURANDO ESTADO INICIAL DO SISTEMA:");
        
        try {
            // Usuários ativos iniciais
            $this->lastActiveUsers = $this->getActiveUsers();
            $this->log("👥 Usuários ativos iniciais: " . count($this->lastActiveUsers));
            
            foreach ($this->lastActiveUsers as $i => $user) {
                $this->log("   " . ($i+1) . ". " . ($user['user'] ?? 'N/A') . 
                          " - " . ($user['address'] ?? 'N/A'));
            }
            
            // Logs iniciais
            $this->lastLogEntries = $this->getRecentLogs(5);
            $this->log("📜 Últimas 5 entradas de log capturadas");
            
            // Estatísticas de interface
            $this->logInterfaceStats();
            
            // Configuração DNS
            $this->logDNSConfig();
            
        } catch (Exception $e) {
            $this->log("⚠️ Erro ao capturar estado inicial: " . $e->getMessage());
        }
    }
    
    /**
     * MONITORAR USUÁRIOS ATIVOS
     */
    private function monitorActiveUsers() {
        try {
            $currentUsers = $this->getActiveUsers();
            
            // Verificar novos usuários
            $newUsers = $this->findNewUsers($this->lastActiveUsers, $currentUsers);
            $disconnectedUsers = $this->findDisconnectedUsers($this->lastActiveUsers, $currentUsers);
            
            if (!empty($newUsers)) {
                $this->log("🆕 NOVO USUÁRIO CONECTADO:");
                foreach ($newUsers as $user) {
                    $this->log("   👤 " . ($user['user'] ?? 'N/A') . 
                              " - IP: " . ($user['address'] ?? 'N/A') . 
                              " - MAC: " . ($user['mac-address'] ?? 'N/A'));
                    
                    // Log detalhado do novo usuário
                    $this->logNewUserDetails($user);
                }
            }
            
            if (!empty($disconnectedUsers)) {
                $this->log("👋 USUÁRIO DESCONECTADO:");
                foreach ($disconnectedUsers as $user) {
                    $this->log("   👤 " . ($user['user'] ?? 'N/A') . 
                              " - IP: " . ($user['address'] ?? 'N/A'));
                }
            }
            
            if (empty($newUsers) && empty($disconnectedUsers) && !empty($currentUsers)) {
                $this->log("📊 Usuários ativos: " . count($currentUsers) . " (sem mudanças)");
            }
            
            $this->lastActiveUsers = $currentUsers;
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao monitorar usuários: " . $e->getMessage());
        }
    }
    
    /**
     * MONITORAR LOGS DO SISTEMA
     */
    private function monitorSystemLogs() {
        try {
            $currentLogs = $this->getRecentLogs(10);
            
            // Comparar com logs anteriores
            $newLogs = $this->findNewLogs($this->lastLogEntries, $currentLogs);
            
            if (!empty($newLogs)) {
                $this->log("📜 NOVOS LOGS DO SISTEMA:");
                foreach ($newLogs as $log) {
                    $topics = $log['topics'] ?? 'general';
                    $message = $log['message'] ?? 'N/A';
                    $time = $log['time'] ?? 'N/A';
                    
                    $icon = '📝';
                    if (stripos($topics, 'error') !== false) $icon = '❌';
                    elseif (stripos($topics, 'warning') !== false) $icon = '⚠️';
                    elseif (stripos($topics, 'hotspot') !== false) $icon = '🏨';
                    elseif (stripos($topics, 'firewall') !== false) $icon = '🛡️';
                    elseif (stripos($topics, 'dhcp') !== false) $icon = '📡';
                    
                    $this->log("   {$icon} [{$topics}] {$time}: {$message}");
                }
            }
            
            $this->lastLogEntries = $currentLogs;
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao monitorar logs: " . $e->getMessage());
        }
    }
    
    /**
     * MONITORAR CONEXÕES DO FIREWALL
     */
    private function monitorFirewallConnections() {
        try {
            // Monitorar conexões ativas relacionadas ao hotspot
            $this->mikrotik->writeRaw('/ip/firewall/connection/print', ['?dst-port=80,443']);
            $rawData = $this->mikrotik->captureAllRawData();
            
            $connections = $this->parseFirewallConnections($rawData);
            
            if (!empty($connections)) {
                $httpConnections = 0;
                $httpsConnections = 0;
                
                foreach ($connections as $conn) {
                    $dstPort = $conn['dst-port'] ?? '';
                    if ($dstPort == '80') $httpConnections++;
                    elseif ($dstPort == '443') $httpsConnections++;
                }
                
                if ($httpConnections > 0 || $httpsConnections > 0) {
                    $this->log("🌐 Conexões Web Ativas: HTTP({$httpConnections}) HTTPS({$httpsConnections})");
                }
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao monitorar firewall: " . $e->getMessage());
        }
    }
    
    /**
     * MONITORAR CONSULTAS DNS
     */
    private function monitorDNSQueries() {
        try {
            // Verificar cache DNS para novas entradas
            $this->mikrotik->writeRaw('/ip/dns/cache/print');
            $rawData = $this->mikrotik->captureAllRawData();
            
            $dnsEntries = $this->parseDNSCache($rawData);
            
            // Mostrar algumas entradas recentes (limitado para não poluir)
            $recentEntries = array_slice($dnsEntries, 0, 3);
            
            if (!empty($recentEntries)) {
                $this->log("🌐 Cache DNS (últimas 3 entradas):");
                foreach ($recentEntries as $entry) {
                    $name = $entry['name'] ?? 'N/A';
                    $address = $entry['address'] ?? 'N/A';
                    $ttl = $entry['ttl'] ?? 'N/A';
                    
                    $this->log("   🔍 {$name} -> {$address} (TTL: {$ttl})");
                }
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao monitorar DNS: " . $e->getMessage());
        }
    }
    
    /**
     * MONITORAR TRÁFEGO DAS INTERFACES
     */
    private function monitorInterfaceTraffic() {
        try {
            $this->mikrotik->writeRaw('/interface/print', ['=stats']);
            $rawData = $this->mikrotik->captureAllRawData();
            
            $interfaces = $this->parseInterfaceStats($rawData);
            
            // Filtrar apenas interfaces com tráfego significativo
            $activeInterfaces = array_filter($interfaces, function($iface) {
                $rxBytes = intval($iface['rx-byte'] ?? 0);
                $txBytes = intval($iface['tx-byte'] ?? 0);
                return ($rxBytes > 0 || $txBytes > 0);
            });
            
            if (!empty($activeInterfaces)) {
                $this->log("📊 Tráfego de Interfaces Ativas:");
                foreach (array_slice($activeInterfaces, 0, 3) as $iface) {
                    $name = $iface['name'] ?? 'N/A';
                    $rxBytes = $this->formatBytes($iface['rx-byte'] ?? 0);
                    $txBytes = $this->formatBytes($iface['tx-byte'] ?? 0);
                    $rxPackets = $iface['rx-packet'] ?? 0;
                    $txPackets = $iface['tx-packet'] ?? 0;
                    
                    $this->log("   📡 {$name}: RX({$rxBytes},{$rxPackets}pkt) TX({$txBytes},{$txPackets}pkt)");
                }
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao monitorar interfaces: " . $e->getMessage());
        }
    }
    
    /**
     * MÉTODOS AUXILIARES
     */
    
    private function getActiveUsers() {
        try {
            $this->mikrotik->writeRaw('/ip/hotspot/active/print');
            $rawData = $this->mikrotik->captureAllRawData();
            return $this->parseActiveUsers($rawData);
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getRecentLogs($limit = 10) {
        try {
            $this->mikrotik->writeRaw('/log/print', ['=count=' . $limit]);
            $rawData = $this->mikrotik->captureAllRawData();
            return $this->parseLogEntries($rawData);
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function parseActiveUsers($rawData) {
        $users = [];
        $parts = explode('!re', $rawData);
        
        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            $user = [];
            $patterns = [
                'id' => '/=\.id=([^\x00-\x1f=]+)/',
                'user' => '/=user=([^\x00-\x1f=]+)/',
                'address' => '/=address=([^\x00-\x1f=]+)/',
                'mac-address' => '/=mac-address=([^\x00-\x1f=]+)/',
                'uptime' => '/=uptime=([^\x00-\x1f=]+)/',
                'session-time-left' => '/=session-time-left=([^\x00-\x1f=]+)/',
                'bytes-in' => '/=bytes-in=([^\x00-\x1f=]+)/',
                'bytes-out' => '/=bytes-out=([^\x00-\x1f=]+)/'
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $part, $matches)) {
                    $user[$field] = trim($matches[1]);
                }
            }
            
            if (isset($user['user'])) {
                $users[] = $user;
            }
        }
        
        return $users;
    }
    
    private function parseLogEntries($rawData) {
        $logs = [];
        $parts = explode('!re', $rawData);
        
        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            $log = [];
            $patterns = [
                'id' => '/=\.id=([^\x00-\x1f=]+)/',
                'topics' => '/=topics=([^\x00-\x1f=]+)/',
                'time' => '/=time=([^\x00-\x1f=]+)/',
                'message' => '/=message=([^\x00-\x1f=]+)/'
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $part, $matches)) {
                    $log[$field] = trim($matches[1]);
                }
            }
            
            if (isset($log['message'])) {
                $logs[] = $log;
            }
        }
        
        return $logs;
    }
    
    private function parseFirewallConnections($rawData) {
        $connections = [];
        $parts = explode('!re', $rawData);
        
        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            $conn = [];
            $patterns = [
                'protocol' => '/=protocol=([^\x00-\x1f=]+)/',
                'src-address' => '/=src-address=([^\x00-\x1f=]+)/',
                'dst-address' => '/=dst-address=([^\x00-\x1f=]+)/',
                'dst-port' => '/=dst-port=([^\x00-\x1f=]+)/',
                'connection-state' => '/=connection-state=([^\x00-\x1f=]+)/'
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $part, $matches)) {
                    $conn[$field] = trim($matches[1]);
                }
            }
            
            if (isset($conn['dst-port'])) {
                $connections[] = $conn;
            }
        }
        
        return $connections;
    }
    
    private function parseDNSCache($rawData) {
        $entries = [];
        $parts = explode('!re', $rawData);
        
        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            $entry = [];
            $patterns = [
                'name' => '/=name=([^\x00-\x1f=]+)/',
                'address' => '/=address=([^\x00-\x1f=]+)/',
                'ttl' => '/=ttl=([^\x00-\x1f=]+)/',
                'type' => '/=type=([^\x00-\x1f=]+)/'
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $part, $matches)) {
                    $entry[$field] = trim($matches[1]);
                }
            }
            
            if (isset($entry['name'])) {
                $entries[] = $entry;
            }
        }
        
        return $entries;
    }
    
    private function parseInterfaceStats($rawData) {
        $interfaces = [];
        $parts = explode('!re', $rawData);
        
        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            $iface = [];
            $patterns = [
                'name' => '/=name=([^\x00-\x1f=]+)/',
                'rx-byte' => '/=rx-byte=([^\x00-\x1f=]+)/',
                'tx-byte' => '/=tx-byte=([^\x00-\x1f=]+)/',
                'rx-packet' => '/=rx-packet=([^\x00-\x1f=]+)/',
                'tx-packet' => '/=tx-packet=([^\x00-\x1f=]+)/',
                'running' => '/=running=([^\x00-\x1f=]+)/'
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $part, $matches)) {
                    $iface[$field] = trim($matches[1]);
                }
            }
            
            if (isset($iface['name'])) {
                $interfaces[] = $iface;
            }
        }
        
        return $interfaces;
    }
    
    private function findNewUsers($oldUsers, $newUsers) {
        $newUsersList = [];
        
        foreach ($newUsers as $newUser) {
            $found = false;
            foreach ($oldUsers as $oldUser) {
                if (($newUser['user'] ?? '') === ($oldUser['user'] ?? '') &&
                    ($newUser['address'] ?? '') === ($oldUser['address'] ?? '')) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $newUsersList[] = $newUser;
            }
        }
        
        return $newUsersList;
    }
    
    private function findDisconnectedUsers($oldUsers, $newUsers) {
        $disconnectedUsers = [];
        
        foreach ($oldUsers as $oldUser) {
            $found = false;
            foreach ($newUsers as $newUser) {
                if (($oldUser['user'] ?? '') === ($newUser['user'] ?? '') &&
                    ($oldUser['address'] ?? '') === ($newUser['address'] ?? '')) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $disconnectedUsers[] = $oldUser;
            }
        }
        
        return $disconnectedUsers;
    }
    
    private function findNewLogs($oldLogs, $newLogs) {
        $newLogsList = [];
        
        foreach ($newLogs as $newLog) {
            $found = false;
            foreach ($oldLogs as $oldLog) {
                if (($newLog['id'] ?? '') === ($oldLog['id'] ?? '')) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $newLogsList[] = $newLog;
            }
        }
        
        return $newLogsList;
    }
    
    private function logNewUserDetails($user) {
        $this->log("   🔍 DETALHES DO NOVO USUÁRIO:");
        $this->log("      └─ Usuário: " . ($user['user'] ?? 'N/A'));
        $this->log("      └─ IP: " . ($user['address'] ?? 'N/A'));
        $this->log("      └─ MAC: " . ($user['mac-address'] ?? 'N/A'));
        $this->log("      └─ Uptime: " . ($user['uptime'] ?? 'N/A'));
        $this->log("      └─ Bytes In: " . $this->formatBytes($user['bytes-in'] ?? 0));
        $this->log("      └─ Bytes Out: " . $this->formatBytes($user['bytes-out'] ?? 0));
        
        // Verificar se o usuário está autenticado ou aguardando
        if (($user['uptime'] ?? '') === '00:00:00') {
            $this->log("      ⚠️ ATENÇÃO: Usuário recém conectado - pode estar na página de login");
        }
    }
    
    private function logInterfaceStats() {
        try {
            $interfaces = $this->monitorInterfaceTraffic();
            $this->log("📊 Estatísticas iniciais de interface capturadas");
        } catch (Exception $e) {
            $this->log("⚠️ Erro ao capturar stats de interface: " . $e->getMessage());
        }
    }
    
    private function logDNSConfig() {
        try {
            $this->mikrotik->writeRaw('/ip/dns/print');
            $rawData = $this->mikrotik->captureAllRawData();
            $this->log("🌐 Configuração DNS inicial capturada");
        } catch (Exception $e) {
            $this->log("⚠️ Erro ao capturar config DNS: " . $e->getMessage());
        }
    }
    
    private function formatBytes($bytes) {
        $bytes = intval($bytes);
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . 'GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'KB';
        }
        return $bytes . 'B';
    }
    
    private function log($message) {
        $timestamp = date('H:i:s.') . substr(microtime(), 2, 3);
        $logMessage = "[{$timestamp}] {$message}";
        
        // Log no arquivo
        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Echo para saída imediata
        echo $logMessage . "\n";
        flush();
    }
    
    public function stop() {
        $this->running = false;
        $this->log("🛑 PARANDO MONITORAMENTO...");
    }
    
    public function getLogFile() {
        return $this->logFile;
    }
}

// EXECUÇÃO DIRETA VIA CLI
if (php_sapi_name() === 'cli') {
    echo "\n🔄 MONITOR EM TEMPO REAL DO HOTSPOT MIKROTIK\n";
    echo str_repeat("=", 50) . "\n";
    echo "Este monitor vai capturar em tempo real:\n";
    echo "• Novos usuários conectando\n";
    echo "• Logs do sistema\n";
    echo "• Conexões de firewall\n";
    echo "• Consultas DNS\n";
    echo "• Tráfego de interfaces\n";
    echo str_repeat("=", 50) . "\n\n";
    
    $duration = 300; // 5 minutos
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $duration = intval($argv[1]);
    }
    
    echo "⏰ Duração do monitoramento: {$duration} segundos\n";
    echo "💡 Use: php realtime_hotspot_monitor.php [segundos] para definir duração\n";
    echo "📱 Agora conecte um dispositivo à rede WiFi para monitorar...\n\n";
    
    try {
        $monitor = new RealtimeHotspotMonitor($mikrotikConfig);
        
        // Configurar handler para Ctrl+C
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function() use ($monitor) {
                $monitor->stop();
                echo "\n\n✅ Monitoramento interrompido pelo usuário.\n";
                exit(0);
            });
        }
        
        $monitor->startMonitoring($duration);
        
        echo "\n📁 Log salvo em: " . $monitor->getLogFile() . "\n";
        echo "✅ Monitoramento concluído com sucesso!\n\n";
        
    } catch (Exception $e) {
        echo "\n❌ ERRO NO MONITORAMENTO: " . $e->getMessage() . "\n";
        echo "🔧 Verifique a conexão com o MikroTik e tente novamente.\n\n";
        exit(1);
    }
}

/**
 * CLASSE PARA INTERFACE WEB DO MONITOR
 */
class RealtimeMonitorWebInterface {
    private $mikrotikConfig;
    
    public function __construct($mikrotikConfig) {
        $this->mikrotikConfig = $mikrotikConfig;
    }
    
    public function renderInterface() {
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor em Tempo Real - Hotspot MikroTik</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .controls {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .monitor-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 30px;
        }
        
        .monitor-panel {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 5px solid #28a745;
        }
        
        .monitor-panel h3 {
            margin-top: 0;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #dc3545;
            display: inline-block;
            margin-left: auto;
        }
        
        .status-indicator.online {
            background: #28a745;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .log-display {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            height: 300px;
            overflow-y: auto;
            margin: 15px 0;
        }
        
        .btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 25px;
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
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-stop {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .btn-stop:hover {
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 5px solid #28a745;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 5px solid;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .timeline {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .timeline-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
            flex-shrink: 0;
        }
        
        .timeline-icon.user { background: #007bff; }
        .timeline-icon.log { background: #6c757d; }
        .timeline-icon.dns { background: #17a2b8; }
        .timeline-icon.firewall { background: #dc3545; }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-time {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .timeline-message {
            font-size: 14px;
            line-height: 1.4;
        }
        
        @media (max-width: 768px) {
            .monitor-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Monitor em Tempo Real</h1>
            <p>Acompanhe conexões e atividade do hotspot em tempo real</p>
        </div>
        
        <div class="controls">
            <div class="alert alert-info">
                <h4>📡 Como usar o monitor:</h4>
                <ol>
                    <li>Clique em "Iniciar Monitoramento" abaixo</li>
                    <li>Conecte um dispositivo à rede WiFi do hotel</li>
                    <li>Abra um navegador no dispositivo e tente acessar qualquer site</li>
                    <li>Observe em tempo real o que acontece nos painéis abaixo</li>
                    <li>Identifique onde o redirecionamento falha</li>
                </ol>
            </div>
            
            <div style="text-align: center;">
                <button class="btn" onclick="startMonitoring()" id="startBtn">
                    🚀 Iniciar Monitoramento
                </button>
                <button class="btn btn-stop" onclick="stopMonitoring()" id="stopBtn" style="display: none;">
                    🛑 Parar Monitoramento
                </button>
                <button class="btn" onclick="clearLogs()">
                    🧹 Limpar Logs
                </button>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="activeUsers">0</div>
                    <div class="stat-label">Usuários Ativos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="newConnections">0</div>
                    <div class="stat-label">Novas Conexões</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="dnsQueries">0</div>
                    <div class="stat-label">Consultas DNS</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="firewallEvents">0</div>
                    <div class="stat-label">Eventos Firewall</div>
                </div>
            </div>
        </div>
        
        <div class="monitor-grid">
            <div class="monitor-panel">
                <h3>
                    👥 Usuários Ativos
                    <span class="status-indicator" id="usersStatus"></span>
                </h3>
                <div class="log-display" id="usersLog">
                    Aguardando início do monitoramento...
                </div>
            </div>
            
            <div class="monitor-panel">
                <h3>
                    📜 Logs do Sistema
                    <span class="status-indicator" id="logsStatus"></span>
                </h3>
                <div class="log-display" id="systemLogs">
                    Aguardando início do monitoramento...
                </div>
            </div>
            
            <div class="monitor-panel">
                <h3>
                    🌐 Atividade DNS
                    <span class="status-indicator" id="dnsStatus"></span>
                </h3>
                <div class="log-display" id="dnsLogs">
                    Aguardando início do monitoramento...
                </div>
            </div>
            
            <div class="monitor-panel">
                <h3>
                    🛡️ Firewall & Conexões
                    <span class="status-indicator" id="firewallStatus"></span>
                </h3>
                <div class="log-display" id="firewallLogs">
                    Aguardando início do monitoramento...
                </div>
            </div>
        </div>
        
        <div style="padding: 30px;">
            <h3>📋 Timeline de Eventos</h3>
            <div class="timeline" id="timeline">
                <div style="text-align: center; color: #6c757d; padding: 20px;">
                    Nenhum evento capturado ainda
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let monitoringActive = false;
        let monitoringInterval;
        let eventCounter = {
            users: 0,
            connections: 0,
            dns: 0,
            firewall: 0
        };
        
        function startMonitoring() {
            if (monitoringActive) return;
            
            monitoringActive = true;
            document.getElementById('startBtn').style.display = 'none';
            document.getElementById('stopBtn').style.display = 'inline-block';
            
            // Ativar indicadores de status
            document.querySelectorAll('.status-indicator').forEach(indicator => {
                indicator.classList.add('online');
            });
            
            // Limpar logs
            clearLogs();
            
            // Adicionar evento inicial
            addTimelineEvent('🚀 Monitoramento iniciado', 'system');
            
            // Iniciar polling
            monitoringInterval = setInterval(fetchMonitoringData, 2000);
            
            // Primeira execução imediata
            fetchMonitoringData();
        }
        
        function stopMonitoring() {
            monitoringActive = false;
            
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
            }
            
            document.getElementById('startBtn').style.display = 'inline-block';
            document.getElementById('stopBtn').style.display = 'none';
            
            // Desativar indicadores
            document.querySelectorAll('.status-indicator').forEach(indicator => {
                indicator.classList.remove('online');
            });
            
            addTimelineEvent('🛑 Monitoramento parado', 'system');
        }
        
        function fetchMonitoringData() {
            if (!monitoringActive) return;
            
            fetch('?action=monitor_data')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateMonitoringPanels(data.data);
                        updateStats(data.data);
                    } else {
                        console.error('Erro no monitoramento:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                });
        }
        
        function updateMonitoringPanels(data) {
            // Atualizar painel de usuários
            if (data.activeUsers) {
                updateLogPanel('usersLog', formatActiveUsers(data.activeUsers));
                
                // Verificar novos usuários
                if (data.newUsers && data.newUsers.length > 0) {
                    data.newUsers.forEach(user => {
                        addTimelineEvent(`👤 Novo usuário: ${user.user} (${user.address})`, 'user');
                        eventCounter.users++;
                    });
                }
            }
            
            // Atualizar logs do sistema
            if (data.systemLogs) {
                updateLogPanel('systemLogs', formatSystemLogs(data.systemLogs));
                
                if (data.newLogs && data.newLogs.length > 0) {
                    data.newLogs.forEach(log => {
                        addTimelineEvent(`📜 ${log.topics}: ${log.message}`, 'log');
                    });
                }
            }
            
            // Atualizar DNS
            if (data.dnsActivity) {
                updateLogPanel('dnsLogs', formatDNSActivity(data.dnsActivity));
                eventCounter.dns += data.dnsActivity.length;
            }
            
            // Atualizar firewall
            if (data.firewallConnections) {
                updateLogPanel('firewallLogs', formatFirewallConnections(data.firewallConnections));
                eventCounter.firewall += data.firewallConnections.length;
            }
        }
        
        function updateStats(data) {
            document.getElementById('activeUsers').textContent = data.activeUsers ? data.activeUsers.length : 0;
            document.getElementById('newConnections').textContent = eventCounter.users;
            document.getElementById('dnsQueries').textContent = eventCounter.dns;
            document.getElementById('firewallEvents').textContent = eventCounter.firewall;
        }
        
        function updateLogPanel(panelId, content) {
            const panel = document.getElementById(panelId);
            panel.innerHTML = content;
            panel.scrollTop = panel.scrollHeight;
        }
        
        function formatActiveUsers(users) {
            if (!users || users.length === 0) {
                return 'Nenhum usuário ativo encontrado';
            }
            
            return users.map((user, index) => 
                `[${getCurrentTime()}] 👤 ${user.user || 'N/A'}\n` +
                `    └─ IP: ${user.address || 'N/A'}\n` +
                `    └─ MAC: ${user['mac-address'] || 'N/A'}\n` +
                `    └─ Uptime: ${user.uptime || 'N/A'}\n`
            ).join('\n');
        }
        
        function formatSystemLogs(logs) {
            if (!logs || logs.length === 0) {
                return 'Nenhum log recente';
            }
            
            return logs.map(log => 
                `[${log.time || getCurrentTime()}] ${getLogIcon(log.topics)} [${log.topics || 'general'}]\n` +
                `    ${log.message || 'N/A'}\n`
            ).join('\n');
        }
        
        function formatDNSActivity(activity) {
            if (!activity || activity.length === 0) {
                return 'Nenhuma atividade DNS recente';
            }
            
            return activity.map(entry => 
                `[${getCurrentTime()}] 🌐 ${entry.name || 'N/A'}\n` +
                `    └─ IP: ${entry.address || 'N/A'}\n` +
                `    └─ TTL: ${entry.ttl || 'N/A'}\n`
            ).join('\n');
        }
        
        function formatFirewallConnections(connections) {
            if (!connections || connections.length === 0) {
                return 'Nenhuma conexão ativa';
            }
            
            return connections.map(conn => 
                `[${getCurrentTime()}] 🛡️ ${conn.protocol || 'N/A'}\n` +
                `    └─ ${conn['src-address'] || 'N/A'} → ${conn['dst-address'] || 'N/A'}:${conn['dst-port'] || 'N/A'}\n` +
                `    └─ Estado: ${conn['connection-state'] || 'N/A'}\n`
            ).join('\n');
        }
        
        function getLogIcon(topics) {
            if (!topics) return '📝';
            topics = topics.toLowerCase();
            
            if (topics.includes('error')) return '❌';
            if (topics.includes('warning')) return '⚠️';
            if (topics.includes('hotspot')) return '🏨';
            if (topics.includes('firewall')) return '🛡️';
            if (topics.includes('dhcp')) return '📡';
            if (topics.includes('dns')) return '🌐';
            
            return '📝';
        }
        
        function addTimelineEvent(message, type) {
            const timeline = document.getElementById('timeline');
            
            // Remover mensagem de "nenhum evento" se existir
            if (timeline.children.length === 1 && timeline.textContent.includes('Nenhum evento')) {
                timeline.innerHTML = '';
            }
            
            const item = document.createElement('div');
            item.className = 'timeline-item';
            
            const iconClass = type === 'user' ? 'user' : 
                             type === 'log' ? 'log' :
                             type === 'dns' ? 'dns' :
                             type === 'firewall' ? 'firewall' : 'log';
            
            const icon = type === 'user' ? '👤' :
                        type === 'log' ? '📜' :
                        type === 'dns' ? '🌐' :
                        type === 'firewall' ? '🛡️' : '📝';
            
            item.innerHTML = `
                <div class="timeline-icon ${iconClass}">${icon}</div>
                <div class="timeline-content">
                    <div class="timeline-time">${getCurrentTime()}</div>
                    <div class="timeline-message">${message}</div>
                </div>
            `;
            
            timeline.insertBefore(item, timeline.firstChild);
            
            // Limitar a 50 eventos
            while (timeline.children.length > 50) {
                timeline.removeChild(timeline.lastChild);
            }
        }
        
        function clearLogs() {
            document.getElementById('usersLog').textContent = 'Aguardando dados...';
            document.getElementById('systemLogs').textContent = 'Aguardando dados...';
            document.getElementById('dnsLogs').textContent = 'Aguardando dados...';
            document.getElementById('firewallLogs').textContent = 'Aguardando dados...';
            
            document.getElementById('timeline').innerHTML = `
                <div style="text-align: center; color: #6c757d; padding: 20px;">
                    Nenhum evento capturado ainda
                </div>
            `;
            
            // Reset counters
            eventCounter = { users: 0, connections: 0, dns: 0, firewall: 0 };
            updateStats({});
        }
        
        function getCurrentTime() {
            return new Date().toLocaleTimeString('pt-BR');
        }
        
        // Auto-refresh a cada 30 segundos se não estiver monitorando
        setInterval(() => {
            if (!monitoringActive) {
                // Verificar status de conexão
                fetch('?action=check_status')
                    .then(response => response.json())
                    .then(data => {
                        // Atualizar indicadores de status baseado na resposta
                    })
                    .catch(error => {
                        // Manter indicadores offline
                    });
            }
        }, 30000);
    </script>
</body>
</html>
        <?php
    }
}

// PROCESSAMENTO DE REQUISIÇÕES AJAX PARA A INTERFACE WEB
if (isset($_GET['action']) && $_GET['action'] === 'monitor_data') {
    header('Content-Type: application/json');
    
    try {
        // Simular dados de monitoramento (em implementação real, conectaria ao MikroTik)
        $monitor = new RealtimeHotspotMonitor($mikrotikConfig);
        
        // Dados simulados para demonstração
        $data = [
            'activeUsers' => [
                ['user' => 'guest-001', 'address' => '192.168.1.100', 'mac-address' => '00:11:22:33:44:55', 'uptime' => '00:05:30'],
                ['user' => 'guest-002', 'address' => '192.168.1.101', 'mac-address' => '00:11:22:33:44:56', 'uptime' => '00:12:15']
            ],
            'systemLogs' => [
                ['time' => date('H:i:s'), 'topics' => 'hotspot,info', 'message' => 'new user logged in'],
                ['time' => date('H:i:s'), 'topics' => 'dhcp,info', 'message' => 'lease assigned to 192.168.1.100']
            ],
            'dnsActivity' => [
                ['name' => 'google.com', 'address' => '8.8.8.8', 'ttl' => '300'],
                ['name' => 'facebook.com', 'address' => '157.240.241.35', 'ttl' => '60']
            ],
            'firewallConnections' => [
                ['protocol' => 'tcp', 'src-address' => '192.168.1.100', 'dst-address' => '8.8.8.8', 'dst-port' => '80', 'connection-state' => 'established']
            ],
            'newUsers' => [],
            'newLogs' => []
        ];
        
        echo json_encode(['success' => true, 'data' => $data]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    
    try {
        $monitor = new RealtimeHotspotMonitor($mikrotikConfig);
        // Testar conexão básica
        echo json_encode(['success' => true, 'status' => 'online']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'status' => 'offline', 'error' => $e->getMessage()]);
    }
    exit;
}

// Se não for CLI nem AJAX, mostrar interface web
if (php_sapi_name() !== 'cli' && !isset($_GET['action'])) {
    $webInterface = new RealtimeMonitorWebInterface($mikrotikConfig);
    $webInterface->renderInterface();
}
?>