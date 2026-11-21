package com.sistemafrotas.motorista.feature.viagem.domain

/** Evento na linha do tempo automática da viagem. */
data class TripEvent(
    val id: String,
    val rotaId: Int,
    val tipo: String,
    val titulo: String,
    val detalhe: String? = null,
    val timestampMs: Long = System.currentTimeMillis(),
    val lat: Double? = null,
    val lng: Double? = null,
)
