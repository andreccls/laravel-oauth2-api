# ADR 0002 — Laravel Octane com RoadRunner

- **Status:** aceito
- **Data:** 2026-10-06

## Contexto

Performance é requisito. Em php-fpm cada requisição reinicia o framework (autoload, providers, container, rotas).
O Octane mantém a aplicação em memória e reusa *workers*. Servidores suportados: **Swoole**, **RoadRunner**
(e FrankenPHP, via Octane também).

## Decisão

**RoadRunner**, 4 *workers* (`OCTANE_WORKERS`), reciclagem a cada 1000 requisições (`OCTANE_MAX_REQUESTS`).

- É um binário Go pré-compilado, copiado para a imagem pelo `rr get-binary`: **não exige compilar extensão PECL**
  (Swoole precisa de `pecl install` e muda o modelo de concorrência com corrotinas).
- Funciona com a imagem oficial `php:8.4-fpm` + extensões comuns (`pcntl`, `sockets`), imagem final **não-root**.
- Swoole **não foi exercitado** neste projeto; a escolha não é uma afirmação de que RoadRunner seja mais rápido.

Cuidados de Octane aplicados: nenhum estado estático/singleton por requisição (o `Principal` vive nos atributos da
request); configuração do Passport e dos *rate limiters* feita em `boot()` do provider (executa 1 vez por worker);
caches de config/rotas/eventos gerados no `entrypoint` (o `.env` só é lido nesse momento).

## Consequências

- (+) Medido: ~1,4x mais requisições/s e latência p50 menor que php-fpm no mesmo endpoint protegido (números e
  metodologia no README; máquina local, indicativo).
- (+) Mesma imagem base e mesmo `php.ini` nas duas pontas do benchmark (comparação justa).
- (−) Vazamento de estado entre requisições vira bug novo (por isso `max-requests` e a regra "sem estado global").
- (−) Código que assume "uma requisição por processo" precisa de revisão; o ganho depende do quanto do tempo é
  *boot* de framework vs. I/O (neste endpoint, DB + criptografia dominam, por isso o ganho é moderado).
