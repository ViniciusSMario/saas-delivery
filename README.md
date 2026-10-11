# SaaS Delivery

SaaS multi-tenant para restaurantes: Laravel 12, Livewire 3, PostgreSQL 17 (um schema por estabelecimento), Redis e Docker.

Entregue até agora:

- **Fase 1 (Fundação):** camada de tenancy, comandos `tenants:*` e suíte de isolamento.
- **Fase 2 (Acesso e estabelecimento):** login, recuperação de senha, usuários, perfis e permissões, auditoria, dados do estabelecimento, horários e configurações. Admin da plataforma com 2FA, lista, criação, suspensão e reativação de estabelecimentos.

- **Fase 3 (Cardápio):** categorias, produtos com fotos e código de PDV, grupos de adicionais, combos e cardápio público (vitrine).

- **Fase 4 (Pedidos e balcão):** pedido online pelo cardápio (carrinho, checkout e acompanhamento), venda de balcão e painel de pedidos com status.

- **Fase 5 (Mesas e PDV):** mesas e comandas, PDV com busca rápida e caixa (abertura, sangria, suprimento, fechamento com conferência).

- **Fase 6 (Estoque):** insumos, movimentações (compra, venda, perda, ajuste), ficha técnica com custo e margem, e baixa automática na venda.

- **Fase 7 (Clientes, financeiro e dashboard):** cadastro de clientes com histórico e LGPD, contas a pagar e receber integradas ao caixa e ao estoque, painel do dia e relatórios com exportação CSV.

- **Fase 8 (Interface):** cardápio online como app web (mobile first, navegação inferior, folha de produto, busca, "meus pedidos" e instalável na tela inicial) e painel padronizado (design system, ícones, barra lateral, cozinha com tempo de espera e tela cheia).

- **Fase 9 (Operação da plataforma):** backup diário e restauração por estabelecimento, exclusão com carência, site com planos, cadastro self-service com teste grátis, limites por plano e cobrança recorrente (Asaas ou gateway de teste).

- **Fase 11 (Produção):** imagens Docker de produção com HTTPS automático (Caddy, certificado sob demanda), deploy por script e GitHub Actions, backups fora do servidor e do banco inteiro, Sentry, acesso de suporte auditado, domínio próprio e papel do PostgreSQL por estabelecimento. Guia em [docs/PRODUCAO.md](docs/PRODUCAO.md).

- **Fase 10 (Operação do restaurante):** impressão na cozinha por setor (estação no navegador ou impressora de rede), entregas com taxa por bairro, tela do entregador e rota no mapa, e avisos ao cliente pelo WhatsApp.

## Subir o ambiente

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose run --rm -u root app php artisan key:generate
docker compose up -d
docker compose exec app php artisan central:migrate --seed
```

| Serviço | Endereço |
|---|---|
| Site central | http://localhost:8080 |
| Admin da plataforma | http://admin.localhost:8080 |
| Estabelecimento | http://&lt;slug&gt;.localhost:8080 |
| Mailpit (e-mails) | http://localhost:8025 |
| PostgreSQL (DBeaver etc.) | localhost:5433, usuário `saas`, senha `secret`, bancos `saas` e `saas_test` |

Use a pasta dentro do Ubuntu (WSL), com a integração WSL do Docker Desktop ligada. O `www-data` dos containers PHP usa o uid/gid 1000, o mesmo do seu usuário; se o seu for outro, rode `UID=$(id -u) GID=$(id -g) docker compose build`. `vendor/` e `node_modules/` ficam em volumes Docker. Rode `composer` e `artisan` sempre dentro do container. Navegadores resolvem `*.localhost` sozinhos, sem editar o `hosts`.

## Primeiro acesso

**Admin da plataforma** (equipe do SaaS):

```bash
docker compose exec app php artisan platform:create-user voce@exemplo.com "Seu Nome"
```

Entre em http://admin.localhost:8080. No primeiro login, escaneie o QR code com um app autenticador (Google Authenticator, Authy, 1Password...). O 2FA é obrigatório. Perfis: `admin` (gerencia estabelecimentos) e `support` (só consulta, `--role=support`).

**Estabelecimento com dono:** pelo admin (botão "Novo estabelecimento") ou pelo terminal:

```bash
docker compose exec app php artisan tenants:create pizzaria "Pizzaria Exemplo" --owner-email=dono@pizzaria.test --owner-name="Dono"
```

O dono recebe um e-mail com o link para definir a senha. Em desenvolvimento, o e-mail aparece no Mailpit (http://localhost:8025). Depois, ele entra em `http://pizzaria.localhost:8080/login`. Para um tenant que ainda não tem usuários:

```bash
docker compose exec app php artisan tenants:invite-user pizzaria outro@pizzaria.test "Nome" --role=dono
```

## Cardápio

