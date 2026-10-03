<?php
/**
 * =====================================================================
 * auditoria_os_lib.php — Auditoria das Ordens de Serviço (módulo O.S.)
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-10-03-auditoria
 *
 * O QUE FAZ
 * ---------
 * Registra, para cada alteração feita numa O.S., o RETRATO COMPLETO da O.S.
 * antes e depois da operação: dados da O.S., atos/itens, pagamentos,
 * comprovantes de pagamento, anexos, devoluções, repasses, liquidações,
 * entrega, NFS-e e documentos assinados.
 *
 * COMO FUNCIONA
 * -------------
 * Cada endpoint que altera a O.S. chama, logo no início:
 *
 *     require_once __DIR__ . '/auditoria_os_lib.php';
 *     osaud_monitorar('pagamento_removido', ['pagamento_id' => $_POST['pagamento_id'] ?? 0]);
 *
 * A função tira o retrato "antes" e agenda (register_shutdown_function) o
 * retrato "depois" para o fim da requisição. Só se houver diferença real
 * entre os dois é que o evento é gravado — operações recusadas, com erro
 * ou desfeitas por rollback não geram registro.
 *
 * A lógica dos endpoints NÃO foi alterada: a auditoria apenas observa.
 * Qualquer falha da auditoria é engolida (vai para o error_log) e nunca
 * impede a operação do usuário.
 *
 * INTEGRIDADE
 * -----------
 * Os registros de cada O.S. formam uma cadeia de hash (SHA-256): cada
 * evento guarda o hash do anterior. Uma alteração manual no banco quebra a
 * cadeia e a página de auditoria acusa.
 * =====================================================================
 */

require_once __DIR__ . '/atlas_tempo.php';

if (!defined('OSAUD_VERSAO')) {
    define('OSAUD_VERSAO', '1.0.0');
    define('OSAUD_CAMPO_MAX', 4000);       // valores maiores que isto são resumidos no retrato
}

if (!function_exists('osaud_pdo')) {

/* =====================================================================
   CONEXÃO E ESQUEMA
   ===================================================================== */

/** Conexão própria (PDO), independente da conexão do endpoint. */
function osaud_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!function_exists('getDatabaseConnection')) {
        require_once __DIR__ . '/db_connection.php';
    }
    $pdo = getDatabaseConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function osaud_agora(): string
{
    if (function_exists('atlas_boot_tempo')) {
        atlas_boot_tempo();
    }
    return date('Y-m-d H:i:s');
}

function osaud_tabela_existe(PDO $pdo, string $tabela): bool
{
    static $cache = [];
    if (array_key_exists($tabela, $cache)) {
        return $cache[$tabela];
    }
    try {
        /* information_schema em vez de SHOW TABLES: SHOW não aceita
           parâmetro em prepared statement nativo (ATTR_EMULATE_PREPARES=false). */
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$tabela]);
        $existe = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        /* Não deu para verificar: tenta ler a tabela direto. */
        try {
            $pdo->query('SELECT 1 FROM `' . str_replace('`', '', $tabela) . '` LIMIT 0');
            $existe = true;
        } catch (Throwable $e2) {
            $existe = false;
        }
    }
    /* Só memoriza o "existe": uma tabela pode ser criada no meio da requisição. */
    if ($existe) {
        $cache[$tabela] = true;
    }
    return $existe;
}

