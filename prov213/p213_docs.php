<?php
/**
 * ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213
 * Modelos dos termos/documentos exigidos pelo Provimento 213/2026.
 * Placeholders: {{CHAVE}} — resolvidos em p213_doc_vars().
 */
require_once __DIR__ . '/p213_lib.php';

function p213_doc_vars() {
    $cfg = p213_config();
    $classe = (int)$cfg['classe'];
    $par = p213_parametros($classe);
    $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto',
              'setembro','outubro','novembro','dezembro'];
    $hoje = date('j') . ' de ' . $meses[(int)date('n') - 1] . ' de ' . date('Y');

    return [
        '{{SERVENTIA}}'      => $cfg['serventia'] ?: '__________________________',
        '{{CNS}}'            => $cfg['cns'] ?: '__________',
        '{{CNPJ}}'           => $cfg['cnpj'] ?: '__________',
        '{{ENDERECO}}'       => $cfg['endereco'] ?: '__________________________',
        '{{MUNICIPIO_UF}}'   => $cfg['municipio_uf'] ?: '__________',
        '{{TITULAR}}'        => $cfg['titular'] ?: '__________________________',
        '{{TITULAR_QUALIF}}' => $cfg['titular_qualif'],
        '{{RESP_TEC}}'       => $cfg['responsavel_tec'] ?: '__________________________',
        '{{DPO}}'            => $cfg['encarregado_dpo'] ?: '__________________________',
        '{{DPO_CONTATO}}'    => $cfg['dpo_contato'] ?: '__________________________',
        '{{CORREGEDORIA}}'   => $cfg['corregedoria'],
        '{{CLASSE}}'         => (string)$classe,
        '{{SUBCLASSE}}'      => $cfg['subclasse'],
        '{{RPO}}'            => $par['rpo'],
        '{{RTO}}'            => $par['rto'],
        '{{BACKUP_FULL}}'    => $par['backup_full'],
        '{{LINK}}'           => $par['link'],
        '{{TESTE_REST}}'     => $par['teste_restauracao'],
        '{{TRILHA}}'         => $par['trilha'],
        '{{EXTRACAO}}'       => $par['extracao'],
        '{{PENTEST}}'        => $par['pentest'],
        '{{DATA_EXTENSO}}'   => $hoje,
        '{{TABELA_ATIVOS}}'  => p213_tabela_ativos(),
    ];
}

function p213_tabela_ativos() {
    $conn = p213_db();
    $res = $conn->query("SELECT * FROM p213_ativos ORDER BY categoria, nome");
    $linhas = '';
    while ($a = $res->fetch_assoc()) {
        $linhas .= '<tr><td>' . p213_esc($a['categoria']) . '</td><td>' . p213_esc($a['nome'])
            . '</td><td>' . p213_esc($a['fabricante'] . ' ' . $a['versao'])
            . '</td><td>' . p213_esc($a['criticidade'])
            . '</td><td>' . ($a['suporte_ativo'] ? 'Sim' : 'NÃO (EOL)')
            . '</td><td>' . p213_esc($a['fornecedor'])
            . '</td><td>' . ($a['validade'] ? date('d/m/Y', strtotime($a['validade'])) : '—')
            . '</td></tr>';
    }
    if ($linhas === '') $linhas = '<tr><td colspan="7">Nenhum ativo cadastrado.</td></tr>';
    return '<table border="1" cellpadding="4"><thead><tr>'
         . '<th>Categoria</th><th>Ativo</th><th>Fabricante/versão</th><th>Criticidade</th>'
         . '<th>Suporte</th><th>Fornecedor</th><th>Validade</th></tr></thead><tbody>'
         . $linhas . '</tbody></table>';
}

function p213_render($html) {
    $v = p213_doc_vars();
    return strtr($html, $v);
}

$RODAPE = '<br><br><p style="text-align:center">{{MUNICIPIO_UF}}, {{DATA_EXTENSO}}.</p>'
        . '<br><br><p style="text-align:center">_________________________________________<br>'
        . '<strong>{{TITULAR}}</strong><br>{{TITULAR_QUALIF}} — {{SERVENTIA}} (CNS {{CNS}})</p>';

$CABEC = '<p style="text-align:center"><strong>{{SERVENTIA}}</strong><br>'
       . 'CNS {{CNS}} — CNPJ {{CNPJ}}<br>{{ENDERECO}} — {{MUNICIPIO_UF}}</p><hr>';

/**
 * Cada documento: id => [titulo, base normativa, resumo, corpo HTML]
 */
