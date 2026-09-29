<?php
/**
 * Atlas Iris — tipos de documento para extração estruturada.
 * Cada tipo define campos (chave, rótulo, tipo, dica, subcampos para listas).
 */

function iris_tipos_campo()
{
    return [
        'texto' => 'Texto', 'texto_longo' => 'Texto longo', 'nome' => 'Nome de pessoa', 'data' => 'Data', 'hora' => 'Hora',
        'cpf' => 'CPF', 'cnpj' => 'CNPJ', 'cpf_cnpj' => 'CPF ou CNPJ', 'cep' => 'CEP', 'moeda' => 'Valor (R$)',
        'numero' => 'Número', 'matricula_certidao' => 'Matrícula de certidão (32 dígitos)', 'lista' => 'Lista (tabela)',
    ];
}

function iris_tipos_schema()
{
    $conn = iris_db();
    $conn->query("CREATE TABLE IF NOT EXISTS iris_tipos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(60) NOT NULL UNIQUE,
        nome VARCHAR(160) NOT NULL,
        descricao VARCHAR(255) NULL,
        instrucoes TEXT NULL,
        campos LONGTEXT NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        sistema TINYINT(1) NOT NULL DEFAULT 0,
        ordem INT NOT NULL DEFAULT 100,
        criado_em DATETIME NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $c = $conn->query("SELECT COUNT(*) AS n FROM iris_tipos")->fetch_assoc();
    if ((int)$c['n'] === 0) iris_tipos_semear(false);
}

/** Campo abreviado: [chave, rótulo, tipo, dica?, subcampos?] */
function iris_c($chave, $rotulo, $tipo = 'texto', $dica = '', $sub = null)
{
    $c = ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => $tipo];
    if ($dica !== '') $c['dica'] = $dica;
    if ($sub) $c['subcampos'] = array_map(function ($s) { return ['chave' => $s[0], 'rotulo' => $s[1], 'tipo' => $s[2] ?? 'texto']; }, $sub);
    return $c;
}

