# fiscal_nfe_itens – Estrutura NF-e 55 e mapeamento XML

Script de alteração: `sql/alter_fiscal_nfe_itens_nfe55.sql`  
Preenchimento: `fiscal/api/documentos_fiscais_v2.php` → `salvarItensNFeDoXml()`

## Objetivo

- Importar qualquer NF-e (combustível, peças, pneus, material).
- Guardar dados fiscais essenciais sem perder informação.
- Servir impressão, relatórios e futura integração com frota (custo por rota/KM).

## Campos da tabela (após ALTER)

| Grupo | Campo | XML (exemplo) | Observação |
|-------|------|----------------|------------|
| **Identificação** | numero_item_nfe | det[@nItem] | nItem do item |
| | gtin | prod->cEAN / cEANTrib | "SEM GTIN" gravado vazio |
| | cest | prod->CEST | Quando existir |
| **Impostos** | cst_icms | imposto->ICMS->*->CST/CSOSN | Primeiro filho de ICMS |
| | cst_pis | imposto->PIS->*->CST | |
| | cst_cofins | imposto->COFINS->*->CST | |
| | cst_ipi | imposto->IPI->*->CST | |
| | valor_icms | ICMS->*->vICMS | |
| | valor_icms_st | ICMS->*->vICMSST / vST | |
| | valor_ipi | IPI->*->vIPI | |
| | valor_pis | PIS->*->vPIS | |
| | valor_cofins | COFINS->*->vCOFINS | |
| | valor_total_tributos | imposto->vTotTrib | |
| **Complementares** | valor_desconto | prod->vDesc | |
| | valor_frete | prod->vFrete | |
| | valor_seguro | prod->vSeg | |
| | valor_outros | prod->vOutro | |
| **Adicional** | informacao_adicional_item | prod->infAdProd / det->infAdProd | |
| **Combustível** | anp_codigo | prod->comb->cProdANP | Só se existir comb |
| | anp_descricao | prod->comb->descANP | |
| | percentual_biodiesel | prod->comb->pBio | |
| | uf_consumo | prod->comb->UFCons | |
| | icms_monofasico_valor | ICMS->*->vICMSMonoRet | Ex.: ICMS 60/61 |
| | icms_monofasico_aliquota_adrem | ICMS->*->adRemICMSRet | |
| **Frota (infCpl)** | placa | infCpl texto "Placa: XXX-9X99" | Regex |
| | motorista_nome | infCpl "Motorista: Nome" | Regex |
| | motorista_cpf | infCpl "CPF: ..." | Regex |
| | km_veiculo | infCpl "KM: 123456" | Regex |
| **Vinculação** | veiculo_id | — | Preenchido pelo sistema |
| | rota_id | — | Preenchido pelo sistema |

## Regra combustível no PHP

```php
if ($prod->comb ?? $prod->children($ns)->comb ?? null) {
    // preenche anp_codigo, anp_descricao, percentual_biodiesel, uf_consumo
    // e icms_monofasico_* quando vier no grupo ICMS
} else {
    // campos de combustível ficam null
}
```

## Execução

1. Rodar o ALTER (idempotente):
   ```bash
   mysql -u usuario -p banco < sql/alter_fiscal_nfe_itens_nfe55.sql
   ```
2. Ao receber/consultar NF-e (upload XML ou SEFAZ), os itens são gravados com todos os campos disponíveis no XML.
3. Se a tabela ainda não tiver as colunas novas, o código faz fallback e grava só os campos originais (comportamento anterior).

## Impressão

`fiscal/impressao/nfe.php` usa `valor_icms`, `valor_pis`, `valor_cofins` (com fallback para `icms_valor`, etc.) para totais de impostos por item.
