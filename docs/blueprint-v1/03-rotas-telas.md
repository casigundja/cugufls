# Rotas e estrutura das telas

Todas as rotas abaixo são contratos planeados. A aplicação atual só tem a homepage inicial. IDs administrativos são numéricos; slugs públicos são resolvidos apenas entre registos publicados/ativos. Rotas estáticas precedem qualquer rota dinâmica. Não criar catch-all de segmento que capture /admin, /api ou /contacto.

## Website

| Método e URI | Nome | Página / ação |
|---|---|---|
| GET / | home | hero, Farmácia, destaques, filiais, quatro segmentos, novidades |
| GET /sobre | about | história, missão, equipa |
| GET /segmentos | segments.index | segmentos ativos |
| GET /farmacia | pharmacy.home | categorias, produtos, serviços, novidades, locais |
| GET /farmacia/luanda | pharmacy.luanda | filial associada ao segmento Farmácia |
| GET /farmacia/bailundo | pharmacy.bailundo | filial associada ao segmento Farmácia |
| GET /farmacia/produtos | pharmacy.products | catálogo filtrado por segmento |
| GET /farmacia/servicos | pharmacy.services | serviços de farmácia |
| GET /farmacia/contacto | pharmacy.contact | contactos das filiais |
| GET /comercial | commercial.home | soluções e produtos empresariais |
| GET /comercial/produtos | commercial.products | catálogo comercial |
| GET /comercial/servicos | commercial.services | serviços empresariais |
| GET /comercial/negomil-erp | commercial.erp | apresentação + pedido de demonstração |
| GET /timbragem | printing.home | produtos personalizados e serviços |
| GET /timbragem/produtos | printing.products | catálogo de timbragem |
| GET /timbragem/servicos | printing.services | serviços |
| GET /timbragem/tipografia | printing.typography | página editorial |
| GET /timbragem/encomendas | printing.orders.create | formulário de encomenda/orçamento |
| GET /lubrificantes | lubricants.home | produtos e categorias |
| GET /lubrificantes/produtos | lubricants.products | catálogo do segmento |
| GET /lubrificantes/categorias | lubricants.categories | categorias de lubrificantes |
| GET /lubrificantes/contacto | lubricants.contact | contactos |
| GET /produtos | products.index | catálogo global |
| GET /produtos/{product:slug} | products.show | detalhe canónico e seleção de filial |
| GET /servicos/{service:slug} | services.show | detalhe e orçamento |
| GET /novidades | posts.index | notícias publicadas |
| GET /novidades/{post:slug} | posts.show | artigo |
| GET /contacto | contacts.create | formulário geral |
| POST /contacto | contacts.store | Contact; general/demo/quote, rate limit |
| POST /pedidos | orders.store | CreateOrder; CSRF, rate limit, idempotência |
| GET /pedidos/confirmacao | orders.confirmation | recibo mínimo associado à sessão; sem busca pública por ID |
| GET /paginas/{page:slug} | pages.show | páginas CMS, incluindo privacidade |
| GET /sitemap.xml | sitemap | apenas URLs publicadas |
| GET /robots.txt | robots | sitemap e orientação de crawlers |

Luanda/Bailundo são URLs editoriais estáveis ligadas à filial por configuração validada. Para futuras filiais: GET /farmacia/localizacoes/{branch:slug}, verificando associação ao segmento. Novos segmentos usam GET /segmentos/{segment:slug}, /segmentos/{segment:slug}/produtos e /segmentos/{segment:slug}/servicos; não exigem nova tabela nem novo layout.

Filtros públicos: q, category, brand, branch, availability, sort e page; listas permitidas de ordenação. Na página de segmento, o filtro não pode trocá-lo por outro segmento. Links de detalhes usam a URL canónica global para evitar duplicação SEO.

## Autenticação planeada

| Método e URI | Finalidade |
|---|---|
| GET /login; POST /login | sessão administrativa e limitação de tentativas |
| POST /logout | encerrar sessão com CSRF |
| GET /forgot-password; POST /forgot-password | recuperação sem revelar se email existe |
| GET /reset-password/{token}; POST /reset-password | redefinição |
| GET /email/verify | aviso de verificação |
| GET /email/verify/{id}/{hash} | link assinado e limitado |
| POST /email/verification-notification | reenviar verificação com limite |
| GET /two-factor-challenge; POST /two-factor-challenge | desafio 2FA do starter kit |
| GET /user/confirm-password; POST /user/confirm-password | reconfirmar operações sensíveis |
| GET /user/confirmed-password-status | estado de reconfirmação |
| POST /user/two-factor-authentication | iniciar 2FA |
| POST /user/confirmed-two-factor-authentication | confirmar configuração |
| DELETE /user/two-factor-authentication | desativar após reconfirmação |
| GET /user/two-factor-qr-code; GET /user/two-factor-secret-key | configuração privada 2FA |
| GET /user/two-factor-recovery-codes; POST /user/two-factor-recovery-codes | consultar/regenerar códigos |
| PUT /user/profile-information; PUT /user/password | perfil e password próprios |

