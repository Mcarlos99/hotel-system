@echo off
:: ========================================
:: Sistema Hotel - Scripts de Manutenção
:: ========================================

:MENU
cls
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║                    🏨 SISTEMA HOTEL                          ║
echo  ║                  Menu de Manutenção                         ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.
echo  1. Iniciar Sistema
echo  2. Parar Sistema  
echo  3. Reiniciar Sistema
echo  4. Verificar Status
echo  5. Fazer Backup
echo  6. Verificar Logs
echo  7. Testar Conectividade
echo  8. Limpeza de Arquivos Temporários
echo  9. Atualizar Sistema
echo  0. Sair
echo.
set /p opcao="Escolha uma opção: "

if "%opcao%"=="1" goto INICIAR
if "%opcao%"=="2" goto PARAR
if "%opcao%"=="3" goto REINICIAR
if "%opcao%"=="4" goto STATUS
if "%opcao%"=="5" goto BACKUP
if "%opcao%"=="6" goto LOGS
if "%opcao%"=="7" goto TESTAR
if "%opcao%"=="8" goto LIMPEZA
if "%opcao%"=="9" goto ATUALIZAR
if "%opcao%"=="0" goto SAIR

goto MENU

:INICIAR
cls
echo ════════════════════════════════════════
echo  🚀 INICIANDO SISTEMA HOTEL
echo ════════════════════════════════════════
echo.

echo Verificando serviços...
net start | find "Apache2.4" >nul
if errorlevel 1 (
    echo ⚡ Iniciando Apache...
    net start Apache2.4
) else (
    echo ✅ Apache já está rodando
)

net start | find "MySQL" >nul
if errorlevel 1 (
    echo ⚡ Iniciando MySQL...
    net start MySQL
) else (
    echo ✅ MySQL já está rodando
)

echo.
echo 🌐 Abrindo sistema no navegador...
timeout /t 3 /nobreak >nul
start http://localhost/hotel-system/

echo.
echo ✅ Sistema iniciado com sucesso!
echo 📍 Acesse: http://localhost/hotel-system/
echo.
pause
goto MENU

:PARAR
cls
echo ════════════════════════════════════════
echo  🛑 PARANDO SISTEMA HOTEL
echo ════════════════════════════════════════
echo.

echo Parando Apache...
net stop Apache2.4 2>nul
if errorlevel 1 (
    echo ⚠️ Apache não estava rodando
) else (
    echo ✅ Apache parado
)

echo Parando MySQL...
net stop MySQL 2>nul
if errorlevel 1 (
    echo ⚠️ MySQL não estava rodando
) else (
    echo ✅ MySQL parado
)

echo.
echo ✅ Sistema parado com sucesso!
echo.
pause
goto MENU

:REINICIAR
cls
echo ════════════════════════════════════════
echo  🔄 REINICIANDO SISTEMA HOTEL
echo ════════════════════════════════════════
echo.

echo Parando serviços...
net stop Apache2.4 2>nul
net stop MySQL 2>nul

echo Aguardando...
timeout /t 5 /nobreak >nul

echo Iniciando serviços...
net start Apache2.4
net start MySQL

echo.
echo ✅ Sistema reiniciado com sucesso!
echo.
pause
goto MENU

:STATUS
cls
echo ════════════════════════════════════════
echo  📊 STATUS DO SISTEMA HOTEL
echo ════════════════════════════════════════
echo.

echo 🔍 Verificando serviços...
echo.

net start | find "Apache2.4" >nul
if errorlevel 1 (
    echo ❌ Apache: PARADO
) else (
    echo ✅ Apache: RODANDO
)

net start | find "MySQL" >nul
if errorlevel 1 (
    echo ❌ MySQL: PARADO
) else (
    echo ✅ MySQL: RODANDO
)

echo.
echo 🔍 Verificando arquivos...
if exist "C:\xampp\htdocs\hotel-system\index.php" (
    echo ✅ Sistema: INSTALADO
) else (
    echo ❌ Sistema: NÃO ENCONTRADO
)

if exist "C:\xampp\htdocs\hotel-system\config.php" (
    echo ✅ Configuração: OK
) else (
    echo ❌ Configuração: NÃO ENCONTRADA
)

echo.
echo 🔍 Verificando conectividade...
ping 192.168.1.1 -n 1 >nul
if errorlevel 1 (
    echo ❌ MikroTik: INACESSÍVEL
) else (
    echo ✅ MikroTik: CONECTADO
)

echo.
echo 📁 Espaço em disco:
dir C:\xampp\htdocs\hotel-system | find "bytes free"

echo.
pause
goto MENU

:BACKUP
cls
echo ════════════════════════════════════════
echo  💾 BACKUP DO SISTEMA HOTEL
echo ════════════════════════════════════════
echo.

set DATA=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%
set DATA=%DATA: =0%
set BACKUP_DIR=C:\Backups\Hotel\%DATA%

echo 📁 Criando diretório de backup...
mkdir "%BACKUP_DIR%" 2>nul

echo 🗄️ Fazendo backup do banco de dados...
"C:\xampp\mysql\bin\mysqldump.exe" -u root hotel_system > "%BACKUP_DIR%\banco_%DATA%.sql" 2>nul
if errorlevel 1 (
    echo ❌ Erro no backup do banco
) else (
    echo ✅ Backup do banco concluído
)

echo 📂 Fazendo backup dos arquivos...
xcopy "C:\xampp\htdocs\hotel-system" "%BACKUP_DIR%\sistema" /E /I /H /Y /Q
if errorlevel 1 (
    echo ❌ Erro no backup dos arquivos
) else (
    echo ✅ Backup dos arquivos concluído
)

