<?php
/**
 * ============================================================================
 *  CRC — geração do XML de carga e validação contra o XSD oficial
 *
 *  Este arquivo é a ÚNICA fonte da estrutura do XML. É usado:
 *   - pelos geradores de carga (carga_crc/gerar_carga*.php);
 *   - pela validação no cadastro/edição (cada registro é montado exatamente
 *     como sairá na carga e validado contra validar_xml/catalogo-crc.xsd);
 *   - pela auditoria de pendências e pela pré-validação da exportação.
 *
 *  A ordem e o conteúdo das tags reproduzem os geradores anteriores.
 * ============================================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const CRC_XSD_PATH = IX_ROOT . '/validar_xml/catalogo-crc.xsd';

/* ======================================================================== */
/*  Infra de montagem                                                       */
/* ======================================================================== */

/** Cria elemento com texto. $forceText = gera <X></X> mesmo vazio (padrão do casamento). */
function crc_el(DOMDocument $doc, DOMElement $parent, string $name, $value = '', bool $forceText = false): DOMElement
{
    $el = $doc->createElement($name);
    $value = (string)$value;
    if ($value !== '' || $forceText) {
        $el->appendChild($doc->createTextNode($value));
    }
    $parent->appendChild($el);
    return $el;
}

function crc_meta(string $tipo): array
{
    switch ($tipo) {
        case 'nascimento': return ['versao' => '2.6', 'mov' => 'MOVIMENTONASCIMENTOTN', 'reg' => 'REGISTRONASCIMENTOINCLUSAO', 'cns_vazio' => 'NAO INFORMADO', 'arquivo' => 'carga_nascimento.xml'];
        case 'casamento':  return ['versao' => '2.7', 'mov' => 'MOVIMENTOCASAMENTOTC', 'reg' => 'REGISTROCASAMENTOINCLUSAO', 'cns_vazio' => '', 'arquivo' => 'carga_casamento.xml'];
        case 'obito':      return ['versao' => '2.7', 'mov' => 'MOVIMENTOOBITOTO', 'reg' => 'REGISTROOBITOINCLUSAO', 'cns_vazio' => '000000', 'arquivo' => 'carga_obito.xml'];
    }
    throw new InvalidArgumentException('Tipo de carga inválido.');
}

/** Documento CARGAREGISTROS com cabeçalho e o contêiner de movimento. */
function crc_document(string $tipo): array
{
    $meta = crc_meta($tipo);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $root = $doc->createElement('CARGAREGISTROS');
    $doc->appendChild($root);
    $force = ($tipo === 'casamento');
    $cns = ix_cns();
    crc_el($doc, $root, 'VERSAO', $meta['versao'], $force);
    crc_el($doc, $root, 'ACAO', 'CARGA', $force);
    crc_el($doc, $root, 'CNS', $cns !== '' ? $cns : $meta['cns_vazio'], $force);
    $mov = $doc->createElement($meta['mov']);
    $root->appendChild($mov);
    return [$doc, $mov];
}

function crc_append(string $tipo, DOMDocument $doc, DOMElement $mov, array $row): DOMElement
{
    switch ($tipo) {
        case 'nascimento': return crc_append_nascimento($doc, $mov, $row);
        case 'casamento':  return crc_append_casamento($doc, $mov, $row);
        case 'obito':      return crc_append_obito($doc, $mov, $row);
    }
    throw new InvalidArgumentException('Tipo de carga inválido.');
}

/** Monta o documento completo para uma lista de registros (linhas do banco). */
function crc_build(string $tipo, array $rows): DOMDocument
{
    [$doc, $mov] = crc_document($tipo);
    foreach ($rows as $row) crc_append($tipo, $doc, $mov, $row);
    return $doc;
}

function crc_txt($v): string { return ix_decode(isset($v) ? (string)$v : ''); }

