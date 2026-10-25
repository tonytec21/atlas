<?php
include(__DIR__ . '/../session_check.php');
checkSession();

/** Mensagem no padrão do sistema (SweetAlert) e volta para a pesquisa de ofícios. */
function atlasOficioAviso($icone, $titulo, $texto)
{
    $j = function ($v) { return json_encode((string) $v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); };
    echo "<!DOCTYPE html><html lang='pt-br'><head><meta charset='UTF-8'><title>Ofícios</title>"
       . "<script src='../script/sweetalert2.js'></script></head><body>"
       . "<script>Swal.fire({icon: " . $j($icone) . ", title: " . $j($titulo) . ", text: " . $j($texto) . ", confirmButtonText: 'OK'})"
       . ".then(function () { window.location.href = 'index.php'; });</script></body></html>";
}

function atlasOficioTemColuna($conn, $coluna)
{
    $r = $conn->query("SHOW COLUMNS FROM oficios LIKE '" . $conn->real_escape_string($coluna) . "'");
    return $r && $r->num_rows > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = $_POST['numero'];
    $tratamento = $_POST['tratamento'];
    $destinatario = $_POST['destinatario'];
    $cargo = $_POST['cargo'];
    $assunto = $_POST['assunto'];
    $corpo = $_POST['corpo'];
    $assinante = $_POST['assinante'];
    $cargo_assinante = $_POST['cargo_assinante'];
    $data = $_POST['data'];
    $dados_complementares = $_POST['dados_complementares'];

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "oficios_db";

    $conn = new mysqli($servername, $username, $password, $dbname); /* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; atlas_aud_conexao($conn); }
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    // Ofício travado ou assinado não pode ser alterado (a tela já bloqueia o botão,
    // mas a gravação também precisa recusar, para quem abrir o endereço direto).
    $chk = $conn->prepare("SELECT status, " . (atlasOficioTemColuna($conn, 'assinado') ? "assinado" : "0 AS assinado") . " FROM oficios WHERE numero = ?");
    $chk->bind_param("s", $numero);
    $chk->execute();
    $atual = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($atual && ((int) $atual['status'] === 1 || (int) $atual['assinado'] === 1)) {
        atlasOficioAviso('error', 'Edição bloqueada', 'Este ofício está travado ou já foi assinado e não pode ser alterado.');
        exit;
    }

    $stmt = $conn->prepare("UPDATE oficios SET tratamento = ?, destinatario = ?, cargo = ?, assunto = ?, corpo = ?, assinante = ?, cargo_assinante = ?, data = ?, dados_complementares = ? WHERE numero = ?");
    $stmt->bind_param("ssssssssss", $tratamento, $destinatario, $cargo, $assunto, $corpo, $assinante, $cargo_assinante, $data, $dados_complementares, $numero);  

    if ($stmt->execute()) {
        atlasOficioAviso('success', 'Ofício atualizado com sucesso!', '');
    } else {
        atlasOficioAviso('error', 'Erro ao atualizar o ofício.', '');
    }

    $stmt->close();
    $conn->close();
}
?>
