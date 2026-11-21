package com.sistemafrotas.motorista.feature.viagem.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.sistemafrotas.motorista.feature.viagem.domain.TripPhase
import com.sistemafrotas.motorista.feature.viagem.domain.TripSession
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

private val Context.tripSessionDataStore: DataStore<Preferences> by preferencesDataStore(name = "trip_mode_session")

class TripSessionStore(private val context: Context) {

    companion object {
        private val ROTA_ID = intPreferencesKey("trip_rota_id")
        private val PHASE = stringPreferencesKey("trip_phase")
        private val STARTED_AT = longPreferencesKey("trip_started_at")
        private val ENDED_AT = longPreferencesKey("trip_ended_at")
        private val ORIGEM = stringPreferencesKey("trip_origem")
        private val DESTINO = stringPreferencesKey("trip_destino")
        private val CARGA = stringPreferencesKey("trip_carga")
        private val PLACA = stringPreferencesKey("trip_placa")
        private val MOTORISTA = stringPreferencesKey("trip_motorista")
        private val KM_INICIO = stringPreferencesKey("trip_km_inicio")
    }

    val sessionFlow: Flow<TripSession?> = context.tripSessionDataStore.data.map { prefs ->
        val rotaId = prefs[ROTA_ID] ?: return@map null
        if (rotaId < 1) return@map null
        TripSession(
            rotaId = rotaId,
            phase = runCatching { TripPhase.valueOf(prefs[PHASE] ?: TripPhase.EM_VIAGEM.name) }
                .getOrDefault(TripPhase.EM_VIAGEM),
            startedAtMs = prefs[STARTED_AT] ?: 0L,
            endedAtMs = prefs[ENDED_AT],
            origem = prefs[ORIGEM],
            destino = prefs[DESTINO],
            carga = prefs[CARGA],
            placa = prefs[PLACA],
            motorista = prefs[MOTORISTA],
            kmInicio = prefs[KM_INICIO]?.toDoubleOrNull(),
        )
    }

    suspend fun save(session: TripSession) {
        context.tripSessionDataStore.edit { prefs ->
            prefs[ROTA_ID] = session.rotaId
            prefs[PHASE] = session.phase.name
            prefs[STARTED_AT] = session.startedAtMs
            if (session.endedAtMs != null) prefs[ENDED_AT] = session.endedAtMs else prefs.remove(ENDED_AT)
            prefs[ORIGEM] = session.origem ?: ""
            prefs[DESTINO] = session.destino ?: ""
            prefs[CARGA] = session.carga ?: ""
            prefs[PLACA] = session.placa ?: ""
            prefs[MOTORISTA] = session.motorista ?: ""
            prefs[KM_INICIO] = session.kmInicio?.toString() ?: ""
        }
    }

    suspend fun clear() {
        context.tripSessionDataStore.edit { it.clear() }
    }
}
