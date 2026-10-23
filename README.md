# CreditFlow

API REST em Laravel para gestão de créditos: cada usuário possui uma conta com saldo e pode registrar **créditos**, **débitos** e **estornos**, consultando o extrato paginado. Acompanha um front-end SPA em Vue.

## Objetivo

Garantir **consistência financeira**: saldo nunca negativo, operações sem duplicidade (idempotência), estornos compensatórios rastreáveis e proteção contra condições de corrida.

## Tecnologias

| Camada | Tecnologia |
| --- | --- |
| Back-end | PHP 8.3+, Laravel 13, Laravel Sanctum (tokens de API) |
| Banco de dados | PostgreSQL 16 (Docker) |
| Front-end | Vue 3, Vite, Tailwind CSS 4 |
| Testes | PHPUnit 12 (SQLite em memória) |

## Pré-requisitos

- PHP **8.3+** com extensões `pdo_pgsql` e `pdo_sqlite`
- Composer
- Node.js 20+ e npm
- Docker e Docker Compose (para o PostgreSQL)

## Instalação

```bash
git clone <url-do-repositorio> creditflow
cd creditflow

composer install
npm install
```

## Configuração do `.env` e do banco

1. Copie o arquivo de exemplo e gere a chave da aplicação:

```bash
cp .env.example .env        # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

2. Edite o `.env` e escolha uma senha em `DB_PASSWORD`. O mesmo valor é usado pelo [docker-compose.yml](docker-compose.yml) para criar o PostgreSQL (o Compose lê o `.env` automaticamente e não sobe sem essa variável). O `.env.example` não contém segredos e o `.env` não deve ser versionado.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=creditflow
DB_USERNAME=postgres
DB_PASSWORD=<senha de sua escolha>
```

3. Suba o PostgreSQL (cria os bancos `creditflow` na porta 5432 e `creditflow_test` na 5433):

```bash
docker compose up -d
```

> **Atenção:** o PostgreSQL só lê a senha na primeira criação do volume. Se trocar `DB_PASSWORD` depois, rode `docker compose down -v` (apaga os dados locais) antes de subir novamente.

## Migrations e seed

```bash
php artisan migrate
php artisan db:seed      # opcional: dados de demonstração
```

O seed cria o usuário demo `demo@creditflow.local` (senha definida em [DatabaseSeeder.php](database/seeders/DatabaseSeeder.php)) com saldo e histórico de exemplo.

> **Somente para demonstração:** as credenciais demo são públicas (o front-end tem um botão que as preenche) e só existem após `db:seed`. Não execute o seed em produção.

## Executando

```bash
npm run build            # compila o front-end
php artisan serve        # http://localhost:8000
```

Para desenvolvimento com hot reload, rode também `npm run dev` em outro terminal.

## Testes

```bash
php artisan test
```

Os testes usam SQLite em memória (ver [phpunit.xml](phpunit.xml)), portanto não alteram o banco PostgreSQL. Cobrem autenticação, créditos/débitos, idempotência, estornos e o utilitário de dinheiro.

> **Nota:** o bloqueio `FOR UPDATE` é ignorado pelo SQLite; a proteção de concorrência só é efetiva no PostgreSQL.

### Teste de concorrência (PostgreSQL)

[ConcurrencyTest](tests/Feature/ConcurrencyTest.php) dispara 5 processos PHP reais contra a mesma conta (via [debit_worker.php](tests/Support/debit_worker.php)). Ele é **ignorado** no SQLite e roda contra o banco `creditflow_test` do Docker (porta 5433):

```powershell
$env:DB_CONNECTION="pgsql"; $env:DB_HOST="127.0.0.1"; $env:DB_PORT="5433"
$env:DB_DATABASE="creditflow_test"; $env:DB_USERNAME="postgres"; $env:DB_PASSWORD="<senha do docker-compose>"
php artisan test --filter=ConcurrencyTest
```

Verifica que, com saldo de R$ 100,00 e 5 débitos simultâneos de R$ 80,00, apenas um é aceito (os outros recebem `422`) e que 5 requisições simultâneas com a mesma `Idempotency-Key` geram um único lançamento.

