@echo off
REM Nyalain MCP server (HTTP+token) + Cloudflare Tunnel bareng, buat akses
REM remote (atasan/luar jaringan). Biarin jendela ini TERBUKA selama mau
REM dipakai — tutup jendela ini = koneksi remote-nya putus.
cd /d "%~dp0"
echo Menyalakan MCP HTTP server...
start "SRN MCP HTTP Server" cmd /k "node http-server.js"
timeout /t 2 /nobreak >nul
echo Menyalakan Cloudflare Tunnel...
start "SRN MCP Tunnel" cmd /k ""C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://127.0.0.1:8787"
echo.
echo Dua jendela baru kebuka. Cek jendela "SRN MCP Tunnel" buat lihat URL publiknya
echo (baris "Your quick Tunnel has been created! Visit it at ...").
pause
