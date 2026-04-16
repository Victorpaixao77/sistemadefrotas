package com.sistemafrotas.motorista.ui.screens

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.outlined.Checklist
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.ChecklistItem
import com.sistemafrotas.motorista.data.api.RotaItem
import com.sistemafrotas.motorista.data.api.VeiculoItem
import com.sistemafrotas.motorista.ui.components.ChecklistsListSkeleton
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class, ExperimentalMaterialApi::class)
@Composable
fun ChecklistsScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
) {
    var showForm by remember { mutableStateOf(false) }
    var list by remember { mutableStateOf<List<ChecklistItem>>(emptyList()) }
    var refreshing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    fun refresh() {
        scope.launch {
            refreshing = true
            error = null
            try {
                authRepository.loadChecklists()
                    .onSuccess { list = it.checklists ?: emptyList() }
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
            onSubScreenChange("Novo Checklist") { showForm = false }
        }
        NovoChecklistScreen(
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
                Icon(Icons.Filled.Add, contentDescription = "Novo checklist")
            }
        },
    ) { pad ->
        Box(Modifier.fillMaxSize().padding(pad).pullRefresh(pullRefreshState)) {
            if (refreshing && list.isEmpty()) {
                ChecklistsListSkeleton(count = 5, modifier = Modifier.fillMaxSize().padding(16.dp))
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
                    Icon(Icons.Outlined.CheckCircle, null, Modifier.size(52.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.55f))
                    Spacer(Modifier.height(12.dp))
                    Text("Nenhum checklist ainda.", style = MaterialTheme.typography.titleMedium)
                    Text("Puxe para atualizar ou toque em + para registrar.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            } else {
                LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(list) { c ->
                        Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)) {
                            Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Outlined.Checklist, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                Spacer(Modifier.width(12.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(c.dataChecklist ?: "", style = MaterialTheme.typography.bodyLarge)
                                    Text("${c.cidadeOrigemNome ?: "-"} - ${c.cidadeDestinoNome ?: "-"}", style = MaterialTheme.typography.bodyMedium)
                                    c.placa?.let { Text("Placa $it", style = MaterialTheme.typography.labelSmall) }
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

private val CHECKLIST_ITENS: List<Pair<String, String>> = listOf(
    "oleo_motor" to "Óleo do motor",
    "agua_radiador" to "Água do radiador",
    "fluido_freio" to "Fluido de freio",
    "fluido_direcao" to "Fluido da direção",
    "combustivel" to "Combustível",
    "pneus" to "Pneus",
    "estepe" to "Estepe",
    "luzes" to "Luzes",
    "buzina" to "Buzina",
    "limpador_para_brisa" to "Limpador de para-brisa",
    "agua_limpador" to "Água do limpador",
    "freios" to "Freios",
    "vazamentos" to "Vazamentos",
    "rastreador" to "Rastreador",
    "triangulo" to "Triângulo",
    "extintor" to "Extintor",
    "chave_macaco" to "Chave e macaco",
    "cintas" to "Cintas",
    "primeiros_socorros" to "Kit primeiros socorros",
    "doc_veiculo" to "Documento do veículo",
    "cnh" to "CNH",
    "licenciamento" to "Licenciamento",
    "seguro" to "Seguro",
    "manifesto_carga" to "Manifesto de carga",
    "doc_empresa" to "Documentação da empresa",
    "carga_amarrada" to "Carga amarrada",
    "peso_correto" to "Peso correto",
    "motorista_descansado" to "Motorista descansado",
    "motorista_sobrio" to "Motorista sóbrio",
    "celular_carregado" to "Celular carregado",
    "epi" to "EPI",
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun NovoChecklistScreen(authRepository: AuthRepository, onBack: () -> Unit, onSuccess: () -> Unit, showTopBar: Boolean = true, modifier: Modifier = Modifier) {
    var rotas by remember { mutableStateOf<List<RotaItem>>(emptyList()) }
    var veiculos by remember { mutableStateOf<List<VeiculoItem>>(emptyList()) }
    var rotaId by remember { mutableStateOf<Int?>(null) }
    var veiculoId by remember { mutableStateOf<Int?>(null) }
    val itemOk = remember {
        mutableStateMapOf<String, Boolean>().apply {
            CHECKLIST_ITENS.forEach { (k, _) -> put(k, true) }
        }
    }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    LaunchedEffect(Unit) {
        authRepository.loadRotas().onSuccess { rotas = it.rotas ?: emptyList() }
        authRepository.loadVeiculos().onSuccess { veiculos = it.veiculos ?: emptyList() }
    }

    BackHandler(onBack = onBack)

    Scaffold(
        modifier = modifier,
        topBar = if (showTopBar) {
            { TopAppBar(title = { Text("Novo Checklist") }, navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar") } }) }
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
                        if (rotaId == null || veiculoId == null) {
                            error = "Selecione a rota e o veiculo."
                            return@Button
                        }
                        loading = true
                        error = null
                        val dataChecklist = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Calendar.getInstance().time)
                        val body = mutableMapOf<String, Any?>(
                            "rota_id" to rotaId,
                            "veiculo_id" to veiculoId,
                            "data_checklist" to dataChecklist,
                        )
                        CHECKLIST_ITENS.forEach { (key, _) -> body[key] = if (itemOk[key] == true) 1 else 0 }
                        scope.launch {
                            authRepository.criarChecklist(body).onSuccess { onSuccess() }.onFailure { error = it.message; loading = false }
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp)
                        .heightIn(min = 48.dp),
                    enabled = !loading,
                ) {
                    if (loading) CircularProgressIndicator(Modifier.size(24.dp), color = MaterialTheme.colorScheme.onPrimary)
                    else Text("Registrar checklist")
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

            var expR by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expR, onExpandedChange = { expR = it }) {
                OutlinedTextField(
                    value = rotas.find { it.id == rotaId }?.let { "${it.cidadeOrigemNome} - ${it.cidadeDestinoNome}" } ?: "Selecione a rota *",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                )
                ExposedDropdownMenu(expanded = expR, onDismissRequest = { expR = false }) {
                    rotas.forEach { r -> DropdownMenuItem(text = { Text("${r.cidadeOrigemNome} - ${r.cidadeDestinoNome} (${r.dataRota ?: ""})") }, onClick = { rotaId = r.id; expR = false }) }
                }
            }
            Spacer(Modifier.height(12.dp))
            var expV by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(expanded = expV, onExpandedChange = { expV = it }) {
                OutlinedTextField(
                    value = veiculos.find { it.id == veiculoId }?.let { "${it.placa} - ${it.modelo ?: ""}" } ?: "Selecione o veiculo *",
                    onValueChange = {},
                    readOnly = true,
                    modifier = Modifier.fillMaxWidth().menuAnchor(),
                )
                ExposedDropdownMenu(expanded = expV, onDismissRequest = { expV = false }) {
                    veiculos.forEach { v -> DropdownMenuItem(text = { Text("${v.placa} - ${v.modelo ?: ""}") }, onClick = { veiculoId = v.id; expV = false }) }
                }
            }
            Spacer(Modifier.height(12.dp))
            Text("Itens do veículo e documentação", style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(8.dp))
            CHECKLIST_ITENS.forEach { (key, label) ->
                Row(
                    Modifier.fillMaxWidth().padding(vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(label, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
                    Switch(
                        checked = itemOk[key] == true,
                        onCheckedChange = { itemOk[key] = it },
                    )
                }
            }
            Spacer(Modifier.height(24.dp))
            }
        }
    }
}
