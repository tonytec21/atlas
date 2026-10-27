@echo off
title Atualizando o Atlas
echo Aguarde, atualizando o Atlas...
cd /d "%~dp0"
rem Usa o mesmo atualizador da tela de login (funciona com o repositorio privado).
set "PHPEXE=php"
where php >nul 2>nul || (if exist "C:\xampp\php\php.exe" set "PHPEXE=C:\xampp\php\php.exe")
if not "%PHPEXE%"=="php" goto rodar
where php >nul 2>nul && goto rodar
for /d %%D in ("%SystemDrive%\laragon\bin\php\php-*") do set "PHPEXE=%%D\php.exe"
:rodar
rem -d display_startup_errors=0 e 2>nul escondem avisos de extensoes do php.ini que nao existem na maquina (ex.: imagick)
"%PHPEXE%" -d display_startup_errors=0 update_atlas\atualizador_git.php 2>nul
echo.
timeout /t 120
