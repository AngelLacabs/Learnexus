@echo off
REM Learnexus Cloudflare Tunnel Starter (host-based vhost)
REM This script assumes you created a local vhost learnexus.local for the project.

echo ========================================
echo Starting Cloudflare Tunnel for Learnexus (learnexus.local)
echo ========================================
echo.
echo BEFORE RUNNING:
echo - Add an Apache VirtualHost pointing learnexus.local -> C:\xampp\htdocs\Learnexus
echo   Example (add to C:\xampp\apache\conf\extra\httpd-vhosts.conf):
echo.
echo   ^<VirtualHost *:80^>
echo       ServerName learnexus.local
echo       DocumentRoot "C:/xampp/htdocs/Learnexus"
echo       ^<Directory "C:/xampp/htdocs/Learnexus"^>
echo           Options Indexes FollowSymLinks
echo           AllowOverride All
echo           Require all granted
echo       ^</Directory^>
echo   ^</VirtualHost^>
echo.
echo - Add to hosts file (run editor as Administrator):
echo     127.0.0.1 learnexus.local
echo.
echo - Restart Apache after adding vhost and hosts entry.
echo.
echo When complete, this script will start cloudflared and point it at http://learnexus.local
echo.
echo Press any key to continue or Ctrl+C to abort...
pause>nul

echo Starting cloudflared tunnel to http://learnexus.local
cloudflared tunnel --url http://learnexus.local

pause
echo.
echo Also add a fallback vhost for localhost to avoid redirects to XAMPP dashboard:
echo.
echo   ^<VirtualHost *:80^>
echo       ServerName localhost
echo       DocumentRoot "C:/xampp/htdocs"
echo.
echo       ^<Directory "C:/xampp/htdocs"^>
echo           AllowOverride All
echo           Require all granted
echo       ^</Directory^>
echo   ^</VirtualHost^>