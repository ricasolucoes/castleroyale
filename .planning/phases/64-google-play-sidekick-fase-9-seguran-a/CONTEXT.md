# Fase 9 — Segurança

**Objetivo:** Auditar e fechar vulnerabilidades de economy (reward abuse, replay, spoofing, cheating).

## 1. Anti-Cheat e Confiança
Tudo que afete leaderboard, recompensas raras ou rankings deve ser validado via servidor (autoridade).
Implementar limites, nonces, timestamps, detecção de anomalias e rate limiting.

## 2. Replay e Idempotência
A concessão de recompensas e a resolução de eventos em background devem ser idempotentes e imunes a ataques de replay no envio. Avaliar Play Integrity API.