## Autenticação

A API usa **Bearer tokens** (Laravel Sanctum). Registre-se ou faça login, receba o `token` e envie-o em todas as rotas protegidas:

```
Authorization: Bearer <token>
Accept: application/json
```

Todas as rotas têm prefixo `/api`. O login é limitado a **5 tentativas por minuto**.

## Endpoints

| Método | Rota | Auth | Descrição |
| --- | --- | --- | --- |
| POST | `/api/register` | Não | Cria usuário e conta (saldo 0) |
| POST | `/api/login` | Não | Retorna token |
| POST | `/api/logout` | Sim | Revoga o token atual |
| GET | `/api/user` | Sim | Usuário autenticado |
| GET | `/api/account` | Sim | Conta e saldo |
| GET | `/api/transactions` | Sim | Extrato paginado com filtros |
| POST | `/api/transactions/credit` | Sim | Registra crédito |
| POST | `/api/transactions/debit` | Sim | Registra débito |
| POST | `/api/transactions/{id}/reverse` | Sim | Estorna uma movimentação |

### `POST /api/register`

Senha: mínimo 8 caracteres, com maiúsculas, minúsculas e números. E-mail é normalizado (minúsculas, sem espaços).

```json
{
  "name": "Maria Silva",
  "email": "maria@example.com",
  "password": "Senha1234",
  "password_confirmation": "Senha1234"
}
```

Resposta `201`:

```json
{
  "user": { "id": 1, "name": "Maria Silva", "email": "maria@example.com" },
  "token": "1|abc123..."
}
```

### `POST /api/login`

```json
{ "email": "maria@example.com", "password": "Senha1234" }
```

Resposta `200`: mesmo formato do registro. Credenciais inválidas retornam `401` com `{"message": "Credenciais inválidas."}`.

### `GET /api/account`

```json
{
  "data": { "id": 1, "balance_cents": 57950, "balance": "579.50" }
}
```

### `GET /api/transactions`

Query params opcionais: `type` (`credit`, `debit`, `reversal`), `from` e `to` (`YYYY-MM-DD`, `to >= from`), `per_page` (1–100, padrão 15), `page`.

```
GET /api/transactions?type=debit&from=2026-10-01&to=2026-10-31&per_page=10
```

Resposta `200` (paginação padrão do Laravel com `links` e `meta`):

```json
{
  "data": [
    {
      "id": 2,
      "account_id": 1,
      "type": "debit",
      "amount_cents": 12050,
      "amount": "120.50",
      "balance_after_cents": 37950,
      "balance_after": "379.50",
      "idempotency_key": "3f6c1d1e-9a3b-4b7a-8d52-0c1f1b6a7e11",
      "reverses_transaction_id": null,
      "created_at": "2026-10-18T14:20:00.000000Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1 }
}
```

### `POST /api/transactions/credit` e `/debit`

Requer o cabeçalho **`Idempotency-Key`** (string, até 100 caracteres, ex.: um UUID). O valor vai em **centavos inteiros** (mínimo 1).

```
POST /api/transactions/credit
Authorization: Bearer <token>
Idempotency-Key: 3f6c1d1e-9a3b-4b7a-8d52-0c1f1b6a7e11
```

```json
{ "amount_cents": 5000 }
```

Resposta `201` (criada) ou `200` (repetição idempotente), com o mesmo corpo:

```json
{
  "data": {
    "id": 7,
    "account_id": 1,
    "type": "credit",
    "amount_cents": 5000,
    "amount": "50.00",
    "balance_after_cents": 62950,
    "balance_after": "629.50",
    "idempotency_key": "3f6c1d1e-9a3b-4b7a-8d52-0c1f1b6a7e11",
    "reverses_transaction_id": null,
    "created_at": "2026-10-22T23:00:00.000000Z"
  }
}
```

### `POST /api/transactions/{id}/reverse`

Sem corpo e sem `Idempotency-Key`. Resposta `201` com a transação do tipo `reversal`:

