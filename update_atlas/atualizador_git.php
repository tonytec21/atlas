<?php
/* ======================================================================
 * ATLAS • Atualizador automático do código (git) — 1.0.0
 * ----------------------------------------------------------------------
 * Substitui o antigo "git pull" da tela de login e funciona com o
 * repositório PRIVADO, sem guardar senha em nenhuma VM:
 *
 *   1. pede à API Atlas uma credencial de LEITURA do GitHub (válida por
 *      pouco tempo), identificando-se com o CNS e com a chave desta
 *      instalação (gerada aqui no primeiro uso e guardada dentro de .git);
 *   2. roda "git pull" passando a credencial só naquele comando
 *      (http.extraHeader) — nada é gravado no .git/config nem no
 *      gerenciador de credenciais do Windows;
 *   3. informa à API o commit em que esta serventia ficou.
 *
 * Se a API não responder, tenta o "git pull" simples (funciona enquanto o
 * repositório for público) sem nunca abrir janela pedindo senha.
 *
 * Uso na tela de login:  $msg = atlas_atualizar_git($cns);
 * Uso manual (prompt):   php update_atlas\atualizador_git.php
 * ====================================================================== */

if (!defined('ATLAS_ATUALIZADOR_URL')) {
  define('ATLAS_ATUALIZADOR_URL',   'http://24.152.39.25/api-atlas/atualizacao.php');
  define('ATLAS_ATUALIZADOR_TOKEN', 'API-ATLAS');   // mesmo X-Atlas-Token do registro
  define('ATLAS_ATUALIZADOR_INTERVALO', 600);       // no máximo 1 tentativa a cada 10 min
  define('ATLAS_ATUALIZADOR_TIMEOUT_GIT', 90);      // segundos até desistir do git
}

if (!function_exists('atlas_atualizar_git')) {

/** Executa o git sem shell, com tempo limite. Retorna [codigo, saida]. */
function atlas_git_exec(string $raiz, array $args, int $timeout = 30): array
{
  $cmd = array_merge(['git', '-c', 'safe.directory=*', '-c', 'credential.helper=', '-c', 'core.askPass='], $args);
  $env = getenv();
  if (!is_array($env)) $env = [];
  $env['GIT_TERMINAL_PROMPT'] = '0';      // nunca pergunta usuário/senha
  $env['GCM_INTERACTIVE']     = 'never';  // Git Credential Manager do Windows: sem janela
  $env['GIT_ASKPASS']         = '';
  $env['LC_ALL']              = 'C';      // mensagens em inglês, previsíveis

  /* saída vai para arquivo temporário: no Windows, pipes não bloqueantes não funcionam
     e o tempo limite deixaria de valer */
  $tmp = tempnam(sys_get_temp_dir(), 'atlasgit');
  if ($tmp === false) return [-1, 'Sem pasta temporária.'];
  $desc = [0 => ['pipe', 'r'], 1 => ['file', $tmp, 'a'], 2 => ['file', $tmp, 'a']];
  $p = @proc_open($cmd, $desc, $pipes, $raiz, $env);
  if (!is_resource($p)) { @unlink($tmp); return [-1, 'Não foi possível executar o git.']; }
  fclose($pipes[0]);

  $fim = microtime(true) + $timeout;
  $sufixo = '';
  while (true) {
    $st = proc_get_status($p);
    if (!$st['running']) { $codigo = $st['exitcode']; break; }
    if (microtime(true) > $fim) {
      if (PHP_OS_FAMILY === 'Windows') @exec('taskkill /F /T /PID ' . (int)$st['pid'] . ' 2>NUL');
      else @proc_terminate($p, 9);
      $sufixo = "\n[tempo limite de {$timeout}s excedido]";
      $codigo = -2;
      break;
    }
    usleep(100000);
  }
  proc_close($p);
  $saida = (string)@file_get_contents($tmp) . $sufixo;
  @unlink($tmp);
  return [(int)$codigo, trim($saida)];
}

/** Chama a API Atlas (POST JSON). Retorna array ou null. */
function atlas_atualizador_api(array $dados): ?array
{
  if (!function_exists('curl_init')) return null;
  $ch = curl_init(ATLAS_ATUALIZADOR_URL);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Atlas-Token: ' . ATLAS_ATUALIZADOR_TOKEN],
    CURLOPT_POSTFIELDS     => json_encode($dados, JSON_UNESCAPED_UNICODE),
  ]);
  $resp = curl_exec($ch);
  curl_close($ch);
  $j = is_string($resp) ? json_decode($resp, true) : null;
  return is_array($j) ? $j : null;
}

