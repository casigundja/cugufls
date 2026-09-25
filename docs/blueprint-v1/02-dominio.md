# Domínio, Models e operações

## Organização

```text
app/Models                         persistência e relações
app/Http/Controllers/Frontend      website Blade
app/Http/Controllers/Admin         dashboard Inertia
app/Http/Controllers/Api/V1        API futura
app/Http/Requests                  validação + autorização de entrada
app/Http/Resources                 allowlist de campos públicos
app/Policies                       permissão + escopo do registo
app/Actions/Inventory              AdjustStock, DispatchTransfer, ReceiveTransfer
app/Actions/Orders                 CreateOrder, TransitionOrder
app/Services                      CatalogService, WhatsAppService, ReportService
app/Jobs                          ProcessImport, ExportReport, SendNotification
app/Notifications                 LowStock, NewOrder, TransferReceived
resources/views/frontend          páginas públicas
resources/js/Pages/Admin           telas Vue
resources/js/Components            campos, tabelas, filtros, diálogos
```

Controllers coordenam Request → Policy → Action/Service → resposta. Models não decidem autorização com base no utilizador global; escopos devem receber o contexto explicitamente. Jobs e comandos também têm de aplicar o escopo do solicitante.

## Contrato dos Models

Os ficheiros em `reference/models` incluem relações tipadas, casts de decimais, datas, JSON e booleanos. Todos bloqueiam mass assignment por padrão (`guarded = ['*']`): na implementação, preencher campos explicitamente a partir de DTOs/Requests validados. Não usar `forceFill($request->all())`. Eles não substituem Policies ou Actions e não devem ser copiados isoladamente para produção.

| Model | Relações e comportamento |
|---|---|
| Segment | categories, products, services, posts, branches (N:N) |
| Branch | segments (N:N), inventories, orders, transferências origem/destino |
| Category | segment, parent, children, products; impedir ciclos e pais de outro segmento |
| Brand | products; marca global, edição reservada a administradores |
| Product | segment, category, brand opcional, images, inventories, movements, orderItems |
| ProductImage | product; caminho relativo, alt text e ordenação |
| Inventory | product, branch; uma linha por par; não editar quantity diretamente |
| StockMovement | product, branch, actor, order opcional, transferItem opcional; append-only |
| StockTransfer | segment, sourceBranch, destinationBranch, creator, receiver, items |
| StockTransferItem | transfer, product, movements |
| Customer | orders; clientes partilhados não são globalmente visíveis para gestores |
| Order | segment, branch, customer, assignee, items, statusHistory |
| OrderItem | order, product opcional, service opcional; snapshots preservados |
| Service | segment, orderItems; preço pode ser nulo (sob orçamento) |
| Post | segment opcional, author; rascunho/agendado/publicado |
| Page | editor; conteúdo e blocos da homepage |
| Banner | segment opcional; agendamento e destino validado |
| Contact | segment/branch opcionais, assignee; mensagem ou pedido de demonstração |
| TeamMember | segment/branch opcionais; apresentação pública, separado de User |
| Testimonial / Faq | segment opcional; publicação e ordenação |
| ActivityLog | actor; alvo polimórfico; append-only |
| Setting | editor; allowlist de chaves e tipos |
| ImportBatch | segment, branch, creator; ficheiro privado e relatório de erros |
| UserAccess | user, role, segment, branch; concessão de perfil num contexto |
| User | manter autenticação existente; acrescentar HasRoles (Spatie), accesses e relações de autoria |

Roles e Permissions pertencem ao pacote Spatie. Notifications usa o Model do Laravel. A referência `UserRelationships.php` é um trait de integração, não substitui User nem instala autenticação.

## Catálogo

`purchase_mode`: `whatsapp`, `catalog_only`, `request_availability`. O modo limita os botões e é validado também no servidor. Um produto `catalog_only` não entra em pedidos públicos. `request_availability` cria consulta sem prometer disponibilidade. Filiais só aceitam produtos/serviços do segmento a que estão associadas.

Preço promocional deve ser não negativo e inferior ao preço normal. SKU é único globalmente quando preenchido. Slug de produto é globalmente único; categoria é única por segmento. Categoria e produto devem ter o mesmo segmento. Produto com movimentos/pedidos não pode mudar de segmento; inativar e criar outro registo quando necessário.

Imagem principal é a primeira por `sort_order, id`; não há `is_primary` que permita duas principais. Publicação e destaque são independentes. O stock apresentado no público é uma disponibilidade (`available`, `unavailable`, `on_request`), nunca o saldo exato por padrão. Não há `products.stock_status` editável: deriva do inventário e do modo de compra.

## Stock e concorrência

1. Autorizar a operação e validar produto, filial e associação de segmento.
2. Abrir transação. Criar inventário ausente com quantidade zero usando unique(product_id, branch_id), tratando corrida de criação; bloquear a linha com `lockForUpdate`.
3. Validar saldo e unidade. Em ajustes, receber quantidade-alvo e versão/saldo observado; rejeitar formulário desatualizado com 409.
4. Calcular delta assinado, saldo anterior e novo saldo em decimal. Não permitir saldo negativo.
5. Atualizar inventário e inserir movimento/auditoria na mesma transação. Cada operação tem UUID único; tentativa repetida não duplica movimento.
6. Publicar alertas após commit. Falha de email não desfaz stock.

