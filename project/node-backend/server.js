const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 3000;
const SECRET = process.env.JWT_SECRET || 'financeiq-local-secret-change-me';
const DATA_DIR = path.join(__dirname, 'data');
const USERS_FILE = path.join(DATA_DIR, 'users.json');
const FRONTEND_DIR = path.join(__dirname, '..');

function serveStaticFile(res, filePath, contentType = 'text/html') {
  try {
    const data = fs.readFileSync(filePath);
    res.writeHead(200, { 'Content-Type': contentType });
    res.end(data);
  } catch (error) {
    jsonResponse(res, 404, { success: false, message: 'File not found.' });
  }
}


function ensureStorage() {
  if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });
  if (!fs.existsSync(USERS_FILE)) fs.writeFileSync(USERS_FILE, '[]', 'utf8');
}

function readUsers() {
  ensureStorage();
  try {
    return JSON.parse(fs.readFileSync(USERS_FILE, 'utf8'));
  } catch (error) {
    return [];
  }
}

function saveUsers(users) {
  ensureStorage();
  fs.writeFileSync(USERS_FILE, JSON.stringify(users, null, 2), 'utf8');
}

function jsonResponse(res, statusCode, payload) {
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type,Authorization'
  });
  res.end(JSON.stringify(payload));
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', chunk => {
      body += chunk.toString();
      if (body.length > 1_000_000) {
        req.destroy();
        reject(new Error('Request body is too large'));
      }
    });
    req.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch (error) {
        reject(new Error('Invalid JSON body'));
      }
    });
  });
}

function hashPassword(password, salt = crypto.randomBytes(16).toString('hex')) {
  const hash = crypto.pbkdf2Sync(password, salt, 100000, 64, 'sha512').toString('hex');
  return `${salt}:${hash}`;
}

function verifyPassword(password, savedPassword) {
  const [salt, hash] = savedPassword.split(':');
  const current = hashPassword(password, salt).split(':')[1];
  return crypto.timingSafeEqual(Buffer.from(hash), Buffer.from(current));
}

function signToken(user) {
  const header = Buffer.from(JSON.stringify({ alg: 'HS256', typ: 'JWT' })).toString('base64url');
  const payload = Buffer.from(JSON.stringify({ id: user.id, email: user.email, exp: Date.now() + 7 * 24 * 60 * 60 * 1000 })).toString('base64url');
  const signature = crypto.createHmac('sha256', SECRET).update(`${header}.${payload}`).digest('base64url');
  return `${header}.${payload}.${signature}`;
}

function verifyToken(token) {
  if (!token) return null;
  const parts = token.split('.');
  if (parts.length !== 3) return null;
  const [header, payload, signature] = parts;
  const expected = crypto.createHmac('sha256', SECRET).update(`${header}.${payload}`).digest('base64url');
  if (signature !== expected) return null;
  const data = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
  if (data.exp < Date.now()) return null;
  return data;
}

function publicUser(user) {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    createdAt: user.createdAt
  };
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function defaultAppData() {
  return {
    transactions: [],
    budgets: {},
    settings: { currency: '$', darkMode: false, budgetAlert: true, unusualAlert: true, weeklyAlert: false },
    notifications: []
  };
}

function getAuthUser(req, res) {
  const auth = req.headers.authorization || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : '';
  const session = verifyToken(token);
  if (!session) {
    jsonResponse(res, 401, { success: false, message: 'Unauthorized. Please login first.' });
    return null;
  }

  const users = readUsers();
  const index = users.findIndex(item => item.id === session.id);
  if (index === -1) {
    jsonResponse(res, 404, { success: false, message: 'User not found.' });
    return null;
  }

  users[index].data = { ...defaultAppData(), ...(users[index].data || {}) };
  return { users, index, user: users[index] };
}

function cleanTransaction(txn) {
  return {
    id: String(txn.id || crypto.randomUUID()),
    type: txn.type === 'income' ? 'income' : 'expense',
    desc: String(txn.desc || '').trim(),
    amount: Number(txn.amount || 0),
    cat: String(txn.cat || 'other'),
    date: String(txn.date || new Date().toISOString().slice(0, 10)),
    notes: String(txn.notes || '').trim(),
    createdAt: txn.createdAt || new Date().toISOString()
  };
}

