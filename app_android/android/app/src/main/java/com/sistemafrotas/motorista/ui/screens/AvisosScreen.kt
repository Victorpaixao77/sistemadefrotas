package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AssignmentTurnedIn
import androidx.compose.material.icons.filled.Badge
import androidx.compose.material.icons.filled.NotificationsNone
import androidx.compose.material.icons.filled.Route
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
import com.sistemafrotas.motorista.data.api.AvisoItem
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.AvisosViewModel

private data class AvisoVis(val icone: ImageVector, val cor: Color)

private fun visualAviso(tipo: String?): AvisoVis = when (tipo) {
    "rota" -> AvisoVis(Icons.Filled.Route, Color(0xFF2563EB))
    "checklist" -> AvisoVis(Icons.Filled.AssignmentTurnedIn, Color(0xFFF59E0B))
    "documento" -> AvisoVis(Icons.Filled.Badge, Color(0xFFDC2626))
    else -> AvisoVis(Icons.Filled.NotificationsNone, Color(0xFF6B7280))
}

@Composable
fun AvisosScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val vm: AvisosViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { AvisosViewModel(it) }
    )
    val list by vm.list.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val error by vm.error.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    Box(modifier.fillMaxSize()) {
        when {
            error != null && list.isEmpty() -> Column(
                Modifier.fillMaxSize().padding(16.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(error ?: "", color = MaterialTheme.colorScheme.error)
                Spacer(Modifier.height(8.dp))
                Button(onClick = { vm.setError(null); vm.refresh() }) { Text("Tentar novamente") }
            }
            list.isEmpty() && !refreshing -> Column(
                Modifier.fillMaxSize().padding(24.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Icon(Icons.Filled.NotificationsNone, contentDescription = null, tint = MaterialTheme.colorScheme.outline)
                Spacer(Modifier.height(8.dp))
                Text("Nenhum aviso no momento.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text("Avisamos sobre novas rotas, checklist pendente e documentos vencendo.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            else -> LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                items(list, key = { it.chave }) { a -> AvisoCard(a) }
            }
        }
    }
}

@Composable
private fun AvisoCard(a: AvisoItem) {
    val v = visualAviso(a.tipo)
    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
        Row(Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Surface(shape = CircleShape, color = v.cor.copy(alpha = 0.15f), modifier = Modifier.size(40.dp)) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(v.icone, contentDescription = null, tint = v.cor, modifier = Modifier.size(22.dp))
                }
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(a.titulo ?: "Aviso", fontWeight = FontWeight.SemiBold)
                if (!a.descricao.isNullOrBlank()) {
                    Text(a.descricao!!, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}
