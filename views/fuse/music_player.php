<?php
$formatMs = function (int $ms): string {
    if ($ms <= 0) return '0:00';
    $sec = (int)round($ms / 1000);
    $m = (int)floor($sec / 60);
    $s = $sec % 60;
    return sprintf('%d:%02d', $m, $s);
};
?>
<div class="mp-card" style="position:relative"
     fuse:window-on="Media.TrackPicked:onTrackPicked;MediaLibrary.Scanned:onLibraryScanned;Secure.Value:onSecureValue;Media.State:onMediaState;App.StorageFileRead:onStorageFileRead"
     fuse:loading-target="#player-spinner">
    <style>
        .mp-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            padding: 16px;
        }
        .mp-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .mp-art {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            object-fit: cover;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
        }
        .mp-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        .mp-artist {
            font-size: 14px;
            color: #6b7280;
            margin: 2px 0 0 0;
        }
        .mp-controls {
            display: flex;
            gap: 10px;
            margin: 10px 0 14px 0;
            flex-wrap: wrap;
        }
        .mp-btn {
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            font-weight: 600;
        }
        .mp-btn-primary { background:#2563eb; color:#fff; }
        .mp-btn-warn { background:#d97706; color:#fff; }
        .mp-btn-gray { background:#e5e7eb; color:#111827; }
        .mp-btn-purple { background:#7c3aed; color:#fff; }
        .mp-btn-indigo { background:#4f46e5; color:#fff; }
        .mp-range {
            width: 100%;
        }
        .mp-section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 18px 0 8px 0;
        }
        .mp-spinner {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 18px;
            height: 18px;
            border: 2px solid transparent;
            border-top-color: #4f46e5;
            border-radius: 50%;
            animation: mp-spin 0.9s linear infinite;
            display: none;
        }
        @keyframes mp-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <div id="player-spinner" class="mp-spinner"></div>
    <div class="mp-header">
        <img class="mp-art" src="<?= e($artwork ?? '') ?>" alt="artwork" onerror="this.onerror=null;this.src='<?= e($defaultArtwork ?? '') ?>'">
        <div>
            <p class="mp-title" fuse:text="title"></p>
            <p class="mp-artist" fuse:text="artist"></p>
        </div>
    </div>

    <div class="mp-controls">
        <button fuse:click="play" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-primary" fuse:if="!isPlaying">Play</button>
        <button fuse:click="pause" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-warn" fuse:if="isPlaying">Pause</button>
        <button fuse:click="previous" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-gray">Prev</button>
        <button fuse:click="next" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-gray">Next</button>
        <button fuse:click="toggleShuffle" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-purple">Shuffle: <?= e(($shuffle ?? false) ? 'On' : 'Off') ?></button>
        <button fuse:click="toggleRepeat" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-indigo">Repeat: <?= e(strtoupper($repeat ?? 'all')) ?></button>
    </div>

    <div class="mt-2">
        <div style="font-size:12px; color:#374151; margin-bottom:6px;">
            <span>Position: <?= e($formatMs((int)($positionMs ?? 0))) ?></span>
            <span> / </span>
            <span>Duration: <?= e($formatMs((int)($durationMs ?? 0))) ?></span>
            <button fuse:click="refreshState" fuse:loading-target="#player-spinner" style="margin-left:6px; color:#2563eb; text-decoration:underline; font-size:11px; background:none; border:none; cursor:pointer;">Refresh</button>
        </div>
        <input class="mp-range" type="range" min="0" max="<?= e((int)($durationMs ?? 0)) ?>" value="<?= e((int)($positionMs ?? 0)) ?>" fuse:input="setSeek($event.target.value)">
    </div>

    <div>
        <button fuse:click="pick" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-gray">Pick Song from Device</button>
        <?php if (!empty($isScanning ?? false)): ?>
            <button class="mp-btn" style="background:#9CA3AF; color:#fff;" disabled>Scanning...</button>
        <?php else: ?>
            <button fuse:click="scan" fuse:loading-target="#player-spinner" class="mp-btn" style="background:#059669; color:#fff;">Scan Library</button>
        <?php endif; ?>
    </div>

    <div class="mt-6">
        <div class="mp-section-title">Library</div>
        <?php if (!empty($isScanning ?? false)): ?>
            <div style="display:flex;align-items:center;gap:8px;color:#2563eb;font-weight:600;">
                <span>Scanning library...</span>
                <span class="mp-spinner" style="display:inline-block; position:static; width:14px; height:14px;"></span>
            </div>
        <?php endif; ?>
        <button fuse:click="loadPersistedLibrary" fuse:loading-target="#player-spinner" style="color:#2563eb; text-decoration:underline; background:none; border:none; cursor:pointer;">Load Saved Library</button>
        <ul class="mt-2">
            <?php if (!empty($library ?? [])): ?>
                <?php foreach (array_slice($library, 0, 20) as $track): ?>
                    <li style="padding:6px 0; display:flex; align-items:center; justify-content:space-between;">
                        <span><?= e(($track['title'] ?? 'Unknown') . ' - ' . ($track['artist'] ?? '')) ?></span>
                        <button fuse:click="playTrack('<?= e($track['uri'] ?? '') ?>','<?= e($track['title'] ?? 'Unknown') ?>','<?= e($track['artist'] ?? 'Unknown') ?>')" fuse:loading-target="#player-spinner" class="mp-btn mp-btn-primary" style="font-size:12px; padding:6px 10px;">Play</button>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li style="color:#6b7280;">No tracks loaded</li>
            <?php endif; ?>
        </ul>
    </div>
</div>
