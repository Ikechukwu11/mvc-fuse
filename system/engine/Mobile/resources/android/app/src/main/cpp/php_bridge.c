#include <jni.h>
#include <android/log.h>
#include "php_embed.h"
#include "PHP.h"
#include <zend_exceptions.h>

// Define Android logging macros first
#define LOG_TAG "PHP-Native"
#define LOGI(...) ((void)__android_log_print(ANDROID_LOG_INFO, LOG_TAG, __VA_ARGS__))
#define LOGE(...) ((void)__android_log_print(ANDROID_LOG_ERROR, LOG_TAG, __VA_ARGS__))

JavaVM *g_jvm = NULL;
jobject g_bridge_instance = NULL;

// Forward declaration for bridge_jni.cpp initialization
extern jint InitializeBridgeJNI(JNIEnv* env);

// Global state
static int php_initialized = 0;
static jobject g_callback_obj = NULL;
static jmethodID g_callback_method = NULL;
static char *g_collected_output = NULL;
static size_t g_collected_length = 0;
static size_t g_collected_capacity = 0;

#define BUFFER_CHUNK_SIZE (256 * 1024)  // 256KB increments
#define MAX_BUFFER_SIZE (16 * 1024 * 1024)  // 16MB max buffer

static void (*jni_output_callback_ptr)(const char *) = NULL;

void clear_collected_output() {
    if (g_collected_output) {
        free(g_collected_output);
        g_collected_output = NULL;
    }

    g_collected_capacity = BUFFER_CHUNK_SIZE;
    g_collected_length = 0;
    g_collected_output = (char *) malloc(g_collected_capacity);
    if (g_collected_output) {
        g_collected_output[0] = '\0';
    }
}


void pipe_php_output(const char *str) {

//    LOGI("PIPE: Output received: %s", str);

    // Safety check
    if (!g_collected_output) {
        clear_collected_output();
        return;  // Failed to allocate
    }

    size_t length = strlen(str);

    // Check if we need more space
    if (g_collected_length + length + 1 > g_collected_capacity) {
        // Calculate new size in chunks
        size_t needed_capacity = g_collected_capacity;
        while (needed_capacity < g_collected_length + length + 1) {
            needed_capacity += BUFFER_CHUNK_SIZE;
        }

        // Enforce maximum size limit
        if (needed_capacity > MAX_BUFFER_SIZE) {
            LOGE("Output buffer exceeded maximum size of %d MB", MAX_BUFFER_SIZE / (1024 * 1024));
            // Just return and drop output beyond this point
            return;
        }

        // Reallocate with the new size
        char *new_buffer = (char *) realloc(g_collected_output, needed_capacity);
        if (new_buffer) {
            g_collected_output = new_buffer;
            g_collected_capacity = needed_capacity;
        } else {
            LOGE("Failed to reallocate output buffer to %zu bytes", needed_capacity);
            return;  // Failed to reallocate
        }
    }

    // Append the string
    strcpy(g_collected_output + g_collected_length, str);
    g_collected_length += length;
}

void cleanup_output_buffer() {
    if (g_collected_output) {
        g_collected_output[0] = '\0';
        g_collected_length = 0;
    }
}

size_t capture_php_output(const char *str, size_t str_length) {
    // Log the raw output coming from PHP
//    LOGI("PHP output captured: length=%zu", str_length);
    if (str_length > 0) {
        // Log a preview of the output (first 100 chars or so)
        char preview[10001] = {0};
        strncpy(preview, str, str_length > 10000 ? 10000 : str_length);
        preview[10000] = '\0'; // Ensure null termination
    } else {
        LOGI("Empty output received");
    }

    // Rest of your original code...
    char *buffer = malloc(str_length + 1);
    if (buffer) {
        memcpy(buffer, str, str_length);
        buffer[str_length] = '\0';

        pipe_php_output(buffer);
        free(buffer);
    }

    return str_length;
}

void override_embed_module_output(void (*callback)(const char *)) {
    jni_output_callback_ptr = callback;
    php_embed_module.ub_write = capture_php_output;
}

