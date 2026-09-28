// SalesDock Service Worker — POS Offline Mode
// Version bump this string whenever CSS/JS changes to force cache refresh
const CACHE    = 'salesdock-v1';
const DB_NAME  = 'salesdock-offline';
const DB_VER   = 1;
const STORE    = 'pending-sales';

// Assets to pre-cache on install
const PRECACHE = [
  '/pos',
  '/css/salesdock.css',
  '/SalesDock.svg',
];

// ── Install: pre-cache shell ──────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

// ── Activate: clear old caches ────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// ── Fetch: serve from cache when offline ─────────────────────────────────
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Only intercept same-origin requests
  if (url.origin !== self.location.origin) return;

  // Intercept POS sale submissions when offline
  if (url.pathname === '/pos' && event.request.method === 'POST') {
    event.respondWith(handleOfflineSale(event.request));
    return;
  }

  // For navigation requests to /pos — serve cached shell if offline
  if (event.request.mode === 'navigate' && url.pathname === '/pos') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // Update cache with fresh response
          const clone = response.clone();
          caches.open(CACHE).then((cache) => cache.put(event.request, clone));
          return response;
        })
        .catch(() => caches.match('/pos'))
    );
    return;
  }

  // Static assets — cache-first
  if (
    url.pathname.startsWith('/css/') ||
    url.pathname.startsWith('/build/') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.webp')
  ) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        if (cached) return cached;
        return fetch(event.request).then((response) => {
          const clone = response.clone();
          caches.open(CACHE).then((cache) => cache.put(event.request, clone));
          return response;
        });
      })
    );
    return;
  }

  // Everything else — network first, no fallback needed
});

// ── Handle offline sale POST ──────────────────────────────────────────────
async function handleOfflineSale(request) {
  try {
    // If we're online, just pass through normally
    const response = await fetch(request.clone());
    return response;
  } catch {
    // We're offline — save the sale to IndexedDB
    const formData = await request.formData();
    const sale = {};
    for (const [key, value] of formData.entries()) {
      if (sale[key] !== undefined) {
        if (!Array.isArray(sale[key])) sale[key] = [sale[key]];
        sale[key].push(value);
      } else {
        sale[key] = value;
      }
    }
    sale._queuedAt = new Date().toISOString();
    sale._id = crypto.randomUUID();

    await saveToQueue(sale);

    // Notify all open clients that a sale was queued
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach((client) => client.postMessage({ type: 'SALE_QUEUED', id: sale._id }));

    // Return a fake JSON success so the POS JS handles it gracefully
    return new Response(JSON.stringify({
      success: true,
      offline: true,
      ref: 'OFFLINE-' + sale._id.slice(-6).toUpperCase(),
      total: '(pending sync)',
      method: sale.method || 'CASH',
      orderUrl: null,
    }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  }
}

// ── IndexedDB helpers ─────────────────────────────────────────────────────
function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VER);
    req.onupgradeneeded = () => {
      req.result.createObjectStore(STORE, { keyPath: '_id' });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function saveToQueue(sale) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).add(sale);
    tx.oncomplete = resolve;
    tx.onerror = () => reject(tx.error);
  });
}

async function getQueue() {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readonly');
    const req = tx.objectStore(STORE).getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function removeFromQueue(id) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite');
    tx.objectStore(STORE).delete(id);
    tx.oncomplete = resolve;
    tx.onerror = () => reject(tx.error);
  });
}

// ── Background sync when back online ─────────────────────────────────────
self.addEventListener('message', async (event) => {
  if (event.data?.type === 'SYNC_NOW') {
    await syncPendingSales(event.source);
  }
  if (event.data?.type === 'GET_QUEUE_COUNT') {
    const queue = await getQueue();
    event.source.postMessage({ type: 'QUEUE_COUNT', count: queue.length });
  }
});

async function syncPendingSales(client) {
  const queue = await getQueue();
  if (queue.length === 0) return;

  let synced = 0;
  let failed = 0;

  for (const sale of queue) {
    try {
      const fd = new FormData();
      for (const [key, value] of Object.entries(sale)) {
        if (key.startsWith('_')) continue; // skip our internal keys
        if (Array.isArray(value)) {
          value.forEach((v) => fd.append(key, v));
        } else {
          fd.append(key, value);
        }
      }
      const response = await fetch('/pos', {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      if (response.ok) {
        await removeFromQueue(sale._id);
        synced++;
      } else {
        failed++;
      }
    } catch {
      failed++;
    }
  }

  const clients = await self.clients.matchAll({ type: 'window' });
  clients.forEach((c) => c.postMessage({ type: 'SYNC_DONE', synced, failed }));
}
