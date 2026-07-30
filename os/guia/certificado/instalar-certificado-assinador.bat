@echo off
REM ============================================================================
REM  Instalar o certificado do Assinador SERPRO nas Autoridades Confiaveis
REM  ---------------------------------------------------------------------------
REM  Depois de rodar isto UMA VEZ na maquina, o Chrome/Edge passam a confiar em
REM  https://127.0.0.1:65156 e o aviso de seguranca nunca mais aparece: a tela
REM  de assinatura vai direto para a autorizacao, dentro do proprio sistema.
REM
REM  Requisitos: executar como Administrador, com o Assinador SERPRO ABERTO.
REM  Atlas / TCloud
REM ============================================================================

setlocal
title Certificado do Assinador SERPRO - Atlas

REM ---- verifica privilegio de administrador ---------------------------------
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo  [!] Este script precisa ser executado como ADMINISTRADOR.
    echo      Clique com o botao direito no arquivo e escolha
    echo      "Executar como administrador".
    echo.
    pause
    exit /b 1
)

echo.
echo  ============================================================
echo   Certificado do Assinador SERPRO - instalacao na maquina
echo  ============================================================
echo.
echo  Verifique se o Assinador SERPRO esta ABERTO antes de continuar.
echo.
pause

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference='Stop';" ^
  "$alvo='127.0.0.1'; $porta=65156;" ^
  "try {" ^
  "  Write-Host '  Conectando ao Assinador em' \"$alvo`:$porta\" '...';" ^
  "  $tcp = New-Object System.Net.Sockets.TcpClient($alvo,$porta);" ^
  "  $cb = [System.Net.Security.RemoteCertificateValidationCallback]{ param($a,$b,$c,$d) $true };" ^
  "  $ssl = New-Object System.Net.Security.SslStream($tcp.GetStream(),$false,$cb);" ^
  "  $ssl.AuthenticateAsClient($alvo);" ^
  "  $cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($ssl.RemoteCertificate);" ^
  "  Write-Host '  Certificado encontrado:' $cert.Subject;" ^
  "  $cadeia = New-Object System.Security.Cryptography.X509Certificates.X509Chain;" ^
  "  $cadeia.ChainPolicy.RevocationMode='NoCheck';" ^
  "  $cadeia.ChainPolicy.VerificationFlags='AllFlags';" ^
  "  [void]$cadeia.Build($cert);" ^
  "  $store = New-Object System.Security.Cryptography.X509Certificates.X509Store('Root','LocalMachine');" ^
  "  $store.Open('ReadWrite');" ^
  "  $n=0;" ^
  "  foreach ($e in $cadeia.ChainElements) { $store.Add($e.Certificate); $n++ };" ^
  "  if ($n -eq 0) { $store.Add($cert); $n=1 };" ^
  "  $store.Close();" ^
  "  $ssl.Dispose(); $tcp.Close();" ^
  "  Write-Host '';" ^
  "  Write-Host '  OK! ' $n ' certificado(s) instalado(s) nas Autoridades Confiaveis.' -ForegroundColor Green;" ^
  "  Write-Host '  Feche e abra o navegador para valer.' -ForegroundColor Green;" ^
  "} catch {" ^
  "  Write-Host '';" ^
  "  Write-Host '  Falhou: ' $_.Exception.Message -ForegroundColor Red;" ^
  "  Write-Host '  Confira se o Assinador SERPRO esta em execucao.' -ForegroundColor Yellow;" ^
  "  exit 1;" ^
  "}"

echo.
if %errorlevel% neq 0 (
    echo  Nada foi instalado. Abra o Assinador SERPRO e tente novamente.
) else (
    echo  Pronto. Feche o navegador e abra de novo para o efeito valer.
)
echo.
pause
endlocal