/* ======================================================================== */
/*  NASCIMENTO (mesma estrutura de carga_crc/gerar_carga.php)               */
/* ======================================================================== */
function crc_append_nascimento(DOMDocument $doc, DOMElement $mov, array $row): DOMElement
{
    $r = $doc->createElement('REGISTRONASCIMENTOINCLUSAO');
    $mov->appendChild($r);
    $id = (string)($row['id'] ?? '');

    crc_el($doc, $r, 'INDICEREGISTRO', $id !== '' ? $id : 'NAO INFORMADO');
    crc_el($doc, $r, 'NOMEREGISTRADO', isset($row['nome_registrado']) ? crc_txt($row['nome_registrado']) : 'NAO INFORMADO');
    crc_el($doc, $r, 'CPFREGISTRADO', crc_txt($row['cpf_registrado'] ?? ''));
    crc_el($doc, $r, 'MATRICULA', isset($row['matricula']) ? crc_txt($row['matricula']) : 'NAO INFORMADO');
    crc_el($doc, $r, 'DATAREGISTRO', ix_br_date($row['data_registro'] ?? null));
    crc_el($doc, $r, 'DNV', crc_txt($row['dnv'] ?? ''));
    crc_el($doc, $r, 'DATANASCIMENTO', ix_br_date($row['data_nascimento'] ?? null));
    crc_el($doc, $r, 'HORANASCIMENTO', crc_txt($row['hora_nascimento'] ?? ''));
    crc_el($doc, $r, 'LOCALNASCIMENTO', crc_txt($row['local_nascimento'] ?? 'IGNORADO'));
    crc_el($doc, $r, 'SEXO', isset($row['sexo']) ? crc_txt($row['sexo']) : 'NAO INFORMADO');
    crc_el($doc, $r, 'POSSUIGEMEOS', crc_txt($row['possui_gemeos'] ?? 'N'));
    crc_el($doc, $r, 'NUMEROGEMEOS', crc_txt($row['numero_gemeos'] ?? ''));
    crc_el($doc, $r, 'CODIGOIBGEMUNNASCIMENTO', isset($row['ibge_naturalidade']) ? crc_txt($row['ibge_naturalidade']) : 'NAO INFORMADO');
    crc_el($doc, $r, 'PAISNASCIMENTO', crc_txt($row['pais_nascimento'] ?? ''));
    crc_el($doc, $r, 'NACIONALIDADE', crc_txt($row['nacionalidade'] ?? ''));
    crc_el($doc, $r, 'TEXTONACIONALIDADEESTRANGEIRO', crc_txt($row['texto_nacionalidade_estrangeiro'] ?? ''));

    $filiacao = function (string $idx, string $nome, string $sexo, string $suf) use ($doc, $r, $row, $id) {
        $f = $doc->createElement('FILIACAONASCIMENTO');
        $r->appendChild($f);
        crc_el($doc, $f, 'INDICEREGISTRO', $id !== '' ? $id : 'NAO INFORMADO');
        crc_el($doc, $f, 'INDICEFILIACAO', $idx);
        crc_el($doc, $f, 'NOME', $nome);
        crc_el($doc, $f, 'SEXO', $sexo);
        crc_el($doc, $f, 'CPF', crc_txt($row['cpf_' . $suf] ?? ''));
        crc_el($doc, $f, 'DATANASCIMENTO', crc_txt($row['data_nascimento_' . $suf] ?? ''));
        crc_el($doc, $f, 'IDADE', crc_txt($row['idade_' . $suf] ?? ''));
        crc_el($doc, $f, 'IDADE_DIAS_MESES_ANOS', crc_txt($row['idade_dias_meses_anos_' . $suf] ?? ''));
        crc_el($doc, $f, 'CODIGOIBGEMUNLOGRADOURO', crc_txt($row['codigo_ibge_mun_logradouro_' . $suf] ?? ''));
        crc_el($doc, $f, 'LOGRADOURO', crc_txt($row['logradouro_' . $suf] ?? ''));
        crc_el($doc, $f, 'NUMEROLOGRADOURO', crc_txt($row['numero_logradouro_' . $suf] ?? ''));
        crc_el($doc, $f, 'COMPLEMENTOLOGRADOURO', crc_txt($row['complemento_logradouro_' . $suf] ?? ''));
        crc_el($doc, $f, 'BAIRRO', crc_txt($row['bairro_' . $suf] ?? ''));
        crc_el($doc, $f, 'NACIONALIDADE', crc_txt($row['nacionalidade_' . $suf] ?? ''));
        crc_el($doc, $f, 'DOMICILIOESTRANGEIRO', crc_txt($row['domicilio_estrangeiro_' . $suf] ?? ''));
        crc_el($doc, $f, 'CODIGOIBGEMUNNATURALIDADE', crc_txt($row['codigo_ibge_mun_naturalidade_' . $suf] ?? ''));
        crc_el($doc, $f, 'TEXTOLIVREMUNICIPIONAT', crc_txt($row['texto_livre_municipio_nat_' . $suf] ?? 'NAO INFORMADO'));
        crc_el($doc, $f, 'CODIGOOCUPACAOSDC', crc_txt($row['codigo_ocupacao_sdc_' . $suf] ?? ''));
    };

    // Pai: somente quando informado
    if (!empty($row['nome_pai'])) {
        $filiacao('1', crc_txt($row['nome_pai']), 'M', 'pai');
    }
    // Mãe: sempre
    $filiacao('2', isset($row['nome_mae']) ? crc_txt($row['nome_mae']) : 'NAO INFORMADO', 'F', 'mae');

    crc_el($doc, $r, 'OBSERVACOES');
    return $r;
}

