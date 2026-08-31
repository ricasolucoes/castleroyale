# Fase 2 — Play Games Services

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
