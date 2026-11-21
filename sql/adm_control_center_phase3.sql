-- Control Center ADM — Fase 3 (geo empresas, seeds config/custos)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/adm_control_center_phase3.sql

-- Geo para mapa de clientes (idempotente via adm_schema também)
-- ALTERs condicionais são feitos em adm_schema.php

INSERT IGNORE INTO adm_platform_config (chave, valor, categoria, sensivel) VALUES
('platform_name', 'Frotec', 'sistema', 0),
('support_email', 'suporte@frotec.com.br', 'sistema', 0),
('maintenance_mode', '0', 'sistema', 0),
('sla_target_pct', '99.9', 'sla', 0),
('heatmap_days', '30', 'analytics', 0);

INSERT IGNORE INTO adm_custos_mensais (categoria, valor, referencia_mes, observacao) VALUES
('smtp', 4.00, DATE_FORMAT(CURRENT_DATE(), '%Y-%m'), 'SMTP / E-mail'),
('servidor', 180.00, DATE_FORMAT(CURRENT_DATE(), '%Y-%m'), 'Hospedagem / VPS'),
('maps', 12.00, DATE_FORMAT(CURRENT_DATE(), '%Y-%m'), 'Google Maps API'),
('ssl', 0.00, DATE_FORMAT(CURRENT_DATE(), '%Y-%m'), 'Certificado SSL'),
('vps', 0.00, DATE_FORMAT(CURRENT_DATE(), '%Y-%m'), 'VPS / Cloud');
