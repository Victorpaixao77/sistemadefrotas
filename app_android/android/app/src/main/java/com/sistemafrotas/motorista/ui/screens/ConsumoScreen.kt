package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.TrendingDown
import androidx.compose.material.icons.filled.TrendingFlat
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material.icons.filled.WarningAmber
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.AbastecimentoAlerta
import com.sistemafrotas.motorista.ui.viewmodel.AbastecimentoInsightsViewModel
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory

private fun corSeveridade(s: String?): Color = when (s) {
    "alta" -> Color(0xFFDC2626)
    "media" -> Color(0xFFF59E0B)
    else -> Color(0xFF6B7280)
}

@Composable
fun ConsumoScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val vm: AbastecimentoInsightsViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { AbastecimentoInsightsViewModel(it) }
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
                LazyColumn(
                    Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp),
                ) {
                    item {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            ConsumoCard("Consumo médio", d.consumoMedio?.let { "$it km/l" } ?: "—", Modifier.weight(1f))
                            ConsumoCard("Último", d.consumoUltimo?.let { "$it km/l" } ?: "—", Modifier.weight(1f))
                        }
                    }
                    d.tendenciaPct?.let { t ->
                        item {
                            val (icone, cor, txt) = when {
                                t <= -10 -> Triple(Icons.Filled.TrendingDown, Color(0xFFDC2626), "Consumo caiu ${kotlin.math.abs(t).toInt()}%")
                                t >= 10 -> Triple(Icons.Filled.TrendingUp, Color(0xFF16A34A), "Consumo melhorou ${t.toInt()}%")
                                else -> Triple(Icons.Filled.TrendingFlat, Color(0xFF6B7280), "Consumo estável")
                            }
                            Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = cor.copy(alpha = 0.12f))) {
                                Row(Modifier.fillMaxWidth().padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(icone, contentDescription = null, tint = cor)
                                    Spacer(Modifier.width(10.dp))
                                    Text(txt, fontWeight = FontWeight.SemiBold, color = cor)
                                }
                            }
                        }
                    }
                    item {
                        Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.secondaryContainer)) {
                            Row(Modifier.fillMaxWidth().padding(14.dp)) {
                                Icon(Icons.Filled.AutoAwesome, contentDescription = null, tint = MaterialTheme.colorScheme.onSecondaryContainer)
                                Spacer(Modifier.width(10.dp))
                                Text(d.insight ?: "", color = MaterialTheme.colorScheme.onSecondaryContainer, style = MaterialTheme.typography.bodyMedium)
                            }
                        }
                    }
                    if (d.alertas.isNotEmpty()) {
                        item {
                            Text("Alertas de abastecimento", style = MaterialTheme.typography.titleSmall, modifier = Modifier.padding(top = 8.dp))
                        }
                        items(d.alertas) { a -> AlertaCard(a) }
                    } else {
                        item {
                            Text(
                                "Nenhum alerta nos abastecimentos do período.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(top = 8.dp),
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ConsumoCard(titulo: String, valor: String, modifier: Modifier = Modifier) {
    Card(modifier, shape = RoundedCornerShape(12.dp)) {
        Column(Modifier.fillMaxWidth().padding(14.dp)) {
            Text(titulo, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(2.dp))
            Text(valor, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun AlertaCard(a: AbastecimentoAlerta) {
    val cor = corSeveridade(a.severidade)
    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
        Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.WarningAmber, contentDescription = null, tint = cor)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(a.motivo ?: "", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium)
                Text(
                    listOfNotNull(a.placa, a.posto, a.data?.take(10)).joinToString(" · "),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
