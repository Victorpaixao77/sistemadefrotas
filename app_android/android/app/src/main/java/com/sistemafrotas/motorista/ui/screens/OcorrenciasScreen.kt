package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.LocationHelper
import com.sistemafrotas.motorista.feature.viagem.TripFeatureBridge
import com.sistemafrotas.motorista.ui.components.FotoPicker
import com.sistemafrotas.motorista.ui.components.rememberSignatureUri
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.OcorrenciasViewModel
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

private val TIPOS = listOf(
    "acidente" to "Acidente",
    "multa" to "Multa",
    "roubo" to "Roubo",
    "tentativa_roubo" to "Tentativa de roubo",
    "mecanico" to "Problema mecânico",
    "carga" to "Problema com carga",
    "bloqueio" to "Bloqueio de estrada",
    "outro" to "Outro",
)

private fun tipoLabel(t: String?): String = TIPOS.firstOrNull { it.first == t }?.second ?: (t ?: "—")

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OcorrenciasScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
) {
    val vm: OcorrenciasViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { OcorrenciasViewModel(it) }
    )
    val list by vm.list.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val error by vm.error.collectAsStateWithLifecycle()
    var showForm by remember { mutableStateOf(false) }
    var voicePrefill by remember { mutableStateOf<Pair<String?, String?>?>(null) }

    LaunchedEffect(Unit) {
        val (desc, tipo) = TripFeatureBridge.consumeOcorrencia()
        if (!desc.isNullOrBlank() || !tipo.isNullOrBlank()) {
            voicePrefill = desc to tipo
            showForm = true
        }
    }

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    if (showForm) {
        LaunchedEffect(Unit) { onSubScreenChange("Nova ocorrência") { showForm = false } }
        NovaOcorrenciaScreen(
            authRepository = authRepository,
            onBack = { showForm = false; voicePrefill = null },
            onSuccess = { showForm = false; voicePrefill = null; vm.refresh() },
            initialDescricao = voicePrefill?.first,
            initialTipo = voicePrefill?.second,
            modifier = modifier,
        )
        return
    }
    LaunchedEffect(Unit) { onSubScreenChange(null, null) }

    Scaffold(
        modifier = modifier,
        floatingActionButton = {
            FloatingActionButton(
                onClick = { showForm = true },
                shape = CircleShape,
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = MaterialTheme.colorScheme.onPrimary,
            ) { Icon(Icons.Filled.Add, contentDescription = "Nova ocorrência") }
        },
    ) { pad ->
        Box(Modifier.fillMaxSize().padding(pad)) {
            when {
                error != null && list.isEmpty() -> {
                    Column(
                        Modifier.fillMaxSize().padding(16.dp),
                        verticalArrangement = Arrangement.Center,
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Text(error ?: "", color = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.height(8.dp))
                        Button(onClick = { vm.setError(null); vm.refresh() }) { Text("Tentar novamente") }
                    }
                }
                list.isEmpty() && !refreshing -> {
                    Column(
                        Modifier.fillMaxSize().padding(24.dp),
                        verticalArrangement = Arrangement.Center,
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Icon(Icons.Filled.Warning, contentDescription = null, tint = MaterialTheme.colorScheme.outline)
                        Spacer(Modifier.height(8.dp))
                        Text("Nenhuma ocorrência registrada.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("Toque em + para registrar.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                else -> {
                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        items(list, key = { it.id }) { o ->
                            Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
                                Column(Modifier.padding(14.dp)) {
                                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                        Text(tipoLabel(o.tipo), fontWeight = FontWeight.SemiBold)
                                        AssistChip(onClick = { }, label = { Text(o.status ?: "aberta") })
                                    }
                                    if (!o.descricao.isNullOrBlank()) {
                                        Spacer(Modifier.height(4.dp))
                                        Text(o.descricao!!, style = MaterialTheme.typography.bodySmall)
                                    }
                                    Spacer(Modifier.height(4.dp))
                                    Text(
                                        "${o.dataHora ?: ""}${o.placa?.let { " • $it" } ?: ""}",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NovaOcorrenciaScreen(
    authRepository: AuthRepository,
    onBack: () -> Unit,
    onSuccess: () -> Unit,
    initialDescricao: String? = null,
    initialTipo: String? = null,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var tipo by remember { mutableStateOf<String?>(initialTipo) }
    var descricao by remember { mutableStateOf(initialDescricao.orEmpty()) }
    var fotoUri by remember { mutableStateOf<Uri?>(null) }
    var videoUri by remember { mutableStateOf<Uri?>(null) }
    val (assinaturaUri, assinaturaUi) = rememberSignatureUri()
    val videoLauncher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        videoUri = uri
    }
    var gpsLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()?.let { gpsLocation = it }
    }

    Column(
        modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Tipo da ocorrência", style = MaterialTheme.typography.labelLarge)
        FlowRowTipos(selected = tipo, onSelect = { tipo = it })

        OutlinedTextField(
            value = descricao,
            onValueChange = { descricao = it },
            label = { Text("Descrição (opcional)") },
            minLines = 3,
            modifier = Modifier.fillMaxWidth(),
        )

        FotoPicker(label = "Foto da ocorrência", uri = fotoUri, onUri = { fotoUri = it }, modifier = Modifier.fillMaxWidth())

        OutlinedButton(onClick = { videoLauncher.launch("video/*") }, modifier = Modifier.fillMaxWidth()) {
            Text(if (videoUri != null) "Vídeo selecionado ✓" else "Anexar vídeo (opcional)")
        }

        assinaturaUi()

        Text(
            gpsLocation?.let { "Localização capturada: %.5f, %.5f".format(Locale.US, it.first, it.second) }
                ?: "Localização não disponível (verifique a permissão de GPS).",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        if (error != null) {
            Text(error!!, color = MaterialTheme.colorScheme.error)
        }

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(onClick = onBack, modifier = Modifier.weight(1f), enabled = !loading) { Text("Cancelar") }
            Button(
                onClick = {
                    if (tipo == null) {
                        error = "Selecione o tipo da ocorrência."
                        return@Button
                    }
                    loading = true
                    error = null
                    val dataHora = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
                    val body = mapOf(
                        "tipo" to tipo,
                        "descricao" to descricao.ifBlank { null },
                        "data_hora" to dataHora,
                        "latitude" to gpsLocation?.first,
                        "longitude" to gpsLocation?.second,
                    )
                    scope.launch {
                        authRepository.criarOcorrencia(
                            body,
                            context = context,
                            fotoUri = fotoUri,
                            videoUri = videoUri,
                            assinaturaUri = assinaturaUri(),
                        )
                            .onSuccess { onSuccess() }
                            .onFailure { err -> error = err.message; loading = false }
                    }
                },
                modifier = Modifier.weight(1f).height(54.dp),
                enabled = !loading,
            ) {
                if (loading) CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                else Text("Registrar", style = MaterialTheme.typography.titleMedium)
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class, ExperimentalMaterial3Api::class)
@Composable
private fun FlowRowTipos(selected: String?, onSelect: (String) -> Unit) {
    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        TIPOS.forEach { (value, label) ->
            FilterChip(
                selected = selected == value,
                onClick = { onSelect(value) },
                label = { Text(label) },
            )
        }
    }
}