function p213_documentos() {
    global $RODAPE, $CABEC;
    return [

'designacao_rt' => [
 'titulo' => 'Termo de Designação de Responsável Técnico Interno',
 'base'   => 'Anexo IV, Etapa 1, item 1.1, I',
 'etapa'  => 1,
 'resumo' => 'Designa a pessoa encarregada da implementação técnica do Provimento na serventia.',
 'html'   => $CABEC . '
<h2 style="text-align:center">TERMO DE DESIGNAÇÃO DE RESPONSÁVEL TÉCNICO INTERNO</h2>
<p><strong>{{TITULAR}}</strong>, {{TITULAR_QUALIF}} da {{SERVENTIA}}, inscrita no CNS {{CNS}}, no uso de suas
atribuições e em cumprimento ao item 1.1, I, do Anexo IV do Provimento CN-CNJ n. 213, de 20 de fevereiro de 2026,
<strong>DESIGNA</strong> o(a) Sr.(a) <strong>{{RESP_TEC}}</strong> como <strong>Responsável Técnico Interno</strong>
pela implementação dos padrões mínimos de tecnologia da informação e comunicação nesta serventia.</p>

<p><strong>Atribuições:</strong></p>
<ol>
<li>coordenar a execução das Etapas 1 a 5 do Anexo IV, observada a ordem sequencial e cumulativa;</li>
<li>manter atualizado o inventário de ativos tecnológicos, integrações, bancos de dados, certificados digitais,
softwares, histórico de atualizações e contratos (item 1.7);</li>
<li>organizar e conservar o dossiê técnico e as evidências de conformidade, pelo prazo mínimo de 5 (cinco) anos;</li>
<li>zelar pela observância dos parâmetros da Classe {{CLASSE}}: RPO de {{RPO}}, RTO de {{RTO}}, cópia completa de
backup em intervalo {{BACKUP_FULL}} e trilha de auditoria de nível {{TRILHA}};</li>
<li>propor ao delegatário as medidas de correção de não conformidades e de tratamento de vulnerabilidades,
observados os prazos do Anexo II;</li>
<li>subsidiar tecnicamente as declarações de conclusão de etapa registradas no Sistema Justiça Aberta.</li>
</ol>

<p>A designação não transfere nem mitiga a responsabilidade pessoal e indelegável do delegatário quanto ao
cumprimento integral dos requisitos normativos (art. 13, §3º, e art. 14 do Provimento).</p>

<p><strong>Ciência do designado:</strong></p>
<p>_________________________________________<br>{{RESP_TEC}} — Responsável Técnico Interno</p>
' . $RODAPE],

'controlador' => [
 'titulo' => 'Declaração de Controlador de Dados Pessoais',
 'base'   => 'Anexo IV, item 1.1, II; art. 7º; Anexo III, 4.8, II',
 'etapa'  => 1,
 'resumo' => 'Caracteriza formalmente o responsável pela serventia como controlador, nos termos da LGPD.',
 'html'   => $CABEC . '
<h2 style="text-align:center">DECLARAÇÃO DE CONTROLADOR DE DADOS PESSOAIS</h2>
<p><strong>{{TITULAR}}</strong>, {{TITULAR_QUALIF}} da {{SERVENTIA}} (CNS {{CNS}}), <strong>DECLARA</strong>,
para os fins do item 1.1, II, do Anexo IV e do art. 7º do Provimento CN-CNJ n. 213/2026, bem como do
art. 5º, VI, da Lei n. 13.709/2018, que:</p>
<ol>
<li>figura como <strong>controlador</strong> dos dados pessoais tratados no âmbito desta serventia, a quem competem
as decisões referentes ao tratamento;</li>
<li>mantém <strong>registro das operações de tratamento</strong> de dados pessoais, nos termos do art. 7º, §1º, do
Provimento e do art. 37 da LGPD;</li>
<li>adota medidas técnicas e organizacionais adequadas à proteção dos dados, incluindo autenticação
individualizada, autenticação multifator para acessos administrativos, criptografia em trânsito e em repouso e
trilhas de auditoria com retenção mínima de 5 (cinco) anos;</li>
<li>comunicará à Autoridade Nacional de Proteção de Dados e à {{CORREGEDORIA}} os incidentes de segurança que
possam acarretar risco ou dano relevante aos titulares (art. 7º, §3º);</li>
<li>disponibiliza canal para o exercício dos direitos dos titulares.</li>
</ol>
<p>A presente declaração integra a Política Interna de Segurança da Informação e o dossiê técnico da serventia.</p>
' . $RODAPE],

'dpo' => [
 'titulo' => 'Termo de Designação de Encarregado (DPO)',
 'base'   => 'Anexo IV, item 1.1, III; art. 7º, §2º',
 'etapa'  => 1,
 'resumo' => 'Aplicável às Classes 2 e 3. A Classe 1 está dispensada (Prov. 214/2026, art. 88, §4º, do CNN).',
 'html'   => $CABEC . '
<h2 style="text-align:center">TERMO DE DESIGNAÇÃO DE ENCARREGADO PELO TRATAMENTO DE DADOS PESSOAIS</h2>
<p><strong>{{TITULAR}}</strong>, {{TITULAR_QUALIF}} da {{SERVENTIA}} (CNS {{CNS}}), na qualidade de controlador,
<strong>DESIGNA</strong> o(a) Sr.(a) <strong>{{DPO}}</strong> como <strong>Encarregado pelo Tratamento de Dados
Pessoais</strong>, nos termos do art. 41 da Lei n. 13.709/2018, do art. 7º, §2º, e do item 1.1, III, do Anexo IV
do Provimento CN-CNJ n. 213/2026.</p>
<p><strong>Canal de contato do encarregado:</strong> {{DPO_CONTATO}}</p>
<p><strong>Atribuições:</strong></p>
<ol>
<li>aceitar reclamações e comunicações dos titulares, prestar esclarecimentos e adotar providências;</li>
<li>receber comunicações da Autoridade Nacional de Proteção de Dados e adotar providências;</li>
<li>orientar colaboradores e prepostos a respeito das práticas de proteção de dados;</li>
<li>acompanhar o registro das operações de tratamento e a comunicação de incidentes (art. 7º, §3º).</li>
</ol>
<p><strong>Nota de proporcionalidade:</strong> as serventias enquadradas na Classe 1 estão dispensadas da
designação de encarregado, na forma do §4º do art. 88 do Código Nacional de Normas, incluído pelo Provimento
CN-CNJ n. 214/2026. A presente serventia está enquadrada na <strong>Classe {{CLASSE}}</strong>.</p>
<p><strong>Ciência do designado:</strong></p>
<p>_________________________________________<br>{{DPO}} — Encarregado (DPO)</p>
' . $RODAPE],

'psi' => [
 'titulo' => 'Política Interna de Segurança da Informação (PSI)',
 'base'   => 'Anexo III integralmente; Anexo IV, item 1.2',
 'etapa'  => 1,
 'resumo' => 'Minuta com a estrutura mínima obrigatória do Anexo III e as diretrizes preliminares do PCN/PRD.',
 'html'   => $CABEC . '
<h2 style="text-align:center">POLÍTICA INTERNA DE SEGURANÇA DA INFORMAÇÃO</h2>
<p><em>Versão 1.0 — aprovada em {{DATA_EXTENSO}}. Serventia enquadrada na Classe {{CLASSE}}, Subclasse {{SUBCLASSE}}.</em></p>

<h3>1. Objetivo</h3>
<p>Assegurar a confidencialidade, a integridade, a disponibilidade, a rastreabilidade e a conformidade legal do
acervo e dos sistemas informatizados da {{SERVENTIA}}, em cumprimento ao Provimento CN-CNJ n. 213/2026.</p>

<h3>2. Abrangência</h3>
<p>Aplica-se ao delegatário, interino, interventor, colaboradores, estagiários, prepostos, terceiros e fornecedores.</p>

<h3>3. Princípios</h3>
<p>Confidencialidade; Integridade; Disponibilidade; Rastreabilidade; Conformidade Legal.</p>

<h3>4. Estrutura mínima obrigatória</h3>

<h4>4.1 Governança e responsabilidades</h4>
<p>O titular da delegação responde pessoalmente pelo cumprimento integral desta Política, ainda que executada por
colaboradores, prepostos ou fornecedores (art. 5º, §1º). O Responsável Técnico Interno é {{RESP_TEC}}. O
Encarregado (DPO) é {{DPO}}.</p>

<h4>4.2 Regras de controle e revogação de acesso</h4>
<p>Autenticação individualizada obrigatória para todos os usuários. Autenticação multifator obrigatória para
acessos administrativos, gestão de sistemas, bancos de dados e funcionalidades críticas. É vedado o uso de contas
genéricas ou credenciais compartilhadas. Contas técnicas automatizadas são admitidas apenas para integração entre
sistemas, com segregação de privilégios, registro auditável e vedação de uso para a prática direta de atos.
O acesso é revogado imediatamente ao término do vínculo, com registro da revogação.</p>

<h4>4.3 Diretrizes de uso aceitável</h4>
<p>Os recursos tecnológicos destinam-se exclusivamente à atividade da serventia. É vedada a instalação de software
sem licenciamento regular, o uso de dispositivos removíveis não autorizados e o compartilhamento de credenciais.</p>

<h4>4.4 Integração com PCN e PRD (diretrizes preliminares)</h4>
<p>Ficam estabelecidos, desde já, o escopo, a governança, os objetivos estratégicos, o cronograma, as
responsabilidades e os critérios de continuidade que fundamentarão a elaboração do Plano de Continuidade de
Negócios (PCN) e do Plano de Recuperação de Desastres (PRD) na Etapa 2. Parâmetros mínimos da Classe {{CLASSE}}:
<strong>RTO de {{RTO}}</strong> e <strong>RPO de {{RPO}}</strong>. As medidas de resposta contemplarão providências
de curto prazo (até 30 dias) e de médio prazo (até 90 dias).</p>

<h4>4.5 Proteção física de ativos</h4>
<p>Os equipamentos críticos são mantidos em espaço isolado, com acesso restrito e proteção contra incêndio,
inundação, variação térmica e acesso indevido. Havendo utilização integral de infraestrutura em nuvem, mantém-se
documentação contratual que comprove os controles equivalentes do fornecedor.</p>

<h4>4.6 Procedimentos de gestão de incidentes</h4>
<p>Todo incidente é identificado, classificado por gravidade (crítico, alto, médio, baixo), contido, erradicado e
recuperado, com registro formal, análise de causa raiz e documentação de lições aprendidas. Incidentes críticos são
comunicados à {{CORREGEDORIA}} em até <strong>72 (setenta e duas) horas</strong>, constituindo meta de diligência
reforçada a comunicação em até 24 (vinte e quatro) horas da ciência. Incidentes com risco ou dano relevante aos
titulares são comunicados também à ANPD.</p>

<h4>4.7 Gestão de vulnerabilidades</h4>
<p>Vulnerabilidades críticas são tratadas em prazo máximo de <strong>30 (trinta) dias</strong> quando inexistente
evidência de exploração ativa. Havendo exploração ativa, risco iminente ou comprometimento relevante, adotam-se
medidas imediatas de contenção e correção emergencial, preferencialmente em até 72 (setenta e duas) horas, com
registro formal das providências e das medidas mitigatórias aplicadas na janela de correção. Os prazos e critérios
técnicos constam exclusivamente do Anexo II do Provimento, <strong>vedada qualquer flexibilização por esta
Política</strong>.</p>

<h4>4.8 Proteção de dados pessoais (LGPD)</h4>
<ol>
<li>observância integral da Lei n. 13.709/2018;</li>
<li>o responsável pela serventia é caracterizado como <strong>controlador</strong> de dados;</li>
<li>é mantido o registro das operações de tratamento (ROPA);</li>
<li>há procedimento para atendimento aos direitos dos titulares;</li>
<li>incidentes são comunicados à ANPD nos termos e prazos da legislação;</li>
<li>o encarregado (DPO) é designado quando exigido.</li>
</ol>

<h4>4.9 Criptografia</h4>
<p>Dados em trânsito protegidos por TLS 1.2 ou superior. Dados críticos em repouso cifrados com AES-256 ou padrão
equivalente/superior. Backups cifrados, inclusive na origem quando armazenados em infraestrutura de terceiros.
Gestão segura de chaves com inventário, segregação de custódia, controle de acesso restrito, política documentada
de rotação e renovação, registro das operações de geração, renovação e revogação, e revisão periódica dos padrões
criptográficos adotados, com substituição tempestiva de algoritmos ou protocolos vulneráveis. Renovação tempestiva
dos certificados digitais.</p>

<h4>4.10 Gestão de fornecedores</h4>
<p>Avaliação prévia de segurança; cláusulas contratuais de confidencialidade, reversibilidade, portabilidade
integral do acervo em formato interoperável e não proprietário, disponibilização de documentação técnica de
migração, cooperação em caso de transição, gestão de incidentes e conformidade integral com a LGPD; definição clara
de responsabilidades em caso de incidente; e monitoramento contínuo do cumprimento contratual.</p>

<h3>5. Revisão e auditoria</h3>
<ol>
<li>revisão periódica documentada, no mínimo anual;</li>
<li>manutenção de registros auditáveis por 5 (cinco) anos;</li>
<li>submissão à fiscalização correicional;</li>
<li>atualização obrigatória sempre que houver alteração legislativa ou regulatória relevante, especialmente em
matéria de proteção de dados pessoais.</li>
</ol>
' . $RODAPE],

'ropa' => [
 'titulo' => 'Registro das Operações de Tratamento de Dados (ROPA)',
 'base'   => 'Art. 7º, §1º; Anexo IV, item 1.4; Anexo III, 4.8, III',
 'etapa'  => 1,
 'resumo' => 'Estrutura mínima do registro das operações de tratamento, a ser preenchida por atividade.',
 'html'   => $CABEC . '
<h2 style="text-align:center">REGISTRO DAS OPERAÇÕES DE TRATAMENTO DE DADOS PESSOAIS</h2>
<p>Controlador: <strong>{{TITULAR}}</strong> ({{TITULAR_QUALIF}}) — {{SERVENTIA}}, CNS {{CNS}}.<br>
Encarregado: {{DPO}} — {{DPO_CONTATO}}.<br>Data-base: {{DATA_EXTENSO}}.</p>

<p>Para cada atividade de tratamento, preencha um quadro:</p>
<table border="1" cellpadding="5">
<tr><td width="30%"><strong>Atividade de tratamento</strong></td><td>Ex.: lavratura de escritura pública / registro de imóveis / atendimento ao público</td></tr>
<tr><td><strong>Finalidade</strong></td><td>Prestação do serviço notarial ou de registro, publicidade, autenticidade, segurança e eficácia dos atos jurídicos</td></tr>
<tr><td><strong>Base legal (LGPD)</strong></td><td>Art. 7º, II (cumprimento de obrigação legal) e/ou art. 7º, III (execução de políticas públicas); dados sensíveis: art. 11, II, "a"</td></tr>
<tr><td><strong>Categorias de titulares</strong></td><td>Partes, outorgantes, outorgados, testemunhas, procuradores, terceiros interessados</td></tr>
<tr><td><strong>Categorias de dados</strong></td><td>Identificação civil, CPF/CNPJ, filiação, estado civil, endereço, dados patrimoniais, biometria/assinatura</td></tr>
<tr><td><strong>Dados sensíveis</strong></td><td>( ) Não  ( ) Sim — especificar</td></tr>
<tr><td><strong>Compartilhamento</strong></td><td>Centrais eletrônicas, ONR, CRC, Poder Judiciário, Receita Federal, órgãos requisitantes</td></tr>
<tr><td><strong>Transferência internacional</strong></td><td>( ) Não  ( ) Sim — indicar país e salvaguarda</td></tr>
<tr><td><strong>Prazo de retenção</strong></td><td>Perpétuo, por força da natureza registral; trilhas de auditoria: mínimo 5 anos</td></tr>
<tr><td><strong>Medidas de segurança</strong></td><td>Autenticação individualizada e MFA; criptografia em trânsito (TLS 1.2+) e em repouso (AES-256); backup cifrado com RPO de {{RPO}}; trilhas de auditoria nível {{TRILHA}}; controle de acesso por perfil</td></tr>
<tr><td><strong>Operadores / suboperadores</strong></td><td>Fornecedores de sistema, hospedagem e backup — vide inventário de ativos</td></tr>
<tr><td><strong>Responsável pela atividade</strong></td><td>&nbsp;</td></tr>
</table>
' . $RODAPE],

'inventario' => [
 'titulo' => 'Inventário de Ativos Tecnológicos',
 'base'   => 'Art. 6º, III; Anexo IV, item 1.7',
 'etapa'  => 1,
 'resumo' => 'Gera o inventário a partir dos dados cadastrados na aba Inventário.',
 'html'   => $CABEC . '
<h2 style="text-align:center">INVENTÁRIO DE ATIVOS TECNOLÓGICOS</h2>
<p>{{SERVENTIA}} — CNS {{CNS}} — Classe {{CLASSE}}, Subclasse {{SUBCLASSE}}.<br>
Emitido em {{DATA_EXTENSO}}.</p>
<p>Documento produzido em cumprimento ao item 1.7 do Anexo IV do Provimento CN-CNJ n. 213/2026, abrangendo ativos
tecnológicos, integrações, bancos de dados, certificados digitais, softwares, histórico de atualizações e contratos.</p>
{{TABELA_ATIVOS}}
<p><small>Observação: nos termos do art. 4º, §3º, não é admitida, para fins de conformidade, a utilização de
componentes cujo ciclo de suporte oficial pelo fabricante tenha sido encerrado (EOL). Os itens assinalados como
"NÃO (EOL)" devem ser substituídos ou ter sua substituição formalmente planejada.</small></p>
' . $RODAPE],

'contingencia_energia' => [
 'titulo' => 'Plano de Contingência Energética',
 'base'   => 'Anexo I, 1, IV; Anexo IV, item 2.2',
 'etapa'  => 2,
 'resumo' => 'Fonte estável, aterramento aferido, SAI/UPS e procedimento de desligamento ordenado.',
 'html'   => $CABEC . '
<h2 style="text-align:center">PLANO DE CONTINGÊNCIA ENERGÉTICA</h2>
<p>Serventia de Classe {{CLASSE}}. Elaborado em {{DATA_EXTENSO}}.</p>

<h3>1. Infraestrutura elétrica</h3>
<p>1.1. Fonte de energia estável e confiável, com circuito dedicado aos ativos críticos de TI.<br>
1.2. Sistema de aterramento funcional e tecnicamente aferido, com <strong>laudo atualizado subscrito por
profissional habilitado e anotação de responsabilidade técnica (ART)</strong>, conforme art. 12, §8º.<br>
Laudo n.: __________  Profissional: __________  ART n.: __________  Validade: __________</p>

<h3>2. Sistema de Alimentação Ininterrupta (SAI/UPS)</h3>
<p>Equipamento: __________  Potência: ______ VA  Autonomia aferida sob carga: ______ minutos.<br>
A autonomia deve ser suficiente para o salvamento de dados em memória, o encerramento seguro das transações ativas
e o desligamento ordenado dos equipamentos (<em>safe shutdown</em>), recomendando-se, preferencialmente, autonomia
estendida de 30 (trinta) minutos.</p>

<h3>3. Procedimento em caso de falta de energia</h3>
<ol>
<li>T+0: acionamento automático do SAI/UPS; registro do evento.</li>
<li>T+2 min: suspensão de novas transações; conclusão das transações em curso.</li>
<li>T+5 min: verificação da previsão de restabelecimento junto à concessionária.</li>
<li>T+10 min: <em>safe shutdown</em> dos servidores e ativos críticos, na ordem: aplicações, SGBD, hipervisor, storage, rede.</li>
<li>Restabelecimento: religamento na ordem inversa; verificação de integridade do SGBD; conferência da última cópia
de segurança íntegra em face do RPO de {{RPO}}; registro em ata.</li>
</ol>

<h3>4. Fonte alternativa</h3>
<p>( ) Gerador  ( ) Segunda alimentação  ( ) Não aplicável à classe — justificativa: __________________________</p>

<h3>5. Responsáveis e acionamento</h3>
<p>Responsável técnico interno: {{RESP_TEC}}.<br>Suporte técnico contratado: __________  Telefone: __________ (SLA compatível com o RTO de {{RTO}}).</p>
' . $RODAPE],

'pcn_prd' => [
 'titulo' => 'Plano de Continuidade de Negócios (PCN) e Plano de Recuperação de Desastres (PRD)',
 'base'   => 'Art. 3º, §§1º e 2º; Anexo IV, item 2.5',
 'etapa'  => 2,
 'resumo' => 'Os quatro elementos cumulativos: riscos, mitigação, RTO/RPO expressos e medidas de 30 e 90 dias.',
 'html'   => $CABEC . '
<h2 style="text-align:center">PLANO DE CONTINUIDADE DE NEGÓCIOS (PCN) E PLANO DE RECUPERAÇÃO DE DESASTRES (PRD)</h2>
<p>{{SERVENTIA}} — Classe {{CLASSE}}, Subclasse {{SUBCLASSE}}. Versão 1.0, {{DATA_EXTENSO}}.</p>
<p><em>A ausência de qualquer dos quatro elementos abaixo veda expressamente a declaração de conclusão da Etapa 2
(Anexo IV, item 2.5, parte final).</em></p>

<h3>1. Identificação e avaliação estruturada de riscos</h3>
<table border="1" cellpadding="4">
<tr><th>Risco</th><th>Ativo afetado</th><th>Probabilidade</th><th>Impacto</th><th>Nível</th></tr>
<tr><td>Falha de hardware do servidor principal</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>Ransomware / criptografia maliciosa do acervo</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>Corrupção lógica do banco de dados</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>Indisponibilidade prolongada de link de internet</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>Falta de energia elétrica prolongada</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>Incêndio, inundação ou sinistro nas instalações</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>Descontinuidade ou insolvência de fornecedor crítico</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>Acesso indevido e vazamento de dados pessoais</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>

<h3>2. Medidas de mitigação</h3>
<p>Para cada risco acima, indique o controle correspondente, o responsável e a evidência.</p>

<h3>3. RTO e RPO</h3>
<p><strong>RTO máximo definido: {{RTO}}</strong> — tempo máximo admissível para restabelecimento das operações
essenciais após incidente que implique indisponibilidade relevante.<br>
<strong>RPO máximo definido: {{RPO}}</strong> — ponto máximo de perda de dados aceitável.<br>
Cópia completa de backup em intervalo {{BACKUP_FULL}}; cópias incrementais, replicação contínua ou
<em>point-in-time recovery</em> compatíveis com o RPO.<br>
Conectividade de referência da classe: {{LINK}}, devendo ser comprovada, por testes documentados, a conclusão do
backup incremental e da sincronização dentro do RPO.<br>
Teste documentado de restauração: periodicidade {{TESTE_REST}} (registros guardados por 5 anos).</p>

<h3>4. Medidas de curto prazo (até 30 dias) e de médio prazo (até 90 dias)</h3>
<p><strong>Curto prazo (até 30 dias):</strong> contenção do incidente; isolamento do ativo comprometido; acionamento
do suporte técnico; restauração a partir do ambiente de backup imutável; comunicação à {{CORREGEDORIA}} em até 72h
nos casos críticos; comunicação à ANPD quando houver risco relevante ao titular; registro formal da ocorrência.</p>
<p><strong>Médio prazo (até 90 dias):</strong> análise de causa raiz; correção definitiva; revisão dos controles
afetados; reteste de restauração; atualização da Política Interna de Segurança da Informação; registro das lições
aprendidas; comunicação de encerramento à Corregedoria.</p>

<h3>5. Acionamento e responsáveis</h3>
<p>Coordenação: {{TITULAR}} ({{TITULAR_QUALIF}}). Execução técnica: {{RESP_TEC}}.
Suporte contratado: __________. Contato do fornecedor de backup: __________.</p>

<h3>6. Validação</h3>
<p>Simulação anual de cenário de desastre para validação deste PCN/PRD (Anexo II, 6, II).
Último teste: ____/____/______. Próximo teste: ____/____/______.</p>
' . $RODAPE],

'arquitetura' => [
 'titulo' => 'Documento Técnico de Arquitetura Tecnológica',
 'base'   => 'Anexo IV, item 2.8',
 'etapa'  => 2,
 'resumo' => 'Os seis elementos obrigatórios: topologia, ambientes, fluxos, backups, integrações e redundância.',
 'html'   => $CABEC . '
<h2 style="text-align:center">DOCUMENTO TÉCNICO SIMPLIFICADO DA ARQUITETURA TECNOLÓGICA</h2>
<p>{{SERVENTIA}} — Classe {{CLASSE}}. Emitido em {{DATA_EXTENSO}}.</p>

<h3>I. Topologia básica de rede</h3>
<p>Descrever/anexar diagrama: link(s) de internet, roteador, firewall, switch, VLANs ou segmentação equivalente,
servidores, estações de trabalho e rede de atendimento ao público (segregada dos ambientes administrativos).</p>

<h3>II. Ambientes utilizados</h3>
<p>( ) Local  ( ) Nuvem  ( ) Híbrido  ( ) SaaS  ( ) Compartilhado / coletivo<br>
Caracterização da solução, na forma do art. 8º, §1º (prevalece a realidade técnica e contratual sobre a
nomenclatura das partes): ______________________</p>

<h3>III. Fluxos de dados críticos</h3>
<p>Descrever o caminho dos dados críticos (art. 2º, V): livros e atos eletrônicos, bases registrais, trilhas de
auditoria, backups, integrações sistêmicas e dados sensíveis — da captura à guarda.</p>

<h3>IV. Localização física ou lógica dos backups</h3>
<p>Ambiente primário: __________________________<br>
Ambiente secundário (tecnicamente independente): __________________________<br>
Mecanismo de imutabilidade (WORM / retention lock / versionamento bloqueado): __________________________<br>
Cifra na origem: ( ) Sim, AES-256, chave sob custódia exclusiva da serventia  ( ) Não</p>

<h3>V. Integrações externas relevantes</h3>
<p>Ex.: centrais eletrônicas, ONR, CRC, SERP, sistemas de fiscalização, gateways de assinatura.
Indicar protocolo, autenticação, canal seguro e registro auditável (art. 19).</p>

<h3>VI. Mecanismos de alta disponibilidade ou redundância</h3>
<p>Descrever a solução adotada e demonstrar a aderência ao RTO de {{RTO}} e ao RPO de {{RPO}}.
Para Classes 1 e 2 admite-se virtualização com restauração automatizada, <em>warm standby</em>, nuvem gerenciada
com redundância regional ou redundância local simples com reposição rápida, desde que documentadas as premissas e
os testes de restauração.</p>
' . $RODAPE],

'incidentes' => [
 'titulo' => 'Procedimento de Gestão de Incidentes de Segurança',
 'base'   => 'Art. 11; Anexo II, 4; Anexo IV, itens 1.5, 1.6 e 4.8',
 'etapa'  => 1,
 'resumo' => 'Identificação, classificação, contenção, erradicação, recuperação, causa raiz e comunicação em 72h.',
 'html'   => $CABEC . '
<h2 style="text-align:center">PROCEDIMENTO DE GESTÃO DE INCIDENTES DE SEGURANÇA DA INFORMAÇÃO</h2>

<h3>1. Classificação por gravidade</h3>
<table border="1" cellpadding="4">
<tr><th>Nível</th><th>Critério</th><th>Comunicação</th></tr>
<tr><td>Crítico</td><td>Compromete ou pode comprometer de forma relevante a disponibilidade, integridade,
autenticidade, confidencialidade ou rastreabilidade do acervo, dos sistemas ou da continuidade do serviço</td>
<td>{{CORREGEDORIA}} em até <strong>72 horas</strong> (meta reforçada: 24 horas da ciência). ANPD quando houver
risco ou dano relevante ao titular</td></tr>
<tr><td>Alto</td><td>Impacto significativo, sem comprometimento sistêmico</td><td>Registro interno e reporte ao delegatário</td></tr>
<tr><td>Médio</td><td>Impacto limitado e contornável</td><td>Registro interno</td></tr>
<tr><td>Baixo</td><td>Sem impacto operacional relevante</td><td>Registro interno</td></tr>
</table>

<h3>2. Fluxo</h3>
<ol>
<li><strong>Identificação</strong> — detecção por alerta automático, usuário ou fornecedor; abertura de registro com data e hora.</li>
<li><strong>Classificação</strong> — atribuição de gravidade pelo Responsável Técnico Interno.</li>
<li><strong>Contenção</strong> — isolamento do ativo; bloqueio de credenciais; preservação de evidências e trilhas.</li>
<li><strong>Erradicação</strong> — remoção da causa; aplicação de correções.</li>
<li><strong>Recuperação</strong> — restauração a partir do ambiente de backup íntegro, respeitados o RTO de {{RTO}} e o RPO de {{RPO}}.</li>
<li><strong>Comunicação</strong> — nos casos críticos, à {{CORREGEDORIA}} em até 72 horas; à ANPD quando cabível.</li>
<li><strong>Análise de causa raiz</strong> — obrigatória para <strong>todos</strong> os incidentes, sem exceção.</li>
<li><strong>Lições aprendidas</strong> — registro formal e revisão dos controles afetados.</li>
</ol>

<h3>3. Registro mínimo do incidente</h3>
<p>Data/hora da ocorrência e da ciência; descrição; ativos afetados; gravidade; medidas de contenção, erradicação e
recuperação; causa raiz; lições aprendidas; data e forma da comunicação à Corregedoria e à ANPD; data de encerramento.</p>

<h3>4. Gestão de vulnerabilidades associada</h3>
<p>Vulnerabilidades críticas: correção em até 30 dias, salvo exploração ativa, risco iminente ou comprometimento
relevante, caso em que a contenção e a correção emergencial ocorrem preferencialmente em até 72 horas, com registro
das medidas mitigatórias aplicadas na janela de correção. Registro formal com data de identificação, classificação
de risco, providências e data de encerramento (art. 11, §4º).</p>
' . $RODAPE],

'ata_restauracao' => [
 'titulo' => 'Ata de Registro do Teste de Restauração',
 'base'   => 'Anexo V (modelo oficial); Anexo IV, item 4.5',
 'etapa'  => 4,
 'resumo' => 'Modelo do Anexo V, com aferição de RTO/RPO, validação amostral e evidências mínimas.',
 'html'   => $CABEC . '
<h2 style="text-align:center">ATA PARA REGISTRO DO TESTE DE RESTAURAÇÃO</h2>

<p><strong>1.</strong> Aos ______ dias do mês de __________ de ______, às ______ horas, na {{SERVENTIA}}
(CNS {{CNS}}), situada à {{ENDERECO}}, sob responsabilidade do(a) {{TITULAR_QUALIF}} {{TITULAR}}, reuniu-se a
equipe designada para a realização do teste documentado de restauração de backup, em cumprimento às disposições do
Provimento CN-CNJ n. 213/2026 relativas à validação periódica de restauração e continuidade operacional.</p>

<p><strong>2. Participaram do teste:</strong><br>
I) {{RESP_TEC}} (responsável técnico interno);<br>
II) __________________________ (colaborador/preposto);<br>
III) __________________________ (fornecedor/empresa), se aplicável.</p>
<p><strong>2.1.</strong> O responsável pela serventia declara ter acompanhado, supervisionado ou validado o
procedimento, assumindo responsabilidade pessoal e funcional pela veracidade das informações registradas e pela
autenticidade das evidências técnicas anexadas.</p>

<p><strong>3. Escopo do teste:</strong> restauração do(s) sistema(s) __________ e do(s) banco(s) de dados __________,
incluindo verificação de integridade de: I) base de dados; II) repositório de documentos/atos eletrônicos;
III) trilhas de auditoria, quando aplicável.</p>

<p><strong>4. Validação de parâmetros operacionais:</strong><br>
I) Horário de início da restauração: __________<br>
II) Horário de conclusão: __________<br>
III) Tempo total efetivo de recuperação (RTO aferido): __________<br>
IV) RTO definido no PCN/PRD: <strong>{{RTO}}</strong><br>
V) Ponto temporal do último dado íntegro restaurado: __________<br>
VI) Perda temporal efetiva de dados (RPO aferido): __________<br>
VII) RPO definido para a classe: <strong>{{RPO}}</strong></p>
<p>Declara-se que os parâmetros encontram-se: ( ) Em conformidade  ( ) Em desconformidade.<br>
Em caso de desconformidade, indicar justificativa técnica e providências adotadas.</p>

