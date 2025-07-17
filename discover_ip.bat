@echo off
title Descobrir IP do MikroTik
color 0A

echo.
echo ================================================
echo       DESCOBRINDO IP DO MIKROTIK
echo ================================================
echo.

echo Verificando seu gateway atual...
for /f "tokens=2 delims=:" %%i in ('ipconfig ^| findstr "Gateway"') do (
    for /f "tokens=1" %%j in ("%%i") do (
        if not "%%j"=="" (
            echo Seu gateway padrao: %%j
            echo.
        )
    )
)

echo Testando IPs mais comuns do MikroTik...
echo.

echo Testando 192.168.88.1 (Padrao MikroTik)...
ping 192.168.88.1 -n 1 -w 1000 >nul
if errorlevel 1 (
    echo   [ERRO] 192.168.88.1 nao responde
) else (
    echo   [OK] 192.168.88.1 responde!
    echo   Testando API na porta 8728...
    timeout /t 1 /nobreak >nul
    powershell -Command "try { Test-NetConnection -ComputerName 192.168.88.1 -Port 8728 -InformationLevel Quiet -WarningAction SilentlyContinue } catch { exit 1 }" >nul 2>&1
    if errorlevel 1 (
        echo   [ERRO] API nao responde - precisa habilitar
    ) else (
        echo   [OK] API funcionando!
        echo.
        echo ================================================
        echo   IP ENCONTRADO: 192.168.88.1
        echo ================================================
        goto ENCONTRADO
    )
)
echo.

echo Testando 192.168.1.1...
ping 192.168.1.1 -n 1 -w 1000 >nul
if errorlevel 1 (
    echo   [ERRO] 192.168.1.1 nao responde
) else (
    echo   [OK] 192.168.1.1 responde!
    echo   Testando API na porta 8728...
    timeout /t 1 /nobreak >nul
    powershell -Command "try { Test-NetConnection -ComputerName 192.168.1.1 -Port 8728 -InformationLevel Quiet -WarningAction SilentlyContinue } catch { exit 1 }" >nul 2>&1
    if errorlevel 1 (
        echo   [ERRO] API nao responde - precisa habilitar
    ) else (
        echo   [OK] API funcionando!
        echo.
        echo ================================================
        echo   IP ENCONTRADO: 192.168.1.1
        echo ================================================
        goto ENCONTRADO
    )
)
echo.

echo Testando 192.168.0.1...
ping 192.168.0.1 -n 1 -w 1000 >nul
if errorlevel 1 (
    echo   [ERRO] 192.168.0.1 nao responde
) else (
    echo   [OK] 192.168.0.1 responde!
    echo   Testando API na porta 8728...
    timeout /t 1 /nobreak >nul
    powershell -Command "try { Test-NetConnection -ComputerName 192.168.0.1 -Port 8728 -InformationLevel Quiet -WarningAction SilentlyContinue } catch { exit 1 }" >nul 2>&1
    if errorlevel 1 (
        echo   [ERRO] API nao responde - precisa habilitar
    ) else (
        echo   [OK] API funcionando!
        echo.
        echo ================================================
        echo   IP ENCONTRADO: 192.168.0.1
        echo ================================================
        goto ENCONTRADO
    )
)
echo.

echo Testando 10.0.0.1...
ping 10.0.0.1 -n 1 -w 1000 >nul
if errorlevel 1 (
    echo   [ERRO] 10.0.0.1 nao responde
) else (
    echo   [OK] 10.0.0.1 responde!
    echo   Testando API na porta 8728...
    timeout /t 1 /nobreak >nul
    powershell -Command "try { Test-NetConnection -ComputerName 10.0.0.1 -Port 8728 -InformationLevel Quiet -WarningAction SilentlyContinue } catch { exit 1 }" >nul 2>&1
    if errorlevel 1 (
        echo   [ERRO] API nao responde - precisa habilitar
    ) else (
        echo   [OK] API funcionando!
        echo.
        echo ================================================
        echo   IP ENCONTRADO: 10.0.0.1
        echo ================================================
        goto ENCONTRADO
    )
)
echo.

echo ================================================
echo   NENHUM MIKROTIK ENCONTRADO COM API
echo ================================================
echo.
echo Possiveis causas:
echo 1. MikroTik em IP diferente
echo 2. API nao habilitada no MikroTik
echo 3. Firewall bloqueando conexao
echo 4. MikroTik desligado
echo.
echo SOLUCOES:
echo 1. Abra o Winbox e conecte no MikroTik
echo 2. Va em Terminal e digite:
echo    /ip service enable api
echo    /ip service set api port=8728
echo 3. Execute este script novamente
echo.
goto FINAL

:ENCONTRADO
echo.
echo AGORA ALTERE O ARQUIVO config.php:
echo.
echo Abra: C:\xampp\htdocs\hotel-system\config.php
echo.
echo Procure por: $mikrotikConfig = [
echo.
echo E altere para:
echo $mikrotikConfig = [
echo     'host' =^> '%IP_ENCONTRADO%',
echo     'username' =^> 'admin',
echo     'password' =^> 'sua_senha_do_mikrotik',
echo     'port' =^> 8728
echo ];
echo.
echo Depois teste o sistema PHP novamente!
echo.

:FINAL
echo.
echo ================================================
echo Pressione qualquer tecla para sair...
pause >nul