@echo off
title Atualizando o Altas
echo Aguarde atualizando o Altas...
cd %SystemDrive%\laragon\www\atlas
git pull
echo.
timeout /t 120
gitpull.bat