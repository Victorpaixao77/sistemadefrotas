package com.sistemafrotas.motorista.feature.viagem.ui

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.Psychology
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material.icons.filled.Toll
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import android.content.Intent
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.ViewModelStore
import androidx.lifecycle.ViewModelStoreOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.LocalViewModelStoreOwner
import androidx.lifecycle.viewmodel.compose.viewModel
import android.app.Application
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.feature.viagem.domain.TripEvent
import com.sistemafrotas.motorista.feature.viagem.domain.TripPhase
import com.sistemafrotas.motorista.feature.viagem.viewmodel.TripModeUiState
import com.sistemafrotas.motorista.feature.viagem.viewmodel.TripModeViewModel
import com.sistemafrotas.motorista.feature.viagem.viewmodel.TripModeViewModelFactory
import com.sistemafrotas.motorista.feature.viagem.ui.TripReportPdf

private val Navy = Color(0xFF0F172A)
private val GreenLive = Color(0xFF16A34A)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TripModeScreen(
    authRepository: AuthRepository,
    onClose: () -> Unit,
    onOpenFeature: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    // ViewModel escopado ao overlay: ao fechar o Modo Viagem, jobs (relógio, GPS prompts) param.
    val viewModelStore = remember { ViewModelStore() }
    val overlayOwner = remember(viewModelStore) {
        object : ViewModelStoreOwner {
            override val viewModelStore: ViewModelStore get() = viewModelStore
        }
    }
    DisposableEffect(Unit) {
        onDispose {
            // Não chamar viewModelStore.clear() aqui: durante encerrar/fechar o relatório
            // coroutines ainda podem estar atualizando o ViewModel e isso derrubava o app.
        }
    }
    CompositionLocalProvider(LocalViewModelStoreOwner provides overlayOwner) {
        TripModeScreenContent(
            authRepository = authRepository,
            onClose = onClose,
            onOpenFeature = onOpenFeature,
            modifier = modifier,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TripModeScreenContent(
    authRepository: AuthRepository,
    onClose: () -> Unit,
    onOpenFeature: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    val app = LocalContext.current.applicationContext as Application
    val vm: TripModeViewModel = viewModel(factory = TripModeViewModelFactory(app, authRepository))
    val ui by vm.ui.collectAsStateWithLifecycle()
    val pendingFluxo by vm.pendingFluxo.collectAsStateWithLifecycle()
    var showEndConfirm by remember { mutableStateOf(false) }

    val smartPhoto = rememberTripSmartPhoto(authRepository, onOpenFeature)
    val tripVoice = rememberTripVoice(onOpenFeature)

    smartPhoto.error?.let { msg ->
        AlertDialog(
            onDismissRequest = { smartPhoto.dismissError() },
            title = { Text("Leitura do documento") },
            text = { Text(msg) },
            confirmButton = { TextButton(onClick = { smartPhoto.dismissError() }) { Text("OK") } },
        )
    }
    tripVoice.error?.let { msg ->
        AlertDialog(
            onDismissRequest = { tripVoice.dismissError() },
            title = { Text("Voz") },
            text = { Text(msg) },
            confirmButton = { TextButton(onClick = { tripVoice.dismissError() }) { Text("OK") } },
        )
    }
    if (smartPhoto.processing || tripVoice.listening) {
        AlertDialog(
            onDismissRequest = {},
            title = {
                Text(if (smartPhoto.processing) "Lendo documento…" else "Ouvindo…")
            },
            text = {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CircularProgressIndicator(Modifier.size(28.dp))
                    Spacer(Modifier.width(16.dp))
                    Text(
                        if (smartPhoto.processing) "IA identificando o tipo e extraindo dados."
                        else "Fale a ocorrência com clareza.",
                    )
                }
            },
            confirmButton = {},
        )
    }
    if (showEndConfirm) {
        AlertDialog(
            onDismissRequest = { showEndConfirm = false },
            title = { Text("Encerrar viagem?") },
            text = { Text("O relatório será exibido e a linha do tempo será salva.") },
            confirmButton = {
                TextButton(onClick = {
                    showEndConfirm = false
                    vm.encerrarViagem()
                }) { Text("Encerrar", color = MaterialTheme.colorScheme.error) }
            },
            dismissButton = {
                TextButton(onClick = { showEndConfirm = false }) { Text("Continuar") }
            },
        )
    }

    BackHandler {
        if (ui.showReport) {
            vm.fecharRelatorio(onDone = onClose)
        } else {
            onClose()
        }
    }

    LaunchedEffect(pendingFluxo) {
        val fluxo = pendingFluxo ?: return@LaunchedEffect
        onOpenFeature(fluxo)
        vm.consumeFluxo()
    }

    ui.prompt?.let { prompt ->
        AlertDialog(
            onDismissRequest = { vm.dismissPrompt() },
            title = { Text(prompt.titulo) },
            text = { Text(prompt.mensagem) },
            confirmButton = {
                TextButton(onClick = { vm.confirmPromptAndOpen() }) {
                    Text(prompt.acaoConfirmar)
                }
            },
            dismissButton = {
                TextButton(onClick = { vm.dismissPrompt() }) {
                    Text(prompt.acaoNegar)
                }
            },
        )
    }

    if (ui.showReport) {
        TripReportDialog(ui, vm, onClose = { vm.fecharRelatorio(onDone = onClose) })
    }

    if (ui.showAiSheet) {
        TripAiSheet(
            onDismiss = { vm.toggleAiSheet(false) },
            onAsk = { pergunta, onResult -> vm.responderAssistente(pergunta, onResult) },
        )
    }

    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text("Modo Viagem") },
                navigationIcon = {
                    IconButton(onClick = onClose) {
                        Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Navy,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White,
                ),
            )
        },
        floatingActionButton = {
            if (ui.session?.phase == TripPhase.EM_VIAGEM) {
                FloatingActionButton(
                    onClick = { vm.toggleAiSheet(true) },
                    containerColor = MaterialTheme.colorScheme.primary,
                ) {
                    Icon(Icons.Filled.Psychology, contentDescription = "Assistente IA")
                }
            }
        },
    ) { padding ->
        Box(
            Modifier
                .fillMaxSize()
                .padding(padding)
                .background(MaterialTheme.colorScheme.background),
        ) {
            when {
                ui.loading && ui.session == null -> {
                    CircularProgressIndicator(Modifier.align(Alignment.Center))
                }
                ui.session?.phase == TripPhase.EM_VIAGEM -> {
                    TripActivePanel(
                        ui = ui,
                        vm = vm,
                        onOpenFeature = onOpenFeature,
                        onSmartPhoto = { smartPhoto.launchCamera() },
                        onVoice = { tripVoice.launch() },
                        onEncerrar = { showEndConfirm = true },
                    )
                }
                ui.showReport -> {
                    // Placeholder estável sob o diálogo do relatório (evita crash ao desmontar LazyColumn).
                    TripEndedPlaceholder(ui)
                }
                else -> {
                    TripStartPanel(ui, onIniciar = { vm.iniciarViagem() })
                }
            }
        }
    }
}

