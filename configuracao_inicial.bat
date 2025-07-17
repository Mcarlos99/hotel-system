@echo off
chcp 65001 > nul
:: ========================================
:: Sistema Hotel - Configuracao Inicial
:: ========================================

title Sistema Hotel - Configuracao Inicial
color 0B

echo.
echo  ================================================================
echo  ║                   SISTEMA HOTEL                             ║
echo  ║                Configuracao Inicial                         ║
echo  ================================================================
echo.
echo  Este assistente ira configurar o sistema automaticamente
echo  para funcionar no computador da recepcao.
echo.
pause

:: Verificar se esta executando como administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo.
    echo  ERRO: Este script precisa ser executado como Administrador
    echo.
    echo  Para executar como administrador:
    echo     1. Clique com botao direito no arquivo
    echo     2. Selecione "Executar como administrador"
    echo.
    pause
    exit
)

:MENU_PRINCIPAL
cls
echo.
echo  ================================================================
echo  ║                   SISTEMA HOTEL                             ║
echo  ║                Configuracao Inicial                         ║
echo  ================================================================
echo.
echo  Escolha uma opcao:
echo.
echo  1. Configuracao Automatica Completa
echo  2. Baixar e Instalar XAMPP
echo  3. Configurar Servicos do Windows
echo  4. Configurar Firewall
echo  5. Criar Estrutura de Pastas
echo  6. Verificar Configuracao
echo  7. Mostrar Informacoes de Rede
echo  8. Solucao de Problemas
echo  0. Sair
echo.
set /p opcao="Digite sua escolha (0-8): "

if "%opcao%"=="1" goto CONFIG_COMPLETA
if "%opcao%"=="2" goto INSTALAR_XAMPP
if "%opcao%"=="3" goto CONFIG_SERVICOS
if "%opcao%"=="4" goto CONFIG_FIREWALL
if "%opcao%"=="5" goto CRIAR_PASTAS
if "%opcao%"=="6" goto VERIFICAR_CONFIG
if "%opcao%"=="7" goto INFO_REDE
if "%opcao%"=="8" goto SOLUCAO_PROBLEMAS
if "%opcao%"=="0" goto SAIR

echo Opcao invalida!
timeout /t 2 /nobreak >nul
goto MENU_PRINCIPAL

:CONFIG_COMPLETA
cls
echo.
echo ================================================================
echo  CONFIGURACAO AUTOMATICA COMPLETA
echo ================================================================
echo.

echo IMPORTANTE: Este processo ira:
echo   - Configurar servicos do Windows
echo   - Configurar firewall
echo   - Criar estrutura de pastas
echo   - Configurar inicializacao automatica
echo.
set /p continuar="Deseja continuar? (S/N): "
if /i not "%continuar%"=="S" goto MENU_PRINCIPAL

echo.
echo Criando estrutura de pastas...
call :CRIAR_PASTAS_FUNC

echo.
echo Configurando servicos...
call :CONFIG_SERVICOS_FUNC

echo.
echo Configurando firewall...
call :CONFIG_FIREWALL_FUNC

echo.
echo Criando atalhos...
call :CRIAR_ATALHOS

echo.
echo Configuracao completa finalizada!
echo.
echo Proximos passos:
echo   1. Instalar XAMPP se ainda nao estiver instalado
echo   2. Copiar arquivos do sistema para C:\xampp\htdocs\hotel-system\
echo   3. Executar http://localhost/hotel-system/install.php
echo.
pause
goto MENU_PRINCIPAL

:INSTALAR_XAMPP
cls
echo.
echo ================================================================
echo  INSTALACAO DO XAMPP
echo ================================================================
echo.

echo Verificando se XAMPP ja esta instalado...
if exist "C:\xampp\xampp-control.exe" (
    echo XAMPP ja esta instalado em C:\xampp\
    echo.
    echo Deseja abrir o painel de controle do XAMPP?
    set /p abrir="(S/N): "
    if /i "%abrir%"=="S" start C:\xampp\xampp-control.exe
    goto MENU_PRINCIPAL
)

echo.
echo XAMPP nao encontrado
echo.
echo Para instalar o XAMPP:
echo   1. Acesse: https://www.apachefriends.org/download.html
echo   2. Baixe XAMPP para Windows
echo   3. Execute como administrador
echo   4. Instale em C:\xampp\
echo   5. Selecione: Apache, MySQL, PHP, phpMyAdmin
echo.
echo Abrindo site de download...
start https://www.apachefriends.org/download.html
echo.
pause
goto MENU_PRINCIPAL

:CONFIG_SERVICOS
cls
echo.
echo ================================================================
echo  CONFIGURACAO DE SERVICOS
echo ================================================================
echo.

call :CONFIG_SERVICOS_FUNC
pause
goto MENU_PRINCIPAL

:CONFIG_SERVICOS_FUNC
echo Configurando servicos do Windows...

