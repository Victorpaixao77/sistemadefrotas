<?php
declare(strict_types=1);

/**
 * Valida CSRF em POST do painel ADM.
 */
function adm_csrf_valid_post(): bool
{
    if (!function_exists('csrf_token_validate')) {
        require_once dirname(__DIR__, 2) . '/includes/csrf.php';
    }
    return csrf_token_validate($_POST['csrf_token'] ?? null);
}

function adm_csrf_field(): string
{
    if (!function_exists('csrf_token_get')) {
        require_once dirname(__DIR__, 2) . '/includes/csrf.php';
    }
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8') . '">';
}

function adm_status_saas_normalize(?string $v): string
{
    $allowed = ['trial', 'ativa', 'bloqueada', 'cancelada', 'suspensa'];
    $v = $v ?? 'ativa';
    return in_array($v, $allowed, true) ? $v : 'ativa';
}
