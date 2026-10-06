# ADR 0004 — Redis para cache, rate limiting e sessão

- **Status:** aceito
- **Data:** 2026-10-06

## Contexto

O limite de requisições precisa ser **compartilhado entre workers/containers** e barato por requisição. A sessão
(só usada no `POST /login` do fluxo authorization code) e o cache não devem ir ao MySQL.

## Decisão

- `CACHE_STORE=redis` e `SESSION_DRIVER=redis` (phpredis). Com Redis como cache, `throttleWithRedis()` troca o
  *limiter* genérico por um baseado em script Lua (1 ida ao Redis, atômico).
- Limite **por cliente OAuth** (`config('api.rate_limit')`, padrão 120/min), não por token: criar tokens novos não
  zera o orçamento (teste dedicado). `/login` é limitado por e-mail+IP (5/min); `/oauth/token` usa o `throttle` padrão do Passport.
- Resposta 429 em `problem+json` com `Retry-After`.
- Nos testes o cache é `array` (sem serviço externo); o caminho Redis/Lua foi verificado à mão na stack real
  (limite 5: cinco 200 e depois 429 com `Retry-After`) e é exercitado pelo `make demo`/benchmark, mas não por teste automatizado.

## Consequências

- (+) Limite consistente com N réplicas da API; sessão sem tabela no MySQL.
- (−) Redis vira dependência de disponibilidade (sem Redis, `/login` e o limiter falham). Sem persistência configurada
  (dev): reiniciar o Redis zera os contadores.
- (−) Não cacheamos a checagem de revogação do Passport (ver ADR 0001): seria o próximo passo de performance, trocando
  consistência imediata por TTL.
