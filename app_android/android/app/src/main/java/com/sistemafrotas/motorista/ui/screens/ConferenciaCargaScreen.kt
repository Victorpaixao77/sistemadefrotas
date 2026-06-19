package com.sistemafrotas.motorista.ui.screens

import android.net.Uri
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.LocationHelper
import com.sistemafrotas.motorista.ui.components.FotoPicker
import com.sistemafrotas.motorista.ui.components.OcrLeitorButton
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.ConferenciaViewModel
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ConferenciaCargaScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
) {
    val vm: ConferenciaViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { ConferenciaViewModel(it) }
    )
    val list by vm.list.collectAsState()
    val refreshing by vm.refreshing.collectAsState()
    val error by vm.error.collectAsState()
    var showForm by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    if (showForm) {
        LaunchedEffect(Unit) { onSubScreenChange("Nova conferência") { showForm = false } }
        NovaConferenciaScreen(
            authRepository = authRepository,
            onBack = { showForm = false },
            onSuccess = { showForm = false; vm.refresh() },
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
            ) { Icon(Icons.Filled.Add, contentDescription = "Nova conferência") }
        },
    ) { pad ->
        Box(Modifier.fillMaxSize().padding(pad)) {
            when {
                error != null && list.isEmpty() -> Column(
                    Modifier.fillMaxSize().padding(16.dp),
                    verticalArrangement = Arrangement.Center,
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(error ?: "", color = MaterialTheme.colorScheme.error)
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = { vm.setError(null); vm.refresh() }) { Text("Tentar novamente") }
                }
                list.isEmpty() && !refreshing -> Column(
                    Modifier.fillMaxSize().padding(24.dp),
                    verticalArrangement = Arrangement.Center,
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Icon(Icons.Filled.LocalShipping, contentDescription = null, tint = MaterialTheme.colorScheme.outline)
                    Spacer(Modifier.height(8.dp))
                    Text("Nenhuma conferência registrada.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text("Toque em + para conferir uma carga.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                else -> LazyColumn(
                    Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(list) { c ->
                        Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
                            Column(Modifier.padding(14.dp)) {
                                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                    Text(
                                        (if (c.tipo == "entrega") "Entrega" else "Coleta") + (c.nfNumero?.let { " · NF $it" } ?: ""),
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                    AssistChip(
                                        onClick = { },
                                        label = { Text(if (c.conforme == 1) "Conforme" else "Divergência") },
                                    )
                                }
                                Spacer(Modifier.height(4.dp))
                                Text(
                                    listOfNotNull(
                                        c.volumes?.let { "$it vol." },
                                        c.lacre?.let { "lacre $it" },
                                        c.placa,
                                        c.dataHora,
                                    ).joinToString(" • "),
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NovaConferenciaScreen(
    authRepository: AuthRepository,
    onBack: () -> Unit,
    onSuccess: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var tipo by remember { mutableStateOf("coleta") }
    var nfNumero by remember { mutableStateOf("") }
    var nfValor by remember { mutableStateOf("") }
    var volumes by remember { mutableStateOf("") }
    var lacre by remember { mutableStateOf("") }
    var conforme by remember { mutableStateOf(true) }
    var observacoes by remember { mutableStateOf("") }
    var fotoUri by remember { mutableStateOf<Uri?>(null) }
    var gpsLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()?.let { gpsLocation = it }
    }

    Column(
        modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        // Tipo coleta/entrega.
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(selected = tipo == "coleta", onClick = { tipo = "coleta" }, label = { Text("Coleta") })
            FilterChip(selected = tipo == "entrega", onClick = { tipo = "entrega" }, label = { Text("Entrega") })
        }

        OcrLeitorButton(
            authRepository = authRepository,
            tipo = "nota",
            titulo = "Ler nota fiscal com IA",
            descricao = "Tire a foto da NF e preenchemos número, valor e volumes.",
            onResultado = { c ->
                c.nfNumero?.let { if (it.isNotBlank()) nfNumero = it }
                c.nfValor?.let { if (it > 0) nfValor = it.toString() }
                c.volumes?.let { if (it > 0) volumes = it.toString() }
            },
            onImagem = { uri -> fotoUri = uri },
        )

        OcrLeitorButton(
            authRepository = authRepository,
            tipo = "lacre",
            titulo = "Ler lacre com IA",
            descricao = "Tire a foto do lacre e preenchemos o código.",
            onResultado = { c -> c.lacre?.let { if (it.isNotBlank()) lacre = it } },
        )

        OutlinedTextField(value = nfNumero, onValueChange = { nfNumero = it }, label = { Text("Número da NF") }, singleLine = true, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(
            value = nfValor,
            onValueChange = { v -> nfValor = v.replace(',', '.').filter { it.isDigit() || it == '.' } },
            label = { Text("Valor da NF (R$)") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )
        OutlinedTextField(
            value = volumes,
            onValueChange = { v -> volumes = v.filter { it.isDigit() } },
            label = { Text("Volumes") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )
        OutlinedTextField(value = lacre, onValueChange = { lacre = it }, label = { Text("Lacre") }, singleLine = true, modifier = Modifier.fillMaxWidth())

        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = if (conforme) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline)
            Spacer(Modifier.width(8.dp))
            Text("Carga conforme", Modifier.weight(1f))
            Switch(checked = conforme, onCheckedChange = { conforme = it })
        }

        OutlinedTextField(value = observacoes, onValueChange = { observacoes = it }, label = { Text("Observações (divergências, avarias…)") }, minLines = 2, modifier = Modifier.fillMaxWidth())

        FotoPicker(label = "Foto da carga / NF", uri = fotoUri, onUri = { fotoUri = it }, modifier = Modifier.fillMaxWidth())

        if (error != null) Text(error!!, color = MaterialTheme.colorScheme.error)

        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(onClick = onBack, modifier = Modifier.weight(1f), enabled = !loading) { Text("Cancelar") }
            Button(
                onClick = {
                    if (nfNumero.isBlank() && lacre.isBlank() && volumes.isBlank() && fotoUri == null) {
                        error = "Informe ao menos a NF, o lacre, os volumes ou uma foto."
                        return@Button
                    }
                    loading = true
                    error = null
                    val dataHora = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
                    val body = mapOf(
                        "tipo" to tipo,
                        "nf_numero" to nfNumero.ifBlank { null },
                        "nf_valor" to nfValor.toDoubleOrNull(),
                        "volumes" to volumes.toIntOrNull(),
                        "lacre" to lacre.ifBlank { null },
                        "conforme" to if (conforme) 1 else 0,
                        "observacoes" to observacoes.ifBlank { null },
                        "data_hora" to dataHora,
                        "latitude" to gpsLocation?.first,
                        "longitude" to gpsLocation?.second,
                    )
                    scope.launch {
                        authRepository.criarConferencia(body, context = context, fotoUri = fotoUri)
                            .onSuccess { onSuccess() }
                            .onFailure { e -> error = e.message; loading = false }
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
