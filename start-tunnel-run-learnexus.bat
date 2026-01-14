@echo off
REM start-tunnel-run-learnexus.bat
REM Resilient runner for the named Cloudflare tunnel "learnexus".
REM Keeps the tunnel running, waits for network if outage occurs, and logs activity.

setlocal enableDelayedExpansion
set TUNNEL_NAME=learnexus
set LOG_FILE=%~dp0cloudflared-learnexus.log
set PING_TARGET=1.1.1.1
set RESTART_WAIT=5
set NETWORK_WAIT=10

echo ========================================
echo Resilient runner for Cloudflare tunnel: %TUNNEL_NAME%
echo Logs: %LOG_FILE%
echo Press Ctrl+C to stop the script.
echo ========================================

:check_cloudflared
where cloudflared >nul 2>&1
if errorlevel 1 (
    echo %date% %time% - cloudflared not found in PATH. Please install cloudflared and ensure it's on PATH. >> "%LOG_FILE%"
    echo cloudflared not found. Press any key to exit.
    pause>nul
    exit /b 1
)

:main_loop
echo %date% %time% - Starting cloudflared tunnel run %TUNNEL_NAME% >> "%LOG_FILE%"
cloudflared tunnel run %TUNNEL_NAME% >> "%LOG_FILE%" 2>&1
set rc=%ERRORLEVEL%
echo %date% %time% - cloudflared exited with code %rc% >> "%LOG_FILE%"

:: If cloudflared exited (network outage or crash), wait for network before retrying
:wait_network
ping -n 1 %PING_TARGET% >nul 2>&1
if errorlevel 1 (
    echo %date% %time% - Network unreachable, waiting %NETWORK_WAIT% seconds... >> "%LOG_FILE%"
    timeout /t %NETWORK_WAIT% /nobreak >nul
    goto wait_network
)

echo %date% %time% - Network reachable. Restarting tunnel in %RESTART_WAIT% seconds... >> "%LOG_FILE%"
timeout /t %RESTART_WAIT% /nobreak >nul
goto main_loop

endlocal

REM If cloudflared fails because the named tunnel doesn't exist, provide quick instructions.
REM (This section won't be reached unless user interrupts; keep for reference.)
echo.
echo If the tunnel 'learnexus' doesn't exist, create it with:
echo   cloudflared tunnel create learnexus
echo then route DNS:
echo   cloudflared tunnel route dns learnexus learnexus.online
echo create a config at C:\cloudflared\config.yml with your ingress mapping, then run:
echo   cloudflared tunnel run learnexus
pause
