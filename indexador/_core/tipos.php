<?php
/**
 * Definição única dos três tipos de ato do Indexador.
 * Tudo que é comum (formulário, listagem, filtros, validação e anexos) é gerado
 * a partir desta configuração — assim Nascimento, Casamento e Óbito ficam
 * padronizados e qualquer ajuste vale para os três.
 *
 * Campos:
 *  type     int | date | time | name | select | city
 *  span     colunas (de 12) no desktop
 *  digits   máximo de dígitos (int)
 *  max      máximo de caracteres (limite do XSD da CRC)
 *  ibge     coluna que guarda o código IBGE (type=city)
 */

$SEXO = ['M' => 'Masculino', 'F' => 'Feminino', 'I' => 'Ignorado'];

$REGISTRO_FIELDS = [
    'livro'         => ['label' => 'Livro', 'type' => 'int', 'required' => true, 'digits' => 5, 'span' => 2, 'section' => 'registro'],
    'folha'         => ['label' => 'Folha', 'type' => 'int', 'required' => true, 'digits' => 3, 'span' => 2, 'section' => 'registro'],
    'termo'         => ['label' => 'Termo', 'type' => 'int', 'required' => true, 'digits' => 7, 'span' => 2, 'section' => 'registro'],
    'data_registro' => ['label' => 'Data do registro', 'type' => 'date', 'required' => true, 'span' => 3, 'section' => 'registro', 'not_future' => true],
];

