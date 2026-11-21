<?php
/**
 * Ajuda — Control Center (pages_adm).
 * Chaves: adm_<pagina.php> | adm_dashboard (index.php)
 *
 * Cada entrada tem:
 *   - visao_geral   (para que serve)
 *   - funcionalidades (o que faz)
 *   - como_usar      (PASSO A PASSO detalhado — ordenado)
 *   - fluxo          (DE ONDE VEM → o que fazer aqui → PARA ONDE VAI / próxima tela)
 *   - dicas          (boas práticas)
 */
declare(strict_types=1);

$adm_h = static function (
    string $title,
    string $paraQue,
    array $oQueFaz,
    array $comoUsar,
    array $fluxo = [],
    array $dicas = []
): array {
    $out = [
        'title' => $title,
        'visao_geral' => '<p>' . $paraQue . '</p>',
        'funcionalidades' => sf_hlp_ul($oQueFaz),
        'como_usar' => sf_hlp_ol($comoUsar),
    ];
    if ($fluxo !== []) {
        // Fluxo é sequencial → lista ordenada (estágio a estágio).
        $out['fluxo'] = sf_hlp_ol($fluxo);
    }
    if ($dicas !== []) {
        $out['dicas'] = sf_hlp_ul($dicas);
    }
    return $out;
};

