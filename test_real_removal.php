<?php
// test_real_removal.php - Teste para verificar remoção REAL do MikroTik
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔬 Teste de Remoção REAL do MikroTik</h1>";

require_once 'config.php';
require_once 'mikrotik_manager.php';

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .step { margin: 10px 0; padding: 15px; border-radius: 8px; border-left: 5px solid; }
    .success { background: #d4edda; border-color: #28a745; color: #155724; }
    .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
    .warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
    .info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
    .user-box { background: #e9ecef; padding: 10px; border-radius: 5px; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
    .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block; }
    .btn-danger { background: #dc3545; }
    .btn-success { background: #28a745; }
</style>";

function displayUsers($users, $title) {
    echo "<h3>{$title}</h3>";
    
    if (empty($users)) {
        echo "<div class='warning'>Nenhum usuário encontrado</div>";
        return;
    }
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Nome</th><th>Senha</th><th>Perfil</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . ($user['id'] ?? 'N/A') . "</td>";
        echo "<td><strong>" . ($user['name'] ?? 'N/A') . "</strong></td>";
        echo "<td>" . ($user['password'] ?? 'N/A') . "</td>";
        echo "<td>" . ($user['profile'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

function testCompleteRemoval($mikrotikConfig, $username) {
    echo "<h2>🎯 Teste Completo de Remoção: <code>{$username}</code></h2>";
    
    try {
        $mikrotik = new MikroTikHotspotManager(
            $mikrotikConfig['host'],
            $mikrotikConfig['username'],
            $mikrotikConfig['password'],
            $mikrotikConfig['port']
        );
        
        // ETAPA 1: Verificar estado inicial
        echo "<div class='step info'><h4>📋 ETAPA 1: Estado Inicial</h4></div>";
        
        $mikrotik->connect();
        
        echo "<div class='step info'>Listando TODOS os usuários hotspot...</div>";
        $allUsers = $mikrotik->listHotspotUsers();
        displayUsers($allUsers, "👥 Todos os Usuários no MikroTik");
        
        echo "<div class='step info'>Verificando usuários ativos...</div>";
        $activeUsers = $mikrotik->getActiveUsers();
        
        if (!empty($activeUsers)) {
            echo "<h4>🟢 Usuários Ativos:</h4>";
            echo "<table><tr><th>ID</th><th>Usuário</th><th>IP</th></tr>";
            foreach ($activeUsers as $user) {
                echo "<tr>";
                echo "<td>" . ($user['id'] ?? 'N/A') . "</td>";
                echo "<td><strong>" . ($user['user'] ?? 'N/A') . "</strong></td>";
                echo "<td>" . ($user['address'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='warning'>Nenhum usuário ativo encontrado</div>";
        }
        
        // ETAPA 2: Verificar se o usuário alvo existe
        echo "<div class='step info'><h4>🔍 ETAPA 2: Verificar Usuário Alvo</h4></div>";
        
        $targetExists = false;
        $targetId = null;
        
        foreach ($allUsers as $user) {
            if (isset($user['name']) && $user['name'] === $username) {
                $targetExists = true;
                $targetId = $user['id'] ?? null;
                echo "<div class='step success'>✅ Usuário <strong>{$username}</strong> encontrado!</div>";
                echo "<div class='user-box'>";
                echo "<strong>ID:</strong> {$targetId}<br>";
                echo "<strong>Nome:</strong> {$user['name']}<br>";
                echo "<strong>Senha:</strong> " . ($user['password'] ?? 'N/A') . "<br>";
                echo "<strong>Perfil:</strong> " . ($user['profile'] ?? 'N/A') . "<br>";
                echo "</div>";
                break;
            }
        }
        
        if (!$targetExists) {
            echo "<div class='step warning'>⚠️ Usuário <strong>{$username}</strong> NÃO encontrado no MikroTik</div>";
            echo "<div class='info'>Isso pode significar que já foi removido ou nunca existiu.</div>";
            $mikrotik->disconnect();
            return false;
        }
        
        // ETAPA 3: Desconectar se estiver ativo
        echo "<div class='step info'><h4>🔌 ETAPA 3: Desconectar Usuário Ativo</h4></div>";
        
        $wasActive = false;
        foreach ($activeUsers as $user) {
            if (isset($user['user']) && $user['user'] === $username) {
                $wasActive = true;
                echo "<div class='step warning'>⚠️ Usuário está ATIVO - desconectando...</div>";
                
                $disconnected = $mikrotik->disconnectUser($username);
                if ($disconnected) {
                    echo "<div class='step success'>✅ Usuário desconectado com sucesso</div>";
                } else {
                    echo "<div class='step warning'>⚠️ Falha na desconexão ou usuário já desconectado</div>";
                }
                break;
            }
        }
        
        if (!$wasActive) {
            echo "<div class='step info'>ℹ️ Usuário não estava ativo</div>";
        }
        
        // ETAPA 4: Remover o usuário
        echo "<div class='step info'><h4>🗑️ ETAPA 4: Remoção do Usuário</h4></div>";
        
        echo "<div class='step info'>Iniciando processo de remoção...</div>";
        
        $removeSuccess = $mikrotik->removeHotspotUser($username);
        
        if ($removeSuccess) {
            echo "<div class='step success'>✅ Comando de remoção executado com sucesso</div>";
        } else {
            echo "<div class='step error'>❌ Falha no comando de remoção</div>";
        }
        
        // ETAPA 5: Verificação final
        echo "<div class='step info'><h4>🔍 ETAPA 5: Verificação Final</h4></div>";
        
        echo "<div class='step info'>Listando usuários novamente para verificar remoção...</div>";
        
        $usersAfterRemoval = $mikrotik->listHotspotUsers();
        
        $stillExists = false;
        foreach ($usersAfterRemoval as $user) {
            if (isset($user['name']) && $user['name'] === $username) {
                $stillExists = true;
                break;
            }
        }
        
        if ($stillExists) {
            echo "<div class='step error'>";
            echo "<h4>❌ FALHA: Usuário AINDA EXISTE após remoção!</h4>";
            echo "<p>Isso indica que há um problema na remoção. O usuário não foi realmente removido do MikroTik.</p>";
            echo "</div>";
            
            displayUsers($usersAfterRemoval, "👥 Usuários Após Tentativa de Remoção");
            
        } else {
            echo "<div class='step success'>";
            echo "<h4>✅ SUCESSO: Usuário foi REALMENTE removido!</h4>";
            echo "<p>O usuário não aparece mais na lista do MikroTik.</p>";
            echo "</div>";
            
            displayUsers($usersAfterRemoval, "👥 Usuários Restantes no MikroTik");
        }
        
        $mikrotik->disconnect();
        
        return !$stillExists;
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h4>❌ ERRO no teste:</h4>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
        echo "</div>";
        
        return false;
    }
}

// Interface principal
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    $username = $_GET['username'] ?? '';
    
    if (empty($username)) {
        echo "<div class='step error'>❌ Nome de usuário não fornecido</div>";
    } else {
        $result = testCompleteRemoval($mikrotikConfig, $username);
        
        echo "<hr>";
        echo "<div class='step " . ($result ? 'success' : 'error') . "'>";
        echo "<h3>" . ($result ? '🎉 TESTE CONCLUÍDO COM SUCESSO!' : '❌ TESTE FALHOU') . "</h3>";
        
        if ($result) {
            echo "<p>O usuário <strong>{$username}</strong> foi realmente removido do MikroTik.</p>";
        } else {
            echo "<p>O usuário <strong>{$username}</strong> NÃO foi removido ou houve erro no processo.</p>";
            echo "<p><strong>Ações recomendadas:</strong></p>";
            echo "<ul>";
            echo "<li>Verificar as credenciais de acesso ao MikroTik</li>";
            echo "<li>Confirmar se o usuário tem permissões para remover usuários hotspot</li>";
            echo "<li>Verificar os logs de erro do PHP para mais detalhes</li>";
            echo "<li>Testar a remoção manual via Winbox/WebFig</li>";
            echo "</ul>";
        }
        echo "</div>";
    }
    
} else {
    // Listar usuários disponíveis
    echo "<h2>👥 Usuários Disponíveis para Teste</h2>";
    
    try {
        // Listar do banco
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password']
        );
        
        $stmt = $pdo->query("
            SELECT room_number, guest_name, username 
            FROM hotel_guests 
            WHERE status = 'active' 
            ORDER BY id DESC 
            LIMIT 10
        ");
        $guests = $stmt->fetchAll();
        
        if (!empty($guests)) {
            echo "<table>";
            echo "<tr><th>Quarto</th><th>Hóspede</th><th>Usuário</th><th>Teste</th></tr>";
            
            foreach ($guests as $guest) {
                echo "<tr>";
                echo "<td>{$guest['room_number']}</td>";
                echo "<td>{$guest['guest_name']}</td>";
                echo "<td><code>{$guest['username']}</code></td>";
                echo "<td>";
                echo "<a href='?action=test&username=" . urlencode($guest['username']) . "' class='btn btn-danger'>";
                echo "🧪 Testar Remoção";
                echo "</a>";
                echo "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<div class='warning'>⚠️ Nenhum usuário ativo encontrado no banco de dados</div>";
        }
        
        // Listar do MikroTik
        echo "<h3>📡 Usuários no MikroTik</h3>";
        
        try {
            $mikrotik = new MikroTikHotspotManager(
                $mikrotikConfig['host'],
                $mikrotikConfig['username'],
                $mikrotikConfig['password'],
                $mikrotikConfig['port']
            );
            
            $mikrotik->connect();
            $mikrotikUsers = $mikrotik->listHotspotUsers();
            $mikrotik->disconnect();
            
            if (!empty($mikrotikUsers)) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Nome</th><th>Perfil</th><th>Teste</th></tr>";
                
                foreach ($mikrotikUsers as $user) {
                    if (isset($user['name'])) {
                        echo "<tr>";
                        echo "<td>" . ($user['id'] ?? 'N/A') . "</td>";
                        echo "<td><code>{$user['name']}</code></td>";
                        echo "<td>" . ($user['profile'] ?? 'N/A') . "</td>";
                        echo "<td>";
                        echo "<a href='?action=test&username=" . urlencode($user['name']) . "' class='btn btn-danger'>";
                        echo "🧪 Testar Remoção";
                        echo "</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                }
                
                echo "</table>";
            } else {
                echo "<div class='warning'>⚠️ Nenhum usuário encontrado no MikroTik</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Erro ao conectar ao MikroTik: " . $e->getMessage() . "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Erro ao conectar ao banco: " . $e->getMessage() . "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🔧 Configurações</h2>";
    echo "<div class='info'>";
    echo "<strong>MikroTik Host:</strong> {$mikrotikConfig['host']}<br>";
    echo "<strong>Porta:</strong> {$mikrotikConfig['port']}<br>";
    echo "<strong>Usuário:</strong> {$mikrotikConfig['username']}<br>";
    echo "</div>";
    
    echo "<h2>🧪 Teste Manual</h2>";
    echo "<form method='GET'>";
    echo "<input type='hidden' name='action' value='test'>";
    echo "<label>Nome do usuário para testar: </label>";
    echo "<input type='text' name='username' placeholder='Ex: 37-865' style='padding: 8px; margin: 5px;'>";
    echo "<button type='submit' class='btn btn-danger'>🧪 Executar Teste de Remoção</button>";
    echo "</form>";
    
    echo "<hr>";
    echo "<div class='info'>";
    echo "<h3>📋 Como usar este teste:</h3>";
    echo "<ol>";
    echo "<li><strong>Escolha um usuário</strong> da lista acima ou digite manualmente</li>";
    echo "<li><strong>Clique em 'Testar Remoção'</strong> para executar o teste completo</li>";
    echo "<li><strong>Observe cada etapa</strong> do processo de remoção</li>";
    echo "<li><strong>Verifique o resultado final</strong> - se o usuário foi realmente removido</li>";
    echo "</ol>";
    echo "<p><strong>⚠️ Atenção:</strong> Este teste remove REALMENTE o usuário do MikroTik!</p>";
    echo "</div>";
    
    echo "<p style='margin-top: 30px;'>";
    echo "<a href='index.php' class='btn'>← Voltar ao Sistema Principal</a>";
    echo "</p>";
}
?>