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

Cada sessão pode fazer até 100 requisições à aplicação em 60 segundos,
incluindo páginas e assets locais. A requisição seguinte recebe HTTP 429,
invalida a autenticação e bloqueia a sessão por 60 segundos. Esta proteção
não substitui firewall, WAF ou CDN contra ataques DDoS volumétricos.