/* ======================================================================== */
/*  CASAMENTO (mesma estrutura de carga_crc/gerar_carga_casamento.php)      */
/* ======================================================================== */
function crc_append_casamento(DOMDocument $doc, DOMElement $mov, array $row): DOMElement
{
    $r = $doc->createElement('REGISTROCASAMENTOINCLUSAO');
    $mov->appendChild($r);
    $t = fn($n, $v = '') => crc_el($doc, $r, $n, $v, true);

    $t('INDICEREGISTRO', (string)($row['id'] ?? ''));

    foreach (['1', '2'] as $n) {
        $t('NOMECONJUGE' . $n, crc_txt($row['conjuge' . $n . '_nome'] ?? ''));
        $t('NOVONOMECONJUGE' . $n, crc_txt($row['conjuge' . $n . '_nome_casado'] ?? ''));
        $t('CPFCONJUGE' . $n);
        $t('SEXOCONJUGE' . $n, crc_txt($row['conjuge' . $n . '_sexo'] ?? ''));
        $t('DATANASCIMENTOCONJUGE' . $n);
        $t('NOMEPAICONJUGE' . $n);
        $t('SEXOPAICONJUGE' . $n);
        $t('NOMEMAECONJUGE' . $n);
        $t('SEXOMAECONJUGE' . $n);
        $t('CODIGOOCUPACAOSDCCONJUGE' . $n);
        $t('PAISNASCIMENTOCONJUGE' . $n);
        $t('NACIONALIDADECONJUGE' . $n);
        $t('CODIGOIBGEMUNNATCONJUGE' . $n);
        $t('TEXTOLIVREMUNNATCONJUGE' . $n);
        $t('CODIGOIBGEMUNLOGRADOURO' . $n);
        $t('DOMICILIOESTRANGEIRO' . $n);
    }

    $t('MATRICULA', crc_txt($row['matricula'] ?? ''));
    $t('DATAREGISTRO', ix_br_date($row['data_registro'] ?? null));
    $t('DATACASAMENTO', ix_br_date($row['data_casamento'] ?? null));
    $t('REGIMECASAMENTO', crc_txt($row['regime_bens'] ?? ''));
    $t('ORGAOEMISSOREXTERIOR');
    $t('INFORMACOESCONSULADO');
    $t('OBSERVACOES');
    return $r;
}

/* ======================================================================== */
/*  ÓBITO (mesma estrutura de carga_crc/gerar_carga_obito.php)              */
/*  Correção: datas anteriores a 1970 deixavam de ser enviadas.             */
/* ======================================================================== */
function crc_append_obito(DOMDocument $doc, DOMElement $mov, array $row): DOMElement
{
    $r = $doc->createElement('REGISTROOBITOINCLUSAO');
    $mov->appendChild($r);
    $t = fn($n, $v = '') => crc_el($doc, $r, $n, $v);
    $ouPadrao = function ($v, $padrao = 'NAO DECLARADO') { $v = crc_txt($v); return $v !== '' && $v !== '0' ? $v : $padrao; };
    $ouVazio  = function ($v) { $v = crc_txt($v); return $v !== '' && $v !== '0' ? $v : ''; };

    $t('INDICEREGISTRO', (string)($row['id'] ?? ''));
    $t('FLAGDESCONHECIDO', 'N');
    $t('NOMEFALECIDO', $ouPadrao($row['nome_registrado'] ?? ''));
    $t('CPFFALECIDO');
    $t('MATRICULA', $ouPadrao($row['matricula'] ?? ''));
    $t('DATAREGISTRO', ix_br_date($row['data_registro'] ?? null, '00/00/0000'));

    $pai = $ouVazio($row['nome_pai'] ?? '');
    $t('NOMEPAI', $pai);
    $t('CPFPAI');
    $t('SEXOPAI', $pai !== '' ? 'M' : '');
    $mae = $ouVazio($row['nome_mae'] ?? '');
    $t('NOMEMAE', $mae);
    $t('CPFMAE');
    $t('SEXOMAE', $mae !== '' ? 'F' : '');

    $t('DATAOBITO', ix_br_date($row['data_obito'] ?? null, '00/00/0000'));
    $hora = ix_parse_time(substr(crc_txt($row['hora_obito'] ?? ''), 0, 5)) ?? '00:00';
    $t('HORAOBITO', $hora);
    $t('SEXO', 'I');
    $t('CORPELE', 'IGNORADA');
    $t('ESTADOCIVIL', 'IGNORADO');

    $nascIso = ix_parse_date($row['data_nascimento'] ?? '');
    $t('DATANASCIMENTOFALECIDO', $nascIso ? ix_br_date($nascIso) : '');

    [$idade, $unidade] = crc_idade($nascIso, ix_parse_date($row['data_obito'] ?? ''));
    $t('IDADE', $idade);
    $t('IDADE_DIAS_MESES_ANOS', $unidade);

    $t('ELEITOR', 'I');
    $t('POSSUIBENS', 'I');
    $t('CODIGOOCUPACAOSDC');
    $t('PAISNASCIMENTO', '076');
    $t('NACIONALIDADE', '076');
    $t('CODIGOIBGEMUNNATURALIDADE');
    $t('TEXTOLIVREMUNICIPIONAT', 'NAO DECLARADO');
    $t('CODIGOIBGEMUNLOGRADOURO', $ouVazio($row['ibge_cidade_endereco'] ?? ''));
    $t('DOMICILIOESTRANGEIROFALECIDO');
    $t('LOGRADOURO');
    $t('NUMEROLOGRADOURO');
    $t('COMPLEMENTOLOGRADOURO');
    $t('BAIRRO');
    $t('TIPOLOCALOBITO', 'IGNORADO');
    $t('TIPOMORTE', 'IGNORADA');
    $t('NUMDECLARACAOOBITO');
    $t('NUMDECLARACAOOBITOIGNORADA', 'S');
    $t('PAISOBITO', '076');
    $t('CODIGOIBGEMUNLOGRADOUROOBITO', $ouVazio($row['ibge_cidade_obito'] ?? ''));
    $t('ENDERECOLOCALOBITOESTRANGEIRO');
    $t('LOGRADOUROOBITO');
    $t('NUMEROLOGRADOUROOBITO');
    $t('COMPLEMENTOLOGRADOUROOBITO');
    $t('BAIRROOBITO');
    foreach (['CAUSAMORTEANTECEDENTES_A', 'CAUSAMORTEANTECEDENTES_B', 'CAUSAMORTEANTECEDENTES_C', 'CAUSAMORTEANTECEDENTES_D', 'CAUSAMORTEOUTRASCOND_A', 'CAUSAMORTEOUTRASCOND_B', 'LUGARFALECIMENTO', 'LUGARSEPULTAMENTOCEMITERIO'] as $tag) {
        $t($tag);
    }
    $t('NOMEATESTANTEPRIMARIO', 'NAO DECLARADO');
    $t('CRMATESTANTEPRIMARIO', 'NAO DECLARADO');
    $t('NOMEATESTANTESECUNDARIO');
    $t('CRMATESTANTESECUNDARIO');
    $t('NOMEDECLARANTE', 'NAO DECLARADO');
    $t('CPFDECLARANTE');
    $t('ORGAOEMISSOREXTERIOR');
    $t('INFORMACOESCONSULADO');
    $t('OBSERVACOES');
    return $r;
}

