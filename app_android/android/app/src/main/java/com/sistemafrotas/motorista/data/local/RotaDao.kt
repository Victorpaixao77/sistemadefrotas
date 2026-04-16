package com.sistemafrotas.motorista.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query

@Dao
interface RotaDao {
    @Query("SELECT * FROM rotas_cache ORDER BY dataRota DESC, id DESC")
    fun getAll(): List<RotaEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    fun insertAll(rotas: List<RotaEntity>)

    @Query("DELETE FROM rotas_cache")
    fun deleteAll()

    @Query("DELETE FROM rotas_cache WHERE id = :id")
    suspend fun deleteById(id: Int)
}
