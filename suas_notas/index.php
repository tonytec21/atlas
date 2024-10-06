<?php
// Inclui os arquivos de conexão e sessão
include 'db_connection.php';
include 'session_check.php';
checkSession();
date_default_timezone_set('America/Sao_Paulo');

// Obtém o nome do usuário da sessão
$username = $_SESSION['username'];

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $conteudo = $_POST['conteudo'] ?? '';

    // Cria o diretório para o usuário, se não existir
    $userDirectory = 'lembretes/' . $username;
    if (!file_exists($userDirectory)) {
        mkdir($userDirectory, 0777, true);
    }

    // Salva o lembrete em um arquivo
    $arquivoNome = $userDirectory . '/' . time() . '.txt';
    $conteudoLembrete = "Título: $titulo\n\nConteúdo:\n$conteudo";
    file_put_contents($arquivoNome, $conteudoLembrete);
    
    // Retorna a notificação para exibição na página
    echo "<div class='notification'>Lembrete criado com sucesso!<button class='close-btn'>&times;</button></div>";
    exit(); // Termina a execução para evitar que o HTML da página seja retornado
}

// Diretórios do usuário e da lixeira
$userDirectory = 'lembretes/' . $username;
$lixeiraDirectory = 'lixeira/' . $username;
$orderFile = $userDirectory . '/order.json';

// Verifica se os diretórios do usuário e da lixeira existem, se não, cria
if (!file_exists($userDirectory)) {
    mkdir($userDirectory, 0777, true);
}
if (!file_exists($lixeiraDirectory)) {
    mkdir($lixeiraDirectory, 0777, true);
}

// Lista os arquivos de lembrete no diretório do usuário
$arquivos = glob($userDirectory . '/*.txt');

// Carrega a ordem e grupos dos lembretes do arquivo JSON
$orderData = [];
if (file_exists($orderFile)) {
    $orderData = json_decode(file_get_contents($orderFile), true);
}

