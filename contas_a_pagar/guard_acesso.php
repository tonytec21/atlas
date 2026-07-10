<?php
/**
 * guard_acesso.php — Proteção de acesso do módulo Contas a Pagar.
 *
 * Regra: só acessam o módulo o ADMINISTRADOR ou usuários cujo campo
 * `funcionarios.acesso_adicional` contenha "Controle de Contas a Pagar".
 *
 * Uso:
 *   require_once __DIR__.'/session_check.php'; checkSession();
 *   require_once __DIR__.'/guard_acesso.php';
 *      - em PÁGINAS:    cap_guard();            (redireciona/mostra aviso)
 *      - em ENDPOINTS:  cap_guard('json');      (responde 403 JSON e encerra)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

/** @return bool  true se o usuário logado pode usar o módulo. */
function cap_pode_acessar()
{
    $username = $_SESSION['username'] ?? null;
    if (!$username) return false;

    $conn = new mysqli('localhost', 'root', '', 'atlas');
    if ($conn->connect_error) return false;
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ? LIMIT 1");
    if (!$stmt) { $conn->close(); return false; }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $u = $res ? $res->fetch_assoc() : null;
    $stmt->close(); $conn->close();
    if (!$u) return false;

    $nivel = strtolower(trim((string)($u['nivel_de_acesso'] ?? '')));
    if ($nivel === 'administrador') return true;

    $adicionais = array_map('trim', explode(',', (string)($u['acesso_adicional'] ?? '')));
    return in_array('Controle de Contas a Pagar', $adicionais, true);
}

/**
 * Bloqueia o acesso se o usuário não tiver permissão.
 * @param string $modo 'page' (padrão) mostra aviso e redireciona; 'json' responde 403 JSON.
 */
function cap_guard($modo = 'page')
{
    if (cap_pode_acessar()) return;

    if ($modo === 'json') {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); http_response_code(403); }
        echo json_encode(['success' => false, 'acesso_negado' => true,
            'message' => 'Acesso negado: você não tem permissão para o Controle de Contas a Pagar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Página: aviso amigável + redirecionamento
    http_response_code(403);
    $swal = '../script/sweetalert2.js';
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">'
       . '<script src="' . $swal . '"></script>'
       . '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>'
       . '</head><body><script>'
       . 'document.addEventListener("DOMContentLoaded",function(){'
       . 'Swal.fire({icon:"error",title:"Acesso negado!",text:"Você não tem permissão para acessar o Controle de Contas a Pagar.",confirmButtonText:"Ok",allowOutsideClick:false})'
       . '.then(function(){ window.location.href="../index.php"; });'
       . '});</script></body></html>';
    exit;
}