- **Gestão** (permissão `menu.manage`, perfis Dono e Gerente): `/produtos`, `/categorias`, `/adicionais` e `/combos` no endereço do estabelecimento.
- **Vitrine pública:** `http://<slug>.localhost:8080/cardapio` ou `http://localhost:8080/cardapio/<slug>`. Mostra se está aberto agora, conforme o horário e o fuso do estabelecimento (turnos depois da meia-noite incluídos).
- **App do cardápio:** feito para o celular, com quatro abas na barra inferior (`#cardapio`, `#buscar`, `#pedidos`, `#loja`). O "voltar" do celular troca de aba e fecha a folha do produto. As categorias acompanham a rolagem. A busca roda no navegador, sem ida ao servidor. A aba Pedidos lista os pedidos feitos naquele aparelho (`localStorage`) com a situação atualizada por `/pedido/{token}/status`. O manifesto (`/cardapio/manifest.webmanifest`) permite "Adicionar à tela inicial". No computador, o app aparece como uma coluna centralizada.
- **Layout da vitrine:**
  - **Capa:** foto do topo enviada em Estabelecimento → "Foto de capa do cardápio" (`establishment.cover_path`, servida em `/cardapio/capa`); sem foto, fica o degradê da marca. O cartão da loja mostra entrega/retirada com o tempo estimado (Configurações).
  - **Barra fixa no topo:** busca (os resultados tomam o lugar das seções enquanto se digita), carrinho com a quantidade de itens e as categorias, que acompanham a rolagem.
  - **Vitrine com foto grande:** os produtos marcados como destaque; sem destaques, os **mais pedidos** (os 3 produtos mais vendidos nos últimos 30 dias, com pelo menos 3 unidades; cache de 10 minutos). Esses produtos levam o selo "Mais pedido" também na lista.
  - Cada produto mostra quantas unidades já estão no carrinho, e a barra "Ver carrinho" (com itens e total) fica sempre à vista no celular quando há itens.
- **Dinheiro** sempre em centavos (`bigint`), com `App\Support\Money` e o cast `MoneyCast`. O formulário aceita "39,90", "1.234,56" ou "R$ 9,90".
- **Busca** por parte do nome usa índice trigram (`pg_trgm`, schema `extensions`); o código do produto é buscado por igualdade. O código é único entre os produtos não excluídos.
- **Produto excluído** é soft delete: sai do cardápio, mas pedidos antigos continuam apontando para ele. **Esgotado** mantém o produto visível, sem permitir pedido. Combo com item indisponível sai do cardápio.
- **Fotos** ficam em `storage/app/tenants/<id>/products/<produto>/` (até 5 por produto) e são servidas por id, nunca por caminho vindo da URL.
- **Cardápio de demonstração:** `docker compose exec app php artisan tenants:seed --tenant=empresa1 --class="Database\Seeders\Tenant\DemoMenuSeeder"`

## Pedidos

- **Pedido online:** no cardápio público, o cliente monta o carrinho (fica no navegador), escolhe entrega ou retirada e o pagamento na entrega (dinheiro com troco, Pix, cartão, vale). Depois acompanha o status em `.../cardapio/pedido/<token>`, que se atualiza sozinho. Funciona no subdomínio e em `localhost:8080/cardapio/<slug>`.
- **Acompanhamento pelo cliente** (`.../cardapio/pedido/<token>`): etapas com o horário de cada uma, previsão de entrega ou de retirada (Configurações → "Tempo estimado de entrega/retirada"; com taxa por bairro vale o tempo do bairro; 0 esconde), o primeiro nome do entregador quando sai, e WhatsApp/telefone da loja. Confere a situação a cada 10 s e ao voltar para a aba.
  - **Avisos no celular do cliente** (Web Push, sem conta): "Avisar" pede a permissão e grava a inscrição em `order_push_subscriptions`, ligada só àquele pedido (até 5 aparelhos; `POST/DELETE .../pedido/<token>/avisos`, mesmos serviços de push permitidos da equipe). A cada etapa sai um aviso (aceito, pronto/pronto para retirada, saiu para entrega com o entregador e a previsão, entregue, cancelado com o motivo) pelo job `SendOrderPush`; tocar abre a página do pedido no mesmo endereço em que o cliente se inscreveu. Quando o pedido termina, as inscrições são apagadas. No iPhone, só com o cardápio adicionado à Tela de Início (iOS 16.4+); sem suporte, a página explica que se atualiza sozinha.
