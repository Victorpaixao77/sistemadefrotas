package com.sistemafrotas.motorista.ui.screens

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.FilterList
import androidx.compose.material.icons.outlined.Route
import androidx.compose.material.icons.filled.Route
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.derivedStateOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.LocationHelper
import com.sistemafrotas.motorista.data.api.CidadeItem
import com.sistemafrotas.motorista.data.api.EstadoItem
import com.sistemafrotas.motorista.data.api.RotaItem
import com.sistemafrotas.motorista.data.api.VeiculoItem
import com.sistemafrotas.motorista.ui.components.DatePickerField
import com.sistemafrotas.motorista.ui.components.CurrencyTransformation
import com.sistemafrotas.motorista.ui.components.KmTransformation
import com.sistemafrotas.motorista.ui.components.RotasListSkeleton
import com.sistemafrotas.motorista.ui.viewmodel.RotasViewModel
import com.sistemafrotas.motorista.ui.viewmodel.RotasViewModelFactory
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/** Converte dd/mm/yyyy ou yyyy-MM-dd para yyyy-MM-dd para a API. */
private fun normalizarDataApi(s: String): String {
    if (s.isBlank()) return s
    val t = s.trim()
    if (t.length == 10 && t[4] == '-' && t[7] == '-') return t
    val parts = t.split("/", "-")
    if (parts.size == 3) {
        val (a, b, c) = Triple(parts[0], parts[1], parts[2])
        val dia = a.toIntOrNull() ?: return t
        val mes = b.toIntOrNull() ?: return t
        val ano = c.toIntOrNull() ?: return t
        val year = if (ano in 0..99) 2000 + ano else ano
        return "%04d-%02d-%02d".format(year, mes, dia)
    }
    return t
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalMaterialApi::class)
@Composable
fun RotasScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
) {
    var showForm by remember { mutableStateOf(false) }
    val vm: RotasViewModel = viewModel(factory = RotasViewModelFactory(authRepository))
    val list by vm.list.collectAsState()
    val refreshing by vm.refreshing.collectAsState()
    val error by vm.error.collectAsState()
    var statusChip by remember { mutableStateOf<String?>(null) }
    var dataIniFiltro by remember { mutableStateOf("") }
    var dataFimFiltro by remember { mutableStateOf("") }
    var deleteError by remember { mutableStateOf<String?>(null) }
    var selectedRotaIdForDetail by remember { mutableStateOf<Int?>(null) }
    var selectedRotaIdForEdit by remember { mutableStateOf<Int?>(null) }
    var deleteTargetRotaId by remember { mutableStateOf<Int?>(null) }
    var deleting by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    fun refreshList() = vm.refreshList()

    fun aplicarFiltros() {
        vm.setStatusFilter(statusChip)
        val di = dataIniFiltro.takeIf { it.isNotBlank() }?.let { normalizarDataApi(it) }
        val df = dataFimFiltro.takeIf { it.isNotBlank() }?.let { normalizarDataApi(it) }
        vm.setIntervaloDatas(di, df)
        refreshList()
    }

    LaunchedEffect(Unit) { refreshList() }

    val pullRefreshState = rememberPullRefreshState(refreshing, onRefresh = { refreshList() })

    if (deleteTargetRotaId != null) {
        AlertDialog(
            onDismissRequest = { deleteTargetRotaId = null; deleteError = null },
            title = { Text("Excluir rota?") },
            text = { Text("Esta ação não pode ser desfeita. A rota e as despesas vinculadas serão excluídas.") },
            confirmButton = {
                Button(
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error),
                    onClick = {
                        val id = deleteTargetRotaId ?: return@Button
                        deleting = true
                        deleteError = null
                        scope.launch {
                            authRepository.excluirRota(id)
                                .onSuccess { deleteTargetRotaId = null; refreshList() }
                                .onFailure { deleteError = it.message }
                            deleting = false
                        }
                    },
                    enabled = !deleting,
                ) { if (deleting) CircularProgressIndicator(Modifier.size(20.dp), color = MaterialTheme.colorScheme.onError) else Text("Excluir") }
            },
            dismissButton = { TextButton(onClick = { deleteTargetRotaId = null; deleteError = null }) { Text("Cancelar") } },
        )
    }

    if (selectedRotaIdForDetail != null) {
        LaunchedEffect(selectedRotaIdForDetail) {
            onSubScreenChange("Detalhe da Rota") { selectedRotaIdForDetail = null }
        }
        RotaDetalheScreen(
            rotaId = selectedRotaIdForDetail!!,
            authRepository = authRepository,
            onBack = { selectedRotaIdForDetail = null },
            onEdit = { selectedRotaIdForDetail = null; selectedRotaIdForEdit = it },
            modifier = modifier,
            showTopBar = false,
        )
        return
    }

    if (showForm || selectedRotaIdForEdit != null) {
        LaunchedEffect(showForm, selectedRotaIdForEdit) {
            onSubScreenChange(if (selectedRotaIdForEdit != null) "Editar Rota" else "Nova Rota") {
                showForm = false
                selectedRotaIdForEdit = null
            }
        }
        NovaRotaScreen(
            authRepository = authRepository,
            rotaIdToEdit = selectedRotaIdForEdit,
            onBack = { showForm = false; selectedRotaIdForEdit = null },
            onSuccess = { showForm = false; selectedRotaIdForEdit = null; refreshList() },
            showTopBar = false,
            modifier = modifier,
        )
        return
    }

    LaunchedEffect(showForm, selectedRotaIdForDetail, selectedRotaIdForEdit) {
        if (!showForm && selectedRotaIdForDetail == null && selectedRotaIdForEdit == null) {
            onSubScreenChange(null, null)
        }
    }

    Scaffold(
        modifier = modifier,
        floatingActionButton = {
            FloatingActionButton(
                onClick = { showForm = true },
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = MaterialTheme.colorScheme.onPrimary,
                shape = androidx.compose.foundation.shape.CircleShape,
            ) {
                Icon(Icons.Filled.Add, contentDescription = "Nova rota")
            }
        },
    ) {
        Box(Modifier.fillMaxSize().padding(it).pullRefresh(pullRefreshState)) {
            LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(bottom = 88.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                item {
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 12.dp, vertical = 8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(Icons.Filled.FilterList, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(8.dp))
                        FilterChip(selected = statusChip == null, onClick = { statusChip = null }, label = { Text("Todas") })
                        Spacer(Modifier.width(6.dp))
                        FilterChip(selected = statusChip == "pendente", onClick = { statusChip = "pendente" }, label = { Text("Pendente") })
                        Spacer(Modifier.width(6.dp))
                        FilterChip(selected = statusChip == "aprovado", onClick = { statusChip = "aprovado" }, label = { Text("Aprovado") })
                        Spacer(Modifier.width(6.dp))
                        FilterChip(selected = statusChip == "rejeitado", onClick = { statusChip = "rejeitado" }, label = { Text("Rejeitado") })
                    }
                }
                item {
                    Text(
                        "Toque em Aplicar para filtrar por status e por intervalo de datas.",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 12.dp),
                    )
                }
                item {
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 12.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        DatePickerField(
                            value = dataIniFiltro,
                            onDateSelected = { dataIniFiltro = it },
                            label = "De (opcional)",
                            modifier = Modifier.weight(1f),
                        )
                        Spacer(Modifier.width(8.dp))
                        DatePickerField(
                            value = dataFimFiltro,
                            onDateSelected = { dataFimFiltro = it },
                            label = "Até (opcional)",
                            modifier = Modifier.weight(1f),
                        )
                        Spacer(Modifier.width(8.dp))
                        Button(onClick = { aplicarFiltros() }, modifier = Modifier.padding(top = 8.dp)) { Text("Aplicar") }
                    }
                }
                when {
                    refreshing && list.isEmpty() -> {
                        item {
                            RotasListSkeleton(count = 5, modifier = Modifier.fillMaxWidth().padding(16.dp))
                        }
                    }
                    error != null || deleteError != null -> {
                        item {
                            Column(
                                Modifier
                                    .fillMaxWidth()
                                    .padding(16.dp),
                                verticalArrangement = Arrangement.Center,
                                horizontalAlignment = Alignment.CenterHorizontally,
                            ) {
                                Text(
                                    deleteError ?: error ?: "",
                                    color = MaterialTheme.colorScheme.error,
                                    style = MaterialTheme.typography.bodyLarge,
                                )
                                Spacer(Modifier.height(12.dp))
                                Button(onClick = { vm.setError(null); deleteError = null; refreshList() }) { Text("Tentar novamente") }
                            }
                        }
                    }
                    list.isEmpty() -> {
                        item {
                            Column(
                                Modifier
                                    .fillMaxWidth()
                                    .padding(24.dp),
                                verticalArrangement = Arrangement.Center,
                                horizontalAlignment = Alignment.CenterHorizontally,
                            ) {
                                Icon(
                                    Icons.Outlined.Route,
                                    contentDescription = null,
                                    modifier = Modifier.size(56.dp),
                                    tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f),
                                )
                                Spacer(Modifier.height(12.dp))
                                Text("Nenhuma rota neste filtro.", style = MaterialTheme.typography.titleMedium)
                                Text(
                                    "Ajuste status ou datas e toque em Aplicar, ou use + para nova rota.",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }
                    else -> {
                        items(list, key = { it.id }) { rota ->
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(horizontal = 16.dp)
                                    .clickable { selectedRotaIdForDetail = rota.id },
                                shape = RoundedCornerShape(8.dp),
                                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                            ) {
                                Row(
                                    Modifier
                                        .fillMaxWidth()
                                        .padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Icon(Icons.Filled.Route, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                    Spacer(Modifier.width(12.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text("${rota.cidadeOrigemNome ?: "-"} → ${rota.cidadeDestinoNome ?: "-"}", style = MaterialTheme.typography.bodyLarge)
                                        Text(rota.dataRota ?: rota.dataSaida ?: "", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                        if (rota.placa != null) Text("Placa ${rota.placa}", style = MaterialTheme.typography.labelSmall)
                                    }
                                    AssistChip(onClick = { }, label = { Text(rota.status ?: "") })
                                    var showMenu by remember { mutableStateOf(false) }
                                    Box {
                                        IconButton(onClick = { showMenu = true }) {
                                            Icon(Icons.Filled.MoreVert, contentDescription = "Menu")
                                        }
                                        DropdownMenu(
                                            expanded = showMenu,
                                            onDismissRequest = { showMenu = false },
                                        ) {
                                            DropdownMenuItem(
                                                text = { Text("Editar") },
                                                onClick = {
                                                    showMenu = false
                                                    selectedRotaIdForEdit = rota.id
                                                },
                                            )
                                            DropdownMenuItem(
                                                text = { Text("Excluir", color = MaterialTheme.colorScheme.error) },
                                                onClick = {
                                                    showMenu = false
                                                    deleteTargetRotaId = rota.id
                                                },
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            PullRefreshIndicator(refreshing, pullRefreshState, Modifier.align(Alignment.TopCenter))
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NovaRotaScreen(
    authRepository: AuthRepository,
    rotaIdToEdit: Int? = null,
    onBack: () -> Unit,
    onSuccess: () -> Unit,
    showTopBar: Boolean = true,
    modifier: Modifier = Modifier,
) {
    var veiculos by remember { mutableStateOf<List<VeiculoItem>>(emptyList()) }
    var estados by remember { mutableStateOf<List<EstadoItem>>(emptyList()) }
    var cidadesOrigem by remember { mutableStateOf<List<CidadeItem>>(emptyList()) }
    var cidadesDestino by remember { mutableStateOf<List<CidadeItem>>(emptyList()) }
    var veiculoId by remember { mutableStateOf<Int?>(null) }
    var estadoOrigem by remember { mutableStateOf<EstadoItem?>(null) }
    var cidadeOrigemId by remember { mutableStateOf<Int?>(null) }
    var estadoDestino by remember { mutableStateOf<EstadoItem?>(null) }
    var cidadeDestinoId by remember { mutableStateOf<Int?>(null) }
    var dataRota by remember { mutableStateOf(java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).format(java.util.Calendar.getInstance().time)) }
    var dataHoraSaida by remember { mutableStateOf(java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm", java.util.Locale.US).format(java.util.Calendar.getInstance().time)) }
    var dataHoraChegada by remember { mutableStateOf("") }
    var kmSaida by remember { mutableStateOf("") }
    var kmChegada by remember { mutableStateOf("") }
    var kmVazio by remember { mutableStateOf("0") }
    var valorFrete by remember { mutableStateOf("") }
    var comissao by remember { mutableStateOf("") }
    var noPrazo by remember { mutableStateOf(true) }
    var pesoCarga by remember { mutableStateOf("") }
    var descricaoCarga by remember { mutableStateOf("") }
    var observacoes by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var porcentagemComissao by remember { mutableStateOf<Double?>(null) }
    var loadingEdit by remember { mutableStateOf(rotaIdToEdit != null) }
    var gpsLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    val scope = rememberCoroutineScope()
    val context = LocalContext.current

    val distKm by remember {
        derivedStateOf {
            kmSaida.replace(",", ".").toDoubleOrNull()?.let { s ->
                kmChegada.replace(",", ".").toDoubleOrNull()?.minus(s)
            }
        }
    }
    val totalKm by remember {
        derivedStateOf {
            (distKm ?: 0.0) + (kmVazio.replace(",", ".").toDoubleOrNull() ?: 0.0)
        }
    }
    val pctVazio by remember {
        derivedStateOf {
            if (totalKm > 0) ((kmVazio.replace(",", ".").toDoubleOrNull() ?: 0.0) / totalKm * 100) else 0.0
        }
    }
    val eficiencia by remember { derivedStateOf { 100 - pctVazio } }

    LaunchedEffect(Unit) {
        withContext(Dispatchers.IO) {
            LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()?.let { gpsLocation = it }
        }
    }

    LaunchedEffect(Unit) {
        authRepository.loadVeiculos()
            .onSuccess { veiculos = it.veiculos ?: emptyList() }
            .onFailure { error = it.message }
        authRepository.loadEstados()
            .onSuccess { estados = it.estados ?: emptyList() }
            .onFailure { e -> if (error == null) error = e.message }
        authRepository.loadMe()
            .onSuccess { me -> porcentagemComissao = me.porcentagemComissao }
            .onFailure { e -> if (error == null) error = e.message }
    }
    LaunchedEffect(estadoOrigem) {
        estadoOrigem?.uf?.let { uf ->
            authRepository.loadCidades(uf).onSuccess { cidadesOrigem = it.cidades ?: emptyList() }
        } ?: run { cidadesOrigem = emptyList() }
        if (rotaIdToEdit == null) cidadeOrigemId = null
    }
    LaunchedEffect(estadoDestino) {
        estadoDestino?.uf?.let { uf ->
            authRepository.loadCidades(uf).onSuccess { cidadesDestino = it.cidades ?: emptyList() }
        } ?: run { cidadesDestino = emptyList() }
        if (rotaIdToEdit == null) cidadeDestinoId = null
    }
    LaunchedEffect(rotaIdToEdit, estados) {
        if (rotaIdToEdit == null || estados.isEmpty()) {
            loadingEdit = false
            return@LaunchedEffect
        }
        authRepository.loadRotaDetalhe(rotaIdToEdit)
            .onSuccess { rota ->
                veiculoId = rota.veiculoId
                estadoOrigem = estados.find { it.uf == rota.estadoOrigem }
                estadoDestino = estados.find { it.uf == rota.estadoDestino }
                dataRota = rota.dataRota?.take(10) ?: dataRota
                dataHoraSaida = rota.dataSaida?.replace(" ", "T")?.take(16) ?: (dataRota + "T00:00")
                dataHoraChegada = rota.dataChegada?.replace(" ", "T")?.take(16) ?: ""
                kmSaida = rota.kmSaida?.let { "%.2f".format(it).replace(".", ",") } ?: ""
                kmChegada = rota.kmChegada?.let { "%.2f".format(it).replace(".", ",") } ?: ""
                kmVazio = rota.kmVazio?.let { "%.2f".format(it).replace(".", ",") } ?: "0"
                valorFrete = rota.frete?.let { (it * 100).toLong().toString() } ?: ""
                comissao = rota.comissao?.let { (it * 100).toLong().toString() } ?: ""
                noPrazo = (rota.noPrazo ?: 1) == 1
                pesoCarga = rota.pesoCarga?.let { "%.2f".format(it).replace(".", ",") } ?: ""
                descricaoCarga = rota.descricaoCarga ?: ""
                observacoes = rota.observacoes ?: ""
                cidadeOrigemId = rota.cidadeOrigemId
                cidadeDestinoId = rota.cidadeDestinoId
                // Carregar cidades para preencher dropdowns
                estadoOrigem?.uf?.let { uf ->
                    authRepository.loadCidades(uf).onSuccess { cidadesOrigem = it.cidades ?: emptyList() }
                }
                estadoDestino?.uf?.let { uf ->
                    authRepository.loadCidades(uf).onSuccess { cidadesDestino = it.cidades ?: emptyList() }
                }
                loadingEdit = false
            }
            .onFailure { error = it.message; loadingEdit = false }
    }

    BackHandler(onBack = onBack)

    Scaffold(
        modifier = modifier,
        topBar = if (showTopBar) {
            {
                TopAppBar(
                    title = { Text(if (rotaIdToEdit != null) "Editar Rota" else "Nova Rota") },
                    navigationIcon = {
                        IconButton(onClick = onBack) {
                            Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar")
                        }
                    },
                )
            }
        } else { {} },
        bottomBar = {
            if (!loadingEdit) {
            Surface(
                modifier = Modifier.fillMaxWidth(),
                shadowElevation = 8.dp,
                color = MaterialTheme.colorScheme.surface,
                tonalElevation = 3.dp,
            ) {
                Button(
                    onClick = {
                        if (veiculoId == null || estadoOrigem == null || cidadeOrigemId == null || estadoDestino == null || cidadeDestinoId == null || dataRota.isBlank()) {
                            error = "Preencha veículo, origem (estado e cidade), destino (estado e cidade) e data da rota."
                            return@Button
                        }
                        val dataRotaNorm = normalizarDataApi(dataRota)
                        val ks = kmSaida.replace(",", ".").toDoubleOrNull()
                        val kc = kmChegada.replace(",", ".").toDoubleOrNull()
                        if (ks != null && kc != null && kc < ks) {
                            error = "KM de chegada deve ser maior ou igual ao KM de saída."
                            return@Button
                        }
                        if (dataHoraChegada.isNotBlank()) {
                            try {
                                val sDate = dataHoraSaida.take(10).ifBlank { dataRotaNorm }
                                val sTime = dataHoraSaida.drop(11).take(5).ifBlank { "00:00" }
                                val cDate = dataHoraChegada.take(10).ifBlank { dataRotaNorm }
                                val cTime = dataHoraChegada.drop(11).take(5).ifBlank { "00:00" }
                                val tS = java.time.LocalDateTime.parse("${sDate}T$sTime:00")
                                val tC = java.time.LocalDateTime.parse("${cDate}T$cTime:00")
                                if (tC.isBefore(tS)) {
                                    error = "Data e hora de chegada devem ser iguais ou posteriores à saída."
                                    return@Button
                                }
                            } catch (_: Exception) {
                                error = "Verifique os horários de saída e chegada (formato HH:mm)."
                                return@Button
                            }
                        }
                        loading = true
                        error = null
                        val dataSaidaStr = if (dataHoraSaida.contains("T")) dataHoraSaida.replace("T", " ") + ":00" else dataRotaNorm + " 00:00:00"
                        val dataChegadaStr = if (dataHoraChegada.isNotBlank()) {
                            if (dataHoraChegada.contains("T")) dataHoraChegada.replace("T", " ") + ":00" else normalizarDataApi(dataHoraChegada) + " 00:00:00"
                        } else null
                        scope.launch {
                            val body = mapOf(
                                "veiculo_id" to veiculoId,
                                "cidade_origem_id" to cidadeOrigemId,
                                "cidade_destino_id" to cidadeDestinoId,
                                "estado_origem" to estadoOrigem!!.uf,
                                "estado_destino" to estadoDestino!!.uf,
                                "data_rota" to dataRotaNorm,
                                "data_saida" to dataSaidaStr,
                                "data_chegada" to dataChegadaStr,
                                "km_saida" to kmSaida.replace(",", ".").toDoubleOrNull(),
                                "km_chegada" to kmChegada.replace(",", ".").toDoubleOrNull(),
                                "distancia_km" to distKm,
                                "km_vazio" to kmVazio.replace(",", ".").toDoubleOrNull(),
                                "total_km" to totalKm,
                                "percentual_vazio" to pctVazio,
                                "eficiencia_viagem" to eficiencia,
                                "frete" to (valorFrete.toLongOrNull() ?: 0L) / 100.0,
                                "comissao" to (comissao.toLongOrNull() ?: 0L) / 100.0,
                                "no_prazo" to if (noPrazo) 1 else 0,
                                "peso_carga" to pesoCarga.replace(",", ".").toDoubleOrNull(),
                                "descricao_carga" to descricaoCarga.ifBlank { null },
                                "observacoes" to observacoes.ifBlank { null },
                                "latitude" to gpsLocation?.first,
                                "longitude" to gpsLocation?.second,
                            )
                            val result = if (rotaIdToEdit != null) {
                                authRepository.atualizarRota(body + ("id" to rotaIdToEdit))
                            } else {
                                authRepository.criarRota(body).map { }
                            }
                            result
                                .onSuccess { onSuccess() }
                                .onFailure { error = it.message; loading = false }
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp)
                        .heightIn(min = 48.dp),
                    enabled = !loading,
                ) {
                    if (loading) CircularProgressIndicator(Modifier.size(24.dp), color = MaterialTheme.colorScheme.onPrimary)
                    else Text("Salvar")
                }
            }
            }
        },
    ) { paddingValues ->
        if (loadingEdit) {
            Box(Modifier.fillMaxSize().padding(paddingValues), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
            return@Scaffold
        }
        val scrollState = rememberScrollState()
        Column(
            Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .then(if (!showTopBar) Modifier.padding(top = 8.dp) else Modifier),
        ) {
            Column(
                Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .verticalScroll(scrollState)
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
            Spacer(Modifier.height(12.dp))

            Text("Veículo *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            var expandedVeiculo by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expandedVeiculo, onExpandedChange = { expandedVeiculo = it }) {
                OutlinedTextField(
                    value = veiculos.find { it.id == veiculoId }?.let { "${it.placa} - ${it.modelo ?: ""}" } ?: "Selecione",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier
                        .fillMaxWidth()
                        .menuAnchor(),
                    label = { Text("Veículo") },
                )
                ExposedDropdownMenu(expanded = expandedVeiculo, onDismissRequest = { expandedVeiculo = false }) {
                    veiculos.forEach { v ->
                        DropdownMenuItem(
                            text = { Text("${v.placa} - ${v.modelo ?: ""}") },
                            onClick = { veiculoId = v.id; expandedVeiculo = false },
                        )
                    }
                }
            }
            Spacer(Modifier.height(16.dp))

            Text("Origem e Destino", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(4.dp))
            var expEstadoOrigem by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expEstadoOrigem, onExpandedChange = { expEstadoOrigem = it }) {
                OutlinedTextField(
                    value = estadoOrigem?.let { "${it.uf} - ${it.nome}" } ?: "Selecione o estado",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                    label = { Text("Estado de Origem *") },
                )
                ExposedDropdownMenu(expanded = expEstadoOrigem, onDismissRequest = { expEstadoOrigem = false }) {
                    estados.forEach { e ->
                        DropdownMenuItem(
                            text = { Text("${e.uf} - ${e.nome}") },
                            onClick = { estadoOrigem = e; expEstadoOrigem = false },
                        )
                    }
                }
            }
            Spacer(Modifier.height(8.dp))
            var expCidadeOrigem by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expCidadeOrigem, onExpandedChange = { expCidadeOrigem = it }) {
                OutlinedTextField(
                    value = cidadesOrigem.find { it.id == cidadeOrigemId }?.nome
                        ?: if (estadoOrigem == null) "Selecione primeiro o estado" else "Selecione a cidade",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                    label = { Text("Cidade de Origem *") },
                    enabled = estadoOrigem != null,
                )
                ExposedDropdownMenu(expanded = expCidadeOrigem, onDismissRequest = { expCidadeOrigem = false }) {
                    cidadesOrigem.forEach { c ->
                        DropdownMenuItem(
                            text = { Text(c.nome) },
                            onClick = { cidadeOrigemId = c.id; expCidadeOrigem = false },
                        )
                    }
                }
            }
            Spacer(Modifier.height(12.dp))

            Text("Estado de Destino *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            var expEstadoDestino by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expEstadoDestino, onExpandedChange = { expEstadoDestino = it }) {
                OutlinedTextField(
                    value = estadoDestino?.let { "${it.uf} - ${it.nome}" } ?: "Selecione o estado",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                    label = { Text("Estado de Destino") },
                )
                ExposedDropdownMenu(expanded = expEstadoDestino, onDismissRequest = { expEstadoDestino = false }) {
                    estados.forEach { e ->
                        DropdownMenuItem(
                            text = { Text("${e.uf} - ${e.nome}") },
                            onClick = { estadoDestino = e; expEstadoDestino = false },
                        )
                    }
                }
            }
            Spacer(Modifier.height(8.dp))
            var expCidadeDestino by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expCidadeDestino, onExpandedChange = { expCidadeDestino = it }) {
                OutlinedTextField(
                    value = cidadesDestino.find { it.id == cidadeDestinoId }?.nome
                        ?: if (estadoDestino == null) "Selecione primeiro o estado" else "Selecione a cidade",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                    label = { Text("Cidade de Destino *") },
                    enabled = estadoDestino != null,
                )
                ExposedDropdownMenu(expanded = expCidadeDestino, onDismissRequest = { expCidadeDestino = false }) {
                    cidadesDestino.forEach { c ->
                        DropdownMenuItem(
                            text = { Text(c.nome) },
                            onClick = { cidadeDestinoId = c.id; expCidadeDestino = false },
                        )
                    }
                }
            }
            Spacer(Modifier.height(16.dp))

            Text("Dados da Viagem", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            Text("Data/Hora Saída *", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            DatePickerField(
                value = dataHoraSaida.take(10),
                onDateSelected = { dataHoraSaida = it + "T" + (dataHoraSaida.drop(11).take(5).ifBlank { java.text.SimpleDateFormat("HH:mm", java.util.Locale.US).format(java.util.Calendar.getInstance().time) }) },
                label = "Data saída",
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = dataHoraSaida.drop(11).take(5),
                onValueChange = { t -> dataHoraSaida = dataHoraSaida.take(10) + "T" + t.filter { c -> c.isDigit() || c == ':' } },
                label = { Text("Hora saída (HH:mm)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            Text("Data/Hora Chegada", style = MaterialTheme.typography.labelLarge)
            Spacer(Modifier.height(4.dp))
            DatePickerField(
                value = dataHoraChegada.take(10).ifBlank { dataRota },
                onDateSelected = { dataHoraChegada = it + "T" + (dataHoraChegada.drop(11).take(5).ifBlank { java.text.SimpleDateFormat("HH:mm", java.util.Locale.US).format(java.util.Calendar.getInstance().time) }) },
                label = "Data chegada",
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = dataHoraChegada.drop(11).take(5),
                onValueChange = { t -> dataHoraChegada = (dataHoraChegada.take(10).ifBlank { dataRota }) + "T" + t.filter { c -> c.isDigit() || c == ':' } },
                label = { Text("Hora chegada (HH:mm)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = kmSaida,
                onValueChange = { kmSaida = it.filter { c -> c.isDigit() || c == '.' || c == ',' } },
                label = { Text("KM Saída") },
                modifier = Modifier.fillMaxWidth(),
                visualTransformation = KmTransformation(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = kmChegada,
                onValueChange = { kmChegada = it.filter { c -> c.isDigit() || c == '.' || c == ',' } },
                label = { Text("KM Chegada") },
                modifier = Modifier.fillMaxWidth(),
                visualTransformation = KmTransformation(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = distKm?.let { "%.2f".format(it).replace(".", ",") } ?: "",
                onValueChange = {},
                readOnly = true,
                label = { Text("Distância (km)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = kmVazio,
                onValueChange = { kmVazio = it.filter { c -> c.isDigit() || c == '.' || c == ',' } },
                label = { Text("KM Vazio") },
                modifier = Modifier.fillMaxWidth(),
                visualTransformation = KmTransformation(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = "%.2f".format(totalKm).replace(".", ","),
                onValueChange = {},
                readOnly = true,
                label = { Text("Total KM") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(16.dp))

            Text("Dados Financeiros e Eficiência", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = valorFrete,
                onValueChange = { newVal ->
                    val filtered = newVal.filter { c -> c.isDigit() }.take(12)
                    valorFrete = filtered
                    val freteVal = (filtered.toLongOrNull() ?: 0L) / 100.0
                    val pct = porcentagemComissao ?: 10.0
                    if (freteVal > 0 && pct >= 0) comissao = (freteVal * pct / 100 * 100).toLong().toString()
                },
                label = { Text("Valor do Frete (R$)") },
                modifier = Modifier.fillMaxWidth(),
                visualTransformation = CurrencyTransformation(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = comissao,
                onValueChange = { comissao = it.filter { c -> c.isDigit() }.take(12) },
                label = { Text("Comissão (R$)") },
                modifier = Modifier.fillMaxWidth(),
                visualTransformation = CurrencyTransformation(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = "%.2f".format(pctVazio).replace(".", ","),
                onValueChange = {},
                readOnly = true,
                label = { Text("Percentual Vazio (%)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = "%.2f".format(eficiencia).replace(".", ","),
                onValueChange = {},
                readOnly = true,
                label = { Text("Eficiência da Viagem (%)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Entrega no Prazo", style = MaterialTheme.typography.bodyLarge)
                Spacer(Modifier.width(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    FilterChip(selected = noPrazo, onClick = { noPrazo = true }, label = { Text("Sim") })
                    Spacer(Modifier.width(8.dp))
                    FilterChip(selected = !noPrazo, onClick = { noPrazo = false }, label = { Text("Não") })
                }
            }
            Spacer(Modifier.height(16.dp))

            Text("Dados da Carga", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = pesoCarga,
                onValueChange = { pesoCarga = it.filter { c -> c.isDigit() || c == '.' || c == ',' } },
                label = { Text("Peso da Carga (kg)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = descricaoCarga,
                onValueChange = { descricaoCarga = it },
                label = { Text("Descrição da Carga") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = observacoes,
                onValueChange = { observacoes = it },
                label = { Text("Observações") },
                modifier = Modifier.fillMaxWidth(),
                minLines = 2,
            )
            Spacer(Modifier.height(24.dp))
            }
        }
    }
}
