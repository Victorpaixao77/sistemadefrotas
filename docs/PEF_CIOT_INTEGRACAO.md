# Integração PEF / CIOT — ANTT v1.1

**Manual completo:** [`MANUAL_CIOT_ANTT.md`](MANUAL_CIOT_ANTT.md) · Índice: [`MANUAL_FISCAL_INDICE.md`](MANUAL_FISCAL_INDICE.md)

Referência oficial: [`docs/pef/dcs_pef_v1-1.pdf`](pef/dcs_pef_v1-1.pdf) (Contrato de Serviço PEF v1.1).

## Visão geral

O **CIOT** (Código Identificador da Operação de Transporte) é gerado via **PEF** (Pagamento Eletrônico de Frete) da ANTT. É independente do campo CIOT no **MDF-e** (informado manualmente ou preenchido após geração aqui).

| Ambiente   | Base URL |
|-----------|----------|
| Homologação | `https://appservices-hml.antt.gov.br/pefServices` |
| Produção    | `https://appservices.antt.gov.br/pefServices` |

- Protocolo: **HTTPS** + **TLS 1.2+**
- Autenticação: **mTLS** com certificado digital **ICP-Brasil A1/A3** (e-CNPJ ou e-CPF do transportador/contratado)
- Formato: **JSON** REST

## Serviços (8)

| Método HTTP | Serviço |
|-------------|---------|
| POST | `ConsultarSituacaoTransportador` |
| POST | `ConsultarFrotaTransportador` |
| POST | `DeclaracaoOperacaoTransporte` — **gera CIOT** |
| POST | `CancelamentoOperacaoTransporte` |
| POST | `RetificacaoOperacaoTransporte` |
| POST | `EncerramentoOperacaoTransporte` |
| GET  | `ConsultarExcecao?CPFCNPJTransportador=` |
| POST | `ConsultarCIOTGerado` |

## Quando o CIOT é obrigatório

O sistema valida o **tipo de contratação** antes de permitir a declaração:

| Situação | CIOT |
|----------|------|
| Frota própria (veículos e motoristas da empresa) | **Não** — declaração bloqueada |
| TAC (caminhoneiro autônomo contratado) | **Sim** |
| ETC terceirizada / subcontratada | **Sim** (verificar operação) |
| Agregado (veículo de terceiro vinculado) | **Sim** |

No MDF-e, o CIOT informado refere-se ao número **já gerado** aqui — são etapas complementares.

## Configuração no sistema

1. Aplicar migração: `sql/pef_ciot.sql`
2. **Configurações → Fiscal & Multas** (`pages/configuracoes.php?aba=fiscal`) — card **CIOT — PEF ANTT**
3. Informar ambiente, CNPJ/CPF do certificado, caminhos PEM e ativar a integração
4. Menu **Sistema Fiscal → CIOT (PEF ANTT)** — declarar e gerenciar operações

Arquivos PEM recomendados fora do webroot, ex.: `uploads/pef/{empresa_id}/cert.pem`.

### Variáveis de ambiente (opcional)

| Variável | Uso |
|----------|-----|
| `SF_PEF_CERT_PASS` | Senha da chave privada |
| `SF_PEF_CERT_PEM` | Caminho padrão do certificado se não configurado no banco |

## Códigos de retorno ANTT (principais)

| Código | Significado |
|--------|-------------|
| 110 | Operação realizada com sucesso |
| 111 | Operação realizada com aviso |
| Outros | Erro de validação ou negócio — ver `Mensagem` |

## IdOperacaoTransporte

Identificador de **12 caracteres** por operação. A ANTT disponibiliza gerador oficial; o sistema usa sequencial interno por empresa/ano até homologação do gerador oficial.

## RNTRC

- 8 dígitos → normalizado com zero à esquerda (9 dígitos)
- 9 dígitos → aceito
- 7 dígitos → inválido

## Tipos de operação

| Valor | Tipo |
|-------|------|
| 1 | Lotação |
| 2 | Fracionada |
| 3 | TAC-Agregado |

## Integração com MDF-e

Após declarar o CIOT, use o número **CodigoIdentificacaoOperacao** (16 caracteres) no MDF-e rodoviário (`rod_ciot_numero`), vinculando frete TAC com pagamento.

## Arquivos do sistema

| Arquivo | Função |
|---------|--------|
| `includes/pef_antt_client.php` | Cliente HTTP mTLS |
| `includes/pef_ciot_helpers.php` | Config, payload, log |
| `api/pef_ciot.php` | API JSON (operações CIOT) |
| `api/configuracoes.php` | Salvar/carregar config PEF (`save_pef_config`, em `get_config_fiscal`) |
| `pages/configuracoes.php?aba=fiscal` | Configuração PEF |
| `fiscal/pages/ciot.php` | Declaração e gestão de CIOT |
| `fiscal/pages/js/ciot.js` | Front-end |
