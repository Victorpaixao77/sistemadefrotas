<?php
/**
 * 📋 ARQUIVO DE CONFIGURAÇÃO DE EXEMPLO
 * 🚀 SISTEMA FISCAL - SISTEMA DE FROTAS
 * 
 * Este arquivo contém as configurações necessárias para
 * o funcionamento do sistema fiscal.
 * 
 * ⚠️  IMPORTANTE: 
 *     1. Copie este arquivo para config.php
 *     2. Ajuste as configurações conforme sua empresa
 *     3. NUNCA commite o arquivo config.php no git
 * 
 * 📅 Data: Agosto 2025
 * 🔧 Versão: 2.0.0
 */

// =====================================================
// 🔐 CONFIGURAÇÕES DE SEGURANÇA
// =====================================================

// Algoritmo de criptografia (AES-256, AES-128, 3DES)
define('FISCAL_CRYPTO_ALGORITHM', 'AES-256');

// Tempo de expiração da sessão em segundos (1 hora = 3600)
define('FISCAL_SESSION_TIMEOUT', 3600);

// Máximo de tentativas de login antes do bloqueio
define('FISCAL_MAX_LOGIN_ATTEMPTS', 5);

// Tempo de bloqueio em segundos (15 minutos = 900)
define('FISCAL_BLOCK_TIME', 900);

// =====================================================
// 🏢 CONFIGURAÇÕES DA EMPRESA
// =====================================================

// ID da empresa no sistema
define('FISCAL_EMPRESA_ID', 1);

// CNPJ da empresa (formato: XX.XXX.XXX/XXXX-XX)
define('FISCAL_CNPJ_EMPRESA', '12.345.678/0001-90');

// Razão Social da empresa
define('FISCAL_RAZAO_SOCIAL', 'EMPRESA EXEMPLO LTDA');

// Inscrição Estadual
define('FISCAL_INSCRICAO_ESTADUAL', '123456789');

// Código do município (IBGE)
define('FISCAL_CODIGO_MUNICIPIO', '3550308');

// CEP da empresa
define('FISCAL_CEP_EMPRESA', '01234-567');

// Endereço completo
define('FISCAL_ENDERECO_EMPRESA', 'Rua Exemplo, 123 - Centro');

// =====================================================
// 🔑 CONFIGURAÇÕES DE CERTIFICADO DIGITAL
// =====================================================

// Caminho para o arquivo do certificado (.pfx ou .p12)
define('FISCAL_CERTIFICADO_PATH', '/caminho/para/certificado.pfx');

// Senha do certificado (será criptografada)
define('FISCAL_CERTIFICADO_SENHA', 'senha_do_certificado');

// Tipo do certificado (A1 ou A3)
define('FISCAL_CERTIFICADO_TIPO', 'A1');

// Data de vencimento do certificado (YYYY-MM-DD)
define('FISCAL_CERTIFICADO_VENCIMENTO', '2026-12-31');

// =====================================================
// 🌐 CONFIGURAÇÕES SEFAZ
// =====================================================

// Ambiente SEFAZ (1 = Produção, 2 = Homologação)
define('FISCAL_SEFAZ_AMBIENTE', 2);

// URLs dos web services SEFAZ
define('FISCAL_SEFAZ_URL_NFE', 'https://nfe-homologacao.sefaz.sp.gov.br/ws/nfeautorizacao4.asmx');
define('FISCAL_SEFAZ_URL_CTE', 'https://cte-homologacao.sefaz.sp.gov.br/ws/cteautorizacao4.asmx');
define('FISCAL_SEFAZ_URL_MDFE', 'https://mdfe-homologacao.sefaz.sp.gov.br/ws/mdfeautorizacao4.asmx');

// Timeout para requisições SEFAZ (em segundos)
define('FISCAL_SEFAZ_TIMEOUT', 30);

// Número máximo de tentativas de envio
define('FISCAL_SEFAZ_MAX_TENTATIVAS', 3);

// =====================================================
// 📁 CONFIGURAÇÕES DE ARQUIVOS
// =====================================================

