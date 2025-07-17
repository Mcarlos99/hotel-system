<?php
// index.php - Interface principal (VERSÃO CORRIGIDA)
session_start();

// Incluir arquivos necessários
require_once 'config.php';
require_once 'mikrotik_manager.php'; // Arquivo com todas as classes

// Inicializar o sistema
try {
    $hotelSystem = new HotelHotspotSystem($mikrotikConfig, $dbConfig);
} catch (Exception $e) {
    die("Erro ao inicializar sistema: " . $e->getMessage());
}

// Processar ações do formulário
$result = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['generate_access'])) {
            $result = $hotelSystem->generateCredentials(
                $_POST['room_number'],
                $_POST['guest_name'],
                $_POST['checkin_date'],
                $_POST['checkout_date'],
                $_POST['profile_type'] ?? 'hotel-guest'
            );
            
        } elseif (isset($_POST['remove_access'])) {
            $result = $hotelSystem->removeGuestAccess($_POST['room_number']);
            $message = $result['success'] ? "Acesso removido com sucesso!" : "Erro: " . $result['error'];
            
        } elseif (isset($_POST['cleanup_expired'])) {
            $result = $hotelSystem->cleanupExpiredUsers();
            $message = "Limpeza concluída. Usuários removidos: " . $result['removed'];
        }
        
    } catch (Exception $e) {
        $message = "Erro: " . $e->getMessage();
    }
}

// Obter dados para exibição
$activeGuests = $hotelSystem->getActiveGuests();
$systemStats = $hotelSystem->getSystemStats();