@Composable
private fun TripEndedPlaceholder(ui: TripModeUiState) {
    val s = ui.session
    Column(
        Modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text("✓", fontSize = 48.sp, color = GreenLive)
        Spacer(Modifier.height(12.dp))
        Text(
            "Viagem encerrada",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.height(8.dp))
        Text(
            "${s?.origem ?: "—"} → ${s?.destino ?: "—"}",
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
private fun TripStartPanel(ui: TripModeUiState, onIniciar: () -> Unit) {
    val rota = ui.proximaRota
    Column(
        Modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(32.dp))
        Text("🚛", fontSize = 56.sp)
        Spacer(Modifier.height(12.dp))
        Text(
            "Modo Viagem",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
        )
        Text(
            "Seu copiloto digital",
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.height(32.dp))
        Card(
            Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        ) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Sua próxima viagem", fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.primary)
                InfoRow("Origem", rota?.cidadeOrigemNome ?: "—")
                InfoRow("Destino", rota?.cidadeDestinoNome ?: "—")
                InfoRow("Veículo", rota?.placa ?: "—")
                InfoRow("Motorista", ui.motoristaNome ?: "—")
            }
        }
        Spacer(Modifier.weight(1f))
        Button(
            onClick = onIniciar,
            enabled = rota != null && !ui.loading && !ui.starting,
            modifier = Modifier
                .fillMaxWidth()
                .height(60.dp),
            shape = RoundedCornerShape(16.dp),
        ) {
            if (ui.starting) {
                CircularProgressIndicator(
                    modifier = Modifier.size(24.dp),
                    color = MaterialTheme.colorScheme.onPrimary,
                    strokeWidth = 2.dp,
                )
            } else {
                Text("INICIAR VIAGEM", fontSize = 18.sp, fontWeight = FontWeight.Bold)
            }
        }
        ui.error?.let { msg ->
            Spacer(Modifier.height(8.dp))
            Text(
                msg,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.error,
                textAlign = TextAlign.Center,
            )
        }
        if (rota == null) {
            Spacer(Modifier.height(8.dp))
            Text(
                "Nenhuma rota encontrada para hoje.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.error,
                textAlign = TextAlign.Center,
            )
        }
    }
}

