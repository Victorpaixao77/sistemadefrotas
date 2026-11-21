[← Índice](README.md)

# 01 · Visão Geral

## O que é

O **Sistema de Frotas (Frotec)** é uma plataforma web **multi-empresa (SaaS)** de gestão
de transporte rodoviário de cargas (TMS + gestão de frota), com **app Android** para os
motoristas. Cobre o ciclo comercial e operacional do frete, do cadastro do cliente à
emissão fiscal e à análise financeira, com módulos de BI, IA, GPS e gamificação.

## Público / quem utiliza

| Perfil | Onde atua | Área |
|--------|-----------|------|
| **Gestor / operação** | `pages/` (web) | Rotas, veículos, financeiro, fiscal, BI |
| **Motorista** | app Android + `pages_motorista/` | Viagens, GPS, POD, checklist, despesas |
| **Administração da plataforma** | `pages_adm/` | Empresas-cliente, planos, cobrança, saúde |
| **Cliente/embarcador** | `rastreio.php` (público, por token) | Rastreio da própria carga |

## Capacidades principais

- **TMS:** clientes, cotações/ordens de transporte, aprovações, planejamento, rotas.
- **Frota:** veículos (cavalos), implementos (carretas), abastecimentos, manutenções,
  manutenção preditiva, pneus (inventário, movimentação, gestão interativa 3D).
- **Financeiro:** lucratividade, contas a pagar, despesas fixas, financiamentos, multas,
  comissões de motoristas.
- **Fiscal:** NF-e, CT-e, MDF-e, CIOT/PEF, vale-pedágio, DENATRAN (libs `nfephp-org/*`).
- **GPS:** posições em tempo real, cercas (geofences), trajeto, agregação diária.
- **BI & IA:** dashboards, resumos em linguagem natural, insights, OCR de documentos.
- **Motorista:** app offline-first com GPS em 2º plano, POD, checklist, jornada, pânico.
- **Plataforma (adm):** planos, limites, cobrança/boletos, health-checks, auditoria.

## Stack tecnológica

| Camada | Tecnologia |
|--------|-----------|
| Backend | **PHP 8.2** (sem framework MVC; scripts + includes compartilhados) |
| Banco | **MySQL/MariaDB** (porta **3307** no XAMPP), PDO (`ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`) |
| Frontend | HTML + CSS + JavaScript vanilla + jQuery; **Chart.js**, **Leaflet**, Google Maps |
| Fiscal | Composer: `nfephp-org/sped-nfe`, `sped-cte`, `sped-mdfe`, `sped-da`, `mpdf/mpdf`, `phpoffice/phpspreadsheet`, `box/spout`, `setasign/fpdi` |
| App | **Kotlin + Jetpack Compose**, Retrofit, Room (offline-first) |
| Servidor | **XAMPP** (Apache + MySQL) em Windows; produção Apache/PHP-FPM |
| IA/OCR | Provedor por empresa: Gemini / OpenAI / OpenRouter / DeepSeek |

> **Sem build step no front:** JS/CSS são versionados por `sf_asset_v()` para *cache-busting*.

## Convenções que valem para todo o sistema

1. **Isolamento multi-empresa:** toda query filtra por `$_SESSION['empresa_id']`.
2. **Prepared statements** com placeholders nomeados — nunca concatenar entrada no SQL.
3. **Autenticação** por `require_authentication()`; **CSRF** em toda ação que muda estado.
4. **Erros** nunca vazam `$e->getMessage()` ao usuário; `error_log()` + mensagem genérica.
5. **Nunca fabricar dados** em `catch` — devolver `success:false` / estado vazio honesto.

Detalhes em [06-regras-de-negocio.md](06-regras-de-negocio.md) e [11-seguranca.md](11-seguranca.md).
