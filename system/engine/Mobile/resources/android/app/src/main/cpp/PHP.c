#include "PHP.h"
#include <android/log.h>
#include <string.h>
#include <stdlib.h>

#define LOG_TAG "PHP-Native"
#define LOGI(...) __android_log_print(ANDROID_LOG_INFO, LOG_TAG, __VA_ARGS__)
#define LOGE(...) __android_log_print(ANDROID_LOG_ERROR, LOG_TAG, __VA_ARGS__)
static php_stream *g_stdout_stream = NULL;
extern void pipe_php_output(const char* str);

void initialize_php_with_request(const char *post_data, const char *method, const char *uri, const char *content_type) {
    LOGI("🛠️ Starting PHP request startup");
    LOGI("🐛 initialize_php_with_request called with method=%s uri=%s ct=%s", method, uri, content_type ? content_type : "NULL");

    // Step 0: Pre-fill SG(request_info) BEFORE startup
    // This is CRITICAL for session persistence so PHP sees the cookie during startup
    SG(request_info).request_method = method;
    SG(request_info).request_uri = (char*)uri;
    SG(request_info).content_type = (char*)content_type;
    SG(request_info).content_length = post_data ? strlen(post_data) : 0;
    SG(request_info).proto_num = 1001; // HTTP/1.1

    const char* cookie_header = getenv("HTTP_COOKIE");
    if (cookie_header) {
        SG(request_info).cookie_data = (char*)cookie_header;
        LOGI("🍪 Pre-set SG(request_info).cookie_data: %s", cookie_header);
    } else {
        LOGI("🚫 No HTTP_COOKIE found in environment for SG setup");
    }

    // Step 1: Bootstrap PHP internals (superglobals, session, etc)
    if (php_request_startup() == FAILURE) {
        LOGE("❌ php_request_startup() failed");
        php_module_shutdown();
        return;
    }
    LOGI("✅ php_request_startup() completed");

    // Step 2: Populate $_SERVER
    zval server_array;
    array_init(&server_array);

    add_assoc_string(&server_array, "REQUEST_METHOD", (char*)method);
    add_assoc_string(&server_array, "REQUEST_URI", (char*)uri);
    add_assoc_string(&server_array, "SCRIPT_NAME", "/mobile_boot.php");
    add_assoc_string(&server_array, "SCRIPT_FILENAME", "/mobile_boot.php");
    add_assoc_string(&server_array, "SERVER_PROTOCOL", "HTTP/1.1");
    add_assoc_string(&server_array, "SERVER_NAME", "127.0.0.1");
    add_assoc_string(&server_array, "SERVER_PORT", "80");
    add_assoc_string(&server_array, "REMOTE_ADDR", "127.0.0.1");
    add_assoc_string(&server_array, "PHP_SELF", "/mobile_boot.php");
    add_assoc_string(&server_array, "REQUEST_SCHEME", "http");
    add_assoc_string(&server_array, "HTTP_HOST", "127.0.0.1");
    add_assoc_string(&server_array, "HTTPS", "off");
    add_assoc_string(&server_array, "HTTP_USER_AGENT", "PHPNative/1.0");
    add_assoc_long(&server_array, "REQUEST_TIME", time(NULL));

    zend_hash_str_update(&EG(symbol_table), "_SERVER", sizeof("_SERVER") - 1, &server_array);
    LOGI("✅ $_SERVER populated");

    // Step 3: Populate $_COOKIE manually (backup/ensure)
    // Even though we set cookie_data, we manually ensure _COOKIE is populated correctly
    if (cookie_header) {
        zval cookie_array;
        array_init(&cookie_array);

        char* cookies = strdup(cookie_header);
        char* token = strtok(cookies, ";");
        while (token) {
            while (*token == ' ') token++;
            char* equal = strchr(token, '=');
            if (equal) {
                *equal = '\0';
                const char* key = token;
                const char* val = equal + 1;
                // LOGI("🍪 Parsed cookie → %s = %s", key, val);
                add_assoc_string(&cookie_array, key, val);
            }
            token = strtok(NULL, ";");
        }
        free(cookies);

        zend_hash_str_update(&EG(symbol_table), "_COOKIE", sizeof("_COOKIE") - 1, &cookie_array);
        LOGI("✅ $_COOKIE manually populated from HTTP_COOKIE");
    }

    // Step 4: Setup PHP output buffer (stdout stream)
    LOGI("🌀 Setting up memory output stream");
    g_stdout_stream = php_stream_memory_create(TEMP_STREAM_DEFAULT);
    if (!g_stdout_stream) {
        LOGE("❌ Failed to create STDOUT memory stream");
        return;
    }

    zend_string *stdout_name = zend_string_init("STDOUT", sizeof("STDOUT") - 1, 0);
    zval stdout_handle;
    php_stream_to_zval(g_stdout_stream, &stdout_handle);
    zend_hash_add(&EG(symbol_table), stdout_name, &stdout_handle);
    zend_string_release(stdout_name);
    LOGI("✅ STDOUT memory stream ready");

    php_output_activate();

    // Step 5: Setup POST/PATCH/PUT body if needed
    if (post_data) {
        size_t post_data_length = strlen(post_data);

        LOGI("📮 Detected POST request");
        LOGI("📦 POST body length: %zu", post_data_length);

        php_stream *mem_stream = php_stream_memory_create(TEMP_STREAM_DEFAULT);
        php_stream_write(mem_stream, post_data, post_data_length);

        SG(request_info).request_body = mem_stream;
        // content_length/type already set in Step 0

        // Fallback logic from previous working build
        // If content_type is missing OR if it's not JSON, force application/x-www-form-urlencoded
        // This ensures traditional forms (which might miss the header or be generic) are parsed correctly
        if (content_type && strstr(content_type, "json")) {
            SG(request_info).content_type = "application/json";
        } else {
            // Force urlencoded for everything else (traditional forms)
            SG(request_info).content_type = "application/x-www-form-urlencoded";
            LOGI("📄 Enforcing application/x-www-form-urlencoded (Old Build Behavior)");
        }

        // Re-fetch content_type from SG as we might have changed it
        content_type = SG(request_info).content_type;

        // Populate $_POST for urlencoded forms (fallback to method if content-type missing)
        if ((content_type && strstr(content_type, "application/x-www-form-urlencoded")) ||
            (strcmp(method, "POST") == 0 || strcmp(method, "PUT") == 0 || strcmp(method, "PATCH") == 0)) {
            sapi_module.treat_data(PARSE_POST, (char*)post_data, NULL);
            LOGI("✅ Parsed POST form data into $_POST");
        }
    }

    // Finalize request startup state (redundant but safe)
    PG(during_request_startup) = 0;
    EG(exit_status) = 0;
}


