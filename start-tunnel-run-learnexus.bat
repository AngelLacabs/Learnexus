@echo off
REM start-tunnel-run-learnexus.bat
REM Resilient runner for the named Cloudflare tunnel "learnexus".
REM Keeps the tunnel running, waits for network if outage occurs, and logs activity.
@echo off
REM start-tunnel-run-learnexus.bat
REM Simple runner for the named Cloudflare tunnel "learnexus".
REM Shows cloudflared output in the console and restarts automatically on exit.

set TUNNEL_NAME=learnexus
set RESTART_WAIT=5

echo ========================================
echo Running Cloudflare tunnel: %TUNNEL_NAME%
echo Press Ctrl+C to stop.
echo ========================================

:: Ensure cloudflared is available
where cloudflared >nul 2>&1
if errorlevel 1 (
    echo cloudflared not found in PATH.
    echo Install from: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/installation
    pause
    exit /b 1
)

:run_loop
echo.
echo %date% %time% - Starting: cloudflared tunnel run %TUNNEL_NAME%
cloudflared tunnel run %TUNNEL_NAME%
echo %date% %time% - cloudflared exited with code %ERRORLEVEL%
echo Waiting %RESTART_WAIT% seconds before restarting...
timeout /t %RESTART_WAIT% /nobreak >nul
goto run_loop

