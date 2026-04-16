package com.sistemafrotas.motorista

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.BatteryManager
import android.location.Location
import android.os.Handler
import android.os.SystemClock
import android.os.Build
import android.os.IBinder
import android.os.Looper
import android.util.Log
import android.content.pm.ServiceInfo
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.sistemafrotas.motorista.data.AuthDataStore
import com.sistemafrotas.motorista.data.GpsDiagnostics
import com.sistemafrotas.motorista.data.GpsNotifyHelper
import com.sistemafrotas.motorista.data.GpsPendingQueue
import com.sistemafrotas.motorista.data.GpsPreferencesStore
import com.sistemafrotas.motorista.data.api.Api
import com.sistemafrotas.motorista.data.api.readApiMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Serviço em primeiro plano: envia posição ao backend a cada ~10–15 s (em movimento).
 * Requer veículo selecionado nas configurações e token de motorista válido.
 */
class GpsForegroundService : Service() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private lateinit var fused: com.google.android.gms.location.FusedLocationProviderClient
    private var locationCallback: LocationCallback? = null

    /** Buffer em memória: envia em lote (até 25) ou a cada ~30 s. */
    private val serviceBuffer = mutableListOf<QueuedGpsPoint>()
    private val serviceBufferLock = Any()
    private val flushHandler = Handler(Looper.getMainLooper())
    private val periodicFlushRunnable = object : Runnable {
        override fun run() {
            scope.launch { flushServiceBuffer() }
            flushHandler.postDelayed(this, 30_000L)
        }
    }
    private val debouncedFlushRunnable = Runnable {
        scope.launch(Dispatchers.IO) { flushServiceBuffer() }
    }

    /** Reduz volume no servidor/bateria: envia só se deslocou ≥ 30 m ou passaram ≥ 30 s (parado ~2 min). */
    private var lastSentLat: Double? = null
    private var lastSentLng: Double? = null
    private var lastSentElapsedMs: Long = 0L
    /** Último envio com velocidade baixa → próximo intervalo mínimo maior (economia). */
    private var lastSentSlowMoving: Boolean = false

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        fused = LocationServices.getFusedLocationProviderClient(this)
        ensureNotificationChannel()
    }

    private fun ensureNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val ch = NotificationChannel(
            CHANNEL_ID,
            getString(R.string.gps_notif_channel_name),
            NotificationManager.IMPORTANCE_LOW,
        )
        (getSystemService(NOTIFICATION_SERVICE) as NotificationManager).createNotificationChannel(ch)
    }

    private fun buildTrackingNotification(): android.app.Notification {
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.gps_notif_title))
            .setContentText(getString(R.string.gps_notif_text))
            .setSmallIcon(android.R.drawable.ic_menu_mylocation)
            .setOngoing(true)
            .build()
    }

    /** Obrigatório após [startForegroundService]: chamar em poucos segundos, antes de qualquer validação. */
    private fun enterForeground(notif: android.app.Notification) {
        Log.d(TAG, "enterForeground: SDK=${Build.VERSION.SDK_INT}")
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                // No Android 10+ (Q), o foregroundServiceType é obrigatório para localização.
                // No Android 14+ (U), o sistema valida se a permissão foi concedida ANTES de chamar este método.
                startForeground(
                    NOTIF_ID,
                    notif,
                    ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION
                )
            } else {
                startForeground(NOTIF_ID, notif)
            }
            Log.d(TAG, "enterForeground: Sucesso")
        } catch (e: Exception) {
            Log.e(TAG, "enterForeground: Erro ao iniciar foreground", e)
            // Se falhar (ex: falta de permissão no Android 14), o sistema matará o processo.
        }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        Log.d(TAG, "onStartCommand: action=${intent?.action} startId=$startId")

        // 1. Chamar startForeground IMEDIATAMENTE. O Android 14+ é extremamente rigoroso.
        try {
            ensureNotificationChannel()
            enterForeground(buildTrackingNotification())
        } catch (e: Exception) {
            Log.e(TAG, "Falha crítica ao entrar em foreground", e)
            // Se falhou aqui, o processo provavelmente será morto pelo SO em breve.
            stopSelf()
            return START_NOT_STICKY
        }

        // 2. Tratar pedido de parada.
        if (intent?.action == ACTION_STOP) {
            Log.d(TAG, "onStartCommand: Parando serviço via ACTION_STOP")
            stopLocationUpdates()
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
            return START_NOT_STICKY
        }

        // 3. Validar se é um início legítimo.
        if (intent?.action != ACTION_START) {
            Log.w(TAG, "onStartCommand: Action desconhecida, parando.")
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
            return START_NOT_STICKY
        }

        val vid = intent.getIntExtra(EXTRA_VEICULO_ID, -1)
        if (vid <= 0) {
            Log.w(TAG, "onStartCommand: veiculo_id inválido ($vid), parando.")
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
            return START_NOT_STICKY
        }

        Log.i(TAG, "Rastreamento iniciado para veículo ID: $vid")
        lastSentLat = null
        lastSentLng = null
        lastSentElapsedMs = 0L
        lastSentSlowMoving = false
        synchronized(serviceBufferLock) { serviceBuffer.clear() }
        flushHandler.removeCallbacks(periodicFlushRunnable)
        flushHandler.removeCallbacks(debouncedFlushRunnable)
        flushHandler.postDelayed(periodicFlushRunnable, 30_000L)
        startLocationUpdates(vid)
        return START_STICKY
    }

    private fun startLocationUpdates(veiculoId: Int) {
        stopLocationUpdates()
        Log.i(TAG, "Iniciando requestLocationUpdates para veiculoId=$veiculoId")
        val request = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 10_000L)
            .setMinUpdateIntervalMillis(5_000L)
            .setMinUpdateDistanceMeters(0f)
            .build()
        
        val cb = object : LocationCallback() {
            override fun onLocationResult(result: LocationResult) {
                val loc = result.lastLocation
                if (loc == null) {
                    Log.d(TAG, "onLocationResult: localizacao nula")
                    return
                }
                Log.d(TAG, "onLocationResult: lat=${loc.latitude}, lng=${loc.longitude}, acc=${loc.accuracy}m")
                scope.launch {
                    postLocation(veiculoId, loc)
                }
            }

            override fun onLocationAvailability(avail: com.google.android.gms.location.LocationAvailability) {
                Log.d(TAG, "onLocationAvailability: isLocationAvailable=${avail.isLocationAvailable}")
            }
        }
        locationCallback = cb
        try {
            val task = fused.requestLocationUpdates(request, cb, Looper.getMainLooper())
            task.addOnSuccessListener {
                Log.i(TAG, "fused.requestLocationUpdates: Sucesso na solicitação")
            }
            task.addOnFailureListener { e ->
                Log.e(TAG, "fused.requestLocationUpdates: Falha na solicitação", e)
            }
        } catch (e: SecurityException) {
            Log.e(TAG, "requestLocationUpdates: Falha por falta de permissão", e)
            GpsNotifyHelper.notifyTrackingStoppedNoPermission(applicationContext)
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
        } catch (e: Exception) {
            Log.e(TAG, "requestLocationUpdates: Erro inesperado", e)
        }
    }

    private fun shouldSendNetworkPoint(loc: Location): Boolean {
        val lat = lastSentLat
        val lng = lastSentLng
        if (lat == null || lng == null) return true
        val now = SystemClock.elapsedRealtime()
        val minInterval = if (lastSentSlowMoving) 120_000L else 30_000L
        if (now - lastSentElapsedMs >= minInterval) return true
        val dist = FloatArray(1)
        Location.distanceBetween(lat, lng, loc.latitude, loc.longitude, dist)
        return dist[0] >= 30f
    }

    private fun markSent(loc: Location, slowMoving: Boolean) {
        lastSentLat = loc.latitude
        lastSentLng = loc.longitude
        lastSentElapsedMs = SystemClock.elapsedRealtime()
        lastSentSlowMoving = slowMoving
    }

    @Suppress("DEPRECATION")
    private fun locationIsMock(loc: Location): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            loc.isMock
        } else {
            loc.isFromMockProvider
        }
    }

    private suspend fun postLocation(veiculoId: Int, loc: Location) {
        val shouldSend = shouldSendNetworkPoint(loc)
        Log.v(TAG, "postLocation: lat=${loc.latitude}, lng=${loc.longitude}, shouldSend=$shouldSend")
        if (!shouldSend) {
            return
        }
        val store = AuthDataStore(applicationContext)
        val motoristaId = store.getMotoristaId() ?: run {
            GpsDiagnostics.recordSend(
                applicationContext,
                loc.latitude,
                loc.longitude,
                false,
                "Sem sessão: faça login novamente no app.",
            )
            Log.w(TAG, "postLocation: motorista_id ausente")
            return
        }
        val kmh = if (loc.hasSpeed() && loc.speed >= 0) (loc.speed * 3.6f).toDouble() else null
        val slowMoving = (kmh ?: 0.0) < 5.0
        val bateriaPct = readBatteryPercent(applicationContext)
        if (bateriaPct != null && bateriaPct <= 15) {
            scope.launch {
                val prefs = GpsPreferencesStore(applicationContext)
                val last = prefs.getLastBatteryWarnMillis()
                if (System.currentTimeMillis() - last > 4 * 60 * 60 * 1000L) {
                    prefs.setLastBatteryWarnMillis()
                    GpsNotifyHelper.notifyBatteryLowWhileTracking(applicationContext)
                }
            }
        }
        val sdf = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
        val dh = sdf.format(Date(loc.time))
        val accM = if (loc.hasAccuracy() && loc.accuracy > 0f) loc.accuracy else null
        val mockInt = if (locationIsMock(loc)) 1 else null

        val point = QueuedGpsPoint(
            veiculoId = veiculoId,
            motoristaId = motoristaId,
            lat = loc.latitude,
            lng = loc.longitude,
            kmh = kmh,
            dataHora = dh,
            bateriaPct = bateriaPct,
            accM = accM,
            provider = "fused",
            locationMock = mockInt,
        )

        var flushNow = false
        synchronized(serviceBufferLock) {
            serviceBuffer.add(point)
            flushNow = serviceBuffer.size >= 25
        }
        flushHandler.removeCallbacks(debouncedFlushRunnable)
        if (flushNow) {
            scope.launch(Dispatchers.IO) { flushServiceBuffer() }
        } else {
            flushHandler.postDelayed(debouncedFlushRunnable, 2_500L)
        }

        try {
            if (Api.getToken().isNullOrBlank()) {
                store.getToken()?.let { Api.setToken(it) }
            }
            GpsPendingQueue.flush(applicationContext)
            // Confirma envio na rede (lote ou buffer periódico); evita reenvio duplicado imediato.
            markSent(loc, slowMoving)
        } catch (e: Exception) {
            val detail = "Exceção: ${e.javaClass.simpleName}: ${e.message?.take(200)}"
            GpsDiagnostics.recordSend(applicationContext, loc.latitude, loc.longitude, false, detail)
            Log.e(TAG, "postLocation", e)
            // Ponto já está no buffer em memória; flush periódico ou falha do lote envia à fila persistente.
            markSent(loc, slowMoving)
        }
    }

    private suspend fun flushServiceBuffer() {
        val batch: List<QueuedGpsPoint>
        synchronized(serviceBufferLock) {
            if (serviceBuffer.isEmpty()) return
            batch = serviceBuffer.take(25).toList()
            repeat(batch.size) { serviceBuffer.removeAt(0) }
        }
        val pontos = batch.map { it.toMap() }
        val first = batch.firstOrNull() ?: return
        try {
            if (Api.getToken().isNullOrBlank()) {
                AuthDataStore(applicationContext).getToken()?.let { Api.setToken(it) }
            }
            val res = Api.service.salvarGpsLote(mapOf("pontos" to pontos))
            val ok = res.isSuccessful && res.body()?.success == true
            val loteData = res.body()?.data
            val salvos = loteData?.salvos ?: 0
            val indicesOk = loteData?.indicesOk
            val serverMsg = res.readApiMessage().ifBlank { "resposta inválida" }
            val detail = if (ok) {
                "HTTP ${res.code()} · lote salvos=$salvos"
            } else {
                "HTTP ${res.code()} · $serverMsg"
            }
            GpsDiagnostics.recordSend(applicationContext, first.lat, first.lng, ok, detail)
            Log.d(TAG, "salvarGpsLote ok=$ok $detail")
            if (ok && salvos > 0) {
                try {
                    GpsPreferencesStore(applicationContext).setLastUploadSuccessMillis()
                } catch (_: Exception) { /* DataStore */ }
            }
            if (!ok) {
                val code = res.code()
                val shouldQueue = code >= 500 || code == 429
                if (shouldQueue) {
                    batch.forEach { p ->
                        GpsPendingQueue.enqueue(
                            applicationContext, p.veiculoId, p.motoristaId, p.lat, p.lng, p.kmh, p.dataHora,
                            p.bateriaPct, p.accM, p.provider, p.locationMock,
                        )
                    }
                } else {
                    Log.w(TAG, "GPS lote não reenfileirado (HTTP $code): $serverMsg")
                }
            } else if (salvos < batch.size) {
                synchronized(serviceBufferLock) {
                    if (indicesOk != null && indicesOk.isNotEmpty()) {
                        val okSet = indicesOk.toSet()
                        val failed = batch.filterIndexed { idx, _ -> idx !in okSet }
                        serviceBuffer.addAll(0, failed)
                    } else {
                        serviceBuffer.addAll(0, batch)
                    }
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "salvarGpsLote", e)
            batch.forEach { p ->
                GpsPendingQueue.enqueue(
                    applicationContext, p.veiculoId, p.motoristaId, p.lat, p.lng, p.kmh, p.dataHora,
                    p.bateriaPct, p.accM, p.provider, p.locationMock,
                )
            }
        }
    }

    /** Percentual 0–100 da bateria do aparelho (melhor esforço). */
    private fun readBatteryPercent(ctx: Context): Int? {
        val bm = ctx.getSystemService(Context.BATTERY_SERVICE) as BatteryManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            val cap = bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
            if (cap in 0..100) return cap
        }
        @Suppress("DEPRECATION")
        val sticky = ctx.registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
        val level = sticky?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
        val scale = sticky?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
        if (level >= 0 && scale > 0) {
            val pct = (level * 100L / scale).toInt().coerceIn(0, 100)
            return pct
        }
        return null
    }

    private fun stopLocationUpdates() {
        locationCallback?.let { fused.removeLocationUpdates(it) }
        locationCallback = null
        flushHandler.removeCallbacks(periodicFlushRunnable)
        flushHandler.removeCallbacks(debouncedFlushRunnable)
        runBlocking(Dispatchers.IO) {
            repeat(40) {
                val empty = synchronized(serviceBufferLock) { serviceBuffer.isEmpty() }
                if (empty) return@runBlocking
                flushServiceBuffer()
            }
        }
    }

    override fun onDestroy() {
        stopLocationUpdates()
        scope.cancel()
        super.onDestroy()
    }

    private data class QueuedGpsPoint(
        val veiculoId: Int,
        val motoristaId: Int,
        val lat: Double,
        val lng: Double,
        val kmh: Double?,
        val dataHora: String,
        val bateriaPct: Int?,
        val accM: Float?,
        val provider: String,
        val locationMock: Int?,
    ) {
        fun toMap(): Map<String, Any?> {
            val m = mutableMapOf<String, Any?>(
                "veiculo_id" to veiculoId,
                "motorista_id" to motoristaId,
                "latitude" to lat,
                "longitude" to lng,
                "data_hora" to dataHora,
            )
            if (kmh != null) m["velocidade"] = kmh
            if (bateriaPct != null) m["bateria_pct"] = bateriaPct
            if (accM != null && accM > 0f) m["accuracy_metros"] = accM
            m["provider"] = provider
            if (locationMock != null) m["location_mock"] = locationMock
            return m
        }
    }

    companion object {
        private const val TAG = "SfGps"
        const val CHANNEL_ID = "gps_tracking"
        const val NOTIF_ID = 7101
        const val ACTION_START = "com.sistemafrotas.motorista.GPS_START"
        const val ACTION_STOP = "com.sistemafrotas.motorista.GPS_STOP"
        const val EXTRA_VEICULO_ID = "veiculo_id"

        fun start(ctx: Context, veiculoId: Int) {
            val i = Intent(ctx, GpsForegroundService::class.java).apply {
                action = ACTION_START
                putExtra(EXTRA_VEICULO_ID, veiculoId)
            }
            androidx.core.content.ContextCompat.startForegroundService(ctx, i)
        }

        fun stop(ctx: Context) {
            val i = Intent(ctx, GpsForegroundService::class.java).apply { action = ACTION_STOP }
            ctx.startService(i)
        }
    }
}
