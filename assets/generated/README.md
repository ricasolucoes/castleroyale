# Castle Royale — visual asset kit

Pacote visual inicial gerado em 2026-08-28 para validar a direção artística do
cliente mobile. A tentativa de criação direta no Google Flow foi preservada no
projeto `Castle Royale — MVP Visual Kit`; nesta sessão o botão de geração não
disparou um job. Os PNGs abaixo foram gerados pelo gerador integrado como
fallback e já estão dentro do workspace.

## Escopo gerado

O catálogo concreto atual em `packages/game-data/data/buildings.json` possui
18 edificações, todas com níveis 1–3: 54 renders no total.

| Edificação | Níveis |
| --- | --- |
| Palace | 1, 2, 3 |
| Farm | 1, 2, 3 |
| Lumber Mill | 1, 2, 3 |
| Quarry | 1, 2, 3 |
| Warehouse | 1, 2, 3 |
| Barracks | 1, 2, 3 |
| Archery Range | 1, 2, 3 |
| Stable | 1, 2, 3 |
| Siege Workshop | 1, 2, 3 |
| Academy | 1, 2, 3 |
| Embassy | 1, 2, 3 |
| Marketplace | 1, 2, 3 |
| Hospital | 1, 2, 3 |
| Walls | 1, 2, 3 |
| Watchtower | 1, 2, 3 |
| Iron Mine | 1, 2, 3 |
| Treasury | 1, 2, 3 |
| Tavern | 1, 2, 3 |

Assets de mapa:

- `map/world-overview.png` — visão mundial com regiões, biomas, rios e marcadores.
- `map/region-detail.png` — detalhe regional com grid, cidades, recursos,
  acampamento, estrada, rio e fortaleza.
- `map/marker-atlas.png` — referência visual de cidade do jogador, cidade neutra,
  cidade inimiga, madeira, pedra, ferro, acampamento e fortaleza.
- `map/terrain-atlas.png` — referência visual para Plains, Forest, Hills,
  Mountains, River e Road.

## Uso previsto

Os renders são concept assets de referência, não um atlas runtime final. Os 15
renders de edificações foram copiados para `apps/mobile/assets/buildings/` e
entram no detalhe de prédio da cidade em escala contida. Eles ainda não são um
atlas para o Skia: os assets de mapa continuam exigindo remoção/normalização de
fundo, definição de escala, compressão e atlas packing antes de serem conectados
ao mapa dinâmico do Expo. Não há alteração de regra de jogo, balanceamento ou
contrato de API neste commit de assets.

Todos os 18 códigos atualmente definidos no catálogo de dados possuem os três
níveis visuais. O arquivo `missing-building-prompts.md` registra os prompts dos
13 conjuntos gerados nesta complementação; nenhum custo, duração ou efeito foi
inventado para produzir os renders.

## Direção visual

Vista ortográfica/isométrica, materiais 3D estilizados com acabamento PBR,
pedra, madeira, bronze, azul profundo, bordô, verde de floresta e tons de
pergaminho. Os prompts pediram ausência de UI, texto, logo e watermark para
manter os arquivos reutilizáveis em protótipos.