function iris_tipos_padrao()
{
    $regCivil = [
        iris_c('matricula', 'Matrícula', 'matricula_certidao', '32 dígitos da certidão (com ou sem espaços)'),
        iris_c('livro', 'Livro', 'texto'), iris_c('folha', 'Folha', 'texto'), iris_c('termo', 'Termo', 'texto'),
        iris_c('cartorio', 'Cartório / serventia', 'texto'), iris_c('municipio_cartorio', 'Município / UF do cartório', 'texto'),
        iris_c('oficial', 'Oficial / escrevente que assina', 'nome'), iris_c('data_emissao', 'Data de emissão da certidão', 'data'),
        iris_c('selo', 'Selo de fiscalização', 'texto', 'número/código do selo e código de validação'),
        iris_c('averbacoes', 'Averbações / anotações', 'texto_longo', 'texto integral de averbações e anotações, inclusive de margem'),
    ];
    return [
        ['slug' => 'certidao_nascimento', 'nome' => 'Certidão / termo de nascimento', 'ordem' => 10,
         'descricao' => 'Registro civil de nascimento — certidão atual ou termo em livro antigo',
         'instrucoes' => 'Em livros antigos, os avós e a filiação costumam aparecer no corpo do termo; extraia-os mesmo assim.',
         'campos' => array_merge([
            iris_c('nome_registrado', 'Nome do registrado', 'nome'), iris_c('cpf_registrado', 'CPF do registrado', 'cpf'),
            iris_c('sexo', 'Sexo', 'texto'), iris_c('data_nascimento', 'Data de nascimento', 'data'),
            iris_c('hora_nascimento', 'Hora de nascimento', 'hora'), iris_c('local_nascimento', 'Local de nascimento', 'texto', 'hospital, domicílio etc.'),
            iris_c('municipio_nascimento', 'Município / UF de nascimento', 'texto'), iris_c('naturalidade', 'Naturalidade', 'texto'),
            iris_c('nome_pai', 'Nome do pai', 'nome'), iris_c('nome_mae', 'Nome da mãe', 'nome'),
            iris_c('avos_paternos', 'Avós paternos', 'texto'), iris_c('avos_maternos', 'Avós maternos', 'texto'),
            iris_c('declarante', 'Declarante', 'nome'), iris_c('data_registro', 'Data do registro', 'data'),
            iris_c('gemeo', 'Gêmeo (informação)', 'texto'), iris_c('dnv', 'DNV (Declaração de Nascido Vivo)', 'texto'),
         ], $regCivil)],
        ['slug' => 'certidao_casamento', 'nome' => 'Certidão / termo de casamento', 'ordem' => 20,
         'descricao' => 'Registro civil de casamento (inclusive religioso com efeito civil)', 'instrucoes' => '',
         'campos' => array_merge([
            iris_c('conjuge1_nome', '1º cônjuge — nome de solteiro(a)', 'nome'), iris_c('conjuge1_nome_casado', '1º cônjuge — nome adotado', 'nome'),
            iris_c('conjuge1_cpf', '1º cônjuge — CPF', 'cpf'), iris_c('conjuge1_nascimento', '1º cônjuge — data de nascimento', 'data'),
            iris_c('conjuge1_naturalidade', '1º cônjuge — naturalidade', 'texto'), iris_c('conjuge1_filiacao', '1º cônjuge — filiação', 'texto'),
            iris_c('conjuge2_nome', '2º cônjuge — nome de solteiro(a)', 'nome'), iris_c('conjuge2_nome_casado', '2º cônjuge — nome adotado', 'nome'),
            iris_c('conjuge2_cpf', '2º cônjuge — CPF', 'cpf'), iris_c('conjuge2_nascimento', '2º cônjuge — data de nascimento', 'data'),
            iris_c('conjuge2_naturalidade', '2º cônjuge — naturalidade', 'texto'), iris_c('conjuge2_filiacao', '2º cônjuge — filiação', 'texto'),
            iris_c('data_casamento', 'Data do casamento', 'data'), iris_c('regime_bens', 'Regime de bens', 'texto'),
            iris_c('pacto_antenupcial', 'Pacto antenupcial (livro/folha/cartório)', 'texto'), iris_c('data_registro', 'Data do registro', 'data'),
         ], $regCivil)],
        ['slug' => 'certidao_obito', 'nome' => 'Certidão / termo de óbito', 'ordem' => 30,
         'descricao' => 'Registro civil de óbito', 'instrucoes' => '',
         'campos' => array_merge([
            iris_c('nome_falecido', 'Nome do(a) falecido(a)', 'nome'), iris_c('cpf_falecido', 'CPF', 'cpf'),
            iris_c('sexo', 'Sexo', 'texto'), iris_c('estado_civil', 'Estado civil', 'texto'), iris_c('conjuge', 'Cônjuge', 'nome'),
            iris_c('idade', 'Idade', 'texto'), iris_c('data_nascimento', 'Data de nascimento', 'data'), iris_c('naturalidade', 'Naturalidade', 'texto'),
            iris_c('filiacao', 'Filiação', 'texto'), iris_c('data_obito', 'Data do óbito', 'data'), iris_c('hora_obito', 'Hora do óbito', 'hora'),
            iris_c('local_obito', 'Local do óbito', 'texto'), iris_c('causa_morte', 'Causa da morte', 'texto'),
            iris_c('medico', 'Médico que atestou', 'nome'), iris_c('local_sepultamento', 'Sepultamento / cremação', 'texto'),
            iris_c('declarante', 'Declarante', 'nome'), iris_c('deixou_bens', 'Deixou bens', 'texto'), iris_c('deixou_filhos', 'Filhos', 'texto'),
            iris_c('deixou_testamento', 'Deixou testamento', 'texto'), iris_c('data_registro', 'Data do registro', 'data'),
         ], $regCivil)],
        ['slug' => 'matricula_imovel', 'nome' => 'Matrícula de imóvel (RI)', 'ordem' => 40,
         'descricao' => 'Ficha de matrícula do Registro de Imóveis, com registros (R) e averbações (AV)',
         'instrucoes' => "Liste TODOS os atos na ordem (R-1, AV-2, ...). No campo resumo de cada ato, faça um resumo objetivo "
                       . "(natureza, partes, valor, título). Em proprietarios, liste apenas os titulares ATUAIS segundo o último ato de transmissão.",
         'campos' => [
            iris_c('numero_matricula', 'Nº da matrícula', 'texto'), iris_c('cns', 'CNS da serventia', 'numero'),
            iris_c('cartorio', 'Registro de Imóveis', 'texto'), iris_c('comarca', 'Comarca', 'texto'),
            iris_c('data_abertura', 'Data de abertura', 'data'), iris_c('registro_anterior', 'Registro anterior / origem', 'texto'),
            iris_c('descricao_imovel', 'Descrição do imóvel', 'texto_longo', 'texto integral da descrição, com medidas e confrontações'),
            iris_c('tipo_imovel', 'Urbano / rural', 'texto'), iris_c('area', 'Área', 'texto', 'com a unidade (m², ha)'),
            iris_c('endereco', 'Endereço / localização', 'texto'), iris_c('inscricao_municipal', 'Inscrição imobiliária municipal', 'texto'),
            iris_c('ccir', 'CCIR', 'texto'), iris_c('nirf_cib', 'NIRF / CIB', 'texto'), iris_c('car', 'CAR', 'texto'),
            iris_c('codigo_sigef', 'Certificação SIGEF / INCRA', 'texto'),
            iris_c('proprietarios', 'Proprietários atuais', 'lista', '', [['nome', 'Nome', 'nome'], ['cpf_cnpj', 'CPF/CNPJ', 'cpf_cnpj'], ['qualificacao', 'Qualificação'], ['fracao', 'Fração']]),
            iris_c('atos', 'Atos (registros e averbações)', 'lista', '', [['numero', 'Nº do ato'], ['data', 'Data', 'data'], ['natureza', 'Natureza'], ['resumo', 'Resumo']]),
            iris_c('onus', 'Ônus e restrições vigentes', 'texto_longo', 'hipotecas, alienação fiduciária, penhoras, indisponibilidades não canceladas'),
         ]],
        ['slug' => 'documento_identificacao', 'nome' => 'Documento de identificação (RG / CIN / CNH)', 'ordem' => 50,
         'descricao' => 'RG, Carteira de Identidade Nacional, CNH, carteira funcional', 'instrucoes' => '',
         'campos' => [
            iris_c('tipo_documento', 'Tipo de documento', 'texto'), iris_c('nome', 'Nome', 'nome'), iris_c('nome_social', 'Nome social', 'nome'),
            iris_c('cpf', 'CPF', 'cpf'), iris_c('numero_documento', 'Nº do documento (RG/registro)', 'texto'),
            iris_c('orgao_emissor', 'Órgão emissor / UF', 'texto'), iris_c('data_emissao', 'Data de emissão / expedição', 'data'),
            iris_c('validade', 'Validade', 'data'), iris_c('data_nascimento', 'Data de nascimento', 'data'),
            iris_c('naturalidade', 'Naturalidade', 'texto'), iris_c('nome_pai', 'Filiação — pai', 'nome'), iris_c('nome_mae', 'Filiação — mãe', 'nome'),
            iris_c('categoria_cnh', 'Categoria (CNH)', 'texto'), iris_c('certidao_origem', 'Certidão de origem', 'texto'),
         ]],
        ['slug' => 'comprovante_endereco', 'nome' => 'Comprovante de endereço', 'ordem' => 60,
         'descricao' => 'Conta de água, luz, telefone, internet, fatura, correspondência bancária', 'instrucoes' => '',
         'campos' => [
            iris_c('titular', 'Titular', 'nome'), iris_c('cpf_cnpj', 'CPF/CNPJ do titular', 'cpf_cnpj'),
            iris_c('logradouro', 'Logradouro', 'texto'), iris_c('numero', 'Número', 'texto'), iris_c('complemento', 'Complemento', 'texto'),
            iris_c('bairro', 'Bairro', 'texto'), iris_c('municipio', 'Município', 'texto'), iris_c('uf', 'UF', 'texto'), iris_c('cep', 'CEP', 'cep'),
            iris_c('emissor', 'Empresa emissora', 'texto'), iris_c('data_referencia', 'Data de emissão / vencimento', 'data'),
         ]],
        ['slug' => 'procuracao', 'nome' => 'Procuração', 'ordem' => 70,
         'descricao' => 'Procuração pública ou particular', 'instrucoes' => '',
         'campos' => [
            iris_c('especie', 'Espécie (pública/particular)', 'texto'),
            iris_c('outorgantes', 'Outorgantes', 'lista', '', [['nome', 'Nome', 'nome'], ['cpf_cnpj', 'CPF/CNPJ', 'cpf_cnpj'], ['qualificacao', 'Qualificação']]),
            iris_c('outorgados', 'Outorgados', 'lista', '', [['nome', 'Nome', 'nome'], ['cpf_cnpj', 'CPF/CNPJ', 'cpf_cnpj'], ['qualificacao', 'Qualificação']]),
            iris_c('finalidade', 'Finalidade', 'texto'), iris_c('poderes', 'Poderes', 'texto_longo'),
            iris_c('substabelecimento', 'Substabelecimento', 'texto'), iris_c('prazo_validade', 'Prazo de validade', 'texto'),
            iris_c('data', 'Data da lavratura', 'data'), iris_c('livro', 'Livro', 'texto'), iris_c('folha', 'Folha', 'texto'),
            iris_c('cartorio', 'Tabelionato', 'texto'), iris_c('selo', 'Selo de fiscalização', 'texto'),
         ]],
        ['slug' => 'escritura_publica', 'nome' => 'Escritura pública', 'ordem' => 80,
         'descricao' => 'Compra e venda, doação, inventário, divórcio, permuta e demais escrituras', 'instrucoes' => '',
         'campos' => [
            iris_c('natureza', 'Natureza do ato', 'texto'), iris_c('data', 'Data da lavratura', 'data'),
            iris_c('livro', 'Livro', 'texto'), iris_c('folha', 'Folha', 'texto'), iris_c('cartorio', 'Tabelionato', 'texto'),
            iris_c('partes', 'Partes', 'lista', '', [['papel', 'Papel'], ['nome', 'Nome', 'nome'], ['cpf_cnpj', 'CPF/CNPJ', 'cpf_cnpj'], ['qualificacao', 'Qualificação']]),
            iris_c('objeto', 'Objeto / imóvel', 'texto_longo'), iris_c('matricula_imovel', 'Matrícula do imóvel', 'texto'),
            iris_c('valor', 'Valor do negócio', 'moeda'), iris_c('valor_avaliacao', 'Valor de avaliação / venal', 'moeda'),
            iris_c('itbi_itcmd', 'ITBI / ITCMD (guia, valor)', 'texto'), iris_c('forma_pagamento', 'Forma de pagamento', 'texto'),
            iris_c('selo', 'Selo de fiscalização', 'texto'),
         ]],
    ];
}

