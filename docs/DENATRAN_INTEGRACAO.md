# Integração WSDenatran – Consulta de Multas no DETRAN

A tela **Multas** do sistema possui a seção **Consulta de Multas no DETRAN**, que utiliza o serviço **WSDenatran** (REST) para consultar infrações na base do Denatran.

## Requisitos

- **Cadastro de certificado** junto ao Denatran/SERPRO para uso do serviço.
- Configuração no arquivo `includes/denatran_config.php`.

## Configuração

Edite `includes/denatran_config.php`:

1. **DENATRAN_BASE_URL**  
   - Desenvolvimento: `https://wsdenatrandes-des07116.apps.dev.serpro`  
   - Homologação: `https://wsrenavam.hom.denatran.serpro.gov.br`  
   - Produção: `https://renavam.denatran.serpro.gov.br`

2. **DENATRAN_CPF_USUARIO**  
   CPF (apenas números) do usuário autorizado a fazer as consultas. Pode ser deixado vazio se o CPF for informado no formulário a cada consulta.

3. **DENATRAN_CERT_PATH**  
   Caminho completo do arquivo do certificado digital (ex.: `.pem` ou `.crt`).

4. **DENATRAN_KEY_PATH**  
   Caminho completo do arquivo da chave privada (ex.: `.key`).

5. **DENATRAN_KEY_PASS**  
   Senha da chave privada, se houver.

6. **DENATRAN_HABILITADO**  
   Defina como `true` após configurar certificado e URLs para ativar a consulta.

## Tipos de consulta na tela

- **Por CPF:** infrações do condutor/proprietário (CPF).
- **Por Placa:** infrações do veículo (placa + exigibilidade: Todas, Exigível, Não exigível).
- **Por CNPJ:** infrações do proprietário pessoa jurídica.

Em todas as consultas é obrigatório informar o **CPF do usuário** (quem está consultando), usado no header `x-cpf-usuario` do WSDenatran. As datas início e fim são opcionais para filtrar o período.

## Códigos de retorno do WSDenatran

| Código | Significado        |
|--------|--------------------|
| 200    | Sucesso            |
| 400    | Requisição inválida (ex.: parâmetro faltando) |
| 401    | Não autorizado (certificado ou CPF) |
| 402    | Erro de negócio    |
| 404    | Recurso não encontrado |
| 500    | Erro no servidor   |

Quando a integração não está habilitada (`DENATRAN_HABILITADO = false`), a API retorna mensagem orientando a configurar o certificado e habilitar em `denatran_config.php`.
