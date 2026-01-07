<?php

namespace Engine\Mobile;

use ZipArchive;

/**
 * Class Manager
 *
 * Manages the lifecycle of the mobile application:
 * - Installation of native dependencies (JNI libs)
 * - Bundling of the PHP framework (production-ready)
 * - Building the Android APK
 * - Launching the application on a connected device
 *
 * @package Engine\Mobile
 */
class Manager
{
    /**
     * The root path of the application.
     *
     * @var string
     */
    protected string $rootPath;

    /**
     * The path to the Android project.
     *
     * @var string
     */
    protected string $androidPath;

    /**
     * Manager constructor.
     *
     * @param string|null $rootPath The root directory of the project.
     */
    public function __construct(?string $rootPath = null)
    {
        // If no root path provided, assume we are in system/engine/Mobile and go up 3 levels
        $this->rootPath = $rootPath ?? dirname(dirname(dirname(__DIR__)));
        $this->androidPath = $this->rootPath . '/native/android';
    }

    /**
     * Install native dependencies (JNI libraries).
     *
     * @return void
     */
    public function install(): void
    {
        echo "=== Native Dependencies Setup ===\n";

        $downloadUrl = "https://d23y5k23b3lz91.cloudfront.net/android/android/jniLibs.zip";
        $targetDir = $this->androidPath . '/app/src/main/jniLibs';
        $tempZip = sys_get_temp_dir() . '/mvc_jni_libs.zip';
        $tempExtract = sys_get_temp_dir() . '/mvc_jni_extract';

        echo "Target Directory: $targetDir\n";

        if (!is_dir($targetDir)) {
            echo "Creating target directory...\n";
            mkdir($targetDir, 0777, true);
        }

        echo "Downloading PHP runtime libraries from $downloadUrl...\n";

        $fp = fopen($tempZip, 'w+');
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200) {
            echo "Error: Failed to download libraries. HTTP Code: $httpCode\n";
            exit(1);
        }

        echo "Download complete. Extracting...\n";

        $zip = new ZipArchive;
        if ($zip->open($tempZip) === TRUE) {
            if (is_dir($tempExtract)) {
                $this->recursiveDelete($tempExtract);
            }
            mkdir($tempExtract, 0777, true);
            $zip->extractTo($tempExtract);
            $zip->close();
        } else {
            echo "Error: Failed to open ZIP file.\n";
            exit(1);
        }

        echo "Installing libraries to Android project...\n";

        $sourcePath = $tempExtract;
        $extractedItems = scandir($tempExtract);
        if (in_array('jniLibs', $extractedItems)) {
            $sourcePath = $tempExtract . '/jniLibs';
        }

        $this->recursiveCopy($sourcePath, $targetDir);

        echo "Cleaning up...\n";
        @unlink($tempZip);
        $this->recursiveDelete($tempExtract);

