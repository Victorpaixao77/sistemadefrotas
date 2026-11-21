package com.sistemafrotas.motorista.feature.viagem.ui

import android.content.Context
import android.content.Intent
import android.graphics.Paint
import android.graphics.pdf.PdfDocument
import androidx.core.content.FileProvider
import com.sistemafrotas.motorista.feature.viagem.viewmodel.TripModeUiState
import com.sistemafrotas.motorista.feature.viagem.viewmodel.TripModeViewModel
import java.io.File

object TripReportPdf {
    fun sharePdf(context: Context, ui: TripModeUiState, vm: TripModeViewModel) {
        val file = buildPdf(context, ui, vm) ?: return
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
        val intent = Intent(Intent.ACTION_SEND).apply {
            type = "application/pdf"
            putExtra(Intent.EXTRA_STREAM, uri)
            putExtra(Intent.EXTRA_SUBJECT, "Relatório de viagem")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(intent, "Compartilhar PDF"))
    }

    private fun buildPdf(context: Context, ui: TripModeUiState, vm: TripModeViewModel): File? {
        return try {
            val doc = PdfDocument()
            val pageInfo = PdfDocument.PageInfo.Builder(595, 842, 1).create()
            val page = doc.startPage(pageInfo)
            val canvas = page.canvas
            val paint = Paint().apply { textSize = 12f }
            val titlePaint = Paint().apply { textSize = 18f; isFakeBoldText = true }
            var y = 40f
            fun line(text: String, bold: Boolean = false) {
                canvas.drawText(text, 40f, y, if (bold) titlePaint else paint)
                y += if (bold) 24f else 18f
            }
            line("Relatório da viagem — Frotec", bold = true)
            ui.session?.let { s ->
                line("Origem: ${s.origem ?: "—"}")
                line("Destino: ${s.destino ?: "—"}")
                line("Placa: ${s.placa ?: "—"}")
            }
            line("Duração: ${ui.elapsedLabel}")
            line("Abastecimentos: ${ui.abastecimentosCount}")
            line("Dirigindo: ${ui.jornadaDirigindoMin} min · Parado: ${ui.jornadaParadoMin} min")
            y += 8f
            line("Linha do tempo:", bold = true)
            ui.events.forEach { ev ->
                if (y > 800f) return@forEach
                line("• ${vm.formatTime(ev.timestampMs)} — ${ev.titulo}")
            }
            doc.finishPage(page)
            val dir = File(context.cacheDir, "relatorios").apply { mkdirs() }
            val out = File(dir, "viagem_${System.currentTimeMillis()}.pdf")
            out.outputStream().use { doc.writeTo(it) }
            doc.close()
            out
        } catch (_: Exception) {
            null
        }
    }
}