/** Idade no óbito: anos (A), meses (M) ou dias (D). Sem datas válidas: ['0','']. */
function crc_idade(?string $nascIso, ?string $obitoIso): array
{
    if (!$nascIso || !$obitoIso) return ['0', ''];
    try {
        $n = new DateTimeImmutable($nascIso);
        $o = new DateTimeImmutable($obitoIso);
        if ((int)$n->format('Y') <= 1900 || (int)$o->format('Y') <= 1900) return ['0', ''];
        $i = $n->diff($o);
        if ($i->y >= 1) return [(string)$i->y, 'A'];
        if ($i->m >= 1) return [(string)$i->m, 'M'];
        return [(string)max($i->d, 1), 'D'];
    } catch (Throwable $e) {
        return ['0', ''];
    }
}

/* ======================================================================== */
/*  VALIDAÇÃO XSD                                                           */
/* ======================================================================== */

/** Mapa tag XML -> [campo do formulário, rótulo amigável] */
function crc_tag_map(string $tipo): array
{
    $comum = [
        'MATRICULA'    => ['matricula', 'Matrícula'],
        'DATAREGISTRO' => ['data_registro', 'Data do registro'],
        'INDICEREGISTRO' => [null, 'Índice do registro'],
    ];
    switch ($tipo) {
        case 'nascimento': return $comum + [
            'NOMEREGISTRADO' => ['nome_registrado', 'Nome do registrado'],
            'DATANASCIMENTO' => ['data_nascimento', 'Data de nascimento'],
            'SEXO' => ['sexo', 'Sexo'],
            'CODIGOIBGEMUNNASCIMENTO' => ['naturalidade', 'Naturalidade (código IBGE)'],
            'FILIACAO1/NOME' => ['nome_pai', 'Nome do pai'],
            'FILIACAO2/NOME' => ['nome_mae', 'Nome da mãe'],
        ];
        case 'casamento': return $comum + [
            'NOMECONJUGE1' => ['conjuge1_nome', 'Nome do 1º cônjuge'],
            'NOVONOMECONJUGE1' => ['conjuge1_nome_casado', 'Nome de casado(a) do 1º cônjuge'],
            'SEXOCONJUGE1' => ['conjuge1_sexo', 'Sexo do 1º cônjuge'],
            'NOMECONJUGE2' => ['conjuge2_nome', 'Nome do 2º cônjuge'],
            'NOVONOMECONJUGE2' => ['conjuge2_nome_casado', 'Nome de casado(a) do 2º cônjuge'],
            'SEXOCONJUGE2' => ['conjuge2_sexo', 'Sexo do 2º cônjuge'],
            'DATACASAMENTO' => ['data_casamento', 'Data do casamento'],
            'REGIMECASAMENTO' => ['regime_bens', 'Regime de bens'],
        ];
        case 'obito': return $comum + [
            'NOMEFALECIDO' => ['nome_registrado', 'Nome do(a) falecido(a)'],
            'NOMEPAI' => ['nome_pai', 'Nome do pai'],
            'NOMEMAE' => ['nome_mae', 'Nome da mãe'],
            'DATAOBITO' => ['data_obito', 'Data do óbito'],
            'HORAOBITO' => ['hora_obito', 'Hora do óbito'],
            'DATANASCIMENTOFALECIDO' => ['data_nascimento', 'Data de nascimento'],
            'IDADE' => ['data_nascimento', 'Idade'],
            'CODIGOIBGEMUNLOGRADOURO' => ['cidade_endereco', 'Município de residência (IBGE)'],
            'CODIGOIBGEMUNLOGRADOUROOBITO' => ['cidade_obito', 'Município do óbito (IBGE)'],
        ];
    }
    return $comum;
}

