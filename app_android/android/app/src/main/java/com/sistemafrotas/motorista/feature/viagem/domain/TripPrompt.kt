package com.sistemafrotas.motorista.feature.viagem.domain

/** Pergunta contextual do copiloto (GPS, posto, pedágio…). */
data class TripPrompt(
    val id: String,
    val titulo: String,
    val mensagem: String,
    val acaoConfirmar: String,
    val acaoNegar: String = "Não",
    /** Chave para abrir fluxo: parada, abastecimento, pedagio, pod, conferencia, ocorrencia */
    val fluxo: String,
)
