<?php
/**
 * =====================================================================
 * index.php — Roteador da API do módulo O.S.
 * ---------------------------------------------------------------------
 * ATLAS-OS-API-BUILD: 2026-07-31-v1
 *
 * Base:  https://SEU-SERVIDOR/os/api/v1/...
 *        (sem mod_rewrite: /os/api/index.php/v1/... ou ?rota=/v1/...)
 *
 * ROTAS
 * -----
 *  GET  /v1/ping                          identifica a credencial
 *  GET  /v1/atos/{codigo}                 consulta a tabela de emolumentos
 *  POST /v1/os                            cria O.S.
 *  GET  /v1/os/{n}                        retrato completo da O.S.
 *  GET  /v1/os/{n}/saldo                  posição financeira
 *  GET  /v1/os/{n}/atos                   todos os itens e sua situação
 *  GET  /v1/os/{n}/atos-disponiveis       o que ainda pode ser selado
 *  GET  /v1/os/{n}/liquidacoes            atos já liquidados
 *  GET  /v1/os/{n}/pagamentos             pagamentos lançados
 *  POST /v1/os/{n}/pagamentos             lança pagamento
 *  POST /v1/os/{n}/verificar-saldo        consulta prévia (não altera nada)
 *  POST /v1/os/{n}/liquidar               liquida o ato (parcial ou total)
 * =====================================================================
 */

require_once __DIR__ . '/api_config.php';
require_once __DIR__ . '/api_auth.php';
require_once __DIR__ . '/api_lib.php';
require_once __DIR__ . '/api_liberacao_lib.php';

/* --------------------------------------------------------------------
 * CORS — o token vai em cabeçalho, não há cookie envolvido.
 * ------------------------------------------------------------------ */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Idempotency-Key, X-Api-Token');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* --------------------------------------------------------------------
 * Descoberta da rota
 * ------------------------------------------------------------------ */

function api_rota_atual(): string
{
    /* 1. PATH_INFO (index.php/v1/...) */
    $r = $_SERVER['PATH_INFO'] ?? '';

    /* 2. ?rota=/v1/... — alternativa para servidor sem mod_rewrite */
    if ($r === '' && !empty($_GET['rota'])) {
        $r = (string) $_GET['rota'];
    }

    /* 3. URI, descontando o diretório onde a API está instalada */
    if ($r === '') {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

        if ($base !== '' && strpos($uri, $base) === 0) {
            $r = substr($uri, strlen($base));
        } else {
            $r = $uri;
        }
        $r = preg_replace('#^/index\.php#', '', $r);
    }

    $r = '/' . trim((string) $r, '/');
    return $r === '/' ? '/' : rtrim($r, '/');
}

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rota   = api_rota_atual();

api_log_iniciar($metodo, $rota);

/* --------------------------------------------------------------------
 * Raiz: cartão de visita, sem autenticação
 * ------------------------------------------------------------------ */
if ($rota === '/' || $rota === '/v1') {
    api_ok([
        'api'       => 'Atlas O.S.',
        'versao'    => ATLAS_OS_API,
        'documento' => 'DOCUMENTACAO.md',
        'auth'      => 'Authorization: Bearer <token>',
        'rotas'     => [
            'GET  /v1/ping',
            'GET  /v1/atos/{codigo}',
            'POST /v1/os',
            'GET  /v1/os/{numero}',
            'GET  /v1/os/{numero}/saldo',
            'GET  /v1/os/{numero}/atos',
            'GET  /v1/os/{numero}/atos-disponiveis',
            'GET  /v1/os/{numero}/liquidacoes',
            'GET  /v1/os/{numero}/pagamentos',
            'POST /v1/os/{numero}/pagamentos',
            'POST /v1/os/{numero}/verificar-saldo',
            'POST /v1/os/{numero}/liquidar',
        ],
    ]);
}

/* --------------------------------------------------------------------
 * Daqui para baixo, tudo exige credencial homologada
 * ------------------------------------------------------------------ */
$sistema = api_autenticar();

/** Confere o verbo da rota. */
$exigirMetodo = static function (string $esperado) use ($metodo, $rota): void {
    if ($metodo !== $esperado) {
        api_erro(
            'metodo_invalido',
            'A rota ' . $rota . ' aceita apenas ' . $esperado . '.',
            405,
            ['metodo_recebido' => $metodo, 'metodo_esperado' => $esperado]
        );
    }
};

/** Resolve a O.S. da URL, com o isolamento de ambiente aplicado. */
$resolverOs = static function (string $n): int {
    $osId = (int) $n;
    if ($osId <= 0) {
        api_erro('os_invalida', 'Número de O.S. inválido.', 422, ['recebido' => $n]);
    }
    api_marcar_os($osId);
    api_autorizar_os($osId);
    return $osId;
};