void jni_output_callback(const char *output) {
//    LOGI("PHP Output Debug - Callback called with: %s", output);

    JNIEnv *env;
    if ((*g_jvm)->GetEnv(g_jvm, (void **) &env, JNI_VERSION_1_6) != JNI_OK) {
        LOGE("Failed to get JNI environment");
        return;
    }

    if (g_callback_obj && g_callback_method) {
        LOGI("WE MADE IT HERE");
        jstring joutput = (*env)->NewStringUTF(env, output);
        (*env)->CallVoidMethod(env, g_callback_obj, g_callback_method, joutput);
        (*env)->DeleteLocalRef(env, joutput);
    }

}

// Header collection buffer
static char *g_header_buffer = NULL;
static size_t g_header_length = 0;
static size_t g_header_capacity = 0;
static char g_status_line[128] = {0};

static void clear_header_buffer() {
    if (g_header_buffer) {
        free(g_header_buffer);
        g_header_buffer = NULL;
    }
    g_header_capacity = BUFFER_CHUNK_SIZE;
    g_header_length = 0;
    g_header_buffer = (char *) malloc(g_header_capacity);
    if (g_header_buffer) {
        g_header_buffer[0] = '\0';
    }
    g_status_line[0] = '\0';
}

static void append_header_line(const char *line) {
    size_t length = strlen(line);
    if (g_header_length + length + 3 > g_header_capacity) {
        size_t needed = g_header_capacity;
        while (needed < g_header_length + length + 3) {
            needed += BUFFER_CHUNK_SIZE;
        }
        char *newbuf = (char *) realloc(g_header_buffer, needed);
        if (!newbuf) return;
        g_header_buffer = newbuf;
        g_header_capacity = needed;
    }
    strcpy(g_header_buffer + g_header_length, line);
    g_header_length += length;
    g_header_buffer[g_header_length++] = '\r';
    g_header_buffer[g_header_length++] = '\n';
    g_header_buffer[g_header_length] = '\0';
}

int android_header_handler(sapi_header_struct *sapi_header, sapi_header_op_enum op, sapi_headers_struct *sapi_headers) {
    LOGI("📤 SAPI header: %s", sapi_header->header);
    if (!sapi_header || !sapi_header->header) return 0;
    const char *h = sapi_header->header;
    if (strncmp(h, "HTTP/", 5) == 0) {
        strncpy(g_status_line, h, sizeof(g_status_line) - 1);
        g_status_line[sizeof(g_status_line) - 1] = '\0';
        return 0;
    }
    append_header_line(h);
    return 0;
}

