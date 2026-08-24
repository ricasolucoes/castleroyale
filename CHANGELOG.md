# Release Notes

---

## [Unreleased](https://github.com/example/project-dominion/compare/v0.1.0...develop)

### ✨ Novidades

- [ ] **Phase 01 — Engineering Foundation** — Docker stack, CI, migrations contra
      PostgreSQL + PostGIS, seeds de desenvolvimento e Makefile

---

## [v0.1.0 (2026-08-24)](https://github.com/example/project-dominion/releases/tag/v0.1.0)

### ✨ Novidades

- [x] **Monorepo** — npm workspaces com `apps/mobile` e `packages/*`, ao lado da API PHP
- [x] **API Laravel 13** — modular monolith no namespace `Game\`, com Sanctum, Horizon
      (seis filas), Reverb, Octane e backoffice Filament
- [x] **Shared kernel** — `Clock` (tempo autoritativo do servidor), `ResourceAmount` e
      `ResourceBundle` (economia inteira com aritmética checada), catálogo `ErrorCode`
      e envelope único de resposta
- [x] **App mobile Expo SDK 57** — React Native 0.86, TypeScript strict, Metro
      ciente do monorepo, Reanimated, Skia, MMKV e SecureStore
- [x] **Plano GSD completo** — 55 fases (00–54) com objetivo, dependências, critérios
      de sucesso observáveis e decisões travadas por fase

### 🎨 Melhorias

- [x] **Health endpoint** — `GET /api/v1/health` reporta dependências e as três
      versões de conteúdo (data, combat, economy)
- [x] **Correlação de requisições** — `X-Request-Id` sanitizado, propagado no contexto
      de log e devolvido na resposta

### 🐛 Correções

- [x] **Boot da aplicação** — removida chamada `Date::use()` inválida que quebrava a
      inicialização
- [x] **Seeder de staff** — `env()` trocado por `config()`; `env()` retorna null com
      config cacheada
- [x] **Gate do Horizon** — referenciava coluna `is_staff` inexistente; migration criada

### 🔧 Técnico

**Qualidade:** Pest com 33 testes e 300 asserções, incluindo testes de arquitetura que
falham o build se a camada de domínio importar o framework ou chamar `now()`. PHPStan
nível 8 com strict rules, zero erros. Pint limpo.

**Decisões:** 17 ADRs registrados — modular monolith, namespace neutro, PostgreSQL +
PostGIS, fronteiras do Redis, autoridade do servidor, eventos de domínio, Reverb,
combate determinístico, economia inteira, rotação de tokens, sharding de mundos,
balanceamento data-driven, observabilidade, versionamento de conteúdo, ULIDs e
contrato OpenAPI.

**Limitações conhecidas:** o host não possui `pdo_pgsql` e o daemon do Docker estava
parado, então migrations contra PostgreSQL/PostGIS **não foram executadas** nesta
máquina — foram validadas apenas contra SQLite. A Phase 01 torna o Docker o ambiente
canônico e adiciona job de CI rodando migrations contra Postgres + PostGIS real.
