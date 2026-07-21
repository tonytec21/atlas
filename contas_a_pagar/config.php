<?php
/**
 * config.php — Núcleo do módulo Contas a Pagar (Atlas).
 * DB `atlas`. Schema/migrações automáticas, configurações, CSRF, anexos e e-mail.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
@ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('America/Fortaleza'); // Maranhão (UTC-3)

/* Categorias padrão (usadas em filtros, formulários e gráficos) */
function cap_categorias()
{
    return ['Aluguel','Água','Energia','Internet/Telefone','Impostos','Fornecedores',
            'Salários','Manutenção','Software/Assinaturas','Financiamento','Fundos (Selos)','Outros'];
}
function cap_recorrencias()
{
    return ['Nenhuma','Mensal','Semanal','Anual'];
}

/* ==================================================================
 * FUNDOS DO SELO (a partir de relatorios_analiticos)
 *  - FERJ, FERC : semanais  (segunda a domingo → vence na 2ª subsequente)
 *  - FEMP, FADEP, FERRFIS : mensais (dia 1 ao fim → vence dia 10 do mês seguinte)
 * ================================================================== */
function cap_fundos_def()
{
    return [
        'FERJ'    => ['coluna' => 'ferj',    'periodicidade' => 'semanal', 'nome' => 'FERJ'],
        'FERC'    => ['coluna' => 'ferc',    'periodicidade' => 'semanal', 'nome' => 'FERC'],
        'FEMP'    => ['coluna' => 'femp',    'periodicidade' => 'mensal',  'nome' => 'FEMP'],
        'FADEP'   => ['coluna' => 'fadep',   'periodicidade' => 'mensal',  'nome' => 'FADEP'],
        'FERRFIS' => ['coluna' => 'ferrfis', 'periodicidade' => 'mensal',  'nome' => 'FERRFIS'],
    ];
}

function cap_tem_relatorios_analiticos()
{
    $conn = cap_db();
    $r = $conn->query("SHOW TABLES LIKE 'relatorios_analiticos'");
    if (!$r || $r->num_rows === 0) return false;
    // confere se as colunas dos fundos existem
    $cols = [];
    $rc = $conn->query("SHOW COLUMNS FROM relatorios_analiticos");
    while ($rc && $row = $rc->fetch_assoc()) $cols[strtolower($row['Field'])] = true;
    foreach (['ferj','ferc','femp','fadep','ferrfis','selagem'] as $need)
        if (!isset($cols[$need])) return false;
    return true;
}

/**
 * Sincroniza as contas de fundos a partir dos selos.
 * Cria/atualiza uma conta por fundo e período (upsert por origem_selo).
 * Não sobrescreve contas já pagas. Considera apenas selos válidos
 * (cancelado=0, isento=0, diferido=0), igual ao total de selos do caixa.
 *
 * @param bool $force ignora o intervalo mínimo entre sincronizações.
 * @return array resumo com quantidades criadas/atualizadas.
 */
