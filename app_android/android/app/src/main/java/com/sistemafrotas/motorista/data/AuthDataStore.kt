package com.sistemafrotas.motorista.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "auth")

class AuthDataStore(private val context: Context) {

    companion object {
        private val TOKEN = stringPreferencesKey("token")
        private val REFRESH_TOKEN = stringPreferencesKey("refresh_token")
        private val MOTORISTA_ID = stringPreferencesKey("motorista_id")
        private val EMPRESA_ID = stringPreferencesKey("empresa_id")
        private val NOME = stringPreferencesKey("nome")
        private val API_BASE_URL = stringPreferencesKey("api_base_url")
    }

    val token: Flow<String?> = context.dataStore.data.map { it[TOKEN] }
    val nome: Flow<String?> = context.dataStore.data.map { it[NOME] }
    val apiBaseUrl: Flow<String?> = context.dataStore.data.map { it[API_BASE_URL] }

    suspend fun saveLogin(token: String, motoristaId: Int, empresaId: Int, nome: String, refreshToken: String? = null) {
        context.dataStore.edit { prefs ->
            prefs[TOKEN] = token
            prefs[REFRESH_TOKEN] = refreshToken ?: ""
            prefs[MOTORISTA_ID] = motoristaId.toString()
            prefs[EMPRESA_ID] = empresaId.toString()
            prefs[NOME] = nome
        }
    }

    suspend fun getRefreshToken(): String? {
        return context.dataStore.data.map { it[REFRESH_TOKEN] }.first()?.takeIf { it.isNotBlank() }
    }

    suspend fun clear() {
        context.dataStore.edit { it.remove(TOKEN); it.remove(REFRESH_TOKEN); it.remove(MOTORISTA_ID); it.remove(EMPRESA_ID); it.remove(NOME) }
    }

    suspend fun getApiBaseUrl(): String? = context.dataStore.data.map { it[API_BASE_URL] }.first()

    suspend fun setApiBaseUrl(url: String) {
        context.dataStore.edit { prefs ->
            prefs[API_BASE_URL] = url.trim().trimEnd('/')
        }
    }

    suspend fun getToken(): String? {
        return context.dataStore.data.map { it[TOKEN] }.first()
    }

    suspend fun getMotoristaId(): Int? {
        return context.dataStore.data.map { it[MOTORISTA_ID]?.toIntOrNull() }.first()
    }
}
