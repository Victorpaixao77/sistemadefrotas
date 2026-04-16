package com.sistemafrotas.motorista.ui.screens

import android.Manifest
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Save
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.GpsForegroundService
import com.sistemafrotas.motorista.GpsPendingSyncWorker
import com.sistemafrotas.motorista.R
import com.sistemafrotas.motorista.data.GpsDiagnostics
import com.sistemafrotas.motorista.data.GpsNotifyHelper
import com.sistemafrotas.motorista.data.GpsPendingQueue
import com.sistemafrotas.motorista.data.GpsPreferencesStore
import com.sistemafrotas.motorista.ui.components.GpsDeviceStatusCard
import com.sistemafrotas.motorista.data.api.Api
import com.sistemafrotas.motorista.data.api.VeiculoItem
import com.sistemafrotas.motorista.data.AuthRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ConfigScreen(
    authRepository: AuthRepository,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val gpsPrefs = remember { GpsPreferencesStore(context.applicationContext) }

    var url by remember { mutableStateOf(Api.baseUrl.trimEnd('/')) }
    var loading by remember { mutableStateOf(true) }
    var saved by remember { mutableStateOf(false) }

    var veiculos by remember { mutableStateOf<List<VeiculoItem>>(emptyList()) }
    var selectedVeiculo by remember { mutableStateOf<VeiculoItem?>(null) }
    var trackingOn by remember { mutableStateOf(false) }
    var dropdownExpanded by remember { mutableStateOf(false) }
    var gpsHint by remember { mutableStateOf<String?>(null) }
    var diagText by remember { mutableStateOf("") }

    val bgLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { ok ->
        gpsHint = context.getString(
            if (ok) R.string.gps_background_ok else R.string.gps_background_denied,
        )
        if (!ok) {
            GpsNotifyHelper.notifyBackgroundLocationDenied(context.applicationContext)
        }
    }

    val permLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions(),
    ) { granted ->
        val locOk = granted[Manifest.permission.ACCESS_FINE_LOCATION] == true ||
            granted[Manifest.permission.ACCESS_COARSE_LOCATION] == true
        val notifOk = if (Build.VERSION.SDK_INT >= 33) {
            granted[Manifest.permission.POST_NOTIFICATIONS] == true
        } else {
            true
        }
        if (!locOk) {
            gpsHint = context.getString(R.string.config_gps_err_location)
            GpsNotifyHelper.notifyLocationRequired(context.applicationContext)
            return@rememberLauncherForActivityResult
        }
        if (!notifOk) {
            gpsHint = context.getString(R.string.config_gps_warn_notification)
            GpsNotifyHelper.notifyNotificationsHelp(context.applicationContext)
        }
        val v = selectedVeiculo ?: return@rememberLauncherForActivityResult
        scope.launch {
            gpsPrefs.setTracking(true, v.id)
            trackingOn = true
            GpsForegroundService.start(context.applicationContext, v.id)
            GpsPendingSyncWorker.schedule(context.applicationContext)
            gpsHint = context.getString(R.string.config_gps_ok_tracking)
        }
    }

    LaunchedEffect(Unit) {
        authRepository.getApiBaseUrl()?.let { url = it.trimEnd('/') }
        val (on, vid) = gpsPrefs.getTracking()
        trackingOn = on
        authRepository.loadVeiculos().onSuccess { list ->
            veiculos = list.veiculos
            if (vid > 0) {
                selectedVeiculo = list.veiculos.find { it.id == vid }
            }
        }
        loading = false
    }

    LaunchedEffect(Unit) {
        while (true) {
            val n = withContext(Dispatchers.IO) {
                GpsPendingQueue.pendingCount(context.applicationContext)
            }
            diagText = GpsDiagnostics.formatLines(context.applicationContext, Api.baseUrl, n)
            delay(2500)
        }
    }

    BackHandler(onBack = onBack)

    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.config_title)) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            Icons.Filled.ArrowBack,
                            contentDescription = stringResource(R.string.config_back),
                        )
                    }
                },
            )
        },
    ) { pad ->
        if (loading) return@Scaffold
        Column(
            Modifier
                .fillMaxSize()
                .padding(pad)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
        ) {
            Text(stringResource(R.string.config_gps_section_title), style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            Text(
                stringResource(R.string.config_gps_section_help),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(6.dp))
            Text(
                stringResource(R.string.config_system_locale_note),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.outline,
            )
            Spacer(Modifier.height(12.dp))

            ExposedDropdownMenuBox(
                expanded = dropdownExpanded,
                onExpandedChange = { dropdownExpanded = it },
            ) {
                OutlinedTextField(
                    modifier = Modifier
                        .menuAnchor()
                        .fillMaxWidth(),
                    readOnly = true,
                    value = selectedVeiculo?.let { v -> "${v.placa} — ${v.modelo ?: ""}".trimEnd() }
                        ?: stringResource(R.string.config_vehicle_placeholder),
                    onValueChange = {},
                    label = { Text(stringResource(R.string.config_vehicle_label)) },
                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = dropdownExpanded) },
                )
                ExposedDropdownMenu(
                    expanded = dropdownExpanded,
                    onDismissRequest = { dropdownExpanded = false },
                ) {
                    veiculos.forEach { v ->
                        DropdownMenuItem(
                            text = { Text("${v.placa} — ${v.modelo ?: ""}".trimEnd()) },
                            onClick = {
                                selectedVeiculo = v
                                dropdownExpanded = false
                                scope.launch {
                                    if (trackingOn) {
                                        gpsPrefs.setTracking(true, v.id)
                                        GpsForegroundService.stop(context.applicationContext)
                                        GpsForegroundService.start(context.applicationContext, v.id)
                                    }
                                }
                            },
                        )
                    }
                }
            }

            Spacer(Modifier.height(12.dp))
            GpsDeviceStatusCard()
            Spacer(Modifier.height(12.dp))
            val switchDesc = stringResource(R.string.config_gps_switch_desc)
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(stringResource(R.string.config_gps_switch_label))
                Switch(
                    checked = trackingOn,
                    onCheckedChange = { on ->
                        if (on) {
                            if (selectedVeiculo == null) {
                                gpsHint = context.getString(R.string.config_gps_err_no_vehicle)
                                return@Switch
                            }
                            gpsHint = null
                            val perms = mutableListOf(
                                Manifest.permission.ACCESS_FINE_LOCATION,
                                Manifest.permission.ACCESS_COARSE_LOCATION,
                            )
                            if (Build.VERSION.SDK_INT >= 33) {
                                perms.add(Manifest.permission.POST_NOTIFICATIONS)
                            }
                            permLauncher.launch(perms.toTypedArray())
                        } else {
                            trackingOn = false
                            scope.launch {
                                gpsPrefs.setTracking(false, selectedVeiculo?.id ?: -1)
                                GpsForegroundService.stop(context.applicationContext)
                                GpsPendingSyncWorker.cancel(context.applicationContext)
                                gpsHint = null
                            }
                        }
                    },
                    modifier = Modifier.semantics { contentDescription = switchDesc },
                )
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                Spacer(Modifier.height(12.dp))
                Text(
                    stringResource(R.string.config_gps_android10_help),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(8.dp))
                OutlinedButton(
                    onClick = {
                        bgLauncher.launch(Manifest.permission.ACCESS_BACKGROUND_LOCATION)
                    },
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(stringResource(R.string.config_gps_background_btn))
                }
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                Spacer(Modifier.height(12.dp))
                Text(
                    stringResource(R.string.config_gps_android13_help),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(Modifier.height(12.dp))
            OutlinedButton(
                onClick = {
                    val intent = Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS).apply {
                        data = Uri.fromParts("package", context.packageName, null)
                    }
                    runCatching { context.startActivity(intent) }
                },
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(stringResource(R.string.config_app_details_btn))
            }
            Text(
                stringResource(R.string.config_app_details_help),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = 6.dp),
            )
            gpsHint?.let {
                Spacer(Modifier.height(8.dp))
                Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }

            Spacer(Modifier.height(16.dp))
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(12.dp)) {
                    Text(
                        stringResource(R.string.config_gps_diag_title),
                        style = MaterialTheme.typography.titleSmall,
                    )
                    Spacer(Modifier.height(6.dp))
                    Text(
                        diagText,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Spacer(Modifier.height(6.dp))
                    Text(
                        stringResource(R.string.config_gps_diag_help),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline,
                    )
                }
            }

            Spacer(Modifier.height(28.dp))
            Divider()
            Spacer(Modifier.height(20.dp))

            Text(stringResource(R.string.config_api_section), style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = url,
                onValueChange = { url = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text(stringResource(R.string.config_api_hint)) },
                singleLine = false,
                minLines = 2,
            )
            Spacer(Modifier.height(8.dp))
            Text(
                stringResource(R.string.config_api_help),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(24.dp))
            Button(
                onClick = {
                    scope.launch {
                        authRepository.setApiBaseUrl(url)
                        saved = true
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) {
                Icon(Icons.Filled.Save, contentDescription = null, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(8.dp))
                Text(stringResource(R.string.config_save_url))
            }
            if (saved) {
                Spacer(Modifier.height(8.dp))
                Text(
                    stringResource(R.string.config_url_saved),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
        }
    }
}
