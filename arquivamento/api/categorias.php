<?php
/**
 * API · Categorias.
 *   GET  ?acao=listar
 *   POST acao=criar|renomear|excluir
 */
require_once __DIR__ . '/../bootstrap.php';
arq_exige_login();

$acao = isset($_REQUEST['acao']) ? (string) $_REQUEST['acao'] : 'listar';

if ($acao === 'listar') {
    $cats = arq_categorias();
    $uso  = arq_uso_categorias();
    $saida = [];
    foreach ($cats as $c) {
        $saida[] = ['nome' => $c, 'uso' => isset($uso[$c]) ? $uso[$c] : 0];
    }
    arq_ok(['categorias' => $saida]);
}

arq_exige_post_seguro();

$cats = arq_categorias();
$nome = trim(preg_replace('/\s+/u', ' ', (string) (isset($_POST['nome']) ? $_POST['nome'] : '')));
$nome = mb_substr($nome, 0, 120);

switch ($acao) {

    case 'criar':
        if ($nome === '') { arq_erro('Informe o nome da categoria.', 422); }
        foreach ($cats as $c) {
            if (arq_normalizar_texto($c) === arq_normalizar_texto($nome)) {
                arq_erro('Já existe uma categoria com esse nome.', 409);
            }
        }
        $cats[] = $nome;
        if (!arq_gravar_categorias($cats)) { arq_erro('Falha ao gravar a lista de categorias.', 500); }
        arq_auditar('categoria', $nome, ['acao' => 'criar']);
        arq_ok(['mensagem' => 'Categoria criada.']);
        break;

    case 'renomear':
        $antigo = trim((string) (isset($_POST['antigo']) ? $_POST['antigo'] : ''));
        if ($antigo === '' || $nome === '') { arq_erro('Informe o nome atual e o novo nome.', 422); }
        $pos = array_search($antigo, $cats, true);
        if ($pos === false) { arq_erro('Categoria não encontrada.', 404); }
        $cats[$pos] = $nome;
        if (!arq_gravar_categorias($cats)) { arq_erro('Falha ao gravar a lista de categorias.', 500); }

        // Reetiqueta os arquivamentos que usavam o nome antigo.
        $alterados = 0;
        foreach (glob(arq_dir_meta() . '/*.json') ?: [] as $arquivo) {
            $d = json_decode(@file_get_contents($arquivo), true);
            if (!is_array($d) || !isset($d['categoria']) || $d['categoria'] !== $antigo) { continue; }
            $d['categoria'] = $nome;
            @file_put_contents($arquivo, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            $alterados++;
        }
        arq_invalidar_indice();
        arq_auditar('categoria', $nome, ['acao' => 'renomear', 'de' => $antigo, 'registros' => $alterados]);
        arq_ok(['mensagem' => 'Categoria renomeada.', 'registros_atualizados' => $alterados]);
        break;

    case 'excluir':
        if ($nome === '') { arq_erro('Informe a categoria.', 422); }
        $uso = arq_uso_categorias();
        if (!empty($uso[$nome])) {
            arq_erro('Esta categoria está em uso por ' . $uso[$nome] . ' arquivamento(s). Renomeie-a ou reclassifique os registros antes de excluir.', 409);
        }
        $pos = array_search($nome, $cats, true);
        if ($pos === false) { arq_erro('Categoria não encontrada.', 404); }
        array_splice($cats, $pos, 1);
        if (!arq_gravar_categorias($cats)) { arq_erro('Falha ao gravar a lista de categorias.', 500); }
        arq_auditar('categoria', $nome, ['acao' => 'excluir']);
        arq_ok(['mensagem' => 'Categoria excluída.']);
        break;

    default:
        arq_erro('Ação desconhecida.', 400);
}
