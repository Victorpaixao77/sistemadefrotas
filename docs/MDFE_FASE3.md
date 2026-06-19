# MDF-e Fase 3 — Wizard completo e vínculos

**Manual completo MDF-e:** [`MANUAL_MDFE.md`](MANUAL_MDFE.md)

## Objetivo

Completar o assistente (wizard) com persistência alinhada ao manual MDF-e 4.00 e vínculos com CT-e, NF-e e PEF/CIOT.

## SQL

```bash
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/mdfe_fase3_completo.sql
```

- `fiscal_mdfe.ufs_percurso` (JSON)
- `fiscal_mdfe_documentos.cte_id` / `nfe_id`
- `fiscal_mdfe_condutores`
- `fiscal_mdfe_reboques`

## Backend

| Arquivo | Função |
|---------|--------|
| `fiscal/includes/mdfe_wizard_helpers.php` | Normalização POST, resolver CT-e/NF-e/veículo/motorista, persistência estendida, logo DAMDFE |
| `fiscal/api/documentos_fiscais_v2.php` | `criar_mdfe` integrado; `documentos_para_mdfe`; PEF desvincula no cancelar; encerra CIOT no encerrar |
| `fiscal/includes/MdfeContextLoader.php` | Carrega percurso, condutores, reboques |
| `fiscal/includes/MdfeXmlBuilder.php` | XML: múltiplos condutores, percurso, reboques |

## API

- `GET documentos_fiscais_v2.php?action=documentos_para_mdfe` — CT-e autorizados e NF-e disponíveis (não vinculados a manifesto ativo).
- `POST action=criar_mdfe` — aceita wizard (`uf_carga`, `doc_documentos_json`, `rod_condutores_json`, `rod_reboques_json`, `ufs_percurso`, `veiculo_id`/`motorista_id` ou placa+CPF).

## Wizard (UI)

- Aba **Dados**: veículo e motorista da frota.
- Aba **Rodoviário → Reboque**: CRUD de reboques.
- Aba **Documentos**: vincular CT-e/NF-e do sistema + chaves manuais.
- Botão **Salvar MDF-e** — grava rascunho via `criar_mdfe`.

## PEF / CIOT

- Cancelar MDF-e → `sf_pef_desvincular_mdfe`
- Encerrar MDF-e → `sf_pef_encerrar_vinculados_mdfe` (avisos na resposta, não bloqueia SEFAZ)

## DAMDFE

- Logo da empresa em `fiscal_mdfe_logo_path()` (sped-da `logoParameters` ou mPDF resumido).

## Fluxo sugerido

1. Novo MDF-e → wizard manual ou importar CT-e/NF-e.
2. Preencher rodoviário (placa, condutores, CIOT PEF, reboques opcional).
3. **Salvar MDF-e** → emitir na lista → PDF DAMDFE.
4. Encerrar → sincroniza encerramento CIOT na ANTT quando vinculado.
