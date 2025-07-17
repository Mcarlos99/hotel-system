<?php
// test_raw_data_analysis.php - Análise final dos dados brutos
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔬 Análise Final de Dados Brutos - Investigação Definitiva</h1>";

require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
    .step { margin: 15px 0; padding: 15px; border-radius: 8px; border-left: 5px solid; }
    .success { background: #d4edda; border-color: #28a745; color: #155724; }
    .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
    .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
    .hex-dump { font-family: 'Courier New', monospace; font-size: 10px; background: #000; color: #00ff00; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto; }
    .ascii-dump { font-family: 'Courier New', monospace; font-size: 11px; background: #2d3748; color: #68d391; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; }
    .highlight { background: #ffeb3b; color: #000; padding: 1px 3px; border-radius: 2px; }
    .user-data { background: #ff6b6b; color: white; padding: 1px 3px; border-radius: 2px; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .btn { background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin: 5px; }
</style>";

echo "<div class='container'>";

function analyzeRawMikroTikData($host, $username, $password, $port) {
    echo "<div class='step info'>";
    echo "<h3>🎯 Objetivo Final:</h3>";
    echo "<p>Esta é a <strong>análise definitiva</strong> para descobrir se os 4 usuários estão chegando nos dados brutos da API.</p>";
    echo "<p>Vamos capturar TODOS os bytes enviados pelo MikroTik e analisar manualmente.</p>";
    echo "</div>";
    
    try {
        // Conectar manualmente
        echo "<div class='step info'><h4>🔌 Conectando ao MikroTik...</h4></div>";
        
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new Exception("Erro ao criar socket");
        }
        
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 15, "usec" => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array("sec" => 15, "usec" => 0));
        
        if (!socket_connect($socket, $host, $port)) {
            $error = socket_strerror(socket_last_error($socket));
            throw new Exception("Falha na conexão: " . $error);
        }
        
        echo "<div class='success'>✅ Conectado com sucesso</div>";
        
        // Login
        echo "<div class='step info'><h4>🔐 Fazendo login...</h4></div>";
        
        // Enviar comando de login
        $loginCmd = '/login';
        $loginData = chr(strlen($loginCmd)) . $loginCmd . chr(0);
        socket_write($socket, $loginData);
        
        // Ler resposta do login
        $loginResponse = '';
        while (true) {
            $chunk = socket_read($socket, 1024);
            if ($chunk === false || $chunk === '') break;
            $loginResponse .= $chunk;
            if (strpos($loginResponse, chr(0)) !== false) break;
        }
        
        // Enviar credenciais
        $credCmd = '/login';
        $nameArg = '=name=' . $username;
        $credData = chr(strlen($credCmd)) . $credCmd . chr(strlen($nameArg)) . $nameArg;
        if (!empty($password)) {
            $passArg = '=password=' . $password;
            $credData .= chr(strlen($passArg)) . $passArg;
        }
        $credData .= chr(0);
        
        socket_write($socket, $credData);
        
        // Ler resposta das credenciais
        $credResponse = '';
        while (true) {
            $chunk = socket_read($socket, 1024);
            if ($chunk === false || $chunk === '') break;
            $credResponse .= $chunk;
            if (strpos($credResponse, chr(0)) !== false) break;
        }
        
        echo "<div class='success'>✅ Login realizado</div>";
        
        // COMANDO PRINCIPAL: listar usuários hotspot
        echo "<div class='step warning'><h4>📡 Enviando comando: /ip/hotspot/user/print</h4></div>";
        
        $userCmd = '/ip/hotspot/user/print';
        $userData = chr(strlen($userCmd)) . $userCmd . chr(0);
        socket_write($socket, $userData);
        
        echo "<div class='step info'>📥 Capturando TODOS os dados da resposta...</div>";
        
        // CAPTURAR TODOS OS DADOS BRUTOS
        $allRawData = '';
        $startTime = time();
        $chunkCount = 0;
        
        while ((time() - $startTime) < 15) {
            $chunk = socket_read($socket, 4096);
            if ($chunk === false) {
                break;
            }
            
            if ($chunk === '') {
                usleep(100000); // 100ms
                continue;
            }
            
            $allRawData .= $chunk;
            $chunkCount++;
            
            echo "<div style='font-family: monospace; font-size: 11px; margin: 2px 0;'>";
            echo "📦 Chunk {$chunkCount}: " . strlen($chunk) . " bytes";
            echo "</div>";
            flush();
            
            // Parar se recebeu dados suficientes
            if (strlen($allRawData) > 1000 && strpos($allRawData, '!done') !== false) {
                break;
            }
        }
        
        socket_close($socket);
        
        echo "<div class='step success'>";
        echo "<h4>📊 Dados Capturados:</h4>";
        echo "<p><strong>Total de bytes:</strong> " . strlen($allRawData) . "</p>";
        echo "<p><strong>Chunks recebidos:</strong> {$chunkCount}</p>";
        echo "</div>";
        
        // ANÁLISE DOS DADOS BRUTOS
        echo "<div class='step info'>";
        echo "<h4>🔍 Análise Estrutural:</h4>";
        
        $reCount = substr_count($allRawData, '!re');
        $doneCount = substr_count($allRawData, '!done');
        $nameCount = substr_count($allRawData, '=name=');
        $idCount = substr_count($allRawData, '=.id=');
        
        echo "<ul>";
        echo "<li><strong>Registros (!re):</strong> {$reCount}</li>";
        echo "<li><strong>Fim de dados (!done):</strong> {$doneCount}</li>";
        echo "<li><strong>Nomes de usuários (=name=):</strong> {$nameCount}</li>";
        echo "<li><strong>IDs (=.id=):</strong> {$idCount}</li>";
        echo "</ul>";
        
        if ($nameCount >= 4) {
            echo "<div class='success'>";
            echo "<h4>🎉 PROBLEMA IDENTIFICADO!</h4>";
            echo "<p>A API está retornando <strong>{$nameCount} usuários</strong> nos dados brutos!</p>";
            echo "<p>O problema está no <strong>parser PHP</strong>, não no MikroTik.</p>";
            echo "</div>";
        } elseif ($nameCount > 1) {
            echo "<div class='warning'>";
            echo "<h4>⚠️ PROGRESSO!</h4>";
            echo "<p>A API retorna <strong>{$nameCount} usuários</strong>, mais que o parser consegue extrair.</p>";
            echo "<p>Ainda pode haver mais dados que não estão sendo capturados corretamente.</p>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<h4>❌ CONFIRMADO: PROBLEMA NO MIKROTIK</h4>";
            echo "<p>A API realmente retorna apenas <strong>{$nameCount} usuário</strong> nos dados brutos.</p>";
            echo "<p>O problema está na configuração ou permissões do MikroTik.</p>";
            echo "</div>";
        }
        echo "</div>";
        
        // DUMP HEXADECIMAL
        echo "<div class='step info'>";
        echo "<h4>🔬 Dump Hexadecimal Completo:</h4>";
        echo "<div class='hex-dump'>";
        
        $hex = bin2hex($allRawData);
        $formatted = '';
        
        for ($i = 0; $i < strlen($hex); $i += 32) {
            $line = substr($hex, $i, 32);
            $offset = sprintf('%08X', $i / 2);
            
            // Adicionar espaços a cada 2 caracteres
            $formatted_line = '';
            for ($j = 0; $j < strlen($line); $j += 2) {
                $formatted_line .= substr($line, $j, 2) . ' ';
            }
            
            $formatted .= "{$offset}: {$formatted_line}\n";
        }
        
        echo $formatted;
        echo "</div>";
        echo "</div>";
        
        // REPRESENTAÇÃO ASCII
        echo "<div class='step info'>";
        echo "<h4>📝 Representação ASCII (com destaque para dados de usuário):</h4>";
        
        $ascii = '';
        $inUserData = false;
        
        for ($i = 0; $i < strlen($allRawData); $i++) {
            $char = ord($allRawData[$i]);
            
            // Detectar início de dados de usuário
            if ($i > 0 && substr($allRawData, $i-5, 6) === '=name=') {
                $inUserData = true;
            }
            
            if ($char >= 32 && $char <= 126) {
                $charStr = chr($char);
                
                // Destacar dados importantes
                if (strpos('!re!done=name==.id==password=', $charStr) !== false || $inUserData) {
                    $ascii .= "<span class='user-data'>{$charStr}</span>";
                } else {
                    $ascii .= $charStr;
                }
            } else {
                $ascii .= '<span class="highlight">.</span>';
                $inUserData = false;
            }
            
            // Quebrar linha a cada 80 caracteres
            if ($i > 0 && $i % 80 === 0) {
                $ascii .= "\n";
            }
        }
        
        echo "<div class='ascii-dump'>{$ascii}</div>";
        echo "</div>";
        
        // EXTRAIR NOMES MANUALMENTE
        echo "<div class='step warning'>";
        echo "<h4>👥 Extração Manual de Nomes de Usuários:</h4>";
        
        $userNames = [];
        $pos = 0;
        
        while (($pos = strpos($allRawData, '=name=', $pos)) !== false) {
            $pos += 6; // Pular '=name='
            $nameEnd = $pos;
            
            // Encontrar fim do nome (próximo byte de controle ou =)
            while ($nameEnd < strlen($allRawData)) {
                $char = ord($allRawData[$nameEnd]);
                if ($char < 32 || $allRawData[$nameEnd] === '=') {
                    break;
                }
                $nameEnd++;
            }
            
            if ($nameEnd > $pos) {
                $userName = substr($allRawData, $pos, $nameEnd - $pos);
                if (!empty(trim($userName))) {
                    $userNames[] = $userName;
                }
            }
        }
        
        echo "<p><strong>Usuários extraídos manualmente:</strong></p>";
        if (empty($userNames)) {
            echo "<p>❌ Nenhum usuário encontrado na extração manual</p>";
        } else {
            echo "<ul>";
            foreach ($userNames as $i => $name) {
                echo "<li><strong>Usuário " . ($i + 1) . ":</strong> <code>{$name}</code></li>";
            }
            echo "</ul>";
            
            echo "<p><strong>Total extraído manualmente:</strong> " . count($userNames) . " usuários</p>";
            
            if (count($userNames) >= 4) {
                echo "<div class='success'>";
                echo "<h4>🎉 CONFIRMADO: DADOS ESTÃO CHEGANDO!</h4>";
                echo "<p>Os 4 usuários estão nos dados brutos. O problema é definitivamente no parser PHP.</p>";
                echo "</div>";
            }
        }
        echo "</div>";
        
        return [
            'raw_data' => $allRawData,
            'analysis' => [
                're_count' => $reCount,
                'done_count' => $doneCount,
                'name_count' => $nameCount,
                'id_count' => $idCount,
                'total_bytes' => strlen($allRawData)
            ],
            'extracted_users' => $userNames
        ];
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h4>❌ Erro na análise:</h4>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
        
        return null;
    }
}

// Executar análise
$result = analyzeRawMikroTikData(
    $mikrotikConfig['host'],
    $mikrotikConfig['username'],
    $mikrotikConfig['password'],
    $mikrotikConfig['port']
);

if ($result) {
    echo "<div class='step success'>";
    echo "<h3>🎯 Conclusão Final:</h3>";
    
    $users = $result['extracted_users'];
    $analysis = $result['analysis'];
    
    if (count($users) >= 4) {
        echo "<p><strong>✅ PROBLEMA IDENTIFICADO E CONFIRMADO:</strong></p>";
        echo "<ul>";
        echo "<li>Os dados dos 4 usuários ESTÃO chegando do MikroTik</li>";
        echo "<li>O problema está no parser PHP que não consegue extrair corretamente</li>";
        echo "<li>É necessário implementar um parser baseado em dados brutos</li>";
        echo "</ul>";
        
        echo "<h4>🛠️ Solução Recomendada:</h4>";
        echo "<p>Implementar um parser que trabalhe diretamente com os dados binários, ignorando a estrutura tradicional da API MikroTik.</p>";
        
    } elseif (count($users) > 1) {
        echo "<p><strong>⚠️ PROBLEMA PARCIALMENTE IDENTIFICADO:</strong></p>";
        echo "<ul>";
        echo "<li>Alguns usuários estão chegando (" . count($users) . " de 4)</li>";
        echo "<li>Pode haver problema na captura completa dos dados</li>";
        echo "<li>Ou alguns usuários podem estar em formato diferente</li>";
        echo "</ul>";
        
    } else {
        echo "<p><strong>❌ PROBLEMA CONFIRMADO NO MIKROTIK:</strong></p>";
        echo "<ul>";
        echo "<li>Apenas " . count($users) . " usuário nos dados brutos</li>";
        echo "<li>O MikroTik realmente não está enviando todos os usuários</li>";
        echo "<li>Problema de configuração, permissões ou filtros</li>";
        echo "</ul>";
        
        echo "<h4>🔧 Ações Necessárias:</h4>";
        echo "<ul>";
        echo "<li>Verificar permissões do usuário da API</li>";
        echo "<li>Testar com usuário 'admin' sem senha</li>";
        echo "<li>Verificar se há filtros na configuração do hotspot</li>";
        echo "<li>Examinar logs do MikroTik para erros</li>";
        echo "</ul>";
    }
    echo "</div>";
}

echo "<div class='step info'>";
echo "<h3>📋 Próximos Passos:</h3>";
echo "<ol>";
echo "<li><strong>Analise os resultados acima</strong> para confirmar quantos usuários estão nos dados brutos</li>";
echo "<li><strong>Se 4+ usuários foram encontrados:</strong> O problema é no parser PHP</li>";
echo "<li><strong>Se apenas 1 usuário foi encontrado:</strong> O problema é no MikroTik</li>";
echo "<li><strong>Implemente a correção apropriada</strong> baseada no diagnóstico</li>";
echo "</ol>";
echo "</div>";

echo "<p style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' class='btn' style='background: #28a745;'>← Voltar ao Sistema</a>";
echo "<a href='mikrotik_deep_diagnosis.php' class='btn' style='background: #17a2b8;'>🔬 Diagnóstico Completo</a>";
echo "<a href='?' class='btn' style='background: #ffc107; color: #000;'>🔄 Executar Novamente</a>";
echo "</p>";

echo "</div>"; // Fechar container
?>