function cleanData(body) {
  const current = defaultAppData();
  return {
    transactions: Array.isArray(body.transactions) ? body.transactions.map(cleanTransaction).filter(t => t.desc && t.amount > 0) : current.transactions,
    budgets: body.budgets && typeof body.budgets === 'object' && !Array.isArray(body.budgets) ? body.budgets : current.budgets,
    settings: { ...current.settings, ...(body.settings || {}) },
    notifications: Array.isArray(body.notifications) ? body.notifications : current.notifications
  };
}

function handleGetData(req, res) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  return jsonResponse(res, 200, { success: true, data: auth.user.data });
}

async function handleSaveData(req, res) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  const body = await readBody(req);
  auth.users[auth.index].data = cleanData(body);
  saveUsers(auth.users);
  return jsonResponse(res, 200, { success: true, message: 'Data saved successfully.', data: auth.users[auth.index].data });
}

async function handleUpdateProfile(req, res) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  const body = await readBody(req);
  const name = String(body.name || '').trim();
  if (!name) return jsonResponse(res, 400, { success: false, message: 'Name is required.' });
  auth.users[auth.index].name = name;
  saveUsers(auth.users);
  return jsonResponse(res, 200, { success: true, user: publicUser(auth.users[auth.index]) });
}

async function handleCreateTransaction(req, res) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  const body = await readBody(req);
  const txn = cleanTransaction({ ...body, id: body.id || crypto.randomUUID() });
  if (!txn.desc || txn.amount <= 0) return jsonResponse(res, 400, { success: false, message: 'Invalid transaction data.' });
  auth.users[auth.index].data.transactions.push(txn);
  saveUsers(auth.users);
  return jsonResponse(res, 201, { success: true, transaction: txn });
}

async function handleUpdateTransaction(req, res, id) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  const body = await readBody(req);
  const list = auth.users[auth.index].data.transactions;
  const idx = list.findIndex(t => t.id === id);
  if (idx === -1) return jsonResponse(res, 404, { success: false, message: 'Transaction not found.' });
  list[idx] = cleanTransaction({ ...list[idx], ...body, id });
  saveUsers(auth.users);
  return jsonResponse(res, 200, { success: true, transaction: list[idx] });
}

function handleDeleteTransaction(req, res, id) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  const before = auth.users[auth.index].data.transactions.length;
  auth.users[auth.index].data.transactions = auth.users[auth.index].data.transactions.filter(t => t.id !== id);
  if (auth.users[auth.index].data.transactions.length === before) {
    return jsonResponse(res, 404, { success: false, message: 'Transaction not found.' });
  }
  saveUsers(auth.users);
  return jsonResponse(res, 200, { success: true, message: 'Transaction deleted.' });
}

async function handleSaveBudget(req, res) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  const body = await readBody(req);
  const category = String(body.category || body.cat || '').trim();
  const amount = Number(body.amount || 0);
  if (!category || amount <= 0) return jsonResponse(res, 400, { success: false, message: 'Invalid budget data.' });
  auth.users[auth.index].data.budgets[category] = amount;
  saveUsers(auth.users);
  return jsonResponse(res, 200, { success: true, budgets: auth.users[auth.index].data.budgets });
}

function handleClearAllData(req, res) {
  const auth = getAuthUser(req, res);
  if (!auth) return;
  auth.users[auth.index].data = defaultAppData();
  saveUsers(auth.users);
  return jsonResponse(res, 200, { success: true, message: 'All data cleared.', data: auth.users[auth.index].data });
}


