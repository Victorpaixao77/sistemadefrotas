package com.sistemafrotas.motorista.feature.viagem.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import com.sistemafrotas.motorista.feature.viagem.domain.TripEvent
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import java.util.UUID

private val Context.tripTimelineDataStore: DataStore<Preferences> by preferencesDataStore(name = "trip_mode_timeline")

class TripTimelineStore(private val context: Context) {

    private val gson = Gson()
    private val listType = object : TypeToken<List<TripEvent>>() {}.type

    companion object {
        private val EVENTS_JSON = stringPreferencesKey("trip_events_json")
    }

    val eventsFlow: Flow<List<TripEvent>> = context.tripTimelineDataStore.data.map { prefs ->
        val raw = prefs[EVENTS_JSON] ?: return@map emptyList()
        runCatching { gson.fromJson<List<TripEvent>>(raw, listType) }.getOrDefault(emptyList())
    }

    suspend fun addEvent(
        rotaId: Int,
        tipo: String,
        titulo: String,
        detalhe: String? = null,
        lat: Double? = null,
        lng: Double? = null,
    ): TripEvent {
        val event = TripEvent(
            id = UUID.randomUUID().toString(),
            rotaId = rotaId,
            tipo = tipo,
            titulo = titulo,
            detalhe = detalhe,
            lat = lat,
            lng = lng,
        )
        context.tripTimelineDataStore.edit { prefs ->
            val current = runCatching {
                gson.fromJson<List<TripEvent>>(prefs[EVENTS_JSON], listType)
            }.getOrDefault(emptyList()).toMutableList()
            current.add(0, event)
            prefs[EVENTS_JSON] = gson.toJson(current.take(200))
        }
        return event
    }

    suspend fun clear() {
        context.tripTimelineDataStore.edit { it.remove(EVENTS_JSON) }
    }
}
