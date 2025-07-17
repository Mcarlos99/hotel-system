<?php
// Configuração gerada automaticamente em 2025-07-16 18:38:02

$mikrotikConfig = [
    'host' => '',
    'username' => '',
    'password' => '',
    'port' => 
];

$dbConfig = [
    'host' => '',
    'database' => '',
    'username' => '',
    'password' => '',
    'charset' => 'utf8mb4'
];

$systemConfig = [
    'hotel_name' => 'Hotel Rio',
    'default_profile' => 'hotel-guest',
    'max_concurrent_sessions' => 3,
    'default_bandwidth' => '10M/2M',
    'password_length' => 8,
    'auto_cleanup' => true,
    'timezone' => 'America/Sao_Paulo'
];

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
    ]
];

$logConfig = [
    'enable_logging' => true,
    'log_file' => 'logs/hotel_system.log',
    'log_level' => 'INFO',
    'max_log_size' => 10485760,
    'backup_logs' => true
];

date_default_timezone_set($systemConfig['timezone']);
?>