/** Chave secreta desta instalação, guardada em .git/atlas_chave (nunca vai para o repositório). */
function atlas_atualizador_chave(string $raiz): string
{
  $arq = $raiz . '/.git/atlas_chave';
  $c = is_file($arq) ? trim((string)@file_get_contents($arq)) : '';
  if (preg_match('/^[a-f0-9]{64}$/', $c)) return $c;
  $c = bin2hex(random_bytes(32));
  @file_put_contents($arq, $c);
  return $c;
}

/**
 * Atualiza o código. Retorna a mensagem para exibir (ou '' se nada foi feito).
 * $forcar ignora o intervalo mínimo entre tentativas.
 */
function atlas_atualizar_git(string $cns, bool $forcar = false): string
{
  try {
    $raiz = realpath(__DIR__ . '/..');
    if (!$raiz || !is_dir($raiz . '/.git')) return '';

    /* não roda a cada abertura do login: no máximo 1 vez por intervalo */
    $marca = sys_get_temp_dir() . '/atlas_atualizador_' . md5($raiz) . '.txt';
    if (!$forcar && is_file($marca) && filemtime($marca) > time() - ATLAS_ATUALIZADOR_INTERVALO) return '';
    @touch($marca);

    [$c, $antes] = atlas_git_exec($raiz, ['rev-parse', 'HEAD'], 10);
    if ($c !== 0) return '';
    $chave = atlas_atualizador_chave($raiz);

    /* 1) credencial temporária de leitura */
    $cred = $cns !== '' ? atlas_atualizador_api([
      'acao' => 'credencial', 'cns' => $cns, 'chave' => $chave, 'commit' => $antes,
    ]) : null;

    $args = [];
    if (is_array($cred) && !empty($cred['ok']) && !empty($cred['token'])) {
      $basic = base64_encode('x-access-token:' . $cred['token']);
      $args  = ['-c', 'http.https://github.com/.extraheader=Authorization: Basic ' . $basic];
    } elseif (is_array($cred) && !empty($cred['bloqueado'])) {
      return '';   // atualização suspensa para esta serventia pelo painel da API
    }

    /* 2) git pull (só avança; nunca faz merge que possa gerar conflito) */
    [$c, $saida] = atlas_git_exec($raiz, array_merge($args, ['pull', '--ff-only']), ATLAS_ATUALIZADOR_TIMEOUT_GIT);
    [, $depois] = atlas_git_exec($raiz, ['rev-parse', 'HEAD'], 10);

    if ($c === 0 && $depois !== $antes)   { $status = 'atualizado';  $msg = 'Atualização do código aplicada com sucesso.'; }
    elseif ($c === 0)                    { $status = 'em dia';      $msg = 'Sistema atualizado. Nenhuma atualização pendente.'; }
    else                                 { $status = 'erro';        $msg = 'Não foi possível atualizar o código agora.'; }

    /* 3) informa o resultado (sem a credencial; mensagem do git resumida) */
    if ($cns !== '') {
      atlas_atualizador_api([
        'acao' => 'relatorio', 'cns' => $cns, 'chave' => $chave,
        'commit' => $depois ?: $antes, 'status' => $status,
        'detalhe' => $c === 0 ? '' : mb_substr(preg_replace('/basic\s+\S+/i', 'basic ***', $saida), 0, 400),
      ]);
    }
    return $msg;
  } catch (Throwable $e) {
    return '';   // a atualização nunca pode impedir o login
  }
}

}

/* execução manual pelo prompt: php update_atlas\atualizador_git.php */
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  $cns = '';
  if (is_file(__DIR__ . '/../db_connection.php')) {
    try {
      require __DIR__ . '/../db_connection.php';
      if (isset($conn) && $conn instanceof mysqli) {
        $r = $conn->query('SELECT cns FROM cadastro_serventia LIMIT 1');
        if ($r) $cns = (string)($r->fetch_assoc()['cns'] ?? '');
      }
    } catch (Throwable $e) {}
  }
  echo (atlas_atualizar_git($cns, true) ?: 'Nada a fazer (sem .git ou sem git instalado).') . PHP_EOL;
}
