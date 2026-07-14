<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Instalador idempotente. Pode ser executado quantas vezes for necessário.
 * Acesse uma vez: http://SEU_HOST/atlas/prov213/install.php
 */
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(P213_DB_HOST, P213_DB_USER, P213_DB_PASS, P213_DB_NAME);
$conn->set_charset(P213_DB_CHARSET);

$sqls = [];

$sqls['p213_config'] = "
CREATE TABLE IF NOT EXISTS p213_config (
  id                TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  serventia         VARCHAR(180)  NOT NULL DEFAULT '',
  cns               VARCHAR(20)   NOT NULL DEFAULT '',
  cnpj              VARCHAR(20)   NOT NULL DEFAULT '',
  endereco          VARCHAR(255)  NOT NULL DEFAULT '',
  municipio_uf      VARCHAR(120)  NOT NULL DEFAULT '',
  titular           VARCHAR(180)  NOT NULL DEFAULT '',
  titular_qualif    VARCHAR(120)  NOT NULL DEFAULT 'Titular da delegação',
  responsavel_tec   VARCHAR(180)  NOT NULL DEFAULT '',
  encarregado_dpo   VARCHAR(180)  NOT NULL DEFAULT '',
  dpo_contato       VARCHAR(180)  NOT NULL DEFAULT '',
  corregedoria      VARCHAR(180)  NOT NULL DEFAULT 'Corregedoria Geral da Justiça',
  receita_semestral DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  fator_ipca        DECIMAL(6,4)  NOT NULL DEFAULT 1.0000,
  classe_manual     TINYINT       NULL,
  subclasse_manual  VARCHAR(2)    NULL,
  modelo_solucao    VARCHAR(30)   NOT NULL DEFAULT 'propria',
  atualizado_em     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqls['p213_respostas'] = "
CREATE TABLE IF NOT EXISTS p213_respostas (
  codigo         VARCHAR(12)  NOT NULL PRIMARY KEY,
  etapa          TINYINT      NOT NULL,
  status         VARCHAR(20)  NOT NULL DEFAULT 'nao_avaliado',
  evidencia      TEXT         NULL,
  observacao     TEXT         NULL,
  responsavel    VARCHAR(180) NULL,
  data_conclusao DATE         NULL,
  atualizado_por VARCHAR(180) NULL,
  atualizado_em  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_etapa (etapa),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Anexo IV, item 1.7 — inventário de ativos, integrações, BDs, certificados,
// softwares, histórico de atualizações e contratos.
$sqls['p213_ativos'] = "
CREATE TABLE IF NOT EXISTS p213_ativos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria       VARCHAR(30)  NOT NULL DEFAULT 'hardware',
  nome            VARCHAR(180) NOT NULL,
  identificacao   VARCHAR(180) NULL,
  fabricante      VARCHAR(120) NULL,
  versao          VARCHAR(80)  NULL,
  criticidade     VARCHAR(20)  NOT NULL DEFAULT 'media',
  suporte_ativo   TINYINT(1)   NOT NULL DEFAULT 1,
  eol             DATE         NULL,
  responsavel     VARCHAR(180) NULL,
  fornecedor      VARCHAR(180) NULL,
  contrato        VARCHAR(180) NULL,
  validade        DATE         NULL,
  localizacao     VARCHAR(180) NULL,
  dados_pessoais  TINYINT(1)   NOT NULL DEFAULT 0,
  observacao      TEXT         NULL,
  criado_em       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cat (categoria),
  INDEX idx_crit (criticidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Art. 11 e Anexo II, 4
$sqls['p213_incidentes'] = "
CREATE TABLE IF NOT EXISTS p213_incidentes (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ocorrido_em        DATETIME    NOT NULL,
  ciencia_em         DATETIME    NULL,
  gravidade          VARCHAR(12) NOT NULL DEFAULT 'baixo',
  titulo             VARCHAR(200) NOT NULL,
  descricao          TEXT        NULL,
  contencao          TEXT        NULL,
  erradicacao        TEXT        NULL,
  recuperacao        TEXT        NULL,
  causa_raiz         TEXT        NULL,
  licoes_aprendidas  TEXT        NULL,
  comunicado_correg  TINYINT(1)  NOT NULL DEFAULT 0,
  comunicado_correg_em DATETIME  NULL,
  comunicado_anpd    TINYINT(1)  NOT NULL DEFAULT 0,
  encerrado_em       DATETIME    NULL,
  criado_em          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_grav (gravidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Anexo IV, itens x.9 — declarações de conclusão de etapa
$sqls['p213_declaracoes'] = "
CREATE TABLE IF NOT EXISTS p213_declaracoes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  etapa          TINYINT      NOT NULL,
  declarante     VARCHAR(180) NOT NULL,
  qualificacao   VARCHAR(120) NOT NULL,
  protocolo_ja   VARCHAR(120) NULL,
  data_registro  DATE         NULL,
  pct_no_momento DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  hash_dossie    VARCHAR(128) NULL,
  criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_etapa (etapa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqls['p213_evidencias'] = "
CREATE TABLE IF NOT EXISTS p213_evidencias (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo         VARCHAR(12)  NOT NULL,
  etapa          TINYINT      NOT NULL,
  titulo         VARCHAR(200) NOT NULL,
  tipo           VARCHAR(30)  NOT NULL DEFAULT 'documento',
  descricao      TEXT         NULL,
  arquivo_nome   VARCHAR(255) NULL,
  arquivo_path   VARCHAR(255) NULL,
  mime           VARCHAR(120) NULL,
  tamanho        INT UNSIGNED NULL,
  sha256         CHAR(64)     NULL,
  data_evidencia DATE         NULL,
  responsavel    VARCHAR(180) NULL,
  criado_por     VARCHAR(180) NULL,
  criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cod (codigo),
  INDEX idx_et (etapa),
  INDEX idx_hash (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sqls['p213_auditoria'] = "
CREATE TABLE IF NOT EXISTS p213_auditoria (
  id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario   VARCHAR(180) NULL,
  acao      VARCHAR(80)  NOT NULL,
  detalhe   TEXT         NULL,
  ip        VARCHAR(45)  NULL,
  criado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_acao (acao),
  INDEX idx_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ok = [];
foreach ($sqls as $t => $sql) {
    $conn->query($sql);
    $ok[] = $t;
}

// colunas acrescentadas em versões posteriores (idempotente)
$colunas = [
    'gemini_api_key' => "ALTER TABLE p213_config ADD COLUMN gemini_api_key VARCHAR(160) NOT NULL DEFAULT ''",
    'gemini_modelo'  => "ALTER TABLE p213_config ADD COLUMN gemini_modelo VARCHAR(60) NOT NULL DEFAULT 'gemini-2.0-flash'",
];
$novasColunas = 0;
foreach ($colunas as $col => $ddl) {
    $r = $conn->query("SHOW COLUMNS FROM p213_config LIKE '" . $col . "'");
    if ($r && $r->num_rows === 0) { $conn->query($ddl); $novasColunas++; }
}

// repositório de evidências
require_once __DIR__ . '/p213_lib.php';
$dirEvid = p213_evid_dir();

// linha de configuração padrão
$conn->query("INSERT IGNORE INTO p213_config (id) VALUES (1)");

// pré-popula as respostas com 'nao_avaliado' (idempotente)
$stmt = $conn->prepare("INSERT IGNORE INTO p213_respostas (codigo, etapa) VALUES (?,?)");
$n = 0;
foreach (p213_catalogo() as $it) {
    $stmt->bind_param('si', $it['cod'], $it['etapa']);
    $stmt->execute();
    $n += $conn->affected_rows > 0 ? 1 : 0;
}
$stmt->close();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">
<title>Instalação — Atlas Prov. 213</title>
<style>body{font-family:system-ui,Segoe UI,Arial;max-width:760px;margin:40px auto;padding:0 16px;color:#222}
code{background:#f2f2f2;padding:2px 6px;border-radius:4px}li{margin:4px 0}
.ok{color:#0a7d33;font-weight:600}</style></head><body>
<h2>Módulo Atlas — Conformidade Provimento 213/2026</h2>
<p class="ok">Instalação concluída.</p>
<ul>
<?php foreach ($ok as $t) echo '<li>Tabela <code>' . htmlspecialchars($t) . '</code> verificada/criada.</li>'; ?>
<li><?= (int)$n ?> requisito(s) do catálogo inseridos nesta execução (total do catálogo: <?= count(p213_catalogo()) ?>).</li>
<li><?= (int)$novasColunas ?> coluna(s) acrescentada(s) em <code>p213_config</code>.</li>
<li>Repositório de evidências: <code><?= htmlspecialchars($dirEvid) ?></code>
    <?= is_writable($dirEvid) ? '<span class="ok">(gravável)</span>' : '<strong style="color:#b00">— SEM PERMISSÃO DE ESCRITA</strong>' ?></li>
</ul>
<h4>Próximos passos</h4>
<ol>
  <li>Adicione o link no <code>menu.php</code> do Atlas:<br>
      <code>&lt;a href="prov213/index.php"&gt;&lt;i class="fa fa-shield"&gt;&lt;/i&gt; Provimento 213&lt;/a&gt;</code></li>
  <li>Abra <a href="configuracao.php">configuracao.php</a> e preencha serventia, CNS, titular e a receita bruta semestral.</li>
  <li>Opcional: informe a chave da API do Gemini em <code>configuracao.php</code> para habilitar as sugestões de evidência.</li>
  <li>Reinicie o Apache (OPcache) e faça <kbd>Ctrl</kbd>+<kbd>F5</kbd>.</li>
  <li>Por segurança, remova ou renomeie este <code>install.php</code> após o uso.</li>
</ol>
<p><a href="index.php">Ir para o painel &rarr;</a></p>
</body></html>
