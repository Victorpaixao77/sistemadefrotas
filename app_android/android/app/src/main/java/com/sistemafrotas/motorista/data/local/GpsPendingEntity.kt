package com.sistemafrotas.motorista.data.local

import androidx.room.ColumnInfo
import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "gps_pending")
data class GpsPendingEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    @ColumnInfo(name = "veiculo_id") val veiculoId: Int,
    @ColumnInfo(name = "motorista_id") val motoristaId: Int,
    val latitude: Double,
    val longitude: Double,
    val velocidade: Double?,
    @ColumnInfo(name = "bateria_pct") val bateriaPct: Int? = null,
    @ColumnInfo(name = "accuracy_metros") val accuracyMetros: Float? = null,
    @ColumnInfo(name = "provider") val provider: String? = null,
    @ColumnInfo(name = "location_mock") val locationMock: Int? = null,
    @ColumnInfo(name = "data_hora") val dataHora: String,
    @ColumnInfo(name = "created_at") val createdAt: Long = System.currentTimeMillis(),
)