- **Tudo é conferido no servidor** (`App\Modules\Orders\Actions\PlaceOrder`): preço, item ativo e não esgotado, regras de mínimo e máximo dos adicionais, horário de funcionamento, pedido mínimo, opções de entrega, troco e desconto (só no balcão, com a permissão `orders.discount`).
- **Retrato:** o pedido copia nome, preço e adicionais do momento da venda. Mudar o cardápio depois não altera o histórico.
- **Número do pedido:** sequência do schema (`order_number_seq`), então cada estabelecimento começa do 1.
- **Painel** (`/pedidos`, permissão `orders.view`): colunas Recebido → Em preparo → Pronto → Saiu para entrega → Concluído. Atualiza a cada 10 s (`wire:poll`), avisa pedido novo (título da aba e som opcional) e registra cada mudança em `order_status_history`. Avançar exige `orders.manage`; cancelar exige `orders.cancel` e motivo. Concluir registra o pagamento como recebido.
- **Venda no balcão** (`/balcao`, permissão `pos.use`): busca por nome ou código, adicionais, balcão/retirada/entrega por telefone. No balcão, o pagamento é registrado na hora.
- **Histórico** (`/pedidos/historico`): por dia, status, número, nome ou telefone. O detalhe do pedido tem impressão.
- **Configurações:** liga/desliga pedidos online e escolhe as formas de pagamento aceitas.
- **Cupons de desconto** (Cardápio → Cupons, `/cupons`, permissão `menu.manage`; módulo `App\Modules\Promotions`):
  - **Tipos:** porcentagem (com teto opcional), valor fixo ou entrega grátis (desconta a taxa; o entregador continua com ela nos relatórios). O desconto incide sobre os itens e nunca passa deles.
  - **Regras:** pedido mínimo (sobre os itens), início e fim (dias inteiros no fuso do restaurante), usos no total, usos por cliente (pelo telefone; padrão 1) e "só no primeiro pedido". Pedido cancelado devolve o uso. Tudo é conferido em `CouponRules` e de novo em `PlaceOrder` com o cupom travado (`FOR UPDATE`), então dois pedidos ao mesmo tempo não passam do limite.
  - **Checkout:** campo "Cupom de desconto" com "Aplicar": a prévia (`POST .../cardapio/pedido/cupom`, limite de 20 por minuto contra tentativa de códigos) diz se vale e o desconto é recalculado na tela conforme o carrinho, a entrega e o mínimo. O valor final é sempre o do servidor. Só no cardápio online; o balcão continua com o desconto manual.
  - **Divulgação:** "Divulgar no cardápio" mostra o cupom no topo do cardápio com o botão "Usar" (o checkout aplica sozinho). "Volte e ganhe" oferece o cupom na página do pedido entregue, com "Copiar" e "Pedir de novo", para quem ainda pode usar.
  - O pedido guarda o código (`orders.coupon_code`) e cada uso fica em `coupon_redemptions` (desconto e telefone). O código aparece no pedido do painel, no cupom impresso, na página do cliente e nos descontos dos relatórios. A tela de cupons mostra usos, clientes, desconto dado e vendas de cada um. Excluir libera o código e mantém o histórico.
- Fica para fases seguintes: pagamento online.

## Mesas, PDV e caixa

- **Mesas** (`/mesas`, permissão `tables.manage`): mapa do salão (livre / ocupada com valor / conta pedida), atualizado a cada 15 s. Cadastro em `/mesas/cadastro`, inclusive em lote ("1-20").
- **Comanda** (`/comandas/<id>`): de uma mesa ou avulsa ("Cartão 15"). Cada lançamento vira um pedido de mesa (`orders.command_id`) e aparece no painel da cozinha. Dá para pedir a conta, reabrir, trocar de mesa, juntar comandas e cancelar itens com motivo.
- **Fechamento:** taxa de serviço configurável (padrão 10%; tirar exige `orders.discount`), desconto, valor por pessoa e pagamento dividido em várias formas. Só dinheiro pode passar do total (troco). O fechamento trava a comanda (`SELECT ... FOR UPDATE`) e conclui os itens que ainda estavam na cozinha.
- **Caixa** (`/caixa`, permissão `cash.operate`): abertura com fundo de troco, sangria e suprimento com motivo, e fechamento com conferência por forma de pagamento, mostrando sobra ou falta. Há relatório imprimível. Um caixa físico só tem uma sessão aberta, e cada operador só abre um caixa (índices únicos parciais). A gerência (`cash.manage`) vê todos em `/caixa/sessoes`, cadastra caixas e pode fechar o caixa de outro.
- **Livro do caixa** (`cash_movements`): tudo entra aqui.
  - Venda de balcão: exige caixa aberto e entra no caixa do operador.
  - Entrega/retirada concluída: entra no caixa aberto (o do operador ou o mais recente).
  - Fechamento de comanda: entra no caixa de quem fechou.
  - Cancelamento de venda já paga: gera estorno.
- **PDV** (`/balcao`): `/` foca a busca, Enter adiciona o primeiro resultado (código exato tem prioridade). Mostra as contas pedidas aguardando pagamento.

## Estoque

- **Insumos** (`/estoque`, permissão `stock.manage`): unidade (`un`, `kg`, `g`, `l`, `ml`), estoque mínimo, custo médio e valor em estoque. Na mesma tela: entrada (quantidade + valor pago, que recalcula o custo médio ponderado), perda com motivo e contagem (lança a diferença como ajuste).
- **O saldo nunca é editado direto:** cada mudança grava uma linha em `stock_movements` e atualiza o saldo materializado na mesma transação, com o insumo travado (`SELECT ... FOR UPDATE`). Isso fica num único ponto, `App\Modules\Stock\StockLedger`. As quantidades são `numeric(14,3)` e as contas usam bcmath (`App\Support\Quantity`).
- **Ficha técnica** (`/estoque/fichas`): o que um produto ou adicional consome, em qualquer unidade compatível (ex.: 200 g de um insumo em kg). Combos usam a ficha de cada produto. A visão geral mostra custo, preço e margem de cada produto.
- **Baixa automática:** quando o pedido entra em preparo (ou é concluído no fechamento da comanda), uma vez só (`orders.stock_deducted_at`). Cancelado antes disso não mexe; depois, os insumos viram perda. Estoque negativo é permitido (a venda não trava) e o insumo fica em alerta. Em Configurações há a opção de esgotar o produto quando um insumo da ficha zerar.
- **Consumo** (`/estoque/consumo`): entradas, vendas, perdas e ajustes por período, com o custo do que saiu. Insumos abaixo do mínimo aparecem no Início.
- **Demonstração:** `docker compose exec app php artisan tenants:seed --tenant=empresa1 --class="Database\Seeders\Tenant\DemoStockSeeder"` (aditivo: não mexe no que já existe).

