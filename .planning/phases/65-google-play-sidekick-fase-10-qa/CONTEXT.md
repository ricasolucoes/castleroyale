# Fase 10 — QA

**Objetivo:** Construir testes end-to-end de lifecycle, Sidekick, achievements, economia e contas.

## 1. Matriz de Dispositivos e Testes Sidekick
Testar Sidekick fechado/aberto, background, screen recording, etc. Testar na matriz de aparelhos baixa/média/alta performance e offline.

## 2. Testes Específicos
- **Autenticação:** Instalação limpa, troca de conta, offline, recuperação de contas.
- **Achievements:** Unlock offline, resync, duplicados (não devem desbloquear duas vezes).
- **Recompensas:** Exactly-once semantics, timeouts, re-tries, server errors.
