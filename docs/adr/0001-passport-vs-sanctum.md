# ADR 0001 — Laravel Passport (e não Sanctum) como servidor OAuth2

- **Status:** aceito
- **Data:** 2026-10-06

## Contexto

O objetivo do projeto é demonstrar um **servidor OAuth2 de verdade** (RFC 6749/7636): authorization code com PKCE,
client credentials e refresh token, com escopos. O Laravel oferece duas opções oficiais: **Sanctum** e **Passport**.

## Decisão

Usar **Laravel Passport 13** (sobre `league/oauth2-server`).

- Sanctum emite tokens de API opacos e cookies de SPA; **não é um servidor OAuth2** (sem `/oauth/token`, sem
  authorization code, sem client credentials, sem PKCE).
- Passport implementa os *grants* padronizados, emite **JWT RS256** (chaves em `storage/oauth-*.key`, geradas por
  `passport:keys`, nunca versionadas), persiste clientes/tokens/refresh tokens e traz `passport:purge`.
- **Password grant não é habilitado** (e o Passport 13 já o deixa desligado por padrão): o OAuth 2.1 o removeu porque
  ele entrega a senha do usuário ao cliente. O teste `test_password_grant_is_not_available` garante o comportamento.
- **Implicit** e **device code** também ficam de fora (YAGNI; o device code desabilitado evita até a tabela extra).

## Consequências

- (+) Fluxos e erros seguem o RFC (`invalid_grant`, `invalid_client`, `invalid_scope`...), sem código de protocolo nosso.
- (+) PKCE obrigatório para clientes públicos; refresh token com rotação (uso único).
- (−) Passport valida revogação **consultando o MySQL a cada requisição** (o JWT sozinho não basta). É o preço da
  revogação imediata; ver "Limitações" no README para a opção de cache.
- (−) O fluxo authorization code exige uma **sessão do dono do recurso** (`POST /login`) e uma tela de consentimento;
  como o projeto é só API, o "consentimento" é uma resposta JSON (`Passport::authorizationView` com closure).
