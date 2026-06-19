package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Speed
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.VelocidadeViewModel

private fun corScore(score: Int): Color = when {
    score >= 90 -> Color(0xFF16A34A)
    score >= 75 -> Color(0xFF65A30D)
    score >= 50 -> Color(0xFFF59E0B)
    else -> Color(0xFFDC2626)
}

@Composable
fun VelocidadeScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val vm: VelocidadeViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { VelocidadeViewModel(it) }
    )
    val data by vm.data.collectAsState()
    val refreshing by vm.refreshing.collectAsState()
    val error by vm.error.collectAsState()

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    Box(modifier.fillMaxSize()) {
        when {
            error != null && data == null -> Column(
                Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(error ?: "", color = MaterialTheme.colorScheme.error)
                Spacer(Modifier.height(8.dp))
                Button(onClick = { vm.setError(null); vm.refresh() }) { Text("Tentar novamente") }
            }
            data == null && refreshing -> CircularProgressIndicator(Modifier.align(Alignment.Center))
            data != null -> {
                val d = data!!
                LazyColumn(
                    Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    item {
                        Card(
                            Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(14.dp),
                            colors = CardDefaults.cardColors(containerColor = corScore(d.score).copy(alpha = 0.12f)),
                        ) {
                            Column(Modifier.fillMaxWidth().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                Text("Pontuação de direção", style = MaterialTheme.typography.labelMedium)
                                Text("${d.score}", style = MaterialTheme.typography.displayMedium, fontWeight = FontWeight.Bold, color = corScore(d.score))
                                Text(d.classificacao ?: "", style = MaterialTheme.typography.titleMedium, color = corScore(d.score))
                                Text("Últimos ${d.dias} dias · limite ${d.limiteKmh.toInt()} km/h", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }
                    item {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            MetricaCard("Vel. média", "${d.velocidadeMedia.toInt()} km/h", Modifier.weight(1f))
                            MetricaCard("Vel. máxima", "${d.velocidadeMaxima.toInt()} km/h", Modifier.weight(1f))
                        }
                    }
                    item {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            MetricaCard("Excessos", "${d.excessos}", Modifier.weight(1f))
                            MetricaCard("% em excesso", "${d.pctExcesso}%", Modifier.weight(1f))
                        }
                    }
                    if (d.eventos.isEmpty()) {
                        item {
                            Text(
                                if (d.totalPontos == 0) "Sem dados de GPS no período. Mantenha o rastreamento ligado durante as viagens."
                                else "Nenhum excesso de velocidade no período. Continue assim! 👏",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(top = 8.dp),
                            )
                        }
                    } else {
                        item {
                            Text("Maiores excessos", style = MaterialTheme.typography.titleSmall, modifier = Modifier.padding(top = 8.dp))
                        }
                        items(d.eventos) { ev ->
                            Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
                                Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Filled.Speed, contentDescription = null, tint = Color(0xFFDC2626))
                                    Spacer(Modifier.width(12.dp))
                                    Text(ev.dataHora ?: "", Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium)
                                    Text("${(ev.velocidade ?: 0.0).toInt()} km/h", fontWeight = FontWeight.Bold, color = Color(0xFFDC2626))
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun MetricaCard(titulo: String, valor: String, modifier: Modifier = Modifier) {
    Card(modifier, shape = RoundedCornerShape(12.dp)) {
        Column(Modifier.fillMaxWidth().padding(14.dp)) {
            Text(titulo, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(2.dp))
            Text(valor, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold)
        }
    }
}