function cap_sync_fundos_selo($force = false)
{
    cap_ensure_schema();
    $conn = cap_db();
    $res = ['criadas' => 0, 'atualizadas' => 0, 'ignoradas_pagas' => 0, 'periodos' => 0, 'ok' => false, 'motivo' => ''];

    if (!cap_tem_relatorios_analiticos()) { $res['motivo'] = 'Tabela relatorios_analiticos indisponível.'; return $res; }

    // throttle: no máximo 1 sync a cada 5 min (a menos que forçado)
    if (!$force) {
        $cfg = cap_settings_get();
        $ult = $cfg['fundos_sync_em'] ?? null;
        if ($ult && (time() - strtotime($ult)) < 300) { $res['ok'] = true; $res['motivo'] = 'throttle'; return $res; }
    }

    $filtro = "cancelado=0 AND isento=0 AND diferido=0";

    // -------- SEMANAIS: FERJ, FERC (segunda a domingo) --------
    $sqlSem = "SELECT DATE_SUB(DATE(selagem), INTERVAL WEEKDAY(selagem) DAY) AS ini,
                      COALESCE(SUM(ferj),0) AS ferj, COALESCE(SUM(ferc),0) AS ferc
               FROM relatorios_analiticos
               WHERE $filtro
               GROUP BY ini";
    $rs = $conn->query($sqlSem);
    while ($rs && $row = $rs->fetch_assoc()) {
        $ini = $row['ini']; if (!$ini) continue;
        $fim = date('Y-m-d', strtotime($ini . ' +6 day'));       // domingo
        $venc = date('Y-m-d', strtotime($ini . ' +7 day'));      // segunda subsequente
        $label = date('d/m', strtotime($ini)) . ' a ' . date('d/m/Y', strtotime($fim));
        foreach (['FERJ' => (float)$row['ferj'], 'FERC' => (float)$row['ferc']] as $fundo => $valor) {
            if ($valor <= 0.0001) continue;
            $res['periodos']++;
            $chave = $fundo . '|S|' . $ini;
            $titulo = $fundo . ' - Semana ' . $label;
            cap_upsert_fundo($conn, $chave, $fundo, $titulo, $valor, $venc, $res);
        }
    }

    // -------- MENSAIS: FEMP, FADEP, FERRFIS --------
    $sqlMes = "SELECT DATE_FORMAT(selagem,'%Y-%m') AS mes,
                      COALESCE(SUM(femp),0) AS femp, COALESCE(SUM(fadep),0) AS fadep, COALESCE(SUM(ferrfis),0) AS ferrfis
               FROM relatorios_analiticos
               WHERE $filtro
               GROUP BY mes";
    $rm = $conn->query($sqlMes);
    while ($rm && $row = $rm->fetch_assoc()) {
        $mes = $row['mes']; if (!$mes) continue;
        $primeiro = $mes . '-01';
        $venc = date('Y-m-10', strtotime($primeiro . ' +1 month')); // dia 10 do mês seguinte
        $label = date('m/Y', strtotime($primeiro));
        foreach (['FEMP' => (float)$row['femp'], 'FADEP' => (float)$row['fadep'], 'FERRFIS' => (float)$row['ferrfis']] as $fundo => $valor) {
            if ($valor <= 0.0001) continue;
            $res['periodos']++;
            $chave = $fundo . '|M|' . $mes;
            $titulo = $fundo . ' - ' . $label;
            cap_upsert_fundo($conn, $chave, $fundo, $titulo, $valor, $venc, $res);
        }
    }

    @$conn->query("UPDATE contas_config SET fundos_sync_em=NOW() WHERE id=1");
    $res['ok'] = true;
    return $res;
}