char* run_php_script_once(const char* scriptPath, const char* method, const char* uri, const char* postData) {
    clear_collected_output();
    clear_header_buffer();

    // 🧠 Get session path from environment (set by Kotlin)
    const char* session_path = getenv("SESSION_SAVE_PATH");
    if (!session_path) session_path = "/tmp"; // fallback

    // ✅ Build ini entries per request
    php_embed_module.ub_write = capture_php_output;
    php_embed_module.phpinfo_as_text = 1;
    php_embed_module.php_ini_ignore = 0;
    php_embed_module.header_handler = android_header_handler;

    sapi_module.header_handler = android_header_handler;
    if (!php_initialized) {
        if (php_embed_init(0, NULL) != SUCCESS) {
            return strdup("HTTP/1.1 500 Internal Server Error\r\nContent-Type: text/plain\r\n\r\nPHP init failed.");
        }
        php_initialized = 1;
    }

    // ✅ Set MVC-relevant env vars
    setenv("REQUEST_URI", uri, 1);
    setenv("REQUEST_METHOD", method, 1);
    setenv("SCRIPT_FILENAME", scriptPath, 1);
    setenv("PHP_SELF", "/mobile_boot.php", 1);
    setenv("HTTP_HOST", "127.0.0.1", 1);
    setenv("APP_URL", "http://127.0.0.1", 1);
    setenv("ASSET_URL", "http://127.0.0.1/_assets/", 1);
    setenv("MVC_MOBILE_RUNNING", "true", 1);

    // ✅ Set QUERY_STRING and defer parsing
    const char* query_string = "";
    const char* query_start = strchr(uri, '?');
    if (query_start && strlen(query_start + 1) > 0) {
        query_string = query_start + 1;
        setenv("QUERY_STRING", query_string, 1);
        LOGI("✅ Set QUERY_STRING: %s", query_string);
    } else {
        unsetenv("QUERY_STRING");
        LOGI("⚠️ No QUERY_STRING found in URI");
    }

    // ✅ Activate Zend and parse query data AFTER engine is live
    zend_first_try {
                zend_activate_modules();

                if (strlen(query_string) > 0) {
                    zend_string *query = zend_string_init(query_string, strlen(query_string), 0);
                    sapi_module.treat_data(PARSE_GET, query->val, NULL);
                    zend_string_free(query);
                    LOGI("✅ Parsed query string into $_GET");
                }

                // ✅ Set up POST data (if needed)
                initialize_php_with_request(postData ?: "", method, uri);

                // ✅ Execute the PHP script
                zend_file_handle fileHandle;
                zend_stream_init_filename(&fileHandle, scriptPath);
                php_execute_script(&fileHandle);

                LOGI("✅ PHP script finished executing");

                if (strlen(query_string) > 0) {
                    zend_string *query2 = zend_string_init(query_string, strlen(query_string), 0);
                    sapi_module.treat_data(PARSE_GET, query2->val, NULL);
                    zend_string_free(query2);
                    LOGI("✅ Re-parsed query string after php_execute_script()");
                }

            } zend_end_try();

    // ✅ End request lifecycle
    php_request_shutdown(NULL);

    // ✅ Compose HTTP response: status + headers + CRLF + body
    const char *status = (g_status_line[0] != '\0') ? g_status_line : "HTTP/1.1 200 OK";
    const char *body = g_collected_output ? g_collected_output : "";
    size_t status_len = strlen(status);
    size_t headers_len = g_header_buffer ? g_header_length : 0;
    size_t body_len = strlen(body);

    size_t total = status_len + 2 + headers_len + 2 + body_len + 1;
    char *response = (char *) malloc(total);
    if (!response) {
        return strdup("HTTP/1.1 500 Internal Server Error\r\nContent-Type: text/plain\r\n\r\nAllocation failure");
    }
    size_t pos = 0;
    memcpy(response + pos, status, status_len); pos += status_len;
    response[pos++] = '\r'; response[pos++] = '\n';
    if (headers_len > 0) {
        memcpy(response + pos, g_header_buffer, headers_len); pos += headers_len;
    }
    response[pos++] = '\r'; response[pos++] = '\n';
    if (body_len > 0) {
        memcpy(response + pos, body, body_len); pos += body_len;
    }
    response[pos] = '\0';

    return response;
}

JNIEXPORT void JNICALL native_initialize(JNIEnv *env, jobject thiz) {
    if (php_initialized) {
        LOGI("PHP already initialized");
        return;
    }

    LOGI("Initializing PHP");

    if (g_bridge_instance) {
        LOGI("Deleting existing bridge instance");
        (*env)->DeleteGlobalRef(env, g_bridge_instance);
    }
    g_bridge_instance = (*env)->NewGlobalRef(env, thiz);
    LOGI("Set g_bridge_instance to %p", g_bridge_instance);

    // Configure the embed SAPI
    php_embed_module.ub_write = capture_php_output;
    php_embed_module.phpinfo_as_text = 1;
    php_embed_module.php_ini_ignore = 0;

    // Initialize PHP
    if (php_embed_init(0, NULL) == SUCCESS) {
        php_initialized = 1;
        sapi_module.header_handler = php_embed_module.header_handler;
        LOGI("PHP initialized successfully");
    } else {
        LOGI("PHP initialization failed");
    }
}


JNIEXPORT jint JNICALL native_set_env(JNIEnv *env, jobject thiz,
                                                            jstring name, jstring value,
                                                            jint overwrite) {

    const char *nameStr = (*env)->GetStringUTFChars(env, name, NULL);
    const char *valueStr = (*env)->GetStringUTFChars(env, value, NULL);

    int result = setenv(nameStr, valueStr, overwrite);

    (*env)->ReleaseStringUTFChars(env, name, nameStr);
    (*env)->ReleaseStringUTFChars(env, value, valueStr);

    return result;
}