/** Converte a mensagem técnica do libxml em texto claro, em português. */
function crc_msg_xsd(string $raw, string $label, string $tag): string
{
    $m = trim($raw);
    if (preg_match("/\\[facet 'maxLength'\\] The value has a length of '(\\d+)'; this exceeds the allowed maximum length of '(\\d+)'/", $m, $g)) {
        return "$label excede o limite da CRC: {$g[1]} caracteres (máximo {$g[2]}).";
    }
    if ($tag === 'MATRICULA' && preg_match("/\\[facet '(length|pattern)'\\]/", $m)) {
        if (preg_match("/length of '(\\d+)'/", $m, $g)) {
            return "Matrícula com {$g[1]} dígitos — a CRC exige exatamente 32. Confira livro (até 5 dígitos), folha (até 3) e termo (até 7).";
        }
        return 'Matrícula fora do padrão da CRC (32 dígitos numéricos).';
    }
    if (preg_match("/\\[facet 'enumeration'\\] The value '([^']*)' is not an element of the set \\{(.*)\\}/", $m, $g)) {
        $set = str_replace("'", '', $g[2]);
        return "$label: o valor \"{$g[1]}\" não é aceito pela CRC (aceitos: $set).";
    }
    if (preg_match("/\\[facet 'pattern'\\] The value '([^']*)'/", $m, $g)) {
        return "$label: o valor \"{$g[1]}\" está fora do formato exigido pela CRC.";
    }
    if (preg_match("/\\[facet 'length'\\] The value has a length of '(\\d+)'; this differs from the allowed length of '(\\d+)'/", $m, $g)) {
        return "$label deve ter exatamente {$g[2]} caracteres (atual: {$g[1]}).";
    }
    if (stripos($m, 'Missing child element') !== false) return "$label: estrutura incompleta no XML.";
    if (stripos($m, 'not expected') !== false) return "$label: elemento fora da ordem esperada pelo XSD.";
    return "$label: " . $m;
}

/**
 * Valida um documento CARGAREGISTROS e devolve os erros agrupados por
 * INDICEREGISTRO (posição do registro) com o campo do formulário associado.
 *
 * @return array{valid:bool, by_index:array<int,array>, general:array}
 */
