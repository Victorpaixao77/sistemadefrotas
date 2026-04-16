package com.sistemafrotas.motorista.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.gpsPrefsDataStore: DataStore<Preferences> by preferencesDataStore(name = "gps_prefs")

class GpsPreferencesStore(private val context: Context) {

    companion object {
        private val TRACKING_ON = booleanPreferencesKey("gps_tracking_on")
        private val VEICULO_ID = intPreferencesKey("gps_veiculo_id")
        private val LAST_UPLOAD_MS = longPreferencesKey("gps_last_upload_ok_ms")
        private val LAST_BATTERY_WARN_MS = longPreferencesKey("gps_last_battery_warn_ms")
        private val LAST_SYSTEM_GPS_OFF_WARN_MS = longPreferencesKey("gps_last_system_gps_off_warn_ms")
    }

    val trackingFlow: Flow<Pair<Boolean, Int>> = context.gpsPrefsDataStore.data.map { prefs ->
        val on = prefs[TRACKING_ON] == true
        val vid = prefs[VEICULO_ID] ?: -1
        on to vid
    }

    suspend fun getTracking(): Pair<Boolean, Int> = trackingFlow.first()

    suspend fun setTracking(enabled: Boolean, veiculoId: Int) {
        context.gpsPrefsDataStore.edit { prefs ->
            prefs[TRACKING_ON] = enabled
            prefs[VEICULO_ID] = veiculoId
        }
    }

    /** Gravar após envio bem-sucedido ao servidor (lote ou fila). */
    suspend fun setLastUploadSuccessMillis(millis: Long = System.currentTimeMillis()) {
        context.gpsPrefsDataStore.edit { it[LAST_UPLOAD_MS] = millis }
    }

    suspend fun getLastUploadSuccessMillis(): Long =
        context.gpsPrefsDataStore.data.map { it[LAST_UPLOAD_MS] ?: 0L }.first()

    suspend fun getLastBatteryWarnMillis(): Long =
        context.gpsPrefsDataStore.data.map { it[LAST_BATTERY_WARN_MS] ?: 0L }.first()

    suspend fun setLastBatteryWarnMillis(millis: Long = System.currentTimeMillis()) {
        context.gpsPrefsDataStore.edit { it[LAST_BATTERY_WARN_MS] = millis }
    }

    suspend fun getLastSystemGpsOffWarnMillis(): Long =
        context.gpsPrefsDataStore.data.map { it[LAST_SYSTEM_GPS_OFF_WARN_MS] ?: 0L }.first()

    suspend fun setLastSystemGpsOffWarnMillis(millis: Long = System.currentTimeMillis()) {
        context.gpsPrefsDataStore.edit { it[LAST_SYSTEM_GPS_OFF_WARN_MS] = millis }
    }
}