// Add this new function to read output from the stdout stream
// Function to capture stdout content after PHP execution
void capture_php_stdout_output() {
    if (!g_stdout_stream) {
        LOGI("No stdout stream to capture");
        return;
    }

    // Flush the stream to make sure all data is in memory
    php_stream_flush(g_stdout_stream);

    // Get the length of data in the stream
    php_stream_seek(g_stdout_stream, 0, SEEK_END);
    size_t size = php_stream_tell(g_stdout_stream);

    if (size > 0) {
        // Allocate buffer for the data
        char *buffer = (char*)malloc(size + 1);
        if (buffer) {
            // Rewind to beginning
            php_stream_rewind(g_stdout_stream);

            // Read all data
            size_t bytes_read = php_stream_read(g_stdout_stream, buffer, size);
            buffer[bytes_read] = '\0';

            LOGI("Captured %zu bytes from stdout stream", bytes_read);

            // Send to our output collector
            pipe_php_output(buffer);

            free(buffer);
        }
    } else {
        LOGI("Stdout stream is empty");
    }
}

// Helper to write headers to the output stream
static void android_send_header(sapi_header_struct *sapi_header, void *server_context) {
    if (sapi_header && g_stdout_stream) {
        // LOGI("📤 Sending header: %s", sapi_header->header);
        php_stream_write(g_stdout_stream, sapi_header->header, strlen(sapi_header->header));
        php_stream_write(g_stdout_stream, "\r\n", 2);
    }
}

// Helper to send all headers including status line
static int android_send_headers(sapi_headers_struct *sapi_headers) {
    if (!g_stdout_stream) return SAPI_HEADER_SEND_FAILED;

    char buf[1024];
    // Simple status line construction
    int response_code = SG(sapi_headers).http_response_code;
    if (response_code == 0) response_code = 200;

    // Note: We could map status codes to text (e.g. 200 OK, 404 Not Found),
    // but for now "OK" or just the code is often sufficient for the bridge parser
    // which primarily looks at the code.
    // However, proper HTTP requires a reason phrase.
    const char* reason = "OK";
    if (response_code == 302) reason = "Found";
    else if (response_code == 404) reason = "Not Found";
    else if (response_code == 500) reason = "Internal Server Error";

    snprintf(buf, sizeof(buf), "HTTP/1.1 %d %s\r\n", response_code, reason);
    php_stream_write(g_stdout_stream, buf, strlen(buf));

    // Iterate and send all headers
    zend_llist_apply_with_argument(&SG(sapi_headers).headers,
                                   (llist_apply_with_arg_func_t) android_send_header,
                                   NULL);

    // End of headers section
    php_stream_write(g_stdout_stream, "\r\n", 2);

    return SAPI_HEADER_SENT_SUCCESSFULLY;
}

static sapi_module_struct php_module = {
        "android",                     // name
        "Android Embedded PHP",        // pretty name

        NULL,                         // startup
        php_module_shutdown_wrapper,   // shutdown

        NULL,                         // activate
        NULL,                         // deactivate

        capture_php_output,           // unbuffered write
        NULL,                         // flush
        NULL,                         // get uid
        NULL,                         // getenv

        NULL,                         // sapi error handler
        NULL,                         // header handler
        android_send_headers,         // send headers handler
        android_send_header,          // send header handler

        NULL,                         // read POST data
        NULL,                         // read Cookies

        NULL,                         // register server variables
        NULL,                         // log message
        NULL,                         // get request time
        NULL,                         // terminate process

        NULL,                         // php_ini_path_override
        NULL,                         // default_post_reader
        NULL,                         // treat_data
        NULL,                         // executable_location

        0,                           // php_ini_ignore
        0,                           // php_ini_ignore_cwd
        NULL,                         // get_fd
        NULL,                         // force_http_10
        NULL,                         // get_target_uid
        NULL,                         // get_target_gid
        NULL,                         // input_filter
        NULL,                         // ini_defaults
        0,                           // phpinfo_as_text
        NULL,                         // ini_entries
        NULL                          // additional_functions
};
