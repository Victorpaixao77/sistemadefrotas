# Importação de XML/PDF e geração de Rotas

## Contexto

O número do arquivo que você mencionou (`11260202393780000102550020009170701484604326`) é uma **chave de acesso de 44 dígitos**, típica de **CT-e** (Conhecimento de Transporte Eletrônico) ou **NF-e**. Com arquivos **XML** (do CT-e/NFe) ou **PDF** (DACTE/DANFE), é possível extrair dados e usar para preencher ou gerar registros no sistema.

---

## O que o sistema faz hoje

| Recurso | Situação |
|--------|----------|
| **Importar NF-e XML** | Existe tela no módulo Fiscal (`fiscal/pages/nfe.php`) que envia o XML para `fiscal/api/fiscal_nfe.php?action=importar_xml`. Hoje a API **não interpreta o XML**; apenas simula sucesso. |
| **Tabelas fiscais** | O schema fiscal (`fiscal/database/schema_fiscal.sql`) já tem tabelas com campos que batem com documento de transporte: **fiscal_cte** (origem/destino, valor_total, peso_total, data_emissao, chave_acesso) e **fiscal_nfe_clientes**. |
| **Módulo Rotas** | As rotas são criadas manualmente em **Rotas** (ou via API `api/route_actions.php?action=add`). **Não existe** hoje nenhum fluxo que importe XML/PDF e crie rotas automaticamente. |

Ou seja: **não há hoje geração automática de rotas a partir de XML ou PDF**. Só é possível usar o que você descreveu (“dois arquivos xml ou pdf”) se for implementada a leitura real do XML (e, se desejado, do PDF) e o vínculo com o módulo de Rotas.

---

## O que conseguimos gerar em Rotas (se implementar a importação)

A partir de um **CT-e em XML** (e, em parte, do **PDF do DACTE**), os dados abaixo podem ser obtidos e usados para **preencher uma rota** no sistema.

### Dados que o CT-e traz e que batem com a tabela `rotas`

| Campo no CT-e (XML) | Uso na rota | Observação |
|--------------------|-------------|------------|
| **Município/UF de origem** | `estado_origem` + `cidade_origem_id` | Buscar ou criar cidade em `cidades` pelo código IBGE do município. |
| **Município/UF de destino** | `estado_destino` + `cidade_destino_id` | Mesmo critério (código IBGE). |
| **Valor total do frete** | `frete` | Valor do serviço de transporte. |
| **Data de emissão** | `data_saida` ou `data_rota` | Pode ser usada como data da viagem. |
| **Peso** | `peso_carga` | Quando informado no CT-e. |
| **Natureza da carga / descrição** | `descricao_carga` ou `observacoes` | Texto disponível no XML. |
| **Chave do CT-e** | (futuro) | Pode ser guardada em um campo ou tabela de vínculo rota ↔ documento fiscal. |

### Campos da rota que o XML/PDF **não** preenchem sozinhos

- **Motorista** (`motorista_id`) – não vem no CT-e; usuário escolhe ou deixa em branco se o sistema permitir.
- **Veículo** (`veiculo_id`) – idem.
- **Cliente** – o CT-e tem remetente/destinatário/expedidor; pode ser usado para sugerir ou criar cliente e depois vincular à rota, se o sistema tiver esse vínculo.
- **Km saída/chegada**, **distância_km** – em geral não vêm no CT-e; podem ficar em branco ou ser preenchidos depois.

Ou seja: com **importação real do XML** (e opcionalmente leitura do PDF), dá para gerar em **Rotas** pelo menos: **origem, destino, valor do frete, data, peso e descrição da carga**, e deixar motorista/veículo para escolha manual ou regras futuras.

---

## Resumo prático

- **Hoje:** importar XML/PDF **não** gera rotas automáticas; a importação de NF-e é apenas simulada.
- **Com implementação da leitura do XML (e, se quiser, do PDF):**
  - **CT-e:** dá para preencher automaticamente em Rotas: **origem** (cidade/UF), **destino** (cidade/UF), **frete**, **data**, **peso** e **descrição da carga**. Motorista e veículo continuam a ser definidos no sistema (ou por regras a criar).
  - **NF-e:** se tiver grupo de transporte (origem, destino, valor frete), esses dados também podem alimentar os mesmos campos da rota.

