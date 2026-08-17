<?php
/**
 * documento_validacao.php — CPF/CNPJ do apresentante
 * ---------------------------------------------------------------------
 * ATLAS-OS-BUILD: 2026-08-17-validacao-documento
 *
 * A validação existia só no JavaScript da tela de criação. Isso deixava
 * duas portas abertas:
 *
 *   1. a edição da O.S., que não validava nada;
 *   2. qualquer requisição que não passe pela tela — o navegador é do
 *      usuário, e validação de front-end não é barreira.
 *
 * Um CPF inválido gravado aqui vira rejeição E0206 na hora de emitir a
 * NFS-e, quando a O.S. já está liquidada e o cliente já foi embora. Por
 * isso a checagem passa a existir também no servidor, que é onde de fato
 * protege.
 */

if (!function_exists('doc_apenas_digitos')) {

function doc_apenas_digitos($valor): string
{
    return preg_replace('/\D/', '', (string) $valor);
}

/**
 * Dígitos verificadores do CPF.
 */
function doc_cpf_valido($cpf): bool
{
    $cpf = doc_apenas_digitos($cpf);

    if (strlen($cpf) !== 11) {
        return false;
    }

    // Sequências repetidas (000.000.000-00, 111.111.111-11 …) passam no
    // cálculo dos dígitos, então precisam ser barradas à parte.
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

/**
 * Dígitos verificadores do CNPJ.
 */
function doc_cnpj_valido($cnpj): bool
{
    $cnpj = doc_apenas_digitos($cnpj);

    if (strlen($cnpj) !== 14) {
        return false;
    }
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    foreach ([[12, $pesos1], [13, $pesos2]] as [$pos, $pesos]) {
        $soma = 0;
        for ($i = 0; $i < $pos; $i++) {
            $soma += (int) $cnpj[$i] * $pesos[$i];
        }
        $resto = $soma % 11;
        $digito = ($resto < 2) ? 0 : 11 - $resto;
        if ((int) $cnpj[$pos] !== $digito) {
            return false;
        }
    }

    return true;
}

/**
 * Formata para exibição, conforme o tamanho.
 */
function doc_formatar($valor): string
{
    $d = doc_apenas_digitos($valor);

    if (strlen($d) === 11) {
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
    if (strlen($d) === 14) {
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/'
             . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    }

    return trim((string) $valor);
}

/**
 * Valida o documento do apresentante.
 *
 * O campo é opcional: vazio passa, porque nem toda O.S. tem apresentante
 * identificado. O que não pode é um número que exista mas esteja errado.
 *
 * @return array{ok:bool, valor:string, tipo:string, erro:string}
 *         tipo: 'cpf', 'cnpj' ou 'vazio'
 */
function doc_validar_apresentante($valor): array
{
    $bruto = trim((string) $valor);
    $d = doc_apenas_digitos($bruto);

    if ($d === '') {
        return ['ok' => true, 'valor' => '', 'tipo' => 'vazio', 'erro' => ''];
    }

    if (strlen($d) === 11) {
        return doc_cpf_valido($d)
            ? ['ok' => true, 'valor' => doc_formatar($d), 'tipo' => 'cpf', 'erro' => '']
            : ['ok' => false, 'valor' => $bruto, 'tipo' => 'cpf',
               'erro' => 'O CPF do apresentante é inválido — confira os números digitados.'];
    }

    if (strlen($d) === 14) {
        return doc_cnpj_valido($d)
            ? ['ok' => true, 'valor' => doc_formatar($d), 'tipo' => 'cnpj', 'erro' => '']
            : ['ok' => false, 'valor' => $bruto, 'tipo' => 'cnpj',
               'erro' => 'O CNPJ do apresentante é inválido — confira os números digitados.'];
    }

    return [
        'ok'    => false,
        'valor' => $bruto,
        'tipo'  => 'indefinido',
        'erro'  => 'O documento do apresentante deve ter 11 dígitos (CPF) ou 14 (CNPJ). '
                 . 'Foram informados ' . strlen($d) . '.',
    ];
}

}
