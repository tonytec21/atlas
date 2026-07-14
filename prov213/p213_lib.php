<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Núcleo do módulo: sessão, conexão, catálogo normativo, motor de conformidade.
 */

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// 1. Sessão (reaproveita o padrão do Atlas, com fallback)
// ---------------------------------------------------------------------------
foreach ($P213_SESSION_CANDIDATES as $__f) {
    if (is_file($__f)) { include_once $__f; break; }
}
if (function_exists('checkSession')) {
    checkSession();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// 2. Conexão
// ---------------------------------------------------------------------------
function p213_db() {
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(P213_DB_HOST, P213_DB_USER, P213_DB_PASS, P213_DB_NAME);
    $conn->set_charset(P213_DB_CHARSET);
    return $conn;
}

function p213_esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Fallback caso a extensão mbstring não esteja habilitada no php.ini
if (!function_exists('mb_substr')) {
    function mb_substr($s, $i, $l = null) { return $l === null ? substr($s, $i) : substr($s, $i, $l); }
}
if (!function_exists('mb_strlen'))   { function mb_strlen($s) { return strlen($s); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower($s); } }

function p213_usuario() {
    foreach (['nome', 'usuario', 'user_nome', 'nome_usuario', 'login'] as $k) {
        if (!empty($_SESSION[$k])) return $_SESSION[$k];
    }
    return 'Usuário do sistema';
}

// ---------------------------------------------------------------------------
// 3. Enquadramento por classe (art. 16)
// ---------------------------------------------------------------------------
/**
 * Classe 1: até R$ 100.000,00 (subclasses A, B, C — terços do teto)
 * Classe 2: acima de C1 até R$ 500.000,00 (subclasses D, E, F — terços do teto)
 * Classe 3: acima de R$ 500.000,00 (G até 3x; H até 6x; I até 12x; J acima de 12x)
 * Base: arrecadação bruta SEMESTRAL. §2º: tetos atualizados anualmente pelo IPCA.
 */
function p213_enquadrar($receitaSemestral, $fatorIpca = 1.0) {
    $r  = (float)$receitaSemestral;
    $t1 = 100000.00 * $fatorIpca;
    $t2 = 500000.00 * $fatorIpca;

    if ($r <= $t1) {
        $sub = ($r <= $t1 / 3) ? 'A' : (($r <= 2 * $t1 / 3) ? 'B' : 'C');
        return ['classe' => 1, 'subclasse' => $sub, 'teto' => $t1];
    }
    if ($r <= $t2) {
        $sub = ($r <= $t2 / 3) ? 'D' : (($r <= 2 * $t2 / 3) ? 'E' : 'F');
        return ['classe' => 2, 'subclasse' => $sub, 'teto' => $t2];
    }
    if ($r <= 3 * $t2)  $sub = 'G';
    elseif ($r <= 6 * $t2)  $sub = 'H';
    elseif ($r <= 12 * $t2) $sub = 'I';
    else                    $sub = 'J';
    return ['classe' => 3, 'subclasse' => $sub, 'teto' => null];
}

// ---------------------------------------------------------------------------
// 4. Parâmetros técnicos por classe (Anexos I e II)
// ---------------------------------------------------------------------------
function p213_parametros($classe) {
    $p = [
        1 => ['rpo' => '24 h', 'rto' => '24 h', 'backup_full' => 'até 72 h', 'link' => '2 Mbps',
              'teste_restauracao' => 'anual', 'trilha' => 'Essencial', 'pentest' => 'não exigido',
              'extracao' => 'a cada 36 meses', 'comprovacao' => 'Relatório simplificado (Anexo IV, VI e VII)',
              'dpo' => 'Dispensado (Prov. 214/2026, art. 88, §4º do CNN)'],
        2 => ['rpo' => '12 h', 'rto' => '24 h', 'backup_full' => 'até 48 h', 'link' => '10 Mbps',
              'teste_restauracao' => 'anual', 'trilha' => 'Essencial', 'pentest' => 'não exigido',
              'extracao' => 'a cada 30 meses', 'comprovacao' => 'Dossiê técnico + lista de hashes assinada',
              'dpo' => 'Exigível quando aplicável'],
        3 => ['rpo' => '4 h',  'rto' => '8 h',  'backup_full' => 'até 24 h', 'link' => '50 Mbps',
              'teste_restauracao' => 'semestral', 'trilha' => 'Intermediário', 'pentest' => 'a cada 2 anos (Anexo II, 6, IV)',
              'extracao' => 'a cada 24 meses', 'comprovacao' => 'Dossiê técnico + lista de hashes assinada',
              'dpo' => 'Exigível quando aplicável'],
    ];
    return isset($p[$classe]) ? $p[$classe] : $p[1];
}

// ---------------------------------------------------------------------------
// 5. Prazos (arts. 20, 21 e 23)
// ---------------------------------------------------------------------------
function p213_prazos($classe, $vigencia = P213_VIGENCIA) {
    $dias  = [3 => 90, 2 => 150, 1 => 210];             // art. 20 — Etapas 1 e 2
    $meses = [3 => 24, 2 => 30,  1 => 36];              // art. 23 — Etapas 1 a 5
    $d = new DateTime($vigencia);

    $inicial = (clone $d)->modify('+' . $dias[$classe] . ' days');
    $global  = (clone $d)->modify('+' . $meses[$classe] . ' months');
    $prorrog = (clone $inicial)->modify('+90 days');    // art. 21 — prorrogação excepcional

    $hoje = new DateTime('today');
    return [
        'inicial'          => $inicial,
        'inicial_dias'     => (int)$hoje->diff($inicial)->format('%r%a'),
        'prorrogado'       => $prorrog,
        'global'           => $global,
        'global_dias'      => (int)$hoje->diff($global)->format('%r%a'),
        'dias_norma'       => $dias[$classe],
        'meses_norma'      => $meses[$classe],
    ];
}

// ---------------------------------------------------------------------------
// 6. Catálogo normativo — extraído do Provimento 213/2026
//    cod | etapa | peso (1..3) | classes aplicáveis | pergunta | base | sugestão
// ---------------------------------------------------------------------------
function p213_catalogo() {
    static $cat = null;
    if ($cat !== null) return $cat;

    $C = [1, 2, 3];
    $cat = [

// ======================= ETAPA 1 =======================
['1.1.I', 1, 3, $C, 'Há designação formal, por escrito, de responsável técnico interno pela implementação do Provimento?',
 'Anexo IV, Etapa 1, item 1.1, I',
 'Emita o Termo de Designação de Responsável Técnico Interno (aba Termos), com nome, qualificação, atribuições e data. Arquive com ciência do designado.'],

['1.1.II', 1, 3, $C, 'O responsável pela serventia está formalmente caracterizado como controlador de dados pessoais?',
 'Anexo IV, item 1.1, II c/c art. 7º e Anexo III, 4.8, II',
 'Emita a Declaração de Controlador de Dados Pessoais e insira a caracterização expressa na Política Interna de Segurança da Informação.'],

['1.1.III', 1, 2, [2, 3], 'Foi designado encarregado pelo tratamento de dados pessoais (DPO)?',
 'Anexo IV, item 1.1, III c/c art. 7º, §2º',
 'Classe 1 está dispensada (Prov. 214/2026 incluiu o §4º no art. 88 do CNN). Classes 2 e 3: emita o Termo de Designação de Encarregado (DPO) e publique o canal de contato do titular.'],

['1.2.a', 1, 3, $C, 'A Política Interna de Segurança da Informação foi elaborada, aprovada e divulgada internamente, com todos os elementos mínimos do Anexo III?',
 'Anexo IV, item 1.2 c/c Anexo III',
 'Gere a minuta da PSI (aba Termos). Ela precisa cobrir governança, controle e revogação de acesso, uso aceitável, integração PCN/PRD, proteção física, incidentes, vulnerabilidades, LGPD, criptografia e gestão de fornecedores.'],

['1.2.b', 1, 2, $C, 'A PSI já contém as diretrizes preliminares do PCN/PRD (escopo, governança, objetivos, cronograma, responsabilidades e critérios de continuidade)?',
 'Anexo IV, item 1.2 (parte final) c/c art. 3º, §1º',
 'Na Etapa 1 exige-se apenas o planejamento na PSI. A formalização técnica completa do PCN/PRD é obrigatória na Etapa 2 — não pode ser antecipada nem postergada.'],

['1.3.a', 1, 3, $C, 'Todos os usuários (inclusive prepostos, estagiários, terceiros e fornecedores) possuem autenticação individualizada?',
 'Art. 5º, caput; Anexo II, 1, I; Anexo IV, item 1.3',
 'Elimine logins compartilhados. Cada pessoa física com credencial própria, perfil aderente à função e ao risco.'],

['1.3.b', 1, 3, $C, 'Há autenticação multifator (MFA) obrigatória para acessos administrativos, gestão de sistemas, bancos de dados e funcionalidades críticas?',
 'Art. 5º, §2º; Anexo II, 1, II',
 'Implante TOTP (RFC 6238) ou equivalente. Fator único só é admitido para perfis de menor risco, e ainda assim com justificativa técnica registrada.'],

['1.3.c', 1, 3, $C, 'Está vedado e efetivamente eliminado o uso de contas genéricas ou credenciais compartilhadas?',
 'Art. 5º, §2º (parte final); Anexo II, 1, III',
 'Faça varredura no diretório de usuários. Contas do tipo "cartorio", "atendimento", "admin" precisam ser nominadas ou desativadas.'],

['1.3.d', 1, 1, $C, 'As contas técnicas automatizadas (integrações/rotinas) possuem segregação de privilégios, registro auditável, identificação inequívoca e vedação de uso para prática direta de atos?',
 'Art. 5º, §3º',
 'Documente cada service account: sistema responsável, escopo, privilégios e trilha. Bloqueie-as para a prática de atos notariais/registrais.'],

['1.4', 1, 3, $C, 'Existe registro formal das operações de tratamento de dados pessoais (ROPA) instituído e atualizado?',
 'Art. 7º, §1º; Anexo IV, item 1.4; Anexo III, 4.8, III',
 'Gere o Registro das Operações de Tratamento (aba Termos): finalidade, base legal, categorias de titulares e dados, compartilhamentos, retenção e medidas de segurança.'],

['1.5', 1, 3, $C, 'Há procedimento documentado que assegure a comunicação de incidente crítico à Corregedoria competente em até 72 horas?',
 'Art. 11, §1º; Anexo II, 4, III; Anexo IV, item 1.5',
 'Formalize o fluxo de resposta a incidentes com classificação por gravidade (crítico, alto, médio, baixo) e gatilho automático de comunicação em 72h. Incidentes com risco relevante ao titular também vão à ANPD (art. 7º, §3º).'],

['1.6', 1, 1, $C, 'A meta de diligência reforçada de comunicação em até 24 horas da ciência do incidente foi incorporada à governança?',
 'Anexo IV, item 1.6',
 'É meta de governança, não prazo peremptório. Registre-a na PSI e no procedimento de incidentes como padrão interno.'],

['1.7', 1, 3, $C, 'Existe inventário completo de ativos tecnológicos, integrações, bancos de dados, certificados digitais, softwares, histórico de atualizações e contratos?',
 'Art. 6º, III; Anexo IV, item 1.7',
 'Use a aba Inventário deste módulo. Ela já contempla as sete categorias exigidas e exporta o documento assinável.'],

['1.8.a', 1, 2, $C, 'Todos os softwares utilizados possuem licenciamento regular para uso comercial?',
 'Art. 4º, §2º; Anexo IV, item 1.8',
 'Software livre/código aberto é admitido, desde que compatível com as normas de segurança. Guarde notas fiscais e termos de licença.'],

['1.8.b', 1, 3, $C, 'Nenhum sistema operacional, SGBD, aplicação crítica ou componente está fora do ciclo de suporte oficial (EOL)?',
 'Art. 4º, §3º',
 'Componente em EOL não é aceito para fins de conformidade. Mantenha evidência documental da vigência do suporte técnico e das atualizações de segurança.'],

['1.8.c', 1, 3, $C, 'Os contratos com terceiros que tratam, armazenam ou processam dados foram revisados e contêm cláusulas de confidencialidade, reversibilidade, portabilidade em formato não proprietário, documentação técnica de migração, cooperação na transição, gestão de incidentes e conformidade com a LGPD?',
 'Anexo IV, item 1.8; art. 13, §6º',
 'São sete cláusulas cumulativas. A ausência de qualquer uma caracteriza dependência estrutural não mitigada (art. 15).'],

['1.9', 1, 3, $C, 'A declaração de conclusão da Etapa 1 foi firmada pelo titular/interino/interventor e registrada no Sistema Justiça Aberta?',
 'Anexo IV, item 1.9; art. 17',
 'Só produza após 100% dos itens 1.1 a 1.8. É vedada declaração parcial, proporcional ou condicionada (Anexo IV, Disposições gerais, II).'],

// ======================= ETAPA 2 =======================
['2.1.a', 2, 2, $C, 'A serventia utiliza fonte de energia estável e confiável para os ativos críticos de TI?',
 'Anexo I, 1, I; Anexo IV, item 2.1', 'Documente o circuito dedicado e a proteção contra surtos.'],

['2.1.b', 2, 3, $C, 'Há laudo de aterramento atualizado, subscrito por profissional habilitado e com ART?',
 'Art. 12, §8º; Anexo I, 1, II',
 'Este é um dos itens mais esquecidos. Contrate profissional habilitado, exija a ART e mantenha o laudo no dossiê.'],

['2.1.c', 2, 3, $C, 'Existe SAI/UPS (nobreak) com autonomia suficiente para salvamento de dados, encerramento de transações e desligamento ordenado — preferencialmente 30 minutos?',
 'Anexo I, 1, III; Anexo IV, item 2.1',
 'Meça a autonomia real sob carga e registre o teste. Autonomia de 30 min é recomendação preferencial; o requisito objetivo é o safe shutdown.'],

['2.2', 2, 2, $C, 'Existe plano de contingência energética compatível com a classe da serventia?',
 'Anexo I, 1, IV; Anexo IV, item 2.2', 'Gere o Plano de Contingência Energética (aba Termos): gerador/segunda fonte, ordem de desligamento, responsáveis e acionamento.'],

['2.3', 2, 2, $C, 'O ambiente físico dos equipamentos críticos é isolado, com acesso restrito e proteção contra incêndio, inundação, variação térmica e acesso indevido?',
 'Anexo I, 3; Anexo IV, item 2.3',
 'Se a serventia opera integralmente em nuvem, o requisito é atendido por documentação contratual que comprove controles equivalentes do fornecedor.'],

['2.4.a', 2, 2, $C, 'A conectividade contratada é compatível com a classe e permite concluir o backup incremental e a sincronização dentro do RPO?',
 'Anexo I, 2, II a V',
 'A velocidade nominal (2/10/50 Mbps) é referencial. O que vale é o teste documentado de aderência ao RPO. Soluções híbridas e "seed" inicial são admitidas.'],

['2.4.b', 2, 1, $C, 'Há roteador para gerenciar conexões internas/externas e switch para os dispositivos internos?',
 'Anexo I, 2, VI e VII', 'Registre modelos, firmware e data da última atualização no inventário.'],

['2.4.c', 2, 2, [2, 3], 'Existem múltiplos links ou tecnologia equivalente que assegure desempenho compatível com a classe?',
 'Anexo I, 2, VIII; Anexo I, 2.1',
 'Classe 1 fica dispensada de redundância simultânea quando RTO/RPO de 24h forem comprovadamente atendidos por backup periódico testado e restauração documentada.'],

['2.5.a', 2, 3, $C, 'O PCN e o PRD estão formalizados (em documentos distintos ou integrados)?',
 'Art. 3º, §1º; Anexo IV, item 2.5', 'Gere a minuta na aba Termos. Sem os quatro elementos abaixo, a etapa não pode ser declarada concluída.'],

['2.5.b', 2, 3, $C, 'O PCN/PRD contém identificação e avaliação estruturada de riscos?',
 'Anexo IV, item 2.5, I', 'Matriz de risco com probabilidade × impacto para cada ativo crítico do inventário.'],

['2.5.c', 2, 3, $C, 'O PCN/PRD define objetivamente as medidas de mitigação de cada risco?',
 'Anexo IV, item 2.5, II', 'Cada risco identificado deve ter controle correspondente, responsável e evidência.'],

['2.5.d', 2, 3, $C, 'O PCN/PRD estabelece expressamente RTO e RPO compatíveis com a classe da serventia?',
 'Anexo IV, item 2.5, III; Anexo II, 2.1 e 2.2',
 'Os valores por classe estão no painel. Registre-os textualmente no plano e comprove por teste de restauração.'],

['2.5.e', 2, 3, $C, 'O PCN/PRD prevê medidas de curto prazo (até 30 dias) e de médio prazo (até 90 dias) para resposta a incidentes e restauração da normalidade?',
 'Art. 3º, §2º; Anexo IV, item 2.5, IV', 'A ausência deste elemento veda expressamente a declaração de conclusão da Etapa 2.'],

['2.6', 2, 2, $C, 'Há equipamentos adequados, digitalizadores e impressoras compatíveis, e suporte técnico próprio ou contratado com atendimento contínuo?',
 'Anexo I, 6; Anexo IV, item 2.6', 'Guarde o contrato de suporte com SLA compatível com o RTO da classe.'],

['2.7', 2, 3, $C, 'Há proteção básica de endpoint (antivírus/antimalware ou equivalente) em TODAS as estações e servidores?',
 'Anexo I, 4, I; Anexo IV, item 2.7', 'Cobertura total é condição mínima de integridade operacional. Exporte o relatório de agentes ativos do console.'],

['2.8', 2, 2, $C, 'Existe documento técnico simplificado da arquitetura tecnológica, com topologia de rede, ambientes utilizados, fluxos de dados críticos, localização dos backups, integrações externas e mecanismos de redundância?',
 'Anexo IV, item 2.8', 'São seis elementos obrigatórios. Gere a minuta na aba Termos e anexe o diagrama de topologia.'],

['2.9', 2, 3, $C, 'A declaração de conclusão da Etapa 2 foi firmada e registrada no Sistema Justiça Aberta?',
 'Anexo IV, item 2.9; art. 17', 'Pressupõe conclusão integral da Etapa 1 e de todos os itens 2.1 a 2.8.'],

// ======================= ETAPA 3 =======================
['3.1.a', 3, 3, $C, 'Os dados em trânsito são protegidos com TLS 1.2 ou superior (ou versão mantida e suportada)?',
 'Art. 9º, §1º, I; Anexo II, 2, I', 'Desative SSLv3/TLS 1.0/1.1. Verifique também as integrações e webhooks, não só o site.'],

['3.1.b', 3, 3, $C, 'Os dados críticos em repouso são cifrados com AES-256 ou padrão equivalente/superior?',
 'Art. 9º, §1º, II; Anexo II, 2, II',
 'Dados críticos = livros e atos eletrônicos, bases registrais, trilhas de auditoria, backups, integrações e dados sensíveis (art. 2º, V).'],

['3.1.c', 3, 3, $C, 'As rotinas de backup são cifradas, especialmente quando envolvem armazenamento externo ou infraestrutura de terceiros?',
 'Art. 9º, §1º, III; Anexo IV, item 3.1', 'Cifre na origem, antes do envio. Chave sob custódia da serventia.'],

['3.1.d', 3, 3, $C, 'Existe gestão formal de chaves com inventário de chaves e certificados, segregação de custódia, controle de acesso, política de rotação, registro de geração/renovação/revogação e revisão periódica dos padrões?',
 'Art. 9º, §3º; Anexo II, 2, IV e V; Anexo IV, item 3.1',
 'São seis subelementos cumulativos. Registre cada certificado (A1/A3, ICP-Brasil, TLS) com validade e responsável pela custódia.'],

['3.2.a', 3, 3, $C, 'As cópias completas de backup são automatizadas e respeitam o intervalo máximo da classe (24h/48h/72h)?',
 'Anexo IV, item 3.2, I; art. 12, §2º', 'O intervalo da sua classe aparece no painel. Não confunda cópia completa com o atendimento ao RPO.'],

['3.2.b', 3, 3, $C, 'Há cópias incrementais (ou replicação contínua / point-in-time recovery) compatíveis com o RPO da classe?',
 'Art. 12, §§3º e 4º; Anexo IV, item 3.2, II', 'O RPO é atendido pelo mecanismo incremental, não pela periodicidade da cópia completa.'],

['3.2.c', 3, 3, $C, 'Os backups estão armazenados em, no mínimo, dois ambientes tecnicamente independentes, com redundância geográfica ou lógica equivalente?',
 'Art. 12, §6º; Anexo IV, item 3.2, III',
 'Nuvem com redundância multirregião, mídia off-site ou arquitetura híbrida. Vedado ponto único de falha.'],

['3.2.d', 3, 3, $C, 'Ao menos um dos ambientes de backup está protegido contra criptografia maliciosa, exclusão indevida ou comprometimento simultâneo (WORM, retention lock, versionamento bloqueado ou equivalente)?',
 'Anexo IV, item 3.2, III, d', 'Este é o controle anti-ransomware. Imutabilidade com bloqueio contra exclusão administrativa.'],

['3.2.e', 3, 2, [1, 2], 'Se usa nuvem comercial de amplo mercado, os arquivos são cifrados na origem (AES-256+) com a chave sob custódia exclusiva da serventia, e não do provedor?',
 'Anexo IV, item 3.2.1.1',
 'É a hipótese simplificada de redundância para Classes 1 e 2. A custódia exclusiva da chave é o elemento decisivo.'],

['3.3', 3, 3, $C, 'As rotinas de backup são monitoradas quanto à execução e integridade, com alerta técnico automático ao responsável e abertura formal de chamado em caso de falha?',
 'Art. 12, §10; Anexo IV, item 3.3', 'Backup sem monitoramento não conta. Configure alerta ativo (e-mail/telegram) e trilha do chamado.'],

['3.4.a', 3, 3, $C, 'Há firewall stateful com IPS/IDS (ou solução equivalente) e proteção perimetral que controle tráfego de entrada e saída e registre eventos de segurança?',
 'Art. 8º, §3º; Anexo I, 4, II; Anexo IV, item 3.4', 'Classe 1: no mínimo filtragem de conexões externas, registro de eventos críticos e configuração documentada.'],

['3.4.b', 3, 2, $C, 'Existe segmentação lógica de rede separando ambientes administrativos/servidores de ambientes de atendimento ao público e dispositivos externos?',
 'Art. 8º, VII', 'Classes 2 e 3: VLANs ou equivalente formal. Classe 1: medida técnica idônea que impeça comunicação irrestrita.'],

['3.4.c', 3, 2, [2, 3], 'A proteção perimetral contempla inspeção ativa de tráfego, retenção de registros auditáveis e detecção/bloqueio de ameaças avançadas?',
 'Art. 8º, §4º', 'Requisitos cumulativos para Classes 2 e 3. Admite-se solução integrada, desde que demonstrada a equivalência funcional.'],

['3.5', 3, 2, $C, 'Foi implementada solução avançada de proteção de endpoint (monitoramento ativo, detecção de comportamento anômalo ou resposta a incidentes), quando compatível com a classe?',
 'Anexo IV, item 3.5', 'EDR/XDR ou recurso equivalente. Para Classe 1, avalie proporcionalidade e registre a justificativa técnica.'],

['3.6', 3, 2, $C, 'O SGBD utilizado possui integridade transacional e logs ativos?',
 'Anexo I, 5, I; Anexo IV, item 3.6', 'MySQL/InnoDB, PostgreSQL ou SQL Server com binlog/WAL habilitado e retido.'],

['3.7', 3, 2, $C, 'Há mecanismos de tolerância a falhas ou alta disponibilidade compatíveis com a classe?',
 'Art. 12, §7º; Anexo I, 5, II e III; Anexo IV, item 3.7',
 'Classes 1 e 2: virtualização com restauração automatizada, warm standby, nuvem gerenciada ou redundância local simples — desde que documentados os testes.'],

['3.8.a', 3, 3, $C, 'As trilhas de auditoria são imutáveis, registrando identificação inequívoca do usuário, data, hora, minuto, segundo, natureza da ação e resultado obtido?',
 'Art. 10, caput e §1º; Anexo IV, item 3.8', 'Proteção contra alteração, exclusão não autorizada e perda acidental, com mecanismo de verificação de integridade.'],

['3.8.b', 3, 2, $C, 'Há sincronização de tempo por fonte confiável (NTP)?',
 'Anexo II, 3, III', 'Aponte para fonte confiável (ex.: NTP.br) e monitore o desvio.'],

['3.8.c', 3, 2, $C, 'O nível da trilha de auditoria atende ao mínimo da classe (Essencial para Classes 1 e 2; Intermediário para Classe 3)?',
 'Art. 10, §§3º, 4º e 5º',
 'Essencial: autenticação, operações principais e erros relevantes. Intermediário acrescenta alterações cadastrais, exportações de dados e tentativas de acesso não autorizado.'],

['3.8.d', 3, 3, $C, 'A retenção das trilhas de auditoria é de, no mínimo, 5 anos?',
 'Anexo II, 3, IV; art. 10, §6º', 'A política de retenção de backup não substitui a guarda das trilhas (art. 12, §11).'],

['3.8.e', 3, 2, $C, 'As trilhas de auditoria estão integradas às rotinas de backup e recuperação, com preservação íntegra e rastreável?',
 'Anexo IV, item 3.8', 'Restaurar o banco sem restaurar a trilha é não conformidade.'],

['3.9', 3, 3, $C, 'A declaração de conclusão da Etapa 3 foi firmada e registrada no Sistema Justiça Aberta?',
 'Anexo IV, item 3.9', 'Pressupõe cumprimento integral e comprovado da Etapa 2.'],

// ======================= ETAPA 4 =======================
['4.1', 4, 3, $C, 'Foi emitido relatório de conformidade de auditoria atestando imutabilidade, identificação inequívoca do usuário, sincronização temporal, retenção mínima e integração das trilhas às rotinas de backup e recuperação?',
 'Anexo IV, itens 4.1 e 4.1.1',
 'Não basta demonstrar que o dado "volta". O relatório precisa comprovar quem fez o quê, quando, com tempo sincronizado e registros efetivamente imutáveis.'],

['4.2', 4, 2, $C, 'Existe rotina documentada de atualização periódica de sistemas e aplicações?',
 'Anexo II, 5, I; Anexo IV, item 4.2', 'Janela de manutenção, responsável, registro de versões antes/depois e plano de rollback.'],

['4.3.a', 4, 3, $C, 'As vulnerabilidades classificadas como críticas são tratadas em prazo máximo de 30 dias (quando não há exploração ativa)?',
 'Art. 11, §3º; Anexo II, 5, II', 'O prazo é do Anexo II e não pode ser flexibilizado por norma interna.'],

['4.3.b', 4, 3, $C, 'Havendo exploração ativa, risco iminente ou comprometimento relevante, são adotadas medidas de contenção e correção emergencial, preferencialmente em até 72 horas?',
 'Anexo II, 5, II e III; Anexo IV, item 4.3, II', 'Registre também as medidas mitigatórias aplicadas durante a janela de correção.'],

['4.3.c', 4, 2, $C, 'Há registro formal, auditável e cronológico das providências, com data de identificação, classificação de risco, medidas e data de encerramento?',
 'Art. 11, §4º; Anexo IV, item 4.3, III', 'Esse registro compõe o dossiê técnico da serventia.'],

['4.4', 4, 3, $C, 'É realizada simulação anual de cenário de desastre para validação do PCN e do PRD?',
 'Anexo II, 6, II; Anexo IV, item 4.4', 'Documente cenário, participantes, cronometragem e desvios em relação a RTO/RPO.'],

['4.5', 4, 3, $C, 'São realizados testes documentados de restauração de backup na periodicidade da classe (semestral para Classe 3; anual para Classes 1 e 2), com guarda dos registros por 5 anos?',
 'Art. 12, §9º; Anexo I, 5, V e VI; Anexo IV, item 4.5',
 'Use o modelo de Ata do Anexo V (disponível na aba Termos). Ele já traz aferição de RTO/RPO e validação amostral.'],

['4.6', 4, 2, $C, 'São realizadas avaliações técnicas periódicas de segurança?',
 'Anexo II, 6, III; Anexo IV, item 4.6', 'Varredura de vulnerabilidades e revisão de configuração de borda, com relatório datado.'],

['4.7', 4, 3, [3], 'É realizado teste de intrusão (pentest) ou metodologia equivalente, no mínimo a cada 2 anos e sempre que houver alteração relevante de infraestrutura, arquitetura, exposição à internet ou substituição de fornecedor crítico?',
 'Anexo II, 6, IV; Anexo IV, item 4.7',
 'Hipóteses de dispensa: serventia Classe 3 integralmente em SaaS/centralizado sem servidores locais, mediante relatório técnico da desenvolvedora e declaração do titular quanto a SO com suporte ativo, antivírus atualizado e firewall habilitado (Anexo II, 6.3). Relatório coletivo do ambiente compartilhado também é aceito (6.1 e 6.2).'],

['4.8', 4, 2, $C, 'Todos os incidentes têm análise de causa raiz e registro de lições aprendidas?',
 'Art. 11, §2º; Anexo II, 4, IV; Anexo IV, item 4.8', 'Sem exceção — inclusive incidentes de baixa gravidade.'],

['4.9', 4, 3, $C, 'A declaração de conclusão da Etapa 4 foi firmada e registrada no Sistema Justiça Aberta?',
 'Anexo IV, item 4.9', 'Exige a manutenção efetiva dos requisitos das etapas anteriores.'],

// ======================= ETAPA 5 =======================
['5.1', 5, 3, $C, 'Os sistemas estão aptos à integração com plataformas eletrônicas de fiscalização, com intercâmbio em formato aberto, identificação inequívoca da serventia e do sistema solicitante, canal seguro e registros auditáveis das integrações?',
 'Art. 19, I a IV; Anexo IV, item 5.1', 'São quatro requisitos cumulativos. Vedada imposição de solução específica quando demonstrada equivalência funcional.'],

['5.2', 5, 2, $C, 'São adotados padrões abertos e formatos não proprietários (ex.: PDF/A, XML), prevenindo dependência exclusiva de fornecedor?',
 'Art. 8º, III; Anexo II, 7; Anexo IV, item 5.2', 'Avalie a exportação do acervo: se depende de anuência do fornecedor, há dependência estrutural não mitigada (art. 15, III).'],

['5.3', 5, 2, $C, 'Há capacitação periódica dos responsáveis e colaboradores, com registro formal das ações realizadas?',
 'Anexo I, 7; Anexo IV, item 5.3', 'Lista de presença, conteúdo programático, carga horária e data. Inclua rotinas de backup e operação segura.'],

['5.4', 5, 2, $C, 'A PSI e os padrões criptográficos são revisados formalmente sempre que houver alteração normativa relevante ou evolução tecnológica?',
 'Anexo III, 4.9, IV e 5, IV; Anexo IV, item 5.4', 'Institua revisão mínima anual e revisão extraordinária por gatilho normativo.'],

['5.5', 5, 2, $C, 'Os registros auditáveis são mantidos por, no mínimo, 5 anos?',
 'Anexo III, 5, II; Anexo IV, item 5.5', 'Vale para PSI, atas, dossiês, trilhas e testes de restauração.'],

['5.6.a', 5, 3, $C, 'Existe plano formal de reversibilidade e portabilidade de dados?',
 'Art. 6º, III; Anexo IV, item 5.6', 'Gere a minuta na aba Termos. O plano viabiliza a transferência organizada do acervo ao sucessor.'],

['5.6.b', 5, 3, $C, 'Foi realizada simulação documentada de extração integral do acervo em formato interoperável e não proprietário, na periodicidade da classe (24/30/36 meses) e sempre que houver alteração relevante de fornecedor, arquitetura ou governança?',
 'Anexo IV, itens 5.6, 5.6.1 e 5.6.2',
 'Admite-se ambiente de contingência/laboratório, exportação estrutural com verificação de integridade e amostragem estatisticamente representativa. Sem essa evidência, a Etapa 5 não pode ser homologada.'],

['5.6.c', 5, 2, $C, 'A dependência estrutural em relação ao fornecedor está mitigada (cláusula de reversibilidade/portabilidade, teste de extração comprovado e inexistência de restrição técnica ou contratual à migração)?',
 'Art. 15, I a III', 'Os três requisitos são cumulativos. Prevalece a realidade técnica e contratual sobre a nomenclatura do contrato.'],

['5.7', 5, 3, $C, 'A declaração de conclusão da Etapa 5 foi firmada e registrada no Sistema Justiça Aberta?',
 'Anexo IV, item 5.7; art. 17, §1º', 'A declaração do art. 17 deve ser renovada anualmente, acompanhada de síntese do dossiê técnico.'],
    ];

    // normaliza para array associativo
    $out = [];
    foreach ($cat as $r) {
        $out[] = ['cod' => $r[0], 'etapa' => $r[1], 'peso' => $r[2], 'classes' => $r[3],
                  'pergunta' => $r[4], 'base' => $r[5], 'sugestao' => $r[6]];
    }
    $cat = $out;
    return $cat;
}

function p213_catalogo_por_classe($classe) {
    $out = [];
    foreach (p213_catalogo() as $item) {
        if (in_array((int)$classe, $item['classes'], true)) $out[] = $item;
    }
    return $out;
}

function p213_etapas() {
    return [
        1 => 'Governança, estruturação organizacional e conformidade legal',
        2 => 'Infraestrutura e continuidade operacional',
        3 => 'Proteção do acervo digital e resiliência tecnológica',
        4 => 'Monitoramento, auditoria e validação de controles',
        5 => 'Interoperabilidade, consolidação e governança evolutiva',
    ];
}

// ---------------------------------------------------------------------------
// 7. Motor de conformidade
// ---------------------------------------------------------------------------
function p213_status_validos() {
    return ['conforme', 'parcial', 'nao_conforme', 'nao_aplicavel', 'nao_avaliado'];
}

function p213_fator($status) {
    switch ($status) {
        case 'conforme': return 1.0;
        case 'parcial':  return 0.5;
        default:         return 0.0;
    }
}

function p213_respostas() {
    $conn = p213_db();
    $out = [];
    $res = $conn->query("SELECT * FROM p213_respostas");
    while ($row = $res->fetch_assoc()) $out[$row['codigo']] = $row;
    return $out;
}

/**
 * Calcula conformidade geral e por etapa.
 * "não aplicável" sai do denominador; "não avaliado" permanece (pesa como zero).
 */
function p213_score($classe, $respostas = null) {
    if ($respostas === null) $respostas = p213_respostas();
    $itens = p213_catalogo_por_classe($classe);

    $etapas = [];
    foreach (array_keys(p213_etapas()) as $e) {
        $etapas[$e] = ['peso' => 0, 'obtido' => 0, 'total' => 0, 'conforme' => 0,
                       'parcial' => 0, 'nao_conforme' => 0, 'nao_aplicavel' => 0,
                       'nao_avaliado' => 0, 'pct' => 0.0, 'apto_declarar' => false];
    }

    foreach ($itens as $it) {
        $e   = $it['etapa'];
        $st  = isset($respostas[$it['cod']]) ? $respostas[$it['cod']]['status'] : 'nao_avaliado';
        if (!in_array($st, p213_status_validos(), true)) $st = 'nao_avaliado';

        $etapas[$e]['total']++;
        $etapas[$e][$st]++;
        if ($st !== 'nao_aplicavel') {
            $etapas[$e]['peso']   += $it['peso'];
            $etapas[$e]['obtido'] += $it['peso'] * p213_fator($st);
        }
    }

    $pesoT = 0; $obtT = 0;
    foreach ($etapas as $e => &$d) {
        $d['pct'] = $d['peso'] > 0 ? round($d['obtido'] / $d['peso'] * 100, 1) : 100.0;
        // Anexo IV, Disposições gerais, II: vedada declaração parcial ou proporcional.
        $d['apto_declarar'] = ($d['nao_conforme'] === 0 && $d['parcial'] === 0 && $d['nao_avaliado'] === 0);
        $pesoT += $d['peso'];
        $obtT  += $d['obtido'];
    }
    unset($d);

    // sequencialidade: etapa N só é declarável se N-1 estiver apta
    $anterior = true;
    foreach ($etapas as $e => &$d) {
        $d['liberada'] = $anterior;
        $anterior = $anterior && $d['apto_declarar'];
    }
    unset($d);

    return [
        'geral'  => $pesoT > 0 ? round($obtT / $pesoT * 100, 1) : 0.0,
        'peso'   => $pesoT,
        'obtido' => $obtT,
        'etapas' => $etapas,
        'itens'  => count($itens),
    ];
}

/** Plano de ação: pendências ordenadas por etapa e peso. */
function p213_plano_acao($classe, $respostas = null) {
    if ($respostas === null) $respostas = p213_respostas();
    $out = [];
    foreach (p213_catalogo_por_classe($classe) as $it) {
        $st = isset($respostas[$it['cod']]) ? $respostas[$it['cod']]['status'] : 'nao_avaliado';
        if (in_array($st, ['conforme', 'nao_aplicavel'], true)) continue;
        $it['status'] = $st;
        $it['obs']    = isset($respostas[$it['cod']]) ? $respostas[$it['cod']]['observacao'] : '';
        $out[] = $it;
    }
    usort($out, function ($a, $b) {
        if ($a['etapa'] !== $b['etapa']) return $a['etapa'] - $b['etapa'];
        if ($a['peso']  !== $b['peso'])  return $b['peso'] - $a['peso'];
        return strcmp($a['cod'], $b['cod']);
    });
    return $out;
}

function p213_criticidade($peso) {
    return $peso >= 3 ? 'CRÍTICO' : ($peso == 2 ? 'ALTO' : 'MÉDIO');
}

function p213_status_label($s) {
    $m = ['conforme' => 'Conforme', 'parcial' => 'Parcialmente atendido',
          'nao_conforme' => 'Não conforme', 'nao_aplicavel' => 'Não aplicável',
          'nao_avaliado' => 'Não avaliado'];
    return isset($m[$s]) ? $m[$s] : $s;
}

function p213_status_cor($s) {
    $m = ['conforme' => 'success', 'parcial' => 'warning', 'nao_conforme' => 'danger',
          'nao_aplicavel' => 'secondary', 'nao_avaliado' => 'light'];
    return isset($m[$s]) ? $m[$s] : 'light';
}

// ---------------------------------------------------------------------------
// 8. Configuração da serventia
// ---------------------------------------------------------------------------
function p213_config() {
    $conn = p213_db();
    $res = $conn->query("SELECT * FROM p213_config WHERE id = 1");
    $cfg = $res ? $res->fetch_assoc() : null;
    if (!$cfg) {
        $conn->query("INSERT INTO p213_config (id) VALUES (1)");
        $res = $conn->query("SELECT * FROM p213_config WHERE id = 1");
        $cfg = $res->fetch_assoc();
    }
    if (empty($cfg['classe_manual'])) {
        $enq = p213_enquadrar($cfg['receita_semestral'], (float)$cfg['fator_ipca']);
        $cfg['classe']    = $enq['classe'];
        $cfg['subclasse'] = $enq['subclasse'];
    } else {
        $cfg['classe']    = (int)$cfg['classe_manual'];
        $cfg['subclasse'] = $cfg['subclasse_manual'] ?: '—';
    }
    return $cfg;
}

function p213_log($acao, $detalhe = '') {
    $conn = p213_db();
    $stmt = $conn->prepare("INSERT INTO p213_auditoria (usuario, acao, detalhe, ip) VALUES (?,?,?,?)");
    $u = p213_usuario();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $stmt->bind_param('ssss', $u, $acao, $detalhe, $ip);
    $stmt->execute();
    $stmt->close();
}

// ---------------------------------------------------------------------------
// 9. Layout (componentes próprios — não dependem das classes do Bootstrap)
// ---------------------------------------------------------------------------
function p213_head($titulo) {
    $a = P213_ASSETS;
    echo '<!DOCTYPE html><html lang="pt-br"><head>'
       . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
       . '<title>' . p213_esc($titulo) . ' — Atlas</title>'
       . '<link rel="stylesheet" href="' . $a . '/css/bootstrap.min.css">'
       . '<link rel="stylesheet" href="' . $a . '/css/font-awesome.min.css">'
       . '<link rel="stylesheet" href="' . $a . '/css/style.css">'
       . '<link rel="icon" href="' . $a . '/img/favicon.png" type="image/png">'
       . '<link rel="stylesheet" href="p213.css">'
       . '</head><body>';
    if (is_file(P213_MENU)) include P213_MENU;
    echo '<div id="main" class="main-content p213-scope"><div class="p213-wrap">';
}

function p213_foot($extraJs = '') {
    $a = P213_ASSETS;
    echo '</div></div>'
       . '<script src="' . $a . '/js/jquery.min.js"></script>'
       . '<script src="' . $a . '/js/bootstrap.bundle.min.js"></script>'
       . '<script src="' . $a . '/js/sweetalert2.all.min.js"></script>'
       . '<script>if(typeof Swal==="undefined"){var s=document.createElement("script");'
       . 's.src="https://cdn.jsdelivr.net/npm/sweetalert2@11";document.head.appendChild(s);}</script>'
       . '<script>console.info("ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213");</script>';
    if ($extraJs) echo '<script>' . $extraJs . '</script>';
    echo '</body></html>';
}

function p213_nav($atual) {
    $itens = [
        'index.php'        => ['fa-tachometer', 'Painel'],
        'diagnostico.php'  => ['fa-check-square-o', 'Diagnóstico'],
        'evidencias.php'   => ['fa-paperclip', 'Evidências'],
        'inventario.php'   => ['fa-server', 'Inventário'],
        'termos.php'       => ['fa-file-text-o', 'Termos'],
        'relatorio.php'    => ['fa-file-pdf-o', 'Relatórios'],
        'configuracao.php' => ['fa-cog', 'Configuração'],
    ];
    echo '<nav class="p213-nav"><div class="p213-nav-track">';
    foreach ($itens as $href => $d) {
        $ativo = ($href === $atual) ? ' active' : '';
        echo '<a class="p213-tab' . $ativo . '" href="' . $href . '">'
           . '<i class="fa ' . $d[0] . '" aria-hidden="true"></i><span>' . $d[1] . '</span></a>';
    }
    echo '</div></nav>';
}

function p213_hero($titulo, $sub) {
    echo '<header class="p213-hero">'
       . '<div class="p213-hero-glow" aria-hidden="true"></div>'
       . '<div class="p213-hero-row">'
       . '<div class="p213-hero-mark"><i class="fa fa-shield" aria-hidden="true"></i></div>'
       . '<div class="p213-hero-txt"><h1>' . p213_esc($titulo) . '</h1><p>' . $sub . '</p></div>'
       . '<span class="p213-chip">Provimento CN-CNJ n.&nbsp;213/2026</span>'
       . '</div></header>';
}

/** Medidor circular em SVG (renderiza igual em qualquer tema). */
function p213_ring($pct, $size = 176) {
    $pct = max(0, min(100, (float)$pct));
    $cor = $pct >= 90 ? '#0f9d78' : ($pct >= 60 ? '#b7791f' : ($pct > 0 ? '#d64545' : '#8f9aab'));
    $r   = 74; $c = 2 * M_PI * $r;
    $off = $c * (1 - $pct / 100);
    $val = number_format($pct, 1, ',', '.') . '%';
    return '<svg class="p213-ring" width="' . $size . '" height="' . $size . '" viewBox="0 0 176 176" role="img"'
        . ' aria-label="Aderência de ' . $val . '">'
        . '<circle class="p213-ring__track" cx="88" cy="88" r="' . $r . '" fill="none" stroke-width="13"/>'
        . '<circle cx="88" cy="88" r="' . $r . '" fill="none" stroke="' . $cor . '" stroke-width="13"'
        . ' stroke-linecap="round" stroke-dasharray="' . round($c, 2) . '" stroke-dashoffset="' . round($off, 2) . '"'
        . ' transform="rotate(-90 88 88)"/>'
        . '<text class="p213-ring__val" x="88" y="86" text-anchor="middle" dominant-baseline="middle">' . $val . '</text>'
        . '<text class="p213-ring__lbl" x="88" y="110" text-anchor="middle">CONFORMIDADE</text>'
        . '</svg>';
}

// ---------------------------------------------------------------------------
// 10. TCPDF
// ---------------------------------------------------------------------------
function p213_tcpdf() {
    global $P213_TCPDF_CANDIDATES;
    foreach ($P213_TCPDF_CANDIDATES as $f) {
        if (is_file($f)) { require_once $f; return true; }
    }
    return false;
}

// ---------------------------------------------------------------------------
// 11. Evidências e IA (Gemini)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/p213_evid.php';
