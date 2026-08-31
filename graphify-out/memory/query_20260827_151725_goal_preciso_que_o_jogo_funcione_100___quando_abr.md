---
type: "query"
date: "2026-08-27T15:17:25.846681+00:00"
question: "/goal Preciso que o jogo funcione 100%. Quando abre no celular pede pra rodar npx expo start. Se essa linguagem for muito ruim podemos trocar para tauri ou flutter ou então faça funcionar"
contributor: "graphify"
outcome: "useful"
source_nodes: ["apps/mobile/package.json,apps/mobile/app.json,apps/mobile/metro.config.js,apps/mobile/src/api/client.ts"]
---

# Q: /goal Preciso que o jogo funcione 100%. Quando abre no celular pede pra rodar npx expo start. Se essa linguagem for muito ruim podemos trocar para tauri ou flutter ou então faça funcionar

## Answer

A causa era a combinação de expo start --dev-client, resolução Metro customizada que escondia dependências do Expo Router, árvore de rotas fora da raiz efetiva e API localhost inacessível no celular. O start agora usa Expo Go, a raiz app é explícita, Metro usa getDefaultConfig e localhost é adaptado ao host LAN. O app foi verificado com bundles iOS/Android HTTP 200 e API LAN HTTP 200; Expo/React Native permanecem a stack adequada.

## Outcome

- Signal: useful

## Source Nodes

- apps/mobile/package.json,apps/mobile/app.json,apps/mobile/metro.config.js,apps/mobile/src/api/client.ts