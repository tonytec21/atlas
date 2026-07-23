<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Catálogo de evidências esperadas por requisito + integração Gemini.
 */

/** cod => [evidência concreta que comprova o requisito, ...] */
function p213_evid_esperadas() {
    static $e = null;
    if ($e !== null) return $e;
    $e = [
// ───────────────────────────────────────────────── ETAPA 1
'1.1.I'   => ['Termo de designação assinado pelo delegatário', 'Ciência formal do designado'],
'1.1.II'  => ['Declaração de controlador de dados assinada', 'Cláusula de caracterização na PSI'],
'1.1.III' => ['Termo de designação do encarregado (DPO)', 'Publicação do canal de contato do titular'],
'1.2.a'   => ['PSI assinada e datada', 'Ata ou registro de aprovação', 'Comprovante de divulgação interna (e-mail, lista de ciência)'],
'1.2.b'   => ['Seção da PSI com escopo, governança, objetivos, cronograma, responsabilidades e critérios de continuidade'],
'1.3.a'   => ['Relatório de usuários do sistema ou do diretório (AD/LDAP)', 'Política de criação e revogação de contas'],
'1.3.b'   => ['Captura da configuração de MFA', 'Relatório de usuários administrativos com MFA ativo'],
'1.3.c'   => ['Relatório de contas genéricas desativadas ou nominadas', 'Declaração de inexistência de credenciais compartilhadas'],
'1.3.d'   => ['Inventário de contas técnicas (service accounts)', 'Matriz de privilégios e escopo', 'Amostra de trilha de auditoria dessas contas'],
'1.4'     => ['ROPA assinado, com data da última atualização', 'Fluxo de atendimento aos direitos dos titulares'],
'1.5'     => ['Procedimento de gestão de incidentes aprovado', 'Fluxo com gatilho de comunicação em 72 h à Corregedoria'],
'1.6'     => ['Registro da meta de 24 h na PSI ou no procedimento de incidentes'],
'1.7'     => ['Inventário de ativos exportado deste módulo, assinado'],
'1.8.a'   => ['Notas fiscais e termos de licença', 'Relação de softwares livres com a respectiva licença'],
'1.8.b'   => ['Relatório de versões confrontado com o ciclo de suporte do fabricante', 'Evidência oficial de suporte vigente'],
'1.8.c'   => ['Contratos revisados contendo as sete cláusulas', 'Aditivos contratuais firmados'],
'1.8.d'   => ['Critérios documentados de seleção e contratação de fornecedores de TIC', 'Avaliação prévia de segurança do fornecedor', 'Registro da rotina de supervisão contratual'],
'1.9'     => ['Declaração de conclusão da Etapa 1 assinada', 'Comprovante ou protocolo do Sistema Justiça Aberta'],

// ───────────────────────────────────────────────── ETAPA 2
'2.1.a'   => ['Projeto elétrico ou registro do circuito dedicado', 'Fotografia do quadro e da proteção contra surtos'],
'2.1.b'   => ['Laudo de aterramento atualizado', 'ART do profissional habilitado'],
'2.1.c'   => ['Nota fiscal do SAI/UPS', 'Relatório do teste de autonomia sob carga'],
'2.2'     => ['Plano de contingência energética assinado'],
'2.3'     => ['Fotografias do ambiente e do controle de acesso', 'Comprovante de proteção contra incêndio', 'Se em nuvem: documentação contratual dos controles do provedor'],
'2.4.a'   => ['Contrato do link de internet', 'Teste documentado de conclusão do backup incremental dentro do RPO'],
'2.4.b'   => ['Registro de roteador e switch no inventário, com firmware e data de atualização'],
'2.4.c'   => ['Contrato do segundo link ou da tecnologia equivalente', 'Classe 1: justificativa técnica de dispensa com RTO/RPO comprovados'],
'2.5.a'   => ['PCN e PRD assinados'],
'2.5.b'   => ['Matriz de riscos com probabilidade e impacto por ativo crítico'],
'2.5.c'   => ['Plano de mitigação com controle, responsável e evidência por risco'],
'2.5.d'   => ['Seção do PCN/PRD com RTO e RPO expressos', 'Teste que comprove a aderência'],
'2.5.e'   => ['Seção do PCN/PRD com as medidas de até 30 e de até 90 dias'],
'2.6'     => ['Contrato de suporte técnico com SLA compatível com o RTO', 'Notas fiscais dos equipamentos'],
'2.7'     => ['Relatório do console de endpoint com 100% dos ativos protegidos'],
'2.8'     => ['Documento técnico de arquitetura', 'Diagrama de topologia de rede'],
'2.9'     => ['Declaração de conclusão da Etapa 2 assinada', 'Comprovante ou protocolo do Justiça Aberta'],

// ───────────────────────────────────────────────── ETAPA 3
'3.1.a'   => ['Relatório de teste de TLS (SSL Labs, testssl.sh ou equivalente)', 'Configuração do servidor com TLS 1.2+'],
'3.1.b'   => ['Captura da configuração de criptografia em repouso (AES-256)'],
'3.1.c'   => ['Configuração da rotina de backup demonstrando cifra', 'Evidência da custódia da chave'],
'3.1.d'   => ['Inventário de chaves e certificados', 'Política de rotação e renovação', 'Registro de geração, renovação e revogação', 'Termo de custódia segregada'],
'3.2.a'   => ['Agendamento da cópia completa', 'Log de execução dentro do intervalo da classe'],
'3.2.b'   => ['Configuração de cópia incremental, replicação contínua ou PITR', 'Log de execução'],
'3.2.c'   => ['Comprovação dos dois ambientes tecnicamente independentes (contratos ou capturas)'],
'3.2.d'   => ['Captura do recurso de imutabilidade (WORM, retention lock ou versionamento bloqueado)'],
'3.2.e'   => ['Evidência da cifra na origem em AES-256', 'Declaração de custódia exclusiva da chave pela serventia'],
'3.2.f'   => ['Estudo de viabilidade técnica e econômica da meta de 30 minutos', 'Orçamento das soluções de mercado avaliadas', 'Decisão fundamentada de adoção ou de não adoção'],
'3.3'     => ['Captura do alerta automático configurado', 'Registro de chamado aberto em caso de falha'],
'3.4.a'   => ['Configuração do firewall com IPS/IDS', 'Amostra de log de eventos de segurança'],
'3.4.b'   => ['Mapa de VLANs ou configuração de segmentação lógica'],
'3.4.c'   => ['Relatório de inspeção ativa de tráfego', 'Política de retenção dos logs de segurança'],
'3.5'     => ['Console do EDR/XDR com ativos cobertos', 'Classe 1: justificativa técnica de proporcionalidade'],
'3.6'     => ['Configuração do SGBD com integridade transacional e binlog/WAL ativo'],
'3.7'     => ['Documentação da solução de alta disponibilidade', 'Registro do teste de failover ou restauração automatizada'],
'3.8.a'   => ['Amostra de trilha com usuário, data, hora, ação e resultado', 'Mecanismo de verificação de integridade'],
'3.8.b'   => ['Configuração do NTP', 'Monitoramento do desvio de tempo'],
'3.8.c'   => ['Especificação dos eventos registrados, confrontada com o nível exigido para a classe'],
'3.8.d'   => ['Política de retenção de trilhas', 'Evidência de registros com 5 anos ou do início da coleta'],
'3.8.e'   => ['Evidência de restauração que inclua as trilhas de auditoria'],
'3.9'     => ['Declaração de conclusão da Etapa 3 assinada', 'Comprovante ou protocolo do Justiça Aberta'],

// ───────────────────────────────────────────────── ETAPA 4
'4.1'     => ['Relatório de conformidade de auditoria assinado, cobrindo os cinco pontos do item 4.1.1'],
'4.2'     => ['Rotina documentada de atualização', 'Registro de versões antes e depois da janela'],
'4.3.a'   => ['Registro de vulnerabilidades críticas com data de identificação e de correção'],
'4.3.b'   => ['Registro da contenção emergencial e das medidas mitigatórias aplicadas'],
'4.3.c'   => ['Planilha ou sistema com registro cronológico auditável'],
'4.4'     => ['Ata da simulação anual de cenário de desastre'],
'4.5'     => ['Ata do teste de restauração no modelo do Anexo V', 'Log da restauração', 'Comprovante de hash'],
'4.6'     => ['Relatório datado de varredura de vulnerabilidades', 'Revisão da configuração de borda'],
'4.7'     => ['Relatório de pentest', 'Ou relatório técnico de dispensa (Anexo II, 6.3) com declaração do titular', 'Ou relatório coletivo do ambiente compartilhado'],
'4.8'     => ['Registro de análise de causa raiz de cada incidente', 'Documento de lições aprendidas'],
'4.9'     => ['Declaração de conclusão da Etapa 4 assinada', 'Comprovante ou protocolo do Justiça Aberta'],

// ───────────────────────────────────────────────── ETAPA 5
'5.1'     => ['Documentação da API ou do canal de integração', 'Amostra de registro auditável das integrações'],
'5.2'     => ['Evidência de exportação em PDF/A e XML', 'Análise de dependência de fornecedor'],
'5.3'     => ['Listas de presença', 'Conteúdo programático e carga horária'],
'5.4'     => ['Ata de revisão da PSI', 'Registro da revisão dos padrões criptográficos'],
'5.5'     => ['Política de retenção de registros auditáveis por 5 anos'],
'5.6.a'   => ['Plano de reversibilidade e portabilidade assinado'],
'5.6.b'   => ['Relatório da simulação de extração integral', 'Verificação de integridade e amostragem'],
'5.6.c'   => ['Cláusula contratual de reversibilidade', 'Comprovação do teste de extração', 'Declaração de inexistência de restrição à migração'],
'5.7'     => ['Declaração de conclusão da Etapa 5 assinada', 'Protocolo do Justiça Aberta', 'Síntese do dossiê técnico'],
    ];
    return $e;
}

