package com.fuse.php.bridge.functions

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CameraManager
import android.os.BatteryManager
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.provider.Settings
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.utils.NativeActionCoordinator
import org.json.JSONObject

/**
 * Functions related to Device hardware
 * Namespace: "Device.*"
 */
object DeviceFunctions {

    private var flashlightState = false
    private const val TAG = "DeviceFunctions"

    /**
     * Trigger haptic feedback
     * Parameters:
     *   - duration: long - Duration in milliseconds (default: 50)
     */
    class Vibrate(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "Vibrate called with params: $parameters")
            val durationParam = parameters["duration"]
            val duration: Long = when (durationParam) {
                is Number -> durationParam.toLong()
                is String -> durationParam.toLongOrNull() ?: 50L
                else -> 50L
            }

            return try {
                // Vibrate permission is normal, but good to check if service exists
                val vibrator = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    val manager = context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
                    manager?.defaultVibrator
                } else {
                    @Suppress("DEPRECATION")
                    context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
                }

                if (vibrator != null) {
                    if (vibrator.hasVibrator()) {
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                            vibrator.vibrate(VibrationEffect.createOneShot(duration, VibrationEffect.DEFAULT_AMPLITUDE))
                        } else {
                            @Suppress("DEPRECATION")
                            vibrator.vibrate(duration)
                        }
                        Log.d(TAG, "Vibration success")
                        mapOf("success" to true)
                    } else {
                        Log.w(TAG, "Device has no vibrator")
                        mapOf("success" to false, "error" to "Device has no vibrator")
                    }
                } else {
                    Log.e(TAG, "Vibrator service not available")
                    mapOf("success" to false, "error" to "Vibrator service not available")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Vibration error", e)
                mapOf("success" to false, "error" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Toggle the device flashlight on/off
     * Parameters: none
     */
    class ToggleFlashlight(private val context: Context, private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "ToggleFlashlight called")

            // Check Camera Permission
            if (ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
                Log.d(TAG, "Camera permission missing, requesting...")
                ActivityCompat.requestPermissions(activity, arrayOf(Manifest.permission.CAMERA), 1003)

                val payload = JSONObject().apply {
                    put("permission", "CAMERA")
                }
                NativeActionCoordinator.dispatchEvent(activity, "Device.FlashlightPermissionRequested", payload.toString())

                return mapOf("success" to false, "error" to "Permission requested", "code" to "PERMISSION_REQUESTED")
            }

            return try {
                val cameraManager = context.getSystemService(Context.CAMERA_SERVICE) as CameraManager
                val cameraId = cameraManager.cameraIdList.firstOrNull {
                    cameraManager.getCameraCharacteristics(it)
                        .get(CameraCharacteristics.FLASH_INFO_AVAILABLE) == true
                }

                if (cameraId != null) {
                    flashlightState = !flashlightState
                    cameraManager.setTorchMode(cameraId, flashlightState)
                    Log.d(TAG, "Flashlight toggled: $flashlightState")

                    val result = mapOf("success" to true, "state" to flashlightState)

                    val payload = JSONObject().apply {
                        put("state", flashlightState)
                    }
                    NativeActionCoordinator.dispatchEvent(activity, "Device.FlashlightToggled", payload.toString())

                    result
                } else {
                    Log.w(TAG, "Flashlight not available")
                    val errPayload = JSONObject().apply {
                        put("error", "Flashlight not available")
                    }
                    NativeActionCoordinator.dispatchEvent(activity, "Device.FlashlightError", errPayload.toString())
                    mapOf("success" to false, "error" to "Flashlight not available")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Flashlight error", e)
                val err = e.message ?: "Unknown error"
                val errPayload = JSONObject().apply {
                    put("error", err)
                }
                NativeActionCoordinator.dispatchEvent(activity, "Device.FlashlightError", errPayload.toString())
                mapOf("success" to false, "error" to err)
            }
        }
    }

    /**
     * Get the unique device ID
     * Parameters: none
     */
    class GetId(private val context: Context, private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "GetId called")
            val androidId = Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID)
            val idValue = androidId ?: "unknown"

            Log.d(TAG, "Device ID: $idValue")

            return mapOf("id" to idValue)
        }
    }

    /**
     * Get device information
     * Parameters: none
     */
    class GetInfo(private val context: Context, private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "GetInfo called")
            val info = mapOf(
                "model" to Build.MODEL,
                "manufacturer" to Build.MANUFACTURER,
                "brand" to Build.BRAND,
                "device" to Build.DEVICE,
                "product" to Build.PRODUCT,
                "osVersion" to Build.VERSION.RELEASE,
                "sdkVersion" to Build.VERSION.SDK_INT
            )
            Log.d(TAG, "Device Info: $info")

            val payload = JSONObject(info)
            NativeActionCoordinator.dispatchEvent(activity, "Device.Info", payload.toString())
            return info
        }
    }

    /**
     * Get battery status
     * Parameters: none
     */
    class GetBatteryInfo(private val context: Context, private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "GetBatteryInfo called")
            val batteryStatus: Intent? = IntentFilter(Intent.ACTION_BATTERY_CHANGED).let { ifilter ->
                context.registerReceiver(null, ifilter)
            }

            val status: Int = batteryStatus?.getIntExtra(BatteryManager.EXTRA_STATUS, -1) ?: -1
            val isCharging: Boolean = status == BatteryManager.BATTERY_STATUS_CHARGING ||
                    status == BatteryManager.BATTERY_STATUS_FULL

            val level: Int = batteryStatus?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
            val scale: Int = batteryStatus?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
            val batteryPct: Float = if (scale > 0) level * 100 / scale.toFloat() else 0f

            val info = mapOf(
                "level" to batteryPct,
                "isCharging" to isCharging
            )
            Log.d(TAG, "Battery Info: $info")

            val payload = JSONObject(info)
            NativeActionCoordinator.dispatchEvent(activity, "Device.BatteryInfo", payload.toString())

            return info
        }
    }
}
