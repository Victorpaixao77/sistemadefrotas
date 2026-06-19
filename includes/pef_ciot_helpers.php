<?php
/**
 * Helpers CIOT/PEF — configuração, validação e persistência local.
 */
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/pef_antt_client.php';

if (!function_exists('sf_pef_config')) {
    function sf_pef_config(int $empresaId): array
    {
        static $cache = [];
        if (isset($cache[$empresaId])) {
            return $cache[$empresaId];
        }
        $defaults = [
            'empresa_id' => $empresaId,
            'ambiente' => 'homologacao',
            'cpf_cnpj_interessado' => null,
            'cert_pem_path' => null,
            'cert_key_path' => null,
            'cert_key_pass' => null,
            'ativo' => 0,
        ];
        try {
            $conn = getConnection();
            $stmt = $conn->prepare('SELECT * FROM pef_config_empresa WHERE empresa_id = :e LIMIT 1');
            $stmt->execute([':e' => $empresaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $conn->prepare('INSERT IGNORE INTO pef_config_empresa (empresa_id) VALUES (:e)')->execute([':e' => $empresaId]);
                $cache[$empresaId] = $defaults;
                return $defaults;
            }
            $cache[$empresaId] = array_merge($defaults, $row);
            return $cache[$empresaId];
        } catch (Throwable $e) {
            error_log('sf_pef_config: ' . $e->getMessage());
            $cache[$empresaId] = $defaults;
            return $defaults;
        }
    }
}

if (!function_exists('sf_pef_save_config')) {
    function sf_pef_save_config(int $empresaId, array $data): bool
    {
        try {
            $conn = getConnection();
            $conn->prepare('INSERT IGNORE INTO pef_config_empresa (empresa_id) VALUES (:e)')->execute([':e' => $empresaId]);
            $atual = sf_pef_config($empresaId);
            $amb = in_array($data['ambiente'] ?? '', ['homologacao', 'producao'], true) ? $data['ambiente'] : 'homologacao';
            $cnpj = preg_replace('/\D/', '', (string) ($data['cpf_cnpj_interessado'] ?? ''));
            $pem = trim((string) ($data['cert_pem_path'] ?? $atual['cert_pem_path'] ?? ''));
            $key = trim((string) ($data['cert_key_path'] ?? $atual['cert_key_path'] ?? ''));
            $pass = array_key_exists('cert_key_pass', $data) ? trim((string) $data['cert_key_pass']) : (string) ($atual['cert_key_pass'] ?? '');
            if ($pass === '' || $pass === '********') {
                $pass = (string) ($atual['cert_key_pass'] ?? '');
            }
            $ativo = !empty($data['ativo']) ? 1 : 0;
            $stmt = $conn->prepare(
                'UPDATE pef_config_empresa SET ambiente = :amb, cpf_cnpj_interessado = :cnpj,
                 cert_pem_path = :pem, cert_key_path = :key, cert_key_pass = :pass, ativo = :ativo WHERE empresa_id = :e'
            );
            $stmt->execute([
                ':amb' => $amb,
                ':cnpj' => $cnpj !== '' ? $cnpj : null,
                ':pem' => $pem !== '' ? $pem : null,
                ':key' => $key !== '' ? $key : null,
                ':pass' => $pass !== '' ? $pass : null,
                ':ativo' => $ativo,
                ':e' => $empresaId,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('sf_pef_save_config: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sf_pef_digits')) {
    function sf_pef_digits(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }
}

if (!function_exists('sf_pef_normalizar_rntrc')) {
    /** RNTRC: 8 dígitos → zero à esquerda; 9 válido; 7 inválido. */
    function sf_pef_normalizar_rntrc(?string $rntrc): ?string
    {
        $d = sf_pef_digits($rntrc);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 8) {
            return '0' . $d;
        }
        if (strlen($d) === 9) {
            return $d;
        }
        return null;
    }
}

if (!function_exists('sf_pef_gerar_id_operacao')) {
    /**
     * Gera IdOperacaoTransporte (12 chars) — sequencial por empresa/ano.
     * A ANTT disponibiliza DDL oficial; use este gerador interno até homologar o oficial.
     */
    function sf_pef_gerar_id_operacao(int $empresaId): string
    {
        $conn = getConnection();
        $ano = date('y');
        $prefix = str_pad((string) ($empresaId % 1000), 3, '0', STR_PAD_LEFT) . $ano;
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM pef_ciot_operacoes WHERE empresa_id = :e AND id_operacao_transporte LIKE :pfx"
        );
        $stmt->execute([':e' => $empresaId, ':pfx' => $prefix . '%']);
        $seq = (int) $stmt->fetchColumn() + 1;
        return $prefix . str_pad((string) $seq, 7, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('sf_pef_log')) {
    function sf_pef_log(int $empresaId, ?int $operacaoId, string $servico, ?array $req, ?array $res, int $http, ?string $erro = null): void
    {
        try {
            getConnection()->prepare(
                'INSERT INTO pef_ciot_log (empresa_id, operacao_id, servico, request_json, response_json, http_code, erro)
                 VALUES (:e, :op, :s, :req, :res, :http, :err)'
            )->execute([
                ':e' => $empresaId,
                ':op' => $operacaoId,
                ':s' => $servico,
                ':req' => $req ? json_encode($req, JSON_UNESCAPED_UNICODE) : null,
                ':res' => $res ? json_encode($res, JSON_UNESCAPED_UNICODE) : null,
                ':http' => $http,
                ':err' => $erro,
            ]);
        } catch (Throwable $e) {
            error_log('sf_pef_log: ' . $e->getMessage());
        }
    }
}

if (!function_exists('sf_pef_empresa_cnpj')) {
    function sf_pef_empresa_cnpj(int $empresaId): string
    {
        try {
            $stmt = getConnection()->prepare('SELECT cnpj FROM empresa_clientes WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $empresaId]);
            return sf_pef_digits((string) $stmt->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('sf_pef_montar_declaracao')) {
    /**
     * Monta payload DeclaracaoOperacaoTransporte a partir de array do formulário.
     *
     * @param array<string,mixed> $in
     */
    function sf_pef_montar_declaracao(array $in): array
    {
        $tipoOp = (int) ($in['tipo_operacao'] ?? 1);
        $veiculos = [];
        foreach (($in['veiculos'] ?? []) as $v) {
            if (!is_array($v)) {
                continue;
            }
            $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($v['placa'] ?? '')));
            if ($placa === '') {
                continue;
            }
            $veiculos[] = array_filter([
                'Placa' => $placa,
                'RNTRCVeiculo' => sf_pef_normalizar_rntrc($v['rntrc_veiculo'] ?? null),
                'NumeroEixos' => (string) ($v['numero_eixos'] ?? ''),
            ], static fn ($x) => $x !== null && $x !== '');
        }

        $payload = [
            'IdOperacaoTransporte' => (string) ($in['id_operacao_transporte'] ?? ''),
            'TipoOperacao' => $tipoOp,
            'CpfCnpjContratado' => sf_pef_digits($in['cpf_cnpj_contratado'] ?? ''),
            'RNTRCContratado' => sf_pef_normalizar_rntrc($in['rntrc_contratado'] ?? ''),
            'CpfCnpjContratante' => sf_pef_digits($in['cpf_cnpj_contratante'] ?? ''),
            'ValorFrete' => number_format((float) ($in['valor_frete'] ?? 0), 2, '.', ''),
            'DataDeclaracao' => date('Y-m-d\TH:i:s'),
            'IndContingencia' => !empty($in['ind_contingencia']),
            'DataInicioViagem' => (string) ($in['data_inicio_viagem'] ?? date('Y-m-d')),
            'DataFimViagem' => (string) ($in['data_fim_viagem'] ?? date('Y-m-d')),
            'Veiculos' => $veiculos,
        ];

        if (!empty($in['rntrc_contratante'])) {
            $payload['RNTRCContratante'] = sf_pef_normalizar_rntrc($in['rntrc_contratante']);
        }
        if ($tipoOp !== 3) {
            $payload['CpfCnpjDestinatario'] = sf_pef_digits($in['cpf_cnpj_destinatario'] ?? '');
        }
        if (!empty($in['justificativa_contingencia'])) {
            $payload['JustificativaContingencia'] = (string) $in['justificativa_contingencia'];
        }

        if ($tipoOp !== 3 && !empty($in['codigo_municipio_origem'])) {
            $od = [
                'Origem' => array_filter([
                    'CodigoMunicipioOrigem' => (string) $in['codigo_municipio_origem'],
                    'CepOrigem' => sf_pef_digits($in['cep_origem'] ?? ''),
                ]),
                'Destino' => array_filter([
                    'CodigoMunicipioDestino' => (string) $in['codigo_municipio_destino'],
                    'CepDestino' => sf_pef_digits($in['cep_destino'] ?? ''),
                ]),
                'DistanciaPercorrida' => (int) ($in['distancia_km'] ?? 0),
            ];
            $payload['OrigemDestino'] = [$od];
            $payload['DadosCarga'] = array_filter([
                'CodigoNaturezaCarga' => (string) ($in['codigo_natureza_carga'] ?? ''),
                'PesoCarga' => number_format((float) ($in['peso_carga'] ?? 0), 2, '.', ''),
                'CodigoTipoCarga' => (string) ($in['codigo_tipo_carga'] ?? ''),
            ]);
        }

        if (!empty($in['inf_pagamento']) && is_array($in['inf_pagamento'])) {
            $payload['InfPagamento'] = [$in['inf_pagamento']];
        }

        if ($tipoOp === 1 && isset($in['ind_alto_desempenho'])) {
            $payload['InfIndicadoresOperacionais'] = [
                'IndAltoDesempenho' => !empty($in['ind_alto_desempenho']),
                'IndRetornoVazio' => !empty($in['ind_retorno_vazio']),
                'ComposicaoVeicular' => !empty($in['composicao_veicular']),
            ];
        }

        return $payload;
    }
}

if (!function_exists('sf_pef_tipos_contratacao')) {
    /** @return array<string, string> */
    function sf_pef_tipos_contratacao(): array
    {
        return [
            'tac' => 'TAC — caminhoneiro autônomo contratado',
            'etc_terceirizada' => 'ETC — transportadora terceirizada / subcontratada',
            'agregado' => 'Agregado — veículo de terceiro vinculado',
            'frota_propria' => 'Frota própria — veículos e motoristas da empresa',
        ];
    }
}

if (!function_exists('sf_pef_validar_tipo_contratacao')) {
    /**
     * Regras de obrigatoriedade do CIOT conforme tipo de contratação.
     *
     * @return array{permitido:bool,obrigatorio:bool,nivel:string,mensagem:string}
     */
    function sf_pef_validar_tipo_contratacao(?string $tipo): array
    {
        $tipo = trim((string) $tipo);
        switch ($tipo) {
            case 'frota_propria':
                return [
                    'permitido' => false,
                    'obrigatorio' => false,
                    'nivel' => 'bloqueio',
                    'mensagem' => 'CIOT não se aplica a transporte com frota própria (veículos e motoristas empregados da empresa). '
                        . 'Não é necessário declarar operação na ANTT neste caso. No MDF-e, use tipo emitente carga própria.',
                ];
            case 'tac':
                return [
                    'permitido' => true,
                    'obrigatorio' => true,
                    'nivel' => 'ok',
                    'mensagem' => 'Contratação de TAC: a geração de CIOT é obrigatória quando há pagamento de frete, conforme a ANTT.',
                ];
            case 'etc_terceirizada':
                return [
                    'permitido' => true,
                    'obrigatorio' => true,
                    'nivel' => 'aviso',
                    'mensagem' => 'Subcontratação de ETC: confira se a operação exige CIOT conforme o contrato e a regulamentação ANTT.',
                ];
            case 'agregado':
                return [
                    'permitido' => true,
                    'obrigatorio' => true,
                    'nivel' => 'ok',
                    'mensagem' => 'Agregado/TAC vinculado: CIOT aplicável — informe RNTRC e dados corretos do contratado.',
                ];
            default:
                return [
                    'permitido' => false,
                    'obrigatorio' => false,
                    'nivel' => 'bloqueio',
                    'mensagem' => 'Selecione o tipo de contratação do transporte antes de declarar o CIOT.',
                ];
        }
    }
}

if (!function_exists('sf_pef_client_or_fail')) {
    function sf_pef_client_or_fail(int $empresaId): PefAnttClient
    {
        $cfg = sf_pef_config($empresaId);
        if (empty($cfg['ativo'])) {
            throw new RuntimeException('Integração PEF/CIOT não está ativa para esta empresa.');
        }
        return PefAnttClient::fromEmpresaConfig($cfg);
    }
}

if (!function_exists('sf_pef_ensure_mdfe_link_schema')) {
    function sf_pef_ensure_mdfe_link_schema(?PDO $conn = null): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $conn = $conn ?? getConnection();
        $sqlFile = dirname(__DIR__) . '/sql/mdfe_fase2_pef_link.sql';
        if (!is_readable($sqlFile)) {
            return;
        }
        try {
            foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($sqlFile)))) as $stmt) {
                if ($stmt === '' || stripos($stmt, '--') === 0) {
                    continue;
                }
                if (stripos($stmt, 'ALTER') === false && stripos($stmt, 'CREATE') === false) {
                    continue;
                }
                try {
                    $conn->exec($stmt);
                } catch (Throwable $e) {
                    error_log('sf_pef_ensure_mdfe_link_schema: ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log('sf_pef_ensure_mdfe_link_schema read: ' . $e->getMessage());
        }
    }
}

if (!function_exists('sf_pef_ciot_disponiveis_mdfe')) {
    /**
     * CIOTs declarados na ANTT ainda não vinculados a um MDF-e.
     *
     * @return array<int,array<string,mixed>>
     */
    function sf_pef_ciot_disponiveis_mdfe(int $empresaId, ?int $rotaId = null, int $limit = 50): array
    {
        sf_pef_ensure_mdfe_link_schema();
        $conn = getConnection();
        $sql = 'SELECT id, rota_id, id_operacao_transporte, codigo_identificacao, codigo_verificador,
                       cpf_cnpj_contratado, valor_frete, data_declaracao, data_inicio_viagem, data_fim_viagem,
                       protocolo_antt, status, criado_em
                FROM pef_ciot_operacoes
                WHERE empresa_id = :e AND status = \'declarado\'
                  AND (mdfe_id IS NULL OR mdfe_id = 0)
                  AND codigo_identificacao IS NOT NULL AND codigo_identificacao <> \'\'';
        $params = [':e' => $empresaId];
        if ($rotaId !== null && $rotaId > 0) {
            $sql .= ' AND rota_id = :rota';
            $params[':rota'] = $rotaId;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit));
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sf_pef_ciot_para_mdfe_item')) {
    /** @param array<string,mixed> $op */
    function sf_pef_ciot_para_mdfe_item(array $op): array
    {
        $ciot = preg_replace('/\D/', '', (string) ($op['codigo_identificacao'] ?? ''));
        return [
            'numeroCiot' => $ciot,
            'numero_ciot' => $ciot,
            'valorFrete' => (float) ($op['valor_frete'] ?? 0),
            'valor_frete' => (float) ($op['valor_frete'] ?? 0),
            'cpfCnpjTac' => sf_pef_digits((string) ($op['cpf_cnpj_contratado'] ?? '')),
            'cpf_cnpj_tac' => sf_pef_digits((string) ($op['cpf_cnpj_contratado'] ?? '')),
            'ipef' => '',
            'pefOperacaoId' => (int) ($op['id'] ?? 0),
            'pef_operacao_id' => (int) ($op['id'] ?? 0),
            'origem' => 'pef',
        ];
    }
}

if (!function_exists('sf_pef_vincular_operacao_mdfe')) {
    function sf_pef_vincular_operacao_mdfe(int $empresaId, int $pefOperacaoId, int $mdfeId): void
    {
        if ($pefOperacaoId <= 0 || $mdfeId <= 0) {
            return;
        }
        sf_pef_ensure_mdfe_link_schema();
        $conn = getConnection();
        $stmt = $conn->prepare('
            UPDATE pef_ciot_operacoes
            SET mdfe_id = :mdfe, atualizado_em = NOW()
            WHERE id = :id AND empresa_id = :e AND status = \'declarado\'
              AND (mdfe_id IS NULL OR mdfe_id = 0 OR mdfe_id = :mdfe2)
        ');
        $stmt->execute([
            ':mdfe' => $mdfeId,
            ':mdfe2' => $mdfeId,
            ':id' => $pefOperacaoId,
            ':e' => $empresaId,
        ]);
    }
}

if (!function_exists('sf_pef_ciot_auto_por_rota')) {
    /** @return array<string,mixed>|null */
    function sf_pef_ciot_auto_por_rota(int $empresaId, int $rotaId): ?array
    {
        if ($rotaId <= 0) {
            return null;
        }
        $lista = sf_pef_ciot_disponiveis_mdfe($empresaId, $rotaId, 1);
        if (!$lista) {
            return null;
        }
        return sf_pef_ciot_para_mdfe_item($lista[0]);
    }
}