## Clientes, financeiro e relatórios

- **Clientes** (`/clientes`, `customers.manage`): lista com total gasto e ticket médio; ficha com endereços, observações (aparecem no PDV) e pedidos. No PDV, o telefone preenche nome e endereço. "Apagar dados pessoais" (LGPD) anonimiza o cliente e os pedidos dele, mantendo os valores.
- **Financeiro** (`/financeiro`; `finance.view` para ver, `finance.manage` para lançar): contas (caixa da loja, banco/Pix, maquininha), contas a pagar e a receber com categoria, vencimento, baixa e repetição mensal, saldos, vencidos e próximos 7 dias.
  - Fechamento do caixa: cada forma de pagamento vira um recebimento já baixado na conta ligada a ela (configurável em `/financeiro/contas`). Não duplica.
  - Entrada de estoque: opção "gerar conta a pagar".
- **Relatórios** (`/relatorios`, `reports.view`): vendas por dia e canal, recebido por forma de pagamento, produtos mais vendidos com custo e margem pela ficha (combos inclusive), horários (pedidos e vendas por hora, mapa de calor dia da semana × hora), cancelamentos, descontos e taxa retirada (por quem), caixas fechados. Exportação CSV (`;`, abre no Excel). "Venda" = pedido online/balcão não cancelado + conta de mesa fechada, sem contar duas vezes os lançamentos de mesa. As consultas vão direto no schema do tenant (`App\Modules\Reports\SalesReport`).
  - **Tempos e entregas** (`App\Modules\Reports\OperationsReport`, pelo histórico de status): tempo médio de aceite do pedido online (chegou → Em preparo), de preparo (Em preparo → Pronto), na rua (Saiu → Entregue) e até o cliente (pedido → concluído), no geral, por tipo de pedido e preparo por hora do dia. Por entregador: entregas, taxas, valor dos pedidos, tempo médio na rua e até o cliente e quantas passaram de 45 min na rua (CSV). Intervalos acima de 4 horas (pedido esquecido aberto) ficam fora das médias.
- **Início:** painel do dia conforme o perfil: vendas, pedidos e ticket médio contra o mesmo dia da semana passada, canais, recebido, pedidos em andamento, mesas ocupadas, contas vencendo e estoque baixo.

## Garçom e entregador no celular ("modo app")

- Quem só tem mesas e/ou entregas (perfis Garçom e Entregador, ou um perfil próprio sem nada além de pedidos, mesas e entregas) usa o painel como um app de celular: topo simples, abas grandes embaixo e nada de barra lateral. Ao entrar, vai direto para Mesas (garçom) ou Entregas (entregador). Dono, gerência, caixa e cozinha seguem no painel completo. A regra é `User::usesFieldMode()`.
- **Garçom:** mesas em 3 colunas com filtro (livres, ocupadas, conta pedida); abrir mesa com botões − / + de pessoas; na comanda, produtos em grade com a quantidade já escolhida, barra "Ver pedido · Enviar" embaixo e "Pedir conta / Lançar itens" ao alcance do polegar. Aba **Prontos** (`/mesas/prontos`): pedidos de mesa que a cozinha marcou como prontos, com o botão "Servido".
- **Entregador:** abas "Para pegar" e "Comigo", valor a cobrar e troco em destaque, botões grandes de mapa, ligar e WhatsApp, "Entregue e recebi" e o resumo do dia.
- **Dois funcionários no mesmo pedido:** toda ação (pegar entrega, "Vou levar", "Servido", "Entregue", trocar entregador) é decidida com a linha do pedido travada no banco (`SELECT ... FOR UPDATE`). Só o primeiro leva; o outro recebe "Fulano já pegou o pedido #N" e o pedido some da tela dele. Toque duplo conta como um só (o pagamento na entrega entra no caixa uma vez). Pegar a entrega grava entregador, hora e status num passo só (`ChangeOrderStatus` aceita `guard` e `attributes` dentro da trava).
- **Garçom reserva antes de buscar:** em "Prontos", "Vou levar" reserva o prato (os outros veem "Com Fulano" e a aba deles não conta esse prato); só quem reservou marca "Servido" ou solta ("Não vou mais levar"). Quem usa o painel completo (gerência) pode liberar ou concluir a reserva de outro. Regras em `App\Modules\Tables\ServeQueue`.
- As abas mostram quantos pedidos estão prontos e o celular vibra quando chega um novo (abas a cada 10 s; telas de Prontos e Entregas a cada 5 s, e na hora depois de cada ação). No celular, os campos usam letra de 16 px para o iPhone não dar zoom, e a busca de produtos não abre o teclado sozinha.

## App no celular e notificações