`stock_movements.quantity` é delta assinado. `new_quantity = previous_quantity + quantity`. Entrada/devolução/receção são positivas; saída/expedição são negativas; ajuste admite ambos. `reason` obrigatório. Histórico não se edita/apaga; correção gera movimento compensatório. Quantidade inicial também é uma entrada. Guardar `last_counted_at` numa contagem física; não confundir com `updated_at`.

Baixo stock: `0 < quantity <= minimum_quantity`; sem stock: `quantity = 0`. Uma linha produto/filial conta como ocorrência. Contagem de produtos distintos é um indicador separado. Sem contagem: `last_counted_at IS NULL`; registos inexistentes significam produto não configurado nessa filial.

## Transferências

Estados: `draft → dispatched → received`; `draft → cancelled`. V1 tem expedição/receção integral, sem recebimentos parciais. Origem diferente do destino, ambos associados ao segmento, itens sem duplicados e quantidades positivas.

- Rascunho: nenhum saldo muda. Cancelar só é permitido aqui.
- Expedir: bloquear transferência e todos os inventários de origem em ordem por ID; verificar todos os saldos e debitar todos os itens atomicamente. Gravar `transfer_out` e `dispatched_at`.
- Receber: utilizador autorizado no destino bloqueia transferência; creditar todos os inventários do destino e gravar `transfer_in`, `received_by`, `received_at` na mesma transação.
- Repetição da confirmação devolve o resultado existente. Índice único `(transfer_item_id, type)` protege contra repetição do débito/crédito.
- Em trânsito: quantidade já saiu da origem, ainda não entrou no destino. Relatórios exibem esse saldo separadamente. Problemas após expedição exigem investigação e operação compensatória auditada; nunca cancelar silenciosamente.

## Pedidos e WhatsApp

Um pedido pertence a um segmento e uma filial. Carrinhos com segmentos/filiais diferentes devem ser separados. Item referencia exatamente um produto ou um serviço; guardar snapshots de nome, SKU, unidade e preço. Itens de serviços podem guardar instruções de personalização; anexos privados, sem execução e com limites.

Fluxo: escolher filial/quantidade → validar → persistir pedido `new` → gerar mensagem e URL `https://wa.me/{numero}?text={mensagem_codificada}` → utilizador abre o WhatsApp. Usar número E.164 sem `+` no URL e codificar texto. Se a filial não tiver WhatsApp configurado, guardar pedido e apresentar contacto alternativo real. Não afirmar que a mensagem foi enviada. Não enviar mensagens automaticamente nesta fase.

Não deduzir stock ao criar, confirmar ou entregar pedido no v1; entrega comercial e saída física são ações distintas, apresentadas claramente ao operador. Stock sai pela operação de inventário auditada, podendo referenciar o pedido. Automatizar apenas quando houver política de reserva/venda implementada; nunca ativar ambos os mecanismos em simultâneo.

Estados válidos: `new → contacted|confirmed|cancelled`; `contacted → confirmed|cancelled`; `confirmed → preparing|cancelled`; `preparing → ready|cancelled`; `ready → delivered|cancelled`. Delivered/cancelled são terminais. Cancelamento exige motivo. Cada transição gera OrderStatusHistory com autor/data/motivo. Serviços sob orçamento mantêm `unit_price` e total nulos até orçamento explícito; zero não significa preço desconhecido.

Pedidos públicos não fazem merge automático de Customer por telefone/email: isso permitiria alterar dados existentes sem verificação. Criar contacto de cliente e deduplicar sob autorização posteriormente. Não guardar receitas médicas ou dados clínicos em notas livres.

## Importação e relatórios

CSV UTF-8 e XLSX, sem macros, até 10 MB e 10.000 linhas por lote inicialmente. Modelo: `sku,nome,segmento,categoria,marca,preco,filial,stock,minimo,unidade`. SKU obrigatório para atualização; SKU desconhecido cria produto. Resolver slugs, não nomes ambíguos. Decimal com ponto no CSV. Duplicados no mesmo lote são erros.

Preview valida todas as linhas e mostra ações antes da confirmação. Categorias/filiais desconhecidas não são criadas implicitamente. No v1, catálogo e inventário são importações separadas: saldo importado significa contagem absoluta, converte-se em ajuste com histórico e deteta saldo alterado desde o preview. Executar em fila e transação por linha; relatório distingue sucesso/erro, retry só repete linhas pendentes com chave única. O escopo é revisto na execução. Relatórios CSV neutralizam células que comecem por `=`, `+`, `-`, `@` quando o conteúdo for textual.

Relatórios exportados vão para disco privado, URL temporária e expiram após 24h. Rastrear solicitante e filtros. Exportação não pode revelar contactos, custo ou stock de filiais não autorizadas.
