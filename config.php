<?php
// config.php - Configuração manual do sistema
// Criado para contornar erro no instalador

// Configuração do MikroTik
$mikrotikConfig = [
    'host' => '10.0.1.1',        // ALTERE para o IP do seu MikroTik
    'username' => 'hotel-system',           // ALTERE para seu usuário
    'password' => 'hotel123',        // ALTERE para sua senha
    'port' => 8728
];

// Configuração do banco de dados
$dbConfig = [
    'host' => 'localhost',
    'database' => 'hotel_system',
    'username' => 'root',
    'password' => '',                // Deixe vazio se não tiver senha
    'charset' => 'utf8mb4'
];

// Configurações do sistema
$systemConfig = [
    'hotel_name' => 'Hotel Rio',
    'default_profile' => 'hotel-guest',
    'max_concurrent_sessions' => 3,
    'default_bandwidth' => '10M/2M',
    'password_length' => 8,
    'auto_cleanup' => true,
    'timezone' => 'America/Sao_Paulo'
];

// Perfis de usuário
$userProfiles = [
    'hotel-guest' => [
        'name' => 'Hóspede Padrão',
        'rate_limit' => '10M/2M',
        'session_timeout' => '24:00:00',
        'idle_timeout' => '00:30:00',
        'shared_users' => 3
    ],
    'hotel-vip' => [
        'name' => 'Hóspede VIP',
        'rate_limit' => '50M/10M',
        'session_timeout' => '24:00:00',
        'idle_timeout' => '01:00:00',
        'shared_users' => 5
    ],
    'hotel-staff' => [
        'name' => 'Funcionário',
        'rate_limit' => '20M/5M',
        'session_timeout' => '08:00:00',
        'idle_timeout' => '00:15:00',
        'shared_users' => 1
    ]
];

// Configuração de logging
$logConfig = [
    'enable_logging' => true,
    'log_file' => 'logs/hotel_system.log',
    'log_level' => 'INFO',
    'max_log_size' => 10485760,
    'backup_logs' => true
];

// Configuração de segurança
$securityConfig = [
    'max_login_attempts' => 5,
    'lockout_duration' => 300,
    'session_timeout' => 3600,
    'csrf_protection' => true,
    'require_https' => false,
    'allowed_ips' => []
];

// Configuração de notificações
$notificationConfig = [
    'email_notifications' => false,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'seu_email@gmail.com',
    'smtp_password' => 'sua_senha_app',
    'from_email' => 'hotel@seudominio.com',
    'admin_email' => 'admin@seudominio.com'
];

// Configuração de backup
$backupConfig = [
    'auto_backup' => true,
    'backup_interval' => 'daily',
    'backup_path' => 'backups/',
    'keep_backups' => 30,
    'backup_database' => true,
    'backup_logs' => true
];

// Configuração de relatórios
$reportConfig = [
    'generate_reports' => true,
    'report_formats' => ['pdf', 'excel', 'csv'],
    'default_format' => 'pdf',
    'report_logo' => 'assets/logo.png',
    'company_info' => [
        'name' => 'Hotel Paradise',
        'address' => 'Rua das Flores, 123',
        'city' => 'São Paulo - SP',
        'phone' => '(11) 1234-5678',
        'email' => 'contato@hotelparadise.com'
    ]
];

// Definir timezone
date_default_timezone_set($systemConfig['timezone']);

// Função para validar configurações
function validateConfig() {
    global $mikrotikConfig, $dbConfig;
    
    $errors = [];
    
    // Validar configuração do MikroTik
    if (empty($mikrotikConfig['host']) || !filter_var($mikrotikConfig['host'], FILTER_VALIDATE_IP)) {
        $errors[] = "IP do MikroTik inválido";
    }
    
    if (empty($mikrotikConfig['username']) || empty($mikrotikConfig['password'])) {
        $errors[] = "Credenciais do MikroTik não configuradas";
    }
    
    // Validar configuração do banco
    if (empty($dbConfig['host']) || empty($dbConfig['database'])) {
        $errors[] = "Configuração do banco de dados incompleta";
    }
    
    return $errors;
}

// Função para criar diretórios necessários
function createDirectories() {
    $dirs = ['logs', 'backups', 'reports', 'uploads'];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Criar diretórios
createDirectories();

// Verificar se as configurações são válidas
$configErrors = validateConfig();
if (!empty($configErrors)) {
    // Log dos erros mas não pare o sistema
    error_log("Erros de configuração: " . implode(", ", $configErrors));
}

// Definir constantes úteis
define('HOTEL_SYSTEM_VERSION', '1.0.0');
define('HOTEL_SYSTEM_PATH', __DIR__);
define('HOTEL_SYSTEM_URL', 'http://localhost/hotel-system/');

// Função para debug (remover em produção)
function debug_log($message, $data = null) {
    if ($data) {
        $message .= ': ' . json_encode($data);
    }
    error_log('[HOTEL_SYSTEM] ' . $message);
}

// Log de inicialização
debug_log('Sistema iniciado - Configuração carregada');
?>