- **App instalável por restaurante:** cada estabelecimento tem o próprio manifesto (`/manifest.webmanifest`, com o nome fantasia) e ícones (`/app-icon/{192,512,maskable,apple,badge}.png`). Com logo, o ícone é o logo em fundo branco; sem logo, uma cloche na cor da marca. Em **Minha conta → App no celular**: botão "Instalar app" (Android/Chrome) ou o passo a passo do iPhone (Compartilhar → Adicionar à Tela de Início). Dá para instalar já pela tela de login.
- **Notificações (Web Push):**
  - **Entregador** (modo app): "Entrega pronta · #N" (bairro, cliente e quanto cobrar) quando a cozinha marca uma entrega como pronta, e "Entrega para você · #N" quando a gerência escolhe ou troca o entregador.
  - **Garçom** (modo app): "Mesa 1 · pedido pronto" com os itens quando um pedido de mesa fica pronto.
  - **Cozinha, caixa e gerência** (painel, quem tem "Criar e atualizar pedidos"): "Pedido novo · #N · R$ 54,00" (entrega/retirada, bairro, cliente e itens) quando chega pedido do cardápio online. Pedido lançado pela equipe (mesa, balcão) não avisa. No computador, a notificação fica na tela até alguém clicar.
  - **Gerência** (quem pode cancelar pedidos): "Pedido #N esperando há 5 min" se ninguém aceitou o pedido online no prazo de **Configurações → Cardápio online** (padrão 5 minutos; 0 desliga). Substitui o aviso de pedido novo daquele pedido.
  - Tocar na notificação abre a tela certa (Entregas, Prontos ou o pedido). Ao abrir o app, as notificações dele saem da bandeja.
- **Ativar:** aviso no topo do modo app e do painel ("Ativar") ou em Minha conta, onde também há "Enviar teste" e "Desativar neste celular". Quem usa o painel escolhe ali quais avisos quer (pedido novo, pedido sem aceite), valendo para todos os aparelhos dele (`users.alert_preferences`). Cada aparelho é uma inscrição (`push_subscriptions`, no schema do estabelecimento) ligada a quem está logado: outra pessoa entrando no mesmo celular passa a receber os avisos dela, e sair da conta desliga o aparelho. Celular que desinstalou o app sai da lista no próximo envio.
- **Alarme no painel** (`App\Livewire\Tenant\OrderAlarm` + `resources/js/alarm.js`): enquanto houver pedido online esperando aceite, aparece em qualquer tela do painel um cartão com os pedidos e o botão **Aceitar** (inicia o preparo; dois cliques juntos aceitam uma vez só), o título da aba mostra a contagem ("(2) Início") e um som toca a cada 8 s até alguém aceitar. "Silenciar" para o som até chegar outro pedido. No painel de pedidos o cartão some (o pedido está em Recebido, com o botão "Aceitar e preparar"), mas o som continua. O som é ligado ou desligado por aparelho, no cartão ou no botão "Som ligado" do painel de pedidos, e o navegador só libera depois do primeiro clique na página (o cartão avisa). Pedido novo de mesa ou balcão dá um bipe curto no painel de pedidos.
- **Como funciona:** os eventos do pedido (`OrderPlaced`, `OrderStatusChanged`, `CourierAssigned`) chamam `App\Modules\Push\StaffAlerts`, que enfileira `SendPush` (sai depois do commit, pela fila, dentro do estabelecimento). O aviso de pedido sem aceite é o job `WarnWaitingOrder`, agendado com atraso na chegada do pedido; ele confere de novo o status e o tempo antes de avisar. A biblioteca é `minishlink/web-push`. Só são aceitos endereços dos serviços de push conhecidos (Google, Mozilla, Apple, Microsoft), com HTTPS. Falha no envio nunca atrapalha o pedido.
- **Configuração:** gere as chaves uma vez com `php artisan push:vapid` e coloque `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` e `VAPID_SUBJECT` (`mailto:` seu e-mail) no `.env`. Trocar as chaves invalida as inscrições: cada celular precisa ativar de novo. Nos testes, `PUSH_DRIVER=fake` guarda os envios em memória.
- **Limites:** exige HTTPS (em `localhost` o navegador libera para desenvolvimento). No iPhone, só com iOS 16.4+ e o app adicionado à Tela de Início. Notificação bloqueada no celular só volta pelas configurações dele.

## Impressão, entregas e WhatsApp

- **Impressão na cozinha** (Administração → Impressoras, `/impressoras`): cadastre os setores (Cozinha, Bar, Expedição) e diga em qual setor sai cada categoria. Todo pedido novo (online, balcão ou lançamento de mesa) vira um cupom por setor, só com os itens dele (combos são separados por componente). Itens de categoria sem setor vão para o setor padrão. Setor marcado como "pedido completo" recebe o pedido inteiro com valores, troco e endereço (expedição/caixa). Há reimpressão no painel de pedidos e na página do pedido.
  - **Estação no navegador** (`/impressao`): deixe aberta no computador da impressora, marque os setores e clique em Começar. Ela pega os cupons (nenhuma outra estação pega o mesmo) e imprime em 80 ou 58 mm. Para imprimir sem a janela de impressão, abra o Chrome com `--kiosk-printing` e a térmica como impressora padrão.
  - **Impressora de rede (ESC/POS, porta 9100):** só para instalação na rede do restaurante (`PRINTING_NETWORK_ENABLED=true`). Na nuvem fica desligada: o servidor não alcança a rede da loja. Aceita só as portas 9100-9109.
