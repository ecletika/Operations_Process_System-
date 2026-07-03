# OPS API REST v1

Fase 5 do roadmap (OPS-PRD-001 §11.23 "API Ready" / Sprint 5 "API REST").
Base URL: `http://localhost:8000/api/v1`

A API é **stateless** — autentica por Bearer token, não usa o cookie de sessão web.
Todas as respostas são JSON, com o envelope:

```json
{ "data": { ... } }
```
ou, em erro:
```json
{ "error": "mensagem" }
```

## Autenticação

### `POST /api/v1/auth/login`

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -d "username=admin&password=Admin@123&device_name=meu-script"
```

Resposta (`201`):
```json
{
  "data": {
    "token": "b7e1...64hex...",
    "expires_at": "2026-10-02 12:00:00",
    "user": { "id": 1, "name": "Administrador OPS", "username": "admin" }
  }
}
```

O token expira em 90 dias (renovável fazendo login novamente) e é aceite em todos os
outros endpoints via header:

```
Authorization: Bearer b7e1...64hex...
```

### `GET /api/v1/auth/me`
Devolve o utilizador autenticado (id, empresa, lote, perfil, permissões).

### `POST /api/v1/auth/logout`
Revoga o token atual.

## Processos

| Método | Rota | Permissão | Descrição |
|---|---|---|---|
| GET | `/processes/queue` | — | Fila Inteligente™ |
| GET | `/processes/mine` | — | Meus Processos |
| POST | `/processes` | `process.create` | Cria processo (RN-0017 a RN-0024) |
| GET | `/processes/{id}` | — | Detalhe + Timeline + DNA do Processo™ |
| POST | `/processes/{id}/assume` | `process.assume` | Assumir Processo |
| POST | `/processes/{id}/close` | `process.close` | Concluir Processo |
| POST | `/processes/{id}/reopen` | `process.reopen` | Reabrir Processo |
| POST | `/processes/{id}/notes` | — | Adicionar Observação |
| POST | `/processes/{id}/attachments` | — | Enviar Anexo (multipart/form-data, campo `file`) |

Exemplo — criar processo:
```bash
curl -X POST http://localhost:8000/api/v1/processes \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
        "plate": "AA-12-BB",
        "customer_name": "João Silva",
        "customer_phone": "912345678",
        "subject_id": 1,
        "priority_id": 2,
        "description": "Cliente reporta ruído no motor."
      }'
```

Resposta possível quando existe um processo recém-encerrado para a mesma
matrícula+assunto (Janela de Reincidência, RN-0021 a RN-0024):
```json
{ "data": { "status": "needs_reopen_decision", "process_id": 42, "process_number": "PR-2026-00000042" } }
```
Reenvie com `"reopen_if_eligible": true` para reabrir, ou `false` para forçar um processo novo.

## Notificações

| Método | Rota | Descrição |
|---|---|---|
| GET | `/notifications` | Lista + contador de não lidas |
| POST | `/notifications/{id}/read` | Marca como lida |

## Códigos de erro

| Status | Significado |
|---|---|
| 401 | Token em falta, inválido, expirado ou revogado |
| 403 | Autenticado mas sem a permissão necessária |
| 404 | Recurso não encontrado |
| 422 | Validação falhou ou regra de negócio impediu a ação |

## Limitações da v1

- Só cobre Processo, Observações, Anexos e Notificações — os módulos de
  Administração (Empresas/Filiais/Utilizadores) ainda não têm endpoints.
- Sem paginação (`listQueue`/`listAssignedTo` devolvem tudo) — a acrescentar
  quando o volume justificar.
- Sem rate limiting dedicado à API (o `RateLimitMiddleware` mencionado no
  capítulo 11 do PRD ainda não foi implementado).
