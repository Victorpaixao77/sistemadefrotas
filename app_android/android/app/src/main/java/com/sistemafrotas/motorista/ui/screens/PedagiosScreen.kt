package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
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
import androidx.compose.material.icons.filled.Toll
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
import com.sistemafrotas.motorista.ui.viewmodel.PedagiosViewModel
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

private fun formatBRL(valor: String?): String {
    val v = valor?.toDoubleOrNull() ?: return "R$ 0,00"
    return "R$ " + String.format(Locale.US, "%,.2f", v).replace(",", "X").replace(".", ",").replace("X", ".")
}

private fun formatBRL(valor: Double): String =
    "R$ " + String.format(Locale.US, "%,.2f", valor).replace(",", "X").replace(".", ",").replace("X", ".")

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PedagiosScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
) {
    val vm: PedagiosViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { PedagiosViewModel(it) }
    )
    val list by vm.list.collectAsStateWithLifecycle()
    val total30d by vm.total30d.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val error by vm.error.collectAsStateWithLifecycle()
    var showForm by remember { mutableStateOf(false) }
    var ocrPrefill by remember { mutableStateOf<com.sistemafrotas.motorista.data.api.OcrCampos?>(null) }

    LaunchedEffect(Unit) {
        val (_, campos) = com.sistemafrotas.motorista.feature.viagem.TripFeatureBridge.consumeOcr()
        if (campos != null) {
            ocrPrefill = campos
            showForm = true
        }
    }

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    if (showForm) {
        LaunchedEffect(Unit) { onSubScreenChange("Novo pedágio") { showForm = false } }
        NovoPedagioScreen(
            authRepository = authRepository,
            onBack = { showForm = false; ocrPrefill = null },
            onSuccess = { showForm = false; ocrPrefill = null; vm.refresh() },
            ocrPrefill = ocrPrefill,
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
            ) { Icon(Icons.Filled.Add, contentDescription = "Novo pedágio") }
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
                        Icon(Icons.Filled.Toll, contentDescription = null, tint = MaterialTheme.colorScheme.outline)
                        Spacer(Modifier.height(8.dp))
                        Text("Nenhum pedágio registrado.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("Toque em + para registrar.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                else -> {
                    LazyColumn(
                        Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        item {
                            Card(
                                Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
                            ) {
                                Column(Modifier.padding(16.dp)) {
                                    Text("Pedágios — últimos 30 dias", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onPrimaryContainer)
                                    Spacer(Modifier.height(2.dp))
                                    Text(formatBRL(total30d), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onPrimaryContainer)
                                }
                            }
                            Spacer(Modifier.height(4.dp))
                        }
                        items(list, key = { it.id }) { p ->
                            Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
                                Row(
                                    Modifier.fillMaxWidth().padding(14.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Icon(Icons.Filled.Toll, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                    Spacer(Modifier.width(12.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text(p.praca?.ifBlank { "Pedágio" } ?: "Pedágio", fontWeight = FontWeight.SemiBold)
                                        Text(
                                            "${p.dataHora ?: ""}${p.placa?.let { " • $it" } ?: ""}",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                    Text(formatBRL(p.valor), fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
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
private fun NovoPedagioScreen(
    authRepository: AuthRepository,
    onBack: () -> Unit,
    onSuccess: () -> Unit,
    ocrPrefill: com.sistemafrotas.motorista.data.api.OcrCampos? = null,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var valor by remember { mutableStateOf("") }
    var praca by remember { mutableStateOf("") }
    var fotoUri by remember { mutableStateOf<Uri?>(null) }
    var gpsLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()?.let { gpsLocation = it }
    }

    LaunchedEffect(ocrPrefill) {
        val c = ocrPrefill ?: return@LaunchedEffect
        c.valor?.let { if (it > 0) valor = it.toString() }
        c.praca?.let { if (it.isNotBlank()) praca = it }
    }

    Column(
        modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        OcrLeitorButton(
            authRepository = authRepository,
            tipo = "pedagio",
            titulo = "Ler comprovante com IA",
            descricao = "Tire a foto do recibo e preenchemos o valor e a praça.",
            onResultado = { c ->
                c.valor?.let { if (it > 0) valor = it.toString() }
                c.praca?.let { if (it.isNotBlank() && praca.isBlank()) praca = it }
            },
        )

        OutlinedTextField(
            value = valor,
            onValueChange = { novo -> valor = novo.replace(',', '.').filter { it.isDigit() || it == '.' } },
            label = { Text("Valor do pedágio (R$)") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )

        OutlinedTextField(
            value = praca,
            onValueChange = { praca = it },
            label = { Text("Praça / local (opcional)") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )

        FotoPicker(label = "Foto do comprovante", uri = fotoUri, onUri = { fotoUri = it }, modifier = Modifier.fillMaxWidth())

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
                    val v = valor.toDoubleOrNull()
                    if (v == null || v <= 0) {
                        error = "Informe um valor válido."
                        return@Button
                    }
                    loading = true
                    error = null
                    val dataHora = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
                    val body = mapOf(
                        "valor" to v,
                        "praca" to praca.ifBlank { null },
                        "data_hora" to dataHora,
                        "latitude" to gpsLocation?.first,
                        "longitude" to gpsLocation?.second,
                    )
                    scope.launch {
                        authRepository.criarPedagio(body, context = context, fotoUri = fotoUri)
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