<p><strong>5. Arquitetura de backup vigente:</strong><br>
I) Solução (nome e versão): __________  II) Identificador do backup restaurado: __________<br>
III) Tipo (completo/incremental): __________  IV) Armazenamento primário e secundário: __________<br>
V) Método de criptografia: __________  VI) Verificação de integridade: __________<br>
VII) Classe da serventia: <strong>{{CLASSE}}</strong></p>

<p><strong>6. Procedimento resumido adotado:</strong><br>
I) Seleção do ponto de restauração referente a ____/____/______ às ______ horas;<br>
II) Execução da restauração do banco de dados;<br>
III) Restauração do repositório documental;<br>
IV) Validação de serviços e acessos;<br>
V) Checagem de integridade por meio de __________;<br>
VI) Validação amostral de atos eletrônicos (amostra de ______ itens), com conferência de leitura, consistência e
rastreabilidade.</p>

<p><strong>7. Resultados consolidados:</strong><br>
I) Aderência ao RTO: ( ) Atendido ( ) Não atendido<br>
II) Aderência ao RPO: ( ) Atendido ( ) Não atendido<br>
III) Método de verificação de integridade e resultado: __________<br>
IV) Inconsistências detectadas: __________</p>

<p><strong>8. Medidas corretivas ou preventivas deliberadas:</strong><br>
Medida 01 — Descrição: __________ | Responsável: __________ | Prazo: ____/____/______<br>
Medida 02 — Descrição: __________ | Responsável: __________ | Prazo: ____/____/______</p>

