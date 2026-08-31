# Fase 4 — Game Stats

**Objetivo:** Instrumentar Game Stats avançados (Progress e Repetitive) gerando Schemas e CSVs compatíveis com Play Console.

## 1. Game Stats
Identificar os eventos relevantes de gameplay (ex: vitórias, partidas concluídas, nível, recorde). Não enviar lixo analítico ou dados sensíveis.

## 2. Player Progression Stat
Definir uma estatística principal de progressão (Level, Rank, World Progress, Power) e atualizar sempre que mudar e no início da sessão para manter consistência.

## 3. Game Stats Schema
Gerar arquivos necessários para o Play Console: `PlayerGameEvent.csv`, `ProgressionStatConfig.csv`, etc.
Criar `/docs/google-play/game-stats-schema.md` documentando os eventos, propriedades e agregações. Respeitar limites rigorosamente.
