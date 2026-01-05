<?php
// relatorios/analiticos.php
include(__DIR__ . '/../os/session_check.php');
checkSession();
include(__DIR__ . '/../os/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

// ============================ DB: tabela (auto-criação) ============================
try {
    $conn = getDatabaseConnection();

    // Cria/atualiza tabela relatorios_analiticos
    $conn->exec("
        CREATE TABLE IF NOT EXISTS relatorios_analiticos (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            seq_linha        INT NULL,
            cartorio         VARCHAR(255) NULL,
            numero_selo      VARCHAR(80) NOT NULL,
            ato              VARCHAR(255) NULL,
            usuario          VARCHAR(255) NULL,
            isento           TINYINT(1) NOT NULL DEFAULT 0,
            cancelado        TINYINT(1) NOT NULL DEFAULT 0,
            diferido         TINYINT(1) NOT NULL DEFAULT 0,
            selagem          DATE NULL,                  -- era VARCHAR(100), agora DATE (apenas data)
            operacao         DATETIME NULL,              -- era VARCHAR(100), agora DATETIME (data e hora)
            tipo             VARCHAR(120) NULL,
            emolumentos      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            ferj             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            fadep            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            ferc             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            femp             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            ferrfis          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            selo_valor       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            total            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            arquivo_origem   VARCHAR(255) NULL,
            uploaded_by      VARCHAR(120) NULL,
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME NULL,
            UNIQUE KEY uq_numero_selo (numero_selo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    // Silencioso
}

// ============================ Helpers servidor ============================
function toBoolFlag($v): int {
    $v = trim((string)$v);
    if ($v === '') return 0;
    $vLower = mb_strtolower($v, 'UTF-8');
    $truthy = ['1','sim','s','yes','y','true','verdadeiro','ok'];
    return in_array($vLower, $truthy, true) ? 1 : (is_numeric($v) && (int)$v === 1 ? 1 : 0);
}

function toMoney($v): float {
    if ($v === null) return 0.0;
    $s = trim((string)$v);
    if ($s === '') return 0.0;

    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false && strpos($s, '.') === false) {
        $s = str_replace(',', '.', $s);
    }

    $s = preg_replace('/[^\d\.\-]/', '', $s);
    if ($s === '' || $s === '-' || $s === '.')
        return 0.0;

    return (float)$s;
}

function normText($v): string {
    $v = (string)$v;
    $v = str_replace(["\r", "\n", "\t"], ' ', $v);
    return trim($v);
}

function readCell($sheet, int $colIndex, int $rowIndex) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    return $sheet->getCell($colLetter . $rowIndex)->getValue();
}

/**
 * Converte um valor Excel (string dd/mm/yyyy [hh:mm:ss] ou serial numérico) para
 * string de data no formato do MySQL.
 * - $withTime=false => 'Y-m-d' (DATE)
 * - $withTime=true  => 'Y-m-d H:i:s' (DATETIME)
 */
function parseExcelDate($v, bool $withTime = false): ?string {
    if ($v === null) return null;
    if ($v instanceof DateTimeInterface) {
        return $withTime ? $v->format('Y-m-d H:i:s') : $v->format('Y-m-d');
    }

    $s = trim((string)$v);
    if ($s === '') return null;

    // Tentar como serial numérico do Excel
    if (is_numeric($s)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$s);
            return $withTime ? $dt->format('Y-m-d H:i:s') : $dt->format('Y-m-d');
        } catch (Throwable $e) {
            // continua para parsing textual
        }
    }

    // Normaliza espaços duplos e separadores
    $s = preg_replace('/\s+/', ' ', $s);

    // Tentar formatos explícitos pt-BR
    if ($withTime) {
        $dt = DateTime::createFromFormat('d/m/Y H:i:s', $s) ?: DateTime::createFromFormat('d/m/Y H:i', $s);
        if ($dt !== false) return $dt->format('Y-m-d H:i:s');
        // Se vier "06/10/2025  08:22:09" (duplo espaço)
        $s2 = preg_replace('/\s{2,}/', ' ', $s);
        $dt = DateTime::createFromFormat('d/m/Y H:i:s', $s2) ?: DateTime::createFromFormat('d/m/Y H:i', $s2);
        if ($dt !== false) return $dt->format('Y-m-d H:i:s');
    } else {
        $dt = DateTime::createFromFormat('d/m/Y', $s);
        if ($dt !== false) return $dt->format('Y-m-d');
    }

    // Fallback genérico
    $ts = strtotime($s);
    if ($ts !== false) {
        return $withTime ? date('Y-m-d H:i:s', $ts) : date('Y-m-d', $ts);
    }

    return null;
}