```json
{
  "data": {
    "id": 8,
    "type": "reversal",
    "amount_cents": 5000,
    "amount": "50.00",
    "balance_after_cents": 57950,
    "balance_after": "579.50",
    "idempotency_key": null,
    "reverses_transaction_id": 7
  }
}
```

### Códigos de erro

| Status | Quando |
| --- | --- |
| 401 | Token ausente/inválido ou credenciais incorretas |
| 409 | `Idempotency-Key` reutilizada com dados diferentes; movimentação já estornada |
| 422 | Validação falhou (inclui movimentação inexistente ou de outra conta no estorno), saldo insuficiente, ou tentativa de estornar um estorno |
| 429 | Limite de tentativas de login excedido |

Erros de validação seguem o formato `{"message": "...", "errors": {"campo": ["..."]}}`.

## Regras de negócio

### Dinheiro

- Valores são armazenados e trafegam como **inteiros em centavos** (`bigInteger`); **nunca** usamos `float`.
- A formatação para exibição (`"579.50"`) é feita por [Money::format](app/Support/Money.php) com aritmética inteira, sem erros de arredondamento.
- O saldo nunca fica negativo: validado no serviço e garantido por `CHECK` no PostgreSQL (`balance_after_cents >= 0`, `amount_cents > 0`).

### Idempotência

- Créditos e débitos exigem `Idempotency-Key`; a chave é única **por conta** (`UNIQUE(account_id, idempotency_key)`).
- Repetir a requisição com a **mesma chave e mesmos dados** não gera nova movimentação: retorna a original com status `200`.
- Reusar a chave com **tipo ou valor diferente** retorna `409`.
- O front-end gera o UUID automaticamente a cada operação; o usuário não precisa informá-lo.

### Estorno

- O estorno cria uma nova movimentação `reversal` compensatória, **sem apagar nem alterar** o histórico original.
- Estornar um crédito subtrai o valor; estornar um débito devolve o valor. Se o saldo ficar negativo, retorna `422`.
- Um estorno **não pode ser estornado** (`422`) e uma movimentação só pode ser estornada **uma vez** (`409`, garantido também por índice único em `reverses_transaction_id`).
- Só é possível estornar movimentações da própria conta.

### Concorrência

- Toda operação roda dentro de `DB::transaction` e trava a linha da conta com `SELECT ... FOR UPDATE` (`lockForUpdate`).
- Requisições simultâneas na mesma conta são serializadas: duas retiradas concorrentes não gastam o mesmo saldo, e a checagem de idempotência ocorre dentro do lock.
- Cadastro é atômico: usuário e conta são criados juntos ou nenhum dos dois.

## Uso de ferramentas de IA

- **Antigravity (assistente de código com modelos Gemini e Claude):** apoio na escrita de testes, documentação (README e DECISOES.md), ajustes de interface e revisão das regras de negócio.

Todo código gerado foi lido, executado e validado antes de entrar no repositório.

### Exemplo de código validado

O [ConcurrencyTest](tests/Feature/ConcurrencyTest.php) foi sugerido pela IA para provar a regra R6. Um teste que passa não prova nada por si só, então ele foi validado removendo temporariamente `->lockForUpdate()` do [TransactionService](app/Services/TransactionService.php):

- **Com o bloqueio:** os 2 testes passam (1 débito aceito, 4 rejeitados com `422`).
- **Sem o bloqueio:** os 2 testes **falham**. Os 5 débitos de R$ 80,00 foram aceitos (`201`) sobre um saldo de R$ 100,00, e a requisição concorrente com mesma chave chegou a violar a constraint única do banco.

Isso confirma que o teste detecta a condição de corrida e que o `lockForUpdate` é o que a evita. O código foi restaurado ao original em seguida.

## Estrutura

```
app/
  Http/Controllers/   AuthController, AccountController, TransactionController
  Http/Requests/      validações (Register, Login, StoreTransaction, ...)
  Http/Resources/     formatação JSON (User, Account, Transaction)
  Services/           TransactionService (regras de saldo, idempotência, estorno)
  Support/Money.php   utilitário de dinheiro
resources/js/App.vue  front-end SPA
routes/api.php        rotas da API
```
