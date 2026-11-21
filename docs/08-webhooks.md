[← Índice](README.md)

# 08 · Webhooks e Integrações

O sistema integra com serviços externos. A maioria é **saída** (o Frotec chama o serviço);
webhooks de **entrada** são pontuais.

## Fiscais (SEFAZ e afins)

| Integração | Onde | Observação |
|-----------|------|-----------|
| **SEFAZ NF-e/CT-e/MDF-e** | `fiscal/` (libs `nfephp-org/*`) | Autorização, cancelamento, eventos, distribuição NSU (`fiscal_distribuicao_nsu`) |
| **CIOT / PEF (ANTT)** | `api/pef_ciot.php` | Cert. PEM `SF_PEF_CERT_PEM`; ver [PEF_CIOT_INTEGRACAO.md](PEF_CIOT_INTEGRACAO.md), [MANUAL_CIOT_ANTT.md](MANUAL_CIOT_ANTT.md) |
| **DENATRAN** | `api/denatran_infracoes.php`, `certificados_denatran` | Infrações; ver [DENATRAN_INTEGRACAO.md](DENATRAN_INTEGRACAO.md) |
| **Vale-pedágio** | tabelas `fiscal_mdfe_vale_pedagio` | Parte do MDF-e |

## Dados externos

| Integração | Endpoint | Uso |
|-----------|----------|-----|
| **BrasilAPI (CNPJ)** | `api/cnpj_brasilapi.php` | Autofill de cadastro por CNPJ |
| **Google Maps** | `google-maps/` (`api.php`, `maps.js`) | Distância/rota, geocode |
| **Provedores de IA** | OpenAI / DeepSeek / Google Vision / OpenRouter | Resumos, insights, OCR (`sf_vision_ocr.php`), por empresa |
| **Azure (import abastecimento)** | `api/import_abastecimentos_azure.php` | Importação em lote |

## App do motorista

- O app fala com `app_android/api/*.php` autenticado por **token** (`api_tokens_motorista`),
  não por sessão web. Contrato em [`../app_android/docs/ENDPOINTS.md`](../app_android/docs/ENDPOINTS.md).

## Rastreio público (saída para o cliente)

- `rastreio.php` expõe o andamento de **uma** OT por **token** (sem login, sem dados
  comerciais de terceiros).

## Pendências

- **Push FCM** (app) não implementado (falta `google-services.json`) — hoje é *polling*.
- Não há um **registro central de webhooks de entrada** configuráveis por empresa
  (melhoria em [99-validacao-final.md](99-validacao-final.md)).