Se quiser, no próximo passo podemos desenhar o fluxo (telas + APIs) para: 1) importar o XML do CT-e, 2) parsear e gravar em `fiscal_cte` (ou só em memória), e 3) criar ou pré-preencher uma rota em **Rotas** com os campos acima.

---

## Exemplo real: NF-e de venda de combustível

Com base no XML de NF-e que você enviou (chave `11260202393780000102550020009170701484604326`), estes são os dados que **dá para importar e usar para criar uma rota**:

### Dados diretos do XML (tags oficiais)

| Dado no XML | Caminho / tag | Uso na rota |
|-------------|----------------|-------------|
| **Origem (emitente)** | `emit/enderEmit` | **Cidade origem:** Vilhena/RO — `xMun` + `UF`; código IBGE `cMun` 1100304 → `cidade_origem_id` |
| **Destino (destinatário)** | `dest/enderDest` | **Cidade destino:** Sarandi/PR — `xMun` + `UF`; código IBGE `cMun` 4126256 → `cidade_destino_id` |
| **Data de saída/emissão** | `ide/dhEmi` ou `ide/dhSaiEnt` | **Data da rota:** 2026-02-02 08:55 → `data_saida` ou `data_rota` |
| **Natureza da operação** | `ide/natOp` | Ex.: "VENDA DE COMBUSTIVEL" → pode ir em `observacoes` ou `descricao_carga` |
| **Produto e quantidade** | `det/prod`: `xProd`, `qCom`, `uCom` | Ex.: "OLEO DIESEL B S500 - 282 L" → `descricao_carga` |
| **Valor da NF** | `total/ICMSTot/vNF` | 1785.06 — na NF-e não há `vFrete` (é 0); pode usar como referência em `observacoes` ou, se a regra de negócio for “valor da entrega”, em campo de valor. |
| **Chave da NF-e** | `infNFe Id` ou `protNFe/infProt/chNFe` | Guardar para vincular rota ↔ documento fiscal (ex.: em `observacoes` ou em campo futuro). |

### Dados nas informações complementares (`infAdic/infCpl`)

No seu XML, o campo **`infCpl`** traz texto livre com dados que podem ser **parseados** para preencher a rota e até fazer **vínculo com motorista e veículo**:

| Texto em `infCpl` | Exemplo no seu XML | Uso na rota |
|-------------------|--------------------|-------------|
| **Motorista** | "Motorista: LEANDRO JOSE - CPF: 030.855.059-54" | Buscar em `motoristas` por CPF (030.855.059-54) → `motorista_id` |
| **Placa** | "Placa: ARV-8F52" | Buscar em `veiculos` por placa (ARV-8F52) → `veiculo_id` |
| **KM** | "KM:1846836" | Quilometragem atual → `km_saida` ou `km_chegada` (conforme regra) |
| **EI / EF** | "EI:971698 EF:971980" | Pode usar como km inicial (971698) e km final (971980) → `km_saida`, `km_chegada`, e `distancia_km` = 282 km |
| **Cliente** | "Cliente: 001137/01" | Código interno do cliente → pode vincular a um cliente no sistema, se existir cadastro por código. |

Ou seja: **dessa NF-e você consegue importar e criar uma rota com**:

- **Origem:** Vilhena/RO (código IBGE 1100304)  
- **Destino:** Sarandi/PR (código IBGE 4126256)  
- **Data:** 2026-02-02  
- **Descrição da carga:** "OLEO DIESEL B S500 - 282 L"  
- **Motorista:** por CPF do `infCpl` (se existir no cadastro)  
- **Veículo:** por placa do `infCpl` (se existir no cadastro)  
- **Km saída / km chegada / distância:** por KM e EI/EF do `infCpl` (se o texto seguir esse padrão)  
- **Observações:** chave da NF-e, natureza da operação, valor da NF, etc.

O **valor do frete** na NF-e está zerado (`transp/modFrete` 9 = sem frete, `vFrete` 0). Se a sua regra for “valor da entrega”, pode usar `vNF` apenas como referência (ex.: em observações) ou em um campo de valor da rota, conforme definição do negócio.
