package com.sistemafrotas.motorista.data.local

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "abastecimentos_cache")
data class AbastecimentoEntity(
    @PrimaryKey val id: Int,
    val veiculoId: Int?,
    val dataAbastecimento: String?,
    val placa: String?,
    val litros: Double?,
    val valorTotal: Double?,
    val status: String?,
    val syncedAt: Long = System.currentTimeMillis(),
)
