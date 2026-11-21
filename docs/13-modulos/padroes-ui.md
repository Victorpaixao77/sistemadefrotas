[← Módulos](README.md) · [← Índice](../README.md)

# Padrões de UI compartilhados (web)

Referência única de como uma tela do gestor é montada. Modelo canônico: `pages/routes.php`.

## Shell da página

```php
require_once '../includes/config.php';   // + functions, csrf, sf_page_help
configure_session(); session_start(); require_authentication();
```
```html
<body class="sf-premium">
  <div class="app-container">
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
      <?php include '../includes/header.php'; ?>
      <div class="dashboard-content"> ... </div>
      <?php include '../includes/footer.php'; ?>
    </div>
  </div>
  <!-- scripts: jquery + ../js/theme.js + ../js/sidebar.js (+ sf_page_help_js) -->
```

## CSS base

- `css/styles.css`, `css/theme.css`, `css/responsive.css` (sempre).
- `css/fornc-modern-page.css` — design system `.fornc-*` (100% escopado).
- `css/sf-premium.css` — regras sob `body.sf-premium` (variáveis `--u-*`, paginação, etc.).

## Toolbar

```html
<div class="fornc-toolbar">
  <div class="fornc-search-block"><label>Busca rápida</label>
    <div class="fornc-search-inner"><i class="fas fa-search"></i><input …></div></div>
  <div class="fornc-filters-inline">
    <div class="fg"><label>Status</label><select>…</select></div>
    <div class="fg"><label>Por página</label><select class="filter-per-page">…</select></div>
  </div>
  <div class="fornc-btn-row">
    <button class="fornc-btn fornc-btn--primary">…</button>
    <button id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon"><i class="fas fa-question-circle"></i></button>
  </div>
</div>
```

## Tabela + ações

- `.fornc-table` dentro de `.fornc-table-wrap`.
- Última coluna `<th>Ações</th>`; célula `<td class="actions">` com botões `.btn-icon`
  (`edit-btn` âmbar, `delete-btn` vermelho, `view-btn` azul — cores no hover).

## Estado vazio

```html
<div class="fornc-empty-state" id="…EmptyState">
  <p class="fornc-empty-state-title">Nenhum X encontrado</p>
  <p class="fornc-empty-state-hint">Dica contextual…</p>
  <button class="fornc-btn fornc-btn--primary">Ação</button>
</div>
```

## Paginação

- `<div class="pagination">` com `‹ | Página X de Y (N registros) | ›` (classe `.pagination`
  do `styles.css`); o seletor **"Por página" fica na toolbar do topo**, não na barra de baixo.

## Ajuda por página

1. `<?php sf_page_help_css('..'); ?>` no `<head>`.
2. Botão `#helpBtn` na toolbar.
3. Antes de `</body>`: `<?php $sf_help_key='<chave>'; include '../includes/sf_page_help_bundle.php'; ?>`
   + `<?php sf_page_help_js('..'); ?>`.
4. Conteúdo em `includes/page_help/*.php` (chave por página). O `js/sf_page_help.js` liga
   o botão ao modal automaticamente.

## Comportamento de acesso

- Login sempre cai em `index.php`; submenus do menu lateral iniciam **recolhidos**
  (reset via `$_SESSION['sf_sidebar_reset']` → limpa `localStorage.sidebarDropdowns`).
