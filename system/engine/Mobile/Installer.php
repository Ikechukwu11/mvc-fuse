<?php

namespace Engine\Mobile;

/**
 * Class Installer
 *
 * Handles the initialization of the mobile project structure.
 * Scaffolds the Android project from the internal skeleton and configures it.
 *
 * @package Engine\Mobile
 */
class Installer
{
    /**
     * The root path of the application.
     *
     * @var string
     */
    protected string $rootPath;

    /**
     * The path to the internal skeleton resources.
     *
     * @var string
     */
    protected string $skeletonPath;

    /**
     * Installer constructor.
     *
     * @param string $rootPath The root directory of the project.
     */
    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->skeletonPath = __DIR__ . '/resources/android';
    }

    /**
     * Install the mobile project structure.
     *
     * @param string $packageName The package name (e.g., com.example.app).
     * @param string $appName The application name (e.g., "My App").
     * @return void
     */
    public function install(string $packageName = 'com.fuse.php', string $appName = 'MVC Mobile'): void
    {
        echo "=== Initializing Mobile Project ===\n";
        echo "Package Name: $packageName\n";
        echo "App Name: $appName\n";

        $targetDir = $this->rootPath . '/native/android';

        if (is_dir($targetDir.'/'.$packageName)) {
            echo "Error: 'native/android/$packageName' directory already exists. Please remove it before re-initializing.\n";
            return;
        }

        // 1. Copy Skeleton
        echo "Scaffolding Android project...\n";
        $this->recursiveCopy($this->skeletonPath, $targetDir);

        // 2. Configure Project (Rename Package)
        echo "Configuring project package...\n";
        $this->renamePackage($targetDir, 'com.fuse.php', $packageName);

        // 3. Configure App Name
        echo "Setting application name...\n";
        $this->setAppName($targetDir, $appName);

        // 4. Configure Gradle Build Settings
        echo "Configuring Gradle settings...\n";
        $this->configureGradle($targetDir, $packageName);

        echo "Mobile project initialized successfully in native/android.\n";
    }

    /**
     * Configure Gradle build settings by replacing placeholders.
     *
     * @param string $targetDir
     * @param string $packageName
     */
    protected function configureGradle(string $targetDir, string $packageName): void
    {
        $gradleFile = $targetDir . '/app/build.gradle.kts';
        if (file_exists($gradleFile)) {
            $content = file_get_contents($gradleFile);

            $replacements = [
                'REPLACE_APP_ID' => $packageName,
                'REPLACEMECODE' => '1',
                'REPLACE_MINIFY_ENABLED' => 'false',
                'REPLACE_SHRINK_RESOURCES' => 'false',
                'REPLACE_DEBUG_SYMBOLS' => 'FULL',
                'REPLACEME' => 'DEBUG'
            ];

            foreach ($replacements as $placeholder => $value) {
                $content = str_replace($placeholder, $value, $content);
            }

            file_put_contents($gradleFile, $content);
        }
    }

    /**
     * Recursively copy a directory.
     *
     * @param string $src Source directory.
     * @param string $dst Destination directory.
     * @return void
     */
    protected function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (!is_dir($dst)) mkdir($dst, 0755, true);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Rename the package in files and directory structure.
     *
     * @param string $targetDir The Android project root.
     * @param string $oldPackage The old package name.
     * @param string $newPackage The new package name.
     * @return void
     */
    protected function renamePackage(string $targetDir, string $oldPackage, string $newPackage): void
    {
        // Replace in files
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($targetDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $oldPath = str_replace('.', '/', $oldPackage);
        $newPath = str_replace('.', '/', $newPackage);

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $extension = $item->getExtension();
                if (in_array($extension, ['xml', 'gradle', 'java', 'kt', 'kts', 'pro', 'c', 'cpp', 'h'])) {
                    $content = file_get_contents($item->getPathname());
                    $modified = false;

                    // Standard package replacement (dots)
                    if (strpos($content, $oldPackage) !== false) {
                        $content = str_replace($oldPackage, $newPackage, $content);
                        $modified = true;
                    }

                    // JNI path replacement (slashes) - Critical for C/C++ bridge
                    if (strpos($content, $oldPath) !== false) {
                        $content = str_replace($oldPath, $newPath, $content);
                        $modified = true;
                    }

                    if ($modified) {
                        file_put_contents($item->getPathname(), $content);
                    }
                }
            }
        }

        // Move directories
        // Java/Kotlin files are usually in src/main/java/com/old/package
        // We need to move them to src/main/java/com/new/package

        $oldPath = str_replace('.', '/', $oldPackage);
        $newPath = str_replace('.', '/', $newPackage);

        $srcDirs = [
            $targetDir . '/app/src/main/java',
            $targetDir . '/app/src/main/kotlin',
            $targetDir . '/app/src/androidTest/java',
            $targetDir . '/app/src/test/java'
        ];

        foreach ($srcDirs as $srcBase) {
            $oldDir = $srcBase . '/' . $oldPath;
            $newDir = $srcBase . '/' . $newPath;

            if (is_dir($oldDir)) {
                if (!is_dir($newDir)) {
                    mkdir($newDir, 0755, true);
                }

                // Move files from oldDir to newDir
                $this->moveDirContent($oldDir, $newDir);

                // Remove old empty directories
                // e.g. com/fusenative/mobile -> remove mobile, fusenative
                $this->cleanupEmptyDirs($srcBase, $oldPath);
            }
        }
    }

    /**
     * Set the application name in strings.xml or build.gradle.
     *
     * @param string $targetDir
     * @param string $appName
     */
    protected function setAppName(string $targetDir, string $appName): void
    {
        // Typically in app/src/main/res/values/strings.xml
        $stringsXml = $targetDir . '/app/src/main/res/values/strings.xml';
        if (file_exists($stringsXml)) {
            $content = file_get_contents($stringsXml);
            // Simple regex replace for <string name="app_name">...</string>
            $content = preg_replace(
                '/<string name="app_name">.*?<\/string>/',
                '<string name="app_name">' . htmlspecialchars($appName) . '</string>',
                $content
            );
            file_put_contents($stringsXml, $content);
        }
    }

    /**
     * Move content from one directory to another.
     */
    protected function moveDirContent($src, $dst) {
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            rename($src . '/' . $file, $dst . '/' . $file);
        }
    }

    /**
     * Clean up empty directories after move.
     */
    protected function cleanupEmptyDirs($base, $path) {
        $parts = explode('/', $path);
        while (!empty($parts)) {
            $currentPath = $base . '/' . implode('/', $parts);
            if (is_dir($currentPath) && count(scandir($currentPath)) == 2) { // . and ..
                rmdir($currentPath);
            }
            array_pop($parts);
        }
    }
}
