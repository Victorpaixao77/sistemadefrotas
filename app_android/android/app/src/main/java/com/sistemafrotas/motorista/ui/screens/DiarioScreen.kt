package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccessTime
import androidx.compose.material.icons.filled.AssignmentTurnedIn
import androidx.compose.material.icons.filled.Campaign
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.EventNote
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.Toll
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.DiarioEvento
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.DiarioViewModel

private fun jornadaSub(t: String?): String = when (t) {
    "inicio" -> "Início de jornada"
    "parada" -> "Parada"
    "descanso" -> "Descanso"
    "refeicao" -> "Refeição"
    "fim" -> "Fim da jornada"
    else -> "Jornada"
}

private fun ocorrenciaSub(t: String?): String = when (t) {
    "acidente" -> "Acidente"
    "multa" -> "Multa"
    "roubo" -> "Roubo"
    "tentativa_roubo" -> "Tentativa de roubo"
    "mecanico" -> "Problema mecânico"
    "carga" -> "Problema com carga"
    "bloqueio" -> "Bloqueio de estrada"
    else -> "Ocorrência"
}

private data class DiarioVis(val titulo: String, val icone: ImageVector, val cor: Color)

private fun visual(ev: DiarioEvento): DiarioVis = when (ev.tipo) {
    "jornada" -> DiarioVis(jornadaSub(ev.titulo), Icons.Filled.AccessTime, Color(0xFF2563EB))
    "ocorrencia" -> DiarioVis(ocorrenciaSub(ev.titulo), Icons.Filled.Warning, Color(0xFFF59E0B))
    "entrega" -> DiarioVis("Comprovante de entrega", Icons.Filled.AssignmentTurnedIn, Color(0xFF16A34A))
    "panico" -> DiarioVis("Pânico acionado", Icons.Filled.Campaign, Color(0xFFDC2626))
    "checklist" -> DiarioVis("Checklist de viagem", Icons.Filled.CheckCircle, Color(0xFF0EA5E9))
    "abastecimento" -> DiarioVis(ev.titulo?.ifBlank { "Abastecimento" } ?: "Abastecimento", Icons.Filled.LocalGasStation, Color(0xFF7C3AED))
    "pedagio" -> DiarioVis(ev.titulo?.ifBlank { "Pedágio" } ?: "Pedágio", Icons.Filled.Toll, Color(0xFF0D9488))
    else -> DiarioVis(ev.titulo ?: "Evento", Icons.Filled.EventNote, Color(0xFF6B7280))
}

@Composable
fun DiarioScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val vm: DiarioViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { DiarioViewModel(it) }
    )
    val eventos by vm.eventos.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val error by vm.error.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    Box(modifier.fillMaxSize()) {
        when {
            error != null && eventos.isEmpty() -> Column(
                Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(error ?: "", color = MaterialTheme.colorScheme.error)
                Spacer(Modifier.height(8.dp))
                Button(onClick = { vm.setError(null); vm.refresh() }) { Text("Tentar novamente") }
            }
            eventos.isEmpty() && !refreshing -> Column(
                Modifier.fillMaxSize().padding(24.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Icon(Icons.Filled.EventNote, contentDescription = null, tint = MaterialTheme.colorScheme.outline)
                Spacer(Modifier.height(8.dp))
                Text("Sem atividades nos últimos 7 dias.", color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            else -> LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                item {
                    Text("Últimos 7 dias", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(4.dp))
                }
                items(eventos) { ev ->
                    val v = visual(ev)
                    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
                        Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Surface(shape = CircleShape, color = v.cor.copy(alpha = 0.15f), modifier = Modifier.size(40.dp)) {
                                Box(contentAlignment = Alignment.Center) {
                                    Icon(v.icone, contentDescription = null, tint = v.cor, modifier = Modifier.size(22.dp))
                                }
                            }
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(v.titulo, fontWeight = FontWeight.Medium)
                                if (!ev.descricao.isNullOrBlank()) {
                                    Text(ev.descricao!!, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                            Text(ev.dataHora ?: "", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
        }
    }
}