function iris_tipos_semear($somenteFaltantes = true)
{
    $conn = iris_db();
    $st = $conn->prepare("INSERT INTO iris_tipos (slug, nome, descricao, instrucoes, campos, ativo, sistema, ordem, criado_em, atualizado_em)
                          VALUES (?,?,?,?,?,1,1,?,?,?)
                          ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), instrucoes=VALUES(instrucoes),
                          campos=VALUES(campos), sistema=1, atualizado_em=VALUES(atualizado_em)");
    foreach (iris_tipos_padrao() as $t) {
        if ($somenteFaltantes && iris_tipo_por_slug($t['slug'], false)) continue;
        $campos = json_encode($t['campos'], JSON_UNESCAPED_UNICODE);
        $agora = date('Y-m-d H:i:s');
        $st->bind_param('sssssiss', $t['slug'], $t['nome'], $t['descricao'], $t['instrucoes'], $campos, $t['ordem'], $agora, $agora);
        $st->execute();
    }
    $st->close();
}

function iris_tipo_linha($row)
{
    if (!$row) return null;
    $row['campos'] = json_decode($row['campos'] ?? '[]', true) ?: [];
    $row['ativo'] = (int)$row['ativo']; $row['sistema'] = (int)$row['sistema']; $row['id'] = (int)$row['id'];
    return $row;
}
function iris_tipos($somenteAtivos = true)
{
    iris_ensure_schema();
    $r = iris_db()->query("SELECT * FROM iris_tipos" . ($somenteAtivos ? " WHERE ativo=1" : "") . " ORDER BY ordem, nome");
    $out = [];
    while ($r && $row = $r->fetch_assoc()) $out[] = iris_tipo_linha($row);
    return $out;
}
function iris_tipo_por_slug($slug, $somenteAtivo = true)
{
    $st = iris_db()->prepare("SELECT * FROM iris_tipos WHERE slug=?" . ($somenteAtivo ? " AND ativo=1" : "") . " LIMIT 1");
    $st->bind_param('s', $slug); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    return iris_tipo_linha($row);
}