- **Entregas** (`/entregas`, permissão "Fazer entregas", dada a Entregador e Gerente): pedidos prontos para sair, "Pegar entrega" (o primeiro que tocar leva), mapa de cada endereço, rota no Google Maps com todas as paradas, ligar/WhatsApp para o cliente e "Entregue" (o pagamento na entrega entra no caixa aberto). A gerência escolhe ou troca o entregador e vê as entregas e as taxas do dia por entregador. O painel de pedidos mostra o entregador.
  - **Taxa por bairro** (`/entregas/bairros`): valor único (Configurações) ou por bairro, com pedido mínimo e tempo por bairro. Por bairro, o checkout mostra a lista e recusa bairro fora da área; o nome é comparado sem acento e sem diferenciar maiúsculas.
- **WhatsApp** (Administração → WhatsApp, `/whatsapp`): avisos de pedido recebido, em preparo, pronto para retirada, saiu para entrega, entregue e cancelado, cada um ligável e com texto editável (`{nome}`, `{numero}`, `{estabelecimento}`, `{total}`, `{link}`, `{motivo}`). Pedido de mesa e pedido sem telefone não recebem aviso. O mesmo aviso nunca sai duas vezes.
  - **Manual** (padrão, grátis): botão "Avisar no WhatsApp" na página do pedido e nas entregas, com a mensagem pronta.
  - **Evolution API:** envio automático pelo seu número (QR code). O endereço precisa ser https público.
  - **Cloud API (Meta):** oficial. Mensagem iniciada pela empresa exige um modelo aprovado com uma variável (ex.: "Atualização do seu pedido: {{1}}"). O texto do aviso vai nela.
  - Credenciais ficam criptografadas. Os envios saem pela fila (`queue`) e aparecem em "Últimos avisos".

## Produção

Passo a passo completo (servidor, DNS, `.env`, primeira subida, backups, monitoramento) em **[docs/PRODUCAO.md](docs/PRODUCAO.md)**.

- **Imagens** (`docker/production/Dockerfile`): `app` (PHP-FPM com opcache, vendor sem dev e assets compilados; também roda a fila e o agendador) e `web` (Caddy). `docker-compose.production.yml` sobe app, fila, agendador, web, PostgreSQL e Redis, com reinício automático e sem portas de banco abertas.
- **HTTPS automático:** o Caddy emite certificado na primeira visita a cada host. Antes, pergunta ao app (`/caddy/ask`, protegido por `CADDY_ASK_TOKEN`) se o domínio é do sistema: domínio central, admin, subdomínio de estabelecimento ou domínio próprio verificado. Basta um registro DNS curinga (`*.seudominio.com.br`).
- **Deploy:** `./scripts/deploy.sh` (código, imagens, manutenção, migrations da plataforma e de todos os estabelecimentos, papéis, containers e conferência de `/saude`). O GitHub Actions (`.github/workflows/ci.yml`) roda os testes a cada push e, na `main`, faz o deploy por SSH quando os segredos `DEPLOY_*` existem.
- **Saúde:** `/saude` (qualquer host) responde 200 com banco, Redis e disco em ordem; 503 se algo falhar.
- **Backups fora do servidor:** com `BACKUPS_OFFSITE_DISK=s3` (S3, Backblaze B2, Cloudflare R2, Wasabi...), cada backup de estabelecimento vai também para o bucket; restaurar busca de lá se a cópia local sumiu. `platform:backup-database` (02:30) salva o banco inteiro, mantém os 14 mais recentes e também vai para o bucket.
- **Erros:** `SENTRY_LARAVEL_DSN` liga o Sentry; cada erro chega marcado com o estabelecimento.
- **Entrar como suporte** (admin → estabelecimento): escolhe o usuário e o motivo; um link de uso único (2 minutos) abre o sistema do estabelecimento logado como ele. Uma faixa avisa o modo suporte; cada ação na auditoria leva `via_suporte`; entrada e saída ficam registradas no estabelecimento e na plataforma.
- **Domínio próprio** (`/dominio`, planos Profissional e Completo): o dono cadastra, cria no DNS o CNAME (para o subdomínio dele) e o TXT `_saas-verify.<domínio>` com o código, e verifica. Só domínio verificado abre o sistema e recebe certificado; pode virar o endereço principal (links de e-mail e WhatsApp).
- **Papel do PostgreSQL por estabelecimento** (`TENANCY_DATABASE_ROLES=true`): cada schema pertence a um papel próprio, e a conexão faz `SET ROLE` ao ativar o tenant. Mesmo uma consulta com outro schema escrito por extenso é recusada pelo banco, assim como as tabelas da plataforma. `tenants:roles` cria/ajusta os papéis (roda no deploy). O usuário da aplicação precisa de `CREATEROLE` (já no `docker/postgres/init`; num banco existente: `ALTER ROLE saas CREATEROLE` e `GRANT USAGE ON SCHEMA extensions TO PUBLIC` como superusuário). A suíte de testes passa com os papéis ligados e desligados.

