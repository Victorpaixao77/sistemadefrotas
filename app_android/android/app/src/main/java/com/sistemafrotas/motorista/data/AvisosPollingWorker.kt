package com.sistemafrotas.motorista.data

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.sistemafrotas.motorista.data.local.DatabaseProvider
import com.sistemafrotas.motorista.data.local.LocalCache

/**
 * Consulta os avisos do motorista e dispara notificação local para os que ainda não foram avisados.
 * Alternativa a Push/FCM enquanto o Firebase não está configurado.
 */
class AvisosPollingWorker(
    appContext: Context,
    params: WorkerParameters,
) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result {
        val ctx = applicationContext
        return try {
            val dataStore = AuthDataStore(ctx)
            if (dataStore.getToken().isNullOrBlank()) return Result.success()
            val localCache = LocalCache(DatabaseProvider.get(ctx))
            val authRepo = AuthRepository(dataStore, localCache, ctx)
            authRepo.restoreToken()
            authRepo.loadAvisos().onSuccess { resp ->
                resp.avisos.forEach { aviso ->
                    if (!AvisosNotifier.jaNotificado(ctx, aviso.chave)) {
                        AvisosNotifier.notificar(
                            ctx,
                            aviso.chave,
                            aviso.titulo ?: "Aviso",
                            aviso.descricao ?: "",
                        )
                        AvisosNotifier.marcarNotificado(ctx, aviso.chave)
                    }
                }
            }
            Result.success()
        } catch (e: Exception) {
            Result.retry()
        }
    }
}
