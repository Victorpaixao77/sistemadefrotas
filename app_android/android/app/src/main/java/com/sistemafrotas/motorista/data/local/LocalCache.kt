package com.sistemafrotas.motorista.data.local

import com.sistemafrotas.motorista.data.OfflineId
import com.sistemafrotas.motorista.data.OutboxPayloadCodec
import com.sistemafrotas.motorista.data.PendingSyncOperation
import com.sistemafrotas.motorista.data.api.AbastecimentoItem
import com.sistemafrotas.motorista.data.api.ChecklistItem
import com.sistemafrotas.motorista.data.api.RotaItem
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * Cache local (Room) para modo offline.
 * Salva dados quando a API retorna sucesso e lê do cache quando a API falha (ex.: sem internet).
 * Mescla listas com alterações pendentes de sincronização ([pending_sync]).
 */
class LocalCache(private val db: AppDatabase) {

    private val rotaDao = db.rotaDao()
    private val abastecimentoDao = db.abastecimentoDao()
    private val checklistDao = db.checklistDao()
    private val pendingSyncDao = db.pendingSyncDao()

    suspend fun enqueuePending(operation: String, payload: Map<String, Any?>): Long = withContext(Dispatchers.IO) {
        pendingSyncDao.insert(
            PendingSyncEntity(
                operation = operation,
                payloadJson = OutboxPayloadCodec.mapToJson(payload),
            ),
        )
    }

    suspend fun removePendingSyncRow(rowId: Long) = withContext(Dispatchers.IO) {
        pendingSyncDao.deleteById(rowId)
    }

    suspend fun removeRotaFromCache(id: Int) = withContext(Dispatchers.IO) {
        rotaDao.deleteById(id)
    }

    /** Mescla campos no payload de um CREATE_ROTA pendente (edição offline antes do envio). */
    suspend fun mergePendingRotaCreatePayload(pendingRowId: Long, newFields: Map<String, Any?>): Boolean =
        withContext(Dispatchers.IO) {
            val row = pendingSyncDao.getById(pendingRowId) ?: return@withContext false
            if (row.operation != PendingSyncOperation.CREATE_ROTA) return@withContext false
            val old = OutboxPayloadCodec.jsonToMap(row.payloadJson)
            val merged = old.toMutableMap()
            for ((k, v) in newFields) {
                if (v != null) merged[k] = v
            }
            pendingSyncDao.updatePayloadById(pendingRowId, OutboxPayloadCodec.mapToJson(merged))
            true
        }

    private suspend fun pendingDeleteRotaIds(): Set<Int> = withContext(Dispatchers.IO) {
        pendingSyncDao.listByOperation(PendingSyncOperation.DELETE_ROTA).mapNotNull { row ->
            runCatching {
                (OutboxPayloadCodec.jsonToMap(row.payloadJson)["id"] as? Number)?.toInt()
            }.getOrNull()
        }.toSet()
    }

    private fun rotaItemFromPayload(rowId: Long, p: Map<String, Any?>): RotaItem {
        val dr = p["data_rota"]?.toString()
        val ds = p["data_saida"]?.toString()
        return RotaItem(
            id = OfflineId.fromPendingRow(rowId),
            veiculoId = (p["veiculo_id"] as? Number)?.toInt(),
            dataRota = dr,
            dataSaida = ds,
            cidadeOrigemNome = "Pendente",
            cidadeDestinoNome = "Aguardando envio",
            placa = null,
            status = "offline",
            distanciaKm = (p["distancia_km"] as? Number)?.toDouble(),
        )
    }

    suspend fun saveRotas(rotas: List<RotaItem>) = withContext(Dispatchers.IO) {
        rotaDao.insertAll(rotas.map { r ->
            RotaEntity(
                id = r.id,
                veiculoId = r.veiculoId,
                dataRota = r.dataRota,
                dataSaida = r.dataSaida,
                cidadeOrigemNome = r.cidadeOrigemNome,
                cidadeDestinoNome = r.cidadeDestinoNome,
                placa = r.placa,
                status = r.status,
                distanciaKm = r.distanciaKm,
            )
        })
    }

    suspend fun loadRotasOffline(): List<RotaItem>? = withContext(Dispatchers.IO) {
        rotaDao.getAll().map { e ->
            RotaItem(
                id = e.id,
                veiculoId = e.veiculoId,
                dataRota = e.dataRota,
                dataSaida = e.dataSaida,
                cidadeOrigemNome = e.cidadeOrigemNome,
                cidadeDestinoNome = e.cidadeDestinoNome,
                placa = e.placa,
                status = e.status,
                distanciaKm = e.distanciaKm,
            )
        }.takeIf { it.isNotEmpty() }
    }

    suspend fun loadRotasOfflineMerged(): List<RotaItem>? = withContext(Dispatchers.IO) {
        val deleted = pendingDeleteRotaIds()
        val fromDb = rotaDao.getAll()
            .map { e ->
                RotaItem(
                    id = e.id,
                    veiculoId = e.veiculoId,
                    dataRota = e.dataRota,
                    dataSaida = e.dataSaida,
                    cidadeOrigemNome = e.cidadeOrigemNome,
                    cidadeDestinoNome = e.cidadeDestinoNome,
                    placa = e.placa,
                    status = e.status,
                    distanciaKm = e.distanciaKm,
                )
            }
            .filter { it.id !in deleted }
        val pendingCreates = pendingSyncDao.listByOperation(PendingSyncOperation.CREATE_ROTA).map { row ->
            val p = OutboxPayloadCodec.jsonToMap(row.payloadJson)
            rotaItemFromPayload(row.id, p)
        }
        val merged = pendingCreates + fromDb
        merged.sortedWith(
            compareByDescending<RotaItem> { it.dataRota ?: "" }.thenByDescending { it.id },
        ).takeIf { it.isNotEmpty() }
    }

