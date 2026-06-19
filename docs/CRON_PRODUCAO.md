# Cron e tarefas agendadas — produção (Frotec)

Guia para configurar **cron**, **variáveis de ambiente** e **migrações** no servidor de produção.

Substitua `/var/www/sistema-frotas` pelo caminho real da aplicação e `/usr/bin/php` pelo PHP CLI do servidor (`which php`).

---

## 1. Antes de configurar o cron

### 1.1 Migração SQL (notificações)

Na primeira implantação (ou após atualizar o módulo de notificações):

```bash
mysql -h 127.0.0.1 -P 3307 -u USUARIO -p sistema_frotas < sql/notificacoes_evolution.sql
```

> As colunas/tabelas também são criadas automaticamente no primeiro acesso (PHP), mas rodar o SQL em produção evita surpresas.

### 1.2 Variáveis de ambiente (`.env` ou Apache/Nginx)

| Variável | Obrigatória | Uso |
|----------|-------------|-----|
| `SF_DB_HOST` | Sim | Host MySQL |
| `SF_DB_PORT` | Recomendado | Porta (ex.: `3307` no XAMPP, `3306` em produção) |
| `SF_DB_USER` | Sim | Usuário do banco |
| `SF_DB_PASS` | Sim | Senha do banco |
| `SF_DB_NAME` | Sim | Nome do banco (`sistema_frotas`) |
| `SF_SMTP_PASS` | Para digest e-mail | Senha SMTP (ou configurar em Configurações → Segurança) |
| `SF_RECAPTCHA_*` | Se usar login com reCAPTCHA | Segredos do Google |
| `SF_REDIS_HOST` | Só se usar worker GPS Redis | Host Redis |
| `SF_REDIS_PORT` | Opcional | Padrão `6379` |
| `SF_REDIS_PASSWORD` | Opcional | Senha Redis |
| `SF_GPS_ALERTAS_OPERACIONAIS` | Opcional | `1` para gravar alertas GPS no banco |

### 1.3 Usuário do cron

- Rodar como usuário do **PHP/web** (ex.: `www-data`), com leitura na pasta do projeto.
- Garantir que o CLI enxerga o mesmo `.env` / `includes/config.php` que o Apache.

### 1.4 Logs

Redirecione saída para log rotacionado:

```bash
>> /var/log/frotec/cron-notificacoes.log 2>&1
```

Crie a pasta `/var/log/frotec/` com permissão de escrita.

---

## 2. Cron obrigatório / altamente recomendado

### 2.1 Notificações — sync completo

**Arquivo:** `notificacoes/sync_all.php`  
**Função:** Gera/atualiza alertas **operacionais** e **IA** para **todas as empresas** (rotas, abastecimentos, multas, contas, anomalias, GPS, etc.).

| Campo | Valor |
|-------|--------|
| Frequência | A cada **15 minutos** |
| CLI | Sim (preferível) |

```cron
*/15 * * * * /usr/bin/php /var/www/sistema-frotas/notificacoes/sync_all.php >> /var/log/frotec/cron-notificacoes-sync.log 2>&1
```

**Sem este cron:** o sistema ainda sincroniza quando alguém está logado (polling do header a cada ~5 min), mas **fora do horário comercial** ou **sem usuário online** os alertas ficam defasados.

---

### 2.2 Notificações — limpeza diária

**Arquivo:** `notificacoes/limpeza_automatica.php`  
**Função:** Remove notificações **lidas** com mais de 30 dias; marca como lidas pendências de rota/abastecimento já resolvidas.

| Campo | Valor |
|-------|--------|
| Frequência | **1× por dia** (madrugada) |

```cron
0 2 * * * /usr/bin/php /var/www/sistema-frotas/notificacoes/limpeza_automatica.php >> /var/log/frotec/cron-notificacoes-limpeza.log 2>&1
```

---

### 2.3 Notificações — digest por e-mail (opcional)

**Arquivo:** `notificacoes/digest_email.php`  
**Função:** Envia resumo diário para usuários que ativaram em **Centro de Notificações → Preferências**.

| Campo | Valor |
|-------|--------|
| Frequência | **1× por dia** (ex.: 08:00) |
| Pré-requisito | SMTP em **Configurações → Segurança** + e-mail do usuário cadastrado |

```cron
0 8 * * * /usr/bin/php /var/www/sistema-frotas/notificacoes/digest_email.php >> /var/log/frotec/cron-notificacoes-digest.log 2>&1
```

**Sem SMTP:** o script roda mas não envia (falha silenciosa no log).

---

## 3. Cron GPS

### 3.1 Agregação diária de pontos GPS