/** Cria as tabelas da auditoria (idempotente). */
function osaud_migrar(?PDO $pdo = null): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $pdo = $pdo ?: osaud_pdo();

    if (!osaud_tabela_existe($pdo, 'os_auditoria')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS os_auditoria (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            os_id         INT NOT NULL,
            acao          VARCHAR(60)  NOT NULL,
            grupo         VARCHAR(40)  NULL,
            usuario       VARCHAR(160) NULL,
            ip            VARCHAR(45)  NULL,
            user_agent    VARCHAR(255) NULL,
            origem        VARCHAR(160) NULL,
            motivo        VARCHAR(1000) NULL,
            secoes        VARCHAR(255) NULL,
            tem_exclusao  TINYINT(1)   NOT NULL DEFAULT 0,
            resumo        TEXT         NULL,
            diff          MEDIUMTEXT   NULL,
            antes         LONGBLOB     NULL,
            depois        LONGBLOB     NULL,
            hash_anterior CHAR(64)     NULL,
            hash          CHAR(64)     NOT NULL,
            criado_em     DATETIME     NOT NULL,
            INDEX idx_os (os_id, id),
            INDEX idx_data (criado_em),
            INDEX idx_usuario (usuario),
            INDEX idx_grupo (grupo),
            INDEX idx_acao (acao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!osaud_tabela_existe($pdo, 'os_auditoria_meta')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS os_auditoria_meta (
            chave VARCHAR(60) NOT NULL PRIMARY KEY,
            valor VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $st = $pdo->prepare("INSERT IGNORE INTO os_auditoria_meta (chave, valor) VALUES ('instalado_em', ?)");
        $st->execute([osaud_agora()]);
    }

    $feito = true;
}

function osaud_instalado_em(PDO $pdo): ?string
{
    try {
        $v = $pdo->query("SELECT valor FROM os_auditoria_meta WHERE chave = 'instalado_em'")->fetchColumn();
        return $v ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/* =====================================================================
   CATÁLOGO: seções do retrato, rótulos e ações
   ===================================================================== */

/**
 * Seções (coleções) do retrato da O.S.
 * tabela / coluna de vínculo com a O.S. / ordenação.
 */
function osaud_secoes(): array
{
    return [
        'itens'        => ['rotulo' => 'Atos / itens',         'icone' => 'fa-list',          'tabela' => 'ordens_de_servico_itens', 'fk' => 'ordem_servico_id',    'ordem' => 'ordem_exibicao ASC, id ASC'],
        'pagamentos'   => ['rotulo' => 'Pagamentos',           'icone' => 'fa-money',         'tabela' => 'pagamento_os',            'fk' => 'ordem_de_servico_id', 'ordem' => 'id ASC'],
        'comprovantes' => ['rotulo' => 'Comprovantes de pagamento', 'icone' => 'fa-paperclip', 'tabela' => 'pagamento_os_anexos',     'fk' => 'os_id',               'ordem' => 'id ASC'],
        'anexos'       => ['rotulo' => 'Anexos da O.S.',       'icone' => 'fa-file',          'tabela' => 'anexos_os',               'fk' => 'ordem_servico_id',    'ordem' => 'id ASC'],
        'devolucoes'   => ['rotulo' => 'Devoluções',           'icone' => 'fa-reply',         'tabela' => 'devolucao_os',            'fk' => 'ordem_de_servico_id', 'ordem' => 'id ASC'],
        'repasses'     => ['rotulo' => 'Repasses ao credor',   'icone' => 'fa-exchange',      'tabela' => 'repasse_credor',          'fk' => 'ordem_de_servico_id', 'ordem' => 'id ASC'],
        'liquidacoes'  => ['rotulo' => 'Liquidações',          'icone' => 'fa-check-square-o', 'tabela' => null,                     'fk' => null,                  'ordem' => null],
        'entregas'     => ['rotulo' => 'Entrega',              'icone' => 'fa-handshake-o',   'tabela' => 'os_entregas',             'fk' => 'ordem_servico_id',    'ordem' => 'id ASC'],
        'nfse'         => ['rotulo' => 'NFS-e',                'icone' => 'fa-file-text-o',   'tabela' => 'nfse_notas',              'fk' => 'ordem_servico_id',    'ordem' => 'id ASC'],
        'assinaturas'  => ['rotulo' => 'Documentos assinados', 'icone' => 'fa-pencil-square-o', 'tabela' => 'os_documentos_assinados', 'fk' => 'os_id',             'ordem' => 'id ASC'],
    ];
}

/** Ações registradas: rótulo, ícone e cor (classe). */
function osaud_acoes(): array
{
    return [
        'os_criada'            => ['O.S. criada',                 'fa-plus-circle',    'ok'],
        'os_editada'           => ['O.S. editada',                'fa-pencil',         'edit'],
        'os_cancelada'         => ['O.S. cancelada',              'fa-ban',            'del'],
        'item_incluido'        => ['Ato incluído',                'fa-plus',           'edit'],
        'item_removido'        => ['Ato removido',                'fa-trash',          'del'],
        'item_alterado'        => ['Ato alterado',                'fa-pencil',         'edit'],
        'item_isento'          => ['Ato marcado como isento',     'fa-percent',        'edit'],
        'itens_reordenados'    => ['Ordem dos atos alterada',     'fa-sort',           'info'],
        'total_alterado'       => ['Total da O.S. alterado',      'fa-calculator',     'edit'],
        'pagamento_lancado'    => ['Pagamento lançado',           'fa-money',          'ok'],
        'pagamento_removido'   => ['Pagamento excluído',          'fa-trash',          'del'],
        'pagamento_observacao' => ['Observação de pagamento',     'fa-comment',        'edit'],
        'pagamento_online'     => ['Pagamento online registrado', 'fa-credit-card',    'ok'],
        'comprovante_enviado'  => ['Comprovante enviado',         'fa-paperclip',      'ok'],
        'comprovante_removido' => ['Comprovante excluído',        'fa-trash',          'del'],
        'anexo_incluido'       => ['Anexo incluído',              'fa-paperclip',      'ok'],
        'anexo_removido'       => ['Anexo removido',              'fa-trash',          'del'],
        'devolucao_lancada'    => ['Devolução lançada',           'fa-reply',          'edit'],
        'devolucao_removida'   => ['Devolução excluída',          'fa-trash',          'del'],
        'repasse_lancado'      => ['Repasse lançado',             'fa-exchange',       'edit'],
        'repasse_removido'     => ['Repasse excluído',            'fa-trash',          'del'],
        'ato_liquidado'        => ['Ato liquidado',               'fa-check',          'ok'],
        'os_liquidada'         => ['O.S. liquidada',              'fa-check-square-o', 'ok'],
        'liquidacao_desfeita'  => ['Liquidação desfeita',         'fa-undo',           'del'],
        'os_entregue'          => ['O.S. entregue',               'fa-handshake-o',    'ok'],
        'documento_assinado'   => ['Documento assinado',          'fa-certificate',    'info'],
        'nfse_emitida'         => ['NFS-e emitida',               'fa-file-text-o',    'info'],
        'nfse_cancelada'       => ['NFS-e cancelada',             'fa-ban',            'del'],
        'nfse_reemitida'       => ['NFS-e reemitida',             'fa-refresh',        'info'],
        'alteracao'            => ['Alteração',                   'fa-pencil',         'edit'],
    ];
}

function osaud_acao_info(string $acao): array
{
    $a = osaud_acoes()[$acao] ?? null;
    if (!$a) {
        return ['rotulo' => ucfirst(str_replace('_', ' ', $acao)), 'icone' => 'fa-pencil', 'cor' => 'edit'];
    }
    return ['rotulo' => $a[0], 'icone' => $a[1], 'cor' => $a[2]];
}

/** Rótulos amigáveis de colunas. */
function osaud_rotulo_campo(string $campo): string
{
    static $m = [
        'cliente' => 'Apresentante', 'cpf_cliente' => 'CPF/CNPJ', 'total_os' => 'Total da O.S.',
        'descricao_os' => 'Descrição da O.S.', 'observacoes' => 'Observações', 'base_de_calculo' => 'Base de cálculo',
        'status' => 'Situação', 'motivo_cancelamento' => 'Motivo do cancelamento', 'cancelado_por' => 'Cancelado por',
        'cancelado_em' => 'Cancelado em', 'criado_por' => 'Criado por', 'data_criacao' => 'Data de criação',
        'criado_em' => 'Criado em', 'ato' => 'Ato', 'quantidade' => 'Quantidade', 'desconto_legal' => 'Desc. legal (%)',
        'descricao' => 'Descrição', 'emolumentos' => 'Emolumentos', 'ferc' => 'FERC', 'fadep' => 'FADEP', 'femp' => 'FEMP',
        'ferrfis' => 'FERRFIS', 'total' => 'Total', 'quantidade_liquidada' => 'Qtd. liquidada', 'ordem_exibicao' => 'Ordem',
        'forma_de_pagamento' => 'Forma de pagamento', 'total_pagamento' => 'Valor pago', 'data_pagamento' => 'Data do pagamento',
        'funcionario' => 'Funcionário', 'observacao' => 'Observação', 'nome_original' => 'Arquivo', 'arquivo' => 'Arquivo no servidor',
        'enviado_por' => 'Enviado por', 'enviado_em' => 'Enviado em', 'caminho_anexo' => 'Arquivo', 'data' => 'Data',
        'total_devolucao' => 'Valor devolvido', 'forma_devolucao' => 'Forma de devolução', 'data_devolucao' => 'Data da devolução',
        'total_repasse' => 'Valor repassado', 'forma_repasse' => 'Forma de repasse', 'data_repasse' => 'Data do repasse',
        'recebido_por' => 'Recebido por', 'recebido_doc' => 'Documento de quem recebeu', 'entregue_por' => 'Entregue por',
        'assinado_por' => 'Assinado por', 'assinado_em' => 'Assinado em', 'tipo' => 'Tipo', 'numero' => 'Número',
    ];
    return $m[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
}

function osaud_campo_monetario(string $campo): bool
{
    return (bool) preg_match('/^(total|emolumentos|ferc|fadep|femp|ferrfis|base_de_calculo|valor)/', $campo);
}

function osaud_brl($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return 'R$ ' . number_format((float) str_replace(',', '.', (string) $v), 2, ',', '.');
}

function osaud_fmt_valor(string $campo, $v): string
{
    if ($v === null) {
        return '—';
    }
    if ($v === '') {
        return '(vazio)';
    }
    if (osaud_campo_monetario($campo) && is_numeric(str_replace(',', '.', (string) $v))) {
        return osaud_brl($v);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', (string) $v)) {
        return date('d/m/Y H:i', strtotime((string) $v));
    }
    return (string) $v;
}

/* =====================================================================
   RETRATO (snapshot) DA O.S.
   ===================================================================== */

/** Normaliza uma linha: tudo vira string (como o mysqli devolve), valores longos são resumidos. */
function osaud_normalizar_linha(array $r): array
{
    foreach ($r as $k => $v) {
        if ($v === null) {
            continue;
        }
        if (is_bool($v)) {
            $v = $v ? '1' : '0';
        }
        $v = (string) $v;
        if (strlen($v) > OSAUD_CAMPO_MAX) {
            $v = '[conteúdo longo omitido · ' . strlen($v) . ' bytes · sha1 ' . substr(sha1($v), 0, 12) . ']';
        } elseif (!mb_check_encoding($v, 'UTF-8')) {
            $v = '[conteúdo binário · ' . strlen($v) . ' bytes · sha1 ' . substr(sha1($v), 0, 12) . ']';
        }
        $r[$k] = $v;
    }
    return $r;
}

function osaud_linhas(PDO $pdo, string $sql, array $params): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $out[] = osaud_normalizar_linha($r);
    }
    return $out;
}

/**
 * Retrato completo da O.S.
 * Seção = [] quando a tabela não existe (nada lá) e null quando a leitura
 * falhou (desconhecido — fica fora da comparação).
 */
function osaud_snapshot(PDO $pdo, int $osId): ?array
{
    if ($osId <= 0) {
        return null;
    }
    $os = osaud_linhas($pdo, 'SELECT * FROM ordens_de_servico WHERE id = ?', [$osId]);
    if (!$os) {
        return null;
    }

    $snap = [
        'v'            => 1,
        'capturado_em' => osaud_agora(),
        'os'           => $os[0],
    ];

    foreach (osaud_secoes() as $chave => $s) {
        try {
            if ($chave === 'liquidacoes') {
                $snap[$chave] = osaud_snapshot_liquidacoes($pdo, $osId);
                continue;
            }
            if (!osaud_tabela_existe($pdo, $s['tabela'])) {
                $snap[$chave] = [];
                continue;
            }
            $snap[$chave] = osaud_linhas(
                $pdo,
                "SELECT * FROM `{$s['tabela']}` WHERE `{$s['fk']}` = ? ORDER BY {$s['ordem']}",
                [$osId]
            );
        } catch (Throwable $e) {
            $snap[$chave] = null;
        }
    }

    return $snap;
}

function osaud_snapshot_liquidacoes(PDO $pdo, int $osId): array
{
    $out = [];
    foreach (['atos_liquidados' => 'tabela', 'atos_manuais_liquidados' => 'manual'] as $tabela => $origem) {
        if (!osaud_tabela_existe($pdo, $tabela)) {
            continue;
        }
        foreach (osaud_linhas($pdo, "SELECT * FROM `$tabela` WHERE ordem_servico_id = ? ORDER BY id ASC", [$osId]) as $r) {
            $r['_chave']  = $origem . ':' . ($r['id'] ?? '');
            $r['_origem'] = $origem === 'manual' ? 'Ato manual' : 'Tabela';
            $out[] = $r;
        }
    }
    return $out;
}

/** Totais financeiros do retrato (usados no impresso). */
function osaud_totais(array $snap): array
{
    $soma = static function ($linhas, $campo) {
        $t = 0.0;
        foreach ((array) $linhas as $l) {
            $t += (float) ($l[$campo] ?? 0);
        }
        return $t;
    };
    return [
        'pagamentos' => is_array($snap['pagamentos'] ?? null) ? $soma($snap['pagamentos'], 'total_pagamento') : null,
        'devolucoes' => is_array($snap['devolucoes'] ?? null) ? $soma($snap['devolucoes'], 'total_devolucao') : null,
        'repasses'   => is_array($snap['repasses'] ?? null)   ? $soma($snap['repasses'], 'total_repasse')     : null,
    ];
}

/* =====================================================================
   COMPARAÇÃO (diff)
   ===================================================================== */

function osaud_chave_linha(array $r): string
{
    return (string) ($r['_chave'] ?? ($r['id'] ?? md5(json_encode($r))));
}

function osaud_iguais($a, $b): bool
{
    if ($a === null || $b === null) {
        return $a === $b;
    }
    $a = (string) $a;
    $b = (string) $b;
    if ($a === $b) {
        return true;
    }
    /* "10.00" x "10" x "10.0" — mesmo número */
    if (is_numeric($a) && is_numeric($b)) {
        return abs((float) $a - (float) $b) < 0.000001;
    }
    return false;
}

function osaud_campos_alterados(array $antes, array $depois): array
{
    $out = [];
    foreach (array_unique(array_merge(array_keys($antes), array_keys($depois))) as $k) {
        if (strpos((string) $k, '_') === 0) {
            continue;
        }
        $va = $antes[$k] ?? null;
        $vd = $depois[$k] ?? null;
        if (!osaud_iguais($va, $vd)) {
            $out[] = ['campo' => $k, 'antes' => $va, 'depois' => $vd];
        }
    }
    return $out;
}

/**
 * Diferenças entre dois retratos.
 * ['os' => ['estado' => criada|excluida|alterada|null, 'campos' => [...]],
 *  'secoes' => [secao => ['incluidos' => [], 'removidos' => [], 'alterados' => []]]]
 */
function osaud_diff(?array $antes, ?array $depois): array
{
    $diff = ['os' => ['estado' => null, 'campos' => []], 'secoes' => []];

    $ao = $antes['os'] ?? null;
    $do = $depois['os'] ?? null;
    if ($ao === null && $do !== null) {
        $diff['os']['estado'] = 'criada';
    } elseif ($ao !== null && $do === null) {
        $diff['os']['estado'] = 'excluida';
    } elseif ($ao !== null && $do !== null) {
        $campos = osaud_campos_alterados($ao, $do);
        if ($campos) {
            $diff['os']['estado'] = 'alterada';
            $diff['os']['campos'] = $campos;
        }
    }

    foreach (array_keys(osaud_secoes()) as $sec) {
        $la = $antes === null ? [] : ($antes[$sec] ?? null);
        $ld = $depois === null ? [] : ($depois[$sec] ?? null);
        if (!is_array($la) || !is_array($ld)) {
            continue;   // seção ilegível num dos lados: não compara
        }
        $ia = [];
        foreach ($la as $r) {
            $ia[osaud_chave_linha($r)] = $r;
        }
        $id = [];
        foreach ($ld as $r) {
            $id[osaud_chave_linha($r)] = $r;
        }

        $inc = $rem = $alt = [];
        foreach ($id as $k => $r) {
            if (!isset($ia[$k])) {
                $inc[] = $r;
            } else {
                $c = osaud_campos_alterados($ia[$k], $r);
                if ($c) {
                    $alt[] = ['chave' => (string) $k, 'antes' => $ia[$k], 'depois' => $r, 'campos' => $c];
                }
            }
        }
        foreach ($ia as $k => $r) {
            if (!isset($id[$k])) {
                $rem[] = $r;
            }
        }
        if ($inc || $rem || $alt) {
            $diff['secoes'][$sec] = ['incluidos' => $inc, 'removidos' => $rem, 'alterados' => $alt];
        }
    }

    return $diff;
}

function osaud_diff_vazio(array $diff): bool
{
    return $diff['os']['estado'] === null && empty($diff['secoes']);
}

/** Houve exclusão? (linhas removidas ou anexo marcado como removido) */
function osaud_diff_tem_exclusao(array $diff): bool
{
    foreach ($diff['secoes'] as $sec => $d) {
        if (!empty($d['removidos'])) {
            return true;
        }
        foreach ($d['alterados'] as $a) {
            foreach ($a['campos'] as $c) {
                if ($c['campo'] === 'status' && in_array(mb_strtolower((string) $c['depois']), ['removido', 'excluido', 'excluído'], true)) {
                    return true;
                }
            }
        }
    }
    return false;
}

/** Nome curto de uma linha de uma seção, para o resumo. */
function osaud_rotulo_linha(string $sec, array $r): string
{
    switch ($sec) {
        case 'itens':
            $d = trim((string) ($r['descricao'] ?? ''));
            $d = $d !== '' ? ' — ' . mb_strimwidth($d, 0, 60, '…') : '';
            return 'Ato ' . ($r['ato'] ?? '?') . $d;
        case 'pagamentos':
            return 'Pagamento ' . ($r['forma_de_pagamento'] ?? '') . ' ' . osaud_brl($r['total_pagamento'] ?? null);
        case 'comprovantes':
            return 'Comprovante "' . ($r['nome_original'] ?? $r['arquivo'] ?? '') . '"';
        case 'anexos':
            return 'Anexo "' . ($r['caminho_anexo'] ?? '') . '"';
        case 'devolucoes':
            return 'Devolução ' . ($r['forma_devolucao'] ?? '') . ' ' . osaud_brl($r['total_devolucao'] ?? null);
        case 'repasses':
            return 'Repasse ' . ($r['forma_repasse'] ?? '') . ' ' . osaud_brl($r['total_repasse'] ?? null);
        case 'liquidacoes':
            return 'Liquidação do ato ' . ($r['ato'] ?? '?') . ' (' . ($r['quantidade_liquidada'] ?? '?') . ' un.)';
        case 'entregas':
            return 'Entrega a ' . ($r['recebido_por'] ?? '?');
        case 'nfse':
            $n = $r['numero_nfse'] ?? $r['numero'] ?? $r['n_nfse'] ?? null;
            return 'NFS-e ' . ($n ? 'nº ' . $n : '#' . ($r['id'] ?? '?')) . (isset($r['status']) ? ' (' . $r['status'] . ')' : '');
        case 'assinaturas':
            return 'Documento assinado "' . ($r['tipo'] ?? '') . '"';
    }
    return '#' . ($r['id'] ?? '?');
}

/** Resumo textual (uma linha por mudança). */
function osaud_resumo(array $diff): array
{
    $l = [];
    if ($diff['os']['estado'] === 'criada') {
        $l[] = 'O.S. criada';
    } elseif ($diff['os']['estado'] === 'excluida') {
        $l[] = 'O.S. excluída do banco';
    }
    foreach ($diff['os']['campos'] as $c) {
        $l[] = osaud_rotulo_campo($c['campo']) . ': ' . mb_strimwidth(osaud_fmt_valor($c['campo'], $c['antes']), 0, 80, '…')
             . ' → ' . mb_strimwidth(osaud_fmt_valor($c['campo'], $c['depois']), 0, 80, '…');
    }
    $verbos = [
        'itens' => ['incluído', 'removido'], 'pagamentos' => ['lançado', 'EXCLUÍDO'], 'comprovantes' => ['enviado', 'EXCLUÍDO'],
        'anexos' => ['incluído', 'excluído'], 'devolucoes' => ['lançada', 'EXCLUÍDA'], 'repasses' => ['lançado', 'EXCLUÍDO'],
        'liquidacoes' => ['registrada', 'desfeita'], 'entregas' => ['registrada', 'excluída'], 'nfse' => ['registrada', 'excluída'],
        'assinaturas' => ['registrado', 'excluído'],
    ];
    foreach ($diff['secoes'] as $sec => $d) {
        foreach ($d['incluidos'] as $r) {
            $l[] = osaud_rotulo_linha($sec, $r) . ' ' . $verbos[$sec][0];
        }
        foreach ($d['removidos'] as $r) {
            $l[] = osaud_rotulo_linha($sec, $r) . ' ' . $verbos[$sec][1];
        }
        foreach ($d['alterados'] as $a) {
            $partes = [];
            foreach ($a['campos'] as $c) {
                $partes[] = osaud_rotulo_campo($c['campo']) . ' ' . mb_strimwidth(osaud_fmt_valor($c['campo'], $c['antes']), 0, 40, '…')
                          . ' → ' . mb_strimwidth(osaud_fmt_valor($c['campo'], $c['depois']), 0, 40, '…');
            }
            $l[] = osaud_rotulo_linha($sec, $a['antes']) . ': ' . implode('; ', $partes);
        }
    }
    return $l;
}

function osaud_secoes_alteradas(array $diff): array
{
    $s = [];
    if ($diff['os']['estado'] !== null) {
        $s[] = 'os';
    }
    return array_merge($s, array_keys($diff['secoes']));
}

/* =====================================================================
   GRAVAÇÃO
   ===================================================================== */

function osaud_json(?array $v): ?string
{
    if ($v === null) {
        return null;
    }
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

function osaud_compactar(?string $json): ?string
{
    return $json === null ? null : gzcompress($json, 6);
}

function osaud_descompactar($blob): ?array
{
    if ($blob === null || $blob === '') {
        return null;
    }
    if (is_resource($blob)) {
        $blob = stream_get_contents($blob);
    }
    $json = @gzuncompress($blob);
    if ($json === false) {
        $json = $blob;   // tolera registro gravado sem compressão
    }
    $v = json_decode($json, true);
    return is_array($v) ? $v : null;
}

function osaud_hash(?string $anterior, int $osId, string $acao, ?string $usuario, string $quando, ?string $jAntes, ?string $jDepois): string
{
    return hash('sha256', implode('|', [
        $anterior ?? '', $osId, $acao, $usuario ?? '', $quando,
        hash('sha256', $jAntes ?? ''), hash('sha256', $jDepois ?? ''),
    ]));
}

/** Usuário da sessão (ou o informado). */
function osaud_usuario(): ?string
{
    return isset($_SESSION['username']) ? (string) $_SESSION['username'] : null;
}

/**
 * Grupo = "sessão de trabalho" na O.S.: as várias gravações feitas por um
 * usuário numa mesma O.S. com menos de 30 min entre elas (ex.: uma edição
 * com vários ajustes de item) ficam agrupadas.
 */
function osaud_grupo(int $osId): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    $g = $_SESSION['osaud_grupo'][$osId] ?? null;
    if (!is_array($g) || (time() - (int) ($g['ts'] ?? 0)) > 1800) {
        $g = ['tok' => bin2hex(random_bytes(8)), 'ts' => time()];
    }
    $g['ts'] = time();
    $_SESSION['osaud_grupo'][$osId] = $g;
    return $g['tok'];
}

/** Abre uma nova sessão de edição (chamado ao abrir editar_os.php). */
function osaud_nova_sessao_edicao(int $osId): void
{
    if (session_status() === PHP_SESSION_ACTIVE && $osId > 0) {
        $_SESSION['osaud_grupo'][$osId] = ['tok' => bin2hex(random_bytes(8)), 'ts' => time()];
    }
}

/**
 * Grava um evento (se houver diferença). Devolve o id ou null.
 * $meta: usuario, grupo, origem, motivo, ip, user_agent
 */
function osaud_registrar(PDO $pdo, int $osId, string $acao, ?array $antes, ?array $depois, array $meta = []): ?int
{
    osaud_migrar($pdo);

    $diff = osaud_diff($antes, $depois);
    if (osaud_diff_vazio($diff)) {
        return null;
    }

    $resumo  = osaud_resumo($diff);
    $jAntes  = osaud_json($antes);
    $jDepois = osaud_json($depois);
    $quando  = osaud_agora();
    $usuario = array_key_exists('usuario', $meta) ? $meta['usuario'] : osaud_usuario();
    $usuario = $usuario !== null ? mb_substr((string) $usuario, 0, 160) : null;

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT hash FROM os_auditoria WHERE os_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $st->execute([$osId]);
        $anterior = $st->fetchColumn() ?: null;

        $hash = osaud_hash($anterior, $osId, $acao, $usuario, $quando, $jAntes, $jDepois);

        $ins = $pdo->prepare('INSERT INTO os_auditoria
            (os_id, acao, grupo, usuario, ip, user_agent, origem, motivo, secoes, tem_exclusao, resumo, diff, antes, depois, hash_anterior, hash, criado_em)
            VALUES (:os, :acao, :grupo, :usuario, :ip, :ua, :origem, :motivo, :secoes, :exc, :resumo, :diff, :antes, :depois, :hant, :hash, :quando)');
        $ins->bindValue(':os', $osId, PDO::PARAM_INT);
        $ins->bindValue(':acao', mb_substr($acao, 0, 60));
        $ins->bindValue(':grupo', $meta['grupo'] ?? null);
        $ins->bindValue(':usuario', $usuario);
        $ins->bindValue(':ip', mb_substr((string) ($meta['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45) ?: null);
        $ins->bindValue(':ua', mb_substr((string) ($meta['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255) ?: null);
        $ins->bindValue(':origem', mb_substr((string) ($meta['origem'] ?? osaud_origem()), 0, 160) ?: null);
        $ins->bindValue(':motivo', isset($meta['motivo']) && $meta['motivo'] !== '' ? mb_substr((string) $meta['motivo'], 0, 1000) : null);
        $ins->bindValue(':secoes', implode(',', osaud_secoes_alteradas($diff)));
        $ins->bindValue(':exc', osaud_diff_tem_exclusao($diff) ? 1 : 0, PDO::PARAM_INT);
        $ins->bindValue(':resumo', implode("\n", $resumo));
        $ins->bindValue(':diff', osaud_json($diff));
        $ins->bindValue(':antes', osaud_compactar($jAntes), $jAntes === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $ins->bindValue(':depois', osaud_compactar($jDepois), $jDepois === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $ins->bindValue(':hant', $anterior);
        $ins->bindValue(':hash', $hash);
        $ins->bindValue(':quando', $quando);
        $ins->execute();
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function osaud_origem(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    $partes = array_slice(explode('/', str_replace('\\', '/', $script)), -2);
    return implode('/', $partes);
}

/* =====================================================================
   MONITORAMENTO AUTOMÁTICO (antes → fim da requisição → depois)
   ===================================================================== */

/**
 * Descobre a(s) O.S. afetada(s) a partir dos parâmetros do endpoint.
 * Chaves aceitas em $alvo: os_id, item_id, itens (lista de ids), pagamento_id,
 * anexo_id, comprovante_id, devolucao_id, repasse_id, nota_id.
 */
function osaud_resolver_os(PDO $pdo, array $alvo): array
{
    $ids = [];
    $um = static function ($sql, $v) use ($pdo, &$ids) {
        $v = (int) $v;
        if ($v <= 0) {
            return;
        }
        try {
            $st = $pdo->prepare($sql);
            $st->execute([$v]);
            $os = (int) $st->fetchColumn();
            if ($os > 0) {
                $ids[$os] = true;
            }
        } catch (Throwable $e) {
            /* tabela ausente: ignora */
        }
    };

    if (!empty($alvo['os_id'])) {
        $ids[(int) $alvo['os_id']] = true;
    }
    if (!empty($alvo['item_id'])) {
        $um('SELECT ordem_servico_id FROM ordens_de_servico_itens WHERE id = ?', $alvo['item_id']);
    }
    if (!empty($alvo['itens']) && is_array($alvo['itens'])) {
        foreach (array_slice($alvo['itens'], 0, 500) as $iid) {
            $um('SELECT ordem_servico_id FROM ordens_de_servico_itens WHERE id = ?', $iid);
        }
    }
    if (!empty($alvo['pagamento_id'])) {
        $um('SELECT ordem_de_servico_id FROM pagamento_os WHERE id = ?', $alvo['pagamento_id']);
    }
    if (!empty($alvo['anexo_id'])) {
        $um('SELECT ordem_servico_id FROM anexos_os WHERE id = ?', $alvo['anexo_id']);
    }
    if (!empty($alvo['comprovante_id'])) {
        $um('SELECT COALESCE(a.os_id, p.ordem_de_servico_id) FROM pagamento_os_anexos a LEFT JOIN pagamento_os p ON p.id = a.pagamento_id WHERE a.id = ?', $alvo['comprovante_id']);
    }
    if (!empty($alvo['devolucao_id'])) {
        $um('SELECT ordem_de_servico_id FROM devolucao_os WHERE id = ?', $alvo['devolucao_id']);
    }
    if (!empty($alvo['repasse_id'])) {
        $um('SELECT ordem_de_servico_id FROM repasse_credor WHERE id = ?', $alvo['repasse_id']);
    }
    if (!empty($alvo['nota_id'])) {
        $um('SELECT ordem_servico_id FROM nfse_notas WHERE id = ?', $alvo['nota_id']);
    }

    unset($ids[0]);
    return array_keys($ids);
}

/**
 * Começa a observar a(s) O.S. afetada(s) pela requisição atual.
 *
 * $alvo  ver osaud_resolver_os(); além disso:
 *        'os_global' => nome de variável global que conterá o id da O.S.
 *                       ao final (O.S. recém-criada).
 * $opc   'usuario' (sobrepõe o da sessão), 'motivo', 'origem'
 *        'motivo_post' => nome do campo do POST com o motivo.
 */
function osaud_monitorar(string $acao, array $alvo = [], array $opc = []): void
{
    try {
        $pdo = osaud_pdo();
        osaud_migrar($pdo);

        $estado = [
            'acao'      => $acao,
            'opc'       => $opc,
            'os_global' => $alvo['os_global'] ?? null,
            'antes'     => [],
            'grupos'    => [],
        ];
        foreach (osaud_resolver_os($pdo, $alvo) as $osId) {
            $estado['antes'][$osId]  = osaud_snapshot($pdo, $osId);
            $estado['grupos'][$osId] = osaud_grupo($osId);
        }
        if (!$estado['antes'] && !$estado['os_global']) {
            return;
        }
        register_shutdown_function('osaud__finalizar', $estado);
    } catch (Throwable $e) {
        error_log('[auditoria_os] monitorar: ' . $e->getMessage());
    }
}

/** @internal executado no fim da requisição. */
function osaud__finalizar(array $estado): void
{
    try {
        $pdo = osaud_pdo();

        if ($estado['os_global']) {
            $novo = (int) ($GLOBALS[$estado['os_global']] ?? 0);
            if ($novo > 0 && !array_key_exists($novo, $estado['antes'])) {
                $estado['antes'][$novo] = null;
                $estado['grupos'][$novo] = null;
            }
        }

        $opc = $estado['opc'];
        $motivo = $opc['motivo'] ?? null;
        if (!$motivo && !empty($opc['motivo_post'])) {
            $motivo = trim((string) ($_POST[$opc['motivo_post']] ?? ''));
        }

        foreach ($estado['antes'] as $osId => $antes) {
            $depois = osaud_snapshot($pdo, (int) $osId);
            if ($antes === null && $depois === null) {
                continue;
            }
            $meta = ['grupo' => $estado['grupos'][$osId] ?? null, 'motivo' => $motivo];
            if (array_key_exists('usuario', $opc)) {
                $meta['usuario'] = is_callable($opc['usuario']) ? call_user_func($opc['usuario']) : $opc['usuario'];
            }
            if (!empty($opc['origem'])) {
                $meta['origem'] = $opc['origem'];
            }
            osaud_registrar($pdo, (int) $osId, $estado['acao'], $antes, $depois, $meta);
        }
    } catch (Throwable $e) {
        error_log('[auditoria_os] finalizar: ' . $e->getMessage());
    }
}

/* =====================================================================
   LEITURA (página de auditoria)
   ===================================================================== */

/** Verifica a cadeia de hash de uma O.S. */
function osaud_verificar_cadeia(PDO $pdo, int $osId): array
{
    $st = $pdo->prepare('SELECT id, os_id, acao, usuario, criado_em, antes, depois, hash_anterior, hash FROM os_auditoria WHERE os_id = ? ORDER BY id ASC');
    $st->execute([$osId]);
    $anterior = null;
    $total = 0;
    $falhas = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $total++;
        $a = osaud_descompactar($r['antes']);
        $d = osaud_descompactar($r['depois']);
        $h = osaud_hash($anterior, (int) $r['os_id'], $r['acao'], $r['usuario'], $r['criado_em'], osaud_json($a), osaud_json($d));
        if ($r['hash_anterior'] !== $anterior || !hash_equals($r['hash'], $h)) {
            $falhas[] = (int) $r['id'];
        }
        $anterior = $r['hash'];
    }
    return ['ok' => !$falhas, 'total' => $total, 'falhas' => $falhas];
}

/** Lê um evento completo (com retratos descompactados). */
function osaud_evento(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM os_auditoria WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    $r['antes']  = osaud_descompactar($r['antes']);
    $r['depois'] = osaud_descompactar($r['depois']);
    $r['diff']   = json_decode((string) $r['diff'], true) ?: osaud_diff($r['antes'], $r['depois']);
    return $r;
}

/* ------------------------------------------------------------------
   Histórico LEGADO (logs_ordens_de_servico): cópia da O.S. feita ao
   abrir a tela de edição, antes desta auditoria existir.
   ------------------------------------------------------------------ */

/** Retrato (parcial) montado a partir de um log legado. */
function osaud_legado_snapshot(PDO $pdo, int $logId): ?array
{
    if (!osaud_tabela_existe($pdo, 'logs_ordens_de_servico')) {
        return null;
    }
    $l = osaud_linhas($pdo, 'SELECT * FROM logs_ordens_de_servico WHERE id = ?', [$logId]);
    if (!$l) {
        return null;
    }
    $l = $l[0];
    $osId = (int) $l['ordem_de_servico_id'];
    $atual = osaud_linhas($pdo, 'SELECT * FROM ordens_de_servico WHERE id = ?', [$osId]);
    $os = $atual ? $atual[0] : ['id' => (string) $osId];
    foreach (['cliente', 'cpf_cliente', 'total_os', 'descricao_os', 'observacoes', 'criado_por'] as $c) {
        if (array_key_exists($c, $l)) {
            $os[$c] = $l[$c];
        }
    }
    $itens = [];
    if (osaud_tabela_existe($pdo, 'logs_ordens_de_servico_itens')) {
        foreach (osaud_linhas($pdo, 'SELECT * FROM logs_ordens_de_servico_itens WHERE ordem_servico_id = ? ORDER BY ordem_exibicao ASC, id ASC', [$logId]) as $i) {
            $i['ordem_servico_id'] = (string) $osId;
            /* Os itens do log não guardam o id original: chave = ato + ordem */
            $i['_chave'] = ($i['ato'] ?? '') . '#' . ($i['ordem_exibicao'] ?? '') . '#' . ($i['descricao'] ?? '');
            unset($i['id']);
            $itens[] = $i;
        }
    }
    return [
        'v' => 1, 'legado' => true, 'capturado_em' => $l['data_edicao'] ?? null,
        'os' => $os, 'itens' => $itens,
        /* demais seções não existiam no log antigo */
        'pagamentos' => null, 'comprovantes' => null, 'anexos' => null, 'devolucoes' => null,
        'repasses' => null, 'liquidacoes' => null, 'entregas' => null, 'nfse' => null, 'assinaturas' => null,
    ];
}

/** Retrato atual com as mesmas chaves de item do legado (para comparar). */
function osaud_legado_atual(PDO $pdo, int $osId): ?array
{
    $s = osaud_snapshot($pdo, $osId);
    if (!$s) {
        return null;
    }
    foreach ($s['itens'] ?? [] as $k => $i) {
        $s['itens'][$k]['_chave'] = ($i['ato'] ?? '') . '#' . ($i['ordem_exibicao'] ?? '') . '#' . ($i['descricao'] ?? '');
        unset($s['itens'][$k]['id']);
    }
    foreach (['pagamentos', 'comprovantes', 'anexos', 'devolucoes', 'repasses', 'liquidacoes', 'entregas', 'nfse', 'assinaturas'] as $c) {
        $s[$c] = null;
    }
    return $s;
}

/** Para comparação legado x legado, limita os campos da O.S. aos que o log guarda. */
function osaud_legado_restringir(?array $s): ?array
{
    if (!$s) {
        return $s;
    }
    $manter = ['id', 'cliente', 'cpf_cliente', 'total_os', 'descricao_os', 'observacoes', 'criado_por'];
    $s['os'] = array_intersect_key($s['os'], array_flip($manter));
    $camposItem = ['ato', 'quantidade', 'desconto_legal', 'descricao', 'emolumentos', 'ferc', 'fadep', 'femp', 'ferrfis', 'total', 'quantidade_liquidada', 'status', 'ordem_exibicao', '_chave'];
    foreach ($s['itens'] ?? [] as $k => $i) {
        $s['itens'][$k] = array_intersect_key($i, array_flip($camposItem));
    }
    return $s;
}

/* =====================================================================
   IMPRESSO (reprodução de uma versão registrada)
   ===================================================================== */

/**
 * Prepara a reprodução do impresso a partir de um retrato. Os geradores
 * imprimir_os.php / imprimir-os.php leem $GLOBALS['__OSAUD_IMPRESSAO__'].
 */
function osaud_preparar_impressao(array $snap, string $marca): void
{
    $GLOBALS['__OSAUD_IMPRESSAO__'] = [
        'os'     => $snap['os'],
        'itens'  => $snap['itens'] ?? [],
        'totais' => osaud_totais($snap),
        'marca'  => $marca,
    ];
}

/** Faixa vermelha no topo de cada página do impresso reproduzido. */
function osaud_pdf_marca($pdf): void
{
    $marca = (string) ($GLOBALS['__OSAUD_IMPRESSAO__']['marca'] ?? '');
    if ($marca === '') {
        return;
    }
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $w = $pdf->getPageWidth();
    $pdf->Rect(0, 0, $w, 6, 'F', [], [254, 226, 226]);
    $pdf->SetFont('helvetica', 'B', 7.5);
    $pdf->SetTextColor(153, 27, 27);
    $tw = $pdf->GetStringWidth($marca);
    $pdf->Text(max(2, ($w - $tw) / 2), 1.4, $marca);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($x, $y);
}

} // function_exists
