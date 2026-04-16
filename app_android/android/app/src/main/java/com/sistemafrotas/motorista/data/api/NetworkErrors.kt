package com.sistemafrotas.motorista.data.api

import retrofit2.HttpException
import java.io.IOException
import java.net.SocketTimeoutException
import java.net.UnknownHostException

/** Mensagens amigáveis para falhas de rede e HTTP comuns no app. */
object NetworkErrors {

    fun message(t: Throwable): String {
        return when (t) {
            is HttpException -> httpHint(t.code(), null)
            is UnknownHostException ->
                "Sem conexão com a internet ou o endereço do servidor está incorreto."
            is SocketTimeoutException ->
                "Tempo esgotado. Verifique a rede ou tente novamente."
            is IOException -> {
                val m = t.message ?: ""
                when {
                    m.contains("Unable to resolve host", ignoreCase = true) ||
                        m.contains("Network is unreachable", ignoreCase = true) ->
                        "Sem conexão com a internet."
                    m.contains("Failed to connect", ignoreCase = true) ||
                        m.contains("Connection refused", ignoreCase = true) ||
                        m.contains("ECONNREFUSED", ignoreCase = true) ->
                        "Não foi possível conectar ao servidor. Ele pode estar offline."
                    m.contains("SSL", ignoreCase = true) || m.contains("Certificate", ignoreCase = true) ->
                        "Falha de segurança na conexão (HTTPS). Verifique o certificado do servidor."
                    else ->
                        "Erro de rede: ${t.message?.take(120)}"
                }
            }
            else -> t.message?.take(200) ?: "Erro desconhecido."
        }
    }

    fun httpHint(code: Int, serverMessage: String?): String {
        val base = serverMessage?.takeIf { it.isNotBlank() }
        return when (code) {
            400 -> base ?: "Dados inválidos. Verifique os campos e tente de novo."
            401 -> base ?: "Sessão expirada ou não autorizado. Faça login novamente."
            403 -> base ?: "Você não tem permissão para esta ação."
            404 -> base ?: "Recurso não encontrado no servidor."
            408, 504 -> base ?: "O servidor demorou demais a responder. Tente novamente."
            429 -> base ?: "Muitas requisições. Aguarde um momento e tente de novo."
            503 -> base ?: "Serviço temporariamente indisponível."
            in 500..599 -> base ?: "Erro no servidor. Tente novamente mais tarde."
            else -> base ?: "Erro $code."
        }
    }
}
