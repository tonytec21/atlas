# Atlas — Módulo Conformidade Provimento CN-CNJ n. 213/2026

Build: `ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213`

## Instalação

1. Copie a pasta `prov213/` para dentro de `atlas/` (fica irmã de `os/`, `caixa/`, etc.).
2. Edite **apenas** `config.php`: credenciais do banco e, se necessário, os caminhos candidatos de
   `session_check.php` e do TCPDF.
3. Adicione o link no `menu.php` do Atlas:

```html
<a href="prov213/index.php"><i class="fa fa-shield"></i> Provimento 213</a>
```

4. Pronto. **O schema é criado automaticamente no primeiro acesso** ao módulo — não é
   preciso rodar `install.php` em cada cartório. Abra `configuracao.php` e preencha
   serventia, CNS, titular e a **arrecadação bruta semestral**.

`install.php` continua disponível para conferência ou para forçar a atualização do
schema após um deploy, mas é opcional. A cada mudança de estrutura, basta subir
`P213_SCHEMA_VERSION` em `p213_migrate.php`: no próximo acesso a migração roda sozinha.

## Estrutura

| Arquivo | Função |
|---|---|
| `config.php` | Credenciais, caminhos e data de vigência |
| `p213_lib.php` | Catálogo normativo (76 requisitos), motor de score, prazos, layout |
| `p213_docs.php` | Modelos dos 14 termos/documentos exigidos |
| `install.php` | Schema idempotente (6 tabelas) |
| `index.php` | Painel: gauge, etapas, prazos, próximas ações |
| `diagnostico.php` | Questionário interativo com autosave |
| `inventario.php` | Inventário de ativos (item 1.7) com alerta de EOL |
| `termos.php` | Geração dos termos em PDF |
| `relatorio.php` | Relatório de conformidade, plano de ação, dossiê de evidências e exportação CSV |
| `configuracao.php` | Identificação, responsáveis, enquadramento |
| `evidencias.php` | Cofre de evidências, hashes e dossiê |
| `p213_evid.php` | Catálogo de evidências esperadas + Gemini |
| `api.php` | Endpoints AJAX |

Tabelas: `p213_config`, `p213_respostas`, `p213_ativos`, `p213_incidentes`,
`p213_declaracoes`, `p213_auditoria`, `p213_evidencias`.

## Cofre de evidências

- Catálogo de **137 evidências esperadas**, mapeadas um a um para os 76 requisitos.
- Upload até 30 MB, extensões restritas, gravado em `evidencias/etapaN/` com nome aleatório.
  A pasta recebe um `.htaccess` que desliga o engine PHP e nega `.php/.phtml/.phar`.
- **SHA-256 calculado no servidor** a cada upload; download só via `evidencias.php?baixar=ID`
  (com validação de `realpath` contra path traversal).
- **Verificar hashes** recalcula todos os arquivos e aponta ausências e adulterações.
- **Termo de Encerramento do Dossiê** por etapa, em PDF, com a lista de hashes e o hash
  consolidado (SHA-256 da concatenação ordenada dos resumos) — o mecanismo idôneo de
  verificação de integridade do Anexo IV, Disposições gerais, IV e VIII.

## Assistente de IA (Gemini) — opcional

Chave e modelo em `configuracao.php` (modelos ativos: Gemini 3.5 Flash — padrão, 3.1 Pro e 3.1 Flash-Lite). Três recursos:

1. **Sugerir evidências** por requisito, com tipo e instrução prática de obtenção.
2. **Redigir a descrição** da evidência para o dossiê, em tom técnico-jurídico.
3. **Plano da etapa** a partir das pendências, ordenado por dependência técnica.

Só o texto do requisito e as anotações do oficial vão à API — **o conteúdo dos arquivos
anexados nunca é enviado**. Os prompts proíbem inventar datas, números e nomes; as lacunas
saem entre colchetes. Sem a chave configurada, os botões de IA simplesmente não aparecem.

## Motor de conformidade

- Cada requisito tem **peso 1 a 3** (médio / alto / crítico) e a lista de **classes aplicáveis**.
- Aderência = `Σ(peso × fator) / Σ(peso aplicável)`, com fator `conforme = 1`, `parcial = 0,5`,
  demais `= 0`.
