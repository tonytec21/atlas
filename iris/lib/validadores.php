<?php
/**
 * Atlas Iris — normalização e validação de campos extraídos.
 * Cada validador retorna ['valor' => normalizado, 'ok' => true|false|null, 'msg' => string]
 * (ok=null significa "não se aplica / não verificável").
 */

function iris_so_digitos($v) { return preg_replace('~\D~', '', (string)$v); }

function iris_valida_cpf($v)
{
    $d = iris_so_digitos($v);
    if (strlen($d) !== 11) return ['valor' => $v, 'ok' => false, 'msg' => 'CPF deve ter 11 dígitos'];
    $fmt = substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    if (preg_match('~^(\d)\1{10}$~', $d)) return ['valor' => $fmt, 'ok' => false, 'msg' => 'CPF inválido'];
    for ($t = 9; $t < 11; $t++) {
        $s = 0;
        for ($i = 0; $i < $t; $i++) $s += (int)$d[$i] * (($t + 1) - $i);
        $dv = ((10 * $s) % 11) % 10;
        if ((int)$d[$t] !== $dv) return ['valor' => $fmt, 'ok' => false, 'msg' => 'Dígito verificador do CPF não confere'];
    }
    return ['valor' => $fmt, 'ok' => true, 'msg' => 'CPF válido'];
}

function iris_valida_cnpj($v)
{
    $d = iris_so_digitos($v);
    if (strlen($d) !== 14) return ['valor' => $v, 'ok' => false, 'msg' => 'CNPJ deve ter 14 dígitos'];
    $fmt = substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    if (preg_match('~^(\d)\1{13}$~', $d)) return ['valor' => $fmt, 'ok' => false, 'msg' => 'CNPJ inválido'];
    $pesos = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]];
    foreach ([12, 13] as $k => $n) {
        $s = 0;
        for ($i = 0; $i < $n; $i++) $s += (int)$d[$i] * $pesos[$k][$i];
        $r = $s % 11; $dv = $r < 2 ? 0 : 11 - $r;
        if ((int)$d[$n] !== $dv) return ['valor' => $fmt, 'ok' => false, 'msg' => 'Dígito verificador do CNPJ não confere'];
    }
    return ['valor' => $fmt, 'ok' => true, 'msg' => 'CNPJ válido'];
}

function iris_valida_cpf_cnpj($v)
{
    $n = strlen(iris_so_digitos($v));
    if ($n === 11) return iris_valida_cpf($v);
    if ($n === 14) return iris_valida_cnpj($v);
    return ['valor' => $v, 'ok' => false, 'msg' => 'Não parece um CPF (11) nem CNPJ (14 dígitos)'];
}

/** Converte números por extenso em português (inclui grafias antigas: "dous", "cincoenta", "anno"). */
function iris_extenso_para_numero($txt)
{
    $mapa = ['zero' => 0, 'um' => 1, 'uma' => 1, 'primeiro' => 1, 'dois' => 2, 'duas' => 2, 'dous' => 2, 'tres' => 3, 'quatro' => 4,
        'cinco' => 5, 'cinko' => 5, 'seis' => 6, 'sete' => 7, 'oito' => 8, 'nove' => 9, 'dez' => 10, 'onze' => 11, 'doze' => 12,
        'treze' => 13, 'quatorze' => 14, 'catorze' => 14, 'quinze' => 15, 'dezesseis' => 16, 'dezaseis' => 16, 'dezeseis' => 16,
        'dezessete' => 17, 'dezasete' => 17, 'dezesete' => 17, 'dezoito' => 18, 'dezenove' => 19, 'dezanove' => 19,
        'vinte' => 20, 'trinta' => 30, 'quarenta' => 40, 'cinquenta' => 50, 'cincoenta' => 50, 'sessenta' => 60, 'setenta' => 70,
        'oitenta' => 80, 'noventa' => 90, 'cem' => 100, 'cento' => 100, 'duzentos' => 200, 'duzentas' => 200, 'trezentos' => 300,
        'quatrocentos' => 400, 'quinhentos' => 500, 'seiscentos' => 600, 'setecentos' => 700, 'oitocentos' => 800, 'novecentos' => 900];
    $t = iris_sem_acento(mb_strtolower($txt));
    $total = 0; $atual = 0; $achou = false;
    foreach (preg_split('~[^a-z]+~', $t, -1, PREG_SPLIT_NO_EMPTY) as $p) {
        if ($p === 'e') continue;
        if ($p === 'mil') { $total += ($atual ?: 1) * 1000; $atual = 0; $achou = true; continue; }
        if (!isset($mapa[$p])) return null;
        $atual += $mapa[$p]; $achou = true;
    }
    return $achou ? $total + $atual : null;
}

