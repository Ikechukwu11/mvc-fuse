package com.fuse.php.bridge

import android.content.Context
import androidx.fragment.app.FragmentActivity
import com.fuse.php.bridge.functions.EdgeFunctions
import com.fuse.php.bridge.functions.DialogFunctions
import com.fuse.php.bridge.functions.DeviceFunctions
import com.fuse.php.bridge.functions.AppFunctions
import com.fuse.php.bridge.functions.MediaFunctions
import com.fuse.php.bridge.functions.MediaLibraryFunctions
import com.fuse.php.bridge.functions.MediaQueueFunctions
import com.fuse.php.bridge.functions.MusicFunctions
import com.fuse.php.bridge.functions.SecureFunctions
import com.fuse.php.bridge.plugins.registerPluginBridgeFunctions

/**
 * Register all bridge functions with the registry
 * Call this once during app initialization
 */
fun registerBridgeFunctions(activity: FragmentActivity, context: Context) {
    val registry = BridgeFunctionRegistry.shared

    // App System
    registry.register("App.SetStatusBar", AppFunctions.SetStatusBar())
    registry.register("App.ReadStorageFile", AppFunctions.ReadStorageFile())

    // Edge UI
    registry.register("Edge.Set", EdgeFunctions.Set())

    // Dialog / Toast
    val toastFunction = DialogFunctions.Toast(activity)
    registry.register("Dialog.Toast", toastFunction)
    registry.register("Toast.Show", toastFunction) // Alias for compatibility

    registry.register("Dialog.Alert", DialogFunctions.Alert(activity))

    // Device / Haptics
    val vibrateFunction = DeviceFunctions.Vibrate(context)
    registry.register("Device.Vibrate", vibrateFunction)
    registry.register("Haptics.Vibrate", vibrateFunction) // Alias for compatibility

    registry.register("Device.ToggleFlashlight", DeviceFunctions.ToggleFlashlight(context, activity))
    registry.register("Device.GetId", DeviceFunctions.GetId(context, activity))
    registry.register("Device.GetInfo", DeviceFunctions.GetInfo(context, activity))
    registry.register("Device.GetBatteryInfo", DeviceFunctions.GetBatteryInfo(context, activity))

    // Media
    registry.register("Media.Play", MediaFunctions.Play())
    registry.register("Media.Pause", MediaFunctions.Pause())
    registry.register("Media.Resume", MediaFunctions.Resume())
    registry.register("Media.PickTrack", MediaFunctions.PickTrack())
    registry.register("Media.State", MediaQueueFunctions.State())
    registry.register("Media.StartStatusUpdates", MediaQueueFunctions.StartStatusUpdates())
    registry.register("Media.StopStatusUpdates", MediaQueueFunctions.StopStatusUpdates())
    registry.register("Media.Next", MediaQueueFunctions.Next())
    registry.register("Media.Previous", MediaQueueFunctions.Previous())
    registry.register("Media.SeekTo", MediaQueueFunctions.SeekTo())
    registry.register("Media.ToggleShuffle", MediaQueueFunctions.ToggleShuffle())
    registry.register("Media.ToggleRepeat", MediaQueueFunctions.ToggleRepeat())

    // Media Library
    registry.register("MediaLibrary.Scan", MediaLibraryFunctions.Scan(activity, context))
    registry.register("MediaLibrary.Search", MediaLibraryFunctions.Search(activity, context))

    // Ludoria Compatibility (Music.*)
    registry.register("Music.Load", MusicFunctions.Load(activity))
    registry.register("Music.Play", MusicFunctions.Play(activity))
    registry.register("Music.Pause", MusicFunctions.Pause(activity))
    registry.register("Music.Stop", MusicFunctions.Stop(activity))
    registry.register("Music.Seek", MusicFunctions.Seek(activity))
    registry.register("Music.Next", MusicFunctions.Next(activity))
    registry.register("Music.Previous", MusicFunctions.Previous(activity))
    registry.register("Music.Status", MusicFunctions.Status(activity))
    registry.register("Music.ListLibrary", MusicFunctions.ListLibrary(activity, context))

    // Secure Storage
    registry.register("Secure.Set", SecureFunctions.Set(activity, context))
    registry.register("Secure.Get", SecureFunctions.Get(activity, context))
    registry.register("Secure.Delete", SecureFunctions.Delete(activity, context))

    // Register plugin bridge functions
    registerPluginBridgeFunctions(activity, context)
}
