# Plano de implementação e critérios de aceite

## Fases

| Fase | Entrega | Dependências / aceite |
|---|---|---|
| 01 Fundação | Laravel, ambiente MySQL, auth/2FA, Vue/Inertia, roles/permissions, escopos, layout | login/logout/recovery; registo público desativado; Policies rejeitam URL direta; último super-admin protegido; teste de perfis cruzados |
| 02 Website | homepage, sobre, segmentos, páginas Farmácia/Comercial/Timbragem/Lubrificantes, contactos | destaque Farmácia; mobile/teclado; contactos reais configurados; sem números inventados; SEO básico e HTML renderizado |
| 03 Catálogo | categorias, marcas, produtos, imagens, pesquisa/filtros e importação de catálogo | category.segment = product.segment; SKU único; modos de compra respeitados no backend; upload inválido rejeitado; preview de importação |
| 04 Filiais + stock | inventário, movimentos, transferências, alertas, importação de contagem | concorrência sem saldo negativo; dupla receção sem duplicação; trânsito separado; importação gera movimentos; escopo em relatórios |
| 05 Pedidos | clientes, itens, estados, WhatsApp | preços calculados no servidor; snapshots; replay idempotente; modo catálogo rejeitado; WhatsApp não contabiliza venda nem garante envio |
| 06 CMS | homepage, banners, notícias, páginas, serviços, equipa, FAQ/depoimentos | editor gere conteúdo sem código; sanitização; preview; agendamento; autor de segmento não publica global |
| 07 Relatórios | stock, movimentos, pedidos, produtos, segmentos e filiais | filtros autorizados; exportação privada; reconciliação de saldos; métricas documentadas |
| 08 Evolução | API, mobile, integrações, vendas/pagamentos, ERP, faturação | contratos e testes próprios; preservar domínio e versionar alterações incompatíveis |

As filiais mínimas e os segmentos necessários ao site são cadastrados na fase 02; a operação de inventário começa na 04. Serviços podem ser publicados como conteúdo institucional na 02 e passam ao cadastro completo na 06. Não ativar formulários de pedido antes da fase 05; até lá, contacto institucional é suficiente.

## Sequência das migrations

1. Preservar migrations existentes: users/password reset/sessions, cache/locks, jobs/batches/failed jobs.
2. Instalar starter kit compatível sem sobrescrever personalizações; aplicar suas migrations de 2FA. Instalar Spatie compatível, manter guard web e teams desativado, publicar migrations oficiais. Fixar versões no lockfile e testar.
3. Executar as referências próprias por fase e ordem de dependência. Extensão de users; segments/branches/branch_segment; user_accesses depois de roles; catálogo; inventário; customers/services/orders/items/history; transfers/items/movements; CMS; logs/settings/imports. Os nomes ordenados da pasta reference refletem essa dependência, não obrigam a ativar todos os módulos ao mesmo tempo.
4. Gerar a migration padrão de notifications quando ativar notificações persistentes. Redis não substitui a tabela notifications.
5. Seeders idempotentes: permissões explícitas, perfis/matriz, quatro segmentos. Filiais e contactos reais são configuração inicial; não fabricar endereços/números. Primeiro super-admin por comando interativo ou segredo de instalação, nunca password padrão no repositório.

Antes de executar migrations em produção: backup restaurável, revisão do SQL, ensaio em staging e plano de avanço/correção. Rollback de referência serve para base de testes; não apagar tabelas com histórico de produção para desfazer uma release.

## Testes obrigatórios da implementação

