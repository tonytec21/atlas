<?php
/**
 * ATLAS O.S. — Tradução das mensagens de erro da NFS-e
 * ---------------------------------------------------------------------
 * ATLAS-NFSE-BUILD: 2026-08-17-mensagens-tratadas
 *
 * A SEFIN devolve JSON. O balcão não deve ver JSON.
 *
 * Este arquivo extrai o que interessa da resposta bruta — código, descrição
 * oficial, Id da DPS — e devolve três coisas:
 *
 *   titulo  : uma frase curta, em português de gente, dizendo o que houve
 *   acao    : o que fazer para resolver, quando há algo a fazer
 *   detalhe : o texto original, para quem precisar (fica escondido)
 *
 * A descrição oficial é preservada no detalhe: em caso de fiscalização ou
 * chamado, é ela que vale.
 */

if (!function_exists('nfse_erro_catalogo')) {

/**
 * Códigos que aparecem no dia a dia do cartório, com a orientação
 * correspondente. A lista completa da SEFIN tem centenas de itens; aqui
 * ficam os que realmente ocorrem, e o resto cai no tratamento genérico.
 */
function nfse_erro_catalogo(): array
{
    return [
        // --- Tomador -------------------------------------------------
        'E0206' => [
            'titulo' => 'O CPF ou CNPJ do tomador está inválido.',
            'acao'   => 'Corrija o documento do apresentante no cadastro da O.S. e emita novamente. '
                      . 'Se o cliente não quiser se identificar, a nota também pode ser emitida sem tomador.',
        ],
        'E0207' => [
            'titulo' => 'O CPF do tomador não foi encontrado no cadastro da Receita Federal.',
            'acao'   => 'Confira o número junto ao cliente. Pode ser digitação, ou um CPF suspenso ou cancelado.',
        ],
        'E0208' => [
            'titulo' => 'O CNPJ do tomador não foi encontrado no cadastro da Receita Federal.',
            'acao'   => 'Confira o número junto ao cliente.',
        ],
        'E0209' => [
            'titulo' => 'O nome do tomador não confere com o cadastro da Receita Federal.',
            'acao'   => 'Ajuste o nome do apresentante para como consta no CPF/CNPJ.',
        ],

        // --- Prestador / credenciamento ------------------------------
        'E0128' => [
            'titulo' => 'O nome do prestador não é aceito pelo Ambiente Nacional.',
            'acao'   => 'Confira a razão social na configuração do módulo.',
        ],
        'E0001' => [
            'titulo' => 'O emitente não está autorizado a emitir neste município.',
            'acao'   => 'Verifique o credenciamento da serventia junto à prefeitura.',
        ],

        // --- Documento / duplicidade ---------------------------------
        'E0003' => [
            'titulo' => 'Já existe NFS-e para esta declaração.',
            'acao'   => 'Use "Sincronizar" para trazer a nota que já foi emitida.',
        ],
        'E0004' => [
            'titulo' => 'A numeração desta DPS já foi utilizada.',
            'acao'   => 'Use "Sincronizar" para localizar a nota correspondente.',
        ],

        // --- Tributos ------------------------------------------------
        'E0166' => [
            'titulo' => 'Falta informação sobre o regime de tributação.',
            'acao'   => 'Confira o regime especial e a opção pelo Simples Nacional na configuração.',
        ],
        'E0170' => [
            'titulo' => 'O código de tributação do serviço não é aceito para este município.',
            'acao'   => 'Confira o código de tributação na configuração do módulo.',
        ],

        // --- Genéricos da SEFIN --------------------------------------
        'E999'  => [
            'titulo' => 'O Ambiente Nacional recusou a declaração sem informar o motivo.',
            'acao'   => 'Tente novamente em alguns minutos. Persistindo, registre o Id da DPS para consulta.',
        ],
    ];
}

/**
 * Interpreta a mensagem gravada em `nfse_notas.mensagem`.
 *
 * @return array{codigo:?string, titulo:string, acao:string, detalhe:string, tipo:string}
 *         tipo: 'dado' (algo a corrigir aqui), 'ambiente' (fora do nosso alcance),
 *               'rede', 'desconhecido'
 */
function nfse_erro_traduzir(?string $bruto): array
{
    $bruto = trim((string) $bruto);

    $r = [
        'codigo'  => null,
        'titulo'  => 'Não foi possível emitir a NFS-e.',
        'acao'    => '',
        'detalhe' => $bruto,
        'tipo'    => 'desconhecido',
    ];

    if ($bruto === '') {
        return $r;
    }

    // 1) Código catalogado — o caso mais informativo.
    if (preg_match('/"?Codigo"?\s*:\s*"([^"]+)"/i', $bruto, $m)) {
        $codigo = strtoupper(trim($m[1]));
        $r['codigo'] = $codigo;
        $r['tipo']   = 'dado';

        $catalogo = nfse_erro_catalogo();
        if (isset($catalogo[$codigo])) {
            $r['titulo'] = $catalogo[$codigo]['titulo'];
            $r['acao']   = $catalogo[$codigo]['acao'];
        } elseif (preg_match('/"?Descricao"?\s*:\s*"([^"]+)"/i', $bruto, $d)) {
            // Sem tradução própria: usa a descrição oficial, que já é legível.
            $r['titulo'] = rtrim(trim($d[1]), '.') . '.';
        }

        if ($codigo === 'E999') {
            $r['tipo'] = 'ambiente';
        }

        return $r;
    }

    // 2) Indisponibilidade do servidor web (503 e afins).
    if (stripos($bruto, 'Service Unavailable') !== false || preg_match('/\bHTTP 50[234]\b/', $bruto)) {
        $r['titulo'] = 'O Ambiente Nacional estava indisponível no momento do envio.';
        $r['acao']   = 'A declaração não chegou a ser processada. Tente novamente em alguns minutos.';
        $r['tipo']   = 'ambiente';
        return $r;
    }

    // 3) Erro interno da aplicação da SEFIN.
    if (stripos($bruto, 'An error has occurred') !== false || preg_match('/\bHTTP 500\b/', $bruto)) {
        $r['titulo'] = 'O Ambiente Nacional apresentou uma falha interna ao processar a declaração.';
        $r['acao']   = 'Tente novamente em alguns minutos. Se persistir, use "Sincronizar" antes de reemitir, '
                     . 'para confirmar que a nota não foi gerada lá.';
        $r['tipo']   = 'ambiente';
        return $r;
    }

    // 4) Rede.
    if (stripos($bruto, 'cURL') !== false || stripos($bruto, 'resolve host') !== false
        || stripos($bruto, 'timed out') !== false || stripos($bruto, 'Falha de rede') !== false) {
        $r['titulo'] = 'Não foi possível se comunicar com o Ambiente Nacional.';
        $r['acao']   = 'Verifique a conexão com a internet e tente novamente.';
        $r['tipo']   = 'rede';
        return $r;
    }

    // 5) Certificado.
    if (stripos($bruto, 'certificad') !== false || stripos($bruto, 'pkcs12') !== false) {
        $r['titulo'] = 'Houve um problema com o certificado digital.';
        $r['acao']   = 'Confira o certificado A1 e a senha na configuração do módulo.';
        $r['tipo']   = 'dado';
        return $r;
    }

    // 6) Sem padrão reconhecido: primeira frase útil do texto, sem JSON.
    $limpo = preg_replace('/\{.*\}/s', '', $bruto);
    $limpo = trim(preg_replace('/\s+/', ' ', (string) $limpo));
    if ($limpo !== '' && mb_strlen($limpo) < 200) {
        $r['titulo'] = rtrim($limpo, '.') . '.';
    }

    return $r;
}

/**
 * Id da DPS mencionado na resposta, quando houver. Útil para chamado.
 */
function nfse_erro_iddps(?string $bruto): ?string
{
    if (preg_match('/"?idDPS"?\s*:\s*"([^"]+)"/i', (string) $bruto, $m)) {
        return $m[1];
    }
    return null;
}

}