/** Valida e normaliza a definição de campos enviada pelo administrador. */
function iris_tipo_normalizar_campos($campos)
{
    if (is_string($campos)) $campos = json_decode($campos, true);
    if (!is_array($campos) || !$campos) throw new RuntimeException('Defina ao menos um campo.');
    $validos = iris_tipos_campo(); $out = []; $vistos = [];
    $slugify = function ($s) {
        $s = iris_sem_acento(mb_strtolower(trim((string)$s)));
        return trim(preg_replace('~[^a-z0-9]+~', '_', $s), '_');
    };
    foreach ($campos as $c) {
        $rot = trim((string)($c['rotulo'] ?? ''));
        $ch = $slugify($c['chave'] ?? '') ?: $slugify($rot);
        if ($ch === '' || $rot === '') continue;
        if (isset($vistos[$ch])) throw new RuntimeException('Chave de campo repetida: ' . $ch);
        $vistos[$ch] = 1;
        $tipo = isset($validos[$c['tipo'] ?? '']) ? $c['tipo'] : 'texto';
        $n = ['chave' => $ch, 'rotulo' => mb_substr($rot, 0, 120), 'tipo' => $tipo];
        if (!empty($c['dica'])) $n['dica'] = mb_substr(trim($c['dica']), 0, 300);
        if ($tipo === 'lista') {
            $sub = [];
            foreach ((array)($c['subcampos'] ?? []) as $s) {
                $sr = trim((string)($s['rotulo'] ?? '')); $sc = $slugify($s['chave'] ?? '') ?: $slugify($sr);
                if ($sc === '' || $sr === '') continue;
                $st = isset($validos[$s['tipo'] ?? '']) && ($s['tipo'] ?? '') !== 'lista' ? $s['tipo'] : 'texto';
                $sub[] = ['chave' => $sc, 'rotulo' => mb_substr($sr, 0, 120), 'tipo' => $st];
            }
            if (!$sub) throw new RuntimeException('O campo de lista "' . $rot . '" precisa de ao menos uma coluna.');
            $n['subcampos'] = $sub;
        }
        $out[] = $n;
    }
    if (!$out) throw new RuntimeException('Defina ao menos um campo válido.');
    if (count($out) > 80) throw new RuntimeException('Máximo de 80 campos por tipo.');
    return $out;
}
function iris_sem_acento($s)
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return $t === false ? $s : $t;
}

