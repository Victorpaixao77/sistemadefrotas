package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Row
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material.icons.filled.Route
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.outlined.Checklist
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.collectAsState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.GpsForegroundService
import com.sistemafrotas.motorista.GpsPendingSyncWorker
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.GpsPreferencesStore
import com.sistemafrotas.motorista.ui.LocalAppSnackbar
import com.sistemafrotas.motorista.ui.components.ConnectivityStatusBanner
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScaffold(
    authRepository: AuthRepository,
    onLogout: () -> Unit,
    onSessionExpired: () -> Unit = {},
) {
    val appContext = LocalContext.current.applicationContext
    val loggedIn by authRepository.isLoggedIn.collectAsState(initial = false)

    LaunchedEffect(loggedIn) {
        if (!loggedIn) {
            GpsForegroundService.stop(appContext)
            GpsPendingSyncWorker.cancel(appContext)
            return@LaunchedEffect
        }
    }

    var selectedTab by remember { mutableIntStateOf(0) }
    var showConfig by remember { mutableStateOf(false) }
    var subScreenTitle by remember { mutableStateOf<String?>(null) }
    var subScreenOnBack by remember { mutableStateOf<(() -> Unit)?>(null) }
    val tabs = listOf(
        "Início" to Icons.Filled.Dashboard,
        "Rotas" to Icons.Filled.Route,
        "Abastecimentos" to Icons.Filled.LocalGasStation,
        "Checklists" to Icons.Outlined.Checklist,
        "Despesas" to Icons.Filled.Receipt,
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
                        containerColor = MaterialTheme.colorScheme.primary,
                        titleContentColor = MaterialTheme.colorScheme.onPrimary,
                        actionIconContentColor = MaterialTheme.colorScheme.onPrimary,
                    ),
                )
            } else if (selectedTab != 0) {
                val tabTitles = listOf("", "Rotas", "Abastecimentos", "Checklists", "Despesas")
                val title = tabTitles.getOrElse(selectedTab) { "Portal do Motorista" }
                TopAppBar(
                    title = { Text(title) },
                    navigationIcon = {
                        IconButton(onClick = { selectedTab = 0 }) {
                            Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar ao Início", tint = MaterialTheme.colorScheme.onPrimary)
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primary,
                        titleContentColor = MaterialTheme.colorScheme.onPrimary,
                        actionIconContentColor = MaterialTheme.colorScheme.onPrimary,
                    ),
                )
            } else {
                TopAppBar(
                    title = { Text("Portal do Motorista") },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primary,
                        titleContentColor = MaterialTheme.colorScheme.onPrimary,
                        actionIconContentColor = MaterialTheme.colorScheme.onPrimary,
                    ),
                    actions = {
                    var showMenu by remember { mutableStateOf(false) }
                    val scope = rememberCoroutineScope()
                    Box {
                        IconButton(onClick = { showMenu = true }) {
                            Icon(
                                Icons.Filled.MoreVert,
                                contentDescription = "Menu",
                                tint = MaterialTheme.colorScheme.onPrimary,
                            )
                        }
                        DropdownMenu(
                            expanded = showMenu,
                            onDismissRequest = { showMenu = false },
                        ) {
                            DropdownMenuItem(
                                text = {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Filled.Settings, contentDescription = null, modifier = Modifier.padding(end = 8.dp))
                                        Text("Configurações")
                                    }
                                },
                                onClick = {
                                    showMenu = false
                                    showConfig = true
                                },
                            )
                            DropdownMenuItem(
                                text = {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Filled.Logout, contentDescription = null, modifier = Modifier.padding(end = 8.dp))
                                        Text("Sair")
                                    }
                                },
                                onClick = {
                                    showMenu = false
                                    scope.launch {
                                        GpsForegroundService.stop(appContext)
                                        GpsPendingSyncWorker.cancel(appContext)
                                        authRepository.logout()
                                        onLogout()
                                    }
                                },
                            )
                        }
                    }
                }
                )
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
                        icon = { Icon(icon, contentDescription = label) },
                        label = { Text(label) },
                        selected = selectedTab == index,
                        onClick = { selectedTab = index },
                        alwaysShowLabel = false,
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
                    2 -> AbastecimentosScreen(
                        authRepository = authRepository,
                        modifier = Modifier.fillMaxSize(),
                        onSubScreenChange = { title, onBack ->
                            subScreenTitle = title
                            subScreenOnBack = onBack
                        },
                    )
                    3 -> ChecklistsScreen(
                        authRepository = authRepository,
                        modifier = Modifier.fillMaxSize(),
                        onSubScreenChange = { title, onBack ->
                            subScreenTitle = title
                            subScreenOnBack = onBack
                        },
                    )
                    4 -> DespesasScreen(
                        authRepository = authRepository,
                        modifier = Modifier.fillMaxSize(),
                        onSubScreenChange = { title, onBack ->
                            subScreenTitle = title
                            subScreenOnBack = onBack
                        },
                    )
                }
            }
            Column(Modifier.fillMaxWidth().align(Alignment.TopCenter)) {
                ConnectivityStatusBanner()
            }
        }
    }
    }
}
