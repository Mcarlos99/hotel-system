<?php
// test_user_1865.php - Teste específico para o usuário 1-865
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Teste Específico: Usuário 1-865</h1>";

require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .step { margin: 15px 0; padding: 15px; border-radius: 8px; border-left: 5px solid; }
    .success { background: #d4edda; border-color: #28a745; color: #155724; }
    .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
    .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; max-height: 300px; overflow-y: auto; }
    .highlight { background: #ffeb3b; padding: 2px 4px; border-radius: 3px; }
</style>";

// Incluir a classe corrigida
include_once 'mikrotik_manager.php'; // Ou o arquivo que você está usando

function testUser1865($mikrotikConfig) {
    $username = "1-865";
    
    echo "<div class='step info'>";
    echo "<h3>🎯 Testando usuário específico: <span class='highlight'>{$username}</span></h3>";
    echo "<p>Este usuário aparece no Winbox mas o sistema não consegue encontrar.</p>";
    echo "</div>";
    
    try {
        // Usar a classe corrigida
        $mikrotik = new MikroTikHotspotManagerFixed(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        echo "<div class='step info'><h4>🔌 Conectando ao MikroTik...</h4></div>";
        $mikrotik->connect();
        echo "<div class='step success'>✅ Conectado com sucesso!</div>";
        
        echo "<div class='step info'><h4>🔍 Executando debug específico...</h4></div>";
        $debug = $mikrotik->debugSpecificUser($username);
        
        echo "<div class='step info'>";
        echo "<h4>📋 Resultado do Debug:</h4>";
        
        foreach ($debug['steps'] as $step) {
            echo "<p>• {$step}</p>";
        }
        echo "</div>";
        
        if (isset($debug['raw_response'])) {
            echo "<div class='step info'>";
            echo "<h4>📤 Resposta Bruta do MikroTik (" . count($debug['raw_response']) . " linhas):</h4>";
            echo "<pre>";
            foreach ($debug['raw_response'] as $i => $line) {
                $highlighted = $line;
                if (strpos($line, '1-865') !== false) {
                    $highlighted = "<span class='highlight'>{$line}</span>";
                }
                echo "[{$i}] {$highlighted}\n";
            }
            echo "</pre>";
            echo "</div>";
        }
        
        if (isset($debug['parsed_users'])) {
            echo "<div class='step info'>";
            echo "<h4>🔍 Usuários Encontrados pelo Parser (" . count($debug['parsed_users']) . "):</h4>";
            echo "<pre>";
            foreach ($debug['parsed_users'] as $i => $user) {
                $name = $user['name'] ?? 'N/A';
                $id = $user['id'] ?? 'N/A';
                
                if ($name === $username) {
                    echo "<span class='highlight'>[{$i}] ENCONTRADO! ID: {$id}, Nome: {$name}</span>\n";
                } else {
                    echo "[{$i}] ID: {$id}, Nome: {$name}\n";
                }
            }
            echo "</pre>";
            echo "</div>";
        }
        
        if (isset($debug['target_user'])) {
            echo "<div class='step success'>";
            echo "<h4>✅ Usuário Encontrado!</h4>";
            echo "<pre>" . print_r($debug['target_user'], true) . "</pre>";
            
            // Tentar remover agora que encontramos
            echo "<h4>🗑️ Tentando remover o usuário...</h4>";
            
            try {
                $removeResult = $mikrotik->removeHotspotUser($username);
                
                if ($removeResult) {
                    echo "<div class='step success'>";
                    echo "<h4>🎉 SUCESSO! Usuário {$username} foi removido!</h4>";
                    echo "</div>";
                } else {
                    echo "<div class='step error'>";
                    echo "<h4>❌ Falha na remoção</h4>";
                    echo "</div>";
                }
                
            } catch (Exception $e) {
                echo "<div class='step error'>";
                echo "<h4>❌ Erro na remoção: " . $e->getMessage() . "</h4>";
                echo "</div>";
            }
            
            echo "</div>";
        } else {
            echo "<div class='step error'>";
            echo "<h4>❌ Usuário NÃO foi encontrado!</h4>";
            echo "<p>Isso indica um problema no parser ou na resposta do MikroTik.</p>";
            echo "</div>";
        }
        
        if (isset($debug['specific_response'])) {
            echo "<div class='step warning'>";
            echo "<h4>🔍 Resposta da Busca Específica:</h4>";
            echo "<pre>";
            foreach ($debug['specific_response'] as $i => $line) {
                echo "[{$i}] {$line}\n";
            }
            echo "</pre>";
            echo "</div>";
        }
        
        $mikrotik->disconnect();
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h4>❌ ERRO no teste:</h4>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
        echo "</div>";
    }
}

// Executar o teste
testUser1865($mikrotikConfig);

echo "<hr>";
echo "<div class='step info'>";
echo "<h3>💡 Diagnóstico:</h3>";
echo "<p>Se o usuário <strong>1-865</strong> aparece no Winbox mas não é encontrado pelo sistema, as possíveis causas são:</p>";
echo "<ul>";
echo "<li><strong>Parser incorreto</strong> - O parser não está interpretando corretamente a resposta da API</li>";
echo "<li><strong>Codificação de caracteres</strong> - Pode haver problema com UTF-8 ou caracteres especiais</li>";
echo "<li><strong>Estrutura da resposta</strong> - A API pode retornar dados em formato diferente do esperado</li>";
echo "<li><strong>Filtros ativos</strong> - Pode haver filtros no MikroTik que afetam a listagem via API</li>";
echo "<li><strong>Permissões da API</strong> - O usuário pode não ter permissão para ver todos os usuários</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step warning'>";
echo "<h3>🔧 Soluções Recomendadas:</h3>";
echo "<ol>";
echo "<li><strong>Analise a resposta bruta</strong> - Verifique se o usuário 1-865 aparece na resposta do MikroTik</li>";
echo "<li><strong>Teste o parser corrigido</strong> - Use a classe MikroTikHotspotManagerFixed</li>";
echo "<li><strong>Verifique permissões</strong> - Confirme se o usuário da API tem acesso total</li>";
echo "<li><strong>Teste no terminal</strong> - Execute comandos diretamente no MikroTik:</li>";
echo "</ol>";
echo "<pre>";
echo "/ip hotspot user print\n";
echo "/ip hotspot user print where name=\"1-865\"\n";
echo "/ip hotspot user remove [find name=\"1-865\"]\n";
echo "</pre>";
echo "</div>";

echo "<div class='step info'>";
echo "<h3>🧪 Próximos Passos:</h3>";
echo "<ol>";
echo "<li><strong>Substitua a classe MikroTik</strong> pela versão corrigida (MikroTikHotspotManagerFixed)</li>";
echo "<li><strong>Execute este teste novamente</strong> para ver se o usuário é encontrado</li>";
echo "<li><strong>Se ainda não funcionar</strong>, analise a resposta bruta para entender o formato</li>";
echo "<li><strong>Como última opção</strong>, remova manualmente no Winbox e recrie pelo sistema</li>";
echo "</ol>";
echo "</div>";

echo "<div class='step success'>";
echo "<h3>✅ Correção Aplicada:</h3>";
echo "<p>A classe <strong>MikroTikHotspotManagerFixed</strong> inclui:</p>";
echo "<ul>";
echo "<li>✅ Parser mais robusto que analisa linha por linha</li>";
echo "<li>✅ Logs detalhados de cada etapa do parsing</li>";
echo "<li>✅ Detecção correta de início/fim de registros</li>";
echo "<li>✅ Fallback para busca específica se não encontrar na lista geral</li>";
echo "<li>✅ Verificação tripla: busca → remoção → confirmação</li>";
echo "</ul>";
echo "</div>";

// Formulário para testar outros usuários
echo "<div class='step info'>";
echo "<h3>🧪 Teste com Outro Usuário:</h3>";
echo "<form method='GET'>";
echo "<label>Nome do usuário: </label>";
echo "<input type='text' name='test_user' value='" . ($_GET['test_user'] ?? '1-865') . "' style='padding: 8px; margin: 5px;'>";
echo "<button type='submit' style='padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px;'>🔍 Testar</button>";
echo "</form>";
echo "</div>";

// Se foi passado um usuário específico para testar
if (isset($_GET['test_user']) && $_GET['test_user'] !== '1-865') {
    $testUsername = $_GET['test_user'];
    echo "<hr>";
    echo "<h2>🎯 Testando usuário: {$testUsername}</h2>";
    
    try {
        $mikrotik = new MikroTikHotspotManagerFixed(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        $mikrotik->connect();
        $debug = $mikrotik->debugSpecificUser($testUsername);
        
        echo "<div class='step info'>";
        echo "<h4>Resultado para {$testUsername}:</h4>";
        foreach ($debug['steps'] as $step) {
            echo "<p>• {$step}</p>";
        }
        echo "</div>";
        
        if (isset($debug['target_user'])) {
            echo "<div class='step success'>";
            echo "<h4>✅ Usuário {$testUsername} encontrado!</h4>";
            echo "<pre>" . print_r($debug['target_user'], true) . "</pre>";
            echo "</div>";
        } else {
            echo "<div class='step warning'>";
            echo "<h4>⚠️ Usuário {$testUsername} não encontrado</h4>";
            echo "</div>";
        }
        
        $mikrotik->disconnect();
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h4>❌ Erro: " . $e->getMessage() . "</h4>";
        echo "</div>";
    }
}

echo "<hr>";
echo "<p style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>← Voltar ao Sistema</a>";
echo "<a href='test_real_removal.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🧪 Teste Completo</a>";
echo "</p>";

echo "<div class='step warning' style='margin-top: 30px;'>";
echo "<h3>📝 Instruções para Correção:</h3>";
echo "<ol>";
echo "<li><strong>Copie a classe MikroTikHotspotManagerFixed</strong> e substitua a classe atual no seu mikrotik_manager.php</li>";
echo "<li><strong>Ou renomeie a classe atual</strong> e use a nova classe no seu código</li>";
echo "<li><strong>No index.php</strong>, instancie a nova classe:</li>";
echo "</ol>";
echo "<pre>";
echo "// Substituir esta linha:\n";
echo "\$mikrotik = new MikroTikHotspotManager(...);\n\n";
echo "// Por esta:\n";
echo "\$mikrotik = new MikroTikHotspotManagerFixed(...);\n";
echo "</pre>";
echo "</div>";
?>