/** "14 de março de 1952", "quatorze de março de mil novecentos e cincoenta e dois", "aos 3 dias do mez de Janeiro do anno de 1901". */
function iris_data_por_extenso($s)
{
    $meses = ['janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8,
              'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12];
    $t = iris_sem_acento(mb_strtolower(trim($s)));
    $t = preg_replace('~^(aos?|em|no dia|dia)\s+~', '', $t);
    if (!preg_match('~^(.+?)\s+(?:dias?\s+)?(?:do\s+)?(?:de\s+)?(?:mes|mez)?\s*(?:de\s+)?(janeiro|fevereiro|marco|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)\s+(?:do\s+)?(?:de\s+)?(?:anno|ano)?\s*(?:de\s+)?(.+)$~', $t, $m)) return null;
    $d = ctype_digit(trim($m[1])) ? (int)$m[1] : iris_extenso_para_numero($m[1]);
    $a = ctype_digit(trim(str_replace('.', '', $m[3]))) ? (int)str_replace('.', '', $m[3]) : iris_extenso_para_numero($m[3]);
    if (!$d || !$a) return null;
    return sprintf('%02d/%02d/%04d', $d, $meses[$m[2]], $a);
}

function iris_valida_data($v)
{
    $s = trim((string)$v);
    if (!preg_match('~\d{1,2}[/.\-]\d{1,2}[/.\-]\d{2,4}|^\d{4}-~', $s) && ($conv = iris_data_por_extenso($s))) {
        $r = iris_valida_data($conv);
        if ($r['ok'] !== false) $r['msg'] = trim('Convertida de "' . $s . '". ' . $r['msg']);
        return $r;
    }
    if (preg_match('~^(\d{4})-(\d{1,2})-(\d{1,2})$~', $s, $m)) { $a = (int)$m[1]; $me = (int)$m[2]; $d = (int)$m[3]; }
    elseif (preg_match('~^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})$~', $s, $m)) {
        $d = (int)$m[1]; $me = (int)$m[2]; $a = (int)$m[3];
        if ($a < 100) return ['valor' => $s, 'ok' => null, 'msg' => 'Ano com 2 dígitos — confira o século'];
    } else return ['valor' => $s, 'ok' => false, 'msg' => 'Data fora do formato DD/MM/AAAA'];
    if (!checkdate($me, $d, $a)) return ['valor' => $s, 'ok' => false, 'msg' => 'Data inexistente'];
    $fmt = sprintf('%02d/%02d/%04d', $d, $me, $a);
    if ($a < 1700) return ['valor' => $fmt, 'ok' => false, 'msg' => 'Ano improvável'];
    if (mktime(0, 0, 0, $me, $d, $a) > time() + 86400 * 366 * 30) return ['valor' => $fmt, 'ok' => false, 'msg' => 'Data muito no futuro'];
    return ['valor' => $fmt, 'ok' => true, 'msg' => ''];
}

function iris_valida_hora($v)
{
    if (preg_match('~^(\d{1,2})\s*[:hH]\s*(\d{2})?~', trim((string)$v), $m)) {
        $h = (int)$m[1]; $mi = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
        if ($h < 24 && $mi < 60) return ['valor' => sprintf('%02d:%02d', $h, $mi), 'ok' => true, 'msg' => ''];
    }
    return ['valor' => $v, 'ok' => false, 'msg' => 'Hora fora do formato HH:MM'];
}

function iris_valida_cep($v)
{
    $d = iris_so_digitos($v);
    if (strlen($d) !== 8) return ['valor' => $v, 'ok' => false, 'msg' => 'CEP deve ter 8 dígitos'];
    return ['valor' => substr($d, 0, 5) . '-' . substr($d, 5), 'ok' => true, 'msg' => ''];
}

function iris_valida_moeda($v)
{
    $s = trim((string)$v);
    $n = preg_replace('~[^\d,.\-]~', '', $s);
    if ($n === '') return ['valor' => $s, 'ok' => false, 'msg' => 'Valor sem número'];
    if (strpos($n, ',') !== false) $n = str_replace(['.', ','], ['', '.'], $n);
    elseif (preg_match('~\.\d{3}(\.|$)~', $n)) $n = str_replace('.', '', $n);
    if (!is_numeric($n)) return ['valor' => $s, 'ok' => false, 'msg' => 'Valor inválido'];
    return ['valor' => 'R$ ' . number_format((float)$n, 2, ',', '.'), 'ok' => true, 'msg' => ''];
}

/**
 * Matrícula das certidões do Registro Civil (Prov. CNJ 3/2009): 32 dígitos
 * CNS(6) acervo(2) serviço(2) ano(4) tipo livro(1) livro(5) folha(3) termo(7) DV(2).
 * Valida o formato e decompõe as partes para conferência com livro/folha/termo.
 */
function iris_valida_matricula_certidao($v)
{
    $d = iris_so_digitos($v);
    if (strlen($d) !== 32) return ['valor' => $v, 'ok' => false, 'msg' => 'A matrícula deve ter 32 dígitos (tem ' . strlen($d) . ')'];
    $partes = iris_matricula_partes($d);
    $fmt = implode(' ', [substr($d, 0, 6), substr($d, 6, 2), substr($d, 8, 2), substr($d, 10, 4), substr($d, 14, 1),
                         substr($d, 15, 5), substr($d, 20, 3), substr($d, 23, 7), substr($d, 30, 2)]);
    $msg = 'CNS ' . $partes['cns'] . ' · ' . $partes['tipo_livro_nome'] . ' · ano ' . $partes['ano']
         . ' · livro ' . $partes['livro'] . ' · fl. ' . $partes['folha'] . ' · termo ' . $partes['termo'];
    $ok = true;
    if ($partes['servico'] !== '55') { $ok = false; $msg = 'Código de serviço ' . $partes['servico'] . ' (esperado 55 — Registro Civil). ' . $msg; }
    return ['valor' => $fmt, 'ok' => $ok, 'msg' => $msg, 'partes' => $partes];
}
function iris_matricula_partes($d)
{
    $tipos = ['1' => 'Livro A (nascimento)', '2' => 'Livro B (casamento)', '3' => 'Livro B-Aux. (casamento religioso)',
              '4' => 'Livro C (óbito)', '5' => 'Livro C-Aux. (natimorto)', '6' => 'Livro D (proclamas)', '7' => 'Livro E'];
    return ['cns' => substr($d, 0, 6), 'acervo' => substr($d, 6, 2), 'servico' => substr($d, 8, 2), 'ano' => substr($d, 10, 4),
            'tipo_livro' => substr($d, 14, 1), 'tipo_livro_nome' => $tipos[substr($d, 14, 1)] ?? ('tipo ' . substr($d, 14, 1)),
            'livro' => ltrim(substr($d, 15, 5), '0') ?: '0', 'folha' => ltrim(substr($d, 20, 3), '0') ?: '0',
            'termo' => ltrim(substr($d, 23, 7), '0') ?: '0', 'dv' => substr($d, 30, 2)];
}

function iris_valida_nome($v)
{
    $s = trim(preg_replace('~\s+~u', ' ', (string)$v));
    if ($s !== '' && preg_match('~\d~', $s)) return ['valor' => $s, 'ok' => false, 'msg' => 'Nome contém números'];
    return ['valor' => $s, 'ok' => null, 'msg' => ''];
}

/** Aplica o validador do tipo do campo. */
function iris_validar_valor($tipo, $valor)
{
    if ($valor === null || (is_string($valor) && trim($valor) === '')) return ['valor' => null, 'ok' => null, 'msg' => ''];
    if (is_array($valor)) return ['valor' => $valor, 'ok' => null, 'msg' => ''];
    $valor = trim((string)$valor);
    switch ($tipo) {
        case 'cpf': return iris_valida_cpf($valor);
        case 'cnpj': return iris_valida_cnpj($valor);
        case 'cpf_cnpj': return iris_valida_cpf_cnpj($valor);
        case 'data': return iris_valida_data($valor);
        case 'hora': return iris_valida_hora($valor);
        case 'cep': return iris_valida_cep($valor);
        case 'moeda': return iris_valida_moeda($valor);
        case 'matricula_certidao': return iris_valida_matricula_certidao($valor);
        case 'nome': return iris_valida_nome($valor);
        default: return ['valor' => $valor, 'ok' => null, 'msg' => ''];
    }
}

/**
 * Conferências cruzadas entre campos (ex.: matrícula × livro/folha/termo).
 * @return array lista de avisos ['campo'=>..., 'msg'=>...]
 */
function iris_conferencias_cruzadas(array $campos)
{
    $avisos = [];
    foreach ($campos as $chave => $c) {
        if (($c['tipo'] ?? '') !== 'matricula_certidao' || empty($c['validacao']['partes'])) continue;
        $p = $c['validacao']['partes'];
        $rot = ['livro' => ['O livro', 'extraído', 'do livro'], 'folha' => ['A folha', 'extraída', 'da folha'], 'termo' => ['O termo', 'extraído', 'do termo']];
        foreach ($rot as $k => $r) {
            if (!isset($campos[$k]['valor']) || $campos[$k]['valor'] === null) continue;
            $ext = ltrim(iris_so_digitos($campos[$k]['valor']), '0');
            if ($ext !== '' && $ext !== $p[$k])
                $avisos[] = ['campo' => $k, 'msg' => $r[0] . ' ' . $r[1] . ' (' . $campos[$k]['valor'] . ') difere ' . $r[2] . ' indicado na matrícula (' . $p[$k] . ').'];
        }
    }
    // Data de registro anterior ao evento (nascimento/óbito/casamento)
    $par = [['data_registro', 'data_nascimento', 'nascimento'], ['data_registro', 'data_obito', 'óbito']];
    foreach ($par as [$reg, $ev, $nome]) {
        $a = $campos[$reg]['valor'] ?? null; $b = $campos[$ev]['valor'] ?? null;
        if (!$a || !$b) continue;
        $da = DateTime::createFromFormat('d/m/Y', $a); $db = DateTime::createFromFormat('d/m/Y', $b);
        if ($da && $db && $da < $db) $avisos[] = ['campo' => $reg, 'msg' => 'A data do registro é anterior à data do ' . $nome . '.'];
    }
    return $avisos;
}