function iris_tipo_salvar($d)
{
    iris_ensure_schema();
    $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '') throw new RuntimeException('Informe o nome do tipo de documento.');
    $campos = json_encode(iris_tipo_normalizar_campos($d['campos'] ?? []), JSON_UNESCAPED_UNICODE);
    $desc = mb_substr(trim((string)($d['descricao'] ?? '')), 0, 255);
    $instr = trim((string)($d['instrucoes'] ?? ''));
    $ativo = !empty($d['ativo']) ? 1 : 0;
    $agora = date('Y-m-d H:i:s');
    $id = (int)($d['id'] ?? 0);
    $conn = iris_db();
    if ($id > 0) {
        $st = $conn->prepare("UPDATE iris_tipos SET nome=?, descricao=?, instrucoes=?, campos=?, ativo=?, atualizado_em=? WHERE id=?");
        $st->bind_param('ssssisi', $nome, $desc, $instr, $campos, $ativo, $agora, $id);
        $st->execute(); $st->close();
        return $id;
    }
    $slug = trim(preg_replace('~[^a-z0-9]+~', '_', iris_sem_acento(mb_strtolower($nome))), '_') ?: 'tipo';
    $base = $slug; $i = 2;
    while ($slug === 'livre' || iris_tipo_por_slug($slug, false)) $slug = $base . '_' . $i++;
    $st = $conn->prepare("INSERT INTO iris_tipos (slug, nome, descricao, instrucoes, campos, ativo, sistema, ordem, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,0,500,?,?)");
    $st->bind_param('sssssiss', $slug, $nome, $desc, $instr, $campos, $ativo, $agora, $agora);
    $st->execute(); $novo = $st->insert_id; $st->close();
    return $novo;
}
function iris_tipo_ativar($id, $ativo)
{
    $st = iris_db()->prepare("UPDATE iris_tipos SET ativo=? WHERE id=?");
    $a = $ativo ? 1 : 0; $st->bind_param('ii', $a, $id); $st->execute(); $st->close();
}
function iris_tipo_excluir($id)
{
    $st = iris_db()->prepare("SELECT sistema FROM iris_tipos WHERE id=?");
    $st->bind_param('i', $id); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
    if (!$r) throw new RuntimeException('Tipo não encontrado.');
    if ((int)$r['sistema'] === 1) throw new RuntimeException('Tipos de fábrica não podem ser excluídos — desative-o, se não for usar.');
    $st = iris_db()->prepare("DELETE FROM iris_tipos WHERE id=?");
    $st->bind_param('i', $id); $st->execute(); $st->close();
}