function p213_evid_do_requisito($cod) {
    $e = p213_evid_esperadas();
    return isset($e[$cod]) ? $e[$cod] : [];
}

// ---------------------------------------------------------------------------
// Armazenamento
// ---------------------------------------------------------------------------
function p213_evid_dir() {
    $dir = __DIR__ . '/evidencias';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess',
            "Options -Indexes\r\nphp_flag engine off\r\n"
          . "<FilesMatch \"\\.(php|php\\d|phtml|phar|inc)$\">\r\n  Require all denied\r\n</FilesMatch>\r\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function p213_tamanho($bytes) {
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
    return number_format($bytes, $i ? 1 : 0, ',', '.') . ' ' . $u[$i];
}

/** Evidências agrupadas por código de requisito. */
function p213_evidencias_por_codigo() {
    $conn = p213_db();
    $out = [];
    $res = $conn->query("SELECT * FROM p213_evidencias ORDER BY etapa, codigo, criado_em");
    while ($r = $res->fetch_assoc()) $out[$r['codigo']][] = $r;
    return $out;
}

/** Contagem por código (para badges no diagnóstico). */
function p213_evid_contagem() {
    $conn = p213_db();
    $out = [];
    $res = $conn->query("SELECT codigo, COUNT(*) n FROM p213_evidencias GROUP BY codigo");
    while ($r = $res->fetch_assoc()) $out[$r['codigo']] = (int)$r['n'];
    return $out;
}