function crc_validate_document(string $tipo, DOMDocument $doc): array
{
    $xml = $doc->saveXML();
    $out = ['valid' => true, 'by_index' => [], 'general' => []];
    if (!is_file(CRC_XSD_PATH)) {
        $out['general'][] = 'Arquivo XSD da CRC não encontrado em validar_xml/catalogo-crc.xsd.';
        $out['valid'] = false;
        return $out;
    }

    $prev = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $d = new DOMDocument();
    $d->loadXML($xml);
    $ok = $d->schemaValidate(CRC_XSD_PATH);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($ok) return $out;

    $out['valid'] = false;
    $meta = crc_meta($tipo);
    $map = crc_tag_map($tipo);

    // linhas iniciais de cada registro (para associar o erro ao registro)
    $starts = [];
    $seen = [];
    $idx = 0;
    foreach ($d->getElementsByTagName($meta['reg']) as $node) {
        $starts[] = ['line' => $node->getLineNo(), 'idx' => $idx++, 'node' => $node];
    }

    foreach ($errors as $err) {
        $line = (int)$err->line;
        $msg = trim($err->message);
        $tag = '';
        if (preg_match("/Element '([^']+)'/", $msg, $g)) $tag = $g[1];

        $rec = null;
        foreach ($starts as $s) { if ($s['line'] <= $line) $rec = $s; else break; }

        // Localiza o elemento exato na linha (para identificar FILIAÇÃO 1/2)
        $key = $tag;
        if ($rec && $tag === 'NOME') {
            foreach ($rec['node']->getElementsByTagName('NOME') as $nomeEl) {
                if ($nomeEl->getLineNo() === $line) {
                    $fil = $nomeEl->parentNode;
                    $ind = '';
                    foreach ($fil->childNodes as $c) {
                        if ($c instanceof DOMElement && $c->tagName === 'INDICEFILIACAO') { $ind = trim($c->textContent); break; }
                    }
                    $key = 'FILIACAO' . $ind . '/NOME';
                    break;
                }
            }
        }
        [$field, $label] = $map[$key] ?? [null, $tag !== '' ? $tag : 'XML'];
        $item = ['field' => $field, 'tag' => $tag, 'msg' => crc_msg_xsd($msg, $label, $tag), 'raw' => $msg, 'source' => 'xsd'];

        if ($rec) {
            // um aviso por elemento (o libxml pode gerar length + pattern para o mesmo valor)
            $dupKey = $key . '@' . $line;
            if (isset($seen[$rec['idx']][$dupKey])) continue;
            $seen[$rec['idx']][$dupKey] = true;
            $out['by_index'][$rec['idx']][] = $item;
        } else {
            $out['general'][] = $item['msg'];
        }
    }
    return $out;
}

/* ======================================================================== */
/*  REGRAS DE NEGÓCIO (o que o XSD não enxerga, mas a CRC recusa)           */
/* ======================================================================== */

/**
 * Normaliza a entrada do formulário (ou linha do banco) para o formato gravado.
 * Datas -> AAAA-MM-DD, nomes -> caixa alta sem espaços duplicados, números sem zeros à esquerda.
 */
function crc_normalize(array $tipoDef, array $in): array
{
    $row = [];
    foreach ($tipoDef['fields'] as $key => $f) {
        $v = $in[$key] ?? null;
        switch ($f['type']) {
            case 'int':
                $d = ix_digits(is_scalar($v) ? (string)$v : '');
                $row[$key] = $d === '' ? '' : (ltrim($d, '0') === '' ? '0' : ltrim($d, '0'));
                $row['__raw_' . $key] = is_scalar($v) ? trim((string)$v) : '';
                break;
            case 'date':
                $row[$key] = ix_parse_date($v);
                $row['__raw_' . $key] = is_scalar($v) ? trim((string)$v) : '';
                break;
            case 'time':
                $row[$key] = ix_parse_time($v);
                $row['__raw_' . $key] = is_scalar($v) ? trim((string)$v) : '';
                break;
            case 'name':
                $row[$key] = ix_norm_name(is_scalar($v) ? (string)$v : '');
                break;
            case 'select':
                $row[$key] = strtoupper(trim((string)$v));
                break;
            case 'city':
                $row[$key] = trim(preg_replace('/\s{2,}/u', ' ', ix_decode((string)$v)));
                $row[$f['ibge']] = ix_digits((string)($in[$f['ibge']] ?? ''));
                break;
            default:
                $row[$key] = trim((string)$v);
        }
    }
    return $row;
}

function crc_tipo_livro(array $tipoDef, array $row): string
{
    $tl = $tipoDef['tipo_livro'];
    if (is_array($tl)) return $tl['map'][$row[$tl['field']] ?? ''] ?? '';
    return (string)$tl;
}

/**
 * Regras de negócio. Retorna ['errors'=>[], 'warnings'=>[]] com itens {field,msg,source}.
 */
