package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.DesempenhoMetricas
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.DesempenhoViewModel

private fun corDesempenho(score: Int): Color = when {
    score >= 90 -> Color(0xFF16A34A)
    score >= 75 -> Color(0xFF65A30D)
    score >= 50 -> Color(0xFFF59E0B)
    else -> Color(0xFFDC2626)
}

@Composable
fun DesempenhoScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val vm: DesempenhoViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { DesempenhoViewModel(it) }
    )
    val data by vm.data.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val error by vm.error.collectAsStateWithLifecycle()

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
                val cor = corDesempenho(d.score)
                Column(
                    Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    Card(
                        Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(14.dp),
                        colors = CardDefaults.cardColors(containerColor = cor.copy(alpha = 0.12f)),
                    ) {
                        Column(Modifier.fillMaxWidth().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("Sua pontuação", style = MaterialTheme.typography.labelMedium)
                            Text("${d.score}", style = MaterialTheme.typography.displayMedium, fontWeight = FontWeight.Bold, color = cor)
                            Text(d.classificacao ?: "", style = MaterialTheme.typography.titleMedium, color = cor)
                            Text("Últimos ${d.dias} dias", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }

                    if (d.rankingTotal > 0 && d.rankingPosicao > 0) {
                        Card(
                            Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
                        ) {
                            Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                                Surface(shape = CircleShape, color = MaterialTheme.colorScheme.primary, modifier = Modifier.size(44.dp)) {
                                    Box(contentAlignment = Alignment.Center) {
                                        Icon(Icons.Filled.EmojiEvents, contentDescription = null, tint = MaterialTheme.colorScheme.onPrimary)
                                    }
                                }
                                Spacer(Modifier.width(14.dp))
                                Column {
                                    Text("Ranking na empresa", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onPrimaryContainer)
                                    Text("${d.rankingPosicao}º de ${d.rankingTotal} motoristas", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onPrimaryContainer)
                                }
                            }
                        }
                    }

                    d.metricas?.let { m ->
                        Text("Indicadores", style = MaterialTheme.typography.titleSmall, modifier = Modifier.padding(top = 6.dp))
                        MetricaLinha("Multas no período", "${m.multas}")
                        MetricaLinha("Pontos na CNH", "${m.pontosCnh}")
                        MetricaLinha("Ocorrências", "${m.ocorrencias}")
                        MetricaLinha("Excessos de velocidade", "${m.excessosVelocidade} (${m.pctExcesso}%)")
                        MetricaLinha("Consumo médio", m.consumoKmL?.let { "$it km/l" } ?: "—")
                        MetricaLinha("KM rodados (estimado)", "${m.kmRodados.toInt()} km")
                        MetricaLinha("Abastecimentos", "${m.abastecimentos}")
                    }

                    Text(
                        "A pontuação considera multas, ocorrências e excessos de velocidade. Dirija com segurança para subir no ranking.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = 4.dp),
                    )
                    Spacer(Modifier.height(16.dp))
                }
            }
        }
    }
}

@Composable
private fun MetricaLinha(titulo: String, valor: String) {
    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(titulo, style = MaterialTheme.typography.bodyMedium)
            Text(valor, fontWeight = FontWeight.SemiBold)
        }
    }
}
