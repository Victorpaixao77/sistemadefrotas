# CT-e: Tabela fiscal_cte_itens e geração de XML/PDF

## Tabela `fiscal_cte_itens`

Um registro por CT-e, no mesmo estilo de `fiscal_nfe_itens`: guarda dados complementares do CT-e (modelo 57) para montar o XML completo e o DACTE.

| Campo | XML (cteProc) | Descrição |
|-------|----------------|-----------|
| tomador_cnpj | toma4/CNPJ | CNPJ do tomador do serviço (contratante) |
| tomador_nome | toma4/xNome | Razão social do tomador |
| valor_prestacao | vPrest/vTPrest | Valor total da prestação |
| valor_receber | vPrest/vRec | Valor líquido a receber |
| comp_nome / comp_valor | vPrest/Comp | Nome e valor do componente (ex: FRETE VALOR BASE) |
| icms_cst, icms_vbc, icms_picms, icms_vicms | imp/ICMS/ICMS00 | CST, base, alíquota e valor do ICMS |
| valor_carga | infCTeNorm/infCarga/vCarga | Valor da carga |
| produto_predominante | infCTeNorm/infCarga/proPred | Produto predominante (ex: SOJA) |
| inf_complementar | infAdic/infCpl | Informações complementares (placa, motorista, etc.) |
| numero_protocolo, data_protocolo, status_protocolo, motivo_protocolo, versao_aplicativo | protCTe/infProt | Dados do protocolo de autorização |

## Criar a tabela

Execute no MySQL:

```sql
-- sql/create_fiscal_cte_itens.sql
```

## Geração de XML (cteProc)

- **Arquivo:** `fiscal/api/download_cte_xml.php` (GET ou POST `id` = ID do CT-e).
- Se existir `fiscal_cte.xml_cte`, o conteúdo é enviado.
- Caso contrário, o XML é montado por `fiscal/includes/CteXmlHelper.php` a partir de:
  - `fiscal_cte` (chave, número, série, data, natureza, valor_total, observações)
  - `fiscal_cte_itens` (tomador, vPrest, imp, infCarga, infAdic, protCTe)
  - `empresa_clientes` (emitente: CNPJ, razão social, endereço, etc.)
- O XML gerado segue o exemplo cteProc 3.00 (ide, emit, toma4, vPrest, imp, infCTeNorm, infAdic, protCTe).
- Após gerar, o conteúdo pode ser salvo em `fiscal_cte.xml_cte` para o próximo download.

## Geração de PDF (DACTE)

- **Arquivo:** `fiscal/api/download_cte_pdf.php` (GET ou POST `id`).
- Se existir `fiscal_cte.pdf_cte` e o arquivo existir no disco, o PDF é enviado.
- Senão, usa o XML (do banco ou gerado pelo CteXmlHelper) e `fiscal/includes/DactePdfHelper.php` para montar o HTML do DACTE e gerar o PDF com mPDF.

## Salvando itens do CT-e

- **API:** `documentos_fiscais_v2.php` com `action=salvar_cte_itens` e POST com `cte_id` e os campos acima (tomador_cnpj, tomador_nome, valor_prestacao, valor_receber, etc.).
- Faz INSERT ou UPDATE em `fiscal_cte_itens` conforme já exista registro para o `cte_id`.

## Ações na tela CT-e

Na listagem de CT-e (`fiscal/pages/cte.php`), cada linha tem:

- **Visualizar** – modal com dados do CT-e.
- **Gerar / Download XML** – abre em nova aba o download do XML (cteProc).
- **Gerar / Download PDF** – abre em nova aba o download do PDF (DACTE).