**Arquivo:** `cron/gps_agregar_diario.php`  
**Função:** Agrega `gps_logs` do **dia anterior** em `gps_logs_resumido` e remove o bruto (economia de disco).

| Campo | Valor |
|-------|--------|
| Frequência | **1× por dia** (após meia-noite) |
| CLI only | Sim |

```cron
15 1 * * * /usr/bin/php /var/www/sistema-frotas/cron/gps_agregar_diario.php >> /var/log/frotec/cron-gps-agregar.log 2>&1
```

Reprocessar um dia específico:

```bash
php cron/gps_agregar_diario.php 2026-06-15
```

**Sem este cron:** tabela `gps_logs` cresce indefinidamente; mapa/relatórios podem ficar lentos.

---

### 3.2 Worker Redis GPS (opcional)

**Arquivo:** `cron/gps_redis_worker.php`  
**Função:** Consome fila Redis (`LPUSH`/`BRPOP`) para integrações externas.

| Campo | Valor |
|-------|--------|
| Tipo | **Daemon** (supervisor/systemd), não cron clássico |
| Pré-requisito | `SF_REDIS_HOST`, extensão PHP `redis` |

Exemplo **systemd** (melhor que cron para processo contínuo):

```ini
# /etc/systemd/system/frotec-gps-redis.service
[Service]
ExecStart=/usr/bin/php /var/www/sistema-frotas/cron/gps_redis_worker.php
Restart=always
User=www-data
Environment=SF_REDIS_HOST=127.0.0.1
```

**Sem Redis:** ignore este item.

---

## 4. Cron módulo fiscal

> Só configure se a empresa usa **emissão fiscal** (CT-e, MDF-e, fila assíncrona).

### 4.1 Worker da fila fiscal

**Arquivo:** `fiscal/worker_fiscal.php`  
**Função:** Processa `fiscal_fila_processamento` (envio MDF-e assíncrono, retry).

| Campo | Valor |
|-------|--------|
| Frequência | **A cada 1 minuto** |

```cron
* * * * * /usr/bin/php /var/www/sistema-frotas/fiscal/worker_fiscal.php >> /var/log/frotec/cron-fiscal-worker.log 2>&1
```

**Sem este cron:** documentos enfileirados como “pendente” não são enviados automaticamente.

---

### 4.2 Limpeza de logs MDF-e

**Arquivo:** `fiscal/cleanup_mdfe_logs.php`  
**Função:** Remove logs/envios MDF-e com mais de **180 dias**.

| Campo | Valor |
|-------|--------|
| Frequência | **1× por dia** (ex.: 03:00) |

```cron
0 3 * * * /usr/bin/php /var/www/sistema-frotas/fiscal/cleanup_mdfe_logs.php >> /var/log/frotec/cron-fiscal-cleanup.log 2>&1
```

---

## 5. O que NÃO precisa de cron (hoje)

| Recurso | Como funciona |
|---------|----------------|
| Sync CNH / manutenção no sininho | Ao abrir **Motoristas** / **Manutenções** ou ao carregar badges |
| Polling notificações (usuário logado) | Header chama `sync_all.php` ~5 min enquanto há sessão |
| Sincronizar NF-e pelo CNPJ | Manual na tela **Fiscal → NF-e** |
| Status SEFAZ | Cache ao abrir dashboard fiscal / configurações |
| Calendário (multas/CNH) | API `calendario/api/calendario_sync.php` — manual ou futuro cron |
| `relatorios_automaticos/` | Pasta de saída; **não há script PHP de geração agendada** no repositório atual |
| Tokens “Lembrar-me” | Expiram sozinhos; sem job de limpeza dedicado |
| `IA/ia_regras.php` | **Não** roda mais automaticamente (substituído por `sync_all`) |

---

## 6. Crontab completo (copiar e ajustar)

```cron
# --- Frotec: notificações ---
*/15 * * * * /usr/bin/php /var/www/sistema-frotas/notificacoes/sync_all.php >> /var/log/frotec/cron-notificacoes-sync.log 2>&1
0 2 * * * /usr/bin/php /var/www/sistema-frotas/notificacoes/limpeza_automatica.php >> /var/log/frotec/cron-notificacoes-limpeza.log 2>&1
0 8 * * * /usr/bin/php /var/www/sistema-frotas/notificacoes/digest_email.php >> /var/log/frotec/cron-notificacoes-digest.log 2>&1

# --- Frotec: GPS ---
15 1 * * * /usr/bin/php /var/www/sistema-frotas/cron/gps_agregar_diario.php >> /var/log/frotec/cron-gps-agregar.log 2>&1

# --- Frotec: fiscal (se usar emissão) ---
* * * * * /usr/bin/php /var/www/sistema-frotas/fiscal/worker_fiscal.php >> /var/log/frotec/cron-fiscal-worker.log 2>&1
0 3 * * * /usr/bin/php /var/www/sistema-frotas/fiscal/cleanup_mdfe_logs.php >> /var/log/frotec/cron-fiscal-cleanup.log 2>&1
```

