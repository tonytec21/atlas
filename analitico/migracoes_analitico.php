<?php
/**
 * Atlas — Migrações automáticas do Relatório Analítico (Portal do Selo).
 *
 * Este arquivo é incluído tanto pelo módulo analítico quanto pelo módulo caixa.
 * Ao abrir qualquer uma das páginas, a estrutura do banco é criada/atualizada
 * sozinha — não é preciso rodar SQL na mão.
 *
 * O controle é feito pela tabela `atlas_migracoes`: cada bloco roda uma única
 * vez e, nas cargas seguintes, o custo é de um único SELECT.
 *
 * >>> AO CRIAR UMA NOVA MIGRAÇÃO NO FUTURO: acrescente um novo item em
 *     $blocos com uma chave inédita. Não altere blocos já publicados, pois
 *     eles não voltarão a rodar nas instalações que já os aplicaram.
 */

if (!function_exists('atlas_migrar_analitico')) {

    /**
     * Executa as migrações pendentes do módulo analítico.
     * Silenciosa por natureza: nenhuma falha de DDL pode derrubar a página.
     */
    function atlas_migrar_analitico(PDO $conn): void
    {
        // Uma vez por requisição, mesmo que vários arquivos chamem a função
        static $jaRodou = false;
        if ($jaRodou) return;
        $jaRodou = true;

        // ------------------------------------------------------------------
        // Controle de versões
        // ------------------------------------------------------------------
        $temControle = false;
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS atlas_migracoes (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    chave       VARCHAR(120) NOT NULL,
                    aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_chave (chave)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $temControle = true;
        } catch (Throwable $e) {
            // Sem controle de versão as migrações ainda rodam — apenas repetem
            // a cada carga. Como todas são idempotentes, não há efeito colateral.
        }

        $aplicadas = [];
        if ($temControle) {
            try {
                $st = $conn->query("SELECT chave FROM atlas_migracoes");
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $k) $aplicadas[$k] = true;
            } catch (Throwable $e) {}
        }

        // ------------------------------------------------------------------
        // Blocos de migração
        // ------------------------------------------------------------------
        $blocos = [];

        // --- Estrutura base do relatório analítico ---
        $blocos['analitico_base_v1'] = [
            "CREATE TABLE IF NOT EXISTS relatorios_analiticos (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                seq_linha        INT NULL,
                cartorio         VARCHAR(255) NULL,
                numero_selo      VARCHAR(80) NOT NULL,
                ato              VARCHAR(255) NULL,
                usuario          VARCHAR(255) NULL,
                isento           TINYINT(1) NOT NULL DEFAULT 0,
                cancelado        TINYINT(1) NOT NULL DEFAULT 0,
                diferido         TINYINT(1) NOT NULL DEFAULT 0,
                indeferido       TINYINT(1) NOT NULL DEFAULT 0,
                pendente_recurso TINYINT(1) NOT NULL DEFAULT 0,
                linha_sinal      TINYINT(1) NOT NULL DEFAULT 1,
                selagem          DATE NULL,
                operacao         DATETIME NULL,
                tipo             VARCHAR(120) NULL,
                emolumentos      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                ferj             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                fadep            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                ferc             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                femp             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                ferrfis          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                selo_valor       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                total            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                desconto         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                arquivo_origem   VARCHAR(255) NULL,
                uploaded_by      VARCHAR(120) NULL,
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NULL,
                UNIQUE KEY uq_selo_sinal (numero_selo, linha_sinal),
                KEY idx_selagem_flags (selagem, cancelado, isento, diferido, indeferido)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "ALTER TABLE relatorios_analiticos MODIFY COLUMN selagem DATE NULL",
            "ALTER TABLE relatorios_analiticos MODIFY COLUMN operacao DATETIME NULL",
        ];

        // --- Estrutura 2026: Desconto / Indeferido / estorno ---
        // O Portal do Selo passou a trazer no resumo as colunas "Desconto"
        // (estornos de cancelamento/retificação na mesma remessa, com valores
        // negativos) e "Indeferido" (atos cuja isenção foi indeferida e que,
        // portanto, voltam a ser cobráveis).
        $blocos['analitico_2026_v1'] = [
            "ALTER TABLE relatorios_analiticos ADD COLUMN indeferido       TINYINT(1) NOT NULL DEFAULT 0 AFTER diferido",
            "ALTER TABLE relatorios_analiticos ADD COLUMN pendente_recurso TINYINT(1) NOT NULL DEFAULT 0 AFTER indeferido",
            "ALTER TABLE relatorios_analiticos ADD COLUMN linha_sinal      TINYINT(1) NOT NULL DEFAULT 1 AFTER pendente_recurso",
            "ALTER TABLE relatorios_analiticos ADD COLUMN desconto         DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total",
            "ALTER TABLE relatorios_analiticos ADD INDEX idx_selagem_flags (selagem, cancelado, isento, diferido, indeferido)",

            // Bloco de resumo do topo da planilha, por arquivo importado.
            "CREATE TABLE IF NOT EXISTS relatorios_analiticos_resumo (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                arquivo_origem VARCHAR(255) NOT NULL,
                rubrica        VARCHAR(60)  NOT NULL,
                valor          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                desconto       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                indeferido     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                total          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                periodo_inicio DATE NULL,
                periodo_fim    DATE NULL,
                gerado_em      DATETIME NULL,
                uploaded_by    VARCHAR(120) NULL,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME NULL,
                UNIQUE KEY uq_arquivo_rubrica (arquivo_origem, rubrica)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            // Reclassifica o que já foi importado antes desta versão.
            "UPDATE relatorios_analiticos SET indeferido = 1       WHERE tipo LIKE '%indeferida%'",
            "UPDATE relatorios_analiticos SET pendente_recurso = 1 WHERE tipo LIKE '%endente de recurso%'",
            "UPDATE relatorios_analiticos SET linha_sinal = -1, desconto = ABS(total) WHERE total < 0 OR emolumentos < 0",
        ];

        foreach ($blocos as $chave => $comandos) {
            if (isset($aplicadas[$chave])) continue;

            foreach ($comandos as $ddl) {
                try {
                    $conn->exec($ddl);
                } catch (Throwable $e) {
                    // Coluna/índice já existente — comportamento esperado em
                    // bases que receberam as alterações manualmente.
                }
            }

            // Troca da chave única: só remove a antiga depois de confirmar que a
            // nova existe, para nunca ficar sem proteção contra duplicidade.
            if ($chave === 'analitico_2026_v1') {
                try {
                    $temNova = $conn->query("SHOW INDEX FROM relatorios_analiticos WHERE Key_name = 'uq_selo_sinal'")->fetch();
                    if (!$temNova) {
                        try { $conn->exec("ALTER TABLE relatorios_analiticos ADD UNIQUE KEY uq_selo_sinal (numero_selo, linha_sinal)"); } catch (Throwable $e) {}
                        $temNova = $conn->query("SHOW INDEX FROM relatorios_analiticos WHERE Key_name = 'uq_selo_sinal'")->fetch();
                    }
                    if ($temNova) {
                        try { $conn->exec("ALTER TABLE relatorios_analiticos DROP INDEX uq_numero_selo"); } catch (Throwable $e) {}
                    }
                } catch (Throwable $e) {}
            }

            if ($temControle) {
                try {
                    $ins = $conn->prepare("INSERT IGNORE INTO atlas_migracoes (chave) VALUES (:c)");
                    $ins->execute([':c' => $chave]);
                } catch (Throwable $e) {}
            }
        }
    }
}

if (!function_exists('atlas_migrar_analitico_seguro')) {

    /**
     * Versão à prova de erro para ser chamada de qualquer página.
     * Nunca lança exceção nem emite saída.
     */
    function atlas_migrar_analitico_seguro($conn): void
    {
        try {
            if ($conn instanceof PDO) atlas_migrar_analitico($conn);
        } catch (Throwable $e) {
            // Silencioso: a migração jamais pode impedir o carregamento da página.
        }
    }
}
