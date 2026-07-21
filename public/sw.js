// public/sw.js — static file, served from the origin root for root scope.
// Do not move into resources/ or let Vite process/hash it.

const CACHE_NAME = 'shell-v1'; // bump this after significant static-asset changes
const OFFLINE_URL = '/offline.html';

// Only stable, hand-known filenames belong here. Vite's hashed /build/ bundle
// filenames change every deploy — listing a stale one would make cache.addAll()
// reject and abort the whole install step (it's all-or-nothing). Build assets are
// cached lazily on first request instead, via the fetch handler below.
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(
                names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Never intercept non-GET — this is what protects Livewire's POST
    // /livewire/update endpoint. Not calling respondWith() = default network handling.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return; // cross-origin: untouched

    // True browser-level navigations only (first load, refresh, typed URL, a plain
    // link without wire:navigate). Network-first; the offline page is the ONLY
    // fallback — we deliberately never serve a stale cached copy of dynamic HTML.
    // NOTE: Livewire's wire:navigate does its own fetch() and is never mode
    // 'navigate', so it intentionally is NOT caught here — see CLAUDE.md §7 Schedule
    // and the PWA plan for why that's a deliberate scope boundary, not an oversight.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Known static, same-origin, safely-cacheable paths only: cache-first with a
    // background stale-while-revalidate refresh, keyed by full URL — Vite's
    // content-hashed /build/ filenames are handled correctly with no hand-maintained
    // list, since a changed hash is simply a new, distinct cache key.
    const isStaticAsset = url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname === '/manifest.json';

    if (!isStaticAsset) return; // everything else (Livewire page fetches, /docs/api, ...): untouched

    event.respondWith(
        caches.match(request).then((cached) => {
            const network = fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                }
                return response;
            }).catch(() => cached);

            return cached || network;
        })
    );
});

// Real Web Push — delivered by the browser's push service even with every
// tab (and the whole browser) closed, since the server decides when to send
// (see App\Services\PushNotifier and the two scheduled commands).
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {};
    }

    event.waitUntil(self.registration.showNotification(data.title || 'nothing-to-do', {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: { url: data.url || '/app' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/app';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((c) => c.url.includes(targetUrl));
            if (existing) return existing.focus();
            return self.clients.openWindow(targetUrl);
        })
    );
});
