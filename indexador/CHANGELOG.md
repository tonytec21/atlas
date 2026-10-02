# Indexador — v2.0.0

## Instalação
Substitua a pasta `indexador/` do Atlas pelo conteúdo deste pacote. Não há alteração de banco de dados.
As pastas `*/anexos/` existentes no servidor devem ser preservadas (o pacote não traz anexos).
Requer PHP 8.0 ou superior (extensões mysqli, dom, libxml, mbstring).

## Telas padronizadas
- Nascimento, Casamento e Óbito agora usam a mesma tela, gerada de uma única configuração (`_core/tipos.php`).
- Pesquisa com filtros principais e "Mais filtros", paginação no servidor, ordenação por coluna, filtros guardados na URL.
- Cadastro/edição em tela ampla com o documento anexado ao lado (digitar olhando o termo), arrastar e soltar PDF/imagem.
- "Salvar e novo": mantém livro, folha e data e já sugere o próximo termo. Sugestão do último termo indexado em cada livro.
- Visualização em painel lateral com dados, matrícula (copiar), quem cadastrou e anexos.
- Atalhos: Alt+N novo, Ctrl+S salvar, Ctrl+Enter salvar e novo, / buscar, Esc fechar.
- Totalmente responsivo (tabela vira cartões no celular) e com tema claro/escuro do Atlas.
- Navegação única entre Nascimento, Casamento, Óbito, Carga CRC e Validar XML em todas as telas do módulo.

## Validação com o XSD da CRC no cadastro
- Cada registro é montado exatamente como sairá na carga (`_core/crc.php`) e validado contra `validar_xml/catalogo-crc.xsd` enquanto é digitado e novamente ao salvar.
- Regras que o XSD não cobre e a CRC recusa: datas coerentes (nascimento/casamento/óbito x registro), código IBGE de 7 dígitos, livro até 5 dígitos, folha até 3, termo até 7 (matrícula de 32 dígitos), nomes sem números/símbolos e com no máximo 100 caracteres, CNS cadastrado.
- Prévia da matrícula em blocos (CNS, acervo, ano, tipo, livro, folha, termo, DV) apontando o trecho com problema.
- Registros com erro não são gravados; alertas (ex.: nome com uma palavra, termo já existente no livro) pedem confirmação.
- "Pendências CRC": verifica todos os registros (inclusive os importados em lote) e abre cada um para correção.

## Carga CRC
- Tela única de exportação para os três tipos (`carga_crc/index.php`), com seleção de todos os resultados (não só da página), validação prévia e opção de gerar apenas os válidos.
- IDs enviados em um único campo: elimina o limite "max_input_vars" em cargas grandes.
- Geradores (`gerar_carga*.php`) usam a mesma montagem da validação. Saída idêntica à anterior, com duas correções:
  - Óbito: a data de nascimento de falecidos nascidos antes de 1970 deixava de ser enviada.
  - Casamento: nomes com "&" ou apóstrofo saíam com escape duplicado (`&amp;amp;`).

## Outras correções
- Casamento: o filtro "Nome do cônjuge" era ignorado na pesquisa.
- Nascimento: "Registro duplicado → continuar" não gravava o registro.
- Casamento: registro excluído impedia recadastrar a mesma matrícula.
- Anexos com o mesmo nome não se sobrescrevem mais.
- Exclusão restrita a administradores também no servidor; endpoints antigos passaram a exigir sessão.
