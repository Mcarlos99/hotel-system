<?php
/**
 * detailed_log_system.php - Sistema de Log Detalhado para Diagnóstico
 * 
 * OBJETIVO: Descobrir por que o redirecionamento automático não funciona
 * 
 * INVESTIGAÇÃO COMPLETA:
 * ✅ Configuração do Hotspot no MikroTik
 * ✅ DNS e resolução de nomes
 * ✅ Firewall e regras NAT
 * ✅ Walled Garden (sites liberados)
 * ✅ Perfis de usuário
 * ✅ Configuração de interface
 * ✅ Logs do sistema MikroTik
 * ✅ Teste de conectividade real
 */

require_once 'config.php';
require_once 'mikrotik_manager.php';

class HotspotDiagnosticLogger {
    private $mikrotik;
    private $logFile;
    private $testResults = [];
    
    public function __construct($mikrotikConfig) {
        $this->mikrotik = new MikroTikRawDataParser(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'], 
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        $this->logFile = 'logs/hotspot_diagnostic_' . date('Y-m-d_H-i-s') . '.log';
        
        // Criar diretório se não existir
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        $this->log("=== DIAGNÓSTICO DETALHADO DO HOTSPOT INICIADO ===");
        $this->log("Timestamp: " . date('Y-m-d H:i:s'));
        $this->log("Host MikroTik: {$mikrotikConfig['host']}:{$mikrotikConfig['port']}");
    }
    
    /**
     * DIAGNÓSTICO COMPLETO - TODOS OS ASPECTOS
     */
    public function runFullDiagnostic() {
        $this->log("\n🔍 INICIANDO DIAGNÓSTICO COMPLETO DO HOTSPOT");
        
        try {
            // 1. Teste de conectividade básica
            $this->testBasicConnectivity();
            
            // 2. Configuração do Hotspot
            $this->analyzeHotspotConfiguration();
            
            // 3. Configuração de DNS
            $this->analyzeDNSConfiguration();
            
            // 4. Firewall e NAT
            $this->analyzeFirewallAndNAT();
            
            // 5. Walled Garden
            $this->analyzeWalledGarden();
            
            // 6. Perfis de usuário
            $this->analyzeUserProfiles();
            
            // 7. Interfaces de rede
            $this->analyzeNetworkInterfaces();
            
            // 8. Logs do sistema
            $this->analyzeSystemLogs();
            
            // 9. Teste de usuário real
            $this->testRealUserScenario();
            
            // 10. Configurações avançadas
            $this->analyzeAdvancedSettings();
            
            $this->generateDiagnosticReport();
            
        } catch (Exception $e) {
            $this->log("❌ ERRO CRÍTICO NO DIAGNÓSTICO: " . $e->getMessage());
        }
        
        return $this->testResults;
    }
    
    /**
     * 1. TESTE DE CONECTIVIDADE BÁSICA
     */
    private function testBasicConnectivity() {
        $this->log("\n📡 1. TESTANDO CONECTIVIDADE BÁSICA");
        
        try {
            $this->mikrotik->connect();
            $this->log("✅ Conexão TCP estabelecida com sucesso");
            
            // Testar comando básico
            $this->executeAndLog('/system/identity/print', '🔍 Identidade do sistema');
            $this->executeAndLog('/system/clock/print', '🕐 Relógio do sistema');
            $this->executeAndLog('/system/resource/print', '💾 Recursos do sistema');
            
            $this->testResults['basic_connectivity'] = 'SUCCESS';
            
        } catch (Exception $e) {
            $this->log("❌ Falha na conectividade básica: " . $e->getMessage());
            $this->testResults['basic_connectivity'] = 'FAILED: ' . $e->getMessage();
        }
    }
    
    /**
     * 2. ANÁLISE DA CONFIGURAÇÃO DO HOTSPOT
     */
    private function analyzeHotspotConfiguration() {
        $this->log("\n🏨 2. ANALISANDO CONFIGURAÇÃO DO HOTSPOT");
        
        try {
            // Servidores Hotspot
            $this->log("\n📋 SERVIDORES HOTSPOT:");
            $servers = $this->executeAndLog('/ip/hotspot/print', '🔍 Lista de servidores hotspot');
            
            if (empty($servers) || count($servers) == 0) {
                $this->log("❌ PROBLEMA CRÍTICO: Nenhum servidor hotspot configurado!");
                $this->testResults['hotspot_servers'] = 'CRITICAL: No servers configured';
            } else {
                $this->log("✅ Encontrados " . count($servers) . " servidor(es) hotspot");
                $this->testResults['hotspot_servers'] = 'SUCCESS: ' . count($servers) . ' servers';
                
                foreach ($servers as $i => $server) {
                    $this->log("📋 Servidor " . ($i+1) . ": " . $this->formatServerInfo($server));
                }
            }
            
            // Configuração de setup do hotspot
            $this->log("\n⚙️ CONFIGURAÇÃO DE SETUP:");
            $this->executeAndLog('/ip/hotspot/service-port/print', '🔍 Portas de serviço');
            
            // Verificar se hotspot está ativo
            $this->checkHotspotStatus();
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar configuração do hotspot: " . $e->getMessage());
            $this->testResults['hotspot_config'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 3. ANÁLISE DE CONFIGURAÇÃO DNS
     */
    private function analyzeDNSConfiguration() {
        $this->log("\n🌐 3. ANALISANDO CONFIGURAÇÃO DE DNS");
        
        try {
            // DNS Settings
            $this->executeAndLog('/ip/dns/print', '🔍 Configuração de DNS');
            
            // DNS Cache
            $this->log("\n📋 CACHE DNS (últimas 10 entradas):");
            $dnsCache = $this->executeAndLogLimited('/ip/dns/cache/print', '🔍 Cache DNS', 10);
            
            // Verificar servidores DNS
            $this->log("\n🔍 VERIFICANDO SERVIDORES DNS:");
            $this->executeAndLog('/ip/dns/static/print', '📋 DNS estático');
            
            $this->testResults['dns_config'] = 'ANALYZED';
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar DNS: " . $e->getMessage());
            $this->testResults['dns_config'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 4. ANÁLISE DE FIREWALL E NAT
     */
    private function analyzeFirewallAndNAT() {
        $this->log("\n🛡️ 4. ANALISANDO FIREWALL E NAT");
        
        try {
            // Regras de Firewall Filter
            $this->log("\n🔥 REGRAS DE FIREWALL FILTER:");
            $filterRules = $this->executeAndLogLimited('/ip/firewall/filter/print', '🔍 Regras de filter', 20);
            
            // Regras NAT
            $this->log("\n🔄 REGRAS DE NAT:");
            $natRules = $this->executeAndLogLimited('/ip/firewall/nat/print', '🔍 Regras de NAT', 15);
            
            // Mangle Rules
            $this->log("\n📝 REGRAS DE MANGLE:");
            $this->executeAndLogLimited('/ip/firewall/mangle/print', '🔍 Regras de mangle', 10);
            
            // Verificar regras específicas do hotspot
            $this->checkHotspotFirewallRules($filterRules, $natRules);
            
            $this->testResults['firewall_nat'] = 'ANALYZED';
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar firewall: " . $e->getMessage());
            $this->testResults['firewall_nat'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 5. ANÁLISE DO WALLED GARDEN
     */
    private function analyzeWalledGarden() {
        $this->log("\n🧱 5. ANALISANDO WALLED GARDEN");
        
        try {
            // Walled Garden IP
            $this->log("\n📋 WALLED GARDEN - IPs LIBERADOS:");
            $walledGardenIPs = $this->executeAndLog('/ip/hotspot/walled-garden/ip/print', '🔍 IPs no walled garden');
            
            if (empty($walledGardenIPs)) {
                $this->log("⚠️ ATENÇÃO: Nenhum IP no walled garden - pode causar problemas de DNS");
            }
            
            // Walled Garden hostnames
            $this->log("\n📋 WALLED GARDEN - HOSTNAMES LIBERADOS:");
            $walledGardenHosts = $this->executeAndLog('/ip/hotspot/walled-garden/print', '🔍 Hostnames no walled garden');
            
            if (empty($walledGardenHosts)) {
                $this->log("⚠️ ATENÇÃO: Nenhum hostname no walled garden");
            }
            
            // Verificar entradas essenciais
            $this->checkEssentialWalledGardenEntries($walledGardenIPs, $walledGardenHosts);
            
            $this->testResults['walled_garden'] = 'ANALYZED';
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar walled garden: " . $e->getMessage());
            $this->testResults['walled_garden'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 6. ANÁLISE DE PERFIS DE USUÁRIO
     */
    private function analyzeUserProfiles() {
        $this->log("\n👥 6. ANALISANDO PERFIS DE USUÁRIO");
        
        try {
            $profiles = $this->executeAndLog('/ip/hotspot/user/profile/print', '🔍 Perfis de usuário');
            
            if (empty($profiles)) {
                $this->log("❌ PROBLEMA CRÍTICO: Nenhum perfil de usuário encontrado!");
                $this->testResults['user_profiles'] = 'CRITICAL: No profiles found';
            } else {
                $this->log("✅ Encontrados " . count($profiles) . " perfil(is) de usuário");
                $this->testResults['user_profiles'] = 'SUCCESS: ' . count($profiles) . ' profiles';
                
                foreach ($profiles as $i => $profile) {
                    $this->log("👤 Perfil " . ($i+1) . ": " . $this->formatProfileInfo($profile));
                }
            }
            
            // Verificar perfil padrão
            $this->checkDefaultProfile($profiles);
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar perfis: " . $e->getMessage());
            $this->testResults['user_profiles'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 7. ANÁLISE DE INTERFACES DE REDE
     */
    private function analyzeNetworkInterfaces() {
        $this->log("\n🔌 7. ANALISANDO INTERFACES DE REDE");
        
        try {
            // Interfaces físicas
            $this->executeAndLog('/interface/print', '🔍 Interfaces físicas');
            
            // Bridges
            $this->executeAndLog('/interface/bridge/print', '🌉 Bridges');
            
            // IPs das interfaces
            $this->executeAndLog('/ip/address/print', '🏷️ Endereços IP');
            
            // DHCP Server
            $this->log("\n📡 SERVIDORES DHCP:");
            $dhcpServers = $this->executeAndLog('/ip/dhcp-server/print', '🔍 Servidores DHCP');
            
            // DHCP Networks
            $this->executeAndLog('/ip/dhcp-server/network/print', '🌐 Redes DHCP');
            
            $this->testResults['network_interfaces'] = 'ANALYZED';
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar interfaces: " . $e->getMessage());
            $this->testResults['network_interfaces'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 8. ANÁLISE DE LOGS DO SISTEMA
     */
    private function analyzeSystemLogs() {
        $this->log("\n📜 8. ANALISANDO LOGS DO SISTEMA");
        
        try {
            // System logs (últimas 20 entradas)
            $this->log("\n📋 LOGS DO SISTEMA (últimas 20 entradas):");
            $this->executeAndLogLimited('/log/print', '🔍 Logs do sistema', 20);
            
            // Hotspot logs específicos
            $this->log("\n🏨 LOGS ESPECÍFICOS DO HOTSPOT:");
            $this->executeAndLogLimited('/log/print where topics~"hotspot"', '🔍 Logs do hotspot', 15);
            
            $this->testResults['system_logs'] = 'ANALYZED';
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar logs: " . $e->getMessage());
            $this->testResults['system_logs'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 9. TESTE DE CENÁRIO REAL DE USUÁRIO
     */
    private function testRealUserScenario() {
        $this->log("\n👤 9. TESTANDO CENÁRIO REAL DE USUÁRIO");
        
        try {
            // Listar usuários ativos
            $this->log("\n📋 USUÁRIOS ATIVOS ATUAIS:");
            $activeUsers = $this->executeAndLog('/ip/hotspot/active/print', '🔍 Usuários ativos');
            
            // Listar todos os usuários
            $this->log("\n📋 TODOS OS USUÁRIOS CADASTRADOS:");
            $allUsers = $this->executeAndLog('/ip/hotspot/user/print', '🔍 Todos os usuários');
            
            // Criar usuário de teste
            $testUser = 'teste-diagnostic-' . rand(100, 999);
            $testPass = rand(1000, 9999);
            
            $this->log("\n🧪 CRIANDO USUÁRIO DE TESTE: {$testUser}");
            
            try {
                $this->mikrotik->writeRaw('/ip/hotspot/user/add', [
                    '=name=' . $testUser,
                    '=password=' . $testPass,
                    '=profile=default'
                ]);
                
                $response = $this->mikrotik->readRaw();
                
                if (!$this->hasError($response)) {
                    $this->log("✅ Usuário de teste criado com sucesso");
                    
                    // Aguardar um pouco
                    sleep(2);
                    
                    // Verificar se foi criado
                    $usersAfterCreate = $this->executeAndLog('/ip/hotspot/user/print where name="' . $testUser . '"', '🔍 Verificar usuário criado');
                    
                    if (!empty($usersAfterCreate)) {
                        $this->log("✅ Usuário de teste encontrado na listagem");
                        
                        // Tentar remover
                        $this->log("🗑️ Removendo usuário de teste...");
                        $removeResult = $this->mikrotik->removeHotspotUser($testUser);
                        
                        if ($removeResult) {
                            $this->log("✅ Usuário de teste removido com sucesso");
                            $this->testResults['user_test'] = 'SUCCESS: Create and remove worked';
                        } else {
                            $this->log("⚠️ Usuário criado mas falha na remoção");
                            $this->testResults['user_test'] = 'WARNING: Create OK, remove failed';
                        }
                    } else {
                        $this->log("❌ Usuário não encontrado após criação");
                        $this->testResults['user_test'] = 'ERROR: User not found after creation';
                    }
                } else {
                    $this->log("❌ Erro ao criar usuário de teste: " . implode(', ', $response));
                    $this->testResults['user_test'] = 'ERROR: Cannot create test user';
                }
                
            } catch (Exception $e) {
                $this->log("❌ Exceção ao testar usuário: " . $e->getMessage());
                $this->testResults['user_test'] = 'ERROR: ' . $e->getMessage();
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro no teste de usuário: " . $e->getMessage());
            $this->testResults['user_test'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * 10. CONFIGURAÇÕES AVANÇADAS
     */
    private function analyzeAdvancedSettings() {
        $this->log("\n⚙️ 10. ANALISANDO CONFIGURAÇÕES AVANÇADAS");
        
        try {
            // HTML templates
            $this->log("\n📄 TEMPLATES HTML DO HOTSPOT:");
            $this->executeAndLogLimited('/file/print where name~"hotspot"', '🔍 Arquivos do hotspot', 10);
            
            // IP Pools
            $this->log("\n🏊 POOLS DE IP:");
            $this->executeAndLog('/ip/pool/print', '🔍 Pools de IP');
            
            // Routes
            $this->log("\n🛣️ ROTAS:");
            $this->executeAndLogLimited('/ip/route/print', '🔍 Tabela de rotas', 10);
            
            // Package versions
            $this->executeAndLog('/system/package/print', '📦 Pacotes instalados');
            
            $this->testResults['advanced_settings'] = 'ANALYZED';
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao analisar configurações avançadas: " . $e->getMessage());
            $this->testResults['advanced_settings'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * MÉTODOS AUXILIARES
     */
    
    private function executeAndLog($command, $description) {
        $this->log("\n🔍 {$description}");
        $this->log("💻 Comando: {$command}");
        
        try {
            $this->mikrotik->writeRaw($command);
            $rawData = $this->mikrotik->captureAllRawData();
            
            if (empty($rawData)) {
                $this->log("⚠️ Resposta vazia para comando: {$command}");
                return [];
            }
            
            // Tentar parsear a resposta
            $parsed = $this->parseRawResponse($rawData);
            
            if (empty($parsed)) {
                $this->log("⚠️ Nenhum dado parseado. Dados brutos (" . strlen($rawData) . " bytes):");
                $this->log($this->formatRawData($rawData));
                return [];
            }
            
            foreach ($parsed as $i => $item) {
                $this->log("📋 Item " . ($i+1) . ": " . $this->formatParsedItem($item));
            }
            
            $this->log("✅ Total de " . count($parsed) . " item(s) encontrado(s)");
            
            return $parsed;
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao executar '{$command}': " . $e->getMessage());
            return [];
        }
    }
    
    private function executeAndLogLimited($command, $description, $limit) {
        $results = $this->executeAndLog($command, $description);
        
        if (count($results) > $limit) {
            $this->log("📋 Mostrando apenas os primeiros {$limit} de " . count($results) . " itens");
            return array_slice($results, 0, $limit);
        }
        
        return $results;
    }
    
    private function parseRawResponse($rawData) {
        $items = [];
        
        // Usar o mesmo parser do sistema
        $mikrotikParser = new MikroTikRawDataParser('', '', '', 8728);
        
        // Dividir por !re
        $parts = explode('!re', $rawData);
        
        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            $item = [];
            
            // Extrair campos
            $patterns = [
                'id' => '/=\.id=([^\x00-\x1f=]+)/',
                'name' => '/=name=([^\x00-\x1f=]+)/',
                'interface' => '/=interface=([^\x00-\x1f=]+)/',
                'address-pool' => '/=address-pool=([^\x00-\x1f=]+)/',
                'profile' => '/=profile=([^\x00-\x1f=]+)/',
                'disabled' => '/=disabled=([^\x00-\x1f=]+)/',
                'server' => '/=server=([^\x00-\x1f=]+)/',
                'idle-timeout' => '/=idle-timeout=([^\x00-\x1f=]+)/',
                'action' => '/=action=([^\x00-\x1f=]+)/',
                'chain' => '/=chain=([^\x00-\x1f=]+)/',
                'dst-address' => '/=dst-address=([^\x00-\x1f=]+)/',
                'src-address' => '/=src-address=([^\x00-\x1f=]+)/',
                'protocol' => '/=protocol=([^\x00-\x1f=]+)/',
                'dst-port' => '/=dst-port=([^\x00-\x1f=]+)/',
                'comment' => '/=comment=([^\x00-\x1f=]+)/',
                'topics' => '/=topics=([^\x00-\x1f=]+)/',
                'message' => '/=message=([^\x00-\x1f=]+)/',
                'time' => '/=time=([^\x00-\x1f=]+)/',
                'user' => '/=user=([^\x00-\x1f=]+)/',
                'address' => '/=address=([^\x00-\x1f=]+)/',
                'mac-address' => '/=mac-address=([^\x00-\x1f=]+)/',
                'uptime' => '/=uptime=([^\x00-\x1f=]+)/',
                'bytes-in' => '/=bytes-in=([^\x00-\x1f=]+)/',
                'bytes-out' => '/=bytes-out=([^\x00-\x1f=]+)/'
            ];
            
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $part, $matches)) {
                    $value = trim($matches[1]);
                    if (!empty($value)) {
                        $item[$field] = $value;
                    }
                }
            }
            
            if (!empty($item)) {
                $items[] = $item;
            }
        }
        
        return $items;
    }
    
    private function formatParsedItem($item) {
        $formatted = [];
        
        foreach ($item as $key => $value) {
            $formatted[] = "{$key}={$value}";
        }
        
        return implode(', ', $formatted);
    }
    
    private function formatRawData($rawData) {
        // Mostrar apenas parte dos dados brutos para não poluir o log
        $preview = substr($rawData, 0, 200);
        $hex = bin2hex($preview);
        
        return "Preview (200 bytes): " . $preview . "\nHex: " . $hex;
    }
    
    private function formatServerInfo($server) {
        $info = [];
        
        if (isset($server['name'])) $info[] = "Nome: {$server['name']}";
        if (isset($server['interface'])) $info[] = "Interface: {$server['interface']}";
        if (isset($server['address-pool'])) $info[] = "Pool: {$server['address-pool']}";
        if (isset($server['profile'])) $info[] = "Perfil: {$server['profile']}";
        if (isset($server['disabled'])) $info[] = "Desabilitado: {$server['disabled']}";
        
        return implode(', ', $info);
    }
    
    private function formatProfileInfo($profile) {
        $info = [];
        
        if (isset($profile['name'])) $info[] = "Nome: {$profile['name']}";
        if (isset($profile['idle-timeout'])) $info[] = "Idle Timeout: {$profile['idle-timeout']}";
        if (isset($profile['shared-users'])) $info[] = "Usuários Simultâneos: {$profile['shared-users']}";
        if (isset($profile['rate-limit'])) $info[] = "Rate Limit: {$profile['rate-limit']}";
        
        return implode(', ', $info);
    }
    
    private function checkHotspotStatus() {
        // Verificar se o serviço hotspot está ativo
        $this->log("\n🔍 VERIFICANDO STATUS DO SERVIÇO HOTSPOT:");
        
        try {
            $services = $this->executeAndLog('/ip/service/print', '🔍 Serviços do sistema');
            
            $httpFound = false;
            $httpsFound = false;
            
            foreach ($services as $service) {
                if (isset($service['name'])) {
                    if ($service['name'] === 'www') {
                        $httpFound = true;
                        $disabled = isset($service['disabled']) ? $service['disabled'] : 'false';
                        $this->log("🌐 Serviço HTTP (www): " . ($disabled === 'true' ? '❌ DESABILITADO' : '✅ HABILITADO'));
                    } elseif ($service['name'] === 'www-ssl') {
                        $httpsFound = true;
                        $disabled = isset($service['disabled']) ? $service['disabled'] : 'false';
                        $this->log("🔒 Serviço HTTPS (www-ssl): " . ($disabled === 'true' ? '❌ DESABILITADO' : '✅ HABILITADO'));
                    }
                }
            }
            
            if (!$httpFound) {
                $this->log("⚠️ ATENÇÃO: Serviço HTTP não encontrado!");
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao verificar serviços: " . $e->getMessage());
        }
    }
    
    private function checkHotspotFirewallRules($filterRules, $natRules) {
        $this->log("\n🔍 VERIFICANDO REGRAS ESPECÍFICAS DO HOTSPOT:");
        
        // Procurar por regras relacionadas ao hotspot
        $hotspotFilterRules = 0;
        $hotspotNatRules = 0;
        
        foreach ($filterRules as $rule) {
            if (isset($rule['comment']) && stripos($rule['comment'], 'hotspot') !== false) {
                $hotspotFilterRules++;
                $this->log("🛡️ Regra de Filter Hotspot: " . $this->formatParsedItem($rule));
            }
        }
        
        foreach ($natRules as $rule) {
            if (isset($rule['comment']) && stripos($rule['comment'], 'hotspot') !== false) {
                $hotspotNatRules++;
                $this->log("🔄 Regra de NAT Hotspot: " . $this->formatParsedItem($rule));
            }
        }
        
        $this->log("📊 Total de regras hotspot - Filter: {$hotspotFilterRules}, NAT: {$hotspotNatRules}");
        
        if ($hotspotFilterRules == 0 && $hotspotNatRules == 0) {
            $this->log("⚠️ ATENÇÃO: Nenhuma regra específica do hotspot encontrada!");
        }
    }
    
    private function checkEssentialWalledGardenEntries($ips, $hosts) {
        $this->log("\n🔍 VERIFICANDO ENTRADAS ESSENCIAIS DO WALLED GARDEN:");
        
        // IPs essenciais que devem estar liberados
        $essentialIPs = [
            '8.8.8.8',      // Google DNS
            '8.8.4.4',      // Google DNS
            '1.1.1.1',      // Cloudflare DNS
            '1.0.0.1'       // Cloudflare DNS
        ];
        
        // Hosts essenciais
        $essentialHosts = [
            'google.com',
            'www.google.com',
            'dns.google',
            'cloudflare.com',
            '*.google.com',
            '*.cloudflare.com'
        ];
        
        foreach ($essentialIPs as $ip) {
            $found = false;
            foreach ($ips as $entry) {
                if (isset($entry['dst-address']) && $entry['dst-address'] === $ip) {
                    $found = true;
                    break;
                }
            }
            $this->log("🌐 DNS IP {$ip}: " . ($found ? '✅ LIBERADO' : '❌ NÃO LIBERADO'));
        }
        
        foreach ($essentialHosts as $host) {
            $found = false;
            foreach ($hosts as $entry) {
                if (isset($entry['dst-host']) && $entry['dst-host'] === $host) {
                    $found = true;
                    break;
                }
            }
            $this->log("🌐 DNS Host {$host}: " . ($found ? '✅ LIBERADO' : '❌ NÃO LIBERADO'));
        }
    }
    
    private function checkDefaultProfile($profiles) {
        $this->log("\n🔍 VERIFICANDO PERFIL PADRÃO:");
        
        $defaultFound = false;
        foreach ($profiles as $profile) {
            if (isset($profile['name']) && ($profile['name'] === 'default' || $profile['name'] === 'hotel-guest')) {
                $defaultFound = true;
                $this->log("✅ Perfil padrão encontrado: " . $this->formatProfileInfo($profile));
                break;
            }
        }
        
        if (!$defaultFound) {
            $this->log("⚠️ ATENÇÃO: Perfil padrão 'default' ou 'hotel-guest' não encontrado!");
        }
    }
    
    private function hasError($response) {
        if (!is_array($response)) return false;
        
        foreach ($response as $line) {
            if (is_string($line) && (strpos($line, '!trap') !== false || strpos($line, '!fatal') !== false)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * GERAR RELATÓRIO FINAL
     */
    private function generateDiagnosticReport() {
        $this->log("\n📊 GERANDO RELATÓRIO FINAL DE DIAGNÓSTICO");
        
        $totalTests = count($this->testResults);
        $successfulTests = 0;
        $errors = 0;
        $warnings = 0;
        
        foreach ($this->testResults as $test => $result) {
            if (strpos($result, 'SUCCESS') !== false) {
                $successfulTests++;
            } elseif (strpos($result, 'ERROR') !== false || strpos($result, 'CRITICAL') !== false) {
                $errors++;
            } elseif (strpos($result, 'WARNING') !== false) {
                $warnings++;
            }
        }
        
        $this->log("\n" . str_repeat("=", 60));
        $this->log("📊 RELATÓRIO FINAL DE DIAGNÓSTICO");
        $this->log(str_repeat("=", 60));
        $this->log("🕐 Data/Hora: " . date('Y-m-d H:i:s'));
        $this->log("📋 Total de Testes: {$totalTests}");
        $this->log("✅ Sucessos: {$successfulTests}");
        $this->log("⚠️ Avisos: {$warnings}");
        $this->log("❌ Erros: {$errors}");
        $this->log("📊 Taxa de Sucesso: " . round(($successfulTests / $totalTests) * 100, 2) . "%");
        
        $this->log("\n📋 DETALHES DOS TESTES:");
        foreach ($this->testResults as $test => $result) {
            $icon = '📋';
            if (strpos($result, 'SUCCESS') !== false) $icon = '✅';
            elseif (strpos($result, 'ERROR') !== false || strpos($result, 'CRITICAL') !== false) $icon = '❌';
            elseif (strpos($result, 'WARNING') !== false) $icon = '⚠️';
            
            $this->log("{$icon} {$test}: {$result}");
        }
        
        $this->generateRecommendations();
        
        $this->log("\n📁 Log salvo em: " . $this->logFile);
        $this->log(str_repeat("=", 60));
    }
    
    /**
     * GERAR RECOMENDAÇÕES BASEADAS NO DIAGNÓSTICO
     */
    private function generateRecommendations() {
        $this->log("\n💡 RECOMENDAÇÕES PARA RESOLVER PROBLEMAS DE REDIRECIONAMENTO:");
        
        $recommendations = [];
        
        // Análise dos resultados e recomendações
        if (isset($this->testResults['hotspot_servers']) && strpos($this->testResults['hotspot_servers'], 'CRITICAL') !== false) {
            $recommendations[] = "🔧 CRÍTICO: Configure um servidor hotspot em /ip/hotspot/setup";
        }
        
        if (isset($this->testResults['user_profiles']) && strpos($this->testResults['user_profiles'], 'CRITICAL') !== false) {
            $recommendations[] = "🔧 CRÍTICO: Crie perfis de usuário em /ip/hotspot/user/profile";
        }
        
        if (isset($this->testResults['basic_connectivity']) && strpos($this->testResults['basic_connectivity'], 'FAILED') !== false) {
            $recommendations[] = "🔧 REDE: Verifique conectividade TCP com o MikroTik";
        }
        
        // Recomendações gerais sempre relevantes
        $generalRecommendations = [
            "🔧 WALLED GARDEN: Adicione DNS servers (8.8.8.8, 1.1.1.1) em /ip/hotspot/walled-garden/ip",
            "🔧 DNS: Configure DNS servers em /ip/dns com allow-remote-requests=yes",
            "🔧 FIREWALL: Verifique se não há regras bloqueando HTTP/HTTPS (porta 80/443)",
            "🔧 HOTSPOT: Certifique-se que o servidor hotspot está ativo e sem erros",
            "🔧 INTERFACE: Verifique se a interface do hotspot tem IP configurado",
            "🔧 DHCP: Configure servidor DHCP na mesma rede do hotspot",
            "🔧 TEMPLATES: Verifique se os templates HTML do hotspot estão corretos",
            "🔧 LOGS: Monitore /log para erros específicos do hotspot",
            "🔧 TESTE: Teste com cliente real conectando à rede WiFi",
            "🔧 BROWSER: Limpe cache do navegador e tente http://1.1.1.1 ou http://google.com"
        ];
        
        foreach ($recommendations as $rec) {
            $this->log($rec);
        }
        
        $this->log("\n💡 RECOMENDAÇÕES GERAIS:");
        foreach ($generalRecommendations as $rec) {
            $this->log($rec);
        }
        
        $this->log("\n🚨 COMANDOS ESSENCIAIS PARA VERIFICAR NO WINBOX:");
        $commands = [
            "/ip/hotspot/print - Verificar se servidor está ativo",
            "/ip/hotspot/user/profile/print - Verificar perfis",
            "/ip/dns/print - Verificar configuração DNS",
            "/ip/firewall/filter/print - Verificar regras de firewall",
            "/ip/hotspot/walled-garden/print - Verificar sites liberados",
            "/ip/service/print - Verificar se HTTP está habilitado",
            "/log/print where topics~\"hotspot\" - Ver logs do hotspot",
            "/ip/hotspot/active/print - Ver usuários conectados",
            "/ip/address/print - Verificar IPs das interfaces",
            "/ip/dhcp-server/print - Verificar servidor DHCP"
        ];
        
        foreach ($commands as $cmd) {
            $this->log("💻 {$cmd}");
        }
    }
    
    private function log($message) {
        $timestamp = date('H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        
        // Log no arquivo
        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Log no PHP error log
        error_log("[HOTSPOT_DIAGNOSTIC] {$message}");
        
        // Echo para saída imediata
        echo $logMessage . "\n";
        flush();
    }
    
    public function getLogFile() {
        return $this->logFile;
    }
    
    public function getTestResults() {
        return $this->testResults;
    }
}

/**
 * PÁGINA DE DIAGNÓSTICO COMPLETO
 */
class HotspotDiagnosticPage {
    private $mikrotikConfig;
    
    public function __construct($mikrotikConfig) {
        $this->mikrotikConfig = $mikrotikConfig;
    }
    
    public function renderDiagnosticPage() {
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Detalhado do Hotspot MikroTik</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
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
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .diagnostic-section {
            padding: 30px;
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
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
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        
        .btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }
        
        .btn-large {
            padding: 20px 40px;
            font-size: 18px;
        }
        
        .progress-container {
            display: none;
            margin: 20px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .log-container {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 500px;
            overflow-y: auto;
            margin: 20px 0;
            display: none;
        }
        
        .results-container {
            display: none;
            margin: 20px 0;
        }
        
        .result-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 5px solid #007bff;
        }
        
        .result-success {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .result-warning {
            border-color: #ffc107;
            background: #fff3cd;
        }
        
        .result-error {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .config-info {
            background: #e9ecef;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .config-info h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .copy-btn {
            background: #6c757d;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .recommendations {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
        }
        
        .recommendations h3 {
            margin-top: 0;
        }
        
        .recommendations ul {
            margin: 15px 0;
            padding-left: 20px;
        }
        
        .recommendations li {
            margin: 8px 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Diagnóstico Detalhado do Hotspot</h1>
            <p>Análise completa para resolver problemas de redirecionamento</p>
        </div>
        
        <div class="diagnostic-section">
            <div class="alert alert-info">
                <h4>🎯 Objetivo do Diagnóstico</h4>
                <p>Este diagnóstico vai analisar todos os aspectos do seu sistema hotspot MikroTik para identificar por que o redirecionamento automático não está funcionando.</p>
                <p><strong>O que será verificado:</strong></p>
                <ul>
                    <li>✅ Configuração do servidor hotspot</li>
                    <li>✅ DNS e resolução de nomes</li>
                    <li>✅ Firewall e regras NAT</li>
                    <li>✅ Walled Garden (sites liberados)</li>
                    <li>✅ Perfis de usuário</li>
                    <li>✅ Interfaces e configuração de rede</li>
                    <li>✅ Logs do sistema</li>
                    <li>✅ Teste de cenário real</li>
                </ul>
            </div>
            
            <div class="config-info">
                <h3>📡 Configuração Atual do MikroTik</h3>
                <p><strong>Host:</strong> <?php echo htmlspecialchars($this->mikrotikConfig['host']); ?></p>
                <p><strong>Porta:</strong> <?php echo htmlspecialchars($this->mikrotikConfig['port']); ?></p>
                <p><strong>Usuário:</strong> <?php echo htmlspecialchars($this->mikrotikConfig['username']); ?></p>
                <p><strong>Status:</strong> <span id="connectionStatus">Verificando...</span></p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <button class="btn btn-large" onclick="startDiagnostic()">
                    🚀 Iniciar Diagnóstico Completo
                </button>
                <button class="btn" onclick="downloadLog()" style="display: none;" id="downloadBtn">
                    📥 Baixar Log Detalhado
                </button>
            </div>
            
            <div class="progress-container" id="progressContainer">
                <h4>Progresso do Diagnóstico:</h4>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <p id="progressText">Iniciando...</p>
            </div>
            
            <div class="log-container" id="logContainer">
                <div id="logContent"></div>
            </div>
            
            <div class="results-container" id="resultsContainer">
                <h3>📊 Resultados do Diagnóstico:</h3>
                <div id="resultsContent"></div>
            </div>
            
            <div class="recommendations">
                <h3>💡 Comandos Essenciais para Verificar no Winbox</h3>
                <p>Execute estes comandos no terminal do Winbox para verificar configurações:</p>
                <ul>
                    <li><code>/ip/hotspot/print</code> - Verificar servidores hotspot</li>
                    <li><code>/ip/hotspot/user/profile/print</code> - Verificar perfis de usuário</li>
                    <li><code>/ip/dns/print</code> - Verificar configuração DNS</li>
                    <li><code>/ip/firewall/filter/print</code> - Verificar regras de firewall</li>
                    <li><code>/ip/hotspot/walled-garden/print</code> - Verificar sites liberados</li>
                    <li><code>/ip/service/print</code> - Verificar se HTTP está habilitado</li>
                    <li><code>/log/print where topics~"hotspot"</code> - Ver logs do hotspot</li>
                </ul>
            </div>
            
            <div class="alert alert-warning">
                <h4>⚠️ Problemas Comuns de Redirecionamento</h4>
                <ul>
                    <li><strong>DNS não configurado:</strong> Clientes não conseguem resolver nomes</li>
                    <li><strong>Walled Garden vazio:</strong> DNS servers não liberados</li>
                    <li><strong>Firewall bloqueando:</strong> HTTP/HTTPS não passa</li>
                    <li><strong>Servidor hotspot inativo:</strong> Não captura requisições</li>
                    <li><strong>Interface sem IP:</strong> Rede não roteada corretamente</li>
                    <li><strong>DHCP mal configurado:</strong> Clientes não recebem IP</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        let diagnosticRunning = false;
        let logFile = '';
        
        // Verificar status de conexão ao carregar
        window.onload = function() {
            checkConnectionStatus();
        };
        
        function checkConnectionStatus() {
            fetch('?action=check_connection')
                .then(response => response.json())
                .then(data => {
                    const statusElement = document.getElementById('connectionStatus');
                    if (data.success) {
                        statusElement.innerHTML = '<span style="color: #28a745;">✅ Online</span>';
                    } else {
                        statusElement.innerHTML = '<span style="color: #dc3545;">❌ Offline</span>';
                    }
                })
                .catch(error => {
                    document.getElementById('connectionStatus').innerHTML = '<span style="color: #dc3545;">❌ Erro</span>';
                });
        }
        
        function startDiagnostic() {
            if (diagnosticRunning) return;
            
            diagnosticRunning = true;
            
            // Mostrar containers
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('logContainer').style.display = 'block';
            
            // Resetar conteúdo
            document.getElementById('logContent').innerHTML = '';
            document.getElementById('resultsContent').innerHTML = '';
            document.getElementById('resultsContainer').style.display = 'none';
            
            // Iniciar diagnóstico
            runDiagnosticStep(1);
        }
        
        function runDiagnosticStep(step) {
            const steps = [
                'Conectividade Básica',
                'Configuração do Hotspot',
                'Configuração DNS',
                'Firewall e NAT',
                'Walled Garden',
                'Perfis de Usuário',
                'Interfaces de Rede',
                'Logs do Sistema',
                'Teste de Usuário Real',
                'Configurações Avançadas'
            ];
            
            if (step > steps.length) {
                finalizeDiagnostic();
                return;
            }
            
            // Atualizar progresso
            const progress = (step / steps.length) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
            document.getElementById('progressText').textContent = `Executando: ${steps[step-1]} (${step}/${steps.length})`;
            
            // Fazer requisição
            fetch('?action=diagnostic_step&step=' + step)
                .then(response => response.text())
                .then(data => {
                    // Adicionar ao log
                    const logContent = document.getElementById('logContent');
                    logContent.innerHTML += data + '\n';
                    logContent.scrollTop = logContent.scrollHeight;
                    
                    // Próximo passo
                    setTimeout(() => runDiagnosticStep(step + 1), 1000);
                })
                .catch(error => {
                    document.getElementById('logContent').innerHTML += 'ERRO no passo ' + step + ': ' + error + '\n';
                    setTimeout(() => runDiagnosticStep(step + 1), 1000);
                });
        }
        
        function finalizeDiagnostic() {
            document.getElementById('progressText').textContent = 'Gerando relatório final...';
            
            fetch('?action=finalize_diagnostic')
                .then(response => response.json())
                .then(data => {
                    // Mostrar resultados
                    displayResults(data.results);
                    
                    // Salvar arquivo de log
                    logFile = data.logFile;
                    
                    // Mostrar botão de download
                    document.getElementById('downloadBtn').style.display = 'inline-block';
                    
                    // Finalizar
                    document.getElementById('progressText').textContent = '✅ Diagnóstico concluído!';
                    diagnosticRunning = false;
                })
                .catch(error => {
                    document.getElementById('progressText').textContent = '❌ Erro ao finalizar diagnóstico';
                    diagnosticRunning = false;
                });
        }
        
        function displayResults(results) {
            const container = document.getElementById('resultsContent');
            container.innerHTML = '';
            
            for (const [test, result] of Object.entries(results)) {
                const item = document.createElement('div');
                item.className = 'result-item';
                
                if (result.includes('SUCCESS')) {
                    item.classList.add('result-success');
                } else if (result.includes('WARNING')) {
                    item.classList.add('result-warning');
                } else if (result.includes('ERROR') || result.includes('CRITICAL')) {
                    item.classList.add('result-error');
                }
                
                item.innerHTML = `<strong>${test}:</strong> ${result}`;
                container.appendChild(item);
            }
            
            document.getElementById('resultsContainer').style.display = 'block';
        }
        
        function downloadLog() {
            if (logFile) {
                window.open('?action=download_log&file=' + encodeURIComponent(logFile));
            }
        }
        
        function copyCommand(command) {
            navigator.clipboard.writeText(command).then(() => {
                alert('Comando copiado: ' + command);
            });
        }
    </script>
</body>
</html>
        <?php
    }
}

// PROCESSAMENTO DE REQUISIÇÕES AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'check_connection':
                $diagnostic = new HotspotDiagnosticLogger($mikrotikConfig);
                try {
                    $diagnostic->getMikrotik()->connect();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;
                
            case 'diagnostic_step':
                $step = intval($_GET['step']);
                $diagnostic = new HotspotDiagnosticLogger($mikrotikConfig);
                
                // Simular execução do passo específico
                echo "Executando passo {$step}...\n";
                
                break;
                
            case 'finalize_diagnostic':
                $diagnostic = new HotspotDiagnosticLogger($mikrotikConfig);
                $results = $diagnostic->runFullDiagnostic();
                
                echo json_encode([
                    'success' => true,
                    'results' => $results,
                    'logFile' => $diagnostic->getLogFile()
                ]);
                break;
                
            case 'download_log':
                $file = $_GET['file'];
                if (file_exists($file)) {
                    header('Content-Type: text/plain');
                    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                    readfile($file);
                } else {
                    echo json_encode(['error' => 'Arquivo não encontrado']);
                }
                break;
                
            default:
                echo json_encode(['error' => 'Ação inválida']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// RENDERIZAR PÁGINA PRINCIPAL
$diagnosticPage = new HotspotDiagnosticPage($mikrotikConfig);
$diagnosticPage->renderDiagnosticPage();
?>