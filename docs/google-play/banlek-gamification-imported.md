# Ideias Importadas da Gamificação (Painel Banlek - Homolog)

O sistema de gamificação da Banlek em homologação baseia-se num motor de missões (MissionRuleEngine) com foco em ações diárias, semanais e mensais, como:
- "Crie um album novo"
- "Venda pra clientes diferentes"
- "Venda em albuns diferentes"
- "Venda fotos diferentes"
- "Fature na semana"
- "Feche vendas na semana"
- "Suba fotos na semana"
- "Feche vendas no mes"
- "Suba fotos no mes"

Para aplicar o **mesmo nível de alto engajamento** dessas ações no nosso jogo `MmoMobile` (MMO de construção de impérios e guerra estratégica), nós as traduzimos para o core loop do jogo.

## Ações Diárias (Daily Quests)
- **Expanda seu Império (Inspirado em "Crie um album novo"):** Construa ou aprimore um edifício.
- **Batalhas Diversas (Inspirado em "Venda pra clientes diferentes"):** Derrote 3 tipos diferentes de inimigos (ex: Bárbaros, Arqueiros, Cavalaria).
- **Explorador Versátil (Inspirado em "Venda em albuns diferentes"):** Colete recursos em 2 biomas/territórios diferentes.
- **Mercador Ativo (Inspirado em "Venda fotos diferentes"):** Venda ou troque 3 recursos diferentes no mercado da aliança.

## Ações Semanais (Weekly Quests)
- **Acumulador de Riquezas (Inspirado em "Fature na semana"):** Acumule uma certa quantidade de Ouro/Recursos durante a semana.
- **Comerciante / Saqueador (Inspirado em "Feche vendas na semana"):** Realize 5 saques bem-sucedidos ou negociações no mercado durante a semana.
- **Senhor da Guerra (Inspirado em "Suba fotos na semana"):** Treine X unidades militares durante a semana.

## Ações Mensais (Monthly Quests / Temporada)
- **Dominador Comercial (Inspirado em "Feche vendas no mes"):** Complete 20 rotas comerciais no mês.
- **Potência Militar (Inspirado em "Suba fotos no mes"):** Aprimore o nível das suas tropas ou complete pesquisas cruciais no mês.

Estas mecânicas deverão ser injetadas pelo `GamificationService` do jogo utilizando a estrutura do `MissionRuleEngine` adaptada para as necessidades do MMO (contagens diárias, somas diárias e contagens distintas diárias).
