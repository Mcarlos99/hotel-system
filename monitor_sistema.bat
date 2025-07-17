@echo off
:: ========================================
:: Sistema Hotel - Monitor em Tempo Real
:: ========================================

title Sistema Hotel - Monitor em Tempo Real
color 0A

:INICIO
cls
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║                    🏨 SISTEMA HOTEL                          ║
echo  ║                Monitor em Tempo Real                        ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.
echo  📅 Data: %date%  🕐 Hora: %time:~0,8%
echo.

:: Verificar status dos serviços
echo ════════════════════════════════════════════════════════════════
echo  🔧 STATUS DOS SERVIÇOS
echo ════════════════════════════════════════════════════════════════

net start | find "Apache2.4" >nul
if errorlevel 1 (
    echo  ❌ Apache: PARADO
    set APACHE_STATUS=PARADO
) else (
    echo  ✅ Apache: RODANDO
    set APACHE_STATUS=RODANDO
)

net start | find "MySQL" >nul
if errorlevel 1 (
    echo  ❌ MySQL: PARADO
    set MYSQL_STATUS=PARADO
) else (
    echo  ✅ MySQL: RODANDO
    set MYSQL_STATUS=RODANDO
)

:: Verificar conectividade
echo.
echo ════════════════════════════════════════════════════════════════
echo  🌐 CONECTIVIDADE
echo ════════════════════════════════════════════════════════════════

ping 10.0.1.1 -n 1 -w 1000 >nul
if errorlevel 1 (
    echo  ❌ MikroTik: INACESSÍVEL
    set MIKROTIK_STATUS=OFFLINE
) else (
    echo  ✅ MikroTik: CONECTADO
    set MIKROTIK_STATUS=ONLINE
)

:: Testar porta API
powershell "Test-NetConnection -ComputerName 192.168.1.1 -Port 8728 -InformationLevel Quiet" >nul 2>&1
if errorlevel 1 (
    echo  ❌ API MikroTik: INACESSÍVEL
    set API_STATUS=OFFLINE
) else (
    echo  ✅ API MikroTik: DISPONÍVEL
    set API_STATUS=ONLINE
)

:: Testar acesso ao sistema
powershell "try { $response = Invoke-WebRequest -Uri 'http://localhost/hotel-system/' -UseBasicParsing -TimeoutSec 5; if($response.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }" >nul 2>&1
if errorlevel 1 (
    echo  ❌ Sistema Web: INACESSÍVEL
    set WEB_STATUS=OFFLINE
) else (
    echo  ✅ Sistema Web: DISPONÍVEL
    set WEB_STATUS=ONLINE
)

:: Verificar uso de recursos
echo.
echo ════════════════════════════════════════════════════════════════
echo  💻 RECURSOS DO SISTEMA
echo ════════════════════════════════════════════════════════════════

:: CPU
for /f "tokens=2 delims==" %%a in ('wmic cpu get loadpercentage /value ^| find "="') do set CPU_USAGE=%%a
echo  🖥️ CPU: %CPU_USAGE%%%

:: Memória
for /f "tokens=4" %%a in ('systeminfo ^| find "Available Physical Memory"') do set MEMORY_AVAILABLE=%%a
echo  🧠 Memória Disponível: %MEMORY_AVAILABLE%

:: Espaço em disco
for /f "tokens=3" %%a in ('dir C:\ /-c ^| find "bytes free"') do set DISK_FREE=%%a
echo  💾 Espaço Livre: %DISK_FREE% bytes

:: Processos do sistema
echo.
echo ════════════════════════════════════════════════════════════════
echo  🔍 PROCESSOS DO SISTEMA
echo ════════════════════════════════════════════════════════════════

:: Apache
for /f "tokens=5" %%a in ('tasklist /fi "imagename eq httpd.exe" /fo table ^| find "httpd.exe"') do (
    echo  🌐 Apache PID: %%a
    goto APACHE_FOUND
)
echo  ❌ Apache: Processo não encontrado
:APACHE_FOUND

:: MySQL
for /f "tokens=5" %%a in ('tasklist /fi "imagename eq mysqld.exe" /fo table ^| find "mysqld.exe"') do (
    echo  🗄️ MySQL PID: %%a
    goto MYSQL_FOUND
)
echo  ❌ MySQL: Processo não encontrado
:MYSQL_FOUND

:: Verificar logs recentes
echo.
echo ════════════════════════════════════════════════════════════════
echo  📋 LOGS RECENTES
echo ════════════════════════════════════════════════════════════════

if exist "C:\xampp\htdocs\hotel-system\logs\hotel_system.log" (
    echo  📄 Últimas 3 linhas do log do sistema:
    powershell "Get-Content 'C:\xampp\htdocs\hotel-system\logs\hotel_system.log' | Select-Object -Last 3 | ForEach-Object { '     ' + $_ }"
) else (
    echo  ❌ Log do sistema não encontrado
)

:: Verificar usuários online (simulação)
echo.
echo ════════════════════════════════════════════════════════════════
echo  👥 USUÁRIOS ONLINE (SIMULAÇÃO)
echo ════════════════════════════════════════════════════════════════

:: Tentar obter dados via PowerShell e curl
powershell "try { $response = Invoke-WebRequest -Uri 'http://localhost/hotel-system/?ajax=stats' -UseBasicParsing -TimeoutSec 5; $data = $response.Content | ConvertFrom-Json; Write-Host '  🟢 Usuários Online: ' $data.online_users; Write-Host '  👥 Hóspedes Ativos: ' $data.active_guests; Write-Host '  📊 Total Hoje: ' $data.today_guests } catch { Write-Host '  ❌ Não foi possível obter estatísticas' }" 2>nul

