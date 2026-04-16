package com.sistemafrotas.motorista.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query

@Dao
interface ChecklistDao {
    @Query("SELECT * FROM checklists_cache ORDER BY dataChecklist DESC, id DESC")
    fun getAll(): List<ChecklistEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    fun insertAll(items: List<ChecklistEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOne(item: ChecklistEntity)

    @Query("DELETE FROM checklists_cache")
    fun deleteAll()
}