echo 📄 Fazendo backup dos logs...
xcopy "C:\xampp\htdocs\hotel-system\logs" "%BACKUP_DIR%\logs" /E /I /H /Y /Q

echo.
echo ✅ Backup concluído!
echo 📍 Local: %BACKUP_DIR%
echo.

echo 🧹 Limpando backups antigos (mais de 30 dias)...
forfiles /p "C:\Backups\Hotel" /s /m *.* /d -30 /c "cmd /c del @path" 2>nul

echo.
pause
goto MENU

:LOGS
cls
echo ════════════════════════════════════════
echo  📋 LOGS DO SISTEMA HOTEL
echo ════════════════════════════════════════
echo.

echo 📊 Últimas 20 linhas do log do sistema:
echo ────────────────────────────────────────
if exist "C:\xampp\htdocs\hotel-system\logs\hotel_system.log" (
    powershell "Get-Content 'C:\xampp\htdocs\hotel-system\logs\hotel_system.log' | Select-Object -Last 20"
) else (
    echo ❌ Log não encontrado
)

echo.
echo ────────────────────────────────────────
echo 📊 Últimas 10 linhas do log do Apache:
echo ────────────────────────────────────────
if exist "C:\xampp\apache\logs\access.log" (
    powershell "Get-Content 'C:\xampp\apache\logs\access.log' | Select-Object -Last 10"
) else (
    echo ❌ Log do Apache não encontrado
)

echo.
echo ────────────────────────────────────────
echo 📊 Últimas 10 linhas do log de erros:
echo ────────────────────────────────────────
if exist "C:\xampp\apache\logs\error.log" (
    powershell "Get-Content 'C:\xampp\apache\logs\error.log' | Select-Object -Last 10"
) else (
    echo ❌ Log de erros não encontrado
)

echo.
pause
goto MENU

:TESTAR
cls
echo ════════════════════════════════════════
echo  🔍 TESTE DE CONECTIVIDADE
echo ════════════════════════════════════════
echo.

echo 🌐 Testando conectividade com MikroTik...
ping 192.168.1.1 -n 4
echo.

echo 🔌 Testando porta API do MikroTik...
powershell "Test-NetConnection -ComputerName 192.168.1.1 -Port 8728"
echo.

echo 🌐 Testando acesso local ao sistema...
powershell "try { (Invoke-WebRequest -Uri 'http://localhost/hotel-system/' -UseBasicParsing).StatusCode } catch { 'ERRO: ' + $_.Exception.Message }"
echo.

echo 🗄️ Testando conexão com banco de dados...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "SHOW DATABASES;" 2>nul
if errorlevel 1 (
    echo ❌ Erro na conexão com MySQL
) else (
    echo ✅ Conexão com MySQL OK
)

echo.
pause
goto MENU

:LIMPEZA
cls
echo ════════════════════════════════════════
echo  🧹 LIMPEZA DO SISTEMA
echo ════════════════════════════════════════
echo.

echo 🗑️ Limpando logs antigos...
forfiles /p "C:\xampp\htdocs\hotel-system\logs" /s /m *.log /d -7 /c "cmd /c del @path" 2>nul

echo 🗑️ Limpando arquivos temporários do Apache...
del "C:\xampp\tmp\*.*" /Q 2>nul

echo 🗑️ Limpando cache do sistema...
del "C:\xampp\htdocs\hotel-system\cache\*.*" /Q 2>nul

echo 🗑️ Limpando sessões antigas do PHP...
del "C:\xampp\tmp\sess_*" /Q 2>nul

echo 🗑️ Limpando backups antigos (mais de 30 dias)...
forfiles /p "C:\Backups\Hotel" /s /m *.* /d -30 /c "cmd /c del @path" 2>nul

echo.
echo ✅ Limpeza concluída!
echo.
pause
goto MENU

:ATUALIZAR
cls
echo ════════════════════════════════════════
echo  🔄 ATUALIZAÇÃO DO SISTEMA
echo ════════════════════════════════════════
echo.

echo ⚠️ IMPORTANTE: Faça backup antes de atualizar!
echo.
set /p confirma="Deseja continuar com a atualização? (S/N): "
if /i not "%confirma%"=="S" goto MENU

echo 💾 Fazendo backup automático...
call :BACKUP_SILENCIOSO

echo 📥 Verificando atualizações...
echo ⚠️ Função não implementada nesta versão
echo 📧 Entre em contato com o suporte para atualizações
echo.
pause
goto MENU

:BACKUP_SILENCIOSO
set DATA=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%
set DATA=%DATA: =0%
set BACKUP_DIR=C:\Backups\Hotel\%DATA%

mkdir "%BACKUP_DIR%" 2>nul
"C:\xampp\mysql\bin\mysqldump.exe" -u root hotel_system > "%BACKUP_DIR%\banco_%DATA%.sql" 2>nul
xcopy "C:\xampp\htdocs\hotel-system" "%BACKUP_DIR%\sistema" /E /I /H /Y /Q >nul
exit /b

:SAIR
cls
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║                    🏨 SISTEMA HOTEL                          ║
echo  ║                   Até a próxima!                            ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.
timeout /t 2 /nobreak >nul
exit

:: ========================================
:: Funções auxiliares
:: ========================================

:VERIFICAR_ADMIN
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ❌ Este script precisa ser executado como Administrador
    pause
    exit
)
exit /b