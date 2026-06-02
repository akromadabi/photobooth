package com.example.photobooth.ui.preview

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.unit.dp

data class DoodleLine(
    val points: List<Pair<Float, Float>>, // Relative (x, y) coordinates from 0f to 1f
    val color: Color,
    val strokeWidth: Float
)

@Composable
fun DoodleCanvas(
    lines: List<DoodleLine>,
    onLinesChanged: (List<DoodleLine>) -> Unit,
    activeColor: Color,
    activeStrokeWidth: Float,
    modifier: Modifier = Modifier
) {
    var currentLinePoints = remember { mutableStateListOf<Pair<Float, Float>>() }
    var canvasWidth by remember { mutableFloatStateOf(1f) }
    var canvasHeight by remember { mutableFloatStateOf(1f) }

    Canvas(
        modifier = modifier
            .fillMaxSize()
            .pointerInput(Unit) {
                detectDragGestures(
                    onDragStart = { offset ->
                        val relativeX = offset.x / canvasWidth
                        val relativeY = offset.y / canvasHeight
                        currentLinePoints.clear()
                        currentLinePoints.add(Pair(relativeX, relativeY))
                    },
                    onDrag = { change, dragAmount ->
                        change.consume()
                        val relativeX = change.position.x / canvasWidth
                        val relativeY = change.position.y / canvasHeight
                        
                        // Add point if it is within bounds
                        if (relativeX in 0f..1f && relativeY in 0f..1f) {
                            currentLinePoints.add(Pair(relativeX, relativeY))
                        }
                        
                        // Re-trigger rendering by forcing a state change
                        val temp = currentLinePoints.toList()
                        currentLinePoints.clear()
                        currentLinePoints.addAll(temp)
                    },
                    onDragEnd = {
                        if (currentLinePoints.isNotEmpty()) {
                            val newLine = DoodleLine(
                                points = currentLinePoints.toList(),
                                color = activeColor,
                                strokeWidth = activeStrokeWidth
                            )
                            onLinesChanged(lines + newLine)
                            currentLinePoints.clear()
                        }
                    }
                )
            }
    ) {
        canvasWidth = size.width
        canvasHeight = size.height

        // Draw already saved lines
        lines.forEach { line ->
            if (line.points.size > 1) {
                val path = Path()
                val startX = line.points[0].first * canvasWidth
                val startY = line.points[0].second * canvasHeight
                path.moveTo(startX, startY)
                
                for (i in 1 until line.points.size) {
                    val x = line.points[i].first * canvasWidth
                    val y = line.points[i].second * canvasHeight
                    path.lineTo(x, y)
                }

                drawPath(
                    path = path,
                    color = line.color,
                    style = Stroke(
                        width = line.strokeWidth * density,
                        cap = StrokeCap.Round,
                        join = StrokeJoin.Round
                    )
                )
            }
        }

        // Draw current active line
        if (currentLinePoints.size > 1) {
            val path = Path()
            val startX = currentLinePoints[0].first * canvasWidth
            val startY = currentLinePoints[0].second * canvasHeight
            path.moveTo(startX, startY)
            
            for (i in 1 until currentLinePoints.size) {
                val x = currentLinePoints[i].first * canvasWidth
                val y = currentLinePoints[i].second * canvasHeight
                path.lineTo(x, y)
            }

            drawPath(
                path = path,
                color = activeColor,
                style = Stroke(
                    width = activeStrokeWidth * density,
                    cap = StrokeCap.Round,
                    join = StrokeJoin.Round
                )
            )
        }
    }
}
