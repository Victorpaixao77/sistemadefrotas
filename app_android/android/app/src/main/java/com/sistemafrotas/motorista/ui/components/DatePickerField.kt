package com.sistemafrotas.motorista.ui.components

import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import java.text.SimpleDateFormat
import java.util.*

private val dateFormatApi = SimpleDateFormat("yyyy-MM-dd", Locale.US)
private val dateFormatDisplay = SimpleDateFormat("dd/MM/yyyy", Locale("pt", "BR"))

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DatePickerField(
    value: String,
    onDateSelected: (String) -> Unit,
    label: String,
    modifier: Modifier = Modifier,
) {
    var showPicker by remember { mutableStateOf(false) }
    key(value) {
        val initialMillis = value.run {
            if (isBlank()) System.currentTimeMillis()
            else try {
                dateFormatApi.parse(trim())?.time ?: System.currentTimeMillis()
            } catch (_: Exception) {
                System.currentTimeMillis()
            }
        }
        val state = rememberDatePickerState(
            initialSelectedDateMillis = initialMillis,
        )

        if (showPicker) {
            DatePickerDialog(
                onDismissRequest = { showPicker = false },
                confirmButton = {
                    TextButton(
                        onClick = {
                            state.selectedDateMillis?.let { ms ->
                                onDateSelected(dateFormatApi.format(Date(ms)))
                                showPicker = false
                            }
                        },
                    ) {
                        Text("OK")
                    }
                },
                dismissButton = {
                    TextButton(onClick = { showPicker = false }) {
                        Text("Cancelar")
                    }
                },
            ) {
                DatePicker(state = state)
            }
        }
    }

    val displayText = value.ifBlank { "" }.run {
        if (isBlank()) "" else try {
            dateFormatApi.parse(trim())?.let { dateFormatDisplay.format(it) } ?: this
        } catch (_: Exception) { this }
    }
    val outlineColor = MaterialTheme.colorScheme.outline
    val shapes = MaterialTheme.shapes

    Box(
        modifier = modifier
            .fillMaxWidth()
            .clickable { showPicker = true }
            .border(1.dp, outlineColor, shapes.small)
            .padding(horizontal = 16.dp, vertical = 16.dp),
    ) {
        Text(
            text = displayText.ifBlank { label },
            style = MaterialTheme.typography.bodyLarge,
            color = if (displayText.isBlank()) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface,
        )
    }
}

/** Formato yyyy-MM-dd para a API. */
fun formatDateForApi(dateMillis: Long): String = dateFormatApi.format(Date(dateMillis))
