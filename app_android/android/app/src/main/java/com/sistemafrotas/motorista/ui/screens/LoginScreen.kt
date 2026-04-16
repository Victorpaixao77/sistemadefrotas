package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.Image
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Shadow
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.sistemafrotas.motorista.R
import com.sistemafrotas.motorista.data.AuthRepository
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

// Cores Frotec (igual login.php web)
private val FrotecBg = Color(0xFF101522)
private val FrotecCard = Color(0xFF181f2f)
private val FrotecPrimary = Color(0xFF3fa6ff)
private val FrotecPrimaryDark = Color(0xFF1e7ecb)
private val FrotecTextSecondary = Color(0xFFb0b8c9)
private val FrotecInputBg = Color(0xFF141a29)
private val FrotecBorder = Color(0xFF26304a)
private val FrotecSuccess = Color(0xFF28a745)
private val FrotecError = Color(0xFFdc3545)

/** (texto, alpha no bloco — mesmos valores de antes; legibilidade vem de fonte maior, largura e sombra) */
private val frasesFundo = listOf(
    "Simples para quem dirige.\nPoderoso para quem gerencia." to 0.22f,
    "Chega de planilhas!\nGestão de frotas simples e eficiente." to 0.16f,
    "Mais Segurança" to 0.13f,
    "Aumente a Eficiência" to 0.18f,
    "Reduza Custos" to 0.15f,
    "Tenha sua frota sob controle em qualquer lugar." to 0.15f,
    "Transforme dados em decisões inteligentes." to 0.17f,
    "Gestão inteligente que cresce com sua empresa." to 0.14f,
)

private data class FraseLayout(
    val fracX: Float,
    val fracY: Float,
    val centerHoriz: Boolean = false,
    val alignStart: Boolean = true,
)

private val layoutFrases = listOf(
    FraseLayout(0.06f, 0.08f, alignStart = true),
    FraseLayout(0.88f, 0.12f, alignStart = false),
    FraseLayout(0.05f, 0.82f, alignStart = true),
    FraseLayout(0.85f, 0.88f, alignStart = false),
    FraseLayout(0.04f, 0.35f, alignStart = true),
    FraseLayout(0.90f, 0.48f, alignStart = false),
    FraseLayout(0.50f, 0.05f, centerHoriz = true),
    FraseLayout(0.48f, 0.92f, centerHoriz = true),
)