/* ============================ Esquemas JSON para a IA ============================ */
function iris_schema_valor()
{
    return ['type' => 'object', 'properties' => [
        'valor' => ['type' => ['string', 'null']],
        'confianca' => ['type' => 'string', 'enum' => ['alta', 'media', 'baixa']],
        'pagina' => ['type' => ['integer', 'null']],
    ], 'required' => ['valor', 'confianca']];
}
function iris_schema_tipo(array $tipo)
{
    $props = []; $req = [];
    foreach ($tipo['campos'] as $c) {
        if ($c['tipo'] === 'lista') {
            $sp = []; foreach ($c['subcampos'] as $s) $sp[$s['chave']] = ['type' => ['string', 'null']];
            $props[$c['chave']] = ['type' => 'object', 'properties' => [
                'itens' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => $sp]],
                'confianca' => ['type' => 'string', 'enum' => ['alta', 'media', 'baixa']],
            ], 'required' => ['itens', 'confianca']];
        } else $props[$c['chave']] = iris_schema_valor();
        $req[] = $c['chave'];
    }
    return ['type' => 'object', 'properties' => [
        'campos' => ['type' => 'object', 'properties' => $props, 'required' => $req],
        'observacoes' => ['type' => 'string'],
    ], 'required' => ['campos']];
}
function iris_schema_livre()
{
    return ['type' => 'object', 'properties' => [
        'tipo_documento' => ['type' => 'string'],
        'campos' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'rotulo' => ['type' => 'string'], 'valor' => ['type' => ['string', 'null']],
            'confianca' => ['type' => 'string', 'enum' => ['alta', 'media', 'baixa']],
        ], 'required' => ['rotulo', 'valor', 'confianca']]],
        'observacoes' => ['type' => 'string'],
    ], 'required' => ['tipo_documento', 'campos']];
}
function iris_schema_classificar()
{
    return ['type' => 'object', 'properties' => [
        'slug' => ['type' => 'string'], 'confianca' => ['type' => 'string', 'enum' => ['alta', 'media', 'baixa']],
        'titulo' => ['type' => 'string'],
    ], 'required' => ['slug']];
}

/* ============================ Pós-processamento ============================ */
function iris_conf($c) { $c = mb_strtolower((string)$c); return in_array($c, ['alta', 'media', 'baixa'], true) ? $c : ($c === 'média' ? 'media' : 'media'); }
function iris_valor_texto($v)
{
    if ($v === null) return null;
    if (is_array($v)) return isset($v['valor']) ? iris_valor_texto($v['valor']) : json_encode($v, JSON_UNESCAPED_UNICODE);
    $s = trim((string)$v);
    return ($s === '' || in_array(mb_strtolower($s), ['null', 'n/a', 'não consta', 'nao consta'], true)) ? null : $s;
}

/**
 * Converte a resposta da IA na estrutura usada pela interface, validando cada campo.
 * @return array ['slug','nome','campos'=>[chave=>{...}],'ordem'=>[chaves],'observacoes','avisos'=>[],'resumo'=>[...]]
 */