return [
    'adm_dashboard' => $adm_h(
        'Dashboard Executivo',
        'Torre de comando da plataforma Frotec SaaS. Serve para enxergar, em um só lugar, saúde dos clientes, receita recorrente, uso do sistema e infraestrutura do servidor.',
        [
            '<strong>KPIs de clientes:</strong> ativas, trial, bloqueadas e canceladas.',
            '<strong>Financeiro:</strong> MRR, ARR, receita do dia, a receber e inadimplentes.',
            '<strong>Uso:</strong> empresas/usuários online, sessões, logins e novos cadastros.',
            '<strong>Infra:</strong> CPU, RAM, disco, cache e tempo médio de API.',
            '<strong>Gráficos e tabela</strong> com ranking de empresas por frota e valor.',
        ],
        [
            ['text' => 'Abrir e ler o painel', 'sub' => [
                'Faça login em pages_adm — você cai direto aqui.',
                'Os cards carregam via API e se atualizam sozinhos (não precisa recarregar a página).',
                'Leia de cima para baixo: clientes → financeiro → uso → infra.',
            ]],
            ['text' => 'Investigar um número que chamou atenção', 'sub' => [
                'Clique no card correspondente ou use o menu lateral.',
                'Ex.: MRR caiu → abra «Financeiro SaaS»; pico de erro → abra «Central de erros».',
            ]],
            ['text' => 'Rotina diária', 'sub' => [
                'Comece o dia por esta tela para detectar queda de MRR, inadimplência nova ou incidente.',
                'Se tudo verde, siga para as telas operacionais só se houver alerta.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> agrega dados de Empresas, Financeiro, Uso e Sistema.',
            '<strong>Aqui:</strong> você tem o panorama e decide onde aprofundar.',
            '<strong>Para onde vai:</strong> cada card é uma porta — clientes → <em>Empresas</em>; receita → <em>Financeiro SaaS</em>; infra → <em>Saúde/Banco</em>; risco → <em>Health Score</em>.',
        ],
        ['Use como primeira tela do dia.', 'Indicadores «—» significam que a API ainda carrega ou não há dado — não é erro.']
    ),

    'adm_monitor' => $adm_h(
        'Monitor ao vivo',
        'Painel operacional em tempo quase real da plataforma. Serve para acompanhar quem está online, tráfego e eventos enquanto você opera o suporte ou o NOC.',
        [
            '<strong>Sessões e presença</strong> de empresas e usuários conectados.',
            '<strong>Feed de atividade</strong> recente na plataforma.',
            '<strong>Indicadores ao vivo</strong> atualizados por polling.',
        ],
        [
            ['text' => 'Acompanhar a operação agora', 'sub' => [
                'Abra a tela e deixe-a aberta — ela se atualiza sozinha por polling.',
                'Observe quantas empresas/usuários estão online e o feed de ações.',
            ]],
            ['text' => 'Durante um incidente', 'sub' => [
                'Compare o número de online com o normal do horário (veja o Heatmap).',
                'Se algo estiver estranho, abra «Saúde (NOC)» para diagnosticar o componente.',
                'Use «Timeline» para ver a sequência exata de eventos.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> sessões ativas e log de atividade da plataforma.',
            '<strong>Aqui:</strong> você observa o "agora" e detecta anomalia.',
            '<strong>Para onde vai:</strong> anomalia → <em>Saúde (NOC)</em> e <em>Central de erros</em>; reconstruir causa → <em>Timeline</em>.',
        ],
        ['Combine com Timeline para entender a sequência de eventos.']
    ),

    'adm_saude' => $adm_h(
        'Saúde (NOC)',
        'Centro de saúde técnica da plataforma (Network Operations Center). Serve para diagnosticar disponibilidade, latência, filas e componentes críticos antes que clientes percebam.',
        [
            '<strong>Status agregado</strong> de serviços (banco, cache, APIs).',
            '<strong>Métricas de uptime</strong> e tempo de resposta.',
            '<strong>Alertas recentes</strong> ligados à observabilidade.',
        ],
        [
            ['text' => 'Triagem de um incidente', 'sub' => [
                'Abra a tela assim que houver reclamação de lentidão/queda.',
                'Localize o componente em vermelho/amarelo (banco, cache, API).',
                'Anote qual é e abra a tela específica dele.',
            ]],
            ['text' => 'Aprofundar por componente', 'sub' => [
                'Banco lento → «Banco de dados» + «SQL ao vivo».',
                'Integração falhando → «APIs externas».',
                'Erros subindo → «Central de erros» + «Central de logs».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> checagens internas de banco, cache e integrações.',
            '<strong>Aqui:</strong> você identifica QUAL componente falhou.',
            '<strong>Para onde vai:</strong> banco → <em>Banco/SQL ao vivo</em>; terceiro → <em>APIs externas</em>; bug → <em>Erros/Logs</em>; SLA → <em>SLA</em>.',
        ],
        ['Em produção, complemente com monitor externo (UptimeRobot, Datadog).']
    ),

    'adm_sla' => $adm_h(
        'SLA e disponibilidade',
        'Mede cumprimento de SLA da plataforma: uptime, tempo de resposta e picos de uso. Serve para reportar disponibilidade a clientes enterprise e auditar contratos.',
        [
            '<strong>Uptime %</strong> calculado internamente.',
            '<strong>Tempo médio de resposta</strong> das APIs.',
            '<strong>Pico de usuários</strong> no período.',
        ],
        [
            ['text' => 'Consultar disponibilidade', 'sub' => [
                'Abra a tela — ela atualiza a cada ~30s.',
                'Leia o uptime % e o tempo médio de resposta do período.',
                'Se aparecer o banner de "valor estimado", registre a ressalva no relatório.',
            ]],
            ['text' => 'Reportar a um cliente enterprise', 'sub' => [
                'Anote os números do mês fechado.',
                'Cruze com «Saúde (NOC)» e «Timeline» para justificar qualquer queda.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> métricas de uptime/latência coletadas internamente.',
            '<strong>Aqui:</strong> você consolida o SLA do período.',
            '<strong>Para onde vai:</strong> queda → investigar em <em>Saúde/Performance</em>; relatório → cliente/contrato.',
        ],
        ['Valores são indicativos — valide com monitor externo em produção.']
    ),

    'adm_heatmap' => $adm_h(
        'Heatmap de uso',
        'Mostra quando os clientes mais usam o sistema (dia da semana × hora). Serve para planejar janelas de deploy, manutenção e dimensionar suporte.',
        [
            '<strong>Mapa de calor</strong> de acessos e ações.',
            '<strong>Identificação de picos</strong> (manhã de segunda, fechamento de mês).',
        ],
        [
            ['text' => 'Planejar uma janela de manutenção/deploy', 'sub' => [
                'Abra o heatmap e localize as faixas mais claras (menor uso).',
                'Escolha um horário fora das faixas vermelhas (pico).',
                'Agende o deploy/manutenção para essa janela.',
            ]],
            ['text' => 'Dimensionar suporte', 'sub' => [
                'Veja em que horas o uso concentra e escale a equipe nesses picos.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> logs de acesso/ações agregados por dia×hora.',
            '<strong>Aqui:</strong> você descobre a melhor janela de baixo uso.',
            '<strong>Para onde vai:</strong> janela escolhida → agendar em <em>Deploy</em>/<em>Jobs</em> e avisar em <em>Comunicação</em>.',
        ],
        ['Cruze com Timeline para ver eventos que causaram picos.']
    ),

    'adm_timeline' => $adm_h(
        'Timeline',
        'Linha do tempo unificada de eventos da plataforma (logins, deploys, erros, jobs). Serve para reconstruir o que aconteceu antes/durante um incidente.',
        [
            '<strong>Eventos cronológicos</strong> de múltiplas fontes.',
            '<strong>Filtros e refresh</strong> automático (~30s).',
        ],
        [
            ['text' => 'Reconstruir um incidente', 'sub' => [
                'Abra a Timeline e vá até o horário do problema.',
                'Role do evento mais recente para trás, procurando o gatilho (um deploy, um job, um pico de erro).',
                'Correlacione o gatilho com «Central de erros» e «Deploy».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> junta eventos de deploy, jobs, erros, logins e segurança.',
            '<strong>Aqui:</strong> você monta a linha causal do que aconteceu.',
            '<strong>Para onde vai:</strong> gatilho identificado → <em>Deploy</em> (rollback), <em>Jobs</em> (reexecutar) ou <em>Erros</em> (corrigir).',
        ],
        ['Anote a sequência antes de abrir um ticket interno.']
    ),

    'adm_mapa_clientes' => $adm_h(
        'Mapa de clientes',
        'Mapa mundial com clientes por cidade, status online/offline e concentração geográfica. Serve para visão comercial e suporte regional.',
        [
            '<strong>Marcadores por cidade</strong> com dados agregados.',
            '<strong>Status de conexão</strong> quando disponível.',
            '<strong>Leaflet</strong> com zoom e popups informativos.',
        ],
        [
            ['text' => 'Explorar a base geograficamente', 'sub' => [
                'Aproxime o zoom nas regiões com mais marcadores.',
                'Clique num marcador para ver os dados da(s) empresa(s) daquela cidade.',
            ]],
            ['text' => 'Usar comercialmente', 'sub' => [
                'Identifique regiões com concentração de clientes (ou vazios de mercado).',
                'Leve os insights para o «Painel comercial».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> endereço/cidade cadastrados em Empresas.',
            '<strong>Aqui:</strong> você vê a distribuição geográfica.',
            '<strong>Para onde vai:</strong> detalhe do cliente → <em>Empresas</em>; estratégia → <em>Comercial</em>.',
        ],
        ['Geolocalização depende de endereço/cidade cadastrados na empresa.']
    ),

    'adm_empresas' => $adm_h(
        'Empresas',
        'Cadastro mestre das empresas clientes do SaaS. Serve para criar, editar, bloquear, vincular plano e acessar o ambiente de cada cliente.',
        [
            '<strong>CRUD de empresas</strong> (razão social, CNPJ, plano, status SaaS).',
            '<strong>Valor por veículo</strong> e limites comerciais.',
            '<strong>Ações rápidas:</strong> impersonar/suporte, editar, bloquear.',
            '<strong>Modal Nova Empresa</strong> com dados iniciais e plano.',
        ],
        [
            ['text' => 'Cadastrar um novo cliente', 'sub' => [
                'Confirme que o plano já existe em «Planos» (senão crie antes).',
                'Clique em «Nova Empresa» na toolbar.',
                'Preencha razão social, CNPJ, e-mail e telefone de contato.',
                'Escolha o plano e o status inicial (trial ou ativa).',
                'Defina o valor por veículo / limites comerciais.',
                'Salve — a empresa entra na lista e já pode receber usuários.',
            ]],
            ['text' => 'Editar ou bloquear um cliente', 'sub' => [
                'Clique no botão editar (lápis) na linha da empresa.',
                'Para suspender inadimplente, mude o status para «bloqueada» e salve — o login do cliente passa a ser barrado.',
            ]],
            ['text' => 'Entrar como o cliente (suporte)', 'sub' => [
                'Use a ação impersonar/suporte na linha.',
                'Você cai no dashboard do gestor daquela empresa; a sessão fica marcada para auditoria.',
                'Ao terminar, saia e a ação fica registrada.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> depende de <em>Planos</em> (tier já criado).',
            '<strong>Aqui:</strong> você cria a empresa (gera empresa_adm + vínculo com a frota) e define plano/status.',
            '<strong>Depois:</strong> <em>Usuários</em> (criar o gestor) → <em>Licenciamento</em> (limites valem) → cliente faz login no <em>Sistema de frotas</em>.',
            '<strong>Manutenção:</strong> volte aqui para editar/bloquear; impersonação gera registro em <em>Auditoria</em>.',
        ],
        ['Trial expirado pode virar bloqueio automático se configurado em Licenciamento.', 'Sempre confira empresa_adm ↔ empresa_clientes (frota) ao criar.']
    ),

    'adm_planos' => $adm_h(
        'Planos',
        'Define os planos comerciais (nome, limites, preço base). Serve para padronizar o que cada tier oferece antes de associar empresas.',
        [
            '<strong>Lista de planos</strong> com veículos, módulos e valores.',
            '<strong>Criar/editar plano</strong> usado no cadastro de empresas.',
        ],
        [
            ['text' => 'Criar um novo tier', 'sub' => [
                'Clique em adicionar plano.',
                'Dê um nome claro (Starter, Pro, Enterprise).',
                'Defina limite de veículos, módulos inclusos e preço base.',
                'Salve — o plano fica disponível no cadastro de Empresas.',
            ]],
            ['text' => 'Editar um plano existente', 'sub' => [
                'Ajuste limites/preço e salve.',
                'Comunique clientes afetados — mudança não altera contratos retroativamente.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> ponto de partida do fluxo comercial (nada antes).',
            '<strong>Aqui:</strong> você define o que cada tier oferece.',
            '<strong>Para onde vai:</strong> o plano é escolhido em <em>Empresas</em> e seus limites passam a valer em <em>Licenciamento</em>; add-ons ficam em <em>Marketplace</em>.',
        ],
        ['Mudança de plano não altera retroativamente contratos — comunique clientes afetados.']
    ),

    'adm_usuarios' => $adm_h(
        'Usuários',
        'Gestão de usuários administrativos e gestores vinculados às empresas. Serve para criar logins, resetar acesso, 2FA e permissões.',
        [
            '<strong>Listagem e busca</strong> por empresa/e-mail.',
            '<strong>Criar/editar usuário</strong> com tipo e status.',
            '<strong>Vínculo empresa_cliente</strong> para acesso ao sistema de frotas.',
        ],
        [
            ['text' => 'Criar o gestor de uma empresa', 'sub' => [
                'Confirme que a empresa já existe em «Empresas».',
                'Clique em novo usuário.',
                'Informe nome, e-mail e selecione a empresa/vínculo.',
                'Defina o tipo (gestor) e o status ativo.',
                'Salve e oriente o cliente a definir a senha pelo fluxo de redefinição.',
            ]],
            ['text' => 'Resetar acesso / pedir 2FA', 'sub' => [
                'Use o fluxo de redefinição de senha (nunca envie senha por chat).',
                'Peça ao gestor ativar 2FA em Configurações do sistema de frotas.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> a empresa precisa existir em <em>Empresas</em>.',
            '<strong>Aqui:</strong> você cria o login e o vínculo empresa_cliente.',
            '<strong>Para onde vai:</strong> o gestor faz login no <em>Sistema de frotas</em>; ações ficam auditáveis em <em>Log de acessos</em>/<em>Auditoria</em>.',
        ],
        ['Nunca compartilhe senha por chat — use fluxo de redefinição.', 'Admin da plataforma ≠ usuário gestor da frota.']
    ),

    'adm_licenciamento' => $adm_h(
        'Licenciamento',
        'Centro de limites e consumo por empresa: veículos, IA, storage, API. Serve para enforcement do SaaS e upsell quando cliente estoura cota.',
        [
            '<strong>Uso vs limite</strong> por recurso e empresa.',
            '<strong>Status de licença</strong> e módulos ativos.',
            '<strong>Visão consolidada</strong> para cobrança e suporte.',
        ],
        [
            ['text' => 'Verificar se um cliente estourou a cota', 'sub' => [
                'Busque a empresa na lista.',
                'Compare as barras de uso (veículos, IA, storage, API) com os limites do plano.',
                'Se estourou, decida: subir de plano (upsell) ou bloquear conforme política.',
            ]],
            ['text' => 'Ajustar/aplicar limite', 'sub' => [
                'Altere o plano em «Empresas»/«Planos» ou ajuste o limite específico.',
                'O enforcement é aplicado nas APIs do gestor automaticamente.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> limites vêm de <em>Planos</em>; consumo vem do uso real do gestor.',
            '<strong>Aqui:</strong> você compara uso × limite e decide.',
            '<strong>Para onde vai:</strong> upsell → <em>Planos/Empresas</em>; add-on → <em>Marketplace</em>; storage cheio → <em>Storage</em>.',
        ],
        ['Integrado a sf_licenca_enforce_json nas APIs do gestor.']
    ),

    'adm_marketplace' => $adm_h(
        'Marketplace',
        'Catálogo de módulos opcionais (IA, CT-e, CRM, etc.) ativáveis por empresa. Serve para monetizar add-ons sem redeploy.',
        [
            '<strong>Módulos disponíveis</strong> na plataforma.',
            '<strong>Ativar/desativar</strong> por cliente.',
        ],
        [
            ['text' => 'Liberar um módulo para um cliente', 'sub' => [
                'Localize a empresa.',
                'Ative o módulo desejado (ex.: IA, CT-e, CRM).',
                'Se for IA, confirme que há chave configurada em «Configurações CC».',
                'O cliente vê o menu correspondente após o próximo login.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> empresa já cadastrada; alguns módulos exigem config em <em>Configurações CC</em>.',
            '<strong>Aqui:</strong> você liga/desliga add-ons por cliente.',
            '<strong>Para onde vai:</strong> o consumo do add-on aparece em <em>Licenciamento</em> e a cobrança em <em>Financeiro SaaS</em>.',
        ],
        ['Módulo IA exige chave configurada em Configurações CC.']
    ),

    'adm_health_score' => $adm_h(
        'Health Score',
        'Nota de saúde por empresa combinando segurança, uso, financeiro e performance. Serve para priorizar sucesso do cliente e churn prevention.',
        [
            '<strong>Score 0–100</strong> por dimensão.',
            '<strong>Explicação do cálculo</strong> por empresa.',
            '<strong>Ranking</strong> de clientes em risco.',
        ],
        [
            ['text' => 'Priorizar sucesso do cliente (CS)', 'sub' => [
                'Ordene a lista pelo menor score.',
                'Abra o detalhe da empresa e veja qual dimensão está vermelha (pouco uso, inadimplência, segurança).',
                'Aja na causa: baixo uso → contato de CS; inadimplência → «Financeiro SaaS».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> combina sinais de <em>Uso</em>, <em>Financeiro</em>, <em>Segurança</em> e <em>Performance</em>.',
            '<strong>Aqui:</strong> você identifica clientes em risco de churn.',
            '<strong>Para onde vai:</strong> risco financeiro → <em>Financeiro SaaS</em>; oportunidade → <em>Comercial/IA comercial</em>.',
        ],
        ['Score baixo + trial = candidato a contato comercial imediato.']
    ),

    'adm_suporte' => $adm_h(
        'Modo suporte',
        'Permite ao admin acessar o sistema de frotas como um cliente (impersonação controlada). Serve para reproduzir bugs e ajudar sem pedir senha.',
        [
            '<strong>Seleção de empresa</strong> para entrar como suporte.',
            '<strong>Sessão marcada</strong> como impersonação (auditoria).',
            '<strong>Retorno</strong> ao Control Center.',
        ],
        [
            ['text' => 'Atender um chamado', 'sub' => [
                'Tenha um ticket aberto antes de impersonar.',
                'Escolha a empresa e confirme o acesso.',
                'Você cai no dashboard do gestor daquela empresa.',
                'Reproduza o problema; ao terminar, saia e registre o que foi feito.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> empresa listada em <em>Empresas</em>.',
            '<strong>Aqui:</strong> você entra como o cliente para diagnosticar.',
            '<strong>Para onde vai:</strong> toda impersonação é registrada em <em>Auditoria</em>; bug encontrado → <em>Erros</em>/repositório.',
        ],
        ['Toda impersonação deve ser auditável — use só com ticket aberto.', 'Não altere dados sensíveis sem autorização do cliente.']
    ),

    'adm_financeiro_executivo' => $adm_h(
        'Financeiro SaaS',
        'Visão financeira da operação SaaS: receita, inadimplência, projeções. Serve à diretoria da Frotec, não ao financeiro do transportador.',
        [
            '<strong>MRR/ARR e receita</strong> consolidados.',
            '<strong>Inadimplentes</strong> e aging.',
            '<strong>Gráficos de tendência</strong> de faturamento da plataforma.',
        ],
        [
            ['text' => 'Fazer o fechamento mensal', 'sub' => [
                'Filtre pelo mês que está fechando.',
                'Leia MRR/ARR, receita realizada e a receber.',
                'Abra a lista de inadimplentes e o aging.',
                'Exporte/anote os KPIs para a contabilidade.',
            ]],
            ['text' => 'Tratar inadimplência', 'sub' => [
                'Liste inadimplentes.',
                'Para casos persistentes, bloqueie em «Empresas» conforme política.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> assinaturas/planos de <em>Empresas</em> e gateway de pagamento.',
            '<strong>Aqui:</strong> você consolida a receita SaaS e a inadimplência.',
            '<strong>Para onde vai:</strong> inadimplente → bloqueio em <em>Empresas</em>; margem → cruzar com <em>Custos</em>; retenção → <em>Health Score</em>.',
        ],
        ['Diferente de «Posição financeira» (fluxo operacional interno da software house).']
    ),

    'adm_comercial' => $adm_h(
        'Painel comercial',
        'Métricas de aquisição: novos clientes, churn, conversão trial→pago e receita nova. Serve ao time comercial e marketing.',
        [
            '<strong>Funil trial</strong> e conversão.',
            '<strong>Churn</strong> por cancelamento/status.',
            '<strong>Receita de novos logos</strong>.',
        ],
        [
            ['text' => 'Preparar a reunião comercial semanal', 'sub' => [
                'Compare novos clientes vs cancelados no período.',
                'Liste os trials próximos do fim (candidatos a conversão).',
                'Veja a receita de novos logos e a taxa de conversão.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> status_saas e cadastros de <em>Empresas</em>.',
            '<strong>Aqui:</strong> você mede aquisição, conversão e churn.',
            '<strong>Para onde vai:</strong> trial acabando → contato de vendas; sinais de upsell → <em>IA comercial</em>/<em>Licenciamento</em>.',
        ],
        ['Churn deriva de status_saas — mantenha o cadastro das empresas atualizado.']
    ),

    'adm_custos' => $adm_h(
        'Custos da plataforma',
        'Consolidado de custos de infra e provedores (IA, VPS, SSL, SMTP, Maps) versus receita. Serve para margem bruta do SaaS.',
        [
            '<strong>Custos por categoria</strong> de provedor.',
            '<strong>Comparativo com receita</strong> da plataforma.',
        ],
        [
            ['text' => 'Controlar a margem', 'sub' => [
                'Revise os custos por categoria (IA costuma ser o mais variável).',
                'Compare o total de custos com a receita da plataforma.',
                'Se a margem cair, ajuste limites de IA em «Licenciamento» ou revise planos.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> consumo de provedores (IA em <em>Estatísticas IA/Copiloto ops</em>) + receita de <em>Financeiro SaaS</em>.',
            '<strong>Aqui:</strong> você calcula a margem bruta.',
            '<strong>Para onde vai:</strong> IA cara → limitar em <em>Licenciamento</em>; decisão de preço → <em>Planos</em>.',
        ],
        ['Custos de IA também aparecem no Copiloto ops e em Configurações CC.']
    ),

    'adm_posicao_financeira' => $adm_h(
        'Posição financeira',
        'Fluxo financeiro interno da operação Frotec (contas, movimentos, saldo). Serve à gestão financeira da própria software house.',
        [
            '<strong>Saldo e movimentações</strong> consolidados.',
            '<strong>Contas a pagar/receber</strong> da plataforma.',
            '<strong>Relatórios e filtros</strong> por período.',
        ],
        [
            ['text' => 'Fazer a conciliação', 'sub' => [
                'Selecione o período.',
                'Confira as entradas de assinaturas contra o extrato do gateway.',
                'Registre ajustes manuais quando necessário.',
                'Feche o saldo do período.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> movimentos internos da software house + receita de assinaturas.',
            '<strong>Aqui:</strong> você concilia o caixa interno da Frotec.',
            '<strong>Para onde vai:</strong> visão executiva de receita → <em>Financeiro SaaS</em>; custos → <em>Custos</em>.',
        ],
        ['Não confundir com o financeiro das transportadoras clientes (isso é no sistema de frotas).']
    ),

    'adm_performance' => $adm_h(
        'Performance',
        'Métricas de desempenho da aplicação: tempo de resposta, endpoints lentos, cache. Serve para otimização contínua.',
        [
            '<strong>Latência média</strong> por rota/API.',
            '<strong>Top endpoints lentos</strong>.',
            '<strong>Hit rate de cache</strong> quando aplicável.',
        ],
        [
            ['text' => 'Otimizar um gargalo', 'sub' => [
                'Identifique a API/rota com maior latência no topo da lista.',
                'Cruze com «SQL ao vivo» e «Banco de dados» para achar a query pesada.',
                'Corrija índice ou código e reavalie após o deploy.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> medições de tempo por endpoint/cache.',
            '<strong>Aqui:</strong> você acha o que está lento.',
            '<strong>Para onde vai:</strong> causa no banco → <em>SQL ao vivo/Banco</em>; correção → <em>Deploy</em>; regressão pós-deploy → <em>Timeline</em>.',
        ],
        ['Picos após deploy podem indicar migration/índice pendente.']
    ),

    'adm_banco' => $adm_h(
        'Banco de dados',
        'Central DBA: tamanho de tabelas, conexões, índices e sugestões. Serve para manutenção MySQL e prevenção de lentidão.',
        [
            '<strong>Overview</strong> do schema e tamanhos.',
            '<strong>Conexões ativas</strong> e status.',
            '<strong>Sugestões de índice</strong> automáticas.',
        ],
        [
            ['text' => 'Fazer manutenção preventiva', 'sub' => [
                'Revise as tabelas maiores (gps, logs costumam liderar).',
                'Veja as sugestões de índice automáticas.',
                'Faça backup em «Backup».',
                'Aplique os índices/ALTER em janela de baixo uso (veja o Heatmap).',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> INFORMATION_SCHEMA e status do MySQL.',
            '<strong>Aqui:</strong> você decide índices e limpeza.',
            '<strong>Para onde vai:</strong> antes de alterar → <em>Backup</em>; efeito na velocidade → <em>Performance/SQL ao vivo</em>.',
        ],
        ['Sempre faça backup antes de ALTER TABLE em produção.']
    ),

    'adm_sql_monitor' => $adm_h(
        'SQL ao vivo',
        'Stream de queries em execução e histórico de queries lentas. Serve para debug em tempo real de gargalo de banco.',
        [
            '<strong>Monitor ao vivo</strong> (~15s refresh).',
            '<strong>Queries lentas</strong> recentes.',
        ],
        [
            ['text' => 'Caçar a query pesada', 'sub' => [
                'Abra o monitor e reproduza a lentidão no sistema do gestor.',
                'Observe qual SQL aparece no topo/na lista de lentas.',
                'Leve a query para «Banco de dados» e crie o índice, ou ajuste o PHP que a gera.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> processlist e slow queries do MySQL.',
            '<strong>Aqui:</strong> você vê a query culpada em tempo real.',
            '<strong>Para onde vai:</strong> índice → <em>Banco</em>; correção de código → repositório → <em>Deploy</em>.',
        ],
        ['Use com cuidado em produção — não exponha a admins de cliente.']
    ),

    'adm_apis' => $adm_h(
        'APIs externas',
        'Saúde das integrações: OpenAI, SMTP, Maps, WhatsApp, SEFAZ proxies. Serve para saber se a falha é interna ou de terceiro.',
        [
            '<strong>Status por integração</strong> com último teste.',
            '<strong>Latência e erro</strong> recente.',
            '<strong>Refresh</strong> ~60s.',
        ],
        [
            ['text' => 'Diagnosticar uma integração', 'sub' => [
                'Abra a tela e veja o status de cada provedor.',
                'Copiloto/IA falhando → cheque OpenAI/provedor aqui.',
                'E-mail não sai → cheque SMTP aqui.',
                'Se o terceiro está fora, o problema não é seu — comunique e aguarde.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> testes de conexão às chaves de «Configurações CC».',
            '<strong>Aqui:</strong> você isola se a falha é interna ou do terceiro.',
            '<strong>Para onde vai:</strong> chave errada → <em>Configurações CC</em>; terceiro fora → <em>Comunicação</em> (avisar clientes).',
        ],
        ['Chaves configuradas em Configurações CC — nunca exiba segredo na tela.']
    ),

    'adm_logs' => $adm_h(
        'Central de logs',
        'Agregador de logs da aplicação (PHP, Apache, jobs). Serve para buscar mensagens de erro e rastrear comportamento.',
        [
            '<strong>Stream/tail</strong> de arquivos de log.',
            '<strong>Filtro por nível</strong> ou texto.',
        ],
        [
            ['text' => 'Investigar um erro', 'sub' => [
                'Filtre pelo horário do incidente (use a Timeline para saber a hora).',
                'Busque por texto (mensagem, empresa, endpoint).',
                'Copie o trecho relevante para o ticket/repositório.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> arquivos em logs/ (PHP, Apache, jobs).',
            '<strong>Aqui:</strong> você lê a mensagem crua.',
            '<strong>Para onde vai:</strong> erro recorrente → <em>Central de erros</em>; job falho → <em>Jobs</em>; correção → <em>Deploy</em>.',
        ],
        ['Logs em logs/ não são versionados — configure rotação no servidor.']
    ),

    'adm_alertas' => $adm_h(
        'Alertas',
        'Fila de alertas operacionais da plataforma (limite, erro, segurança). Serve para não perder notificações críticas.',
        [
            '<strong>Lista priorizada</strong> de alertas abertos.',
            '<strong>Reconhecer/dismiss</strong> conforme implementado.',
        ],
        [
            ['text' => 'Rotina NOC de alertas', 'sub' => [
                'Revise os alertas abertos junto com «Saúde (NOC)».',
                'Trate os vermelhos (críticos) antes dos amarelos.',
                'Reconheça/resolva cada um e registre a ação.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> thresholds de limite/erro/segurança da plataforma.',
            '<strong>Aqui:</strong> você prioriza o que tratar.',
            '<strong>Para onde vai:</strong> segurança → <em>Segurança NOC</em>; erro → <em>Erros</em>; limite → <em>Licenciamento</em>.',
        ],
        ['Configure thresholds em Configurações CC quando disponível.']
    ),

    'adm_erros' => $adm_h(
        'Central de erros',
        'Erros PHP/JS/API capturados com stack e frequência. Serve para triagem de bugs em produção.',
        [
            '<strong>Erros recentes</strong> agrupados.',
            '<strong>Detalhe</strong> de mensagem e origem.',
        ],
        [
            ['text' => 'Corrigir um bug de produção', 'sub' => [
                'Ordene os erros por frequência (ataque o que mais afeta clientes).',
                'Abra o detalhe e veja o arquivo/linha de origem.',
                'Corrija no repositório e teste.',
                'Publique via «Deploy» e confirme que o erro parou aqui.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> handler de erros PHP/JS/API.',
            '<strong>Aqui:</strong> você prioriza e localiza o bug.',
            '<strong>Para onde vai:</strong> detalhe cru → <em>Logs</em>; correção → repositório → <em>Deploy</em>; sequência → <em>Timeline</em>.',
        ],
        ['O usuário final vê mensagem genérica — o detalhe só aparece aqui para o admin.']
    ),

    'adm_benchmark' => $adm_h(
        'Benchmark',
        'Comparativo de métricas entre clientes (anonimizado/agregado). Serve para identificar outliers e melhores práticas.',
        [
            '<strong>Ranking entre empresas</strong> por uso/KPI.',
            '<strong>Comparativo visual</strong>.',
        ],
        [
            ['text' => 'Analisar a base', 'sub' => [
                'Veja quem mais usa cada recurso (IA, rotas, veículos).',
                'Identifique outliers (muito acima/abaixo da média).',
                'Use os insights (sem dados identificáveis) em material comercial.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> métricas agregadas de uso das empresas.',
            '<strong>Aqui:</strong> você compara clientes e acha padrões.',
            '<strong>Para onde vai:</strong> outlier de uso → <em>Licenciamento</em>; insight de venda → <em>Comercial</em>.',
        ],
        ['Respeite LGPD — não exporte dados identificáveis sem base legal.']
    ),

    'adm_ia_admin' => $adm_h(
        'IA administrativa',
        'Ferramentas de IA para operação interna: resumos, análise de código, estatísticas. Serve à equipe Frotec, não ao gestor de frota.',
        [
            '<strong>Painéis de uso de IA</strong> na plataforma.',
            '<strong>Análises assistidas</strong> para suporte L2.',
        ],
        [
            ['text' => 'Usar uma ferramenta de IA interna', 'sub' => [
                'Selecione a ferramenta desejada na página.',
                'Informe o contexto pedido (ex.: empresa ID).',
                'Revise a saída manualmente antes de agir — IA pode errar.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> provedor de IA configurado em «Configurações CC».',
            '<strong>Aqui:</strong> você usa IA para tarefas internas.',
            '<strong>Para onde vai:</strong> consumo conta em <em>Custos</em> e <em>Estatísticas IA</em>.',
        ],
        ['Consumo conta contra a cota global — monitore em Custos.']
    ),

    'adm_ia_dev' => $adm_h(
        'IA desenvolvedor',
        'Auditoria estática e sugestões de melhoria no código via IA. Serve para revisão técnica rápida antes de release.',
        [
            '<strong>Análise de repositório</strong> (escopo configurado).',
            '<strong>Sugestões</strong> de segurança e qualidade.',
        ],
        [
            ['text' => 'Rodar uma análise de código', 'sub' => [
                'Abra a tela e aguarde a API de auditoria (ia_code_audit) carregar.',
                'Leia as sugestões de segurança/qualidade.',
                'Aplique manualmente as que fizerem sentido — nunca patch automático sem revisão.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> código do repositório + provedor de IA.',
            '<strong>Aqui:</strong> você recebe sugestões técnicas pré-release.',
            '<strong>Para onde vai:</strong> correção aprovada → repositório → <em>Deploy</em>.',
        ],
        ['Nunca aplique patch automático sem revisão humana.']
    ),

    'adm_ia_estatisticas' => $adm_h(
        'Estatísticas IA',
        'Volume de chamadas IA, tokens, intents e empresas que mais usam. Serve para capacity planning e precificação.',
        [
            '<strong>Gráficos de uso</strong> por período.',
            '<strong>Breakdown por empresa/intent</strong> quando disponível.',
        ],
        [
            ['text' => 'Acompanhar o consumo de IA', 'sub' => [
                'Compare o mês atual com o anterior (volume e tokens).',
                'Veja quais empresas e intents concentram o uso.',
                'Se o custo subir demais, ajuste limites em «Licenciamento».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> logs de chamadas de IA (gestor + Copiloto).',
            '<strong>Aqui:</strong> você mede volume e distribuição.',
            '<strong>Para onde vai:</strong> custo → <em>Custos</em>; limite → <em>Licenciamento</em>; qualidade → <em>Copiloto ops</em>.',
        ],
        ['O Copiloto ops tem a fila de perguntas não entendidas — complementar a análise aqui.']
    ),

    'adm_auditoria' => $adm_h(
        'Auditoria',
        'Trilha de ações sensíveis: logins admin, impersonação, alterações críticas. Serve para compliance e investigação.',
        [
            '<strong>Registros imutáveis</strong> de ações admin.',
            '<strong>Filtro</strong> por usuário, data e tipo.',
        ],
        [
            ['text' => 'Investigar um acesso suspeito', 'sub' => [
                'Filtre por e-mail do admin, empresa ou tipo de ação.',
                'Localize a ação sensível (impersonação, alteração crítica).',
                'Correlacione com «Log de acessos» (IP/horário) e «Segurança NOC».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> eventos registrados por Empresas (impersonação), login admin e mudanças críticas.',
            '<strong>Aqui:</strong> você reconstrói quem fez o quê.',
            '<strong>Para onde vai:</strong> IP suspeito → <em>Log de acessos/Segurança NOC</em>.',
        ],
        ['Retenção conforme política — arquive logs antigos periodicamente.']
    ),

    'adm_ia_comercial' => $adm_h(
        'IA comercial',
        'Insights de IA para vendas: leads, upsell, texto sugerido. Serve ao time comercial com dados agregados da base.',
        [
            '<strong>Sugestões automáticas</strong> de abordagem.',
            '<strong>Sinais de upsell</strong> (limite estourando, alto uso).',
        ],
        [
            ['text' => 'Prospectar com apoio da IA', 'sub' => [
                'Revise as empresas sinalizadas (upsell/risco).',
                'Leia os talking points sugeridos.',
                'Valide os dados antes de contatar — IA é apoio, não fonte única.',
                'Exporte para o CRM/registro comercial.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> sinais de <em>Licenciamento</em> (limite) e <em>Health Score</em>.',
            '<strong>Aqui:</strong> a IA sugere quem abordar e como.',
            '<strong>Para onde vai:</strong> ação → time comercial → <em>Comercial</em> (acompanhar conversão).',
        ],
        ['Valide os dados antes do contato — IA é apoio, não fonte única.']
    ),

    'adm_analytics' => $adm_h(
        'Analytics',
        'Análises avançadas de uso da plataforma: retenção, feature adoption, funis. Serve a produto e growth.',
        [
            '<strong>Gráficos de adoção</strong> de funcionalidades.',
            '<strong>Segmentação</strong> por plano ou porte.',
        ],
        [
            ['text' => 'Tomar uma decisão de produto', 'sub' => [
                'Veja a adoção de cada funcionalidade e a retenção.',
                'Identifique features pouco usadas (candidatas a melhorar ou cortar).',
                'Segmente por plano/porte para entender quem usa o quê.',
                'Priorize o roadmap com base nesses dados.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> eventos de uso agregados da base.',
            '<strong>Aqui:</strong> você mede adoção e retenção.',
            '<strong>Para onde vai:</strong> decisão → <em>Roadmap</em>; oportunidade comercial → <em>Comercial</em>.',
        ],
        ['Cruze com «Roadmap» para fechar o ciclo de produto.']
    ),

    'adm_deploy' => $adm_h(
        'Central de Deploy',
        'Histórico de releases, versão atual, commits Git e rollback. Serve para rastrear o que entrou em produção e quando. Não é um botão de "publicar" — é o painel que registra e acompanha cada versão.',
        [
            '<strong>Versão deployada</strong>, hash Git e data.',
            '<strong>Histórico de deploys</strong> com autor e arquivos alterados.',
            '<strong>Rollback</strong> (registra a solicitação; executa só se auto-rollback estiver ligado).',
        ],
        [
            ['text' => 'Publicar / registrar uma versão', 'sub' => [
                'Faça o commit no Git (e o git pull/checkout no servidor, se produção).',
                'Abra esta tela: o commit atual é registrado automaticamente como um deploy.',
                'Confira versão, hash e arquivos alterados na lista.',
                'Rode «Saúde (NOC)» logo depois para validar o pós-deploy.',
            ]],
            ['text' => 'Fazer rollback', 'sub' => [
                'É preciso ter ao menos 2 deploys registrados.',
                'Clique em «Registrar rollback» — ele registra e mostra o comando: git checkout <hash-anterior>.',
                'Execute o comando no servidor (ou ligue auto-rollback: SF_ADM_AUTO_ROLLBACK=1 / deploy_auto_rollback=1 em Configurações CC).',
                'Comunique clientes se houver impacto.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> lê o commit atual do Git (.git/HEAD) a cada abertura.',
            '<strong>Aqui:</strong> cada commit vira uma linha de deploy; você acompanha e pode acionar rollback.',
            '<strong>Para onde vai:</strong> pós-deploy → <em>Saúde/Performance</em>; problema → rollback aqui ou <em>Timeline</em> para achar a causa.',
        ],
        ['Sempre faça backup antes de deploy em produção.', 'O rollback só executa sozinho se o auto-rollback estiver habilitado.']
    ),

    'adm_jobs' => $adm_h(
        'Jobs / Cron',
        'Scripts agendados (GPS, relatórios, embeddings, e-mail). Serve para garantir que as automações rodem e para debugar falhas.',
        [
            '<strong>Lista de crons</strong> com última execução.',
            '<strong>Execução manual</strong> quando disponível.',
            '<strong>Status OK/erro</strong> por job.',
        ],
        [
            ['text' => 'Quando um job falhou', 'sub' => [
                'Localize o job com status de erro na lista.',
                'Abra o detalhe e veja a última execução.',
                'Cheque «Central de logs» no horário para a mensagem exata.',
                'Corrija a causa e reexecute manualmente por aqui.',
            ]],
            ['text' => 'Garantir agendamento', 'sub' => [
                'Confira a última execução de cada cron crítico (GPS, relatórios).',
                'No Windows/XAMPP, os crons dependem do Agendador de Tarefas — valide que está ativo.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> scripts em cron/ e relatorios_automaticos/.',
            '<strong>Aqui:</strong> você monitora e reexecuta jobs.',
            '<strong>Para onde vai:</strong> erro → <em>Logs</em>; sequência → <em>Timeline</em>.',
        ],
        ['Cron no Windows/XAMPP exige Agendador de Tarefas — ver documentação do projeto.']
    ),

    'adm_storage' => $adm_h(
        'Storage',
        'Uso de disco por empresa: uploads, fotos, documentos, OS. Serve para cobrar storage extra e limpar lixo.',
        [
            '<strong>Consumo MB/GB</strong> por cliente.',
            '<strong>Breakdown</strong> por tipo de arquivo.',
        ],
        [
            ['text' => 'Cliente estourou a cota de storage', 'sub' => [
                'Localize a empresa e veja o consumo total.',
                'Abra o breakdown e identifique as pastas grandes em uploads/{empresa_id}.',
                'Peça limpeza ao cliente ou venda um pacote extra.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> varredura das pastas uploads/ por empresa.',
            '<strong>Aqui:</strong> você vê quem consome disco.',
            '<strong>Para onde vai:</strong> cobrança de extra → <em>Licenciamento/Marketplace</em>.',
        ],
        ['Classificação por extensão/caminho — valores estimados em alguns ambientes.']
    ),

    'adm_dependencias' => $adm_h(
        'Dependências',
        'Inventário PHP, Composer, extensões e versões. Serve para auditoria de segurança e compatibilidade antes de upgrade.',
        [
            '<strong>Versão PHP</strong> e extensões carregadas.',
            '<strong>Pacotes Composer</strong> e desatualizados.',
        ],
        [
            ['text' => 'Planejar um upgrade de stack', 'sub' => [
                'Confira a versão do PHP e as extensões carregadas.',
                'Veja os pacotes Composer desatualizados.',
                'Compare com os requisitos do composer.json.',
                'Teste em staging antes de aplicar em produção.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> ambiente PHP e composer.lock do servidor.',
            '<strong>Aqui:</strong> você audita versões e vulnerabilidades.',
            '<strong>Para onde vai:</strong> upgrade → testar → <em>Deploy</em>; rodar composer install após pull.',
        ],
        ['Rode composer install após o pull — a pasta vendor/ não é versionada.']
    ),

    'adm_configuracoes_cc' => $adm_h(
        'Configurações CC',
        'Parâmetros globais da plataforma: SMTP, chaves IA, Google, login, backup, integrações. Serve como um ".env visual" para o admin Frotec.',
        [
            '<strong>Hub de configuração</strong> centralizado.',
            '<strong>Testes de conexão</strong> SMTP/API.',
            '<strong>Segredos mascarados</strong> (********).',
        ],
        [
            ['text' => 'Configurar o e-mail (SMTP)', 'sub' => [
                'Preencha host, porta, usuário e senha SMTP.',
                'Clique em enviar teste e confirme o recebimento.',
                'Salve — passa a ser usado em alertas e na Comunicação.',
            ]],
            ['text' => 'Configurar chaves de IA', 'sub' => [
                'Prefira definir via variáveis de ambiente (SF_*) no servidor.',
                'Use os campos aqui como fallback (ex.: XAMPP local).',
                'Valide o status depois em «APIs externas».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> ponto central de configuração (env primeiro, banco como fallback).',
            '<strong>Aqui:</strong> você liga integrações e políticas globais.',
            '<strong>Para onde vai:</strong> status das chaves → <em>APIs externas</em>; módulos IA → <em>Marketplace</em>; e-mails → <em>Comunicação</em>.',
        ],
        ['Prefira SF_* no ambiente — o banco é apenas fallback local.', 'Segredos são mascarados e nunca devolvidos pela rede.']
    ),

    'adm_comunicacao' => $adm_h(
        'Comunicação',
        'Envio de avisos/e-mails em massa para clientes da plataforma. Serve para manutenção programada, novidades e incidentes.',
        [
            '<strong>Templates</strong> de mensagem.',
            '<strong>Disparo</strong> por segmento ou todos.',
            '<strong>Histórico</strong> de envios.',
        ],
        [
            ['text' => 'Avisar sobre uma manutenção', 'sub' => [
                'Confirme que o SMTP está OK em «Configurações CC».',
                'Escolha um template ou escreva a mensagem.',
                'Selecione o público (segmento ou todos).',
                'Envie um teste para um e-mail interno antes do broadcast.',
                'Agende ou dispare agora.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> usa o SMTP de «Configurações CC» e a base de «Empresas».',
            '<strong>Aqui:</strong> você comunica os clientes em massa.',
            '<strong>Para onde vai:</strong> falha de envio → <em>APIs externas</em> (SMTP); registro → histórico.',
        ],
        ['Teste com um e-mail interno antes de qualquer broadcast.']
    ),

    'adm_backup' => $adm_h(
        'Backup',
        'Gestão de backups do banco e arquivos críticos. Serve para disaster recovery e restore pontual.',
        [
            '<strong>Backups disponíveis</strong> com data e tamanho.',
            '<strong>Gerar backup manual</strong>.',
            '<strong>Download/restauração</strong> conforme a UI.',
        ],
        [
            ['text' => 'Gerar um backup antes de uma mudança', 'sub' => [
                'Antes de ALTER TABLE, deploy grande ou upgrade, gere um backup manual.',
                'Baixe uma cópia off-site.',
                'Só então prossiga com a mudança.',
            ]],
            ['text' => 'Restaurar (disaster recovery)', 'sub' => [
                'Faça o restore só em janela combinada.',
                'Se necessário, pare Apache/MySQL antes.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> dump do banco + arquivos críticos.',
            '<strong>Aqui:</strong> você cria/guarda pontos de restauração.',
            '<strong>Para onde vai:</strong> passo obrigatório antes de <em>Banco</em> (ALTER) e <em>Deploy</em> em produção.',
        ],
        ['Restore em produção só em janela combinada — pare Apache/MySQL antes se necessário.']
    ),

    'adm_sistema' => $adm_h(
        'Sistema',
        'Informações do servidor: SO, PHP, Apache, espaço em disco, uptime. Serve para um snapshot rápido do ambiente.',
        [
            '<strong>system_info</strong> agregado.',
            '<strong>Versões</strong> de componentes.',
        ],
        [
            ['text' => 'Abrir um ticket com a hospedagem', 'sub' => [
                'Abra a tela e copie o snapshot (SO, PHP, Apache, disco, uptime).',
                'Cole no ticket para agilizar o suporte da infraestrutura.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> metadados do servidor/PHP.',
            '<strong>Aqui:</strong> você tem o retrato do ambiente.',
            '<strong>Para onde vai:</strong> detalhe de pacotes → <em>Dependências</em>; disco cheio → <em>Storage/Banco</em>.',
        ],
        ['Não expõe senhas — apenas metadados.']
    ),

    'adm_roadmap' => $adm_h(
        'Roadmap',
        'Visão de evolução do produto Frotec: fases, features planejadas e status. Serve ao alinhamento interno produto/dev/comercial.',
        [
            '<strong>Marcos e fases</strong> documentados.',
            '<strong>Status</strong> concluído/em andamento/planejado.',
        ],
        [
            ['text' => 'Planejar a próxima sprint', 'sub' => [
                'Revise os itens em andamento e os planejados.',
                'Use dados de «Analytics» para priorizar o que dá mais valor.',
                'Atualize o status dos itens após cada release.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> insumos de <em>Analytics</em> e feedback de clientes.',
            '<strong>Aqui:</strong> você organiza a evolução do produto.',
            '<strong>Para onde vai:</strong> item concluído → versão em <em>Deploy</em>; manter sincronizado com docs/ e commits.',
        ],
        ['Manter sincronizado com docs/ e os commits reais.']
    ),

    'adm_seguranca_premium' => $adm_h(
        'Segurança NOC',
        'Painel de segurança: ataques, IPs bloqueados, 2FA, firewall. Serve para resposta a incidentes de segurança.',
        [
            '<strong>Overview de ameaças</strong> recentes.',
            '<strong>2FA e login</strong> agregados.',
            '<strong>Refresh</strong> ~60s.',
        ],
        [
            ['text' => 'Responder a um incidente de segurança', 'sub' => [
                'Abra a tela e veja as ameaças recentes e IPs ofensores.',
                'Bloqueie o IP se o ataque for recorrente.',
                'Force reset de senha em contas comprometidas.',
                'Registre tudo e cheque «Auditoria»/«Log de acessos».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> tentativas de login, geo-bloqueio e eventos de segurança.',
            '<strong>Aqui:</strong> você responde à ameaça.',
            '<strong>Para onde vai:</strong> detalhe de acessos → <em>Log de acessos</em>; política de login → <em>Login/reCAPTCHA</em>; trilha → <em>Auditoria</em>.',
        ],
        ['Complementa «Login/reCAPTCHA» e «Log de acessos».']
    ),

    'adm_conta_adm' => $adm_h(
        'Minha conta / 2FA',
        'Configurações da conta do administrador logado: senha, TOTP 2FA, sessões. Serve para proteger o acesso ao Control Center.',
        [
            '<strong>Alterar senha</strong> forte.',
            '<strong>Ativar 2FA TOTP</strong> (Google Authenticator etc.).',
            '<strong>Recovery codes</strong> quando disponível.',
        ],
        [
            ['text' => 'Ativar o 2FA da sua conta', 'sub' => [
                'Abra a tela e inicie a configuração de 2FA.',
                'Escaneie o QR code no app autenticador (Google Authenticator, Authy).',
                'Digite o código de 6 dígitos para confirmar.',
                'Guarde os backup codes offline.',
            ]],
            ['text' => 'Trocar a senha', 'sub' => [
                'Informe a senha atual e a nova (forte).',
                'Salve — as demais sessões podem ser encerradas.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> sua própria conta admin.',
            '<strong>Aqui:</strong> você endurece o seu acesso.',
            '<strong>Para onde vai:</strong> políticas globais de login ficam em <em>Login/reCAPTCHA</em>; acessos em <em>Log de acessos</em>.',
        ],
        ['Obrigatório para admins em produção.']
    ),

    'adm_log_acessos' => $adm_h(
        'Log de acessos',
        'Histórico de logins e tentativas (sucesso/falha) na plataforma. Serve para detectar brute force e acessos suspeitos.',
        [
            '<strong>KPIs:</strong> total, sucesso, falha, hoje.',
            '<strong>Tabela paginada</strong> com IP, user-agent, empresa.',
            '<strong>Filtros</strong> por data e empresa.',
        ],
        [
            ['text' => 'Auditar tentativas suspeitas', 'sub' => [
                'Filtre por falhas repetidas do mesmo IP (sinal de brute force).',
                'Cheque o user-agent e a empresa alvo.',
                'Correlacione com «Segurança NOC» e bloqueie o IP se preciso.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> registros de login (gestor + admin).',
            '<strong>Aqui:</strong> você detecta padrões de ataque.',
            '<strong>Para onde vai:</strong> ataque → <em>Segurança NOC</em> (bloquear IP); endurecer → <em>Login/reCAPTCHA</em>.',
        ],
        ['Retenção limitada — exporte se precisar de arquivo de longo prazo.']
    ),

    'adm_seguranca_login' => $adm_h(
        'Login / reCAPTCHA',
        'Configuração de políticas de login da plataforma: reCAPTCHA, tentativas, mensagens. Serve para endurecer a autenticação pública.',
        [
            '<strong>reCAPTCHA site/secret</strong> (env preferencial).',
            '<strong>Limites de tentativa</strong> e lockout.',
        ],
        [
            ['text' => 'Endurecer o login', 'sub' => [
                'Ative o reCAPTCHA (v2/v3) informando as chaves.',
                'Defina o limite de tentativas e o lockout.',
                'Teste o login do gestor e do admin.',
                'Monitore o efeito em «Log de acessos».',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> chaves reCAPTCHA (SF_RECAPTCHA_* no servidor).',
            '<strong>Aqui:</strong> você define a política de autenticação.',
            '<strong>Para onde vai:</strong> efeito → <em>Log de acessos</em>; incidentes → <em>Segurança NOC</em>.',
        ],
        ['Segredos via SF_RECAPTCHA_* no servidor — o banco é fallback.']
    ),

    'adm_deploy_producao' => $adm_h(
        'Deploy Produção',
        'Central de Deploy Inteligente: conecta ao servidor via SSH, compara homologação × produção (arquivos e banco), gera backup, executa migrations validadas, sincroniza alterações, roda testes e permite rollback. Objetivo: publicar sem acessar o servidor manualmente.',
        [
            '<strong>Configuração</strong> de múltiplos servidores (prod, homolog, staging) com credenciais criptografadas.',
            '<strong>Status</strong> remoto: CPU, RAM, disco, versões PHP/MySQL/Git.',
            '<strong>Comparação</strong> de arquivos e schema MySQL com relatório.',
            '<strong>Migrations</strong> SQL geradas e validadas (sem DROP destrutivo).',
            '<strong>Backup</strong> automático antes do deploy + histórico.',
            '<strong>Git</strong> pull, push, tag e release.',
            '<strong>Pipeline</strong> em 16 etapas com barra de progresso.',
            '<strong>2FA obrigatório</strong> para ambiente produção.',
            '<strong>Rollback</strong> de banco e Git quando há backup.',
        ],
        [
            ['text' => 'Configurar servidor', 'sub' => [
                'Preencha host, SSH, caminhos e credenciais DB.',
                'Use 127.0.0.1 + mesmo caminho para simular produção local no XAMPP.',
                'Salve — senhas ficam criptografadas (SF_DEPLOY_ENC_KEY).',
            ]],
            ['text' => 'Publicar', 'sub' => [
                'Compare arquivos e banco.',
                'Revise SQL na prévia e marque aprovação.',
                'Confirme + código 2FA (produção).',
                'Clique Publicar Produção e acompanhe as etapas.',
            ]],
        ],
        [
            'Requer cliente OpenSSH no servidor do Control Center.',
            'Defina SF_DEPLOY_ENC_KEY no ambiente para criptografia forte.',
            'Composer e reinício de serviços dependem do SO remoto (Linux recomendado).',
        ]
    ),

    'adm_ia_copilot_ops' => $adm_h(
        'Copiloto — operações IA',
        'Operação L2 do Centro de Inteligência: saúde global, custos, perguntas não entendidas, benchmark e traces. Serve para evoluir o Copiloto (assistente IA) dos clientes.',
        [
            '<strong>Saúde global</strong> e custos USD estimados.',
            '<strong>Fila de perguntas pendentes</strong> → mapear para uma intenção.',
            '<strong>Playground de benchmark</strong> do classificador.',
            '<strong>Traces modo dev</strong> (suporte L2).',
        ],
        [
            ['text' => 'Ensinar o Copiloto (fechar o loop de aprendizado)', 'sub' => [
                'Abra a seção «Perguntas pendentes» — são frases que o Copiloto não entendeu.',
                'Para cada uma, digite a intenção correta (ex.: faturamento, manutencao) no campo.',
                'Clique em «Mapear» — cria um padrão em ia_intencoes_custom.',
                'A partir daí, perguntas parecidas passam a ser entendidas sem código.',
                'Use «Ignorar» para descartar ruído.',
            ]],
            ['text' => 'Testar o classificador (benchmark)', 'sub' => [
                'Informe uma empresa com chave IA e os modelos a comparar.',
                'Rode a suite e compare acertos entre modelos baratos vs precisos.',
            ]],
        ],
        [
            '<strong>De onde vem:</strong> perguntas reais do assistente IA dos gestores (assistente_ia.php) que caíram na fila de não entendidas.',
            '<strong>Aqui:</strong> você mapeia a pergunta → intenção e valida o classificador.',
            '<strong>Para onde vai:</strong> intenção salva volta a alimentar o Copiloto; custo → <em>Custos</em>; volume → <em>Estatísticas IA</em>.',
        ],
        ['Acesso só admin Frotec — não confundir com o assistente_ia.php do gestor.']
    ),
];