/** Recalcula os hashes e aponta divergências (Anexo IV, Disposições gerais, IV e VIII). */
function p213_evid_verificar($etapa = null) {
    $conn = p213_db();
    $sql = "SELECT * FROM p213_evidencias WHERE arquivo_path IS NOT NULL";
    if ($etapa) $sql .= " AND etapa = " . (int)$etapa;
    $res = $conn->query($sql . " ORDER BY etapa, codigo");

    $ok = 0; $problemas = [];
    while ($r = $res->fetch_assoc()) {
        $abs = p213_evid_dir() . '/' . $r['arquivo_path'];
        if (!is_file($abs)) {
            $problemas[] = ['id' => $r['id'], 'codigo' => $r['codigo'], 'titulo' => $r['titulo'],
                            'erro' => 'Arquivo ausente no repositório'];
            continue;
        }
        $h = hash_file('sha256', $abs);
        if (!hash_equals((string)$r['sha256'], $h)) {
            $problemas[] = ['id' => $r['id'], 'codigo' => $r['codigo'], 'titulo' => $r['titulo'],
                            'erro' => 'Hash divergente — o arquivo foi alterado após o registro'];
            continue;
        }
        $ok++;
    }
    return ['integros' => $ok, 'problemas' => $problemas];
}

/** Hash consolidado do dossiê da etapa: SHA-256 da concatenação ordenada dos hashes. */
function p213_dossie_hash($etapa) {
    $conn = p213_db();
    $st = $conn->prepare("SELECT sha256 FROM p213_evidencias WHERE etapa=? AND sha256 IS NOT NULL ORDER BY sha256");
    $st->bind_param('i', $etapa);
    $st->execute();
    $r = $st->get_result();
    $acc = '';
    while ($x = $r->fetch_assoc()) $acc .= $x['sha256'];
    $st->close();
    return $acc === '' ? null : hash('sha256', $acc);
}