/** Upsert de uma conta de fundo por chave (origem_selo). Não mexe em contas pagas. */
function cap_upsert_fundo($conn, $chave, $fundo, $titulo, $valor, $venc, &$res)
{
    $stmt = $conn->prepare("SELECT id, status, valor, data_vencimento, titulo FROM contas_a_pagar WHERE origem_selo=? LIMIT 1");
    $stmt->bind_param('s', $chave); $stmt->execute();
    $ex = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if ($ex) {
        if ($ex['status'] === 'Pago') { $res['ignoradas_pagas']++; return; }
        $mudou = abs((float)$ex['valor'] - $valor) > 0.0001
              || (string)$ex['data_vencimento'] !== $venc
              || (string)$ex['titulo'] !== $titulo;
        if ($mudou) {
            $u = $conn->prepare("UPDATE contas_a_pagar SET valor=?, data_vencimento=?, titulo=? WHERE id=?");
            $u->bind_param('dssi', $valor, $venc, $titulo, $ex['id']);
            $u->execute(); $u->close();
            $res['atualizadas']++;
        }
        return;
    }
    $cat = 'Fundos (Selos)'; $forn = $fundo; $rec = 'Nenhuma'; $func = '(automático)';
    $desc = 'Gerado automaticamente a partir dos Relatórios Analíticos do selo.';
    $agora = date('Y-m-d H:i:s');
    $ins = $conn->prepare("INSERT INTO contas_a_pagar
        (titulo, categoria, fornecedor, valor, data_vencimento, descricao, recorrencia, funcionario, status, origem_selo, created_at)
        VALUES (?,?,?,?,?,?,?,?, 'Pendente', ?, ?)");
    $ins->bind_param('sssdssssss', $titulo, $cat, $forn, $valor, $venc, $desc, $rec, $func, $chave, $agora);
    $ins->execute(); $ins->close();
    $res['criadas']++;
}

/* ------------------------------------------------------------------
 * Contas virtuais (saldo do cartório, alimentado pelo módulo Caixa)
 *  - 'especie' : depósitos registrados como "Espécie"
 *  - 'banco'   : depósitos "Depósito Bancário" e "Transferência"
 * ------------------------------------------------------------------ */
function cap_contas_virtuais()
{
    return [
        'especie' => ['nome' => 'Espécie (dinheiro)', 'icone' => 'fa-money',       'tipos' => ['Espécie']],
        'banco'   => ['nome' => 'Saldo bancário',     'icone' => 'fa-university',  'tipos' => ['Depósito Bancário','Transferência']],
    ];
}

/** Formas de pagamento e a conta virtual que cada uma consome. */
function cap_formas_pagamento()
{
    return [
        'Espécie'                 => 'especie', // única que debita o dinheiro em espécie
        'PIX'                     => 'banco',
        'Transferência'           => 'banco',
        'TED/DOC'                 => 'banco',
        'Boleto'                  => 'banco',
        'Débito automático'       => 'banco',
        'Cartão de Débito'        => 'banco',
        'Cartão de Crédito'       => 'banco',
        'Centrais Eletrônicas'    => 'banco',
        'Outro (não afeta saldo)' => '',
    ];
}
function cap_conta_da_forma($forma)
{
    $m = cap_formas_pagamento();
    return $m[$forma] ?? '';
}
function cap_nome_conta($cod)
{
    $c = cap_contas_virtuais();
    return $c[$cod]['nome'] ?? '—';
}

/** Existe a tabela de depósitos do módulo Caixa? */
function cap_tem_deposito_caixa()
{
    $conn = cap_db();
    $r = $conn->query("SHOW TABLES LIKE 'deposito_caixa'");
    return $r && $r->num_rows > 0;
}

/** Existe a tabela de pagamentos do módulo de O.S.? */
function cap_tem_pagamento_os()
{
    $conn = cap_db();
    $r = $conn->query("SHOW TABLES LIKE 'pagamento_os'");
    return $r && $r->num_rows > 0;
}

/**
 * A qual conta virtual pertence uma forma de recebimento da O.S.
 * Regra do cartório: SOMENTE "Espécie" entra na conta espécie; todo o resto
 * (PIX, Transferência, Crédito, Débito, Boleto, Cheque…) entra no Saldo bancário.
 */
function cap_conta_recebimento_os($forma)
{
    $f = mb_strtolower(trim((string)$forma), 'UTF-8');
    if ($f === 'espécie' || $f === 'especie' || $f === 'dinheiro') return 'especie';
    return 'banco';
}

/**
 * Saldos das contas virtuais.
 * saldo = (depósitos do módulo Caixa) − (contas pagas debitadas naquela conta)
 * @return array ['especie'=>['entradas'=>x,'saidas'=>y,'saldo'=>z], 'banco'=>[...]]
 */
function cap_saldos()
{
    cap_ensure_schema();
    $conn = cap_db();
    $out = [];
    foreach (cap_contas_virtuais() as $cod => $meta) $out[$cod] = ['entradas'=>0.0,'saidas'=>0.0,'saldo'=>0.0];

    // Entradas (depósitos do Caixa)
    if (cap_tem_deposito_caixa()) {
        $r = $conn->query("SELECT tipo_deposito, COALESCE(SUM(valor_do_deposito),0) t FROM deposito_caixa GROUP BY tipo_deposito");
        while ($r && $row = $r->fetch_assoc()) {
            foreach (cap_contas_virtuais() as $cod => $meta) {
                if (in_array($row['tipo_deposito'], $meta['tipos'], true)) $out[$cod]['entradas'] += (float)$row['t'];
            }
        }
    }

    // Entradas (recebimentos de O.S.): SOMENTE o que não é espécie vai ao banco.
    // A espécie recebida na O.S. não é somada aqui — ela é depositada depois pelo
    // módulo Caixa (como depósito "Espécie"), evitando dupla contagem.
    if (cap_tem_pagamento_os()) {
        $r = $conn->query("SELECT COALESCE(SUM(total_pagamento),0) t FROM pagamento_os
                           WHERE status='pago'
                             AND LOWER(TRIM(forma_de_pagamento)) NOT IN ('espécie','especie','dinheiro')");
        $row = $r ? $r->fetch_row() : [0];
        $out['banco']['entradas'] += (float)($row[0] ?? 0);
    }
    // Saídas (contas pagas)
    $r2 = $conn->query("SELECT conta_origem, COALESCE(SUM(valor),0) t FROM contas_a_pagar WHERE status='Pago' AND conta_origem IN ('especie','banco') GROUP BY conta_origem");
    while ($r2 && $row = $r2->fetch_assoc()) {
        $cod = $row['conta_origem'];
        if (isset($out[$cod])) $out[$cod]['saidas'] += (float)$row['t'];
    }

    // Transferências entre contas virtuais (saem da origem, entram no destino)
    $r3 = $conn->query("SELECT origem, destino, COALESCE(SUM(valor),0) t FROM conta_transferencias GROUP BY origem, destino");
    while ($r3 && $row = $r3->fetch_assoc()) {
        $o = $row['origem']; $d = $row['destino']; $t = (float)$row['t'];
        if (isset($out[$o])) $out[$o]['saidas']   += $t;
        if (isset($out[$d])) $out[$d]['entradas'] += $t;
    }

    foreach ($out as $cod => $v) $out[$cod]['saldo'] = $v['entradas'] - $v['saidas'];
    return $out;
}

/* ------------------------------------------------------------------ DB */
function cap_db()
{
    static $conn = null;
    if ($conn instanceof mysqli && @$conn->ping()) return $conn;
    $conn = new mysqli('localhost', 'root', '', 'atlas');
    if ($conn->connect_error) throw new RuntimeException('Falha na conexão com o banco.');
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* --------------------------------------------------------------- schema */
function cap_ensure_schema()
{
    $conn = cap_db();

    $conn->query("CREATE TABLE IF NOT EXISTS contas_a_pagar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(180) NOT NULL,
        categoria VARCHAR(60) NULL,
        fornecedor VARCHAR(180) NULL,
        nota_fiscal VARCHAR(60) NULL,
        valor DECIMAL(12,2) NOT NULL DEFAULT 0,
        data_vencimento DATE NOT NULL,
        descricao TEXT NULL,
        recorrencia VARCHAR(20) NOT NULL DEFAULT 'Nenhuma',
        funcionario VARCHAR(120) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pendente',
        data_pagamento DATE NULL,
        caminho_anexo VARCHAR(255) NULL,
        origem_id INT NULL,
        parcela_num INT NULL,
        parcela_total INT NULL,
        parcela_grupo VARCHAR(40) NULL,
        created_at DATETIME NULL,
        INDEX idx_venc (data_vencimento),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // migrações defensivas (para bases antigas)
    $cols = [
        'categoria'      => "VARCHAR(60) NULL AFTER titulo",
        'fornecedor'     => "VARCHAR(180) NULL AFTER categoria",
        'nota_fiscal'    => "VARCHAR(60) NULL AFTER fornecedor",
        'parcela_num'    => "INT NULL",
        'parcela_total'  => "INT NULL",
        'parcela_grupo'  => "VARCHAR(40) NULL",
        'data_pagamento' => "DATE NULL AFTER status",
        'forma_pagamento'=> "VARCHAR(40) NULL AFTER data_pagamento",
        'conta_origem'   => "VARCHAR(10) NULL AFTER forma_pagamento",
        'origem_id'      => "INT NULL AFTER caminho_anexo",
        'origem_selo'    => "VARCHAR(48) NULL",
        'created_at'     => "DATETIME NULL",
    ];
    foreach ($cols as $c => $def) {
        try {
            $r = $conn->query("SHOW COLUMNS FROM contas_a_pagar LIKE '" . $conn->real_escape_string($c) . "'");
            if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE contas_a_pagar ADD COLUMN `$c` $def");
        } catch (Throwable $e) { /* coluna opcional; não bloqueia */ }
    }
    // índice único para permitir upsert das contas de fundos por período
    try {
        $ri = $conn->query("SHOW INDEX FROM contas_a_pagar WHERE Key_name='uniq_origem_selo'");
        if ($ri && $ri->num_rows === 0) $conn->query("ALTER TABLE contas_a_pagar ADD UNIQUE KEY uniq_origem_selo (origem_selo)");
    } catch (Throwable $e) { /* índice é opcional; segue sem bloquear */ }

    $conn->query("CREATE TABLE IF NOT EXISTS conta_anexos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conta_id INT NOT NULL,
        nome_original VARCHAR(255) NOT NULL,
        arquivo VARCHAR(255) NOT NULL,
        mime VARCHAR(120) NULL,
        tamanho INT NULL,
        descricao VARCHAR(255) NULL,
        enviado_por VARCHAR(120) NULL,
        enviado_em DATETIME NOT NULL,
        INDEX idx_conta (conta_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS conta_transferencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_transferencia DATE NOT NULL,
        origem VARCHAR(10) NOT NULL,
        destino VARCHAR(10) NOT NULL,
        valor DECIMAL(12,2) NOT NULL,
        observacao VARCHAR(255) NULL,
        usuario VARCHAR(120) NULL,
        created_at DATETIME NULL,
        INDEX idx_data (data_transferencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS contas_config (
        id INT PRIMARY KEY,
        email_notificacao VARCHAR(180) NULL,
        dias_aviso INT NOT NULL DEFAULT 3,
        notif_ativo TINYINT(1) NOT NULL DEFAULT 1,
        smtp_host VARCHAR(120) NULL,
        smtp_port INT NULL,
        smtp_secure VARCHAR(10) NULL,
        smtp_user VARCHAR(180) NULL,
        smtp_pass VARCHAR(255) NULL,
        smtp_from_email VARCHAR(180) NULL,
        smtp_from_name VARCHAR(120) NULL,
        ultimo_envio DATETIME NULL,
        fundos_sync_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        $rc = $conn->query("SHOW COLUMNS FROM contas_config LIKE 'fundos_sync_em'");
        if ($rc && $rc->num_rows === 0) $conn->query("ALTER TABLE contas_config ADD COLUMN fundos_sync_em DATETIME NULL");
    } catch (Throwable $e) { /* opcional */ }
    // linha única
    $conn->query("INSERT IGNORE INTO contas_config (id, dias_aviso, notif_ativo) VALUES (1, 3, 1)");

    // backfill: contas pagas sem data_pagamento assumem a data de vencimento (histórico)
    @$conn->query("UPDATE contas_a_pagar SET data_pagamento = data_vencimento WHERE status='Pago' AND data_pagamento IS NULL");
}

/* --------------------------------------------------------- configurações */
function cap_settings_get()
{
    cap_ensure_schema();
    $conn = cap_db();
    $r = $conn->query("SELECT * FROM contas_config WHERE id = 1 LIMIT 1");
    $s = $r ? $r->fetch_assoc() : null;
    if (!$s) $s = ['id'=>1,'dias_aviso'=>3,'notif_ativo'=>1];
    return $s;
}
function cap_settings_save(array $d)
{
    cap_ensure_schema();
    $conn = cap_db();
    $sql = "UPDATE contas_config SET email_notificacao=?, dias_aviso=?, notif_ativo=?,
            smtp_host=?, smtp_port=?, smtp_secure=?, smtp_user=?, smtp_pass=?, smtp_from_email=?, smtp_from_name=? WHERE id=1";
    $stmt = $conn->prepare($sql);
    $email = $d['email_notificacao'] ?? null;
    $dias  = (int)($d['dias_aviso'] ?? 3);
    $ativo = !empty($d['notif_ativo']) ? 1 : 0;
    $host  = $d['smtp_host'] ?? null;
    $port  = ($d['smtp_port'] ?? '') !== '' ? (int)$d['smtp_port'] : null;
    $sec   = $d['smtp_secure'] ?? null;
    $user  = $d['smtp_user'] ?? null;
    $pass  = $d['smtp_pass'] ?? null;
    $fmail = $d['smtp_from_email'] ?? null;
    $fname = $d['smtp_from_name'] ?? null;
    $stmt->bind_param('siisissssss', $email, $dias, $ativo, $host, $port, $sec, $user, $pass, $fmail, $fname);
    $stmt->execute(); $stmt->close();
}

/* ---------------------------------------------------------------- CSRF */
function cap_csrf_token()
{
    if (empty($_SESSION['cap_csrf'])) $_SESSION['cap_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['cap_csrf'];
}
function cap_csrf_check($t)
{
    return is_string($t) && !empty($_SESSION['cap_csrf']) && hash_equals($_SESSION['cap_csrf'], $t);
}

/* --------------------------------------------------------- utilitários */
function cap_dir_anexos()
{
    $d = __DIR__ . '/anexos';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d.'/.htaccess')) @file_put_contents($d.'/.htaccess', "php_flag engine off\nOptions -Indexes\n");
    return $d;
}
function cap_log($m)
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir.'/contas_'.date('Y-m').'.log', '['.date('Y-m-d H:i:s').'] '.$m.PHP_EOL, FILE_APPEND);
}
function cap_money($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function cap_parse_money($s)
{
    $s = trim((string)$s);
    $s = str_replace(['R$', ' '], '', $s);
    if (strpos($s, ',') !== false) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
    return (float)$s;
}
/** Status efetivo: "Atrasado" quando pendente e vencida. */
function cap_status_efetivo($conta)
{
    if (($conta['status'] ?? '') === 'Pago') return 'Pago';
    $venc = $conta['data_vencimento'] ?? null;
    if ($venc && strtotime($venc) < strtotime(date('Y-m-d'))) return 'Atrasado';
    return 'Pendente';
}

/* --------------------------------------------------- recorrência (próxima) */
function cap_proximo_vencimento($data, $recorrencia)
{
    $ts = strtotime($data);
    switch ($recorrencia) {
        case 'Mensal':
            $d = new DateTime($data);
            $day = (int)$d->format('d');
            $d->modify('first day of next month');
            $last = (int)$d->format('t');
            $d->setDate((int)$d->format('Y'), (int)$d->format('m'), min($day, $last));
            return $d->format('Y-m-d');
        case 'Semanal': return date('Y-m-d', strtotime('+1 week', $ts));
        case 'Anual':   return date('Y-m-d', strtotime('+1 year', $ts));
        default:        return null;
    }
}

/* ------------------------------------------------------------ e-mail/alerta */
function cap_enviar_alertas($echo = false)
{
    cap_ensure_schema();
    $conn = cap_db();
    $cfg = cap_settings_get();
    if (empty($cfg['notif_ativo']) || empty($cfg['email_notificacao'])) {
        if ($echo) echo "Notificações desativadas ou e-mail não configurado.\n";
        return ['status' => 'skip', 'message' => 'Notificações desativadas ou sem e-mail.'];
    }
    $dias = max(0, (int)$cfg['dias_aviso']);

    $vencidas = $conn->query("SELECT * FROM contas_a_pagar WHERE status='Pendente' AND data_vencimento < CURDATE() ORDER BY data_vencimento")->fetch_all(MYSQLI_ASSOC);
    $prox = $conn->prepare("SELECT * FROM contas_a_pagar WHERE status='Pendente' AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY data_vencimento");
    $prox->bind_param('i', $dias); $prox->execute();
    $prestes = $prox->get_result()->fetch_all(MYSQLI_ASSOC); $prox->close();

    if (empty($vencidas) && empty($prestes)) {
        if ($echo) echo "Nenhuma conta vencida ou a vencer.\n";
        return ['status' => 'empty', 'message' => 'Nenhuma conta a alertar.'];
    }

    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        if (!empty($cfg['smtp_host'])) {
            $mail->isSMTP();
            $mail->Host = $cfg['smtp_host'];
            $mail->SMTPAuth = !empty($cfg['smtp_user']);
            if (!empty($cfg['smtp_user'])) { $mail->Username = $cfg['smtp_user']; $mail->Password = $cfg['smtp_pass']; }
            if (!empty($cfg['smtp_secure'])) $mail->SMTPSecure = $cfg['smtp_secure'];
            if (!empty($cfg['smtp_port'])) $mail->Port = (int)$cfg['smtp_port'];
        }
        $fromEmail = $cfg['smtp_from_email'] ?: ($cfg['smtp_user'] ?: 'no-reply@atlas.local');
        $fromName  = $cfg['smtp_from_name'] ?: 'Atlas - Contas a Pagar';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($cfg['email_notificacao']);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = 'Atlas · Contas vencidas e a vencer';
        $mail->Body = cap_render_email($vencidas, $prestes, $dias);
        $mail->send();
        $conn->query("UPDATE contas_config SET ultimo_envio = NOW() WHERE id = 1");
        cap_log('Alerta enviado para ' . $cfg['email_notificacao'] . ' (' . count($vencidas) . ' vencidas, ' . count($prestes) . ' a vencer)');
        if ($echo) echo "E-mail enviado para {$cfg['email_notificacao']}.\n";
        return ['status' => 'success', 'vencidas' => count($vencidas), 'prestes' => count($prestes)];
    } catch (\Throwable $e) {
        cap_log('ERRO ao enviar alerta: ' . $e->getMessage());
        if ($echo) echo "Erro: " . $e->getMessage() . "\n";
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function cap_render_email($vencidas, $prestes, $dias)
{
    $tot = function ($rows) { $s = 0; foreach ($rows as $r) $s += (float)$r['valor']; return $s; };
    $tbl = function ($rows, $cor) {
        $h = "<table style='border-collapse:collapse;width:100%;font-family:Arial,sans-serif;font-size:13px'>
              <thead><tr style='background:$cor'>
              <th style='border:1px solid #ddd;padding:8px;text-align:left'>Título</th>
              <th style='border:1px solid #ddd;padding:8px;text-align:left'>Categoria</th>
              <th style='border:1px solid #ddd;padding:8px;text-align:right'>Valor</th>
              <th style='border:1px solid #ddd;padding:8px'>Vencimento</th></tr></thead><tbody>";
        foreach ($rows as $c) {
            $h .= "<tr>
                <td style='border:1px solid #ddd;padding:8px'>" . htmlspecialchars($c['titulo']) . "</td>
                <td style='border:1px solid #ddd;padding:8px'>" . htmlspecialchars($c['categoria'] ?? '') . "</td>
                <td style='border:1px solid #ddd;padding:8px;text-align:right'>" . cap_money($c['valor']) . "</td>
                <td style='border:1px solid #ddd;padding:8px;text-align:center'>" . date('d/m/Y', strtotime($c['data_vencimento'])) . "</td></tr>";
        }
        return $h . "</tbody></table>";
    };
    $html = "<div style='font-family:Arial,sans-serif;color:#111'>
      <h2 style='color:#4f46e5'>Atlas · Alerta de Contas a Pagar</h2>";
    $html .= "<h3 style='color:#b91c1c'>Vencidas (" . count($vencidas) . ") — total " . cap_money($tot($vencidas)) . "</h3>";
    $html .= empty($vencidas) ? "<p>Nenhuma.</p>" : $tbl($vencidas, '#fde8e8');
    $html .= "<h3 style='color:#b45309'>A vencer nos próximos {$dias} dia(s) (" . count($prestes) . ") — total " . cap_money($tot($prestes)) . "</h3>";
    $html .= empty($prestes) ? "<p>Nenhuma.</p>" : $tbl($prestes, '#fef3c7');
    $html .= "<p style='color:#6b7280;font-size:12px;margin-top:16px'>Enviado automaticamente pelo módulo Contas a Pagar do Atlas.</p></div>";
    return $html;
}
