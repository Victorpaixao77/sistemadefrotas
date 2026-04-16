/**
 * Validação de CPF/CNPJ no browser (mesma regra que includes/doc_validators.php).
 */
(function (global) {
    'use strict';

    function onlyDigits(s) {
        return String(s || '').replace(/\D/g, '');
    }

    function validarCpf(cpf) {
        const c = onlyDigits(cpf);
        if (c.length !== 11) return false;
        if (/^(\d)\1{10}$/.test(c)) return false;
        let soma = 0;
        for (let i = 0, j = 10; i < 9; i++, j--) {
            soma += parseInt(c[i], 10) * j;
        }
        let r = soma % 11;
        const d1 = r < 2 ? 0 : 11 - r;
        if (parseInt(c[9], 10) !== d1) return false;
        soma = 0;
        for (let i = 0, j = 11; i < 10; i++, j--) {
            soma += parseInt(c[i], 10) * j;
        }
        r = soma % 11;
        const d2 = r < 2 ? 0 : 11 - r;
        return parseInt(c[10], 10) === d2;
    }

    function validarCnpj(cnpj) {
        const c = onlyDigits(cnpj);
        if (c.length !== 14) return false;
        if (/^(\d)\1{13}$/.test(c)) return false;
        const p1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        let soma = 0;
        for (let i = 0; i < 12; i++) {
            soma += parseInt(c[i], 10) * p1[i];
        }
        let r = soma % 11;
        const dv1 = r < 2 ? 0 : 11 - r;
        if (parseInt(c[12], 10) !== dv1) return false;
        const p2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        soma = 0;
        for (let i = 0; i < 13; i++) {
            soma += parseInt(c[i], 10) * p2[i];
        }
        r = soma % 11;
        const dv2 = r < 2 ? 0 : 11 - r;
        return parseInt(c[13], 10) === dv2;
    }

    global.DocValidators = {
        onlyDigits: onlyDigits,
        validarCpf: validarCpf,
        validarCnpj: validarCnpj
    };
})(typeof window !== 'undefined' ? window : this);
