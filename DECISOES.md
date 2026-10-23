# Decisões Técnicas

Registro das principais decisões de arquitetura do CreditFlow, com motivo e consequências. Todas estão refletidas no código atual.

## 1. Valores monetários em centavos inteiros

**Decisão:** saldo e movimentações são armazenados e trafegam como inteiros em centavos (`balance_cents`, `amount_cents`, `balance_after_cents`, colunas `bigInteger`). O contrato da API recebe `amount_cents` como inteiro ≥ 1 ([StoreTransactionRequest](app/Http/Requests/StoreTransactionRequest.php)).

**Motivo:** ponto flutuante não representa decimais exatamente e acumula erros de arredondamento, inaceitável em contexto financeiro.

**Consequências:**
- Somas e subtrações são exatas.
- A formatação para exibição (`1050` → `"10.50"`) é feita só na saída, por [Money::format](app/Support/Money.php), com aritmética inteira.
- A API devolve os dois formatos (`amount_cents` e `amount`).
- O cliente precisa converter reais em centavos antes de enviar (o front-end faz isso em `submitTransaction`).

## 2. PostgreSQL com restrições no próprio banco

**Decisão:** o banco alvo é PostgreSQL 16 (via [docker-compose.yml](docker-compose.yml)). A migration de `transactions` cria `CHECK` constraints que valem independentemente da aplicação: tipo válido, `amount_cents > 0`, `balance_after_cents >= 0`, vínculo correto de estorno e `idempotency_key` obrigatória para não estornos.

**Motivo:** regras de integridade críticas não devem depender só do código PHP; o banco é a última linha de defesa contra bugs ou acessos diretos.

**Consequências:**
- Um saldo negativo é rejeitado mesmo se a validação da aplicação falhar.
- As constraints só são criadas quando `DB::getDriverName() === 'pgsql'`; os testes rodam em SQLite em memória ([phpunit.xml](phpunit.xml)) e **não** exercitam essas restrições.
- É necessário ter o Docker (ou um PostgreSQL local) para rodar a aplicação.

## 3. Transações de banco e bloqueio de linha

**Decisão:** cada crédito, débito e estorno roda dentro de `DB::transaction` e trava a linha da conta com `lockForUpdate()` (`SELECT ... FOR UPDATE`) antes de ler o saldo ([TransactionService](app/Services/TransactionService.php)). O cadastro também é atômico: usuário e conta são criados na mesma transação ([AuthController](app/Http/Controllers/AuthController.php)).

**Motivo:** evitar condições de corrida do tipo "ler saldo, calcular, gravar", em que duas requisições simultâneas gastariam o mesmo saldo.

**Consequências:**
- Operações na mesma conta são serializadas, o que garante consistência com custo de menor paralelismo por conta (aceitável, pois o bloqueio é por linha e não afeta outras contas).
- A verificação de idempotência acontece dentro do lock, eliminando a janela entre checar e inserir.
- O bloqueio é ignorado no SQLite, então a proteção de concorrência só é efetiva no PostgreSQL.

## 4. Idempotência por chave única por conta

**Decisão:** créditos e débitos exigem o cabeçalho `Idempotency-Key` (até 100 caracteres), copiado para a validação em `prepareForValidation`. A migration define `UNIQUE(account_id, idempotency_key)`. No serviço:
- mesma chave com mesmos dados retorna a movimentação original (`200`, em vez de `201`);
- mesma chave com tipo ou valor diferente retorna `409`.

**Motivo:** permitir repetição segura de requisições após falhas de rede ou cliques duplicados, sem cobrar duas vezes.

**Consequências:**
- O cliente é responsável por gerar a chave; o front-end gera um UUID por operação, de forma invisível ao usuário.
- A unicidade é **por conta**, então usuários diferentes podem reutilizar a mesma chave sem conflito.
- Estornos não usam chave; a duplicidade é evitada por outro mecanismo: índice único em `reverses_transaction_id` e verificação `409` de "já estornada".
- Chaves não expiram; ficam gravadas com a transação.

## 5. Autenticação por tokens com Laravel Sanctum

**Decisão:** a API usa tokens Bearer do Sanctum. `register` e `login` devolvem `createToken('api')`, as rotas protegidas ficam sob `auth:sanctum` e `logout` apaga o token atual ([routes/api.php](routes/api.php)). O login tem `throttle:5,1`. Cada usuário opera apenas na própria conta (`$request->user()->account`).

**Motivo:** a API é consumida por um front-end SPA e potencialmente por outros clientes; tokens são simples, sem estado de sessão e fáceis de revogar.

**Consequências:**
- Não há sessão nem CSRF para a API; o token deve ser guardado com cuidado pelo cliente.
- Logout revoga somente o token usado; outros tokens do mesmo usuário continuam válidos.
- O isolamento entre contas é garantido pelo escopo: estornar uma transação de outra conta resulta em `422`, pois a regra `exists` do [ReverseTransactionRequest](app/Http/Requests/ReverseTransactionRequest.php) filtra por `account_id`. O `findOrFail` no serviço é uma segunda barreira.
- O limite de 5 tentativas por minuto reduz força bruta, mas pode bloquear usuários legítimos que errem a senha repetidamente.
