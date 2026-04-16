<?php
/**
 * Validação de CPF e CNPJ (formato + dígitos verificadores).
 * Uso: require em APIs e validações server-side.
 */

if (!function_exists('doc_only_digits')) {
    function doc_only_digits(?string $s): string {
        return preg_replace('/\D/', '', (string) $s);
    }
}

/**
 * CPF válido (11 dígitos, não sequência repetida, DV correto).
 */
function doc_validar_cpf(?string $cpf): bool {
    $c = doc_only_digits($cpf);
    if (strlen($c) !== 11) {
        return false;
    }
    if (preg_match('/^(\d)\1{10}$/', $c)) {
        return false;
    }
    $soma = 0;
    for ($i = 0, $j = 10; $i < 9; $i++, $j--) {
        $soma += (int) $c[$i] * $j;
    }
    $r = $soma % 11;
    $d1 = ($r < 2) ? 0 : 11 - $r;
    if ((int) $c[9] !== $d1) {
        return false;
    }
    $soma = 0;
    for ($i = 0, $j = 11; $i < 10; $i++, $j--) {
        $soma += (int) $c[$i] * $j;
    }
    $r = $soma % 11;
    $d2 = ($r < 2) ? 0 : 11 - $r;
    return (int) $c[10] === $d2;
}

/**
 * CNPJ válido (14 dígitos, não todos iguais, DV correto).
 */
function doc_validar_cnpj(?string $cnpj): bool {
    $c = doc_only_digits($cnpj);
    if (strlen($c) !== 14) {
        return false;
    }
    if (preg_match('/^(\d)\1{13}$/', $c)) {
        return false;
    }
    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $soma = 0;
    for ($i = 0; $i < 12; $i++) {
        $soma += (int) $c[$i] * $pesos1[$i];
    }
    $r = $soma % 11;
    $dv1 = $r < 2 ? 0 : 11 - $r;
    if ((int) $c[12] !== $dv1) {
        return false;
    }
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $soma = 0;
    for ($i = 0; $i < 13; $i++) {
        $soma += (int) $c[$i] * $pesos2[$i];
    }
    $r = $soma % 11;
    $dv2 = $r < 2 ? 0 : 11 - $r;
    return (int) $c[13] === $dv2;
}
