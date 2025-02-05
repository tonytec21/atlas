@echo off
setlocal enabledelayedexpansion

:: Caminho do diretório de entrada
set "input_dir=C:\Users\Tony Tec\Downloads\MATRICULAS REURB\MATRICULAS TXT"
:: Caminho do diretório de saída
set "output_dir=C:\Users\Tony Tec\Downloads\MATRICULAS REURB\MATRICULAS DOCX"

:: Cria o diretório de saída, se não existir
if not exist "%output_dir%" mkdir "%output_dir%"

:: Loop para cada arquivo .txt no diretório de entrada
for %%f in ("%input_dir%\*.txt") do (
    :: Nome do arquivo sem extensão
    set "filename=%%~nf"
    :: Converte o arquivo para docx usando o cscript
    cscript //nologo "%ProgramFiles%\Microsoft Office\Office16\word.vbs" "%%f" "%output_dir%\!filename!.docx"
)

echo Conversão concluída!
pause
