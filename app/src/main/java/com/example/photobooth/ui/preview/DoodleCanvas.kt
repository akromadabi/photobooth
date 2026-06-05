package com.example.photobooth.ui.preview

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.input.pointer.pointerInput
import kotlin.math.sqrt

data class DoodlePoint(
    val x: Float,          // Relative coordinate (0f to 1f)
    val y: Float,          // Relative coordinate (0f to 1f)
    val strokeWidth: Float // The thickness at this specific point
)

data class DoodleLine(
    val points: List<DoodlePoint>,
    val color: Color
)

@Composable
fun DoodleCanvas(
    lines: List<DoodleLine>,
    onLinesChanged: (List<DoodleLine>) -> Unit,
    activeColor: Color,
    activeStrokeWidth: Float,
    enabled: Boolean = true,
    modifier: Modifier = Modifier
) {
    val currentLinePoints = remember { mutableStateListOf<DoodlePoint>() }
    var canvasWidth by remember { mutableFloatStateOf(1f) }
    var canvasHeight by remember { mutableFloatStateOf(1f) }

    val dragModifier = if (enabled) {
        Modifier.pointerInput(activeColor, activeStrokeWidth) {
            detectDragGestures(
                onDragStart = { offset ->
                    val relativeX = offset.x / canvasWidth
                    val relativeY = offset.y / canvasHeight
                    currentLinePoints.clear()
                    currentLinePoints.add(DoodlePoint(relativeX, relativeY, activeStrokeWidth))
                },
                onDrag = { change, _ ->
                    change.consume()
                    val relativeX = change.position.x / canvasWidth
                    val relativeY = change.position.y / canvasHeight

                    if (relativeX in 0f..1f && relativeY in 0f..1f) {
                        val currentPos = change.position
                        val prevPos = change.previousPosition
                        val currentTime = change.uptimeMillis
                        val prevTime = change.previousUptimeMillis

                        val timeDelta = currentTime - prevTime
                        val distance = sqrt(
                            (currentPos.x - prevPos.x) * (currentPos.x - prevPos.x) +
                                    (currentPos.y - prevPos.y) * (currentPos.y - prevPos.y)
                        )
                        val velocity = if (timeDelta > 0) distance / timeDelta else 0f

                        // Define velocity mapping: slower = thicker, faster = thinner
                        val minWidthFactor = 0.35f
                        val maxWidthFactor = 1.4f
                        val maxVelocity = 4.0f // Threshold speed in px/ms

                        val velocityFraction = (velocity / maxVelocity).coerceIn(0f, 1f)
                        val widthFactor = maxWidthFactor - (maxWidthFactor - minWidthFactor) * velocityFraction

                        // Read pointer physical pressure if available (>0 and variable)
                        val pressure = change.pressure
                        val pressureFactor = if (pressure > 0f) {
                            0.6f + pressure * 0.8f
                        } else {
                            1f
                        }

                        var targetWidth = activeStrokeWidth * widthFactor * pressureFactor

                        // Exponential moving average to smooth transition between widths
                        val lastPoint = currentLinePoints.lastOrNull()
                        val prevWidth = lastPoint?.strokeWidth ?: activeStrokeWidth
                        val smoothWidth = prevWidth * 0.7f + targetWidth * 0.3f

                        currentLinePoints.add(DoodlePoint(relativeX, relativeY, smoothWidth))
                    }

                    // Re-trigger rendering by forcing a state update
                    val temp = currentLinePoints.toList()
                    currentLinePoints.clear()
                    currentLinePoints.addAll(temp)
                },
                onDragEnd = {
                    if (currentLinePoints.isNotEmpty()) {
                        val newLine = DoodleLine(
                            points = currentLinePoints.toList(),
                            color = activeColor
                        )
                        onLinesChanged(lines + newLine)
                        currentLinePoints.clear()
                    }
                }
            )
        }
    } else {
        Modifier
    }

    Canvas(
        modifier = modifier
            .fillMaxSize()
            .then(dragModifier)
    ) {
        canvasWidth = size.width
        canvasHeight = size.height

        // Helper function to draw a line segment or dot
        val drawLinePoints = { points: List<DoodlePoint>, color: Color ->
            if (points.size == 1) {
                val p = points[0]
                drawCircle(
                    color = color,
                    center = Offset(p.x * canvasWidth, p.y * canvasHeight),
                    radius = (p.strokeWidth / 2f) * density
                )
            } else if (points.size > 1) {
                for (i in 1 until points.size) {
                    val p1 = points[i - 1]
                    val p2 = points[i]

                    val start = Offset(p1.x * canvasWidth, p1.y * canvasHeight)
                    val end = Offset(p2.x * canvasWidth, p2.y * canvasHeight)
                    val width = ((p1.strokeWidth + p2.strokeWidth) / 2f) * density

                    drawLine(
                        color = color,
                        start = start,
                        end = end,
                        strokeWidth = width,
                        cap = StrokeCap.Round
                    )
                }
            }
        }

        // Draw already saved lines
        lines.forEach { line ->
            drawLinePoints(line.points, line.color)
        }

        // Draw current active line
        if (currentLinePoints.isNotEmpty()) {
            drawLinePoints(currentLinePoints, activeColor)
        }
    }
}