JNIEXPORT void JNICALL native_set_request_info(JNIEnv *env, jobject thiz,
                                                     jstring method, jstring uri,
                                                     jstring post_data) {

    const char *methodStr = (*env)->GetStringUTFChars(env, method, NULL);
    const char *uriStr = (*env)->GetStringUTFChars(env, uri, NULL);
    const char *postStr = post_data ? (*env)->GetStringUTFChars(env, post_data, NULL) : "";

    initialize_php_with_request(postStr, methodStr, uriStr);

    (*env)->ReleaseStringUTFChars(env, method, methodStr);
    (*env)->ReleaseStringUTFChars(env, uri, uriStr);
    if (post_data) {
        (*env)->ReleaseStringUTFChars(env, post_data, postStr);
    }
}

JNIEXPORT jstring JNICALL native_run_runner_command(JNIEnv *env, jobject thiz, jstring jcommand) {
    const char *command = (*env)->GetStringUTFChars(env, jcommand, NULL);
    LOGI("🛠️ runRunnerCommand: %s", command);

    clear_collected_output();
    php_embed_module.ub_write = capture_php_output;
    php_embed_module.phpinfo_as_text = 1;
    php_embed_module.php_ini_ignore = 0;
    php_embed_module.ini_entries = "display_errors=1\nimplicit_flush=1\noutput_buffering=0\n";

    // Get App Public path
    jclass cls = (*env)->GetObjectClass(env, thiz);
    jmethodID method = (*env)->GetMethodID(env, cls, "getAppPublicPath", "()Ljava/lang/String;");
    jstring jAppPath = (jstring)(*env)->CallObjectMethod(env, thiz, method);
    const char *cAppPath = (*env)->GetStringUTFChars(env, jAppPath, NULL);

    native_initialize(env, thiz);

    // Assuming cAppPath is .../storage/app/public
    // runner is at .../storage/app/runner
    char runnerPath[1024];
    snprintf(runnerPath, sizeof(runnerPath), "%s/../runner", cAppPath);
    char basePath[1024];
    snprintf(basePath, sizeof(basePath), "%s/..", cAppPath);
    chdir(basePath);
    LOGI("✅ Changed CWD to Base: %s", basePath);

    // Tokenize command
    char *argv[128];
    int argc = 0;
    argv[argc++] = "php";

    char *commandCopy = strdup(command);
    char *token = strtok(commandCopy, " ");
    while (token && argc < 127) {
        argv[argc++] = token;
        token = strtok(NULL, " ");
    }
    argv[argc] = NULL;

    if (php_initialized) {
        php_embed_shutdown();
        php_initialized = 0;
    }

    setenv("APP_RUNNING_IN_CONSOLE", "true", 1);
    setenv("PHP_SELF", "runner", 1);
    setenv("APP_ENV", "local", 1);

    if (php_embed_init(argc, argv) == SUCCESS) {
        php_initialized = 1;

        // Force STDOUT/STDERR through php://output so Symfony StreamOutput works
        zend_eval_string(
                "if (!defined('STDOUT')) define('STDOUT', fopen('php://output', 'w')); "
                "if (!defined('STDERR')) define('STDERR', fopen('php://output', 'w'));",
                NULL, "patch_stdio"
        );

        zend_file_handle file_handle;
        zend_stream_init_filename(&file_handle, runnerPath);
        php_execute_script(&file_handle);
        php_embed_shutdown();
        php_initialized = 0;
    } else {
        LOGE("❌ Failed to initialize PHP runtime");
    }

    (*env)->ReleaseStringUTFChars(env, jcommand, command);
    (*env)->ReleaseStringUTFChars(env, jAppPath, cAppPath);
    (*env)->DeleteLocalRef(env, jAppPath);
    free(commandCopy);

    return (*env)->NewStringUTF(env, g_collected_output ? g_collected_output : "");
}

