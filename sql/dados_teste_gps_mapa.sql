-- Dados de teste para o mapa da frota (gps_posicoes.php lê gps_ultima_posicao).
-- Ajuste empresa_id, veiculo_id e motorista_id para IDs que existam nas suas tabelas
-- veiculos / motoristas (mesma empresa da sessão do painel).

-- Exemplo: Avenida Paulista, São Paulo (NÃO é região de emulador — aparece no mapa)
SET @empresa_id   := 1;
SET @veiculo_id    := 66;
SET @motorista_id  := 51;

-- 1) Última posição (obrigatório para o marcador no mapa em tempo real)
INSERT INTO gps_ultima_posicao (
    veiculo_id,
    empresa_id,
    motorista_id,
    latitude,
    longitude,
    velocidade,
    status,
    ignicao,
    ultima_atualizacao,
    endereco,
    data_hora
) VALUES (
    @veiculo_id,
    @empresa_id,
    @motorista_id,
    -23.56141400,
    -46.65613900,
    0.00,
    'parado',
    NULL,
    NOW(),
    'Teste SQL — Av. Paulista, SP',
    NOW()
) ON DUPLICATE KEY UPDATE
    empresa_id = VALUES(empresa_id),
    motorista_id = VALUES(motorista_id),
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    velocidade = VALUES(velocidade),
    status = VALUES(status),
    ignicao = VALUES(ignicao),
    ultima_atualizacao = VALUES(ultima_atualizacao),
    endereco = VALUES(endereco),
    data_hora = VALUES(data_hora);

-- Se sua tabela não tiver status/ignicao/ultima_atualizacao/endereco, use só o bloco "legado" abaixo
-- (descomente e remova o INSERT acima se der erro "Unknown column"):
/*
INSERT INTO gps_ultima_posicao (veiculo_id, empresa_id, motorista_id, latitude, longitude, velocidade, data_hora)
VALUES (@veiculo_id, @empresa_id, @motorista_id, -23.56141400, -46.65613900, 0.00, NOW())
ON DUPLICATE KEY UPDATE
    empresa_id = VALUES(empresa_id),
    motorista_id = VALUES(motorista_id),
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    velocidade = VALUES(velocidade),
    data_hora = VALUES(data_hora);
*/

-- 2) Opcional: pontos no histórico bruto (mapa histórico / trilha se usar gps_logs)
INSERT INTO gps_logs (empresa_id, veiculo_id, motorista_id, latitude, longitude, velocidade, data_hora)
VALUES
    (@empresa_id, @veiculo_id, @motorista_id, -23.56200000, -46.65700000, 15.00, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
    (@empresa_id, @veiculo_id, @motorista_id, -23.56141400, -46.65613900, 0.00, NOW());

-- Depois de rodar: atualize o painel (F5). Se usar Redis (SF_GPS_REDIS_CACHE), pode demorar
-- até o cache expirar ou limpe a chave do veículo.