## Operação da plataforma

- **Backups** (`public.tenant_backups`, disco `backups` em `storage/app/backups/<tenant>/`): `pg_dump` só do schema do estabelecimento + `tar.gz` das fotos e do logo + `manifest.json`. Diário às 03:00. Retenção: 7 diários, o de domingo das últimas 4 semanas, e os demais (manual, "antes da restauração", final) por 30 dias. No admin da plataforma (página do estabelecimento): "Fazer backup agora", baixar (.zip) e restaurar.
- **Restauração:** salva o estado atual como backup "antes da restauração" (dá para desfazer), deixa o app em manutenção (503), tira o schema atual do caminho, restaura o dump e os arquivos e roda as migrations pendentes. Se o `pg_restore` falhar, o schema original volta e nada muda.
- **Exclusão com carência:** "Agendar exclusão" (digitando o slug) bloqueia o acesso na hora ("Conta em exclusão") e avisa o dono por e-mail. Até a data (30 dias) dá para cancelar. Depois, `tenants:purge-deleted` faz um backup final e apaga schema, arquivos e domínios.
- **Site e cadastro** (`http://localhost:8080`): página com os planos e `/cadastro`. O restaurante escolhe o endereço (verificado enquanto digita), cria o dono com senha e entra com 14 dias grátis. Proteções: campo escondido contra robôs, limite de cadastros por IP e slugs reservados.
- **Planos** (`database/seeders/Central/CentralDatabaseSeeder.php`): Essencial, Profissional e Completo, com limites de usuários, mesas, produtos e pedidos/mês e recursos (estoque, financeiro). Passar do limite bloqueia o cadastro com aviso; recurso fora do plano aparece com cadeado para o dono. Sem plano = cortesia (estabelecimentos antigos, como empresa1 e empresa2): sem limites e sem cobrança.
- **Assinatura** (`/assinatura`, só o dono): plano, uso dos limites, troca de plano (não deixa ir para um plano menor que o uso), faturas e cancelamento. Durante o teste, a primeira cobrança vence no fim dele.
- **Cobrança:** teste grátis → ativa (fatura paga) → pagamento pendente (fatura vencida ou teste acabou) → suspensa depois de 7 dias. Pagou, reativa sozinha. Cancelada: acesso até o fim do período pago. A tela de conta suspensa mostra "Pagar fatura".
  - `BILLING_DRIVER=fake` (padrão): nada sai da máquina; o link da fatura abre uma página com "Simular pagamento".
  - `BILLING_DRIVER=asaas`: configure `ASAAS_API_KEY` (sandbox por padrão) e `ASAAS_WEBHOOK_TOKEN`, e cadastre no Asaas o webhook `https://<seu-domínio>/webhooks/billing/asaas`. O cliente paga por Pix, boleto ou cartão na fatura do Asaas. Webhooks repetidos são ignorados (`billing_events`).
- **Admin da plataforma → Assinaturas:** receita recorrente (MRR), recebido no mês, em aberto, estabelecimentos por situação, faturas e planos. Na página do estabelecimento: trocar plano, +7 dias de teste e cortesia.

## Interface

- **Design system** em `resources/css/app.css`: cor da marca (`brand-*`), fonte Inter, sombras, e as classes `card`, `btn-*`, `input`, `badge-*`, `table`, `alert-*`, `tab`, `chip` e `stat-*`. Mudar ali muda o sistema inteiro.
- **Componentes:** `<x-icon name="..."/>` (ícones de traço, lista em `components/icon.blade.php`), `<x-ui.page-header>`, `<x-ui.stat>` e `<x-ui.empty>`.
- **Layouts:** `layouts.app` (painel com barra lateral; gaveta no celular), `layouts.public` (casca do app do cardápio), `layouts.guest` (login) e `layouts.platform` (admin). Telas de operação (cozinha, PDV, mesas) usam a largura toda com `#[Layout('components.layouts.app', ['wide' => true])]`.
- **Cozinha** (`/pedidos`): o cartão muda de cor com o tempo de espera (10 e 20 minutos). Tem tela cheia para TV ou tablet e aviso sonoro de pedido novo (o pedido online toca até ser aceito; veja "Alarme no painel").

## Comandos