// ============================ API: Buscar Relatórios COM PAGINAÇÃO ============================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $cartorio = isset($_GET['cartorio']) ? trim($_GET['cartorio']) : '';
    $ato = isset($_GET['ato']) ? trim($_GET['ato']) : '';
    $dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
    $dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';
    
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 50;
    $offset = ($page - 1) * $perPage;
    
    $sql = "SELECT * FROM relatorios_analiticos WHERE 1=1";
    $sqlCount = "SELECT COUNT(*) as total FROM relatorios_analiticos WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $condition = " AND (numero_selo LIKE :search OR ato LIKE :search OR usuario LIKE :search)";
        $sql .= $condition;
        $sqlCount .= $condition;
        $params['search'] = "%$search%";
    }

    if ($cartorio !== '') {
        $condition = " AND cartorio LIKE :cartorio";
        $sql .= $condition;
        $sqlCount .= $condition;
        $params['cartorio'] = "%$cartorio%";
    }

    if ($ato !== '') {
        $condition = " AND ato LIKE :ato";
        $sql .= $condition;
        $sqlCount .= $condition;
        $params['ato'] = "%$ato%";
    }

    if ($dataInicio !== '' && $dataFim !== '') {
        $condition = " AND created_at BETWEEN :data_inicio AND :data_fim";
        $sql .= $condition;
        $sqlCount .= $condition;
        $params['data_inicio'] = "$dataInicio 00:00:00";
        $params['data_fim']    = "$dataFim 23:59:59";
    }

    try {
        // Conta total de registros
        $stmtCount = $conn->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue(':' . $key, $value);
        }
        $stmtCount->execute();
        $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        $totalPages = ceil($totalRecords / $perPage);
        
        // Busca registros da página atual
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================ Upload/Processamento (AJAX) ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    header('Content-Type: application/json; charset=utf-8');

    $hasSpreadsheet = false;
    $autoloadPaths = [
        '/vendor/autoload.php',
        (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/vendor/autoload.php' : null),
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../os/vendor/autoload.php'
    ];
    foreach ($autoloadPaths as $ap) {
        if ($ap && is_file($ap)) {
            require_once $ap;
            if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                $hasSpreadsheet = true;
                break;
            }
        }
    }

    if (!$hasSpreadsheet) {
        echo json_encode([
            'success' => false,
            'error'   => "Dependência não encontrada: phpoffice/phpspreadsheet.\nInstale com: composer require phpoffice/phpspreadsheet"
        ]);
        exit;
    }

    if (empty($_FILES['relatorios']) || !isset($_FILES['relatorios']['name'])) {
        echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.']);
        exit;
    }

    $uploader = isset($_SESSION['username']) ? $_SESSION['username'] : 'desconhecido';
    $totalFiles = count($_FILES['relatorios']['name']);
    $summary = [
        'files'    => $totalFiles,
        'inserted' => 0,
        'updated'  => 0,
        'skipped'  => 0,
        'errors'   => []
    ];

    $sql = "
        INSERT INTO relatorios_analiticos
        (seq_linha, cartorio, numero_selo, ato, usuario, isento, cancelado, diferido, selagem, operacao, tipo,
         emolumentos, ferj, fadep, ferc, femp, ferrfis, selo_valor, total, arquivo_origem, uploaded_by, created_at, updated_at)
        VALUES
        (:seq_linha, :cartorio, :numero_selo, :ato, :usuario, :isento, :cancelado, :diferido, :selagem, :operacao, :tipo,
         :emolumentos, :ferj, :fadep, :ferc, :femp, :ferrfis, :selo_valor, :total, :arquivo_origem, :uploaded_by, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            cartorio = VALUES(cartorio),
            ato = VALUES(ato),
            usuario = VALUES(usuario),
            isento = VALUES(isento),
            cancelado = VALUES(cancelado),
            diferido = VALUES(diferido),
            selagem = VALUES(selagem),
            operacao = VALUES(operacao),
            tipo = VALUES(tipo),
            emolumentos = VALUES(emolumentos),
            ferj = VALUES(ferj),
            fadep = VALUES(fadep),
            ferc = VALUES(ferc),
            femp = VALUES(femp),
            ferrfis = VALUES(ferrfis),
            selo_valor = VALUES(selo_valor),
            total = VALUES(total),
            arquivo_origem = VALUES(arquivo_origem),
            uploaded_by = VALUES(uploaded_by),
            updated_at = NOW()
    ";
    $stmt = $conn->prepare($sql);

    $ROW_HEADERS = 17;
    $ROW_DATA    = 18;

    for ($i = 0; $i < $totalFiles; $i++) {
        $name = $_FILES['relatorios']['name'][$i];
        $tmp  = $_FILES['relatorios']['tmp_name'][$i];
        $err  = $_FILES['relatorios']['error'][$i];

        if ($err !== UPLOAD_ERR_OK) {
            $summary['errors'][] = "Falha no upload de '{$name}' (código {$err}).";
            continue;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

            $expectedHeaders = [
                '#','Cartório','Nº do Selo','Ato','Usuário','Isento','Cancelado','Diferido','Selagem','Operação','Tipo',
                'Emolumentos','FERJ','FADEP','FERC','FEMP','FERRFIS','Selo','Total'
            ];
            $headersOk = true;
            $headersRead = [];
            for ($c = 1; $c <= min($highestColIndex, count($expectedHeaders)); $c++) {
                $headersRead[] = normText(readCell($sheet, $c, $ROW_HEADERS));
            }
            for ($h = 0; $h < count($expectedHeaders); $h++) {
                if (!isset($headersRead[$h]) || mb_strtolower($headersRead[$h], 'UTF-8') !== mb_strtolower($expectedHeaders[$h], 'UTF-8')) {
                    $headersOk = false;
                    break;
                }
            }
            if (!$headersOk) {
                $summary['errors'][] = "Cabeçalho inesperado em '{$name}'. Verifique se os títulos estão na linha 17.";
                continue;
            }

            for ($row = $ROW_DATA; $row <= $highestRow; $row++) {
                $numeroSelo = normText(readCell($sheet, 3, $row));
                if ($numeroSelo === '') {
                    continue;
                }

                $seqLinha   = normText(readCell($sheet, 1, $row));
                $cartorio   = normText(readCell($sheet, 2, $row));
                $ato        = normText(readCell($sheet, 4, $row));
                $usuario    = normText(readCell($sheet, 5, $row));
                $isento     = toBoolFlag(readCell($sheet, 6, $row));
                $cancelado  = toBoolFlag(readCell($sheet, 7, $row));
                $diferido   = toBoolFlag(readCell($sheet, 8, $row));

                // ====== CONVERTE DATAS ======
                // Selagem: apenas data (DATE)
                $rawSelagem = readCell($sheet, 9, $row);
                $selagemDb  = parseExcelDate($rawSelagem, false); // 'Y-m-d' ou null

                // Operação: data e hora (DATETIME)
                $rawOperacao = readCell($sheet, 10, $row);
                $operacaoDb  = parseExcelDate($rawOperacao, true); // 'Y-m-d H:i:s' ou null

                $tipo       = normText(readCell($sheet, 11, $row));
                $emol       = toMoney(readCell($sheet, 12, $row));
                $ferj       = toMoney(readCell($sheet, 13, $row));
                $fadep      = toMoney(readCell($sheet, 14, $row));
                $ferc       = toMoney(readCell($sheet, 15, $row));
                $femp       = toMoney(readCell($sheet, 16, $row));
                $ferrfis    = toMoney(readCell($sheet, 17, $row));
                $seloValor  = toMoney(readCell($sheet, 18, $row));
                $total      = toMoney(readCell($sheet, 19, $row));

                if ($seqLinha === '') {
                    $stmt->bindValue(':seq_linha', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':seq_linha', (int)$seqLinha, PDO::PARAM_INT);
                }
                $stmt->bindValue(':cartorio',       $cartorio);
                $stmt->bindValue(':numero_selo',    $numeroSelo);
                $stmt->bindValue(':ato',            $ato);
                $stmt->bindValue(':usuario',        $usuario);
                $stmt->bindValue(':isento',         $isento, PDO::PARAM_INT);
                $stmt->bindValue(':cancelado',      $cancelado, PDO::PARAM_INT);
                $stmt->bindValue(':diferido',       $diferido, PDO::PARAM_INT);

                if ($selagemDb === null) {
                    $stmt->bindValue(':selagem', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':selagem', $selagemDb);
                }

                if ($operacaoDb === null) {
                    $stmt->bindValue(':operacao', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':operacao', $operacaoDb);
                }

                $stmt->bindValue(':tipo',           $tipo);
                $stmt->bindValue(':emolumentos',    $emol);
                $stmt->bindValue(':ferj',           $ferj);
                $stmt->bindValue(':fadep',          $fadep);
                $stmt->bindValue(':ferc',           $ferc);
                $stmt->bindValue(':femp',           $femp);
                $stmt->bindValue(':ferrfis',        $ferrfis);
                $stmt->bindValue(':selo_valor',     $seloValor);
                $stmt->bindValue(':total',          $total);
                $stmt->bindValue(':arquivo_origem', $name);
                $stmt->bindValue(':uploaded_by',    $uploader);

                try {
                    $stmt->execute();
                    $aff = $stmt->rowCount();
                    if ($aff === 1) $summary['inserted']++;
                    elseif ($aff === 2) $summary['updated']++;
                    else $summary['skipped']++;
                } catch (Exception $ie) {
                    $summary['errors'][] = "Linha {$row} em '{$name}': " . $ie->getMessage();
                }
            }
        } catch (Throwable $ex) {
            $summary['errors'][] = "Erro ao ler '{$name}': " . $ex->getMessage();
        }
    }

    echo json_encode(['success' => true] + $summary);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atlas - Relatórios Analíticos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fontes & Ícones -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">

    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>  
/* ===================== CSS VARIABLES ===================== */  
:root {  
  /* Typography */  
  --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;  
  --font-mono: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;  
   
  /* Spacing Scale */  
  --space-xs: 4px;  
  --space-sm: 8px;  
  --space-md: 16px;  
  --space-lg: 24px;  
  --space-xl: 32px;  
  --space-2xl: 48px;  
  --space-3xl: 64px;  
   
  /* Border Radius */  
  --radius-xs: 6px;  
  --radius-sm: 10px;  
  --radius-md: 14px;  
  --radius-lg: 20px;  
  --radius-xl: 28px;  
  --radius-2xl: 36px;  
  --radius-full: 9999px;  
   
  /* Shadows */  
  --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);  
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.04);  
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.06);  
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.12), 0 4px 16px rgba(0, 0, 0, 0.08);  
  --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.16), 0 8px 24px rgba(0, 0, 0, 0.12);  
  --shadow-2xl: 0 24px 64px rgba(0, 0, 0, 0.20), 0 12px 32px rgba(0, 0, 0, 0.16);  
   
  /* Light Theme */  
  --bg-primary: #fafbfc;  
  --bg-secondary: #f4f6f8;  
  --bg-tertiary: #ffffff;  
  --text-primary: #0d1117;  
  --text-secondary: #424a53;  
  --text-tertiary: #656d76;  
  --text-quaternary: #8b949e;  
  --border-primary: rgba(13, 17, 23, 0.08);  
  --border-secondary: rgba(13, 17, 23, 0.12);  
  --surface: rgba(255, 255, 255, 0.92);  
  --surface-hover: rgba(248, 250, 252, 0.96);  
   
  /* Brand Colors */  
  --brand-primary: #6366f1;  
  --brand-primary-light: #818cf8;  
  --brand-primary-dark: #4f46e5;  
  --brand-secondary: #8b5cf6;  
  --brand-accent: #06b6d4;  
  --brand-success: #10b981;  
  --brand-warning: #f59e0b;  
  --brand-error: #ef4444;  
   
  /* Gradients */  
  --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);  
  --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);  
  --gradient-success: linear-gradient(135deg, #4ade80 0%, #22d3ee 100%);  
  --gradient-warning: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);  
  --gradient-surface: linear-gradient(145deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 251, 252, 0.98) 100%);  
  --gradient-mesh:   
    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.06) 0px, transparent 50%),  
    radial-gradient(at 0% 100%, rgba(244, 114, 182, 0.06) 0px, transparent 50%);  
}  

/* ===================== DARK MODE VARIABLES ===================== */  
.dark-mode {  
  --bg-primary: #0d1117;  
  --bg-secondary: #161b22;  
  --bg-tertiary: #21262d;  
  --text-primary: #f0f6fc;  
  --text-secondary: #c9d1d9;  
  --text-tertiary: #8b949e;  
  --text-quaternary: #6e7681;  
  --border-primary: rgba(240, 246, 252, 0.10);  
  --border-secondary: rgba(240, 246, 252, 0.14);  
  --surface: rgba(33, 38, 45, 0.92);  
  --surface-hover: rgba(48, 54, 61, 0.96);  
   
  --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.6);  
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.7), 0 1px 4px rgba(0, 0, 0, 0.6);  
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.8), 0 2px 8px rgba(0, 0, 0, 0.7);  
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.85), 0 4px 16px rgba(0, 0, 0, 0.8);  
  --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.9), 0 8px 24px rgba(0, 0, 0, 0.85);  
  --shadow-2xl: 0 24px 64px rgba(0, 0, 0, 0.95), 0 12px 32px rgba(0, 0, 0, 0.9);  
   
  --gradient-surface: linear-gradient(145deg, rgba(33, 38, 45, 0.95) 0%, rgba(22, 27, 34, 0.98) 100%);  
  --gradient-mesh:   
    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),  
    radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),  
    radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.12) 0px, transparent 50%),  
    radial-gradient(at 0% 100%, rgba(244, 114, 182, 0.12) 0px, transparent 50%);  
}  

/* ===================== BASE OVERRIDES ===================== */  
body {
  font-family: var(--font-primary) !important;
  background: var(--bg-primary) !important;
  color: var(--text-primary) !important;
  transition: background-color 0.3s ease, color 0.3s ease;
  margin: 0 !important;
  padding: 0 !important;
  min-height: 100vh !important;
  display: flex !important;
  flex-direction: column !important;
}

.main-content {
  position: relative;
  min-height: auto;
  flex: 1;
  padding: var(--space-xl) var(--space-lg);
}

.main-content::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--gradient-mesh);
  pointer-events: none;
  z-index: 0;
  opacity: 0.4;
}

.container {
  position: relative;
  z-index: 1;
  padding-bottom: var(--space-xl);
}

/* ===================== HERO SECTION ===================== */  
.page-hero {  
  position: relative;  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-2xl);  
  margin-bottom: var(--space-xl);  
  box-shadow: var(--shadow-xl);  
  overflow: hidden;  
  animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);  
}  

.page-hero::before {  
  content: '';  
  position: absolute;  
  top: 0;  
  left: 0;  
  right: 0;  
  height: 5px;  
  background: var(--gradient-primary);  
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;  
}  

@keyframes fadeInUp {  
  from {  
    opacity: 0;  
    transform: translateY(30px);  
  }  
  to {  
    opacity: 1;  
    transform: translateY(0);  
  }  
}  

.title-row {  
  display: flex;  
  align-items: center;  
  gap: var(--space-lg);  
  margin-bottom: var(--space-lg);  
}  

.title-icon i {  
  font-size: 32px;  
  color: var(--text-primary);  
  position: relative;  
  z-index: 1;  
}  

.dark-mode .title-icon {
  color: white; 
}

.title-icon::before {  
  content: '';  
  position: absolute;  
  inset: -50%;  
  background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);  
  animation: iconShine 3s ease-in-out infinite;  
}  

@keyframes iconShine {  
  0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }  
  50% { transform: translateX(100%) translateY(100%) rotate(45deg); }  
}  

