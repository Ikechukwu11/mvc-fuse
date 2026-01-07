@echo off
setlocal EnableDelayedExpansion

echo === Mobile Build Tool (No PHP Required) ===

set "SOURCE_DIR=%~dp0"
set "ANDROID_DIR=%SOURCE_DIR%native\android"
set "ASSETS_DIR=%ANDROID_DIR%\app\src\main\assets"
set "BUNDLE_ZIP=%ASSETS_DIR%\app_bundle.zip"
set "TEMP_DIR=%TEMP%\mvc_mobile_build_%RANDOM%"

echo [1/5] Preparing build environment...
if not exist "%ASSETS_DIR%" mkdir "%ASSETS_DIR%"
if exist "%TEMP_DIR%" rmdir /s /q "%TEMP_DIR%"
mkdir "%TEMP_DIR%"

echo [2/5] Copying project files...
rem Exclude list: .git .idea .vscode native node_modules storage tests vendor app_bundle.zip laravel_bundle.zip
robocopy "%SOURCE_DIR%." "%TEMP_DIR%" /E /XD .git .idea .vscode native node_modules storage tests vendor /XF app_bundle.zip laravel_bundle.zip mobile_build.bat > nul

echo [3/5] Creating app bundle...
cd /d "%TEMP_DIR%"
jar cMf bundle.zip .
if %ERRORLEVEL% NEQ 0 (
    echo Error: Failed to create zip with 'jar'. Make sure JDK is in your PATH.
    goto :error
)
move /Y bundle.zip "%BUNDLE_ZIP%" > nul
cd /d "%SOURCE_DIR%"
rmdir /s /q "%TEMP_DIR%"
echo Bundle created at: %BUNDLE_ZIP%

echo [4/5] Building and Installing APK...
cd /d "%ANDROID_DIR%"
call gradlew.bat installDebug
if %ERRORLEVEL% NEQ 0 (
    echo Error: Gradle build failed.
    goto :error
)

echo [5/5] Launching App...
adb shell am force-stop com.fuse.php
adb shell am start -n com.fuse.php/com.fuse.php.ui.MainActivity
echo.
echo Build and Launch Complete!
goto :end

:error
echo.
echo Build Failed.
cd /d "%SOURCE_DIR%"
exit /b 1

:end
cd /d "%SOURCE_DIR%"
exit /b 0
