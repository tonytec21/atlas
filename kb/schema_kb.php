<?php
/**
 * atlas/kb/schema_kb.php
 * DDL idempotente da base de conhecimento.
 * Usado tanto pelo migracao.php (CLI) quanto pelo bootstrap automatico.
 */

/**
 * Cria/atualiza o schema. Pode rodar quantas vezes quiser.
 * @return array linhas de log
 */
function kbGarantirSchema(PDO $conn)
{
    $log = array();

    $ddl = function ($sql, $rotulo) use ($conn, &$log) {
        try {
            $conn->exec($sql);
            $log[] = "[OK]   {$rotulo}";
        } catch (PDOException $e) {
            $cod = isset($e->errorInfo[1]) ? $e->errorInfo[1] : 0;
            // 1050 tabela | 1060 coluna | 1061 indice | 1022/1826 FK duplicada
            if (in_array($cod, array(1050, 1060, 1061, 1022, 1826), true)) {
                $log[] = "[SKIP] {$rotulo}";
            } else {
                $log[] = "[ERRO] {$rotulo}: " . $e->getMessage();
            }
        }
    };

    $ddl("
CREATE TABLE kb_chunks (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  provimento_id  INT NOT NULL,
  ordem          SMALLINT UNSIGNED NOT NULL,
  referencia     VARCHAR(60) NULL,
  conteudo       TEXT NOT NULL,
  embedding      BLOB NULL,
  dim            SMALLINT UNSIGNED NULL,
  hash_conteudo  CHAR(32) NOT NULL,
  indexado_em    DATETIME NULL,
  UNIQUE KEY uq_chunk (provimento_id, ordem),
  KEY idx_prov (provimento_id),
  FULLTEXT KEY ft_conteudo (conteudo),
  CONSTRAINT fk_kb_prov FOREIGN KEY (provimento_id)
    REFERENCES provimentos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_chunks');

    $ddl("
CREATE TABLE kb_consultas (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  funcionario   VARCHAR(120) NULL,
  pergunta      VARCHAR(1000) NOT NULL,
  chunks_ids    VARCHAR(255) NULL,
  resposta      MEDIUMTEXT NULL,
  ms_busca      INT NULL,
  ms_geracao    INT NULL,
  util          TINYINT NULL,
  criado_em     DATETIME NOT NULL,
  KEY idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_consultas');

    // Trava de indexacao: impede dois usuarios rodando ao mesmo tempo e
    // guarda o progresso para quem abrir a tela no meio do processo.
    $ddl("
CREATE TABLE kb_indexacao (
  id            TINYINT PRIMARY KEY,
  fase          VARCHAR(20) NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'ocioso',
  token         CHAR(32) NULL,
  funcionario   VARCHAR(120) NULL,
  processados   INT NOT NULL DEFAULT 0,
  total         INT NOT NULL DEFAULT 0,
  mensagem      VARCHAR(500) NULL,
  iniciado_em   DATETIME NULL,
  atualizado_em DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_indexacao');

    try {
        $conn->exec("INSERT IGNORE INTO kb_indexacao (id, status) VALUES (1, 'ocioso')");
    } catch (PDOException $e) {
        // tabela pode nao existir se o DDL acima falhou; ignora
    }

    $ddl("
CREATE TABLE kb_config (
  chave         VARCHAR(50) PRIMARY KEY,
  valor         TEXT NULL,
  funcionario   VARCHAR(120) NULL,
  atualizado_em DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_config');

    $ddl("
CREATE TABLE kb_modelos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  tipo       ENUM('chat','embedding') NOT NULL,
  nome       VARCHAR(120) NOT NULL,
  rotulo     VARCHAR(120) NULL,
  dimensao   SMALLINT UNSIGNED NULL,
  ativo      TINYINT(1) NOT NULL DEFAULT 0,
  criado_em  DATETIME NULL,
  UNIQUE KEY uq_modelo (tipo, nome),
  KEY idx_ativo (tipo, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_modelos');

    // Semente: modelos atuais. INSERT IGNORE nao sobrescreve escolha do usuario.
    try {
        $conn->exec("INSERT IGNORE INTO kb_modelos (tipo, nome, rotulo, dimensao, ativo, criado_em) VALUES
            ('chat',      'gemini-3.1-flash-lite', 'Gemini 3.1 Flash-Lite', NULL, 1, NOW()),
            ('chat',      'gemini-3.1-flash',      'Gemini 3.1 Flash',      NULL, 0, NOW()),
            ('embedding', 'gemini-embedding-001',  'Gemini Embedding 001',  768,  1, NOW())");
    } catch (PDOException $e) {
        // tabela pode nao ter sido criada; ignora
    }

    $ddl("
CREATE TABLE kb_conversas (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  funcionario   VARCHAR(120) NULL,
  titulo        VARCHAR(200) NULL,
  criado_em     DATETIME NOT NULL,
  atualizado_em DATETIME NOT NULL,
  KEY idx_func (funcionario, atualizado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_conversas');

    $ddl("
CREATE TABLE kb_mensagens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  conversa_id INT NOT NULL,
  papel       ENUM('user','assistant') NOT NULL,
  conteudo    MEDIUMTEXT NOT NULL,
  fontes      MEDIUMTEXT NULL,
  busca_usada VARCHAR(500) NULL,
  ms_total    INT NULL,
  util        TINYINT NULL,
  criado_em   DATETIME NOT NULL,
  KEY idx_conversa (conversa_id, id),
  CONSTRAINT fk_msg_conversa FOREIGN KEY (conversa_id)
    REFERENCES kb_conversas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_mensagens');

    // Relacoes entre normas: quem revoga ou altera quem.
    $ddl("
CREATE TABLE kb_relacoes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  origem_id      INT NOT NULL,
  destino_id     INT NULL,
  destino_texto  VARCHAR(160) NULL,
  tipo           ENUM('revoga_total','revoga_parcial','altera') NOT NULL,
  dispositivos   VARCHAR(255) NULL,
  trecho         TEXT NULL,
  status         ENUM('sugerida','confirmada','descartada') NOT NULL DEFAULT 'sugerida',
  confirmado_por VARCHAR(120) NULL,
  confirmado_em  DATETIME NULL,
  criado_em      DATETIME NULL,
  KEY idx_origem (origem_id),
  KEY idx_destino (destino_id),
  KEY idx_status (status),
  CONSTRAINT fk_rel_origem FOREIGN KEY (origem_id)
    REFERENCES provimentos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", 'tabela kb_relacoes');

    $ddl("ALTER TABLE provimentos ADD FULLTEXT KEY ft_conteudo_anexo (conteudo_anexo)",
         'FULLTEXT em provimentos.conteudo_anexo');

    // Colunas incrementais
    $coluna = function ($tabela, $col, $sql) use ($conn, $ddl, &$log) {
        $st = $conn->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
        );
        $st->execute(array(':t' => $tabela, ':c' => $col));
        if ((int) $st->fetchColumn() === 0) {
            $ddl($sql, "coluna {$tabela}.{$col}");
        } else {
            $log[] = "[SKIP] coluna {$tabela}.{$col}";
        }
    };

    $coluna('provimentos', 'kb_indexado_em',
        "ALTER TABLE provimentos ADD COLUMN kb_indexado_em DATETIME NULL");

    // Hash do conteudo: e o que detecta edicao de provimento ja indexado.
    // A data_cadastro nao muda quando o texto e corrigido, entao nao serve.
    $coluna('provimentos', 'kb_hash',
        "ALTER TABLE provimentos ADD COLUMN kb_hash CHAR(32) NULL");

    // Marca se o documento ja passou pela extracao de relacoes normativas.
    $coluna('provimentos', 'kb_relacoes_em',
        "ALTER TABLE provimentos ADD COLUMN kb_relacoes_em DATETIME NULL");

    // Situacao do trecho perante normas posteriores.
    $coluna('kb_chunks', 'situacao',
        "ALTER TABLE kb_chunks ADD COLUMN situacao ENUM('vigente','alterado','revogado')
         NOT NULL DEFAULT 'vigente'");

    return $log;
}

/** Verifica rapidamente se o schema ja existe (1 query em information_schema). */
/**
 * Verifica tabelas E colunas incrementais.
 * Conferir so as tabelas faz a migracao ser pulada numa base antiga que ja
 * tem as tabelas mas ainda nao tem as colunas adicionadas depois.
 */
function kbSchemaExiste(PDO $conn)
{
    try {
        $st = $conn->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('kb_chunks','kb_consultas','kb_indexacao','kb_config',
                                   'kb_modelos','kb_relacoes','kb_conversas','kb_mensagens')");
        $st->execute();
        if ((int) $st->fetchColumn() !== 8) {
            return false;
        }

        $st = $conn->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND ((TABLE_NAME = 'kb_chunks'   AND COLUMN_NAME = 'situacao')
                  OR (TABLE_NAME = 'provimentos' AND COLUMN_NAME IN
                      ('kb_hash','kb_indexado_em','kb_relacoes_em')))");
        $st->execute();
        return (int) $st->fetchColumn() === 4;
    } catch (PDOException $e) {
        return false;
    }
}
