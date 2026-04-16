# Emissão de NF-e (modelo 55)

## 1. Banco de dados

Execute o script:

`fiscal/database/create_fiscal_nfe_emitidas.sql`

## 2. Endpoint

`POST` `fiscal/api/documentos_fiscais_v2.php` (sessão autenticada com `empresa_id`)

- **action:** `emitir_nfe_sefaz`
- **Content-Type:** `application/json` **ou** form com `pedido_json`

### Corpo JSON (exemplo)

```json
{
  "natureza_operacao": "Venda de mercadoria",
  "natOp": "Venda de mercadoria",
  "serie": 1,
  "crt": 1,
  "csosn": "102",
  "tPag": "01",
  "modFrete": "9",
  "dest": {
    "CNPJ": "00000000000000",
    "xNome": "Cliente Teste",
    "indIEDest": 1,
    "IE": "123456789",
    "email": "teste@email.com",
    "enderDest": {
      "xLgr": "Rua A",
      "nro": "100",
      "xBairro": "Centro",
      "cMun": "1100205",
      "xMun": "Porto Velho",
      "UF": "RO",
      "CEP": "76800000"
    }
  },
  "itens": [
    {
      "cProd": "1",
      "xProd": "Produto teste",
      "NCM": "84713012",
      "CFOP": "5102",
      "uCom": "UN",
      "qCom": 1,
      "vUnCom": 100.0,
      "vProd": 100.0
    }
  ]
}
```

- **nNF:** opcional; se omitido, usa `sequencias_documentos` (`tipo_documento = NFE`).
- **emitente.enderEmit:** opcional; se omitido, usa `fiscal_config_empresa` + `cidades` (endereço em texto é dividido de forma simples).
- **CRT 1** = Simples Nacional; impostos do item no builder: **CSOSN** (padrão 102), **PIS/COFINS CST 07**.

## 3. Listagem

`GET` `documentos_fiscais_v2.php?action=list&tipo=nfe_emitida`

## 4. Observações

- Certificado A1 e dados da **fiscal_config_empresa** devem estar corretos (CNPJ, IE, município IBGE).
- Ambiente homologação: a SEFAZ pode exigir regras específicas de nome do destinatário; em homologação o NFePHP ajusta o nome automaticamente em `tpAmb=2`.
- **Regime tributário** diferente de Simples exige alterar o builder (ICMS normal, CSTs, etc.).
