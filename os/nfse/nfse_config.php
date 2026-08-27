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
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../ui-config.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <div class="cfg">

      <section class="cfg-hero">
        <div class="cfg-titulo">
          <div class="cfg-icone"><i class="fa fa-file-text-o" aria-hidden="true"></i></div>
          <div>
            <h1>Nota Fiscal de Serviço Eletrônica</h1>
            <p class="cfg-sub">Emissão pelo Ambiente Nacional, padrão da LC 214/2025.</p>
          </div>
        </div>
        <div class="cfg-selos">
          <?php if (!empty($cfg['ativo']) && !$pendencias): ?>
            <span class="cfg-pill cfg-pill-ok"><i class="fa fa-check-circle"></i> Emissão habilitada</span>
          <?php elseif ($pendencias): ?>
            <span class="cfg-pill cfg-pill-warn"><i class="fa fa-exclamation-triangle"></i> Configuração incompleta</span>
          <?php else: ?>
            <span class="cfg-pill cfg-pill-off"><i class="fa fa-ban"></i> Emissão desativada</span>
          <?php endif; ?>
          <span class="cfg-pill <?= ($cfg['ambiente'] ?? '2') === '1' ? 'cfg-pill-alerta' : 'cfg-pill-off' ?>">
            <?= ($cfg['ambiente'] ?? '2') === '1' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?>
          </span>
        </div>
      </section>

      <?php if ($erroFatal): ?>
        <div class="cfg-msg cfg-msg-erro"><b>Erro:</b> <?= htmlspecialchars($erroFatal) ?></div>
      <?php endif; ?>

      <div class="cfg-aviso">
        <b>Base normativa aplicada.</b> Os serviços notariais e de registro estão no subitem 21.01 da lista anexa à
        LC 116/2003 — código de tributação nacional <b>210101</b> — e, por força do art. 62, §1º, I, da
        <b>LC 214/2025</b>, passaram a emitir NFS-e exclusivamente pelo Ambiente Nacional a partir de 01/01/2026.
        Durante o exercício de 2026 admite-se a NFS-e <b>consolidada</b> e a opção “Tomador não informado”;
        a partir de <b>01/01/2027</b> a emissão deve ser <b>individualizada por ato</b>, com identificação do tomador
        — o sistema alterna sozinho na virada. O fato gerador é a <b>liquidação do ato</b>: o depósito prévio é
        adiantamento e não gera nota. O regime especial de tributação é <b>4 — Notário ou Registrador</b>.
      </div>

      <?php if ($pendencias): ?>
        <div class="cfg-msg cfg-msg-warn">
          <b>Faltam informações para habilitar a emissão:</b>
          <ul>
            <?php foreach ($pendencias as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="cfg-colunas">

        <!-- ============ COLUNA LATERAL ============ -->
        <aside>
          <div class="cfg-card">
            <header><i class="fa fa-certificate"></i><h2>Certificado digital A1</h2></header>
            <div class="cfg-corpo">
              <?php if ($temCert): ?>
                <dl class="cfg-dados">
                  <div class="cfg-dado"><dt>Titular</dt><dd><?= $v('cert_titular') ?></dd></div>
                  <div class="cfg-dado"><dt>Arquivo</dt><dd><?= $v('cert_nome') ?></dd></div>
                  <div class="cfg-dado">
                    <dt>Validade</dt>
                    <dd>
                      <?= !empty($cfg['cert_validade']) ? date('d/m/Y H:i', strtotime($cfg['cert_validade'])) : '—' ?>
                      <?php if ($diasVenc !== null): ?>
                        <?php if ($diasVenc < 0): ?>
                          <span class="cfg-pill cfg-pill-alerta">Vencido</span>
                        <?php elseif ($diasVenc <= 30): ?>
                          <span class="cfg-pill cfg-pill-warn">vence em <?= $diasVenc ?> dia(s)</span>
                        <?php else: ?>
                          <span class="cfg-pill cfg-pill-ok"><?= $diasVenc ?> dias</span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </dd>
                  </div>
                </dl>
                <button type="button" class="cfg-btn cfg-btn-perigo cfg-btn-bloco" onclick="removerCertificado()">
                  <i class="fa fa-trash"></i> Remover certificado
                </button>
              <?php else: ?>
                <div class="cfg-msg cfg-msg-warn" style="margin-top:0">
                  Nenhum certificado instalado. A emissão permanece bloqueada.
                </div>
              <?php endif; ?>

              <div class="cfg-drop" style="margin-top:14px">
                <label class="cfg-rot" for="cert_arquivo"><?= $temCert ? 'Substituir' : 'Enviar' ?> arquivo .pfx / .p12</label>
                <input type="file" id="cert_arquivo" accept=".pfx,.p12">

                <label class="cfg-rot" for="cert_senha" style="margin-top:13px">Senha do certificado</label>
                <input type="password" class="cfg-in" id="cert_senha" autocomplete="new-password">

                <button type="button" class="cfg-btn cfg-btn-marca cfg-btn-bloco" style="margin-top:12px" onclick="enviarCertificado()">
                  <i class="fa fa-upload"></i> Validar e instalar
                </button>

                <div class="cfg-dica" style="margin-top:11px">
                  O <code>.pfx</code> e a senha são cifrados com AES-256-GCM antes de ir ao banco.
                  A chave mestra fica em <code>certs/.nfse.key</code>. <b>Faça backup dessa chave junto com o banco</b> —
                  sem ela o certificado guardado não é recuperável.
                </div>
              </div>
            </div>
          </div>

          <div class="cfg-card">
            <header><i class="fa fa-stethoscope"></i><h2>Ambiente</h2></header>
            <div class="cfg-corpo">
              <ul class="cfg-lista">
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
                  <li>
                    <i class="fa <?= $ok ? 'fa-check-circle ok' : 'fa-times-circle bad' ?>"></i>
                    <span><?= $rot ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>

              <?php if (!$diag['sdk']): ?>
                <div class="cfg-msg cfg-msg-erro">
                  SDK ausente. Na pasta <code>os/nfse</code> execute:<br>
                  <code>composer require nfse-nacional/nfse-php</code>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="cfg-card">
            <header><i class="fa fa-plug"></i><h2>Testes de conexão</h2></header>
            <div class="cfg-corpo">
              <div class="cfg-pilha">
                <button type="button" class="cfg-btn cfg-btn-neutro cfg-btn-bloco" onclick="testar('convenio')">
                  <i class="fa fa-institution"></i> Verificar adesão do município
                </button>
                <button type="button" class="cfg-btn cfg-btn-neutro cfg-btn-bloco" onclick="testar('aliquota')">
                  <i class="fa fa-percent"></i> Consultar alíquota do 210101
                </button>
              </div>
              <div class="cfg-dica" style="margin-top:11px">
                Ambos usam o certificado instalado e o ambiente selecionado ao lado.
              </div>
            </div>
          </div>
        </aside>

        <!-- ============ COLUNA PRINCIPAL ============ -->
        <form id="formNfse">

          <div class="cfg-card" style="margin-top:0">
            <header><i class="fa fa-toggle-on"></i><h2>Operação</h2></header>
            <div class="cfg-corpo">

              <label class="cfg-switch">
                <input type="checkbox" id="ativo" name="ativo" <?= $chk('ativo') ?>>
                <span class="cfg-trilho"></span>
                <span style="flex:1">
                  <b>Habilitar emissão de NFS-e</b>
                  <span class="cfg-desc">Com isto desligado, nenhuma nota é enviada ao Ambiente Nacional.</span>
                </span>
              </label>

              <div class="cfg-grid" style="margin-top:16px">
                <div class="c6">
                  <label class="cfg-check">
                    <input type="checkbox" id="emissao_automatica" name="emissao_automatica" <?= $chk('emissao_automatica') ?>>
                    <span class="cfg-box"></span>
                    <span>
                      <b>Emitir ao liquidar a O.S.</b>
                      <span class="cfg-desc">Dispara sozinho quando todos os atos da ordem forem liquidados.</span>
                    </span>
                  </label>
                </div>
                <div class="c6">
                  <label class="cfg-check">
                    <input type="checkbox" id="identificar_tomador" name="identificar_tomador" <?= $chk('identificar_tomador') ?>>
                    <span class="cfg-box"></span>
                    <span>
                      <b>Exigir identificação do tomador</b>
                      <span class="cfg-desc">Desmarcado, usa “Tomador não informado” — facultado apenas durante 2026.</span>
                    </span>
                  </label>
                </div>
              </div>

              <div class="cfg-grid">
                <div class="c4">
                  <label class="cfg-rot" for="ambiente">Ambiente</label>
                  <select class="cfg-in" id="ambiente" name="ambiente">
                    <option value="2" <?= $sel('ambiente', '2') ?>>Homologação (produção restrita)</option>
                    <option value="1" <?= $sel('ambiente', '1') ?>>Produção</option>
                  </select>
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="serie_dps">Série da DPS</label>
                  <input type="text" class="cfg-in" id="serie_dps" name="serie_dps" maxlength="5" value="<?= $v('serie_dps', '1') ?>">
                </div>
                <div class="c5">
                  <label class="cfg-rot" for="ultimo_numero_dps">Último nº de DPS emitido</label>
                  <input type="number" class="cfg-in" id="ultimo_numero_dps" name="ultimo_numero_dps" min="0" value="<?= $v('ultimo_numero_dps', '0') ?>">
                  <div class="cfg-dica">A próxima nota usará este número + 1.</div>
                </div>
                <div class="c12">
                  <label class="cfg-rot" for="modo_emissao">Modo de emissão</label>
                  <select class="cfg-in" id="modo_emissao" name="modo_emissao">
                    <option value="consolidado" <?= $sel('modo_emissao', 'consolidado') ?>>Consolidado — uma NFS-e por Ordem de Serviço (regime de 2026)</option>
                    <option value="individualizado" <?= $sel('modo_emissao', 'individualizado') ?>>Individualizado — uma NFS-e por ato praticado (obrigatório a partir de 2027)</option>
                  </select>
                  <div class="cfg-dica">A partir de 01/01/2027 o sistema força o modo individualizado, independentemente desta escolha.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="cfg-card">
            <header><i class="fa fa-building"></i><h2>Prestador</h2><small>a serventia</small></header>
            <div class="cfg-corpo">
              <div class="cfg-grid">
                <div class="c3">
                  <label class="cfg-rot" for="prest_tipo">Tipo de inscrição</label>
                  <select class="cfg-in" id="prest_tipo" name="prest_tipo">
                    <option value="CNPJ" <?= $sel('prest_tipo', 'CNPJ') ?>>CNPJ</option>
                    <option value="CPF" <?= $sel('prest_tipo', 'CPF') ?>>CPF (delegatário)</option>
                  </select>
                </div>
                <div class="c4">
                  <label class="cfg-rot" for="prest_doc">CPF / CNPJ<span class="cfg-req">*</span></label>
                  <input type="text" class="cfg-in cfg-mono" id="prest_doc" name="prest_doc" value="<?= $v('prest_doc') ?>" placeholder="somente números">
                </div>
                <div class="c5">
                  <label class="cfg-rot" for="prest_im">Inscrição municipal</label>
                  <input type="text" class="cfg-in" id="prest_im" name="prest_im" value="<?= $v('prest_im') ?>">
                  <div class="cfg-dica">Exigida pela maioria dos municípios para habilitar o emissor.</div>
                </div>

                <div class="c12">
                  <label class="cfg-rot" for="prest_nome">Nome / razão social<span class="cfg-req">*</span></label>
                  <input type="text" class="cfg-in" id="prest_nome" name="prest_nome" maxlength="150" value="<?= $v('prest_nome') ?>">
                </div>

                <div class="c3">
                  <label class="cfg-rot" for="cod_municipio">Código IBGE<span class="cfg-req">*</span></label>
                  <input type="text" class="cfg-in cfg-mono" id="cod_municipio" name="cod_municipio" maxlength="7" value="<?= $v('cod_municipio') ?>" placeholder="7 dígitos">
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="prest_cep">CEP<span class="cfg-req">*</span></label>
                  <input type="text" class="cfg-in cfg-mono" id="prest_cep" name="prest_cep" maxlength="9" value="<?= $v('prest_cep') ?>">
                </div>
                <div class="c6">
                  <label class="cfg-rot" for="prest_logradouro">Logradouro<span class="cfg-req">*</span></label>
                  <input type="text" class="cfg-in" id="prest_logradouro" name="prest_logradouro" value="<?= $v('prest_logradouro') ?>">
                </div>

                <div class="c2">
                  <label class="cfg-rot" for="prest_numero">Número<span class="cfg-req">*</span></label>
                  <input type="text" class="cfg-in" id="prest_numero" name="prest_numero" value="<?= $v('prest_numero') ?>">
                </div>
                <div class="c4">
                  <label class="cfg-rot" for="prest_complemento">Complemento <span class="cfg-opcional">(opcional)</span></label>
                  <input type="text" class="cfg-in" id="prest_complemento" name="prest_complemento" value="<?= $v('prest_complemento') ?>">
                </div>
                <div class="c6">
                  <label class="cfg-rot" for="prest_bairro">Bairro<span class="cfg-req">*</span></label>
                  <input type="text" class="cfg-in" id="prest_bairro" name="prest_bairro" value="<?= $v('prest_bairro') ?>">
                </div>

                <div class="c4">
                  <label class="cfg-rot" for="prest_fone">Telefone</label>
                  <input type="text" class="cfg-in" id="prest_fone" name="prest_fone" value="<?= $v('prest_fone') ?>">
                </div>
                <div class="c8">
                  <label class="cfg-rot" for="prest_email">E-mail</label>
                  <input type="email" class="cfg-in" id="prest_email" name="prest_email" value="<?= $v('prest_email') ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="cfg-card">
            <header><i class="fa fa-calculator"></i><h2>Tributação</h2></header>
            <div class="cfg-corpo">

              <div class="cfg-grid">
                <div class="c3">
                  <label class="cfg-rot" for="ctrib_nac">Código nacional</label>
                  <input type="text" class="cfg-in cfg-mono" id="ctrib_nac" name="ctrib_nac" maxlength="6" value="<?= $v('ctrib_nac', '210101') ?>">
                  <div class="cfg-dica">21.01.01 — registros públicos, cartorários e notariais.</div>
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="ctrib_mun">Código municipal</label>
                  <input type="text" class="cfg-in cfg-mono" id="ctrib_mun" name="ctrib_mun" value="<?= $v('ctrib_mun') ?>">
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="cnae">CNAE</label>
                  <input type="text" class="cfg-in cfg-mono" id="cnae" name="cnae" maxlength="7" value="<?= $v('cnae') ?>">
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="aliquota_iss">Alíquota do ISSQN<span class="cfg-req">*</span></label>
                  <input type="number" step="0.01" min="0" max="100" class="cfg-in" id="aliquota_iss" name="aliquota_iss" value="<?= $v('aliquota_iss', '5.00') ?>">
                  <div class="cfg-dica">Em porcentagem.</div>
                </div>
              </div>

              <div class="cfg-grid">
                <div class="c6">
                  <label class="cfg-rot" for="base_calculo">Composição do valor do serviço</label>
                  <select class="cfg-in" id="base_calculo" name="base_calculo">
                    <option value="emolumentos" <?= $sel('base_calculo', 'emolumentos') ?>>Somente emolumentos (receita do delegatário) — recomendado</option>
                    <option value="emolumentos_taxas" <?= $sel('base_calculo', 'emolumentos_taxas') ?>>Emolumentos + taxas e fundos (FERC, FADEP, FEMP, FERRFIS)</option>
                    <option value="total" <?= $sel('base_calculo', 'total') ?>>Total cobrado do usuário</option>
                  </select>
                  <div class="cfg-dica">
                    As parcelas repassadas a fundos estaduais não são receita do notário e, na opção recomendada,
                    ficam de fora do valor do serviço. O item interno “ISS” da O.S. nunca entra.
                  </div>
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="reducao_base">Redução da base</label>
                  <input type="number" step="0.01" min="0" max="100" class="cfg-in" id="reducao_base" name="reducao_base" value="<?= $v('reducao_base', '12.00') ?>">
                  <div class="cfg-dica">Em %. Ex.: 12 (fator 0,88) para os fundos estaduais.</div>
                </div>
                <div class="c3">
                  <label class="cfg-rot" for="reducao_modo">Forma da redução</label>
                  <select class="cfg-in" id="reducao_modo" name="reducao_modo">
                    <option value="grupo" <?= $sel('reducao_modo', 'grupo') ?>>Grupo de dedução (vDedRed)</option>
                    <option value="embutida" <?= $sel('reducao_modo', 'embutida') ?>>Embutida no valor do serviço</option>
                  </select>
                  <div class="cfg-dica">“Embutida” já reduz o <code>vServ</code>. Use quando o município recusa o grupo de dedução (erro E0440).</div>
                </div>
              </div>

              <div class="cfg-grid">
                <div class="c4">
                  <label class="cfg-rot" for="reg_esp_trib">Regime especial</label>
                  <select class="cfg-in" id="reg_esp_trib" name="reg_esp_trib">
                    <option value="4" <?= $sel('reg_esp_trib', '4') ?>>4 — Notário ou Registrador</option>
                    <option value="0" <?= $sel('reg_esp_trib', '0') ?>>0 — Nenhum</option>
                    <option value="2" <?= $sel('reg_esp_trib', '2') ?>>2 — Estimativa</option>
                    <option value="5" <?= $sel('reg_esp_trib', '5') ?>>5 — Profissional autônomo</option>
                  </select>
                </div>
                <div class="c4">
                  <label class="cfg-rot" for="cst_piscofins">CST PIS/COFINS</label>
                  <select class="cfg-in" id="cst_piscofins" name="cst_piscofins">
                    <option value="08" <?= $sel('cst_piscofins', '08') ?>>08 — Sem incidência</option>
                    <option value="07" <?= $sel('cst_piscofins', '07') ?>>07 — Isenta</option>
                    <option value="01" <?= $sel('cst_piscofins', '01') ?>>01 — Alíquota básica</option>
                    <option value="99" <?= $sel('cst_piscofins', '99') ?>>99 — Outras operações</option>
                  </select>
                </div>
                <div class="c4">
                  <label class="cfg-rot" for="op_simp_nac">Simples Nacional</label>
                  <select class="cfg-in" id="op_simp_nac" name="op_simp_nac">
                    <option value="1" <?= $sel('op_simp_nac', '1') ?>>1 — Não optante</option>
                    <option value="3" <?= $sel('op_simp_nac', '3') ?>>3 — Optante ME/EPP</option>
                  </select>
                  <div class="cfg-dica">Serventias extrajudiciais são vedadas ao Simples (LC 123/2006, art. 17, XI).</div>
                </div>

                <div class="c6">
                  <label class="cfg-rot" for="reg_ap_trib_sn">Regime de apuração <span class="cfg-opcional">(Simples)</span></label>
                  <select class="cfg-in" id="reg_ap_trib_sn" name="reg_ap_trib_sn">
                    <option value="" <?= $sel('reg_ap_trib_sn', '') ?>>— (não optante)</option>
                    <option value="1" <?= $sel('reg_ap_trib_sn', '1') ?>>1 — Federais e ISSQN pelo SN</option>
                    <option value="2" <?= $sel('reg_ap_trib_sn', '2') ?>>2 — Federais pelo SN, ISSQN pelo regime normal</option>
                    <option value="3" <?= $sel('reg_ap_trib_sn', '3') ?>>3 — MEI</option>
                  </select>
                  <div class="cfg-dica">Obrigatório para optantes; a SEFIN recusa a DPS sem este campo. Ignorado para não optantes.</div>
                </div>
                <div class="c6">
                  <label class="cfg-rot" for="p_tot_trib_sn">% total de tributos <span class="cfg-opcional">(Simples)</span></label>
                  <input type="text" class="cfg-in" id="p_tot_trib_sn" name="p_tot_trib_sn" value="<?= $v('p_tot_trib_sn') ?>" placeholder="ex.: 6,00">
                  <div class="cfg-dica">Alíquota efetiva do Simples (pTotTribSN). Deixe 0 para não optantes; num optante, se ficar 0 o sistema usa 6,00.</div>
                </div>
              </div>

              <div class="cfg-msg cfg-msg-ok">
                <b>Simulação.</b> Emolumentos de R$ 100,00 →
                base R$ <span id="simBase">88,00</span> → ISSQN R$ <span id="simIss">4,40</span>.
              </div>
            </div>
          </div>

          <div class="cfg-fim">
            <p class="cfg-nota">Campos marcados com <span class="cfg-req" style="color:var(--erro)">*</span> são exigidos pelo Ambiente Nacional.</p>
            <div class="cfg-linha">
              <a href="nfse_notas.php" class="cfg-btn cfg-btn-neutro cfg-btn-grande"><i class="fa fa-list"></i> Notas emitidas</a>
              <button type="button" class="cfg-btn cfg-btn-forte cfg-btn-grande" onclick="salvar()">
                <i class="fa fa-save"></i> Salvar configuração
              </button>
            </div>
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
