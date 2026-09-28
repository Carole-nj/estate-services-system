/**
 * Greenridge Estate — Services Desk
 * Backend server. Zero external dependencies — runs with plain Node.js.
 *
 *   node server.js
 *   -> http://localhost:3000
 *
 * Data is persisted to data/db.json (a tiny file-based database).
 * Swap `readDB`/`writeDB` for a real database later without touching
 * the route handlers much — they only work with the `db` object.
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 3000;
const DB_PATH = path.join(__dirname, 'data', 'db.json');
const PUBLIC_DIR = path.join(__dirname, 'public');

const STATUS_VALUES = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];

// ---------------------------------------------------------------------------
// tiny file-based "database"
// ---------------------------------------------------------------------------

function readDB() {
  const raw = fs.readFileSync(DB_PATH, 'utf-8');
  return JSON.parse(raw);
}

function writeDB(db) {
  fs.writeFileSync(DB_PATH, JSON.stringify(db, null, 2), 'utf-8');
}

// serialize writes so two near-simultaneous requests can't clobber each other
let writeQueue = Promise.resolve();
function mutateDB(fn) {
  writeQueue = writeQueue.then(() => {
    const db = readDB();
    const result = fn(db);
    writeDB(db);
    return result;
  });
  return writeQueue;
}

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------

function sendJSON(res, status, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

function sendError(res, status, message) {
  sendJSON(res, status, { error: message });
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let chunks = [];
    let size = 0;
    req.on('data', (chunk) => {
      size += chunk.length;
      if (size > 1_000_000) {
        reject(new Error('Body too large'));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => {
      if (chunks.length === 0) return resolve({});
      try {
        resolve(JSON.parse(Buffer.concat(chunks).toString('utf-8')));
      } catch (err) {
        reject(new Error('Invalid JSON body'));
      }
    });
    req.on('error', reject);
  });
}

function nextRef(db) {
  const ref = 'EST-' + String(db.seq).padStart(4, '0');
  db.seq += 1;
  return ref;
}

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
};

function serveStatic(req, res, pathname) {
  let filePath = pathname === '/' ? '/index.html' : pathname;
  filePath = path.normalize(filePath).replace(/^(\.\.[/\\])+/, '');
  const fullPath = path.join(PUBLIC_DIR, filePath);

  // prevent escaping the public directory
  if (!fullPath.startsWith(PUBLIC_DIR)) {
    return sendError(res, 403, 'Forbidden');
  }

  fs.readFile(fullPath, (err, data) => {
    if (err) {
      return sendError(res, 404, 'Not found');
    }
    const ext = path.extname(fullPath);
    res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
    res.end(data);
  });
}

// ---------------------------------------------------------------------------
// validation
// ---------------------------------------------------------------------------

function validateBookingInput(body, db) {
  const errors = [];
  const svc = db.services.find((s) => s.id === body.serviceId && s.active !== false);
  if (!svc) errors.push('serviceId must reference an active service');
  if (!body.resident || !String(body.resident).trim()) errors.push('resident is required');
  if (!body.unit || !String(body.unit).trim()) errors.push('unit is required');
  if (!body.phone || !String(body.phone).trim()) errors.push('phone is required');
  if (!body.date) errors.push('date is required');
  return { errors, svc };
}

// ---------------------------------------------------------------------------
// route handlers
// ---------------------------------------------------------------------------

const routes = [];
function route(method, pattern, handler) {
  // pattern like /api/bookings/:ref -> regex with named groups
  const paramNames = [];
  const regexStr = pattern.replace(/:[a-zA-Z]+/g, (m) => {
    paramNames.push(m.slice(1));
    return '([^/]+)';
  });
  const regex = new RegExp('^' + regexStr + '$');
  routes.push({ method, regex, paramNames, handler });
}

function matchRoute(method, pathname) {
  for (const r of routes) {
    if (r.method !== method) continue;
    const m = r.regex.exec(pathname);
    if (m) {
      const params = {};
      r.paramNames.forEach((name, i) => (params[name] = decodeURIComponent(m[i + 1])));
      return { handler: r.handler, params };
    }
  }
  return null;
}

// ---- services --------------------------------------------------------------

route('GET', '/api/services', async (req, res) => {
  const db = readDB();
  sendJSON(res, 200, db.services);
});

route('POST', '/api/services', async (req, res) => {
  const body = await readBody(req);
  if (!body.name || typeof body.price !== 'number') {
    return sendError(res, 400, 'name and numeric price are required');
  }
  const svc = {
    id: body.id || body.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 24) + '-' + crypto.randomBytes(2).toString('hex'),
    name: body.name,
    category: body.category || 'General',
    price: body.price,
    duration: body.duration || '',
    active: true,
  };
  await mutateDB((db) => db.services.push(svc));
  sendJSON(res, 201, svc);
});

route('PATCH', '/api/services/:id', async (req, res, params) => {
  const body = await readBody(req);
  const result = await mutateDB((db) => {
    const svc = db.services.find((s) => s.id === params.id);
    if (!svc) return null;
    if (typeof body.name === 'string') svc.name = body.name;
    if (typeof body.category === 'string') svc.category = body.category;
    if (typeof body.price === 'number') svc.price = body.price;
    if (typeof body.duration === 'string') svc.duration = body.duration;
    if (typeof body.active === 'boolean') svc.active = body.active;
    return svc;
  });
  if (!result) return sendError(res, 404, 'Service not found');
  sendJSON(res, 200, result);
});

// ---- staff -------------------------------------------------------------

route('GET', '/api/staff', async (req, res) => {
  const db = readDB();
  sendJSON(res, 200, db.staff);
});

route('POST', '/api/staff', async (req, res) => {
  const body = await readBody(req);
  if (!body.name) return sendError(res, 400, 'name is required');
  const member = {
    id: 'stf-' + crypto.randomBytes(3).toString('hex'),
    name: body.name,
    role: body.role || 'General',
    phone: body.phone || '',
  };
  await mutateDB((db) => db.staff.push(member));
  sendJSON(res, 201, member);
});

route('DELETE', '/api/staff/:id', async (req, res, params) => {
  const result = await mutateDB((db) => {
    const idx = db.staff.findIndex((s) => s.id === params.id);
    if (idx === -1) return false;
    db.staff.splice(idx, 1);
    return true;
  });
  if (!result) return sendError(res, 404, 'Staff member not found');
  sendJSON(res, 200, { ok: true });
});

// ---- bookings ------------------------------------------------------------

route('GET', '/api/bookings', async (req, res, params, query) => {
  const db = readDB();
  let rows = db.bookings;

  if (query.status && query.status !== 'all') {
    rows = rows.filter((b) => b.status === query.status);
  }
  if (query.serviceId && query.serviceId !== 'all') {
    rows = rows.filter((b) => b.serviceId === query.serviceId);
  }
  if (query.q) {
    const q = query.q.toLowerCase();
    rows = rows.filter(
      (b) =>
        b.resident.toLowerCase().includes(q) ||
        b.unit.toLowerCase().includes(q) ||
        b.ref.toLowerCase().includes(q)
    );
  }

  rows = [...rows].sort((a, b) => b.createdAt - a.createdAt);
  sendJSON(res, 200, rows);
});

route('POST', '/api/bookings', async (req, res) => {
  const body = await readBody(req);
  const db = readDB();
  const { errors, svc } = validateBookingInput(body, db);
  if (errors.length) return sendError(res, 400, errors.join('; '));

  const booking = await mutateDB((db) => {
    const svc2 = db.services.find((s) => s.id === body.serviceId);
    const b = {
      ref: nextRef(db),
      serviceId: svc2.id,
      serviceName: svc2.name,
      price: svc2.price,
      resident: String(body.resident).trim(),
      unit: String(body.unit).trim(),
      phone: String(body.phone).trim(),
      date: body.date,
      time: body.time || '',
      notes: (body.notes || '').trim(),
      status: 'Pending',
      staffId: null,
      createdAt: Date.now(),
      updatedAt: Date.now(),
      history: [{ status: 'Pending', at: Date.now() }],
    };
    db.bookings.push(b);
    return b;
  });

  sendJSON(res, 201, booking);
});

route('PATCH', '/api/bookings/:ref', async (req, res, params) => {
  const body = await readBody(req);

  if (body.status && !STATUS_VALUES.includes(body.status)) {
    return sendError(res, 400, `status must be one of: ${STATUS_VALUES.join(', ')}`);
  }

  const result = await mutateDB((db) => {
    const b = db.bookings.find((x) => x.ref === params.ref);
    if (!b) return null;
    if (body.status && body.status !== b.status) {
      b.status = body.status;
      b.history.push({ status: body.status, at: Date.now() });
    }
    if (typeof body.staffId !== 'undefined') b.staffId = body.staffId;
    if (typeof body.notes === 'string') b.notes = body.notes;
    b.updatedAt = Date.now();
    return b;
  });

  if (!result) return sendError(res, 404, 'Booking not found');
  sendJSON(res, 200, result);
});

route('DELETE', '/api/bookings/:ref', async (req, res, params) => {
  const result = await mutateDB((db) => {
    const idx = db.bookings.findIndex((b) => b.ref === params.ref);
    if (idx === -1) return false;
    db.bookings.splice(idx, 1);
    return true;
  });
  if (!result) return sendError(res, 404, 'Booking not found');
  sendJSON(res, 200, { ok: true });
});

// ---- stats -----------------------------------------------------------------

route('GET', '/api/stats', async (req, res) => {
  const db = readDB();
  const bookings = db.bookings;

  const byStatus = {};
  STATUS_VALUES.forEach((s) => (byStatus[s] = 0));
  bookings.forEach((b) => (byStatus[b.status] = (byStatus[b.status] || 0) + 1));

  const byService = {};
  db.services.forEach((s) => (byService[s.id] = { name: s.name, count: 0 }));
  bookings.forEach((b) => {
    if (byService[b.serviceId]) byService[b.serviceId].count += 1;
  });

  const revenue = bookings.filter((b) => b.status !== 'Cancelled').reduce((sum, b) => sum + b.price, 0);
  const open = bookings.filter((b) => b.status !== 'Completed' && b.status !== 'Cancelled').length;

  sendJSON(res, 200, {
    total: bookings.length,
    open,
    completed: byStatus['Completed'],
    cancelled: byStatus['Cancelled'],
    revenue,
    byStatus,
    byService,
  });
});

// ---------------------------------------------------------------------------
// server
// ---------------------------------------------------------------------------

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${req.headers.host}`);
    const pathname = url.pathname;
    const query = Object.fromEntries(url.searchParams.entries());

    if (pathname.startsWith('/api/')) {
      const match = matchRoute(req.method, pathname);
      if (!match) return sendError(res, 404, 'No such endpoint');
      await match.handler(req, res, match.params, query);
      return;
    }

    if (req.method === 'GET') {
      return serveStatic(req, res, pathname);
    }

    sendError(res, 405, 'Method not allowed');
  } catch (err) {
    console.error(err);
    sendError(res, 500, err.message || 'Internal server error');
  }
});

server.listen(PORT, () => {
  console.log(`Greenridge Estate Services Desk running at http://localhost:${PORT}`);
});
