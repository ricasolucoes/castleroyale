# Fase 5 — Gamificação Avançada

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
