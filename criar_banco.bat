@echo off
title Sistema Hotel - Criacao do Banco de Dados
color 0A

echo.
echo ================================================
echo       SISTEMA HOTEL - CRIACAO DE BANCO
echo ================================================
echo.

REM Verificar se MySQL esta rodando
echo Verificando se MySQL esta rodando...
tasklist /fi "imagename eq mysqld.exe" 2>NUL | find /i /n "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL esta rodando
) else (
    echo [ERRO] MySQL nao esta rodando
    echo.
    echo Tentando iniciar MySQL...
    net start MySQL 2>nul
    if errorlevel 1 (
        echo [ERRO] Nao foi possivel iniciar o MySQL
        echo Verifique se o XAMPP esta instalado corretamente
        pause
        exit
    ) else (
        echo [OK] MySQL iniciado com sucesso
    )
)

echo.
echo ================================================
echo         CONFIGURACAO DO BANCO DE DADOS
echo ================================================
echo.

REM Definir variaveis
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe
set MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe
set DB_NAME=hotel_system
set DB_USER=root
set DB_HOST=localhost

REM Verificar se MySQL existe
if not exist "%MYSQL_PATH%" (
    echo [ERRO] MySQL nao encontrado em %MYSQL_PATH%
    echo Verifique se o XAMPP esta instalado corretamente
    pause
    exit
)

echo Escolha uma opcao:
echo.
echo 1 - Criar banco novo (apaga dados existentes)
echo 2 - Criar banco se nao existir
echo 3 - Criar usuario especifico para o sistema
echo 4 - Backup do banco existente
echo 5 - Restaurar backup
echo 6 - Verificar banco existente
echo 7 - Limpar banco (apagar todos os dados)
echo 0 - Sair
echo.
set /p opcao="Digite sua escolha: "

if "%opcao%"=="1" goto CRIAR_NOVO
if "%opcao%"=="2" goto CRIAR_SE_NAO_EXISTIR
if "%opcao%"=="3" goto CRIAR_USUARIO
if "%opcao%"=="4" goto BACKUP
if "%opcao%"=="5" goto RESTAURAR
if "%opcao%"=="6" goto VERIFICAR
if "%opcao%"=="7" goto LIMPAR
if "%opcao%"=="0" goto SAIR

echo Opcao invalida!
timeout /t 2 /nobreak >nul
goto :EOF

:CRIAR_NOVO
cls
echo.
echo ================================================
echo          CRIANDO BANCO NOVO
echo ================================================
echo.

echo ATENCAO: Esta operacao ira apagar todos os dados existentes!
set /p confirma="Tem certeza? (S/N): "
if /i not "%confirma%"=="S" goto :EOF

echo.
echo Criando banco de dados...

