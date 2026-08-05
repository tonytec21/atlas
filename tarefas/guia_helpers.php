<?php
/**
 * Atlas · Tarefas — funções de apoio da Guia de Recebimento.
 *
 * Centraliza tudo o que a emissão, a reimpressão e o histórico de guias
 * precisam saber sobre a tabela `guia_de_recebimento`.
 *
 * Um cuidado importante: as colunas novas (emitido_por, criado_em,
 * impressoes, ultima_impressao) só existem depois que
 * `migracao_guia_recebimento.php` for executado. Todas as funções aqui
 * checam a existência da coluna antes de usá-la, então o módulo continua
 * funcionando mesmo antes da migração — apenas sem o histórico completo.
 */

if (!defined('GUIA_TABELA')) {
    define('GUIA_TABELA', 'guia_de_recebimento');
}

/**
 * Lista as colunas existentes na tabela de guias.
 * O resultado fica em cache estático: uma consulta por requisição.
 *
 * @return array nomes de coluna em minúsculo
 */
function guia_colunas($conn)
{
    static $colunas = null;
    if ($colunas !== null) {
        return $colunas;
    }

    $colunas = array();
    try {
        $res = $conn->query('SHOW COLUMNS FROM `' . GUIA_TABELA . '`');
        if ($res) {
            while ($linha = $res->fetch_assoc()) {
                $colunas[] = strtolower($linha['Field']);
            }
            $res->free();
        }
    } catch (Exception $e) {
        error_log('[tarefas] guia_colunas: ' . $e->getMessage());
    }

    return $colunas;
}

/** A tabela possui esta coluna? */
function guia_tem_coluna($conn, $coluna)
{
    return in_array(strtolower($coluna), guia_colunas($conn), true);
}

/**
 * Busca uma guia específica pelo seu número, ou a mais recente da tarefa.
 *
 * @param int $guia_id  número da guia (tem prioridade)
 * @param int $task_id  protocolo geral, usado quando não há $guia_id
 * @return array|null
 */
