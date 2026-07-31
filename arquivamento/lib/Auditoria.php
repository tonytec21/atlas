<?php
/**
 * Atlas · Arquivamento Digital
 * Trilha de auditoria — registro append-only em JSONL, um arquivo por mês.
 *
 * Serve para responder "quem viu, baixou, alterou ou excluiu o quê e quando",
 * que é exatamente o tipo de pergunta que a corregedoria faz numa correição.
 */

function arq_log_dir()
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
    return $dir;
}

/**
 * @param string $acao     criar|editar|excluir|restaurar|expurgar|ver|baixar|compilar|categoria
 * @param string $alvo     ID do arquivamento ou identificador do recurso
 * @param array  $detalhes dados adicionais (nomes de arquivo, campos alterados…)
 */
function arq_auditar($acao, $alvo = '', $detalhes = [])
{
    $registro = [
        'ts'       => date('c'),
        'usuario'  => arq_usuario(),
        'nome'     => arq_usuario_nome(),
        'ip'       => arq_ip(),
        'acao'     => $acao,
        'alvo'     => (string) $alvo,
        'detalhes' => $detalhes,
    ];
    $arquivo = arq_log_dir() . '/auditoria-' . date('Y-m') . '.jsonl';
    @file_put_contents(
        $arquivo,
        json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Lê os últimos eventos de auditoria de um alvo (ou de todos).
 * Percorre no máximo os 3 arquivos mensais mais recentes.
 */
function arq_auditoria_recente($alvo = '', $limite = 50)
{
    $arquivos = glob(arq_log_dir() . '/auditoria-*.jsonl');
    if (!$arquivos) { return []; }
    rsort($arquivos);
    $arquivos = array_slice($arquivos, 0, 3);

    $eventos = [];
    foreach ($arquivos as $arq) {
        $linhas = @file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$linhas) { continue; }
        $linhas = array_reverse($linhas);
        foreach ($linhas as $linha) {
            $ev = json_decode($linha, true);
            if (!is_array($ev)) { continue; }
            if ($alvo !== '' && (!isset($ev['alvo']) || $ev['alvo'] !== (string) $alvo)) { continue; }
            $eventos[] = $ev;
            if (count($eventos) >= $limite) { return $eventos; }
        }
    }
    return $eventos;
}

/**
 * Limitador simples de taxa por usuário+ação, baseado em sessão.
 * Evita que um script automatizado dispare centenas de gravações.
 */
function arq_limite_taxa($chave, $maximo = 60, $janelaSegundos = 60)
{
    $agora = time();
    if (!isset($_SESSION['arq_taxa']) || !is_array($_SESSION['arq_taxa'])) {
        $_SESSION['arq_taxa'] = [];
    }
    $b = isset($_SESSION['arq_taxa'][$chave]) ? $_SESSION['arq_taxa'][$chave] : ['inicio' => $agora, 'n' => 0];
    if (($agora - $b['inicio']) > $janelaSegundos) { $b = ['inicio' => $agora, 'n' => 0]; }
    $b['n']++;
    $_SESSION['arq_taxa'][$chave] = $b;
    return $b['n'] <= $maximo;
}
