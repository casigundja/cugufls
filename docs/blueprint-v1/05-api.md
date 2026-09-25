# Contrato API v1

Estado: desenhado, implementação prevista na fase 08. Prefixo `/api/v1`, JSON UTF-8, HTTPS em produção. Controllers finos reutilizam as Actions do website/dashboard. Inertia não precisa de consumir esta API para funcionar.

## Endpoints públicos

| Método e caminho | Parâmetros | Resposta |
|---|---|---|
| GET /segments | page, per_page | Segment[] |
| GET /segments/{slug} | — | Segment |
| GET /branches | segment (slug), city, page, per_page | Branch[] |
| GET /branches/{slug} | — | Branch |
| GET /categories | segment (slug), parent_id, page, per_page | Category[] |
| GET /brands | segment (slug), q, page, per_page | Brand[] do catálogo ativo |
| GET /products | q, segment, category (slug), brand (slug), branch (slug), availability, sort, page, per_page | ProductSummary[] |
| GET /products/{slug} | branch (slug, opcional) | ProductDetail |
| GET /services | segment, page, per_page | Service[] |
| GET /services/{slug} | — | Service |
| GET /posts | segment, page, per_page | PostSummary[] |
| GET /posts/{slug} | — | PostDetail |
| POST /orders | Idempotency-Key obrigatório + corpo abaixo | 201 OrderReceipt |
| POST /contacts | nome/contacto/mensagem/contexto | 202 Receipt mínimo |

Não expor GET público de pedidos, clientes, inventário exato, utilizadores, auditoria ou relatórios. Slugs inexistentes/inativos devolvem 404. Produtos só aparecem se segmento/categoria estiverem ativos. Preço nulo mantém null, nunca 0.

## Recursos e allowlists

| Recurso | Campos permitidos |
|---|---|
| Segment | id, name, slug, description, icon, image_url |
| Branch | id, name, slug, city, province, address, phone, whatsapp, email, latitude, longitude, opening_hours, segments[] (id/name/slug) |
| Category | id, segment_id, parent_id, name, slug, description, image_url |
| Brand | id, name, slug, image_url |
| ProductSummary | id, slug, name, short_description, unit, purchase_mode, price, sale_price, currency, primary_image_url, segment, category, brand, availability |
| ProductDetail | campos de Summary + description sanitizada, images[] (url/alt/order), branches[] (id/name/availability) |
| Service | id, segment_id, slug, name, description, price, currency, image_url |
| PostSummary | id, slug, title, excerpt, image_url, segment_id, published_at |
| PostDetail | campos de Summary + content sanitizado, author_display_name |
| OrderReceipt | reference (public_id), status, currency, quoted_total, whatsapp_url nullable |

Resources nunca serializam Models com `toArray()` indiscriminadamente. O custo, notas internas, SKU se reservado, dados de cliente, emails de utilizadores, mínimos/máximos de stock e caminhos privados não são públicos. URLs de imagens usam o disk configurado; não expor caminhos absolutos.

## Convenções de consulta

Paginação: page >= 1, per_page padrão 20, máximo 100. q até 100 caracteres. Sort permitido: name, -name, price, -price, newest; preços desconhecidos no fim. category slug exige segment para resolver ambiguidade. availability: available, unavailable, on_request; exige branch. Sem branch, availability de produto é on_request. IDs auxiliares devem pertencer ao segmento filtrado; combinação incoerente devolve 422.

```json
{
  "data": [],
  "links": {"first": "https://example.invalid/api/v1/products?page=1", "last": "https://example.invalid/api/v1/products?page=1", "prev": null, "next": null},
  "meta": {"current_page": 1, "last_page": 1, "per_page": 20, "total": 0}
}
```

O domínio example.invalid é ilustrativo. Recursos individuais retornam `{"data": {...}}`. Dinheiro e quantidades são strings decimais; datas ISO-8601 em UTC. Imagens têm URLs absolutas. Códigos de erro e estados em inglês; mensagens traduzíveis.

## Criar pedido

Cabeçalhos: Content-Type application/json, Accept application/json, Idempotency-Key UUID v4. Corpo ilustrativo, com IDs e contacto a substituir por dados válidos:

```json
{
  "segment_id": 1,
  "branch_id": 1,
  "customer": {"name": "Cliente de exemplo", "phone": "+244XXXXXXXXX", "email": null, "company": null},
  "items": [
    {"product_id": 10, "quantity": "2.000"},
    {"service_id": 3, "quantity": "1.000", "customization": {"instructions": "Texto de exemplo"}}
  ],
  "notes": null
}
```

Regras: 1–50 itens; exatamente um product_id/service_id por item; IDs distintos por tipo; quantidade > 0 e <= 9999, unidade inteira quando necessário; nome 2–150, telefone internacional válido até 20 caracteres, email válido até 254, company até 150, notes/instructions até 1000. Campo customization só permite instructions no v1. Não aceitar preço, custo, desconto, total, estado, user_id ou assigned_to enviados pelo público. Rejeitar chaves inesperadas para tornar o contrato claro.

