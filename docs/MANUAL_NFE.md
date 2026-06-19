# Manual NF-e — Sistema Frotec

**Referência oficial:** [Portal NF-e](https://www.nfe.fazenda.gov.br/portal/) — MOC 7.0, leiaute **4.00**.  
**Pacote PHP:** `nfephp-org/sped-nfe` + `nfephp-org/sped-da` (DANFE).

## Escopo no Frotec

O módulo cobre dois fluxos distintos:

| Fluxo | Tabela | Uso na frota |
|-------|--------|--------------|
| **NF-e recebida** (cliente → transportadora) | `fiscal_nfe_clientes` | Base para emitir CT-e |
| **NF-e emitida** (transportadora emite) | `fiscal_nfe_emitidas` | Emissão própria modelo 55 |

**Tela:** `fiscal/pages/nfe.php`  
**API:** `fiscal/api/documentos_fiscais_v2.php`  
**Serviço:** `fiscal/includes/NFeService.php`  
**Emissão XML:** `fiscal/includes/NFeEmissaoBuilder.php`

---

## Pré-requisitos

1. `composer install` na raiz (`sped-nfe`, `sped-da`)
2. Certificado A1 em `fiscal_config_empresa`
3. CNPJ, IE, código município IBGE configurados
4. Para emissão própria: `fiscal/database/create_fiscal_nfe_emitidas.sql`

---

## Banco de dados

### NF-e recebida (`fiscal_nfe_clientes`)

| Coluna | Descrição |
|--------|-----------|
| `chave_acesso` | Chave 44 dígitos |
| `numero_nfe`, `serie_nfe` | Numeração |
| `cliente_cnpj`, `cliente_razao_social` | Emitente da nota (seu cliente) |
| `cliente_remetente`, `cnpj_remetente` | Remetente da carga |
| `cliente_destinatario`, `cnpj_destinatario` | Destinatário |
| `valor_total`, `peso_carga`, `volumes` | Totais para CT-e/MDF-e |
| `xml_nfe` | XML completo (NFe ou nfeProc) |
| `status` | `recebida`, `em_transporte`, `cancelada`, etc. |
| `cte_id` | CT-e que transporta esta NF-e |
| `protocolo_autorizacao` | nProt SEFAZ |

### Itens (`fiscal_nfe_itens`)

Produtos parseados do XML — NCM, CFOP, quantidades, pesos.

### NF-e emitida (`fiscal_nfe_emitidas`)

Registro após `emitir_nfe_sefaz` — XML assinado, protocolo, destinatário.

### Eventos (`fiscal_eventos_fiscais`)

Cancelamento, CC-e, manifestação destinatário, inutilização.

### Distribuição (`fiscal_distribuicao_nsu`)

Controle NSU para sincronização DistDFe por CNPJ.

---

## Fluxos na interface

### 1. Receber NF-e do cliente

Opções na tela NF-e:

- **Upload XML** — `receber_nfe_xml`
- **Chave + consulta SEFAZ** — `receber_nfe_sefaz`
- **Cadastro manual** — `receber_nfe_manual`
- **Sincronizar CNPJ (DistDFe)** — `sincronizar_nfe_cnpj`

Após recebimento, status típico: **`recebida`**. A NF-e fica disponível no wizard CT-e.

### 2. Manifestação do destinatário

Transportadora deve manifestar ciência/confirmação quando aplicável:

- **API:** `processar_evento` com `tipo_evento=manifestacao`
- Tipos: `ciencia`, `confirmacao`, `desconhecimento`, `nao_realizada`

### 3. Emitir NF-e própria

Formulário de emissão → `emitir_nfe_sefaz` (homologação primeiro).

### 4. Eventos pós-autorização

- Cancelamento (prazo SEFAZ)
- Carta de Correção (CC-e)
- Inutilização de numeração

---

## APIs — `documentos_fiscais_v2.php`

**Base:** `POST` ou `GET` com sessão autenticada.  
**CSRF:** obrigatório em POST.  
**Parâmetro comum:** `action=<nome>`

### Listagem

```
GET ?action=list&tipo=nfe
GET ?action=list&tipo=nfe_emitida
```

### Receber NF-e

#### `receber_nfe_xml`

```
POST action=receber_nfe_xml
Content-Type: multipart/form-data

arquivo_xml: (file)
```

Grava XML, extrai chave, rem/dest, itens e totais.

#### `receber_nfe_sefaz`

```
POST action=receber_nfe_sefaz
chave_acesso=35260112345678901234567890123456789012345678
```

Consulta SEFAZ e baixa XML autorizado.

#### `receber_nfe_manual`

```
POST action=receber_nfe_manual
numero_nfe=123
serie_nfe=1
chave_acesso=...
cliente_cnpj=...
valor_total=1500.00
```

#### `sincronizar_nfe_cnpj`

```
POST action=sincronizar_nfe_cnpj
```

Varre DistDFe a partir do último NSU salvo.

### Emissão própria

#### `emitir_nfe_sefaz`

```
POST action=emitir_nfe_sefaz
Content-Type: application/json
```

Corpo JSON (resumo):

```json
{
  "natureza_operacao": "Venda de mercadoria",
  "serie": 1,
  "crt": 1,
  "csosn": "102",
  "tPag": "01",
  "modFrete": "9",
  "dest": {
    "CNPJ": "00000000000000",
    "xNome": "Cliente",
    "indIEDest": 1,
    "IE": "123456789",
    "enderDest": {
      "xLgr": "Rua A", "nro": "100", "xBairro": "Centro",
      "cMun": "3550308", "xMun": "São Paulo", "UF": "SP", "CEP": "01000000"
    }
  },
  "itens": [{
    "cProd": "1", "xProd": "Produto", "NCM": "84713012",
    "CFOP": "5102", "uCom": "UN", "qCom": 1, "vUnCom": 100, "vProd": 100
  }]
}
```

- `nNF` opcional — senão usa `sequencias_documentos`
- CRT 1 = Simples Nacional (CSOSN + PIS/COFINS 07)
- CRT 3 = regime normal (ICMS00 no builder)

Resposta: `success`, `nfe_id`, `chave_acesso`, `protocolo`, `cStat`.

### Eventos NF-e

#### `processar_evento`

```
POST action=processar_evento
tipo_evento=cancelamento|cce|manifestacao|inutilizacao
documento_id=123
justificativa=Texto mínimo 15 caracteres
```

**Cancelamento:**

```
tipo_evento=cancelamento
documento_id=<id fiscal_nfe_clientes>
justificativa=Erro na operacao de transporte
```

**CC-e:**

```
tipo_evento=cce
documento_id=123
correcao=Texto da correção
n_seq_evento=1
```

**Manifestação:**

```
tipo_evento=manifestacao
documento_id=123
manifestacao_tipo=ciencia
```

**Inutilização** (sem documento_id):

```
tipo_evento=inutilizacao
serie=1
n_ini=10
n_fin=10
justificativa_inutil=Numeracao nao utilizada
```

Alternativa cancelamento via API dedicada:

```
POST fiscal/api/fiscal_events.php
Content-Type: application/json

{"action":"cancelar","documento_tipo":"nfe","documento_id":123,"justificativa":"..."}
```

### Consulta / detalhe

```
GET ?action=get&tipo=nfe&id=123
```

### Totais dashboard

```
GET ?action=totals
```

---

## Downloads

| Arquivo | URL |
|---------|-----|
| XML NF-e recebida | `fiscal/api/download_nfe_xml.php?id={id}` |
| DANFE PDF | `fiscal/api/download_nfe_pdf.php?id={id}` |

Engine PDF: `sped-da` preferencial; fallback mPDF.

---

## Integração com CT-e e MDF-e

```
fiscal_nfe_clientes (recebida)
    ├── cte_id → fiscal_cte
    └── status em_transporte após CT-e autorizado

MDF-e pode incluir NF-e diretamente (sem CT-e) via wizard documentos
```

Regra CT-e: NF-e deve estar `recebida` (rascunho) ou `recebida`/`em_transporte` (emissão).

---

## Status típicos

| Status | Significado |
|--------|-------------|
| `recebida` | Pronta para CT-e |
| `em_transporte` | Vinculada a CT-e em trânsito |
| `cancelada` | Cancelada na SEFAZ |
| `consultada_sefaz` | Atualizada via consulta |

---

## Homologação SEFAZ

1. Ambiente **homologação** na config fiscal
2. Receber ou emitir 1 NF-e teste
3. Manifestar ciência se necessário
4. Testar cancelamento/CC-e em documento de teste
5. Validar download XML e DANFE
6. Virar **produção** somente após OK

---

## Limitações conhecidas

- Emissão própria: builder otimizado para Simples (CRT 1) e regime normal básico (CRT 3)
- NF-e de serviço, importação, combustível, etc.: exigem extensão do builder
- Este manual não reproduz regras fiscais do MOC — consulte NTs para CFOP, CST e obrigatoriedades por UF

**Ver também:** [`fiscal/docs/EMISSAO_NFE.md`](../fiscal/docs/EMISSAO_NFE.md), [`MANUAL_FISCAL_INDICE.md`](MANUAL_FISCAL_INDICE.md)
