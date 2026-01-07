package com.fuse.php.ui

import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import android.util.Log

private const val TAG = "NativeBottomNav"

/**
 * Dynamic BottomNavigation that renders from Laravel NativeUI state
 */
@Composable
fun NativeBottomNavigation(
    onNavigate: (String) -> Unit
) {
    val bottomNavData by NativeUIState.bottomNavData

    if (bottomNavData == null || bottomNavData?.children.isNullOrEmpty()) {
        return  // Don't render if no data
    }

    val items = bottomNavData?.children?.mapNotNull { it.data } ?: emptyList()

    Log.d(TAG, "🎨 Rendering bottom nav with ${items.size} items")

    NavigationBar {
        items.forEach { item ->
            NavigationBarItem(
                icon = {
                    BadgedBox(
                        badge = {
                            when {
                                // News dot: just a small red indicator
                                item.news == true -> {
                                    Badge(
                                        containerColor = parseBadgeColor("red")
                                    )
                                }
                                // Badge with text
                                item.badge != null -> {
                                    Badge(
                                        containerColor = parseBadgeColor(item.badgeColor ?: "red"),
                                        contentColor = Color.White
                                    ) {
                                        Text(
                                            text = item.badge,
                                            style = MaterialTheme.typography.labelSmall
                                        )
                                    }
                                }
                            }
                        }
                    ) {
                        MaterialIcon(
                            name = item.icon,
                            contentDescription = item.label
                        )
                    }
                },
                label = {
                    // Support 3 Material Design label visibility modes
                    when (bottomNavData?.labelVisibility) {
                        "unlabeled" -> null  // Never show labels
                        "selected" -> if (item.active == true) {
                            Text(item.label)  // Only show on selected item
                        } else null
                        else -> Text(item.label)  // Always show (labeled or default)
                    }
                },
                selected = item.active == true,
                onClick = {
                    Log.d(TAG, "🖱️ Nav item clicked: ${item.label} -> ${item.url}")
                    // Optimistically update active state to prevent flash
                    NativeUIState.setBottomNavActiveOptimistic(item.url)
                    onNavigate(item.url)
                }
            )
        }
    }
}

/**
 * Parse badge color string to Color
 * Defaults to red if not specified or unknown
 */
private fun parseBadgeColor(colorString: String?): Color {
    return when (colorString?.lowercase()) {
        "lime" -> Color(0xFF84CC16)
        "green" -> Color(0xFF22C55E)
        "blue" -> Color(0xFF3B82F6)
        "red" -> Color(0xFFEF4444)
        "yellow" -> Color(0xFFEAB308)
        "purple" -> Color(0xFFA855F7)
        "pink" -> Color(0xFFEC4899)
        "orange" -> Color(0xFFF97316)
        else -> Color(0xFFEF4444)  // Default to red
    }
}