@Composable
private fun TripActivePanel(
    ui: TripModeUiState,
    vm: TripModeViewModel,
    onOpenFeature: (String) -> Unit,
    onSmartPhoto: () -> Unit,
    onVoice: () -> Unit,
    onEncerrar: () -> Unit,
) {
    LazyColumn(
        Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        item {
            Card(
                Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = Navy),
            ) {
                Column(Modifier.padding(20.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(10.dp).background(GreenLive, CircleShape))
                        Spacer(Modifier.width(8.dp))
                        Text("Viagem em andamento", color = Color.White.copy(alpha = 0.9f))
                    }
                    Spacer(Modifier.height(8.dp))
                    Text(
                        ui.elapsedLabel,
                        fontSize = 42.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                    )
                    ui.session?.let { s ->
                        Text(
                            "${s.origem ?: "?"} → ${s.destino ?: "?"}",
                            color = Color.White.copy(alpha = 0.85f),
                            style = MaterialTheme.typography.bodyMedium,
                        )
                    }
                }
            }
        }

        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                StatChip("Dirigindo", "${ui.jornadaDirigindoMin} min", Modifier.weight(1f))
                StatChip("Parado", "${ui.jornadaParadoMin} min", Modifier.weight(1f))
                StatChip("Abast.", "${ui.abastecimentosCount}", Modifier.weight(1f))
            }
        }

        item {
            Text("Ações rápidas", fontWeight = FontWeight.SemiBold)
        }

        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                TripActionBtn(Icons.Filled.CameraAlt, "Foto\nOCR", Modifier.weight(1f), onSmartPhoto)
                TripActionBtn(Icons.Filled.Mic, "Voz\nOcorrência", Modifier.weight(1f), onVoice)
                TripActionBtn(Icons.Filled.LocalGasStation, "Abast.", Modifier.weight(1f)) {
                    onOpenFeature("abastecimentos")
                }
            }
        }

        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                TripActionBtn(Icons.Filled.Toll, "Pedágio", Modifier.weight(1f)) {
                    onOpenFeature("pedagios")
                }
                TripActionBtn(Icons.Filled.CheckCircle, "Entrega", Modifier.weight(1f)) {
                    onOpenFeature("pod")
                }
                TripActionBtn(Icons.Filled.Warning, "Ocorr.", Modifier.weight(1f)) {
                    onOpenFeature("ocorrencias")
                }
            }
        }

        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedButton(
                    onClick = { onOpenFeature("checklists") },
                    modifier = Modifier.weight(1f).height(48.dp),
                ) { Text("Checklist") }
                OutlinedButton(
                    onClick = { onOpenFeature("conferencia") },
                    modifier = Modifier.weight(1f).height(48.dp),
                ) { Text("Conferir carga") }
            }
        }

        item {
            Text("Linha do tempo", fontWeight = FontWeight.SemiBold)
        }

        items(ui.events, key = { it.id }) { ev ->
            TimelineRow(ev, vm)
        }

        item {
            OutlinedButton(
                onClick = onEncerrar,
                modifier = Modifier.fillMaxWidth().height(52.dp),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
            ) {
                Icon(Icons.Filled.Stop, null, Modifier.size(20.dp))
                Spacer(Modifier.width(8.dp))
                Text("Encerrar viagem", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, fontWeight = FontWeight.Medium)
    }
}

