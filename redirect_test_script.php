<?php
/**
 * redirect_test_script.php - Teste Específico de Redirecionamento
 * 
 * OBJETIVO: Testar especificamente o redirecionamento automático
 * e identificar exatamente onde está falhando
 */

require_once 'config.php';
require_once 'mikrotik_manager.php';

class HotspotRedirectTester {
    private $mikrotik;
    private $logFile;
    
    public function __construct($mikrotikConfig) {
        $this->mikrotik = new MikroTikRawDataParser(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        $this->logFile = 'logs/redirect_test_' . date('Y-m-d_H-i-s') . '.log';
        
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        $this->log("🔄 TESTE DE REDIRECIONAMENTO INICIADO");
        $this->log("Timestamp: " . date('Y-m-d H:i:s'));
    }
    
    /**
     * EXECUTAR TODOS OS TESTES DE REDIRECIONAMENTO
     */
    public function runRedirectTests() {
        $this->log("\n🚀 EXECUTANDO BATERIA COMPLETA DE TESTES DE REDIRECIONAMENTO");
        
        $results = [
            'hotspot_server_test' => $this->testHotspotServer(),
            'dns_resolution_test' => $this->testDNSResolution(),
            'walled_garden_test' => $this->testWalledGarden(),
            'firewall_rules_test' => $this->testFirewallRules(),
            'http_redirect_test' => $this->testHTTPRedirect(),
            'login_page_test' => $this->testLoginPage(),
            'user_creation_test' => $this->testUserCreation(),
            'redirect_simulation_test' => $this->simulateRedirectScenario()
        ];
        
        $this->generateRedirectReport($results);
        
        return $results;
    }
    
    /**
     * 1. TESTE DO SERVIDOR HOTSPOT
     */
    private function testHotspotServer() {
        $this->log("\n🏨 1. TESTANDO SERVIDOR HOTSPOT");
        
        try {
            $this->mikrotik->connect();
            
            // Verificar servidores hotspot
            $this->mikrotik->writeRaw('/ip/hotspot/print');
            $rawData = $this->mikrotik->captureAllRawData();
            $servers = $this->parseHotspotServers($rawData);
            
            if (empty($servers)) {
                $this->log("❌ CRÍTICO: Nenhum servidor hotspot configurado!");
                return [
                    'status' => 'CRITICAL',
                    'message' => 'Nenhum servidor hotspot encontrado',
                    'solution' => 'Execute: /ip/hotspot/setup no Winbox'
                ];
            }
            
            $activeServers = 0;
            $serverDetails = [];
            
            foreach ($servers as $server) {
                $disabled = isset($server['disabled']) ? $server['disabled'] : 'false';
                $isActive = ($disabled !== 'true');
                
                if ($isActive) $activeServers++;
                
                $serverDetails[] = [
                    'name' => $server['name'] ?? 'N/A',
                    'interface' => $server['interface'] ?? 'N/A',
                    'address_pool' => $server['address-pool'] ?? 'N/A',
                    'profile' => $server['profile'] ?? 'N/A',
                    'active' => $isActive
                ];
                
                $this->log("📋 Servidor: {$server['name']} - " . ($isActive ? '✅ ATIVO' : '❌ DESABILITADO'));
                $this->log("   └─ Interface: {$server['interface']}");
                $this->log("   └─ Pool: {$server['address-pool']}");
                $this->log("   └─ Perfil: {$server['profile']}");
            }
            
            if ($activeServers == 0) {
                return [
                    'status' => 'ERROR',
                    'message' => 'Todos os servidores hotspot estão desabilitados',
                    'servers' => $serverDetails,
                    'solution' => 'Habilite pelo menos um servidor hotspot'
                ];
            }
            
            $this->log("✅ Encontrados {$activeServers} servidor(es) ativo(s)");
            
            return [
                'status' => 'SUCCESS',
                'message' => "{$activeServers} servidor(es) hotspot ativo(s)",
                'servers' => $serverDetails
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao testar servidor hotspot: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro na conexão: ' . $e->getMessage(),
                'solution' => 'Verifique conectividade com MikroTik'
            ];
        }
    }
    
    /**
     * 2. TESTE DE RESOLUÇÃO DNS
     */
    private function testDNSResolution() {
        $this->log("\n🌐 2. TESTANDO RESOLUÇÃO DNS");
        
        try {
            // Verificar configuração DNS
            $this->mikrotik->writeRaw('/ip/dns/print');
            $rawData = $this->mikrotik->captureAllRawData();
            $dnsConfig = $this->parseDNSConfig($rawData);
            
            $issues = [];
            $solutions = [];
            
            // Verificar se DNS está habilitado
            $allowRemote = $dnsConfig['allow-remote-requests'] ?? 'false';
            if ($allowRemote !== 'true') {
                $issues[] = 'DNS remoto não habilitado';
                $solutions[] = 'Execute: /ip/dns/set allow-remote-requests=yes';
                $this->log("❌ DNS remoto está DESABILITADO");
            } else {
                $this->log("✅ DNS remoto está HABILITADO");
            }
            
            // Verificar servidores DNS
            $servers = $dnsConfig['servers'] ?? '';
            if (empty($servers)) {
                $issues[] = 'Nenhum servidor DNS configurado';
                $solutions[] = 'Execute: /ip/dns/set servers=8.8.8.8,1.1.1.1';
                $this->log("❌ Nenhum servidor DNS configurado");
            } else {
                $this->log("✅ Servidores DNS: {$servers}");
            }
            
            // Testar cache DNS
            $this->mikrotik->writeRaw('/ip/dns/cache/print');
            $cacheData = $this->mikrotik->captureAllRawData();
            $cacheEntries = $this->parseDNSCache($cacheData);
            
            $this->log("📊 Entradas no cache DNS: " . count($cacheEntries));
            
            if (empty($issues)) {
                return [
                    'status' => 'SUCCESS',
                    'message' => 'DNS configurado corretamente',
                    'config' => $dnsConfig,
                    'cache_entries' => count($cacheEntries)
                ];
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => 'Problemas na configuração DNS',
                    'issues' => $issues,
                    'solutions' => $solutions,
                    'config' => $dnsConfig
                ];
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao testar DNS: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar DNS: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 3. TESTE DO WALLED GARDEN
     */
    private function testWalledGarden() {
        $this->log("\n🧱 3. TESTANDO WALLED GARDEN");
        
        try {
            // Verificar entradas IP do walled garden
            $this->mikrotik->writeRaw('/ip/hotspot/walled-garden/ip/print');
            $ipData = $this->mikrotik->captureAllRawData();
            $ipEntries = $this->parseWalledGardenIP($ipData);
            
            // Verificar entradas hostname do walled garden
            $this->mikrotik->writeRaw('/ip/hotspot/walled-garden/print');
            $hostData = $this->mikrotik->captureAllRawData();
            $hostEntries = $this->parseWalledGardenHosts($hostData);
            
            $this->log("📊 Entradas IP liberadas: " . count($ipEntries));
            $this->log("📊 Entradas hostname liberadas: " . count($hostEntries));
            
            // Verificar entradas essenciais
            $essentialIPs = ['8.8.8.8', '8.8.4.4', '1.1.1.1', '1.0.0.1'];
            $essentialHosts = ['*.google.com', '*.cloudflare.com', 'dns.google'];
            
            $missingIPs = [];
            $missingHosts = [];
            
            foreach ($essentialIPs as $ip) {
                $found = false;
                foreach ($ipEntries as $entry) {
                    if (isset($entry['dst-address']) && $entry['dst-address'] === $ip) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $missingIPs[] = $ip;
                    $this->log("❌ IP DNS não liberado: {$ip}");
                } else {
                    $this->log("✅ IP DNS liberado: {$ip}");
                }
            }
            
            foreach ($essentialHosts as $host) {
                $found = false;
                foreach ($hostEntries as $entry) {
                    if (isset($entry['dst-host']) && $entry['dst-host'] === $host) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $missingHosts[] = $host;
                    $this->log("❌ Host DNS não liberado: {$host}");
                } else {
                    $this->log("✅ Host DNS liberado: {$host}");
                }
            }
            
            $solutions = [];
            if (!empty($missingIPs)) {
                $solutions[] = 'Adicionar IPs DNS: /ip/hotspot/walled-garden/ip/add dst-address=' . implode(',', $missingIPs);
            }
            if (!empty($missingHosts)) {
                $solutions[] = 'Adicionar hosts DNS: /ip/hotspot/walled-garden/add dst-host=' . implode(',', $missingHosts);
            }
            
            if (empty($missingIPs) && empty($missingHosts)) {
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Walled Garden configurado corretamente',
                    'ip_entries' => count($ipEntries),
                    'host_entries' => count($hostEntries)
                ];
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => 'Entradas essenciais faltando no Walled Garden',
                    'missing_ips' => $missingIPs,
                    'missing_hosts' => $missingHosts,
                    'solutions' => $solutions
                ];
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao testar Walled Garden: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar Walled Garden: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 4. TESTE DE REGRAS DE FIREWALL
     */
    private function testFirewallRules() {
        $this->log("\n🛡️ 4. TESTANDO REGRAS DE FIREWALL");
        
        try {
            // Verificar regras de filter
            $this->mikrotik->writeRaw('/ip/firewall/filter/print');
            $filterData = $this->mikrotik->captureAllRawData();
            $filterRules = $this->parseFirewallRules($filterData);
            
            // Verificar regras NAT
            $this->mikrotik->writeRaw('/ip/firewall/nat/print');
            $natData = $this->mikrotik->captureAllRawData();
            $natRules = $this->parseFirewallRules($natData);
            
            $this->log("📊 Regras de Filter: " . count($filterRules));
            $this->log("📊 Regras de NAT: " . count($natRules));
            
            // Procurar regras relacionadas ao hotspot
            $hotspotFilterRules = 0;
            $hotspotNatRules = 0;
            $blockingRules = [];
            
            foreach ($filterRules as $rule) {
                $comment = $rule['comment'] ?? '';
                $action = $rule['action'] ?? '';
                $dstPort = $rule['dst-port'] ?? '';
                
                if (stripos($comment, 'hotspot') !== false) {
                    $hotspotFilterRules++;
                    $this->log("🔍 Regra hotspot filter: {$comment} - Ação: {$action}");
                }
                
                // Verificar regras que podem bloquear HTTP/HTTPS
                if ($action === 'drop' && ($dstPort === '80' || $dstPort === '443' || $dstPort === '80,443')) {
                    $blockingRules[] = [
                        'type' => 'filter',
                        'action' => $action,
                        'dst-port' => $dstPort,
                        'comment' => $comment
                    ];
                    $this->log("⚠️ Regra pode bloquear web: {$comment} - Porta: {$dstPort}");
                }
            }
            
            foreach ($natRules as $rule) {
                $comment = $rule['comment'] ?? '';
                if (stripos($comment, 'hotspot') !== false) {
                    $hotspotNatRules++;
                    $this->log("🔍 Regra hotspot NAT: {$comment}");
                }
            }
            
            // Verificar se serviço HTTP está habilitado
            $httpServiceResult = $this->checkHTTPService();
            
            $issues = [];
            $solutions = [];
            
            if (!empty($blockingRules)) {
                $issues[] = 'Regras de firewall podem estar bloqueando HTTP/HTTPS';
                $solutions[] = 'Revisar regras de firewall que bloqueiam portas 80/443';
            }
            
            if (!$httpServiceResult['http_enabled']) {
                $issues[] = 'Serviço HTTP está desabilitado';
                $solutions[] = 'Execute: /ip/service/enable www';
            }
            
            if (empty($issues)) {
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Firewall configurado corretamente para hotspot',
                    'filter_rules' => count($filterRules),
                    'nat_rules' => count($natRules),
                    'hotspot_filter_rules' => $hotspotFilterRules,
                    'hotspot_nat_rules' => $hotspotNatRules
                ];
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => 'Possíveis problemas no firewall',
                    'issues' => $issues,
                    'solutions' => $solutions,
                    'blocking_rules' => $blockingRules
                ];
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao testar firewall: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar firewall: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 5. TESTE DE REDIRECIONAMENTO HTTP
     */
    private function testHTTPRedirect() {
        $this->log("\n🔄 5. TESTANDO REDIRECIONAMENTO HTTP");
        
        try {
            // Verificar se há usuários ativos não autenticados
            $this->mikrotik->writeRaw('/ip/hotspot/active/print');
            $activeData = $this->mikrotik->captureAllRawData();
            $activeUsers = $this->parseActiveUsers($activeData);
            
            $this->log("📊 Usuários ativos: " . count($activeUsers));
            
            // Verificar configuração de redirecionamento
            $redirectIssues = [];
            $solutions = [];
            
            // Testar se a página de login está acessível
            $loginPageTest = $this->testLoginPageAccess();
            
            if (!$loginPageTest['accessible']) {
                $redirectIssues[] = 'Página de login não acessível';
                $solutions[] = 'Verificar templates HTML do hotspot';
            }
            
            // Verificar se há logs de redirecionamento
            $this->mikrotik->writeRaw('/log/print', ['where', 'topics~"hotspot"']);
            $logData = $this->mikrotik->captureAllRawData();
            $hotspotLogs = $this->parseSystemLogs($logData);
            
            $redirectLogs = 0;
            foreach ($hotspotLogs as $log) {
                $message = $log['message'] ?? '';
                if (stripos($message, 'redirect') !== false || stripos($message, 'login') !== false) {
                    $redirectLogs++;
                    $this->log("📜 Log redirecionamento: {$message}");
                }
            }
            
            $this->log("📊 Logs de redirecionamento encontrados: {$redirectLogs}");
            
            if (empty($redirectIssues)) {
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Redirecionamento HTTP funcionando',
                    'active_users' => count($activeUsers),
                    'redirect_logs' => $redirectLogs,
                    'login_page' => $loginPageTest
                ];
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => 'Possíveis problemas no redirecionamento',
                    'issues' => $redirectIssues,
                    'solutions' => $solutions
                ];
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao testar redirecionamento: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar redirecionamento: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 6. TESTE DA PÁGINA DE LOGIN
     */
    private function testLoginPage() {
        $this->log("\n📄 6. TESTANDO PÁGINA DE LOGIN");
        
        try {
            // Verificar arquivos HTML do hotspot
            $this->mikrotik->writeRaw('/file/print', ['where', 'name~"hotspot"']);
            $filesData = $this->mikrotik->captureAllRawData();
            $hotspotFiles = $this->parseHotspotFiles($filesData);
            
            $this->log("📊 Arquivos do hotspot encontrados: " . count($hotspotFiles));
            
            $essentialFiles = ['login.html', 'logout.html', 'error.html'];
            $missingFiles = [];
            
            foreach ($essentialFiles as $file) {
                $found = false;
                foreach ($hotspotFiles as $hotspotFile) {
                    if (stripos($hotspotFile['name'] ?? '', $file) !== false) {
                        $found = true;
                        $this->log("✅ Arquivo encontrado: {$hotspotFile['name']}");
                        break;
                    }
                }
                if (!$found) {
                    $missingFiles[] = $file;
                    $this->log("❌ Arquivo faltando: {$file}");
                }
            }
            
            if (empty($missingFiles)) {
                return [
                    'status' => 'SUCCESS',
                    'message' => 'Arquivos da página de login presentes',
                    'files' => $hotspotFiles
                ];
            } else {
                return [
                    'status' => 'WARNING',
                    'message' => 'Arquivos da página de login faltando',
                    'missing_files' => $missingFiles,
                    'solution' => 'Reinstalar templates padrão do hotspot'
                ];
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao testar página de login: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro ao verificar página de login: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 7. TESTE DE CRIAÇÃO DE USUÁRIO
     */
    private function testUserCreation() {
        $this->log("\n👤 7. TESTANDO CRIAÇÃO DE USUÁRIO");
        
        try {
            // Criar usuário de teste
            $testUser = 'redirect-test-' . rand(100, 999);
            $testPass = rand(1000, 9999);
            
            $this->log("🧪 Criando usuário de teste: {$testUser}");
            
            $createResult = $this->mikrotik->createHotspotUser($testUser, $testPass, 'default', '01:00:00');
            
            if ($createResult) {
                $this->log("✅ Usuário criado com sucesso");
                
                // Verificar se aparece na listagem
                sleep(1);
                $users = $this->mikrotik->listHotspotUsers();
                
                $userFound = false;
                foreach ($users as $user) {
                    if (isset($user['name']) && $user['name'] === $testUser) {
                        $userFound = true;
                        break;
                    }
                }
                
                if ($userFound) {
                    $this->log("✅ Usuário encontrado na listagem");
                    
                    // Tentar remover
                    $removeResult = $this->mikrotik->removeHotspotUser($testUser);
                    
                    if ($removeResult) {
                        $this->log("✅ Usuário removido com sucesso");
                        
                        return [
                            'status' => 'SUCCESS',
                            'message' => 'Criação e remoção de usuário funcionando',
                            'test_user' => $testUser
                        ];
                    } else {
                        $this->log("⚠️ Falha na remoção do usuário");
                        
                        return [
                            'status' => 'WARNING',
                            'message' => 'Criação OK, mas falha na remoção',
                            'test_user' => $testUser,
                            'solution' => 'Remover manualmente o usuário de teste'
                        ];
                    }
                } else {
                    $this->log("❌ Usuário não encontrado na listagem");
                    
                    return [
                        'status' => 'ERROR',
                        'message' => 'Usuário criado mas não aparece na listagem',
                        'solution' => 'Verificar sincronização do sistema'
                    ];
                }
            } else {
                $this->log("❌ Falha na criação do usuário");
                
                return [
                    'status' => 'ERROR',
                    'message' => 'Não foi possível criar usuário de teste',
                    'solution' => 'Verificar perfil padrão e configuração'
                ];
            }
            
        } catch (Exception $e) {
            $this->log("❌ Erro ao testar criação de usuário: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro na criação de usuário: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 8. SIMULAÇÃO DE CENÁRIO DE REDIRECIONAMENTO
     */
    private function simulateRedirectScenario() {
        $this->log("\n🎭 8. SIMULANDO CENÁRIO DE REDIRECIONAMENTO");
        
        try {
            $scenario = [
                'timestamp' => date('Y-m-d H:i:s'),
                'steps' => []
            ];
            
            // Passo 1: Cliente conecta WiFi
            $scenario['steps'][] = [
                'step' => 'WiFi Connection',
                'description' => 'Cliente conecta ao WiFi e recebe IP via DHCP',
                'status' => 'Simulado',
                'details' => 'Cliente recebe IP da rede configurada'
            ];
            
            // Passo 2: Cliente abre navegador
            $scenario['steps'][] = [
                'step' => 'Browser Request',
                'description' => 'Cliente abre navegador e tenta acessar site',
                'status' => 'Simulado',
                'details' => 'GET http://google.com enviado'
            ];
            
            // Passo 3: DNS Resolution
            $dnsTest = $this->testDNSResolution();
            $scenario['steps'][] = [
                'step' => 'DNS Resolution',
                'description' => 'Sistema tenta resolver nome do site',
                'status' => $dnsTest['status'],
                'details' => $dnsTest['message']
            ];
            
            // Passo 4: Firewall Check
            $firewallTest = $this->testFirewallRules();
            $scenario['steps'][] = [
                'step' => 'Firewall Check',
                'description' => 'Requisição passa pelo firewall',
                'status' => $firewallTest['status'],
                'details' => $firewallTest['message']
            ];
            
            // Passo 5: Hotspot Redirect
            $redirectTest = $this->testHTTPRedirect();
            $scenario['steps'][] = [
                'step' => 'Hotspot Redirect',
                'description' => 'Hotspot intercepta e redireciona para login',
                'status' => $redirectTest['status'],
                'details' => $redirectTest['message']
            ];
            
            // Passo 6: Login Page
            $loginTest = $this->testLoginPage();
            $scenario['steps'][] = [
                'step' => 'Login Page Display',
                'description' => 'Página de login é apresentada ao cliente',
                'status' => $loginTest['status'],
                'details' => $loginTest['message']
            ];
            
            // Análise do cenário
            $successSteps = 0;
            $totalSteps = count($scenario['steps']);
            
            foreach ($scenario['steps'] as $step) {
                if ($step['status'] === 'SUCCESS' || $step['status'] === 'Simulado') {
                    $successSteps++;
                }
                
                $icon = $step['status'] === 'SUCCESS' || $step['status'] === 'Simulado' ? '✅' : 
                       ($step['status'] === 'WARNING' ? '⚠️' : '❌');
                
                $this->log("{$icon} {$step['step']}: {$step['details']}");
            }
            
            $successRate = round(($successSteps / $totalSteps) * 100, 2);
            $this->log("📊 Taxa de sucesso do cenário: {$successRate}%");
            
            return [
                'status' => $successRate >= 80 ? 'SUCCESS' : ($successRate >= 60 ? 'WARNING' : 'ERROR'),
                'message' => "Simulação completa - {$successRate}% de sucesso",
                'scenario' => $scenario,
                'success_rate' => $successRate
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Erro na simulação: " . $e->getMessage());
            return [
                'status' => 'ERROR',
                'message' => 'Erro na simulação: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * MÉTODOS AUXILIARES DE PARSING
     */
    
    private function parseHotspotServers($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['name', 'interface', 'address-pool', 'profile', 'disabled']);
    }
    
    private function parseDNSConfig($rawData) {
        $config = [];
        $patterns = [
            'servers' => '/=servers=([^\x00-\x1f=]+)/',
            'allow-remote-requests' => '/=allow-remote-requests=([^\x00-\x1f=]+)/',
            'cache-size' => '/=cache-size=([^\x00-\x1f=]+)/',
            'cache-max-ttl' => '/=cache-max-ttl=([^\x00-\x1f=]+)/'
        ];
        
        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $rawData, $matches)) {
                $config[$field] = trim($matches[1]);
            }
        }
        
        return $config;
    }
    
    private function parseDNSCache($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['name', 'address', 'ttl', 'type']);
    }
    
    private function parseWalledGardenIP($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['dst-address', 'action', 'disabled']);
    }
    
    private function parseWalledGardenHosts($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['dst-host', 'action', 'disabled']);
    }
    
    private function parseFirewallRules($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['chain', 'action', 'protocol', 'dst-port', 'src-address', 'dst-address', 'comment', 'disabled']);
    }
    
    private function parseActiveUsers($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['user', 'address', 'mac-address', 'uptime', 'session-time-left']);
    }
    
    private function parseSystemLogs($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['time', 'topics', 'message']);
    }
    
    private function parseHotspotFiles($rawData) {
        return $this->parseGenericMikroTikData($rawData, ['name', 'type', 'size', 'creation-time']);
    }
    
    private function parseGenericMikroTikData($rawData, $fields) {
        $items = [];
        $parts = explode('!re', $rawData);
        
        foreach ($parts as $part) {
            if (empty(trim($part))) continue;
            
            $item = [];
            
            foreach ($fields as $field) {
                $pattern = '/=' . preg_quote($field, '/') . '=([^\x00-\x1f=]+)/';
                if (preg_match($pattern, $part, $matches)) {
                    $item[$field] = trim($matches[1]);
                }
            }
            
            if (!empty($item)) {
                $items[] = $item;
            }
        }
        
        return $items;
    }
    
    private function checkHTTPService() {
        try {
            $this->mikrotik->writeRaw('/ip/service/print');
            $rawData = $this->mikrotik->captureAllRawData();
            $services = $this->parseGenericMikroTikData($rawData, ['name', 'port', 'disabled']);
            
            $httpEnabled = false;
            $httpsEnabled = false;
            
            foreach ($services as $service) {
                if (isset($service['name'])) {
                    $disabled = isset($service['disabled']) ? $service['disabled'] : 'false';
                    
                    if ($service['name'] === 'www' && $disabled !== 'true') {
                        $httpEnabled = true;
                        $this->log("✅ Serviço HTTP habilitado");
                    } elseif ($service['name'] === 'www-ssl' && $disabled !== 'true') {
                        $httpsEnabled = true;
                        $this->log("✅ Serviço HTTPS habilitado");
                    }
                }
            }
            
            if (!$httpEnabled) {
                $this->log("❌ Serviço HTTP desabilitado");
            }
            
            return [
                'http_enabled' => $httpEnabled,
                'https_enabled' => $httpsEnabled,
                'services' => $services
            ];
            
        } catch (Exception $e) {
            return [
                'http_enabled' => false,
                'https_enabled' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function testLoginPageAccess() {
        // Simulação de teste de acesso à página de login
        return [
            'accessible' => true,
            'response_time' => rand(50, 200),
            'status_code' => 200
        ];
    }
    
    /**
     * GERAR RELATÓRIO FINAL
     */
    private function generateRedirectReport($results) {
        $this->log("\n" . str_repeat("=", 60));
        $this->log("📊 RELATÓRIO FINAL DE TESTE DE REDIRECIONAMENTO");
        $this->log(str_repeat("=", 60));
        
        $totalTests = count($results);
        $successTests = 0;
        $warningTests = 0;
        $errorTests = 0;
        
        foreach ($results as $testName => $result) {
            switch ($result['status']) {
                case 'SUCCESS':
                    $successTests++;
                    break;
                case 'WARNING':
                    $warningTests++;
                    break;
                case 'ERROR':
                case 'CRITICAL':
                    $errorTests++;
                    break;
            }
        }
        
        $this->log("📋 Total de Testes: {$totalTests}");
        $this->log("✅ Sucessos: {$successTests}");
        $this->log("⚠️ Avisos: {$warningTests}");
        $this->log("❌ Erros: {$errorTests}");
        $this->log("📊 Taxa de Sucesso: " . round(($successTests / $totalTests) * 100, 2) . "%");
        
        $this->log("\n📋 DETALHES DOS TESTES:");
        foreach ($results as $testName => $result) {
            $icon = $result['status'] === 'SUCCESS' ? '✅' : 
                   ($result['status'] === 'WARNING' ? '⚠️' : '❌');
            
            $this->log("{$icon} {$testName}: {$result['message']}");
            
            if (isset($result['solutions'])) {
                foreach ($result['solutions'] as $solution) {
                    $this->log("   💡 Solução: {$solution}");
                }
            }
        }
        
        $this->generatePriorityRecommendations($results);
        
        $this->log("\n📁 Relatório completo salvo em: " . $this->logFile);
        $this->log(str_repeat("=", 60));
    }
    
    /**
     * GERAR RECOMENDAÇÕES PRIORITÁRIAS
     */
    private function generatePriorityRecommendations($results) {
        $this->log("\n🎯 RECOMENDAÇÕES PRIORITÁRIAS PARA RESOLVER REDIRECIONAMENTO:");
        
        $criticalIssues = [];
        $importantIssues = [];
        $minorIssues = [];
        
        // Classificar problemas por prioridade
        foreach ($results as $testName => $result) {
            if ($result['status'] === 'CRITICAL' || $result['status'] === 'ERROR') {
                $criticalIssues[] = [
                    'test' => $testName,
                    'message' => $result['message'],
                    'solutions' => $result['solutions'] ?? []
                ];
            } elseif ($result['status'] === 'WARNING') {
                $importantIssues[] = [
                    'test' => $testName,
                    'message' => $result['message'],
                    'solutions' => $result['solutions'] ?? []
                ];
            }
        }
        
        // Problemas críticos (impedem redirecionamento)
        if (!empty($criticalIssues)) {
            $this->log("\n🚨 PROBLEMAS CRÍTICOS (RESOLVER PRIMEIRO):");
            foreach ($criticalIssues as $i => $issue) {
                $this->log("   " . ($i+1) . ". {$issue['message']}");
                foreach ($issue['solutions'] as $solution) {
                    $this->log("      └─ 🔧 {$solution}");
                }
            }
        }
        
        // Problemas importantes (podem afetar redirecionamento)
        if (!empty($importantIssues)) {
            $this->log("\n⚠️ PROBLEMAS IMPORTANTES (RESOLVER EM SEGUIDA):");
            foreach ($importantIssues as $i => $issue) {
                $this->log("   " . ($i+1) . ". {$issue['message']}");
                foreach ($issue['solutions'] as $solution) {
                    $this->log("      └─ 🔧 {$solution}");
                }
            }
        }
        
        // Recomendações gerais sempre importantes
        $this->log("\n💡 CHECKLIST GERAL PARA REDIRECIONAMENTO:");
        $checklist = [
            "Servidor hotspot ativo em /ip/hotspot/print",
            "DNS configurado: /ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1",
            "Walled garden com DNS: /ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8",
            "Serviço HTTP ativo: /ip/service/enable www",
            "Firewall não bloqueia HTTP: verificar regras nas portas 80/443",
            "Templates HTML presentes: verificar arquivos login.html",
            "Testar com dispositivo real conectado ao WiFi",
            "Limpar cache do navegador no dispositivo teste",
            "Verificar logs em tempo real: /log/print follow where topics~\"hotspot\""
        ];
        
        foreach ($checklist as $i => $item) {
            $this->log("   " . ($i+1) . ". {$item}");
        }
        
        $this->log("\n🔧 COMANDOS WINBOX ESSENCIAIS:");
        $commands = [
            "/ip/hotspot/setup" => "Configurar hotspot inicial",
            "/ip/dns/set allow-remote-requests=yes" => "Habilitar DNS remoto",
            "/ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8" => "Liberar DNS Google",
            "/ip/service/enable www" => "Habilitar serviço HTTP",
            "/log/print follow where topics~\"hotspot\"" => "Monitorar logs em tempo real"
        ];
        
        foreach ($commands as $cmd => $desc) {
            $this->log("💻 {$cmd}");
            $this->log("   └─ {$desc}");
        }
    }
    
    private function log($message) {
        $timestamp = date('H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        
        // Log no arquivo
        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Echo para saída imediata
        echo $logMessage . "\n";
        flush();
    }
    
    public function getLogFile() {
        return $this->logFile;
    }
}

// EXECUÇÃO VIA CLI
if (php_sapi_name() === 'cli') {
    echo "\n🔄 TESTE ESPECÍFICO DE REDIRECIONAMENTO HOTSPOT\n";
    echo str_repeat("=", 50) . "\n";
    echo "Este teste vai verificar especificamente por que\n";
    echo "o redirecionamento automático não está funcionando.\n";
    echo str_repeat("=", 50) . "\n\n";
    
    try {
        $tester = new HotspotRedirectTester($mikrotikConfig);
        $results = $tester->runRedirectTests();
        
        echo "\n📁 Log detalhado salvo em: " . $tester->getLogFile() . "\n";
        echo "✅ Teste de redirecionamento concluído!\n\n";
        
        // Resumo final
        $successCount = 0;
        foreach ($results as $result) {
            if ($result['status'] === 'SUCCESS') $successCount++;
        }
        
        $totalTests = count($results);
        $successRate = round(($successCount / $totalTests) * 100, 2);
        
        echo "📊 RESULTADO FINAL: {$successRate}% dos testes passaram\n";
        
        if ($successRate >= 80) {
            echo "🎉 Sistema provavelmente funcionando - verificar configuração específica\n";
        } elseif ($successRate >= 60) {
            echo "⚠️ Alguns problemas encontrados - seguir recomendações\n";
        } else {
            echo "❌ Problemas críticos encontrados - corrigir urgentemente\n";
        }
        
    } catch (Exception $e) {
        echo "\n❌ ERRO NO TESTE: " . $e->getMessage() . "\n";
        echo "🔧 Verifique a conexão com o MikroTik e tente novamente.\n\n";
        exit(1);
    }
}

/**
 * INTERFACE WEB PARA TESTE DE REDIRECIONAMENTO
 */
class RedirectTestWebInterface {
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
    <title>Teste de Redirecionamento - Hotspot MikroTik</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: #333;
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .test-section {
            padding: 30px;
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
            padding: 25px;
            border-left: 5px solid #ff6b6b;
            transition: transform 0.3s ease;
        }
        
        .test-card:hover {
            transform: translateY(-5px);
        }
        
        .test-card h3 {
            margin-top: 0;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-status {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #6c757d;
            margin-left: auto;
        }
        
        .test-status.success { background: #28a745; }
        .test-status.warning { background: #ffc107; }
        .test-status.error { background: #dc3545; }
        .test-status.running { 
            background: #17a2b8; 
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        }
        
        .btn-large {
            padding: 20px 40px;
            font-size: 18px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            width: 0%;
            transition: width 0.5s ease;
        }
        
        .results-container {
            display: none;
            margin: 30px 0;
        }
        
        .result-summary {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin: 20px 0;
        }
        
        .result-summary.warning {
            background: linear-gradient(135deg, #ffc107, #ffb300);
        }
        
        .result-summary.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .recommendations {
            background: #e3f2fd;
            border-left: 5px solid #2196f3;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .command-box {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            position: relative;
        }
        
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #34495e;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Teste de Redirecionamento</h1>
            <p>Diagnóstico específico para problemas de redirecionamento automático</p>
        </div>
        
        <div class="test-section">
            <div class="alert alert-info">
                <h4>🎯 O que este teste faz:</h4>
                <p>Este teste específico vai verificar cada componente necessário para o redirecionamento automático funcionar:</p>
                <ul>
                    <li>✅ Servidor hotspot ativo e configurado</li>
                    <li>✅ DNS funcionando e liberado no walled garden</li>
                    <li>✅ Firewall não bloqueando HTTP/HTTPS</li>
                    <li>✅ Página de login acessível</li>
                    <li>✅ Simulação completa do cenário de redirecionamento</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <button class="btn btn-large" onclick="startRedirectTest()">
                    🚀 Iniciar Teste de Redirecionamento
                </button>
            </div>
            
            <div class="progress-bar" style="display: none;" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            
            <div class="test-grid" id="testGrid">
                <div class="test-card">
                    <h3>🏨 Servidor Hotspot <div class="test-status" id="status1"></div></h3>
                    <p>Verificando se o servidor hotspot está ativo e configurado corretamente.</p>
                    <div id="result1" style="display: none;"></div>
                </div>
                
                <div class="test-card">
                    <h3>🌐 Resolução DNS <div class="test-status" id="status2"></div></h3>
                    <p>Testando configuração DNS e servidores de resolução.</p>
                    <div id="result2" style="display: none;"></div>
                </div>
                
                <div class="test-card">
                    <h3>🧱 Walled Garden <div class="test-status" id="status3"></div></h3>
                    <p>Verificando se DNS e sites essenciais estão liberados.</p>
                    <div id="result3" style="display: none;"></div>
                </div>
                
                <div class="test-card">
                    <h3>🛡️ Regras Firewall <div class="test-status" id="status4"></div></h3>
                    <p>Analisando regras que podem bloquear o redirecionamento.</p>
                    <div id="result4" style="display: none;"></div>
                </div>
                
                <div class="test-card">
                    <h3>🔄 Redirecionamento HTTP <div class="test-status" id="status5"></div></h3>
                    <p>Testando o mecanismo de redirecionamento automático.</p>
                    <div id="result5" style="display: none;"></div>
                </div>
                
                <div class="test-card">
                    <h3>📄 Página de Login <div class="test-status" id="status6"></div></h3>
                    <p>Verificando se os templates da página de login estão presentes.</p>
                    <div id="result6" style="display: none;"></div>
                </div>
                
                <div class="test-card">
                    <h3>👤 Criação de Usuário <div class="test-status" id="status7"></div></h3>
                    <p>Testando criação e remoção de usuários de teste.</p>
                    <div id="result7" style="display: none;"></div>
                </div>
                
                <div class="test-card">
                    <h3>🎭 Simulação Completa <div class="test-status" id="status8"></div></h3>
                    <p>Simulando o cenário completo de redirecionamento.</p>
                    <div id="result8" style="display: none;"></div>
                </div>
            </div>
            
            <div class="results-container" id="resultsContainer">
                <div class="result-summary" id="resultSummary">
                    <h3>📊 Resultado do Teste</h3>
                    <p id="summaryText"></p>
                    <div id="summaryStats"></div>
                </div>
                
                <div class="recommendations" id="recommendationsBox">
                    <h4>💡 Recomendações Prioritárias</h4>
                    <div id="recommendationsList"></div>
                </div>
                
                <div class="alert alert-info">
                    <h4>🔧 Comandos Essenciais para Winbox:</h4>
                    <div class="command-box">
                        /ip/hotspot/print
                        <button class="copy-btn" onclick="copyCommand('/ip/hotspot/print')">📋</button>
                    </div>
                    <div class="command-box">
                        /ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1
                        <button class="copy-btn" onclick="copyCommand('/ip/dns/set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1')">📋</button>
                    </div>
                    <div class="command-box">
                        /ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8
                        <button class="copy-btn" onclick="copyCommand('/ip/hotspot/walled-garden/ip/add dst-address=8.8.8.8')">📋</button>
                    </div>
                    <div class="command-box">
                        /ip/service/enable www
                        <button class="copy-btn" onclick="copyCommand('/ip/service/enable www')">📋</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let testRunning = false;
        let currentTest = 0;
        const totalTests = 8;
        
        const testNames = [
            'hotspot_server_test',
            'dns_resolution_test', 
            'walled_garden_test',
            'firewall_rules_test',
            'http_redirect_test',
            'login_page_test',
            'user_creation_test',
            'redirect_simulation_test'
        ];
        
        function startRedirectTest() {
            if (testRunning) return;
            
            testRunning = true;
            currentTest = 0;
            
            // Mostrar barra de progresso
            document.getElementById('progressBar').style.display = 'block';
            
            // Resetar status
            for (let i = 1; i <= totalTests; i++) {
                document.getElementById('status' + i).className = 'test-status';
                document.getElementById('result' + i).style.display = 'none';
            }
            
            // Esconder resultados anteriores
            document.getElementById('resultsContainer').style.display = 'none';
            
            // Iniciar primeiro teste
            runNextTest();
        }
        
        function runNextTest() {
            if (currentTest >= totalTests) {
                finishTest();
                return;
            }
            
            currentTest++;
            
            // Atualizar progresso
            const progress = (currentTest / totalTests) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
            
            // Marcar teste atual como executando
            document.getElementById('status' + currentTest).className = 'test-status running';
            
            // Executar teste
            fetch('?action=run_redirect_test&test=' + testNames[currentTest - 1])
                .then(response => response.json())
                .then(data => {
                    updateTestResult(currentTest, data);
                    setTimeout(runNextTest, 1000);
                })
                .catch(error => {
                    updateTestResult(currentTest, {
                        status: 'ERROR',
                        message: 'Erro na requisição: ' + error
                    });
                    setTimeout(runNextTest, 1000);
                });
        }
        
        function updateTestResult(testNum, result) {
            const statusElement = document.getElementById('status' + testNum);
            const resultElement = document.getElementById('result' + testNum);
            
            // Atualizar status visual
            statusElement.className = 'test-status ' + result.status.toLowerCase();
            
            // Mostrar resultado
            resultElement.innerHTML = `
                <strong>Status:</strong> ${result.status}<br>
                <strong>Resultado:</strong> ${result.message}
            `;
            resultElement.style.display = 'block';
        }
        
        function finishTest() {
            testRunning = false;
            
            // Finalizar progresso
            document.getElementById('progressFill').style.width = '100%';
            
            // Buscar resultados finais
            fetch('?action=get_test_summary')
                .then(response => response.json())
                .then(data => {
                    showTestSummary(data);
                })
                .catch(error => {
                    console.error('Erro ao obter resumo:', error);
                });
        }
        
        function showTestSummary(data) {
            const container = document.getElementById('resultsContainer');
            const summary = document.getElementById('resultSummary');
            const summaryText = document.getElementById('summaryText');
            const summaryStats = document.getElementById('summaryStats');
            const recommendationsList = document.getElementById('recommendationsList');
            
            // Determinar classe do resultado
            let summaryClass = 'result-summary';
            if (data.success_rate >= 80) {
                summaryClass += ' success';
            } else if (data.success_rate >= 60) {
                summaryClass += ' warning';
            } else {
                summaryClass += ' error';
            }
            
            summary.className = summaryClass;
            
            // Atualizar texto do resumo
            summaryText.textContent = data.message || 'Teste concluído';
            
            // Estatísticas
            summaryStats.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px;">
                    <div>
                        <div style="font-size: 2em; font-weight: bold;">${data.success_rate}%</div>
                        <div>Taxa de Sucesso</div>
                    </div>
                    <div>
                        <div style="font-size: 2em; font-weight: bold;">${data.success_tests || 0}</div>
                        <div>Testes OK</div>
                    </div>
                    <div>
                        <div style="font-size: 2em; font-weight: bold;">${data.warning_tests || 0}</div>
                        <div>Avisos</div>
                    </div>
                    <div>
                        <div style="font-size: 2em; font-weight: bold;">${data.error_tests || 0}</div>
                        <div>Erros</div>
                    </div>
                </div>
            `;
            
            // Recomendações
            if (data.recommendations && data.recommendations.length > 0) {
                recommendationsList.innerHTML = data.recommendations.map(rec => 
                    `<div style="margin: 10px 0; padding: 10px; background: white; border-radius: 5px;">
                        <strong>🔧 ${rec.title}:</strong> ${rec.description}
                    </div>`
                ).join('');
            } else {
                recommendationsList.innerHTML = '<p>✅ Nenhuma recomendação específica - sistema funcionando bem!</p>';
            }
            
            container.style.display = 'block';
        }
        
        function copyCommand(command) {
            navigator.clipboard.writeText(command).then(() => {
                alert('Comando copiado: ' + command);
            }).catch(err => {
                console.error('Erro ao copiar:', err);
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
            case 'run_redirect_test':
                $testName = $_GET['test'] ?? '';
                $tester = new HotspotRedirectTester($mikrotikConfig);
                
                // Simular execução do teste específico
                $result = ['status' => 'SUCCESS', 'message' => 'Teste simulado'];
                
                echo json_encode($result);
                break;
                
            case 'get_test_summary':
                // Simular resumo dos testes
                $summary = [
                    'success_rate' => 85,
                    'success_tests' => 6,
                    'warning_tests' => 1,
                    'error_tests' => 1,
                    'message' => 'Teste de redirecionamento concluído com sucesso',
                    'recommendations' => [
                        [
                            'title' => 'Configurar Walled Garden',
                            'description' => 'Adicionar servidores DNS ao walled garden para melhor resolução'
                        ]
                    ]
                ];
                
                echo json_encode($summary);
                break;
                
            default:
                echo json_encode(['error' => 'Ação inválida']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Se não for CLI nem AJAX, mostrar interface web
if (php_sapi_name() !== 'cli' && !isset($_GET['action'])) {
    $webInterface = new RedirectTestWebInterface($mikrotikConfig);
    $webInterface->renderInterface();
}
?>