echo.
echo Configurando Apache como servico...
if exist "C:\xampp\apache\bin\httpd.exe" (
    C:\xampp\apache\bin\httpd.exe -k install -n "Apache2.4"
    sc config Apache2.4 start= auto
    echo Apache configurado para iniciar automaticamente
) else (
    echo Apache nao encontrado - instale XAMPP primeiro
)

echo.
echo Configurando MySQL como servico...
if exist "C:\xampp\mysql\bin\mysqld.exe" (
    C:\xampp\mysql\bin\mysqld.exe --install MySQL --defaults-file="C:\xampp\mysql\bin\my.ini"
    sc config MySQL start= auto
    echo MySQL configurado para iniciar automaticamente
) else (
    echo MySQL nao encontrado - instale XAMPP primeiro
)

echo.
echo Iniciando servicos...
net start Apache2.4 2>nul
net start MySQL 2>nul

echo Configuracao de servicos concluida!
exit /b

:CONFIG_FIREWALL
cls
echo.
echo ================================================================
echo  CONFIGURACAO DO FIREWALL
echo ================================================================
echo.

call :CONFIG_FIREWALL_FUNC
pause
goto MENU_PRINCIPAL

:CONFIG_FIREWALL_FUNC
echo Configurando Firewall do Windows...

echo.
echo Permitindo Apache HTTP Server...
netsh advfirewall firewall add rule name="Apache HTTP Server" dir=in action=allow program="C:\xampp\apache\bin\httpd.exe" enable=yes
netsh advfirewall firewall add rule name="Apache HTTP Server" dir=out action=allow program="C:\xampp\apache\bin\httpd.exe" enable=yes

echo.
echo Permitindo MySQL...
netsh advfirewall firewall add rule name="MySQL Server" dir=in action=allow program="C:\xampp\mysql\bin\mysqld.exe" enable=yes
netsh advfirewall firewall add rule name="MySQL Server" dir=out action=allow program="C:\xampp\mysql\bin\mysqld.exe" enable=yes

echo.
echo Permitindo porta 80 (HTTP)...
netsh advfirewall firewall add rule name="HTTP Port 80" dir=in action=allow protocol=TCP localport=80

echo.
echo Permitindo porta 443 (HTTPS)...
netsh advfirewall firewall add rule name="HTTPS Port 443" dir=in action=allow protocol=TCP localport=443

echo.
echo Permitindo porta 3306 (MySQL)...
netsh advfirewall firewall add rule name="MySQL Port 3306" dir=in action=allow protocol=TCP localport=3306

echo Firewall configurado!
exit /b

:CRIAR_PASTAS
cls
echo.
echo ================================================================
echo  CRIACAO DE ESTRUTURA DE PASTAS
echo ================================================================
echo.

call :CRIAR_PASTAS_FUNC
pause
goto MENU_PRINCIPAL

:CRIAR_PASTAS_FUNC
echo Criando estrutura de pastas...

echo.
echo Criando pasta do sistema...
mkdir "C:\xampp\htdocs\hotel-system" 2>nul
mkdir "C:\xampp\htdocs\hotel-system\logs" 2>nul
mkdir "C:\xampp\htdocs\hotel-system\cache" 2>nul
mkdir "C:\xampp\htdocs\hotel-system\uploads" 2>nul

echo.
echo Criando pasta de backups...
mkdir "C:\Backups" 2>nul
mkdir "C:\Backups\Hotel" 2>nul

echo.
echo Criando pasta de scripts...
mkdir "C:\Scripts\Hotel" 2>nul

echo.
echo Configurando permissoes...
icacls "C:\xampp\htdocs\hotel-system" /grant Everyone:(OI)(CI)F /T 2>nul
icacls "C:\Backups\Hotel" /grant Everyone:(OI)(CI)F /T 2>nul

echo Estrutura de pastas criada!
exit /b

:CRIAR_ATALHOS
echo Criando atalhos na area de trabalho...

echo.
echo Criando atalho para o sistema...
echo Set oWS = WScript.CreateObject("WScript.Shell") > "%temp%\shortcut.vbs"
echo sLinkFile = "%USERPROFILE%\Desktop\Sistema Hotel.lnk" >> "%temp%\shortcut.vbs"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%temp%\shortcut.vbs"
echo oLink.TargetPath = "http://localhost/hotel-system/" >> "%temp%\shortcut.vbs"
echo oLink.Save >> "%temp%\shortcut.vbs"
cscript /nologo "%temp%\shortcut.vbs"
del "%temp%\shortcut.vbs"

echo.
echo Criando atalho para XAMPP Control...
echo Set oWS = WScript.CreateObject("WScript.Shell") > "%temp%\shortcut2.vbs"
echo sLinkFile = "%USERPROFILE%\Desktop\XAMPP Control.lnk" >> "%temp%\shortcut2.vbs"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%temp%\shortcut2.vbs"
echo oLink.TargetPath = "C:\xampp\xampp-control.exe" >> "%temp%\shortcut2.vbs"
echo oLink.Save >> "%temp%\shortcut2.vbs"
cscript /nologo "%temp%\shortcut2.vbs"
del "%temp%\shortcut2.vbs"

echo Atalhos criados!
exit /b

