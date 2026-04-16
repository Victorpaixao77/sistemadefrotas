<?php

/**
 * Requisitos de extensões PHP para integração SEFAZ via NFePHP (SoapClient / SOAPHeader).
 */
function fiscal_require_soap_for_sefaz(): void
{
    if (extension_loaded('soap') && class_exists('SoapClient', false) && class_exists('SOAPHeader', false)) {
        return;
    }

    $ini = function_exists('php_ini_loaded_file') ? (string)php_ini_loaded_file() : '';
    $extra = sprintf(
        ' [Diagnóstico: PHP %s, SAPI %s, soap=%s, ini=%s]',
        PHP_VERSION,
        PHP_SAPI,
        extension_loaded('soap') ? 'sim' : 'não',
        $ini !== '' ? $ini : '(desconhecido)'
    );

    throw new RuntimeException(
        'Extensão PHP SOAP não está habilitada neste processo (SoapClient/SOAPHeader). '
        . 'O comando "php -m" no SSH mostra o PHP de linha de comando; o site pode usar outra versão (ex.: PHP 8.3 no Apache enquanto você instalou php8.2-soap). '
        . 'Instale o pacote que combina com a versão do PHP do site: ex. sudo apt install php8.3-soap && sudo systemctl restart php8.3-fpm apache2. '
        . 'No Windows/XAMPP: php.ini → descomente extension=soap e reinicie o Apache.'
        . $extra
    );
}
