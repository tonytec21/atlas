<?php
include(__DIR__ . '/session_check.php'); // Se necessário para verificar a sessão, caso contrário remova esta linha.
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// Função para atualizar recorrências automaticamente
function atualizarRecorrencias($conn) {
    $sql_mensal = "UPDATE contas_a_pagar SET data_vencimento = DATE_ADD(data_vencimento, INTERVAL 1 MONTH), status = 'Pendente' WHERE recorrencia = 'Mensal' AND status = 'Pago' AND data_vencimento < CURDATE()";
    $conn->query($sql_mensal);

    $sql_semanal = "UPDATE contas_a_pagar SET data_vencimento = DATE_ADD(data_vencimento, INTERVAL 1 WEEK), status = 'Pendente' WHERE recorrencia = 'Semanal' AND status = 'Pago' AND data_vencimento < CURDATE()";
    $conn->query($sql_semanal);

    $sql_anual = "UPDATE contas_a_pagar SET data_vencimento = DATE_ADD(data_vencimento, INTERVAL 1 YEAR), status = 'Pendente' WHERE recorrencia = 'Anual' AND status = 'Pago' AND data_vencimento < CURDATE()";
    $conn->query($sql_anual);
}

// Atualizar as contas recorrentes
atualizarRecorrencias($conn);

// Buscando contas prestes a vencer (com 1 dia de antecedência) e vencidas
$sql_prestes_vencer = "SELECT * FROM contas_a_pagar WHERE data_vencimento = CURDATE() + INTERVAL 1 DAY AND status = 'Pendente'";
$sql_vencidas = "SELECT * FROM contas_a_pagar WHERE data_vencimento < CURDATE() AND status = 'Pendente'";

$contas_prestes_vencer = $conn->query($sql_prestes_vencer)->fetch_all(MYSQLI_ASSOC);
$contas_vencidas = $conn->query($sql_vencidas)->fetch_all(MYSQLI_ASSOC);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// E-mail do administrador
$email_admin = "tonytec@outlook.com.br"; // Substitua pelo e-mail real do administrador

// Função para enviar e-mail usando PHPMailer
function enviarEmailAlerta($contas_prestes_vencer, $contas_vencidas, $email_admin) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'atlas@backupcloud.site'; // Seu e-mail autenticado
        $mail->Password = '@Rr6rh3264f9'; // Sua senha
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom('atlas@backupcloud.site', 'Atlas - Alerta de Contas');
        $mail->addAddress($email_admin);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "Alerta de Contas Vencidas e Prestes a Vencer";

        // Construindo a mensagem do e-mail
        $mensagem = "<h1>Alerta de Contas</h1>";
        
        // Contas Vencidas
        $mensagem .= "<h3>Contas Vencidas</h3>";
        if (!empty($contas_vencidas)) {
            $mensagem .= "<table style='border-collapse: collapse; width: 60%;'>
                            <thead>
                                <tr style='background-color: #f8d7da;'>
                                    <th style='border: 1px solid #000; padding: 8px;'>Título</th>
                                    <th style='border: 1px solid #000; padding: 8px;'>Valor</th>
                                    <th style='border: 1px solid #000; padding: 8px;'>Data de Vencimento</th>
                                    <th style='border: 1px solid #000; padding: 8px;'>Status</th>
                                </tr>
                            </thead>
                            <tbody>";
            foreach ($contas_vencidas as $conta) {
                $mensagem .= "<tr style='background-color: #f8d7da;'>
                                <td style='border: 1px solid #000; padding: 8px;'>{$conta['titulo']}</td>
                                <td style='border: 1px solid #000; padding: 8px;'>R$ " . number_format($conta['valor'], 2, ',', '.') . "</td>
                                <td style='border: 1px solid #000; padding: 8px;'>" . date('d/m/Y', strtotime($conta['data_vencimento'])) . "</td>
                                <td style='border: 1px solid #000; padding: 8px;'>{$conta['status']}</td>
                              </tr>";
            }
            $mensagem .= "</tbody></table>";
        } else {
            $mensagem .= "<p>Nenhuma conta vencida.</p>";
        }

        // Contas Prestes a Vencer
        $mensagem .= "<h3>Contas Prestes a Vencer</h3>";
        if (!empty($contas_prestes_vencer)) {
            $mensagem .= "<table style='border-collapse: collapse; width: 60%;'>
                            <thead>
                                <tr style='background-color: #fff3cd;'>
                                    <th style='border: 1px solid #000; padding: 8px;'>Título</th>
                                    <th style='border: 1px solid #000; padding: 8px;'>Valor</th>
                                    <th style='border: 1px solid #000; padding: 8px;'>Data de Vencimento</th>
                                    <th style='border: 1px solid #000; padding: 8px;'>Status</th>
                                </tr>
                            </thead>
                            <tbody>";
            foreach ($contas_prestes_vencer as $conta) {
                $mensagem .= "<tr style='background-color: #fff3cd;'>
                                <td style='border: 1px solid #000; padding: 8px;'>{$conta['titulo']}</td>
                                <td style='border: 1px solid #000; padding: 8px;'>R$ " . number_format($conta['valor'], 2, ',', '.') . "</td>
                                <td style='border: 1px solid #000; padding: 8px;'>" . date('d/m/Y', strtotime($conta['data_vencimento'])) . "</td>
                                <td style='border: 1px solid #000; padding: 8px;'>{$conta['status']}</td>
                              </tr>";
            }
            $mensagem .= "</tbody></table>";
        } else {
            $mensagem .= "<p>Nenhuma conta prestes a vencer.</p>";
        }

        $mail->Body = $mensagem;

        // Enviar o e-mail
        $mail->send();
        echo "E-mail enviado com sucesso.";
    } catch (Exception $e) {
        echo "Erro ao enviar e-mail: {$mail->ErrorInfo}";
    }
}

// Somente enviar e-mail se houver contas vencidas ou prestes a vencer
if (!empty($contas_prestes_vencer) || !empty($contas_vencidas)) {
    enviarEmailAlerta($contas_prestes_vencer, $contas_vencidas, $email_admin);
}

?>