try {

    /* ---------------- ping ---------------- */
    if ($rota === '/v1/ping') {
        $exigirMetodo('GET');
        api_ok([
            'pong'      => true,
            'sistema'   => $sistema['nome'],
            'client_id' => $sistema['client_id'],
            'ambiente'  => $sistema['ambiente'],
            'status'    => $sistema['status'],
            'escopos'   => array_values(array_filter(array_map('trim', explode(',', $sistema['escopos'])))),
            'servidor'  => ['data_hora' => date('c'), 'fuso' => date_default_timezone_get()],
        ]);
    }

    /* ---------------- consulta de ato ---------------- */
    if (preg_match('#^/v1/atos/(.+)$#', $rota, $m)) {
        $exigirMetodo('GET');
        api_exigir_escopo('os:ler');

        $codigo = urldecode($m[1]);
        $ato = api_ato_buscar($codigo);

        if (!$ato) {
            api_erro('ato_nao_encontrado', 'Ato "' . $codigo . '" não consta na tabela vigente.', 404, ['ato' => $codigo]);
        }

        api_ok([
            'ato'         => $ato['ATO'],
            'descricao'   => $ato['DESCRICAO'],
            'emolumentos' => api_dinheiro($ato['EMOLUMENTOS']),
            'ferc'        => api_dinheiro($ato['FERC']),
            'fadep'       => api_dinheiro($ato['FADEP']),
            'femp'        => api_dinheiro($ato['FEMP']),
            'ferrfis'     => api_dinheiro($ato['FERRFIS'] ?? 0),
            'total'       => api_dinheiro($ato['TOTAL']),
        ]);
    }

    /* ---------------- criar O.S. ---------------- */
    if ($rota === '/v1/os') {
        $exigirMetodo('POST');
        api_exigir_escopo('os:criar');

        $corpo = api_corpo();
        api_idempotencia_verificar($rota, $corpo);

        $dados = api_os_criar($corpo);
        api_marcar_os($dados['os']['numero']);

        api_idempotencia_guardar($rota, $corpo, 201, ['sucesso' => true, 'dados' => $dados]);
        api_ok($dados, 201);
    }

    /* ---------------- rotas por O.S. ---------------- */
    if (preg_match('#^/v1/os/(\d+)(/[a-z\-]+)?$#i', $rota, $m)) {
        $osId  = $resolverOs($m[1]);
        $sufixo = strtolower($m[2] ?? '');

        /* GET /v1/os/{n} */
        if ($sufixo === '') {
            $exigirMetodo('GET');
            api_exigir_escopo('os:ler');
            api_ok(api_os_retrato($osId));
        }

        /* GET /v1/os/{n}/saldo */
        if ($sufixo === '/saldo') {
            $exigirMetodo('GET');
            api_exigir_escopo('os:ler');

            $os = api_os_buscar($osId);
            api_ok([
                'os'              => $osId,
                'cliente'         => $os['cliente'],
                'cancelada'       => api_os_cancelada($os),
                /* LEGADO: base única da O.S., preenchida só nas O.S. antigas.
                   A base que define a faixa do selo está em cada item —
                   use GET /v1/os/{n}/atos-disponiveis. */
                'base_de_calculo_os' => api_base_calculo_os($os),
                'financeiro'      => api_os_saldo($osId, $os),
            ]);
        }

        /* GET /v1/os/{n}/atos  e  /atos-disponiveis */
        if ($sufixo === '/atos' || $sufixo === '/atos-disponiveis') {
            $exigirMetodo('GET');
            api_exigir_escopo('os:ler');

            $os    = api_os_buscar($osId);
            $itens = api_os_itens($osId);
            $saldo = api_os_saldo($osId, $os);

            if ($sufixo === '/atos-disponiveis') {
                $itens = array_values(array_filter($itens, static fn($i) => $i['quantidade_disponivel'] > 0));

                /* Marca, item a item, se o saldo cobre a liquidação —
                   é a resposta direta ao "posso selar isto?". */
                $acumulado = $saldo['saldo_liquidacao'];
                foreach ($itens as &$i) {
                    $exige = !$i['isento'] && !$saldo['isenta_de_pagamento'] && $i['valor_unitario_liquidacao'] > 0;
                    $i['exige_saldo']       = $exige;
                    $i['saldo_cobre_uma_unidade'] = !$exige || ($acumulado + 0.001) >= $i['valor_unitario_liquidacao'];
                    $i['saldo_cobre_o_restante']  = !$exige || ($acumulado + 0.001) >= $i['valor_restante_liquidacao'];

                    /* Ato de faixa sem base informada: não dá para selar,
                       porque o selo é escolhido pela faixa do valor declarado. */
                    $i['pronto_para_selagem'] = $i['saldo_cobre_uma_unidade']
                        && (!$i['exige_base_de_calculo'] || $i['base_de_calculo'] !== null);
                }
                unset($i);
            }

            api_ok([
                'os'         => $osId,
                'cancelada'  => api_os_cancelada($os),
                /* LEGADO: base única da O.S. A base de cada ato — a que
                   escolhe a faixa do selo — vem em `itens[].base_de_calculo`. */
                'base_de_calculo_os' => api_base_calculo_os($os),
                'financeiro' => $saldo,
                'quantidade' => count($itens),
                'itens'      => $itens,
            ]);
        }

        /* GET /v1/os/{n}/liquidacoes */
        if ($sufixo === '/liquidacoes') {
            $exigirMetodo('GET');
            api_exigir_escopo('os:ler');

            $liq = api_os_liquidacoes($osId);
            api_ok([
                'os'         => $osId,
                'quantidade' => count($liq),
                'total'      => api_dinheiro(array_sum(array_column($liq, 'total'))),
                'liquidacoes'=> $liq,
                'selos'      => api_os_selos($osId),
            ]);
        }

        /* GET|POST /v1/os/{n}/pagamentos */
        if ($sufixo === '/pagamentos') {
            api_exigir_escopo($metodo === 'POST' ? 'pagamento:criar' : 'os:ler');

            if ($metodo === 'GET') {
                $pg = api_os_pagamentos($osId);
                api_ok([
                    'os'         => $osId,
                    'quantidade' => count($pg),
                    'pagamentos' => $pg,
                    'financeiro' => api_os_saldo($osId),
                ]);
            }

            $exigirMetodo('POST');
            $corpo = api_corpo();
            api_idempotencia_verificar($rota, $corpo);

            $forma = trim((string) api_exigir($corpo, 'forma_de_pagamento'));
            $valor = api_valor(api_campo($corpo, 'valor', 0));

            $dados = api_pagamento_criar($osId, $valor, $forma, [
                'operador' => $corpo['operador'] ?? null,
            ]);

            api_idempotencia_guardar($rota, $corpo, 201, ['sucesso' => true, 'dados' => $dados]);
            api_ok($dados, 201);
        }

        /* POST /v1/os/{n}/verificar-saldo */
        if ($sufixo === '/verificar-saldo') {
            $exigirMetodo('POST');
            api_exigir_escopo('os:ler');

            $corpo = api_corpo();
            $itemId = (int) api_exigir($corpo, 'item_id');
            $qtd    = (int) api_campo($corpo, 'quantidade', 1);

            api_ok(api_verificar_liquidacao($osId, $itemId, max(1, $qtd)));
        }

        /* GET /v1/os/{n}/liberacao — resumo do que pode ser desfeito.
           Rota não documentada no manual público do integrador. */
        if ($sufixo === '/liberacao') {
            $exigirMetodo('GET');
            api_exigir_escopo('ato:liberar');
            api_ok(api_liberacao_resumo($osId));
        }

        /* POST /v1/os/{n}/liberar — desfaz a liquidação DE HOJE. */
        if ($sufixo === '/liberar') {
            $exigirMetodo('POST');
            api_exigir_escopo('ato:liberar');

            $corpo = api_corpo();
            api_idempotencia_verificar($rota, $corpo);

            $dados = api_liberar($osId, [
                'liquidacao_id' => $corpo['liquidacao_id'] ?? null,
                'item_id'       => $corpo['item_id'] ?? null,
                'motivo'        => $corpo['motivo'] ?? null,
                'operador'      => $corpo['operador'] ?? null,
            ]);

            api_idempotencia_guardar($rota, $corpo, 200, ['sucesso' => true, 'dados' => $dados]);
            api_ok($dados);
        }

        /* POST /v1/os/{n}/liquidar */
        if ($sufixo === '/liquidar') {
            $exigirMetodo('POST');
            api_exigir_escopo('ato:liquidar');

            $corpo = api_corpo();
            api_idempotencia_verificar($rota, $corpo);

            $itemId = (int) api_exigir($corpo, 'item_id');
            $qtd    = (int) api_campo($corpo, 'quantidade', 1);

            if ($qtd < 1) {
                api_erro('quantidade_invalida', 'A quantidade a liquidar deve ser pelo menos 1.', 422);
            }

            $dados = api_liquidar($osId, $itemId, $qtd, [
                'selo'      => $corpo['selo'] ?? null,
                'protocolo' => $corpo['protocolo'] ?? null,
                'operador'  => $corpo['operador'] ?? null,
            ]);

            api_idempotencia_guardar($rota, $corpo, 200, ['sucesso' => true, 'dados' => $dados]);
            api_ok($dados);
        }
    }

    api_erro('rota_nao_encontrada', 'Rota não reconhecida: ' . $metodo . ' ' . $rota, 404);

} catch (PDOException $e) {
    error_log('[api][pdo] ' . $e->getMessage());
    api_erro('erro_banco', 'Falha ao acessar o banco de dados.', 500);
} catch (Throwable $e) {
    error_log('[api] ' . $e->getMessage());
    api_erro('erro_interno', 'Falha ao processar a requisição.', 500);
}
