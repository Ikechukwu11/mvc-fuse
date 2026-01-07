package com.fuse.php.bridge

import android.content.Context
import androidx.fragment.app.FragmentActivity
import com.fuse.php.bridge.functions.EdgeFunctions
import com.fuse.php.bridge.plugins.registerPluginBridgeFunctions

/**
 * Register all bridge functions with the registry
 * Call this once during app initialization
 */
fun registerBridgeFunctions(activity: FragmentActivity, context: Context) {
    val registry = BridgeFunctionRegistry.shared

    registry.register("Edge.Set", EdgeFunctions.Set())

    // Register plugin bridge functions
    registerPluginBridgeFunctions(activity, context)
}
