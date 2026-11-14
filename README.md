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

A dashboard continua a ser a página inicial. Os links administrativos abrem
o conteúdo correspondente na área central do mesmo layout; os outros módulos
ainda não integrados continuam como páginas estáticas informativas.

Cada sessão pode fazer até 100 requisições à aplicação em 60 segundos,
incluindo páginas e assets locais. A requisição seguinte recebe HTTP 429,
invalida a autenticação e bloqueia a sessão por 60 segundos. Esta proteção
não substitui firewall, WAF ou CDN contra ataques DDoS volumétricos.
