# MDF-e — Fase 2

**Manual completo MDF-e:** [`MANUAL_MDFE.md`](MANUAL_MDFE.md)

Itens complementares após a integração SEFAZ layout 4.00 (fase 1).

## Status

| Item | Status | Arquivos |
|------|--------|----------|
| DAMDFE PDF (sped-da + fallback mPDF) | Implementado | `fiscal/includes/MdfeDamdfeSped.php`, `fiscal/includes/DamdfePdfHelper.php`, `fiscal/api/download_mdfe_pdf.php`, `mdfe.js` |
| Botão "Importar CIOT do PEF" no wizard | Implementado | `mdfe_wizard_modal.php`, `mdfe.js`, `api/pef_ciot.php` (`disponiveis_mdfe`) |
| Vínculo automático PEF → MDF-e | Implementado | `sql/mdfe_fase2_pef_link.sql`, `criar_mdfe`, `persistMdfeWizardEstruturado` |
| Composer em produção | Pendente (DM) | `docs/PRODUCAO_COMPOSER_FISCAL.md` |

## DAMDFE

- **Preferencial:** `NFePHP\DA\MDFe\Damdfe` (`nfephp-org/sped-da`) com XML em `fiscal_mdfe.xml_mdfe`.
- **Fallback:** resumo HTML via mPDF quando sped-da indisponível ou XML incompleto.
- Download: `fiscal/api/download_mdfe_pdf.php?id={mdfe_id}` — botão na listagem para status autorizado/encerrado.

## CIOT PEF → MDF-e

1. Declarar CIOT em **CIOT (PEF ANTT)** com status `declarado`.
2. No wizard MDF-e, aba **Rodoviário → CIOT**, clique em **Importar do PEF**.
3. Selecione a operação; o sistema preenche número, valor frete e CPF/CNPJ TAC.
4. Ao salvar o MDF-e, `pef_ciot_operacoes.mdfe_id` é atualizado (evita reuso indevido).

### Vínculo automático por rota

Se `criar_mdfe` receber `rota_id` e existir CIOT `declarado` na mesma rota sem `mdfe_id`, o sistema importa automaticamente para o wizard (quando a lista CIOT do POST estiver vazia).

## Migração SQL

```bash
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/mdfe_fase2_pef_link.sql
```

## Próximos incrementos (fase 3 — opcional)

- DAMDFE com logo da empresa
- Desvincular CIOT ao cancelar MDF-e
- Sincronizar encerramento PEF ANTT ao encerrar MDF-e
