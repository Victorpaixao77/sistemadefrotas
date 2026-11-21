package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Map
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.GpsTrajetoPonto
import kotlinx.coroutines.launch

@Composable
fun MapaViagemScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    var pontos by remember { mutableStateOf<List<GpsTrajetoPonto>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    LaunchedEffect(Unit) {
        authRepository.loadGpsTrajeto(horas = 24)
            .onSuccess { pontos = it.pontos ?: emptyList(); loading = false }
            .onFailure { error = it.message; loading = false }
    }

    Column(modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Mapa da viagem", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
        Text("Últimas 24h de GPS registrado", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)

        when {
            loading -> CircularProgressIndicator(Modifier.align(Alignment.CenterHorizontally))
            error != null -> {
                Text(error!!, color = MaterialTheme.colorScheme.error)
                Button(onClick = {
                    loading = true
                    scope.launch {
                        authRepository.loadGpsTrajeto(24)
                            .onSuccess { pontos = it.pontos ?: emptyList(); loading = false }
                            .onFailure { error = it.message; loading = false }
                    }
                }) { Text("Tentar novamente") }
            }
            pontos.isEmpty() -> Column(
                Modifier.fillMaxWidth().padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Icon(Icons.Filled.Map, null, tint = MaterialTheme.colorScheme.outline)
                Spacer(Modifier.height(8.dp))
                Text("Nenhum ponto GPS nas últimas 24h.", color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            else -> {
                val coords = pontos.mapNotNull { p ->
                    val lat = p.lat ?: return@mapNotNull null
                    val lng = p.lng ?: return@mapNotNull null
                    lat to lng
                }
                Card(Modifier.fillMaxWidth().height(220.dp), shape = RoundedCornerShape(12.dp)) {
                    Canvas(Modifier.fillMaxSize().padding(8.dp)) {
                        if (coords.size < 2) return@Canvas
                        val minLat = coords.minOf { it.first }
                        val maxLat = coords.maxOf { it.first }
                        val minLng = coords.minOf { it.second }
                        val maxLng = coords.maxOf { it.second }
                        val path = Path()
                        coords.forEachIndexed { i, (lat, lng) ->
                            val x = if (maxLng == minLng) size.width / 2f
                            else ((lng - minLng) / (maxLng - minLng)).toFloat() * size.width
                            val y = if (maxLat == minLat) size.height / 2f
                            else (1f - ((lat - minLat) / (maxLat - minLat)).toFloat()) * size.height
                            if (i == 0) path.moveTo(x, y) else path.lineTo(x, y)
                        }
                        drawPath(path, Color(0xFF3B82F6), style = Stroke(width = 4f))
                        val (lat0, lng0) = coords.first()
                        val x0 = ((lng0 - minLng) / (maxLng - minLng + 1e-9)).toFloat() * size.width
                        val y0 = (1f - ((lat0 - minLat) / (maxLat - minLat + 1e-9)).toFloat()) * size.height
                        drawCircle(Color(0xFF16A34A), 8f, Offset(x0, y0))
                        val (latN, lngN) = coords.last()
                        val xN = ((lngN - minLng) / (maxLng - minLng + 1e-9)).toFloat() * size.width
                        val yN = (1f - ((latN - minLat) / (maxLat - minLat + 1e-9)).toFloat()) * size.height
                        drawCircle(Color(0xFFDC2626), 8f, Offset(xN, yN))
                    }
                }
                Text("${coords.size} pontos · verde=início · vermelho=agora", style = MaterialTheme.typography.labelSmall)
                LazyColumn(Modifier.heightIn(max = 180.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    itemsIndexed(coords.takeLast(20).reversed()) { _, (lat, lng) ->
                        Text("%.5f, %.5f".format(lat, lng), style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }
}