// Diretório base para uploads
define('FISCAL_UPLOAD_BASE', __DIR__ . '/../uploads/');

// Diretório para arquivos XML
define('FISCAL_UPLOAD_XML', FISCAL_UPLOAD_BASE . 'xml/');

// Diretório para arquivos PDF
define('FISCAL_UPLOAD_PDF', FISCAL_UPLOAD_BASE . 'pdf/');

// Diretório para arquivos CT-e
define('FISCAL_UPLOAD_CTE', FISCAL_UPLOAD_BASE . 'cte/');

// Diretório para arquivos MDF-e
define('FISCAL_UPLOAD_MDFE', FISCAL_UPLOAD_BASE . 'mdfe/');

// Tamanho máximo de upload (em bytes) - 10MB
define('FISCAL_MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// Tipos de arquivo permitidos
define('FISCAL_ALLOWED_FILE_TYPES', [
    'xml' => 'application/xml',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain'
]);

// =====================================================
// 📧 CONFIGURAÇÕES DE E-MAIL
// =====================================================

// Servidor SMTP
define('FISCAL_SMTP_HOST', 'smtp.gmail.com');

// Porta SMTP
define('FISCAL_SMTP_PORT', 587);

// Usuário SMTP
define('FISCAL_SMTP_USER', 'seu_email@gmail.com');

// Senha SMTP (será criptografada)
define('FISCAL_SMTP_PASS', 'sua_senha_app');

// Nome do remetente
define('FISCAL_SMTP_FROM_NAME', 'Sistema Fiscal - Empresa Exemplo');

// E-mail do remetente
define('FISCAL_SMTP_FROM_EMAIL', 'fiscal@empresaexemplo.com.br');

// =====================================================
// 🔄 CONFIGURAÇÕES DE SINCRONIZAÇÃO
// =====================================================

// Intervalo de sincronização automática (em segundos)
define('FISCAL_SYNC_INTERVAL', 300); // 5 minutos

// Sincronizar automaticamente ao criar documentos
define('FISCAL_SYNC_ON_CREATE', true);

// Sincronizar automaticamente ao alterar documentos
define('FISCAL_SYNC_ON_UPDATE', true);

// Sincronizar automaticamente ao cancelar documentos
define('FISCAL_SYNC_ON_CANCEL', true);

// =====================================================
// 📊 CONFIGURAÇÕES DE RELATÓRIOS
// =====================================================

// Formato padrão de data
define('FISCAL_DATE_FORMAT', 'd/m/Y');

// Formato padrão de data e hora
define('FISCAL_DATETIME_FORMAT', 'd/m/Y H:i:s');

// Formato padrão de moeda
define('FISCAL_CURRENCY_FORMAT', 'R$ #,##0.00');

// Fuso horário padrão
define('FISCAL_TIMEZONE', 'America/Sao_Paulo');

// =====================================================
// 🚨 CONFIGURAÇÕES DE ALERTAS
// =====================================================

// Alertar quando certificado vencer em X dias
define('FISCAL_ALERT_CERTIFICADO_DAYS', 30);

// Alertar quando MDF-e não for encerrado em X dias
define('FISCAL_ALERT_MDFE_DAYS', 7);

// Alertar quando houver erro SEFAZ
define('FISCAL_ALERT_SEFAZ_ERROR', true);

// Alertar quando documento estiver pendente por X dias
define('FISCAL_ALERT_DOCUMENTO_PENDENTE_DAYS', 3);

// =====================================================
// 🔧 CONFIGURAÇÕES DE DESENVOLVIMENTO
// =====================================================

// Modo debug (true = desenvolvimento, false = produção)
define('FISCAL_DEBUG_MODE', true);

// Log de todas as operações
define('FISCAL_LOG_OPERATIONS', true);

// Log de erros
define('FISCAL_LOG_ERRORS', true);

// Log de requisições SEFAZ
define('FISCAL_LOG_SEFAZ', true);

// Diretório de logs
define('FISCAL_LOG_DIR', __DIR__ . '/../logs/');

// =====================================================
// 📋 CONFIGURAÇÕES DE BACKUP
// =====================================================

// Fazer backup automático dos documentos
define('FISCAL_BACKUP_AUTO', true);

// Intervalo de backup (em horas)
define('FISCAL_BACKUP_INTERVAL', 24);

// Manter backups por X dias
define('FISCAL_BACKUP_RETENTION_DAYS', 90);

// Criptografar backups
define('FISCAL_BACKUP_ENCRYPT', true);

// Diretório de backup
define('FISCAL_BACKUP_DIR', __DIR__ . '/../backup/');

// =====================================================
// 🎯 CONFIGURAÇÕES DE VALIDAÇÃO
// =====================================================

// Validar CNPJ antes de emitir documentos
define('FISCAL_VALIDATE_CNPJ', true);

// Validar endereços antes de emitir documentos
define('FISCAL_VALIDATE_ADDRESS', true);

// Validar dados do motorista antes de emitir MDF-e
define('FISCAL_VALIDATE_DRIVER', true);

// Validar dados do veículo antes de emitir MDF-e
define('FISCAL_VALIDATE_VEHICLE', true);

// =====================================================
// 📱 CONFIGURAÇÕES DE NOTIFICAÇÕES
// =====================================================

// Enviar notificações por e-mail
define('FISCAL_NOTIFY_EMAIL', true);

// Enviar notificações por SMS (se implementado)
define('FISCAL_NOTIFY_SMS', false);

// Enviar notificações push (se implementado)
define('FISCAL_NOTIFY_PUSH', false);

// Notificar administradores sobre erros críticos
define('FISCAL_NOTIFY_ADMIN_ERRORS', true);

// =====================================================
// 🔒 CONFIGURAÇÕES DE PERMISSÕES
// =====================================================

// Usuários que podem cancelar documentos
define('FISCAL_USERS_CAN_CANCEL', [1, 2, 3]);

// Usuários que podem encerrar MDF-e
define('FISCAL_USERS_CAN_CLOSE_MDFE', [1, 2, 3, 4]);

// Usuários que podem emitir CCE
define('FISCAL_USERS_CAN_EMIT_CCE', [1, 2]);

// Usuários que podem acessar logs completos
define('FISCAL_USERS_CAN_VIEW_LOGS', [1]);

// =====================================================
// 📋 EXEMPLO DE USO
// =====================================================

/*
// Exemplo de como usar as configurações:
if (FISCAL_DEBUG_MODE) {
    error_log("Modo debug ativado para empresa ID: " . FISCAL_EMPRESA_ID);
}

// Verificar se certificado está próximo do vencimento
$vencimento = new DateTime(FISCAL_CERTIFICADO_VENCIMENTO);
$hoje = new DateTime();
$dias_para_vencer = $hoje->diff($vencimento)->days;

if ($dias_para_vencer <= FISCAL_ALERT_CERTIFICADO_DAYS) {
    // Enviar alerta
    error_log("ALERTA: Certificado vence em {$dias_para_vencer} dias!");
}

// Configurar timezone
date_default_timezone_set(FISCAL_TIMEZONE);
*/

// =====================================================
// ✅ CONFIGURAÇÃO CONCLUÍDA
// =====================================================

// Verificar se todas as constantes necessárias estão definidas
$required_constants = [
    'FISCAL_EMPRESA_ID',
    'FISCAL_CNPJ_EMPRESA',
    'FISCAL_RAZAO_SOCIAL',
    'FISCAL_CERTIFICADO_PATH',
    'FISCAL_CERTIFICADO_SENHA'
];

foreach ($required_constants as $constant) {
    if (!defined($constant)) {
        die("ERRO: Constante obrigatória '{$constant}' não está definida!");
    }
}

// Configuração carregada com sucesso
if (FISCAL_DEBUG_MODE) {
    error_log("✅ Configuração fiscal carregada com sucesso para empresa: " . FISCAL_RAZAO_SOCIAL);
}
?>