:: Alertas
echo.
echo ════════════════════════════════════════════════════════════════
echo  🚨 ALERTAS
echo ════════════════════════════════════════════════════════════════

set ALERTS=0

if "%APACHE_STATUS%"=="PARADO" (
    echo  ⚠️ ALERTA: Apache está parado!
    set /a ALERTS+=1
)

if "%MYSQL_STATUS%"=="PARADO" (
    echo  ⚠️ ALERTA: MySQL está parado!
    set /a ALERTS+=1
)

if "%MIKROTIK_STATUS%"=="OFFLINE" (
    echo  ⚠️ ALERTA: MikroTik inacessível!
    set /a ALERTS+=1
)

if "%API_STATUS%"=="OFFLINE" (
    echo  ⚠️ ALERTA: API do MikroTik inacessível!
    set /a ALERTS+=1
)

if "%WEB_STATUS%"=="OFFLINE" (
    echo  ⚠️ ALERTA: Sistema web inacessível!
    set /a ALERTS+=1
)

if %CPU_USAGE% GTR 80 (
    echo  ⚠️ ALERTA: CPU acima de 80%% (%CPU_USAGE%%%)
    set /a ALERTS+=1
)

if %ALERTS%==0 (
    echo  ✅ Nenhum alerta - Sistema funcionando normalmente
)

:: Controles
echo.
echo ════════════════════════════════════════════════════════════════
echo  🎮 CONTROLES
echo ════════════════════════════════════════════════════════════════
echo.
echo  [R] Reiniciar Serviços  [B] Backup  [L] Ver Logs  [S] Sair
echo  [A] Abrir Sistema       [T] Testar  [M] Menu Principal
echo.

:: Aguardar atualização ou comando
choice /c RBLSATM /t 10 /d C /n /m "Escolha uma opção ou aguarde 10s para atualizar: "

if errorlevel 7 goto MENU_PRINCIPAL
if errorlevel 6 goto TESTAR
if errorlevel 5 goto ABRIR_SISTEMA
if errorlevel 4 goto SAIR
if errorlevel 3 goto VER_LOGS
if errorlevel 2 goto FAZER_BACKUP
if errorlevel 1 goto REINICIAR_SERVICOS

goto INICIO

:REINICIAR_SERVICOS
cls
echo.
echo ════════════════════════════════════════════════════════════════
echo  🔄 REINICIANDO SERVIÇOS
echo ════════════════════════════════════════════════════════════════
echo.

echo Parando serviços...
net stop Apache2.4 >nul 2>&1
net stop MySQL >nul 2>&1

echo Aguardando...
timeout /t 3 /nobreak >nul

echo Iniciando serviços...
net start Apache2.4
net start MySQL

echo.
echo ✅ Serviços reiniciados!
timeout /t 3 /nobreak >nul
goto INICIO

:FAZER_BACKUP
cls
echo.
echo ════════════════════════════════════════════════════════════════
echo  💾 BACKUP RÁPIDO
echo ════════════════════════════════════════════════════════════════
echo.

set DATA=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%
set DATA=%DATA: =0%
set BACKUP_DIR=C:\Backups\Hotel\%DATA%

echo Criando backup...
mkdir "%BACKUP_DIR%" 2>nul
"C:\xampp\mysql\bin\mysqldump.exe" -u root hotel_system > "%BACKUP_DIR%\banco_%DATA%.sql" 2>nul

if errorlevel 1 (
    echo ❌ Erro no backup
) else (
    echo ✅ Backup criado em %BACKUP_DIR%
)

timeout /t 3 /nobreak >nul
goto INICIO

:VER_LOGS
cls
echo.
echo ════════════════════════════════════════════════════════════════
echo  📋 LOGS DETALHADOS
echo ════════════════════════════════════════════════════════════════
echo.

if exist "C:\xampp\htdocs\hotel-system\logs\hotel_system.log" (
    echo Últimas 15 linhas do log:
    echo ────────────────────────────────────────
    powershell "Get-Content 'C:\xampp\htdocs\hotel-system\logs\hotel_system.log' | Select-Object -Last 15"
) else (
    echo ❌ Log não encontrado
)

echo.
echo Pressione qualquer tecla para voltar...
pause >nul
goto INICIO

:TESTAR
cls
echo.
echo ════════════════════════════════════════════════════════════════
echo  🔍 TESTE COMPLETO
echo ════════════════════════════════════════════════════════════════
echo.

echo Testando MikroTik...
ping 192.168.1.1 -n 2

echo.
echo Testando API...
powershell "Test-NetConnection -ComputerName 192.168.1.1 -Port 8728"

echo.
echo Testando sistema web...
powershell "try { $response = Invoke-WebRequest -Uri 'http://localhost/hotel-system/' -UseBasicParsing; Write-Host 'Status:' $response.StatusCode } catch { Write-Host 'Erro:' $_.Exception.Message }"

echo.
echo Pressione qualquer tecla para voltar...
pause >nul
goto INICIO

:ABRIR_SISTEMA
start http://localhost/hotel-system/
goto INICIO

:MENU_PRINCIPAL
start manutencao_hotel.bat
goto INICIO

:SAIR
cls
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║                    Monitor Finalizado                       ║
echo  ║                   Sistema Hotel - 2024                      ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.
timeout /t 2 /nobreak >nul
exit

:: ========================================
:: Funções de log
:: ========================================

:LOG_EVENTO
echo [%date% %time%] %1 >> C:\xampp\htdocs\hotel-system\logs\monitor.log
exit /b