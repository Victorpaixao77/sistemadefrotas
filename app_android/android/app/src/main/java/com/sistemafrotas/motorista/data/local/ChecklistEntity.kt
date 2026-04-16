package com.sistemafrotas.motorista.data.local

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "checklists_cache")
data class ChecklistEntity(
    @PrimaryKey val id: Int,
    val rotaId: Int?,
    val veiculoId: Int?,
    val dataChecklist: String?,
    val placa: String?,
    val cidadeOrigemNome: String?,
    val cidadeDestinoNome: String?,
    val syncedAt: Long = System.currentTimeMillis(),
)
