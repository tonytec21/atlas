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
"%PHPEXE%" update_atlas\atualizador_git.php
echo.
timeout /t 120
