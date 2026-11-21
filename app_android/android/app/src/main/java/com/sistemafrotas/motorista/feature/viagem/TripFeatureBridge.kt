package com.sistemafrotas.motorista.feature.viagem

import com.sistemafrotas.motorista.data.api.OcrCampos

/**
 * Ponte temporária entre Modo Viagem e telas do "Mais" (voz → ocorrência, OCR → formulário).
 * Consumir ao abrir a feature de destino.
 */
object TripFeatureBridge {
    var pendingOcorrenciaDescricao: String? = null
        private set
    var pendingOcorrenciaTipo: String? = null
        private set
    var pendingOcrCampos: OcrCampos? = null
        private set
    var pendingOcrFeature: String? = null
        private set

    fun setVoiceOcorrencia(texto: String) {
        pendingOcorrenciaDescricao = texto.trim()
        pendingOcorrenciaTipo = inferirTipoOcorrencia(texto)
    }

    fun setSmartOcr(tipoOcr: String, campos: OcrCampos) {
        pendingOcrCampos = campos
        pendingOcrFeature = featureParaOcrTipo(tipoOcr)
    }

    fun consumeOcorrencia(): Pair<String?, String?> {
        val desc = pendingOcorrenciaDescricao
        val tipo = pendingOcorrenciaTipo
        pendingOcorrenciaDescricao = null
        pendingOcorrenciaTipo = null
        return desc to tipo
    }

    fun consumeOcr(): Pair<String?, OcrCampos?> {
        val feature = pendingOcrFeature
        val campos = pendingOcrCampos
        pendingOcrFeature = null
        pendingOcrCampos = null
        return feature to campos
    }

    fun inferirTipoOcorrencia(texto: String): String {
        val t = texto.lowercase()
        return when {
            t.contains("acidente") || t.contains("batida") || t.contains("colis") -> "acidente"
            t.contains("multa") -> "multa"
            t.contains("roubo") && t.contains("tentativa") -> "tentativa_roubo"
            t.contains("roubo") || t.contains("assalt") -> "roubo"
            t.contains("pneu") || t.contains("furou") || t.contains("mecân") || t.contains("mecan")
                || t.contains("motor") || t.contains("caminhão quebr") -> "mecanico"
            t.contains("carga") || t.contains("lacre") || t.contains("volume") -> "carga"
            t.contains("bloqueio") || t.contains("interdit") || t.contains("estrada") -> "bloqueio"
            else -> "outro"
        }
    }

    fun featureParaOcrTipo(tipo: String): String = when (tipo) {
        "abastecimento", "painel" -> "abastecimentos"
        "pedagio" -> "pedagios"
        "nota", "lacre" -> "conferencia"
        else -> "abastecimentos"
    }
}
