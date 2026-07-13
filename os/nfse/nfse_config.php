<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional
 * Página de configuração da integração com o Emissor Nacional da NFS-e.
 * Acesso restrito a administradores.
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';

$diag = nfse_diagnostico();

$erroFatal = null;
$cfg = [];
try {
    nfse_migrar();
    $cfg = nfse_config(true);
} catch (Throwable $e) {
    $erroFatal = $e->getMessage();
}

$temCert   = !empty($cfg['cert_blob']);
$pendencias = $cfg ? nfse_pendencias($cfg) : ['Banco de dados indisponível'];
$diasVenc  = null;
if ($temCert && !empty($cfg['cert_validade'])) {
    $diasVenc = (int) floor((strtotime($cfg['cert_validade']) - time()) / 86400);
}

$v = static fn($k, $d = '') => htmlspecialchars((string) ($cfg[$k] ?? $d), ENT_QUOTES, 'UTF-8');
$sel = static fn($k, $val) => (string) ($cfg[$k] ?? '') === (string) $val ? 'selected' : '';
$chk = static fn($k) => !empty($cfg[$k]) ? 'checked' : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NFS-e Nacional — Configuração</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
    <style>
        .nfse-card{border:1px solid #e2e8f0;border-radius:12px;background:#fff;margin-bottom:20px;overflow:hidden}
        .nfse-card > h5{margin:0;padding:14px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.95rem;font-weight:700;color:#0f172a}
        .nfse-card > h5 i{margin-right:8px;color:#0f766e}
        .nfse-card .body{padding:18px}
        .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:700}
        .pill-ok{background:#dcfce7;color:#166534}
        .pill-off{background:#fee2e2;color:#991b1b}
        .pill-warn{background:#fef3c7;color:#92400e}
        .req-list{margin:0;padding-left:18px;font-size:.85rem}
        .diag li{font-size:.85rem;list-style:none}
        .diag i.ok{color:#16a34a}
        .diag i.bad{color:#dc2626}
        .helper{font-size:.78rem;color:#64748b;margin-top:2px}
        .legal{font-size:.8rem;color:#475569;background:#f1f5f9;border-left:4px solid #0f766e;padding:12px 14px;border-radius:6px}
        .legal b{color:#0f172a}
        .cert-box{border:1px dashed #cbd5e1;border-radius:10px;padding:16px;background:#f8fafc}
        .sticky-actions{position:sticky;bottom:0;background:#fff;border-top:1px solid #e2e8f0;padding:12px 0;z-index:5}
    </style>
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">

    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <h3 class="m-0">Nota Fiscal de Serviço Eletrônica — Padrão Nacional</h3>
      <div>
        <?php if (!empty($cfg['ativo']) && !$pendencias): ?>
          <span class="pill pill-ok"><i class="fa fa-check-circle"></i> Emissão habilitada</span>
        <?php elseif ($pendencias): ?>
          <span class="pill pill-warn"><i class="fa fa-exclamation-triangle"></i> Configuração incompleta</span>
        <?php else: ?>
          <span class="pill pill-off"><i class="fa fa-ban"></i> Emissão desativada</span>
        <?php endif; ?>
        <span class="pill <?= ($cfg['ambiente'] ?? '2') === '1' ? 'pill-off' : 'pill-warn' ?>">
          <?= ($cfg['ambiente'] ?? '2') === '1' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?>
        </span>
      </div>
    </div>
    <hr>

    <?php if ($erroFatal): ?>
      <div class="alert alert-danger"><b>Erro:</b> <?= htmlspecialchars($erroFatal) ?></div>
    <?php endif; ?>

    <div class="legal mb-4">
      <b>Base normativa aplicada.</b> Os serviços notariais e de registro estão no subitem 21.01 da lista anexa à
      LC 116/2003 — código de tributação nacional <b>210101</b> — e, por força do art. 62, §1º, I, da
      <b>LC 214/2025</b>, passaram a emitir NFS-e exclusivamente pelo Ambiente Nacional a partir de 01/01/2026.
      Durante o exercício de 2026 admite-se a NFS-e <b>consolidada</b> e a opção "Tomador não informado";
      a partir de <b>01/01/2027</b> a emissão deve ser <b>individualizada por ato</b>, com identificação do tomador
      — o sistema alterna sozinho na virada. O fato gerador é a <b>liquidação do ato</b>: o depósito prévio é
      adiantamento e não gera nota. O regime especial de tributação é <b>4 — Notário ou Registrador</b>.
    </div>

    <?php if ($pendencias): ?>
      <div class="alert alert-warning">
        <b>Faltam informações para habilitar a emissão:</b>
        <ul class="req-list mt-2 mb-0">
          <?php foreach ($pendencias as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-lg-4">
        <div class="nfse-card">
          <h5><i class="fa fa-stethoscope"></i> Diagnóstico do ambiente</h5>
          <div class="body">
            <ul class="diag pl-0 mb-0">
              <?php
              $checks = [
                'PHP ≥ 8.1 (atual: ' . $diag['php_versao'] . ')' => $diag['php_ok'],
                'Extensão openssl' => $diag['openssl'],
                'Extensão curl'    => $diag['curl'],
                'Extensão dom'     => $diag['dom'],
                'Compressão gzip'  => $diag['zlib'],
                'SDK nfse-nacional/nfse-php' => $diag['sdk'],
                'Pasta certs/ gravável'      => $diag['certs_dir'],
              ];
              foreach ($checks as $rot => $ok): ?>
                <li><i class="fa <?= $ok ? 'fa-check-circle ok' : 'fa-times-circle bad' ?>"></i> <?= $rot ?></li>
              <?php endforeach; ?>
            </ul>
            <?php if (!$diag['sdk']): ?>
              <div class="alert alert-danger mt-3 mb-0" style="font-size:.8rem">
                SDK ausente. Na pasta <code>os/nfse</code> execute:<br>
                <code>composer require nfse-nacional/nfse-php</code>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="nfse-card">
          <h5><i class="fa fa-certificate"></i> Certificado digital A1</h5>
          <div class="body">
            <?php if ($temCert): ?>
              <p class="mb-1"><b>Titular:</b> <?= $v('cert_titular') ?></p>
              <p class="mb-1"><b>Arquivo:</b> <?= $v('cert_nome') ?></p>
              <p class="mb-2"><b>Validade:</b>
                <?= !empty($cfg['cert_validade']) ? date('d/m/Y H:i', strtotime($cfg['cert_validade'])) : '—' ?>
                <?php if ($diasVenc !== null): ?>
                  <?php if ($diasVenc < 0): ?>
                    <span class="pill pill-off">VENCIDO</span>
                  <?php elseif ($diasVenc <= 30): ?>
                    <span class="pill pill-warn">vence em <?= $diasVenc ?> dia(s)</span>
                  <?php else: ?>
                    <span class="pill pill-ok"><?= $diasVenc ?> dias</span>
                  <?php endif; ?>
                <?php endif; ?>
              </p>
              <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerCertificado()">
                <i class="fa fa-trash"></i> Remover certificado
              </button>
            <?php else: ?>
              <p class="text-muted" style="font-size:.85rem">Nenhum certificado instalado. A emissão permanece bloqueada.</p>
            <?php endif; ?>

            <div class="cert-box mt-3">
              <div class="form-group mb-2">
                <label for="cert_arquivo" style="font-size:.85rem"><b><?= $temCert ? 'Substituir' : 'Enviar' ?> arquivo .pfx / .p12</b></label>
                <input type="file" class="form-control-file" id="cert_arquivo" accept=".pfx,.p12">
              </div>
              <div class="form-group mb-2">
                <label for="cert_senha" style="font-size:.85rem"><b>Senha do certificado</b></label>
                <input type="password" class="form-control" id="cert_senha" autocomplete="new-password">
              </div>
              <button type="button" class="btn btn-primary btn-sm btn-block" onclick="enviarCertificado()">
                <i class="fa fa-upload"></i> Validar e instalar
              </button>
              <div class="helper mt-2">
                O <code>.pfx</code> e a senha são cifrados com AES-256-GCM antes de ir ao banco.
                A chave mestra fica em <code>certs/.nfse.key</code> (bloqueada por <code>.htaccess</code>).
                Faça backup dessa chave junto com o banco.
              </div>
            </div>
          </div>
        </div>

        <div class="nfse-card">
          <h5><i class="fa fa-plug"></i> Testes de conexão</h5>
          <div class="body">
            <button type="button" class="btn btn-outline-secondary btn-sm btn-block mb-2" onclick="testar('convenio')">
              <i class="fa fa-institution"></i> Verificar adesão do município
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm btn-block" onclick="testar('aliquota')">
              <i class="fa fa-percent"></i> Consultar alíquota do 210101
            </button>
            <div class="helper mt-2">Ambos usam o certificado instalado e o ambiente selecionado.</div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <form id="formNfse">
          <div class="nfse-card">
            <h5><i class="fa fa-toggle-on"></i> Operação</h5>
            <div class="body">
              <div class="form-row">
                <div class="form-group col-md-4">
                  <label for="ambiente">Ambiente</label>
                  <select class="form-control" id="ambiente" name="ambiente">
                    <option value="2" <?= $sel('ambiente', '2') ?>>Homologação (produção restrita)</option>
                    <option value="1" <?= $sel('ambiente', '1') ?>>Produção</option>
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label for="serie_dps">Série da DPS</label>
                  <input type="text" class="form-control" id="serie_dps" name="serie_dps" maxlength="5" value="<?= $v('serie_dps', '1') ?>">
                </div>
                <div class="form-group col-md-4">
                  <label for="ultimo_numero_dps">Último nº de DPS emitido</label>
                  <input type="number" class="form-control" id="ultimo_numero_dps" name="ultimo_numero_dps" min="0" value="<?= $v('ultimo_numero_dps', '0') ?>">
                  <div class="helper">A próxima nota usará este número + 1.</div>
                </div>
              </div>

              <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input" id="ativo" name="ativo" <?= $chk('ativo') ?>>
                <label class="custom-control-label" for="ativo"><b>Habilitar emissão de NFS-e</b></label>
              </div>
              <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input" id="emissao_automatica" name="emissao_automatica" <?= $chk('emissao_automatica') ?>>
                <label class="custom-control-label" for="emissao_automatica">
                  Emitir automaticamente ao liquidar todos os atos da O.S.
                </label>
              </div>
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="identificar_tomador" name="identificar_tomador" <?= $chk('identificar_tomador') ?>>
                <label class="custom-control-label" for="identificar_tomador">
                  Exigir identificação do tomador (CPF/CNPJ do apresentante)
                </label>
                <div class="helper">Desmarcado, usa-se "Tomador não informado" — facultado apenas durante 2026.</div>
              </div>

              <div class="form-group mt-3 mb-0">
                <label for="modo_emissao">Modo de emissão</label>
                <select class="form-control" id="modo_emissao" name="modo_emissao">
                  <option value="consolidado" <?= $sel('modo_emissao', 'consolidado') ?>>Consolidado — uma NFS-e por Ordem de Serviço (regime de 2026)</option>
                  <option value="individualizado" <?= $sel('modo_emissao', 'individualizado') ?>>Individualizado — uma NFS-e por ato praticado (obrigatório a partir de 2027)</option>
                </select>
                <div class="helper">A partir de 01/01/2027 o sistema força o modo individualizado, independentemente desta escolha.</div>
              </div>
            </div>
          </div>

          <div class="nfse-card">
            <h5><i class="fa fa-building"></i> Prestador (serventia)</h5>
            <div class="body">
              <div class="form-row">
                <div class="form-group col-md-3">
                  <label for="prest_tipo">Tipo de inscrição</label>
                  <select class="form-control" id="prest_tipo" name="prest_tipo">
                    <option value="CNPJ" <?= $sel('prest_tipo', 'CNPJ') ?>>CNPJ</option>
                    <option value="CPF" <?= $sel('prest_tipo', 'CPF') ?>>CPF (delegatário)</option>
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label for="prest_doc">CPF/CNPJ *</label>
                  <input type="text" class="form-control" id="prest_doc" name="prest_doc" value="<?= $v('prest_doc') ?>" placeholder="somente números">
                </div>
                <div class="form-group col-md-5">
                  <label for="prest_im">Inscrição Municipal</label>
                  <input type="text" class="form-control" id="prest_im" name="prest_im" value="<?= $v('prest_im') ?>">
                  <div class="helper">Exigida pela maioria dos municípios para habilitar o emissor.</div>
                </div>
              </div>

              <div class="form-group">
                <label for="prest_nome">Nome / Razão social *</label>
                <input type="text" class="form-control" id="prest_nome" name="prest_nome" maxlength="150" value="<?= $v('prest_nome') ?>">
              </div>

              <div class="form-row">
                <div class="form-group col-md-3">
                  <label for="cod_municipio">Código IBGE do município *</label>
                  <input type="text" class="form-control" id="cod_municipio" name="cod_municipio" maxlength="7" value="<?= $v('cod_municipio') ?>" placeholder="7 dígitos">
                </div>
                <div class="form-group col-md-3">
                  <label for="prest_cep">CEP *</label>
                  <input type="text" class="form-control" id="prest_cep" name="prest_cep" maxlength="9" value="<?= $v('prest_cep') ?>">
                </div>
                <div class="form-group col-md-6">
                  <label for="prest_logradouro">Logradouro *</label>
                  <input type="text" class="form-control" id="prest_logradouro" name="prest_logradouro" value="<?= $v('prest_logradouro') ?>">
                </div>
              </div>

              <div class="form-row">
                <div class="form-group col-md-2">
                  <label for="prest_numero">Número *</label>
                  <input type="text" class="form-control" id="prest_numero" name="prest_numero" value="<?= $v('prest_numero') ?>">
                </div>
                <div class="form-group col-md-4">
                  <label for="prest_complemento">Complemento</label>
                  <input type="text" class="form-control" id="prest_complemento" name="prest_complemento" value="<?= $v('prest_complemento') ?>">
                </div>
                <div class="form-group col-md-6">
                  <label for="prest_bairro">Bairro *</label>
                  <input type="text" class="form-control" id="prest_bairro" name="prest_bairro" value="<?= $v('prest_bairro') ?>">
                </div>
              </div>

              <div class="form-row">
                <div class="form-group col-md-4">
                  <label for="prest_fone">Telefone</label>
                  <input type="text" class="form-control" id="prest_fone" name="prest_fone" value="<?= $v('prest_fone') ?>">
                </div>
                <div class="form-group col-md-8">
                  <label for="prest_email">E-mail</label>
                  <input type="email" class="form-control" id="prest_email" name="prest_email" value="<?= $v('prest_email') ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="nfse-card">
            <h5><i class="fa fa-calculator"></i> Tributação</h5>
            <div class="body">
              <div class="form-row">
                <div class="form-group col-md-3">
                  <label for="ctrib_nac">Código de tributação nacional</label>
                  <input type="text" class="form-control" id="ctrib_nac" name="ctrib_nac" maxlength="6" value="<?= $v('ctrib_nac', '210101') ?>">
                  <div class="helper">21.01.01 — registros públicos, cartorários e notariais.</div>
                </div>
                <div class="form-group col-md-3">
                  <label for="ctrib_mun">Código municipal</label>
                  <input type="text" class="form-control" id="ctrib_mun" name="ctrib_mun" value="<?= $v('ctrib_mun') ?>">
                </div>
                <div class="form-group col-md-3">
                  <label for="cnae">CNAE</label>
                  <input type="text" class="form-control" id="cnae" name="cnae" maxlength="7" value="<?= $v('cnae') ?>">
                </div>
                <div class="form-group col-md-3">
                  <label for="aliquota_iss">Alíquota do ISSQN (%) *</label>
                  <input type="number" step="0.01" min="0" max="100" class="form-control" id="aliquota_iss" name="aliquota_iss" value="<?= $v('aliquota_iss', '5.00') ?>">
                </div>
              </div>

              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="base_calculo">Composição do valor do serviço</label>
                  <select class="form-control" id="base_calculo" name="base_calculo">
                    <option value="emolumentos" <?= $sel('base_calculo', 'emolumentos') ?>>Somente emolumentos (receita do delegatário) — recomendado</option>
                    <option value="emolumentos_taxas" <?= $sel('base_calculo', 'emolumentos_taxas') ?>>Emolumentos + taxas e fundos (FERC, FADEP, FEMP, FERRFIS)</option>
                    <option value="total" <?= $sel('base_calculo', 'total') ?>>Total cobrado do usuário</option>
                  </select>
                  <div class="helper">
                    As parcelas repassadas a fundos estaduais não são receita do notário e, na opção recomendada,
                    ficam de fora do valor do serviço. O item interno "ISS" da O.S. nunca entra.
                  </div>
                </div>
                <div class="form-group col-md-3">
                  <label for="reducao_base">Redução da base (%)</label>
                  <input type="number" step="0.01" min="0" max="100" class="form-control" id="reducao_base" name="reducao_base" value="<?= $v('reducao_base', '12.00') ?>">
                  <div class="helper">Ex.: 12% (fator 0,88) para os fundos estaduais.</div>
                </div>
                <div class="form-group col-md-3">
                  <label for="reducao_modo">Forma da redução</label>
                  <select class="form-control" id="reducao_modo" name="reducao_modo">
                    <option value="grupo" <?= $sel('reducao_modo', 'grupo') ?>>Grupo de dedução (vDedRed)</option>
                    <option value="embutida" <?= $sel('reducao_modo', 'embutida') ?>>Embutida no valor do serviço</option>
                  </select>
                  <div class="helper">"Embutida" já reduz o <code>vServ</code> e não envia <code>vDedRed</code>. Use quando o município recusa o grupo de dedução (erro E0440).</div>
                </div>
                <div class="form-group col-md-3">
                  <label for="cst_piscofins">CST PIS/COFINS</label>
                  <select class="form-control" id="cst_piscofins" name="cst_piscofins">
                    <option value="08" <?= $sel('cst_piscofins', '08') ?>>08 — Sem incidência</option>
                    <option value="07" <?= $sel('cst_piscofins', '07') ?>>07 — Isenta</option>
                    <option value="01" <?= $sel('cst_piscofins', '01') ?>>01 — Alíquota básica</option>
                    <option value="99" <?= $sel('cst_piscofins', '99') ?>>99 — Outras operações</option>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="reg_esp_trib">Regime especial de tributação</label>
                  <select class="form-control" id="reg_esp_trib" name="reg_esp_trib">
                    <option value="4" <?= $sel('reg_esp_trib', '4') ?>>4 — Notário ou Registrador</option>
                    <option value="0" <?= $sel('reg_esp_trib', '0') ?>>0 — Nenhum</option>
                    <option value="2" <?= $sel('reg_esp_trib', '2') ?>>2 — Estimativa</option>
                    <option value="5" <?= $sel('reg_esp_trib', '5') ?>>5 — Profissional autônomo</option>
                  </select>
                </div>
                <div class="form-group col-md-6">
                  <label for="op_simp_nac">Simples Nacional</label>
                  <select class="form-control" id="op_simp_nac" name="op_simp_nac">
                    <option value="1" <?= $sel('op_simp_nac', '1') ?>>1 — Não optante</option>
                    <option value="3" <?= $sel('op_simp_nac', '3') ?>>3 — Optante ME/EPP</option>
                  </select>
                  <div class="helper">Serventias extrajudiciais são vedadas ao Simples Nacional (LC 123/2006, art. 17, XI).</div>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group col-md-6">
                  <label for="reg_ap_trib_sn">Regime de apuração (Simples Nacional)</label>
                  <select class="form-control" id="reg_ap_trib_sn" name="reg_ap_trib_sn">
                    <option value="" <?= $sel('reg_ap_trib_sn', '') ?>>— (não optante)</option>
                    <option value="1" <?= $sel('reg_ap_trib_sn', '1') ?>>1 — Federais e ISSQN pelo SN</option>
                    <option value="2" <?= $sel('reg_ap_trib_sn', '2') ?>>2 — Federais pelo SN, ISSQN pelo regime normal</option>
                    <option value="3" <?= $sel('reg_ap_trib_sn', '3') ?>>3 — MEI</option>
                  </select>
                  <div class="helper">Obrigatório para optantes do Simples Nacional (ME/EPP ou MEI); a SEFIN recusa a DPS sem este campo. Ignorado para não optantes.</div>
                </div>
                <div class="form-group col-md-6">
                  <label for="p_tot_trib_sn">% total de tributos (Simples Nacional)</label>
                  <input type="text" class="form-control" id="p_tot_trib_sn" name="p_tot_trib_sn" value="<?= $v('p_tot_trib_sn') ?>" placeholder="ex.: 6,00">
                  <div class="helper">Alíquota efetiva do Simples (pTotTribSN), exigida no totTrib para optantes. Deixe 0 para não optantes; num optante, se ficar 0 o sistema usa 6,00.</div>
                </div>
              </div>

              <div class="alert alert-secondary mb-0" style="font-size:.82rem">
                <b>Simulação:</b> emolumentos de R$ 100,00 →
                base R$ <span id="simBase">88,00</span> → ISSQN R$ <span id="simIss">4,40</span>.
              </div>
            </div>
          </div>

          <div class="sticky-actions text-right">
            <a href="nfse_notas.php" class="btn btn-outline-secondary btn-sm">
              <i class="fa fa-list"></i> Notas emitidas
            </a>
            <button type="button" class="btn btn-success" onclick="salvar()">
              <i class="fa fa-save"></i> Salvar configuração
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="../../script/jquery-3.5.1.min.js"></script>
<script src="../../script/bootstrap.min.js"></script>
<script src="../../script/bootstrap.bundle.min.js"></script>
<script src="../../script/sweetalert2.js"></script>
<script>
function simular() {
    const red = parseFloat(document.getElementById('reducao_base').value) || 0;
    const aliq = parseFloat(document.getElementById('aliquota_iss').value) || 0;
    const base = 100 * (1 - red / 100);
    document.getElementById('simBase').textContent = base.toFixed(2).replace('.', ',');
    document.getElementById('simIss').textContent = (base * aliq / 100).toFixed(2).replace('.', ',');
}
['reducao_base', 'aliquota_iss'].forEach(id => document.getElementById(id).addEventListener('input', simular));
simular();

function salvar() {
    const fd = new FormData(document.getElementById('formNfse'));
    ['ativo', 'emissao_automatica', 'identificar_tomador'].forEach(id => {
        fd.set(id, document.getElementById(id).checked ? '1' : '0');
    });

    fetch('nfse_salvar_config.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Configuração salva', text: res.mensagem || '' })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Não foi possível salvar', text: res.mensagem || 'Erro desconhecido.' });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de comunicação com o servidor.' }));
}

function enviarCertificado() {
    const arq = document.getElementById('cert_arquivo').files[0];
    const senha = document.getElementById('cert_senha').value;

    if (!arq)   { Swal.fire({ icon: 'warning', title: 'Selecione o arquivo .pfx' }); return; }
    if (!senha) { Swal.fire({ icon: 'warning', title: 'Informe a senha do certificado' }); return; }

    const fd = new FormData();
    fd.append('certificado', arq);
    fd.append('senha', senha);

    Swal.fire({ title: 'Validando certificado...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('nfse_upload_certificado.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Certificado instalado',
                    html: `<b>Titular:</b> ${res.titular}<br><b>Válido até:</b> ${res.valido_ate}`
                }).then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Certificado rejeitado', text: res.mensagem });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao enviar o certificado.' }));
}

function removerCertificado() {
    Swal.fire({
        icon: 'warning',
        title: 'Remover certificado?',
        text: 'A emissão de NFS-e será desativada imediatamente.',
        showCancelButton: true,
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch('nfse_upload_certificado.php', {
            method: 'POST',
            body: new URLSearchParams({ acao: 'remover' })
        })
            .then(r => r.json())
            .then(res => {
                if (res.ok) Swal.fire({ icon: 'success', title: 'Certificado removido' }).then(() => location.reload());
                else Swal.fire({ icon: 'error', title: 'Erro', text: res.mensagem });
            });
    });
}

function testar(tipo) {
    Swal.fire({ title: 'Consultando o Ambiente Nacional...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('nfse_testar.php?tipo=' + encodeURIComponent(tipo))
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                Swal.fire({
                    icon: 'success',
                    title: res.titulo || 'Sucesso',
                    html: '<pre style="text-align:left;max-height:320px;overflow:auto;font-size:.75rem">'
                        + (res.detalhe || '') + '</pre>'
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Falha', text: res.mensagem });
            }
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de comunicação.' }));
}
</script>
</body>
</html>