<p><strong>9. Evidências técnicas mínimas anexadas:</strong><br>
I) Log ou relatório automatizado do processo de restauração;<br>
II) Identificador único do backup restaurado;<br>
III) Comprovante de verificação de integridade (hash ou equivalente);<br>
IV) Evidência da validação amostral;<br>
V) Identificação do ambiente em que o teste foi executado.</p>

<p><strong>10.</strong> Encerrado o teste às ______ horas, lavrou-se o presente registro, ao qual se vinculam as
evidências identificadas no item 9, numeradas sequencialmente e arquivadas em repositório com controle de acesso e
registro auditável, assegurada sua guarda íntegra, imutável e auditável pelo prazo mínimo de 5 (cinco) anos.</p>

<p><strong>11.</strong> O responsável pela serventia declara, sob responsabilidade pessoal e funcional, que o teste
foi realizado conforme os parâmetros oficiais vigentes e que as informações registradas refletem fielmente os
resultados obtidos.<br>
( ) Conformidade integral  ( ) Conformidade parcial (com plano corretivo anexo)  ( ) Não conformidade (com medidas
emergenciais adotadas)</p>

<br><p style="text-align:center">{{MUNICIPIO_UF}}, ____ de __________ de ______.</p>
<br><p style="text-align:center">_________________________________________<br><strong>{{TITULAR}}</strong><br>{{TITULAR_QUALIF}}</p>
<p style="text-align:center">_________________________________________<br>{{RESP_TEC}}<br>Responsável técnico interno</p>'],

