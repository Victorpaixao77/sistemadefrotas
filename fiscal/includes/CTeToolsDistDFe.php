<?php

/**
 * Estende NFePHP\CTe\Tools para corrigir sefazDownload: a versão original
 * monta XML inválido (usa consChNFe e não abre distDFeInt). Para CT-e
 * o correto é distDFeInt com tpAmb, cUFAutor, CNPJ/CPF e consChCTe/chCTe.
 *
 * @link https://github.com/nfephp-org/sped-cte
 */

use NFePHP\Common\UFList;

class CTeToolsDistDFe extends \NFePHP\CTe\Tools
{
    /**
     * Download de CT-e por chave na Distribuição DFe (corrigido).
     * O Tools original usa consChNFe e monta distDFeInt incorreto.
     *
     * @param string $chave Chave do CT-e (44 dígitos)
     * @return string XML de resposta SOAP
     */
    public function sefazDownload($chave)
    {
        $servico = 'CTeDistribuicaoDFe';
        $this->checkContingencyForWebServices($servico);
        $this->servico(
            $servico,
            'AN',
            $this->tpAmb,
            true
        );

        $cUF = UFList::getCodeByUF($this->config->siglaUF);
        $cnpj = $this->config->cnpj;
        $tagChave = '<consChCTe><chCTe>' . $chave . '</chCTe></consChCTe>';

        $consulta = '<distDFeInt xmlns="' . $this->urlPortal . '" versao="' . $this->urlVersion . '">'
            . '<tpAmb>' . $this->tpAmb . '</tpAmb>'
            . '<cUFAutor>' . $cUF . '</cUFAutor>'
            . (strlen($cnpj) == 14
                ? '<CNPJ>' . $cnpj . '</CNPJ>'
                : '<CPF>' . $cnpj . '</CPF>')
            . $tagChave
            . '</distDFeInt>';

        $this->isValid($this->urlVersion, $consulta, 'distDFeInt');
        $this->lastRequest = $consulta;

        $request = '<cteDadosMsg xmlns="' . $this->urlNamespace . '">' . $consulta . '</cteDadosMsg>';
        $parameters = ['cteDistDFeInteresse' => $request];
        $body = '<cteDistDFeInteresse xmlns="' . $this->urlNamespace . '">'
            . $request
            . '</cteDistDFeInteresse>';

        $this->objHeader = null;
        $this->lastResponse = $this->sendRequest($body, $parameters);
        return $this->lastResponse;
    }
}
