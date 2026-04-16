package com.sistemafrotas.motorista.data.api

import com.sistemafrotas.motorista.BuildConfig
import com.sistemafrotas.motorista.data.AuthRepository
import kotlinx.coroutines.runBlocking
import okhttp3.Authenticator
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object Api {

    // Desenvolvimento: HTTP local. Em produção use HTTPS na tela Configurações (ex.: https://seu-dominio/sistema-frotas/app_android/api/).
    var baseUrl: String = "http://10.0.2.2/sistema-frotas/app_android/api/"
        set(value) {
            field = value.trimEnd('/') + "/"
            _retrofit = null
            _refreshRetrofit = null
        }

    private var _retrofit: Retrofit? = null

    /** Retrofit só para refresh: não pode usar o [client] principal (Authenticator + runBlocking = deadlock). */
    private var _refreshRetrofit: Retrofit? = null

    @Volatile
    private var currentToken: String? = null

    /** Define o repositório de auth para o Authenticator tentar refresh em 401. Chamar em MainActivity após criar AuthRepository. */
    var authRepository: AuthRepository? = null

    fun setToken(token: String?) {
        currentToken = token
    }

    fun getToken(): String? = currentToken

    /** Evita segundo refresh no mesmo pedido (loop 401 → Too many follow-up requests). */
    private const val AUTH_RETRY_HEADER = "X-Sf-Auth-Retry"

    private val authInterceptor = Interceptor { chain ->
        val originalRequest = chain.request()
        val token = currentToken

        if (token.isNullOrBlank()) {
            return@Interceptor chain.proceed(originalRequest)
        }

        // Sempre substituir ?token= na URL: em retentativas do OkHttp o URL antigo acumula vários token=
        // e o PHP pode validar o primeiro (expirado) → 401 em loop.
        val urlWithToken = originalRequest.url.newBuilder()
            .removeAllQueryParameters("token")
            .addQueryParameter("token", token)
            .build()

        val requestWithHeader = originalRequest.newBuilder()
            .url(urlWithToken)
            .header("Authorization", "Bearer $token")
            .header("X-Authorization", "Bearer $token")
            .build()

        chain.proceed(requestWithHeader)
    }

    private val loggingInterceptor = HttpLoggingInterceptor().apply {
        level = if (BuildConfig.LOG_API_BODY) HttpLoggingInterceptor.Level.BODY else HttpLoggingInterceptor.Level.NONE
    }

    private val tokenAuthenticator = Authenticator { _, response ->
        if (response.code != 401) return@Authenticator null
        // Já tentámos refresh para este encadeamento — não insistir (limite ~20 follow-ups no OkHttp).
        if (response.request.header(AUTH_RETRY_HEADER) != null) return@Authenticator null

        val refreshed = runBlocking {
            authRepository?.refreshToken()?.isSuccess == true
        }
        if (!refreshed || getToken().isNullOrBlank()) return@Authenticator null

        response.request.newBuilder()
            .header(AUTH_RETRY_HEADER, "1")
            .build()
    }

    private val client = OkHttpClient.Builder()
        .addInterceptor(authInterceptor)
        .addInterceptor(loggingInterceptor)
        .authenticator(tokenAuthenticator)
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .callTimeout(60, TimeUnit.SECONDS)
        .build()

    /** Sem Authenticator nem interceptor de token — só corpo JSON com refresh_token. */
    private val refreshClient = OkHttpClient.Builder()
        .addInterceptor(loggingInterceptor)
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .callTimeout(45, TimeUnit.SECONDS)
        .build()

    private fun retrofit(): Retrofit {
        val currentRetrofit = _retrofit
        if (currentRetrofit != null) return currentRetrofit

        val newRetrofit = Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
        _retrofit = newRetrofit
        return newRetrofit
    }

    private fun refreshRetrofit(): Retrofit {
        _refreshRetrofit?.let { return it }
        val built = Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(refreshClient)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
        _refreshRetrofit = built
        return built
    }

    val service: ApiService get() = retrofit().create(ApiService::class.java)

    /** Chamadas de renovação de token ([AuthRepository.refreshToken]); evita reentrância no Authenticator. */
    val refreshService: ApiService get() = refreshRetrofit().create(ApiService::class.java)
}
