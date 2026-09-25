# Referências Laravel

As migrations e os Models deste diretório são artefactos de blueprint, fora dos caminhos executados pela aplicação. Não representam um sistema pronto nem substituem as regras transacionais, Policies e Requests descritos nos documentos.

- `migrations/`: extensão de users e tabelas de domínio, ordenadas; users e roles têm de existir antes. Roles é fornecida pela migration oficial do Spatie. Não copiar uma segunda migration de users.
- `models/`: relações e casts de domínio. UserRelationships é um trait para integrar no User existente; adicionar HasRoles só depois de instalar o pacote. Guarded bloqueia mass assignment por padrão. Cast active em User deve ser acrescentado durante integração.
- `../schema.json`: representação legível por ferramentas do schema próprio; migrations são a implementação SQL de referência.
- `../erd.mmd`: diagrama completo de relações, incluindo relações lógicas de pacotes.

Sem constraints condicionais SQL: exatamente um produto/serviço, limites não negativos, origem diferente de destino, associações de segmento e estados dependem das Actions. A integração só fica completa com os testes de negócio descritos no blueprint. Roles/permissions e notifications usam migrations dos respetivos fornecedores, não versões reinventadas aqui.

## Verificação isolada

Da raiz `plataforma`, executar:

```powershell
php docs/blueprint-v1/reference/verify.php
```

O verificador usa o vendor existente e SQLite `:memory:` com foreign_keys ativo, sem carregar `.env` ou iniciar a aplicação. Cria apenas fixtures mínimas users/roles, executa as migrations, verifica índices/referências e relações locais, depois desfaz as migrations. Não instala Spatie; a relação Role é inspecionada estruturalmente e será validada com o pacote na fase 01. Não valida comportamento concorrente MySQL, permissões, autenticação ou interface.

Resultado em 24/09/2026: **29 migrations up/down, 28 tabelas próprias e 90 relações locais verificadas**, com rejeição de inventário duplicado, FK inexistente e eliminação de produto referenciado. Nenhuma migration foi executada na base da aplicação.

Análise de sintaxe: **58 ficheiros PHP aprovados por php -l** (29 migrations, 27 Models, um trait e o verificador).