function crc_rules(array $tipoDef, array $row): array
{
    $E = []; $W = [];
    $err  = function ($field, $msg) use (&$E) { $E[] = ['field' => $field, 'msg' => $msg, 'source' => 'regra']; };
    $warn = function ($field, $msg) use (&$W) { $W[] = ['field' => $field, 'msg' => $msg, 'source' => 'regra']; };
    $hoje = date('Y-m-d');

    foreach ($tipoDef['fields'] as $key => $f) {
        $label = $f['label'];
        $v = $row[$key] ?? null;
        $vazio = ($v === null || $v === '');

        switch ($f['type']) {
            case 'int':
                $raw = $row['__raw_' . $key] ?? (string)$v;
                if ($vazio) { if (!empty($f['required'])) $err($key, "$label é obrigatório."); break; }
                if ($raw !== '' && !preg_match('/^\d+$/', $raw)) { $err($key, "$label deve conter apenas números."); break; }
                if ((int)$v <= 0) { $err($key, "$label deve ser maior que zero."); break; }
                if (!empty($f['digits']) && strlen((string)$v) > $f['digits']) {
                    $err($key, "$label aceita no máximo {$f['digits']} dígitos (posições reservadas na matrícula CNJ).");
                }
                break;

            case 'date':
                $raw = $row['__raw_' . $key] ?? '';
                if ($vazio) {
                    if ($raw !== '') $err($key, "$label inválida. Use DD/MM/AAAA.");
                    elseif (!empty($f['required'])) $err($key, "$label é obrigatória.");
                    break;
                }
                $ano = (int)substr($v, 0, 4);
                if ($ano < 1800) $err($key, "$label com ano $ano parece incorreto.");
                elseif (!empty($f['not_future']) && $v > $hoje) $err($key, "$label não pode ser posterior a hoje.");
                break;

            case 'time':
                $raw = $row['__raw_' . $key] ?? '';
                if ($vazio) {
                    if ($raw !== '') $err($key, "$label inválida. Use HH:MM (00:00 a 23:59).");
                    elseif (!empty($f['required'])) $err($key, "$label é obrigatória.");
                }
                break;

            case 'name':
                if ($vazio) { if (!empty($f['required'])) $err($key, "$label é obrigatório."); break; }
                $len = mb_strlen($v, 'UTF-8');
                if (!empty($f['max']) && $len > $f['max']) $err($key, "$label tem $len caracteres; a CRC aceita no máximo {$f['max']}.");
                if (preg_match('/\d/u', $v)) $err($key, "$label não pode conter números.");
                elseif (preg_match("/[^\\p{L}\\s'\\-.]/u", $v, $mm)) $err($key, "$label contém caractere não aceito pela CRC: \"{$mm[0]}\".");
                if (!preg_match('/\S\s+\S/u', $v) && !in_array($v, ['IGNORADO', 'NAO DECLARADO', 'NÃO DECLARADO'], true)) {
                    $warn($key, "$label tem apenas uma palavra. Confirme se é o nome completo.");
                }
                break;

            case 'select':
                if ($vazio) { if (!empty($f['required'])) $err($key, "Selecione: $label."); break; }
                if (!array_key_exists($v, $f['options'])) $err($key, "$label: opção inválida.");
                break;

            case 'city':
                $ibge = (string)($row[$f['ibge']] ?? '');
                if ($vazio && $ibge === '') { if (!empty($f['required'])) $err($key, "$label é obrigatório."); break; }
                if (!preg_match('/^\d{7}$/', $ibge)) $err($key, "$label: selecione o município pela pesquisa para obter o código IBGE (7 dígitos).");
                break;
        }
    }

    // ---------- regras cruzadas por tipo ----------
    $dr = $row['data_registro'] ?? null;
    switch ($tipoDef['key']) {
        case 'nascimento':
            if ($dr && !empty($row['data_nascimento']) && $row['data_nascimento'] > $dr) {
                $err('data_nascimento', 'Data de nascimento não pode ser posterior à data do registro.');
            }
            if (!empty($row['nome_pai']) && $row['nome_pai'] === ($row['nome_mae'] ?? null)) {
                $warn('nome_pai', 'Nome do pai igual ao da mãe.');
            }
            if (!empty($row['nome_registrado']) && in_array($row['nome_registrado'], [$row['nome_pai'] ?? '', $row['nome_mae'] ?? ''], true)) {
                $warn('nome_registrado', 'Nome do registrado igual ao de um dos genitores.');
            }
            break;

        case 'casamento':
            if ($dr && !empty($row['data_casamento']) && $row['data_casamento'] > $dr) {
                $err('data_casamento', 'Data do casamento deve ser anterior ou igual à data do registro.');
            }
            if (!empty($row['conjuge1_nome']) && $row['conjuge1_nome'] === ($row['conjuge2_nome'] ?? null)) {
                $err('conjuge2_nome', 'Os dois cônjuges estão com o mesmo nome.');
            }
            break;

        case 'obito':
            $do = $row['data_obito'] ?? null;
            if ($dr && $do && $do > $dr) $err('data_obito', 'Data do óbito não pode ser posterior à data do registro.');
            if ($do && !empty($row['data_nascimento']) && $row['data_nascimento'] > $do) {
                $err('data_nascimento', 'Data de nascimento não pode ser posterior à data do óbito.');
            }
            if ($do && !empty($row['data_nascimento'])) {
                [$idade, $un] = crc_idade($row['data_nascimento'], $do);
                if ($un === 'A' && (int)$idade > 125) $warn('data_nascimento', "Idade calculada de $idade anos. Confira as datas.");
            }
            break;
    }

    // ---------- matrícula ----------
    if (ix_cns() === '') {
        $err('matricula', 'CNS da serventia não cadastrado (tabela cadastro_serventia): a matrícula não pode ser gerada.');
    }
    return ['errors' => $E, 'warnings' => $W];
}