return [

    /* =================================================================== */
    'nascimento' => [
        'label'        => 'Nascimento',
        'plural'       => 'Nascimentos',
        'title'        => 'Indexador de Nascimento',
        'icon'         => 'baby',
        'accent'       => 'nasc',
        'table'        => 'indexador_nascimento',
        'col_cadastro' => 'data_cadastro',
        'status_ativo' => 'ativo',
        'status_removido' => ['removido', 'inativo'],
        'anexos' => [
            'table'  => 'indexador_nascimento_anexos',
            'fk'     => 'id_nascimento',
            'ativo'  => 'ativo',
            'remove' => ['mode' => 'status', 'value' => 'removido'],
            'dir'    => 'anexos/{id}/',
        ],
        'tipo_livro'  => '1',
        'nome_field'  => 'nome_registrado',
        'crc_xml'     => 'nascimento',
        'sections' => [
            'registro'   => ['label' => 'Dados do registro', 'icon' => 'book'],
            'registrado' => ['label' => 'Registrado', 'icon' => 'user'],
            'filiacao'   => ['label' => 'Filiação', 'icon' => 'users'],
        ],
        'fields' => $REGISTRO_FIELDS + [
            'nome_registrado' => ['label' => 'Nome do registrado', 'type' => 'name', 'required' => true, 'max' => 100, 'span' => 12, 'section' => 'registrado'],
            'data_nascimento' => ['label' => 'Data de nascimento', 'type' => 'date', 'required' => true, 'span' => 3, 'section' => 'registrado', 'not_future' => true],
            'sexo'            => ['label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => $SEXO, 'span' => 3, 'section' => 'registrado'],
            'naturalidade'    => ['label' => 'Naturalidade (município de nascimento)', 'type' => 'city', 'ibge' => 'ibge_naturalidade', 'required' => true, 'span' => 6, 'section' => 'registrado'],
            'nome_pai'        => ['label' => 'Nome do pai', 'type' => 'name', 'required' => false, 'max' => 100, 'span' => 6, 'section' => 'filiacao'],
            'nome_mae'        => ['label' => 'Nome da mãe', 'type' => 'name', 'required' => true, 'max' => 100, 'span' => 6, 'section' => 'filiacao'],
        ],
        'columns' => [
            ['key' => 'termo', 'label' => 'Termo', 'sort' => 'termo', 'mono' => true],
            ['key' => 'livro', 'label' => 'Livro', 'sort' => 'livro', 'mono' => true],
            ['key' => 'folha', 'label' => 'Folha', 'sort' => 'folha', 'mono' => true],
            ['key' => 'nome_registrado', 'label' => 'Registrado', 'sort' => 'nome_registrado', 'primary' => true, 'sub' => 'filiacao'],
            ['key' => 'data_nascimento', 'label' => 'Nascimento', 'sort' => 'data_nascimento', 'date' => true],
            ['key' => 'data_registro', 'label' => 'Registro', 'sort' => 'data_registro', 'date' => true],
        ],
        'filters' => [
            ['key' => 'q',        'label' => 'Nome do registrado', 'type' => 'text', 'cols' => ['nome_registrado'], 'span' => 6, 'main' => true],
            ['key' => 'termo',    'label' => 'Termo', 'type' => 'int', 'col' => 'termo', 'span' => 2, 'main' => true],
            ['key' => 'livro',    'label' => 'Livro', 'type' => 'livro', 'col' => 'livro', 'span' => 2, 'main' => true],
            ['key' => 'folha',    'label' => 'Folha', 'type' => 'int', 'col' => 'folha', 'span' => 2, 'main' => true],
            ['key' => 'pai',      'label' => 'Nome do pai', 'type' => 'text', 'cols' => ['nome_pai'], 'span' => 6],
            ['key' => 'mae',      'label' => 'Nome da mãe', 'type' => 'text', 'cols' => ['nome_mae'], 'span' => 6],
            ['key' => 'nasc',     'label' => 'Data de nascimento', 'type' => 'daterange', 'col' => 'data_nascimento', 'span' => 6],
            ['key' => 'reg',      'label' => 'Data de registro', 'type' => 'daterange', 'col' => 'data_registro', 'span' => 6],
            ['key' => 'matricula','label' => 'Matrícula', 'type' => 'text', 'cols' => ['matricula'], 'span' => 6],
            ['key' => 'func',     'label' => 'Cadastrado por', 'type' => 'user', 'col' => 'funcionario', 'span' => 6],
        ],
    ],

    /* =================================================================== */
    'casamento' => [
        'label'        => 'Casamento',
        'plural'       => 'Casamentos',
        'title'        => 'Indexador de Casamento',
        'icon'         => 'rings',
        'accent'       => 'casa',
        'table'        => 'indexador_casamento',
        'col_cadastro' => 'criado_em',
        'status_ativo' => 'ativo',
        'status_removido' => ['removido', 'inativo'],
        'anexos' => [
            'table'  => 'indexador_casamento_anexos',
            'fk'     => 'id_casamento',
            'ativo'  => 'ativo',
            'remove' => ['mode' => 'delete'],
            'dir'    => 'anexos/{id}/',
        ],
        'tipo_livro'  => ['field' => 'tipo_casamento', 'map' => ['CIVIL' => '2', 'RELIGIOSO' => '3']],
        'nome_field'  => 'conjuge1_nome',
        'crc_xml'     => 'casamento',
        'sections' => [
            'registro' => ['label' => 'Dados do registro', 'icon' => 'book'],
            'conjuge1' => ['label' => '1º cônjuge', 'icon' => 'user'],
            'conjuge2' => ['label' => '2º cônjuge', 'icon' => 'user'],
            'ato'      => ['label' => 'Casamento', 'icon' => 'rings'],
        ],
        'fields' => [
            'livro'         => $REGISTRO_FIELDS['livro'],
            'folha'         => $REGISTRO_FIELDS['folha'],
            'termo'         => $REGISTRO_FIELDS['termo'],
            'tipo_casamento'=> ['label' => 'Tipo', 'type' => 'select', 'required' => true, 'options' => ['CIVIL' => 'Civil', 'RELIGIOSO' => 'Religioso com efeito civil'], 'span' => 3, 'section' => 'registro'],
            'data_registro' => $REGISTRO_FIELDS['data_registro'],
            'conjuge1_nome'        => ['label' => 'Nome de solteiro(a)', 'type' => 'name', 'required' => true, 'max' => 100, 'span' => 9, 'section' => 'conjuge1'],
            'conjuge1_sexo'        => ['label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => $SEXO, 'span' => 3, 'section' => 'conjuge1'],
            'conjuge1_nome_casado' => ['label' => 'Nome após o casamento', 'type' => 'name', 'required' => false, 'max' => 100, 'span' => 12, 'section' => 'conjuge1', 'hint' => 'Deixe em branco se não houve alteração.'],
            'conjuge2_nome'        => ['label' => 'Nome de solteiro(a)', 'type' => 'name', 'required' => true, 'max' => 100, 'span' => 9, 'section' => 'conjuge2'],
            'conjuge2_sexo'        => ['label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => $SEXO, 'span' => 3, 'section' => 'conjuge2'],
            'conjuge2_nome_casado' => ['label' => 'Nome após o casamento', 'type' => 'name', 'required' => false, 'max' => 100, 'span' => 12, 'section' => 'conjuge2', 'hint' => 'Deixe em branco se não houve alteração.'],
            'regime_bens'   => ['label' => 'Regime de bens', 'type' => 'select', 'required' => true, 'span' => 8, 'section' => 'ato', 'options' => [
                'COMUNHAO_PARCIAL'            => 'Comunhão parcial de bens',
                'COMUNHAO_UNIVERSAL'          => 'Comunhão universal de bens',
                'PARTICIPACAO_FINAL_AQUESTOS' => 'Participação final nos aquestos',
                'SEPARACAO_BENS'              => 'Separação de bens',
                'SEPARACAO_LEGAL_BENS'        => 'Separação legal (obrigatória) de bens',
                'OUTROS'                      => 'Outros',
                'IGNORADO'                    => 'Ignorado',
            ]],
            'data_casamento'=> ['label' => 'Data do casamento', 'type' => 'date', 'required' => true, 'span' => 4, 'section' => 'ato', 'not_future' => true],
        ],
        'columns' => [
            ['key' => 'termo', 'label' => 'Termo', 'sort' => 'termo', 'mono' => true],
            ['key' => 'livro', 'label' => 'Livro', 'sort' => 'livro', 'mono' => true],
            ['key' => 'folha', 'label' => 'Folha', 'sort' => 'folha', 'mono' => true],
            ['key' => 'conjuges', 'label' => 'Cônjuges', 'sort' => 'conjuge1_nome', 'primary' => true, 'sub' => 'casados'],
            ['key' => 'tipo_casamento', 'label' => 'Tipo', 'sort' => 'tipo_casamento', 'badge' => true],
            ['key' => 'data_casamento', 'label' => 'Casamento', 'sort' => 'data_casamento', 'date' => true],
            ['key' => 'data_registro', 'label' => 'Registro', 'sort' => 'data_registro', 'date' => true],
        ],
        'filters' => [
            ['key' => 'q',      'label' => 'Nome do cônjuge (1º ou 2º)', 'type' => 'text', 'cols' => ['conjuge1_nome', 'conjuge2_nome'], 'span' => 6, 'main' => true],
            ['key' => 'termo',  'label' => 'Termo', 'type' => 'int', 'col' => 'termo', 'span' => 2, 'main' => true],
            ['key' => 'livro',  'label' => 'Livro', 'type' => 'livro', 'col' => 'livro', 'span' => 2, 'main' => true],
            ['key' => 'folha',  'label' => 'Folha', 'type' => 'int', 'col' => 'folha', 'span' => 2, 'main' => true],
            ['key' => 'casado', 'label' => 'Nome de casado (1º ou 2º)', 'type' => 'text', 'cols' => ['conjuge1_nome_casado', 'conjuge2_nome_casado'], 'span' => 6],
            ['key' => 'tipo',   'label' => 'Tipo', 'type' => 'select', 'col' => 'tipo_casamento', 'options_from' => 'tipo_casamento', 'span' => 3],
            ['key' => 'regime', 'label' => 'Regime de bens', 'type' => 'select', 'col' => 'regime_bens', 'options_from' => 'regime_bens', 'span' => 3],
            ['key' => 'cas',    'label' => 'Data do casamento', 'type' => 'daterange', 'col' => 'data_casamento', 'span' => 6],
            ['key' => 'reg',    'label' => 'Data de registro', 'type' => 'daterange', 'col' => 'data_registro', 'span' => 6],
            ['key' => 'matricula','label' => 'Matrícula', 'type' => 'text', 'cols' => ['matricula'], 'span' => 6],
            ['key' => 'func',   'label' => 'Cadastrado por', 'type' => 'user', 'col' => 'funcionario', 'span' => 6],
        ],
    ],

    /* =================================================================== */
    'obito' => [
        'label'        => 'Óbito',
        'plural'       => 'Óbitos',
        'title'        => 'Indexador de Óbito',
        'icon'         => 'cross',
        'accent'       => 'obito',
        'table'        => 'indexador_obito',
        'col_cadastro' => 'data_cadastro',
        'status_ativo' => 'A',
        'status_removido' => ['removido', 'R'],
        'anexos' => [
            'table'  => 'indexador_obito_anexos',
            'fk'     => 'id_obito',
            'ativo'  => 'A',
            'remove' => ['mode' => 'status', 'value' => 'R'],
            'dir'    => 'anexos/obitos/{id}/',
        ],
        'tipo_livro'  => '4',
        'nome_field'  => 'nome_registrado',
        'crc_xml'     => 'obito',
        'sections' => [
            'registro'   => ['label' => 'Dados do registro', 'icon' => 'book'],
            'falecido'   => ['label' => 'Falecido(a)', 'icon' => 'user'],
            'filiacao'   => ['label' => 'Filiação', 'icon' => 'users'],
            'local'      => ['label' => 'Localidades', 'icon' => 'pin'],
        ],
        'fields' => $REGISTRO_FIELDS + [
            'nome_registrado' => ['label' => 'Nome do(a) falecido(a)', 'type' => 'name', 'required' => true, 'max' => 100, 'span' => 12, 'section' => 'falecido'],
            'data_nascimento' => ['label' => 'Data de nascimento', 'type' => 'date', 'required' => false, 'span' => 4, 'section' => 'falecido', 'not_future' => true, 'hint' => 'Usada também para calcular a idade na carga.'],
            'data_obito'      => ['label' => 'Data do óbito', 'type' => 'date', 'required' => true, 'span' => 4, 'section' => 'falecido', 'not_future' => true],
            'hora_obito'      => ['label' => 'Hora do óbito', 'type' => 'time', 'required' => false, 'span' => 4, 'section' => 'falecido', 'hint' => 'Se ignorada, a carga envia 00:00.'],
            'nome_pai'        => ['label' => 'Nome do pai', 'type' => 'name', 'required' => false, 'max' => 100, 'span' => 6, 'section' => 'filiacao'],
            'nome_mae'        => ['label' => 'Nome da mãe', 'type' => 'name', 'required' => false, 'max' => 100, 'span' => 6, 'section' => 'filiacao'],
            'cidade_endereco' => ['label' => 'Município de residência', 'type' => 'city', 'ibge' => 'ibge_cidade_endereco', 'required' => true, 'span' => 6, 'section' => 'local'],
            'cidade_obito'    => ['label' => 'Município do óbito', 'type' => 'city', 'ibge' => 'ibge_cidade_obito', 'required' => true, 'span' => 6, 'section' => 'local'],
        ],
        'columns' => [
            ['key' => 'termo', 'label' => 'Termo', 'sort' => 'termo', 'mono' => true],
            ['key' => 'livro', 'label' => 'Livro', 'sort' => 'livro', 'mono' => true],
            ['key' => 'folha', 'label' => 'Folha', 'sort' => 'folha', 'mono' => true],
            ['key' => 'nome_registrado', 'label' => 'Falecido(a)', 'sort' => 'nome_registrado', 'primary' => true, 'sub' => 'filiacao'],
            ['key' => 'data_obito', 'label' => 'Óbito', 'sort' => 'data_obito', 'date' => true],
            ['key' => 'data_registro', 'label' => 'Registro', 'sort' => 'data_registro', 'date' => true],
        ],
        'filters' => [
            ['key' => 'q',        'label' => 'Nome do(a) falecido(a)', 'type' => 'text', 'cols' => ['nome_registrado'], 'span' => 6, 'main' => true],
            ['key' => 'termo',    'label' => 'Termo', 'type' => 'int', 'col' => 'termo', 'span' => 2, 'main' => true],
            ['key' => 'livro',    'label' => 'Livro', 'type' => 'livro', 'col' => 'livro', 'span' => 2, 'main' => true],
            ['key' => 'folha',    'label' => 'Folha', 'type' => 'int', 'col' => 'folha', 'span' => 2, 'main' => true],
            ['key' => 'pai',      'label' => 'Nome do pai', 'type' => 'text', 'cols' => ['nome_pai'], 'span' => 6],
            ['key' => 'mae',      'label' => 'Nome da mãe', 'type' => 'text', 'cols' => ['nome_mae'], 'span' => 6],
            ['key' => 'obi',      'label' => 'Data do óbito', 'type' => 'daterange', 'col' => 'data_obito', 'span' => 6],
            ['key' => 'reg',      'label' => 'Data de registro', 'type' => 'daterange', 'col' => 'data_registro', 'span' => 6],
            ['key' => 'matricula','label' => 'Matrícula', 'type' => 'text', 'cols' => ['matricula'], 'span' => 6],
            ['key' => 'func',     'label' => 'Cadastrado por', 'type' => 'user', 'col' => 'funcionario', 'span' => 6],
        ],
    ],
];
