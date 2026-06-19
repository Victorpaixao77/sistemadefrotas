# Manual fiscal Frotec — Índice geral

Guia técnico para operação e integração dos módulos **NF-e**, **CT-e**, **MDF-e** e **CIOT (PEF ANTT)** no Sistema de Frotas.  
Não substitui os manuais oficiais da SEFAZ/ANTT — aponta para eles e descreve **como o Frotec implementa** cada fluxo.

## Documentação oficial (consulta externa)

| Documento | Portal | Leiaute atual |
|-----------|--------|---------------|
| NF-e MOC | [Portal Nacional NF-e](https://www.nfe.fazenda.gov.br/portal/principal.aspx) | 4.00 + NTs |
| CT-e MOC | [Portal CT-e](https://www.cte.fazenda.gov.br/portal/) | 4.00 (modelo 57) |
| MDF-e MOC | [Portal MDF-e](https://dfe-portal.svrs.rs.gov.br/Mdfe) | 4.00 |
| CIOT / PEF | [CIOT Para Todos — ANTT](https://www.gov.br/antt/pt-br/assuntos/cargas/ciot) | PEF WebService v1.1 |

PDF local PEF: [`docs/pef/dcs_pef_v1-1.pdf`](pef/dcs_pef_v1-1.pdf)

## Manuais do sistema (por módulo)

| Módulo | Manual | Tela | API principal |
|--------|--------|------|---------------|
| NF-e | [`MANUAL_NFE.md`](MANUAL_NFE.md) | `fiscal/pages/nfe.php` | `fiscal/api/documentos_fiscais_v2.php` |
| CT-e | [`MANUAL_CTE.md`](MANUAL_CTE.md) | `fiscal/pages/cte.php` | idem + `fiscal/api/fiscal_events.php` (cancelar) |
| MDF-e | [`MANUAL_MDFE.md`](MANUAL_MDFE.md) | `fiscal/pages/mdfe.php` | idem |
| CIOT ANTT | [`MANUAL_CIOT_ANTT.md`](MANUAL_CIOT_ANTT.md) | `fiscal/pages/ciot.php` | `api/pef_ciot.php` |

Complementos: [`CTE_MOC_V4_STATUS.md`](CTE_MOC_V4_STATUS.md), [`MDFE_FASE2.md`](MDFE_FASE2.md), [`MDFE_FASE3.md`](MDFE_FASE3.md), [`PRODUCAO_COMPOSER_FISCAL.md`](PRODUCAO_COMPOSER_FISCAL.md)

## Fluxo operacional recomendado (frota)

```
Cliente emite NF-e
       ↓
Transportadora recebe NF-e (XML / DistDFe / manual)
       ↓
[Se TAC/terceiro] Declarar CIOT na ANTT (PEF) — antes da viagem
       ↓
Emitir CT-e (vincula NF-e + veículo + motorista)
       ↓
Emitir MDF-e (CT-e/NF-e + rodoviário + CIOT + vale pedágio se aplicável)
       ↓
Viagem em andamento
       ↓
Encerrar MDF-e (+ encerramento CIOT vinculado, quando configurado)
```

## Bibliotecas PHP (Composer na raiz)

```bash
composer install --no-dev --optimize-autoloader
```

| Pacote | Módulos |
|--------|---------|
| `nfephp-org/sped-nfe` | NF-e recebida, emissão, eventos, DistDFe |
| `nfephp-org/sped-cte` | CT-e emissão e eventos |
| `nfephp-org/sped-mdfe` | MDF-e emissão e eventos |
| `nfephp-org/sped-da` | DANFE, DACTE, DAMDFE |
| `mpdf/mpdf` | Fallback PDF |

Sem `vendor/`, APIs fiscais retornam **503** `missing_vendor`.

## Configuração comum (todas as empresas)

1. **Configurações → Fiscal & Multas** (`pages/configuracoes.php?aba=fiscal`)
   - Certificado digital **A1** (`.pfx` + senha)
   - CNPJ, IE, município IBGE (`fiscal_config_empresa`)
   - Ambiente SEFAZ: homologação → produção
   - RNTRC (CT-e / MDF-e rodoviário)
2. **CIOT:** card PEF ANTT + certificados mTLS (ver manual CIOT)
3. **Composer** em produção: [`PRODUCAO_COMPOSER_FISCAL.md`](PRODUCAO_COMPOSER_FISCAL.md)

## Migrações SQL (checklist deploy)

| Arquivo | Módulo |
|---------|--------|
| `sql/cte_layout_v4.sql` | CT-e wizard 4.00 |
| `sql/cte_fase2_eventos.sql` | CT-e eventos |
| `sql/fiscal_mdfe_meta_v1.sql` | MDF-e wizard documentos |
| `sql/mdfe_fase2_pef_link.sql` | Vínculo PEF ↔ MDF-e |
| `sql/mdfe_fase3_completo.sql` | Condutores, reboques, percurso |
| `sql/pef_ciot.sql` | CIOT / PEF ANTT |
| `fiscal/database/create_fiscal_nfe_emitidas.sql` | NF-e emitida própria |

## Autenticação das APIs

- **Sessão web:** `require_authentication()` + `empresa_id` na sessão
- **POST mutável:** CSRF via `api_require_csrf_json()` (header ou campo `csrf_token`)
- **Rate limit:** ações SEFAZ têm limite por IP/sessão (ex.: 15 req/5 min emissão)

## Relacionamento com a frota

| Cadastro frota | Uso fiscal |
|----------------|------------|
| `veiculos` | CT-e modal rodoviário; MDF-e tração/reboque |
| `motoristas` | Condutor CT-e/MDF-e; evento inclusão condutor |
| `rotas` | Auto-vínculo CIOT PEF no MDF-e; `rota_id` em operações |
| `clientes` / NF-e | Remetente/destinatário no CT-e |

## Ordem de estudo sugerida

1. **MDF-e** — documento que consolida a viagem  
2. **CT-e** — transporte da carga  
3. **CIOT** — obrigatoriedade ANTT 2026 para TAC/terceiros  
4. **NF-e** — entrada da carga no fluxo  

## Status geral (jun/2026)

| Área | Implementação | Pendente |
|------|---------------|----------|
| NF-e recebida + DistDFe | ✅ | — |
| NF-e emissão própria (Simples/CRT) | ✅ | Regimes complexos sob demanda |
| CT-e 4.00 rodoviário + eventos | ✅ | EPEC contingência |
| MDF-e 4.00 wizard + eventos | ✅ | — |
| CIOT PEF v1.1 | ✅ | Validação homologação ANTT em prod |
| Testes automatizados | ❌ | Validação manual homologação SEFAZ/ANTT |
