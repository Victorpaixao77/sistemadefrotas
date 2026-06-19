# Manual CT-e — Sistema Frotec

**Referência oficial:** [Portal CT-e](https://www.cte.fazenda.gov.br/portal/) — MOC **4.00**, modelo **57**, modal rodoviário.  
**Pacotes PHP:** `nfephp-org/sped-cte`, `nfephp-org/sped-da` (DACTE).

## Escopo no Frotec

| Implementado | Fora do escopo |
|--------------|----------------|
| CT-e normal rodoviário 4.00 | CT-e OS (modelo 67) |
| Wizard NF-e + veículo + motorista | GTV-e, multimodal |
| Eventos: cancelar, CC-e, CE, desacordo | EPEC contingência (110113) |
| DACTE PDF (sped-da) | Modais aéreo/aquaviário |
| Consulta SEFAZ / importar XML | |

**Tela:** `fiscal/pages/cte.php`  
**API principal:** `fiscal/api/documentos_fiscais_v2.php`  
**Cancelamento:** `fiscal/api/fiscal_events.php`  
**Serviços:** `CTeService`, `CteXmlBuilder`, `CteContextLoader`, `CteDacteSped`

---

## Pré-requisitos

1. `composer install` — `sped-cte` + `sped-da`
2. SQL: `sql/cte_layout_v4.sql`, `sql/cte_fase2_eventos.sql`
3. Certificado A1 + **RNTRC** na config fiscal
4. NF-e(s) com status `recebida` em `fiscal_nfe_clientes`
5. Veículo e motorista cadastrados na frota

---

## Banco de dados

### `fiscal_cte`

| Coluna | Uso |
|--------|-----|
| `numero_cte`, `serie_cte`, `chave_acesso` | Identificação |
| `veiculo_id`, `motorista_id` | Frota |
| `origem`, `destino` + `*_cidade`, `*_estado` | Rota |
| `cod_mun_ini`, `cod_mun_fim`, `cod_mun_env` | IBGE XML |
| `valor_total`, `peso_carga`, `volumes_carga` | Carga/frete |
| `nfe_ids` | JSON IDs NF-e vinculadas |
| `rntrc`, `modal` (01), `tp_cte` (0) | Rodoviário |
| `xml_cte`, `protocolo_autorizacao` | Pós-SEFAZ |
| `status` | `rascunho` → `autorizado` / `cancelado` |
| `mdfe_id` | Manifesto vinculado |
| `cce_seq` | Sequência CC-e |

### `fiscal_cte_itens`

Tomador, remetente, destinatário (campos `rem_*`, `dest_*`), valores de prestação.

### `fiscal_cte_eventos`

Histórico CC-e, comprovante entrega, desacordo, protocolos.

### Vínculo NF-e

`fiscal_nfe_clientes.cte_id` aponta para o CT-e que transporta a nota.

---

## Fluxo na interface

1. Menu **Sistema Fiscal → CT-e**
2. **Novo CT-e** — wizard:
   - Selecionar NF-e(s) `recebida`
   - Veículo + motorista
   - Origem/destino, valor frete, peso/volumes
3. **Salvar** → status `rascunho`
4. **Enviar SEFAZ** → `emitir_cte_sefaz`
5. Autorizado → download **XML** e **DACTE PDF**
6. Eventos (toolbar/lista): CC-e, comprovante entrega, desacordo tomador
7. **Cancelar** (ícone) → `fiscal_events.php` (prazo SEFAZ)

---

## APIs — `documentos_fiscais_v2.php`

Todas exigem sessão + CSRF em POST.

### Dados para o wizard

```
GET ?action=documentos_para_cte
```

Retorna: NF-e disponíveis, veículos, motoristas, config RNTRC.

### Criar rascunho

```
POST action=criar_cte
nfe_ids[]=12
nfe_ids[]=15
veiculo_id=3
motorista_id=7
origem=São Paulo - SP
destino=Curitiba - PR
valor_frete=2500.00
peso_total=12000
volumes_total=48
```

Resposta: `cte_id`, `numero_cte`, `chave_acesso`, `validation_version`, `warnings`.

Validação (`validarCTeRegras`): NF-e obrigatória, status `recebida`, veículo/motorista da empresa.

### Emitir na SEFAZ

```
POST action=emitir_cte_sefaz
cte_id=42
```

Monta XML 4.00 via `CteXmlBuilder`:

- `ide`, `emit`, `rem`, `dest`
- `vPrest`, `infCarga`
- `infDoc/infNFe` (chaves das NF-e)
- `infModal/rodo` — RNTRC, placa, condutor

Resposta: `success`, `protocolo`, `cStat`, `chave_acesso`.

### Consultas

```
POST action=consultar_cte_sefaz
cte_id=42

GET ?action=consultar_status_cte_sefaz
```

### Importar XML externo

```
POST action=importar_xml_cte
xml=... (conteúdo)
```

### Salvar itens manualmente

```
POST action=salvar_cte_itens
cte_id=42
itens_json=[...]
```

### Detalhe

```
GET ?action=get&tipo=cte&id=42
```

Inclui `nfes_vinculadas`, `itens`, `eventos`.

---

## Eventos CT-e

### Carta de Correção (110110)

```
POST action=cte_cce
cte_id=42
correcoes_json=[{"grupo":"ide","campo":"xObs","valor":"Correção texto"}]
```

Ou campos simplificados conforme `cte_event_helpers.php`.

### Prestação em desacordo — tomador (610110)

```
POST action=cte_desacordo
cte_id=42
observacao=Prestação em desacordo com o contratado
```

### Comprovante de entrega (110180)

```
POST action=cte_comprovante_entrega
cte_id=42
data_entrega=2026-06-16T14:30:00
nome_recebedor=João Silva
doc_recebedor=12345678900
```

### Cancelar comprovante (110181)

```
POST action=cte_cancelar_comprovante
cte_id=42
n_prot_ce=...
```

### Inutilização numeração

```
POST action=cte_inutilizar
serie=1
n_ini=100
n_fin=100
justificativa=Numeracao nao utilizada em homologacao
```

### Cancelamento CT-e (110111)

```
POST fiscal/api/fiscal_events.php
Content-Type: application/json

{
  "action": "cancelar",
  "documento_tipo": "cte",
  "documento_id": 42,
  "justificativa": "Erro na emissao do conhecimento"
}
```

Requer `protocolo_autorizacao` e chave válida.

---

## Downloads

| Tipo | URL |
|------|-----|
| DACTE PDF | `fiscal/api/download_cte_pdf.php?id={cte_id}` |
| XML | via detalhe / campo `xml_cte` |

Header de resposta PDF: `X-Fiscal-Dacte-Engine: sped-da` quando usa biblioteca oficial.

---

## XML — grupos principais (MOC 4.00)

| Grupo | Origem no sistema |
|-------|-------------------|
| `ide` | `fiscal_cte` + config empresa |
| `emit` | `fiscal_config_empresa` |
| `rem` / `dest` | Parse NF-e ou `fiscal_cte_itens` |
| `vPrest` | Valor frete wizard |
| `infCarga` | Peso/volumes NF-e ou wizard |
| `infDoc/infNFe` | Chaves `fiscal_nfe_clientes` |
| `infModal/rodo` | Veículo, motorista, RNTRC |

Mapeamento detalhado: [`fiscal/docs/CTE_ITENS_XML_MAPEAMENTO.md`](../fiscal/docs/CTE_ITENS_XML_MAPEAMENTO.md)

---

## Integração frota

```
rotas / viagem
    └── NF-e recebida
            └── criar_cte (veiculo_id, motorista_id)
                    └── emitir_cte_sefaz
                            └── criar_mdfe (cte_ids)
```

Status NF-e atualizado para `em_transporte` após autorização do CT-e.

---

## Legado descontinuado

`fiscal/api/fiscal_cte.php?action=emitir` → **HTTP 410**.  
Use sempre `criar_cte` + `emitir_cte_sefaz`.

---

## Homologação

1. `composer install` + verificar `NFePHP\CTe\Tools`
2. Config homologação + certificado teste
3. NF-e recebida de teste
4. Wizard → emitir → protocolo `100`/`150`
5. DACTE PDF
6. Testar CC-e e cancelamento em documento descartável
7. Vincular em MDF-e de teste

Deploy: [`CTE_MOC_V4_STATUS.md`](CTE_MOC_V4_STATUS.md), [`PRODUCAO_COMPOSER_FISCAL.md`](PRODUCAO_COMPOSER_FISCAL.md)

---

## Limitações

- Apenas modal **rodoviário** (`modal=01`)
- `tpCTe=0` (normal) — substituição/anulação não implementados
- EPEC exige modo contingência no sped-cte (não ativo)

**Ver também:** [`MANUAL_FISCAL_INDICE.md`](MANUAL_FISCAL_INDICE.md), [`MANUAL_MDFE.md`](MANUAL_MDFE.md)
