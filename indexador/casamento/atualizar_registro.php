<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include(__DIR__ . '/db_connection.php');
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('America/Sao_Paulo');

    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error_log.txt');

    // ===== Dígitos verificadores da matrícula =====
    function calcularDigitoVerificador($matriculaBase) {
        $multiplicadorFase1 = 32; $soma = 0;
        for ($i=0; $i<30; $i++) { $multiplicadorFase1--; $soma += intval($matriculaBase[$i]) * $multiplicadorFase1; }
        $dig1 = ($soma * 10) % 11; $dig1 = ($dig1 == 10) ? 1 : $dig1;

        $multiplicadorFase2 = 33; $soma2 = 0;
        for ($j=0; $j<30; $j++) { $multiplicadorFase2--; $soma2 += intval($matriculaBase[$j]) * $multiplicadorFase2; }
        $soma2 += $dig1 * 2; $dig2 = ($soma2 * 10) % 11; $dig2 = ($dig2 == 10) ? 1 : $dig2;
        return $dig1 . $dig2;
    }

    function parseDateBR($str) {
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $str, $m)) return null;
        $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3];
        if (!checkdate($mo,$d,$y)) return null;
        return sprintf('%04d-%02d-%02d',$y,$mo,$d);
    }

    // Sanitização: &,<,>,", apóstrofo só entre letras
    function sanitize_text($s){
        if ($s===null) return '';
        $s=str_replace(array("\r\n","\r","\n"),' ',trim($s));
        $s=str_replace('&','&amp;',$s);
        $s=str_replace('<','&lt;',$s);
        $s=str_replace('>','&gt;',$s);
        $s=str_replace('"','&quot;',$s);
        $s=preg_replace("/(?<!\\p{L})'|'(?!\\p{L})/u",'&#39;',$s);
        return $s;
    }

    session_start();
    $funcionario = $_SESSION['username'] ?? '';

    $id               = intval($_POST['id'] ?? 0);
    $termo            = $_POST['termo'] ?? '';
    $livro            = $_POST['livro'] ?? '';
    $folha            = $_POST['folha'] ?? '';
    $tipo_casamento   = $_POST['tipo_casamento'] ?? '';
    $data_registro_br = $_POST['data_registro'] ?? '';
    $conjuge1_nome_in = $_POST['conjuge1_nome'] ?? '';
    $conjuge1_nome_casado_in  = $_POST['conjuge1_nome_casado'] ?? '';
    $conjuge1_sexo    = $_POST['conjuge1_sexo'] ?? '';
    $conjuge2_nome_in = $_POST['conjuge2_nome'] ?? '';
    $conjuge2_nome_casado_in  = $_POST['conjuge2_nome_casado'] ?? '';
    $conjuge2_sexo    = $_POST['conjuge2_sexo'] ?? '';
    $regime_bens      = $_POST['regime_bens'] ?? '';
    $data_casamento_br= $_POST['data_casamento'] ?? '';

    if ($id<=0) { echo json_encode(['status'=>'error','message'=>'ID inválido.']); exit; }

    $tiposValidos   = ['CIVIL','RELIGIOSO'];
    $regimesValidos = ['COMUNHAO_PARCIAL','COMUNHAO_UNIVERSAL','PARTICIPACAO_FINAL_AQUESTOS','SEPARACAO_BENS'];
    if (!in_array($tipo_casamento,$tiposValidos,true))   { echo json_encode(['status'=>'error','message'=>'Tipo inválido.']); exit; }
    if (!in_array($regime_bens,$regimesValidos,true))    { echo json_encode(['status'=>'error','message'=>'Regime inválido.']); exit; }

    $data_registro  = parseDateBR($data_registro_br);
    $data_casamento = parseDateBR($data_casamento_br);
    if (!$data_registro || !$data_casamento) {
        echo json_encode(['status'=>'error','message'=>'Datas inválidas. Use DD/MM/AAAA.']); exit;
    }

    $ts_reg=strtotime($data_registro); $ts_cas=strtotime($data_casamento); $ts_hoje=strtotime(date('Y-m-d'));
    if ($ts_cas>$ts_reg) { echo json_encode(['status'=>'error','message'=>'Data do casamento deve ser <= data do registro.']); exit; }
    if ($ts_reg>$ts_hoje) { echo json_encode(['status'=>'error','message'=>'Data do registro deve ser <= hoje.']); exit; }

    $conjuge1_nome = mb_strtoupper($conjuge1_nome_in, 'UTF-8');
    $conjuge2_nome = mb_strtoupper($conjuge2_nome_in, 'UTF-8');
    $conjuge1_nome = sanitize_text($conjuge1_nome);
    $conjuge2_nome = sanitize_text($conjuge2_nome);

    $conjuge1_nome_casado = null;
    if (strlen(trim($conjuge1_nome_casado_in)) > 0) {
        $conjuge1_nome_casado = sanitize_text(mb_strtoupper($conjuge1_nome_casado_in, 'UTF-8'));
    }
    $conjuge2_nome_casado = null;
    if (strlen(trim($conjuge2_nome_casado_in)) > 0) {
        $conjuge2_nome_casado = sanitize_text(mb_strtoupper($conjuge2_nome_casado_in, 'UTF-8'));
    }

    // Duplicidade (exclui o próprio ID)
    $stmt = $conn->prepare("SELECT id FROM indexador_casamento WHERE termo=? AND livro=? AND folha=? AND data_registro=? AND status='ativo' AND id<>?");
    $stmt->bind_param("ssssi", $termo, $livro, $folha, $data_registro, $id);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows>0) { echo json_encode(['status'=>'duplicate','message'=>'Registro duplicado.']); $stmt->close(); $conn->close(); exit; }
    $stmt->close();

    // Atualizar
    $sql="UPDATE indexador_casamento SET
            termo=?, livro=?, folha=?, tipo_casamento=?, data_registro=?,
            conjuge1_nome=?, conjuge1_nome_casado=?, conjuge1_sexo=?,
            conjuge2_nome=?, conjuge2_nome_casado=?, conjuge2_sexo=?,
            regime_bens=?, data_casamento=?, funcionario=?
        WHERE id=?";
    $stmt=$conn->prepare($sql);
    $stmt->bind_param("ssssssssssssssi",
        $termo, $livro, $folha, $tipo_casamento, $data_registro,
        $conjuge1_nome, $conjuge1_nome_casado, $conjuge1_sexo,
        $conjuge2_nome, $conjuge2_nome_casado, $conjuge2_sexo,
        $regime_bens, $data_casamento, $funcionario, $id
    );

    if ($stmt->execute()) {
        // Recalcular matrícula (sempre que salvar)
        $matriculaFinal = null;
        $rs = $conn->query("SELECT cns FROM cadastro_serventia LIMIT 1");
        if ($rs && $rs->num_rows>0) {
            $row = $rs->fetch_assoc();
            $cns = str_pad($row['cns'], 6, "0", STR_PAD_LEFT);

            $livroFormatado  = str_pad($livro, 5, "0", STR_PAD_LEFT);
            $folhaFormatada  = str_pad($folha, 3, "0", STR_PAD_LEFT);
            $termoFormatado  = str_pad($termo, 7, "0", STR_PAD_LEFT);
            $anoRegistro     = substr($data_registro, 0, 4);
            $acervo          = '01';
            $fixo55          = '55';
            $tipoLivro       = ($tipo_casamento==='CIVIL') ? '2' : '3';

            $base = $cns.$acervo.$fixo55.$anoRegistro.$tipoLivro.$livroFormatado.$folhaFormatada.$termoFormatado;
            $dv   = calcularDigitoVerificador($base);
            $matriculaFinal = $base.$dv;

            $conn->query("UPDATE indexador_casamento SET matricula='{$conn->real_escape_string($matriculaFinal)}' WHERE id={$id}");
        } else {
            error_log("CNS não encontrado para recálculo da matrícula (edição).");
        }

        echo json_encode(['status'=>'success','matricula'=>$matriculaFinal]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Erro ao atualizar: '.$stmt->error]);
    }

    $stmt->close(); $conn->close();
}
