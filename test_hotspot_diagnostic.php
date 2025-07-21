<?php
/**
 * test_hotspot_diagnostic.php - Executar Diagnóstico via CLI
 * 
 * Execute este arquivo via linha de comando para diagnóstico completo:
 * php test_hotspot_diagnostic.php
 */

require_once 'config.php';

// Verificar se executando via CLI
if (php_sapi_name() !== 'cli') {
    echo "Este script deve ser executado via linha de comando (CLI)\n";
    echo "Use: php test_hotspot_diagnostic.php\n";
    exit(1);
}

echo "\n";
echo "🔍 DIAGNÓSTICO COMPLETO DO HOTSPOT MIKROTIK\n";
echo str_repeat("=", 50) . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Host: {$mikrotikConfig['host']}:{$mikrotikConfig['port']}\n";
echo str_repeat("=", 50) . "\n\n";

// Incluir a classe de diagnóstico
require_once 'detailed_log_system.php';

try {
    // Criar instância do diagnóstico
    $diagnostic = new HotspotDiagnosticLogger($mikrotikConfig);
    
    echo "📡 Iniciando diagnóstico completo...\n\n";
    
    // Executar diagnóstico completo
    $results = $diagnostic->runFullDiagnostic();
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 DIAGNÓSTICO CONCLUÍDO\n";
    echo str_repeat("=", 50) . "\n";
    
    echo "📁 Arquivo de log: " . $diagnostic->getLogFile() . "\n";
    echo "📋 Total de testes: " . count($results) . "\n";
    
    $successCount = 0;
    $errorCount = 0;
    $warningCount = 0;
    
    foreach ($results as $test => $result) {
        if (strpos($result, 'SUCCESS') !== false) {
            $successCount++;
        } elseif (strpos($result, 'ERROR') !== false || strpos($result, 'CRITICAL') !== false) {
            $errorCount++;
        } elseif (strpos($result, 'WARNING') !== false) {
            $warningCount++;
        }
    }
    
    echo "✅ Sucessos: {$successCount}\n";
    echo "⚠️ Avisos: {$warningCount}\n";
    echo "❌ Erros: {$errorCount}\n";
    echo "📊 Taxa de sucesso: " . round(($successCount / count($results)) * 100, 2) . "%\n\n";
    
    // Mostrar resumo dos resultados
    echo "📋 RESUMO DOS RESULTADOS:\n";
    echo str_repeat("-", 30) . "\n";
    
    foreach ($results as $test => $result) {
        $icon = '📋';
        if (strpos($result, 'SUCCESS') !== false) $icon = '✅';
        elseif (strpos($result, 'ERROR') !== false || strpos($result, 'CRITICAL') !== false) $icon = '❌';
        elseif (strpos($result, 'WARNING') !== false) $icon = '⚠️';
        
        echo "{$icon} {$test}: {$result}\n";
    }
    
    echo "\n💡 PRÓXIMOS PASSOS:\n";
    echo str_repeat("-", 20) . "\n";
    echo "1. 📖 Analise o arquivo de log detalhado\n";
    echo "2. 🔧 Implemente as recomendações sugeridas\n";
    echo "3. 🌐 Teste no Winbox os comandos indicados\n";
    echo "4. 🔄 Execute o diagnóstico novamente após correções\n";
    echo "5. 📞 Se persistir, contate suporte técnico com o log\n\n";
    
    echo "🎯 COMANDOS ESSENCIAIS PARA WINBOX:\n";
    echo str_repeat("-", 35) . "\n";
    
    $commands = [
        "/ip/hotspot/print" => "Verificar servidores hotspot",
        "/ip/hotspot/user/profile/print" => "Verificar perfis de usuário", 
        "/ip/dns/print" => "Verificar configuração DNS",
        "/ip/firewall/filter/print" => "Verificar regras firewall",
        "/ip/hotspot/walled-garden/print" => "Verificar sites liberados",
        "/ip/service/print" => "Verificar serviços ativos",
        "/log/print where topics~\"hotspot\"" => "Ver logs específicos",
        "/ip/hotspot/active/print" => "Ver usuários conectados"
    ];
    
    foreach ($commands as $cmd => $desc) {
        echo "💻 {$cmd}\n   └─ {$desc}\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO NO DIAGNÓSTICO: " . $e->getMessage() . "\n";
    echo "📝 Detalhes técnicos:\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n\n";
    
    echo "🔧 POSSÍVEIS SOLUÇÕES:\n";
    echo "1. Verificar se o MikroTik está ligado e acessível\n";
    echo "2. Confirmar IP, usuário e senha no config.php\n";
    echo "3. Verificar firewall do servidor PHP\n";
    echo "4. Testar conectividade: ping {$mikrotikConfig['host']}\n";
    echo "5. Verificar se API está habilitada no MikroTik\n\n";
    
    exit(1);
}

echo "✅ Diagnóstico concluído com sucesso!\n";
echo "📁 Verifique o arquivo de log para detalhes completos.\n\n";
?>