# Fase 0 — Discovery

**Objetivo:** Compreender a estrutura atual do projeto e criar a matriz de compatibilidade.

## 1. Princípio Fundamental
Antes de modificar qualquer código:
1. Leia todo o repositório e entenda sua arquitetura.
2. Descubra automaticamente: engine, framework, linguagem, plataforma, estrutura Android, autenticação, banco, backend, sistema atual de usuários, save, progressão, etc.
3. Identifique o que já existe, o que pode ser reaproveitado, conflitos e débito técnico.
4. Somente depois comece a implementação.

**NÃO crie sistemas paralelos desnecessariamente.** Adapte o que existir para a integração com Google Play Games.
A arquitetura desejada é: Gameplay -> Game Domain Events -> Gamification Engine -> Integration Layer.

## 2. Verificação da Documentação
Antes de implementar:
- Consulte a documentação oficial atual do Play Games Services, Sidekick, Level Up, Game Stats, Achievements, Cloud Save, Recall, etc.
- **Não assuma que requisitos antigos continuam válidos.**

Registre em: `/docs/google-play/current-requirements.md` (requisito, fonte, impacto, situação atual, implementação necessária, status).

## 3. Entregáveis da Fase 0
- `/docs/google-play/compatibility-audit.md`: O que existia antes, problemas encontrados, estado atual e matriz de compatibilidade inicial.
- Preparar a estrutura documental em `/docs/google-play/` com base nos requisitos mapeados.
