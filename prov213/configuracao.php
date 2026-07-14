<?php
/** ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213 — Configuração */
require_once __DIR__ . '/p213_lib.php';
$cfg = p213_config();
$par = p213_parametros((int)$cfg['classe']);
$iaOn = p213_gemini_ativo();

p213_head('Configuração — Provimento 213');
p213_hero('Configuração da serventia',
    'Identificação, responsáveis e enquadramento por classe (art. 16)');
p213_nav('configuracao.php');
?>

<div class="p213-split" style="grid-template-columns:minmax(0,1.35fr) minmax(0,1fr)">
  <div>
    <div class="p213-card" style="margin-bottom:18px">
      <div class="p213-card__head"><h2 class="p213-card__title"><i class="fa fa-building-o"></i> Identificação</h2></div>
      <div class="p213-card__body">
        <div class="p213-fields">
          <div class="p213-field span2"><label class="p213-label">Serventia</label>
            <input id="serventia" class="p213-in" value="<?= p213_esc($cfg['serventia']) ?>"></div>
          <div class="p213-field"><label class="p213-label">CNS</label>
            <input id="cns" class="p213-in" value="<?= p213_esc($cfg['cns']) ?>"></div>
          <div class="p213-field"><label class="p213-label">CNPJ</label>
            <input id="cnpj" class="p213-in" value="<?= p213_esc($cfg['cnpj']) ?>"></div>
          <div class="p213-field span2"><label class="p213-label">Endereço</label>
            <input id="endereco" class="p213-in" value="<?= p213_esc($cfg['endereco']) ?>"></div>
          <div class="p213-field"><label class="p213-label">Município / UF</label>
            <input id="municipio_uf" class="p213-in" value="<?= p213_esc($cfg['municipio_uf']) ?>"></div>
          <div class="p213-field"><label class="p213-label">Corregedoria competente</label>
            <input id="corregedoria" class="p213-in" value="<?= p213_esc($cfg['corregedoria']) ?>"></div>
        </div>
      </div>
    </div>

    <div class="p213-card">
      <div class="p213-card__head">
        <h2 class="p213-card__title"><i class="fa fa-users"></i> Responsáveis</h2>
        <span class="p213-hint">Anexo IV, item 1.1</span>
      </div>
      <div class="p213-card__body">
        <div class="p213-fields">
          <div class="p213-field span2"><label class="p213-label">Titular / interino / interventor</label>
            <input id="titular" class="p213-in" value="<?= p213_esc($cfg['titular']) ?>"></div>
          <div class="p213-field"><label class="p213-label">Qualificação</label>
            <select id="titular_qualif" class="p213-in">
              <?php foreach (['Titular da delegação','Interino','Interventor'] as $q): ?>
                <option <?= $cfg['titular_qualif'] === $q ? 'selected' : '' ?>><?= $q ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="p213-field"><label class="p213-label">Responsável técnico interno</label>
            <input id="responsavel_tec" class="p213-in" value="<?= p213_esc($cfg['responsavel_tec']) ?>"></div>
          <div class="p213-field"><label class="p213-label">Encarregado (DPO)</label>
            <input id="encarregado_dpo" class="p213-in" placeholder="Dispensado na Classe 1"
                   value="<?= p213_esc($cfg['encarregado_dpo']) ?>"></div>
          <div class="p213-field"><label class="p213-label">Canal de contato do encarregado</label>
            <input id="dpo_contato" class="p213-in" value="<?= p213_esc($cfg['dpo_contato']) ?>"></div>
          <div class="p213-field span2"><label class="p213-label">Modelo de solução (art. 13)</label>
            <select id="modelo_solucao" class="p213-in">
              <?php foreach (['propria'=>'Própria','contratada'=>'Contratada (SaaS/PaaS/IaaS)',
                              'compartilhada'=>'Compartilhada','coletiva'=>'Coletiva'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $cfg['modelo_solucao'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="p213-card" style="margin-bottom:18px">
      <div class="p213-card__head"><h2 class="p213-card__title"><i class="fa fa-balance-scale"></i> Enquadramento</h2>
        <span class="p213-hint">art. 16</span></div>
      <div class="p213-card__body">
        <div class="p213-fields" style="grid-template-columns:minmax(0,1fr)">
          <div class="p213-field"><label class="p213-label">Arrecadação bruta semestral (R$)</label>
            <input id="receita_semestral" class="p213-in"
                   value="<?= number_format((float)$cfg['receita_semestral'], 2, ',', '.') ?>"></div>
          <div class="p213-field"><label class="p213-label">Fator de atualização IPCA (§2º)</label>
            <input id="fator_ipca" class="p213-in" value="<?= p213_esc($cfg['fator_ipca']) ?>"></div>
        </div>

        <div class="p213-alert neutral" style="margin:16px 0">
          <i class="fa fa-exclamation-circle"></i>
          <div>O art. 16 fala em <em>arrecadação bruta</em>. O total exibido no Justiça Aberta inclui fundos
            públicos e repasses obrigatórios, o que pode elevar a classe. Havendo divergência, registre a
            memória de cálculo no dossiê e, se necessário, fixe a classe manualmente.</div>
        </div>

        <div class="p213-fields">
          <div class="p213-field"><label class="p213-label">Fixar classe manualmente</label>
            <select id="classe_manual" class="p213-in">
              <option value="">Automático pelo cálculo</option>
              <?php for ($i = 1; $i <= 3; $i++): ?>
                <option value="<?= $i ?>" <?= (string)$cfg['classe_manual'] === (string)$i ? 'selected' : '' ?>>
                  Classe <?= $i ?></option>
              <?php endfor; ?>
            </select></div>
          <div class="p213-field"><label class="p213-label">Subclasse</label>
            <input id="subclasse_manual" class="p213-in" placeholder="A–J"
                   value="<?= p213_esc($cfg['subclasse_manual']) ?>"></div>
        </div>

        <div id="preview" class="p213-preview" style="margin-top:16px">
          <div class="p213-sub">Enquadramento atual</div>
          <h5>Classe <?= (int)$cfg['classe'] ?> &middot; Subclasse <?= p213_esc($cfg['subclasse']) ?></h5>
          <div class="p213-sub">RPO <?= $par['rpo'] ?> &middot; RTO <?= $par['rto'] ?>
            &middot; backup completo <?= $par['backup_full'] ?></div>
        </div>

        <button id="btnSalvar" class="p213-btn p213-btn--pri p213-btn--block" style="margin-top:16px">
          <i class="fa fa-save"></i> Salvar configuração</button>
      </div>
    </div>

    <div class="p213-card" style="margin-bottom:18px">
      <div class="p213-card__head">
        <h2 class="p213-card__title"><i class="fa fa-magic"></i> Assistente de IA (Gemini)</h2>
        <span class="p213-tag <?= $iaOn ? 'c1' : 'c3' ?>"><?= $iaOn ? 'ativo' : 'desativado' ?></span>
      </div>
      <div class="p213-card__body">
        <div class="p213-fields" style="grid-template-columns:minmax(0,1fr)">
          <div class="p213-field"><label class="p213-label">Chave da API</label>
            <input id="gemini_api_key" class="p213-in" type="password" autocomplete="off"
                   placeholder="<?= $iaOn ? 'chave configurada — digite para substituir' : 'AIza…' ?>"
                   value="<?= p213_esc(isset($cfg['gemini_api_key']) ? $cfg['gemini_api_key'] : '') ?>"></div>
          <div class="p213-field"><label class="p213-label">Modelo</label>
            <select id="gemini_modelo" class="p213-in">
              <?php foreach (['gemini-3.5-flash' => 'Gemini 3.5 Flash (recomendado)',
                             'gemini-3.1-pro' => 'Gemini 3.1 Pro (reasoning avançado)',
                             'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite (mais econômico)'] as $mv => $ml): ?>
                <option value="<?= $mv ?>" <?= (isset($cfg['gemini_modelo']) ? $cfg['gemini_modelo'] : 'gemini-3.5-flash') === $mv ? 'selected' : '' ?>><?= $ml ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <p class="p213-hint" style="margin-top:12px">
          Usada para sugerir evidências por requisito, redigir descrições do dossiê e montar o plano de cada
          etapa. A chave fica no banco desta instalação e o conteúdo dos arquivos anexados <strong>não</strong>
          é enviado à API — apenas o texto do requisito e as suas anotações. Nada gerado por IA substitui a
          conferência contra o texto do Provimento.
        </p>
      </div>
    </div>

    <div class="p213-card">
      <div class="p213-card__head"><h2 class="p213-card__title"><i class="fa fa-list"></i> Faixas do art. 16</h2></div>
      <div class="p213-card__body">
        <dl style="margin:0">
          <div class="p213-kv"><dt>Classe 1</dt><dd class="sm">até R$ 100.000,00 &middot; A, B, C</dd></div>
          <div class="p213-kv"><dt>Classe 2</dt><dd class="sm">até R$ 500.000,00 &middot; D, E, F</dd></div>
          <div class="p213-kv"><dt>Classe 3</dt><dd class="sm">G até 3× &middot; H até 6× &middot; I até 12× &middot; J acima</dd></div>
        </dl>
        <p class="p213-hint" style="margin-top:14px">
          Reavaliação anual com base no semestre imediatamente anterior (§1º). A migração de classe não produz
          efeito imediato quando a variação não ultrapassa 10% do limite superior da faixa anterior, exigindo
          consolidação por dois ciclos consecutivos (§3º).
        </p>
      </div>
    </div>
  </div>
</div>

<?php
$js = <<<'JS'
var IDS = ['serventia','cns','cnpj','endereco','municipio_uf','titular','titular_qualif',
           'responsavel_tec','encarregado_dpo','dpo_contato','corregedoria','modelo_solucao',
           'receita_semestral','fator_ipca','classe_manual','subclasse_manual',
           'gemini_api_key','gemini_modelo'];

document.getElementById('btnSalvar').addEventListener('click', function(){
  var fd = new FormData();
  fd.append('acao','salvar_config');
  IDS.forEach(function(i){ fd.append(i, document.getElementById(i).value); });
  fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    if(j.success){
      Swal.fire({icon:'success', title:'Configuração salva', confirmButtonColor:'#4f46e5',
        text:'Enquadrada como Classe ' + j.data.classe + ' · Subclasse ' + j.data.subclasse})
        .then(function(){ location.reload(); });
    } else { Swal.fire({icon:'error', title:'Erro', text:j.message, confirmButtonColor:'#4f46e5'}); }
  });
});

['receita_semestral','fator_ipca'].forEach(function(id){
  document.getElementById(id).addEventListener('input', function(){
    var fd = new FormData();
    fd.append('acao','simular_classe');
    fd.append('receita', document.getElementById('receita_semestral').value);
    fd.append('fator_ipca', document.getElementById('fator_ipca').value || '1');
    fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
      if(!j.success) return;
      var e = j.data.enquadramento, p = j.data.parametros, z = j.data.prazos;
      document.getElementById('preview').innerHTML =
        '<div class="p213-sub">Simulação</div>' +
        '<h5>Classe ' + e.classe + ' · Subclasse ' + e.subclasse + '</h5>' +
        '<div class="p213-sub">RPO ' + p.rpo + ' · RTO ' + p.rto + ' · backup completo ' + p.backup_full + '</div>' +
        '<div class="p213-sub" style="margin-top:5px">Etapas 1 e 2 até ' + z.inicial +
        ' · integral até ' + z.global + '</div>';
    });
  });
});
JS;
p213_foot($js);