async function handleRegister(req, res) {
  const body = await readBody(req);
  const name = String(body.name || '').trim();
  const email = String(body.email || '').trim().toLowerCase();
  const password = String(body.password || '');

  if (!name || !email || !password) {
    return jsonResponse(res, 400, { success: false, message: 'Please fill in all fields.' });
  }
  if (!isValidEmail(email)) {
    return jsonResponse(res, 400, { success: false, message: 'Invalid email address.' });
  }
  if (password.length < 6) {
    return jsonResponse(res, 400, { success: false, message: 'Password must be at least 6 characters.' });
  }

  const users = readUsers();
  if (users.some(user => user.email === email)) {
    return jsonResponse(res, 409, { success: false, message: 'Email already registered.' });
  }

  const user = {
    id: crypto.randomUUID(),
    name,
    email,
    password: hashPassword(password),
    createdAt: new Date().toISOString()
  };

  users.push(user);
  saveUsers(users);

  return jsonResponse(res, 201, { success: true, message: 'Registered successfully. Please login.', user: publicUser(user) });
}

async function handleLogin(req, res) {
  const body = await readBody(req);
  const email = String(body.email || '').trim().toLowerCase();
  const password = String(body.password || '');

  if (!email || !password) {
    return jsonResponse(res, 400, { success: false, message: 'Please fill in all fields.' });
  }

  const users = readUsers();
  const user = users.find(item => item.email === email);
  if (!user || !verifyPassword(password, user.password)) {
    return jsonResponse(res, 401, { success: false, message: 'Invalid email or password.' });
  }

  return jsonResponse(res, 200, { success: true, token: signToken(user), user: publicUser(user) });
}

function handleMe(req, res) {
  const auth = req.headers.authorization || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : '';
  const session = verifyToken(token);
  if (!session) return jsonResponse(res, 401, { success: false, message: 'Unauthorized.' });

  const user = readUsers().find(item => item.id === session.id);
  if (!user) return jsonResponse(res, 404, { success: false, message: 'User not found.' });

  return jsonResponse(res, 200, { success: true, user: publicUser(user) });
}

const server = http.createServer(async (req, res) => {
  try {
    if (req.method === 'OPTIONS') return jsonResponse(res, 200, { success: true });

    const url = new URL(req.url, `http://${req.headers.host}`);

    if (req.method === 'GET' && (url.pathname === '/' || url.pathname === '/index.html')) {
      return serveStaticFile(res, path.join(FRONTEND_DIR, 'test.html'));
    }

    if (req.method === 'GET' && url.pathname === '/script.js') {
      return serveStaticFile(res, path.join(FRONTEND_DIR, 'script.js'), 'application/javascript');
    }

    if (req.method === 'GET' && url.pathname === '/style.css') {
      return serveStaticFile(res, path.join(FRONTEND_DIR, 'style.css'), 'text/css');
    }
    if (req.method === 'POST' && url.pathname === '/api/auth/register') return handleRegister(req, res);
    if (req.method === 'POST' && url.pathname === '/api/auth/login') return handleLogin(req, res);
    if (req.method === 'GET' && url.pathname === '/api/me') return handleMe(req, res);
    if (req.method === 'GET' && url.pathname === '/api/data') return handleGetData(req, res);
    if (req.method === 'PUT' && url.pathname === '/api/data') return handleSaveData(req, res);
    if (req.method === 'DELETE' && url.pathname === '/api/data') return handleClearAllData(req, res);
    if (req.method === 'PUT' && url.pathname === '/api/profile') return handleUpdateProfile(req, res);
    if (req.method === 'POST' && url.pathname === '/api/transactions') return handleCreateTransaction(req, res);
    if (url.pathname.startsWith('/api/transactions/')) {
      const id = decodeURIComponent(url.pathname.split('/').pop());
      if (req.method === 'PUT') return handleUpdateTransaction(req, res, id);
      if (req.method === 'DELETE') return handleDeleteTransaction(req, res, id);
    }
    if (req.method === 'PUT' && url.pathname === '/api/budgets') return handleSaveBudget(req, res);

    return jsonResponse(res, 404, { success: false, message: 'Route not found.' });
  } catch (error) {
    return jsonResponse(res, 500, { success: false, message: error.message || 'Server error' });
  }
});

ensureStorage();
server.listen(PORT, () => {
  console.log(`FinanceIQ Node.js backend running on http://localhost:${PORT}`);
});
