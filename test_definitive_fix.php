<?php
// test_definitive_fix.php - Teste da solução definitiva
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🎯 Teste da Solução Definitiva - Remoção REAL</h1>";

require_once 'config.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .step { margin: 15px 0; padding: 15px; border-radius: 8px; border-left: 5px solid; }
    .success { background: #d4edda; border-color: #28a745; color: #155724; }
    .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
    .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; max-height: 400px; overflow-y: auto; }
    .highlight { background: #ffeb3b; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
    .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin: 5px; }
    .btn-danger { background: #dc3545; }
    .btn-success { background: #28a745; }
</style>";

// Incluir a nova classe
include_once 'mikrotik_manager.php';

function testDefinitiveFix($mikrotikConfig, $username = null) {
    echo "<div class='step info'>";
    echo "<h3>🚀 Testando Nova Abordagem de Remoção</h3>";
    echo "<p>Esta versão usa <strong>3 métodos diferentes</strong> para garantir que a remoção funcione:</p>";
    echo "<ul>";
    echo "<li><strong>Método 1:</strong> Comando direto (como no terminal)</li>";
    echo "<li><strong>Método 2:</strong> Buscar ID e remover (tradicional melhorado)</li>";
    echo "<li><strong>Método 3:</strong> Força bruta (varrer todos os usuários)</li>";
    echo "</ul>";
    echo "</div>";
    
    try {
        // Usar a nova classe
        $mikrotik = new MikroTikParserFixed(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        echo "<div class='step info'><h4>🔌 Conectando ao MikroTik...</h4></div>";
        $mikrotik->connect();
        echo "<div class='step success'>✅ Conectado com sucesso!</div>";
        
        // Se não foi especificado usuário, listar os disponíveis
        if (!$username) {
            echo "<div class='step info'>";
            echo "<h4>👥 Usuários Disponíveis no MikroTik:</h4>";
            
            $users = $mikrotik->listHotspotUsers();
            
            if (empty($users)) {
                echo "<p>❌ Nenhum usuário encontrado ou erro na listagem</p>";
            } else {
                echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                echo "<tr><th>ID</th><th>Nome</th><th>Perfil</th><th>Ação</th></tr>";
                
                foreach ($users as $user) {
                    $name = $user['name'] ?? 'N/A';
                    $id = $user['id'] ?? 'N/A';
                    $profile = $user['profile'] ?? 'N/A';
                    
                    echo "<tr>";
                    echo "<td>{$id}</td>";
                    echo "<td><strong>{$name}</strong></td>";
                    echo "<td>{$profile}</td>";
                    echo "<td>";
                    echo "<a href='?test_user=" . urlencode($name) . "' class='btn btn-danger'>🧪 Testar Remoção</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
            echo "</div>";
            
        } else {
            // Testar remoção do usuário específico
            echo "<div class='step warning'>";
            echo "<h4>🎯 Testando Remoção do Usuário: <span class='highlight'>{$username}</span></h4>";
            echo "</div>";
            
            $result = $mikrotik->testRemoveUser($username);
            
            if ($result['success']) {
                echo "<div class='step success'>";
                echo "<h4>🎉 SUCESSO!</h4>";
                echo "<p>{$result['message']}</p>";
                echo "<p><strong>✅ O usuário foi removido do MikroTik!</strong></p>";
                echo "</div>";
                
                // Verificar se realmente foi removido
                echo "<div class='step info'>";
                echo "<h4>🔍 Verificação Final:</h4>";
                echo "<p>Listando usuários para confirmar que foi removido...</p>";
                
                $usersAfter = $mikrotik->listHotspotUsers();
                $stillExists = false;
                
                foreach ($usersAfter as $user) {
                    if (isset($user['name']) && $user['name'] === $username) {
                        $stillExists = true;
                        break;
                    }
                }
                
                if ($stillExists) {
                    echo "<div class='step error'>";
                    echo "<p>❌ <strong>ATENÇÃO:</strong> O usuário ainda aparece na lista!</p>";
                    echo "<p>Isso pode indicar um problema de sincronização ou cache.</p>";
                    echo "</div>";
                } else {
                    echo "<div class='step success'>";
                    echo "<p>✅ <strong>CONFIRMADO:</strong> O usuário não aparece mais na lista!</p>";
                    echo "</div>";
                }
                echo "</div>";
                
            } else {
                echo "<div class='step error'>";
                echo "<h4>❌ FALHA na Remoção</h4>";
                echo "<p>{$result['message']}</p>";
                echo "</div>";
                
                echo "<div class='step warning'>";
                echo "<h4>🔧 Diagnóstico:</h4>";
                echo "<p>A remoção falhou mesmo com os 3 métodos. Possíveis causas:</p>";
                echo "<ul>";
                echo "<li>Usuário não existe no MikroTik</li>";
                echo "<li>Permissões insuficientes do usuário da API</li>";
                echo "<li>Usuário está sendo usado ativamente</li>";
                echo "<li>Problema na conexão durante a remoção</li>";
                echo "</ul>";
                echo "</div>";
            }
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

// Verificar se foi passado um usuário para testar
$testUser = $_GET['test_user'] ?? null;

// Executar o teste
testDefinitiveFix($mikrotikConfig, $testUser);

echo "<hr>";

// Formulário para teste manual
echo "<div class='step info'>";
echo "<h3>🧪 Teste Manual</h3>";
echo "<form method='GET'>";
echo "<label>Nome do usuário para testar remoção: </label>";
echo "<input type='text' name='test_user' value='" . ($testUser ?? '') . "' placeholder='Ex: 1-865' style='padding: 8px; margin: 5px;'>";
echo "<button type='submit' class='btn btn-danger'>🧪 Testar Remoção</button>";
echo "</form>";
echo "</div>";

echo "<div class='step success'>";
echo "<h3>🎯 Como Implementar a Correção:</h3>";
echo "<ol>";
echo "<li><strong>Copie a classe MikroTikParserFixed</strong> do código acima</li>";
echo "<li><strong>No seu mikrotik_manager.php</strong>, substitua a classe atual por esta nova</li>";
echo "<li><strong>No index.php</strong>, altere a inicialização:</li>";
echo "</ol>";
echo "<pre>";
echo "// Substituir:\n";
echo "\$mikrotik = new MikroTikHotspotManager(...);\n\n";
echo "// Por:\n";
echo "\$mikrotik = new MikroTikParserFixed(...);\n";
echo "</pre>";
echo "<p><strong>Ou</strong> renomeie a classe atual e mantenha ambas para teste.</p>";
echo "</div>";

echo "<div class='step warning'>";
echo "<h3>⚙️ Principais Melhorias da Nova Versão:</h3>";
echo "<ul>";
echo "<li>✅ <strong>3 Métodos de Remoção:</strong> Se um falhar, tenta os outros</li>";
echo "<li>✅ <strong>Comando Direto:</strong> Usa o mesmo comando do terminal</li>";
echo "<li>✅ <strong>Busca Robusta:</strong> Múltiplas formas de encontrar o ID do usuário</li>";
echo "<li>✅ <strong>Força Bruta:</strong> Varre todos os usuários se necessário</li>";
echo "<li>✅ <strong>Verificação Final:</strong> Confirma se foi realmente removido</li>";
echo "<li>✅ <strong>Logs Detalhados:</strong> Mostra exatamente o que está acontecendo</li>";
echo "</ul>";
echo "</div>";

if (!$testUser) {
    echo "<div class='step info'>";
    echo "<h3>📋 Instruções de Uso:</h3>";
    echo "<ol>";
    echo "<li>Execute este teste primeiro para ver os usuários disponíveis</li>";
    echo "<li>Clique em 'Testar Remoção' para um usuário específico</li>";
    echo "<li>Observe se a remoção funciona com a nova abordagem</li>";
    echo "<li>Se funcionar, implemente a correção no seu sistema</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='step info'>";
    echo "<h3>📝 Resultado do Teste:</h3>";
    echo "<p>O teste acima mostra se o usuário <strong>{$testUser}</strong> foi removido com sucesso.</p>";
    echo "<p><a href='?' class='btn'>🔄 Voltar para Lista de Usuários</a></p>";
    echo "</div>";
}

echo "<hr>";
echo "<div class='step warning'>";
echo "<h3>🚨 Importante:</h3>";
echo "<p><strong>Este teste remove REALMENTE o usuário do MikroTik!</strong></p>";
echo "<p>Use apenas com usuários de teste ou que você realmente quer remover.</p>";
echo "<p>Se você quer apenas testar sem remover, analise primeiro os logs no PHP error log.</p>";
echo "</div>";

echo "<p style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' class='btn'>← Voltar ao Sistema Principal</a>";
echo "<a href='test_user_1865.php' class='btn'>🔍 Teste Específico 1-865</a>";
echo "</p>";

// Mostrar logs recentes se existirem
if (file_exists('logs/hotel_system.log')) {
    echo "<div class='step info'>";
    echo "<h3>📋 Logs Recentes (últimas 10 linhas):</h3>";
    $logs = file('logs/hotel_system.log');
    if ($logs) {
        $recentLogs = array_slice($logs, -10);
        echo "<pre>";
        foreach ($recentLogs as $log) {
            echo htmlspecialchars($log);
        }
        echo "</pre>";
    }
    echo "</div>";
}
?>