REM Criar arquivo SQL temporario
echo DROP DATABASE IF EXISTS %DB_NAME%; > "%temp%\create_db.sql"
echo CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; >> "%temp%\create_db.sql"
echo USE %DB_NAME%; >> "%temp%\create_db.sql"
echo. >> "%temp%\create_db.sql"
echo -- Tabela de hospedes >> "%temp%\create_db.sql"
echo CREATE TABLE hotel_guests ( >> "%temp%\create_db.sql"
echo     id INT AUTO_INCREMENT PRIMARY KEY, >> "%temp%\create_db.sql"
echo     room_number VARCHAR(10) NOT NULL, >> "%temp%\create_db.sql"
echo     guest_name VARCHAR(100) NOT NULL, >> "%temp%\create_db.sql"
echo     username VARCHAR(50) UNIQUE NOT NULL, >> "%temp%\create_db.sql"
echo     password VARCHAR(50) NOT NULL, >> "%temp%\create_db.sql"
echo     profile_type VARCHAR(50) DEFAULT 'hotel-guest', >> "%temp%\create_db.sql"
echo     checkin_date DATE NOT NULL, >> "%temp%\create_db.sql"
echo     checkout_date DATE NOT NULL, >> "%temp%\create_db.sql"
echo     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, >> "%temp%\create_db.sql"
echo     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, >> "%temp%\create_db.sql"
echo     status ENUM('active', 'expired', 'disabled') DEFAULT 'active', >> "%temp%\create_db.sql"
echo     INDEX idx_room (room_number), >> "%temp%\create_db.sql"
echo     INDEX idx_status (status), >> "%temp%\create_db.sql"
echo     INDEX idx_dates (checkin_date, checkout_date) >> "%temp%\create_db.sql"
echo ); >> "%temp%\create_db.sql"
echo. >> "%temp%\create_db.sql"
echo -- Tabela de logs de acesso >> "%temp%\create_db.sql"
echo CREATE TABLE access_logs ( >> "%temp%\create_db.sql"
echo     id INT AUTO_INCREMENT PRIMARY KEY, >> "%temp%\create_db.sql"
echo     username VARCHAR(50) NOT NULL, >> "%temp%\create_db.sql"
echo     room_number VARCHAR(10) NOT NULL, >> "%temp%\create_db.sql"
echo     action ENUM('login', 'logout', 'created', 'disabled') NOT NULL, >> "%temp%\create_db.sql"
echo     ip_address VARCHAR(45), >> "%temp%\create_db.sql"
echo     user_agent TEXT, >> "%temp%\create_db.sql"
echo     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, >> "%temp%\create_db.sql"
echo     INDEX idx_username (username), >> "%temp%\create_db.sql"
echo     INDEX idx_room (room_number), >> "%temp%\create_db.sql"
echo     INDEX idx_action (action), >> "%temp%\create_db.sql"
echo     INDEX idx_date (created_at) >> "%temp%\create_db.sql"
echo ); >> "%temp%\create_db.sql"
echo. >> "%temp%\create_db.sql"
echo -- Tabela de configuracoes do sistema >> "%temp%\create_db.sql"
echo CREATE TABLE system_settings ( >> "%temp%\create_db.sql"
echo     id INT AUTO_INCREMENT PRIMARY KEY, >> "%temp%\create_db.sql"
echo     setting_key VARCHAR(100) UNIQUE NOT NULL, >> "%temp%\create_db.sql"
echo     setting_value TEXT, >> "%temp%\create_db.sql"
echo     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, >> "%temp%\create_db.sql"
echo     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP >> "%temp%\create_db.sql"
echo ); >> "%temp%\create_db.sql"
echo. >> "%temp%\create_db.sql"
echo -- Inserir dados iniciais >> "%temp%\create_db.sql"
echo INSERT INTO system_settings (setting_key, setting_value) VALUES >> "%temp%\create_db.sql"
echo ('hotel_name', 'Hotel Paradise'), >> "%temp%\create_db.sql"
echo ('system_version', '1.0.0'), >> "%temp%\create_db.sql"
echo ('install_date', NOW()); >> "%temp%\create_db.sql"

REM Executar SQL
"%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% < "%temp%\create_db.sql"

if errorlevel 1 (
    echo [ERRO] Falha ao criar banco de dados
    echo Verifique se o MySQL esta rodando e as credenciais estao corretas
) else (
    echo [OK] Banco de dados criado com sucesso!
    echo.
    echo Banco: %DB_NAME%
    echo Tabelas criadas:
    echo - hotel_guests (hospedes)
    echo - access_logs (logs de acesso)
    echo - system_settings (configuracoes)
)

REM Limpar arquivo temporario
del "%temp%\create_db.sql" 2>nul

echo.
pause
goto :EOF

:CRIAR_SE_NAO_EXISTIR
cls
echo.
echo ================================================
echo       CRIANDO BANCO SE NAO EXISTIR
echo ================================================
echo.

echo Verificando se banco existe...
"%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% -e "USE %DB_NAME%;" 2>nul
if errorlevel 1 (
    echo Banco nao existe. Criando...
    goto CRIAR_NOVO
) else (
    echo [OK] Banco %DB_NAME% ja existe!
    echo.
    echo Verificando tabelas...
    "%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% -e "USE %DB_NAME%; SHOW TABLES;" 2>nul
)

echo.
pause
goto :EOF

:CRIAR_USUARIO
cls
echo.
echo ================================================
echo        CRIANDO USUARIO ESPECIFICO
echo ================================================
echo.

set /p NEW_USER="Digite o nome do usuario: "
set /p NEW_PASS="Digite a senha: "

echo.
echo Criando usuario %NEW_USER%...

echo CREATE USER '%NEW_USER%'@'localhost' IDENTIFIED BY '%NEW_PASS%'; > "%temp%\create_user.sql"
echo GRANT ALL PRIVILEGES ON %DB_NAME%.* TO '%NEW_USER%'@'localhost'; >> "%temp%\create_user.sql"
echo FLUSH PRIVILEGES; >> "%temp%\create_user.sql"

"%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% < "%temp%\create_user.sql"