JNIEXPORT jstring JNICALL native_get_app_path(JNIEnv *env, jobject thiz) {
    // Get context from the PHPBridge instance
    jclass bridgeClass = (*env)->GetObjectClass(env, thiz);
    jfieldID contextFieldId = (*env)->GetFieldID(env, bridgeClass, "context", "Landroid/content/Context;");
    jobject context = (*env)->GetObjectField(env, thiz, contextFieldId);

    // Call getDir method on the context
    jclass contextClass = (*env)->GetObjectClass(env, context);
    jmethodID getDirMethod = (*env)->GetMethodID(env, contextClass, "getDir", "(Ljava/lang/String;I)Ljava/io/File;");
    jstring dirName = (*env)->NewStringUTF(env, "storage");
    jint mode = 0; // MODE_PRIVATE
    jobject storageDir = (*env)->CallObjectMethod(env, context, getDirMethod, dirName, mode);

    // Get the absolute path from the file object
    jclass fileClass = (*env)->GetObjectClass(env, storageDir);
    jmethodID getAbsolutePathMethod = (*env)->GetMethodID(env, fileClass, "getAbsolutePath", "()Ljava/lang/String;");
    jstring storagePath = (jstring) (*env)->CallObjectMethod(env, storageDir, getAbsolutePathMethod);

    // Convert to C string for concatenation
    const char *cStoragePath = (*env)->GetStringUTFChars(env, storagePath, NULL);

    // Concatenate with "/app"
    char fullPath[1024];
    sprintf(fullPath, "%s/app", cStoragePath);

    // Release resources
    (*env)->ReleaseStringUTFChars(env, storagePath, cStoragePath);
    (*env)->DeleteLocalRef(env, dirName);
    (*env)->DeleteLocalRef(env, storageDir);
    (*env)->DeleteLocalRef(env, storagePath);

    // Return the final path
    return (*env)->NewStringUTF(env, fullPath);
}

JNIEXPORT jstring JNICALL native_handle_request_once(
        JNIEnv *env, jobject thiz,
        jstring jMethod, jstring jUri, jstring jPostData, jstring jScriptPath) {

    const char *method = (*env)->GetStringUTFChars(env, jMethod, NULL);
    const char *uri = (*env)->GetStringUTFChars(env, jUri, NULL);
    const char *post = jPostData ? (*env)->GetStringUTFChars(env, jPostData, NULL) : "";
    const char *path = (*env)->GetStringUTFChars(env, jScriptPath, NULL);

    char *output = run_php_script_once(path, method, uri, post);

    jstring result = (*env)->NewStringUTF(env, output ? output : "");

    // Clean up
    free(output);
    (*env)->ReleaseStringUTFChars(env, jMethod, method);
    (*env)->ReleaseStringUTFChars(env, jUri, uri);
    (*env)->ReleaseStringUTFChars(env, jScriptPath, path);
    if (jPostData) (*env)->ReleaseStringUTFChars(env, jPostData, post);

    return result;
}

JNIEXPORT jstring JNICALL native_get_app_public_path(JNIEnv *env, jobject thiz) {
    // Get context from the PHPBridge instance
    jclass bridgeClass = (*env)->GetObjectClass(env, thiz);
    jfieldID contextFieldId = (*env)->GetFieldID(env, bridgeClass, "context", "Landroid/content/Context;");
    jobject context = (*env)->GetObjectField(env, thiz, contextFieldId);

    // Call getDir method on the context
    jclass contextClass = (*env)->GetObjectClass(env, context);
    jmethodID getDirMethod = (*env)->GetMethodID(env, contextClass, "getDir", "(Ljava/lang/String;I)Ljava/io/File;");
    jstring dirName = (*env)->NewStringUTF(env, "storage");
    jint mode = 0; // MODE_PRIVATE
    jobject storageDir = (*env)->CallObjectMethod(env, context, getDirMethod, dirName, mode);

    // Get the absolute path from the file object
    jclass fileClass = (*env)->GetObjectClass(env, storageDir);
    jmethodID getAbsolutePathMethod = (*env)->GetMethodID(env, fileClass, "getAbsolutePath", "()Ljava/lang/String;");
    jstring storagePath = (jstring) (*env)->CallObjectMethod(env, storageDir, getAbsolutePathMethod);

    // Convert to C string for concatenation
    const char *cStoragePath = (*env)->GetStringUTFChars(env, storagePath, NULL);
    setenv("APP_RUNNING_IN_CONSOLE", "false", 1);

    // Concatenate with "/app/public"
    char fullPath[1024];
    sprintf(fullPath, "%s/app/public", cStoragePath);

    // Release resources
    (*env)->ReleaseStringUTFChars(env, storagePath, cStoragePath);
    (*env)->DeleteLocalRef(env, dirName);
    (*env)->DeleteLocalRef(env, storageDir);
    (*env)->DeleteLocalRef(env, storagePath);

    // Return the final path
    return (*env)->NewStringUTF(env, fullPath);
}

