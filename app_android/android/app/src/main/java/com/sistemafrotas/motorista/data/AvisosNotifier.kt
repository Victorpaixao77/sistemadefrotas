package com.sistemafrotas.motorista.data

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

/**
 * Avisos do gestor (nova rota, checklist pendente, documento vencendo) por notificação local.
 * Sem Firebase: o app consulta o servidor periodicamente (AvisosPollingWorker). Cada aviso tem
 * uma "chave" estável guardada em SharedPreferences para não notificar duas vezes.
 */
object AvisosNotifier {

    const val CHANNEL_ID = "avisos"
    private const val PREFS = "avisos_notificados"
    private const val KEY_SET = "chaves"
    private var baseNotifId = 9200

    fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (nm.getNotificationChannel(CHANNEL_ID) != null) return
        val ch = NotificationChannel(CHANNEL_ID, "Avisos", NotificationManager.IMPORTANCE_DEFAULT).apply {
            description = "Novas rotas, checklist pendente e documentos vencendo."
        }
        nm.createNotificationChannel(ch)
    }

    private fun prefs(context: Context) =
        context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    /** Já notificou esta chave? */
    fun jaNotificado(context: Context, chave: String): Boolean =
        prefs(context).getStringSet(KEY_SET, emptySet())?.contains(chave) == true

    fun marcarNotificado(context: Context, chave: String) {
        val p = prefs(context)
        val atual = p.getStringSet(KEY_SET, emptySet())?.toMutableSet() ?: mutableSetOf()
        atual.add(chave)
        // Evita crescer indefinidamente.
        val limitado = if (atual.size > 300) atual.toList().takeLast(200).toMutableSet() else atual
        p.edit().putStringSet(KEY_SET, limitado).apply()
    }

    fun notificar(context: Context, chave: String, titulo: String, texto: String) {
        val app = context.applicationContext
        ensureChannel(app)
        if (!NotificationManagerCompat.from(app).areNotificationsEnabled()) return
        val launch = app.packageManager.getLaunchIntentForPackage(app.packageName)
            ?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        val pi = if (launch != null) {
            PendingIntent.getActivity(
                app, chave.hashCode(), launch,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
            )
        } else null
        val n = NotificationCompat.Builder(app, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(titulo)
            .setContentText(texto)
            .setStyle(NotificationCompat.BigTextStyle().bigText(texto))
            .setContentIntent(pi)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()
        NotificationManagerCompat.from(app).notify(baseNotifId + (chave.hashCode() and 0xFFF), n)
    }
}
