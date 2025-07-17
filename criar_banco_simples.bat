@echo off
title Criar Banco Hotel
color 0A

echo.
echo ================================================
echo      CRIANDO BANCO HOTEL_SYSTEM
echo ================================================
echo.

REM Verificar se MySQL esta rodando
tasklist | findstr "mysqld.exe" >nul
if errorlevel 1 (
    echo Iniciando MySQL...
    net start MySQL 2>nul
    timeout /t 3 /nobreak >nul
)

REM Criar banco automaticamente
echo Criando banco de dados...

echo DROP DATABASE IF EXISTS hotel_system; > "%temp%\hotel_db.sql"
echo CREATE DATABASE hotel_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; >> "%temp%\hotel_db.sql"
echo USE hotel_system; >> "%temp%\hotel_db.sql"
echo. >> "%temp%\hotel_db.sql"
echo CREATE TABLE hotel_guests ( >> "%temp%\hotel_db.sql"
echo     id INT AUTO_INCREMENT PRIMARY KEY, >> "%temp%\hotel_db.sql"
echo     room_number VARCHAR(10) NOT NULL, >> "%temp%\hotel_db.sql"
echo     guest_name VARCHAR(100) NOT NULL, >> "%temp%\hotel_db.sql"
echo     username VARCHAR(50) UNIQUE NOT NULL, >> "%temp%\hotel_db.sql"
echo     password VARCHAR(50) NOT NULL, >> "%temp%\hotel_db.sql"
echo     profile_type VARCHAR(50) DEFAULT 'hotel-guest', >> "%temp%\hotel_db.sql"
echo     checkin_date DATE NOT NULL, >> "%temp%\hotel_db.sql"
echo     checkout_date DATE NOT NULL, >> "%temp%\hotel_db.sql"
echo     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, >> "%temp%\hotel_db.sql"
echo     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, >> "%temp%\hotel_db.sql"
echo     status ENUM('active', 'expired', 'disabled') DEFAULT 'active', >> "%temp%\hotel_db.sql"
echo     INDEX idx_room (room_number), >> "%temp%\hotel_db.sql"
echo     INDEX idx_status (status), >> "%temp%\hotel_db.sql"
echo     INDEX idx_dates (checkin_date, checkout_date) >> "%temp%\hotel_db.sql"
echo ); >> "%temp%\hotel_db.sql"
echo. >> "%temp%\hotel_db.sql"
echo CREATE TABLE access_logs ( >> "%temp%\hotel_db.sql"
echo     id INT AUTO_INCREMENT PRIMARY KEY, >> "%temp%\hotel_db.sql"
echo     username VARCHAR(50) NOT NULL, >> "%temp%\hotel_db.sql"
echo     room_number VARCHAR(10) NOT NULL, >> "%temp%\hotel_db.sql"
echo     action ENUM('login', 'logout', 'created', 'disabled') NOT NULL, >> "%temp%\hotel_db.sql"
echo     ip_address VARCHAR(45), >> "%temp%\hotel_db.sql"
echo     user_agent TEXT, >> "%temp%\hotel_db.sql"
echo     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, >> "%temp%\hotel_db.sql"
echo     INDEX idx_username (username), >> "%temp%\hotel_db.sql"
echo     INDEX idx_room (room_number), >> "%temp%\hotel_db.sql"
echo     INDEX idx_action (action), >> "%temp%\hotel_db.sql"
echo     INDEX idx_date (created_at) >> "%temp%\hotel_db.sql"
echo ); >> "%temp%\hotel_db.sql"
echo. >> "%temp%\hotel_db.sql"
echo CREATE TABLE system_settings ( >> "%temp%\hotel_db.sql"
echo     id INT AUTO_INCREMENT PRIMARY KEY, >> "%temp%\hotel_db.sql"
echo     setting_key VARCHAR(100) UNIQUE NOT NULL, >> "%temp%\hotel_db.sql"
echo     setting_value TEXT, >> "%temp%\hotel_db.sql"
echo     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, >> "%temp%\hotel_db.sql"
echo     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP >> "%temp%\hotel_db.sql"
echo ); >> "%temp%\hotel_db.sql"

REM Executar SQL
"C:\xampp\mysql\bin\mysql.exe" -u root < "%temp%\hotel_db.sql"

if errorlevel 1 (
    echo.
    echo [ERRO] Falha ao criar banco!
    echo Verifique se:
    echo - XAMPP esta instalado
    echo - MySQL esta rodando
    echo - Usuario root existe
    echo.
    pause
) else (
    echo.
    echo [OK] Banco criado com sucesso!
    echo.
    echo Banco: hotel_system
    echo Tabelas: hotel_guests, access_logs, system_settings
    echo.
    echo Agora voce pode:
    echo 1. Deletar config.php (se existir)
    echo 2. Acessar http://localhost/hotel-system/install.php
    echo 3. Continuar com a instalacao
    echo.
    pause
)

REM Limpar arquivo temporario
del "%temp%\hotel_db.sql" 2>nul

echo.
echo Pressione qualquer tecla para sair...
pause >nul