.page-hero h1 {  
  font-size: 32px;  
  font-weight: 800;  
  letter-spacing: -0.03em;  
  color: var(--text-primary) !important;  
  margin: 0;  
  line-height: 1.2;  
}

.subtitle {
  font-size: 14px;
  color: var(--text-secondary);
  margin-top: var(--space-sm);
  line-height: 1.6;
}

/* ===================== CONTINUAÇÃO DO CSS ===================== */  

/* ===================== TOP ACTIONS ===================== */  
.top-actions {  
  display: flex;  
  flex-wrap: wrap;  
  gap: var(--space-md);  
  margin-bottom: var(--space-xl);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.2s backwards;  
}  

@keyframes fadeIn {  
  from { opacity: 0; }  
  to { opacity: 1; }  
}  

/* ===================== UPLOAD CARD ===================== */  
.upload-card {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-xl);  
  margin-bottom: var(--space-xl);  
  box-shadow: var(--shadow-lg);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.3s backwards;  
}  

.card-title {  
  font-size: 18px;  
  font-weight: 700;  
  margin-bottom: var(--space-lg);  
  color: var(--text-primary);  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
}  

.card-title i {  
  font-size: 22px;  
  color: var(--brand-primary);  
}  

.dropzone {  
  border: 3px dashed var(--brand-primary);  
  background: rgba(99, 102, 241, .06);  
  border-radius: var(--radius-lg);  
  padding: var(--space-2xl) var(--space-xl);  
  text-align: center;  
  cursor: pointer;  
  transition: all .25s ease;  
  outline: none;  
}  

.dropzone:focus {  
  box-shadow: 0 0 0 6px rgba(99, 102, 241, .15);  
}  

.dropzone:hover {  
  background: rgba(99, 102, 241, .10);  
  transform: translateY(-2px);  
}  

.dropzone.dragover {  
  background: rgba(99, 102, 241, .14);  
  border-color: var(--brand-primary-light);  
}  

.dz-icon {  
  width: 64px;  
  height: 64px;  
  border-radius: var(--radius-md);  
  background: rgba(99, 102, 241, .15);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  margin: 0 auto var(--space-md);  
}  

.dz-icon i {  
  font-size: 32px;  
  color: var(--brand-primary);  
}  

.dz-title {  
  font-weight: 800;  
  font-size: 18px;  
  color: var(--text-primary);  
  margin-bottom: 6px;  
}  

.dz-sub {  
  color: var(--text-tertiary);  
  font-size: 14px;  
}  

.file-list {  
  margin-top: var(--space-md);  
}  

.file-item {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  gap: var(--space-sm);  
  padding: 12px 16px;  
  border: 2px solid var(--border-primary);  
  background: var(--bg-tertiary);  
  border-radius: var(--radius-md);  
  margin-bottom: var(--space-sm);  
  animation: fadeInUp .35s ease both;  
}  

.file-name {  
  font-weight: 700;  
  color: var(--text-primary);  
  word-break: break-all;  
  display: flex;  
  align-items: center;  
  gap: 8px;  
}  

.file-name i {  
  font-size: 20px;  
  color: var(--brand-success);  
}  

.file-size {  
  color: var(--text-tertiary);  
  font-size: 13px;  
  white-space: nowrap;  
}  

/* ===================== RESULT STATS ===================== */  
.result-grid {  
  display: grid;  
  grid-template-columns: repeat(4, minmax(0, 1fr));  
  gap: var(--space-md);  
  margin-top: var(--space-md);  
}  

.stat {  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  text-align: center;  
  transition: all 0.3s ease;  
}  

.stat:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
}  

.stat h3 {  
  margin: 0;  
  font-weight: 900;  
  font-size: 32px;  
  color: var(--text-primary);  
}  

.stat small {  
  color: var(--text-secondary);  
  font-size: 12px;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  font-weight: 700;  
}  

.stat.ok {  
  border-color: var(--brand-success);  
  background: rgba(16, 185, 129, .08);  
}  

.stat.warn {  
  border-color: var(--brand-warning);  
  background: rgba(245, 158, 11, .08);  
}  

.stat.err {  
  border-color: var(--brand-error);  
  background: rgba(239, 68, 68, .08);  
}  

.errors-box {  
  margin-top: var(--space-md);  
  background: var(--bg-tertiary);  
  border: 2px solid var(--brand-error);  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  max-height: 300px;  
  overflow: auto;  
}  

.errors-box strong {  
  display: block;  
  margin-bottom: var(--space-sm);  
  color: var(--brand-error);  
  display: flex;  
  align-items: center;  
  gap: 8px;  
}  

.errors-box strong i {  
  font-size: 20px;  
}  

.errors-box code {  
  display: block;  
  white-space: pre-wrap;  
  word-break: break-word;  
  color: var(--brand-error);  
  font-size: 13px;  
  line-height: 1.6;  
}  

/* ===================== FILTER SECTION ===================== */  
.filter-card {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-xl);  
  margin-bottom: var(--space-xl);  
  box-shadow: var(--shadow-lg);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.4s backwards;  
}  

.filter-header {  
  display: flex;  
  align-items: center;  
  gap: var(--space-md);  
  margin-bottom: var(--space-lg);  
  padding-bottom: var(--space-md);  
  border-bottom: 2px solid var(--border-primary);  
}  

.filter-header i {  
  font-size: 24px;  
  color: var(--brand-primary);  
}  

.filter-header h3 {  
  font-size: 18px;  
  font-weight: 700;  
  margin: 0;  
  color: var(--text-primary);  
}  

.search-form {  
  display: grid;  
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));  
  gap: var(--space-md);  
  margin-bottom: var(--space-lg);  
}  

.form-group {  
  display: flex;  
  flex-direction: column;  
  gap: 6px;  
}  

.form-label {  
  font-size: 13px;  
  font-weight: 700;  
  color: var(--text-secondary) !important;  
  margin-bottom: var(--space-sm);  
  letter-spacing: -0.01em;  
  text-transform: uppercase;  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.form-label i {  
  font-size: 14px;  
  color: var(--brand-primary);  
}  

.form-control {  
  background: var(--bg-tertiary) !important;  
  border: 2px solid var(--border-primary) !important;  
  border-radius: var(--radius-md) !important;  
  padding: 12px 16px;  
  font-size: 15px;  
  color: var(--text-primary) !important;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  font-weight: 500;  
}  

.form-control::placeholder {  
  color: var(--text-quaternary) !important;  
  opacity: 1;  
}  

.form-control:focus {  
  outline: none !important;  
  border-color: var(--brand-primary) !important;  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important;  
  background: var(--surface) !important;  
  color: var(--text-primary) !important;  
}  

.form-control:hover {  
  border-color: var(--border-secondary) !important;  
}  

.btn {  
  position: relative;  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  gap: var(--space-sm);  
  padding: 12px 24px;  
  font-family: var(--font-primary);  
  font-size: 15px;  
  font-weight: 700;  
  line-height: 1;  
  border: none;  
  border-radius: var(--radius-md) !important;  
  cursor: pointer;  
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);  
  white-space: nowrap;  
}  

.btn i {  
  font-size: 16px;  
  transition: transform 0.3s ease;  
}  

.btn-primary {  
  background: var(--gradient-primary) !important;  
  color: white !important;  
  box-shadow: var(--shadow-md), inset 0 1px 0 rgba(255, 255, 255, 0.1);  
}  

.btn-primary:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-xl), inset 0 1px 0 rgba(255, 255, 255, 0.1);  
  color: white !important;  
}  

.btn-secondary {  
  background: linear-gradient(135deg, #64748b, #475569) !important;  
  color: white !important;  
  box-shadow: var(--shadow-sm);  
  border: none !important;  
}  

.btn-secondary:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  color: white !important;  
}  

.btn-success {  
  background: var(--gradient-success) !important;  
  color: white !important;  
  box-shadow: var(--shadow-sm);  
}  

.btn-success:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  color: white !important;  
}  

.btn-sm {  
  padding: 8px 16px !important;  
  font-size: 13px !important;  
}  

.btn-info {  
  background: linear-gradient(135deg, #06b6d4, #0891b2) !important;  
  color: white !important;  
  box-shadow: var(--shadow-sm);  
}  

.btn-info:hover {  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-md);  
  color: white !important;  
}  

.btn:active {  
  transform: translateY(0) !important;  
}  

.btn:disabled {  
  opacity: 0.6;  
  cursor: not-allowed;  
  transform: none !important;  
}  

/* ===================== RESULTS ===================== */  
.results-header {  
  display: flex;  
  justify-content: space-between;  
  align-items: center;  
  margin-bottom: var(--space-md);  
  padding: var(--space-md);  
  background: var(--bg-secondary);  
  border-radius: var(--radius-md);  
  border: 2px solid var(--border-primary);  
}  

.results-header h5 {  
  margin: 0;  
  font-size: 16px;  
  font-weight: 800;  
  color: var(--text-primary);  
  display: flex;  
  align-items: center;  
  gap: 8px;  
}  

.results-header h5 i {  
  font-size: 20px;  
  color: var(--brand-primary);  
}  

.results-count {  
  display: inline-flex;  
  align-items: center;  
  gap: 6px;  
  padding: 6px 14px;  
  background: var(--brand-primary);  
  color: white;  
  border-radius: 999px;  
  font-size: 13px;  
  font-weight: 700;  
}  

.results-count i {  
  font-size: 16px;  
}  

/* ===================== TABLE STYLES ===================== */  
.desktop-table {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  padding: var(--space-lg);  
  box-shadow: var(--shadow-lg);  
  animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.5s backwards;  
}  

.table-responsive {  
  border-radius: var(--radius-lg);  
  overflow: hidden;  
}  

.table {  
  margin-bottom: 0;  
  color: var(--text-primary) !important;  
  width: 100%;  
}  

.table thead th {  
  background: var(--bg-secondary) !important;  
  color: var(--text-primary) !important;  
  font-weight: 700;  
  font-size: 13px;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  padding: 16px 12px;  
  border-bottom: 2px solid var(--border-primary) !important;  
  white-space: nowrap;  
  border: none !important;  
}  

.table thead th i {  
  margin-right: 4px;  
  font-size: 14px;  
}  

