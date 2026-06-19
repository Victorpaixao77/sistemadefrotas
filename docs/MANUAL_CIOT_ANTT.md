# Manual CIOT (PEF ANTT) — Sistema Frotec

**Referência oficial:**

- [CIOT Para Todos — ANTT](https://www.gov.br/antt/pt-br/assuntos/cargas/ciot)
- Contrato técnico PEF v1.1: [`docs/pef/dcs_pef_v1-1.pdf`](pef/dcs_pef_v1-1.pdf)

Desde **24/05/2026**, o CIOT é obrigatório para praticamente todas as operações de transporte remunerado de cargas (com exceções regulamentares). O Frotec integra via **PEF** (Pagamento Eletrônico de Frete) — WebService REST JSON com **mTLS**.

> **CIOT no MDF-e** e **CIOT na ANTT** são etapas complementares: primeiro declara na ANTT (este módulo), depois informa o número no MDF-e rodoviário.

**Tela:** `fiscal/pages/ciot.php`  
**API:** `api/pef_ciot.php`  
**Cliente HTTP:** `includes/pef_antt_client.php`  
**Regras/payload:** `includes/pef_ciot_helpers.php`

---

## Escopo no Frotec

| Operação ANTT | API action | Status |
|---------------|------------|--------|
| Declarar operação (gera CIOT) | `declarar` | ✅ |
| Cancelar | `cancelar` | ✅ |
| Encerrar | `encerrar` | ✅ |
| Consultar CIOT | `consultar_ciot` | ✅ |
| Consultar situação transportador | `consultar_situacao` | ✅ |
| Consultar frota | `consultar_frota` | ✅ |
| Consultar exceção | `consultar_excecao` | ✅ |
| Retificação | via payload helpers | ⚠️ Verificar homologação |

Integração MDF-e: importar CIOT declarado no wizard; encerramento automático ao encerrar MDF-e.

---

## Quando o CIOT é obrigatório no sistema

Validação em `sf_pef_validar_tipo_contratacao`:

| Tipo contratação | CIOT |
|------------------|------|
| Frota própria (veículos/motoristas da empresa) | **Bloqueado** — declaração não permitida |
| TAC (autônomo) | **Obrigatório** |
| ETC terceirizada / subcontratada | **Sim** |
| Agregado | **Sim** |

Use `GET api/pef_ciot.php?action=validar_contratacao&tipo_contratacao=tac` antes de declarar.

---

## Pré-requisitos

1. SQL: `sql/pef_ciot.sql`
2. **Configurações → Fiscal** — card **CIOT — PEF ANTT**:
   - Ambiente: `homologacao` | `producao`
   - CNPJ/CPF do certificado
   - Caminhos PEM certificado + chave privada
   - Ativar integração
3. Certificado **ICP-Brasil A1/A3** (e-CNPJ ou e-CPF do transportador/contratado)
4. **RNTRC** válido (8 ou 9 dígitos)

### URLs ANTT

| Ambiente | Base URL |
|----------|----------|
| Homologação | `https://appservices-hml.antt.gov.br/pefServices` |
| Produção | `https://appservices.antt.gov.br/pefServices` |

Protocolo: HTTPS + TLS 1.2+, autenticação **mTLS**.

### Variáveis de ambiente (opcional)

| Variável | Uso |
|----------|-----|
| `SF_PEF_CERT_PASS` | Senha da chave privada |
| `SF_PEF_CERT_PEM` | Caminho padrão do certificado |

Recomendado: arquivos PEM fora do webroot, ex. `uploads/pef/{empresa_id}/cert.pem`.

---

## Banco de dados

### `pef_config_empresa`

Configuração mTLS por empresa (ambiente, paths, ativo).

### `pef_ciot_operacoes`

| Coluna | Descrição |
|--------|-----------|
| `id_operacao_transporte` | 12 caracteres (IdOperacaoTransporte ANTT) |
| `codigo_identificacao` | CIOT 16 caracteres |
| `codigo_verificador` | 4 caracteres |
| `tipo_operacao` | 1=Lotação, 2=Fracionada, 3=TAC-Agregado |
| `status` | `rascunho`, `declarado`, `cancelado`, `encerrado`, `erro` |
| `cpf_cnpj_contratado`, `rntrc_contratado` | Transportador TAC |
| `cpf_cnpj_contratante`, `cpf_cnpj_destinatario` | Partes |
| `valor_frete` | Valor da operação |
| `data_inicio_viagem`, `data_fim_viagem` | Período |
| `rota_id` | Vínculo viagem frota |
| `mdfe_id` | Vínculo MDF-e (após importar no manifesto) |
| `payload_envio`, `payload_resposta` | Auditoria JSON |
| `protocolo_antt`, `codigo_antt`, `mensagem_antt` | Retorno ANTT |

### `pef_ciot_log`

Log de cada chamada HTTP (serviço, request, response, http_code).

---

## Fluxo na interface

1. **Sistema Fiscal → CIOT (PEF ANTT)**
2. Preencher declaração:
   - Tipo contratação (TAC/agregado/etc.)
   - Contratado (CPF/CNPJ + RNTRC)
   - Contratante, destinatário
   - Valor frete, datas viagem
   - Tipo operação (lotação/fracionada/TAC-agregado)
   - Opcional: `rota_id`
3. **Declarar** → ANTT retorna CIOT
4. Status `declarado` → disponível para MDF-e
5. Ao fim: **Encerrar** na ANTT (manual ou via encerramento MDF-e)
6. Se necessário: **Cancelar** antes do início da viagem

---

## APIs — `api/pef_ciot.php`

Sessão autenticada + CSRF em POST. Base: `/api/pef_ciot.php`

### Configuração

```
GET ?action=config
```

Retorna ambiente, CNPJ configurado, se ativo (sem expor senhas).

### Validar contratação

```
GET ?action=validar_contratacao&tipo_contratacao=tac
```

### Listar operações

```
GET ?action=list&status=declarado&limit=50
```

### Detalhe

```
GET ?action=detail&id=12
```

### CIOT disponíveis para MDF-e

```
GET ?action=disponiveis_mdfe
```

Operações `declarado` sem `mdfe_id` ou já vinculadas ao MDF-e em edição.

### Declarar (gerar CIOT)

```
POST ?action=declarar
Content-Type: application/json
```

Corpo exemplo:

```json
{
  "tipo_contratacao": "tac",
  "tipo_operacao": 1,
  "rota_id": 55,
  "cpf_cnpj_contratado": "12345678901",
  "rntrc_contratado": "12345678",
  "cpf_cnpj_contratante": "11222333000144",
  "cpf_cnpj_destinatario": "99888777000166",
  "valor_frete": 3500.00,
  "data_inicio_viagem": "2026-06-20",
  "data_fim_viagem": "2026-06-22",
  "id_operacao_transporte": ""
}
```

- `id_operacao_transporte` vazio → sistema gera sequencial 12 dígitos
- Sucesso ANTT: códigos `110` ou `111`
- Resposta: `ciot`, `codigo_verificador`, `protocolo`, `operacao_id`

### Cancelar

```
POST ?action=cancelar
Content-Type: application/json

{
  "operacao_id": 12,
  "motivo": "Operacao nao realizada"
}
```

### Encerrar

```
POST ?action=encerrar
Content-Type: application/json

{
  "operacao_id": 12
}
```

### Consultar CIOT na ANTT

```
POST ?action=consultar_ciot
Content-Type: application/json

{
  "ciot": "1234567890123456"
}
```

### Consultar situação transportador

```
POST ?action=consultar_situacao
Content-Type: application/json

{
  "cpf_cnpj": "12345678901",
  "rntrc": "12345678"
}
```

### Consultar frota

```
POST ?action=consultar_frota
Content-Type: application/json

{
  "cpf_cnpj": "11222333000144",
  "rntrc": "12345678"
}
```

### Consultar exceção (dispensa CIOT)

```
POST ?action=consultar_excecao
Content-Type: application/json

{
  "cpf_cnpj_transportador": "12345678901"
}
```

---

## Serviços ANTT (mapeamento)

| Serviço PEF | Método | Cliente PHP |
|-------------|--------|-------------|
| `DeclaracaoOperacaoTransporte` | POST | `declaracaoOperacaoTransporte()` |
| `CancelamentoOperacaoTransporte` | POST | `cancelamentoOperacaoTransporte()` |
| `EncerramentoOperacaoTransporte` | POST | `encerramentoOperacaoTransporte()` |
| `ConsultarCIOTGerado` | POST | `consultarCiotGerado()` |
| `ConsultarSituacaoTransportador` | POST | `consultarSituacaoTransportador()` |
| `ConsultarFrotaTransportador` | POST | `consultarFrotaTransportador()` |
| `ConsultarExcecao` | GET | `consultarExcecao()` |

---

## Códigos de retorno ANTT

| Código | Significado |
|--------|-------------|
| `110` | Sucesso |
| `111` | Sucesso com aviso (`AvisoTransportador`) |
| Outros | Erro — ver campo `Mensagem` |

---

## Integração com MDF-e

```
1. declarar (PEF) → status declarado, codigo_identificacao
2. criar_mdfe → Importar do PEF (rod_ciot_json)
3. pef_ciot_operacoes.mdfe_id = id do manifesto
4. encerrar_mdfe → sf_pef_encerrar_vinculados_mdfe
5. cancelar_mdfe → sf_pef_desvincular_mdfe
```

Auto-import: `criar_mdfe` com `rota_id` busca CIOT `declarado` na mesma rota.

No XML MDF-e: grupo `infANTT` / CIOT no modal rodoviário (`rod_ciot_numero`).

**Ver:** [`MDFE_FASE2.md`](MDFE_FASE2.md), [`PEF_CIOT_INTEGRACAO.md`](PEF_CIOT_INTEGRACAO.md)

---

## RNTRC — normalização

| Entrada | Tratamento |
|---------|------------|
| 7 dígitos | Inválido |
| 8 dígitos | Zero à esquerda → 9 dígitos |
| 9 dígitos | Aceito |

---

## Fluxo operacional completo (TAC)

```
Cadastrar rota + motorista TAC
        ↓
Declarar CIOT (ANTT) — ANTES do início da viagem
        ↓
Receber NF-e do cliente
        ↓
Emitir CT-e
        ↓
Emitir MDF-e (importar CIOT do PEF)
        ↓
Viagem
        ↓
Encerrar MDF-e (+ encerramento CIOT ANTT)
```

Frota própria: pula declaração PEF; MDF-e sem CIOT ANTT (quando não aplicável).

---

## Configuração via API

Salvar config PEF:

```
POST api/configuracoes.php
action=save_pef_config
(+ campos ambiente, cert paths, ativo)
```

Carregar junto com fiscal:

```
GET api/configuracoes.php?action=get_config_fiscal
```

---

## Homologação ANTT

1. Aplicar `sql/pef_ciot.sql`
2. Certificados de **homologação** ANTT
3. Ambiente `homologacao` no card PEF
4. `consultar_situacao` com transportador teste
5. `declarar` operação fictícia
6. `consultar_ciot` com código retornado
7. Importar no MDF-e homologação SEFAZ
8. `encerrar` operação teste
9. Virar produção ANTT + SEFAZ coordenados

---

## Limitações

- Retificação: verificar payload em `pef_ciot_helpers` — testar em HML antes de prod
- Piso mínimo de frete: validações ANTT retornam erro de negócio — tratar `Mensagem`
- IdOperacaoTransporte: gerador interno até homologação do gerador oficial ANTT
- Não substitui leitura do PDF PEF para campos novos de NT ANTT

**Ver também:** [`MANUAL_MDFE.md`](MANUAL_MDFE.md), [`MANUAL_FISCAL_INDICE.md`](MANUAL_FISCAL_INDICE.md)
