# SGECE
Sistema de emissao de certificados 

## Criar a primeira conta administrativa

Se a base de dados ainda não tiver uma conta `Admin` ou `Root`, execute no
PowerShell, a partir da pasta do projecto:

```powershell
.\bin\create-admin.ps1
```

O comando pede o nome e o e-mail e solicita a palavra-passe sem a mostrar no
terminal. Depois, inicie sessão em `/admin`. O comando só cria a conta inicial;
se já existir uma conta administrativa activa, não cria outra.

A área administrativa aceita contas activas com nível `Root` ou `Admin`.
Termine a sessão pelo botão de saída no painel; o pedido de logout é protegido
por token CSRF.

## Gestão de escolas

Após iniciar sessão em `/admin`, abra **Escolas** na navegação administrativa
para pesquisar, filtrar, registar e editar escolas. Uma escola pode ser
activada ou desactivada sem apagar os registos académicos associados. Os
formulários usam token CSRF e consultas parametrizadas.
O código é preenchido em tempo real com as iniciais do nome, a sigla do nível
(`PRI`/`SEC`) e um sufixo hexadecimal aleatório de 64 bits. O índice `UNIQUE`
da base de dados e as novas tentativas do backend impedem códigos repetidos.
O e-mail institucional usa o acrónimo da escola e o distrito, por exemplo
`ebdm-alto-molocue@sgece.gov.mz`; em caso de colisão, recebe um sufixo numérico
para continuar único, sem usar o hexadecimal do código. Registo e edição usam
um modal, que fecha e limpa o formulário após guardar com sucesso. Os contactos
são obrigatórios e únicos. Os distritos disponíveis são os da Zambézia. O NUIT
foi removido. Em bases existentes, aplicar uma vez
`database/migrations/remove_nuit_from_escolas.sql` e
`database/migrations/increase_school_code_length.sql`. Antes de executar
`database/migrations/schools_contact_constraints.sql`, corrija registos
existentes sem contacto ou com valores repetidos.

## Gestão de alunos

Após iniciar sessão, abra **Alunos** para pesquisar, registar, editar e
activar/desactivar alunos. O código central é gerado automaticamente. O BI é
cifrado com uma chave derivada de `APP_KEY` e o respectivo HMAC é usado para
impedir registos duplicados; o BI não aparece na lista e, ao editar, pode
ficar em branco para manter o valor actual. No registo, seleccione primeiro o
distrito e depois a escola; a associação é criada automaticamente. A escola
associada não pode ser alterada na edição; transferências devem preservar o
histórico e ser tratadas pelo processo próprio. Mantenha `APP_KEY` confidencial
e estável.

## Gestão de matrículas

Em **Matrículas**, seleccione o distrito, a escola e um aluno activo dessa
escola; a lista exclui alunos que já têm matrícula activa no ano lectivo
seleccionado. A classe disponível é filtrada pelo nível da escola e a turma
pela classe e pelo ano. A turma é atribuída automaticamente por escola, classe
e ano, seguindo A, B, C…; cada turma aceita no máximo 50 matrículas activas e
uma nova é criada quando as anteriores não têm vaga. Os identificadores de
classe e turma ficam gravados na matrícula. O número de processo é sequencial
por escola e ano, com controlo de concorrência e unicidade na base de dados.
Não é permitida mais de uma matrícula activa do mesmo aluno no mesmo ano. O
cancelamento mantém o registo no histórico e não reutiliza o número de processo.
Em bases de dados existentes, aplique uma vez
`database/migrations/add_classe_id_to_matriculas.sql`; a migração preenche a
classe dos registos existentes a partir da turma.

## Gestão académica

Em **Gestão académica**, o administrador pode registar e actualizar anos
lectivos, classes, disciplinas e as disciplinas associadas a cada classe. Só
pode existir um ano lectivo activo; ao activar outro, os restantes são
encerrados. O módulo também permite consultar turmas por distrito, escola, ano
lectivo, nível, classe e estado. As turmas continuam a ser criadas
automaticamente durante a matrícula, respeitando o limite de 50 alunos; não
são criadas manualmente nesta página.

O currículo inicial do Ensino Primário inclui as classes 1.ª–6.ª. Língua
Portuguesa, Língua Moçambicana, Matemática, Educação Física e Educação Visual
e Ofícios estão associadas às seis classes; Ciências Naturais e Ciências
Sociais, da 4.ª à 6.ª. Para bases existentes, aplique uma vez
`database/migrations/seed_primary_curriculum.sql`.

