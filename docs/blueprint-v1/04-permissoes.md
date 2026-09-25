# Matriz de perfis e autorização

## Regra de acesso

A decisão combina **utilizador ativo + permissão da operação + escopo da concessão + estado do recurso**. Esconder menu não autoriza acesso. Aplicar a mesma Policy em página, API, exportação, download, job e endpoints auxiliares.

Spatie fornece roles, permissions e a relação role_has_permissions. Na proposta, `model_has_roles` guarda somente perfis globais (`super-admin`, `admin`). Os perfis operacionais ficam em `user_accesses`, com FK para roles e escopo. Não atribuir um perfil operacional por HasRoles globalmente, pois isso perderia a ligação entre perfil e localização. Não usar permissões diretas em model_has_permissions nesta versão.

`user_accesses` representa uma concessão:

| segment_id | branch_id | Significado |
|---|---|---|
| preenchido | NULL | papel nesse segmento, em todas as suas filiais |
| preenchido | preenchido | papel apenas nesse segmento e nessa filial |
| NULL | NULL | inválido; papéis globais são atribuídos separadamente |
| NULL | preenchido | inválido; não conceder implicitamente todos os segmentos da filial |

Validar que branch_segment contém a associação. Proibir super-admin/admin em user_accesses. Ao atribuir/remover concessões, bloquear o User numa transação e verificar duplicado por igualdade incluindo NULL: índices únicos com colunas nullable não bastam para evitar duplicação no escopo de segmento.

O autorizador deve verificar a permissão **no role daquela concessão**, e testar o recurso contra o segmento/filial da mesma linha. Nunca combinar a permissão de uma linha com o escopo de outra. `Gate::before` pode permitir apenas super-admin ativo; para restantes utilizadores as Policies chamam o autorizador explícito. `user->can()` isolado não resolve as concessões contextuais.

Queries de listagem usam OR entre concessões válidas, cada uma com AND entre segmento e filial. Filtros só reduzem esse conjunto. Route model binding não substitui Policy. Devolver 404 para IDs fora do escopo; 403 para operação proibida sobre recurso visível.

## Perfis

| Perfil | Âmbito / finalidade |
|---|---|
| super-admin | global; segurança, atribuição de perfis, configuração e todas as operações |
| admin | global; operação empresarial e CMS; sem alterar perfis/permissões ou promover utilizadores |
| manager | segmento; catálogo, inventário, pedidos, clientes e relatórios |
| stock-manager | segmento ou segmento+filial; inventário e transferências |
| sales-manager | segmento ou segmento+filial; pedidos e contactos de clientes associados |
| employee | segmento+filial; consulta operacional e atualização de pedidos; sem ajustes de stock |
| content-manager | segmento; notícias, banners, FAQ, serviços e conteúdo desse segmento |

Legenda na matriz: **G** global; **E** apenas escopo atribuído; **—** negado. Super-admin tem todas as permissões. Cada conjunto abaixo expande para permissões individuais; não armazenar wildcard literal.

| Permissões | admin | manager | stock-manager | sales-manager | employee | content-manager |
|---|---|---|---|---|---|---|
| dashboard.view | G | E | E | E | E | E |
| products.view | G | E | E | E | E | E |
| products.create, products.update | G | E | — | — | — | — |
| products.delete, products.import | G | E | — | — | — | — |
| products.cost.view | G | E | — | — | — | — |
| categories.view | G | E | E | E | E | E |
| categories.create, categories.update, categories.delete | G | E | — | — | — | — |
| brands.view | G | E | E | E | E | E |
| brands.create, brands.update, brands.delete | G | — | — | — | — | — |
| images.view, images.create, images.update, images.delete | G | E | — | — | — | E |
| stock.view, movements.view, alerts.view | G | E | E | — | E | — |
| stock.update, stock.import | G | E | E | — | — | — |
| transfers.view, stock.transfer, transfers.dispatch, transfers.receive | G | E | E | — | — | — |
| orders.view, orders.create, orders.update | G | E | — | E | E | — |
| orders.cancel | G | E | — | E | — | — |
| customers.view, customers.create | G | E | — | E | E | — |
| customers.update | G | E | — | E | — | — |
| customers.export | G | E | — | E | — | — |
| segments.view, branches.view | G | E | E | E | E | E |
| segments.create, segments.update, segments.delete | G | — | — | — | — | — |
| branches.create, branches.update, branches.delete | G | — | — | — | — | — |
| services.view, services.create, services.update, services.delete | G | E | — | — | — | E |
| team.view, team.create, team.update, team.delete | G | E | — | — | — | E |
| content.view, content.create, content.update, content.delete, content.publish | G | — | — | — | — | E |
| contacts.view, contacts.update | G | E | — | E | — | — |
| reports.stock, reports.movements | G | E | E | — | — | — |
| reports.orders | G | E | — | E | — | — |
| reports.products, reports.segments, reports.branches | G | E | — | — | — | — |
| users.view, users.create, users.update, users.disable | G | — | — | — | — | — |
| roles.manage, permissions.manage, users.assign_roles | — | — | — | — | — | — |
| logs.view, settings.view, settings.update | — | — | — | — | — | — |

`sales.view/create/update`, `reports.sales`, `api.tokens.manage` ficam reservadas e sem atribuição até à fase 08. A tela Vendas não apresenta funcionalidades fictícias.

## Restrições adicionais

- Funcionário não edita dados globais de produto nem promove estados para cancelled/delivered sem regra explícita: delivered permitido apenas a manager/sales-manager/admin. Mesmo orders.update não ignora esta regra de transição.
- Um utilizador com concessão restrita à filial não altera cadastros de todo o segmento, mesmo se o perfil tiver products.update; esses cadastros exigem concessão de segmento sem branch_id.
- Conteúdo sem segmento (homepage, páginas globais, banner institucional) só pode ser alterado por admin/super-admin. Editor de segmento não modifica globals nem as definições de navegação.
- Imagens de produtos exigem também products.update; imagens editoriais exigem content.update. Uma permissão images.create não autoriza alterar qualquer objeto.
- Cliente só é visível se tiver pedido visível. A edição de dados centrais de cliente partilhado por segmentos exige acesso a todos os seus pedidos; caso contrário, pode editar apenas o snapshot do pedido autorizado. Consulta por telefone nunca revela cliente de outro escopo.
- Transferências: criar/expedir exige autorização na origem; receber exige autorização no destino. Destinos do mesmo segmento podem ser listados apenas com id/nome para seleção, sem expor stock. Destinatário vê a transferência inbound e dados mínimos dos itens, não relatórios da origem.
- Admin cria contas sem privilégios e pode alterar contas operacionais; não altera super-admin, outro admin, credenciais privilegiadas ou concessões. Só super-admin atribui privilégios; proteger o último super-admin ativo contra remoção/desativação.
- Revogar acesso invalida cache de permissões e sessões quando necessário; exportações e jobs revalidam antes de executar ou entregar ficheiros.
- Auditoria sempre regista autor, operação, objeto, alterações permitidas e request_id, sem segredos.

## Exemplos de aceite

Gestor Farmácia vê o segmento nas duas filiais; funcionário Farmácia/Bailundo só vê esse par. Se o mesmo utilizador for stock-manager Farmácia/Luanda e employee Comercial/Bailundo, não pode ajustar stock Comercial. Aceder por URL direta, alterar filtros, trocar IDs de formulário ou pedir uma exportação não contorna estas restrições.
