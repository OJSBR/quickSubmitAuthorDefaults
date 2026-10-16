# Quick Submit Contributor Defaults — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.0.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS 3.5](https://github.com/OJSBR/quickSubmitAuthorDefaults/releases/download/1.0.0.0/quickSubmitAuthorDefaults-1.0.0.0.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **OJS** that opens the form of a new contributor of the
[Quick Submit](https://github.com/pkp/quickSubmit) plugin already filled in with the
**email address** and the **country** of the journal and with the **Author** role. The editor
types the given name and the family name and clicks **Save**. Every field stays editable.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| Application | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.0.0 |

## Why

Quick Submit is used to record articles that were already published elsewhere — the back
issues of a journal, mostly. Its contributor form requires an email address, a country and a
role for every author, and the editor seldom has the email address of each coauthor of an old
article: an address gets invented for every one of them, and the form has to be filled three
more times per author. With this plugin the form opens with the address of the journal (or
one the journal chooses), the country of the journal and the Author role already in place.

## What it does

- Fills the **email address** of a new contributor with the **principal contact email** of
  the journal, or with an address the journal chooses (for instance one kept for this
  purpose).
- Fills the **country** of a new contributor with the **country of the journal**.
- Selects the **role** of a new contributor: the **default Author role** of the journal, or a
  role the journal chooses among its contributor roles.
- Each of the three can be switched off on its own.
- Only a **new** contributor is touched. Opening an existing contributor shows what was
  stored, and a value the form already has is never overwritten.
- Nothing is saved by the plugin: the values are only what the form opens with, the editor
  can change any of them, and the form validates them as it always did.

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder
   into `plugins/generic/` so you get `plugins/generic/quickSubmitAuthorDefaults/`.
2. Enable **Quick Submit Contributor Defaults** under the *Generic* plugins list.

Quick Submit itself is a separate plugin (*Tools → Quick Submit*); install it from the
plugin gallery if the journal does not have it yet.

## Configuration

**Settings → Website → Plugins → Quick Submit Contributor Defaults → Settings.**

| Setting | Default | What it does |
| --- | --- | --- |
| Fill in the email address of a new contributor | **on** | The email field opens filled. |
| Address to use | *(empty)* | Empty: the principal contact email of the journal (**Settings → Journal → Contact**). Otherwise this address. |
| Fill in the country of a new contributor with the country of the journal | **on** | The country opens set to the country of the journal (**Settings → Journal → Masthead**). A journal without a country leaves the field empty. |
| Select the role of a new contributor | **on** | A contributor role opens selected. |
| Role | *Default Author role of the journal* | Or any of the contributor roles of the journal. A role that is later deleted falls back to the default. |

With the plugin enabled and nothing saved, all three fields are filled with the values of
the journal.

## How it works (technical)

In OJS 3.5 the contributors of a submission are edited in the Vue form of the workflow, which
this plugin leaves alone. Quick Submit still uses the **legacy contributor grid** of the core
(`grid.users.author.AuthorGridHandler` → `APP\controllers\grid\users\author\form\AuthorForm`),
which is the form this plugin fills.

One hook, and no core template or class is replaced:

- `authorform::initdata` — fired by `Form::initData()` with the short name of the class in
  lowercase. When the form has no contributor (a new one), the plugin sets `email`,
  `country` and `userGroupId` where the form has no value yet, and returns
  `Hook::CONTINUE`.

The role is resolved as: the role the journal chose, while it is still one of its contributor
roles; otherwise the first default contributor role (`is_default`), which is **Author** in a
journal created by OJS; otherwise the first contributor role.

## Tests

- **PHPUnit** (`tests/*Test.php`, on `PKP\tests\PKPTestCase`): the plugin classes against the
  installed PKP and its registry, every imported class, the values a new contributor starts
  with and each switch, the chosen address over the contact address, the resolution of the
  role, the real `AuthorForm` filled **through the core hook** — and, as counterproof, the same
  form opening empty with nothing listening to it — an existing contributor left alone, a value
  already in the form never overwritten, and the 38 translations. From the OJS root:

  ```bash
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/quickSubmitAuthorDefaults/tests"
  ```

- **Cypress** (`cypress/tests/functional/QuickSubmitAuthorDefaults.cy.js`, run by
  [pkp-github-actions](https://github.com/pkp/pkp-github-actions) on every push): enables the
  plugin and saves its settings form; opens the contributor form exactly as the
  *Add contributor* link of Quick Submit does and finds the contact email, the country and a
  contributor role of the journal in it; switches to a chosen address and role; refuses an
  address that is not one; switches everything off and finds the form empty; and finally
  saves a contributor typed with the name only, **reading it back from the REST API** with the
  values of the journal. The contributor grid is part of every OJS 3.5, so the spec runs in
  PKP's continuous integration, which does not ship Quick Submit. The settings of the journal
  are put back and the contributor is deleted.

- Both suites are run by `.github/actions/tests.sh`, so a failure in either one fails the job.
- Verified on OJS 3.5.0.3 with Quick Submit 1.0.8.0: 27 unit tests, and a contributor saved
  from the filled form and read back from the database.

Tests are kept in the repository and are not part of the release package.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**, the same license as OJS.

## AI use

Generative AI (Claude Opus 5.5, by Anthropic) was used to write and run tests, improve the
code and bring it in line with PKP standards. Every change is reviewed and tested by OJSBR,
which is responsible for the published releases.

## Contributing

Issues and pull requests are welcome.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin genérico para **OJS** que abre o formulário de um novo coautor da
[Submissão rápida](https://github.com/pkp/quickSubmit) (Quick Submit) já preenchido com o
**e-mail** e o **país** da revista e com o papel de **Autor**. O editor digita o nome e o
sobrenome e clica em **Salvar**. Todos os campos continuam editáveis.

**⬇️ Pacote de instalação:** [OJS 3.5](https://github.com/OJSBR/quickSubmitAuthorDefaults/releases/download/1.0.0.0/quickSubmitAuthorDefaults-1.0.0.0.tar.gz) — ou veja todas as [Releases](../../releases).

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com).** Veja os
> [Créditos e autoria](#créditos-e-autoria).

### Compatibilidade e branches

| Aplicação | Branch | Versão do plugin |
|-----------|--------|------------------|
| OJS 3.5.x | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.0.0.0 |

### Por quê

A Submissão rápida serve para cadastrar artigos já publicados — em geral o acervo antigo da
revista. O formulário de coautor dela exige e-mail, país e papel de cada autor, e o editor
quase nunca tem o e-mail de cada coautor de um artigo antigo: inventa-se um endereço para cada
um, e o formulário tem de ser preenchido mais três vezes por autor. Com este plugin o formulário
já abre com o e-mail da revista (ou outro que a revista escolher), o país da revista e o papel
de Autor.

### O que faz

- Preenche o **e-mail** do novo coautor com o **e-mail do contato principal** da revista, ou
  com um endereço escolhido pela revista (por exemplo, um reservado para isso).
- Preenche o **país** do novo coautor com o **país da revista**.
- Seleciona o **papel** do novo coautor: o **papel padrão de Autor** da revista, ou outro
  papel de coautor escolhido pela revista.
- Cada um dos três pode ser desligado separadamente.
- Só o coautor **novo** é tocado. Ao abrir um coautor já cadastrado aparece o que está
  gravado, e valor que o formulário já tem nunca é sobrescrito.
- O plugin não grava nada: os valores são só o ponto de partida do formulário, o editor pode
  mudar qualquer um, e o formulário valida tudo como sempre validou.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a pasta
em `plugins/generic/` (ficando `plugins/generic/quickSubmitAuthorDefaults/`). Depois ative
**Padrões de coautor da Submissão rápida** na lista de plugins *Genéricos*.

A Submissão rápida é outro plugin (*Ferramentas → Submissão rápida*); instale-a pela galeria
de plugins se a revista ainda não tiver.

### Configuração

**Configurações → Website → Plugins → Padrões de coautor da Submissão rápida → Configurações.**

| Opção | Padrão | O que faz |
| --- | --- | --- |
| Preencher o e-mail de um novo coautor | **ligada** | O campo de e-mail já abre preenchido. |
| Endereço a usar | *(vazio)* | Vazio: o e-mail do contato principal da revista (**Configurações → Revista → Contato**). Senão, este endereço. |
| Preencher o país de um novo coautor com o país da revista | **ligada** | O país abre com o país da revista (**Configurações → Revista → Expediente**). Revista sem país deixa o campo vazio. |
| Selecionar o papel de um novo coautor | **ligada** | Um papel de coautor já abre marcado. |
| Papel | *Papel padrão de Autor da revista* | Ou qualquer papel de coautor da revista. Papel apagado depois volta para o padrão. |

Com o plugin ativado e nada salvo, os três campos são preenchidos com os dados da revista.

### Como funciona (técnico)

No OJS 3.5 os coautores são editados no formulário Vue do fluxo de trabalho, que este plugin
não toca. A Submissão rápida ainda usa o **grid legado de coautores** do núcleo
(`grid.users.author.AuthorGridHandler` → `APP\controllers\grid\users\author\form\AuthorForm`),
e é esse formulário que o plugin preenche.

Um hook só, sem substituir template nem classe do núcleo:

- `authorform::initdata` — disparado pelo `Form::initData()` com o nome curto da classe em
  minúsculas. Quando o formulário não tem coautor (é um novo), o plugin põe `email`,
  `country` e `userGroupId` onde o formulário ainda não tem valor e devolve
  `Hook::CONTINUE`.

O papel é escolhido assim: o papel escolhido pela revista, enquanto ainda for um papel de
coautor dela; senão o primeiro papel de coautor padrão (`is_default`), que é **Autor** numa
revista criada pelo OJS; senão o primeiro papel de coautor.

### Testes

PHPUnit em `tests/` e Cypress em `cypress/tests/functional/` (rodado pelo
[pkp-github-actions](https://github.com/pkp/pkp-github-actions) a cada push), com os comandos
da seção em inglês. A suíte prova o `AuthorForm` real preenchido **pelo hook do núcleo** — e,
como contraprova, o mesmo formulário vazio sem o plugin — e, no navegador, grava um coautor
digitado só com o nome e **relê pela API REST** com os dados da revista. Verificado no OJS
3.5.0.3 com a Submissão rápida 1.0.8.0: 27 testes de unidade, e um coautor gravado a partir do
formulário preenchido e relido do banco.

Os testes ficam no repositório e não fazem parte do pacote da release.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin autoral.
- Distribuído sob a **GNU GPL v3**, a mesma licença do OJS.

### Uso de IA

Foi usada IA generativa (Claude Opus 5.5, da Anthropic) para escrever e rodar testes, melhorar
o código e alinhá-lo aos padrões da PKP. Toda mudança é revisada e testada pela OJSBR, que
responde pelas releases publicadas.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
