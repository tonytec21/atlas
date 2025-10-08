<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisar Fluxo de Caixa</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">
    <?php include(__DIR__ . '/../style/style_caixa.php'); ?>  
</head>

<body class="light-mode">
    <?php
    include(__DIR__ . '/../menu.php');

    $conn = getDatabaseConnection();
    $stmt = $conn->prepare('SELECT nivel_de_acesso, status, usuario FROM funcionarios WHERE usuario = :usuario');
    $stmt->bindParam(':usuario', $_SESSION['username']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user['status'] !== 'ativo') {
        echo "<script>alert('O usuário não tem acesso à página.'); window.location.href='../index.php';</script>";
        exit;
    }

    $query = "SELECT usuario, nome_completo, nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = :usuario";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':usuario', $_SESSION['username']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $temAcessoFluxoDeCaixa = false;

    // Verifica se o nível de acesso é "usuario" e se tem o acesso adicional "Fluxo de Caixa"
    if ($user['nivel_de_acesso'] === 'usuario' && !empty($user['acesso_adicional'])) {
        $acessosAdicionais = explode(',', $user['acesso_adicional']);
        $acessosAdicionais = array_map('trim', $acessosAdicionais); 
        if (in_array('Fluxo de Caixa', $acessosAdicionais)) {
            $temAcessoFluxoDeCaixa = true;
        }
    }

    // ===== NOVO: Período rápido (default: hoje) =====
    $selectedPeriodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'hoje';
    $hoje = date('Y-m-d');
    $ultimo7 = date('Y-m-d', strtotime('-6 days')); 
    $ultimo30 = date('Y-m-d', strtotime('-30 days')); 
    ?>

    <div id="main" class="main-content">
        <div class="container">
            <section class="page-hero">
                <div class="title-row">
                    <div class="title-icon"><i class="fa fa-university" aria-hidden="true"></i></div>
                    <div class="title-texts">
                        <h1>Pesquisar Fluxo de Caixa</h1>
                        <div class="subtitle muted">Consulta do fluxo diário com filtros por funcionário, período rápido e intervalo de datas — e ações rápidas.</div>
                    </div>
                </div>
            </section>

            <hr>
            <form id="pesquisarForm" method="GET">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="funcionario">Funcionário:</label>
                        <select class="form-control" id="funcionario" name="funcionario" <?php echo $user['nivel_de_acesso'] === 'usuario' && !$temAcessoFluxoDeCaixa ? 'disabled' : ''; ?>>
                            <?php if ($user['nivel_de_acesso'] === 'administrador' || $temAcessoFluxoDeCaixa) { ?>
                                <option value="todos" <?php echo (isset($_GET['funcionario']) && $_GET['funcionario']=='todos') ? 'selected' : '' ?>>Todos</option>
                                <option value="caixa_unificado" <?php echo (isset($_GET['funcionario']) && $_GET['funcionario']=='caixa_unificado') ? 'selected' : '' ?>>Caixa Unificado</option>
                            <?php } ?>
                            <?php
                            // Definir a query de acordo com o nível de acesso ou o acesso adicional
                            $queryFuncionarios = $user['nivel_de_acesso'] === 'administrador' || $temAcessoFluxoDeCaixa ?
                                "SELECT usuario, nome_completo FROM funcionarios WHERE status = 'ativo'" :
                                "SELECT usuario, nome_completo FROM funcionarios WHERE usuario = :usuario";

                            $stmt = $conn->prepare($queryFuncionarios);
                            
                            if ($user['nivel_de_acesso'] !== 'administrador' && !$temAcessoFluxoDeCaixa) {
                                $stmt->bindParam(':usuario', $user['usuario']);
                            }
                            
                            $stmt->execute();
                            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($funcionarios as $funcionario) {
                                $sel = (isset($_GET['funcionario']) && $_GET['funcionario']==$funcionario['usuario']) ? 'selected' : '';
                                echo '<option value="' . $funcionario['usuario'] . '" '.$sel.'>' . $funcionario['nome_completo'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- NOVO: Filtro secundário de período -->
                    <div class="form-group col-md-4">
                        <label for="periodo">Período Rápido:</label>
                        <select class="form-control" id="periodo" name="periodo">
                            <option value="hoje" <?php echo $selectedPeriodo==='hoje'?'selected':''; ?>>Hoje</option>
                            <option value="ultimos7" <?php echo $selectedPeriodo==='ultimos7'?'selected':''; ?>>Últimos 7 dias</option>
                            <option value="ultimoMes" <?php echo $selectedPeriodo==='ultimoMes'?'selected':''; ?>>Último mês (30 dias)</option>
                            <option value="todos" <?php echo $selectedPeriodo==='todos'?'selected':''; ?>>Todos</option>
                        </select>
                        <small class="text-muted">Dica: escolher um período aqui preenche as datas abaixo.</small>
                    </div>

                    <div class="form-group col-md-2">
                        <label for="data_inicial">Data Inicial:</label>
                        <input type="date" class="form-control" id="data_inicial" name="data_inicial" value="<?php echo isset($_GET['data_inicial'])?htmlspecialchars($_GET['data_inicial']):''; ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="data_final">Data Final:</label>
                        <input type="date" class="form-control" id="data_final" name="data_final" value="<?php echo isset($_GET['data_final'])?htmlspecialchars($_GET['data_final']):''; ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <button type="submit" style="width: 100%;" class="btn btn-primary">
                            <i class="fa fa-filter" aria-hidden="true"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" style="width: 100%;" class="btn btn-secondary" onclick="window.location.href='../os/index.php'">
                            <i class="fa fa-search" aria-hidden="true"></i> Pesquisar OS
                        </button>
                    </div>
                </div>
            </form>
            <hr>

            <h5>Resultados da Pesquisa</h5>
            <div id="cardsResultados" class="row cards-wrap">
                <?php
                $conditions = [];
                $params = [];
                $filtered = false;
                $isUnificado = false;

                // Verifica se o usuário é "administrador" ou se tem acesso adicional para "Fluxo de Caixa"
                $temAcessoCompleto = ($user['nivel_de_acesso'] === 'administrador' || in_array('Fluxo de Caixa', explode(',', $user['acesso_adicional'])));

                if (isset($_GET['funcionario']) && $_GET['funcionario'] !== 'todos' && $_GET['funcionario'] !== 'caixa_unificado') {
                    // Funcionário específico
                    $conditions[] = 'funcionario = :funcionario';
                    $params[':funcionario'] = $_GET['funcionario'];
                    $filtered = true;
                } elseif ($temAcessoCompleto) {
                    // Pode ver todos (inclui opção unificado)
                    if (isset($_GET['funcionario']) && $_GET['funcionario'] === 'caixa_unificado') {
                        $isUnificado = true;
                    }
                } else {
                    $conditions[] = 'funcionario = :funcionario';
                    $params[':funcionario'] = $user['usuario'];
                    $filtered = true;
                }

                // ===== Datas: se o usuário informou intervalo manual, usa-o; caso contrário, aplica o "período" rápido
                $temIntervaloManual = (!empty($_GET['data_inicial']) || !empty($_GET['data_final']));

                if ($temIntervaloManual) {
                    if (!empty($_GET['data_inicial']) && !empty($_GET['data_final'])) {
                        $conditions[] = 'DATE(data) BETWEEN :data_inicial AND :data_final';
                        $params[':data_inicial'] = $_GET['data_inicial'];
                        $params[':data_final'] = $_GET['data_final'];
                        $filtered = true;
                    } elseif (!empty($_GET['data_inicial'])) {
                        $conditions[] = 'DATE(data) >= :data_inicial';
                        $params[':data_inicial'] = $_GET['data_inicial'];
                        $filtered = true;
                    } elseif (!empty($_GET['data_final'])) {
                        $conditions[] = 'DATE(data) <= :data_final';
                        $params[':data_final'] = $_GET['data_final'];
                        $filtered = true;
                    }
                } else {
                    // Aplica período rápido (default hoje)
                    if ($selectedPeriodo === 'hoje') {
                        $conditions[] = 'DATE(data) = :hoje';
                        $params[':hoje'] = $hoje;
                        $filtered = true;
                    } elseif ($selectedPeriodo === 'ultimos7') {
                        $conditions[] = 'DATE(data) BETWEEN :ini7 AND :fim7';
                        $params[':ini7'] = $ultimo7;
                        $params[':fim7'] = $hoje;
                        $filtered = true;
                    } elseif ($selectedPeriodo === 'ultimoMes') {
                        $conditions[] = 'DATE(data) BETWEEN :ini30 AND :fim30';
                        $params[':ini30'] = $ultimo30;
                        $params[':fim30'] = $hoje;
                        $filtered = true;
                    } else {
                        // "todos": sem restrição adicional de data
                    }
                }

                if ($isUnificado) {
                    $sql = 'SELECT 
                                GROUP_CONCAT(DISTINCT funcionario SEPARATOR ", ") as funcionarios, 
                                DATE(data) as data,
                                SUM(CASE WHEN tipo = "ato" THEN total ELSE 0 END) as total_atos,
                                SUM(CASE WHEN tipo = "pagamento" THEN total ELSE 0 END) as total_pagamentos,
                                SUM(CASE WHEN tipo = "devolucao" THEN total ELSE 0 END) as total_devolucoes,
                                SUM(CASE WHEN tipo = "saida" THEN total ELSE 0 END) as total_saidas,
                                SUM(CASE WHEN tipo = "deposito" THEN total ELSE 0 END) as total_depositos
                            FROM (
                                SELECT funcionario, data, "ato" as tipo, total 
                                FROM atos_liquidados
                                UNION ALL
                                SELECT funcionario, data_pagamento as data, "pagamento" as tipo, total_pagamento as total
                                FROM pagamento_os
                                UNION ALL
                                SELECT funcionario, data_devolucao as data, "devolucao" as tipo, total_devolucao as total
                                FROM devolucao_os
                                UNION ALL
                                SELECT funcionario, data, "saida" as tipo, valor_saida as total
                                FROM saidas_despesas WHERE status = "ativo"
                                UNION ALL
                                SELECT funcionario, data_caixa as data, "deposito" as tipo, valor_do_deposito as total
                                FROM deposito_caixa WHERE status = "ativo"
                                UNION ALL
                                SELECT funcionario, data_caixa as data, "caixa" as tipo, saldo_inicial as total
                                FROM caixa WHERE status = "aberto"
                            ) as fluxos';
                    if ($conditions) {
                        $sql .= ' WHERE ' . implode(' AND ', $conditions);
                    }
                    $sql .= ' GROUP BY DATE(data)';
                } else {
                    $sql = 'SELECT 
                                funcionario, 
                                DATE(data) as data,
                                SUM(CASE WHEN tipo = "ato" THEN total ELSE 0 END) as total_atos,
                                SUM(CASE WHEN tipo = "pagamento" THEN total ELSE 0 END) as total_pagamentos,
                                SUM(CASE WHEN tipo = "devolucao" THEN total ELSE 0 END) as total_devolucoes,
                                SUM(CASE WHEN tipo = "saida" THEN total ELSE 0 END) as total_saidas,
                                SUM(CASE WHEN tipo = "deposito" THEN total ELSE 0 END) as total_depositos,
                                SUM(CASE WHEN tipo = "caixa" THEN total ELSE 0 END) as saldo_inicial
                            FROM (
                                SELECT funcionario, data, "ato" as tipo, total 
                                FROM atos_liquidados
                                UNION ALL
                                SELECT funcionario, data_pagamento as data, "pagamento" as tipo, total_pagamento as total
                                FROM pagamento_os
                                UNION ALL
                                SELECT funcionario, data_devolucao as data, "devolucao" as tipo, total_devolucao as total
                                FROM devolucao_os
                                UNION ALL
                                SELECT funcionario, data, "saida" as tipo, valor_saida as total
                                FROM saidas_despesas WHERE status = "ativo"
                                UNION ALL
                                SELECT funcionario, data_caixa as data, "deposito" as tipo, valor_do_deposito as total
                                FROM deposito_caixa WHERE status = "ativo"
                                UNION ALL
                                SELECT funcionario, data_caixa as data, "caixa" as tipo, saldo_inicial as total
                                FROM caixa WHERE status = "aberto"
                            ) as fluxos';
                    if ($conditions) {
                        $sql .= ' WHERE ' . implode(' AND ', $conditions);
                    }
                    $sql .= ' GROUP BY funcionario, DATE(data)';
                }

                $sql .= $filtered ? ' ORDER BY DATE(data) DESC' : ' ORDER BY DATE(data) DESC LIMIT 50';

                $stmt = $conn->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($resultados as $resultado) {
                    $funcionariosList = $isUnificado ? $resultado['funcionarios'] : $resultado['funcionario'];
                    $data = $resultado['data'];
                    $total_atos = $resultado['total_atos'];
                    $total_pagamentos = $resultado['total_pagamentos'];
                    $total_devolucoes = $resultado['total_devolucoes'];
                    $total_saidas = $resultado['total_saidas'];
                    $total_depositos = $resultado['total_depositos'];
                    $saldo_inicial_sum = isset($resultado['saldo_inicial']) ? $resultado['saldo_inicial'] : 0.0;

                    // Calcula Conta/Espécie/Devolvido em espécie
                    $stmtInner = $conn->prepare('SELECT forma_de_pagamento, total_pagamento FROM pagamento_os WHERE ' . ($isUnificado ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_pagamento) = :data');
                    if (!$isUnificado) { $stmtInner->bindParam(':funcionario', $funcionariosList); }
                    $stmtInner->bindParam(':data', $data);
                    $stmtInner->execute();
                    $pagamentos = $stmtInner->fetchAll(PDO::FETCH_ASSOC);

                    $totalRecebidoConta = 0;
                    $totalRecebidoEspecie = 0;
                    foreach ($pagamentos as $pg) {
                        if (in_array($pg['forma_de_pagamento'], ['PIX', 'Centrais Eletrônicas', 'Boleto', 'Transferência Bancária', 'Depósito Bancário', 'Crédito', 'Débito'])) {
                            $totalRecebidoConta += $pg['total_pagamento'];
                        } else if ($pg['forma_de_pagamento'] === 'Espécie') {
                            $totalRecebidoEspecie += $pg['total_pagamento'];
                        }
                    }

                    $stmtInner = $conn->prepare('SELECT forma_devolucao, total_devolucao FROM devolucao_os WHERE ' . ($isUnificado ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_devolucao) = :data');
                    if (!$isUnificado) { $stmtInner->bindParam(':funcionario', $funcionariosList); }
                    $stmtInner->bindParam(':data', $data);
                    $stmtInner->execute();
                    $devolucoes = $stmtInner->fetchAll(PDO::FETCH_ASSOC);

                    $totalDevolvidoEspecie = 0;
                    foreach ($devolucoes as $dv) {
                        if ($dv['forma_devolucao'] === 'Espécie') {
                            $totalDevolvidoEspecie += $dv['total_devolucao'];
                        }
                    }

                    // Depósitos do Caixa
                    $stmtInner = $conn->prepare('SELECT valor_do_deposito FROM deposito_caixa WHERE ' . ($isUnificado ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_caixa) = :data AND status = "ativo"');
                    if (!$isUnificado) { $stmtInner->bindParam(':funcionario', $funcionariosList); }
                    $stmtInner->bindParam(':data', $data);
                    $stmtInner->execute();
                    $depositosArr = $stmtInner->fetchAll(PDO::FETCH_ASSOC);
                    $totalDepositoCaixa = array_reduce($depositosArr, function($carry, $item){ return $carry + $item['valor_do_deposito']; }, 0);

                    // Saldo Transportado (individual)
                    $stmtInner = $conn->prepare('SELECT valor_transportado FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :data AND funcionario = :funcionario');
                    $stmtInner->bindParam(':data', $data);
                    $stmtInner->bindParam(':funcionario', $funcionariosList);
                    $stmtInner->execute();
                    $transportes = $stmtInner->fetchAll(PDO::FETCH_ASSOC);
                    $totalSaldoTransportado = array_reduce($transportes, function($carry, $item){ return $carry + floatval($item['valor_transportado']); }, 0);

                    // Saldo Inicial (individual) e ID do caixa
                    $stmtInner = $conn->prepare('SELECT id, saldo_inicial FROM caixa WHERE DATE(data_caixa) = :data' . ($isUnificado ? '' : ' AND funcionario = :funcionario'));
                    if (!$isUnificado) { $stmtInner->bindParam(':funcionario', $funcionariosList); }
                    $stmtInner->bindParam(':data', $data);
                    $stmtInner->execute();
                    $caixa = $stmtInner->fetch(PDO::FETCH_ASSOC);
                    $saldoInicial = $caixa ? floatval($caixa['saldo_inicial']) : 0.0;
                    $idCaixa = ($caixa && isset($caixa['id'])) ? $caixa['id'] : null;

                    // Total em Caixa (para cor do card e, no individual, exibição)
                    if ($isUnificado) {
                        $totalEmCaixa_calc = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $total_saidas - $totalDepositoCaixa;
                        $stmtInner = $conn->prepare('SELECT SUM(valor_transportado) as total_transportado FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :data');
                        $stmtInner->bindParam(':data', $data);
                        $stmtInner->execute();
                        $saldoTransportadoUni = $stmtInner->fetch(PDO::FETCH_ASSOC);
                        if ($saldoTransportadoUni && isset($saldoTransportadoUni['total_transportado'])) {
                            $totalEmCaixa_calc -= $saldoTransportadoUni['total_transportado'];
                        }
                    } else {
                        $totalEmCaixa_calc = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $total_saidas - $totalDepositoCaixa - $totalSaldoTransportado;
                    }

                    $isClosed = (round($totalEmCaixa_calc, 2) == 0.00);
                    $cardBgClass = $isClosed ? 'pastel-closed' : 'pastel-open';
                    $badgeClass = $isClosed ? 'badge-closed' : 'badge-open';
                    $statusLabel = $isClosed ? 'Fechado' : 'Aberto';
                    $statusIcon = $isClosed ? 'fa-lock' : 'fa-unlock-alt';

                    // Helper de formatação
                    $fmt = function($v){ return 'R$ ' . number_format(floatval($v), 2, ',', '.'); };
                    $dataBR = date('d/m/Y', strtotime($data));

                    echo '<div class="col-12 col-sm-6 col-md-4 col-lg-3 caixa-col">
                            <div class="card caixa-card '.$cardBgClass.'" onclick="verDetalhes(\''.htmlspecialchars($funcionariosList, ENT_QUOTES).'\', \''.$data.'\', \''.($isUnificado ? 'unificado' : 'individual').'\')">
                                <div class="card-body">
                                    <div class="header-block topline">
                                        <div class="title-strong">'.htmlspecialchars($isUnificado ? "Caixa Unificado" : $funcionariosList).'</div>
                                        <span class="badge-status '.$badgeClass.'"><i class="fa '.$statusIcon.'"></i> '.$statusLabel.'</span>
                                    </div>
                                    <div class="muted">Data: '.$dataBR.'</div>

                                    <div class="metrics">';

                    if (!$isUnificado) {
                        echo '      <div class="metric">
                                        <span class="chip chip-saldo">Saldo Inicial</span>
                                        <div class="k">'.$fmt($saldoInicial).'</div>
                                    </div>';
                    }
                    echo '              <div class="metric">
                                        <span class="chip chip-atos">Atos Liquidados</span>
                                        <div class="k">'.$fmt($total_atos).'</div>
                                    </div>
                                    <div class="metric">
                                        <span class="chip chip-conta">Recebido em Conta</span>
                                        <div class="k">'.$fmt($totalRecebidoConta).'</div>
                                    </div>
                                    <div class="metric">
                                        <span class="chip chip-especie">Recebido em Espécie</span>
                                        <div class="k">'.$fmt($totalRecebidoEspecie).'</div>
                                    </div>
                                    <div class="metric">
                                        <span class="chip chip-devolucoes">Devoluções</span>
                                        <div class="k">'.$fmt($total_devolucoes).'</div>
                                    </div>
                                    <div class="metric">
                                        <span class="chip chip-saidas">Saídas e Despesas</span>
                                        <div class="k">'.$fmt($total_saidas).'</div>
                                    </div>
                                    <div class="metric">
                                        <span class="chip chip-deposito">Depósito do Caixa</span>
                                        <div class="k">'.$fmt($totalDepositoCaixa).'</div>
                                    </div>';
                    if (!$isUnificado) {
                        echo '      <div class="metric">
                                        <span class="chip chip-total">Total em Caixa</span>
                                        <div class="k">'.$fmt($totalEmCaixa_calc).'</div>
                                    </div>';
                    }

                    echo '          </div>';

                    // Ações (preservadas) — padronizadas com .btn-icon — impedir propagação para não abrir detalhes junto
                    echo '          <div class="card-footer-eq">
                                        <div class="card-actions">';

                    if (!$isUnificado) {
                        echo '          <button title="Saídas e Despesas" class="btn btn-delete btn-sm btn-icon" onclick="event.stopPropagation(); cadastrarSaida(\''.htmlspecialchars($funcionariosList, ENT_QUOTES).'\', \''.$data.'\')">
                                        <i class="fa fa-sign-out" aria-hidden="true"></i>
                                    </button>
                                    <button title="Depósito do Caixa" class="btn btn-success btn-sm btn-icon" onclick="event.stopPropagation(); cadastrarDeposito(\''.htmlspecialchars($funcionariosList, ENT_QUOTES).'\', \''.$data.'\')">
                                        <i class="fa fa-university" aria-hidden="true"></i>
                                    </button>';
                        if ($idCaixa) {
                            echo '<a href="imprimir_fechamento_caixa.php?id='.urlencode($idCaixa).'" target="_blank" title="Imprimir Fechamento" class="btn btn-primary btn-sm btn-icon" onclick="event.stopPropagation();">
                                    <i class="fa fa-file-pdf-o"></i>
                                  </a>';
                        }
                    } else {
                        echo '      <button title="Ver Depósitos do Caixa" class="btn btn-success btn-sm btn-icon" onclick="event.stopPropagation(); verDepositosCaixa(\''.$data.'\')">
                                    <i class="fa fa-list" aria-hidden="true"></i>
                                  </button>
                                  <a href="imprimir_fechamento_caixa_unificado.php?data='.urlencode($data).'" target="_blank" title="Imprimir Fechamento Caixa Unificado" class="btn btn-primary btn-sm btn-icon" onclick="event.stopPropagation();">
                                    <i class="fa fa-file-pdf-o"></i>
                                  </a>';
                    }

                    echo '          </div>';

                    // Botão de Fechamento (cadeado dourado) — aciona o mesmo fluxo do modal de Depósito
                    if (!$isUnificado) {
                        $disabledLock = $isClosed ? 'disabled' : '';
                        echo '<button class="btn btn-lock btn-sm btn-icon" '.$disabledLock.' title="Fechar caixa" onclick="event.stopPropagation(); fecharCaixaRapido(\''.htmlspecialchars($funcionariosList, ENT_QUOTES).'\', \''.$data.'\')">
                                <i class="fa fa-lock"></i>
                              </button>';
                    }

                    echo '      </div>
                                </div>
                            </div>
                        </div>';
                }
                ?>
            </div><!-- /cardsResultados -->
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalhesModal" tabindex="-1" role="dialog" aria-labelledby="detalhesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-center position-relative">
                    <h5 class="modal-title text-center mb-0" id="detalhesModalLabel"></h5>
                    <div id="modalStatusPill" class="modal-status-pill"></div>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" style="position:absolute; right:12px; top:8px;">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-primary mb-3" style="background-color: #005d15 !important">
                                <div class="card-header">Saldo Inicial</div>
                                <div class="card-body"><h5 class="card-title" id="cardSaldoInicial">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-primary mb-3">
                                <div class="card-header">Atos Liquidados</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalAtos">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white" style="background-color: #6f42c1;">
                                <div class="card-header">Atos Manuais</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalAtosManuais">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <!-- NOVO: Atos Isentos -->
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white" style="background-color: #17a2b8;">
                                <div class="card-header">Atos Isentos</div>
                                <div class="card-body"><h5 class="card-title" id="cardAtosIsentos">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-warning mb-3">
                                <div class="card-header">Recebido em Conta</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalRecebidoConta">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-header">Recebido em Espécie</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalRecebidoEspecie">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-petroleo mb-3">
                                <div class="card-header">Total Recebido</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalRecebido">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-secondary mb-3">
                                <div class="card-header">Devoluções</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalDevolucoes">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-danger mb-3">
                                <div class="card-header">Saídas e Despesas</div>
                                <div class="card-body"><h5 class="card-title" id="cardSaidasDespesas">R$ 0,00</h5></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-header">Depósito do Caixa</div>
                                <div class="card-body"><h5 class="card-title" id="cardDepositoCaixa">R$ 0,00</h5></div>
                            </div>
                        </div>

                        <!-- NOVO: Total em Selos -->
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-primary mb-3">
                                <div class="card-header">Total em Selos</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalSelos">R$ 0,00</h5></div>
                            </div>
                        </div>

                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white btn-4 mb-3">
                                <div class="card-header">Saldo Transportado</div>
                                <div class="card-body"><h5 class="card-title" id="cardSaldoTransportado">R$ 0,00</h5></div>
                            </div>
                        </div>
                        
                        <div class="col-6 col-sm-6 col-md-3 col-lg-3">
                            <div class="card text-white bg-dark mb-3">
                                <div class="card-header">Total em Caixa</div>
                                <div class="card-body"><h5 class="card-title" id="cardTotalEmCaixa">R$ 0,00</h5></div>
                            </div>
                        </div>
                    </div>
                    <hr>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>ATOS LIQUIDADOS</b></div>
                        <div class="card-body">
                            <!-- NOVO: Toolbar de filtros -->
                            <div class="row no-gutters align-items-end mb-3" id="filtrosAtosLiquidados">
                                <div class="col-12 col-md-3 mb-2" id="filtroAtosFuncCol" style="display:none;">
                                    <label class="input-label">Funcionário</label>
                                    <select class="form-control form-control-sm" id="filtroAtosFuncionario"></select>
                                </div>
                                <div class="col-12 col-md-3 mb-2">
                                    <label class="input-label">Ato</label>
                                    <select class="form-control form-control-sm" id="filtroAtosAto"></select>
                                </div>
                                <div class="col-12 col-md-3 mb-2">
                                    <label class="input-label">Apresentante</label>
                                    <select class="form-control form-control-sm" id="filtroAtosApresentante"></select>
                                </div>
                                <div class="col-12 col-md-3 mb-2">
                                    <label class="input-label">Nº OS</label>
                                    <select class="form-control form-control-sm" id="filtroAtosOS"></select>
                                </div>
                                <div class="col-12 mt-1">
                                    <button class="btn btn-outline-secondary btn-sm" id="btnLimparFiltrosAtos">
                                        <i class="fa fa-eraser"></i> Limpar filtros
                                    </button>
                                </div>
                            </div>
                            <!-- /Toolbar de filtros -->

                            <div class="table-responsive">
                                <table id="tabelaAtos" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Nº OS</th>
                                            <th>Apresentante</th>
                                            <th>Ato</th>
                                            <th>Descrição</th>
                                            <th>Quantidade</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesAtos"></tbody>
                                </table>
                            </div>

                            <!-- NOVO: total com quantidade dinâmica -->
                            <h6 class="total-label d-flex flex-wrap align-items-center">
                                <span class="mr-3">Qtd.: <span id="qtdAtos">0</span></span>
                                <span>Total Atos Liquidados: <span id="totalAtos"></span></span>
                            </h6>
                        </div>
                    </div>

                    <!-- NOVO: SELOS -->
                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>SELOS</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaSelos" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Nº Selo</th>
                                            <th>Ato</th>
                                            <th>Tipo</th>
                                            <th>Selagem</th>
                                            <th>Emolumentos</th>
                                            <th>FERJ</th>
                                            <th>FADEP</th>
                                            <th>FERC</th>
                                            <th>FERC</th>
                                            <th>TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesSelos"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Total em Selos: <span id="totalSelos">R$ 0,00</span></h6>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>ATOS MANUAIS</b></div>
                        <div class="card-body">
                            <!-- NOVO: Toolbar de filtros -->
                            <div class="row no-gutters align-items-end mb-3" id="filtrosAtosManuais">
                                <div class="col-12 col-md-3 mb-2" id="filtroManuaisFuncCol" style="display:none;">
                                    <label class="input-label">Funcionário</label>
                                    <select class="form-control form-control-sm" id="filtroManuaisFuncionario"></select>
                                </div>
                                <div class="col-12 col-md-3 mb-2">
                                    <label class="input-label">Ato</label>
                                    <select class="form-control form-control-sm" id="filtroManuaisAto"></select>
                                </div>
                                <div class="col-12 col-md-3 mb-2">
                                    <label class="input-label">Apresentante</label>
                                    <select class="form-control form-control-sm" id="filtroManuaisApresentante"></select>
                                </div>
                                <div class="col-12 col-md-3 mb-2">
                                    <label class="input-label">Nº OS</label>
                                    <select class="form-control form-control-sm" id="filtroManuaisOS"></select>
                                </div>
                                <div class="col-12 mt-1">
                                    <button class="btn btn-outline-secondary btn-sm" id="btnLimparFiltrosManuais">
                                        <i class="fa fa-eraser"></i> Limpar filtros
                                    </button>
                                </div>
                            </div>
                            <!-- /Toolbar de filtros -->

                            <div class="table-responsive">
                                <table id="tabelaAtosManuais" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Nº OS</th>
                                            <th>Apresentante</th>
                                            <th>Ato</th>
                                            <th>Descrição</th>
                                            <th>Quantidade</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesAtosManuais"></tbody>
                                </table>
                            </div>

                            <!-- NOVO: total com quantidade dinâmica -->
                            <h6 class="total-label d-flex flex-wrap align-items-center">
                                <span class="mr-3">Qtd.: <span id="qtdAtosManuais">0</span></span>
                                <span>Total Atos Manuais: <span id="totalAtosManuais"></span></span>
                            </h6>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>PAGAMENTOS</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaPagamentos" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>    
                                            <th>Nº OS</th>
                                            <th>Apresentante</th>
                                            <th>Forma de Pagamento</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesPagamentos"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Total Pagamentos: <span id="totalPagamentos"></span></h6>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>TOTAL POR TIPO DE PAGAMENTO</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaTotalPorTipo" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Forma de Pagamento</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesTotalPorTipo"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>DEVOLUÇÕES</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaDevolucoes" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Nº OS</th>
                                            <th>Apresentante</th>
                                            <th>Forma de Devolução</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesDevolucoes"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Total Devoluções: <span id="totalDevolucoes"></span></h6>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>SAÍDAS E DESPESAS</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaSaidas" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Título</th>
                                            <th>Valor</th>
                                            <th>Forma de Saída</th>
                                            <th>Data do Caixa</th>
                                            <th>Data Cadastro</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesSaidas"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Total Saídas: <span id="totalSaidas"></span></h6>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>DEPÓSITOS</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaDepositos" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Data do Caixa</th>
                                            <th>Data Cadastro</th>
                                            <th>Valor</th>
                                            <th>Tipo</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesDepositos"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header table-title text-center"><b>SALDO TRANSPORTADO</b></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tabelaSaldoTransportado" class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Data Caixa</th>
                                            <th>Data Transporte</th>
                                            <th>Valor Transportado</th>
                                            <th>Funcionário</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detalhesSaldoTransportado"></tbody>
                                </table>
                            </div>
                            <h6 class="total-label">Total Saldo Transportado: <span id="totalSaldoTransportado"></span></h6>
                        </div>
                    </div>
                </div><!-- /modal-body -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro de Saídas -->
    <div class="modal fade" id="cadastroSaidaModal" tabindex="-1" role="dialog" aria-labelledby="cadastroSaidaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-responsive modal-modern" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastroSaidaModalLabel">Cadastrar Saída/Despesa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formCadastroSaida" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-12 col-md-8">
                                <label class="input-label" for="titulo">Título</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required placeholder="Ex.: Combustível, Material de escritório...">
                            </div>
                            <div class="form-group col-12 col-md-4">
                                <label class="input-label" for="valor_saida">Valor da Saída</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                    <input type="text" class="form-control" id="valor_saida" name="valor_saida" required placeholder="0,00">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="display:none;">
                            <label class="input-label" for="forma_de_saida">Forma de Saída</label>
                            <select class="form-control" id="forma_de_saida" name="forma_de_saida" required>
                                <option value="Espécie">Espécie</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="input-label d-block" for="anexo">Anexo</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="anexo" name="anexo" required>
                                <label class="custom-file-label" for="anexo">Selecione um arquivo...</label>
                            </div>
                            <small class="input-hint">Formatos aceitos: PDF, JPG, PNG (máx. 10MB).</small>
                        </div>

                        <input type="hidden" id="data_saida" name="data_saida">
                        <input type="hidden" id="data_caixa_saida" name="data_caixa_saida">
                        <input type="hidden" id="funcionario_saida" name="funcionario_saida">

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Adicionar
                        </button>
                    </form>

                    <hr>
                    <h5>Saídas/Despesas Cadastradas</h5>
                    <div class="table-responsive">
                        <table id="tabelaSaidasCadastradas" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Título</th>
                                    <th>Valor</th>
                                    <th>Forma de Saída</th>
                                    <th>Anexo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="detalhesSaidasCadastradas"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro de Depósito -->
    <div class="modal fade" id="cadastroDepositoModal" tabindex="-1" role="dialog" aria-labelledby="cadastroDepositoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-responsive modal-modern" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastroDepositoModalLabel">Cadastrar Depósito do Caixa</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row stats-card">
                        <div class="col-12 col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Total em Caixa</h5>
                                    <p class="card-text" id="total_em_caixa" style="font-size:1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Depósitos</h5>
                                    <p class="card-text" id="total_depositos" style="font-size:1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title2">Saldo Transportado</h5>
                                    <p class="card-text" id="saldo_transportado" style="font-size:1.5em;">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>

                    <form id="formCadastroDeposito" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group col-12 col-md-6">
                                <label class="input-label" for="valor_deposito">Valor do Depósito</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                    <input type="text" class="form-control" id="valor_deposito" name="valor_deposito" required placeholder="0,00">
                                </div>
                            </div>
                            <div class="form-group col-12 col-md-6">
                                <label class="input-label" for="tipo_deposito">Tipo de Depósito</label>
                                <select class="form-control" id="tipo_deposito" name="tipo_deposito" required>
                                    <option value="" disabled selected>Selecione</option>
                                    <option value="Depósito Bancário">Depósito Bancário</option>
                                    <option value="Espécie">Espécie</option>
                                    <option value="Transferência">Transferência</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row align-items-end">
                            <div class="form-group col-12 col-md-6">
                                <label class="input-label d-block" for="comprovante_deposito">Comprovante de Depósito</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="comprovante_deposito" name="comprovante_deposito" required>
                                    <label class="custom-file-label" for="comprovante_deposito">Selecione um arquivo...</label>
                                </div>
                                <small class="input-hint">PDF, JPG, PNG (máx. 10MB).</small>
                            </div>

                            <div class="form-group col-12 col-md-6" id="sem-comprovante-group" style="display:none;">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input" id="sem_comprovante" name="sem_comprovante">
                                    <label class="custom-control-label" for="sem_comprovante">Sem comprovante</label>
                                </div>
                                <small class="input-hint">Use apenas quando o depósito em espécie não gerar comprovante.</small>
                            </div>
                        </div>

                        <input type="hidden" id="data_caixa_deposito" name="data_caixa_deposito">
                        <input type="hidden" id="funcionario_deposito" name="funcionario_deposito">

                        <button type="submit" id="btnAdicionarDeposito" class="btn btn-primary btn-block">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Adicionar
                        </button>
                    </form>

                    <hr>
                    <h5>Depósitos Registrados</h5>
                    <div class="table-responsive">
                        <table id="tabelaDepositosRegistrados" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Data do Caixa</th>
                                    <th>Data Cadastro</th>
                                    <th>Valor</th>
                                    <th>Tipo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="detalhesDepositosRegistrados"></tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="form-group">
                        <button type="button" id="btnTransportarSaldo" style="width: 100%" class="btn btn-danger" onclick="transportarSaldoFecharCaixa()">
                            <i class="fa fa-lock" aria-hidden="true"></i> Fechar Caixa e Transportar Saldo <i class="fa fa-share" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Anexar Comprovante de Depósito -->
    <div class="modal fade" id="anexarComprovanteModal" tabindex="-1" role="dialog" aria-labelledby="anexarComprovanteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-responsive" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="anexarComprovanteModalLabel">Anexar Comprovante</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formAnexarComprovante" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="input-label d-block" for="arquivo_comprovante">Comprovante</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="arquivo_comprovante" name="arquivo_comprovante" required>
                                <label class="custom-file-label" for="arquivo_comprovante">Selecione um arquivo...</label>
                            </div>
                            <small class="input-hint">PDF, JPG, PNG (máx. 10MB).</small>
                        </div>

                        <input type="hidden" id="deposito_id_anexo" name="deposito_id_anexo">
                        <input type="hidden" id="funcionario_anexo" name="funcionario_anexo">
                        <input type="hidden" id="data_caixa_anexo" name="data_caixa_anexo">

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-upload" aria-hidden="true"></i> Enviar Comprovante
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Listagem de Depósitos do Caixa Unificado -->
    <div class="modal fade" id="verDepositosCaixaModal" tabindex="-1" role="dialog" aria-labelledby="verDepositosCaixaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-deposito-caixa-unificado modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verDepositosCaixaModalLabel">Depósitos do Caixa Unificado</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tabelaDepositosCaixaUnificado" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Data do Caixa</th>
                                    <th>Data Cadastro</th>
                                    <th>Valor</th>
                                    <th>Tipo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="detalhesDepositosCaixaUnificado"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal de Abertura de Caixa -->
    <div class="modal fade" id="abrirCaixaModal" tabindex="-1" role="dialog" aria-labelledby="abrirCaixaModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content modal-abrir-caixa">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="abrirCaixaModalLabel">Abrir Caixa do Dia</h5>
                    </div>
                    <div class="modal-body">
                        <form id="formAbrirCaixa">
                            <div class="form-group">
                                <label for="saldo_inicial">Saldo Inicial</label>
                                <input type="text" class="form-control" id="saldo_inicial" name="saldo_inicial" required>
                            </div>
                            <button type="submit" style="width: 100%" class="btn btn-primary">Abrir Caixa</button>
                        </form>

                        <!-- NOVO: opção para entrar sem abrir agora -->
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-outline-secondary" style="width:100%" onclick="pularAberturaCaixa()">
                                Entrar sem abrir agora
                            </button>
                            <small class="form-text text-muted mt-2">
                                Você poderá abrir o caixa a qualquer momento.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/bootstrap.bundle.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/jquery.dataTables.min.js"></script>
    <script src="../script/dataTables.bootstrap4.min.js"></script>
    <script src="../script/sweetalert2.js"></script>
    <script>
        $(document).ready(function() {
            // Preencher datas com base no período rápido ao mudar o select
            function applyPeriodoToDates() {
                const periodo = $('#periodo').val();
                const hoje = new Date();
                function toISO(d){ return d.toISOString().slice(0,10); }

                if (periodo === 'hoje') {
                    $('#data_inicial').val(toISO(hoje));
                    $('#data_final').val(toISO(hoje));
                } else if (periodo === 'ultimos7') {
                    const d = new Date();
                    d.setDate(d.getDate() - 6);
                    $('#data_inicial').val(toISO(d));
                    $('#data_final').val(toISO(hoje));
                } else if (periodo === 'ultimoMes') {
                    const d = new Date();
                    d.setDate(d.getDate() - 30);
                    $('#data_inicial').val(toISO(d));
                    $('#data_final').val(toISO(hoje));
                } else {
                    // todos: limpa
                    $('#data_inicial').val('');
                    $('#data_final').val('');
                }
            }

            // Na primeira carga, se não houver parâmetros, aplicar "hoje" (evita sobrecarga)
            <?php if (!isset($_GET['periodo']) && empty($_GET['data_inicial']) && empty($_GET['data_final'])): ?>
                $('#periodo').val('hoje');
                applyPeriodoToDates();
            <?php endif; ?>

            $('#periodo').on('change', function(){
                applyPeriodoToDates();
            });

            // Máscaras
            $('#valor_saida').mask('#.##0,00', {reverse: true});
            $('#valor_deposito').mask('#.##0,00', {reverse: true});
            $('#saldo_inicial').mask('#.##0,00', {reverse: true});

            // Envio de Saída
            $('#formCadastroSaida').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                $.ajax({
                    url: 'salvar_saida.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Saída cadastrada com sucesso!', confirmButtonText: 'OK' })
                            .then(() => { $('#cadastroSaidaModal').modal('hide'); location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar saída: ' + response.error, confirmButtonText: 'OK' });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar saída: ' + textStatus + ' - ' + errorThrown, confirmButtonText: 'OK' });
                    }
                });
            });

            // Atualiza label do custom-file ao selecionar arquivo (Saída + Depósito)
            $(document).on('change', '.custom-file-input', function () {
                var fileName = $(this).val().split('\\').pop();
                $(this).siblings('.custom-file-label').addClass('selected').text(fileName || 'Selecione um arquivo...');
            });

            // Depósito: comportamento do tipo (ajustado para "Espécie" + "Sem comprovante")
            $('#tipo_deposito').on('change', function() {
                var tipoDeposito = $(this).val();
                if (tipoDeposito === 'Espécie') {
                    $('#sem-comprovante-group').show();
                    // Se o usuário marcou "Sem comprovante", o anexo NÃO é obrigatório
                    var semComprovanteMarcado = $('#sem_comprovante').is(':checked');
                    $('#comprovante_deposito').prop('required', !semComprovanteMarcado);
                } else {
                    $('#sem-comprovante-group').hide();
                    $('#sem_comprovante').prop('checked', false);
                    // Para outros tipos, o anexo é sempre obrigatório
                    $('#comprovante_deposito').prop('required', true);
                }
            });

            // Marcar/Desmarcar "Sem comprovante" atualiza a obrigatoriedade do anexo
            $('#sem_comprovante').on('change', function() {
                var isChecked = $(this).is(':checked');
                var tipoDeposito = $('#tipo_deposito').val();
                if (tipoDeposito === 'Espécie') {
                    $('#comprovante_deposito').prop('required', !isChecked);
                    if (isChecked) {
                        // limpa o input para evitar o "required" em alguns navegadores
                        $('#comprovante_deposito').val('');
                    }
                }
            });

            // Garante estado inicial consistente
            $('#tipo_deposito').trigger('change');

            // Envio Depósito (com validação do total em caixa)
            $('#formCadastroDeposito').on('submit', function(e) {
                e.preventDefault();

                var totalEmCaixa = parseFloat($('#total_em_caixa').text().replace('R$ ', '').replace(/\./g, '').replace(',', '.')) || 0;
                var valorDeposito = parseFloat($('#valor_deposito').val().replace(/\./g, '').replace(',', '.')) || 0;

                if (valorDeposito > totalEmCaixa) {
                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'O valor do depósito não pode ser maior do que o total disponível em caixa.', confirmButtonText: 'OK' });
                    return;
                }

                Swal.fire({
                    title: 'Você tem certeza?',
                    text: `Deseja realmente inserir o depósito de R$ ${valorDeposito.toFixed(2).replace('.', ',')}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sim, inserir',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var formData = new FormData($('#formCadastroDeposito')[0]);
                        $.ajax({
                            url: 'salvar_deposito.php',
                            type: 'POST',
                            data: formData,
                            contentType: false,
                            processData: false,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Depósito cadastrado com sucesso!', confirmButtonText: 'OK' })
                                    .then(() => { $('#cadastroDepositoModal').modal('hide'); location.reload(); });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar depósito: ' + response.error, confirmButtonText: 'OK' });
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao cadastrar depósito: ' + textStatus + ' - ' + errorThrown, confirmButtonText: 'OK' });
                            }
                        });
                    }
                });
            });

            // Carregar Saídas no modal
            $('#cadastroSaidaModal').on('shown.bs.modal', function () {
                var funcionario = $('#funcionario_saida').val();
                var data_caixa = $('#data_caixa_saida').val();

                $.ajax({
                    url: 'listar_saidas.php',
                    type: 'GET',
                    data: { funcionario: funcionario, data_caixa: data_caixa },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) { alert('Erro: ' + response.error); return; }

                        var saidas = response.saidas;
                        $('#detalhesSaidasCadastradas').empty();
                        saidas.forEach(function(saida) {
                            var anexo = saida.caminho_anexo ? `<button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarAnexoSaida('${saida.caminho_anexo}', '${saida.funcionario}', '${saida.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>` : '';
                            const podeExcluirSaida = (saida.pode_excluir === true || saida.pode_excluir === 1 || saida.pode_excluir === '1');
                            const deleteBtnSaida = podeExcluirSaida
                                ? `<button title="Remover" class="btn btn-delete btn-sm btn-icon" onclick="removerSaida(${saida.id})"><i class="fa fa-trash" aria-hidden="true"></i></button>`
                                : '';

                            $('#detalhesSaidasCadastradas').append(`
                                <tr>
                                    <td>${saida.funcionario}</td>
                                    <td>${saida.titulo}</td>
                                    <td>${formatCurrency(saida.valor_saida)}</td>
                                    <td>${saida.forma_de_saida}</td>
                                    <td>${anexo}</td>
                                    <td>${deleteBtnSaida}</td>
                                </tr>
                            `);
                        });

                        $('#tabelaSaidasCadastradas').DataTable({
                            "language": { "url": "../style/Portuguese-Brasil.json" },
                            "destroy": true,
                            "pageLength": 10,
                            "order": [],
                        });
                    },
                    error: function() { alert('Erro ao obter saídas.'); }
                });
            });

            // Abrir Caixa ao carregar (se necessário)
            abrirCaixaModal();

            // Validação de data (filtros)
            var currentYear = new Date().getFullYear();
            function validateDate(input) {
                var selectedDate = new Date($(input).val());
                if (selectedDate.getFullYear() > currentYear) {
                    Swal.fire({ icon: 'warning', title: 'Data inválida', text: 'O ano não pode ser maior que o ano atual.', confirmButtonText: 'Ok' });
                    $(input).val('');
                }
            }
            $('#data_inicial, #data_final').on('change', function() {
                if ($(this).val()) { validateDate(this); }
            });
        });

        function abrirCaixaModal() {
            if (window.__skipAbrirCaixa === true) return;

            $.ajax({
                url: 'verificar_caixa_aberto.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.aberto) {
                        return;
                    } else {
                        $('#abrirCaixaModal').modal('show');
                        var v = response.saldo_transportado || 0;
                        $('#saldo_inicial').val(parseFloat(v).toFixed(2).replace('.', ','));
                    }
                },
                error: function() { alert('Erro ao verificar caixa.'); }
            });
        }

        function pularAberturaCaixa(){
            window.__skipAbrirCaixa = true;
            $('#abrirCaixaModal').modal('hide');
        }

        function verDetalhes(funcionarios, data, tipo) {
            $.ajax({
                url: 'detalhes_fluxo_caixa.php',
                type: 'GET',
                data: { funcionarios: funcionarios, data: data, tipo: tipo },
                success: function(response) {
                    if (response.error) { alert('Erro: ' + response.error); return; }

                    var detalhes = response;

                    var dataFormatada = formatDateForDisplay(data);
                    $('#detalhesModalLabel').html(`CAIXA DO DIA ${dataFormatada} - FUNCIONÁRIO: ${funcionarios}`);

                    // Status (com cadeado) no cabeçalho do modal
                    var fechado = (parseFloat(detalhes.totalEmCaixa) === 0);
                    var statusText = fechado ? 'Fechado' : 'Aberto';
                    var statusClass = fechado ? 'badge-closed' : 'badge-open';
                    var icon = fechado ? 'fa-lock' : 'fa-unlock-alt';
                    $('#modalStatusPill').html(`<span class="badge-status ${statusClass}"><i class="fa ${icon}"></i> ${statusText}</span>`);

                    // NOVO: guardar tipo atual (individual | unificado)
                    window.currentTipoCaixa = (tipo || 'individual');

                    // Cards topo
                    toggleCard('#cardTotalAtos', detalhes.totalAtos);
                    toggleCard('#cardTotalAtosManuais', detalhes.totalAtosManuais);
                    toggleCard('#cardTotalRecebidoConta', detalhes.totalRecebidoConta);
                    toggleCard('#cardTotalRecebidoEspecie', detalhes.totalRecebidoEspecie);
                    toggleCard('#cardTotalDevolucoes', detalhes.totalDevolucoes);
                    toggleCard('#cardTotalEmCaixa', detalhes.totalEmCaixa);
                    toggleCard('#cardSaidasDespesas', detalhes.totalSaidasDespesas);
                    toggleCard('#cardDepositoCaixa', detalhes.totalDepositoCaixa);

                    // NOVO: Card Total em Selos
                    toggleCard('#cardTotalSelos', detalhes.totalSelos);

                    toggleCard('#cardSaldoTransportado', detalhes.totalSaldoTransportado);
                    toggleCard('#cardSaldoInicial', detalhes.saldoInicial);


                    // Atos Liquidados
                    var totalAtos = 0;
                    $('#detalhesAtos').empty();
                    detalhes.atos.forEach(function(ato) {
                        totalAtos += parseFloat(ato.total);
                        $('#detalhesAtos').append(`
                            <tr>
                                <td>${ato.funcionario}</td>    
                                <td>${ato.ordem_servico_id}</td>
                                <td>${ato.cliente}</td>
                                <td>${ato.ato}</td>
                                <td>${ato.descricao}</td>
                                <td>${ato.quantidade_liquidada}</td>
                                <td>${formatCurrency(ato.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalAtos').text(formatCurrency(totalAtos));

                    // NOVO: prepara filtros da seção ATOS LIQUIDADOS
                    setupAtosFilterUI(detalhes, window.currentTipoCaixa);

                    // Atos Manuais
                    var totalAtosManuais = 0;
                    $('#detalhesAtosManuais').empty();
                    detalhes.atosManuais.forEach(function(atoManual) {
                        totalAtosManuais += parseFloat(atoManual.total);
                        $('#detalhesAtosManuais').append(`
                            <tr>
                                <td>${atoManual.funcionario}</td>    
                                <td>${atoManual.ordem_servico_id}</td>
                                <td>${atoManual.cliente}</td>
                                <td>${atoManual.ato}</td>
                                <td>${atoManual.descricao}</td>
                                <td>${atoManual.quantidade_liquidada}</td>
                                <td>${formatCurrency(atoManual.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalAtosManuais').text(formatCurrency(totalAtosManuais));

                    // NOVO: prepara filtros da seção ATOS MANUAIS
                    setupManuaisFilterUI(detalhes, window.currentTipoCaixa);

                    // Pagamentos
                    var totalPagamentos = 0;
                    var totalPorTipo = {};
                    $('#detalhesPagamentos').empty();
                    detalhes.pagamentos.forEach(function(pagamento) {
                        totalPagamentos += parseFloat(pagamento.total_pagamento);
                        if (!totalPorTipo[pagamento.forma_de_pagamento]) totalPorTipo[pagamento.forma_de_pagamento] = 0;
                        totalPorTipo[pagamento.forma_de_pagamento] += parseFloat(pagamento.total_pagamento);
                        $('#detalhesPagamentos').append(`
                            <tr>
                                <td>${pagamento.funcionario}</td>    
                                <td>${pagamento.ordem_de_servico_id}</td>
                                <td>${pagamento.cliente}</td>
                                <td>${pagamento.forma_de_pagamento}</td>
                                <td>${formatCurrency(pagamento.total_pagamento)}</td>
                            </tr>
                        `);
                    });
                    $('#totalPagamentos').text(formatCurrency(totalPagamentos));

                    // Total por Tipo
                    var totalRecebidoConta = 0;
                    var totalRecebidoEspecie = 0;
                    $('#detalhesTotalPorTipo').empty();
                    for (var tipo in totalPorTipo) {
                        $('#detalhesTotalPorTipo').append(`
                            <tr>
                                <td>${tipo}</td>
                                <td>${formatCurrency(totalPorTipo[tipo])}</td>
                            </tr>
                        `);
                        if (['PIX', 'Centrais Eletrônicas', 'Boleto', 'Transferência Bancária', 'Depósito Bancário', 'Crédito', 'Débito'].includes(tipo)) {
                            totalRecebidoConta += totalPorTipo[tipo];
                        } else if (tipo === 'Espécie') {
                            totalRecebidoEspecie += totalPorTipo[tipo];
                        }
                    }
                    $('#cardTotalRecebidoConta').text(formatCurrency(totalRecebidoConta));
                    $('#cardTotalRecebidoEspecie').text(formatCurrency(totalRecebidoEspecie));
                    $('#cardTotalRecebido').text(formatCurrency(totalRecebidoConta + totalRecebidoEspecie));

                    // >>> NOVO: Card "Atos Isentos" vem do total por tipo de pagamento "Ato Isento"
                    var totalAtoIsento = totalPorTipo['Ato Isento'] || 0;
                    toggleCard('#cardAtosIsentos', totalAtoIsento);

                    // Devoluções
                    var totalDevolucoes = 0;
                    $('#detalhesDevolucoes').empty();
                    detalhes.devolucoes.forEach(function(devolucao) {
                        totalDevolucoes += parseFloat(devolucao.total_devolucao);
                        $('#detalhesDevolucoes').append(`
                            <tr>
                                <td>${devolucao.funcionario}</td>    
                                <td>${devolucao.ordem_de_servico_id}</td>
                                <td>${devolucao.cliente}</td>
                                <td>${devolucao.forma_devolucao}</td>
                                <td>${formatCurrency(devolucao.total_devolucao)}</td>
                            </tr>
                        `);
                    });
                    $('#totalDevolucoes').text(formatCurrency(totalDevolucoes));

                    // Saídas
                    var totalSaidas = 0;
                    $('#detalhesSaidas').empty();
                    detalhes.saidas.forEach(function(saida) {
                        totalSaidas += parseFloat(saida.valor_saida);
                        var dataCaixaFormatada = formatDateForDisplay(saida.data_caixa);
                        var dataCadastroFormatada = formatDateForDisplay(saida.data);
                        $('#detalhesSaidas').append(`
                            <tr>
                                <td>${saida.funcionario}</td>    
                                <td>${saida.titulo}</td>
                                <td>${formatCurrency(saida.valor_saida)}</td>
                                <td>${saida.forma_de_saida}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarAnexoSaida('${saida.caminho_anexo}', '${saida.funcionario}', '${saida.data_caixa}')">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                    $('#totalSaidas').text(formatCurrency(totalSaidas));

                    // Depósitos
                    var totalDepositos = 0;
                    $('#detalhesDepositos').empty();
                    detalhes.depositos.forEach(function(deposito) {
                        totalDepositos += parseFloat(deposito.valor_do_deposito);
                        $('#detalhesDepositos').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${formatDateForDisplay(deposito.data_caixa)}</td>
                                <td>${formatDateForDisplay2(deposito.data_cadastro)}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarComprovante('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                    $('#totalDepositos').text(formatCurrency(totalDepositos));

                    /* NOVO: SELOS */
                    var totalSelos = 0;
                    $('#detalhesSelos').empty();
                    (detalhes.selos || []).forEach(function(s) {
                        totalSelos += parseFloat(s.total || 0);
                        $('#detalhesSelos').append(`
                            <tr>
                                <td>${s.funcionario}</td>
                                <td>${s.numero_selo}</td>
                                <td>${s.ato || ''}</td>
                                <td>${s.tipo || ''}</td>
                                <td>${s.selagem ? formatDateForDisplay(s.selagem) : ''}</td>
                                <td>${formatCurrency(s.emolumentos)}</td>
                                <td>${formatCurrency(s.ferj)}</td>
                                <td>${formatCurrency(s.fadep)}</td>
                                <td>${formatCurrency(s.ferc)}</td>
                                <td>${formatCurrency(s.femp)}</td>
                                <td>${formatCurrency(s.total)}</td>
                            </tr>
                        `);
                    });
                    $('#totalSelos').text(formatCurrency(totalSelos));
                    // mantém o card em sincronia (caso tabela seja paginada/filtrada no futuro)
                    $('#cardTotalSelos').text(formatCurrency(totalSelos));

                    // Saldo Transportado
                    var totalSaldoTransportado = 0;
                    $('#detalhesSaldoTransportado').empty();
                    detalhes.saldoTransportado.forEach(function(transporte) {
                        totalSaldoTransportado += parseFloat(transporte.valor_transportado);
                        $('#detalhesSaldoTransportado').append(`
                            <tr>
                                <td>${formatDateForDisplay(transporte.data_caixa)}</td>
                                <td>${formatDateForDisplay(transporte.data_transporte)}</td>
                                <td>${formatCurrency(transporte.valor_transportado)}</td>
                                <td>${transporte.funcionario}</td>
                                <td>${transporte.status}</td>
                            </tr>
                        `);
                    });
                    $('#totalSaldoTransportado').text(formatCurrency(totalSaldoTransportado));

                    function toggleCard(selector, value) {
                        const cardElement = $(selector).closest('.col-md-3');
                        if (parseFloat(value) > 0) {
                            $(selector).text(formatCurrency(value));
                            cardElement.show();
                        } else {
                            cardElement.hide();
                        }
                    }

                    $('#detalhesModal').modal('show');

                    // DataTables
                    // NOVO: manter referências globais para recálculo dinâmico
                    if (window.dtAtos) window.dtAtos.destroy();
                    window.dtAtos = $('#tabelaAtos').DataTable({
                        language: { url: "../style/Portuguese-Brasil.json" },
                        destroy: true,
                        pageLength: 10,
                        order: []
                    }).on('draw', function(){ recalcAtosTotals(); });

                    if (window.dtAtosManuais) window.dtAtosManuais.destroy();
                    window.dtAtosManuais = $('#tabelaAtosManuais').DataTable({
                        language: { url: "../style/Portuguese-Brasil.json" },
                        destroy: true,
                        pageLength: 10,
                        order: []
                    }).on('draw', function(){ recalcManuaisTotals(); });

                    $('#tabelaPagamentos').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaTotalPorTipo').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaDevolucoes').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaSaidas').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                    $('#tabelaDepositos').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });

                    // NOVO: tabela de Selos
                    $('#tabelaSelos').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });

                    $('#tabelaSaldoTransportado').DataTable({ "language": { "url": "../style/Portuguese-Brasil.json" }, "destroy": true, "pageLength": 10, "order": [] });
                },
                error: function() { alert('Erro ao obter detalhes.'); }
            });
        }

        // Escapa regex para buscas exatas nas colunas
        function escapeRegex(s){ return String(s || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

        function parseBRMoney(str){
            if (str == null) return 0;
            const s = String(str).replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
            const v = parseFloat(s);
            return isNaN(v) ? 0 : v;
        }

        // Monta lista única e ordenada a partir de um campo
        function distinctFrom(list, key){
            const set = new Set();
            (list || []).forEach(it=>{
                const v = (it && it[key] != null) ? String(it[key]).trim() : '';
                if (v) set.add(v);
            });
            return Array.from(set).sort((a,b)=> a.localeCompare(b,'pt-BR',{numeric:true, sensitivity:'base'}));
        }

        // Preenche um <select> com placeholder + valores
        function fillSelect(selectId, values, placeholder){
            const $sel = $(selectId);
            $sel.empty().append(`<option value="">${placeholder}</option>`);
            values.forEach(v => $sel.append(`<option value="${$('<div>').text(v).html()}">${v}</option>`));
        }

        // ---------- ATOS LIQUIDADOS ----------
        function setupAtosFilterUI(detalhes, tipo){
            const isUni = (tipo === 'unificado');
            // Mostra/oculta filtro por funcionário
            $('#filtroAtosFuncCol').toggle(isUni);

            // Popula selects com base nos dados recebidos do back-end
            fillSelect('#filtroAtosAto',          distinctFrom(detalhes.atos, 'ato'),                 'Todos os Atos');
            fillSelect('#filtroAtosApresentante', distinctFrom(detalhes.atos, 'cliente'),             'Todos os Apresentantes');
            fillSelect('#filtroAtosOS',           distinctFrom(detalhes.atos, 'ordem_servico_id'),    'Todas as O.S');
            if (isUni){
                fillSelect('#filtroAtosFuncionario', distinctFrom(detalhes.atos, 'funcionario'),      'Todos os Funcionários');
            } else {
                $('#filtroAtosFuncionario').empty().append('<option value=""></option>');
            }

            // Eventos
            $('#filtroAtosAto, #filtroAtosApresentante, #filtroAtosOS, #filtroAtosFuncionario')
                .off('change').on('change', applyAtosFilters);

            $('#btnLimparFiltrosAtos').off('click').on('click', function(){
                $('#filtrosAtosLiquidados select').val('');
                applyAtosFilters();
            });

            // Inicializa totais coerentes com a tabela carregada
            recalcAtosTotals();
        }

        function applyAtosFilters(){
            if (!window.dtAtos) return;
            const func  = $('#filtroAtosFuncionario').val() || '';
            const ato   = $('#filtroAtosAto').val() || '';
            const apr   = $('#filtroAtosApresentante').val() || '';
            const os    = $('#filtroAtosOS').val() || '';

            // Mapeamento de colunas: 0=Funcionário, 1=OS, 2=Apresentante, 3=Ato
            window.dtAtos.column(0).search(func ? '^' + escapeRegex(func) + '$' : '', true, false);
            window.dtAtos.column(1).search(os   ? '^' + escapeRegex(os)   + '$' : '', true, false);
            window.dtAtos.column(2).search(apr  ? escapeRegex(apr) : '', true, false);  // contém
            window.dtAtos.column(3).search(ato  ? '^' + escapeRegex(ato)  + '$' : '', true, false);

            window.dtAtos.draw();
            recalcAtosTotals();
        }

        function recalcAtosTotals(){
            if (!window.dtAtos) return;
            let soma = 0, qtd = 0;
            window.dtAtos.rows({search:'applied'}).every(function(){
                const row = this.data(); // array de células
                // col[5]=Quantidade, col[6]=Total
                qtd += parseFloat(String(row[5]).replace(/[^\d,.-]/g,'').replace('.','').replace(',','.')) || 0;
                soma += parseBRMoney(row[6]);
            });
            $('#qtdAtos').text(qtd);
            $('#totalAtos').text(formatCurrency(soma));
            // Atualiza card topo
            $('#cardTotalAtos').text(formatCurrency(soma));
        }

        // ---------- ATOS MANUAIS ----------
        function setupManuaisFilterUI(detalhes, tipo){
            const isUni = (tipo === 'unificado');
            $('#filtroManuaisFuncCol').toggle(isUni);

            fillSelect('#filtroManuaisAto',          distinctFrom(detalhes.atosManuais, 'ato'),                 'Todos os Atos');
            fillSelect('#filtroManuaisApresentante', distinctFrom(detalhes.atosManuais, 'cliente'),             'Todos os Apresentantes');
            fillSelect('#filtroManuaisOS',           distinctFrom(detalhes.atosManuais, 'ordem_servico_id'),    'Todas as O.S');
            if (isUni){
                fillSelect('#filtroManuaisFuncionario', distinctFrom(detalhes.atosManuais, 'funcionario'),      'Todos os Funcionários');
            } else {
                $('#filtroManuaisFuncionario').empty().append('<option value=""></option>');
            }

            $('#filtroManuaisAto, #filtroManuaisApresentante, #filtroManuaisOS, #filtroManuaisFuncionario')
                .off('change').on('change', applyManuaisFilters);

            $('#btnLimparFiltrosManuais').off('click').on('click', function(){
                $('#filtrosAtosManuais select').val('');
                applyManuaisFilters();
            });

            recalcManuaisTotals();
        }

        function applyManuaisFilters(){
            if (!window.dtAtosManuais) return;
            const func  = $('#filtroManuaisFuncionario').val() || '';
            const ato   = $('#filtroManuaisAto').val() || '';
            const apr   = $('#filtroManuaisApresentante').val() || '';
            const os    = $('#filtroManuaisOS').val() || '';

            // Mapeamento igual: 0=Funcionário, 1=OS, 2=Apresentante, 3=Ato
            window.dtAtosManuais.column(0).search(func ? '^' + escapeRegex(func) + '$' : '', true, false);
            window.dtAtosManuais.column(1).search(os   ? '^' + escapeRegex(os)   + '$' : '', true, false);
            window.dtAtosManuais.column(2).search(apr  ? escapeRegex(apr) : '', true, false);
            window.dtAtosManuais.column(3).search(ato  ? '^' + escapeRegex(ato)  + '$' : '', true, false);

            window.dtAtosManuais.draw();
            recalcManuaisTotals();
        }

        function recalcManuaisTotals(){
            if (!window.dtAtosManuais) return;
            let soma = 0, qtd = 0;
            window.dtAtosManuais.rows({search:'applied'}).every(function(){
                const row = this.data();
                qtd += parseFloat(String(row[5]).replace(/[^\d,.-]/g,'').replace('.','').replace(',','.')) || 0;
                soma += parseBRMoney(row[6]);
            });
            $('#qtdAtosManuais').text(qtd);
            $('#totalAtosManuais').text(formatCurrency(soma));
            // Atualiza card topo
            $('#cardTotalAtosManuais').text(formatCurrency(soma));
        }

        // ============ /FIM NOVO BLOCO ============ 


        function cadastrarSaida(funcionarios, data) {
            $('#data_saida').val(data);
            $('#data_caixa_saida').val(data);
            $('#funcionario_saida').val(funcionarios);
            $('#cadastroSaidaModal').modal('show');
        }

        function cadastrarDeposito(funcionarios, data) {
            $('#data_caixa_deposito').val(data);
            $('#funcionario_deposito').val(funcionarios);
            carregarDepositos(funcionarios, data);
            $('#cadastroDepositoModal').modal('show');
        }

        // ADAPTADO: aceitar callback opcional após carregar depósitos (usado no fechamento rápido)
        function carregarDepositos(funcionarios, data, callback) {
            $.ajax({
                url: 'listar_depositos.php',
                type: 'GET',
                data: { funcionarios: funcionarios, data: data },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: response.error, confirmButtonText: 'OK' });
                        return;
                    }
                    if (!Array.isArray(response.depositos)) {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: 'Dados de depósitos inválidos', confirmButtonText: 'OK' });
                        return;
                    }

                    var depositos = response.depositos;
                    $('#detalhesDepositosRegistrados').empty();
                    depositos.forEach(function(deposito) {
                        var dataCadastroFormatada = formatDateForDisplay2(deposito.data_cadastro);
                        var dataCaixaFormatada = formatDateForDisplay(deposito.data_caixa);
                        const podeExcluirDeposito = (deposito.pode_excluir === true || deposito.pode_excluir === 1 || deposito.pode_excluir === '1');
                        const deleteBtnDeposito = podeExcluirDeposito
                            ? `<button title="Remover" style="margin-bottom: 5px !important;" class="btn btn-delete btn-sm btn-icon" data-id="${deposito.id ? deposito.id : 'undefined'}" onclick="removerDeposito(this)"><i class="fa fa-trash" aria-hidden="true"></i></button>`
                            : '';

                        const temAnexo = deposito.caminho_anexo && String(deposito.caminho_anexo).trim() !== '';
                        const visualizarBtn = temAnexo
                            ? `<button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarComprovante('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>`
                            : '';

                        const anexarBtn = !temAnexo
                            ? `<button title="Anexar Comprovante" class="btn btn-primary btn-sm btn-icon" onclick="abrirModalAnexarComprovante(${deposito.id}, '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-paperclip" aria-hidden="true"></i></button>`
                            : '';

                        $('#detalhesDepositosRegistrados').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    ${visualizarBtn}
                                    ${anexarBtn}
                                    ${deleteBtnDeposito}
                                </td>
                            </tr>
                        `);
                    });

                    var saldoInicial = parseFloat(response.saldoInicial) || 0;
                    var totalRecebidoEspecie = parseFloat(response.totalRecebidoEspecie) || 0;
                    var totalDevolvidoEspecie = parseFloat(response.totalDevolvidoEspecie) || 0;
                    var totalSaidasDespesas = parseFloat(response.totalSaidasDespesas) || 0;
                    var totalDepositoCaixa = parseFloat(response.totalDepositoCaixa) || 0;
                    var totalSaldoTransportado = parseFloat(response.totalSaldoTransportado) || 0;

                    var totalEmCaixa = saldoInicial + totalRecebidoEspecie - totalDevolvidoEspecie - totalSaidasDespesas - totalDepositoCaixa;
                    if (response.data_caixa === data && response.funcionario === funcionarios) {
                        totalEmCaixa -= totalSaldoTransportado;
                    }

                    $('#total_em_caixa').text(formatCurrency(totalEmCaixa));
                    $('#total_depositos').text(formatCurrency(totalDepositoCaixa));
                    $('#saldo_transportado').text(formatCurrency(totalSaldoTransportado));

                    if (totalEmCaixa === 0) {
                        $('#btnTransportarSaldo').prop('disabled', true);
                        $('#btnAdicionarDeposito').prop('disabled', true);
                    } else {
                        $('#btnTransportarSaldo').prop('disabled', false);
                        $('#btnAdicionarDeposito').prop('disabled', false);
                    }

                    $('#tabelaDepositosRegistrados').DataTable({
                        "language": { "url": "../style/Portuguese-Brasil.json" },
                        "destroy": true,
                        "pageLength": 10
                    });

                    if (typeof callback === 'function') {
                        callback();
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao obter depósitos.', confirmButtonText: 'OK' });
                }
            });
        }

        function carregarDepositosCaixaUnificado(data) {
            $.ajax({
                url: 'listar_depositos_unificado.php',
                type: 'GET',
                data: { data: data },
                dataType: 'json',
                success: function(response) {
                    if (response.error) { alert('Erro: ' + response.error); return; }
                    if (!Array.isArray(response.depositos)) { alert('Erro: Dados de depósitos inválidos'); return; }

                    var depositos = response.depositos;
                    $('#detalhesDepositosCaixaUnificado').empty();
                    depositos.forEach(function(deposito) {
                        var dataCadastroFormatada = formatDateForDisplay2(deposito.data_cadastro);
                        var dataCaixaFormatada = formatDateForDisplay(deposito.data_caixa);
                        const podeExcluirUnificado = (deposito.pode_excluir === true || deposito.pode_excluir === 1 || deposito.pode_excluir === '1');
                        const deleteBtnUnificado = podeExcluirUnificado
                            ? `<button title="Remover" style="margin-bottom: 5px !important;" class="btn btn-delete btn-sm btn-icon" onclick="removerDeposito(${deposito.id})"><i class="fa fa-trash" aria-hidden="true"></i></button>`
                            : '';

                        $('#detalhesDepositosCaixaUnificado').append(`
                            <tr>
                                <td>${deposito.funcionario}</td>
                                <td>${dataCaixaFormatada}</td>
                                <td>${dataCadastroFormatada}</td>
                                <td>${formatCurrency(deposito.valor_do_deposito)}</td>
                                <td>${deposito.tipo_deposito}</td>
                                <td>
                                    <button title="Visualizar" class="btn btn-info btn-sm btn-icon" onclick="visualizarComprovanteCaixaUnificado('${deposito.caminho_anexo}', '${deposito.funcionario}', '${deposito.data_caixa}')"><i class="fa fa-eye" aria-hidden="true"></i></button>
                                    ${deleteBtnUnificado}
                                </td>
                            </tr>
                        `);
                    });

                    $('#tabelaDepositosCaixaUnificado').DataTable({
                        language: { url: "../style/Portuguese-Brasil.json" },
                        destroy: true,
                        pageLength: 10,
                        order: [],
                        autoWidth: false,
                        scrollX: true
                    });
                },
                error: function() { alert('Erro ao obter depósitos.'); }
            });
        }

        function formatDateForDisplay(date) {
            var d = new Date(date + 'T00:00:00');
            var day = ('0' + d.getUTCDate()).slice(-2);
            var month = ('0' + (d.getUTCMonth() + 1)).slice(-2);
            var year = d.getUTCFullYear();
            return `${day}/${month}/${year}`;
        }
        function formatDateForDisplay2(date) {
            var d = new Date(date);
            var day = ('0' + d.getDate()).slice(-2);
            var month = ('0' + (d.getMonth() + 1)).slice(-2);
            var year = d.getFullYear();
            return `${day}/${month}/${year}`;
        }

        function visualizarComprovante(caminho, funcionario, data_caixa) {
            var dir = `anexos/${formatDateForDir(data_caixa)}/${funcionario}/`;
            window.open(dir + caminho, '_blank');
        }
        function visualizarComprovanteCaixaUnificado(caminho, funcionario, data_caixa) {
            var dir = `anexos/${formatDateForDir(data_caixa)}/${funcionario}/`;
            window.open(dir + caminho, '_blank');
        }
        function visualizarAnexoSaida(caminho, funcionario, data_caixa) {
            var dir = `anexos/${data_caixa}/${funcionario}/saidas/`;
            window.open(dir + caminho, '_blank');
        }

        function formatDateForDir(date) {
            var d = new Date(date + 'T00:00:00');
            var day = ('0' + d.getUTCDate()).slice(-2);
            var month = ('0' + (d.getUTCMonth() + 1)).slice(-2);
            var year = d.getUTCFullYear().toString().slice(-2);
            return `${day}-${month}-${year}`;
        }

        function removerDeposito(button) {
            var id = $(button).attr('data-id');
            if (!id || id === 'undefined') { console.error('ID do depósito a ser removido é indefinido.'); return; }

            Swal.fire({
                title: 'Deseja realmente remover este depósito?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, remover!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('remover_deposito.php', { id: id }, function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Depósito removido com sucesso!', confirmButtonText: 'OK' })
                            .then(() => { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro!', text: response.error || 'Erro ao remover depósito.', confirmButtonText: 'OK' });
                        }
                    }, 'json');
                }
            });
        }

        function removerSaida(id) {
            Swal.fire({
                title: 'Deseja realmente remover esta saída/despesa?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, remover!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('update_saida.php', { id: id, status: 'removido' }, function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Saída/Despesa removida com sucesso!', confirmButtonText: 'OK' })
                            .then(() => { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Erro!', text: 'Erro ao remover saída/despesa.', confirmButtonText: 'OK' });
                        }
                    }, 'json');
                }
            });
        }

        function verDepositosCaixa(data) {
            carregarDepositosCaixaUnificado(data);
            $('#verDepositosCaixaModal').modal('show');
            // Ajusta largura das colunas quando o modal terminar de abrir
            $('#verDepositosCaixaModal').one('shown.bs.modal', function () {
                if ($.fn.DataTable.isDataTable('#tabelaDepositosCaixaUnificado')) {
                    $('#tabelaDepositosCaixaUnificado').DataTable().columns.adjust();
                }
            });
        }

        function formatCurrency(value) {
            if (isNaN(value)) return 'R$ 0,00';
            return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',').replace(/\d(?=(\d{3})+,)/g, '$&.');
        }

        function transportarSaldoFecharCaixa() {
            var totalEmCaixa = $('#total_em_caixa').text().replace(/\./g, '').replace(',', '.').replace('R$ ', '');
            var totalEmCaixaFormatado = $('#total_em_caixa').text();

            Swal.fire({
                title: 'Tem certeza?',
                text: `Você realmente deseja fechar o caixa e transportar o saldo de ${totalEmCaixaFormatado} para o caixa seguinte?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, fechar caixa',
                cancelButtonText: 'Não, cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    var dataCaixa = $('#data_caixa_deposito').val();
                    var funcionario = $('#funcionario_deposito').val();

                    $.ajax({
                        url: 'transportar_saldo_fechar_caixa.php',
                        type: 'POST',
                        data: {
                            total_em_caixa: totalEmCaixa,
                            data_caixa: dataCaixa,
                            funcionario: funcionario
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Sucesso!', 'Saldo transportado e caixa fechado com sucesso!', 'success');
                                $('#cadastroDepositoModal').modal('hide');
                                location.reload();
                            } else {
                                Swal.fire('Erro!', 'Erro ao transportar saldo e fechar caixa: ' + response.error, 'error');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            Swal.fire('Erro!', 'Erro ao transportar saldo e fechar caixa: ' + textStatus + ' - ' + errorThrown, 'error');
                        }
                    });
                }
            });
        }

        // NOVO: Fechamento rápido via botão com cadeado dourado no card
        function fecharCaixaRapido(funcionario, data) {
            $('#data_caixa_deposito').val(data);
            $('#funcionario_deposito').val(funcionario);
            carregarDepositos(funcionario, data, function(){
                transportarSaldoFecharCaixa();
            });
        }

        // NOVO: Abrir modal para anexar comprovante posteriormente
        function abrirModalAnexarComprovante(idDeposito, funcionario, data_caixa) {
            $('#deposito_id_anexo').val(idDeposito);
            $('#funcionario_anexo').val(funcionario);
            $('#data_caixa_anexo').val(data_caixa);
            $('#arquivo_comprovante').val('');
            $('#anexarComprovanteModal').modal('show');
        }

        // NOVO: Submit do formulário de anexo de comprovante
        $('#formAnexarComprovante').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            $.ajax({
                url: 'anexar_comprovante_deposito.php',   // endpoint para salvar o anexo (BACK-END)
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Comprovante anexado com sucesso!', confirmButtonText: 'OK' })
                        .then(() => { $('#anexarComprovanteModal').modal('hide'); location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro!', text: response.error || 'Falha ao anexar comprovante.', confirmButtonText: 'OK' });
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Swal.fire({ icon: 'error', title: 'Erro!', text: 'Falha ao anexar comprovante: ' + textStatus + ' - ' + errorThrown, confirmButtonText: 'OK' });
                }
            });
        });

        // Recarregar ao fechar modais
        $('#detalhesModal').on('hidden.bs.modal', function () { location.reload(); });
        $('#cadastroSaidaModal').on('hidden.bs.modal', function () { location.reload(); });
        $('#cadastroDepositoModal').on('hidden.bs.modal', function () { location.reload(); });
        $('#verDepositosCaixaModal').on('hidden.bs.modal', function () { location.reload(); });

        // Abertura do caixa (submit)
        $('#formAbrirCaixa').on('submit', function(e) {
            e.preventDefault();

            var saldoInicial = $('#saldo_inicial').val().replace(/\./g, '').replace(',', '.');

            $.ajax({
                url: 'abrir_caixa.php',
                type: 'POST',
                data: { saldo_inicial: saldoInicial },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Caixa aberto com sucesso!', showConfirmButton: true, confirmButtonText: 'OK' })
                        .then((result) => {
                            if (result.isConfirmed) {
                                $('#abrirCaixaModal').modal('hide');
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao abrir caixa: ' + response.error, showConfirmButton: true, confirmButtonText: 'OK' });
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao abrir caixa: ' + textStatus + ' - ' + errorThrown, showConfirmButton: true, confirmButtonText: 'OK' });
                }
            });
        });
    </script>
    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
