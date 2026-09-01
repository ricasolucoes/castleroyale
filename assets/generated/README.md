# Castle Royale — visual asset kit

Pacote visual inicial gerado em 2026-08-28 para validar a direção artística do
cliente mobile. A tentativa de criação direta no Google Flow foi preservada no
projeto `Castle Royale — MVP Visual Kit`; nesta sessão o botão de geração não
disparou um job. Os PNGs abaixo foram gerados pelo gerador integrado como
fallback e já estão dentro do workspace.

## Escopo gerado

O catálogo concreto atual em `packages/game-data/data/buildings.json` possui
cinco edificações, todas com níveis 1–3: 15 renders no total.

| Edificação | Níveis |
| --- | --- |
| Palace | 1, 2, 3 |
| Farm | 1, 2, 3 |
| Lumber Mill | 1, 2, 3 |
| Quarry | 1, 2, 3 |
| Warehouse | 1, 2, 3 |

Assets de mapa:

- `map/world-overview.png` — visão mundial com regiões, biomas, rios e marcadores.
- `map/region-detail.png` — detalhe regional com grid, cidades, recursos,
  acampamento, estrada, rio e fortaleza.
- `map/marker-atlas.png` — referência visual de cidade do jogador, cidade neutra,
  cidade inimiga, madeira, pedra, ferro, acampamento e fortaleza.
- `map/terrain-atlas.png` — referência visual para Plains, Forest, Hills,
  Mountains, River e Road.

## Uso previsto

Os renders são concept assets de referência, não um atlas runtime final. Eles
devem passar por remoção/normalização de fundo, recorte, definição de escala,
compressão e atlas packing antes de serem conectados ao Skia ou às telas do
Expo. Não há alteração de regra de jogo, balanceamento ou contrato de API neste
commit de assets.

As outras 13 edificações listadas em `docs/game-design/buildings.md` ainda não
possuem níveis concretos no catálogo de dados. Elas devem ser geradas quando
`buildings.json` definir seus níveis e progressão; este pacote não inventa esses
dados.

## Direção visual

Vista ortográfica/isométrica, materiais 3D estilizados com acabamento PBR,
pedra, madeira, bronze, azul profundo, bordô, verde de floresta e tons de
pergaminho. Os prompts pediram ausência de UI, texto, logo e watermark para
manter os arquivos reutilizáveis em protótipos.