// ---------------------------------------------------------------------------
// Gemini
// ---------------------------------------------------------------------------
function p213_gemini_config() {
    $cfg = p213_config();
    return [
        'key'    => isset($cfg['gemini_api_key']) ? trim($cfg['gemini_api_key']) : '',
        'modelo' => !empty($cfg['gemini_modelo']) ? $cfg['gemini_modelo'] : 'gemini-3.1-flash-lite',
    ];
}

function p213_gemini_ativo() {
    $g = p213_gemini_config();
    return $g['key'] !== '';
}

/**
 * Chama a API do Gemini. Retorna [ok(bool), texto|mensagem_de_erro].
 * $esperaJson força responseMimeType=application/json.
 */
function p213_gemini($prompt, $esperaJson = true, $maxTokens = 2048) {
    $g = p213_gemini_config();
    if ($g['key'] === '') return [false, 'Chave da API do Gemini não configurada (aba Configuração).'];
    if (!function_exists('curl_init')) return [false, 'Extensão cURL não habilitada no PHP.'];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . rawurlencode($g['modelo']) . ':generateContent?key=' . urlencode($g['key']);

    $gc = ['temperature' => 0.2, 'maxOutputTokens' => $maxTokens];
    if ($esperaJson) $gc['responseMimeType'] = 'application/json';

    $body = json_encode([
        'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => $gc,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    // XAMPP/Windows frequentemente não traz bundle de CA — repete sem verificação.
    if ($resp === false && stripos(curl_error($ch), 'certificate') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $resp = curl_exec($ch);
    }
    if ($resp === false) { $e = curl_error($ch); curl_close($ch); return [false, 'Falha de conexão: ' . $e]; }
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $j = json_decode($resp, true);
    if ($http !== 200) {
        $msg = isset($j['error']['message']) ? $j['error']['message'] : ('HTTP ' . $http);
        return [false, 'Gemini: ' . $msg];
    }
    if (!isset($j['candidates'][0]['content']['parts'][0]['text']))
        return [false, 'Resposta vazia do Gemini (possível bloqueio de conteúdo).'];

    return [true, $j['candidates'][0]['content']['parts'][0]['text']];
}

/** Contexto normativo do requisito, para alimentar os prompts. */
function p213_contexto_requisito($cod) {
    foreach (p213_catalogo() as $it) {
        if ($it['cod'] === $cod) return $it;
    }
    return null;
}

function p213_gemini_json($texto) {
    $t = trim($texto);
    $t = preg_replace('/^```(?:json)?|```$/m', '', $t);
    $d = json_decode(trim($t), true);
    return is_array($d) ? $d : null;
}
