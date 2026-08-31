import os

phases_data = {
    "55-google-play-sidekick-fase-0-discovery": """# Fase 0 — Discovery

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
""",

    "56-google-play-sidekick-fase-1-funda-o": """# Fase 1 — Fundação

**Objetivo:** Estabelecer a infraestrutura básica (Domain Events, Gamification Service) e Feature Flags sem espalhar dependências do Google Play pelo código.

## 1. Gamification Engine (Módulo)
Criar ou refatorar um módulo independente `Gamification` respeitando a arquitetura existente.
Componentes sugeridos (adaptar se necessário): Achievements, Experience, Levels, Progression, Quests, Challenges, Streaks, Rewards, Leaderboards, Seasons, Leagues, Collections, Milestones, Social, Comeback, LiveOps, GameStats, Analytics, Integrations.

## 2. Event Bus de Gameplay
Gamificação não deve depender de chamadas manuais espalhadas.
Criar eventos de domínio como: `GameStarted`, `TutorialCompleted`, `MatchStarted`, `LevelCompleted`, `QuestCompleted`, etc.
Os consumidores desses eventos alimentarão Achievements, Quests, Game Stats, Analytics, Streaks, etc. Evitar forte acoplamento.

## 3. Feature Flags
Toda funcionalidade nova deve possuir feature flag (ex: `google_play_sidekick`, `game_stats`, `daily_quests`, etc.) permitindo rollback rápido.

## 4. Analytics e Telemetria
Instrumentar o sistema com eventos analíticos essenciais (`gamification_viewed`, `achievement_unlocked`, `quest_completed`, etc.) e estabelecer KPIs para medir D1, D7, D30, sessões, etc.
""",

    "57-google-play-sidekick-fase-2-play-games-services": """# Fase 2 — Play Games Services

**Objetivo:** Implementar robustamente o PGS v2 com fallback, tratamento de lifecycle, autenticação e Recall API.

## 1. Google Play Games Services V2
Implementar corretamente o PGS v2 garantindo:
- Inicialização no startup.
- Autenticação automática quando apropriada.
- Tratamento assíncrono, reconexão, troca de conta, retomada de background.
- Fallback quando PGS indisponível. **O jogo nunca deve deixar de funcionar sem o Play Games.**

## 2. Identidade e Recall API
Se o jogo possui sistema próprio de contas, criar associação segura (`internal_player_id <-> play_games_player_id`).
Avaliar a utilização da **Recall API** para facilitar a recuperação de contas. Nunca usar atributos mutáveis como chave.

## 3. Cloud Save e Offline-First
Auditar o sistema de save atual. Implementar ou integrar a solução oficial do Cloud Save se necessário.
Garantir:
- Estratégia de resolução de conflitos (localVersion, cloudVersion).
- Funcionamento offline para a gamificação básica (fila de eventos pendentes `pending_game_events` com sync posterior).
""",

    "58-google-play-sidekick-fase-3-achievements": """# Fase 3 — Achievements

**Objetivo:** Criar uma Achievement Engine conectada ao Google Play com pelo menos 40 conquistas, incluindo 4 alcançáveis na primeira hora.

## 1. Conquistas
Criar um sistema profundo de Achievements integrado ao Play Games, com 40 a 60 conquistas (nunca menos que o exigido pelo Level Up).
Distribuir em categorias: Progressão, Habilidade, Exploração, Coleção, Social, Persistência, Segredos.

## 2. A Primeira Hora
Garantir pelo menos 4 conquistas alcançáveis na primeira hora (ex: 0-5 min, 5-15 min, 15-30 min, 30-60 min). Devem representar progresso real.

## 3. Achievement Matrix
Criar `/docs/google-play/achievement-matrix.md` documentando: ID, Nome, Categoria, Objetivo, Incremental, Hidden, XP, Recompensa, Dificuldade.
Toda conquista precisa de ID permanente, ícone e evento de progresso associado. IDs nunca devem ser reciclados.
""",

    "59-google-play-sidekick-fase-4-game-stats": """# Fase 4 — Game Stats

**Objetivo:** Instrumentar Game Stats avançados (Progress e Repetitive) gerando Schemas e CSVs compatíveis com Play Console.

## 1. Game Stats
Identificar os eventos relevantes de gameplay (ex: vitórias, partidas concluídas, nível, recorde). Não enviar lixo analítico ou dados sensíveis.

## 2. Player Progression Stat
Definir uma estatística principal de progressão (Level, Rank, World Progress, Power) e atualizar sempre que mudar e no início da sessão para manter consistência.

## 3. Game Stats Schema
Gerar arquivos necessários para o Play Console: `PlayerGameEvent.csv`, `ProgressionStatConfig.csv`, etc.
Criar `/docs/google-play/game-stats-schema.md` documentando os eventos, propriedades e agregações. Respeitar limites rigorosamente.
""",

    "60-google-play-sidekick-fase-5-gamifica-o-avan-ada": """# Fase 5 — Gamificação Avançada

**Objetivo:** Criar XP centralizado, Levels, Quests, Streaks, Collections, Mastery e Rewards seguros (loops diários e semanais).

## 1. Sistema de XP e Níveis
Criar curva de XP baseada em habilidade, progresso e exploração. Curva sustentável: primeiros níveis rápidos, longo prazo prestigioso (sem grind artificial abusivo).

## 2. Quests e Streaks
- **Quest System:** Baseado em eventos (Daily, Weekly, Season, etc). Criar Quest Chains (metas encadeadas).
- **Streak System:** Sequências (ex: 1, 3, 7, 30 dias) com mecanismos de perdão (Streak Freeze, Grace Period).
- **Daily/Weekly Loops:** Ciclo claro de "Entrar -> Objetivo -> Jogar -> Recompensa".

## 3. Reward Economy
Criar tabela central (`RewardDefinition`) com ID, amount, rarity, maxClaims.
O Reward Service deve validar elegibilidade e concessão via servidor (idempotente) antes de notificar UI.

## 4. Collections e Mastery
Sistema visual de coleções e Masteries de personagens/armas para criar metas orgânicas de longo prazo.

## 5. Comeback System
Experiência de retorno acolhedora para jogadores inativos, com missões de catch-up balanceadas.
""",

    "61-google-play-sidekick-fase-6-social": """# Fase 6 — Social

**Objetivo:** Integrar Leaderboards, Social Challenges e Progressão competitiva, caso aplicável.

## 1. Social Engagement e Challenges
Criar loops sociais: comparar progresso, objetivos cooperativos, milestones comunitários. Arquitetar métricas para Social Challenges do Google Play.

## 2. Leaderboards e Ligas
Integrar leaderboards com recortes razoáveis (Global, Friends, Weekly).
Se aplicável, criar sistema de Ligas (Bronze, Silver, Gold) para grupos competitivos menores.
*Nota: Não confiar no score do cliente para rankings cruciais.*

## 3. Perfil do Jogador
Criar perfil interno forte (Rank, Badges, Stats). Espelhar no Gamer Profile do Google Play através das APIs apropriadas.
""",

    "62-google-play-sidekick-fase-7-liveops": """# Fase 7 — LiveOps

**Objetivo:** Permitir configuração Server-Driven (Seasons, Daily/Weekly Quests) para operar o jogo sem depender de atualizações.

## 1. Infraestrutura Server-Driven
Sistema para injetar missões diárias, eventos e recompensas dinamicamente (versão, startsAt, endsAt, audience).

## 2. Seasons (Temporadas)
Criar infraestrutura de temporadas (XP, níveis, recompensas, líder de temporada) independente do client release.

## 3. Dificuldade Dinâmica e Personalização
Balancear missões sugeridas com base no perfil do jogador, sem adulterar o gameplay competitivo em si.

## 4. Play Points e Play Pass
Preparar infraestrutura para benefícios, ofertas ou troca de pontos do Play Points/Pass. Totalmente configurável.
""",

    "63-google-play-sidekick-fase-8-sidekick": """# Fase 8 — Sidekick

**Objetivo:** Validar UI, ciclo de vida e overlay do Play Games Sidekick em todos os fluxos e imersões sem quebrar UX/controles.

## 1. Play Games Sidekick Integration
Preparar completamente o jogo para o Sidekick (AAB ou APK legado).
Testar overlay, immersive mode, cutouts, rotações, multitarefa e interrupções. Nenhum controle essencial pode ficar inutilizável.
Criar `/docs/google-play/sidekick-integration.md`.

## 2. Game Tips / Gemini / Conteúdo
Preparar nomenclatura semântica de sistemas, modos e itens para funcionar bem com Gemini e Game Tips.
Criar estratégia de conteúdos e vídeos (`/docs/google-play/content-strategy.md`).

## 3. UI/UX Visual Polish
Garantir microanimações e feedback elegante (AchievementUnlocked, LevelUp) sem popups obstrutivos. Implementar identidade de Raridade visual clara.
""",

    "64-google-play-sidekick-fase-9-seguran-a": """# Fase 9 — Segurança

**Objetivo:** Auditar e fechar vulnerabilidades de economy (reward abuse, replay, spoofing, cheating).

## 1. Anti-Cheat e Confiança
Tudo que afete leaderboard, recompensas raras ou rankings deve ser validado via servidor (autoridade).
Implementar limites, nonces, timestamps, detecção de anomalias e rate limiting.

## 2. Replay e Idempotência
A concessão de recompensas e a resolução de eventos em background devem ser idempotentes e imunes a ataques de replay no envio. Avaliar Play Integrity API.
""",

    "65-google-play-sidekick-fase-10-qa": """# Fase 10 — QA

**Objetivo:** Construir testes end-to-end de lifecycle, Sidekick, achievements, economia e contas.

## 1. Matriz de Dispositivos e Testes Sidekick
Testar Sidekick fechado/aberto, background, screen recording, etc. Testar na matriz de aparelhos baixa/média/alta performance e offline.

## 2. Testes Específicos
- **Autenticação:** Instalação limpa, troca de conta, offline, recuperação de contas.
- **Achievements:** Unlock offline, resync, duplicados (não devem desbloquear duas vezes).
- **Recompensas:** Exactly-once semantics, timeouts, re-tries, server errors.
""",

    "66-google-play-sidekick-fase-11-performance": """# Fase 11 — Performance

**Objetivo:** Monitorar métricas do Google Play Games Level Up (FPS, ANR, Battery, Memória) para garantir que a gamificação não afeta a performance.

## 1. Impacto da Gamificação
Usar event-driven, batch processing e caches locais em vez de validações custosas frame-a-frame. Não deteriorar o gameplay.

## 2. Android Quality
Auditar crashes, ANRs, frame pacing, startup time e network payload.
Verificar conformidade com requisitos de qualidade do Level Up e criar `/docs/google-play/level-up-quality.md`.
""",

    "67-google-play-sidekick-fase-12-release": """# Fase 12 — Release

**Objetivo:** Orquestrar lançamento no Play Console, Checklist Level Up, testes A/B, Relatório Final e Matriz 100% de Compatibilidade.

## 1. Rollout e Checklist do Console
Criar `/docs/google-play/play-console-checklist.md` com ações manuais (cadastros, credenciais, leaderboards, stats).
Planejar rollout seguro (Internal -> Closed -> Production) com critérios de rollback.

## 2. Definition of Done e Relatório
Gerar a Matriz Final de Compatibilidade atestando 100% de readiness para todos os recursos do Sidekick, PGS e Level Up (marcar manuais detalhadamente). Preparar o relatório final conforme solicitado na Fase 12 do escopo original.
"""
}

base_dir = ".planning/phases"

for dirname, content in phases_data.items():
    path = os.path.join(base_dir, dirname, "CONTEXT.md")
    if os.path.exists(path):
        with open(path, "w") as f:
            f.write(content)
            print(f"Updated {path}")
    else:
        print(f"Directory {dirname} not found")