/**
 * Verificação completa de UM registro (normalizado): regras + XSD.
 * $row deve conter 'matricula' já calculada e, se existir, 'id'.
 */
function crc_check_one(array $tipoDef, array $row): array
{
    $res = crc_rules($tipoDef, $row);
    $doc = crc_build($tipoDef['crc_xml'], [$row + ['id' => $row['id'] ?? 'NOVO']]);
    $v = crc_validate_document($tipoDef['crc_xml'], $doc);

    $xsdErrors = $v['by_index'][0] ?? [];
    // evita mensagem duplicada quando a regra já apontou o mesmo campo
    $camposComErro = array_flip(array_filter(array_column($res['errors'], 'field')));
    foreach ($xsdErrors as $e) {
        if ($e['field'] && isset($camposComErro[$e['field']])) continue;
        $res['errors'][] = ['field' => $e['field'], 'msg' => $e['msg'], 'source' => 'xsd'];
        if ($e['field']) $camposComErro[$e['field']] = true;
    }
    foreach ($v['general'] as $g) $res['errors'][] = ['field' => null, 'msg' => $g, 'source' => 'xsd'];

    $res['xsd_ok'] = empty($xsdErrors) && empty($v['general']);
    $res['ok'] = empty($res['errors']);
    $res['xml'] = crc_record_xml($doc);
    return $res;
}

/** XML (somente o registro) para exibição. */
function crc_record_xml(DOMDocument $doc): string
{
    $mov = $doc->documentElement->lastChild;
    if (!$mov || !$mov->firstChild) return '';
    return $doc->saveXML($mov->firstChild);
}

/**
 * Auditoria em lote: regras + XSD para várias linhas do banco.
 * @return array<int,array{errors:array,warnings:array}> indexado pelo id
 */
function crc_audit_rows(array $tipoDef, array $rows): array
{
    $out = [];
    if (!$rows) return $out;
    foreach (array_chunk($rows, 1500) as $chunk) {
        $doc = crc_build($tipoDef['crc_xml'], $chunk);
        $v = crc_validate_document($tipoDef['crc_xml'], $doc);
        foreach ($chunk as $i => $row) {
            $norm = crc_normalize($tipoDef, crc_row_to_input($tipoDef, $row));
            $norm['matricula'] = $row['matricula'] ?? '';
            $res = crc_rules($tipoDef, $norm);
            $campos = array_flip(array_filter(array_column($res['errors'], 'field')));
            foreach ($v['by_index'][$i] ?? [] as $e) {
                if ($e['field'] && isset($campos[$e['field']])) continue;
                $res['errors'][] = ['field' => $e['field'], 'msg' => $e['msg'], 'source' => 'xsd'];
                if ($e['field']) $campos[$e['field']] = true;
            }
            // texto gravado fora do padrão (espaços duplicados, minúsculas, entidades HTML)
            foreach ($tipoDef['fields'] as $k => $f) {
                if ($f['type'] !== 'name' || empty($row[$k]) || isset($campos[$k])) continue;
                if ((string)$row[$k] !== $norm[$k]) {
                    $res['warnings'][] = ['field' => $k, 'msg' => $f['label'] . ' gravado fora do padrão (espaços duplicados, minúsculas ou símbolos codificados). Abra e salve para normalizar.', 'source' => 'regra'];
                }
            }
            // matrícula gravada diferente da calculada
            $calc = ix_matricula(crc_tipo_livro($tipoDef, $norm), $norm['livro'] ?? '', $norm['folha'] ?? '', $norm['termo'] ?? '', $norm['data_registro'] ?? null);
            if ($calc && !empty($row['matricula']) && $calc !== $row['matricula'] && !isset($campos['matricula'])) {
                $res['warnings'][] = ['field' => 'matricula', 'msg' => 'Matrícula gravada difere da calculada (' . $calc . '). Abra e salve o registro para recalcular.', 'source' => 'regra'];
            }
            if (empty($row['matricula']) && !isset($campos['matricula'])) {
                $res['errors'][] = ['field' => 'matricula', 'msg' => 'Registro sem matrícula. Abra e salve para gerar.', 'source' => 'regra'];
            }
            $out[(int)$row['id']] = $res;
        }
    }
    return $out;
}

/** Linha do banco -> formato de entrada do formulário (decodificando legados). */
function crc_row_to_input(array $tipoDef, array $row): array
{
    $in = [];
    foreach ($tipoDef['fields'] as $key => $f) {
        $v = $row[$key] ?? '';
        if ($f['type'] === 'time') $v = $v ? substr((string)$v, 0, 5) : '';
        $in[$key] = ix_decode(is_null($v) ? '' : (string)$v);
        if ($f['type'] === 'city') $in[$f['ibge']] = (string)($row[$f['ibge']] ?? '');
    }
    return $in;
}
