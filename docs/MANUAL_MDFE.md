# Manual MDF-e — Sistema Frotec

**Referência oficial:** [Portal MDF-e](https://dfe-portal.svrs.rs.gov.br/Mdfe) — MOC **4.00**.  
**Pacotes PHP:** `nfephp-org/sped-mdfe`, `nfephp-org/sped-da` (DAMDFE).

## Escopo no Frotec

Manifesto eletrônico que **consolida** CT-e(s) e/ou NF-e(s) de uma viagem, com dados rodoviários (placa, condutores, RNTRC, CIOT, vale pedágio).

| Funcionalidade | Status |
|----------------|--------|
| Wizard completo 4.00 | ✅ |
| Emissão / consulta SEFAZ | ✅ |
| Cancelamento (24h) | ✅ |
| Encerramento | ✅ |
| Inclusão condutor (evento) | ✅ |
| DAMDFE PDF (sped-da) | ✅ |
| Vínculo CIOT PEF | ✅ |
| Encerramento CIOT ao encerrar MDF-e | ✅ |

**Tela:** `fiscal/pages/mdfe.php`  
**API:** `fiscal/api/documentos_fiscais_v2.php`  
**Serviços:** `MdfeService`, `MdfeXmlBuilder`, `MdfeContextLoader`, `MdfeDamdfeSped`  
**Helpers:** `mdfe_wizard_helpers.php`

---

## Pré-requisitos

1. `composer install` — `sped-mdfe` + `sped-da`
2. SQL:
   - `sql/fiscal_mdfe_meta_v1.sql`
   - `sql/mdfe_fase2_pef_link.sql`
   - `sql/mdfe_fase3_completo.sql`
3. Certificado A1, RNTRC, ambiente SEFAZ
4. CT-e **autorizado** e/ou NF-e disponível
5. Veículo + motorista (frota ou placa/CPF manual)

---

## Banco de dados

### `fiscal_mdfe`

| Coluna | Uso |
|--------|-----|
| `numero_mdfe`, `chave_acesso`, `serie_mdfe` | Identificação |
| `veiculo_id`, `motorista_id` | Frota |
| `uf_inicio`, `uf_fim` | Percurso UF |
| `municipio_carregamento`, `municipio_descarregamento` | Municípios |
| `cod_mun_carreg_ibge`, `cod_mun_desc_ibge` | IBGE |
| `tipo_emitente`, `tipo_transportador` | Regras ANTT/SEFAZ |
| `rod_rntrc`, `rod_placa`, `rod_tipo_rodado`, `rod_tipo_carroceria` | Rodoviário |
| `ufs_percurso` | JSON UFs intermediárias |
| `peso_total_carga`, `valor_total_carga` | Totalizadores |
| `xml_mdfe`, `protocolo_autorizacao` | Pós-SEFAZ |
| `status` | `rascunho`, `autorizado`, `encerrado`, `cancelado` |
| `rota_id` | Vínculo viagem |

### Tabelas relacionadas

| Tabela | Conteúdo |
|--------|----------|
| `fiscal_mdfe_cte` | CT-e vinculados |
| `fiscal_mdfe_nfe` | NF-e vinculadas |
| `fiscal_mdfe_documentos` | Documentos por município descarga |
| `fiscal_mdfe_condutores` | Múltiplos condutores |
| `fiscal_mdfe_reboques` | Reboques |
| `fiscal_mdfe_eventos` | Eventos SEFAZ |
| `pef_ciot_operacoes.mdfe_id` | CIOT PEF vinculado |

---

## Fluxo na interface

1. **Sistema Fiscal → MDF-e**
2. **Novo MDF-e** — wizard abas:
   - **Dados:** UF início/fim, municípios, veículo, motorista, rota
   - **Documentos:** CT-e/NF-e do sistema ou chaves manuais
   - **Rodoviário:** RNTRC, placa, condutores, reboques, **CIOT** (importar PEF), vale pedágio, pagamentos frete
   - **Seguro / Produto predominante** (quando aplicável)
3. **Salvar MDF-e** → `criar_mdfe` (rascunho) — aba **Conformidade** valida checklist antes de salvar
4. **Enviar SEFAZ** → `enviar_sefaz` com `tipo_documento=mdfe`
5. Autorizado → **DAMDFE PDF**
6. Durante viagem: **Incluir condutor** (troca motorista)
7. Fim viagem: **Encerrar**
8. Cancelamento só dentro de **24h** da autorização

### Checklist de conformidade (aba 7 do wizard)

Antes de salvar, o sistema verifica: veículo, motorista/CPF, documentos, CT-e/NF-e conforme tipo emitente, RNTRC, CIOT (TAC), vale pedágio (aviso), dados rodoviários e peso. Itens em vermelho **bloqueiam** o salvamento.

### CIOT PEF no wizard

1. Declarar CIOT em **CIOT (PEF ANTT)** com status `declarado`
2. No MDF-e → Rodoviário → **Importar do PEF**
3. Ao salvar, `pef_ciot_operacoes.mdfe_id` é gravado
4. Se `rota_id` informado e CIOT livre na rota → importação automática

---

## APIs — `documentos_fiscais_v2.php`

### Documentos disponíveis

```
GET ?action=documentos_para_mdfe
```

CT-e autorizados sem manifesto ativo; NF-e elegíveis.

### Criar / atualizar rascunho

```
POST action=criar_mdfe
```

**Campos principais:**

| Campo | Descrição |
|-------|-----------|
| `cte_ids[]` | IDs CT-e |
| `origem_nfe_ids[]` | IDs NF-e diretas |
| `veiculo_id`, `motorista_id` | Frota |
| `rota_id` | Viagem (auto CIOT) |
| `uf_inicio`, `uf_fim` | Obrigatórios |
| `municipio_carregamento`, `municipio_descarregamento` | Obrigatórios |
| `tipo_emitente` | 1=prestador, 2=carga própria, 3=globalizado |
| `tipo_transportador` | 1=ETC, 2=TAC, 3=CTC |
| `doc_documentos_json` | Documentos por município descarga |
| `rod_condutores_json` | Lista condutores |
| `rod_reboques_json` | Reboques |
| `rod_ciot_json` | CIOT(s) |
| `rod_vales_pedagio_json` | Vale pedágio |
| `rod_pagamentos_frete_json` | Pagamento frete |
| `ufs_percurso` | JSON `["SP","PR"]` |
| `modo` | `emissao` (validação estrita) |

Exemplo mínimo:

```
POST action=criar_mdfe
cte_ids[]=42
veiculo_id=3
motorista_id=7
uf_inicio=SP
uf_fim=PR
municipio_carregamento=São Paulo
municipio_descarregamento=Curitiba
tipo_emitente=1
tipo_transportador=1
rod_rntrc=12345678
rod_placa=ABC1D23
```

Validação: `validarMDFeRegras` — retorna `erros[]` e `warnings[]` com códigos tipo `E0xx`.

### Validar sem enviar

```
POST action=validar_mdfe
(+ mesmos campos do wizard)
```

### Enviar SEFAZ

```
POST action=enviar_sefaz
id=15
tipo_documento=mdfe
```

Ou fila assíncrona:

```
POST action=enfileirar_envio_fiscal
id=15
tipo_documento=mdfe
```

Lock `em_envio` evita envio duplicado (10 min).

### Eventos MDF-e

#### Cancelar

```
POST action=cancelar_mdfe
id=15
justificativa=Cancelamento por erro na composicao da carga
```

Desvincula CIOT PEF (`sf_pef_desvincular_mdfe`).

#### Encerrar

```
POST action=encerrar_mdfe
id=15
uf_encerramento=PR
municipio_encerramento=Curitiba
cod_mun_encerramento=4106902
```

Encerra CIOT vinculado na ANTT quando configurado (`sf_pef_encerrar_vinculados_mdfe`).

#### Incluir condutor

```
POST action=incluir_condutor_mdfe
id=15
motorista_id=9
```

Evento SEFAZ + atualiza `motorista_id` no MDF-e.

### Consulta / detalhe

```
GET ?action=get&tipo=mdfe&id=15
POST action=consultar_status_cte_sefaz  (status webservice — nome legado, serve MDF-e também via MdfeService)
```

### Acompanhamento viagem

```
GET ?action=acompanhar_viagem&mdfe_id=15
POST action=atualizar_status_viagem
```

---

## Downloads

```
GET fiscal/api/download_mdfe_pdf.php?id={mdfe_id}
```

Logo empresa: `fiscal_mdfe_logo_path()` no DAMDFE sped-da.

---

## XML — grupos principais

| Grupo | Origem |
|-------|--------|
| `ide` | MDF-e + percurso |
| `emit` | Config fiscal |
| `infModal/rodo` | Placa, RNTRC, CIOT, vale pedágio |
| `veicTracao` / `veicReboque` | Veículo + reboques |
| `condutor` | Motoristas (múltiplos) |
| `infDoc` | CT-e e NF-e vinculados |
| `tot` | Peso/valor calculados |
| `seg` | Seguros (wizard) |

---

## Integração com CT-e, NF-e e CIOT

```
                    ┌─────────────┐
  fiscal_nfe ──────►│             │
                    │   MDF-e     │◄──── pef_ciot_operacoes
  fiscal_cte ──────►│  (viagem)   │
                    └─────────────┘
                           │
                    veiculos / motoristas / rotas
```

**Ordem típica:** NF-e → CT-e → (CIOT se TAC) → MDF-e → encerramento.

---

## Regras de validação (resumo)

- Pelo menos 1 CT-e ou NF-e (sistema ou chave manual)
- Veículo e motorista obrigatórios
- Tipo emitente/transportador coerente com documentos
- TAC: CIOT frequentemente obrigatório no rodoviário
- Vale pedágio: validado quando `rota_tem_pedagio=1`
- CT-e deve estar `autorizado` para vincular

Versão regras: `NT2025.001` / `validarMDFeRegras`.

---

## Homologação

1. Composer + SQL fase 2 e 3
2. Emitir CT-e de teste
3. Wizard MDF-e → salvar → enviar
4. DAMDFE PDF
5. Encerrar manifesto
6. (Opcional) Cancelar outro MDF-e < 24h
7. Testar inclusão condutor em viagem longa

---

## Arquivos de referência

| Doc | Conteúdo |
|-----|----------|
| [`MDFE_FASE2.md`](MDFE_FASE2.md) | DAMDFE + CIOT PEF |
| [`MDFE_FASE3.md`](MDFE_FASE3.md) | Wizard completo |
| [`PRODUCAO_COMPOSER_FISCAL.md`](PRODUCAO_COMPOSER_FISCAL.md) | Deploy |

**Ver também:** [`MANUAL_CTE.md`](MANUAL_CTE.md), [`MANUAL_CIOT_ANTT.md`](MANUAL_CIOT_ANTT.md), [`MANUAL_FISCAL_INDICE.md`](MANUAL_FISCAL_INDICE.md)
