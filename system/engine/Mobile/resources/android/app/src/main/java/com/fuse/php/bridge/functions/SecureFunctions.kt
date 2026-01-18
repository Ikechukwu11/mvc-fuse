@file:Suppress("DEPRECATION")

package com.fuse.php.bridge.functions

import android.content.Context
import android.content.SharedPreferences
import android.os.Build
import androidx.fragment.app.FragmentActivity
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.utils.NativeActionCoordinator
import org.json.JSONObject

object SecureFunctions {
    private fun prefs(context: Context): SharedPreferences {
        val key = MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build()
        return EncryptedSharedPreferences.create(
            context,
            "secure_prefs",
            key,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    class Set(private val activity: FragmentActivity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val key = parameters["key"] as? String ?: return mapOf("error" to "key required")
            val value = parameters["value"] as? String ?: ""
            val p = prefs(context)
            p.edit().putString(key, value).apply()
            val payload = JSONObject().apply {
                put("key", key)
                put("value", value)
            }
            NativeActionCoordinator.dispatchEvent(activity, "Secure.Value", payload.toString())
            return mapOf("success" to true)
        }
    }

    class Get(private val activity: FragmentActivity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val key = parameters["key"] as? String ?: return mapOf("error" to "key required")
            val p = prefs(context)
            val v = p.getString(key, null)
            val payload = JSONObject().apply {
                put("key", key)
                put("value", v)
            }
            NativeActionCoordinator.dispatchEvent(activity, "Secure.Value", payload.toString())
            val retVal: Any = v ?: JSONObject.NULL
            return mapOf<String, Any>("value" to retVal)
        }
    }

    class Delete(private val activity: FragmentActivity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val key = parameters["key"] as? String ?: return mapOf("error" to "key required")
            val p = prefs(context)
            p.edit().remove(key).apply()
            val payload = JSONObject().apply {
                put("key", key)
            }
            NativeActionCoordinator.dispatchEvent(activity, "Secure.Deleted", payload.toString())
            return mapOf("success" to true)
        }
    }
}
