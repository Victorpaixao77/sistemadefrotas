package com.sistemafrotas.motorista.data.local

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "rotas_cache")
data class RotaEntity(
    @PrimaryKey val id: Int,
    val veiculoId: Int?,
    val dataRota: String?,
    val dataSaida: String?,
    val cidadeOrigemNome: String?,
    val cidadeDestinoNome: String?,
    val placa: String?,
    val status: String?,
    val distanciaKm: Double?,
    val syncedAt: Long = System.currentTimeMillis(),
)
