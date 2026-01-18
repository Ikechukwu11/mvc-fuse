- Define MVP features and flows
- Plan MediaStore integration and persistence
- Outline secure storage strategy and extension roadmap
  MVP Features

- Player: play/pause/resume, seek, next/prev, shuffle/repeat, current queue
- Lockscreen: fully functional MediaSession controls, artwork, position, seek
- Notification: persistent foreground playback with actions (play/pause, next/prev), media style
- Widget: compact controls (play/pause, next/prev), title/artist, artwork thumbnail
- Library: scan device music via MediaStore, build a local catalog, fast search
- Persistence: store catalog and user selections in JSON under persisted_data
- Permissions: runtime READ_MEDIA_AUDIO (API 33+), READ_EXTERNAL_STORAGE (older), POST_NOTIFICATIONS
  Device Library & Persistence

- Native scan: build a dedicated scanner, not in MainActivity
  - Create MediaStoreScanner to query MediaStore.Audio.Media with projections:
    - id, contentUri, title, artist, album, duration, track, albumId, dateAdded
    - optional artwork via MediaStore.Audio.Albums (album art) or load on demand
  - Return results as JSON through bridge functions (e.g., MediaLibrary.List, MediaLibrary.Search)
- Persistence layer (JSON, not global SQLite):
  - Use Storage to write/read catalog and user data:
    - storage/app/music/library.json: indexed tracks
    - storage/app/music/playlists.json: user playlists
    - storage/app/music/settings.json: playback prefs (shuffle/repeat)
  - Merge strategy:
    - First boot: full scan → write library.json
    - Subsequent boots: quick scan by dateAdded or id; update deltas
- PHP facade and component:

  - Facade MediaLibrary to call bridge and hydrate component
  - Component shows library, allows enqueue and play
    Playback Service & Lockscreen

- Service: continue with Media3+ExoPlayer in MediaPlaybackService.kt
  - Playlist management via ExoPlayer’s media items
  - Expose actions: PLAY, PAUSE, RESUME, NEXT, PREV, SEEK, TOGGLE_REPEAT, TOGGLE_SHUFFLE
- MediaSession metadata:
  - Set title/artist/artworkUri; update position and state
  - Ensure controller calls run on UI thread; current fix applied in MediaFunctions.kt
- Lockscreen integration:

  - MediaSessionService handles transport controls automatically
  - Verify lockscreen shows current track and allows actions
    Notification & Widget

- Notification:
  - Media style notification via MediaSession; ensure foreground service type mediaPlayback is set in AndroidManifest.xml
  - Add next/prev actions; sync state with player listeners
- Widget:

  - Extend MusicWidgetProvider.kt
  - Add next/prev PendingIntent, artwork thumbnail binding, update on broadcasts
    Secure Storage

- Use EncryptedSharedPreferences + Android Keystore in a separate function file
  - SecureStorageFunctions: set(key, value), get(key), delete(key)
  - Use for sensitive items (favorites sync token, cloud auth, user preferences that require confidentiality)
- Bridge to PHP:
  - Native\Mobile\Facades\App or a dedicated Secure facade to call secure storage functions
- Do not store catalog in secure storage; use JSON under persisted_data for size and speed
  Permissions

- READ_MEDIA_AUDIO (API 33+), fallback READ_EXTERNAL_STORAGE (older)
- POST_NOTIFICATIONS (API 33+) for notification actions
- Request flow:

  - On first library access, ask for permission via a bridge method (DeviceFunctions or new PermissionFunctions); retry scan after grant
    Why your local music didn’t play

- We fixed thread violations by running controller ops on the UI thread
- We trimmed backticks and whitespace from incoming URL/title/artist to avoid malformed URIs
- For library scan-based playback, ensure ExoPlayer receives a content:// URI from MediaStore; picker still works but the full library needs MediaStore scanning plus permissions
  Extension Roadmap

- Queue management: add server-driven queue UI, sync with ExoPlayer playlist
- Favorites and playlists: persist in storage/app/music/playlists.json, expose CRUD
- Artwork pipeline: load album art lazily; cache thumbnails in storage/app/music/art/
- Search: in-memory index on PHP side; optional native filter via MediaStore query parameters
- Background resume: auto-reconnect to MediaSession on app resume; keep queue state in service
- Cloud sync (optional): securely store tokens via SecureStorageFunctions; sync playlists/favorites
  Verification Plan

- Permissions: prompt and grant; retry scan
- Library: verify library.json exists and reflects device songs
- Playback: pick local track via library and confirm notification/lockscreen controls
- Widget: verify updates on play/pause, next/prev
- Persistence: restart app; library and playlists load from storage
  If you’re good with this feature map, I’ll implement:

- MediaStoreScanner and MediaLibrary bridge functions
- JSON persistence via Storage for library/playlists/settings
- SecureStorageFunctions with EncryptedSharedPreferences
- Expanded notification and widget actions
- Permissions request flow and retries
