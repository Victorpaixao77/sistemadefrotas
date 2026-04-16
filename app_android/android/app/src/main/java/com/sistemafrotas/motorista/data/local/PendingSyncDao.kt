package com.sistemafrotas.motorista.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.Query

@Dao
interface PendingSyncDao {

    @Insert
    suspend fun insert(entity: PendingSyncEntity): Long

    @Query("SELECT * FROM pending_sync ORDER BY id ASC LIMIT 1")
    suspend fun peekOldest(): PendingSyncEntity?

    @Query("DELETE FROM pending_sync WHERE id = :id")
    suspend fun deleteById(id: Long)

    @Query("UPDATE pending_sync SET retry_count = retry_count + 1, last_error = :err WHERE id = :id")
    suspend fun bumpRetry(id: Long, err: String?)

    @Query("SELECT COUNT(*) FROM pending_sync")
    suspend fun count(): Int

    @Query("SELECT * FROM pending_sync WHERE operation = :op ORDER BY id ASC")
    suspend fun listByOperation(op: String): List<PendingSyncEntity>

    @Query("SELECT * FROM pending_sync WHERE id = :id LIMIT 1")
    suspend fun getById(id: Long): PendingSyncEntity?

    @Query("UPDATE pending_sync SET payload_json = :json WHERE id = :id")
    suspend fun updatePayloadById(id: Long, json: String)
}