'reversibilidade' => [
 'titulo' => 'Plano de Reversibilidade e Portabilidade de Dados',
 'base'   => 'Art. 6º, III; art. 15; Anexo IV, itens 5.6 e 5.6.1',
 'etapa'  => 5,
 'resumo' => 'Plano formal e registro da simulação de extração integral do acervo.',
 'html'   => $CABEC . '
<h2 style="text-align:center">PLANO DE REVERSIBILIDADE E PORTABILIDADE DE DADOS</h2>
<p>{{SERVENTIA}} — Classe {{CLASSE}}. Emitido em {{DATA_EXTENSO}}.</p>

<h3>1. Finalidade</h3>
<p>Assegurar a restituição integral e utilizável dos dados, configurações e registros da serventia ao seu titular em
caso de encerramento contratual, substituição de fornecedor ou transição de gestão, bem como a transferência
organizada do acervo aos eventuais sucessores (art. 6º, III).</p>

<h3>2. Escopo do acervo</h3>
<p>Bancos de dados; softwares; manuais; políticas internas; controle de acessos; inventário de ativos tecnológicos;
histórico de atualizações; livros e atos eletrônicos; trilhas de auditoria; backups; integrações.</p>

<h3>3. Formato de saída</h3>
<p>Estruturado, interoperável e não proprietário (ex.: PDF/A para documentos; XML, CSV ou SQL para bases), com
preservação de integridade, rastreabilidade e autenticidade.</p>

<h3>4. Mitigação de dependência estrutural (art. 15)</h3>
<p>Requisitos cumulativos:<br>
I — cláusula contratual expressa de reversibilidade integral e portabilidade em formato interoperável, estruturado e
não proprietário: ( ) Sim  ( ) Não — contrato n.: __________<br>
II — comprovação documental de teste de extração integral do acervo: ( ) Sim  ( ) Não — data: ____/____/______<br>
III — inexistência de restrição técnica ou contratual que impeça a migração sem anuência discricionária do
fornecedor: ( ) Sim  ( ) Não</p>

<h3>5. Simulação documentada de extração integral</h3>
<p>Periodicidade obrigatória para esta classe: <strong>{{EXTRACAO}}</strong>, e imediatamente sempre que houver
alteração relevante de fornecedor, arquitetura tecnológica ou modelo de governança.</p>
<p>A simulação pode ser realizada em ambiente de contingência, réplica, laboratório isolado ou cópia técnica
representativa; por exportação estrutural completa com validação de consistência e integridade, admitida verificação
por amostragem estatisticamente representativa; ou de forma escalonada por módulos, desde que demonstrada por
evidência técnica formal a viabilidade concreta de reconstrução integral do acervo.</p>

<table border="1" cellpadding="4">
<tr><th>Data</th><th>Ambiente</th><th>Volume extraído</th><th>Formato</th><th>Verificação de integridade</th><th>Resultado</th><th>Responsável</th></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>

<p><small>A declaração de conclusão da Etapa 5 não pode ser homologada sem evidência técnica de viabilidade concreta
de transferência organizada ao sucessor (Anexo IV, item 5.6).</small></p>
' . $RODAPE],

'relatorio_simplificado' => [
 'titulo' => 'Relatório Simplificado de Implementação (Classe 1)',
 'base'   => 'Art. 4º, §6º; Anexo IV, Disposições gerais, VI e VII',
 'etapa'  => 1,
 'resumo' => 'Forma adequada e suficiente de comprovação para a Classe 1, dispensado o dossiê ampliado.',
 'html'   => $CABEC . '
<h2 style="text-align:center">RELATÓRIO SIMPLIFICADO DE IMPLEMENTAÇÃO</h2>
<p><strong>a) Identificação:</strong> {{SERVENTIA}}, CNS {{CNS}} — Classe {{CLASSE}}, Subclasse {{SUBCLASSE}} —
Etapa do Anexo IV a que se refere: ______</p>

<p><strong>b) Requisito normativo e solução técnica adotada:</strong></p>
<table border="1" cellpadding="4">
<tr><th width="12%">Item</th><th width="38%">Requisito normativo</th><th width="38%">Solução adotada</th><th width="12%">Data</th></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>

