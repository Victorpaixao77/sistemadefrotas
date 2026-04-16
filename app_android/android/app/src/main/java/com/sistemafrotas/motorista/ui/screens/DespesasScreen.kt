package com.sistemafrotas.motorista.ui.screens

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material.icons.filled.Route
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.RotaItem
import com.sistemafrotas.motorista.ui.components.CurrencyTransformation
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class, ExperimentalMaterialApi::class)
@Composable
fun DespesasScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
) {
    var rotas by remember { mutableStateOf<List<RotaItem>>(emptyList()) }
    var selectedRota by remember { mutableStateOf<RotaItem?>(null) }
    var loading by remember { mutableStateOf(true) }
    var refreshing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun loadRotas() {
        scope.launch {
            refreshing = true
            error = null
            try {
                authRepository.loadRotas(limite = 80)
                    .onSuccess { rotas = it.rotas ?: emptyList() }
                    .onFailure { error = it.message }
            } finally {
                loading = false
                refreshing = false
            }
        }
    }

    LaunchedEffect(Unit) { loadRotas() }
    val pullRefreshState = rememberPullRefreshState(refreshing, onRefresh = { loadRotas() })

    if (selectedRota != null) {
        LaunchedEffect(selectedRota) {
            val r = selectedRota!!
            onSubScreenChange("Despesas - ${r.cidadeOrigemNome ?: ""} - ${r.cidadeDestinoNome ?: ""}") { selectedRota = null }
        }
        DespesasFormScreen(
            authRepository = authRepository,
            rota = selectedRota!!,
            onBack = { selectedRota = null },
            onSuccess = { selectedRota = null },
            showTopBar = false,
            modifier = modifier,
        )
        return
    }

    LaunchedEffect(selectedRota) {
        if (selectedRota == null) onSubScreenChange(null, null)
    }

    Box(modifier = modifier.fillMaxSize().pullRefresh(pullRefreshState)) {
        if (loading && rotas.isEmpty()) Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
        else if (error != null) {
            Column(
                Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(error!!, color = MaterialTheme.colorScheme.error)
                Spacer(Modifier.height(8.dp))
                Button(onClick = { error = null; loadRotas() }) { Text("Tentar novamente") }
            }
        } else if (rotas.isEmpty()) {
            Column(
                Modifier.fillMaxSize().padding(24.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text("Nenhuma rota disponível.", style = MaterialTheme.typography.titleMedium)
                Text("Registre uma rota em Rotas ou puxe para atualizar.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        } else {
            LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(rotas) { r ->
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { selectedRota = r },
                        shape = RoundedCornerShape(8.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    ) {
                        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Filled.Receipt, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text("${r.cidadeOrigemNome} - ${r.cidadeDestinoNome}", style = MaterialTheme.typography.bodyLarge)
                                Text(r.dataRota ?: r.dataSaida ?: "", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            Icon(Icons.Filled.Route, contentDescription = null)
                        }
                    }
                }
            }
        }
        PullRefreshIndicator(refreshing, pullRefreshState, Modifier.align(Alignment.TopCenter))
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DespesasFormScreen(
    authRepository: AuthRepository,
    rota: RotaItem,
    onBack: () -> Unit,
    onSuccess: () -> Unit,
    showTopBar: Boolean = true,
    modifier: Modifier = Modifier,
) {
    var despesaId by remember { mutableStateOf<Int?>(null) }
    var descarga by remember { mutableStateOf("") }
    var pedagios by remember { mutableStateOf("") }
    var caixinha by remember { mutableStateOf("") }
    var estacionamento by remember { mutableStateOf("") }
    var lavagem by remember { mutableStateOf("") }
    var borracharia by remember { mutableStateOf("") }
    var eletricaMecanica by remember { mutableStateOf("") }
    var adiantamento by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var loadingDados by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun moneyFromApi(v: Double?) = ((v ?: 0.0) * 100).toLong().toString()

    fun toDouble(s: String) = (s.filter { it.isDigit() }.toLongOrNull() ?: 0L) / 100.0

    LaunchedEffect(rota.id) {
        loadingDados = true
        authRepository.loadDespesas(rota.id)
            .onSuccess { w ->
                val d = w.despesas.firstOrNull()
                if (d != null) {
                    despesaId = d.id
                    descarga = moneyFromApi(d.descarga)
                    pedagios = moneyFromApi(d.pedagios)
                    caixinha = moneyFromApi(d.caixinha)
                    estacionamento = moneyFromApi(d.estacionamento)
                    lavagem = moneyFromApi(d.lavagem)
                    borracharia = moneyFromApi(d.borracharia)
                    eletricaMecanica = moneyFromApi(d.eletricaMecanica)
                    adiantamento = moneyFromApi(d.adiantamento)
                } else {
                    despesaId = null
                }
            }
            .onFailure { error = it.message }
        loadingDados = false
    }

    BackHandler(onBack = onBack)

    Scaffold(
        modifier = modifier,
        topBar = if (showTopBar) {
            {
                TopAppBar(
                    title = { Text("Despesas - ${rota.cidadeOrigemNome} - ${rota.cidadeDestinoNome}") },
                    navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar") } },
                )
            }
        } else { {} },
        bottomBar = {
            if (!loadingDados) {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shadowElevation = 8.dp,
                    color = MaterialTheme.colorScheme.surface,
                    tonalElevation = 3.dp,
                ) {
                    Button(
                        onClick = {
                            loading = true
                            error = null
                            val total = toDouble(descarga) + toDouble(pedagios) + toDouble(caixinha) + toDouble(estacionamento) + toDouble(lavagem) + toDouble(borracharia) + toDouble(eletricaMecanica) + toDouble(adiantamento)
                            scope.launch {
                                val action = if (despesaId != null) "update" else "create"
                                val payload = mutableMapOf<String, Any?>(
                                    "rota_id" to rota.id,
                                    "action" to action,
                                    "descarga" to toDouble(descarga),
                                    "pedagios" to toDouble(pedagios),
                                    "caixinha" to toDouble(caixinha),
                                    "estacionamento" to toDouble(estacionamento),
                                    "lavagem" to toDouble(lavagem),
                                    "borracharia" to toDouble(borracharia),
                                    "eletrica_mecanica" to toDouble(eletricaMecanica),
                                    "adiantamento" to toDouble(adiantamento),
                                    "total" to total,
                                )
                                if (despesaId != null) payload["id"] = despesaId
                                authRepository.salvarDespesas(payload)
                                    .onSuccess { onSuccess() }
                                    .onFailure { err -> error = err.message; loading = false }
                            }
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 12.dp)
                            .heightIn(min = 48.dp),
                        enabled = !loading,
                    ) {
                        if (loading) CircularProgressIndicator(Modifier.size(24.dp), color = MaterialTheme.colorScheme.onPrimary)
                        else Text(if (despesaId != null) "Atualizar despesas" else "Salvar despesas")
                    }
                }
            }
        },
    ) { pad ->
        if (loadingDados) {
            Box(Modifier.fillMaxSize().padding(pad), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
            return@Scaffold
        }
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
            if (despesaId != null) {
                Text("Editando despesas já salvas desta rota.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(8.dp))
            }
            OutlinedTextField(value = descarga, onValueChange = { descarga = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Descarga") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = pedagios, onValueChange = { pedagios = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Pedágios") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = caixinha, onValueChange = { caixinha = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Caixinha") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = estacionamento, onValueChange = { estacionamento = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Estacionamento") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = lavagem, onValueChange = { lavagem = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Lavagem") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = borracharia, onValueChange = { borracharia = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Borracharia") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = eletricaMecanica, onValueChange = { eletricaMecanica = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Elétrica/Mecânica") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(value = adiantamento, onValueChange = { adiantamento = it.filter { ch -> ch.isDigit() }.take(12) }, label = { Text("Adiantamento") }, modifier = Modifier.fillMaxWidth(), visualTransformation = CurrencyTransformation())
            Spacer(Modifier.height(16.dp))
            val totalDespesas = toDouble(descarga) + toDouble(pedagios) + toDouble(caixinha) + toDouble(estacionamento) + toDouble(lavagem) + toDouble(borracharia) + toDouble(eletricaMecanica) + toDouble(adiantamento)
            Card(Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer)) {
                Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text("Total das despesas", style = MaterialTheme.typography.titleSmall)
                    Text("R$ %.2f".format(java.util.Locale("pt", "BR"), totalDespesas), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                }
            }
            Spacer(Modifier.height(24.dp))
            }
        }
    }
}