:VERIFICAR_CONFIG
cls
echo.
echo ================================================================
echo  VERIFICACAO DA CONFIGURACAO
echo ================================================================
echo.

echo Verificando instalacao do XAMPP...
if exist "C:\xampp\xampp-control.exe" (
    echo [OK] XAMPP instalado
) else (
    echo [ERRO] XAMPP nao encontrado
)

echo.
echo Verificando servicos...
net start | find "Apache2.4" >nul
if errorlevel 1 (
    echo [ERRO] Apache: PARADO
) else (
    echo [OK] Apache: RODANDO
)

net start | find "MySQL" >nul
if errorlevel 1 (
    echo [ERRO] MySQL: PARADO
) else (
    echo [OK] MySQL: RODANDO
)

echo.
echo Verificando estrutura de pastas...
if exist "C:\xampp\htdocs\hotel-system" (
    echo [OK] Pasta do sistema criada
) else (
    echo [ERRO] Pasta do sistema nao encontrada
)

if exist "C:\Backups\Hotel" (
    echo [OK] Pasta de backups criada
) else (
    echo [ERRO] Pasta de backups nao encontrada
)

echo.
echo Verificando conectividade...
ping 127.0.0.1 -n 1 >nul
if errorlevel 1 (
    echo [ERRO] Localhost: INACESSIVEL
) else (
    echo [OK] Localhost: OK
)

echo.
echo Testando acesso web...
powershell "try { $response = Invoke-WebRequest -Uri 'http://localhost/' -UseBasicParsing -TimeoutSec 5; Write-Host '[OK] Servidor Web: FUNCIONANDO' } catch { Write-Host '[ERRO] Servidor Web: ERRO' }"

echo.
pause
goto MENU_PRINCIPAL

:INFO_REDE
cls
echo.
echo ================================================================
echo  INFORMACOES DE REDE
echo ================================================================
echo.

echo Configuracao de rede atual:
echo.
ipconfig | findstr /C:"IPv4" /C:"Subnet Mask" /C:"Default Gateway"

echo.
echo Testando conectividade com possiveis IPs do MikroTik...
echo.

for %%i in (192.168.1.1 192.168.0.1 10.0.0.1 172.16.1.1) do (
    echo Testando %%i...
    ping %%i -n 1 -w 1000 >nul
    if errorlevel 1 (
        echo   [ERRO] %%i: Nao responde
    ) else (
        echo   [OK] %%i: Responde
    )
)

echo.
echo Para configurar IP fixo:
echo   1. Painel de Controle - Rede e Internet
echo   2. Central de Rede e Compartilhamento
echo   3. Alterar configuracoes do adaptador
echo   4. Botao direito no adaptador - Propriedades
echo   5. Protocolo IP versao 4 - Propriedades
echo   6. Usar o seguinte endereco IP
echo.
echo Sugestao de configuracao:
echo   IP: 192.168.1.100
echo   Mascara: 255.255.255.0
echo   Gateway: 192.168.1.1
echo   DNS: 8.8.8.8
echo.
pause
goto MENU_PRINCIPAL

:SOLUCAO_PROBLEMAS
cls
echo.
echo ================================================================
echo  SOLUCAO DE PROBLEMAS
echo ================================================================
echo.
echo Problemas comuns e solucoes:
echo.
echo [PROBLEMA] Apache nao inicia:
echo   - Verificar se porta 80 nao esta ocupada
echo   - Fechar Skype ou IIS
echo   - Executar como administrador
echo.
echo [PROBLEMA] MySQL nao inicia:
echo   - Verificar se porta 3306 nao esta ocupada
echo   - Parar outros servicos MySQL
echo   - Verificar arquivos de configuracao
echo.
echo [PROBLEMA] Nao consegue acessar MikroTik:
echo   - Verificar IP do MikroTik
echo   - Verificar se API esta habilitada
echo   - Testar conectividade com ping
echo.
echo [PROBLEMA] Sistema nao carrega:
echo   - Verificar se arquivos foram copiados
echo   - Verificar permissoes das pastas
echo   - Verificar logs de erro
echo.
echo Comandos uteis para diagnostico:
echo.
echo   netstat -an ^| findstr :80     (verificar porta 80)
echo   netstat -an ^| findstr :3306   (verificar porta 3306)
echo   tasklist ^| findstr httpd      (verificar Apache)
echo   tasklist ^| findstr mysqld     (verificar MySQL)
echo.
pause
goto MENU_PRINCIPAL

:SAIR
cls
echo.
echo  ================================================================
echo  ║                Configuracao Finalizada                      ║
echo  ║                                                              ║
echo  ║  Proximos passos:                                            ║
echo  ║  1. Instalar XAMPP (se ainda nao instalado)                 ║
echo  ║  2. Copiar arquivos do sistema                               ║
echo  ║  3. Executar http://localhost/hotel-system/install.php      ║
echo  ║                                                              ║
echo  ║                Sistema Hotel - 2024                         ║
echo  ================================================================
echo.
timeout /t 3 /nobreak >nul
exit