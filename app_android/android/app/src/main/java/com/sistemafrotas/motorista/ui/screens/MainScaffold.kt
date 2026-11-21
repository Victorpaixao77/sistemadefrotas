package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.ui.graphics.Color
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material.icons.filled.Route
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.TireRepair
import androidx.compose.material.icons.filled.Apps
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.outlined.Checklist
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.sistemafrotas.motorista.GpsForegroundService
import com.sistemafrotas.motorista.GpsPendingSyncWorker
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.GpsPreferencesStore
import com.sistemafrotas.motorista.ui.LocalAppSnackbar
import com.sistemafrotas.motorista.feature.viagem.ui.TripModeScreen
import com.sistemafrotas.motorista.ui.components.ConnectivityStatusBanner
import kotlinx.coroutines.launch

// Topo navy da identidade do sistema web (bg-sidebar #0f172a); azul fica só de acento.
private val ScaffoldNavyTop = Color(0xFF0F172A)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScaffold(
    authRepository: AuthRepository,
    onLogout: () -> Unit,
    onSessionExpired: () -> Unit = {},
) {
    val appContext = LocalContext.current.applicationContext
    val loggedIn by authRepository.isLoggedIn.collectAsStateWithLifecycle(initialValue = false)

    LaunchedEffect(loggedIn) {
        if (!loggedIn) {
            GpsForegroundService.stop(appContext)
            GpsPendingSyncWorker.cancel(appContext)
            return@LaunchedEffect
        }
    }

    var selectedTab by remember { mutableIntStateOf(0) }
    var showConfig by remember { mutableStateOf(false) }
    var showTripMode by remember { mutableStateOf(false) }
    var maisInicial by remember { mutableStateOf<String?>(null) }
    var subScreenTitle by remember { mutableStateOf<String?>(null) }
    var subScreenOnBack by remember { mutableStateOf<(() -> Unit)?>(null) }
    val tabs = listOf(
        "Início" to Icons.Filled.Dashboard,
        "Fretes" to Icons.Filled.LocalShipping,
        "Financeiro" to Icons.Filled.Payments,
        "Documentos" to Icons.Filled.Description,
        "Mais" to Icons.Filled.Apps,
    )

    val snackbarHostState = remember { SnackbarHostState() }
    CompositionLocalProvider(LocalAppSnackbar provides snackbarHostState) {
    Scaffold(
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            if (subScreenTitle != null && subScreenOnBack != null) {
                TopAppBar(
                    title = { Text(subScreenTitle!!) },
                    navigationIcon = {
                        IconButton(onClick = subScreenOnBack!!) {
                            Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar", tint = MaterialTheme.colorScheme.onPrimary)
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = ScaffoldNavyTop,
                        titleContentColor = Color.White,
                        actionIconContentColor = MaterialTheme.colorScheme.onPrimary,
                    ),
                )
            } else if (selectedTab != 0) {
                val tabTitles = listOf("", "Fretes", "Financeiro", "Documentos", "Mais")
                val title = tabTitles.getOrElse(selectedTab) { "Portal do Motorista" }
                TopAppBar(
                    title = { Text(title) },
                    navigationIcon = {
                        IconButton(onClick = { selectedTab = 0 }) {
                            Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar ao Início", tint = MaterialTheme.colorScheme.onPrimary)
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = ScaffoldNavyTop,
                        titleContentColor = Color.White,
                        actionIconContentColor = MaterialTheme.colorScheme.onPrimary,
                    ),
                )
            } else {
                // Início: header azul (saudação + sino + avatar/menu) é renderizado
                // pelo próprio DashboardScreen para o visual do Portal do Motorista.
            }
        },
        bottomBar = {
            NavigationBar {
                val itemColors = NavigationBarItemDefaults.colors(
                    selectedIconColor = MaterialTheme.colorScheme.primary,
                    selectedTextColor = MaterialTheme.colorScheme.primary,
                    indicatorColor = MaterialTheme.colorScheme.primaryContainer,
                    unselectedIconColor = MaterialTheme.colorScheme.onSurfaceVariant,
                    unselectedTextColor = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                tabs.forEachIndexed { index, (label, icon) ->
                    NavigationBarItem(
                        icon = { Icon(icon, contentDescription = label, modifier = Modifier.size(22.dp)) },
                        label = { Text(label, fontSize = 11.sp, maxLines = 1) },
                        selected = selectedTab == index,
                        onClick = { selectedTab = index },
                        alwaysShowLabel = true,
                        colors = itemColors,
                    )
                }
            }
        },
    ) { paddingValues ->
        // Conteúdo principal em Box.fillMaxSize (sem Column+weight): em alguns aparelhos/versões
        // do Compose, weight(1f) dentro do content do Scaffold media altura 0 e some tudo exceto as barras.
        Box(
            Modifier
                .fillMaxSize()
                .padding(paddingValues),
        ) {
            if (showConfig) {
                ConfigScreen(
                    authRepository = authRepository,
                    onBack = { showConfig = false },
                    modifier = Modifier.fillMaxSize(),
                )
            } else {
                when (selectedTab) {
                    0 -> DashboardScreen(
                        authRepository = authRepository,
                        onSessionExpired = onSessionExpired,
                        onNavigateToTab = { selectedTab = it },
                        onAbrirMais = { feature -> maisInicial = feature; selectedTab = 4 },
                        onOpenTripMode = { showTripMode = true },
                        onOpenConfig = { showConfig = true },
                        onLogout = onLogout,
                        modifier = Modifier.fillMaxSize(),
                    )
                    1 -> RotasScreen(
                        authRepository = authRepository,
                        modifier = Modifier.fillMaxSize(),
                        onSubScreenChange = { title, onBack ->
                            subScreenTitle = title
                            subScreenOnBack = onBack
                        },
                    )
                    2 -> DespesasScreen(
                        authRepository = authRepository,
                        modifier = Modifier.fillMaxSize(),
                        onSubScreenChange = { title, onBack ->
                            subScreenTitle = title
                            subScreenOnBack = onBack
                        },
                    )
                    3 -> PodScreen(
                        authRepository = authRepository,
                        modifier = Modifier.fillMaxSize(),
                    )
                    4 -> MaisScreen(
                        authRepository = authRepository,
                        modifier = Modifier.fillMaxSize(),
                        onSubScreenChange = { title, onBack ->
                            subScreenTitle = title
                            subScreenOnBack = onBack
                        },
                        inicial = maisInicial,
                        onInicialConsumed = { maisInicial = null },
                        onExitToHome = {
                            subScreenTitle = null
                            subScreenOnBack = null
                            selectedTab = 0
                        },
                        onOpenTripMode = { showTripMode = true },
                    )
                }
            }
            Column(Modifier.fillMaxWidth().align(Alignment.TopCenter)) {
                ConnectivityStatusBanner()
            }
            if (showTripMode) {
                TripModeScreen(
                    authRepository = authRepository,
                    onClose = { showTripMode = false },
                    onOpenFeature = { key ->
                        showTripMode = false
                        maisInicial = key
                        selectedTab = 4
                    },
                    modifier = Modifier.fillMaxSize(),
                )
            }
        }
    }
    }
}