.table tbody td {  
  padding: 16px 12px;  
  vertical-align: middle;  
  border-bottom: 1px solid var(--border-primary) !important;  
  font-size: 14px;  
  color: var(--text-secondary) !important;  
  background: transparent !important;  
  border-left: none !important;  
  border-right: none !important;  
  border-top: none !important;  
}  

.table tbody tr {  
  transition: all 0.2s ease;  
  background: transparent !important;  
  cursor: pointer;  
  border: none !important;  
}  

.table tbody tr:hover {  
  background: var(--surface-hover) !important;  
  transform: scale(1.002);  
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);  
}  

.badge {  
  display: inline-flex;  
  align-items: center;  
  gap: 4px;  
  padding: 4px 10px;  
  border-radius: 999px;  
  font-size: 11px;  
  font-weight: 700;  
  letter-spacing: 0.02em;  
}  

.badge i {  
  font-size: 12px;  
}  

.badge-success {  
  background: rgba(16, 185, 129, 0.15);  
  color: var(--brand-success);  
  border: 1.5px solid rgba(16, 185, 129, 0.3);  
}  

.badge-danger {  
  background: rgba(239, 68, 68, 0.15);  
  color: var(--brand-error);  
  border: 1.5px solid rgba(239, 68, 68, 0.3);  
}  

.badge-warning {  
  background: rgba(245, 158, 11, 0.15);  
  color: var(--brand-warning);  
  border: 1.5px solid rgba(245, 158, 11, 0.3);  
}  

.badge-secondary {  
  background: rgba(107, 114, 128, 0.15);  
  color: var(--text-secondary);  
  border: 1.5px solid rgba(107, 114, 128, 0.3);  
}  

.badge-info {  
  background: rgba(6, 182, 212, 0.15);  
  color: var(--brand-accent);  
  border: 1.5px solid rgba(6, 182, 212, 0.3);  
}  

/* ===================== MODAL DE DETALHES ===================== */  
.modal-backdrop {  
  position: fixed;  
  inset: 0;  
  background: rgba(0, 0, 0, 0.75);  
  backdrop-filter: blur(8px);  
  z-index: 9998;  
  display: none;  
  animation: fadeIn 0.25s ease;  
}  

.modal-backdrop.show {  
  display: block;  
}  

.detail-modal {  
  position: fixed;  
  inset: 0;  
  z-index: 9999;  
  display: none;  
  align-items: center;  
  justify-content: center;  
  padding: var(--space-lg);  
  overflow-y: auto;  
  animation: fadeIn 0.3s ease;  
}  

.detail-modal.show {  
  display: flex;  
}  

.modal-container {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(32px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-xl);  
  box-shadow: var(--shadow-2xl);  
  width: 100%;  
  max-width: 900px;  
  max-height: 90vh;  
  overflow: hidden;  
  animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);  
  display: flex;  
  flex-direction: column;  
}  

@keyframes modalSlideUp {  
  from {  
    opacity: 0;  
    transform: translateY(50px) scale(0.95);  
  }  
  to {  
    opacity: 1;  
    transform: translateY(0) scale(1);  
  }  
}  

.modal-header {  
  position: relative;  
  padding: var(--space-xl);  
  border-bottom: 2px solid var(--border-primary);  
  background: var(--bg-secondary);  
}  

.modal-header::before {  
  content: '';  
  position: absolute;  
  top: 0;  
  left: 0;  
  right: 0;  
  height: 5px;  
  background: var(--gradient-primary);  
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;  
}  

.modal-title-row {  
  display: flex;  
  align-items: center;  
  justify-content: space-between;  
  gap: var(--space-md);  
}  

.modal-title-left {  
  display: flex;  
  align-items: center;  
  gap: var(--space-md);  
}  

.modal-icon {  
  width: 64px;  
  height: 64px;  
  border-radius: var(--radius-lg);  
  background: var(--gradient-primary);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  flex-shrink: 0;  
  box-shadow: var(--shadow-lg);  
}  

.modal-icon i {  
  font-size: 32px;  
  color: white;  
}  

.modal-title-text h2 {  
  font-size: 24px;  
  font-weight: 800;  
  margin: 0 0 4px 0;  
  color: var(--text-primary);  
  letter-spacing: -0.02em;  
}  

.modal-title-text p {  
  margin: 0;  
  font-size: 13px;  
  color: var(--text-tertiary);  
  font-weight: 600;  
}  

.modal-close {  
  width: 48px;  
  height: 48px;  
  border-radius: var(--radius-md);  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  cursor: pointer;  
  transition: all 0.25s ease;  
  flex-shrink: 0;  
}  

.modal-close:hover {  
  background: var(--brand-error);  
  border-color: var(--brand-error);  
  transform: rotate(90deg);  
}  

.modal-close i {  
  font-size: 20px;  
  color: var(--text-primary);  
  transition: color 0.25s ease;  
}  

.modal-close:hover i {  
  color: white;  
}  

.modal-body {  
  padding: var(--space-xl);  
  overflow-y: auto;  
  flex: 1;  
}  

.detail-section {  
  margin-bottom: var(--space-xl);  
}  

.detail-section:last-child {  
  margin-bottom: 0;  
}  

.section-title {  
  font-size: 14px;  
  font-weight: 800;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  color: var(--text-primary);  
  margin-bottom: var(--space-md);  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
  padding-bottom: var(--space-sm);  
  border-bottom: 2px solid var(--border-primary);  
}  

.section-title i {  
  font-size: 18px;  
  color: var(--brand-primary);  
}  

.detail-grid {  
  display: grid;  
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));  
  gap: var(--space-md);  
}  

.detail-item {  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  padding: var(--space-md);  
  transition: all 0.25s ease;  
}  

.detail-item:hover {  
  border-color: var(--brand-primary);  
  transform: translateY(-2px);  
  box-shadow: var(--shadow-sm);  
}  

