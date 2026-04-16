package com.sistemafrotas.motorista.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.Query

@Dao
interface GpsPendingDao {

    @Query("SELECT * FROM gps_pending ORDER BY created_at ASC LIMIT :limit")
    suspend fun listOldest(limit: Int): List<GpsPendingEntity>

    @Insert
    suspend fun insert(entity: GpsPendingEntity): Long

    @Query("DELETE FROM gps_pending WHERE id = :id")
    suspend fun deleteById(id: Long)

    @Query("SELECT COUNT(*) FROM gps_pending")
    suspend fun count(): Int
}
