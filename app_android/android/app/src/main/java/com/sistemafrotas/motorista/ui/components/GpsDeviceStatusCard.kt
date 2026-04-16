package com.sistemafrotas.motorista.ui.components

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.R
import com.sistemafrotas.motorista.data.GpsLocationUtils
import com.sistemafrotas.motorista.data.GpsPendingQueue
import com.sistemafrotas.motorista.data.GpsPreferencesStore
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun GpsDeviceStatusCard(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val app = context.applicationContext
    val gpsPrefs = remember { GpsPreferencesStore(app) }
    var trackingOn by remember { mutableStateOf(false) }
    var pending by remember { mutableIntStateOf(0) }
    var lastUploadMs by remember { mutableLongStateOf(0L) }
    var systemLocOn by remember { mutableStateOf(true) }

    LaunchedEffect(Unit) {
        while (true) {
            val (on, _) = withContext(Dispatchers.IO) { gpsPrefs.getTracking() }
            val n = withContext(Dispatchers.IO) { GpsPendingQueue.pendingCount(app) }
            val last = withContext(Dispatchers.IO) { gpsPrefs.getLastUploadSuccessMillis() }
            val locOn = GpsLocationUtils.isSystemLocationEnabled(app)
            trackingOn = on
            pending = n
            lastUploadMs = last
            systemLocOn = locOn
            delay(2500)
        }
    }

    val hora = remember(lastUploadMs) {
        if (lastUploadMs <= 0L) "—"
        else SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date(lastUploadMs))
    }

    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.55f)),
    ) {
        Column(Modifier.padding(12.dp)) {
            Text(
                text = stringResource(R.string.gps_device_status_title),
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(6.dp))
            Text(
                text = if (trackingOn) {
                    stringResource(R.string.gps_device_tracking_on)
                } else {
                    stringResource(R.string.gps_device_tracking_off)
                },
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurface,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = stringResource(R.string.gps_device_pending_fmt, pending),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(
                text = stringResource(R.string.gps_device_last_fmt, hora),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            if (trackingOn && !systemLocOn) {
                Spacer(Modifier.height(6.dp))
                Text(
                    text = stringResource(R.string.gps_device_system_location_off),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error,
                )
            }
        }
    }
}
