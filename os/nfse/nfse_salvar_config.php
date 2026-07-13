<?php
/** ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nfse_json(['ok' => false, 'mensagem' => 'Método inválido.'], 405);
}

try {
    nfse_migrar();
    $pdo = nfse_pdo();

    $s = static fn(string $k, $d = null) => isset($_POST[$k]) && $_POST[$k] !== '' ? trim((string) $_POST[$k]) : $d;
    $b = static fn(string $k) => (isset($_POST[$k]) && ($_POST[$k] === '1' || $_POST[$k] === 'on')) ? 1 : 0;
    $n = static fn(string $k, float $d = 0.0) => (float) str_replace(',', '.', (string) ($_POST[$k] ?? $d));

    $ativo    = $b('ativo');
    $ambiente = in_array($s('ambiente', '2'), ['1', '2'], true) ? $s('ambiente', '2') : '2';
    $modo     = in_array($s('modo_emissao', 'consolidado'), ['consolidado', 'individualizado'], true)
                ? $s('modo_emissao') : 'consolidado';
    $base     = in_array($s('base_calculo', 'emolumentos'), ['emolumentos', 'emolumentos_taxas', 'total'], true)
                ? $s('base_calculo') : 'emolumentos';

    $prestDoc = nfse_so_digitos($s('prest_doc', ''));
    $codMun   = nfse_so_digitos($s('cod_municipio', ''));

    // ---- Validações de negócio -------------------------------------
    if ($prestDoc !== '' && !in_array(strlen($prestDoc), [11, 14], true)) {
        nfse_json(['ok' => false, 'mensagem' => 'CPF/CNPJ do prestador deve ter 11 ou 14 dígitos.']);
    }
    if ($codMun !== '' && strlen($codMun) !== 7) {
        nfse_json(['ok' => false, 'mensagem' => 'O código IBGE do município deve ter exatamente 7 dígitos.']);
    }

    $aliquota = $n('aliquota_iss', 5.0);
    $reducao  = $n('reducao_base', 0.0);

    if ($aliquota < 0 || $aliquota > 100) {
        nfse_json(['ok' => false, 'mensagem' => 'Alíquota do ISSQN fora do intervalo 0–100%.']);
    }
    if ($reducao < 0 || $reducao > 100) {
        nfse_json(['ok' => false, 'mensagem' => 'Redução da base fora do intervalo 0–100%.']);
    }

    $numeroDps = max(0, (int) ($_POST['ultimo_numero_dps'] ?? 0));

    $cfgAtual = nfse_config(true);

    // Guarda-corpos: não deixa ligar a emissão com a configuração incompleta.
    if ($ativo) {
        $simulado = array_merge($cfgAtual, [
            'prest_doc' => $prestDoc, 'prest_nome' => $s('prest_nome', ''),
            'cod_municipio' => $codMun, 'prest_cep' => nfse_so_digitos($s('prest_cep', '')),
            'prest_logradouro' => $s('prest_logradouro', ''), 'prest_numero' => $s('prest_numero', ''),
            'prest_bairro' => $s('prest_bairro', ''), 'aliquota_iss' => $aliquota,
        ]);
        $faltas = nfse_pendencias($simulado);
        if ($faltas) {
            nfse_json(['ok' => false, 'mensagem' => 'Não é possível habilitar a emissão. Pendências: ' . implode(', ', $faltas) . '.']);
        }
    }

    // Retrocesso de numeração é perigoso: bloqueia (evita DPS duplicada).
    if ($numeroDps < (int) $cfgAtual['ultimo_numero_dps'] && $ambiente === '1') {
        nfse_json([
            'ok' => false,
            'mensagem' => 'Em produção o número da DPS não pode retroceder (atual: ' . $cfgAtual['ultimo_numero_dps'] . ').',
        ]);
    }

    $sql = "UPDATE nfse_config SET
                ativo = :ativo, ambiente = :ambiente, emissao_automatica = :auto,
                modo_emissao = :modo, identificar_tomador = :idtom,
                prest_tipo = :ptipo, prest_doc = :pdoc, prest_im = :pim, prest_nome = :pnome,
                prest_cep = :pcep, prest_logradouro = :plog, prest_numero = :pnum,
                prest_complemento = :pcpl, prest_bairro = :pbai,
                prest_fone = :pfone, prest_email = :pmail,
                cod_municipio = :cmun, serie_dps = :serie, ultimo_numero_dps = :ndps,
                ctrib_nac = :ctnac, ctrib_mun = :ctmun, cnae = :cnae,
                base_calculo = :base, reducao_base = :red, aliquota_iss = :aliq,
                reg_esp_trib = :regesp, op_simp_nac = :simples, reg_ap_trib_sn = :regap,
                p_tot_trib_sn = :ptotsn, cst_piscofins = :cst,
                atualizado_em = NOW(), atualizado_por = :usr
            WHERE id = 1";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':ativo'   => $ativo,
        ':ambiente' => $ambiente,
        ':auto'    => $b('emissao_automatica'),
        ':modo'    => $modo,
        ':idtom'   => $b('identificar_tomador'),
        ':ptipo'   => in_array($s('prest_tipo', 'CNPJ'), ['CPF', 'CNPJ'], true) ? $s('prest_tipo') : 'CNPJ',
        ':pdoc'    => $prestDoc ?: null,
        ':pim'     => $s('prest_im'),
        ':pnome'   => $s('prest_nome'),
        ':pcep'    => nfse_so_digitos($s('prest_cep', '')) ?: null,
        ':plog'    => $s('prest_logradouro'),
        ':pnum'    => $s('prest_numero'),
        ':pcpl'    => $s('prest_complemento'),
        ':pbai'    => $s('prest_bairro'),
        ':pfone'   => nfse_so_digitos($s('prest_fone', '')) ?: null,
        ':pmail'   => $s('prest_email'),
        ':cmun'    => $codMun ?: null,
        ':serie'   => $s('serie_dps', '1'),
        ':ndps'    => $numeroDps,
        ':ctnac'   => nfse_so_digitos($s('ctrib_nac', '210101')) ?: '210101',
        ':ctmun'   => $s('ctrib_mun'),
        ':cnae'    => nfse_so_digitos($s('cnae', '')) ?: null,
        ':base'    => $base,
        ':red'     => $reducao,
        ':aliq'    => $aliquota,
        ':regesp'  => $s('reg_esp_trib', '4'),
        ':simples' => $s('op_simp_nac', '1'),
        ':regap'   => in_array($s('reg_ap_trib_sn', ''), ['1', '2', '3'], true) ? $s('reg_ap_trib_sn') : null,
        ':ptotsn'  => max(0.0, (float) str_replace(',', '.', (string) ($_POST['p_tot_trib_sn'] ?? 0))),
        ':cst'     => $s('cst_piscofins', '08'),
        ':usr'     => $_SESSION['username'] ?? 'sistema',
    ]);

    nfse_log('config', 'Configuração da NFS-e atualizada. Ambiente: ' . ($ambiente === '1' ? 'produção' : 'homologação') . '; emissão ' . ($ativo ? 'ativa' : 'inativa') . '.', 'info');

    $avisos = [];
    if ($ativo && $ambiente === '1') {
        $avisos[] = 'Atenção: ambiente de PRODUÇÃO. As notas emitidas terão validade fiscal.';
    }
    if ($modo === 'consolidado' && nfse_exige_individualizacao()) {
        $avisos[] = 'O regime transitório de 2026 terminou: o sistema emitirá individualizado por ato.';
    }

    nfse_json(['ok' => true, 'mensagem' => implode(' ', $avisos)]);
} catch (Throwable $e) {
    error_log('[nfse_salvar_config] ' . $e->getMessage());
    nfse_json(['ok' => false, 'mensagem' => $e->getMessage()], 500);
}
