# Google Play Games & Sidekick Compatibility Audit

## 1. Auditoria Inicial: Estado Atual do Projeto

### 1.1. Stack e Arquitetura
- **Engine / Plataforma Mobile:** React Native (0.86.3) com Expo (~57.0.17) prebuild (existência da pasta `android/`).
- **Renderização de Mapa:** `@shopify/react-native-skia` (2.6.2) e `react-native-reanimated` (4.5.1).
- **Backend:** Laravel 13 (API), Filament 5 (Painel Admin), Sanctum (Auth), Postgres/PostGIS (produção) via Docker.
- **Estado Global:** Zustand e TanStack React Query.
- **Estrutura de Repositório:** Monorepo (`apps/api`, `apps/mobile`, `packages/contracts`, `packages/game-data`).
- **Autenticação:** Baseada em tokens com Laravel Sanctum. Suporte a Apple/Google identity tokens validado server-side na Fase 03.
- **Sistema de Usuários:** `Game\Identity\Domain\Player` (backend).
- **Moedas/Economia:** Valores inteiros rigorosos.
- **Offline / Save Atual:** App focado em server-authoritative. Ações críticas (como economia) passam pelo servidor.

### 1.2. Gamificação Existente
- **Módulos atuais:** Focado no core loop de estratégia e conquista. A base de dados suporta `Player`, e a API processa regras de jogo estritas e determinísticas. 
- **Ausências:** Não há módulos de `Achievements`, `Quests`, `Streaks`, `Leaderboards`, ou `Rewards` consolidados no front-end/back-end. Isso será criado nas fases subsequentes (Fases 1 a 7).
- **Integração Google Play Games:** Nenhuma biblioteca de Play Games Services instalada ainda (ausência de pacotes PGS nativos no `package.json` atual).

---

## 2. Problemas Encontrados e Riscos Mapeados

### 2.1. Arquitetura e Integração React Native
- **Falta de um Plugin Expo para PGS v2:** Precisamos integrar o SDK nativo do Google Play Games Services v2 no build do Expo (via custom config plugin ou pacote wrapper).
- **Sidekick Overlay:** O jogo usa `@shopify/react-native-skia` e `react-native-gesture-handler`. É imperativo garantir que o overlay do Sidekick e as interações imersivas (drag, pinch, tap) não conflitem com os eventos de toque do canvas (especialmente o hit testing implementado na Fase 06).
- **Formato de Distribuição:** O Expo constrói AABs por padrão para a Play Store, o que facilita a habilitação do Sidekick via Console. A auditoria confirma que **Android App Bundle** é perfeitamente suportado.

### 2.2. Autenticação e Sincronização
- Como a autenticação atual valida identity tokens server-side, a integração do Play Games v2 deve retornar o `serverAuthCode` do Google para o Laravel gerar a sessão do Sanctum transparente ao usuário.
- O jogo é autoritativo (server-side). Se o usuário estiver offline, a gamificação básica deve ser armazenada localmente (MMKV / Zustand) e enfileirada (`pending_game_events`) para envio assim que a rede retornar.

### 2.3. Segurança e Economia
- Toda a Gamificação e Game Stats devem ser verificadas via Backend. Play Games Leaderboards não devem receber "scores" brutos gerados pelo client de forma cega.

---

## 3. Matriz de Compatibilidade Inicial

| Recurso Google Play / Sidekick | Status Atual no Projeto | Risco | Ação Necessária |
|--------------------------------|-------------------------|-------|-----------------|
| **Play Games Services v2**     | Não implementado        | Médio | Adicionar módulo nativo/Expo para PGS v2. |
| **Sidekick Overlay**           | Desconhecido            | Alto  | Testar sobreposição com SKIA Canvas. |
| **Android App Bundle**         | Suportado (Expo)        | Baixo | Configuração nativa via EAS/Play Console. |
| **Achievements**               | Não implementado        | Baixo | Criar infra (backend + UI) e cadastrar no Console. |
| **Game Stats**                 | Não implementado        | Médio | Criar event bus e agregar no Backend. |
| **Quests & Streaks**           | Não implementado        | Médio | Infraestrutura server-driven de LiveOps (Fase 7). |
| **Cloud Save**                 | Server-authoritative    | Baixo | API já é a fonte de verdade; integrar Recall API. |
| **Recall API**                 | Não implementado        | Baixo | Relacionar `internal_id` com `play_games_player_id`. |
| **Leaderboards**               | Não implementado        | Médio | API segura de ranking via Backend (evitar spoofing). |

---

## 4. Próximos Passos
1. Consolidar a documentação de requisitos atuais em `/docs/google-play/current-requirements.md` (conforme exigido pela Fase 0).
2. Avançar para a **Fase 1 (Fundação)**, estruturando o módulo de Gamificação e o Event Bus sem ainda injetar as bibliotecas nativas, garantindo um código limpo.
