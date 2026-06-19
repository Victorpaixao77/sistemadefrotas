<?php
/** Fiscal — CIOT/PEF ANTT e configuração fiscal */
return [
'ciot_pef' => [
    'title' => 'CIOT — PEF ANTT',
    'visao_geral' => '<p>O <strong>CIOT</strong> (Código Identificador da Operação de Transporte) é gerado via <strong>PEF</strong> (Pagamento Eletrônico de Frete) junto à ANTT. '
        . 'É diferente do campo CIOT no <strong>MDF-e</strong>: aqui você <em>emite</em> o número na ANTT; no MDF-e você <em>informa</em> o CIOT já gerado no bloco rodoviário.</p>'
        . '<p>O CIOT é exigido principalmente quando há <strong>contratação de transporte rodoviário</strong> por <strong>TAC</strong> (Transportador Autônomo de Cargas) '
        . 'ou por <strong>ETC subcontratada</strong>, conforme regulamentação da ANTT — não para frota própria com motoristas empregados.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Declarar operação</strong> de transporte e obter CIOT + código verificador.',
        '<strong>Listar operações</strong> com status: declarado, encerrado, cancelado, erro.',
        '<strong>Cancelar e encerrar</strong> operações já declaradas na ANTT.',
        '<strong>Consultar situação</strong> do transportador e <strong>frota</strong> cadastrada na ANTT.',
        '<strong>Copiar CIOT</strong> para colar no MDF-e rodoviário.',
        '<strong>Validação de obrigatoriedade</strong> conforme tipo de contratação (frota própria vs terceiro/TAC).',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Configurar integração (uma vez)', 'sub' => [
            'Acesse <strong>Configurações → Fiscal &amp; Multas</strong>.',
            'No card <strong>CIOT — PEF ANTT</strong>, informe ambiente (homologação ou produção).',
            'Cadastre CNPJ/CPF do titular do certificado ICP-Brasil.',
            'Informe caminhos dos arquivos <strong>PEM</strong> (certificado + chave privada) fora do webroot.',
            'Senha da chave no formulário ou variável de ambiente <code>SF_PEF_CERT_PASS</code>.',
            'Marque <strong>Integração CIOT/PEF ativa</strong> e salve.',
        ]],
        ['text' => 'Declarar CIOT', 'sub' => [
            'Acesse <strong>Sistema Fiscal → CIOT (PEF ANTT)</strong>.',
            'Clique em <strong>Declarar CIOT</strong>.',
            'Selecione o <strong>tipo de contratação</strong> (o sistema bloqueia frota própria).',
            'Preencha contratado, RNTRC, contratante, frete, datas, veículo e municípios IBGE.',
            'Envie à ANTT e aguarde o código de 16 caracteres.',
        ]],
        ['text' => 'Usar no MDF-e', 'sub' => [
            'Copie o CIOT gerado (botão <strong>Copiar CIOT</strong>).',
            'No MDF-e rodoviário, informe no campo CIOT quando o transportador for TAC com pagamento de frete.',
            'O CIOT do PEF e o do MDF-e são complementares: emissão aqui, referência no documento fiscal.',
        ]],
        ['text' => 'Homologação real com a ANTT', 'sub' => [
            'Certificado e-CNPJ/e-CPF convertido para PEM.',
            'Credenciamento PEF na ANTT (homologação antes de produção).',
            'Dados reais: RNTRC válido, municípios IBGE, natureza da carga, peso e distância.',
        ]],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Operações declaradas</strong> no período.',
        '<strong>CIOT ativos</strong> (declarados, não encerrados/cancelados).',
        '<strong>Erros ANTT</strong> — revisar log e payload.',
        '<strong>Status por operação</strong> na listagem.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#dcfce7', 'label' => 'Declarado', 'desc' => 'CIOT gerado com sucesso na ANTT'],
        ['color' => '#dbeafe', 'label' => 'Encerrado', 'desc' => 'Operação finalizada na ANTT'],
        ['color' => '#f1f5f9', 'label' => 'Cancelado', 'desc' => 'Operação cancelada'],
        ['color' => '#fee2e2', 'label' => 'Erro', 'desc' => 'Rejeição ou falha na comunicação'],
        ['color' => '#fef9c3', 'label' => 'Rascunho', 'desc' => 'Registro local antes do retorno ANTT'],
    ]),
    'dicas' => sf_hlp_ul([
        '<strong>Gera CIOT quando:</strong> contrata caminhoneiro autônomo (TAC) ou subcontrata transportadora (ETC) em operação que exige registro ANTT.',
        '<strong>Não gera CIOT quando:</strong> transporte com veículos e motoristas próprios (frota própria) — o sistema bloqueia a declaração nesse caso.',
        '<strong>Exemplo:</strong> caminhões próprios + motoristas CLT → sem CIOT. Autônomo contratado para levar carga → gera CIOT.',
        'Homologação ANTT: <code>appservices-hml.antt.gov.br/pefServices</code>. Produção: <code>appservices.antt.gov.br/pefServices</code>.',
        'Documentação técnica: <code>docs/PEF_CIOT_INTEGRACAO.md</code> e <code>docs/pef/dcs_pef_v1-1.pdf</code>.',
    ]),
],
'mdfe' => [
    'title' => 'MDF-e — Manifesto Eletrônico',
    'visao_geral' => '<p>O <strong>MDF-e</strong> (layout <strong>4.00</strong>, PL_MDFe_400) agrupa CT-e e/ou NF-e em uma viagem rodoviária e é transmitido à <strong>SEFAZ</strong> (SVRS). '
        . 'Use o wizard para tipo de emitente, documentos por município de descarga, CIOT, pagamento de frete, vale-pedágio e demais blocos exigidos pela NT.</p>'
        . '<p><strong>Cancelamento</strong> (evento 110111): até 24h após autorização, com justificativa. '
        . '<strong>Encerramento</strong> (110112): obrigatório ao fim da viagem. <strong>Inclusão de condutor</strong> (110114): durante viagem autorizada.</p>',
    'funcionalidades' => sf_hlp_ul([
        'Wizard completo com validação NT2025.001 antes de emitir.',
        'Emissão via SEFAZ (MDFeRecepcaoSinc) com XML 4.00 montado a partir do wizard.',
        'Cancelar, encerrar e incluir condutor com eventos reais na SEFAZ.',
        'Vínculo de CT-e, NF-e, CIOT (PEF ANTT), RNTRC e dados rodoviários.',
        'Persistência estruturada de pagamentos, seguros, produtos predominantes e lacres.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Pré-requisitos', 'sub' => [
            'Certificado A1 em <strong>Configurações → Fiscal</strong>.',
            'CT-e autorizados e/ou NF-e recebidas para vincular ao manifesto.',
            'RNTRC, veículo com placa e motorista com CPF válidos.',
        ]],
        ['text' => 'Criar e emitir', 'sub' => [
            'Clique em <strong>Novo MDF-e</strong> e preencha o wizard (tipo emitente, UFs, municípios, documentos).',
            'Informe CIOT quando aplicável (TAC/terceiro — gere antes em CIOT PEF ANTT).',
            'Salve como rascunho ou envie para a SEFAZ quando a validação estiver verde.',
        ]],
        ['text' => 'Após autorização', 'sub' => [
            '<strong>Encerrar</strong> ao concluir a entrega (município de encerramento = descarga).',
            '<strong>Cancelar</strong> só dentro de 24h e antes do encerramento, com justificativa ≥ 15 caracteres.',
            '<strong>Incluir condutor</strong> para troca de motorista em viagem.',
        ]],
    ]),
    'dicas' => sf_hlp_ul([
        'Manual de negócio: FAQ SEFAZ (<code>docs/fiscal/DF_e_MDF-e.pdf</code>). Integração técnica: layout 4.00 + webservices SVRS — não use API Ophos v3.00.',
        'Tipo emitente 1 = prestador de serviço; 2 = carga própria; 3 = globalizado — afeta documentos e transportador.',
        'Encerramento sem evento SEFAZ gera multa; o sistema envia o evento 110112 automaticamente.',
        'DAMDFE: ícone PDF na listagem (autorizado/encerrado). Layout oficial exige <code>composer install</code> — ver <code>docs/PRODUCAO_COMPOSER_FISCAL.md</code>.',
        'CIOT PEF: use <strong>Importar do PEF</strong> no wizard ou vincule pela mesma <code>rota_id</code> ao criar o MDF-e.',
        'Execute <code>composer install</code> na raiz para nfephp-org/sped-mdfe (recomendado); há fallback manual com OpenSSL.',
    ]),
],
'configuracoes_fiscal' => [
    'title' => 'Configurações — Fiscal & Multas',
    'visao_geral' => '<p>Aba <strong>Fiscal &amp; Multas</strong> das configurações da empresa: certificado digital A1 (SEFAZ), DETRAN/multas, ambiente fiscal (NF-e, CT-e, MDF-e), RNTRC e integração <strong>CIOT/PEF ANTT</strong>.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Certificado Digital A1:</strong> upload .pfx/.p12 para emissão fiscal na SEFAZ.',
        '<strong>DETRAN:</strong> consulta de multas via WSDenatran com certificado próprio.',
        '<strong>Ambiente SEFAZ:</strong> homologação ou produção (vale para NF-e, CT-e e MDF-e).',
        '<strong>Dados da empresa:</strong> CNPJ, razão social, RNTRC, município IBGE — usados no MDF-e.',
        '<strong>CIOT — PEF ANTT:</strong> certificado mTLS PEM, ambiente ANTT e ativação da integração.',
        '<strong>Testes SEFAZ:</strong> conexão, certificado e diagnóstico.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'CIOT / PEF ANTT', 'sub' => [
            'Role até o card <strong>CIOT — PEF ANTT</strong>.',
            'Escolha homologação (testes) ou produção (real).',
            'Informe CNPJ/CPF do titular do certificado ICP-Brasil.',
            'Caminhos PEM do certificado e da chave privada (recomendado fora de <code>htdocs</code>).',
            'Senha da chave ou <code>SF_PEF_CERT_PASS</code> no servidor.',
            'Ative a integração e salve.',
            'Depois use <strong>Sistema Fiscal → CIOT (PEF ANTT)</strong> para declarar operações.',
        ]],
        ['text' => 'Certificado SEFAZ (NF-e/MDF-e)', 'sub' => [
            'Envie .pfx/.p12 no card Certificado Digital A1.',
            'A validade é lida automaticamente do arquivo.',
        ]],
        ['text' => 'Ambiente fiscal', 'sub' => [
            'Preencha CNPJ, endereço e RNTRC.',
            'Use homologação até validar todos os fluxos.',
            'Salve antes de emitir documentos em produção.',
        ]],
    ]),
    'dicas' => sf_hlp_ul([
        'CIOT só é necessário para TAC/terceiro — não para frota própria. Veja a ajuda da tela CIOT para detalhes.',
        'O certificado PEF (mTLS ANTT) pode ser o mesmo e-CNPJ da empresa, em formato PEM, distinto do .pfx da SEFAZ.',
        'RNTRC informado aqui alimenta validações do MDF-e rodoviário.',
        'Após salvar PEF, teste primeiro em homologação ANTT.',
    ]),
],
];