| Área | Casos que devem passar |
|---|---|
| Autorização | utilizador inativo, URL direta, ID de outra filial, OR/AND de concessões, perfil de uma linha não empresta permissões a outra, exportação/job sem acesso após revogação |
| Catálogo | categoria de outro segmento, pai cíclico, SKU duplicado, preço promocional inválido, publication off, catalog_only no POST |
| Inventário | entrada/saída/ajuste, saldo zero, decimal/unidade, saldo negativo rejeitado, versão antiga rejeitada, duas saídas concorrentes disputando último saldo |
| Transferência | mesma origem/destino, segmento incompatível, saldo insuficiente desfaz todos os itens, receber duas vezes, expedir duas vezes, negar receção na filial errada |
| Pedidos | payload com preço adulterado, items de outro segmento, produto/serviço simultâneos, transição inválida, snapshot imutável, total nulo sob orçamento, idempotência concorrente com rollback do cliente duplicado |
| CMS/uploads | MIME falso, SVG/script, ficheiro excedente, XSS, destino javascript:, publicação agendada, editor de segmento em conteúdo global |
| Importação | preview, linhas inválidas/duplicadas, retry sem duplicação, stock alterado após preview, produto fora do escopo, erro numa linha reportado |
| Relatórios | saldo = soma de movimentos desde inicialização, em trânsito reconciliado, CSV sem fórmula injetada, URL privada expirada, campos custo ocultos |
| UI | teclado/mobile, erros preservam formulário, sem dados fictícios em vazio, loading, links canónicos |

Teste de concorrência e locks deve rodar em MySQL de versão igual à produção; SQLite não verifica esses comportamentos. CI: dependências locked, formatter, testes PHP, typecheck/build Vue, migrations numa base descartável e análise de vulnerabilidades conforme fase.

## Operação

Ubuntu + Nginx + PHP-FPM + MySQL + Redis. Nginx aponta para public/, apenas public/ é servido. TLS, APP_DEBUG=false, cookies secure/httpOnly/sameSite apropriado, segredos fora do Git. Worker supervisionado e scheduler ativo. Permissões de escrita só em storage/bootstrap/cache; não servir uploads privados.

Disco público para imagens reprocessadas (JPEG/PNG/WebP, limite inicial 5 MB e 6000×6000 px); recusar SVG na versão inicial. Nome aleatório e MIME confirmado pelo conteúdo, não pela extensão. Disco privado para anexos/importações/exportações. Rich text sanitizado no servidor com allowlist, mesmo para administradores. URLs de botões limitadas a paths locais ou https em hosts aprovados; recusar javascript/data.

Logs estruturados com request_id, métricas de jobs falhados, erros 5xx, latência, espaço em disco e falhas de backup. Notificações low stock deduplicadas por cruzamento de limiar; novas ocorrências só depois da reposição. Notificações não incluem informação sensível desnecessária.

Backup cifrado diário da base e uploads, cópia fora da VPS, retenção inicial proposta de 30 dias e exercício de restauro antes do lançamento. Objetivos iniciais: RPO até 24h, RTO até 4h, a validar com a operação; não prometer sem medir. Segredos de cifragem têm cópia segura separada. Backups e retenção precisam de responsável identificado.

Deploy: build em CI, release versionada, migrations compatíveis, cache de configuração/rotas/views quando aplicável, restart controlado de workers, healthcheck e smoke tests. Rollback do código só quando compatível com o schema; alterações destrutivas seguem estratégia expandir/migrar/contrair.

## Antes do lançamento

Validar domínio, marca, imagens autorizadas, contactos, horários e moradas de Luanda/Bailundo; definição de quem recebe pedidos; política de atendimento/cancelamento; termos e privacidade; requisitos aplicáveis à exposição de produtos farmacêuticos. Se stock passar a ser o sistema operacional da farmácia, definir primeiro lotes, validade, rastreabilidade e procedimentos associados. Não existe parecer jurídico neste blueprint.

## Entrega técnica atual

Documentação e referências PHP apenas. Não foram instalados Vue/Inertia, Spatie, Fortify ou Redis; não foram criados utilizadores, enviados contactos ou alteradas bases da aplicação. As referências não incluem Controllers/Policies/Actions implementadas: essas entregas pertencem às fases acima. A verificação estrutural é descrita em reference/README.md.
