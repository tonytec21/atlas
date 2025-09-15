<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    include(__DIR__ . '/db_connection.php');
    date_default_timezone_set('America/Sao_Paulo');

    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error_log.txt');

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
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    function sanitize_text($s) {
        if ($s === null) return '';
        $s = str_replace(array("\r\n","\r","\n"), ' ', trim($s));
        $s = str_replace('&','&amp;', $s);
        $s = str_replace('<','&lt;',  $s);
        $s = str_replace('>','&gt;',  $s);
        $s = str_replace('"','&quot;', $s);
        $s = preg_replace("/(?<!\\p{L})'|'(?!\\p{L})/u", '&#39;', $s);
        return $s;
    }

    session_start();
    $funcionario = $_SESSION['username'] ?? '';

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
    $status           = 'ativo';
    $forcar           = isset($_POST['forcar']);

    $tiposValidos   = array('CIVIL','RELIGIOSO');
    $regimesValidos = array('COMUNHAO_PARCIAL','COMUNHAO_UNIVERSAL','PARTICIPACAO_FINAL_AQUESTOS','SEPARACAO_BENS');

    if (!in_array($tipo_casamento, $tiposValidos, true)) {
        echo json_encode(['status'=>'error','message'=>'Tipo de casamento inválido.']); exit;
    }
    if (!in_array($regime_bens, $regimesValidos, true)) {
        echo json_encode(['status'=>'error','message'=>'Regime de bens inválido.']); exit;
    }

    $data_registro   = parseDateBR($data_registro_br);
    $data_casamento  = parseDateBR($data_casamento_br);
    if (!$data_registro || !$data_casamento) {
        echo json_encode(['status'=>'error','message'=>'Datas inválidas. Use o formato DD/MM/AAAA.']); exit;
    }

    $ts_reg = strtotime($data_registro);
    $ts_cas = strtotime($data_casamento);
    $ts_hoje = strtotime(date('Y-m-d'));

    if ($ts_cas > $ts_reg) {
        echo json_encode(['status'=>'error','message'=>'A data do casamento deve ser menor ou igual à data do registro.']); exit;
    }
    if ($ts_reg > $ts_hoje) {
        echo json_encode(['status'=>'error','message'=>'A data do registro deve ser menor ou igual à data atual.']); exit;
    }

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

    if (!$forcar) {
        $stmt = $conn->prepare("SELECT id FROM indexador_casamento WHERE termo=? AND livro=? AND folha=? AND data_registro=? AND status='ativo'");
        $stmt->bind_param("ssss", $termo, $livro, $folha, $data_registro);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status'=>'duplicate','message'=>'Registro duplicado.']); $stmt->close(); $conn->close(); exit;
        }
        $stmt->close();
    }

    $sql = "INSERT INTO indexador_casamento
            (termo, livro, folha, tipo_casamento, data_registro,
            conjuge1_nome, conjuge1_nome_casado, conjuge1_sexo,
            conjuge2_nome, conjuge2_nome_casado, conjuge2_sexo,
            regime_bens, data_casamento, funcionario, status)
            VALUES (?,?,?,?,?,
                    ?,?,?, ?,?,?,
                    ?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssssss",
        $termo, $livro, $folha, $tipo_casamento, $data_registro,
        $conjuge1_nome, $conjuge1_nome_casado, $conjuge1_sexo,
        $conjuge2_nome, $conjuge2_nome_casado, $conjuge2_sexo,
        $regime_bens, $data_casamento, $funcionario, $status
    );

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;

        $cns = null;
        $rs = $conn->query("SELECT cns FROM cadastro_serventia LIMIT 1");
        if ($rs && $rs->num_rows>0) {
            $row = $rs->fetch_assoc();
            $cns = str_pad($row['cns'], 6, "0", STR_PAD_LEFT);
        } else {
            error_log("CNS não encontrado na tabela cadastro_serventia.");
        }

        $matriculaFinal = null;
        if ($cns) {
            $livroFormatado  = str_pad($livro, 5, "0", STR_PAD_LEFT);
            $folhaFormatada  = str_pad($folha, 3, "0", STR_PAD_LEFT);
            $termoFormatado  = str_pad($termo, 7, "0", STR_PAD_LEFT);
            $anoRegistro     = substr($data_registro, 0, 4);
            $acervo          = '01';
            $fixo55          = '55';
            $tipoLivro       = ($tipo_casamento === 'CIVIL') ? '2' : '3';

            $matriculaBase = $cns . $acervo . $fixo55 . $anoRegistro . $tipoLivro . $livroFormatado . $folhaFormatada . $termoFormatado;
            $dv = calcularDigitoVerificador($matriculaBase);
            $matriculaFinal = $matriculaBase . $dv;

            $conn->query("UPDATE indexador_casamento SET matricula='{$conn->real_escape_string($matriculaFinal)}' WHERE id={$last_id}");
        }

        if (!empty($_POST['arquivo_pdf_paths']) && is_array($_POST['arquivo_pdf_paths'])) {
            foreach ($_POST['arquivo_pdf_paths'] as $tmp) {
                $dir = __DIR__ . '/anexos/' . $last_id . '/';
                if (!file_exists($dir)) { mkdir($dir, 0777, true); }
                $name = basename($tmp);
                $final = $dir . $name;

                if (@rename($tmp, $final)) {
                    $relPath = 'anexos/' . $last_id . '/' . $name;
                    $st = $conn->prepare("INSERT INTO indexador_casamento_anexos (id_casamento, caminho_anexo, funcionario, status) VALUES (?,?,?,?)");
                    $st->bind_param("isss", $last_id, $relPath, $funcionario, $status);
                    $st->execute(); $st->close();
                } else {
                    if (@copy($tmp, $final)) {
                        @unlink($tmp);
                        $relPath = 'anexos/' . $last_id . '/' . $name;
                        $st = $conn->prepare("INSERT INTO indexador_casamento_anexos (id_casamento, caminho_anexo, funcionario, status) VALUES (?,?,?,?)");
                        $st->bind_param("isss", $last_id, $relPath, $funcionario, $status);
                        $st->execute(); $st->close();
                    } else {
                        error_log("Falha ao mover anexo temporário: $tmp");
                    }
                }
            }
        }

        echo json_encode(['status'=>'success','message'=>'Registro salvo com sucesso!','matricula'=>$matriculaFinal]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Erro ao salvar: '.$stmt->error]);
    }

    $stmt->close(); $conn->close();
}