        echo "Native dependencies installed successfully.\n";
    }

    /**
     * Bundle the PHP application for Android.
     * This creates a production-ready build by installing dependencies without dev packages.
     *
     * @return void
     */
    public function bundle(): void
    {
        echo "=== Bundling Framework ===\n";

        $bundleZipPath = $this->androidPath . '/app/src/main/assets/app_bundle.zip';

        // 1. Prepare a temporary directory
        $tempDir = sys_get_temp_dir() . '/mvc_framework_build_' . time();
        echo "Preparing temporary build directory: $tempDir\n";

        if (is_dir($tempDir)) {
            $this->recursiveDelete($tempDir);
        }
        mkdir($tempDir, 0777, true);

        // 2. Copy project files to temp directory
        echo "Copying source files...\n";
        $exclusions = [
            '.git',
            '.idea',
            '.vscode',
            'native',
            'node_modules',
            'storage',
            'tests',
            'vendor', // We will reinstall vendor
            'app_bundle.zip',
            'laravel_bundle.zip',
            'system/engine/Mobile/resources'
        ];

        $this->recursiveCopy($this->rootPath, $tempDir, $exclusions);

        // 3. Install production dependencies
        if (file_exists($this->rootPath . '/composer.json')) {
            echo "Installing production dependencies (composer install --no-dev)...\n";
            // Check if composer is available
            $composerCmd = 'composer';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $composerCmd = 'composer.bat'; // Try batch file on Windows
            }

            // We need to copy the composer.json and lock file explicitly if they weren't copied (they should be)
            // Run composer in the temp dir
            $cmd = "cd $tempDir && $composerCmd install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs";
            exec($cmd, $output, $returnVar);

            if ($returnVar !== 0) {
                echo "Warning: Composer install failed or composer not found. Using current vendor directory (might include dev deps).\n";
                echo "Output: " . implode("\n", $output) . "\n";
                // Fallback: copy current vendor
                if (is_dir($this->rootPath . '/vendor')) {
                    $this->recursiveCopy($this->rootPath . '/vendor', $tempDir . '/vendor');
                }
            }
        } else {
            echo "No composer.json found. Skipping composer install.\n";
            // If vendor exists in root but was excluded, copy it back?
            // But we excluded 'vendor' in step 2.
            // If there is no composer.json, maybe there is no vendor, or it's a manual vendor.
            // Let's copy vendor if it exists and we didn't run composer.
            if (is_dir($this->rootPath . '/vendor')) {
                 echo "Copying existing vendor directory...\n";
                 $this->recursiveCopy($this->rootPath . '/vendor', $tempDir . '/vendor');
            }
        }

        // 4. Create ZIP bundle
        echo "Creating bundle archive at $bundleZipPath...\n";

        if (!is_dir(dirname($bundleZipPath))) {
            mkdir(dirname($bundleZipPath), 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($bundleZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            die("Failed to create bundle zip.\n");
        }

        $this->addDirToZip($zip, $tempDir, '');

        $zip->close();

        // 5. Cleanup
        echo "Cleaning up temp directory...\n";
        $this->recursiveDelete($tempDir);

        echo "Bundle created successfully. Size: " . round(filesize($bundleZipPath) / 1024 / 1024, 2) . " MB\n";
    }

    /**
     * Build the Android APK.
     *
     * @return void
     */
    public function build(): void
    {
        echo "=== Building Android App ===\n";

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $gradlew = $isWindows ? 'gradlew.bat' : './gradlew';

        // Check if wrapper exists
        if (!file_exists($this->androidPath . '/' . ($isWindows ? $gradlew : 'gradlew'))) {
            echo "Gradle wrapper not found. Falling back to global 'gradle' command.\n";
            $cmd = "cd {$this->androidPath} && gradle assembleDebug";
        } else {
            $cmd = "cd {$this->androidPath} && $gradlew assembleDebug";
        }

        echo "Running Gradle build ($cmd)...\n";

        // Use passthru to show real-time output
        passthru($cmd, $returnVar);

        if ($returnVar !== 0) {
            echo "Build failed.\n";
            exit(1);
        }

        echo "Build successful.\n";
    }

    /**
     * Launch the app on a connected device.
     *
     * @return void
     */
    public function launch(): void
    {
        echo "=== Launching App ===\n";

        // Install the app first
        $this->installApp();

        $package = $this->getPackageName();
        if (!$package) {
            echo "Could not detect package name. Defaulting to com.mvc.mobile\n";
            $package = 'com.mvc.mobile';
        }

        $activityName = $this->getLaunchableActivity();
        if ($activityName) {
            if (substr($activityName, 0, 1) === '.') {
                $activity = $package . $activityName;
            } elseif (strpos($activityName, '.') === false) {
                $activity = $package . '.' . $activityName;
            } else {
                $activity = $activityName;
            }
        } else {
            $activity = $package . '.MainActivity';
        }

        $cmd = "adb shell am start -n $package/$activity";
        echo "Running: $cmd\n";

        $returnVar = $this->executeCommand($cmd);

        if ($returnVar !== 0) {
            echo "Launch failed. Is a device connected?\n";
        } else {
            echo "App launched.\n";
        }
    }

    /**
     * Install the APK to the connected device.
     *
     * @return void
     */
    protected function installApp(): void
    {
        echo "Installing APK...\n";

        $apkPath = $this->androidPath . '/app/build/outputs/apk/debug/app-debug.apk';

        if (!file_exists($apkPath)) {
            echo "Error: APK not found at $apkPath. Build might have failed.\n";
            return;
        }

        $cmd = "adb install -r \"$apkPath\"";
        echo "Running: $cmd\n";

        $returnVar = $this->executeCommand($cmd);

        if ($returnVar !== 0) {
            echo "Installation failed.\n";
        } else {
            echo "APK installed successfully.\n";
        }
    }

    /**
     * Execute a shell command.
     *
     * @param string $cmd
     * @return int
     */
    protected function executeCommand(string $cmd): int
    {
        passthru($cmd, $returnVar);
        return $returnVar;
    }

    /**
     * Get the launchable activity name from AndroidManifest.xml.
     *
     * @return string|null
     */
    protected function getLaunchableActivity(): ?string
    {
        $manifestFile = $this->androidPath . '/app/src/main/AndroidManifest.xml';
        if (!file_exists($manifestFile)) {
            return null;
        }

        $content = file_get_contents($manifestFile);

        try {
            $xml = simplexml_load_string($content);
            if ($xml === false) return null;

            $xml->registerXPathNamespace('android', 'http://schemas.android.com/apk/res/android');

            // Find activity with MAIN action and LAUNCHER category
            $activities = $xml->xpath('//application/activity[intent-filter/action[@android:name="android.intent.action.MAIN"] and intent-filter/category[@android:name="android.intent.category.LAUNCHER"]]');

            if (!empty($activities)) {
                $attributes = $activities[0]->attributes('android', true);
                return (string)$attributes['name'];
            }
        } catch (\Exception $e) {
            // Ignore XML errors
        }

        return null;
    }

    /**
     * Get the package name from the Android project.
     *
     * @return string|null
     */
    public function getPackageName(): ?string
    {
        $gradleFile = $this->androidPath . '/app/build.gradle.kts';
        if (file_exists($gradleFile)) {
            $content = file_get_contents($gradleFile);
            if (preg_match('/applicationId\s*=\s*"([^"]+)"/', $content, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Helper to recursively copy directories.
     *
     * @param string $src Source directory path.
     * @param string $dst Destination directory path.
     * @param array $exclusions List of filenames to exclude.
     * @return void
     */
    protected function recursiveCopy(string $src, string $dst, array $exclusions = []): void
    {
        $dir = opendir($src);
        if (!is_dir($dst)) mkdir($dst, 0777, true);

        $normalizedExclusions = array_map(function ($ex) {
            return str_replace('\\', '/', rtrim($this->rootPath . '/' . $ex, '/'));
        }, $exclusions);

        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $current = $src . '/' . $file;
            $currentNorm = str_replace('\\', '/', $current);

            $basenameExcluded = in_array($file, $exclusions, true);
            $pathExcluded = false;
            foreach ($normalizedExclusions as $exAbs) {
                if (str_starts_with($currentNorm, $exAbs)) {
                    $pathExcluded = true;
                    break;
                }
            }
            if ($basenameExcluded || $pathExcluded) {
                continue;
            }

            if (is_dir($current)) {
                $this->recursiveCopy($current, $dst . '/' . $file, $exclusions);
            } else {
                copy($current, $dst . '/' . $file);
            }
        }
        closedir($dir);
    }

    /**
     * Helper to recursively delete directories.
     *
     * @param string $dir The directory to delete.
     * @return void
     */
    protected function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    /**
     * Helper to add directory to zip.
     *
     * @param ZipArchive $zip The ZipArchive instance.
     * @param string $path The path to add.
     * @param string $localPath The local path inside the zip.
     * @return void
     */
    protected function addDirToZip(ZipArchive $zip, string $path, string $localPath): void
    {
        if (!is_dir($path)) return;

        $basePath = realpath($path);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();

            // Calculate relative path inside the zip
            $relativePath = substr($filePath, strlen($basePath));
            $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

            if ($localPath !== '') {
                $relativePath = $localPath . '/' . $relativePath;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
}
