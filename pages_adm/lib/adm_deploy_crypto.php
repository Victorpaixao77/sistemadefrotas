<?php
declare(strict_types=1);

/**
 * Criptografia de credenciais de deploy (AES-256-GCM).
 * Chave: SF_DEPLOY_ENC_KEY (32+ bytes) ou derivada de SF_APP_KEY.
 */
function adm_deploy_enc_key(): string
{
    $key = getenv('SF_DEPLOY_ENC_KEY') ?: getenv('SF_APP_KEY') ?: '';
    if ($key === '') {
        $root = defined('SF_PROJECT_ROOT') ? SF_PROJECT_ROOT : dirname(__DIR__, 2);
        $key = 'frotec-deploy-local-' . $root;
    }
    return hash('sha256', $key, true);
}

function adm_deploy_encrypt(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return null;
    }
    $key = adm_deploy_enc_key();
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        return null;
    }
    return base64_encode($iv . $tag . $cipher);
}

function adm_deploy_decrypt(?string $enc): ?string
{
    if ($enc === null || $enc === '') {
        return null;
    }
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', adm_deploy_enc_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function adm_deploy_mask_secret(?string $v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    return '********';
}
