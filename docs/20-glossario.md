[← Índice](README.md)

# 20 · Glossário

| Termo | Significado |
|-------|-------------|
| **TMS** | *Transportation Management System* — gestão de transporte de cargas |
| **Empresa-cliente / tenant** | Cada empresa que usa o sistema (isolada por `empresa_id`, tabela `empresa_clientes`) |
| **Embarcador / tomador** | Cliente que contrata o frete (tabela `clientes`) |
| **OT** | Ordem de Transporte — o pedido de frete (cotação → aprovada → em viagem → concluída) |
| **Rota** | Execução da viagem (origem→destino, veículo, motorista, km, frete) |
| **Cavalo** | Veículo trator/mecânico. **Implemento** = carreta/reboque/dolly rebocado |
| **Frete** | Valor cobrado pelo transporte |
| **Piso ANTT** | Frete mínimo obrigatório (Lei 13.703/2018): piso = CCD × km + CC |
| **CCD / CC** | Coeficientes de custo de deslocamento / de carga-descarga (tabela ANTT) |
| **Frete de equilíbrio** | Frete que zera o lucro (break-even) no simulador |
| **NF-e** | Nota Fiscal eletrônica (mercadoria) |
| **CT-e** | Conhecimento de Transporte eletrônico (o frete) |
| **MDF-e** | Manifesto de Documentos Fiscais eletrônico (viagem) |
| **CIOT** | Código Identificador da Operação de Transporte (ANTT/PEF) |
| **PEF** | Prestador de Emissão de Frete (gera CIOT) |
| **Vale-pedágio** | Adiantamento obrigatório de pedágio ao transportador |
| **DENATRAN** | Base nacional de trânsito (infrações/multas) |
| **POD** | *Proof of Delivery* — comprovante de entrega (com assinatura) |
| **Jornada** | Controle de horas do motorista (Lei do Motorista) |
| **Checklist** | Inspeção do veículo por tipo (inclui carreta) |
| **Geofence / cerca** | Área geográfica que dispara alerta na entrada/saída/permanência |
| **OCR** | Leitura por IA de visão (cupom, painel/KM, pedágio, NF, lacre) |
| **BI** | *Business Intelligence* — dashboards e análises |
| **Gamificação** | Pontuação/ranking/conquistas dos motoristas |
| **CSRF** | *Cross-Site Request Forgery* — protegido por token nas ações de escrita |
| **2FA / TOTP** | Segundo fator de autenticação por código temporal |
| **`sf_asset_v()`** | Versão dos assets (cache-busting do front) |
| **`empresa_id`** | Chave de isolamento multi-empresa presente em quase toda tabela |
| **adm / Control Center** | Painel de administração da plataforma (`pages_adm/`, tabelas `adm_*`) |
