# Implementação das 7 melhorias – App Motorista

Resumo do que foi implementado.

---

## 1. ViewModels

- **RotasViewModel** e **RotasViewModelFactory** criados.
- **RotasScreen** passou a usar o ViewModel: lista, refreshing e error vêm do `RotasViewModel`; `refreshList()` chama `vm.refreshList()`.
- As outras abas (Abastecimentos, Checklists, Despesas) continuam com estado na tela; o mesmo padrão pode ser aplicado depois.

---

## 2. Modo offline (Room)

- **Room** adicionado (dependências + KSP).
- **Entidades:** `RotaEntity`, `AbastecimentoEntity`, `ChecklistEntity`.
- **DAOs:** `RotaDao`, `AbastecimentoDao`, `ChecklistDao`.
- **AppDatabase** e **DatabaseProvider** (singleton).
- **LocalCache**: salva no banco quando a API retorna sucesso; lê do banco quando a API falha (ex.: sem internet).
- **AuthRepository** passou a receber `LocalCache` opcional e usa em `loadRotas`, `loadAbastecimentos` e `loadChecklists`.
- **MainActivity** cria `LocalCache(DatabaseProvider.get(applicationContext))` e injeta em `AuthRepository`.

---

## 3. Máscaras de entrada

- **InputMasks.kt**: `CurrencyTransformation` (R$ 1.234,56), `PlacaTransformation` (ABC-1D23), `KmTransformation` (quilometragem com separador).
- **Nova Rota**: Valor do Frete e Comissão com `CurrencyTransformation` (valor guardado em centavos); KM Saída, KM Chegada e KM Vazio com `KmTransformation`.
- Funções auxiliares: `parseCurrencyToDouble`, `formatCurrency`, `currencyRawDigits`.

---

## 4. Integração com câmera / galeria

- **Permissão** `CAMERA` no manifesto.
- **Coil** para exibir imagens.
- **Novo Abastecimento**: botão "Anexar comprovante (câmera ou galeria)" com `rememberLauncherForActivityResult(GetContent())` para `image/*`; foto exibida com `AsyncImage`; opção "Remover foto".
- Envio do arquivo para o servidor (multipart) **não** implementado; depende de a API aceitar anexo no endpoint de abastecimento.

---

## 5. Localização (GPS)

- **Permissões** `ACCESS_FINE_LOCATION` e `ACCESS_COARSE_LOCATION` no manifesto.
- **Play Services Location** e **LocationHelper** (`getLastLocation()`, `hasLocationPermission()`).
- **Nova Rota**: ao abrir o formulário, tenta obter última localização e envia `latitude` e `longitude` no body (se a API aceitar).
- **Novo Abastecimento**: mesma lógica e envio de `latitude` e `longitude`.
- **Opcional:** pedir permissão em tempo de execução (por exemplo com `rememberLauncherForActivityResult(PermissionRequest())`) na primeira vez que abrir uma tela que usa localização.

---

## 6. Skeletons (estados de carregamento)

- **ShimmerSkeleton.kt**: `ShimmerBox`, `RotaListItemSkeleton`, `AbastecimentoListItemSkeleton`, `ChecklistListItemSkeleton`, e listas de skeleton (`RotasListSkeleton`, `AbastecimentosListSkeleton`, `ChecklistsListSkeleton`).
- **RotasScreen**: quando `refreshing && list.isEmpty()`, exibe `RotasListSkeleton` em vez de `CircularProgressIndicator`.
- **AbastecimentosScreen** e **ChecklistsScreen**: mesmo padrão com seus skeletons.

---

## 7. Refresh token

- **AuthDataStore**: chave `REFRESH_TOKEN` e `getRefreshToken()`; `saveLogin` e `clear` passam a tratar refresh token.
- **LoginData** (ApiModels): campo opcional `refresh_token`.
- **AuthRepository**: `refreshToken()` chama a API com `action=refresh` e `refresh_token`; em sucesso, atualiza token e refresh token e chama `Api.setToken()`.
- **Api.kt**: `authRepository` configurável; **Authenticator** no OkHttp que, em resposta 401, chama `authRepository?.refreshToken()` e repete o request com o novo token.
- **MainActivity**: define `Api.authRepository = authRepo` após criar o repositório.
- **ApiService**: endpoint `refreshToken(@Body body)`.
- **API PHP (auth.php)**:
  - Login retorna `refresh_token` (igual ao token por enquanto).
  - Nova ação `action=refresh`: recebe `refresh_token`, valida, emite novo token e atualiza a linha na tabela; retorna novo `token` e `refresh_token`.

---

## Arquivos novos

- `ui/components/InputMasks.kt`
- `ui/components/ShimmerSkeleton.kt`
- `ui/viewmodel/RotasViewModel.kt`
- `ui/viewmodel/RotasViewModelFactory.kt`
- `data/local/RotaEntity.kt`, `AbastecimentoEntity.kt`, `ChecklistEntity.kt`
- `data/local/RotaDao.kt`, `AbastecimentoDao.kt`, `ChecklistDao.kt`
- `data/local/AppDatabase.kt`
- `data/local/LocalCache.kt`
- `data/LocationHelper.kt`

## Dependências adicionadas (build.gradle.kts)

- Room (runtime, ktx, compiler com KSP)
- Coil Compose
- Play Services Location
- Plugin KSP (root e app)

## Permissões (AndroidManifest)

- `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION`, `CAMERA`, `uses-feature camera` (não obrigatório).

---

## Pendências / melhorias opcionais

1. **Permissão de localização em runtime** – pedir ao usuário na primeira vez que abrir Nova Rota/Novo Abastecimento.
2. **Upload do comprovante** – quando a API tiver endpoint para anexo, enviar o arquivo com Multipart no Retrofit.
3. **ViewModels para as outras abas** – Abastecimentos, Checklists e Despesas podem seguir o mesmo padrão do RotasViewModel.
4. **API** – se as tabelas de rotas/abastecimentos tiverem colunas `latitude`/`longitude`, o backend pode persistir esses campos; caso contrário, podem ser ignorados sem quebrar o app.
