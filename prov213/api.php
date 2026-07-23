<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Endpoints AJAX. Resposta padrão: {success, data, message}
 */
require_once __DIR__ . '/p213_lib.php';
header('Content-Type: application/json; charset=utf-8');

function out($success, $data = null, $message = '') {
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message],
        JSON_UNESCAPED_UNICODE);
    exit;
}

$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');
$conn = p213_db();

try {
    switch ($acao) {

    // -----------------------------------------------------------------
    case 'salvar_resposta':
        $cod = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
        $st  = isset($_POST['status']) ? trim($_POST['status']) : '';
        if (!in_array($st, p213_status_validos(), true)) out(false, null, 'Status inválido.');

        $etapa = null;
        foreach (p213_catalogo() as $it) if ($it['cod'] === $cod) { $etapa = $it['etapa']; break; }
        if ($etapa === null) out(false, null, 'Requisito inexistente: ' . $cod);

        $ev   = isset($_POST['evidencia'])   ? trim($_POST['evidencia'])   : null;
        $ob   = isset($_POST['observacao'])  ? trim($_POST['observacao'])  : null;
        $resp = isset($_POST['responsavel']) ? trim($_POST['responsavel']) : null;
        $dt   = isset($_POST['data_conclusao']) && $_POST['data_conclusao'] !== ''
                ? $_POST['data_conclusao'] : null;
        $user = p213_usuario();

        // "não aplicável" exige justificativa (art. 4º, §5º — equivalência demonstrável)
        if ($st === 'nao_aplicavel' && ($ob === null || $ob === '')) {
            out(false, null, 'Marcar como não aplicável exige justificativa técnica.');
        }

        $stmt = $conn->prepare(
            "INSERT INTO p213_respostas
               (codigo, etapa, status, evidencia, observacao, responsavel, data_conclusao, atualizado_por)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               status=VALUES(status), evidencia=VALUES(evidencia), observacao=VALUES(observacao),
               responsavel=VALUES(responsavel), data_conclusao=VALUES(data_conclusao),
               atualizado_por=VALUES(atualizado_por)");
        $stmt->bind_param('sissssss', $cod, $etapa, $st, $ev, $ob, $resp, $dt, $user);
        $stmt->execute();
        $stmt->close();

        p213_log('resposta', $cod . ' => ' . $st);

        $cfg = p213_config();
        out(true, p213_score($cfg['classe']), 'Resposta registrada.');

    // -----------------------------------------------------------------
    case 'score':
        $cfg = p213_config();
        out(true, ['score' => p213_score($cfg['classe']), 'classe' => $cfg['classe'],
                   'subclasse' => $cfg['subclasse']]);

    // -----------------------------------------------------------------
    case 'salvar_config':
        $campos = ['serventia','cns','cnpj','endereco','municipio_uf','titular','titular_qualif',
                   'responsavel_tec','encarregado_dpo','dpo_contato','corregedoria','modelo_solucao',
                   'subclasse_manual','gemini_api_key','gemini_modelo'];
        $sets = []; $vals = []; $tipos = '';
        foreach ($campos as $c) {
            if (isset($_POST[$c])) { $sets[] = "$c=?"; $vals[] = trim($_POST[$c]); $tipos .= 's'; }
        }
        $monetarios = ['receita_semestral','rec_emolumentos','rec_outras','rec_atos_gratuitos',
                       'rec_renda_minima','ded_terceiros','ded_repasses'];
        foreach ($monetarios as $mc) {
            if (!isset($_POST[$mc])) continue;
            // aceita "1.234.567,89" (pt-BR) e "1234567.89"
            $v = str_replace(['.', ' '], '', (string)$_POST[$mc]);
            $v = str_replace(',', '.', $v);
            $sets[] = $mc . '=?';
            $vals[] = (float)$v;
            $tipos .= 'd';
        }
        if (isset($_POST['fator_atualizacao'])) {
            $sets[] = 'fator_atualizacao=?'; $vals[] = (float)$_POST['fator_atualizacao']; $tipos .= 'd';
        }
        if (array_key_exists('classe_manual', $_POST)) {
            $cm = $_POST['classe_manual'] === '' ? null : (int)$_POST['classe_manual'];
            $sets[] = 'classe_manual=?'; $vals[] = $cm; $tipos .= 'i';
        }
        if (!$sets) out(false, null, 'Nada a salvar.');

        $sql = "UPDATE p213_config SET " . implode(',', $sets) . " WHERE id=1";
        $stmt = $conn->prepare($sql);
        $refs = [$tipos];
        foreach ($vals as $k => $v) $refs[] = &$vals[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $stmt->close();
        p213_log('config', 'atualizada');

        $cfg = p213_config();
        out(true, ['classe' => $cfg['classe'], 'subclasse' => $cfg['subclasse'],
                   'parametros' => p213_parametros($cfg['classe'])], 'Configuração salva.');

    // -----------------------------------------------------------------
    case 'simular_classe':
        $r = (float)str_replace([',', ' '], ['.', ''], isset($_POST['receita']) ? $_POST['receita'] : '0');
        $f = isset($_POST['fator_atualizacao']) ? (float)$_POST['fator_atualizacao'] : 1.0;
        $e = p213_enquadrar($r, $f);
        out(true, ['enquadramento' => $e, 'parametros' => p213_parametros($e['classe']),
                   'prazos' => array_map(function ($v) {
                        return $v instanceof DateTime ? $v->format('d/m/Y') : $v;
                   }, p213_prazos($e['classe']))]);

    // -----------------------------------------------------------------
    case 'ativo_salvar':
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $f = [
            'categoria'     => isset($_POST['categoria']) ? $_POST['categoria'] : 'hardware',
            'nome'          => isset($_POST['nome']) ? trim($_POST['nome']) : '',
            'identificacao' => isset($_POST['identificacao']) ? trim($_POST['identificacao']) : '',
            'fabricante'    => isset($_POST['fabricante']) ? trim($_POST['fabricante']) : '',
            'versao'        => isset($_POST['versao']) ? trim($_POST['versao']) : '',
            'criticidade'   => isset($_POST['criticidade']) ? $_POST['criticidade'] : 'media',
            'responsavel'   => isset($_POST['responsavel']) ? trim($_POST['responsavel']) : '',
            'fornecedor'    => isset($_POST['fornecedor']) ? trim($_POST['fornecedor']) : '',
            'contrato'      => isset($_POST['contrato']) ? trim($_POST['contrato']) : '',
            'localizacao'   => isset($_POST['localizacao']) ? trim($_POST['localizacao']) : '',
            'observacao'    => isset($_POST['observacao']) ? trim($_POST['observacao']) : '',
        ];
        if ($f['nome'] === '') out(false, null, 'Informe o nome do ativo.');
        $suporte = isset($_POST['suporte_ativo']) ? (int)$_POST['suporte_ativo'] : 1;
        $dpess   = isset($_POST['dados_pessoais']) ? (int)$_POST['dados_pessoais'] : 0;
        $eol     = (isset($_POST['eol']) && $_POST['eol'] !== '') ? $_POST['eol'] : null;
        $val     = (isset($_POST['validade']) && $_POST['validade'] !== '') ? $_POST['validade'] : null;

        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE p213_ativos SET categoria=?,nome=?,identificacao=?,fabricante=?,versao=?,
                 criticidade=?,suporte_ativo=?,eol=?,responsavel=?,fornecedor=?,contrato=?,
                 validade=?,localizacao=?,dados_pessoais=?,observacao=? WHERE id=?");
            $stmt->bind_param('ssssssissssssisi',
                $f['categoria'],$f['nome'],$f['identificacao'],$f['fabricante'],$f['versao'],
                $f['criticidade'],$suporte,$eol,$f['responsavel'],$f['fornecedor'],$f['contrato'],
                $val,$f['localizacao'],$dpess,$f['observacao'],$id);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO p213_ativos (categoria,nome,identificacao,fabricante,versao,criticidade,
                 suporte_ativo,eol,responsavel,fornecedor,contrato,validade,localizacao,dados_pessoais,observacao)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssssissssssis',
                $f['categoria'],$f['nome'],$f['identificacao'],$f['fabricante'],$f['versao'],
                $f['criticidade'],$suporte,$eol,$f['responsavel'],$f['fornecedor'],$f['contrato'],
                $val,$f['localizacao'],$dpess,$f['observacao']);
        }
        $stmt->execute();
        $novo = $id > 0 ? $id : $conn->insert_id;
        $stmt->close();
        p213_log('ativo', ($id ? 'editado #' : 'criado #') . $novo);
        out(true, ['id' => $novo], 'Ativo salvo.');

    // -----------------------------------------------------------------
    case 'ativo_excluir':
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM p213_ativos WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        p213_log('ativo', 'excluído #' . $id);
        out(true, null, 'Ativo excluído.');

    // -----------------------------------------------------------------
    case 'declarar_etapa':
        $etapa = (int)$_POST['etapa'];
        $cfg   = p213_config();
        $score = p213_score($cfg['classe']);
        $d     = $score['etapas'][$etapa];

        if (!$d['liberada'])
            out(false, null, 'A etapa anterior não está integralmente cumprida. '
                . 'Anexo IV, Disposições gerais, I — as etapas são sucessivas e cumulativas.');
        if (!$d['apto_declarar'])
            out(false, null, 'Ainda há requisitos não conformes, parciais ou não avaliados. '
                . 'Anexo IV, Disposições gerais, II — vedada declaração parcial, proporcional ou condicionada.');

        $decl = isset($_POST['declarante']) ? trim($_POST['declarante']) : $cfg['titular'];
        $qual = isset($_POST['qualificacao']) ? trim($_POST['qualificacao']) : $cfg['titular_qualif'];
        $prot = isset($_POST['protocolo_ja']) ? trim($_POST['protocolo_ja']) : '';
        $data = (isset($_POST['data_registro']) && $_POST['data_registro'] !== '')
                ? $_POST['data_registro'] : date('Y-m-d');
        $pct  = $d['pct'];

        $stmt = $conn->prepare(
            "INSERT INTO p213_declaracoes (etapa, declarante, qualificacao, protocolo_ja, data_registro, pct_no_momento)
             VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('issssd', $etapa, $decl, $qual, $prot, $data, $pct);
        $stmt->execute();
        $stmt->close();
        p213_log('declaracao', 'Etapa ' . $etapa);
        out(true, null, 'Declaração da Etapa ' . $etapa . ' registrada.');


    // ----------------------------------------------------------------- EVIDÊNCIAS
    case 'evid_upload':
        $cod = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
        $it  = p213_contexto_requisito($cod);
        if (!$it) out(false, null, 'Requisito inexistente: ' . $cod);

        $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        if ($titulo === '') out(false, null, 'Informe o título da evidência.');

        $tipo  = isset($_POST['tipo']) ? trim($_POST['tipo']) : 'documento';
        $desc  = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
        $resp  = isset($_POST['responsavel']) ? trim($_POST['responsavel']) : null;
        $data  = (isset($_POST['data_evidencia']) && $_POST['data_evidencia'] !== '') ? $_POST['data_evidencia'] : null;

        $nome = $path = $mime = $hash = null; $tam = null;

        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['arquivo'];
            if ($f['error'] !== UPLOAD_ERR_OK) out(false, null, 'Falha no upload (código ' . $f['error'] . ').');
            if ($f['size'] > 30 * 1024 * 1024) out(false, null, 'Arquivo acima de 30 MB.');

            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $permitidas = ['pdf','png','jpg','jpeg','gif','webp','txt','log','csv','xml','json',
                           'doc','docx','xls','xlsx','ppt','pptx','zip','p7s','eml','msg'];
            if (!in_array($ext, $permitidas, true))
                out(false, null, 'Extensão não permitida: .' . $ext);

            $dir = p213_evid_dir() . '/etapa' . (int)$it['etapa'];
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (!is_writable($dir)) out(false, null, 'Repositório de evidências sem permissão de escrita.');

            $slug = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($f['name'], PATHINFO_FILENAME));
            $slug = trim(strtolower(substr($slug, 0, 60)), '-');
            $rel  = 'etapa' . (int)$it['etapa'] . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4))
                  . '-' . $slug . '.' . $ext;
            $abs  = p213_evid_dir() . '/' . $rel;

            if (!move_uploaded_file($f['tmp_name'], $abs)) out(false, null, 'Não foi possível gravar o arquivo.');
            @chmod($abs, 0644);

            $nome = $f['name'];
            $path = $rel;
            $tam  = (int)$f['size'];
            $hash = hash_file('sha256', $abs);
            $mime = function_exists('mime_content_type') ? @mime_content_type($abs) : $f['type'];
        }

        $etapa = (int)$it['etapa'];
        $user  = p213_usuario();
        $stmt = $conn->prepare(
            "INSERT INTO p213_evidencias
              (codigo, etapa, titulo, tipo, descricao, arquivo_nome, arquivo_path, mime, tamanho,
               sha256, data_evidencia, responsavel, criado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sissssssissss', $cod, $etapa, $titulo, $tipo, $desc, $nome, $path, $mime,
                          $tam, $hash, $data, $resp, $user);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();

        p213_log('evidencia', 'add #' . $id . ' ' . $cod);
        out(true, ['id' => $id, 'sha256' => $hash], 'Evidência registrada.');

    // -----------------------------------------------------------------
    case 'evid_excluir':
        $id = (int)$_POST['id'];
        $st = $conn->prepare("SELECT arquivo_path FROM p213_evidencias WHERE id=?");
        $st->bind_param('i', $id); $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        if ($r && $r['arquivo_path']) @unlink(p213_evid_dir() . '/' . $r['arquivo_path']);

        $st = $conn->prepare("DELETE FROM p213_evidencias WHERE id=?");
        $st->bind_param('i', $id); $st->execute(); $st->close();
        p213_log('evidencia', 'del #' . $id);
        out(true, null, 'Evidência excluída.');

    // -----------------------------------------------------------------
    case 'evid_verificar':
        $etapa = isset($_POST['etapa']) && $_POST['etapa'] !== '' ? (int)$_POST['etapa'] : null;
        $r = p213_evid_verificar($etapa);
        p213_log('evidencia', 'verificacao de integridade');
        out(true, $r, count($r['problemas']) === 0
            ? 'Todos os ' . $r['integros'] . ' arquivo(s) estão íntegros.'
            : count($r['problemas']) . ' problema(s) encontrado(s).');

    // ----------------------------------------------------------------- IA
    case 'ia_evidencias':
        $cod = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
        $it  = p213_contexto_requisito($cod);
        if (!$it) out(false, null, 'Requisito inexistente.');
        $cfg = p213_config();

        $prompt = "Você é auditor de conformidade de cartórios extrajudiciais brasileiros, especialista no "
            . "Provimento CN-CNJ n. 213/2026 (padrões mínimos de TIC dos serviços notariais e de registro).\n\n"
            . "SERVENTIA: " . ($cfg['serventia'] ?: 'não informada') . ", Classe " . $cfg['classe']
            . ", modelo de solução: " . $cfg['modelo_solucao'] . ".\n"
            . "REQUISITO " . $it['cod'] . " (Etapa " . $it['etapa'] . "): " . $it['pergunta'] . "\n"
            . "BASE NORMATIVA: " . $it['base'] . "\n"
            . "ORIENTAÇÃO GERAL: " . $it['sugestao'] . "\n\n"
            . "Liste de 3 a 6 evidências documentais CONCRETAS que comprovem objetivamente o cumprimento "
            . "deste requisito perante a fiscalização correicional. Cada evidência deve ser um artefato "
            . "verificável (documento, relatório, log, captura de tela, contrato, ata), não uma ação genérica.\n"
            . "Responda APENAS com JSON no formato: "
            . '{"evidencias":[{"titulo":"...","tipo":"documento|relatorio|log|captura|contrato|ata|foto",'
            . '"como_obter":"instrução prática em uma frase"}]}';

        list($ok, $txt) = p213_gemini($prompt, true, 1600);
        if (!$ok) out(false, null, $txt);
        $d = p213_gemini_json($txt);
        if (!$d || !isset($d['evidencias'])) out(false, null, 'Não foi possível interpretar a resposta da IA.');
        p213_log('ia', 'sugerir evidencias ' . $cod);
        out(true, $d['evidencias'], 'Sugestões geradas.');

    // -----------------------------------------------------------------
    case 'ia_descricao':
        $cod = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
        $it  = p213_contexto_requisito($cod);
        if (!$it) out(false, null, 'Requisito inexistente.');
        $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        $notas  = isset($_POST['notas']) ? trim($_POST['notas']) : '';
        if ($titulo === '' && $notas === '') out(false, null, 'Informe ao menos o título ou anotações.');
        $cfg = p213_config();

        $prompt = "Você redige o dossiê técnico de conformidade ao Provimento CN-CNJ n. 213/2026 de uma "
            . "serventia extrajudicial brasileira.\n\n"
            . "SERVENTIA: " . ($cfg['serventia'] ?: 'não informada') . " (Classe " . $cfg['classe'] . ")\n"
            . "REQUISITO " . $it['cod'] . ": " . $it['pergunta'] . "\n"
            . "BASE NORMATIVA: " . $it['base'] . "\n"
            . "EVIDÊNCIA: " . $titulo . "\n"
            . "ANOTAÇÕES DO OFICIAL: " . ($notas !== '' ? $notas : '(nenhuma)') . "\n\n"
            . "Redija a descrição formal desta evidência para o dossiê técnico, em português brasileiro, "
            . "de 3 a 5 frases, em terceira pessoa, tom técnico-jurídico sóbrio. Explicite o que o artefato "
            . "demonstra e por que satisfaz o requisito, citando o dispositivo normativo. "
            . "NÃO invente fatos, datas, números de contrato ou nomes que não estejam nas anotações. "
            . "Se faltar informação essencial, escreva a lacuna entre colchetes, como [inserir data]. "
            . "Responda APENAS com JSON: {\"descricao\":\"...\"}";

        list($ok, $txt) = p213_gemini($prompt, true, 1200);
        if (!$ok) out(false, null, $txt);
        $d = p213_gemini_json($txt);
        if (!$d || !isset($d['descricao'])) out(false, null, 'Não foi possível interpretar a resposta da IA.');
        p213_log('ia', 'descricao ' . $cod);
        out(true, ['descricao' => $d['descricao']], 'Texto gerado.');

    // -----------------------------------------------------------------
    case 'ia_lacunas':
        $etapa = (int)$_POST['etapa'];
        $cfg = p213_config();
        $resp = p213_respostas();
        $linhas = [];
        foreach (p213_catalogo_por_classe((int)$cfg['classe']) as $it) {
            if ($it['etapa'] != $etapa) continue;
            $st = isset($resp[$it['cod']]) ? $resp[$it['cod']]['status'] : 'nao_avaliado';
            if ($st === 'conforme' || $st === 'nao_aplicavel') continue;
            $linhas[] = '- ' . $it['cod'] . ' [' . p213_status_label($st) . '] ' . $it['pergunta'];
        }
        if (!$linhas) out(false, null, 'Esta etapa não possui pendências.');

        $prompt = "Você assessora o titular de uma serventia extrajudicial brasileira (Classe "
            . $cfg['classe'] . ") na adequação ao Provimento CN-CNJ n. 213/2026.\n\n"
            . "Pendências da Etapa " . $etapa . ":\n" . implode("\n", $linhas) . "\n\n"
            . "Produza um plano de ação enxuto e executável, ordenado por dependência técnica e não apenas "
            . "por numeração. Para cada passo, indique a providência, o responsável típico (oficial, "
            . "responsável técnico, fornecedor, profissional habilitado) e um prazo relativo em dias. "
            . "Agrupe as providências que podem ser feitas em conjunto. Seja objetivo e não repita o texto "
            . "do requisito.\n"
            . "Responda APENAS com JSON: "
            . '{"passos":[{"ordem":1,"acao":"...","responsavel":"...","prazo_dias":15,"itens":["1.1.I"]}]}';

        list($ok, $txt) = p213_gemini($prompt, true, 2400);
        if (!$ok) out(false, null, $txt);
        $d = p213_gemini_json($txt);
        if (!$d || !isset($d['passos'])) out(false, null, 'Não foi possível interpretar a resposta da IA.');
        p213_log('ia', 'lacunas etapa ' . $etapa);
        out(true, $d['passos'], 'Plano gerado.');

    // -----------------------------------------------------------------
    default:
        out(false, null, 'Ação não reconhecida.');
    }
} catch (Throwable $e) {
    out(false, null, 'Erro: ' . $e->getMessage());
}
