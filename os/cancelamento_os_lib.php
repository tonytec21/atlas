<?php
/**
 * cancelamento_os_lib.php — Controle de acesso ao cancelamento de O.S.
 *
 * Lê cancelamento_os_config.json e decide se o usuário logado pode cancelar
 * uma Ordem de Serviço. Usado em:
 *   - visualizar_os.php  → exibir/ocultar o botão "Cancelar OS"
 *   - cancelar_os.php    → bloquear a requisição no servidor (defesa real)
 *
 * Regras (cancelamento_os_config.json):
 *   restringir_cancelamento = false → liberado para todos (padrão)
 *   restringir_cancelamento = true  e nivel_liberacao = 1 → somente Administrador
 *   restringir_cancelamento = true  e nivel_liberacao = 2 → Administrador OU
 *       colaborador com o acesso adicional "acesso_adicional_caixa"
 *       (por padrão "Fluxo de Caixa", o mesmo usado no botão Pagamentos)
 *
 * Uso:
 *   require_once __DIR__ . '/cancelamento_os_lib.php';
 *   if (!cancel_os_usuario_pode($conn)) { ... }
 */

if (!defined('CANCEL_OS_CONFIG_PATH')) {
    define('CANCEL_OS_CONFIG_PATH', __DIR__ . '/cancelamento_os_config.json');
}

/**
 * Carrega a configuração (com defaults seguros se o JSON faltar ou estiver inválido).
 * Resultado memorizado por requisição.
 *
 * @return array{restringir_cancelamento:bool, nivel_liberacao:int, acesso_adicional_caixa:string}
 */
function cancel_os_config()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cfg = [
        'restringir_cancelamento' => false,
        'nivel_liberacao'         => 1,
        'acesso_adicional_caixa'  => 'Fluxo de Caixa',
    ];

    if (is_file(CANCEL_OS_CONFIG_PATH)) {
        $raw = @file_get_contents(CANCEL_OS_CONFIG_PATH);
        $arr = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($arr)) {
            $v = $arr['restringir_cancelamento'] ?? false;
            // Aceita true, "S", "1", 1
            $cfg['restringir_cancelamento'] = ($v === true || $v === 1 || $v === '1' || $v === 'S' || $v === 's');

            $n = (int)($arr['nivel_liberacao'] ?? 1);
            $cfg['nivel_liberacao'] = ($n === 2) ? 2 : 1;

            $a = trim((string)($arr['acesso_adicional_caixa'] ?? ''));
            if ($a !== '') {
                $cfg['acesso_adicional_caixa'] = $a;
            }
        }
    }

    $cache = $cfg;
    return $cache;
}

/**
 * Busca nível de acesso e acessos adicionais do usuário logado na tabela funcionarios.
 *
 * @param mysqli $conn
 * @return array{is_admin:bool, adicionais:string[]}
 */
function cancel_os_dados_usuario($conn)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $out = ['is_admin' => false, 'adicionais' => []];
    $usuario = $_SESSION['username'] ?? '';

    if ($usuario !== '' && $conn instanceof mysqli) {
        try {
            $stmt = $conn->prepare("SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $usuario);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if ($row) {
                    $nivel = strtolower(trim((string)($row['nivel_de_acesso'] ?? '')));
                    $out['is_admin'] = ($nivel === 'administrador' || $nivel === 'admin');

                    $adStr = trim((string)($row['acesso_adicional'] ?? ''));
                    if ($adStr !== '') {
                        $out['adicionais'] = array_values(array_filter(array_map('trim', explode(',', $adStr)), 'strlen'));
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[cancelamento_os_lib] ' . $e->getMessage());
        }
    }

    $cache = $out;
    return $cache;
}

/**
 * Decide se o usuário logado pode cancelar O.S. segundo o JSON.
 *
 * @param mysqli $conn
 * @return bool
 */
function cancel_os_usuario_pode($conn)
{
    $cfg = cancel_os_config();

    // Recurso desligado → liberado para todos
    if (!$cfg['restringir_cancelamento']) {
        return true;
    }

    $u = cancel_os_dados_usuario($conn);

    if ($u['is_admin']) {
        return true;
    }

    if ($cfg['nivel_liberacao'] === 2) {
        // Comparação sem diferenciar maiúsculas/minúsculas
        $alvo = mb_strtolower($cfg['acesso_adicional_caixa'], 'UTF-8');
        foreach ($u['adicionais'] as $ad) {
            if (mb_strtolower($ad, 'UTF-8') === $alvo) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Mensagem amigável para exibir quando o cancelamento é negado.
 *
 * @return string
 */
function cancel_os_mensagem_negado()
{
    $cfg = cancel_os_config();
    if ($cfg['nivel_liberacao'] === 2) {
        return 'O cancelamento de Ordens de Serviço está restrito a administradores e a colaboradores com acesso ao Controle de Caixa.';
    }
    return 'O cancelamento de Ordens de Serviço está restrito a administradores.';
}