function iris_normalizar_dados(array $tipo, array $resp)
{
    $bruto = $resp['campos'] ?? [];
    $campos = []; $ordem = [];
    $cont = ['total' => 0, 'preenchidos' => 0, 'invalidos' => 0, 'baixa' => 0];
    foreach ($tipo['campos'] as $def) {
        $k = $def['chave']; $ordem[] = $k; $b = $bruto[$k] ?? null;
        $cont['total']++;
        if ($def['tipo'] === 'lista') {
            $itens = []; $vals = [];
            $lista = [];
            if (is_array($b)) $lista = isset($b['itens']) && is_array($b['itens']) ? $b['itens'] : (array_is_list($b) ? $b : []);
            foreach ($lista as $it) {
                if (!is_array($it)) continue;
                $linha = []; $lv = []; $vazio = true;
                foreach ($def['subcampos'] as $s) {
                    $v = iris_validar_valor($s['tipo'], iris_valor_texto($it[$s['chave']] ?? null));
                    $linha[$s['chave']] = $v['valor']; if ($v['valor'] !== null) $vazio = false;
                    if ($v['ok'] === false) { $lv[$s['chave']] = $v['msg']; $cont['invalidos']++; }
                }
                if (!$vazio) { $itens[] = $linha; $vals[] = $lv; }
            }
            $conf = iris_conf(is_array($b) ? ($b['confianca'] ?? 'media') : 'media');
            if ($itens) $cont['preenchidos']++;
            if ($itens && $conf === 'baixa') $cont['baixa']++;
            $campos[$k] = ['chave' => $k, 'rotulo' => $def['rotulo'], 'tipo' => 'lista', 'subcampos' => $def['subcampos'],
                           'itens' => $itens, 'itens_validacao' => $vals, 'confianca' => $conf];
            continue;
        }
        $valor = iris_valor_texto(is_array($b) ? ($b['valor'] ?? null) : $b);
        $v = iris_validar_valor($def['tipo'], $valor);
        $conf = iris_conf(is_array($b) ? ($b['confianca'] ?? 'media') : 'media');
        $pag = is_array($b) && isset($b['pagina']) && is_numeric($b['pagina']) ? (int)$b['pagina'] : null;
        if ($v['valor'] !== null) $cont['preenchidos']++;
        if ($v['ok'] === false) $cont['invalidos']++;
        if ($v['valor'] !== null && $conf === 'baixa') $cont['baixa']++;
        $campos[$k] = ['chave' => $k, 'rotulo' => $def['rotulo'], 'tipo' => $def['tipo'], 'valor' => $v['valor'],
                       'confianca' => $conf, 'pagina' => $pag,
                       'validacao' => ['ok' => $v['ok'], 'msg' => $v['msg']] + (isset($v['partes']) ? ['partes' => $v['partes']] : [])];
    }
    return ['slug' => $tipo['slug'], 'nome' => $tipo['nome'], 'campos' => $campos, 'ordem' => $ordem,
            'observacoes' => trim((string)($resp['observacoes'] ?? '')), 'avisos' => iris_conferencias_cruzadas($campos),
            'resumo' => $cont];
}

/** Normaliza a extração livre (lista de pares). */
function iris_normalizar_livre(array $resp)
{
    $campos = []; $ordem = []; $i = 0;
    $cont = ['total' => 0, 'preenchidos' => 0, 'invalidos' => 0, 'baixa' => 0];
    foreach ((array)($resp['campos'] ?? []) as $c) {
        if (!is_array($c)) continue;
        $rot = trim((string)($c['rotulo'] ?? '')); $val = iris_valor_texto($c['valor'] ?? null);
        if ($rot === '') continue;
        $k = 'c' . (++$i); $ordem[] = $k;
        // validação heurística pelo rótulo
        $tipo = 'texto'; $l = mb_strtolower($rot);
        if (preg_match('~\bcpf\b~u', $l) && preg_match('~\bcnpj\b~u', $l)) $tipo = 'cpf_cnpj';
        elseif (preg_match('~\bcpf\b~u', $l)) $tipo = 'cpf';
        elseif (preg_match('~\bcnpj\b~u', $l)) $tipo = 'cnpj';
        elseif (preg_match('~\bcep\b~u', $l)) $tipo = 'cep';
        elseif (preg_match('~^data\b|\bdata d[eo]~u', $l)) $tipo = 'data';
        elseif (preg_match('~matr[ií]cula~u', $l) && strlen(iris_so_digitos($val)) === 32) $tipo = 'matricula_certidao';
        $v = iris_validar_valor($tipo, $val);
        $conf = iris_conf($c['confianca'] ?? 'media');
        $cont['total']++; if ($v['valor'] !== null) $cont['preenchidos']++;
        if ($v['ok'] === false) $cont['invalidos']++; if ($conf === 'baixa') $cont['baixa']++;
        $campos[$k] = ['chave' => $k, 'rotulo' => $rot, 'tipo' => $tipo, 'valor' => $v['valor'], 'confianca' => $conf, 'pagina' => null,
                       'validacao' => ['ok' => $v['ok'], 'msg' => $v['msg']]];
    }
    return ['slug' => 'livre', 'nome' => trim((string)($resp['tipo_documento'] ?? '')) ?: 'Documento', 'campos' => $campos, 'ordem' => $ordem,
            'observacoes' => trim((string)($resp['observacoes'] ?? '')), 'avisos' => [], 'resumo' => $cont];
}