Os dois itens do exemplo só podem coexistir se forem do mesmo segmento e estiverem disponíveis para atendimento na filial selecionada. Backend calcula preços atuais; itens sob orçamento têm preços/total null. Total do pedido é null se qualquer item não estiver orçamentado. `catalog_only` é rejeitado com 422; `request_availability` nunca garante saldo. Stock não é reservado.

201 inclui apenas o recibo mínimo, sem telefone/email de cliente. Referência usa UUID/ULID aleatório, independente do ID sequencial. Mensagem WhatsApp pode incluir referência, nome do produto, quantidade e filial; não incluir dados clínicos ou notas sensíveis.

Idempotência: normalizar o payload e guardar hash HMAC-SHA256 com segredo do servidor estável, separado da base. A chave é globalmente única em orders; manter enquanto o pedido existir. Mesma chave+hash retorna o mesmo recibo mínimo com 200, mesmo sob concorrência. Mesma chave+payload diferente retorna 409. Criar Customer, Order, Items e primeiro estado na mesma transação; colisão da chave desfaz toda a tentativa perdedora. Não usar a chave como autorização para consultar dados do cliente.

## Criar contacto

POST /contacts recebe name (2–150), email ou phone (ao menos um), company opcional, message (10–3000), type (general/demo/quote), segment_id/branch_id opcionais. Branch exige segment e associação válida. Demo deve ser encaminhado ao Comercial; não aceitar o cliente a escolher o responsável. Resposta 202: `{"data":{"accepted":true}}`. Honeypot e limite de frequência; nunca refletir conteúdo não sanitizado em HTML/email.

## Erros e limites

```json
{
  "message": "Não foi possível validar o pedido.",
  "code": "validation_failed",
  "errors": {"items.0.quantity": ["A quantidade deve ser superior a zero."]},
  "request_id": "uuid-de-correlacao"
}
```

| HTTP | Uso |
|---|---|
| 200 / 201 / 202 / 204 | leitura ou repetição idempotente / criação / processamento / ação sem corpo |
| 400 | JSON malformado |
| 401 | token/sessão exigido, ausente ou inválido |
| 403 | operação proibida sobre recurso visível |
| 404 | recurso inexistente, inativo ou fora do escopo |
| 409 | chave reutilizada com payload diferente, estado/saldo desatualizado |
| 413 | payload/upload demasiado grande |
| 415 | tipo de conteúdo não suportado |
| 422 | erro de validação |
| 429 | limite excedido, com Retry-After |
| 500 / 503 | falha interna genérica / indisponibilidade temporária |

Limites iniciais propostos: GET público 120/min/IP; pedidos 5/min/IP e 20/h por hash de telefone; contactos 3/min/IP; login 5/min por identidade+IP. Ajustar após observar tráfego real e configurar proxies confiáveis. JSON até 64 KB, uploads em endpoint separado. Não confiar em X-Forwarded-For arbitrário.

Cache público com TTL inicial de 60s e invalidação após alterações relevantes; chaves incluem filtros e filial. Respostas de pedidos/contactos são no-store. Cache nunca mistura escopos privados. CORS restrito aos clientes publicados. Web continua a usar CSRF; futura API mobile usa bearer tokens revogáveis. Para SPA first-party, preferir sessão/cookie com proteção CSRF do mecanismo escolhido.

## Extensão autenticada

Na fase 08, Sanctum é candidato para tokens mobile; instalar e verificar compatibilidade nessa fase. Tokens têm abilities mínimas, expiração e revogação; abilities não substituem Policies nem escopo. Sem registo público de funcionários.

Rotas privadas planeadas, sob `/api/v1`:

| Método e caminho | Contrato / permissão |
|---|---|
| GET /me | identidade própria e contextos autorizados |
| DELETE /tokens/current | revogar token atual |
| GET /orders; GET /orders/{reference} | pedidos no escopo; orders.view |
| PATCH /orders/{reference}/status | to_status/reason; mesmas transições web |
| GET /inventories | produto/filial no escopo; stock.view |
| GET /stock-movements | filtros e paginação; movements.view |
| POST /inventory-adjustments | operation_id, product_id, branch_id, tipo, quantidade/alvo, saldo observado, motivo; stock.update |
| GET /transfers; GET /transfers/{id} | transferências visíveis |
| POST /transfers | segmento/origem/destino/itens; stock.transfer |
| POST /transfers/{id}/dispatch | transfers.dispatch, operação idempotente |
| POST /transfers/{id}/receive | transfers.receive, operação idempotente |

Emissão de token só será exposta após desenhar autenticação com 2FA e fluxo do cliente mobile; não criar endpoint simplificado que contorne 2FA. API de pagamentos e ERP fica fora do v1; contrato de fornecedor e regras fiscais ainda não foram definidos.