// Ordena os arquivos conforme o JSON ou coloca no grupo "Novos"
$groupedFiles = isset($orderData['groups']) ? $orderData['groups'] : ['Novos' => []];
foreach ($arquivos as $arquivo) {
    $filename = basename($arquivo);
    $found = false;
    foreach ($groupedFiles as $groupName => $files) {
        if (in_array($filename, $files)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $groupedFiles['Novos'][] = $filename;
    }
}

$orderData['groups'] = $groupedFiles;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Visualizar Notas</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <style>
        .create-group-expansion {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px; /* Espaçamento entre os elementos */
            margin-top: 50px;
            margin-bottom: 50px;
        }

        .create-group-expansion a {
            color: #6c6b6b;
            text-decoration: none;
            font-size: 1em;
            cursor: pointer;
            transition: color 0.3s;
        }

        .create-group-expansion a:hover {
            color: #0056b3;
        }

        .line {
            flex-grow: 1;
            border: none;
            border-top: 1px solid #ccc;
            margin: 0 10px;
        }

        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: flex-start;
            margin-bottom: 20px;
            min-height: 50px; /* Adiciona altura mínima para facilitar o drop */
            border: 1px dashed transparent; /* Borda invisível que aparecerá quando um card for arrastado */
        }
        .card-container.drag-over {
            border: 1px dashed #007bff; /* Borda visível ao arrastar */
        }
        .card {
            width: 250px;
            height: 250px; /* Aumenta a altura do card */
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            cursor: grab;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-content {
            flex: 1; /* Permite que o conteúdo ocupe todo o espaço disponível */
            overflow-y: auto; /* Habilita a rolagem vertical */
            max-height: 200px; /* Limita a altura do conteúdo para que a barra de rolagem vertical apareça */
            word-wrap: break-word; /* Quebra as palavras longas */
            white-space: normal; /* Permite a quebra de linha */
        }


        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 80%;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .group-container {
            margin-top: 20px;
        }
        .group-name {
            font-size: 1.3em;
            cursor: pointer;
        }
        .search-bar {
            /* margin-bottom: 20px; */
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . '/../menu.php'); ?>
    <div id="main" class="main-content">
        <div class="container">
            <h3 class="mt-4">Suas Notas</h3>

            <!-- Barra de pesquisa e botão "Nova Nota" -->
            <div class="d-flex align-items-center mb-4">
                <input type="text" id="searchBar" class="form-control search-bar mr-2" placeholder="Pesquisar notas...">
                <button class="btn btn-success" id="novaNotaBtn" style="flex: 0 0 10%;"><i class="fa fa-plus-circle" aria-hidden="true"></i> Nova Nota</button>
            </div>

            <hr>

            <!-- Container de grupos -->
            <div id="group-container" class="group-container">
                <?php
                // Verifica se o grupo "Novos" contém algum card
                if (!empty($groupedFiles['Novos'])) {
                    // Renderiza o grupo "Novos" primeiro
                    echo '<div class="group">';
                    echo '<div class="group-header" onclick="toggleGroup(this)">';
                    echo '<span class="group-name" contenteditable="true" onblur="editGroupName(\'Novos\', this.innerText)">Novos</span>';
                    echo '<i class="fa fa-chevron-down toggle-icon"></i>';
                    echo '</div>';
                    echo '<div class="card-container" data-group="Novos">';

                    foreach ($groupedFiles['Novos'] as $filename) {
                        if (file_exists($userDirectory . '/' . $filename)) {
                            $conteudo = file_get_contents($userDirectory . '/' . $filename);
                            $linhas = explode("\n", $conteudo);
                            $titulo = '';
                            $corpo = '';

                            foreach ($linhas as $linha) {
                                if (stripos($linha, 'Título:') === 0) {
                                    $titulo = trim(substr($linha, strlen('Título:')));
                                } elseif (stripos($linha, 'Conteúdo:') === 0) {
                                    $corpo = trim(substr($linha, strlen('Conteúdo:'))) . "\n";
                                } else {
                                    $corpo .= $linha . "\n";
                                }
                            }

                            echo '<div class="card" draggable="true" data-filename="' . $filename . '" onclick="openModal(\'' . $filename . '\')">';
                            echo '<h6><strong>' . htmlspecialchars($titulo) . '</strong></h6>';
                            echo '<div class="card-content">';
                            echo '<div>' . nl2br(htmlspecialchars($corpo)) . '</div>';
                            echo '</div>';
                            echo '<button class="btn-delete mt-2" onclick="event.stopPropagation(); moveToTrash(\'' . $filename . '\')"><i class="fa fa-trash" aria-hidden="true"></i></button>';
                            echo '</div>';
                        }
                    }

                    echo '</div>'; // Fecha .card-container
                    echo '</div>'; // Fecha .group
                }

                // Renderiza os outros grupos
                foreach ($orderData['groups'] as $groupName => $group) {
                    // Pula o grupo "Novos" para evitar duplicação
                    if ($groupName === 'Novos') {
                        continue;
                    }

                    echo '<div class="group">';
                    echo '<div class="group-header">'; // Removendo o onclick daqui para controle manual do ícone

                    // Adiciona o nome do grupo e a linha horizontal
                    echo '<span class="group-name" contenteditable="true" onblur="editGroupName(\'' . $groupName . '\', this.innerText)">' . htmlspecialchars($groupName) . '</span>';
                    echo '<hr style="flex-grow: 1; border: none; border-top: 1px solid #ccc; margin: 0 10px;">'; // Linha horizontal

                    // Verifica se o grupo está vazio para exibir o ícone apropriado
                    if (empty($group)) {
                        $cleanGroupName = str_replace(["\n", "\r"], ' ', $groupName);
                        echo '<i class="fa fa-times" aria-hidden="true" data-group-name="' . htmlspecialchars($cleanGroupName, ENT_QUOTES, 'UTF-8') . '" onclick="deleteGroup(this)"></i>';
                        

                    } else {
                        echo '<i class="fa fa-chevron-down toggle-icon" onclick="toggleGroup(this)"></i>';
                    }

                    echo '</div>';
                    echo '<div class="card-container" data-group="' . $groupName . '">';

                    foreach ($group as $filename) {
                        if (file_exists($userDirectory . '/' . $filename)) {
                            $conteudo = file_get_contents($userDirectory . '/' . $filename);
                            $linhas = explode("\n", $conteudo);
                            $titulo = '';
                            $corpo = '';

                            foreach ($linhas as $linha) {
                                if (stripos($linha, 'Título:') === 0) {
                                    $titulo = trim(substr($linha, strlen('Título:')));
                                } elseif (stripos($linha, 'Conteúdo:') === 0) {
                                    $corpo = trim(substr($linha, strlen('Conteúdo:'))) . "\n";
                                } else {
                                    $corpo .= $linha . "\n";
                                }
                            }

                            echo '<div class="card" draggable="true" data-filename="' . $filename . '" onclick="openModal(\'' . $filename . '\')">';
                            echo '<h6><strong>' . htmlspecialchars($titulo) . '</strong></h6>';
                            echo '<div class="card-content">';
                            echo '<div>' . nl2br(htmlspecialchars($corpo)) . '</div>';
                            echo '</div>';
                            echo '<button class="btn-delete mt-2" onclick="event.stopPropagation(); moveToTrash(\'' . $filename . '\')"><i class="fa fa-trash" aria-hidden="true"></i></button>';
                            echo '</div>';
                        }
                    }

                    echo '</div>'; // Fecha .card-container
                    echo '</div>'; // Fecha .group
                }
                ?>
            </div>
            <!-- Novo "Criar Novo Grupo" com linhas horizontais -->
            <div id="create-group-expansion" class="create-group-expansion">
                <hr class="line"> <!-- Linha horizontal esquerda -->
                <a href="javascript:void(0);" onclick="createGroup()">+ Criar Novo Grupo</a>
                <hr class="line"> <!-- Linha horizontal direita -->
            </div>

        </div>
    </div>

    <!-- Modal para visualização e edição do lembrete -->
    <div class="modal fade" id="noteModal" tabindex="-1" role="dialog" aria-labelledby="noteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="width: 40%">
            <div class="modal-content">
                <form id="editNoteForm" method="post" class="mt-4">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Lembrete</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="noteTitle" class="form-label">Título</label>
                            <input type="text" id="noteTitle" name="titulo" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="noteContent" class="form-label">Conteúdo</label>
                            <textarea id="noteContent" name="conteudo" rows="5" class="form-control" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" onclick="saveNote()">Salvar</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para criar nova nota -->
    <div class="modal fade" id="novaNotaModal" tabindex="-1" role="dialog" aria-labelledby="novaNotaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="width: 40%">
            <div class="modal-content">
                <form id="novaNotaForm" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="novaNotaModalLabel">Criar Nota</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título</label>
                            <input type="text" id="titulo" name="titulo" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="conteudo" class="form-label">Conteúdo</label>
                            <textarea id="conteudo" name="conteudo" rows="5" class="form-control" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" id="salvarNotaBtn">Criar Nota</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script>

        // Abrir o modal ao clicar no botão "Nova Nota"
        $('#novaNotaBtn').on('click', function() {
            $('#novaNotaModal').modal('show');
        });

        // Função para fechar a notificação
        $('.notification .close-btn').on('click', function() {
            $(this).parent().hide();
        });

        // Salvar nota via AJAX ao clicar no botão "Criar Lembrete" no modal
        $('#salvarNotaBtn').on('click', function() {
            var titulo = $('#titulo').val();
            var conteudo = $('#conteudo').val();

            $.ajax({
                url: '', // A URL para o mesmo arquivo
                method: 'POST',
                data: {
                    titulo: titulo,
                    conteudo: conteudo
                },
                success: function(response) {
                    // Exibir a notificação de sucesso
                    $('body').append(response);
                    
                    // Limpar os campos do formulário e fechar o modal
                    $('#novaNotaForm')[0].reset();
                    $('#novaNotaModal').modal('hide');
                },
                error: function() {
                    alert('Erro ao criar o lembrete.');
                }
            });
        });
        
        // Função para mover o arquivo para a lixeira com confirmação
        function moveToTrash(filename) {
            if (confirm('Tem certeza que deseja excluir esta nota?')) {
                $.ajax({
                    url: 'move_to_trash.php',
                    method: 'POST',
                    data: { filename: filename },
                    success: function(response) {
                        if (response === 'success') {
                            document.querySelector(`.card[data-filename='${filename}']`).remove();
                        } else {
                            alert('Erro ao mover para a lixeira.');
                        }
                    },
                    error: function() {
                        alert('Erro ao mover para a lixeira.');
                    }
                });
            }
        }

        // Função para alternar a exibição dos grupos
        function toggleGroup(header) {
            // Seleciona o grupo ao qual o cabeçalho pertence
            const group = header.closest('.group');
            if (!group) {
                console.error('Erro: O grupo não foi encontrado.');
                return;
            }

            // Seleciona o contêiner dentro do grupo
            const container = group.querySelector('.card-container');
            if (container) {
                // Alterna a exibição do contêiner
                container.style.display = container.style.display === 'none' ? 'flex' : 'none';

                // Alterna o ícone
                const icon = header.querySelector('.toggle-icon');
                if (icon) {
                    icon.classList.toggle('fa-chevron-down');
                    icon.classList.toggle('fa-chevron-up');
                }
            } else {
                console.error('Erro: O container do grupo não foi encontrado.');
            }
        }

        // Função para criar um novo grupo de cards
        function createGroup() {
            const groupName = prompt('Digite o nome do novo grupo:');
            if (groupName) {
                // Cria o div do grupo
                const groupDiv = document.createElement('div');
                groupDiv.classList.add('group');

                // Cria o cabeçalho do grupo
                const groupHeader = document.createElement('div');
                groupHeader.classList.add('group-header');
                groupHeader.onclick = function() { toggleGroup(groupHeader); };

                // Nome do grupo
                const groupNameSpan = document.createElement('span');
                groupNameSpan.classList.add('group-name');
                groupNameSpan.setAttribute('contenteditable', 'true');
                groupNameSpan.innerText = groupName;
                groupNameSpan.onblur = function() { editGroupName(groupName, groupNameSpan.innerText); };
                groupHeader.appendChild(groupNameSpan);

                // Ícone de alternância
                const toggleIcon = document.createElement('i');
                toggleIcon.classList.add('fa', 'fa-chevron-down', 'toggle-icon');
                groupHeader.appendChild(toggleIcon);

                // Adiciona o cabeçalho ao grupo
                groupDiv.appendChild(groupHeader);

                // Cria o contêiner para os cartões
                const container = document.createElement('div');
                container.classList.add('card-container');
                container.style.display = 'flex'; // Inicialmente definido como flex
                groupDiv.appendChild(container);

                // Adiciona eventos de arrastar e soltar
                addDragAndDropEvents(container);

                // Adiciona o grupo ao contêiner principal
                document.getElementById('group-container').appendChild(groupDiv);

                // Salva o novo grupo no arquivo JSON
                updateOrder();
            }
        }


        // Função para editar o nome do grupo
        function editGroupName(oldName, newName) {
            if (oldName !== newName && newName.trim() !== '') {
                document.querySelectorAll(`[data-group='${oldName}']`).forEach(container => {
                    container.setAttribute('data-group', newName);
                });
                updateOrder();
            }
        }

        // Função para pesquisar por título ou conteúdo
        document.getElementById('searchBar').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            document.querySelectorAll('.card').forEach(card => {
                const titleElement = card.querySelector('h6');
                const contentElement = card.querySelector('.card-content');

                // Verifica se os elementos existem
                const title = titleElement ? titleElement.innerText.toLowerCase() : '';
                const content = contentElement ? contentElement.innerText.toLowerCase() : '';

                // Exibe ou oculta o card com base na pesquisa
                if (title.includes(searchValue) || content.includes(searchValue)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Drag and drop para organização dos cards
        let draggedCard = null;

        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('dragstart', function() {
                draggedCard = card;
                setTimeout(() => this.classList.add('dragging'), 0);
            });

            card.addEventListener('dragend', function() {
                setTimeout(() => this.classList.remove('dragging'), 0);
                draggedCard = null;
                updateOrder();
            });
        });

        // Adiciona eventos de arrastar e soltar aos contêineres
        function addDragAndDropEvents(container) {
            container.addEventListener('dragover', function(e) {
                e.preventDefault();
                container.classList.add('drag-over'); // Adiciona classe para indicar área de drop
                const afterElement = getDragAfterElement(container, e.clientY);
                if (afterElement == null) {
                    container.appendChild(draggedCard);
                } else {
                    container.insertBefore(draggedCard, afterElement);
                }
            });

            container.addEventListener('dragleave', function() {
                container.classList.remove('drag-over'); // Remove a indicação ao sair da área
            });

            container.addEventListener('drop', function() {
                container.classList.remove('drag-over'); // Remove a indicação ao soltar

                // Chama a função para salvar a nova ordem no servidor
                updateOrder();

                // Recarrega a página após a atualização dos dados
                setTimeout(function() {
                    location.reload();
                }, 500); // Aguarda 500ms para garantir que a atualização seja concluída
            });
        }

        document.querySelectorAll('.card-container').forEach(container => {
            addDragAndDropEvents(container); // Adiciona eventos a todos os contêineres de grupo
        });

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.card:not(.dragging)')];

            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // Função para salvar a nova ordem dos cards e grupos
        function updateOrder() {
            const order = { groups: {} };
            document.querySelectorAll('.card-container').forEach(container => {
                const groupName = container.getAttribute('data-group') || 'Novos';
                order.groups[groupName] = [];

                container.querySelectorAll('.card').forEach(card => {
                    const filename = card.getAttribute('data-filename');
                    order.groups[groupName].push(filename);
                });
            });

            $.ajax({
                url: 'save_order.php',
                method: 'POST',
                data: { order: JSON.stringify(order) },
                success: function(response) {
                    if (response !== 'success') {
                        alert('Erro ao salvar a nova ordem.');
                    }
                },
                error: function() {
                    alert('Erro ao salvar a nova ordem.');
                }
            });
        }

        let currentFilename = '';

        // Função para abrir o modal com o conteúdo do lembrete
        function openModal(filename) {
            currentFilename = filename;

            // Solicitação AJAX para obter o conteúdo do arquivo .txt
            $.ajax({
                url: 'read_note.php',
                method: 'POST',
                data: { filename: currentFilename },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);

                        if (data.status === 'success') {
                            document.getElementById('noteTitle').value = data.title;
                            document.getElementById('noteContent').value = data.content;
                            $('#noteModal').modal('show');
                        } else {
                            alert('Erro ao ler o lembrete: ' + (data.message || 'Erro desconhecido.'));
                        }
                    } catch (e) {
                        console.error('Erro ao processar a resposta:', e);
                        alert('Erro ao processar a resposta do servidor.');
                    }
                },
                error: function() {
                    alert('Erro ao fazer a solicitação para ler o lembrete.');
                }
            });
        }

        // Função para salvar as alterações no lembrete
        function saveNote() {
            const newTitle = document.getElementById('noteTitle').value;
            const newContent = document.getElementById('noteContent').value;

            $.ajax({
                url: 'save_note.php',
                method: 'POST',
                data: {
                    filename: currentFilename,
                    title: newTitle,
                    content: newContent
                },
                success: function(response) {
                    if (response === 'success') {
                        // Seleciona o card correspondente
                        const card = document.querySelector(`.card[data-filename='${currentFilename}']`);

                        if (card) {
                            // Atualiza o título e conteúdo do card se o elemento existir
                            const titleElement = card.querySelector('h5');
                            const contentElement = card.querySelector('.card-content pre');

                            if (titleElement) {
                                titleElement.innerText = newTitle;
                            }

                            if (contentElement) {
                                contentElement.innerText = newContent;
                            }
                        } else {
                            console.error('Erro: O card correspondente não foi encontrado no DOM.');
                        }

                        // Fechar o modal após salvar
                        $('#noteModal').modal('hide');
                    } else {
                        alert('Erro ao salvar o lembrete.');
                    }
                },
                error: function() {
                    alert('Erro ao salvar o lembrete.');
                }
            });
        }

        // Função para excluir grupos vazios
        function deleteGroup(element) {
            if (confirm('Tem certeza que deseja excluir este grupo?')) {
                // Obtém o nome do grupo a partir do atributo data-group-name e remove espaços extras
                const groupName = element.getAttribute('data-group-name').trim();

                // Escapa o nome do grupo para uso no seletor CSS
                const escapedGroupName = CSS.escape(groupName);

                // Remove o grupo do DOM
                const groupElement = document.querySelector(`[data-group="${escapedGroupName}"]`);
                if (groupElement) {
                    groupElement.parentElement.remove();
                } else {
                    console.error(`Grupo não encontrado: ${groupName}`);
                }

                // Atualiza o arquivo JSON
                updateOrder();
            }
        }


        // Função para criar um novo grupo de cards
        function createGroup() {
            const groupName = 'Novo Grupo';

            // Cria os elementos do novo grupo
            const container = document.createElement('div');
            container.classList.add('card-container');
            container.setAttribute('data-group', groupName);
            addDragAndDropEvents(container); // Adiciona os eventos de arrastar e soltar

            const groupDiv = document.createElement('div');
            groupDiv.classList.add('group');

            const groupHeader = document.createElement('div');
            groupHeader.classList.add('group-header');

            const groupNameSpan = document.createElement('span');
            groupNameSpan.classList.add('group-name');
            groupNameSpan.setAttribute('contenteditable', 'true');
            groupNameSpan.innerText = groupName;
            groupNameSpan.onblur = function() { editGroupName(groupName, groupNameSpan.innerText); };
            groupHeader.appendChild(groupNameSpan);

            const toggleIcon = document.createElement('i');
            toggleIcon.classList.add('fa', 'fa-chevron-down', 'toggle-icon');
            groupHeader.appendChild(toggleIcon);

            groupDiv.appendChild(groupHeader);
            groupDiv.appendChild(container);
            document.getElementById('group-container').appendChild(groupDiv);

            // Seleciona o nome do grupo para edição
            groupNameSpan.focus();
            document.execCommand('selectAll', false, null);

            // Salva o novo grupo no arquivo JSON
            updateOrder();
        }

        // Recarrega a página ao fechar o modal "novaNotaModal"
        $('#novaNotaModal').on('hidden.bs.modal', function () {
            location.reload();
        });

        // Recarrega a página ao fechar o modal "noteModal"
            $('#noteModal').on('hidden.bs.modal', function () {
            location.reload();
        });

    </script>
    <?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