Não existe registo público administrativo. Nomes e rotas efetivos do starter kit devem ser confrontados com route:list na fase 01; desativar features não utilizadas. Require 2FA para perfis privilegiados antes do lançamento.

## Administração

Prefixo `/admin`, nomes `admin.*`, middleware sessão web + auth + verified + active + 2FA conforme perfil; Policies em todas as ações. GET /admin redireciona para /admin/dashboard.

Para cada recurso CRUD da tabela: GET /{recurso} (index), GET /{recurso}/criar (create), POST /{recurso} (store), GET /{recurso}/{id} (show), GET /{recurso}/{id}/editar (edit), PATCH /{recurso}/{id} (update), DELETE /{recurso}/{id} (destroy). Destroy só elimina rascunhos sem dependências; cadastros usados são inativados via PATCH. Os verbos fazem parte do contrato; não usar GET para mutação.

| Grupo | Recursos CRUD | Permissão base | Tela Vue |
|---|---|---|---|
| Catálogo | produtos, categorias, marcas | products / categories / brands | Catalog/{Resource}/{Index,Show,Form} |
| Negócio | clientes | customers (sem DELETE) | Business/Customers/{Index,Show,Form} |
| Empresa | segmentos, filiais, servicos, equipa | segments / branches / services / team | Company/{Resource}/{Index,Show,Form} |
| Website | banners, noticias, paginas, depoimentos, faq | content | Website/{Resource}/{Index,Show,Form} |
| Sistema | utilizadores | users (sem DELETE) | System/Users/{Index,Show,Form} |
| Sistema | perfis | roles.manage | System/Roles/{Index,Show,Form} |

Permissões atómicas de CRUD: view/create/update/delete. Permissões CMS usam content.view/create/update/delete; publicar exige content.publish adicional. Perfis não removem papéis internos nem o último acesso super-admin.

| Método e URI relativa a /admin | Ação / permissão |
|---|---|
| GET /dashboard | métricas filtradas; dashboard.view |
| GET /imagens | biblioteca filtrada; images.view |
| POST /imagens | upload para proprietário autorizado; images.create + permissão do proprietário |
| PATCH /imagens/{id}; DELETE /imagens/{id} | metadata/remover; images.update/delete + proprietário |
| GET /importacoes; GET /importacoes/criar; GET /importacoes/{id} | listar, enviar e acompanhar importação; products.import ou stock.import |
| POST /importacoes | guardar ficheiro privado e validar |
| POST /importacoes/{id}/confirmar | executar lote validado e autorizado |
| GET /importacoes/{id}/erros | descarregar relatório privado |
| GET /stock | tabela produto×filial; stock.view |
| POST /stock/ajustes | entrada, saída, ajuste/devolução; stock.update |
| PATCH /stock/{inventory}/limites | mínimo/máximo; stock.update |
| GET /movimentos | histórico imutável; movements.view |
| GET /transferencias; GET /transferencias/{id} | listar/detalhar; transfers.view |
| GET /transferencias/criar; POST /transferencias | rascunho; stock.transfer |
| PATCH /transferencias/{id} | editar rascunho; stock.transfer |
| POST /transferencias/{id}/expedir | debitar origem; transfers.dispatch |
| POST /transferencias/{id}/receber | creditar destino; transfers.receive |
| POST /transferencias/{id}/cancelar | apenas rascunho; stock.transfer |
| GET /alertas | baixo/zero/sem contagem/trânsito; alerts.view |
| GET /pedidos; GET /pedidos/{id} | lista e detalhe; orders.view |
| GET /pedidos/criar; POST /pedidos | criar; orders.create |
| PATCH /pedidos/{id} | responsável/notas; orders.update; preços/itens só em new/contacted |
| POST /pedidos/{id}/estado | transição; orders.update + orders.cancel para cancelamento |
| GET /contactos; GET /contactos/{id} | caixa de contactos; contacts.view |
| PATCH /contactos/{id} | estado/responsável; contacts.update |
| GET /homepage; PATCH /homepage | blocos CMS validados; content.view/update global |
| POST /{noticias\|paginas\|banners\|depoimentos\|faq}/{id}/publicar | publicação; content.publish no escopo |
| GET /relatorios/{tipo} | stock, movimentos, pedidos, produtos, segmentos, filiais; reports.{tipo em inglês} |
| POST /relatorios/{tipo}/exportar | mesma permissão; job com filtros autorizados |
| GET /exportacoes/{token} | URL assinada, auth e escopo revalidado |
| GET /permissoes | catálogo fixo; permissions.manage |
| PUT /perfis/{id}/permissoes | sincronizar allowlist; permissions.manage |
| PUT /utilizadores/{id}/acessos | concessões; users.assign_roles |
| POST /utilizadores/{id}/desativar | users.disable e proteção de privilégios |
| GET /logs; GET /logs/{id} | auditoria; logs.view |
| GET /configuracoes; PATCH /configuracoes | chaves permitidas; settings.view/update |
| GET /notificacoes | apenas notificações próprias |
| PATCH /notificacoes/{uuid}/ler | verificar proprietário |

