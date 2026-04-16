<?php
/**
 * DANFE em layout oficial (NFePHP sped-da) a partir do XML completo (NFe ou nfeProc).
 * Inclui protocolo, totais, impostos, infAdic, pagamento, etc.
 */
function fiscal_gerar_danfe_pdf_sped_da(?string $xml): ?string
{
    if ($xml === null || trim($xml) === '' || strpos($xml, '<') === false) {
        return null;
    }
    if (!class_exists(\NFePHP\DA\NFe\Danfe::class)) {
        error_log(
            'fiscal_gerar_danfe_pdf_sped_da: pacote ausente. Na pasta raiz do sistema (onde está composer.json), execute: composer install'
        );
        return null;
    }
    try {
        $danfe = new \NFePHP\DA\NFe\Danfe($xml);
        $danfe->exibirPIS = true;
        $danfe->exibirIcmsInterestadual = true;
        $danfe->exibirValorTributos = true;
        $danfe->exibirTextoFatura = true;
        $danfe->descProdInfoComplemento = true;
        $danfe->setOcultarUnidadeTributavel(false);
        $danfe->printParameters('P', 'A4', 2, 2);
        $danfe->debugMode(false);
        $bin = $danfe->render('');
        return is_string($bin) && $bin !== '' ? $bin : null;
    } catch (Throwable $e) {
        error_log('fiscal_gerar_danfe_pdf_sped_da: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}
