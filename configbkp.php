<?php
// config.php - Arquivo de configuração do sistema

// Configuração do MikroTik
$mikrotikConfig = [
    'host' => '192.168.1.1',        // IP do seu MikroTik
    'username' => 'admin',          // Usuário administrativo do MikroTik
    'password' => 'lukas.beta', // Senha do usuário admin
    'port' => 8728                  // Porta da API (padrão: 8728)
];

// Configuração do banco de dados MySQL
$dbConfig = [
    'host' => 'localhost',
    'database' => 'hotel_system',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Configurações gerais do sistema
$systemConfig = [
    'hotel_name' => 'Hotel Rio',
    'default_profile' => 'hotel-guest',    // Perfil padrão no MikroTik
    'max_concurrent_sessions' => 3,        // Máximo de sessões simultâneas por usuário
    'default_bandwidth' => '10M/2M',       // Largura de banda padrão (download/upload)
    'password_length' => 8,                // Tamanho da senha gerada
    'auto_cleanup' => true,                // Limpeza automática de usuários expirados
    'timezone' => 'America/Sao_Paulo'
];

// Configuração de perfis de usuário
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
    'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
    'max_log_size' => 10485760, // 10MB
    'backup_logs' => true
];

// Configuração de segurança
$securityConfig = [
    'max_login_attempts' => 5,
    'lockout_duration' => 300, // 5 minutos
    'session_timeout' => 3600, // 1 hora
    'csrf_protection' => true,
    'require_https' => false, // Recomendado: true em produção
    'allowed_ips' => [], // IPs permitidos (vazio = todos)
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
    'backup_interval' => 'daily', // daily, weekly, monthly
    'backup_path' => 'backups/',
    'keep_backups' => 30, // Dias
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
        'name' => 'Hotel Rio',
        'address' => 'Rua das Flores, 123',
        'city' => 'Tucuruí - PA',
        'phone' => '(11) 1234-5678',
        'email' => 'contato@hotelrio.com'
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

// Executar validações e criação de diretórios
$configErrors = validateConfig();
if (!empty($configErrors)) {
    die("Erro na configuração: " . implode(", ", $configErrors));
}

createDirectories();
?>