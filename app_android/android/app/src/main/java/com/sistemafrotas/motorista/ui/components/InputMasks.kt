package com.sistemafrotas.motorista.ui.components

import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation
import java.util.Locale

private val identity = object : OffsetMapping {
    override fun originalToTransformed(offset: Int) = offset
    override fun transformedToOriginal(offset: Int) = offset
}

/**
 * Máscara para valores em R$ (ex: 1.234,56).
 * Aceita apenas dígitos; formata com separador de milhar e 2 decimais.
 */
class CurrencyTransformation(private val locale: Locale = Locale("pt", "BR")) : VisualTransformation {

    override fun filter(text: AnnotatedString): TransformedText {
        val digits = text.text.filter { it.isDigit() }
        if (digits.isEmpty()) return TransformedText(AnnotatedString(""), identity)
        val cents = digits.takeLast(2).padStart(2, '0')
        val intPart = digits.dropLast(2).ifEmpty { "0" }
        val formatted = intPart.reversed().chunked(3).joinToString(".").reversed()
        val display = "R$ $formatted,$cents"
        return TransformedText(
            AnnotatedString(display),
            object : OffsetMapping {
                override fun originalToTransformed(offset: Int): Int = display.length.coerceAtMost(offset + 4)
                override fun transformedToOriginal(offset: Int): Int = digits.length.coerceAtMost((display.take(offset).count { it.isDigit() }))
            }
        )
    }
}

/**
 * Máscara para placa (Mercosul ABC1D23 ou antiga ABC-1234).
 * Aceita letras e números; formata com hífen.
 */
class PlacaTransformation : VisualTransformation {
    override fun filter(text: AnnotatedString): TransformedText {
        val s = text.text.uppercase(Locale.getDefault()).filter { it.isLetterOrDigit() }.take(7)
        val display = when {
            s.length <= 3 -> s
            s.length <= 4 -> "${s.take(3)}-${s.drop(3)}"
            else -> "${s.take(3)}-${s.drop(3)}"
        }
        return TransformedText(
            AnnotatedString(display),
            object : OffsetMapping {
                override fun originalToTransformed(offset: Int) = display.length.coerceAtMost(offset + 2)
                override fun transformedToOriginal(offset: Int) = s.length.coerceAtMost(display.take(offset).count { it.isLetterOrDigit() })
            }
        )
    }
}

/**
 * Máscara para quilometragem (ex: 123.456 ou 123456).
 * Aceita apenas dígitos e uma vírgula/ponto para decimais; opcional separador de milhar.
 */
class KmTransformation : VisualTransformation {
    override fun filter(text: AnnotatedString): TransformedText {
        val filtered = text.text.filter { it.isDigit() || it == ',' || it == '.' }
        val (intPart, decPart) = when {
            filtered.contains('.') -> {
                val i = filtered.indexOf('.')
                filtered.take(i).filter { it.isDigit() } to filtered.drop(i + 1).filter { it.isDigit() }.take(2)
            }
            filtered.contains(',') -> {
                val i = filtered.indexOf(',')
                filtered.take(i).filter { it.isDigit() } to filtered.drop(i + 1).filter { it.isDigit() }.take(2)
            }
            else -> filtered.filter { it.isDigit() } to ""
        }
        val intFormatted = intPart.reversed().chunked(3).joinToString(".").reversed().ifEmpty { "0" }
        val display = if (decPart.isEmpty()) intFormatted else "$intFormatted,$decPart"
        return TransformedText(
            AnnotatedString(display),
            object : OffsetMapping {
                override fun originalToTransformed(offset: Int) = display.length.coerceAtMost(offset + 2)
                override fun transformedToOriginal(offset: Int) = (intPart.length + decPart.length).coerceAtMost(display.take(offset).count { it.isDigit() || it == ',' || it == '.' })
            }
        )
    }
}

/** Remove formatação R$ e retorna número (ex: "R$ 1.234,56" -> 1234.56). */
fun parseCurrencyToDouble(s: String): Double? {
    val digits = s.filter { it.isDigit() }
    if (digits.isEmpty()) return null
    val cents = digits.takeLast(2).padStart(2, '0')
    val intPart = digits.dropLast(2).ifEmpty { "0" }
    return "$intPart.$cents".toDoubleOrNull()
}

/** Remove formatação e retorna string só com dígitos para valor (para enviar à API). */
fun currencyRawDigits(s: String): String = s.filter { it.isDigit() }

/** Formata número para exibição R$ (ex: 1234.56 -> "R$ 1.234,56"). */
fun formatCurrency(value: Double, locale: Locale = Locale("pt", "BR")): String =
    "R$ %,.2f".format(locale, value).replace(',', 'X').replace('.', ',').replace('X', '.')
