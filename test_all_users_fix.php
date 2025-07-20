<?php
// test_all_users_fix.php - Teste específico para ver TODOS os usuários
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); // Sem limite de tempo para debug

echo "<h1>🔍 Teste DEFINITIVO - Ver TODOS os Usuários</h1>";

require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .step { margin: 15px 0; padding: 15px; border-radius: 8px; border-left: 5px solid; }
    .success { background: #d4edda; border-color: #28a745; color: #155724; }
    .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
    .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
    .debug { background: #f8f9fa; border: 1px solid #dee2e6; font-family: monospace; font-size: 11px; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; max-height: 400px; overflow-y: auto; }
    .highlight { background: #ffeb3b; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
    .user-count { font-size: 1.5em; font-weight: bold; color: #dc3545; }
</style>";

// Incluir a nova classe
include_once 'mikrotik_manager.php';

function testAllUsersFix($mikrotikConfig) {
    echo "<div class='step info'>";
    echo "<h3>🎯 Problema Identificado:</h3>";
    echo "<p>O sistema atual só encontra <span class='highlight'>1 usuário (default-trial)</span> mas o Winbox mostra <span class='highlight'>4 usuários</span>:</p>";
    echo "<ul>";
    echo "<li>✅ default-trial (encontrado)</li>";
    echo "<li>❌ admin-recepcao (não encontrado)</li>";
    echo "<li>❌ guest_103 (não encontrado)</li>";
    echo "<li>❌ 37-90 (não encontrado)</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='step warning'>";
    echo "<h3>🔧 Testando Nova Abordagem:</h3>";
    echo "<p>Esta versão usa <strong>3 métodos diferentes</strong> de parser e debug completo para encontrar todos os usuários.</p>";
    echo "</div>";
    
    try {
        // Usar a nova classe com debug
        $mikrotik = new MikroTikUltimateFix(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        echo "<div class='step debug'>";
        echo "<h4>📡 Debug da Conexão e Listagem:</h4>";
        
        // Este método fará debug em tempo real
        $users = $mikrotik->listHotspotUsersWithDebug();
        
        echo "</div>";
        
        echo "<div class='step " . (count($users) >= 4 ? 'success' : 'warning') . "'>";
        echo "<h3>📊 Resultado:</h3>";
        echo "<p>Usuários encontrados: <span class='user-count'>" . count($users) . "</span></p>";
        
        if (count($users) >= 4) {
            echo "<p>🎉 <strong>SUCESSO!</strong> O parser conseguiu encontrar todos (ou a maioria) dos usuários!</p>";
        } elseif (count($users) > 1) {
            echo "<p>⚠️ <strong>PROGRESSO!</strong> Encontrou mais usuários que antes, mas ainda pode estar faltando alguns.</p>";
        } else {
            echo "<p>❌ <strong>PROBLEMA PERSISTE!</strong> Ainda encontra apenas 1 usuário.</p>";
        }
        echo "</div>";
        
        if (!empty($users)) {
            echo "<div class='step success'>";
            echo "<h4>👥 Usuários Encontrados:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
            echo "<tr><th>#</th><th>ID</th><th>Nome</th><th>Senha</th><th>Perfil</th><th>Status</th></tr>";
            
            foreach ($users as $i => $user) {
                $name = $user['name'] ?? 'N/A';
                $id = $user['id'] ?? 'N/A';
                $password = isset($user['password']) ? str_repeat('*', min(8, strlen($user['password']))) : 'N/A';
                $profile = $user['profile'] ?? 'N/A';
                
                // Verificar se é um dos usuários que deveria encontrar
                $isExpected = in_array($name, ['admin-recepcao', 'guest_103', '37-90', 'default-trial']);
                $status = $isExpected ? '✅ Esperado' : '🔍 Adicional';
                
                echo "<tr" . ($isExpected ? " style='background: #d4edda;'" : "") . ">";
                echo "<td>" . ($i + 1) . "</td>";
                echo "<td>{$id}</td>";
                echo "<td><strong>{$name}</strong></td>";
                echo "<td>{$password}</td>";
                echo "<td>{$profile}</td>";
                echo "<td>{$status}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            echo "</div>";
            
            // Testar remoção se houver usuários
            echo "<div class='step info'>";
            echo "<h4>🧪 Teste de Remoção:</h4>";
            echo "<p>Agora que encontramos os usuários, podemos testar a remoção:</p>";
            
            foreach ($users as $user) {
                if (isset($user['name']) && $user['name'] !== 'default-trial') {
                    $name = $user['name'];
                    echo "<p>";
                    echo "<a href='?test_remove=" . urlencode($name) . "' style='background: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin: 5px;'>";
                    echo "🗑️ Testar Remoção: {$name}";
                    echo "</a>";
                    echo "</p>";
                }
            }
            echo "</div>";
            
        } else {
            echo "<div class='step error'>";
            echo "<h4>❌ Nenhum usuário encontrado</h4>";
            echo "<p>Isso indica um problema mais profundo na comunicação com a API do MikroTik.</p>";
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

// Testar remoção se solicitado
if (isset($_GET['test_remove'])) {
    $userToRemove = $_GET['test_remove'];
    
    echo "<div class='step warning'>";
    echo "<h3>🗑️ Testando Remoção do Usuário: <span class='highlight'>{$userToRemove}</span></h3>";
    echo "</div>";
    
    try {
        $mikrotik = new MikroTikUltimateFix(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        echo "<div class='step debug'>";
        echo "<h4>🔧 Debug da Remoção:</h4>";
        
        $result = $mikrotik->removeHotspotUser($userToRemove);
        
        echo "</div>";
        
        if ($result) {
            echo "<div class='step success'>";
            echo "<h4>🎉 SUCESSO!</h4>";
            echo "<p>O usuário <strong>{$userToRemove}</strong> foi removido com sucesso!</p>";
            echo "<p><a href='?' style='background: #28a745; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px;'>🔄 Verificar Lista Atualizada</a></p>";
            echo "</div>";
        } else {
            echo "<div class='step error'>";
            echo "<h4>❌ Falha na Remoção</h4>";
            echo "<p>A remoção do usuário <strong>{$userToRemove}</strong> falhou.</p>";
            echo "<p>Verifique os logs de debug acima para identificar o problema.</p>";
            echo "</div>";
        }
        
        $mikrotik->disconnect();
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h4>❌ ERRO na remoção:</h4>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Executar o teste principal se não estiver testando remoção
if (!isset($_GET['test_remove'])) {
    testAllUsersFix($mikrotikConfig);
}

echo "<hr>";

echo "<div class='step info'>";
echo "<h3>🔍 Análise dos Resultados:</h3>";
echo "<h4>✅ Se encontrou 4+ usuários:</h4>";
echo "<ul>";
echo "<li>O parser está funcionando corretamente</li>";
echo "<li>Substitua a classe no seu sistema principal</li>";
echo "<li>Teste a remoção com usuários não críticos</li>";
echo "</ul>";

echo "<h4>⚠️ Se encontrou 2-3 usuários:</h4>";
echo "<ul>";
echo "<li>Houve progresso, mas ainda há problemas</li>";
echo "<li>Analise os logs de debug para identificar onde parou</li>";
echo "<li>Pode ser problema de timeout ou estrutura da resposta</li>";
echo "</ul>";

echo "<h4>❌ Se ainda encontra apenas 1 usuário:</h4>";
echo "<ul>";
echo "<li>Problema mais profundo na API ou permissões</li>";
echo "<li>Verifique se o usuário da API tem permissões completas</li>";
echo "<li>Teste com usuário 'admin' sem senha</li>";
echo "<li>Verifique se há firewall bloqueando acesso completo à API</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step success'>";
echo "<h3>🛠️ Como Implementar a Correção:</h3>";
echo "<h4>Opção 1: Substituição Direta</h4>";
echo "<pre>";
echo "// No seu mikrotik_manager.php, substitua a classe por:\n";
echo "class MikroTikHotspotManager extends MikroTikUltimateFix {\n";
echo "    // Manter métodos específicos se houver\n";
echo "}\n";
echo "</pre>";

echo "<h4>Opção 2: Teste Paralelo</h4>";
echo "<pre>";
echo "// No index.php, teste com a nova classe:\n";
echo "\$mikrotikNew = new MikroTikUltimateFix(\n";
echo "    \$mikrotikConfig['host'],\n";
echo "    \$mikrotikConfig['username'],\n";
echo "    \$mikrotikConfig['password'],\n";
echo "    \$mikrotikConfig['port']\n";
echo ");\n";
echo "\n";
echo "// Use \$mikrotikNew para listagem e remoção\n";
echo "</pre>";
echo "</div>";

echo "<div class='step warning'>";
echo "<h3>🚨 Características da Nova Versão:</h3>";
echo "<ul>";
echo "<li>✅ <strong>3 Métodos de Parser:</strong> Tradicional, por blocos, e linha por linha</li>";
echo "<li>✅ <strong>Debug Completo:</strong> Mostra exatamente o que está acontecendo</li>";
echo "<li>✅ <strong>Timeout Maior:</strong> 20 segundos para ler todos os dados</li>";
echo "<li>✅ <strong>200 Iterações:</strong> Lê muito mais dados que a versão anterior</li>";
echo "<li>✅ <strong>Escolha Automática:</strong> Usa o método que encontra mais usuários</li>";
echo "<li>✅ <strong>Logs em Tempo Real:</strong> Vê o progresso durante a execução</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step info'>";
echo "<h3>📋 Próximos Passos:</h3>";
echo "<ol>";
echo "<li><strong>Execute este teste</strong> e analise quantos usuários são encontrados</li>";
echo "<li><strong>Compare com o Winbox</strong> para ver se bate o número</li>";
echo "<li><strong>Se funcionar</strong>, implemente no sistema principal</li>";
echo "<li><strong>Teste a remoção</strong> com usuários não críticos primeiro</li>";
echo "<li><strong>Se ainda não funcionar</strong>, analise os logs detalhados</li>";
echo "</ol>";
echo "</div>";

// Mostrar configuração atual
echo "<div class='step info'>";
echo "<h3>⚙️ Configuração Testada:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> {$mikrotikConfig['host']}</li>";
echo "<li><strong>Porta:</strong> {$mikrotikConfig['port']}</li>";
echo "<li><strong>Usuário:</strong> {$mikrotikConfig['username']}</li>";
echo "<li><strong>Timeout:</strong> 20 segundos</li>";
echo "<li><strong>Max Iterações:</strong> 200</li>";
echo "</ul>";
echo "</div>";

echo "<p style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>← Voltar ao Sistema</a>";
echo "<a href='test_definitive_fix.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔧 Teste Anterior</a>";
echo "<a href='?' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔄 Executar Novamente</a>";
echo "</p>";

echo "<div class='step warning'>";
echo "<h3>⚡ Dica Important:</h3>";
echo "<p>Este teste mostra <strong>logs em tempo real</strong> durante a execução. Se a página parecer 'travada', na verdade está processando e mostrando o debug.</p>";
echo "<p>Aguarde até ver o resultado final com a contagem de usuários encontrados.</p>";
echo "</div>";
?>