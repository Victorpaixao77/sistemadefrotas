package com.sistemafrotas.motorista.feature.viagem.domain

/** Sessão ativa do Modo Viagem (persistida localmente). */
data class TripSession(
    val rotaId: Int,
    val phase: TripPhase = TripPhase.EM_VIAGEM,
    val startedAtMs: Long = System.currentTimeMillis(),
    val endedAtMs: Long? = null,
    val origem: String? = null,
    val destino: String? = null,
    val carga: String? = null,
    val placa: String? = null,
    val motorista: String? = null,
    val kmInicio: Double? = null,
)
