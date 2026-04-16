# App Android - Frotas Motorista

Aplicativo para caminhoneiros que consome a API em `../api/`.

## Design

- **Tema:** Material 3 com paleta “estrada”: fundo slate (cinza escuro), destaque em âmbar/laranja.
- **Telas:** Login, Dashboard (resumo + rotas do dia e últimas), Rotas, Abastecimentos, Checklists.
- **Navegação:** Barra inferior com 4 abas; barra superior com título e botão Sair.

## Configuração

1. **URL da API**  
   Edite em `app/src/main/java/com/sistemafrotas/motorista/data/api/Api.kt`:
   - Emulador: `http://10.0.2.2/sistema-frotas/app_android/api/`
   - Dispositivo físico: `http://SEU_IP/sistema-frotas/app_android/api/`

2. **Android Studio**  
   Abra a pasta `android` como projeto e sincronize o Gradle.

3. **Build**  
   - Debug: `./gradlew assembleDebug`  
   - Instalar no dispositivo/emulador: Run ▶ do Android Studio.

## Estrutura

- `data/api/` – Retrofit, modelos, interceptor com token.
- `data/AuthDataStore.kt` – DataStore para token e nome.
- `data/AuthRepository.kt` – Login, logout, dashboard.
- `ui/theme/Theme.kt` – Cores e tipografia.
- `ui/screens/` – LoginScreen, MainScaffold, DashboardScreen, RotasScreen, AbastecimentosScreen, ChecklistsScreen.

## Credenciais

Use o mesmo usuário/senha do **Portal do Motorista** (`usuarios_motoristas`: nome e senha).
