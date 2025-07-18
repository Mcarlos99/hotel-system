<?php
/**
 * fix_database.php - Correção do Banco de Dados
 * 
 * Este script corrige problemas no banco de dados existente,
 * adicionando colunas que podem estar faltando
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configurações
if (!file_exists('config.php')) {
    die("❌ Arquivo config.php não encontrado!");
}

require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção do Banco de Dados - Sistema Hotel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .step.completed { border-left-color: #28a745; }
        .step.error { border-left-color: #dc3545; }
        .step.warning { border-left-color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correção do Banco de Dados - Sistema Hotel</h1>
        <p>Este script corrige problemas no banco de dados existente, adicionando colunas que podem estar faltando.</p>
        
        <?php
        $steps = [];
        $hasErrors = false;
        
        try {
            // Conectar ao banco
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10
            ];
            
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            
            $steps[] = [
                'title' => 'Conexão com Banco',
                'status' => 'completed',
                'message' => 'Conectado com sucesso ao banco: ' . $dbConfig['database']
            ];
            
            // Verificar se a tabela hotel_guests existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'hotel_guests'");
            if ($stmt->rowCount() == 0) {
                $steps[] = [
                    'title' => 'Verificação da Tabela',
                    'status' => 'error',
                    'message' => 'Tabela hotel_guests não existe! Execute o sistema principal para criar as tabelas.'
                ];
                $hasErrors = true;
            } else {
                $steps[] = [
                    'title' => 'Verificação da Tabela',
                    'status' => 'completed',
                    'message' => 'Tabela hotel_guests encontrada'
                ];
                
                // Verificar estrutura atual
                $stmt = $pdo->query("DESCRIBE hotel_guests");
                $currentColumns = [];
                while ($row = $stmt->fetch()) {
                    $currentColumns[] = $row['Field'];
                }
                
                $steps[] = [
                    'title' => 'Estrutura Atual',
                    'status' => 'completed',
                    'message' => 'Colunas encontradas: ' . implode(', ', $currentColumns)
                ];
                
                // Verificar e adicionar colunas faltantes
                $requiredColumns = [
                    'sync_status' => "ENUM('synced', 'pending', 'failed') DEFAULT 'pending'",
                    'last_sync' => "TIMESTAMP NULL"
                ];
                
                $addedColumns = [];
                $existingColumns = [];
                
                foreach ($requiredColumns as $columnName => $columnDefinition) {
                    if (!in_array($columnName, $currentColumns)) {
                        try {
                            $pdo->exec("ALTER TABLE hotel_guests ADD COLUMN {$columnName} {$columnDefinition}");
                            $addedColumns[] = $columnName;
                        } catch (Exception $e) {
                            $steps[] = [
                                'title' => "Erro ao Adicionar {$columnName}",
                                'status' => 'error',
                                'message' => $e->getMessage()
                            ];
                            $hasErrors = true;
                        }
                    } else {
                        $existingColumns[] = $columnName;
                    }
                }
                
                if (!empty($addedColumns)) {
                    $steps[] = [
                        'title' => 'Colunas Adicionadas',
                        'status' => 'completed',
                        'message' => 'Colunas adicionadas: ' . implode(', ', $addedColumns)
                    ];
                }
                
                if (!empty($existingColumns)) {
                    $steps[] = [
                        'title' => 'Colunas Existentes',
                        'status' => 'completed',
                        'message' => 'Colunas que já existiam: ' . implode(', ', $existingColumns)
                    ];
                }
                
                // Adicionar índices se necessário
                $indices = [
                    'idx_sync' => 'sync_status'
                ];
                
                foreach ($indices as $indexName => $indexColumn) {
                    if (in_array($indexColumn, $currentColumns) || in_array($indexColumn, $addedColumns)) {
                        try {
                            $pdo->exec("ALTER TABLE hotel_guests ADD INDEX {$indexName} ({$indexColumn})");
                            $steps[] = [
                                'title' => "Índice {$indexName}",
                                'status' => 'completed',
                                'message' => "Índice {$indexName} adicionado"
                            ];
                        } catch (Exception $e) {
                            // Índice já existe, ignorar
                            $steps[] = [
                                'title' => "Índice {$indexName}",
                                'status' => 'warning',
                                'message' => "Índice {$indexName} já existe ou não pôde ser criado"
                            ];
                        }
                    }
                }
                
                // Verificar dados existentes
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM hotel_guests");
                $totalGuests = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) as active FROM hotel_guests WHERE status = 'active'");
                $activeGuests = $stmt->fetchColumn();
                
                $steps[] = [
                    'title' => 'Dados Existentes',
                    'status' => 'completed',
                    'message' => "Total de hóspedes: {$totalGuests} | Ativos: {$activeGuests}"
                ];
                
                // Atualizar registros sem sync_status
                if (in_array('sync_status', $addedColumns)) {
                    $stmt = $pdo->query("UPDATE hotel_guests SET sync_status = 'pending' WHERE sync_status IS NULL");
                    $updatedRows = $stmt->rowCount();
                    
                    if ($updatedRows > 0) {
                        $steps[] = [
                            'title' => 'Atualização de Dados',
                            'status' => 'completed',
                            'message' => "{$updatedRows} registros atualizados com sync_status = 'pending'"
                        ];
                    }
                }
                
                // Verificar estrutura final
                $stmt = $pdo->query("DESCRIBE hotel_guests");
                $finalColumns = [];
                while ($row = $stmt->fetch()) {
                    $finalColumns[] = $row['Field'];
                }
                
                $steps[] = [
                    'title' => 'Estrutura Final',
                    'status' => 'completed',
                    'message' => 'Colunas finais: ' . implode(', ', $finalColumns)
                ];
            }
            
        } catch (Exception $e) {
            $steps[] = [
                'title' => 'Erro de Conexão',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $hasErrors = true;
        }
        
        // Exibir resultados
        foreach ($steps as $step) {
            echo "<div class='step {$step['status']}'>";
            echo "<h3>";
            
            switch ($step['status']) {
                case 'completed':
                    echo "✅ ";
                    break;
                case 'error':
                    echo "❌ ";
                    break;
                case 'warning':
                    echo "⚠️ ";
                    break;
                default:
                    echo "ℹ️ ";
            }
            
            echo htmlspecialchars($step['title']) . "</h3>";
            echo "<p>" . htmlspecialchars($step['message']) . "</p>";
            echo "</div>";
        }
        
        // Resumo final
        if (!$hasErrors) {
            echo "<div class='card' style='background: #d4edda; border: 1px solid #c3e6cb;'>";
            echo "<h2 style='color: #155724;'>🎉 Correção Concluída com Sucesso!</h2>";
            echo "<p>O banco de dados foi corrigido e está pronto para uso.</p>";
            echo "<p><strong>Próximos passos:</strong></p>";
            echo "<ul>";
            echo "<li>Acesse o sistema principal: <a href='index.php' class='btn btn-success'>Ir para o Sistema</a></li>";
            echo "<li>Teste a criação de credenciais</li>";
            echo "<li>Verifique se não há mais erros</li>";
            echo "</ul>";
            echo "</div>";
        } else {