@Composable
private fun StatChip(label: String, value: String, modifier: Modifier = Modifier) {
    Card(
        modifier,
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)),
    ) {
        Column(Modifier.padding(12.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text(value, fontWeight = FontWeight.Bold, fontSize = 16.sp)
            Text(label, style = MaterialTheme.typography.labelSmall)
        }
    }
}

@Composable
private fun TripActionBtn(
    icon: ImageVector,
    label: String,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Card(
        modifier = modifier.height(88.dp).clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
    ) {
        Column(
            Modifier.fillMaxSize().padding(8.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Icon(icon, null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.height(4.dp))
            Text(label, textAlign = TextAlign.Center, style = MaterialTheme.typography.labelSmall)
        }
    }
}

@Composable
private fun TimelineRow(ev: TripEvent, vm: TripModeViewModel) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        Text(
            vm.formatTime(ev.timestampMs),
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier.width(48.dp),
        )
        Column {
            Text(ev.titulo, fontWeight = FontWeight.Medium)
            ev.detalhe?.let {
                Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TripAiSheet(onDismiss: () -> Unit, onAsk: (String, (String) -> Unit) -> Unit) {
    var pergunta by remember { mutableStateOf("") }
    var resposta by remember { mutableStateOf<String?>(null) }
    var loading by remember { mutableStateOf(false) }
    val sugestoes = listOf(
        "Quanto falta de km?",
        "Quanto já gastei?",
        "Tenho abastecimento pendente?",
        "Quanto tempo falta para descanso?",
    )

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(Modifier.padding(20.dp).padding(bottom = 32.dp)) {
            Text("Assistente IA", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text("Dados reais da viagem atual", style = MaterialTheme.typography.bodySmall)
            Spacer(Modifier.height(16.dp))
            sugestoes.forEach { s ->
                SuggestionChip(
                    onClick = {
                        pergunta = s
                        loading = true
                        resposta = null
                        onAsk(s) { r -> resposta = r; loading = false }
                    },
                    label = { Text(s) },
                    modifier = Modifier.padding(bottom = 6.dp),
                )
            }
            Spacer(Modifier.height(12.dp))
            OutlinedTextField(
                value = pergunta,
                onValueChange = { pergunta = it },
                label = { Text("Sua pergunta") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            Button(
                onClick = {
                    loading = true
                    resposta = null
                    onAsk(pergunta) { r -> resposta = r; loading = false }
                },
                modifier = Modifier.fillMaxWidth(),
                enabled = !loading && pergunta.isNotBlank(),
            ) { Text(if (loading) "Consultando…" else "Perguntar") }
            resposta?.let {
                Spacer(Modifier.height(12.dp))
                Card(Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.secondaryContainer)) {
                    Text(it, Modifier.padding(16.dp))
                }
            }
        }
    }
}

@Composable
private fun TripReportDialog(
    ui: TripModeUiState,
    vm: TripModeViewModel,
    onClose: () -> Unit,
) {
    val s = ui.session
    val context = LocalContext.current
    AlertDialog(
        onDismissRequest = onClose,
        title = { Text("Relatório da viagem") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("Origem: ${s?.origem ?: "—"}")
                Text("Destino: ${s?.destino ?: "—"}")
                Text("Placa: ${s?.placa ?: "—"}")
                Text("Duração: ${ui.elapsedLabel}")
                Text("Abastecimentos: ${ui.abastecimentosCount}")
                Text("Eventos: ${ui.events.size}")
                Spacer(Modifier.height(8.dp))
                Text("Linha do tempo:", fontWeight = FontWeight.SemiBold)
                ui.events.take(8).forEach { ev ->
                    Text("• ${vm.formatTime(ev.timestampMs)} — ${ev.titulo}", style = MaterialTheme.typography.bodySmall)
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onClose) { Text("Concluir") }
        },
        dismissButton = {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                TextButton(onClick = {
                    TripReportPdf.sharePdf(context, ui, vm)
                }) { Text("PDF") }
                TextButton(onClick = {
                    val intent = Intent(Intent.ACTION_SEND).apply {
                        type = "text/plain"
                        putExtra(Intent.EXTRA_SUBJECT, "Relatório de viagem")
                        putExtra(Intent.EXTRA_TEXT, vm.buildReportText(ui))
                    }
                    context.startActivity(Intent.createChooser(intent, "Compartilhar relatório"))
                }) { Text("Compartilhar") }
            }
        },
    )
}