| Comando | O que faz |
|---|---|
| `central:migrate [--fresh] [--seed]` | migra o schema `public` (plataforma) |
| `tenants:create {slug} {nome} [--owner-email=] [--owner-name=] [--domain=] [--document=]` | provisiona schema, migrations, seed, dados iniciais e dono |
| `tenants:invite-user {slug} {email} {nome} [--role=dono]` | cria usuário no estabelecimento e envia o convite |
| `tenants:migrate [--tenant=slug] [--pretend]` | migra todos os tenants ativos e suspensos, ou um só |
| `tenants:rollback [--tenant=slug] [--step=1]` | desfaz migrations de tenant |
| `tenants:seed [--tenant=slug] [--class=]` | roda seeders de tenant (idempotente) |
| `tenants:status` | status, schema, versão e migrations pendentes de cada tenant |
| `tenants:roles [--tenant=slug]` | cria/ajusta o papel do PostgreSQL de cada estabelecimento |
| `platform:backup-database` | backup do banco inteiro (02:30), com cópia no bucket se configurado |
| `platform:create-user {email} {nome} [--role=admin]` | cria usuário da equipe do SaaS |
| `tenants:backup [--tenant=slug] [--kind=manual]` | backup do schema e dos arquivos (todo dia às 03:00 com `--kind=daily`) |
| `tenants:restore {slug} [--backup=id] [--force]` | sem `--backup`, lista os backups; com ele, restaura |
| `tenants:prune-backups` | apaga backups fora da retenção (04:30) |
| `tenants:purge-deleted` | remove de vez quem passou da carência de exclusão (05:00) |
| `billing:check` | fim de teste, inadimplência, suspensão e reativação (06:00) |

Ordem do deploy: `central:migrate`, `tenants:migrate`, `tenants:seed`. Se a migration falhar em um tenant, os outros seguem, a falha vai para `tenant_events` e o comando termina com erro.

## Testes

```bash
docker compose exec app php artisan test
```

Os testes rodam contra PostgreSQL e Redis reais (banco `saas_test`, Redis DB 14/15). A cada execução, o `public` é recriado e os tenants `teste1` e `teste2` são provisionados.

## Acesso e permissões

- **Usuários** vivem na tabela `users` do schema do tenant. O mesmo e-mail em dois estabelecimentos são contas independentes. Ninguém digita a senha de outra pessoa: usuário novo recebe um convite por e-mail.
- **Permissões** são o enum `App\Modules\Access\Permission`. **Perfis** são dados do tenant que agrupam permissões; os padrões são Dono, Gerente, Caixa, Garçom, Cozinha e Entregador, e o dono pode criar outros. A checagem usa `Gate::before`, então rotas usam `can:users.manage`, o Livewire usa `$this->authorize('users.manage')` e as views usam `@can`.
- **Contra escalada de privilégio:** só um dono concede o perfil de dono ou edita outro dono. Ninguém concede um perfil ou permissão que não tem. Sempre resta pelo menos um dono ativo.
- **Desativar** um usuário derruba a sessão dele na próxima requisição. Trocar a senha encerra as outras sessões (`AuthenticateSession`).
- **Login:** rate limit por tenant + e-mail + IP. Tokens de redefinição ficam no schema do tenant, e um token de A não vale em B.
- **Auditoria:** `audit_logs` registra login, logout, senhas, usuários, perfis, estabelecimento, horários e configurações, e aparece em `/auditoria`.
- **Admin da plataforma:** guard `platform` (tabela `public.platform_users`), só em `admin.localhost`, com 2FA TOTP obrigatório. No host de um tenant, o guard é sempre o `web`; no admin, sempre o `platform`.

## Como a tenancy funciona

- **Conexões:** `central` sempre em `public`; `tenant` é a padrão e, sem tenant ativo, aponta para `tenant_none`, um schema que não existe. Assim, qualquer query sem tenant falha.
- **search_path do tenant:** `"<schema>,extensions"`, nunca `public`. Só `App\Tenancy\SchemaManager` mexe nele (há um teste de arquitetura que garante isso).
- **Schema:** o nome é `tenant_<ulid>`, gerado pelo sistema e imutável. Ele vem sempre de `public.tenants`, nunca da URL, e é validado por `/^tenant_[a-z0-9]{6,40}$/`.
- **Requisição:** `IdentifyTenant` resolve o tenant pelo host (`tenant_domains`) ou pelo slug (`/cardapio/{slug}`). Depois, `EnsureTenantIsActive` bloqueia suspenso (403) e provisioning/excluído (404) e ativa o contexto. Só então a sessão inicia. `EnsureSessionBelongsToTenant` derruba sessões de outro tenant. No `terminate`, o estado fechado volta.
- **Contexto auxiliar:** o prefixo de cache vira `tenant:<id>:`, o disco `tenant` aponta para `storage/app/tenants/<id>` (logo do estabelecimento, uploads do Livewire) e os logs ganham `tenant_id`. A sessão usa um store próprio com prefixo fixo e fica isolada pelo cookie por host.
- **Jobs:** use o trait `App\Tenancy\Jobs\TenantAware`. O id do tenant vai no payload, e o worker reativa esse tenant e reseta no fim. Despachar sem tenant ativo é erro.
- **Livewire:** `/livewire/update`, upload e preview passam pelo grupo `tenant`. No `admin.localhost`, existe uma rota de update própria no grupo `platform`, registrada antes da do tenant.
- **Role do PostgreSQL por tenant:** está preparado (`config('tenancy.database_roles')`, ponto marcado em `SchemaManager::switchTo`), mas não ativado.

Estrutura: `app/Tenancy` (camada de tenancy), `app/Platform` (admin da plataforma, onboarding), `app/Modules/{Access,Establishment,Menu,Orders,Customers,Tables,Cash,Stock,Finance,Reports}` (negócio; não pode usar `Platform`), `app/Support` (Money, Quantity), `app/Livewire/{Tenant,Platform}` (telas), `database/migrations/{central,tenant}`.