`/{a|b}` representa expansão em rotas distintas, não regex a copiar diretamente. Imagens de CMS guardam paths nos respetivos registos; biblioteca é uma projeção desses recursos e de product_images, não uma tabela adicional. Upload requer owner_type em allowlist e owner_id autorizado; criação nova usa upload temporário vinculado à sessão e limpeza após 24h.

Não ativar `/admin/vendas` nem rotas de pagamento nesta versão. Relatórios de segmentos/filiais agregam pedidos e inventário, nunca vendas presumidas.

## Estrutura visual

```text
┌ CUGUFLS / FAMÍLIA GUNDJA ─── Segmento ▾  Filial ▾  Notificações  Perfil ┐
│ Dashboard         │ Período ▾                                         │
│ CATÁLOGO          │ Produtos     Stock baixo     Pedidos               │
│ INVENTÁRIO        │ [valor real] [ocorrências]    [no período]          │
│ NEGÓCIO           │                                                    │
│ EMPRESA           │ Pedidos por dia       Pedidos por segmento         │
│ WEBSITE           │ [gráfico temporal]    [percentagem + total]         │
│ SISTEMA           │                                                    │
│                   │ Produtos com stock baixo                          │
│                   │ Produto | Filial | Atual | Mínimo | Ação           │
└───────────────────┴────────────────────────────────────────────────────┘
```

Cada indicador informa definição/período. Produtos = distintos ativos no escopo (filial: associados via inventário). Pedidos = contagem de pedidos criados no período, com filtro de estado visível. Gráfico de segmentos = número de pedidos do segmento / total de pedidos filtrados; zero pedidos mostra estado vazio, não percentagens fictícias. Stock é posição atual e não muda retroativamente ao selecionar período. Valores monetários de pedidos, se exibidos, são identificados como valores cotados, não receita.

### Telas operacionais

| Tela | Campos / ações principais |
|---|---|
| Produtos / lista | pesquisa, segmento, categoria, marca, filial, ativo; nome/SKU, segmento, saldo autorizado, preço, estado, ações; paginação |
| Produto / formulário | informações, categoria/marca, descrição, preço/promo/custo com permissão, purchase_mode, unidade, imagens, publicado/destaque; aba inventário abre ação auditada separada |
| Categorias | árvore por segmento, pai, nome/slug, imagem, ordem; impedir ciclos |
| Stock | filial obrigatória para alteração, saldo/mínimo/máximo, entrada/saída/contagem; modal exige motivo e mostra antes/depois |
| Transferência | origem/destino, itens, quantidades, estado, expedir/receber conforme permissão; alerta de stock em trânsito |
| Pedido | referência, cliente, filial, itens/snapshots, orçamento, responsável, timeline, transições permitidas e link WhatsApp |
| Importação | template, upload, preview com erros por linha, confirmar, progresso e relatório |
| Filial | nome, localidade, endereço, contactos, horários estruturados por dia, mapa opcional, segmentos associados |
| Serviços | segmento, nome, descrição, preço opcional, imagem, ativo |
| Homepage | hero, CTAs, destaque Farmácia, filiais, produtos e novidades; preview antes de publicar |
| Conteúdo | título, slug, texto sanitizado, imagem/alt, segmento, estado, publicação, SEO |
| Utilizador | nome/email, ativo, perfil global ou concessões segmentadas, estado de verificação/2FA; não mostrar password |
| Relatórios | filtros, definição de métricas, tabela, exportação assíncrona e estado |

Responsivo: sidebar vira drawer, tabelas mantêm cabeçalhos e scroll horizontal, ações acessíveis por teclado, labels e erros associados aos campos, foco nos diálogos, contraste adequado. Todas as telas têm estados de carregamento, vazio, erro, sem permissão e sucesso. Formulários mantêm dados após erro e impedem dupla submissão. Valores de exemplo só aparecem em mocks identificados, nunca como fallback da API.
