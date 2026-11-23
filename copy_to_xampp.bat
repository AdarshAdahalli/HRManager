@echo off
echo ========================================================
echo  Copying InternHub to XAMPP htdocs...
echo ========================================================

set SOURCE=%~dp0
set TARGET=C:\xampp\htdocs\intern_management

if not exist "%TARGET%" mkdir "%TARGET%"
xcopy "%SOURCE%*" "%TARGET%\" /E /I /Y /Q

echo.
echo ========================================================
echo  SUCCESS! Project deployed to:
echo  %TARGET%
echo.
echo  Access in your browser:
echo  http://localhost/intern_management/
echo ========================================================
pause
