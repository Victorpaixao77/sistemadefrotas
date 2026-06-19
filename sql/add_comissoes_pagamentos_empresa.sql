-- Migração: adicionar empresa_id em comissoes_pagamentos (instalações existentes)
ALTER TABLE comissoes_pagamentos
    ADD COLUMN IF NOT EXISTS empresa_id INT NULL AFTER rota_id;

UPDATE comissoes_pagamentos cp
INNER JOIN rotas r ON r.id = cp.rota_id
SET cp.empresa_id = r.empresa_id
WHERE cp.empresa_id IS NULL;

ALTER TABLE comissoes_pagamentos
    MODIFY empresa_id INT NOT NULL;

ALTER TABLE comissoes_pagamentos
    ADD INDEX IF NOT EXISTS idx_cp_empresa_status (empresa_id, status, data_pagamento);
