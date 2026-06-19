# CT-e — MOC 4.00 vs Sistema

**Manual completo do módulo:** [`MANUAL_CTE.md`](MANUAL_CTE.md) · Índice fiscal: [`MANUAL_FISCAL_INDICE.md`](MANUAL_FISCAL_INDICE.md)

Referência: **MOC CT-e Visão Geral v4.00**, pacote `PL_CTe_400`, modelo **57**.

## Resumo executivo (jun/2026)

| Área | Status |
|------|--------|
| Emissão SEFAZ layout 4.00 | ✅ `CteContextLoader` + `CteXmlBuilder` |
| Wizard criar + emitir | ✅ `cte.php` + `criar_cte` / `emitir_cte_sefaz` |
| Cancelamento (110111) | ✅ `CTeService` + `fiscal_events.php` |
| CC-e (110110) | ✅ API `cte_cce` + UI |
| Comprovante entrega (110180) | ✅ API `cte_comprovante_entrega` + UI |
| Cancel. comprovante (110181) | ✅ API `cte_cancelar_comprovante` |
| Desacordo tomador (610110) | ✅ API `cte_desacordo` + UI |
| Inutilização numeração | ✅ API `cte_inutilizar` (se sped-cte suportar) |
| Consulta status serviço | ✅ `consultar_status_cte_sefaz` |
| DACTE PDF | ✅ sped-da + fallback mPDF |
| Importar / consultar / DistDFe | ✅ |
| EPEC (110113) | ⚠️ Exige contingência ativa no sped-cte |
| CT-e OS (67) / GTV-e (64) | ❌ Fora do escopo frota |
| Outros modais (aéreo, aquático…) | ❌ Frota rodoviária |

## Arquivos principais

| Arquivo | Papel |
|---------|--------|
| `fiscal/includes/CteContextLoader.php` | Contexto emissão |
| `fiscal/includes/CteXmlBuilder.php` | XML 4.00 rodoviário |
| `fiscal/includes/CTeService.php` | SEFAZ + eventos |
| `fiscal/includes/cte_event_helpers.php` | CC-e, CE, desacordo |
| `fiscal/includes/CteDacteSped.php` | DACTE sped-da |
| `fiscal/api/documentos_fiscais_v2.php` | API principal |
| `sql/cte_layout_v4.sql` | Schema wizard/emissão |
| `sql/cte_fase2_eventos.sql` | Eventos + `fiscal_cte_eventos` |

## APIs CT-e (documentos_fiscais_v2)

| action | Descrição |
|--------|-----------|
| `documentos_para_cte` | NF-e, veículos, motoristas |
| `criar_cte` | Rascunho + vínculos |
| `emitir_cte_sefaz` | Autorização SEFAZ |
| `cte_cce` | Carta de correção |
| `cte_desacordo` | Prestação em desacordo (tomador) |
| `cte_comprovante_entrega` | Comprovante 110180 |
| `cte_cancelar_comprovante` | Cancelamento CE |
| `cte_inutilizar` | Faixa de numeração |
| `consultar_status_cte_sefaz` | Status webservice |

## Fluxo recomendado

1. NF-e status `recebida` → **Novo CT-e** (wizard)
2. Revisar rascunho → **Enviar SEFAZ**
3. Autorizado → **DACTE PDF**, CC-e ou comprovante se necessário
4. Viagem → **MDF-e** com CT-e/NF-e vinculados

## Legado

- `fiscal/api/fiscal_cte.php?action=emitir` → **410 descontinuado**; usar `emitir_cte_sefaz`.

---

## Deploy em produção

Guia completo compartilhado com MDF-e/NF-e: [`docs/PRODUCAO_COMPOSER_FISCAL.md`](PRODUCAO_COMPOSER_FISCAL.md). Abaixo o essencial **só para CT-e**.

### 1. Composer na raiz do projeto

Pacotes obrigatórios para emissão e DACTE oficial:

| Pacote | Uso no CT-e |
|--------|-------------|
| `nfephp-org/sped-cte` | Emissão, consulta, eventos (CC-e, cancelar, comprovante…) |
| `nfephp-org/sped-da` | DACTE PDF layout SEFAZ (`NFePHP\DA\CTe\Dacte`) |
| `mpdf/mpdf` | Fallback PDF se sped-da falhar |

