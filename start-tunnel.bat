@echo off
REM Learnexus Cloudflare Tunnel Starter
REM This exposes your localhost Learnexus to the internet so SoleSource can send webhooks

echo ========================================
echo Starting Cloudflare Tunnel for Learnexus
echo ========================================
echo.
echo This will expose: http://localhost/Learnexus
echo.
echo IMPORTANT: Copy the generated URL (https://xxxx.trycloudflare.com)
echo and send it to SoleSource team to update their webhook config!
echo.
echo The webhook endpoint will be at:
echo https://xxxx.trycloudflare.com/solesource_webhook.php
echo.
echo Press Ctrl+C to stop the tunnel
echo ========================================
echo.

cloudflared tunnel --url http://localhost/Learnexus

pause
