<?php
// test_raw_parser_final.php - Teste Final do Parser de Dados Brutos
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

echo "<h1>🔬 Teste Final - Parser de Dados Brutos DEFINITIVO</h1>";

require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .step { margin: 15px 0; padding: 15px; border-radius: 8px; border-left: 5px solid; }
    .success { background: #d4edda; border-color: #28a745; color: #155724; }
    .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
    .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
    .users-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
    .user-card { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; }
    .user-name { font-weight: bold; color: #495057; font-size: 1.1em; }
    .user-id { color: #6c757d; font-size: 0.9em; }
    .highlight { background: #ffeb3b; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
    .btn { display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; }
    .btn-success { background: #28a745; }
    .btn-danger { background: #dc3545; }
    .btn-warning { background: #ffc107; color: #212529; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
    .comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
    .method-result { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; }
    .method-title { font-weight: bold; color: #495057; margin-bottom: 10px; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
    .stat-card { background: #17a2b8; color: white; padding: 15px; border-radius: 8px; text-align: center; }
    .stat-number { font-size: 2em; font-weight: bold; }
    .stat-label { font-size: 0.9em; opacity: 0.9; }
</style>";

echo "<div class='container'>";

// Incluir o novo parser
require_once 'mikrotik_manager.php';

echo "<div class='step info'>";
echo "<h3>🎯 Objetivo do Teste:</h3>";
echo "<p>Validar se o novo <strong>Parser de Dados Brutos</strong> consegue encontrar todos os 4 usuários que estão chegando do MikroTik.</p>";
echo "<p>Este parser trabalha diretamente com os dados binários, ignorando a estrutura tradicional da API.</p>";
echo "</div>";

function testRawDataParser($mikrotikConfig) {
    echo "<div class='step info'>";
    echo "<h3>🔧 Inicializando Parser de Dados Brutos...</h3>";
    echo "</div>";
    
    try {
        // Usar a nova classe
        $mikrotik = new MikroTikRawDataParser(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        echo "<div class='step success'>";
        echo "<h4>✅ Parser inicializado com sucesso</h4>";
        echo "</div>";
        
        // Teste de conexão
        echo "<div class='step info'>";
        echo "<h4>🔌 Testando conexão...</h4>";
        echo "</div>";
        
        $connectionTest = $mikrotik->testConnection();
        
        if ($connectionTest['success']) {
            echo "<div class='step success'>";
            echo "<h4>✅ Conexão estabelecida com sucesso</h4>";
            echo "<p>Mensagem: " . htmlspecialchars($connectionTest['message']) . "</p>";
            echo "</div>";
        } else {
            echo "<div class='step error'>";
            echo "<h4>❌ Falha na conexão</h4>";
            echo "<p>Erro: " . htmlspecialchars($connectionTest['message']) . "</p>";
            echo "</div>";
            return null;
        }
        
        // Teste de extração de dados brutos
        echo "<div class='step warning'>";
        echo "<h4>🔬 Executando teste de extração de dados brutos...</h4>";
        echo "<p>Este teste mostrará como cada método de parser funciona.</p>";
        echo "</div>";
        
        $rawTest = $mikrotik->testRawDataExtraction();
        
        if (isset($rawTest['error'])) {
            echo "<div class='step error'>";
            echo "<h4>❌ Erro no teste de extração</h4>";
            echo "<p>Erro: " . htmlspecialchars($rawTest['error']) . "</p>";
            echo "</div>";
            return null;
        }
        
        // Mostrar análise dos dados brutos
        echo "<div class='step info'>";
        echo "<h4>📊 Análise dos Dados Brutos:</h4>";
        
        echo "<div class='stats'>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-number'>" . $rawTest['raw_analysis']['total_bytes'] . "</div>";
        echo "<div class='stat-label'>Bytes Totais</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number'>" . $rawTest['raw_analysis']['name_count'] . "</div>";
        echo "<div class='stat-label'>Nomes Encontrados</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number'>" . $rawTest['raw_analysis']['id_count'] . "</div>";
        echo "<div class='stat-label'>IDs Encontrados</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number'>" . $rawTest['raw_analysis']['re_count'] . "</div>";
        echo "<div class='stat-label'>Registros (!re)</div>";
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        
        // Comparação dos 3 métodos
        echo "<div class='step warning'>";
        echo "<h4>⚖️ Comparação dos 3 Métodos de Parser:</h4>";
        
        echo "<div class='comparison'>";
        
        // Método 1
        echo "<div class='method-result'>";
        echo "<div class='method-title'>Método 1: Por Padrões</div>";
        echo "<p><strong>Usuários encontrados:</strong> " . $rawTest['method1_users'] . "</p>";
        if (!empty($rawTest['method1_data'])) {
            echo "<ul>";
            foreach ($rawTest['method1_data'] as $user) {
                echo "<li><strong>" . htmlspecialchars($user['name'] ?? 'N/A') . "</strong>";
                if (isset($user['id'])) echo " (ID: " . htmlspecialchars($user['id']) . ")";
                echo "</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
        // Método 2
        echo "<div class='method-result'>";
        echo "<div class='method-title'>Método 2: Por Sequência</div>";
        echo "<p><strong>Usuários encontrados:</strong> " . $rawTest['method2_users'] . "</p>";
        if (!empty($rawTest['method2_data'])) {
            echo "<ul>";
            foreach ($rawTest['method2_data'] as $user) {
                echo "<li><strong>" . htmlspecialchars($user['name'] ?? 'N/A') . "</strong>";
                if (isset($user['id'])) echo " (ID: " . htmlspecialchars($user['id']) . ")";
                echo "</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
        echo "</div>";
        
        // Método 3
        echo "<div class='method-result'>";
        echo "<div class='method-title'>Método 3: Por Estrutura</div>";
        echo "<p><strong>Usuários encontrados:</strong> " . $rawTest['method3_users'] . "</p>";
        if (!empty($rawTest['method3_data'])) {
            echo "<ul>";
            foreach ($rawTest['method3_data'] as $user) {
                echo "<li><strong>" . htmlspecialchars($user['name'] ?? 'N/A') . "</strong>";
                if (isset($user['id'])) echo " (ID: " . htmlspecialchars($user['id']) . ")";
                echo "</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
        echo "</div>";
        
        // Teste da função principal
        echo "<div class='step warning'>";
        echo "<h4>🎯 Teste da Função Principal (listHotspotUsers):</h4>";
        echo "<p>Agora vamos testar a função principal que combina os 3 métodos automaticamente.</p>";
        echo "</div>";
        
        $mikrotik->connect();
        $finalUsers = $mikrotik->listHotspotUsers();
        $mikrotik->disconnect();
        
        echo "<div class='step " . (count($finalUsers) >= 4 ? 'success' : 'warning') . "'>";
        echo "<h4>📊 Resultado Final:</h4>";
        echo "<p><strong>Usuários encontrados pela função principal:</strong> <span class='highlight'>" . count($finalUsers) . "</span></p>";
        
        if (count($finalUsers) >= 4) {
            echo "<p>🎉 <strong>SUCESSO TOTAL!</strong> O parser conseguiu encontrar todos (ou a maioria) dos usuários!</p>";
        } elseif (count($finalUsers) > 1) {
            echo "<p>⚠️ <strong>PROGRESSO SIGNIFICATIVO!</strong> Encontrou mais usuários que o parser anterior.</p>";
        } else {
            echo "<p>❌ <strong>PROBLEMA PERSISTE!</strong> Ainda encontra apenas 1 usuário.</p>";
        }
        echo "</div>";
        
        // Mostrar usuários encontrados
        if (!empty($finalUsers)) {
            echo "<div class='step success'>";
            echo "<h4>👥 Usuários Encontrados:</h4>";
            
            echo "<div class='users-grid'>";
            foreach ($finalUsers as $i => $user) {
                $name = htmlspecialchars($user['name'] ?? 'N/A');
                $id = htmlspecialchars($user['id'] ?? 'N/A');
                $profile = htmlspecialchars($user['profile'] ?? 'N/A');
                
                // Verificar se é um dos usuários esperados
                $expectedUsers = ['admin-recepcao', 'guest_103', '37-90', 'default-trial'];
                $isExpected = in_array($user['name'] ?? '', $expectedUsers);
                
                echo "<div class='user-card'" . ($isExpected ? " style='border-color: #28a745; background: #d4edda;'" : "") . ">";
                echo "<div class='user-name'>" . ($i + 1) . ". {$name}</div>";
                echo "<div class='user-id'>ID: {$id}</div>";
                echo "<div class='user-id'>Perfil: {$profile}</div>";
                echo "<div style='margin-top: 10px; font-size: 0.9em;'>";
                echo $isExpected ? "✅ Esperado" : "🔍 Adicional";
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
            
            echo "</div>";
            
            // Teste de remoção
            echo "<div class='step info'>";
            echo "<h4>🧪 Teste de Remoção:</h4>";
            echo "<p>Selecione um usuário para testar a remoção:</p>";
            
            foreach ($finalUsers as $user) {
                if (isset($user['name']) && $user['name'] !== 'default-trial') {
                    $name = htmlspecialchars($user['name']);
                    echo "<a href='?test_remove=" . urlencode($user['name']) . "' class='btn btn-danger'>";
                    echo "🗑️ Remover: {$name}";
                    echo "</a>";
                }
            }
            echo "</div>";
        }
        
        return [
            'success' => true,
            'raw_test' => $rawTest,
            'final_users' => $finalUsers,
            'users_count' => count($finalUsers)
        ];
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h4>❌ Erro no teste:</h4>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
        echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
        echo "</div>";
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Teste de remoção se solicitado
if (isset($_GET['test_remove'])) {
    $userToRemove = $_GET['test_remove'];
    
    echo "<div class='step warning'>";
    echo "<h3>🗑️ Testando Remoção: <span class='highlight'>" . htmlspecialchars($userToRemove) . "</span></h3>";
    echo "</div>";
    
    try {
        $mikrotik = new MikroTikRawDataParser(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        echo "<div class='step info'>";
        echo "<h4>🔧 Executando remoção com parser de dados brutos...</h4>";
        echo "</div>";
        
        $mikrotik->connect();
        $result = $mikrotik->removeHotspotUser($userToRemove);
        $mikrotik->disconnect();
        
        if ($result) {
            echo "<div class='step success'>";
            echo "<h4>🎉 SUCESSO NA REMOÇÃO!</h4>";
            echo "<p>O usuário <strong>" . htmlspecialchars($userToRemove) . "</strong> foi removido com sucesso!</p>";
            echo "<p>O parser de dados brutos conseguiu:</p>";
            echo "<ul>";
            echo "<li>✅ Encontrar o usuário na lista</li>";
            echo "<li>✅ Extrair o ID correto</li>";
            echo "<li>✅ Executar o comando de remoção</li>";
            echo "<li>✅ Verificar que foi realmente removido</li>";
            echo "</ul>";
            echo "<p><a href='?' class='btn btn-success'>🔄 Verificar Lista Atualizada</a></p>";
            echo "</div>";
        } else {
            echo "<div class='step error'>";
            echo "<h4>❌ Falha na Remoção</h4>";
            echo "<p>A remoção do usuário <strong>" . htmlspecialchars($userToRemove) . "</strong> falhou.</p>";
            echo "<p>Possíveis causas:</p>";
            echo "<ul>";
            echo "<li>Usuário não foi encontrado</li>";
            echo "<li>ID não foi extraído corretamente</li>";
            echo "<li>Erro na execução do comando</li>";
            echo "<li>Problema de permissões</li>";
            echo "</ul>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h4>❌ Erro na remoção:</h4>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
    echo "<p style='text-align: center; margin: 30px 0;'>";
    echo "<a href='?' class='btn btn-warning'>🔄 Voltar ao Teste Principal</a>";
    echo "</p>";
    
} else {
    // Executar teste principal
    echo "<div class='step info'>";
    echo "<h3>🚀 Executando Teste Completo...</h3>";
    echo "</div>";
    
    $result = testRawDataParser($mikrotikConfig);
    
    if ($result && $result['success']) {
        echo "<div class='step success'>";
        echo "<h3>📊 Resumo do Teste:</h3>";
        echo "<ul>";
        echo "<li><strong>Usuários encontrados:</strong> " . $result['users_count'] . "</li>";
        echo "<li><strong>Método mais eficaz:</strong> Parser de dados brutos</li>";
        echo "<li><strong>Status:</strong> " . ($result['users_count'] >= 4 ? "✅ SUCESSO TOTAL" : ($result['users_count'] > 1 ? "⚠️ PROGRESSO SIGNIFICATIVO" : "❌ PROBLEMA PERSISTE")) . "</li>";
        echo "</ul>";
        echo "</div>";
    }
}

// Conclusões e próximos passos
echo "<div class='step info'>";
echo "<h3>🎯 Análise dos Resultados:</h3>";

echo "<h4>✅ Se encontrou 4+ usuários:</h4>";
echo "<ul>";
echo "<li>🎉 <strong>PROBLEMA RESOLVIDO!</strong> O parser de dados brutos está funcionando</li>";
echo "<li>💾 Substitua o mikrotik_manager.php atual por esta versão</li>";
echo "<li>🧪 Teste a remoção com usuários não críticos</li>";
echo "<li>🔄 Atualize o index.php para usar a nova classe</li>";
echo "</ul>";

echo "<h4>⚠️ Se encontrou 2-3 usuários:</h4>";
echo "<ul>";
echo "<li>📈 Progresso significativo em relação ao parser anterior</li>";
echo "<li>🔍 Alguns usuários podem estar em formato diferente</li>";
echo "<li>⚙️ Pode ser necessário ajustar os padrões de extração</li>";
echo "<li>🧪 Teste com diferentes configurações</li>";
echo "</ul>";

echo "<h4>❌ Se ainda encontra apenas 1 usuário:</h4>";
echo "<ul>";
echo "<li>🔧 Problema pode estar na captura dos dados brutos</li>";
echo "<li>⏱️ Tente aumentar o timeout</li>";
echo "<li>🔍 Verifique se há filtros no MikroTik</li>";
echo "<li>👤 Teste com usuário 'admin' sem senha</li>";
echo "</ul>";

echo "</div>";

echo "<div class='step warning'>";
echo "<h3>🛠️ Como Implementar no Sistema:</h3>";

echo "<h4>Passo 1: Backup</h4>";
echo "<pre>cp mikrotik_manager.php mikrotik_manager.php.backup</pre>";

echo "<h4>Passo 2: Substituir arquivo</h4>";
echo "<p>Substitua o conteúdo do <code>mikrotik_manager.php</code> pelo código gerado.</p>";

echo "<h4>Passo 3: Atualizar index.php</h4>";
echo "<pre>";
echo "// Substitua a linha de inicialização do MikroTik por:\n";
echo "\$mikrotik = new MikroTikHotspotManagerFixed(\n";
echo "    \$mikrotikConfig['host'],\n";
echo "    \$mikrotikConfig['username'],\n";
echo "    \$mikrotikConfig['password'],\n";
echo "    \$mikrotikConfig['port']\n";
echo ");\n";
echo "</pre>";

echo "<h4>Passo 4: Testar</h4>";
echo "<ul>";
echo "<li>Teste a listagem de usuários</li>";
echo "<li>Teste a criação de novos usuários</li>";
echo "<li>Teste a remoção com usuários não críticos</li>";
echo "<li>Monitore os logs para verificar funcionamento</li>";
echo "</ul>";

echo "</div>";

echo "<div class='step success'>";
echo "<h3>🎉 Características do Novo Parser:</h3>";
echo "<ul>";
echo "<li>✅ <strong>3 Métodos de Extração:</strong> Por padrões, sequência e estrutura</li>";
echo "<li>✅ <strong>Escolha Automática:</strong> Usa o método que encontra mais usuários</li>";
echo "<li>✅ <strong>Dados Brutos:</strong> Trabalha diretamente com os bytes do MikroTik</li>";
echo "<li>✅ <strong>Remoção Verificada:</strong> Confirma que o usuário foi realmente removido</li>";
echo "<li>✅ <strong>Logs Detalhados:</strong> Sistema de logging completo</li>";
echo "<li>✅ <strong>Timeout Robusto:</strong> Não trava em caso de problemas</li>";
echo "<li>✅ <strong>Compatibilidade:</strong> Mantém interface do sistema existente</li>";
echo "</ul>";
echo "</div>";

// Informações técnicas
echo "<div class='step info'>";
echo "<h3>⚙️ Informações Técnicas:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> {$mikrotikConfig['host']}</li>";
echo "<li><strong>Porta:</strong> {$mikrotikConfig['port']}</li>";
echo "<li><strong>Usuário:</strong> {$mikrotikConfig['username']}</li>";
echo "<li><strong>Timeout:</strong> 20 segundos</li>";
echo "<li><strong>Versão:</strong> Parser de Dados Brutos v3.0</li>";
echo "<li><strong>Classe Principal:</strong> MikroTikRawDataParser</li>";
echo "<li><strong>Classe Compatível:</strong> MikroTikHotspotManagerFixed</li>";
echo "</ul>";
echo "</div>";

// Botões de navegação
echo "<p style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' class='btn btn-success'>← Voltar ao Sistema</a>";
echo "<a href='?' class='btn btn-warning'>🔄 Executar Teste Novamente</a>";
echo "<a href='debug_hotel.php' class='btn'>🔍 Debug Sistema</a>";
echo "</p>";

echo "<div class='step warning'>";
echo "<h3>🚨 Importante:</h3>";
echo "<p>Este é o teste final do parser de dados brutos. Se funcionou corretamente, você tem a solução definitiva para o problema de listagem de usuários do MikroTik.</p>";
echo "<p>O parser trabalha diretamente com os dados binários, contornando os problemas da interpretação tradicional da API.</p>";
echo "</div>";

echo "</div>"; // Fechar container
?>