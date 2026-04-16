package com.sistemafrotas.motorista.ui.screens

import android.net.Uri
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.FileProvider
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.LocationHelper
import com.sistemafrotas.motorista.data.api.AbastecimentoItem
import com.sistemafrotas.motorista.data.api.RotaItem
import com.sistemafrotas.motorista.data.api.VeiculoItem
import com.sistemafrotas.motorista.ui.components.DatePickerField
import com.sistemafrotas.motorista.ui.components.AbastecimentosListSkeleton
import coil.compose.AsyncImage
import java.io.File
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.coroutines.Dispatchers
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class, ExperimentalMaterialApi::class)
@Composable
fun AbastecimentosScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
) {
    var showForm by remember { mutableStateOf(false) }
    var list by remember { mutableStateOf<List<AbastecimentoItem>>(emptyList()) }
    var refreshing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun refresh() {
        scope.launch {
            refreshing = true
            error = null
            try {
                authRepository.loadAbastecimentos()
                    .onSuccess { list = it.abastecimentos ?: emptyList() }
                    .onFailure { error = it.message }
            } finally {
                refreshing = false
            }
        }
    }
    LaunchedEffect(Unit) { refresh() }
    val pullRefreshState = rememberPullRefreshState(refreshing, onRefresh = { refresh() })

    if (showForm) {
        LaunchedEffect(showForm) {
            onSubScreenChange("Novo Abastecimento") { showForm = false }
        }
        NovoAbastecimentoScreen(
            authRepository = authRepository,
            onBack = { showForm = false },
            onSuccess = { showForm = false; refresh() },
            showTopBar = false,
            modifier = modifier,
        )
        return
    }

    LaunchedEffect(showForm) {
        if (!showForm) onSubScreenChange(null, null)
    }

    Scaffold(
        modifier = modifier,
        floatingActionButton = {
            FloatingActionButton(onClick = { showForm = true }, shape = CircleShape, containerColor = MaterialTheme.colorScheme.primary, contentColor = MaterialTheme.colorScheme.onPrimary) {
                Icon(Icons.Filled.Add, contentDescription = "Novo abastecimento")
            }
        },
    ) { pad ->
        Box(Modifier.fillMaxSize().padding(pad).pullRefresh(pullRefreshState)) {
            if (refreshing && list.isEmpty()) {
                AbastecimentosListSkeleton(count = 5, modifier = Modifier.fillMaxSize().padding(16.dp))
            } else if (error != null) {
                Column(
                    Modifier.fillMaxSize().padding(16.dp),
                    verticalArrangement = Arrangement.Center,
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(error!!, color = MaterialTheme.colorScheme.error)
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = { error = null; refresh() }) { Text("Tentar novamente") }
                }
            } else if (list.isEmpty()) {
                Column(
                    Modifier.fillMaxSize().padding(24.dp),
                    verticalArrangement = Arrangement.Center,
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Icon(Icons.Filled.LocalGasStation, null, Modifier.size(52.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.55f))
                    Spacer(Modifier.height(12.dp))
                    Text("Nenhum abastecimento.", style = MaterialTheme.typography.titleMedium)
                    Text("Puxe para atualizar ou toque em + para registrar.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            } else {
                LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(list) { a ->
                        Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)) {
                            Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Filled.LocalGasStation, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                Spacer(Modifier.width(12.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(a.dataAbastecimento ?: "", style = MaterialTheme.typography.bodyLarge)
                                    Text("${a.litros ?: 0} L - R$ ${a.valorTotal ?: 0}", style = MaterialTheme.typography.bodyMedium)
                                    a.placa?.let { Text("Placa $it", style = MaterialTheme.typography.labelSmall) }
                                }
                                AssistChip(onClick = { }, label = { Text(a.status ?: "") })
                            }
                        }
                    }
                }
            }
            PullRefreshIndicator(refreshing, pullRefreshState, Modifier.align(Alignment.TopCenter))
        }
    }
}

private fun parseDouble(s: String): Double? {
    if (s.isBlank()) return null
    return s.replace(",", ".").toDoubleOrNull()
}

private fun formatDecimal(value: Double, decimals: Int = 2): String =
    "%.${decimals}f".format(Locale.US, value).replace(".", ",")

