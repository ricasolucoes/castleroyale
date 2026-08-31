# MVP jogável

O MVP atual é uma fatia vertical pequena, mas utilizável:

1. O jogador entra como convidado.
2. O servidor cria ou recupera um jogador em Aurora e uma cidade inicial.
3. A cidade exibe recursos, capacidades e edifícios.
4. Recursos de comida, madeira e pedra acumulam com o tempo do servidor.
5. O jogador inicia uma melhoria, paga o custo no servidor e acompanha a
   contagem regressiva.
6. Ao concluir, o edifício sobe de nível e a cidade é atualizada.
7. Mundo mostra assentamentos persistidos na janela territorial do servidor.
8. Militar mostra a guarnição inicial calculada pela API.
9. Aliança permite fundar uma aliança e repetir a solicitação com segurança.

O cliente não calcula saldo, custo, duração ou conclusão. Ele apenas envia
intenções e renderiza a resposta autoritativa da API.

## Executar

Com o ambiente Docker configurado:

    make setup
    make dev
    npm run mobile

No app, toque em Jogar como convidado. Cidade, Mundo, Militar e Aliança já
podem ser explorados; os sistemas completos de cada fase continuam reservados
para o roadmap.

Para testar a passagem do tempo durante o desenvolvimento, configure
DEBUG_TIME_SCALE no ambiente local da API. A economia e a conclusão continuam
calculadas no servidor.

## Limites deliberados

Este MVP ainda não inclui mapa explorável com tiles persistentes, múltiplos
mundos selecionáveis, pesquisa, treinamento, marchas, combate, conquista,
convites/território de alianças, mercado, chat ou LiveOps. Essas capacidades
permanecem nas fases correspondentes do roadmap.