<p><strong>c) Demonstração da equivalência funcional:</strong> descrever sucintamente por que a solução implementada
atende ao requisito funcional estabelecido, nos termos do art. 4º, §5º.</p>

<p><strong>d) Evidências disponíveis para fiscalização:</strong> contratos de serviços de TI, notas fiscais
correspondentes, prints, logs de configuração e relatórios automatizados do fornecedor ou do sistema, mantidos sob
guarda local.</p>

<p><strong>e) Declaração formal de responsabilidade:</strong></p>
<p>Eu, {{TITULAR}}, {{TITULAR_QUALIF}} da {{SERVENTIA}}, <strong>DECLARO</strong>, sob as penas da lei, a veracidade
das informações prestadas neste relatório e a manutenção das evidências pelo prazo mínimo de 5 (cinco) anos, nos
termos do Anexo IV, Disposições gerais, VII, "e", do Provimento CN-CNJ n. 213/2026, ciente de que a declaração falsa,
objetivamente verificada em inspeções ou correições, sujeita o responsável às penalidades previstas em lei
(art. 17, §2º).</p>
' . $RODAPE],

'declaracao_etapa' => [
 'titulo' => 'Declaração de Conclusão de Etapa (Justiça Aberta)',
 'base'   => 'Anexo IV, itens 1.9, 2.9, 3.9, 4.9 e 5.7; art. 17',
 'etapa'  => 0,
 'resumo' => 'Modelo da declaração firmada pelo titular/interino/interventor para registro no Justiça Aberta.',
 'html'   => $CABEC . '
<h2 style="text-align:center">DECLARAÇÃO DE CONCLUSÃO DE ETAPA</h2>
<p>Etapa concluída: <strong>Etapa _____</strong> do Anexo IV do Provimento CN-CNJ n. 213/2026.</p>

<p>Eu, <strong>{{TITULAR}}</strong>, {{TITULAR_QUALIF}} da {{SERVENTIA}}, inscrita no CNS {{CNS}}, enquadrada na
<strong>Classe {{CLASSE}}, Subclasse {{SUBCLASSE}}</strong>, <strong>DECLARO</strong>, sob as penas da lei e para
registro em campo próprio do Sistema Justiça Aberta (art. 17):</p>

<ol>
<li>que foram <strong>integralmente cumpridos todos os requisitos</strong> da etapa acima indicada, item a item, não
havendo declaração parcial, proporcional ou condicionada (Anexo IV, Disposições gerais, II);</li>
<li>que os requisitos das etapas precedentes permanecem <strong>efetivamente mantidos</strong>, observada a ordem
sequencial e cumulativa (Anexo IV, Disposições gerais, I);</li>
<li>que a comprovação está formalizada em <strong>dossiê técnico específico</strong> da etapa, contendo atas,
relatórios, registros de configuração, contratos revisados, registros de capacitação e evidências técnicas
(Disposições gerais, III);</li>
<li>que as evidências permanecerão arquivadas pelo prazo mínimo de <strong>5 (cinco) anos</strong>;</li>
<li>que estou ciente de que a declaração falsa, objetivamente verificada em inspeções ou correições, sujeita o
responsável às penalidades previstas em lei (art. 17, §2º).</li>
</ol>

<p><strong>Integridade das evidências</strong> (Classes 2 e 3 — Disposições gerais, IV e VIII):<br>
( ) Lista de <em>hashes</em> dos arquivos do dossiê, assinada digitalmente pelo responsável<br>
( ) Guarda em repositório com controle de acesso e registro auditável de alterações, ou em armazenamento com
imutabilidade/<em>retention lock</em><br>
( ) Documento eletrônico único assinado digitalmente, acompanhado de relatório de hash<br>
Hash consolidado do dossiê: ________________________________________</p>

<p><strong>Classe 1</strong> (Disposições gerais, V e VI): admite-se mecanismo simplificado de comprovação,
mediante guarda dos contratos de serviços de TI, das notas fiscais correspondentes e do relatório simplificado de
implementação assinado pelo responsável, dispensado o dossiê técnico ampliado.</p>

<p>Data do registro no Sistema Justiça Aberta: ____/____/______  Protocolo: __________________</p>
' . $RODAPE],
    ];
}
