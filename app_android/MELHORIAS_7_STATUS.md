# Status das 7 melhorias – App Motorista

Resumo do que **já existe** e do que **ainda não tem** (e dá para colocar).

---

## 1. Arquitetura: ViewModels

| Status | Detalhe |
|--------|---------|
| **Não tem** | Não há nenhum `ViewModel` no projeto. Toda a lógica (carregar rotas, abastecimentos, cálculos) está dentro dos arquivos `*Screen.kt` com `remember { mutableStateOf(...) }`. |

**Dá para colocar:** Sim. Criar ViewModels (ex.: `RotasViewModel`, `AbastecimentosViewModel`) e mover carga de dados e estado para eles; as telas ficam só com UI e chamadas ao ViewModel. Isso também evita perder dados ao girar a tela.

---

## 2. Modo offline (Room)

| Status | Detalhe |
|--------|---------|
| **Não tem** | Não há Room, `@Entity`, `@Database` nem cache local. Tudo depende da API; sem internet não há lista de rotas/abastecimentos. |

**Dá para colocar:** Sim. Adicionar Room, entidades (ex.: rota, abastecimento), DAO e um repositório que escreve no banco ao receber da API e lê do banco quando offline; sincronizar quando a internet voltar (WorkManager ou ao abrir o app).

---

## 3. Máscaras de entrada e validação

| Status | Detalhe |
|--------|---------|
| **Parcial** | Existe só `PasswordVisualTransformation` no login. Campos de valor (R$), placa e KM são `OutlinedTextField` com filtro manual (ex.: `it.filter { c -> c.isDigit() \|\| c == '.' \|\| c == ',' }`), sem máscara visual (R$ 0,00), placa (ABC-1D23) ou KM formatado. |

**Dá para colocar:** Sim. Implementar `VisualTransformation` para:
- **Moeda (R$):** ex. `CurrencyTransformation()` em valor frete, comissão, despesas, litros/valor total.
- **Placa:** máscara tipo ABC-1D23 (ou padrão Mercosul).
- **Quilometragem:** máscara numérica com separador de milhar (opcional).

---

## 4. Integração com câmera

| Status | Detalhe |
|--------|---------|
| **Não tem** | Só há menção em `APP_CONTEUDO_E_MELHORIAS.md` (“Anexo de comprovante”). Não há uso de câmera, Coil, nem envio Multipart no Retrofit. Formulário de abastecimento mostra “Nenhum ficheiro selecionado” sem ação. |

**Dá para colocar:** Sim. Usar CameraX ou Intent para tirar foto/galeria, Coil para exibir a imagem, e Retrofit `MultipartBody.Part` para enviar o comprovante (depende da API aceitar anexo no endpoint de abastecimento/despesa).

---

## 5. Localização (GPS)

| Status | Detalhe |
|--------|---------|
| **Não tem** | Nenhum uso de `Location`, `FusedLocationProviderClient`, latitude/longitude. Início de rota ou registro de abastecimento não gravam coordenadas. |

**Dá para colocar:** Sim. Solicitar permissão de localização, usar Fused Location Provider para pegar última localização (ou atual) ao iniciar rota ou registrar abastecimento, e enviar lat/lng na API (se o backend tiver campos para isso).

---

## 6. UX: Skeletons (estados de carregamento)

| Status | Detalhe |
|--------|---------|
| **Não tem** | Listas usam apenas `CircularProgressIndicator` no centro ao carregar. Não há skeleton/shimmer que simule o layout dos cards. |

**Dá para colocar:** Sim. Criar um composable de skeleton (retângulos cinzas com `Modifier.shimmer()` ou biblioteca tipo `compose-shimmer`) e exibir em vez da lista vazia enquanto `refreshing && list.isEmpty()`; melhora a sensação de velocidade.

---

## 7. Refresh token

| Status | Detalhe |
|--------|---------|
| **Não tem** | Em `AuthRepository` e chamadas da API, ao receber **401** o app apenas limpa o token (`clearTokenLocally()`) e retorna erro, o que leva à tela “Sessão expirada” e novo login. Não há renovação automática de token nem `Authenticator` no OkHttp. |

**Dá para colocar:** Só se a **API** tiver endpoint de refresh (ex.: `POST /auth/refresh` com refresh_token). Se tiver: implementar um `Authenticator` no OkHttp que, ao receber 401, chama o refresh, atualiza o token e repete a requisição original sem deslogar. Se a API não devolver refresh_token, não dá para implementar renovação automática.

---

## Resumo rápido

| # | Melhoria              | Tem hoje? | Dá para colocar? |
|---|------------------------|-----------|-------------------|
| 1 | ViewModels             | Não       | Sim               |
| 2 | Modo offline (Room)    | Não       | Sim               |
| 3 | Máscaras (R$, placa, KM) | Parcial | Sim               |
| 4 | Câmera (comprovante)   | Não       | Sim (se API aceitar anexo) |
| 5 | GPS (lat/lng)          | Não       | Sim (se API aceitar lat/lng) |
| 6 | Skeletons               | Não       | Sim               |
| 7 | Refresh token           | Não       | Só se a API tiver refresh token |

**Ordem sugerida (impacto vs esforço):**  
3 (máscaras) → 6 (skeletons) → 1 (ViewModels) → 7 (refresh, se a API permitir) → 5 (GPS) → 4 (câmera) → 2 (Room).