JNIEXPORT void JNICALL native_shutdown(JNIEnv *env, jobject thiz) {
    if (php_initialized) {
        php_embed_shutdown();
        php_initialized = 0;

        if (g_callback_obj) {
            (*env)->DeleteGlobalRef(env, g_callback_obj);
            g_callback_obj = NULL;
        }
        g_callback_method = NULL;

        if (g_bridge_instance) {
            (*env)->DeleteGlobalRef(env, g_bridge_instance);
            g_bridge_instance = NULL;
        }

        // Free the collected output buffer
        if (g_collected_output) {
            free(g_collected_output);
            g_collected_output = NULL;
            g_collected_length = 0;
            g_collected_capacity = 0;
        }
    }
}

JNIEXPORT jstring JNICALL native_execute_script(JNIEnv *env, jobject thiz, jstring filename) {
    const char *phpFilePath = (*env)->GetStringUTFChars(env, filename, NULL);

    zend_file_handle file_handle;
    zend_stream_init_filename(&file_handle, phpFilePath);

    php_execute_script(&file_handle);

    (*env)->ReleaseStringUTFChars(env, filename, phpFilePath);

    // Return collected output
    return (*env)->NewStringUTF(env, g_collected_output ? g_collected_output : "");
}

static JNINativeMethod gMethods[] = {
         // Updated method signature array for PHPBridge
            {"nativeExecuteScript", "(Ljava/lang/String;)Ljava/lang/String;", (void *) native_execute_script},
            {"initialize", "()V", (void *) native_initialize},
            {"shutdown", "()V", (void *) native_shutdown},
            {"setRequestInfo", "(Ljava/lang/String;Ljava/lang/String;Ljava/lang/String;)V", (void *) native_set_request_info},
            {"runRunnerCommand", "(Ljava/lang/String;)Ljava/lang/String;", (void *) native_run_runner_command},
            {"getAppPublicPath", "()Ljava/lang/String;", (void *) native_get_app_public_path},
            {"getAppPath", "()Ljava/lang/String;", (void *) native_get_app_path},
            {"nativeSetEnv", "(Ljava/lang/String;Ljava/lang/String;I)I", (void *) native_set_env},
            {"nativeHandleRequestOnce","(Ljava/lang/String;Ljava/lang/String;Ljava/lang/String;Ljava/lang/String;)Ljava/lang/String;",(void *) native_handle_request_once}
    };

JNIEXPORT jint JNICALL JNI_OnLoad(JavaVM *vm, void *reserved) {
    g_jvm = vm;

    JNIEnv *env;
    if ((*vm)->GetEnv(vm, (void **) &env, JNI_VERSION_1_6) != JNI_OK) {
        return JNI_ERR;
    }

    // Register native methods for PHPBridge
    jclass phpBridgeClass = (*env)->FindClass(env, "com/fuse/php/bridge/PHPBridge");
    if (phpBridgeClass == NULL) {
        return JNI_ERR;
    }

    if ((*env)->RegisterNatives(env, phpBridgeClass, gMethods, sizeof(gMethods) / sizeof(gMethods[0])) != 0) {
        return JNI_ERR;
    }

    // Register native methods for MobileEnvironment
    jclass mobileEnvClass = (*env)->FindClass(env, "com/fuse/php/bridge/MobileEnvironment");
    if (mobileEnvClass == NULL) {
        return JNI_ERR;
    }

    static JNINativeMethod envMethods[] = {
            {"nativeSetEnv", "(Ljava/lang/String;Ljava/lang/String;I)I", (void *) native_set_env},
    };

    if ((*env)->RegisterNatives(env, mobileEnvClass, envMethods, sizeof(envMethods) / sizeof(envMethods[0])) != 0) {
        return JNI_ERR;
    }

    // Initialize BridgeJNI (Connect C++ to Kotlin BridgeRouter)
    if (InitializeBridgeJNI(env) != JNI_OK) {
        LOGE("Failed to initialize BridgeJNI");
        return JNI_ERR;
    }

    return JNI_VERSION_1_6;
}
