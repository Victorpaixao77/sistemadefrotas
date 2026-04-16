package com.sistemafrotas.motorista.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.Sync
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.NetworkConnectivity
import com.sistemafrotas.motorista.data.local.DatabaseProvider
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.withContext

@Composable
fun ConnectivityStatusBanner() {
    val ctx = LocalContext.current.applicationContext
    var online by remember { mutableStateOf(true) }
    var pending by remember { mutableIntStateOf(0) }
    LaunchedEffect(Unit) {
        while (isActive) {
            online = NetworkConnectivity.isOnline(ctx)
            pending = withContext(Dispatchers.IO) {
                runCatching { DatabaseProvider.get(ctx).pendingSyncDao().count() }.getOrDefault(0)
            }
            delay(2500)
        }
    }
    val visible = !online || pending > 0
    AnimatedVisibility(
        visible = visible,
        enter = fadeIn() + expandVertically(),
        exit = fadeOut() + shrinkVertically(),
    ) {
        val (bg, fg, icon) = if (!online) {
            Triple(
                MaterialTheme.colorScheme.errorContainer,
                MaterialTheme.colorScheme.onErrorContainer,
                Icons.Outlined.CloudOff,
            )
        } else {
            Triple(
                MaterialTheme.colorScheme.secondaryContainer,
                MaterialTheme.colorScheme.onSecondaryContainer,
                Icons.Outlined.Sync,
            )
        }
        val msg = when {
            !online && pending > 0 -> "Sem internet · $pending alteração(ões) na fila de envio"
            !online -> "Sem internet · dados em cache; envio automático quando voltar a rede"
            else -> "$pending alteração(ões) aguardando envio"
        }
        Surface(
            color = bg,
            tonalElevation = 2.dp,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Row(
                Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(icon, contentDescription = null, tint = fg)
                Spacer(Modifier.width(8.dp))
                Text(msg, style = MaterialTheme.typography.bodySmall, color = fg)
            }
        }
    }
}
