-- Script para adicionar campos de controle aos contratos
-- Executar este script para adicionar as novas colunas à tabela seguro_contratos_clientes

USE sistema_frotas;

-- Adicionar coluna TIPO DE OS
ALTER TABLE seguro_contratos_clientes 
ADD COLUMN IF NOT EXISTS tipo_os VARCHAR(100) NULL
COMMENT 'Tipo de Ordem de Serviço'
AFTER situacao;

-- Adicionar coluna ENVIO WHATSAPP
ALTER TABLE seguro_contratos_clientes 
ADD COLUMN IF NOT EXISTS envio_whatsapp ENUM('sim', 'nao') NOT NULL DEFAULT 'nao'
COMMENT 'Envio de notificações por WhatsApp'
AFTER tipo_os;

-- Adicionar coluna ENVIO E-MAIL
ALTER TABLE seguro_contratos_clientes 
ADD COLUMN IF NOT EXISTS envio_email ENUM('sim', 'nao') NOT NULL DEFAULT 'nao'
COMMENT 'Envio de notificações por E-mail'
AFTER envio_whatsapp;

-- Adicionar coluna PLANILHA
ALTER TABLE seguro_contratos_clientes 
ADD COLUMN IF NOT EXISTS planilha ENUM('sim', 'nao') NOT NULL DEFAULT 'nao'
COMMENT 'Inclusão em planilha de controle'
AFTER envio_email;

-- Verificar as colunas adicionadas
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'sistema_frotas'
AND TABLE_NAME = 'seguro_contratos_clientes'
AND COLUMN_NAME IN ('tipo_os', 'envio_whatsapp', 'envio_email', 'planilha')
ORDER BY ORDINAL_POSITION;