if errorlevel 1 (
    echo [ERRO] Falha ao criar usuario
) else (
    echo [OK] Usuario %NEW_USER% criado com sucesso!
    echo Permissoes concedidas para o banco %DB_NAME%
)

del "%temp%\create_user.sql" 2>nul

echo.
pause
goto :EOF

:BACKUP
cls
echo.
echo ================================================
echo           BACKUP DO BANCO
echo ================================================
echo.

set BACKUP_DATE=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%
set BACKUP_DATE=%BACKUP_DATE: =0%
set BACKUP_FILE=C:\Backups\Hotel\backup_%BACKUP_DATE%.sql

echo Criando backup do banco %DB_NAME%...

mkdir "C:\Backups\Hotel" 2>nul

"%MYSQLDUMP_PATH%" -u %DB_USER% -h %DB_HOST% %DB_NAME% > "%BACKUP_FILE%"

if errorlevel 1 (
    echo [ERRO] Falha ao criar backup
) else (
    echo [OK] Backup criado com sucesso!
    echo Arquivo: %BACKUP_FILE%
)

echo.
pause
goto :EOF

:RESTAURAR
cls
echo.
echo ================================================
echo         RESTAURAR BACKUP
echo ================================================
echo.

echo Backups disponiveis:
dir "C:\Backups\Hotel\*.sql" /b 2>nul

echo.
set /p BACKUP_NAME="Digite o nome do arquivo de backup: "
set BACKUP_PATH=C:\Backups\Hotel\%BACKUP_NAME%

if not exist "%BACKUP_PATH%" (
    echo [ERRO] Arquivo de backup nao encontrado
    pause
    goto :EOF
)

echo.
echo ATENCAO: Esta operacao ira substituir todos os dados!
set /p confirma="Tem certeza? (S/N): "
if /i not "%confirma%"=="S" goto :EOF

echo.
echo Restaurando backup...

"%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% %DB_NAME% < "%BACKUP_PATH%"

if errorlevel 1 (
    echo [ERRO] Falha ao restaurar backup
) else (
    echo [OK] Backup restaurado com sucesso!
)

echo.
pause
goto :EOF

:VERIFICAR
cls
echo.
echo ================================================
echo         VERIFICAR BANCO EXISTENTE
echo ================================================
echo.

echo Verificando banco %DB_NAME%...
"%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% -e "USE %DB_NAME%; SHOW TABLES;" 2>nul

if errorlevel 1 (
    echo [ERRO] Banco %DB_NAME% nao existe ou nao foi possivel acessar
) else (
    echo [OK] Banco %DB_NAME% existe e esta acessivel!
    echo.
    echo Verificando dados...
    "%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% -e "USE %DB_NAME%; SELECT COUNT(*) as total_hospedes FROM hotel_guests;" 2>nul
    "%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% -e "USE %DB_NAME%; SELECT COUNT(*) as total_logs FROM access_logs;" 2>nul
)

echo.
pause
goto :EOF

:LIMPAR
cls
echo.
echo ================================================
echo           LIMPAR BANCO
echo ================================================
echo.

echo ATENCAO: Esta operacao ira apagar TODOS os dados!
echo Mas mantera a estrutura das tabelas.
set /p confirma="Tem certeza? (S/N): "
if /i not "%confirma%"=="S" goto :EOF

echo.
echo Limpando dados...

echo TRUNCATE TABLE hotel_guests; > "%temp%\clean_db.sql"
echo TRUNCATE TABLE access_logs; >> "%temp%\clean_db.sql"
echo DELETE FROM system_settings WHERE setting_key NOT IN ('hotel_name', 'system_version'); >> "%temp%\clean_db.sql"

"%MYSQL_PATH%" -u %DB_USER% -h %DB_HOST% %DB_NAME% < "%temp%\clean_db.sql"

if errorlevel 1 (
    echo [ERRO] Falha ao limpar banco
) else (
    echo [OK] Banco limpo com sucesso!
    echo Estrutura mantida, dados removidos.
)

del "%temp%\clean_db.sql" 2>nul

echo.
pause
goto :EOF

:SAIR
cls
echo.
echo ================================================
echo           OPERACAO FINALIZADA
echo ================================================
echo.
echo Banco de dados configurado!
echo.
echo Proximos passos:
echo 1. Executar o instalador do sistema
echo 2. Acessar http://localhost/hotel-system/install.php
echo 3. Configurar conexao com MikroTik
echo.
echo Sistema Hotel - 2024
echo.
timeout /t 3 /nobreak >nul
exit