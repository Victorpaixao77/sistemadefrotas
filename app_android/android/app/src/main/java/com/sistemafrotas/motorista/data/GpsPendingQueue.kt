package com.sistemafrotas.motorista.data



import android.content.Context

import android.util.Log

import com.sistemafrotas.motorista.data.api.Api

import com.sistemafrotas.motorista.data.api.readApiMessage

import com.sistemafrotas.motorista.data.local.DatabaseProvider

import com.sistemafrotas.motorista.data.local.GpsPendingEntity

import kotlinx.coroutines.delay



/**

 * Fila local: grava pontos quando a rede falha e reenvia em flush() (serviço GPS + WorkManager).

 * Usa [gps_salvar_lote.php] em lotes de até 25 pontos para reduzir chamadas HTTP.

 */

object GpsPendingQueue {



    private const val MAX_PENDING = 500

    private const val LOTE_MAX = 25

    private const val TAG = "GpsPendingQueue"



    suspend fun pendingCount(context: Context): Int {

        return DatabaseProvider.get(context).gpsPendingDao().count()

    }



    suspend fun enqueue(

        context: Context,

        veiculoId: Int,

        motoristaId: Int,

        lat: Double,

        lng: Double,

        vel: Double?,

        dataHora: String,

        bateriaPct: Int? = null,

        accuracyMetros: Float? = null,

        provider: String? = null,

        locationMock: Int? = null,

    ) {

        val db = DatabaseProvider.get(context)

        val dao = db.gpsPendingDao()

        if (dao.count() >= MAX_PENDING) return

        dao.insert(

            GpsPendingEntity(

                veiculoId = veiculoId,

                motoristaId = motoristaId,

                latitude = lat,

                longitude = lng,

                velocidade = vel,

                bateriaPct = bateriaPct,

                accuracyMetros = accuracyMetros,

                provider = provider,

                locationMock = locationMock,

                dataHora = dataHora,

            ),

        )

    }



    private fun entityToMap(e: GpsPendingEntity): Map<String, Any?> {

        val m = mutableMapOf<String, Any?>(

            "veiculo_id" to e.veiculoId,

            "motorista_id" to e.motoristaId,

            "latitude" to e.latitude,

            "longitude" to e.longitude,

            "data_hora" to e.dataHora,

        )

        if (e.velocidade != null) m["velocidade"] = e.velocidade

        if (e.bateriaPct != null) m["bateria_pct"] = e.bateriaPct

        if (e.accuracyMetros != null && e.accuracyMetros > 0f) m["accuracy_metros"] = e.accuracyMetros

        if (!e.provider.isNullOrBlank()) m["provider"] = e.provider

        if (e.locationMock != null) m["location_mock"] = e.locationMock

        return m

    }



    suspend fun flush(context: Context) {

        val db = DatabaseProvider.get(context)

        val dao = db.gpsPendingDao()

        val store = AuthDataStore(context.applicationContext)

        if (Api.getToken().isNullOrBlank()) {

            store.getToken()?.let { Api.setToken(it) }

        }

        if (Api.getToken().isNullOrBlank()) return



        while (true) {

            val pending = dao.listOldest(LOTE_MAX)

            if (pending.isEmpty()) return



            val pontos = pending.map { entityToMap(it) }

            try {

                val res = Api.service.salvarGpsLote(mapOf("pontos" to pontos))

                val body = res.body()

                when {

                    res.code() == 401 -> break

                    res.isSuccessful && body != null && body.success -> {

                        val data = body.data
                        val salvos = data?.salvos ?: 0
                        val indicesOk = data?.indicesOk

                        when {
                            indicesOk != null && indicesOk.isNotEmpty() -> {
                                indicesOk.forEach { i ->
                                    if (i in pending.indices) dao.deleteById(pending[i].id)
                                }
                            }
                            salvos == pending.size && salvos > 0 -> {
                                pending.forEach { dao.deleteById(it.id) }
                            }
                            salvos > 0 && salvos < pending.size -> {
                                Log.w(
                                    TAG,
                                    "Lote parcial sem indices_ok: mantendo ${pending.size} linhas (${salvos} salvos)",
                                )
                                delay(2_500L)
                                return
                            }
                            salvos == 0 -> {
                                Log.w(TAG, "Lote aceito mas 0 salvos; aguardando antes de tentar de novo")
                                delay(10_000L)
                                return
                            }
                        }

                        if (salvos > 0) {
                            try {
                                GpsPreferencesStore(context.applicationContext).setLastUploadSuccessMillis()
                            } catch (_: Exception) { /* DataStore */ }
                        }
                    }

                    res.code() in 400..499 && res.code() != 429 -> {

                        Log.w(TAG, "HTTP ${res.code()}, mantendo fila: ${res.readApiMessage()}")

                        delay(2_500L)

                        return

                    }

                    res.code() >= 500 || res.code() == 429 -> {

                        delay(3_000L)

                        return

                    }

                    else -> {

                        delay(1_500L)

                        return

                    }

                }

            } catch (_: Exception) {

                delay(2_000L)

                return

            }

        }

    }

}