- **Não aplicável** sai do denominador, mas exige justificativa técnica (art. 4º, §5º).
- Uma etapa só fica **apta a declarar** quando não há item não conforme, parcial ou não avaliado
  (Anexo IV, Disposições gerais, II — vedada declaração parcial, proporcional ou condicionada).
- Etapa N só é liberada se a etapa N−1 estiver apta (Disposições gerais, I — etapas sucessivas e
  cumulativas). O botão de declaração no painel respeita essa trava.

## Enquadramento e prazos (validados)

| Classe | Receita semestral | Etapas 1–2 (art. 20) | Prorrogação (art. 21) | Integral (art. 23) |
|---|---|---|---|---|
| 3 | acima de R$ 500.000 | 24/05/2026 (90 d) | 22/08/2026 | 23/02/2028 (24 m) |
| 2 | até R$ 500.000 | 23/07/2026 (150 d) | 21/10/2026 | 23/08/2028 (30 m) |
| 1 | até R$ 100.000 | 21/09/2026 (210 d) | 20/12/2026 | 23/02/2029 (36 m) |

Base: vigência em 23/02/2026 (DJe/CNJ n. 40/2026). Alterável em `P213_VIGENCIA` (`config.php`).

Parâmetros por classe (Anexos I e II): RPO 4 h / 12 h / 24 h · RTO 8 h / 24 h / 24 h ·
backup completo 24 h / 48 h / 72 h · link 50 / 10 / 2 Mbps · teste de restauração semestral (C3) ou
anual (C1 e C2) · trilha nível Intermediário (C3) ou Essencial (C1 e C2) · retenção de trilhas 5 anos ·
pentest só C3 · extração integral 24 / 30 / 36 meses.

## Pontos de atenção

- **Arrecadação bruta.** O art. 16 usa *arrecadação bruta semestral*. O valor consolidado no Justiça
  Aberta inclui fundos e repasses, o que pode elevar a classe. `configuracao.php` permite fixar a
  classe manualmente; registre a memória de cálculo no dossiê.
- **DPO.** A Classe 1 está dispensada da designação de encarregado (§4º do art. 88 do CNN, incluído
  pelo Provimento CN-CNJ n. 214/2026). O item 1.1.III só aparece para Classes 2 e 3.
- **Aterramento (item 2.1.b).** É o item mais esquecido: exige laudo atualizado com ART (art. 12, §8º).
- **TCPDF.** Se não for encontrado, os termos e relatórios abrem em versão imprimível
  (`Imprimir → Salvar como PDF`). Ajuste `$P213_TCPDF_CANDIDATES` em `config.php`.
- **CRLF.** Os arquivos foram gravados com quebras de linha do Windows.

## Escopo

O módulo é ferramenta de **autoavaliação, planejamento e produção documental**. Ele não substitui o
dossiê técnico, as evidências, o laudo de aterramento, o pentest nem o teste de restauração. A
responsabilidade pelo cumprimento é pessoal e indelegável do delegatário (arts. 13, §3º, e 14).

## Relatórios (`relatorio.php`)

- **Relatório de Conformidade** — enquadramento, parâmetros da classe, prazos dos arts. 20/21/23,
  situação por etapa e detalhamento item a item. Peça central do dossiê técnico.
- **Plano de Ação** — pendências ordenadas por etapa e criticidade, com a orientação de
  cumprimento e campos para responsável, prazo e data de conclusão.
- **Dossiê de Evidências** — relação das evidências registradas por requisito. Nas Classes 2 e 3
  deve ser acompanhado de lista de *hashes* assinada digitalmente (Anexo IV, Disposições gerais, IV).
- **Exportação CSV** — planilha com todos os requisitos aplicáveis, situação, evidência,
  responsável e data. Separador `;` e BOM UTF-8 (abre direto no Excel).

Sem TCPDF, tudo abre em versão imprimível (*Imprimir → Salvar como PDF*). Nada quebra.

## Validação desta entrega

- Todos os `.php` passaram por `php -l`.
- Os blocos JavaScript embutidos passaram por `node --check`.
- Enquadramento e prazos conferidos por execução direta das funções puras.
- Catálogo auditado: 76 requisitos, sem códigos duplicados, todos com base normativa e sugestão.
  Aplicáveis: 72 à Classe 1, 75 à Classe 2, 75 à Classe 3.
- Arquivos gravados com CRLF (padrão XAMPP/Windows).
