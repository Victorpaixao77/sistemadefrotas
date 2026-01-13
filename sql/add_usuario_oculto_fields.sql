-- Adicionar campos para usuário oculto com acesso a todas as empresas
-- Execute este script no banco de dados

-- Adicionar campo is_oculto (ignorar erro se já existir)
ALTER TABLE usuarios 
ADD COLUMN is_oculto TINYINT(1) DEFAULT 0 COMMENT '1 = usuário oculto (não aparece nas listagens), 0 = usuário visível';

-- Adicionar campo acesso_todas_empresas (ignorar erro se já existir)
ALTER TABLE usuarios 
ADD COLUMN acesso_todas_empresas TINYINT(1) DEFAULT 0 COMMENT '1 = tem acesso a todas as empresas, 0 = acesso apenas à empresa_id';

-- Criar índices (ignorar erro se já existirem)
CREATE INDEX idx_usuarios_oculto ON usuarios(is_oculto);
CREATE INDEX idx_usuarios_acesso_todas ON usuarios(acesso_todas_empresas);
