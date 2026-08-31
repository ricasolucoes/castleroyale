# Fase 1 — Fundação

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
