<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Schema idempotente. Roda automaticamente no primeiro acesso (via p213_ensure_schema)
 * e também pode ser executado manualmente por install.php.
 */

// Suba este número sempre que o schema mudar; força uma nova verificação.
if (!defined('P213_SCHEMA_VERSION')) define('P213_SCHEMA_VERSION', 3);

/**
 * Cria/atualiza todas as tabelas e colunas. Idempotente e barato:
 * usa CREATE TABLE IF NOT EXISTS e SHOW COLUMNS. Retorna um relatório.
 */
function p213_migrate(mysqli $conn) {
    $rel = ['tabelas' => [], 'colunas' => 0, 'requisitos' => 0, 'dir' => null, 'dir_ok' => false, 'erros' => []];

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
  gemini_api_key    VARCHAR(160)  NOT NULL DEFAULT '',
  gemini_modelo     VARCHAR(60)   NOT NULL DEFAULT 'gemini-2.0-flash',
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

    // marcador de versão do schema (chave/valor)
    $sqls['p213_meta'] = "
CREATE TABLE IF NOT EXISTS p213_meta (
  chave VARCHAR(60) NOT NULL PRIMARY KEY,
  valor VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    foreach ($sqls as $t => $sql) {
        try { $conn->query($sql); $rel['tabelas'][] = $t; }
        catch (Throwable $e) { $rel['erros'][] = $t . ': ' . $e->getMessage(); }
    }

    // colunas acrescentadas depois da criação original (para bases já existentes)
    $colunas = [
        'p213_config' => [
            'gemini_api_key' => "ALTER TABLE p213_config ADD COLUMN gemini_api_key VARCHAR(160) NOT NULL DEFAULT ''",
            'gemini_modelo'  => "ALTER TABLE p213_config ADD COLUMN gemini_modelo VARCHAR(60) NOT NULL DEFAULT 'gemini-2.0-flash'",
        ],
    ];
    foreach ($colunas as $tab => $cols) {
        foreach ($cols as $col => $ddl) {
            try {
                $r = $conn->query("SHOW COLUMNS FROM `$tab` LIKE '$col'");
                if ($r && $r->num_rows === 0) { $conn->query($ddl); $rel['colunas']++; }
            } catch (Throwable $e) { $rel['erros'][] = "$tab.$col: " . $e->getMessage(); }
        }
    }

    // linha padrão de configuração
    try { $conn->query("INSERT IGNORE INTO p213_config (id) VALUES (1)"); } catch (Throwable $e) {}

    // pré-popula respostas (idempotente) — depende do catálogo em p213_lib.php
    if (function_exists('p213_catalogo')) {
        try {
            $stmt = $conn->prepare("INSERT IGNORE INTO p213_respostas (codigo, etapa) VALUES (?,?)");
            foreach (p213_catalogo() as $it) {
                $stmt->bind_param('si', $it['cod'], $it['etapa']);
                $stmt->execute();
                if ($conn->affected_rows > 0) $rel['requisitos']++;
            }
            $stmt->close();
        } catch (Throwable $e) { $rel['erros'][] = 'respostas: ' . $e->getMessage(); }
    }

    // repositório de evidências
    if (function_exists('p213_evid_dir')) {
        $rel['dir'] = p213_evid_dir();
        $rel['dir_ok'] = is_writable($rel['dir']);
    }

    // grava a versão aplicada
    try {
        $v = P213_SCHEMA_VERSION;
        $conn->query("INSERT INTO p213_meta (chave, valor) VALUES ('schema_version', '$v')
                      ON DUPLICATE KEY UPDATE valor='$v'");
    } catch (Throwable $e) {}

    return $rel;
}

/**
 * Garante o schema no primeiro acesso, sem custo perceptível nos acessos seguintes.
 * Estratégia: lê p213_meta.schema_version; se igual à atual, não faz nada.
 * Se a tabela não existir (base nova) ou a versão divergir, roda a migração.
 * Trava por sessão para não repetir na mesma navegação.
 */
function p213_ensure_schema(mysqli $conn) {
    if (!empty($_SESSION['p213_schema_ok']) && $_SESSION['p213_schema_ok'] == P213_SCHEMA_VERSION) {
        return null;
    }
    $atual = null;
    try {
        $r = @$conn->query("SELECT valor FROM p213_meta WHERE chave='schema_version'");
        if ($r && ($row = $r->fetch_assoc())) $atual = (int)$row['valor'];
    } catch (Throwable $e) { $atual = null; } // tabela ainda não existe

    if ($atual === (int)P213_SCHEMA_VERSION) {
        $_SESSION['p213_schema_ok'] = P213_SCHEMA_VERSION;
        return null;
    }

    $rel = p213_migrate($conn);
    if (empty($rel['erros'])) $_SESSION['p213_schema_ok'] = P213_SCHEMA_VERSION;
    return $rel;
}