@Composable
fun LoginScreen(
    onLoginSuccess: () -> Unit,
    authRepository: AuthRepository,
    logoutSuccessMessage: String? = null,
    onLogoutMessageShown: () -> Unit = {},
) {
    var usuario by remember { mutableStateOf("") }
    var senha by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val coroutineScope = rememberCoroutineScope()
    val focusUsuario = remember { FocusRequester() }
    val focusSenha = remember { FocusRequester() }

    fun tentarLogin() {
        if (usuario.isBlank() || senha.isBlank()) {
            error = "Preencha e-mail e senha"
            return
        }
        if (loading) return
        loading = true
        error = null
        coroutineScope.launch {
            authRepository.login(usuario, senha)
                .onSuccess { onLoginSuccess() }
                .onFailure { loading = false; error = it.message ?: "Erro ao entrar" }
        }
    }

    LaunchedEffect(Unit) {
        focusUsuario.requestFocus()
    }

    LaunchedEffect(logoutSuccessMessage) {
        if (logoutSuccessMessage != null) {
            delay(3000)
            onLogoutMessageShown()
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(FrotecBg),
    ) {
        // Frases de fundo: mesmas cores de antes (Primary / branco por index % 3); alpha e sombra só para ler melhor
        BoxWithConstraints(Modifier.fillMaxSize()) {
            val maxW = maxWidth
            val maxH = maxHeight
            val blocoW = (maxW * 0.44f).coerceIn(152.dp, 220.dp)
            val sombraTexto = Shadow(
                color = Color.Black.copy(alpha = 0.75f),
                offset = Offset(0f, 1.5f),
                blurRadius = 8f,
            )
            frasesFundo.forEachIndexed { index, (texto, alphaBloco) ->
                val layout = layoutFrases.getOrElse(index) { FraseLayout(0.5f, 0.1f, centerHoriz = true) }
                val xOff = when {
                    layout.centerHoriz -> maxW * layout.fracX - blocoW / 2
                    layout.alignStart -> maxW * layout.fracX
                    else -> maxW * layout.fracX - blocoW
                }
                val alinhamento = when {
                    layout.centerHoriz -> TextAlign.Center
                    layout.alignStart -> TextAlign.Start
                    else -> TextAlign.End
                }
                val corTexto = if (index % 3 == 0) FrotecPrimary else Color.White
                Box(
                    modifier = Modifier
                        .align(Alignment.TopStart)
                        .offset(x = xOff, y = maxH * layout.fracY)
                        .width(blocoW)
                        .alpha(alphaBloco),
                ) {
                    Text(
                        text = texto,
                        textAlign = alinhamento,
                        style = MaterialTheme.typography.bodyMedium.copy(
                            color = corTexto,
                            fontSize = 13.sp,
                            lineHeight = 18.sp,
                            fontWeight = FontWeight.Medium,
                            shadow = sombraTexto,
                        ),
                    )
                }
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Spacer(modifier = Modifier.height(24.dp))

            // Logo Frotec
            Image(
                painter = painterResource(R.drawable.logo_frotec),
                contentDescription = "Logo Frotec",
                modifier = Modifier.size(90.dp),
                contentScale = ContentScale.Fit,
            )
            Spacer(modifier = Modifier.height(18.dp))

            // Título FROTEC (igual web)
            Text(
                text = "FROTEC",
                style = MaterialTheme.typography.headlineLarge,
                color = FrotecPrimary,
                fontWeight = FontWeight.Bold,
                fontSize = 28.sp,
                letterSpacing = 2.sp,
            )
            Text(
                text = "Bem-vindo de volta! Acesse sua conta",
                style = MaterialTheme.typography.bodyLarge,
                color = FrotecTextSecondary,
                modifier = Modifier.padding(top = 8.dp),
            )
            Spacer(modifier = Modifier.height(28.dp))

            // Card do formulário (cor #181f2f)
            Card(
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = FrotecCard),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Column(Modifier.padding(32.dp)) {
                    if (logoutSuccessMessage != null) {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = FrotecSuccess),
                            modifier = Modifier.fillMaxWidth().padding(bottom = 16.dp),
                        ) {
                            Text(
                                text = logoutSuccessMessage,
                                color = Color.White,
                                style = MaterialTheme.typography.bodyMedium,
                                modifier = Modifier.padding(12.dp),
                            )
                        }
                    }

                    OutlinedTextField(
                        value = usuario,
                        onValueChange = { usuario = it; error = null },
                        label = { Text("E-mail", color = FrotecTextSecondary) },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Email,
                            imeAction = ImeAction.Next,
                        ),
                        keyboardActions = KeyboardActions(
                            onNext = { focusSenha.requestFocus() },
                        ),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = Color(0xFFeaf1fb),
                            unfocusedTextColor = Color(0xFFeaf1fb),
                            focusedBorderColor = FrotecPrimary,
                            unfocusedBorderColor = FrotecBorder,
                            focusedLabelColor = FrotecTextSecondary,
                            unfocusedLabelColor = FrotecTextSecondary,
                            cursorColor = FrotecPrimary,
                            focusedContainerColor = FrotecInputBg,
                            unfocusedContainerColor = FrotecInputBg,
                        ),
                        modifier = Modifier
                            .fillMaxWidth()
                            .focusRequester(focusUsuario),
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    OutlinedTextField(
                        value = senha,
                        onValueChange = { senha = it; error = null },
                        label = { Text("Senha", color = FrotecTextSecondary) },
                        singleLine = true,
                        visualTransformation = PasswordVisualTransformation(),
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Password,
                            imeAction = ImeAction.Done,
                        ),
                        keyboardActions = KeyboardActions(
                            onDone = { tentarLogin() },
                        ),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = Color(0xFFeaf1fb),
                            unfocusedTextColor = Color(0xFFeaf1fb),
                            focusedBorderColor = FrotecPrimary,
                            unfocusedBorderColor = FrotecBorder,
                            focusedLabelColor = FrotecTextSecondary,
                            unfocusedLabelColor = FrotecTextSecondary,
                            cursorColor = FrotecPrimary,
                            focusedContainerColor = FrotecInputBg,
                            unfocusedContainerColor = FrotecInputBg,
                        ),
                        modifier = Modifier
                            .fillMaxWidth()
                            .focusRequester(focusSenha),
                    )
                    error?.let { msg ->
                        Spacer(modifier = Modifier.height(12.dp))
                        Text(
                            text = msg,
                            color = FrotecError,
                            style = MaterialTheme.typography.bodyMedium,
                        )
                    }
                    Spacer(modifier = Modifier.height(24.dp))

                    // Botão Entrar (gradiente igual web)
                    Button(
                        onClick = { tentarLogin() },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp),
                        enabled = !loading,
                        colors = ButtonDefaults.buttonColors(containerColor = Color.Transparent),
                        contentPadding = PaddingValues(0.dp),
                        shape = RoundedCornerShape(6.dp),
                        elevation = ButtonDefaults.buttonElevation(defaultElevation = 2.dp),
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxSize()
                                .background(
                                    Brush.horizontalGradient(
                                        colors = listOf(FrotecPrimary, FrotecPrimaryDark),
                                    ),
                                    RoundedCornerShape(6.dp),
                                ),
                            contentAlignment = Alignment.Center,
                        ) {
                            if (loading) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(24.dp),
                                    color = Color.White,
                                )
                            } else {
                                Text(
                                    "Entrar",
                                    color = Color.White,
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 18.sp,
                                )
                            }
                        }
                    }
                }
            }
            Spacer(modifier = Modifier.height(48.dp))
        }
    }
}
