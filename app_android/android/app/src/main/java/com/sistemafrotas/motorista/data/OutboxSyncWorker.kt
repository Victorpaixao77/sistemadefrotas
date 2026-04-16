package com.sistemafrotas.motorista.data

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.sistemafrotas.motorista.data.local.DatabaseProvider
import com.sistemafrotas.motorista.data.local.LocalCache

/**
 * Drena a fila [pending_sync] enquanto houver rede e itens pendentes.
 */
class OutboxSyncWorker(
    appContext: Context,
    params: WorkerParameters,
) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result {
        val ctx = applicationContext
        val dataStore = AuthDataStore(ctx)
        val localCache = LocalCache(DatabaseProvider.get(ctx))
        val authRepo = AuthRepository(dataStore, localCache, ctx)
        authRepo.restoreToken()
        var processed = 0
        while (OutboxSyncProcessor.processNext(ctx)) {
            processed++
            if (processed > 200) break
        }
        if (processed > 0) {
            authRepo.loadRotas(limite = 80)
            authRepo.loadAbastecimentos()
            authRepo.loadChecklists()
        }
        return Result.success()
    }
}
