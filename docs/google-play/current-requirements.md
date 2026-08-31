# Google Play Games & Sidekick: Requisitos Atuais

| Recurso | Fonte Oficial | Impacto no Projeto | Situação Atual | Implementação Necessária | Status |
|---------|---------------|-------------------|----------------|--------------------------|--------|
| **Play Games Services v2** | docs.google.com/games | Exige login não-obstrutivo, tratamento de lifecycle e integração nativa Android. | Ausente. | Adicionar lib nativa, flow de serverAuthCode para Backend. | PENDENTE (Fase 2) |
| **Sidekick Overlay** | Play Console | Overlay pode sobrepor UI. Jogo deve lidar com interrupções. | Ausente. | Teste imersivo com React Native Skia e Gesture Handler. | PENDENTE (Fase 8) |
| **Google Play Level Up** | Level Up Program | Mínimo de conquistas e Game Stats para qualificação de destaque. | Ausente. | Desenvolver 40+ achievements; integrar Progression Stat. | PENDENTE (Fase 3/4) |
| **Cloud Save / Recall** | docs.google.com/games | Facilita migração e recuperação de contas. | Backend-authoritative. | Associar `play_games_player_id` na conta do jogador. | PENDENTE (Fase 2) |
| **Game Stats** | Play Console | Necessita arquivos CSV de schema detalhados. Limites estritos de push. | Ausente. | Gerar os arquivos Schema e API de Sync no Backend. | PENDENTE (Fase 4) |
| **Achievements** | Play Console | Necessita fallback offline, resync, icones padronizados e progresso seguro. | Ausente. | Event Bus no frontend e backend para destravamento seguro. | PENDENTE (Fase 3) |

*Nota: Não foi assumida validade de SDKs antigos (ex: v1). A integração deve ser feita com a API Play Games Services v2 e bibliotecas modernas para React Native (ou módulos Expo Custom).*
