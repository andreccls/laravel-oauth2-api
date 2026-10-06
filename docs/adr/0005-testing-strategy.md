# ADR 0005 — Estratégia de testes: PHPUnit, SQLite em memória, 100 % de linhas

- **Status:** aceito
- **Data:** 2026-10-06

## Contexto

Autenticação é o tipo de código em que "funciona no caminho feliz" não vale nada: o valor está nos caminhos de erro
(401/403/429, token expirado/revogado, PKCE errado). Os testes precisam ser rápidos e rodar sem PHP no host.

## Decisão

- **PHPUnit 12** (e não Pest): é o runner nativo do Laravel 13, sem camada extra; testes são classes simples, legíveis
  para quem não conhece Pest. Troca por Pest seria cosmética.
- **Feature tests** (HTTP real contra o kernel): fluxo completo authorization code + PKCE (login, consentimento,
  código, troca, refresh com rotação), client credentials, revogação, 401/403/429, CRUD, ETag/304, isolamento por dono.
- **Unit tests**: `Principal`, `ProblemDetails`, regras dos Form Requests, middleware com token transitório.
- **SQLite `:memory:`** como padrão (suíte inteira em ~5 s, sem serviços). Como o schema usa recursos portáveis,
  `make test-mysql` roda **a mesma suíte** no MySQL 8.4 (rodado e verde); o CI executa os dois.
- **N+1 provado por teste**: contagem de queries da listagem idêntica para 2 e 25 itens, e
  `Model::preventLazyLoading()` ligado fora de produção (lazy loading lança exceção).
- **OpenAPI não pode ficar velho**: um teste regenera o documento (Scramble) e compara com `docs/openapi.json`.
- **Cobertura com PCOV**, gate no `make coverage` e no CI: **100 % de linhas** (186/186), meta mínima configurável
  (`COVERAGE_MIN`). Sem exclusões de cobertura no código.
- **PHPStan nível 8 (Larastan) + Pint** em `make lint`, incluindo os testes.

## Consequências

- (+) Regressão em autenticação/escopo derruba o CI; testes rodam em segundos.
- (−) SQLite ≠ MySQL em detalhes de índice/otimizador: por isso o índice de `tasks` foi conferido com `EXPLAIN` no MySQL
  real e a suíte também roda nele.
- (−) 100 % de **linhas** não é 100 % de **comportamento**; branches não são medidos (PCOV só mede linhas).
