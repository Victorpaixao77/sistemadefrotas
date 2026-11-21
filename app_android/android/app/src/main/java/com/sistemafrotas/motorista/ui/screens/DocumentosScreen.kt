package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Badge
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.MotoristaDocumentosData
import kotlinx.coroutines.launch

@Composable
fun DocumentosScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    var doc by remember { mutableStateOf<MotoristaDocumentosData?>(null) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    LaunchedEffect(Unit) {
        authRepository.loadMotoristaDocumentos()
            .onSuccess { doc = it; loading = false }
            .onFailure { error = it.message; loading = false }
    }

    Column(
        modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Meus documentos", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
        when {
            loading -> CircularProgressIndicator()
            error != null -> {
                Text(error!!, color = MaterialTheme.colorScheme.error)
                Button(onClick = {
                    loading = true
                    error = null
                    scope.launch {
                        authRepository.loadMotoristaDocumentos()
                            .onSuccess { doc = it; loading = false }
                            .onFailure { error = it.message; loading = false }
                    }
                }) { Text("Tentar novamente") }
            }
            doc != null -> {
                val d = doc!!
                val statusColor = when (d.statusCnh) {
                    "vencida" -> Color(0xFFDC2626)
                    "vencendo" -> Color(0xFFF59E0B)
                    else -> Color(0xFF16A34A)
                }
                Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp)) {
                    Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Badge, null, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(12.dp))
                        Column {
                            Text("CNH", fontWeight = FontWeight.SemiBold)
                            Text("Nº ${d.cnh ?: "—"} · Cat. ${d.categoriaCnh ?: "—"}")
                            Text(
                                "Validade: ${d.dataValidadeCnh ?: "—"}",
                                style = MaterialTheme.typography.bodySmall,
                                color = statusColor,
                            )
                            d.diasParaVencer?.let {
                                Text(
                                    when {
                                        it < 0 -> "Vencida há ${-it} dia(s)"
                                        it == 0 -> "Vence hoje"
                                        else -> "Vence em $it dia(s)"
                                    },
                                    style = MaterialTheme.typography.labelSmall,
                                    color = statusColor,
                                )
                            }
                        }
                    }
                }
                Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp)) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text("Identificação", fontWeight = FontWeight.SemiBold)
                        Text("Nome: ${d.nome ?: "—"}")
                        Text("CPF: ${d.cpf ?: "—"}")
                    }
                }
            }
        }
    }
}