O currículo inicial do Ensino Secundário inclui as classes 7.ª–12.ª. Francês
e Química/Física começam na 8.ª classe; as disciplinas do 2.º ciclo ficam
associadas como união das duas áreas de estudo, sem distinção de
obrigatoriedade/opcionalidade. Para bases existentes, aplique uma vez
`database/migrations/seed_secondary_curriculum.sql`.

## Utilizadores

Em **Utilizadores**, o administrador pode pesquisar e filtrar contas por
distrito, escola, nível de acesso e estado, criar contas e actualizar os seus
dados. O e-mail é gerado automaticamente com a inicial do primeiro nome,
sobrenome, quatro dígitos e o domínio `@sgece.gov.mz`; ao alterar o nome, o
endereço é regenerado. Associar uma escola atribui automaticamente o nível
**Escola**; sem escola, o nível é **Admin**. Apenas um utilizador Root pode
atribuir o nível **Root**. A palavra-passe é armazenada com hash. Na criação
da conta, usa os dígitos do telefone; se o telefone mudar, a palavra-passe
também é actualizada. Como o número é previsível, não deve ser tratado como
credencial definitiva. Um administrador não pode gerir contas Root, alterar o
próprio nível de acesso ou desactivar a própria conta; a última conta Root
activa também é protegida.
Contas **Escola** iniciam sessão em `/escola/dashboard`, numa área limitada aos
dados da escola associada; não têm acesso às rotas administrativas.

## Funcionalidades da escola

No painel da escola, **Pautas existentes** permite consultar as notas lançadas
por classe, turma, disciplina e período (1.º, 2.º, 3.º trimestre ou anual).
Para introduzir ou actualizar notas, use **Lançar pauta** e seleccione classe,
turma e disciplina, por esta ordem.
Registe duas ACS e uma avaliação provincial, cada uma de 0 a 20, por aluno. A
média do trimestre só é calculada quando as três notas estão preenchidas e a
média anual só aparece quando os três trimestres têm média. As pautas existentes
podem ser descarregadas em PDF por disciplina, como pauta geral trimestral ou
como pauta anual. As pautas gerais organizam os alunos em tabelas separadas por
disciplina, com as três avaliações e a média trimestral; no fim, apresentam a
média final e o resultado de cada estudante. A pauta anual agrupa A1, A2, A3 e
média por trimestre em cada disciplina, com a média final anual e o resultado
num quadro de resumo.
A média mínima para aprovação é 10 valores; as pautas ficam provisórias enquanto
houver notas em falta.

Em **Pedidos de documentos**, a escola pode solicitar certificados ou
declarações para alunos associados à própria escola e acompanhar o estado e a
resposta administrativa. O protocolo permite imprimir o recibo do pedido; após
a emissão, o mesmo recibo inclui o código e o endereço de validação para o
aluno consultar a autenticidade em `/validar-documento`. O administrador
acompanha a fila em **Pedidos das escolas**, encaminha pedidos para análise e
regista emissão ou rejeição; a rejeição exige uma justificação e a emissão
exige o texto validado. Ao emitir, o SGECE gera e arquiva o PDF com o brasão da
República de Moçambique, atribui um código de validação e disponibiliza o
descarregamento à escola. O documento também pode ser consultado no portal
público com o código do aluno e a data de nascimento.

Em bases de dados existentes, aplique por ordem
`database/migrations/add_school_grades_and_document_requests.sql` e
`database/migrations/add_assessment_scores_and_issued_pdfs.sql`. A estrutura
completa em `database/migrations/database.sql` também inclui os novos campos.
As rotas da escola e os formulários usam isolamento pela escola associada,
consultas parametrizadas e protecção CSRF.

A dashboard continua a ser a página inicial. Os links administrativos abrem
o conteúdo correspondente na área central do mesmo layout; auditoria e
configurações continuam como páginas informativas.

Cada sessão pode fazer até 100 requisições à aplicação em 60 segundos,
incluindo páginas e assets locais. A requisição seguinte recebe HTTP 429,
invalida a autenticação e bloqueia a sessão por 60 segundos. Esta proteção
não substitui firewall, WAF ou CDN contra ataques DDoS volumétricos.
