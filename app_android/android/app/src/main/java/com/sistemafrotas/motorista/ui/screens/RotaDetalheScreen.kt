package com.sistemafrotas.motorista.ui.screens

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.RotaDetalheItem
import kotlinx.coroutines.launch
import java.util.Locale

private fun formatMoney(value: Double?) =
    if (value == null || value == 0.0) "—" else "R$ %.2f".format(Locale("pt", "BR"), value)
private fun formatDate(s: String?) = if (s.isNullOrBlank()) "—" else s.substring(0, minOf(10, s.length))

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RotaDetalheScreen(
    rotaId: Int,
    authRepository: AuthRepository,
    onBack: () -> Unit,
    onEdit: (Int) -> Unit,
    modifier: Modifier = Modifier,
    showTopBar: Boolean = true,
) {
    var rota by remember { mutableStateOf<RotaDetalheItem?>(null) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var showDeleteConfirm by remember { mutableStateOf(false) }
    var deleting by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    fun load() {
        loading = true
        scope.launch {
            authRepository.loadRotaDetalhe(rotaId)
                .onSuccess { rota = it }
                .onFailure { error = it.message }
            loading = false
        }
    }

    LaunchedEffect(rotaId) { load() }

    BackHandler(onBack = onBack)

    if (showDeleteConfirm) {
        AlertDialog(
            onDismissRequest = { showDeleteConfirm = false },
            title = { Text("Excluir rota?") },
            text = { Text("Esta ação não pode ser desfeita. A rota e as despesas vinculadas serão excluídas.") },
            confirmButton = {
                Button(
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error),
                    onClick = {
                        deleting = true
                        scope.launch {
                            authRepository.excluirRota(rotaId)
                                .onSuccess { onBack() }
                                .onFailure { error = it.message }
                            deleting = false
                            showDeleteConfirm = false
                        }
                    },
                    enabled = !deleting,
                ) { if (deleting) CircularProgressIndicator(Modifier.size(20.dp), color = MaterialTheme.colorScheme.onError) else Text("Excluir") }
            },
            dismissButton = { TextButton(onClick = { showDeleteConfirm = false }) { Text("Cancelar") } },
        )
    }

    Scaffold(
        modifier = modifier,
        topBar = if (showTopBar) {
            {
                TopAppBar(
                    title = { Text("Detalhe da Rota") },
                    navigationIcon = {
                        IconButton(onClick = onBack) {
                            Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar")
                        }
                    },
                    actions = {
                        IconButton(onClick = { rota?.id?.let { onEdit(it) } }) {
                            Icon(Icons.Filled.Edit, contentDescription = "Editar")
                        }
                        IconButton(onClick = { showDeleteConfirm = true }) {
                            Icon(Icons.Filled.Delete, contentDescription = "Excluir", tint = MaterialTheme.colorScheme.error)
                        }
                    },
                )
            }
        } else { {} },
    ) { paddingValues ->
        if (loading) {
            Box(Modifier.fillMaxSize().padding(paddingValues), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
        } else if (error != null) {
            Box(Modifier.fillMaxSize().padding(paddingValues), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(error!!, color = MaterialTheme.colorScheme.error)
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = { error = null; load() }) { Text("Tentar novamente") }
                }
            }
        } else {
            val r = rota ?: return@Scaffold
            val totalDespesas = (r.despesas?.sumOf { (it.totalDespviagem ?: 0.0) } ?: 0.0).takeIf { it > 0 }
                ?: (r.despesas?.sumOf { listOfNotNull(it.descarga, it.pedagios, it.caixinha, it.estacionamento, it.lavagem, it.borracharia, it.eletricaMecanica, it.adiantamento).sum() } ?: 0.0)
            val totalAbast = r.abastecimentos?.sumOf { it.valorTotal ?: 0.0 } ?: 0.0
            val frete = r.frete ?: 0.0
            val comissao = r.comissao ?: 0.0
            val lucro = frete - comissao - totalDespesas

            Column(
                Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp),
            ) {
                if (!showTopBar) {
                    Row(
                        Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.End,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        TextButton(onClick = { r.id?.let { onEdit(it) } }) {
                            Icon(Icons.Filled.Edit, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("Editar")
                        }
                        TextButton(onClick = { showDeleteConfirm = true }) {
                            Icon(Icons.Filled.Delete, contentDescription = null, modifier = Modifier.size(18.dp), tint = MaterialTheme.colorScheme.error)
                            Spacer(Modifier.width(4.dp))
                            Text("Excluir", color = MaterialTheme.colorScheme.error)
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                }
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
                ) {
                    Column(Modifier.padding(16.dp)) {
                        Text(
                            "${r.cidadeOrigemNome ?: "—"} → ${r.cidadeDestinoNome ?: "—"}",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                        )
                        Spacer(Modifier.height(4.dp))
                        Text("${formatDate(r.dataRota)} • ${r.placa ?: "—"} ${r.status?.let { "• $it" } ?: ""}", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        if (!r.observacoes.isNullOrBlank()) {
                            Spacer(Modifier.height(8.dp))
                            Text(r.observacoes!!, style = MaterialTheme.typography.bodySmall)
                        }
                    }
                }
                Spacer(Modifier.height(16.dp))

                Text("Resumo financeiro", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Frete", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(formatMoney(r.frete), fontWeight = FontWeight.Medium)
                }
                Spacer(Modifier.height(4.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Comissão", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(formatMoney(r.comissao), fontWeight = FontWeight.Medium)
                }
                Spacer(Modifier.height(4.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Total despesas", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(formatMoney(totalDespesas), fontWeight = FontWeight.Medium)
                }
                Divider(Modifier.padding(vertical = 8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Lucro da rota", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Text(
                        formatMoney(lucro),
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = if (lucro >= 0) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error,
                    )
                }
                Spacer(Modifier.height(16.dp))

                if (!r.despesas.isNullOrEmpty()) {
                    Text("Despesas vinculadas", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(8.dp))
                    r.despesas!!.forEach { d ->
                        val tot = d.totalDespviagem ?: listOfNotNull(d.descarga, d.pedagios, d.caixinha, d.estacionamento, d.lavagem, d.borracharia, d.eletricaMecanica, d.adiantamento).sum()
                        Card(Modifier.fillMaxWidth().padding(vertical = 4.dp), shape = RoundedCornerShape(8.dp)) {
                            Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Filled.Receipt, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                Column(Modifier.weight(1f)) {
                                    Text("Descarga ${formatMoney(d.descarga)} • Pedágios ${formatMoney(d.pedagios)} • Outros", style = MaterialTheme.typography.bodySmall)
                                }
                                Text(formatMoney(tot), fontWeight = FontWeight.Medium)
                            }
                        }
                    }
                    Spacer(Modifier.height(16.dp))
                }

                if (!r.abastecimentos.isNullOrEmpty()) {
                    Text("Abastecimentos vinculados", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(8.dp))
                    r.abastecimentos!!.forEach { a ->
                        Card(Modifier.fillMaxWidth().padding(vertical = 4.dp), shape = RoundedCornerShape(8.dp)) {
                            Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Filled.LocalGasStation, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                                Column(Modifier.weight(1f)) {
                                    Text("${formatDate(a.dataAbastecimento)} • ${a.litros?.let { "%.1f L".format(Locale.US, it) } ?: "—"}", style = MaterialTheme.typography.bodySmall)
                                    a.posto?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.labelSmall) }
                                }
                                Text(formatMoney(a.valorTotal), fontWeight = FontWeight.Medium)
                            }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text("Total abastecimentos", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(formatMoney(totalAbast), fontWeight = FontWeight.Medium)
                    }
                    Spacer(Modifier.height(16.dp))
                }

                Text("Dados da viagem", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))
                DetailRow("KM saída", r.kmSaida?.toString())
                DetailRow("KM chegada", r.kmChegada?.toString())
                DetailRow("Distância (km)", r.distanciaKm?.toString())
                DetailRow("Total KM", r.totalKm?.toString())
                DetailRow("Eficiência (%)", r.eficienciaViagem?.toString())
                DetailRow("Peso carga (kg)", r.pesoCarga?.toString())
                r.descricaoCarga?.takeIf { it.isNotBlank() }?.let { DetailRow("Descrição carga", it) }
                Spacer(Modifier.height(80.dp))
            }
        }
    }
}

@Composable
private fun DetailRow(label: String, value: String?) {
    if (value.isNullOrBlank()) return
    Row(Modifier.fillMaxWidth().padding(vertical = 2.dp), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodyMedium)
        Text(value, style = MaterialTheme.typography.bodyMedium)
    }
}
