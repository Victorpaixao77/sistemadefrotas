package com.sistemafrotas.motorista.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query

@Dao
interface AbastecimentoDao {
    @Query("SELECT * FROM abastecimentos_cache ORDER BY dataAbastecimento DESC, id DESC")
    fun getAll(): List<AbastecimentoEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    fun insertAll(items: List<AbastecimentoEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertOne(item: AbastecimentoEntity)

    @Query("DELETE FROM abastecimentos_cache")
    fun deleteAll()
}