// Função auxiliar para formatar bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// API para atualização via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    header('Content-Type: application/json');
    echo json_encode($hotelSystem->getSystemStats());
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $systemConfig['hotel_name']; ?> - Sistema de Internet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
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
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        .main-content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input[type="text"],
        input[type="date"],
        select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .credentials-card {
            background: linear-gradient(135deg, #f39c12 0%, #d68910 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        
        .credentials-card h3 {
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .credential-value {
            font-family: 'Courier New', monospace;
            font-size: 1.4em;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .credential-value:hover {
            background: rgba(255,255,255,0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        th {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 15px 20px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .room-number {
            font-weight: bold;
            color: #3498db;
            font-size: 1.2em;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            color: white;
            background: #3498db;
        }
        
        .warning-message {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #ffeaa7;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏨 <?php echo $systemConfig['hotel_name']; ?></h1>
            <p>Sistema de Gerenciamento de Internet</p>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['total_guests']; ?></div>
                <div class="stat-label">Total de Hóspedes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['active_guests']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['online_users']; ?></div>
                <div class="stat-label">Online Agora</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $systemStats['today_guests']; ?></div>
                <div class="stat-label">Hoje</div>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Mensagens -->
            <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, 'Erro') === 0 ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulário para gerar acesso -->
            <div class="section">
                <h2>🆕 Gerar Novo Acesso</h2>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="room_number">Número do Quarto:</label>
                            <input type="text" id="room_number" name="room_number" required placeholder="Ex: 101">
                        </div>
                        
                        <div class="form-group">
                            <label for="guest_name">Nome do Hóspede:</label>
                            <input type="text" id="guest_name" name="guest_name" required placeholder="Nome completo">
                        </div>
                        
                        <div class="form-group">
                            <label for="checkin_date">Data de Check-in:</label>
                            <input type="date" id="checkin_date" name="checkin_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="checkout_date">Data de Check-out:</label>
                            <input type="date" id="checkout_date" name="checkout_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_type">Tipo de Perfil:</label>
                            <select id="profile_type" name="profile_type">
                                <?php foreach ($userProfiles as $key => $profile): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $profile['name']; ?> (<?php echo $profile['rate_limit']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="generate_access" class="btn">
                        ✨ Gerar Acesso
                    </button>
                </form>
                
                <!-- Resultado da geração -->
                <?php if (isset($result) && $result['success']): ?>
                    <div class="credentials-card">
                        <h3>✅ Acesso Gerado com Sucesso!</h3>
                        <div class="credential-item">
                            <span>Quarto:</span>
                            <span class="credential-value"><?php echo htmlspecialchars($_POST['room_number']); ?></span>
                        </div>
                        <div class="credential-item">
                            <span>Hóspede:</span>
                            <span class="credential-value"><?php echo htmlspecialchars($_POST['guest_name']); ?></span>
                        </div>
                        <div class="credential-item">
                            <span>Usuário:</span>
                            <span class="credential-value" onclick="copyToClipboard(this)"><?php echo $result['username']; ?></span>
                        </div>
                        <div class="credential-item">
                            <span>Senha:</span>
                            <span class="credential-value" onclick="copyToClipboard(this)"><?php echo $result['password']; ?></span>
                        </div>
                        <div class="credential-item">
                            <span>Perfil:</span>
                            <span class="credential-value"><?php echo $result['profile']; ?></span>
                        </div>
                        <div class="credential-item">
                            <span>Válido até:</span>
                            <span class="credential-value"><?php echo date('d/m/Y', strtotime($result['valid_until'])); ?></span>
                        </div>
                        
                        <?php if (isset($result['warning'])): ?>
                            <div class="warning-message">
                                <strong>⚠️ Atenção:</strong> <?php echo $result['warning']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 20px;">
                            <button onclick="window.print()" class="btn btn-success">
                                🖨️ Imprimir Credenciais
                            </button>
                        </div>
                    </div>
                <?php elseif (isset($result) && !$result['success']): ?>
                    <div class="alert alert-error">
                        <strong>Erro:</strong> <?php echo htmlspecialchars($result['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Hóspedes Ativos -->
            <div class="section">
                <h2>👥 Hóspedes Ativos</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Quarto</th>
                            <th>Hóspede</th>
                            <th>Usuário</th>
                            <th>Perfil</th>
                            <th>Check-out</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeGuests as $guest): ?>
                        <tr>
                            <td class="room-number"><?php echo htmlspecialchars($guest['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($guest['guest_name']); ?></td>
                            <td><?php echo htmlspecialchars($guest['username']); ?></td>
                            <td>
                                <span class="profile-badge">
                                    <?php echo isset($userProfiles[$guest['profile_type']]) ? $userProfiles[$guest['profile_type']]['name'] : $guest['profile_type']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($guest['checkout_date'])); ?></td>
                            <td>
                                <div class="actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="room_number" value="<?php echo $guest['room_number']; ?>">
                                        <button type="submit" name="remove_access" class="btn btn-danger" 
                                                onclick="return confirm('Remover acesso do quarto <?php echo $guest['room_number']; ?>?')">
                                            🗑️ Remover
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="actions" style="margin-top: 20px;">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="cleanup_expired" class="btn btn-danger">
                            🧹 Limpar Expirados
                        </button>
                    </form>
                    <button onclick="location.reload()" class="btn">
                        🔄 Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Definir data atual como padrão para check-in
        document.getElementById('checkin_date').valueAsDate = new Date();
        
        // Definir data de amanhã como padrão para check-out
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('checkout_date').valueAsDate = tomorrow;
        
        // Função para copiar texto
        function copyToClipboard(element) {
            const text = element.textContent;
            navigator.clipboard.writeText(text).then(() => {
                // Feedback visual
                const original = element.style.background;
                element.style.background = 'rgba(39, 174, 96, 0.3)';
                setTimeout(() => {
                    element.style.background = original;
                }, 500);
            });
        }
        
        // Atualização automática das estatísticas
        setInterval(() => {
            fetch('?ajax=stats')
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.stat-card:nth-child(3) .stat-number').textContent = data.online_users;
                })
                .catch(error => console.log('Erro ao atualizar estatísticas:', error));
        }, 30000); // Atualizar a cada 30 segundos
    </script>
</body>
</html>