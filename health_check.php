<?php
require_once 'config.php';
require_once 'mikrotik_manager.php';

$health = checkSystemHealth($mikrotikConfig);

if (!$health['connection']) {
    // Enviar alerta por email/SMS
    error_log("ALERTA: Sistema MikroTik offline");
}

if ($health['response_time'] > 10000) {
    error_log("ALERTA: Sistema MikroTik lento - " . $health['response_time'] . "ms");
}

echo json_encode($health);
?>