/** Converte dd/mm/yyyy ou yyyy-MM-dd para yyyy-MM-dd para a API. */
private fun normalizarDataApi(s: String): String {
    if (s.isBlank()) return s
    val t = s.trim()
    if (t.length == 10 && t[4] == '-' && t[7] == '-') return t
    val parts = t.split("/", "-")
    if (parts.size == 3) {
        val dia = parts[0].toIntOrNull() ?: return t
        val mes = parts[1].toIntOrNull() ?: return t
        val ano = parts[2].toIntOrNull() ?: return t
        val year = if (ano in 0..99) 2000 + ano else ano
        return "%04d-%02d-%02d".format(year, mes, dia)
    }
    return t
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NovoAbastecimentoScreen(authRepository: AuthRepository, onBack: () -> Unit, onSuccess: () -> Unit, showTopBar: Boolean = true, modifier: Modifier = Modifier) {
    var veiculos by remember { mutableStateOf<List<VeiculoItem>>(emptyList()) }
    var rotas by remember { mutableStateOf<List<RotaItem>>(emptyList()) }
    var veiculoId by remember { mutableStateOf<Int?>(null) }
    var rotaId by remember { mutableStateOf<Int?>(null) }
    var dataRota by remember { mutableStateOf(SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Calendar.getInstance().time)) }
    var dataAbast by remember { mutableStateOf(SimpleDateFormat("yyyy-MM-dd'T'HH:mm", Locale.US).format(Calendar.getInstance().time)) }
    var tipoCombustivel by remember { mutableStateOf("Diesel") }
    var posto by remember { mutableStateOf("") }
    var litros by remember { mutableStateOf("") }
    var valorLitro by remember { mutableStateOf("") }
    var valorTotal by remember { mutableStateOf("") }
    var kmAtual by remember { mutableStateOf("") }
    var formaPagamento by remember { mutableStateOf("Dinheiro") }
    var observacoes by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()
    var comprovanteUri by remember { mutableStateOf<Uri?>(null) }
    var cameraCaptureUri by remember { mutableStateOf<Uri?>(null) }
    val context = LocalContext.current
    val galleryLauncher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri -> comprovanteUri = uri }
    val takePictureLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (ok && cameraCaptureUri != null) comprovanteUri = cameraCaptureUri
    }
    var gpsLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }

    LaunchedEffect(Unit) {
        withContext(Dispatchers.IO) {
            LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()?.let { gpsLocation = it }
        }
    }

    // Cálculo automático: litros × valor/litro ⇄ valor total (igual ao web)
    // - Ao editar litros: se tem valor/litro → total = litros × valor/litro; se tem total → valor/litro = total ÷ litros
    // - Ao editar valor/litro: se tem litros → total = litros × valor/litro; se tem total → litros = total ÷ valor/litro
    // - Ao editar valor total: se tem litros → valor/litro = total ÷ litros; se tem valor/litro → litros = total ÷ valor/litro
    fun onLitrosChange(newLitros: String) {
        val filtered = newLitros.filter { c -> c.isDigit() || c == '.' || c == ',' }
        litros = filtered
        val l = parseDouble(filtered)
        val vl = parseDouble(valorLitro)
        val vt = parseDouble(valorTotal)
        when {
            l != null && vl != null && vl > 0 -> valorTotal = formatDecimal(l * vl)
            l != null && l > 0 && vt != null -> valorLitro = formatDecimal(vt / l)
        }
    }
    fun onValorLitroChange(newValorLitro: String) {
        val filtered = newValorLitro.filter { c -> c.isDigit() || c == '.' || c == ',' }
        valorLitro = filtered
        val l = parseDouble(litros)
        val vl = parseDouble(filtered)
        val vt = parseDouble(valorTotal)
        when {
            l != null && vl != null && l > 0 -> valorTotal = formatDecimal(l * vl)
            vl != null && vl > 0 && vt != null -> litros = formatDecimal(vt / vl)
        }
    }
    fun onValorTotalChange(newValorTotal: String) {
        val filtered = newValorTotal.filter { c -> c.isDigit() || c == '.' || c == ',' }
        valorTotal = filtered
        val l = parseDouble(litros)
        val vl = parseDouble(valorLitro)
        val vt = parseDouble(filtered)
        when {
            l != null && l > 0 && vt != null -> valorLitro = formatDecimal(vt / l)
            vl != null && vl > 0 && vt != null -> litros = formatDecimal(vt / vl)
        }
    }

    LaunchedEffect(Unit) { authRepository.loadVeiculos().onSuccess { veiculos = it.veiculos ?: emptyList() } }
    LaunchedEffect(dataRota) {
        if (dataRota.isNotBlank()) {
            val dataNorm = normalizarDataApi(dataRota)
            authRepository.loadRotas(dataInicio = dataNorm, dataFim = dataNorm)
                .onSuccess { resp ->
                    rotas = resp.rotas ?: emptyList()
                    rotaId = null
                    if (veiculoId != null && rotas.none { it.veiculoId == veiculoId }) veiculoId = null
                }
                .onFailure { rotas = emptyList(); rotaId = null }
        } else { rotas = emptyList(); rotaId = null }
    }

    BackHandler(onBack = onBack)

    Scaffold(
        modifier = modifier,
        topBar = if (showTopBar) {
            { TopAppBar(title = { Text("Novo Abastecimento") }, navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar") } }) }
        } else { {} },
        bottomBar = {
            Surface(
                modifier = Modifier.fillMaxWidth(),
                shadowElevation = 8.dp,
                color = MaterialTheme.colorScheme.surface,
                tonalElevation = 3.dp,
            ) {
                Button(
                    onClick = {
                        if (veiculoId == null || rotaId == null) {
                            error = "Selecione o veículo e a rota do dia."
                            return@Button
                        }
                        if (posto.isBlank()) {
                            error = "Informe o posto de combustível."
                            return@Button
                        }
                        if (parseDouble(litros) == null || parseDouble(valorTotal) == null || kmAtual.replace(",", ".").toDoubleOrNull() == null) {
                            error = "Preencha litros, valor total e quilometragem."
                            return@Button
                        }
                        loading = true
                        error = null
                        val litrosVal = parseDouble(litros)!!
                        val valorLitroVal = parseDouble(valorLitro) ?: (parseDouble(valorTotal)!! / litrosVal)
                        val valorTotalVal = parseDouble(valorTotal)!!
                        val dataAbastStr = if (dataAbast.contains("T")) dataAbast.replace("T", " ") + ":00" else normalizarDataApi(dataAbast) + " 00:00:00"
                        scope.launch {
                            authRepository.criarAbastecimento(
                                mapOf(
                                    "veiculo_id" to veiculoId,
                                    "rota_id" to rotaId,
                                    "data_abastecimento" to dataAbastStr,
                                    "posto" to posto,
                                    "litros" to litrosVal,
                                    "valor_litro" to valorLitroVal,
                                    "valor_total" to valorTotalVal,
                                    "km_atual" to kmAtual.replace(",", ".").toDouble(),
                                    "tipo_combustivel" to tipoCombustivel.lowercase(Locale.US),
                                    "forma_pagamento" to formaPagamento.lowercase(Locale.US),
                                    "observacoes" to observacoes.ifBlank { null },
                                    "latitude" to gpsLocation?.first,
                                    "longitude" to gpsLocation?.second,
                                ),
                                context = context,
                                comprovanteUri = comprovanteUri,
                            ).onSuccess { onSuccess() }.onFailure { err -> error = err.message; loading = false }
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp)
                        .heightIn(min = 48.dp),
                    enabled = !loading,
                ) {
                    if (loading) CircularProgressIndicator(Modifier.size(24.dp), color = MaterialTheme.colorScheme.onPrimary)
                    else Text("Registrar abastecimento")
                }
            }
        },
    ) { pad ->
        Column(
            Modifier
                .fillMaxSize()
                .padding(pad)
                .then(if (!showTopBar) Modifier.padding(top = 8.dp) else Modifier),
        ) {
            Column(
                Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp),
            ) {
            error?.let { Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(bottom = 8.dp)) }

            Text("Data da Rota *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            DatePickerField(
                value = dataRota,
                onDateSelected = { dataRota = it },
                label = "Data da rota",
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            Text("Data do Abastecimento *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            DatePickerField(
                value = dataAbast.take(10),
                onDateSelected = { dataAbast = it + "T" + SimpleDateFormat("HH:mm", Locale.US).format(Calendar.getInstance().time) },
                label = "Data do abastecimento",
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(12.dp))

            Text("Veículo *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            val veiculosDoDia = if (rotas.isEmpty()) veiculos else veiculos.filter { v -> rotas.any { it.veiculoId == v.id } }
            val rotasDoVeiculo = if (veiculoId == null) emptyList() else rotas.filter { it.veiculoId == veiculoId }
            var expV by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expV, onExpandedChange = { expV = it }) {
                OutlinedTextField(
                    value = veiculos.find { it.id == veiculoId }?.let { "${it.placa} - ${it.modelo ?: ""}" } ?: if (dataRota.isBlank()) "Selecione primeiro a data da rota" else if (veiculosDoDia.isEmpty()) "Nenhum veículo com rota nesta data" else "Selecione um veículo",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                    label = { Text("Veículo") },
                    enabled = dataRota.isNotBlank(),
                )
                ExposedDropdownMenu(expanded = expV, onDismissRequest = { expV = false }) {
                    veiculosDoDia.forEach { v -> DropdownMenuItem(text = { Text("${v.placa} - ${v.modelo ?: ""}") }, onClick = { veiculoId = v.id; rotaId = null; expV = false }) }
                }
            }
            Spacer(Modifier.height(8.dp))
            Text("Rota *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            var expRota by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expRota, onExpandedChange = { expRota = it }) {
                OutlinedTextField(
                    value = rotasDoVeiculo.find { it.id == rotaId }?.let { "${it.cidadeOrigemNome ?: ""} → ${it.cidadeDestinoNome ?: ""}" } ?: if (veiculoId == null) "Selecione primeiro o veículo" else if (rotasDoVeiculo.isEmpty()) "Nenhuma rota para este veículo" else "Selecione uma rota",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                    label = { Text("Rota") },
                    enabled = veiculoId != null,
                )
                ExposedDropdownMenu(expanded = expRota, onDismissRequest = { expRota = false }) {
                    rotasDoVeiculo.forEach { r -> DropdownMenuItem(text = { Text("${r.cidadeOrigemNome ?: ""} → ${r.cidadeDestinoNome ?: ""}") }, onClick = { rotaId = r.id; expRota = false }) }
                }
            }
            Spacer(Modifier.height(16.dp))

            Text("Dados do Abastecimento", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            Text("Tipo de Combustível *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            var expTipo by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expTipo, onExpandedChange = { expTipo = it }) {
                OutlinedTextField(value = tipoCombustivel, onValueChange = {}, readOnly = true, modifier = Modifier.fillMaxWidth().menuAnchor(), label = { Text("Tipo de Combustível") })
                ExposedDropdownMenu(expanded = expTipo, onDismissRequest = { expTipo = false }) {
                    listOf("Diesel", "Gasolina", "Etanol", "GNV").forEach { t -> DropdownMenuItem(text = { Text(t) }, onClick = { tipoCombustivel = t; expTipo = false }) }
                }
            }
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = posto, onValueChange = { posto = it }, label = { Text("Posto de Combustível *") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = litros, onValueChange = { onLitrosChange(it) }, label = { Text("Quantidade (Litros) *") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = valorLitro, onValueChange = { onValorLitroChange(it) }, label = { Text("Preço por Litro (R$) *") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = valorTotal, onValueChange = { onValorTotalChange(it) }, label = { Text("Valor Total (R$) *") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = kmAtual, onValueChange = { kmAtual = it.filter { c -> c.isDigit() || c == '.' || c == ',' } }, label = { Text("Quilometragem Atual *") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
            Text("Forma de Pagamento *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            var expForma by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expForma, onExpandedChange = { expForma = it }) {
                OutlinedTextField(value = formaPagamento, onValueChange = {}, readOnly = true, modifier = Modifier.fillMaxWidth().menuAnchor(), label = { Text("Forma de Pagamento") })
                ExposedDropdownMenu(expanded = expForma, onDismissRequest = { expForma = false }) {
                    listOf("Dinheiro", "Cartão", "PIX", "Outro").forEach { f -> DropdownMenuItem(text = { Text(f) }, onClick = { formaPagamento = f; expForma = false }) }
                }
            }
            Spacer(Modifier.height(8.dp))
            Text("Comprovante", style = MaterialTheme.typography.labelLarge)
            if (comprovanteUri != null) {
                AsyncImage(
                    model = comprovanteUri,
                    contentDescription = "Comprovante",
                    modifier = Modifier.fillMaxWidth().height(180.dp).clip(RoundedCornerShape(8.dp)),
                    contentScale = ContentScale.Fit,
                )
                Spacer(Modifier.height(4.dp))
                TextButton(onClick = { comprovanteUri = null }) { Text("Remover foto") }
            } else {
                Text("Nenhum ficheiro selecionado", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(4.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedButton(
                        onClick = {
                            val f = File(context.cacheDir, "comprovante_${System.currentTimeMillis()}.jpg")
                            val u = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", f)
                            cameraCaptureUri = u
                            takePictureLauncher.launch(u)
                        },
                        modifier = Modifier.weight(1f),
                    ) { Text("Câmera") }
                    OutlinedButton(
                        onClick = { galleryLauncher.launch("image/*") },
                        modifier = Modifier.weight(1f),
                    ) { Text("Galeria") }
                }
            }
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = observacoes, onValueChange = { observacoes = it }, label = { Text("Observações") }, modifier = Modifier.fillMaxWidth(), minLines = 2)
            Spacer(Modifier.height(24.dp))
            }
        }
    }
}