function guia_buscar($conn, $guia_id = 0, $task_id = 0)
{
    $guia_id = (int) $guia_id;
    $task_id = (int) $task_id;

    try {
        if ($guia_id > 0) {
            $stmt = $conn->prepare('SELECT * FROM `' . GUIA_TABELA . '` WHERE id = ? LIMIT 1');
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('i', $guia_id);
        } elseif ($task_id > 0) {
            // Sem número informado, imprime sempre a última guia emitida.
            $stmt = $conn->prepare('SELECT * FROM `' . GUIA_TABELA . '` WHERE task_id = ? ORDER BY id DESC LIMIT 1');
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('i', $task_id);
        } else {
            return null;
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $linha = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $linha ? $linha : null;
    } catch (Exception $e) {
        error_log('[tarefas] guia_buscar: ' . $e->getMessage());
        return null;
    }
}

/**
 * Lista o histórico de guias de uma tarefa, da mais nova para a mais antiga.
 *
 * @return array
 */
function guia_listar_por_tarefa($conn, $task_id)
{
    $task_id = (int) $task_id;
    $guias = array();

    try {
        $stmt = $conn->prepare('SELECT * FROM `' . GUIA_TABELA . '` WHERE task_id = ? ORDER BY id DESC');
        if (!$stmt) {
            return $guias;
        }
        $stmt->bind_param('i', $task_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($linha = $res->fetch_assoc()) {
                $guias[] = $linha;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log('[tarefas] guia_listar_por_tarefa: ' . $e->getMessage());
    }

    return $guias;
}

/**
 * Registra mais uma impressão da guia e devolve o número da impressão atual.
 * A 1ª impressão é a da emissão; da 2ª em diante é reimpressão.
 *
 * @return int número da impressão que está sendo feita agora (mínimo 1)
 */
function guia_registrar_impressao($conn, $guia_id)
{
    $guia_id = (int) $guia_id;
    if ($guia_id <= 0 || !guia_tem_coluna($conn, 'impressoes')) {
        return 1;
    }

    try {
        // O horário vem do PHP, e não de NOW(), para que a data da impressão
        // fique na mesma referência de fuso já usada em criado_em.
        $agora = date('Y-m-d H:i:s');
        $temData = guia_tem_coluna($conn, 'ultima_impressao');

        $sql = 'UPDATE `' . GUIA_TABELA . '` SET impressoes = COALESCE(impressoes, 0) + 1';
        if ($temData) {
            $sql .= ', ultima_impressao = ?';
        }
        $sql .= ' WHERE id = ?';

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($temData) {
                $stmt->bind_param('si', $agora, $guia_id);
            } else {
                $stmt->bind_param('i', $guia_id);
            }
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare('SELECT impressoes FROM `' . GUIA_TABELA . '` WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $guia_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $linha = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($linha && (int) $linha['impressoes'] > 0) {
                return (int) $linha['impressoes'];
            }
        }
    } catch (Exception $e) {
        error_log('[tarefas] guia_registrar_impressao: ' . $e->getMessage());
    }

    return 1;
}

/**
 * Nome do funcionário logado (nome_completo quando encontrado na tabela
 * `funcionarios`; caso contrário o próprio usuário da sessão).
 */
function guia_usuario_logado($conn)
{
    $usuario = isset($_SESSION['username']) ? trim((string) $_SESSION['username']) : '';
    if ($usuario === '') {
        return '';
    }

    try {
        $stmt = $conn->prepare('SELECT nome_completo FROM funcionarios WHERE usuario = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $usuario);
            $stmt->execute();
            $res = $stmt->get_result();
            $linha = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($linha && trim((string) $linha['nome_completo']) !== '') {
                return $linha['nome_completo'];
            }
        }
    } catch (Exception $e) {
        error_log('[tarefas] guia_usuario_logado: ' . $e->getMessage());
    }

    return $usuario;
}

/**
 * O nome informado é um funcionário válido para constar na guia?
 *
 * Aceita qualquer funcionário cadastrado (mesmo inativo, para não travar
 * reemissões de tarefas antigas) e também o responsável pela própria tarefa.
 * Se a consulta falhar, libera — validação nunca deve impedir a emissão.
 */
function guia_funcionario_valido($conn, $nome, $task_id = 0)
{
    $nome = trim((string) $nome);
    if ($nome === '') {
        return false;
    }

    try {
        $stmt = $conn->prepare('SELECT 1 FROM funcionarios WHERE nome_completo = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $nome);
            $stmt->execute();
            $res = $stmt->get_result();
            $achou = $res && $res->num_rows > 0;
            $stmt->close();
            if ($achou) {
                return true;
            }
        }

        // Responsável pela tarefa também vale, mesmo que não bata com o cadastro.
        $task_id = (int) $task_id;
        if ($task_id > 0) {
            $stmt = $conn->prepare('SELECT funcionario_responsavel FROM tarefas WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $task_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $linha = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if ($linha && trim((string) $linha['funcionario_responsavel']) === $nome) {
                    return true;
                }
            }
        }
    } catch (Exception $e) {
        error_log('[tarefas] guia_funcionario_valido: ' . $e->getMessage());
        return true; // na dúvida, não bloqueia a emissão
    }

    return false;
}

/** Converte DATETIME do banco para o formato brasileiro. */
function guia_data_br($valor, $comHora = true)
{
    $valor = trim((string) $valor);
    if ($valor === '' || $valor === '0000-00-00 00:00:00' || $valor === '0000-00-00') {
        return '';
    }

    try {
        $data = new DateTime($valor);
        return $data->format($comHora ? 'd/m/Y H:i:s' : 'd/m/Y');
    } catch (Exception $e) {
        return $valor;
    }
}

/** Ordinal feminino curto usado na marcação de reimpressão (2ª, 3ª...). */
function guia_ordinal($numero)
{
    return ((int) $numero) . 'ª';
}