**Linux (produção):**

```bash
cd /caminho/para/sistema-frotas
composer install --no-dev --optimize-autoloader --no-interaction
```

**Windows / XAMPP (homologação local):**

```powershell
cd C:\xampp\htdocs\sistema-frotas
php composer.phar install --no-dev --optimize-autoloader
```

Se não existir `composer.lock`, gere uma vez em dev e versione:

```bash
composer update --no-dev
```

### 2. Verificar pacotes CT-e

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('NFePHP\\CTe\\Tools') ? 'sped-cte OK' : 'FALHA sped-cte'; echo PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('NFePHP\\DA\\CTe\\Dacte') ? 'sped-da DACTe OK' : 'FALHA sped-da'; echo PHP_EOL;"
php -l fiscal/includes/CTeService.php
php -l fiscal/includes/CteXmlBuilder.php
```

### 3. Migrações SQL (uma vez por ambiente)

```bash
mysql -h HOST -P PORTA -u USER sistema_frotas < sql/cte_layout_v4.sql
mysql -h HOST -P PORTA -u USER sistema_frotas < sql/cte_fase2_eventos.sql
```

**XAMPP local (porta 3307):**

```powershell
Get-Content sql\cte_layout_v4.sql -Raw | C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root sistema_frotas
Get-Content sql\cte_fase2_eventos.sql -Raw | C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root sistema_frotas
```

> O sistema também tenta aplicar essas migrações no primeiro uso (`cte_ensure_extended_schema`), mas rodar o SQL manualmente em produção evita surpresas.

### 4. Configuração fiscal (por empresa)

- Certificado **A1** ativo em Configurações → Fiscal
- **RNTRC** cadastrado (modal rodoviário)
- Ambiente SEFAZ: começar em **homologação**, virar **produção** só após testes OK
- CNPJ, IE, município IBGE (`fiscal_config_empresa`)

### 5. Permissões

```bash
chown -R www-data:www-data vendor uploads/certificados
chmod -R a+rX vendor
```

Pastas de escrita: `uploads/`, `uploads/mpdf_tmp/` (PDF fallback), `logs/` (se habilitado).

### 6. Checklist pós-deploy CT-e

- [ ] `vendor/autoload.php` legível pelo usuário do Apache/PHP-FPM
- [ ] `composer install` sem erros (`sped-cte` + `sped-da` OK)
- [ ] SQL `cte_layout_v4.sql` e `cte_fase2_eventos.sql` aplicados
- [ ] NF-e com status `recebida` disponível no wizard
- [ ] **Novo CT-e** → rascunho → **Enviar SEFAZ** (homologação)
- [ ] Download **XML** e **DACTE PDF**
- [ ] (Opcional) CC-e, comprovante de entrega, cancelamento
- [ ] CT-e autorizado vinculável no **MDF-e**
- [ ] Logs sem `missing_vendor` / `pacote ausente`

### 7. Testes homologação antes da virada

1. `consultar_status_cte_sefaz` (webservice online)
2. Wizard: NF-e + veículo + motorista + frete
3. Emitir → protocolo `100`/`150`
4. DACTE com engine `sped-da` (header `X-Fiscal-Dacte-Engine` no download)
5. Só então: Configurações → ambiente **produção** SEFAZ

---

## O que ainda não está no escopo

| Item | Situação |
|------|----------|
| EPEC contingência (110113) | Não implementado (exige modo contingência no sped-cte) |
| CT-e OS modelo 67 / GTV-e 64 | Fora do escopo frota |
| Modais aéreo, aquaviário, etc. | Frota rodoviária apenas |
| Testes automatizados (PHPUnit) | Não há suíte; validação manual em homologação |

**Para o fluxo frota completo (NF-e → CT-e → MDF-e → PEF/CIOT):** implementação de código concluída; falta **validar em homologação SEFAZ** com certificado real e dados de teste.

---

*Última atualização: jun/2026 — Fases 1–4 CT-e implementadas; pendente validação SEFAZ homologação.*