.detail-label {  
  font-size: 11px;  
  font-weight: 800;  
  text-transform: uppercase;  
  letter-spacing: 0.05em;  
  color: var(--text-tertiary);  
  margin-bottom: 6px;  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.detail-label i {  
  font-size: 13px;  
  color: var(--brand-primary);  
}  

.detail-value {  
  font-size: 16px;  
  font-weight: 700;  
  color: var(--text-primary);  
  word-break: break-word;  
  line-height: 1.4;  
}  

.detail-value.money {  
  font-size: 20px;  
  color: var(--brand-success);  
  font-weight: 800;  
}  

.detail-value.total {  
  font-size: 28px;  
  background: var(--gradient-success);  
  -webkit-background-clip: text;  
  -webkit-text-fill-color: transparent;  
  background-clip: text;  
}  

.status-badges-modal {  
  display: flex;  
  flex-wrap: wrap;  
  gap: var(--space-sm);  
}  

.modal-footer {  
  padding: var(--space-lg) var(--space-xl);  
  border-top: 2px solid var(--border-primary);  
  background: var(--bg-secondary);  
  display: flex;  
  justify-content: flex-end;  
  gap: var(--space-md);  
}  

/* ===================== MOBILE CARDS ===================== */  
.mobile-cards {  
  display: none;  
}  

.mobile-card {  
  background: var(--gradient-surface);  
  backdrop-filter: blur(24px) saturate(180%);  
  border: 1px solid var(--border-primary);  
  border-radius: var(--radius-lg);  
  padding: var(--space-md);  
  margin-bottom: var(--space-md);  
  box-shadow: var(--shadow-md);  
  transition: all 0.3s ease;  
  animation: fadeInUp 0.35s ease both;  
  cursor: pointer;  
}  

.mobile-card:hover {  
  transform: translateY(-4px);  
  box-shadow: var(--shadow-lg);  
  border-color: var(--brand-primary);  
}  

.mobile-card-header {  
  display: flex;  
  justify-content: space-between;  
  align-items: center;  
  margin-bottom: var(--space-md);  
  padding-bottom: var(--space-sm);  
  border-bottom: 2px solid var(--border-primary);  
}  

.mobile-card-number {  
  font-weight: 800;  
  font-size: 16px;  
  color: var(--brand-primary);  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.mobile-card-body {  
  display: grid;  
  gap: var(--space-sm);  
}  

.mobile-card-field {  
  display: flex;  
  justify-content: space-between;  
  align-items: center;  
  gap: var(--space-sm);  
}  

.mobile-card-label {  
  font-size: 12px;  
  font-weight: 700;  
  color: var(--text-tertiary);  
  text-transform: uppercase;  
}  

.mobile-card-value {  
  font-size: 14px;  
  font-weight: 700;  
  color: var(--text-primary);  
}  

/* ===================== PAGINAÇÃO ===================== */  
.pagination-container {  
  display: flex;  
  justify-content: space-between;  
  align-items: center;  
  margin-top: var(--space-xl);  
  padding: var(--space-md);  
  background: var(--bg-secondary);  
  border-radius: var(--radius-md);  
  border: 2px solid var(--border-primary);  
  flex-wrap: wrap;  
  gap: var(--space-md);  
}  

.pagination-info {  
  font-size: 14px;  
  color: var(--text-secondary);  
  font-weight: 600;  
  display: flex;  
  align-items: center;  
  gap: 6px;  
}  

.pagination-info i {  
  font-size: 16px;  
  color: var(--brand-primary);  
}  

.pagination-controls {  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
}  

.pagination-controls .btn {  
  min-width: 40px;  
  padding: 8px 12px;  
}  

.pagination-controls .btn:disabled {  
  opacity: 0.4;  
  cursor: not-allowed;  
  transform: none !important;  
}  

.pagination-page {  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  min-width: 40px;  
  height: 40px;  
  padding: 8px 12px;  
  font-size: 14px;  
  font-weight: 700;  
  color: var(--text-primary);  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  cursor: pointer;  
  transition: all 0.25s ease;  
}  

.pagination-page:hover {  
  background: var(--surface-hover);  
  border-color: var(--brand-primary);  
  transform: translateY(-2px);  
}  

.pagination-page.active {  
  background: var(--gradient-primary);  
  color: white;  
  border-color: var(--brand-primary);  
  box-shadow: var(--shadow-md);  
}  

.pagination-ellipsis {  
  display: inline-flex;  
  align-items: center;  
  justify-content: center;  
  min-width: 40px;  
  height: 40px;  
  padding: 8px 12px;  
  font-size: 14px;  
  font-weight: 700;  
  color: var(--text-quaternary);  
}  

.per-page-selector {  
  display: flex;  
  align-items: center;  
  gap: var(--space-sm);  
}  

.per-page-selector label {  
  font-size: 13px;  
  font-weight: 700;  
  color: var(--text-secondary);  
  text-transform: uppercase;  
  letter-spacing: 0.02em;  
}  

.per-page-selector select {  
  padding: 8px 12px;  
  background: var(--bg-tertiary);  
  border: 2px solid var(--border-primary);  
  border-radius: var(--radius-md);  
  color: var(--text-primary);  
  font-size: 14px;  
  font-weight: 600;  
  cursor: pointer;  
  transition: all 0.25s ease;  
}  

.per-page-selector select:hover {  
  border-color: var(--brand-primary);  
}  

.per-page-selector select:focus {  
  outline: none;  
  border-color: var(--brand-primary);  
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);  
}  

/* ===================== LOADING & EMPTY STATES ===================== */  
.loading-state,  
.empty-state {  
  padding: var(--space-3xl) var(--space-xl);  
  text-align: center;  
}  

.spinner {  
  width: 48px;  
  height: 48px;  
  border: 4px solid var(--border-primary);  
  border-top-color: var(--brand-primary);  
  border-radius: 50%;  
  animation: spin 0.8s linear infinite;  
  margin: 0 auto;  
}  

@keyframes spin {  
  to { transform: rotate(360deg); }  
}  

.empty-state-icon {  
  width: 80px;  
  height: 80px;  
  border-radius: var(--radius-lg);  
  background: var(--bg-secondary);  
  display: flex;  
  align-items: center;  
  justify-content: center;  
  margin: 0 auto var(--space-lg);  
}  

.empty-state-icon i {  
  font-size: 40px;  
  color: var(--text-quaternary);  
}  

.empty-state-title {  
  font-size: 20px;  
  font-weight: 800;  
  color: var(--text-primary);  
  margin-bottom: var(--space-sm);  
}  

.empty-state-text {  
  font-size: 14px;  
  color: var(--text-tertiary);  
  max-width: 400px;  
  margin: 0 auto;  
  line-height: 1.6;  
}  

/* ===================== RESPONSIVE ===================== */  
@media (min-width: 992px) {  
  .desktop-table { display: block !important; }  
  .mobile-cards { display: none !important; }  
}  

@media (max-width: 991.98px) {  
  .desktop-table { display: none !important; }  
  .mobile-cards { display: block !important; }  
   
  .page-hero {  
    padding: var(--space-xl);  
  }  
   
  .page-hero h1 {  
    font-size: 24px;  
  }  
   
  .title-icon {  
    width: 56px;  
    height: 56px;  
  }  
   
  .title-icon i {  
    font-size: 28px;  
  }  
 
  .search-form {  
    grid-template-columns: 1fr;  
  }  
 
  .result-grid {  
    grid-template-columns: repeat(2, minmax(0, 1fr));  
  }  
 
  .modal-container {  
    max-width: 95%;  
    margin: var(--space-md);  
  }  
 
  .modal-title-left {  
    flex-direction: column;  
    align-items: flex-start;  
  }  
 
  .modal-icon {  
    width: 56px;  
    height: 56px;  
  }  
 
  .modal-icon i {  
    font-size: 28px;  
  }  
 
  .modal-title-text h2 {  
    font-size: 20px;  
  }  
 
  .detail-grid {  
    grid-template-columns: 1fr;  
  }  
 
  .pagination-container {  
    flex-direction: column;  
    align-items: stretch;  
  }  
 
  .pagination-info {  
    justify-content: center;  
  }  
 
  .pagination-controls {  
    justify-content: center;  
    flex-wrap: wrap;  
  }  
 
  .per-page-selector {  
    justify-content: center;  
  }  
}  
 
@media (max-width: 640px) {  
  .main-content {  
    padding: var(--space-md);  
  }  
 
  .page-hero {  
    padding: var(--space-lg);  
  }  
 
  .title-row {  
    flex-direction: column;  
    align-items: flex-start;  
    gap: var(--space-md);  
  }  
 
  .result-grid {  
    grid-template-columns: 1fr;  
  }  
 
  .btn {  
    width: 100%;  
    justify-content: center;  
  }  
 
  .top-actions {  
    flex-direction: column;  
  }  
 
  .results-header {  
    flex-direction: column;  
    gap: var(--space-sm);  
    text-align: center;  
  }  
 
  .results-count {  
    width: 100%;  
    justify-content: center;  
  }  
 
  .modal-container {  
    max-width: 100%;  
    max-height: 100vh;  
    border-radius: 0;  
    margin: 0;  
  }  
 
  .modal-header {  
    padding: var(--space-lg);  
  }  
 
  .modal-title-row {  
    flex-direction: column;  
    align-items: flex-start;  
    gap: var(--space-md);  
  }  
 
  .modal-close {  
    position: absolute;  
    top: var(--space-md);  
    right: var(--space-md);  
  }  
 
  .modal-body {  
    padding: var(--space-lg);  
  }  
 
  .modal-footer {  
    padding: var(--space-md) var(--space-lg);  
    flex-direction: column;  
  }  
 
  .modal-footer .btn {  
    width: 100%;  
  }  
 
  .detail-value.total {  
    font-size: 24px;  
  }  
 
  .pagination-controls .btn {  
    min-width: 36px;  
    padding: 6px 10px;  
  }  
 
  .pagination-page {  
    min-width: 36px;  
    height: 36px;  
    padding: 6px 10px;  
    font-size: 13px;  
  }  
}  
 
/* ===================== FOOTER COMPATIBILITY ===================== */  
footer {  
  position: relative !important;  
  z-index: 10 !important;  
  margin-top: auto !important;  
  width: 100% !important;  
}  
 
.dark-mode footer {  
  background-color: transparent !important;  
}  
 
/* ===================== UTILITIES ===================== */  
.d-grid {  
  display: grid;  
}  
 
.gap-2 {  
  gap: var(--space-sm);  
}  
 
.d-flex {  
  display: flex !important;  
}  
 
.justify-content-between {  
  justify-content: space-between !important;  
}  
 
.align-items-center {  
  align-items: center !important;  
}  
 
.mb-0 { margin-bottom: 0 !important; }  
.mb-1 { margin-bottom: 0.25rem !important; }  
.mb-2 { margin-bottom: 0.5rem !important; }  
.mb-3 { margin-bottom: 1rem !important; }  
.mt-1 { margin-top: 0.25rem !important; }  
.mt-3 { margin-top: 1rem !important; }  
 
/* ===================== DARK MODE SPECIFIC ADJUSTMENTS ===================== */  
.dark-mode .page-hero,  
.dark-mode .filter-card,  
.dark-mode .upload-card,  
.dark-mode .desktop-table,  
.dark-mode .mobile-card,  
.dark-mode .modal-container {  
  background: var(--gradient-surface);  
}  
 
.dark-mode .modal-backdrop {  
  background: rgba(0, 0, 0, 0.85);  
}  
 
.dark-mode .pagination-container {  
  background: var(--bg-tertiary);  
}  
 
/* Força exibição dos ícones */  
i, i::before, i::after {  
  font-family: 'Font Awesome 6 Free', 'FontAwesome' !important;  
  font-style: normal !important;  
  font-weight: 900 !important;  
}  
 
.fa, .fas, .far, .fal, .fad, .fab {  
  font-family: "Font Awesome 6 Free" !important;  
  font-weight: 900 !important;  
  -webkit-font-smoothing: antialiased;  
  display: inline-block;  
  font-style: normal;  
  font-variant: normal;  
  text-rendering: auto;  
  line-height: 1;  
}  
 
/* ===================== SCROLLBAR PERSONALIZADA ===================== */  
::-webkit-scrollbar {  
  width: 12px;  
  height: 12px;  
}  
 
::-webkit-scrollbar-track {  
  background: var(--bg-secondary);  
  border-radius: var(--radius-sm);  
}  
 
::-webkit-scrollbar-thumb {  
  background: var(--brand-primary);  
  border-radius: var(--radius-sm);  
  border: 2px solid var(--bg-secondary);  
}  
 
::-webkit-scrollbar-thumb:hover {  
  background: var(--brand-primary-dark);  
}  
 
.modal-body::-webkit-scrollbar {  
  width: 8px;  
}  
 
.modal-body::-webkit-scrollbar-thumb {  
  background: var(--brand-primary);  
  border-radius: var(--radius-full);  
}  
</style>  
</head>  
<body>  
<?php include(__DIR__ . '/../menu.php'); ?>  

<div id="main" class="main-content">  
  <div class="container">  

    <!-- HERO -->  
    <section class="page-hero">  
      <div class="title-row">  
        <div class="title-icon">  
          <i class="fas fa-file-excel"></i>  
        </div>  
        <div>  
          <h1>Relatórios Analíticos</h1>  
          <div class="subtitle">  
            Importe planilhas XLSX com dados a partir da <strong>linha 17</strong>. O campo <em>Nº do Selo</em> é único.  
          </div>  
        </div>  
      </div>  
    </section>  

    <!-- TOP ACTIONS -->  
    <div class="top-actions">  
      <a href="../caixa/index.php" class="btn btn-secondary">  
        <i class="fas fa-arrow-left"></i> Voltar  
      </a>  
      <button class="btn btn-primary" id="btnProcessar">  
        <i class="fas fa-upload"></i> Processar Relatórios  
      </button>  
      <button class="btn btn-success" id="btnLimpar">  
        <i class="fas fa-eraser"></i> Limpar Seleção  
      </button>  
    </div>  

    <!-- CARD DE UPLOAD -->  
    <div class="upload-card">  
      <div class="card-title">  
        <i class="fas fa-cloud-upload-alt"></i> Anexar Arquivos XLSX  
      </div>  

      <input type="file" id="relatorios" name="relatorios[]" accept=".xlsx,.xls" multiple style="display:none">  

      <div id="dropArea" class="dropzone" tabindex="0" role="button" aria-label="Área para soltar arquivos">  
        <div class="dz-icon">  
          <i class="fas fa-cloud-upload-alt"></i>  
        </div>  
        <div class="dz-title">Arraste e solte seus arquivos XLSX aqui</div>  
        <div class="dz-sub">ou clique para selecionar</div>  
      </div>  

      <div id="fileList" class="file-list" aria-live="polite"></div>  

      <div id="depWarn" style="display:none; margin-top:14px; padding:12px; background:rgba(245,158,11,.1); border:2px solid var(--brand-warning); border-radius:var(--radius-md); color:var(--brand-warning);">  
        <i class="fas fa-exclamation-triangle"></i> <strong>PhpSpreadsheet não encontrado.</strong> Instale com: <code>composer require phpoffice/phpspreadsheet</code>  
      </div>  
    </div>  

    <!-- CARD DE RESULTADO -->  
    <div class="upload-card" id="resultadoCard" style="display:none;">  
      <div class="card-title">  
        <i class="fas fa-chart-bar"></i> Resultado da Importação  
      </div>  
      
      <div class="result-grid">  
        <div class="stat ok">  
          <h3 id="statInserted">0</h3>  
          <small>Inseridos</small>  
        </div>  
        <div class="stat ok">  
          <h3 id="statUpdated">0</h3>  
          <small>Atualizados</small>  
        </div>  
        <div class="stat warn">  
          <h3 id="statSkipped">0</h3>  
          <small>Pulados</small>  
        </div>  
        <div class="stat err">  
          <h3 id="statErrors">0</h3>  
          <small>Erros</small>  
        </div>  
      </div>  

      <div id="errorsBox" class="errors-box" style="display:none;">  
        <strong><i class="fas fa-exclamation-circle"></i> Ocorreram erros:</strong>  
        <code id="errorsText"></code>  
      </div>  
    </div>  

    <!-- SEÇÃO DE PESQUISA -->  
    <div class="filter-card">  
      <div class="card-title">  
        <i class="fas fa-search"></i> Pesquisar Relatórios  
      </div>  

      <form id="searchForm" class="search-form">  
        <div class="form-group">  
          <label class="form-label">  
            <i class="fas fa-search"></i> Busca Geral  
          </label>  
          <input type="text" class="form-control" id="search" name="search" placeholder="Nº do Selo, Ato, Usuário...">  
        </div>

        <div class="form-group">  
          <label class="form-label">  
            <i class="fas fa-file-alt"></i> Ato  
          </label>  
          <input type="text" class="form-control" id="ato" name="ato" placeholder="Nome do ato">  
        </div>  

        <div class="form-group">  
          <label class="form-label">  
            <i class="fas fa-calendar-alt"></i> Data Início  
          </label>  
          <input type="date" class="form-control" id="data_inicio" name="data_inicio">  
        </div>  

        <div class="form-group">  
          <label class="form-label">  
            <i class="fas fa-calendar-check"></i> Data Fim  
          </label>  
          <input type="date" class="form-control" id="data_fim" name="data_fim">  
        </div>  

        <div class="form-group" style="display:flex; align-items:flex-end;">  
          <button type="submit" class="btn btn-primary" style="width:100%;">  
            <i class="fas fa-search"></i> Pesquisar  
          </button>  
        </div>  
      </form>  
    </div>  

    <!-- RESULTADOS -->  
    <div class="upload-card" id="resultsCard" style="display:none;">  
      <div class="results-header">  
        <h5>  
          <i class="fas fa-list"></i> Resultados da Pesquisa  
        </h5>  
        <span class="results-count">  
          <i class="fas fa-file-alt"></i>   
          <span id="resultsCount">0</span> <span id="resultsLabel">registros</span>  
        </span>  
      </div>  

      <!-- TABELA DESKTOP -->  
      <div class="desktop-table" id="desktopTable" style="display:none;">  
        <div class="table-responsive">  
          <table class="table table-hover">  
            <thead>  
              <tr>  
                <th><i class="fas fa-hashtag"></i> Seq</th>  
                <th><i class="fas fa-certificate"></i> Nº do Selo</th>  
                <th><i class="fas fa-file-alt"></i> Ato</th>  
                <th><i class="fas fa-user"></i> Usuário</th>  
                <th><i class="fas fa-dollar-sign"></i> Total</th>  
                <th><i class="fas fa-calendar"></i> Criado em</th>  
                <th><i class="fas fa-cog"></i> Ações</th>  
              </tr>  
            </thead>  
            <tbody id="resultsTableBody">  
            </tbody>  
          </table>  
        </div>  
      </div>  

      <!-- CARDS MOBILE -->  
      <div class="mobile-cards" id="mobileCards" style="display:none;">  
      </div>  

      <div id="loadingState" class="loading-state" style="display:none;">  
        <div class="spinner"></div>  
        <p style="color:var(--text-secondary); font-weight:600; margin-top:20px;">Carregando dados...</p>  
      </div>  

      <div id="emptyState" class="empty-state" style="display:none;">  
        <div class="empty-state-icon">  
          <i class="fas fa-inbox"></i>  
        </div>  
        <p class="empty-state-title">Nenhum registro encontrado</p>  
        <p class="empty-state-text">Tente ajustar os filtros de pesquisa ou importe novos relatórios.</p>  
      </div>  

      <!-- PAGINAÇÃO -->  
      <div id="paginationContainer" class="pagination-container" style="display:none;">  
        <div class="pagination-info">  
          <i class="fas fa-info-circle"></i>  
          <span id="paginationInfo">Mostrando 0-0 de 0 registros</span>  
        </div>  
        
        <div class="pagination-controls" id="paginationControls">  
          <!-- Será preenchido via JavaScript -->  
        </div>  

        <div class="per-page-selector">  
          <label for="perPageSelect">Por página:</label>  
          <select id="perPageSelect" class="form-control" style="width:auto;">  
            <option value="10">10</option>  
            <option value="25">25</option>  
            <option value="50" selected>50</option>  
            <option value="100">100</option>  
          </select>  
        </div>  
      </div>  
    </div>  

  </div>  
</div>  

<!-- MODAL DE DETALHES -->  
<div class="modal-backdrop" id="modalBackdrop"></div>  
<div class="detail-modal" id="detailModal">  
  <div class="modal-container">  
    <div class="modal-header">  
      <div class="modal-title-row">  
        <div class="modal-title-left">  
          <div class="modal-icon">  
            <i class="fas fa-file-invoice"></i>  
          </div>  
          <div class="modal-title-text">  
            <h2 id="modalSeloNumber">—</h2>  
            <p>Detalhes do Relatório Analítico</p>  
          </div>  
        </div>  
        <div class="modal-close" id="modalClose">  
          <i class="fas fa-times"></i>  
        </div>  
      </div>  
    </div>  

    <div class="modal-body" id="modalBodyContent">  
      <!-- Conteúdo será inserido via JavaScript -->  
    </div>  

    <div class="modal-footer">  
      <button class="btn btn-secondary" id="modalCloseBtn">  
        <i class="fas fa-times"></i> Fechar  
      </button>  
    </div>  
  </div>  
</div>  

<?php include(__DIR__ . '/../rodape.php'); ?>  

<script src="../script/jquery-3.6.0.min.js"></script>  
<script src="../script/bootstrap.bundle.min.js"></script>  
<script src="../script/sweetalert2.js"></script>  
<script>  
(function(){  
  // Carrega tema  
  $.get('../load_mode.php', function(mode){  
    $('body').removeClass('light-mode dark-mode').addClass(mode);  
  });  
})();  

// ===================== VARIÁVEIS GLOBAIS DE PAGINAÇÃO =====================  
let currentPage = 1;  
let perPage = 50;  
let totalPages = 1;  
let currentFilters = {};  

// ===================== MODAL DE DETALHES =====================  
function openDetailModal(item) {  
  $('#modalSeloNumber').text(item.numero_selo || '—');  
   
  const isento = item.isento == 1;  
  const cancelado = item.cancelado == 1;  
  const diferido = item.diferido == 1;  

  // Formatações de data de acordo com novos tipos (DATE / DATETIME)
  const selagemFmt = item.selagem 
    ? new Date(String(item.selagem) + 'T00:00:00').toLocaleDateString('pt-BR') 
    : '—';
  const operacaoFmt = item.operacao 
    ? new Date(String(item.operacao).replace(' ', 'T')).toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' }) 
    : '—';
  
  let statusBadges = '';  
  if(cancelado) statusBadges += '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Cancelado</span> ';  
  if(isento) statusBadges += '<span class="badge badge-warning"><i class="fas fa-check-circle"></i> Isento</span> ';  
  if(diferido) statusBadges += '<span class="badge badge-warning"><i class="fas fa-clock"></i> Diferido</span>';  
   
  const modalContent = `  
    <div class="detail-section">  
      <div class="section-title">  
        <i class="fas fa-info-circle"></i> Informações Básicas  
      </div>  
      ${statusBadges ? `<div class="status-badges-modal" style="margin-bottom: var(--space-md);">${statusBadges}</div>` : ''}  
      <div class="detail-grid">  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-hashtag"></i> Sequência  
          </div>  
          <div class="detail-value">${item.seq_linha || '—'}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-building"></i> Cartório  
          </div>  
          <div class="detail-value">${item.cartorio || '—'}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-file-alt"></i> Ato  
          </div>  
          <div class="detail-value">${item.ato || '—'}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-user"></i> Usuário  
          </div>  
          <div class="detail-value">${item.usuario || '—'}</div>  
        </div>  
        ${item.tipo ? `  
          <div class="detail-item">  
            <div class="detail-label">  
              <i class="fas fa-tag"></i> Tipo  
            </div>  
            <div class="detail-value">${item.tipo}</div>  
          </div>  
        ` : ''}  
        ${selagemFmt !== '—' ? `  
          <div class="detail-item">  
            <div class="detail-label">  
              <i class="fas fa-stamp"></i> Selagem (Data)  
            </div>  
            <div class="detail-value">${selagemFmt}</div>  
          </div>  
        ` : ''}  
        ${operacaoFmt !== '—' ? `  
          <div class="detail-item">  
            <div class="detail-label">  
              <i class="fas fa-cog"></i> Operação (Data e Hora)  
            </div>  
            <div class="detail-value">${operacaoFmt}</div>  
          </div>  
        ` : ''}  
      </div>  
    </div>  

    <div class="detail-section">  
      <div class="section-title">  
        <i class="fas fa-money-bill-wave"></i> Valores Financeiros  
      </div>  
      <div class="detail-grid">  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-coins"></i> Emolumentos  
          </div>  
          <div class="detail-value money">R$ ${parseFloat(item.emolumentos || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-university"></i> FERJ  
          </div>  
          <div class="detail-value money">R$ ${parseFloat(item.ferj || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-university"></i> FADEP  
          </div>  
          <div class="detail-value money">R$ ${parseFloat(item.fadep || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-university"></i> FERC  
          </div>  
          <div class="detail-value money">R$ ${parseFloat(item.ferc || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-university"></i> FEMP  
          </div>  
          <div class="detail-value money">R$ ${parseFloat(item.femp || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div> 
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-university"></i> FERRFIS  
          </div>  
          <div class="detail-value money">R$ ${parseFloat(item.ferrfis || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div>  
        <div class="detail-item">  
          <div class="detail-label">  
            <i class="fas fa-certificate"></i> Selo (Valor)  
          </div>  
          <div class="detail-value money">R$ ${parseFloat(item.selo_valor || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div>  
      </div>  
    </div>  

    <div class="detail-section">  
      <div class="section-title">  
        <i class="fas fa-calculator"></i> Total  
      </div>  
      <div class="detail-grid">  
        <div class="detail-item" style="grid-column: 1 / -1;">  
          <div class="detail-label">  
            <i class="fas fa-money-check-alt"></i> Valor Total do Ato  
          </div>  
          <div class="detail-value total">R$ ${parseFloat(item.total || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>  
        </div>  
      </div>  
    </div>  

    ${item.arquivo_origem || item.uploaded_by ? `  
      <div class="detail-section">  
        <div class="section-title">  
          <i class="fas fa-info"></i> Informações de Importação  
        </div>  
        <div class="detail-grid">  
          ${item.arquivo_origem ? `  
            <div class="detail-item">  
              <div class="detail-label">  
                <i class="fas fa-file-excel"></i> Arquivo de Origem  
              </div>  
              <div class="detail-value" style="font-size:13px; word-break:break-all;">${item.arquivo_origem}</div>  
            </div>  
          ` : ''}  
          ${item.uploaded_by ? `  
            <div class="detail-item">  
              <div class="detail-label">  
                <i class="fas fa-user-circle"></i> Importado Por  
              </div>  
              <div class="detail-value">${item.uploaded_by}</div>  
            </div>  
          ` : ''}  
          <div class="detail-item">  
            <div class="detail-label">  
              <i class="fas fa-calendar-plus"></i> Data de Criação  
            </div>  
            <div class="detail-value">${new Date(item.created_at).toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'})}</div>  
          </div>  
          ${item.updated_at ? `  
            <div class="detail-item">  
              <div class="detail-label">  
                <i class="fas fa-calendar-check"></i> Última Atualização  
              </div>  
              <div class="detail-value">${new Date(item.updated_at).toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'})}</div>  
            </div>  
          ` : ''}  
        </div>  
      </div>  
    ` : ''}  
  `;  
   
  $('#modalBodyContent').html(modalContent);  
  $('#modalBackdrop').addClass('show');  
  $('#detailModal').addClass('show');  
  $('body').css('overflow', 'hidden');  
}  

function closeDetailModal() {  
  $('#modalBackdrop').removeClass('show');  
  $('#detailModal').removeClass('show');  
  $('body').css('overflow', '');  
}  

// Event listeners para fechar modal  
$('#modalClose, #modalCloseBtn, #modalBackdrop').on('click', closeDetailModal);  

// Fechar com ESC  
$(document).on('keydown', function(e) {  
  if (e.key === 'Escape' && $('#detailModal').hasClass('show')) {  
    closeDetailModal();  
  }  
});  

// Prevenir fechar ao clicar dentro do modal  
$('.modal-container').on('click', function(e) {  
  e.stopPropagation();  
});  

// ===================== RENDERIZAR PAGINAÇÃO =====================  
function renderPagination(pagination) {
  if(!pagination || pagination.total_pages <= 1) {
    $('#paginationContainer').hide();
    return;
  }

  $('#paginationContainer').show();
  
  // Atualiza informações
  const start = (pagination.current_page - 1) * pagination.per_page + 1;
  const end = Math.min(start + pagination.per_page - 1, pagination.total_records);
  $('#paginationInfo').text(`Mostrando ${start}-${end} de ${pagination.total_records} registros`);
  
  // Monta controles de paginação
  let paginationHTML = '';
  
  // Botão Primeira
  paginationHTML += `
    <button class="btn btn-secondary btn-sm" id="btnFirstPage" ${!pagination.has_prev ? 'disabled' : ''}>
      <i class="fas fa-angle-double-left"></i>
    </button>
  `;
  
  // Botão Anterior
  paginationHTML += `
    <button class="btn btn-secondary btn-sm" id="btnPrevPage" ${!pagination.has_prev ? 'disabled' : ''}>
      <i class="fas fa-angle-left"></i>
    </button>
  `;
  
  // Páginas numeradas
  const maxButtons = 5;
  let startPage = Math.max(1, pagination.current_page - Math.floor(maxButtons / 2));
  let endPage = Math.min(pagination.total_pages, startPage + maxButtons - 1);
  
  if(endPage - startPage < maxButtons - 1) {
    startPage = Math.max(1, endPage - maxButtons + 1);
  }
  
  if(startPage > 1) {
    paginationHTML += `<span class="pagination-page page-btn" data-page="1">1</span>`;
    if(startPage > 2) {
      paginationHTML += `<span class="pagination-ellipsis">...</span>`;
    }
  }
  
  for(let i = startPage; i <= endPage; i++) {
    paginationHTML += `
      <span class="pagination-page page-btn ${i === pagination.current_page ? 'active' : ''}" data-page="${i}">
        ${i}
      </span>
    `;
  }
  
  if(endPage < pagination.total_pages) {
    if(endPage < pagination.total_pages - 1) {
      paginationHTML += `<span class="pagination-ellipsis">...</span>`;
    }
    paginationHTML += `<span class="pagination-page page-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</span>`;
  }
  
  // Botão Próxima
  paginationHTML += `
    <button class="btn btn-secondary btn-sm" id="btnNextPage" ${!pagination.has_next ? 'disabled' : ''}>
      <i class="fas fa-angle-right"></i>
    </button>
  `;
  
  // Botão Última
  paginationHTML += `
    <button class="btn btn-secondary btn-sm" id="btnLastPage" ${!pagination.has_next ? 'disabled' : ''}>
      <i class="fas fa-angle-double-right"></i>
    </button>
  `;
  
  $('#paginationControls').html(paginationHTML);
  
  // Event listeners
  $('#btnFirstPage').on('click', function() {
    if(!$(this).prop('disabled')) {
      loadPage(1);
    }
  });
  
  $('#btnPrevPage').on('click', function() {
    if(!$(this).prop('disabled')) {
      loadPage(pagination.current_page - 1);
    }
  });
  
  $('#btnNextPage').on('click', function() {
    if(!$(this).prop('disabled')) {
      loadPage(pagination.current_page + 1);
    }
  });
  
  $('#btnLastPage').on('click', function() {
    if(!$(this).prop('disabled')) {
      loadPage(pagination.total_pages);
    }
  });
  
  $('.page-btn').on('click', function() {
    const page = parseInt($(this).data('page'));
    if(page !== pagination.current_page) {
      loadPage(page);
    }
  });
}

// ===================== CARREGAR PÁGINA =====================
function loadPage(page) {
  currentPage = page;
  performSearch();
}

// ===================== EXECUTAR PESQUISA =====================
function performSearch() {
  const formData = {
    action: 'search',
    search: $('#search').val(),
    cartorio: $('#cartorio').val(),
    ato: $('#ato').val(),
    data_inicio: $('#data_inicio').val(),
    data_fim: $('#data_fim').val(),
    page: currentPage,
    per_page: perPage
  };
  
  currentFilters = formData;

  $('#resultsCard').show();
  $('#loadingState').show();
  $('#emptyState').hide();
  $('#desktopTable').hide();
  $('#mobileCards').hide();
  $('#paginationContainer').hide();

  $.ajax({
    url: location.href,
    method: 'GET',
    data: formData,
    dataType: 'json',
    success: function(response){
      $('#loadingState').hide();
      
      if(!response.success){
        $('#emptyState').show();
        Swal.fire({
          icon: 'error',
          title: 'Erro na Pesquisa',
          text: response.error || 'Erro desconhecido ao buscar dados.',
          confirmButtonColor: '#ef4444'
        });
        return;
      }

      const data = response.data || [];
      const pagination = response.pagination || {};
      
      totalPages = pagination.total_pages || 1;
      
      $('#resultsCount').text(pagination.total_records || 0);
      $('#resultsLabel').text(pagination.total_records === 1 ? 'registro' : 'registros');

      if(data.length === 0){
        $('#emptyState').show();
        return;
      }

      // ========== RENDERIZA TABELA DESKTOP ==========
      let tableHTML = '';
      data.forEach(function(item){
        const isento = item.isento == 1;
        const cancelado = item.cancelado == 1;
        const diferido = item.diferido == 1;
        
        let statusBadges = '';
        if(cancelado) statusBadges += '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Cancelado</span> ';
        if(isento) statusBadges += '<span class="badge badge-warning"><i class="fas fa-check-circle"></i> Isento</span> ';
        if(diferido) statusBadges += '<span class="badge badge-warning"><i class="fas fa-clock"></i> Diferido</span>';
        
        const itemJson = JSON.stringify(item).replace(/'/g, '&#39;');
        
        tableHTML += `
          <tr class="table-row-clickable" data-item='${itemJson}'>
            <td><strong style="color:var(--brand-primary);">${item.seq_linha || '—'}</strong></td>
            <td>
              <span class="badge badge-secondary">
                <i class="fas fa-certificate"></i> ${item.numero_selo}
              </span>
              ${statusBadges ? '<br>' + statusBadges : ''}
            </td>
            <td>${item.ato || '—'}</td>
            <td>${item.usuario || '—'}</td>
            <td><strong style="color:var(--brand-success); font-size:15px;">R$ ${parseFloat(item.total || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong></td>
            <td style="white-space:nowrap;">${new Date(item.created_at).toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'})}</td>
            <td>
              <button class="btn btn-sm btn-info btn-view-detail" data-item='${itemJson}'>
                <i class="fas fa-eye"></i> Ver
              </button>
            </td>
          </tr>
        `;
      });
      $('#resultsTableBody').html(tableHTML);

      // ========== RENDERIZA CARDS MOBILE ==========
      let cardsHTML = '';
      data.forEach(function(item){
        const isento = item.isento == 1;
        const cancelado = item.cancelado == 1;
        const diferido = item.diferido == 1;
        
        let statusBadges = '';
        if(cancelado) statusBadges += '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Cancelado</span> ';
        if(isento) statusBadges += '<span class="badge badge-warning"><i class="fas fa-check-circle"></i> Isento</span> ';
        if(diferido) statusBadges += '<span class="badge badge-warning"><i class="fas fa-clock"></i> Diferido</span>';
        
        const itemJson = JSON.stringify(item).replace(/'/g, '&#39;');
        
        cardsHTML += `
          <div class="mobile-card" data-item='${itemJson}'>
            <div class="mobile-card-header">
              <div class="mobile-card-number">
                <i class="fas fa-certificate"></i> ${item.numero_selo}
              </div>
              ${statusBadges}
            </div>
            
            <div class="mobile-card-body">
              <div class="mobile-card-field">
                <span class="mobile-card-label">Ato:</span>
                <span class="mobile-card-value">${item.ato || '—'}</span>
              </div>
              
              <div class="mobile-card-field">
                <span class="mobile-card-label">Usuário:</span>
                <span class="mobile-card-value">${item.usuario || '—'}</span>
              </div>
              
              <div class="mobile-card-field">
                <span class="mobile-card-label">Total:</span>
                <span class="mobile-card-value" style="color:var(--brand-success); font-weight:800; font-size:18px;">
                  R$ ${parseFloat(item.total || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}
                </span>
              </div>
              
              <div class="mobile-card-field">
                <span class="mobile-card-label">Criado:</span>
                <span class="mobile-card-value">${new Date(item.created_at).toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'})}</span>
              </div>
            </div>
          </div>
        `;
      });
      $('#mobileCards').html(cardsHTML);

      // Verifica o tamanho da tela e exibe o layout correto
      if(window.innerWidth >= 992){
        $('#desktopTable').show();
        $('#mobileCards').hide();
      } else {
        $('#desktopTable').hide();
        $('#mobileCards').show();
      }

      // Event listeners - USANDO DELEGAÇÃO DE EVENTOS
      // Click na linha inteira da tabela (delegação de eventos)
      $('#resultsTableBody').off('click', 'tr.table-row-clickable').on('click', 'tr.table-row-clickable', function(e){
        // Ignora se clicou no botão
        if($(e.target).closest('button').length) return;
        
        const itemData = $(this).data('item');
        if(itemData){
          openDetailModal(itemData);
        }
      });

      // Click no botão Ver (delegação de eventos)
      $('#resultsTableBody').off('click', '.btn-view-detail').on('click', '.btn-view-detail', function(e){
        e.stopPropagation();
        const itemData = $(this).data('item');
        if(itemData){
          openDetailModal(itemData);
        }
      });

      // Click nos cards mobile (delegação de eventos)
      $('#mobileCards').off('click', '.mobile-card').on('click', '.mobile-card', function(){
        const itemData = $(this).data('item');
        if(itemData){
          openDetailModal(itemData);
        }
      });
      
      // Renderiza paginação
      renderPagination(pagination);
    },
    error: function(xhr, status, error){
      $('#loadingState').hide();
      $('#emptyState').show();
      console.error('Erro AJAX:', error);
      console.log('Response:', xhr.responseText);
      Swal.fire({
        icon: 'error',
        title: 'Erro na Requisição',
        text: 'Não foi possível carregar os dados. Verifique sua conexão e tente novamente.',
        confirmButtonColor: '#ef4444'
      });
    }
  });
}

// ===================== DROPZONE =====================
(function initDrop(){
  const dropArea = document.getElementById('dropArea');
  const fileInput = document.getElementById('relatorios');
  const fileList = document.getElementById('fileList');

  function humanSize(bytes){
    if(bytes === 0) return '0 B';
    const k = 1024, sizes = ['B','KB','MB','GB','TB'];
    const i = Math.floor(Math.log(bytes)/Math.log(k));
    return (bytes/Math.pow(k,i)).toFixed(1)+' '+sizes[i];
  }

  function renderList(files){
    fileList.innerHTML = '';
    if(!files || !files.length) return;
    Array.from(files).forEach(f=>{
      const row = document.createElement('div');
      row.className = 'file-item';
      row.innerHTML = `
        <span class="file-name">
          <i class="fas fa-file-excel"></i> ${f.name}
        </span>
        <span class="file-size">${humanSize(f.size)}</span>
      `;
      fileList.appendChild(row);
    });
  }

  function setFiles(files){
    const dt = new DataTransfer();
    Array.from(files).forEach(f=> dt.items.add(f));
    fileInput.files = dt.files;
    renderList(fileInput.files);
  }

  dropArea.addEventListener('click', ()=> fileInput.click());
  dropArea.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter' || e.key === ' '){ 
      e.preventDefault(); 
      fileInput.click(); 
    }
  });
  dropArea.addEventListener('dragover', (e)=>{ 
    e.preventDefault(); 
    dropArea.classList.add('dragover'); 
  });
  dropArea.addEventListener('dragleave', ()=> dropArea.classList.remove('dragover'));
  dropArea.addEventListener('drop', (e)=>{
    e.preventDefault();
    dropArea.classList.remove('dragover');
    if(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length){
      setFiles(e.dataTransfer.files);
    }
  });
  fileInput.addEventListener('change', ()=> renderList(fileInput.files));

  // Botão Limpar
  $('#btnLimpar').on('click', function(){
    fileInput.value = '';
    fileList.innerHTML = '';
    $('#resultadoCard').hide();
    $('#errorsBox').hide();
    $('#errorsText').text('');
  });

  // Botão Processar
  $('#btnProcessar').on('click', function(){
    const files = fileInput.files;
    if(!files || !files.length){
      Swal.fire({
        icon:'warning', 
        title:'Nenhum arquivo', 
        text:'Selecione ao menos um arquivo XLSX.',
        confirmButtonColor: '#6366f1'
      });
      return;
    }

    const fd = new FormData();
    for(let i=0; i<files.length; i++){ 
      fd.append('relatorios[]', files[i]); 
    }
    fd.append('action', 'upload');

    Swal.fire({
      title: 'Processando…',
      html: '<div class="spinner" style="margin:20px auto;"></div><p style="margin-top:20px;">Lendo e importando seus relatórios. Aguarde.</p>',
      allowOutsideClick: false,
      showConfirmButton: false
    });

    fetch(location.href, { method:'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        Swal.close();
        $('#resultadoCard').show();

        if(!res.success){
          $('#statInserted').text('0');
          $('#statUpdated').text('0');
          $('#statSkipped').text('0');
          $('#statErrors').text('1');
          $('#errorsBox').show();
          $('#errorsText').text(res.error || 'Falha desconhecida.');
          $('#depWarn').show();
          
          Swal.fire({
            icon: 'error',
            title: 'Erro na Importação',
            text: res.error || 'Ocorreu um erro ao processar os arquivos.',
            confirmButtonColor: '#ef4444'
          });
          return;
        }

        $('#statInserted').text(res.inserted || 0);
        $('#statUpdated').text(res.updated || 0);
        $('#statSkipped').text(res.skipped || 0);
        const errCount = (res.errors && res.errors.length) ? res.errors.length : 0;
        $('#statErrors').text(errCount);

        if(errCount > 0){
          $('#errorsBox').show();
          $('#errorsText').text(res.errors.join('\n'));
        } else {
          $('#errorsBox').hide();
          $('#errorsText').text('');
        }

        if(errCount === 0){
          Swal.fire({
            icon:'success', 
            title:'Importação Concluída!',
            html: `
              <p><strong>${res.inserted}</strong> registros inseridos</p>
              <p><strong>${res.updated}</strong> registros atualizados</p>
            `,
            timer: 3000, 
            showConfirmButton: false
          });
          
          // Limpa seleção de arquivos
          fileInput.value = '';
          fileList.innerHTML = '';
          
          // Recarrega pesquisa automaticamente
          setTimeout(function(){
            currentPage = 1;
            performSearch();
          }, 500);
        } else {
          Swal.fire({
            icon:'warning', 
            title:'Importação com Observações', 
            html: `
              <p><strong>${res.inserted}</strong> inseridos, <strong>${res.updated}</strong> atualizados</p>
              <p style="color:#ef4444;"><strong>${errCount}</strong> erros encontrados</p>
              <p>Verifique os detalhes abaixo.</p>
            `,
            confirmButtonColor: '#6366f1'
          });
        }
      })
      .catch(err => {
        Swal.close();
        $('#resultadoCard').show();
        $('#statInserted').text('0');
        $('#statUpdated').text('0');
        $('#statSkipped').text('0');
        $('#statErrors').text('1');
        $('#errorsBox').show();
        $('#errorsText').text('Erro na requisição: ' + (err && err.message ? err.message : err));
        
        Swal.fire({
          icon: 'error',
          title: 'Erro de Conexão',
          text: 'Não foi possível conectar ao servidor. Verifique sua conexão.',
          confirmButtonColor: '#ef4444'
        });
      });
  });
})();

// ===================== PESQUISA =====================
$('#searchForm').on('submit', function(e){
  e.preventDefault();
  currentPage = 1;
  performSearch();
});

// ===================== SELETOR DE ITENS POR PÁGINA =====================
$('#perPageSelect').on('change', function(){
  perPage = parseInt($(this).val());
  currentPage = 1;
  performSearch();
});

// ===================== RESIZE HANDLER =====================
$(window).on('resize', function(){
  if($('#resultsCard').is(':visible')){
    if(window.innerWidth >= 992){
      $('#desktopTable').show();
      $('#mobileCards').hide();
    } else {
      $('#desktopTable').hide();
      $('#mobileCards').show();
    }
  }
});

// ===================== AUTO-LOAD INICIAL =====================
$(document).ready(function(){
  // Carrega automaticamente os últimos registros ao abrir a página
  setTimeout(function(){
    performSearch();
  }, 800);
});
</script>
</body>
</html>
