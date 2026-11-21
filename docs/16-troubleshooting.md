[← Índice](README.md)

# 16 · Troubleshooting (erros comuns)

Onde olhar primeiro: **`logs/`** (erros PHP via `error_log()`), `fiscal_logs` (fiscal),
`log_acessos` (login), console do navegador (JS). Ligar `SF_DEBUG=1` só em dev.

---

### 1. "Acesso não autorizado" / redireciona para login
- **Causa:** sem sessão / sessão expirada / faltou `empresa_id`.
- **Solução:** refazer login; conferir `configure_session()`/`SF_SESSION_PATH` gravável;
  cookies habilitados. APIs retornam `401 {"error":"Acesso não autorizado"}` — o front
  trata como sessão perdida.

### 2. Erro de conexão com o banco
- **Causa:** `SF_DB_*` errado ou MySQL na porta errada.
- **Solução:** local é **porta 3307**; testar `mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas`.

### 3. Página/lista "não atualiza" após mudar CSS/JS
- **Causa:** cache do navegador de asset versionado.
- **Solução:** subir a versão (`?v=…`) do asset ou **Ctrl+F5**. O sistema usa `sf_asset_v()`.

### 4. CSRF inválido ao salvar
- **Causa:** token ausente/expirado; sessão trocada.
- **Solução:** recarregar a página (novo `csrf_token_get()`); garantir que o POST envia
  `csrf_token`. Ver [11-seguranca.md](11-seguranca.md).

### 5. Emissão fiscal falha
- **Causa:** certificado A1 vencido/errado, `vendor/` ausente, ambiente SEFAZ fora.
- **Solução:** `composer install`; conferir `fiscal_certificados_digitais`/`fiscal_config_empresa`;
  ler `fiscal_logs` e os manuais ([MANUAL_CTE.md](MANUAL_CTE.md), [MANUAL_MDFE.md](MANUAL_MDFE.md)).

### 6. GPS não aparece no mapa
- **Causa:** app sem permissão/segundo plano; posição reprovada na validação (`SF_GPS_*`),
  mock bloqueado, ou precisão pior que `SF_GPS_MAX_ACCURACY_M`.
- **Solução:** conferir `gps_logs`/`gps_ultima_posicao`; revisar variáveis `SF_GPS_*`
  ([06-regras-de-negocio.md](06-regras-de-negocio.md#gps)).

### 7. OCR não lê o documento
- **Causa:** provedor de IA da empresa não configurado / chave inválida.
- **Solução:** conferir `empresa_openai_credentials`/`empresa_vision_ai`/`configuracoes_ia`
  e as chaves de env; ver `includes/sf_vision_ocr.php`.

### 8. Relatório agendado não chega
- **Causa:** cron não registrado ou SMTP.
- **Solução:** agendar `cron/enviar_relatorios_agendados.php` (de hora em hora); conferir
  `SF_SMTP_PASS`. Ver [10-cronjobs.md](10-cronjobs.md).

### 9. Menu lateral/submenus com estado inesperado
- **Causa:** estado salvo em `localStorage` (`sidebarCollapsed`, `sidebarDropdowns`).
- **Solução:** no login o sistema reseta os submenus (`sf_sidebar_reset`). Limpar
  `localStorage` do domínio resolve casos presos.

### 10. Tela em branco / erro 500
- **Causa:** erro fatal PHP.
- **Solução:** `php -l arquivo.php`; ler `logs/`; ligar `SF_DEBUG=1` em dev para ver o stack.

---

**Padrão de investigação:** reproduzir → ler `logs/` e a resposta JSON crua (`?action=…`)
→ `php -l` no arquivo → checar `empresa_id`/CSRF → validar a query no MySQL.