    suspend fun saveAbastecimentos(items: List<AbastecimentoItem>) = withContext(Dispatchers.IO) {
        abastecimentoDao.insertAll(items.map { a ->
            AbastecimentoEntity(
                id = a.id,
                veiculoId = a.veiculoId,
                dataAbastecimento = a.dataAbastecimento,
                placa = a.placa,
                litros = a.litros,
                valorTotal = a.valorTotal,
                status = a.status,
            )
        })
    }

    suspend fun loadAbastecimentosOffline(): List<AbastecimentoItem>? = withContext(Dispatchers.IO) {
        abastecimentoDao.getAll().map { e ->
            AbastecimentoItem(
                id = e.id,
                veiculoId = e.veiculoId,
                dataAbastecimento = e.dataAbastecimento,
                placa = e.placa,
                litros = e.litros,
                valorTotal = e.valorTotal,
                status = e.status,
            )
        }.takeIf { it.isNotEmpty() }
    }

    private suspend fun pendingAbastecimentoItems(): List<AbastecimentoItem> = withContext(Dispatchers.IO) {
        val rows = pendingSyncDao.listByOperation(PendingSyncOperation.CREATE_ABASTECIMENTO_JSON) +
            pendingSyncDao.listByOperation(PendingSyncOperation.CREATE_ABASTECIMENTO_MULTIPART)
        rows.map { row ->
            val p = OutboxPayloadCodec.jsonToMap(row.payloadJson)
            AbastecimentoItem(
                id = OfflineId.fromPendingRow(row.id),
                veiculoId = (p["veiculo_id"] as? Number)?.toInt(),
                dataAbastecimento = p["data_abastecimento"]?.toString(),
                placa = null,
                litros = (p["litros"] as? Number)?.toDouble(),
                valorTotal = (p["valor_total"] as? Number)?.toDouble(),
                status = "offline",
            )
        }
    }

    suspend fun loadAbastecimentosOfflineMerged(): List<AbastecimentoItem>? = withContext(Dispatchers.IO) {
        val fromDb = abastecimentoDao.getAll().map { e ->
            AbastecimentoItem(
                id = e.id,
                veiculoId = e.veiculoId,
                dataAbastecimento = e.dataAbastecimento,
                placa = e.placa,
                litros = e.litros,
                valorTotal = e.valorTotal,
                status = e.status,
            )
        }
        val pending = pendingAbastecimentoItems()
        val merged = pending + fromDb
        merged.sortedWith(
            compareByDescending<AbastecimentoItem> { it.dataAbastecimento ?: "" }.thenByDescending { it.id },
        ).takeIf { it.isNotEmpty() }
    }

    suspend fun saveChecklists(items: List<ChecklistItem>) = withContext(Dispatchers.IO) {
        checklistDao.insertAll(items.map { c ->
            ChecklistEntity(
                id = c.id,
                rotaId = c.rotaId,
                veiculoId = c.veiculoId,
                dataChecklist = c.dataChecklist,
                placa = c.placa,
                cidadeOrigemNome = c.cidadeOrigemNome,
                cidadeDestinoNome = c.cidadeDestinoNome,
            )
        })
    }

    suspend fun loadChecklistsOffline(): List<ChecklistItem>? = withContext(Dispatchers.IO) {
        checklistDao.getAll().map { e ->
            ChecklistItem(
                id = e.id,
                rotaId = e.rotaId,
                veiculoId = e.veiculoId,
                dataChecklist = e.dataChecklist,
                placa = e.placa,
                cidadeOrigemNome = e.cidadeOrigemNome,
                cidadeDestinoNome = e.cidadeDestinoNome,
            )
        }.takeIf { it.isNotEmpty() }
    }

    private suspend fun pendingChecklistItems(): List<ChecklistItem> = withContext(Dispatchers.IO) {
        val rotaById = rotaDao.getAll().associateBy { it.id }
        pendingSyncDao.listByOperation(PendingSyncOperation.CREATE_CHECKLIST).map { row ->
            val p = OutboxPayloadCodec.jsonToMap(row.payloadJson)
            val rid = (p["rota_id"] as? Number)?.toInt()
            val r = rid?.let { rotaById[it] }
            ChecklistItem(
                id = OfflineId.fromPendingRow(row.id),
                rotaId = rid,
                veiculoId = (p["veiculo_id"] as? Number)?.toInt(),
                dataChecklist = p["data_checklist"]?.toString(),
                placa = null,
                cidadeOrigemNome = r?.cidadeOrigemNome ?: "—",
                cidadeDestinoNome = r?.cidadeDestinoNome ?: "—",
            )
        }
    }

    suspend fun loadChecklistsOfflineMerged(): List<ChecklistItem>? = withContext(Dispatchers.IO) {
        val fromDb = checklistDao.getAll().map { e ->
            ChecklistItem(
                id = e.id,
                rotaId = e.rotaId,
                veiculoId = e.veiculoId,
                dataChecklist = e.dataChecklist,
                placa = e.placa,
                cidadeOrigemNome = e.cidadeOrigemNome,
                cidadeDestinoNome = e.cidadeDestinoNome,
            )
        }
        val pending = pendingChecklistItems()
        val merged = pending + fromDb
        merged.sortedWith(
            compareByDescending<ChecklistItem> { it.dataChecklist ?: "" }.thenByDescending { it.id },
        ).takeIf { it.isNotEmpty() }
    }
}
