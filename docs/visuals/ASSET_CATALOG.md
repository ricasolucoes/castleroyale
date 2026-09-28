# Castle Royale — catálogo visual para Google AI Studio / Flow

Este catálogo organiza a direção artística do MVP e das 68 fases do roadmap.
Os prompts são deliberadamente livres de texto legível, marcas e logotipos para
que a interface do app continue sendo localizada e acessível.

## Direção comum

Arte original para jogo mobile de estratégia, composição legível em retrato,
contraste alto entre fundo e interface, espaço negativo para HUD, sem texto,
sem logotipo, sem watermark, sem marcas reais. Usar a paleta semântica do app:
bronze, ouro, azul-aço, superfícies escuras e iluminação de recorte suave.

## Três eras

| Era | Fases cobertas | Motivos | Aplicação principal |
| --- | --- | --- | --- |
| Passado | 00–10, 28–32, 44–45 | pedra, madeira, pergaminho, estandartes, fogo | onboarding, cidade, mundo e tutorial |
| Presente | 11–27, 33–43, 46–54 | aço, cartografia, logística, rádio, luz âmbar | militar, aliança, economia, social e operação |
| Futuro | 55–67 | cristal, energia azul, arquitetura solarpunk, holografia discreta | Play Games, LiveOps, Sidekick e lançamento |

## Prompts prontos

### Imagem — cidade e mundo

`Mobile strategy game environment, [ERA], a fortified settlement viewed from
above with a readable central landmark and clear paths, original game art, no
readable text, no logos, no brands, portrait-safe composition, negative space
for a HUD, dark bronze and steel-blue palette with [ERA_MOTIFS], cinematic
soft rim light, crisp silhouettes, coherent with a persistent world map.`

### Imagem — militar e aliança

`Mobile strategy game key art, [ERA], a disciplined garrison beside a council
banner and a tactical map table, clear focal hierarchy for a small phone
screen, original game art, no readable text, no logos, no brands, negative
space for UI, [ERA_MOTIFS], bronze-gold highlights, steel-blue shadows.`

### Animação — transição entre eras

`Create a short seamless UI background loop for a mobile strategy game. A
camera slowly passes from [ERA_A] materials into [ERA_B] materials while the
same fortress silhouette remains stable. Minimal motion, no camera shake, no
readable text, no logos, no brands, loop-safe first and last frames, enough
negative space for interface, preserve the bronze/gold/steel-blue palette.`

### Animação — pulso de evento

`Create a subtle seamless 2-second notification loop for a strategy game:
one controlled pulse of light travels across a world-map marker and fades,
with restrained particles, no text, no logos, no brands, transparent-looking
dark background, safe for repeated playback and reduced-motion fallback.`

## Matriz de telas

Cada tela recebe uma variante das três eras; o app nunca depende do asset para
comunicar estado.

| Tela | Passado | Presente | Futuro | Movimento |
| --- | --- | --- | --- | --- |
| Splash / login | brasão em pedra | mapa técnico | núcleo de energia | brilho de entrada |
| Cidade | praça medieval | distrito industrial | núcleo solarpunk | fumaça/luz muito sutil |
| Mundo | relevo e estradas | grade cartográfica | camadas holográficas | marcador em pulso |
| Militar | estandarte e muralha | mesa tática | drones geométricos | bandeira/cursor |
| Aliança | salão do conselho | central logística | rede de energia | conexão de nós |
| Perfil | selo de governante | ficha de campanha | identidade modular | revelação de cartão |
| Eventos / LiveOps | feira sazonal | operação de campo | anúncio orbital | entrada de painel |

## Estado de geração

O projeto `Castle Royale` foi verificado no Google Flow em 2026-09-09. O Flow
está autenticado, mas a conta exibiu `0 créditos` e deixou a geração de novas
imagens desabilitada. A direção visual e as cenas já existentes no projeto foram
usadas como referência; os assets que faltavam foram produzidos localmente pelo
gerador de imagens integrado, com prompts versionados ao lado de cada PNG.

## Integração mobile

O cliente Expo empacota e usa os assets concretos: `apps/mobile/assets/game/castle-hero.jpg`
na entrada, `apps/mobile/assets/game/village-house.jpg` no detalhe de lote vazio,
`apps/mobile/assets/game/royal-guard.png` e `apps/mobile/assets/game/legendary-sword.png`
na tela militar, e os 54 renders de `apps/mobile/assets/buildings/` no detalhe
dos prédios da cidade. Os quatro arquivos de mundo aparecem na galeria
cartográfica como referência visual, enquanto o mapa em runtime permanece
baseado nos tiles e marcadores autoritativos do servidor.
