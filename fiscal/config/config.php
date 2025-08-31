<?php
/**
 * 🚀 CONFIGURAÇÃO DO SISTEMA FISCAL
 * 📋 SISTEMA DE FROTAS - MÓDULO FISCAL
 * 
 * Este arquivo contém as configurações básicas do sistema fiscal
 * 
 * 📅 Data: Agosto 2025
 * 🔧 Versão: 2.0.0
 */

// =====================================================
// 🔐 CONFIGURAÇÕES DE SEGURANÇA
// =====================================================
define('FISCAL_CRYPTO_ALGORITHM', 'AES-256');
define('FISCAL_SESSION_TIMEOUT', 3600);
define('FISCAL_MAX_LOGIN_ATTEMPTS', 5);
define('FISCAL_BLOCK_TIME', 900);

// =====================================================
// 🏢 CONFIGURAÇÕES DA EMPRESA
// =====================================================
define('FISCAL_EMPRESA_ID', 1); // Será sobrescrito pela sessão
define('FISCAL_CNPJ_EMPRESA', '12.345.678/0001-90');
define('FISCAL_RAZAO_SOCIAL', 'EMPRESA EXEMPLO LTDA');
define('FISCAL_NOME_FANTASIA', 'EMPRESA EXEMPLO');
define('FISCAL_INSCRICAO_ESTADUAL', '123456789');
define('FISCAL_CODIGO_MUNICIPIO', '3550308');
define('FISCAL_CEP', '01310-100');
define('FISCAL_ENDERECO', 'Av. Paulista, 1000');
define('FISCAL_TELEFONE', '(11) 3000-0000');
define('FISCAL_EMAIL', 'fiscal@empresa.com');

// =====================================================
// 🔑 CONFIGURAÇÕES DE CERTIFICADO DIGITAL
// =====================================================
define('FISCAL_CERTIFICADO_PATH', '../certificados/certificado.pfx');
define('FISCAL_CERTIFICADO_SENHA', 'senha_do_certificado');
define('FISCAL_CERTIFICADO_TIPO', 'A1');
define('FISCAL_CERTIFICADO_EMISSOR', 'SERASA EXPERIAN');

// =====================================================
// 🌐 CONFIGURAÇÕES SEFAZ
// =====================================================
define('FISCAL_AMBIENTE_SEFAZ', 'homologacao'); // producao ou homologacao
define('FISCAL_URL_SEFAZ_SP', 'https://nfe.fazenda.sp.gov.br/ws/');
define('FISCAL_URL_SEFAZ_NACIONAL', 'https://nfe.fazenda.sp.gov.br/ws/');
define('FISCAL_TIMEOUT_SEFAZ', 30);
define('FISCAL_RETRY_SEFAZ', 3);

// =====================================================
// 📁 CONFIGURAÇÕES DE ARQUIVOS
// =====================================================
define('FISCAL_UPLOAD_PATH', '../uploads/');
define('FISCAL_XML_PATH', '../uploads/xml/');
define('FISCAL_PDF_PATH', '../uploads/pdf/');
define('FISCAL_CTE_PATH', '../uploads/cte/');
define('FISCAL_MDFE_PATH', '../uploads/mdfe/');
define('FISCAL_MAX_FILE_SIZE', 10485760); // 10MB
define('FISCAL_ALLOWED_EXTENSIONS', ['xml', 'pdf']);

// =====================================================
// 📧 CONFIGURAÇÕES DE EMAIL
// =====================================================
define('FISCAL_EMAIL_HOST', 'smtp.gmail.com');
define('FISCAL_EMAIL_PORT', 587);
define('FISCAL_EMAIL_USERNAME', 'fiscal@empresa.com');
define('FISCAL_EMAIL_PASSWORD', 'senha_do_email');
define('FISCAL_EMAIL_FROM_NAME', 'Sistema Fiscal');
define('FISCAL_EMAIL_ENCRYPTION', 'tls');

// =====================================================
// 🔄 CONFIGURAÇÕES DE SINCRONIZAÇÃO
// =====================================================
define('FISCAL_SYNC_INTERVAL', 300); // 5 minutos
define('FISCAL_AUTO_SYNC', true);
define('FISCAL_SYNC_TIMEOUT', 60);
define('FISCAL_MAX_SYNC_ATTEMPTS', 3);

// =====================================================
// 📊 CONFIGURAÇÕES DE RELATÓRIOS
// =====================================================
define('FISCAL_REPORT_PATH', '../reports/');
define('FISCAL_REPORT_FORMAT', 'pdf'); // pdf, excel, csv
define('FISCAL_REPORT_TEMPLATE', 'default');
define('FISCAL_REPORT_LOGO', '../assets/img/logo.png');

// =====================================================
// 🚨 CONFIGURAÇÕES DE ALERTAS
// =====================================================
define('FISCAL_ALERT_CERTIFICADO_DAYS', 30);
define('FISCAL_ALERT_MDFE_DAYS', 7);
define('FISCAL_ALERT_EMAIL', true);
define('FISCAL_ALERT_SMS', false);
define('FISCAL_ALERT_PUSH', false);

// =====================================================
// 🛠️ CONFIGURAÇÕES DE DESENVOLVIMENTO
// =====================================================
define('FISCAL_DEBUG_MODE', true);
define('FISCAL_LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('FISCAL_LOG_PATH', '../logs/fiscal.log');
define('FISCAL_SHOW_ERRORS', true);
define('FISCAL_DEV_MODE', true);

// =====================================================
// 💾 CONFIGURAÇÕES DE BACKUP
// =====================================================
define('FISCAL_BACKUP_ENABLED', true);
define('FISCAL_BACKUP_PATH', '../backups/fiscal/');
define('FISCAL_BACKUP_INTERVAL', 86400); // 24 horas
define('FISCAL_BACKUP_RETENTION', 30); // dias
define('FISCAL_BACKUP_ENCRYPT', true);

// =====================================================
// ✅ CONFIGURAÇÃO CONCLUÍDA
// =====================================================
if (FISCAL_DEBUG_MODE) {
    error_log("✅ Configuração fiscal carregada com sucesso!");
}

// Função para obter configuração dinâmica
function getFiscalConfig($key, $default = null) {
    $constant_name = 'FISCAL_' . strtoupper($key);
    return defined($constant_name) ? constant($constant_name) : $default;
}

// Função para verificar se o ambiente é de produção
function isFiscalProduction() {
    return FISCAL_AMBIENTE_SEFAZ === 'producao';
}

// Função para obter URL da SEFAZ baseada no estado
function getFiscalSefazUrl($estado = 'SP') {
    if ($estado === 'SP') {
        return FISCAL_URL_SEFAZ_SP;
    }
    return FISCAL_URL_SEFAZ_NACIONAL;
}

// Função para obter caminho de upload baseado no tipo
function getFiscalUploadPath($tipo) {
    switch (strtolower($tipo)) {
        case 'xml':
            return FISCAL_XML_PATH;
        case 'pdf':
            return FISCAL_PDF_PATH;
        case 'cte':
            return FISCAL_CTE_PATH;
        case 'mdfe':
            return FISCAL_MDFE_PATH;
        default:
            return FISCAL_UPLOAD_PATH;
    }
}
?>
