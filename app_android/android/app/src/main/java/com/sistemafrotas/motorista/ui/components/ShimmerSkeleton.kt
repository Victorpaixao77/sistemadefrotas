package com.sistemafrotas.motorista.ui.components

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp

/**
 * Caixa com efeito shimmer (skeleton) para estados de carregamento.
 */
@Composable
fun ShimmerBox(
    modifier: Modifier = Modifier,
    shape: RoundedCornerShape = RoundedCornerShape(8.dp),
) {
    val transition = rememberInfiniteTransition(label = "shimmer")
    val alpha by transition.animateFloat(
        initialValue = 0.3f,
        targetValue = 0.7f,
        animationSpec = infiniteRepeatable(
            animation = tween(800),
            repeatMode = RepeatMode.Reverse
        ),
        label = "alpha"
    )
    Box(
        modifier = modifier
            .clip(shape)
            .background(
                Brush.linearGradient(
                    colors = listOf(
                        Color.Gray.copy(alpha = alpha),
                        Color.Gray.copy(alpha = alpha * 0.6f)
                    ),
                    start = Offset.Zero,
                    end = Offset(100f, 100f)
                )
            )
    )
}

/**
 * Skeleton que imita um item de lista de rotas (card com origem/destino, data, placa).
 */
@Composable
fun RotaListItemSkeleton(modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        ShimmerBox(modifier = Modifier.size(40.dp), shape = RoundedCornerShape(8.dp))
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            ShimmerBox(Modifier.fillMaxWidth(0.7f).height(16.dp))
            Spacer(Modifier.height(6.dp))
            ShimmerBox(Modifier.fillMaxWidth(0.4f).height(14.dp))
            Spacer(Modifier.height(4.dp))
            ShimmerBox(Modifier.fillMaxWidth(0.25f).height(12.dp))
        }
        ShimmerBox(Modifier.width(60.dp).height(28.dp), shape = RoundedCornerShape(16.dp))
    }
}

/**
 * Skeleton que imita um item de lista de abastecimentos.
 */
@Composable
fun AbastecimentoListItemSkeleton(modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        ShimmerBox(modifier = Modifier.size(40.dp), shape = RoundedCornerShape(8.dp))
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            ShimmerBox(Modifier.fillMaxWidth(0.3f).height(16.dp))
            Spacer(Modifier.height(6.dp))
            ShimmerBox(Modifier.fillMaxWidth(0.5f).height(14.dp))
            Spacer(Modifier.height(4.dp))
            ShimmerBox(Modifier.fillMaxWidth(0.2f).height(12.dp))
        }
        ShimmerBox(Modifier.width(50.dp).height(24.dp), shape = RoundedCornerShape(12.dp))
    }
}

/**
 * Skeleton que imita um item de checklist.
 */
@Composable
fun ChecklistListItemSkeleton(modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        ShimmerBox(modifier = Modifier.size(40.dp), shape = RoundedCornerShape(8.dp))
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            ShimmerBox(Modifier.fillMaxWidth(0.4f).height(16.dp))
            Spacer(Modifier.height(6.dp))
            ShimmerBox(Modifier.fillMaxWidth(0.6f).height(14.dp))
            Spacer(Modifier.height(4.dp))
            ShimmerBox(Modifier.fillMaxWidth(0.25f).height(12.dp))
        }
    }
}

/** Exibe N skeletons de lista de rotas (para estado de loading). */
@Composable
fun RotasListSkeleton(count: Int = 5, modifier: Modifier = Modifier) {
    Column(modifier) {
        repeat(count) {
            RotaListItemSkeleton(Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
        }
    }
}

/** Exibe N skeletons de lista de abastecimentos. */
@Composable
fun AbastecimentosListSkeleton(count: Int = 5, modifier: Modifier = Modifier) {
    Column(modifier) {
        repeat(count) {
            AbastecimentoListItemSkeleton(Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
        }
    }
}

/** Exibe N skeletons de lista de checklists. */
@Composable
fun ChecklistsListSkeleton(count: Int = 5, modifier: Modifier = Modifier) {
    Column(modifier) {
        repeat(count) {
            ChecklistListItemSkeleton(Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
        }
    }
}

/** Skeleton da área principal do dashboard (título + cartões). */
@Composable
fun DashboardHomeSkeleton(modifier: Modifier = Modifier) {
    Column(modifier.padding(20.dp)) {
        ShimmerBox(Modifier.fillMaxWidth(0.55f).height(28.dp))
        Spacer(Modifier.height(12.dp))
        ShimmerBox(Modifier.fillMaxWidth(0.45f).height(16.dp))
        Spacer(Modifier.height(24.dp))
        ShimmerBox(Modifier.fillMaxWidth().height(120.dp), shape = RoundedCornerShape(12.dp))
        Spacer(Modifier.height(16.dp))
        repeat(4) {
            ShimmerBox(Modifier.fillMaxWidth().height(76.dp), shape = RoundedCornerShape(16.dp))
            Spacer(Modifier.height(12.dp))
        }
    }
}
