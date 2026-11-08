// ═══════════════════════════════════════════════════════════
// Barangay Lipay Document Request System — Service Worker
// Version: brgy-lipay-v3
// ═══════════════════════════════════════════════════════════

const CACHE_VERSION = "brgy-lipay-v3";
const STATIC_CACHE  = `${CACHE_VERSION}-static`;

// App shell — relative paths (./) para gumana sa /barangay3/ at sa root
const PRECACHE_ASSETS = [
    "./",
    "./index.php",
    "./manifest.json",
    "./uploads/lipay.png",
    "./offline.html"
];

const OFFLINE_URL = "./offline.html";

// ❌ HUWAG i-cache — dynamic / auth / API / admin
const NEVER_CACHE = [
    /\/auth\//i,
    /\/admin\//i,
    /\/process\//i,
    /\/api\//i,
    /chat_api=1/i,
    /logout/i,
    /login/i,
    /register/i,
    /verify/i
];

// Safely cache a response clone
async function safeCachePut(cache, request, response) {
    try {
        if (!response || !response.ok || response.type !== "basic") return;
        await cache.put(request, response.clone());
    } catch (err) {
        console.log("[SW] Cache put skipped:", err.message);
    }
}

// ── INSTALL: pre-cache app shell (individual add = resilient) ──
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return Promise.all(
                PRECACHE_ASSETS.map((url) =>
                    cache.add(url).catch((err) => {
                        console.warn("[SW] Precache skipped:", url, err.message);
                    })
                )
            );
        })
    );
    self.skipWaiting();
});

// ── ACTIVATE: clear old caches ──
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key.startsWith("brgy-lipay-") && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// ── MESSAGE: for update handling ──
self.addEventListener("message", (event) => {
    if (event.data && event.data.type === "SKIP_WAITING") {
        self.skipWaiting();
    }
});

// ── FETCH: routing strategy ──
self.addEventListener("fetch", (event) => {
    const { request } = event;

    // Only handle GET — POST/PUT/DELETE bypass SW
    if (request.method !== "GET") return;

    const url = new URL(request.url);

    // Skip cross-origin
    if (url.origin !== self.location.origin) return;

    // ❌ NEVER cache auth/admin/process/api pages
    if (NEVER_CACHE.some((rx) => rx.test(url.pathname + url.search))) {
        event.respondWith(fetch(request));
        return;
    }

    // Navigation requests (PHP pages): NETWORK-FIRST
    if (request.mode === "navigate" || url.pathname.endsWith(".php")) {
        event.respondWith(
            (async () => {
                try {
                    const response = await fetch(request);
                    const cache = await caches.open(STATIC_CACHE);
                    await safeCachePut(cache, request, response);
                    return response;
                } catch (err) {
                    const cached = await caches.match(request);
                    return cached || caches.match(OFFLINE_URL);
                }
            })()
        );
        return;
    }

    // Static assets: CACHE-FIRST
    if (
        request.destination === "image" ||
        request.destination === "style" ||
        request.destination === "script" ||
        request.destination === "font" ||
        url.pathname.includes("/uploads/")
    ) {
        event.respondWith(
            (async () => {
                const cached = await caches.match(request);
                if (cached) {
                    // Serve from cache, refresh in background
                    fetch(request)
                        .then(async (response) => {
                            const cache = await caches.open(STATIC_CACHE);
                            await safeCachePut(cache, request, response);
                        })
                        .catch(() => {});
                    return cached;
                }
                try {
                    const response = await fetch(request);
                    const cache = await caches.open(STATIC_CACHE);
                    await safeCachePut(cache, request, response);
                    return response;
                } catch (err) {
                    // Image fallback: inline SVG
                    if (request.destination === "image") {
                        return new Response(
                            '<svg xmlns="http://www.w3.org/2000/svg" width="192" height="192"><rect width="100%" height="100%" fill="#0d1b3e"/><text x="50%" y="50%" fill="#fff" font-family="sans-serif" font-size="14" text-anchor="middle" dy=".3em">Offline</text></svg>',
                            { headers: { "Content-Type": "image/svg+xml" } }
                        );
                    }
                    return caches.match(OFFLINE_URL);
                }
            })()
        );
        return;
    }

    // Everything else: network with cache fallback
    event.respondWith(
        fetch(request).catch(async () => {
            const cached = await caches.match(request);
            if (cached) return cached;
            return caches.match(OFFLINE_URL) || new Response("Offline", { status: 503 });
        })
    );
});