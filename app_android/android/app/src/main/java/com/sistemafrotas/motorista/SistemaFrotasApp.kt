package com.sistemafrotas.motorista

import android.app.Application
import android.content.Context
import android.content.IntentFilter
import android.content.res.Configuration
import android.location.LocationManager
import android.os.Build
import com.sistemafrotas.motorista.data.GpsPreferencesStore
import com.sistemafrotas.motorista.data.GpsSystemLocationNotifier
import com.sistemafrotas.motorista.data.OutboxSyncScheduler
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import java.util.Locale

class SistemaFrotasApp : Application() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private val locationSettingsReceiver = GpsLocationSettingsReceiver()

    override fun onCreate() {
        super.onCreate()
        OutboxSyncScheduler.schedulePeriodic(this)
        registerLocationSettingsReceiver()
        // Ao iniciar o app (ex: após o SO matar o processo em background), 
        // verifica se o rastreamento deveria estar ativo e reinicia o serviço.
        scope.launch(Dispatchers.IO) {
            val prefs = GpsPreferencesStore(applicationContext)
            val (enabled, veiculoId) = prefs.getTracking()
            if (enabled && veiculoId > 0) {
                GpsForegroundService.start(applicationContext, veiculoId)
            }
            GpsSystemLocationNotifier.onPossibleGpsOff(applicationContext)
        }
    }

    private fun registerLocationSettingsReceiver() {
        val filter = IntentFilter(LocationManager.PROVIDERS_CHANGED_ACTION)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            filter.addAction(LocationManager.MODE_CHANGED_ACTION)
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(locationSettingsReceiver, filter, Context.RECEIVER_NOT_EXPORTED)
        } else {
            @Suppress("DEPRECATION")
            registerReceiver(locationSettingsReceiver, filter)
        }
    }

    override fun attachBaseContext(base: Context) {
        super.attachBaseContext(updateLocale(base))
    }

    /** Força recursos do app em português (textos do Material e do app). Os diálogos do sistema seguem o idioma do aparelho. */
    private fun updateLocale(context: Context): Context {
        val locale = Locale.forLanguageTag("pt-BR")
        Locale.setDefault(locale)
        val config = Configuration(context.resources.configuration)
        config.setLocale(locale)
        return context.createConfigurationContext(config)
    }
}