### Windows (Task Scheduler)

Use o mesmo comando com o PHP do XAMPP, por exemplo:

`C:\xampp\php\php.exe C:\xampp\htdocs\sistema-frotas\notificacoes\sync_all.php`

Configure tarefas repetidas com o mesmo intervalo da tabela acima.

---

## 7. Checklist pós-deploy

- [ ] `php notificacoes/sync_all.php` roda sem erro no CLI
- [ ] Badges do header atualizam após alguns minutos **sem** ninguém logado (após cron)
- [ ] `pages/notificacoes.php` abre e lista alertas
- [ ] SMTP testado se usar digest e-mail
- [ ] `cron/gps_agregar_diario.php` testado em homologação
- [ ] `fiscal/worker_fiscal.php` testado se houver fila fiscal
- [ ] Logs em `/var/log/frotec/` sem erros de permissão ou banco

---

## 8. Resumo — notificações hoje

### Dois painéis

| Painel | Onde | Conteúdo |
|--------|------|----------|
| **Operacionais** | Sininho “Operacionais” | Ações do dia a dia |
| **IA** | Botão IA | Análises, anomalias, insights |

### Operacionais (tipos)

| Tipo | Exemplos |
|------|----------|
| `rota` | Rota pendente — **Aprovar / Rejeitar** no card |
| `abastecimento` | Abastecimento pendente — ações inline |
| `despesa` | Despesas de viagem pendentes (resumo) |
| `multa` | Multas em aberto / vencidas |
| `financeiro` | Contas a pagar vencidas |
| `comissao` | Comissões não pagas |
| `fiscal` | NF-e / CT-e / MDF-e pendentes |
| `cnh` | CNH vencendo e **CNH vencida** |
| `documento` | Veículos sem CRLV |
| `pneu` | Estoque de pneus baixo |

*Financeiro, comissão e fiscal só para quem tem permissão financeira.*

### IA (tipos)

| Tipo | Exemplos |
|------|----------|
| `manutencao` | Planos vencidos/próximos |
| `fraudes` | Anomalias em abastecimento |
| `alerta` | Inconsistências em rotas |
| `gps` | Alertas GPS (24h) |
| `pneu` | Insights de sulco/estoque |
| `insights` | Briefing diário da frota |
| `consumo`, `custo`, `seguranca`, `recomendacao` | Reservados / legado IA |

### Recursos de UX

- Severidade: **crítico / aviso / info**
- Clique → marca lida + abre página relacionada
- Centro: `pages/notificacoes.php` (filtros, histórico, preferências)
- Preferências: silenciar tipos, digest e-mail
- Respeita `sistema_notificacoes_ativo` em Configurações

---

## 9. O que ainda dá para colocar (notificações)

### Conteúdo (novas fontes)

| Ideia | Esforço |
|-------|---------|
| Despesas de viagem com aprovação no card | Médio |
| Multas com link direto ao comprovante | Baixo |
| Alertas de **novo login / IP** (segurança) | Médio |
| Lucratividade / margem (queda no mês) | Médio — API já existe |
| Fornecedores (atraso, concentração) | Médio |
| Checklists do motorista com problema | Médio |
| Cerca GPS violada | Baixo — API existe |
| IPVA / licenciamento veículo | Médio — se campo existir no cadastro |
| Resumo IA das páginas (`*_resumo_ia.php`) | Médio — 1 briefing consolidado/dia |

### Produto (UX)

| Ideia | Esforço |
|-------|---------|
| **Snooze** (“lembrar em 1h”) | Médio |
| **Web Push** no navegador | Alto (service worker + VAPID) |
| **WhatsApp** digest | Alto (API Meta / Twilio) |
| Ações inline em mais tipos (pagar conta, quitar multa) | Médio |
| Notificação por **perfil** (motorista vs gestor) | Médio |
| Relatório semanal PDF por e-mail | Alto |

### Infra

| Ideia | Esforço |
|-------|---------|
| Cron dedicado só sync IA (separar de operacional) | Baixo |
| Fila Redis para notificações de alto volume | Alto |
| Métricas (Prometheus / log estruturado) | Médio |

---

*Última atualização: junho/2026 — módulo notificações Frotec.*
