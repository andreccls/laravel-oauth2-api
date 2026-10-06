# ADR 0003 — Escopos como contrato de autorização

- **Status:** aceito
- **Data:** 2026-10-06

## Contexto

Autenticar (quem é) não basta: o token precisa carregar **o que** pode fazer. Precisamos de um mecanismo simples,
declarativo nas rotas e testável.

## Decisão

- Os escopos são declarados **num único lugar** (`App\Support\Scopes`) e registrados com `Passport::tokensCan()`.
- A rota declara o que exige: middleware `oauth:tasks:read` / `oauth:tasks:write` (`AuthenticateToken`, que estende o
  `ValidateToken` do Passport). Ele valida assinatura, expiração e revogação, **exige todos os escopos listados** e
  expõe um `Principal` (usuário ou cliente-máquina) para o controller.
- **`tasks:write` não implica `tasks:read`**: privilégio mínimo, sem hierarquia implícita (teste dedicado).
- Escopo ausente → **403** `application/problem+json` com `required_scopes`; token ausente/inválido/expirado/revogado → **401**
  com `WWW-Authenticate: Bearer`.
- Não usamos o middleware `auth:api` do Passport porque ele exige um *usuário*; tokens `client_credentials` não têm.
  Por isso o `Principal` distingue `user:{id}` de `client:{uuid}` e o dado de negócio é isolado por esse "dono".
- O `throttle:api` roda **depois** do `oauth` (prioridade de middleware ajustada em `bootstrap/app.php`), para limitar
  por cliente OAuth e não por IP.

## Consequências

- (+) Adicionar um recurso protegido = 1 constante + 1 linha de rota (ver `docs/ARCHITECTURE.md`).
- (−) O Passport aceita qualquer escopo registrado para qualquer cliente: **não há allow-list de escopos por cliente**
  (a migration padrão não tem a coluna). Em produção, restrinja por cliente.
- (−) Escopos de granularidade fina (por recurso/objeto) continuam sendo responsabilidade do domínio (aqui: filtro por dono).
