@echo off
echo ========================================================
echo  Starting InternHub PHP Built-in Dev Server...
echo ========================================================
echo  URL: http://localhost:8000/
echo  Press Ctrl+C to stop the server.
echo ========================================================
cd /D "%~dp0"
start http://localhost:8000/
C:\xampp\php\php.exe -S localhost:8000
pause
