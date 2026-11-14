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

A dashboard continua a ser a página inicial. Os links administrativos abrem
o conteúdo correspondente na área central do mesmo layout; os módulos ainda
não integrados continuam como páginas estáticas informativas.

Cada sessão pode fazer até 100 requisições à aplicação em 60 segundos,
incluindo páginas e assets locais. A requisição seguinte recebe HTTP 429,
invalida a autenticação e bloqueia a sessão por 60 segundos. Esta proteção
não substitui firewall, WAF ou CDN contra ataques DDoS volumétricos.
