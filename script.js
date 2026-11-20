/* ============================================================
   CONSTANTS & CONFIG
============================================================ */
const CATEGORIES = {
  expense: [
    { id: 'food',          label: 'Food & Dining',    icon: '🍔', color: '#ef4444' },
    { id: 'transport',     label: 'Transport',         icon: '🚗', color: '#3b82f6' },
    { id: 'bills',         label: 'Bills & Utilities', icon: '💡', color: '#f59e0b' },
    { id: 'shopping',      label: 'Shopping',          icon: '🛍️', color: '#a855f7' },
    { id: 'health',        label: 'Health',            icon: '🏥', color: '#10b981' },
    { id: 'entertainment', label: 'Entertainment',     icon: '🎬', color: '#ec4899' },
    { id: 'education',     label: 'Education',         icon: '📚', color: '#6366f1' },
    { id: 'other',         label: 'Other',             icon: '📦', color: '#6b7280' },
  ],
  income: [
    { id: 'salary',     label: 'Salary',      icon: '💼', color: '#10b981' },
    { id: 'freelance',  label: 'Freelance',   icon: '💻', color: '#3b82f6' },
    { id: 'investment', label: 'Investment',  icon: '📈', color: '#f59e0b' },
    { id: 'other',      label: 'Other',       icon: '💵', color: '#6b7280' },
  ]
};

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const FULL_MONTHS = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

/* ============================================================
   NODE.JS API HELPERS
   Start the Node backend from node-backend/ to save users there.
   If it is not running, the app falls back to localStorage so it still works.
============================================================ */
const API_BASE_URL = localStorage.getItem('fiq_api_base') || `${window.location.origin}/api`; // connected frontend with backend API

async function apiRequest(path, options = {}) {
  const token = localStorage.getItem('fiq_token');
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {})
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers
  });

  let data = {};
  try { data = await response.json(); } catch (e) { data = {}; }
  if (!response.ok || data.success === false) {
    throw new Error(data.message || 'Server error');
  }
  return data;
}


/* ============================================================
   STATE
============================================================ */
let state = {
  user: null,
  transactions: [],
  budgets: {},
  settings: { currency: '$', darkMode: false, budgetAlert: true, unusualAlert: true, weeklyAlert: false },
  notifications: [],
  editId: null,
  currentType: 'income',
  charts: {}
};

/* ============================================================
   STORAGE HELPERS
============================================================ */
function localStatePayload() {
  return {
    transactions: state.transactions,
    budgets: state.budgets,
    settings: state.settings,
    notifications: state.notifications
  };
}

function saveState() {
  if (!state.user) return;
  const key = `fiq_${state.user.email}`;
  localStorage.setItem(key, JSON.stringify(localStatePayload()));
  syncStateToBackend();
}

async function syncStateToBackend() {
  if (!state.user || !localStorage.getItem('fiq_token')) return;
  try {
    await apiRequest('/data', { method: 'PUT', body: JSON.stringify(localStatePayload()) });
  } catch (error) {
    console.warn('Backend data sync failed. Local data is still saved:', error.message);
  }
}

async function loadStateFromBackend() {
  if (!localStorage.getItem('fiq_token')) return false;
  try {
    const data = await apiRequest('/data', { method: 'GET' });
    const saved = data.data || {};
    const hasSavedData = (saved.transactions && saved.transactions.length) || Object.keys(saved.budgets || {}).length;
    if (!hasSavedData) return false;
    state.transactions = saved.transactions || [];
    state.budgets = saved.budgets || {};
    state.settings = { ...state.settings, ...(saved.settings || {}) };
    state.notifications = saved.notifications || [];
    const key = `fiq_${state.user.email}`;
    localStorage.setItem(key, JSON.stringify(localStatePayload()));
    return true;
  } catch (error) {
    console.warn('Backend data load failed. Loading local data:', error.message);
    return false;
  }
}

function loadState() {
  if (!state.user) return;
  const key = `fiq_${state.user.email}`;
  const data = localStorage.getItem(key);
  if (data) {
    const parsed = JSON.parse(data);
    state.transactions = parsed.transactions || [];
    state.budgets = parsed.budgets || {};
    state.settings = { ...state.settings, ...(parsed.settings || {}) };
    state.notifications = parsed.notifications || [];
  } else {
    // Seed demo data for new users
    seedDemoData();
  }
}

/* ============================================================
   DEMO DATA SEED
============================================================ */
function seedDemoData() {
  const now = new Date();
  const y = now.getFullYear();
  const m = now.getMonth();

  const demo = [
    { type:'income',  cat:'salary',        desc:'Monthly Salary',        amount:5000, date:`${y}-${pad(m+1)}-01` },
    { type:'income',  cat:'freelance',     desc:'Web Design Project',    amount:1200, date:`${y}-${pad(m+1)}-05` },
    { type:'expense', cat:'bills',         desc:'Rent',                  amount:1500, date:`${y}-${pad(m+1)}-02` },
    { type:'expense', cat:'food',          desc:'Groceries',             amount:320,  date:`${y}-${pad(m+1)}-03` },
    { type:'expense', cat:'transport',     desc:'Monthly Transit Pass',  amount:120,  date:`${y}-${pad(m+1)}-04` },
    { type:'expense', cat:'entertainment', desc:'Netflix & Spotify',     amount:28,   date:`${y}-${pad(m+1)}-06` },
    { type:'expense', cat:'food',          desc:'Restaurant Dinner',     amount:85,   date:`${y}-${pad(m+1)}-08` },
    { type:'expense', cat:'shopping',      desc:'Clothing',              amount:210,  date:`${y}-${pad(m+1)}-10` },
    { type:'expense', cat:'health',        desc:'Gym Membership',        amount:60,   date:`${y}-${pad(m+1)}-12` },
    { type:'income',  cat:'investment',    desc:'Dividend Income',       amount:340,  date:`${y}-${pad(m+1)}-15` },
    { type:'expense', cat:'education',     desc:'Online Course',         amount:99,   date:`${y}-${pad(m+1)}-16` },
    { type:'expense', cat:'food',          desc:'Coffee Shop',           amount:45,   date:`${y}-${pad(m+1)}-18` },
    // Previous month
    { type:'income',  cat:'salary',        desc:'Monthly Salary',        amount:5000, date:`${y}-${pad(m)}-01` },
    { type:'expense', cat:'bills',         desc:'Rent',                  amount:1500, date:`${y}-${pad(m)}-02` },
    { type:'expense', cat:'food',          desc:'Groceries',             amount:290,  date:`${y}-${pad(m)}-05` },
    { type:'expense', cat:'shopping',      desc:'Electronics',           amount:450,  date:`${y}-${pad(m)}-12` },
    { type:'expense', cat:'transport',     desc:'Fuel',                  amount:95,   date:`${y}-${pad(m)}-15` },
  ].filter(t => {
    const [ty, tm] = t.date.split('-').map(Number);
    return tm > 0;
  });

  state.transactions = demo.map((t, i) => ({
    id: `txn_${Date.now()}_${i}`,
    ...t,
    notes: '',
    createdAt: new Date().toISOString()
  }));

  state.budgets = {
    food: 400, transport: 150, bills: 1600,
    shopping: 300, health: 100, entertainment: 80,
    education: 150, other: 100
  };

  saveState();
}

function pad(n) { return String(n).padStart(2,'0'); }

/* ============================================================
   AUTH
============================================================ */
function switchAuthTab(tab) {
  document.querySelectorAll('.auth-tab').forEach((t,i) => {
    t.classList.toggle('active', (i===0 && tab==='login') || (i===1 && tab==='signup'));
  });
  document.getElementById('login-form').classList.toggle('active', tab==='login');
  document.getElementById('signup-form').classList.toggle('active', tab==='signup');

  const heading = document.getElementById('auth-heading');
  const subtitle = document.getElementById('auth-subtitle');
  if (heading && subtitle) {
    heading.textContent = tab === 'login' ? 'Welcome back' : 'Create your account';
    subtitle.textContent = tab === 'login'
      ? 'Sign in to continue to your finance dashboard.'
      : 'Register now and start tracking your money.';
  }

  ['login-error','signup-error'].forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'none'; el.classList.remove('success'); }
  });
}

async function handleLogin() {
  const email = document.getElementById('login-email').value.trim().toLowerCase();
  const pass  = document.getElementById('login-pass').value;
  const errEl = document.getElementById('login-error');
  errEl.style.display = 'none';

  if (!email || !pass) { showAuthError('login-error', 'Please fill in all fields.'); return; }
  if (pass.length < 6) { showAuthError('login-error', 'Password must be at least 6 characters.'); return; }

  try {
    const data = await apiRequest('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password: pass })
    });
    localStorage.setItem('fiq_token', data.token);
    loginUser(data.user);
    return;
  } catch (apiError) {
    console.warn('Node backend login failed. Falling back to localStorage:', apiError.message);
  }

  const users = JSON.parse(localStorage.getItem('fiq_users') || '[]');
  const user = users.find(u => u.email === email);
  if (!user) { showAuthError('login-error', 'No account found. Please sign up.'); return; }
  if (user.password !== btoa(pass)) { showAuthError('login-error', 'Invalid password.'); return; }

  loginUser(user);
}

async function handleSignup() {
  const name  = document.getElementById('signup-name').value.trim();
  const email = document.getElementById('signup-email').value.trim().toLowerCase();
  const pass  = document.getElementById('signup-pass').value;

  if (!name || !email || !pass) { showAuthError('signup-error', 'Please fill in all fields.'); return; }
  if (pass.length < 6) { showAuthError('signup-error', 'Password must be at least 6 characters.'); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showAuthError('signup-error', 'Invalid email address.'); return; }

  try {
    await apiRequest('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ name, email, password: pass })
    });

    document.getElementById('signup-name').value = '';
    document.getElementById('signup-email').value = '';
    document.getElementById('signup-pass').value = '';
    document.getElementById('login-email').value = email;
    document.getElementById('login-pass').value = '';
    switchAuthTab('login');
    showAuthError('login-error', 'Registered successfully! Please login.');
    return;
  } catch (apiError) {
    console.warn('Node backend register failed. Falling back to localStorage:', apiError.message);
  }

  const users = JSON.parse(localStorage.getItem('fiq_users') || '[]');
  if (users.find(u => u.email === email)) { showAuthError('signup-error', 'Email already registered.'); return; }

  const newUser = { name, email, password: btoa(pass), createdAt: new Date().toISOString() };
  users.push(newUser);
  localStorage.setItem('fiq_users', JSON.stringify(users));

  document.getElementById('signup-name').value = '';
  document.getElementById('signup-email').value = '';
  document.getElementById('signup-pass').value = '';
  document.getElementById('login-email').value = email;
  document.getElementById('login-pass').value = '';
  switchAuthTab('login');
  showAuthError('login-error', 'Registered successfully! Please login.');
}

function showAuthError(id, msg) {
  const el = document.getElementById(id);
  el.textContent = msg;
  el.classList.toggle('success', /success|registered/i.test(msg));
  el.style.display = 'block';
}

async function loginUser(user) {
  state.user = user;
  localStorage.setItem('fiq_current_user', JSON.stringify(user));
  const loadedFromBackend = await loadStateFromBackend();
  if (!loadedFromBackend) {
    loadState();
    syncStateToBackend();
  }
  initApp();
}

function logout() {
  if (!confirm('Are you sure you want to logout?')) return;
  state.user = null;
  state.transactions = [];
  state.budgets = {};
  state.notifications = [];
  localStorage.removeItem('fiq_current_user');
  localStorage.removeItem('fiq_token');
  destroyCharts();
  document.getElementById('app').style.display = 'none';
  document.getElementById('auth-screen').style.display = 'flex';
  // Clear form fields
  ['login-email','login-pass','signup-name','signup-email','signup-pass'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  switchAuthTab('login');
}

/* ============================================================
   APP INIT
============================================================ */
function initApp() {
  document.getElementById('auth-screen').style.display = 'none';
  document.getElementById('app').style.display = 'block';

  // Set user info
  document.getElementById('user-name-display').textContent = state.user.name;
  document.getElementById('user-email-display').textContent = state.user.email;
  document.getElementById('user-avatar').textContent = state.user.name.charAt(0).toUpperCase();

  // Apply settings
  applyTheme(state.settings.darkMode);
  document.getElementById('settings-name').value = state.user.name;
  document.getElementById('settings-currency').value = state.settings.currency || '$';

  // Set report month/year to current
  const now = new Date();
  document.getElementById('report-month').value = now.getMonth();
  document.getElementById('report-year').value = now.getFullYear();

  // Populate filter dropdowns
  populateFilterDropdowns();

  // Navigate to dashboard
  navigate('dashboard');

  // Check notifications
  checkBudgetAlerts();
}

/* ============================================================
   NAVIGATION
============================================================ */
function navigate(page) {
  // Update nav
  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.remove('active');
    if (item.getAttribute('onclick') === `navigate('${page}')`) {
      item.classList.add('active');
    }
  });

  // Update pages
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const pageEl = document.getElementById(`page-${page}`);
  if (pageEl) pageEl.classList.add('active');

  // Update title
  const titles = {
    dashboard: 'Dashboard', transactions: 'Transactions',
    budget: 'Budget Planner', reports: 'Reports',
    insights: 'AI Insights', settings: 'Settings'
  };
  document.getElementById('page-title').textContent = titles[page] || page;

  // Close sidebar on mobile
  closeSidebar();

  // Page-specific init
  switch(page) {
    case 'dashboard':    renderDashboard(); break;
    case 'transactions': renderTransactions(); break;
    case 'budget':       renderBudget(); break;
    case 'reports':      generateReport(); break;
    case 'insights':     generateInsights(); break;
    case 'settings':     /* already populated */ break;
  }
}

/* ============================================================
   SIDEBAR TOGGLE
============================================================ */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('open');
}

/* ============================================================
   THEME
============================================================ */
function toggleTheme() {
  state.settings.darkMode = !state.settings.darkMode;
  applyTheme(state.settings.darkMode);
  saveState();
  const toggle = document.getElementById('toggle-dark');
  if (toggle) toggle.classList.toggle('on', state.settings.darkMode);
}
function toggleThemeFromSettings() {
  toggleTheme();
}
function applyTheme(dark) {
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  document.getElementById('theme-btn').textContent = dark ? '☀️' : '🌙';
  const toggle = document.getElementById('toggle-dark');
  if (toggle) toggle.classList.toggle('on', dark);
  // Re-render charts with new theme
  if (state.user) {
    setTimeout(() => {
      destroyCharts();
      renderCharts();
    }, 50);
  }
}

/* ============================================================
   CURRENCY FORMAT
============================================================ */
function fmt(amount) {
  const sym = state.settings.currency || '$';
  return `${sym}${Math.abs(amount).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
}

/* ============================================================
   CATEGORY HELPERS
============================================================ */
function getCat(type, id) {
  const all = [...CATEGORIES.expense, ...CATEGORIES.income];
  return all.find(c => c.id === id) || { label: id, icon: '📦', color: '#6b7280' };
}

function getCatClass(id) {
  const map = {
    food:'cat-food', transport:'cat-transport', bills:'cat-bills',
    shopping:'cat-shopping', health:'cat-health', entertainment:'cat-entertainment',
    education:'cat-education', salary:'cat-salary', freelance:'cat-freelance',
    investment:'cat-investment', other:'cat-other'
  };
  return map[id] || 'cat-other';
}

/* ============================================================
   DASHBOARD
============================================================ */
function renderDashboard() {
  const now = new Date();
  const thisMonth = state.transactions.filter(t => {
    const d = new Date(t.date);
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  });
  const lastMonth = state.transactions.filter(t => {
    const d = new Date(t.date);
    const lm = new Date(now.getFullYear(), now.getMonth()-1, 1);
    return d.getMonth() === lm.getMonth() && d.getFullYear() === lm.getFullYear();
  });

  const income  = thisMonth.filter(t=>t.type==='income').reduce((s,t)=>s+t.amount,0);
  const expense = thisMonth.filter(t=>t.type==='expense').reduce((s,t)=>s+t.amount,0);
  const balance = income - expense;
  const savings = income > 0 ? ((balance/income)*100).toFixed(1) : 0;

  const lIncome  = lastMonth.filter(t=>t.type==='income').reduce((s,t)=>s+t.amount,0);
  const lExpense = lastMonth.filter(t=>t.type==='expense').reduce((s,t)=>s+t.amount,0);

  document.getElementById('dash-balance').textContent = fmt(balance);
  document.getElementById('dash-balance').className = `card-amount ${balance>=0?'positive':'negative'}`;
  document.getElementById('dash-income').textContent  = fmt(income);
  document.getElementById('dash-expense').textContent = fmt(expense);
  document.getElementById('dash-savings').textContent = `${savings}%`;

  // Changes
  setChange('dash-income-change',  income,  lIncome,  'vs last month');
  setChange('dash-expense-change', expense, lExpense, 'vs last month');

  // Recent transactions (last 5)
  const recent = [...state.transactions]
    .sort((a,b) => new Date(b.date) - new Date(a.date))
    .slice(0,5);
  const listEl = document.getElementById('recent-txn-list');
  listEl.innerHTML = recent.length ? recent.map(t => txnHTML(t)).join('') : emptyState('No transactions yet');

  // Badge
  const badge = document.getElementById('txn-badge');
  badge.textContent = state.transactions.length;
  badge.style.display = state.transactions.length ? 'inline-block' : 'none';

  renderCharts();
}

function setChange(id, current, previous, label) {
  const el = document.getElementById(id);
  if (!el) return;
  if (previous === 0) { el.innerHTML = `<span>${label}</span>`; return; }
  const pct = (((current - previous) / previous) * 100).toFixed(1);
  const up = current >= previous;
  el.innerHTML = `<span class="${up?'up':'down'}">${up?'↑':'↓'} ${Math.abs(pct)}%</span> ${label}`;
}

/* ============================================================
   CHARTS
============================================================ */
function destroyCharts() {
  Object.values(state.charts).forEach(c => { try { c.destroy(); } catch(e){} });
  state.charts = {};
}

function getChartDefaults() {
  const dark = state.settings.darkMode;
  return {
    color: dark ? '#9ca3af' : '#6b7280',
    gridColor: dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
    bg: dark ? '#1e2336' : '#ffffff'
  };
}

function renderCharts() {
  renderBarChart();
  renderPieChart();
  renderLineChart();
}

function renderBarChart() {
  const ctx = document.getElementById('barChart');
  if (!ctx) return;
  if (state.charts.bar) { state.charts.bar.destroy(); }

  const now = new Date();
  const labels = [], incomeData = [], expenseData = [];

  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    labels.push(MONTHS[d.getMonth()] + ' ' + d.getFullYear().toString().slice(2));
    const monthTxns = state.transactions.filter(t => {
      const td = new Date(t.date);
      return td.getMonth() === d.getMonth() && td.getFullYear() === d.getFullYear();
    });
    incomeData.push(monthTxns.filter(t=>t.type==='income').reduce((s,t)=>s+t.amount,0));
    expenseData.push(monthTxns.filter(t=>t.type==='expense').reduce((s,t)=>s+t.amount,0));
  }

  const { color, gridColor } = getChartDefaults();
  state.charts.bar = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Income',   data: incomeData,  backgroundColor: 'rgba(16,185,129,0.8)',  borderRadius: 6 },
        { label: 'Expenses', data: expenseData, backgroundColor: 'rgba(239,68,68,0.8)',   borderRadius: 6 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { labels: { color, font:{size:12} } } },
      scales: {
        x: { ticks:{color}, grid:{color:gridColor} },
        y: { ticks:{color, callback: v => fmt(v)}, grid:{color:gridColor} }
      }
    }
  });
}

function renderPieChart() {
  const ctx = document.getElementById('pieChart');
  if (!ctx) return;
  if (state.charts.pie) { state.charts.pie.destroy(); }

  const now = new Date();
  const thisMonth = state.transactions.filter(t => {
    const d = new Date(t.date);
    return t.type === 'expense' && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  });

  const catMap = {};
  thisMonth.forEach(t => { catMap[t.cat] = (catMap[t.cat] || 0) + t.amount; });

  const labels = [], data = [], colors = [];
  Object.entries(catMap).forEach(([id, amt]) => {
    const cat = getCat('expense', id);
    labels.push(cat.label);
    data.push(amt);
    colors.push(cat.color);
  });

  const { color } = getChartDefaults();
  state.charts.pie = new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: state.settings.darkMode ? '#1e2336' : '#fff' }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position:'right', labels:{ color, font:{size:12}, padding:12 } },
        tooltip: { callbacks: { label: ctx => ` ${fmt(ctx.parsed)} (${((ctx.parsed/ctx.dataset.data.reduce((a,b)=>a+b,0))*100).toFixed(1)}%)` } }
      }
    }
  });
}

function renderLineChart() {
  const ctx = document.getElementById('lineChart');
  if (!ctx) return;
  if (state.charts.line) { state.charts.line.destroy(); }

  // Daily balance for last 30 days
  const labels = [], balanceData = [];
  let runningBalance = 0;

  // Get all transactions sorted by date
  const sorted = [...state.transactions].sort((a,b) => new Date(a.date)-new Date(b.date));
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth()-1, now.getDate());

  for (let i = 30; i >= 0; i--) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    const dateStr = d.toISOString().split('T')[0];
    labels.push(i === 0 ? 'Today' : `${d.getDate()} ${MONTHS[d.getMonth()]}`);

    const dayTxns = sorted.filter(t => t.date === dateStr);
    dayTxns.forEach(t => {
      runningBalance += t.type === 'income' ? t.amount : -t.amount;
    });
    balanceData.push(runningBalance);
  }

  const { color, gridColor } = getChartDefaults();
  state.charts.line = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Balance',
        data: balanceData,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99,102,241,0.10)',
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: '#6366f1'
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { labels: { color } } },
      scales: {
        x: { ticks: { color, maxTicksLimit: 8 }, grid: { color: gridColor } },
        y: { ticks: { color, callback: v => fmt(v) }, grid: { color: gridColor } }
      }
    }
  });
}

/* ============================================================
   TRANSACTION HTML
============================================================ */
function txnHTML(t, showActions = true) {
  const cat = getCat(t.type, t.cat);
  const catCls = getCatClass(t.cat);
  const d = new Date(t.date);
  const dateStr = `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
  const sign = t.type === 'income' ? '+' : '-';
  return `
    <div class="txn-item" onclick="${showActions ? `editTransaction('${t.id}')` : ''}">
      <div class="txn-cat-icon ${catCls}">${cat.icon}</div>
      <div class="txn-info">
        <div class="txn-name">${escHtml(t.desc)}</div>
        <div class="txn-meta">${cat.label} · ${dateStr}${t.notes ? ' · ' + escHtml(t.notes) : ''}</div>
      </div>
      <div class="txn-amount ${t.type}">${sign}${fmt(t.amount)}</div>
      ${showActions ? `
        <div class="txn-actions" onclick="event.stopPropagation()">
          <button class="action-btn edit" onclick="editTransaction('${t.id}')" title="Edit">✏️</button>
          <button class="action-btn del" onclick="deleteTransaction('${t.id}')" title="Delete">🗑️</button>
        </div>
      ` : ''}
    </div>
  `;
}

function emptyState(msg) {
  return `<div class="empty-state"><div class="empty-icon">📭</div><p>${msg}</p></div>`;
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ============================================================
   TRANSACTIONS PAGE
============================================================ */
function populateFilterDropdowns() {
  const catSel = document.getElementById('filter-cat');
  const allCats = [...CATEGORIES.expense, ...CATEGORIES.income];
  const unique = [...new Map(allCats.map(c => [c.id, c])).values()];
  catSel.innerHTML = '<option value="">All Categories</option>' +
    unique.map(c => `<option value="${c.id}">${c.icon} ${c.label}</option>`).join('');

  const monthSel = document.getElementById('filter-month');
  const months = new Set(state.transactions.map(t => t.date.slice(0,7)));
  monthSel.innerHTML = '<option value="">All Months</option>' +
    [...months].sort().reverse().map(m => {
      const [y,mo] = m.split('-');
      return `<option value="${m}">${FULL_MONTHS[parseInt(mo)-1]} ${y}</option>`;
    }).join('');
}

function renderTransactions() {
  const search = (document.getElementById('search-input')?.value || '').toLowerCase();
  const type   = document.getElementById('filter-type')?.value || '';
  const cat    = document.getElementById('filter-cat')?.value || '';
  const month  = document.getElementById('filter-month')?.value || '';

  let txns = [...state.transactions].sort((a,b) => new Date(b.date)-new Date(a.date));

  if (search) txns = txns.filter(t => t.desc.toLowerCase().includes(search) || t.cat.toLowerCase().includes(search) || (t.notes||'').toLowerCase().includes(search));
  if (type)  txns = txns.filter(t => t.type === type);
  if (cat)   txns = txns.filter(t => t.cat === cat);
  if (month) txns = txns.filter(t => t.date.startsWith(month));

  const listEl = document.getElementById('all-txn-list');
  if (listEl) listEl.innerHTML = txns.length ? txns.map(t => txnHTML(t)).join('') : emptyState('No transactions found');
}

/* ============================================================
   ADD / EDIT TRANSACTION
============================================================ */
function openAddModal() {
  state.editId = null;
  document.getElementById('modal-title').textContent = 'New Transaction';
  document.getElementById('txn-desc').value = '';
  document.getElementById('txn-amount').value = '';
  document.getElementById('txn-notes').value = '';
  document.getElementById('txn-date').value = new Date().toISOString().split('T')[0];
  setType('expense');
  openModal('txn-modal');
}

function editTransaction(id) {
  const t = state.transactions.find(tx => tx.id === id);
  if (!t) return;
  state.editId = id;
  document.getElementById('modal-title').textContent = 'Edit Transaction';
  document.getElementById('txn-desc').value = t.desc;
  document.getElementById('txn-amount').value = t.amount;
  document.getElementById('txn-notes').value = t.notes || '';
  document.getElementById('txn-date').value = t.date;
  setType(t.type);
  // Set category after type is set
  setTimeout(() => { document.getElementById('txn-cat').value = t.cat; }, 50);
  openModal('txn-modal');
}

function deleteTransaction(id) {
  if (!confirm('Delete this transaction?')) return;
  if (localStorage.getItem('fiq_token')) {
    apiRequest(`/transactions/${encodeURIComponent(id)}`, { method: 'DELETE' })
      .catch(error => console.warn('Backend delete failed. Local delete is saved:', error.message));
  }
  state.transactions = state.transactions.filter(t => t.id !== id);
  saveState();
  showToast('Transaction deleted', 'success');
  refreshCurrentPage();
  checkBudgetAlerts();
}

function setType(type) {
  state.currentType = type;
  document.getElementById('type-income').classList.toggle('active', type==='income');
  document.getElementById('type-expense').classList.toggle('active', type==='expense');
  // Populate category dropdown
  const sel = document.getElementById('txn-cat');
  sel.innerHTML = CATEGORIES[type].map(c => `<option value="${c.id}">${c.icon} ${c.label}</option>`).join('');
}

function saveTransaction() {
  const desc   = document.getElementById('txn-desc').value.trim();
  const amount = parseFloat(document.getElementById('txn-amount').value);
  const cat    = document.getElementById('txn-cat').value;
  const date   = document.getElementById('txn-date').value;
  const notes  = document.getElementById('txn-notes').value.trim();

  if (!desc)           { showToast('Please enter a description', 'error'); return; }
  if (!amount || amount <= 0) { showToast('Please enter a valid amount', 'error'); return; }
  if (!date)           { showToast('Please select a date', 'error'); return; }

  if (state.editId) {
    const idx = state.transactions.findIndex(t => t.id === state.editId);
    if (idx !== -1) {
      state.transactions[idx] = { ...state.transactions[idx], desc, amount, cat, date, notes, type: state.currentType };
    }
    showToast('Transaction updated!', 'success');
  } else {
    const newTxn = {
      id: `txn_${Date.now()}`,
      type: state.currentType, desc, amount, cat, date, notes,
      createdAt: new Date().toISOString()
    };
    state.transactions.push(newTxn);
    showToast('Transaction added!', 'success');
  }

  saveState();
  closeModal('txn-modal');
  refreshCurrentPage();
  populateFilterDropdowns();
  checkBudgetAlerts();
}

/* ============================================================
   BUDGET
============================================================ */
function openBudgetModal() {
  const sel = document.getElementById('budget-cat');
  sel.innerHTML = CATEGORIES.expense.map(c => `<option value="${c.id}">${c.icon} ${c.label}</option>`).join('');
  document.getElementById('budget-amount').value = '';
  openModal('budget-modal');
}

function saveBudget() {
  const cat    = document.getElementById('budget-cat').value;
  const amount = parseFloat(document.getElementById('budget-amount').value);
  if (!amount || amount <= 0) { showToast('Please enter a valid budget amount', 'error'); return; }
  state.budgets[cat] = amount;
  saveState();
  showToast('Budget saved!', 'success');
  closeModal('budget-modal');
  renderBudget();
  checkBudgetAlerts();
}

function renderBudget() {
  const now = new Date();
  const thisMonth = state.transactions.filter(t => {
    const d = new Date(t.date);
    return t.type === 'expense' && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  });

  const grid = document.getElementById('budget-grid');
  const cats = CATEGORIES.expense;

  grid.innerHTML = cats.map(cat => {
    const budget  = state.budgets[cat.id] || 0;
    const spent   = thisMonth.filter(t => t.cat === cat.id).reduce((s,t)=>s+t.amount,0);
    const pct     = budget > 0 ? Math.min((spent/budget)*100, 100) : 0;
    const over    = budget > 0 && spent > budget;
    const warning = budget > 0 && pct >= 80 && !over;

    const barColor = over ? '#ef4444' : warning ? '#f59e0b' : '#6366f1';
    const statusText = budget === 0 ? 'No budget set' :
                       over    ? `Over by ${fmt(spent-budget)}` :
                       warning ? `⚠️ ${fmt(budget-spent)} remaining` :
                                 `${fmt(budget-spent)} remaining`;
    const statusColor = over ? 'var(--danger)' : warning ? 'var(--warning)' : 'var(--success)';

    return `
      <div class="budget-card">
        <div class="budget-card-header">
          <div class="budget-cat">
            <div class="budget-cat-icon ${getCatClass(cat.id)}">${cat.icon}</div>
            <div>
              <div class="budget-cat-name">${cat.label}</div>
              <div class="budget-amounts">${fmt(spent)} / ${budget > 0 ? fmt(budget) : 'Not set'}</div>
            </div>
          </div>
          <div class="budget-pct" style="color:${barColor}">${pct.toFixed(0)}%</div>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:${pct}%;background:${barColor}"></div>
        </div>
        <div class="budget-status" style="color:${statusColor}">${statusText}</div>
      </div>
    `;
  }).join('');
}

/* ============================================================
   REPORTS
============================================================ */
function generateReport() {
  const month = parseInt(document.getElementById('report-month').value);
  const year  = parseInt(document.getElementById('report-year').value);

  const txns = state.transactions.filter(t => {
    const d = new Date(t.date);
    return d.getMonth() === month && d.getFullYear() === year;
  });

  const income  = txns.filter(t=>t.type==='income').reduce((s,t)=>s+t.amount,0);
  const expense = txns.filter(t=>t.type==='expense').reduce((s,t)=>s+t.amount,0);
  const balance = income - expense;
  const savings = income > 0 ? ((balance/income)*100).toFixed(1) : 0;

  // Summary cards
  document.getElementById('report-cards').innerHTML = `
    <div class="summary-card income">
      <div class="card-header"><span class="card-label">Total Income</span><div class="card-icon green">📥</div></div>
      <div class="card-amount positive">${fmt(income)}</div>
      <div class="card-change">${txns.filter(t=>t.type==='income').length} transactions</div>
    </div>
    <div class="summary-card expense">
      <div class="card-header"><span class="card-label">Total Expenses</span><div class="card-icon red">📤</div></div>
      <div class="card-amount negative">${fmt(expense)}</div>
      <div class="card-change">${txns.filter(t=>t.type==='expense').length} transactions</div>
    </div>
    <div class="summary-card balance">
      <div class="card-header"><span class="card-label">Net Balance</span><div class="card-icon purple">💰</div></div>
      <div class="card-amount ${balance>=0?'positive':'negative'}">${balance<0?'-':''}${fmt(balance)}</div>
      <div class="card-change">Savings rate: ${savings}%</div>
    </div>
  `;

  // Transaction list
  const listEl = document.getElementById('report-txn-list');
  const sorted = [...txns].sort((a,b) => new Date(b.date)-new Date(a.date));
  listEl.innerHTML = sorted.length ? sorted.map(t => txnHTML(t, false)).join('') : emptyState('No transactions for this period');

  // Report charts
  renderReportCharts(txns, month, year);
}

function renderReportCharts(txns, month, year) {
  // Bar: daily income vs expense
  const daysInMonth = new Date(year, month+1, 0).getDate();
  const dayLabels = [], dayIncome = [], dayExpense = [];
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${pad(month+1)}-${pad(d)}`;
    dayLabels.push(d);
    dayIncome.push(txns.filter(t=>t.type==='income'&&t.date===dateStr).reduce((s,t)=>s+t.amount,0));
    dayExpense.push(txns.filter(t=>t.type==='expense'&&t.date===dateStr).reduce((s,t)=>s+t.amount,0));
  }

  const { color, gridColor } = getChartDefaults();

  if (state.charts.reportBar) state.charts.reportBar.destroy();
  const rbc = document.getElementById('reportBarChart');
  if (rbc) {
    state.charts.reportBar = new Chart(rbc, {
      type: 'bar',
      data: {
        labels: dayLabels,
        datasets: [
          { label:'Income',   data:dayIncome,  backgroundColor:'rgba(16,185,129,0.7)', borderRadius:4 },
          { label:'Expenses', data:dayExpense, backgroundColor:'rgba(239,68,68,0.7)',  borderRadius:4 }
        ]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ labels:{ color } } },
        scales:{
          x:{ ticks:{ color }, grid:{ color:gridColor } },
          y:{ ticks:{ color, callback: v=>fmt(v) }, grid:{ color:gridColor } }
        }
      }
    });
  }

  // Pie: expense by category
  const catMap = {};
  txns.filter(t=>t.type==='expense').forEach(t => { catMap[t.cat]=(catMap[t.cat]||0)+t.amount; });
  const labels=[], data=[], colors=[];
  Object.entries(catMap).forEach(([id,amt]) => {
    const cat = getCat('expense',id);
    labels.push(cat.label); data.push(amt); colors.push(cat.color);
  });

  if (state.charts.reportPie) state.charts.reportPie.destroy();
  const rpc = document.getElementById('reportPieChart');
  if (rpc) {
    state.charts.reportPie = new Chart(rpc, {
      type:'doughnut',
      data:{ labels, datasets:[{ data, backgroundColor:colors, borderWidth:2, borderColor: state.settings.darkMode?'#1e2336':'#fff' }] },
      options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{
          legend:{ position:'right', labels:{ color, font:{size:12} } },
          tooltip:{ callbacks:{ label: ctx=>` ${fmt(ctx.parsed)}` } }
        }
      }
    });
  }
}








function generateInsights() {
  const now = new Date();
  
  // === DATA PREPARATION ===
  const thisMonth = state.transactions.filter(t => {
    const d = new Date(t.date);
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  });
  const lastMonth = state.transactions.filter(t => {
    const d = new Date(t.date);
    const lm = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    return d.getMonth() === lm.getMonth() && d.getFullYear() === lm.getFullYear();
  });
  
  // Last 3 months for trend analysis
  const last3Months = state.transactions.filter(t => {
    const d = new Date(t.date);
    const cutoff = new Date(now.getFullYear(), now.getMonth() - 3, 1);
    return d >= cutoff;
  });

  const income = thisMonth.filter(t => t.type === 'income').reduce((s, t) => s + t.amount, 0);
  const expense = thisMonth.filter(t => t.type === 'expense').reduce((s, t) => s + t.amount, 0);
  const lIncome = lastMonth.filter(t => t.type === 'income').reduce((s, t) => s + t.amount, 0);
  const lExpense = lastMonth.filter(t => t.type === 'expense').reduce((s, t) => s + t.amount, 0);
  
  const insights = [];
  const dayOfMonth = now.getDate();
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  const monthProgress = dayOfMonth / daysInMonth;

  // ============================================================
  // 1. SAVINGS RATE — with ACTIONABLE numbers
  // ============================================================
  const savingsRate = income > 0 ? ((income - expense) / income) * 100 : 0;
  const targetSavings = income * 0.20;
  const currentSavings = income - expense;
  const gap = targetSavings - currentSavings;

  if (income > 0) {
    if (savingsRate < 0) {
      insights.push({
        type: 'danger', icon: '🔥', title: 'Spending Exceeds Income!',
        desc: `You're spending ${fmt(Math.abs(currentSavings))} more than you earn. This is unsustainable. Immediate action: cut your top expense category by 30% to break even.`
      });
    } else if (savingsRate < 10) {
      insights.push({
        type: 'danger', icon: '🚨', title: 'Critically Low Savings Rate',
        desc: `Saving only ${savingsRate.toFixed(1)}% (${fmt(currentSavings)}). To hit the 20% target, cut ${fmt(gap)} from expenses — about ${fmt(gap / 30)}/day.`
      });
    } else if (savingsRate >= 30) {
      const yearlyProjection = currentSavings * 12;
      insights.push({
        type: 'success', icon: '🌟', title: 'Excellent Savings Rate!',
        desc: `Saving ${savingsRate.toFixed(1)}% (${fmt(currentSavings)}/mo). At this pace, you'll save ${fmt(yearlyProjection)} this year. Consider investing in index funds for 7-10% annual returns.`
      });
    } else {
      insights.push({
        type: '', icon: '💡', title: 'Savings on Track',
        desc: `Saving ${savingsRate.toFixed(1)}%. To reach 30%, reduce expenses by just ${fmt((income * 0.30) - currentSavings)}/month.`
      });
    }
  }

  // ============================================================
  // 2. PREDICTIVE END-OF-MONTH SPEND
  // ============================================================
  if (monthProgress > 0.2 && monthProgress < 0.95 && expense > 0) {
    const projectedSpend = expense / monthProgress;
    const projectedOverIncome = projectedSpend - income;
    
    if (projectedSpend > income && income > 0) {
      insights.push({
        type: 'warning', icon: '🔮', title: 'Forecast: Overspending Risk',
        desc: `At your current pace (${fmt(expense)} in ${dayOfMonth} days), you'll spend ~${fmt(projectedSpend)} this month — ${fmt(projectedOverIncome)} over income. Slow down spending by ${((projectedOverIncome / projectedSpend) * 100).toFixed(0)}% to stay safe.`
      });
    } else if (projectedSpend > lExpense * 1.15 && lExpense > 0) {
      insights.push({
        type: 'warning', icon: '📊', title: 'Trending Higher Than Last Month',
        desc: `Projected spend: ${fmt(projectedSpend)} vs last month's ${fmt(lExpense)}. You're on track to exceed last month by ${fmt(projectedSpend - lExpense)}.`
      });
    }
  }

  // ============================================================
  // 3. SPENDING CHANGE — with ROOT CAUSE detection
  // ============================================================
  if (lExpense > 0) {
    const changePct = ((expense - lExpense) / lExpense) * 100;
    
    if (changePct > 20) {
      // Find which category drove the spike
      const thisCatTotals = {}, lastCatTotals = {};
      thisMonth.filter(t => t.type === 'expense').forEach(t => {
        thisCatTotals[t.cat] = (thisCatTotals[t.cat] || 0) + t.amount;
      });
      lastMonth.filter(t => t.type === 'expense').forEach(t => {
        lastCatTotals[t.cat] = (lastCatTotals[t.cat] || 0) + t.amount;
      });
      
      let biggestDriver = null, biggestDelta = 0;
      Object.keys(thisCatTotals).forEach(cat => {
        const delta = thisCatTotals[cat] - (lastCatTotals[cat] || 0);
        if (delta > biggestDelta) { biggestDelta = delta; biggestDriver = cat; }
      });
      
      const driverCat = biggestDriver ? getCat('expense', biggestDriver) : null;
      insights.push({
        type: 'warning', icon: '📈', title: 'Spending Spike Detected',
        desc: `Expenses up ${changePct.toFixed(1)}% (${fmt(lExpense)} → ${fmt(expense)})${
          driverCat ? `. Main driver: ${driverCat.label} (+${fmt(biggestDelta)}). Review recent ${driverCat.label} purchases.` : '.'
        }`
      });
    } else if (changePct < -10) {
      insights.push({
        type: 'success', icon: '✅', title: 'Great Cost Reduction!',
        desc: `Spending down ${Math.abs(changePct).toFixed(1)}% — saved ${fmt(lExpense - expense)} vs last month. If you maintain this for a year, that's ${fmt((lExpense - expense) * 12)} extra saved!`
      });
    }
  }

  // ============================================================
  // 4. CATEGORY ANALYSIS — Detect unhealthy ratios
  // ============================================================
  const catTotals = {};
  thisMonth.filter(t => t.type === 'expense').forEach(t => {
    catTotals[t.cat] = (catTotals[t.cat] || 0) + t.amount;
  });
  
  // Healthy spending benchmarks (% of income)
  const benchmarks = {
    food: 15, transport: 10, shopping: 10,
    entertainment: 5, bills: 25, rent: 30, housing: 30
  };

  Object.entries(catTotals).forEach(([catId, amount]) => {
    const cat = getCat('expense', catId);
    const pctOfIncome = income > 0 ? (amount / income) * 100 : 0;
    const benchmark = benchmarks[catId];
    
    if (benchmark && pctOfIncome > benchmark * 1.5) {
      const ideal = (income * benchmark) / 100;
      const potentialSavings = amount - ideal;
      insights.push({
        type: 'warning', icon: cat.icon,
        title: `High ${cat.label} Spending`,
        desc: `${cat.label} is ${pctOfIncome.toFixed(1)}% of income (healthy: ~${benchmark}%). Reducing to the recommended level would save ${fmt(potentialSavings)}/month — that's ${fmt(potentialSavings * 12)}/year.`
      });
    }
  });

  // Top category insight
  const topCat = Object.entries(catTotals).sort((a, b) => b[1] - a[1])[0];
  if (topCat) {
    const cat = getCat('expense', topCat[0]);
    const pct = expense > 0 ? ((topCat[1] / expense) * 100).toFixed(1) : 0;
    insights.push({
      type: pct > 40 ? 'warning' : '', icon: cat.icon,
      title: `Top Spend: ${cat.label}`,
      desc: `${cat.label} = ${pct}% of expenses (${fmt(topCat[1])}). ${
        pct > 40 ? `Try a 10% cut: save ${fmt(topCat[1] * 0.10)}/month effortlessly.` : 'Distribution looks balanced.'
      }`
    });
  }

  // ============================================================
  // 5. BUDGET ALERTS — with severity levels
  // ============================================================
  Object.entries(state.budgets).forEach(([catId, budget]) => {
    const spent = thisMonth.filter(t => t.type === 'expense' && t.cat === catId)
      .reduce((s, t) => s + t.amount, 0);
    if (budget <= 0) return;
    
    const usedPct = (spent / budget) * 100;
    const cat = getCat('expense', catId);
    
    if (spent > budget) {
      insights.push({
        type: 'danger', icon: '🔴',
        title: `Budget Exceeded: ${cat.label}`,
        desc: `Over by ${fmt(spent - budget)} (${(usedPct - 100).toFixed(0)}% over). Budget: ${fmt(budget)}, Spent: ${fmt(spent)}. Pause non-essential ${cat.label} purchases until next month.`
      });
    } else if (usedPct > 80 && monthProgress < 0.75) {
      const daysLeft = daysInMonth - dayOfMonth;
      const remaining = budget - spent;
      insights.push({
        type: 'warning', icon: '⚠️',
        title: `${cat.label} Budget Almost Used`,
        desc: `${usedPct.toFixed(0)}% used with ${daysLeft} days left. Limit yourself to ${fmt(remaining / daysLeft)}/day to stay within budget.`
      });
    }
  });

  // ============================================================
  // 6. BEHAVIORAL PATTERNS — Frequency & habits
  // ============================================================
  const foodTxns = thisMonth.filter(t => t.cat === 'food');
  if (foodTxns.length > 10) {
    const avgFood = foodTxns.reduce((s, t) => s + t.amount, 0) / foodTxns.length;
    insights.push({
      type: 'warning', icon: '🍔', title: 'Frequent Food Spending',
      desc: `${foodTxns.length} food transactions averaging ${fmt(avgFood)}. Cooking 3 meals/week at home could save ~${fmt(avgFood * 12)}/month.`
    });
  }

  // Weekend vs weekday spending pattern
  const weekendSpend = thisMonth.filter(t => {
    const d = new Date(t.date).getDay();
    return t.type === 'expense' && (d === 0 || d === 6);
  }).reduce((s, t) => s + t.amount, 0);
  
  if (expense > 0 && (weekendSpend / expense) > 0.5) {
    insights.push({
      type: 'warning', icon: '🎉', title: 'Weekend Overspending',
      desc: `${((weekendSpend / expense) * 100).toFixed(0)}% of spending happens on weekends (${fmt(weekendSpend)}). Plan free/low-cost weekend activities to cut this in half.`
    });
  }

  // Small transactions adding up
  const smallTxns = thisMonth.filter(t => t.type === 'expense' && t.amount < 10);
  const smallTotal = smallTxns.reduce((s, t) => s + t.amount, 0);
  if (smallTxns.length > 15 && smallTotal > 50) {
    insights.push({
      type: '', icon: '☕', title: 'Small Purchases Add Up',
      desc: `${smallTxns.length} small purchases totaled ${fmt(smallTotal)}. These "invisible" expenses are a stealth budget killer — track them mindfully.`
    });
  }

  // ============================================================
  // 7. POSITIVE REINFORCEMENT
  // ============================================================
  if (income > expense * 1.5 && income > 0) {
    const surplus = income - expense;
    insights.push({
      type: 'success', icon: '🏆', title: 'Strong Financial Health',
      desc: `Surplus of ${fmt(surplus)}. Smart split: ${fmt(surplus * 0.5)} to investments, ${fmt(surplus * 0.3)} to emergency fund, ${fmt(surplus * 0.2)} for guilt-free spending.`
    });
  }

  // ============================================================
  // 8. EDGE CASES
  // ============================================================
  if (income === 0) {
    insights.push({
      type: 'danger', icon: '⚠️', title: 'No Income Recorded',
      desc: `No income logged this month. Add your salary/income sources to unlock accurate insights and budgeting recommendations.`
    });
  }
  
  if (thisMonth.length < 5) {
    insights.push({
      type: '', icon: '📝', title: 'Add More Transactions',
      desc: `Only ${thisMonth.length} transactions logged. Track daily for 1 week to unlock pattern detection and predictive insights.`
    });
  }

  // ============================================================
  // RENDER
  // ============================================================
  const grid = document.getElementById('insights-grid');
  grid.innerHTML = insights.map(ins => `
    <div class="insight-card ${ins.type}">
      <div class="insight-header">
        <div class="insight-icon">${ins.icon}</div>
        <div>
          <div class="insight-title">${ins.title}</div>
          <div class="insight-desc">${ins.desc}</div>
        </div>
      </div>
    </div>
  `).join('') || '<p class="text-muted">Add more transactions to generate insights.</p>';
  
  renderInsightChart();
}





function renderInsightChart() {
  const ctx = document.getElementById('insightLineChart');
  if (!ctx) return;
  if (state.charts.insightLine) state.charts.insightLine.destroy();

  const now = new Date();
  const HISTORY_WEEKS = 12;
  const FORECAST_WEEKS = 4;
  
  const labels = [];
  const weeklyExpense = [];
  const weeklyIncome = [];

  // ============================================================
  // 1. BUILD HISTORICAL DATA (last 12 weeks)
  // ============================================================
  for (let w = HISTORY_WEEKS - 1; w >= 0; w--) {
    const weekStart = new Date(now);
    weekStart.setDate(weekStart.getDate() - w * 7 - weekStart.getDay());
    weekStart.setHours(0, 0, 0, 0);
    
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);
    weekEnd.setHours(23, 59, 59, 999);

    labels.push(`W${HISTORY_WEEKS - w}`);

    weeklyExpense.push(
      state.transactions.filter(t => {
        const d = new Date(t.date);
        return t.type === 'expense' && d >= weekStart && d <= weekEnd;
      }).reduce((s, t) => s + t.amount, 0)
    );

    weeklyIncome.push(
      state.transactions.filter(t => {
        const d = new Date(t.date);
        return t.type === 'income' && d >= weekStart && d <= weekEnd;
      }).reduce((s, t) => s + t.amount, 0)
    );
  }

  // ============================================================
  // 2. PREDICTIVE FORECASTING — Linear Regression + Smoothing
  // ============================================================
  const expenseForecast = forecastNextWeeks(weeklyExpense, FORECAST_WEEKS);
  const incomeForecast = forecastNextWeeks(weeklyIncome, FORECAST_WEEKS);

  // Add forecast labels
  for (let i = 1; i <= FORECAST_WEEKS; i++) {
    labels.push(`W${HISTORY_WEEKS + i} 🔮`);
  }

  // ============================================================
  // 3. PREPARE DATASETS — Connect history to forecast smoothly
  // ============================================================
  // Pad arrays so forecast lines start where history ends
  const expenseHistoryPadded = [...weeklyExpense, ...Array(FORECAST_WEEKS).fill(null)];
  const incomeHistoryPadded = [...weeklyIncome, ...Array(FORECAST_WEEKS).fill(null)];

  // Forecast arrays: null for past, then last historical value + forecast
  const expenseForecastPadded = [
    ...Array(HISTORY_WEEKS - 1).fill(null),
    weeklyExpense[weeklyExpense.length - 1], // bridge point
    ...expenseForecast
  ];
  const incomeForecastPadded = [
    ...Array(HISTORY_WEEKS - 1).fill(null),
    weeklyIncome[weeklyIncome.length - 1],
    ...incomeForecast
  ];

  // Average expense reference line
  const avgExpense = weeklyExpense.reduce((s, v) => s + v, 0) / weeklyExpense.length;
  const avgLine = Array(labels.length).fill(avgExpense);

  const { color, gridColor } = getChartDefaults();

  // ============================================================
  // 4. RENDER CHART
  // ============================================================
  state.charts.insightLine = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        // --- Historical Expenses ---
        {
          label: 'Expenses',
          data: expenseHistoryPadded,
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239,68,68,0.08)',
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2.5
        },
        // --- Predicted Expenses ---
        {
          label: 'Predicted Expenses',
          data: expenseForecastPadded,
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239,68,68,0.04)',
          fill: true,
          tension: 0.4,
          pointRadius: 5,
          pointStyle: 'rectRot',
          borderDash: [6, 4],
          borderWidth: 2
        },
        // --- Historical Income ---
        {
          label: 'Income',
          data: incomeHistoryPadded,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16,185,129,0.08)',
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2.5
        },
        // --- Predicted Income ---
        {
          label: 'Predicted Income',
          data: incomeForecastPadded,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16,185,129,0.04)',
          fill: true,
          tension: 0.4,
          pointRadius: 5,
          pointStyle: 'rectRot',
          borderDash: [6, 4],
          borderWidth: 2
        },
        // --- Average Expense Reference ---
        {
          label: 'Avg Expense',
          data: avgLine,
          borderColor: 'rgba(156, 163, 175, 0.7)',
          borderDash: [2, 4],
          borderWidth: 1.5,
          pointRadius: 0,
          fill: false,
          tension: 0
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          labels: {
            color,
            usePointStyle: true,
            padding: 15,
            filter: (item) => item.text !== 'Avg Expense' || true
          }
        },
        tooltip: {
          backgroundColor: 'rgba(17, 24, 39, 0.95)',
          titleColor: '#fff',
          bodyColor: '#fff',
          padding: 12,
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          callbacks: {
            title: (items) => {
              const label = items[0].label;
              return label.includes('🔮') ? `${label} (Forecast)` : label;
            },
            label: (ctx) => {
              if (ctx.parsed.y === null) return null;
              const isPredicted = ctx.dataset.label.includes('Predicted');
              const prefix = isPredicted ? '🔮 ' : '';
              return `${prefix}${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`;
            }
          }
        },
        // Highlight forecast zone with annotation if plugin available
        annotation: window.Chart?.registry?.plugins?.get('annotation') ? {
          annotations: {
            forecastZone: {
              type: 'box',
              xMin: HISTORY_WEEKS - 0.5,
              xMax: HISTORY_WEEKS + FORECAST_WEEKS - 0.5,
              backgroundColor: 'rgba(99, 102, 241, 0.05)',
              borderColor: 'rgba(99, 102, 241, 0.2)',
              borderWidth: 1,
              borderDash: [4, 4],
              label: {
                content: 'Forecast Zone',
                display: true,
                position: 'start',
                color: 'rgba(99, 102, 241, 0.8)',
                font: { size: 10, weight: 'bold' }
              }
            }
          }
        } : undefined
      },
      scales: {
        x: {
          ticks: { color, font: { size: 11 } },
          grid: { color: gridColor }
        },
        y: {
          ticks: {
            color,
            callback: v => fmt(v)
          },
          grid: { color: gridColor },
          beginAtZero: true
        }
      }
    }
  });
}

// ============================================================
// 🤖 AI FORECASTING HELPER — Linear Regression + EMA Smoothing
// ============================================================
function forecastNextWeeks(history, weeksAhead) {
  if (!history || history.length < 3) {
    // Not enough data — return flat line of last value
    const last = history[history.length - 1] || 0;
    return Array(weeksAhead).fill(last);
  }

  // === STEP 1: Calculate Linear Regression (trend line) ===
  const n = history.length;
  const xMean = (n - 1) / 2;
  const yMean = history.reduce((s, v) => s + v, 0) / n;

  let numerator = 0, denominator = 0;
  for (let i = 0; i < n; i++) {
    numerator += (i - xMean) * (history[i] - yMean);
    denominator += (i - xMean) ** 2;
  }
  const slope = denominator === 0 ? 0 : numerator / denominator;
  const intercept = yMean - slope * xMean;

  // === STEP 2: Calculate Exponential Moving Average (recent bias) ===
  const alpha = 0.4; // Higher = more weight to recent weeks
  let ema = history[0];
  for (let i = 1; i < n; i++) {
    ema = alpha * history[i] + (1 - alpha) * ema;
  }

  // === STEP 3: Blend Regression + EMA for robust forecast ===
  const forecast = [];
  for (let i = 1; i <= weeksAhead; i++) {
    const regressionPrediction = intercept + slope * (n - 1 + i);
    // 60% trend + 40% recent average for stability
    const blended = (regressionPrediction * 0.6) + (ema * 0.4);
    // Apply slight drift from slope to EMA component
    const finalPrediction = Math.max(0, blended + (slope * i * 0.4));
    forecast.push(Math.round(finalPrediction * 100) / 100);
  }

  return forecast;
}


/* ============================================================
   BUDGET ALERTS / NOTIFICATIONS
============================================================ */
function checkBudgetAlerts() {
  if (!state.settings.budgetAlert) return;
  const now = new Date();
  const thisMonth = state.transactions.filter(t => {
    const d = new Date(t.date);
    return t.type==='expense' && d.getMonth()===now.getMonth() && d.getFullYear()===now.getFullYear();
  });

  const newNotifs = [];
  Object.entries(state.budgets).forEach(([catId, budget]) => {
    if (!budget) return;
    const spent = thisMonth.filter(t=>t.cat===catId).reduce((s,t)=>s+t.amount,0);
    const pct = (spent/budget)*100;
    const cat = getCat('expense', catId);

    if (pct >= 100) {
      const exists = state.notifications.find(n => n.id === `over_${catId}_${now.getMonth()}`);
      if (!exists) {
        newNotifs.push({ id:`over_${catId}_${now.getMonth()}`, type:'danger', text:`🔴 Budget exceeded for ${cat.label}! Spent ${fmt(spent)} of ${fmt(budget)} budget.`, time: new Date().toISOString() });
      }
    } else if (pct >= 80) {
      const exists = state.notifications.find(n => n.id === `warn_${catId}_${now.getMonth()}`);
      if (!exists) {
        newNotifs.push({ id:`warn_${catId}_${now.getMonth()}`, type:'warning', text:`⚠️ Approaching budget limit for ${cat.label}. ${pct.toFixed(0)}% used.`, time: new Date().toISOString() });
      }
    }
  });

  if (newNotifs.length) {
    state.notifications = [...newNotifs, ...state.notifications].slice(0, 20);
    saveState();
    renderNotifications();
    document.getElementById('notif-dot').style.display = 'block';
  }

  renderNotifications();
}

function renderNotifications() {
  const list = document.getElementById('notif-list');
  if (!list) return;
  if (!state.notifications.length) {
    list.innerHTML = '<div class="no-txns">No notifications</div>';
    document.getElementById('notif-dot').style.display = 'none';
    return;
  }
  document.getElementById('notif-dot').style.display = 'block';
  list.innerHTML = state.notifications.slice(0,10).map(n => {
    const t = new Date(n.time);
    const timeStr = `${t.getDate()} ${MONTHS[t.getMonth()]} ${t.getHours()}:${String(t.getMinutes()).padStart(2,'0')}`;
    const dotColor = n.type==='danger'?'#ef4444':n.type==='warning'?'#f59e0b':'#6366f1';
    return `
      <div class="notif-item">
        <div class="notif-dot-type" style="background:${dotColor}"></div>
        <div>
          <div class="notif-text">${n.text}</div>
          <div class="notif-time">${timeStr}</div>
        </div>
      </div>
    `;
  }).join('');
}

function toggleNotifPanel() {
  const panel = document.getElementById('notif-panel');
  panel.classList.toggle('open');
  if (panel.classList.contains('open')) renderNotifications();
}

function clearNotifs() {
  state.notifications = [];
  saveState();
  renderNotifications();
  document.getElementById('notif-dot').style.display = 'none';
}

/* ============================================================
   SETTINGS
============================================================ */
async function saveSettings() {
  const name = document.getElementById('settings-name').value.trim();
  const currency = document.getElementById('settings-currency').value;
  if (!name) { showToast('Please enter a name', 'error'); return; }

  state.settings.currency = currency;
  state.user.name = name;

  try {
    if (localStorage.getItem('fiq_token')) {
      await apiRequest('/profile', { method: 'PUT', body: JSON.stringify({ name }) });
    }
  } catch (error) {
    console.warn('Backend profile update failed. Local settings are saved:', error.message);
  }

  // Update users list
  const users = JSON.parse(localStorage.getItem('fiq_users') || '[]');
  const idx = users.findIndex(u => u.email === state.user.email);
  if (idx !== -1) { users[idx].name = name; localStorage.setItem('fiq_users', JSON.stringify(users)); }
  localStorage.setItem('fiq_current_user', JSON.stringify(state.user));

  saveState();
  document.getElementById('user-name-display').textContent = name;
  document.getElementById('user-avatar').textContent = name.charAt(0).toUpperCase();
  showToast('Settings saved!', 'success');
}

function toggleSetting(btn) {
  btn.classList.toggle('on');
  const id = btn.id;
  if (id === 'toggle-budget-alert') state.settings.budgetAlert = btn.classList.contains('on');
  if (id === 'toggle-unusual')      state.settings.unusualAlert = btn.classList.contains('on');
  if (id === 'toggle-weekly')       state.settings.weeklyAlert = btn.classList.contains('on');
  saveState();
}

function clearAllData() {
  if (!confirm('This will permanently delete ALL your transactions and budgets. Are you sure?')) return;
  if (localStorage.getItem('fiq_token')) {
    apiRequest('/data', { method: 'DELETE' })
      .catch(error => console.warn('Backend clear data failed. Local data is cleared:', error.message));
  }
  state.transactions = [];
  state.budgets = {};
  state.notifications = [];
  saveState();
  showToast('All data cleared', 'warning');
  refreshCurrentPage();
}

/* ============================================================
   EXPORT
============================================================ */
function exportCSV() {
  const month = parseInt(document.getElementById('report-month').value);
  const year  = parseInt(document.getElementById('report-year').value);
  const txns  = state.transactions.filter(t => {
    const d = new Date(t.date);
    return d.getMonth()===month && d.getFullYear()===year;
  }).sort((a,b) => new Date(a.date)-new Date(b.date));

  const rows = [['Date','Type','Category','Description','Amount','Notes']];
  txns.forEach(t => {
    const cat = getCat(t.type, t.cat);
    rows.push([t.date, t.type, cat.label, t.desc, t.amount, t.notes||'']);
  });

  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type:'text/csv' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url;
  a.download = `FinanceIQ_${FULL_MONTHS[month]}_${year}.csv`;
  a.click();
  URL.revokeObjectURL(url);
  showToast('CSV exported!', 'success');
}

function exportPDF() {
  const month = parseInt(document.getElementById('report-month').value);
  const year  = parseInt(document.getElementById('report-year').value);
  const txns  = state.transactions.filter(t => {
    const d = new Date(t.date);
    return d.getMonth()===month && d.getFullYear()===year;
  }).sort((a,b) => new Date(a.date)-new Date(b.date));

  const income  = txns.filter(t=>t.type==='income').reduce((s,t)=>s+t.amount,0);
  const expense = txns.filter(t=>t.type==='expense').reduce((s,t)=>s+t.amount,0);
  const balance = income - expense;

  try {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    let y = 20;

    // Header
    doc.setFillColor(99,102,241);
    doc.rect(0,0,210,40,'F');
    doc.setTextColor(255,255,255);
    doc.setFontSize(22); doc.setFont(undefined,'bold');
    doc.text('FinanceIQ', 15, 20);
    doc.setFontSize(12); doc.setFont(undefined,'normal');
    doc.text(`Financial Report – ${FULL_MONTHS[month]} ${year}`, 15, 32);
    y = 55;

    // Summary
    doc.setTextColor(30,31,60);
    doc.setFontSize(14); doc.setFont(undefined,'bold');
    doc.text('Summary', 15, y); y += 10;
    doc.setFontSize(11); doc.setFont(undefined,'normal');
    doc.setTextColor(60,60,80);
    doc.text(`Total Income:   ${fmt(income)}`, 15, y); y += 8;
    doc.text(`Total Expenses: ${fmt(expense)}`, 15, y); y += 8;
    doc.text(`Net Balance:    ${fmt(balance)}`, 15, y); y += 14;

    // Transactions
    doc.setFontSize(14); doc.setFont(undefined,'bold'); doc.setTextColor(30,31,60);
    doc.text('Transactions', 15, y); y += 8;

    // Table header
    doc.setFillColor(240,244,255);
    doc.rect(10, y, 190, 8, 'F');
    doc.setFontSize(9); doc.setFont(undefined,'bold'); doc.setTextColor(80,80,120);
    doc.text('Date',15,y+5.5); doc.text('Description',45,y+5.5);
    doc.text('Category',110,y+5.5); doc.text('Type',150,y+5.5); doc.text('Amount',175,y+5.5);
    y += 10;

    txns.forEach((t,i) => {
      if (y > 270) { doc.addPage(); y = 20; }
      if (i%2===0) { doc.setFillColor(248,249,255); doc.rect(10,y-2,190,8,'F'); }
      const cat = getCat(t.type,t.cat);
      doc.setFont(undefined,'normal'); doc.setFontSize(9); doc.setTextColor(40,40,60);
      doc.text(t.date,15,y+4);
      doc.text(t.desc.length>28?t.desc.slice(0,25)+'...':t.desc,45,y+4);
      doc.text(cat.label,110,y+4);
      doc.text(t.type.charAt(0).toUpperCase()+t.type.slice(1),150,y+4);
      const amtColor = t.type==='income'?[16,185,129]:[239,68,68];
      doc.setTextColor(...amtColor);
      doc.text(`${t.type==='income'?'+':'-'}${fmt(t.amount)}`,175,y+4);
      y += 8;
    });

    // Footer
    doc.setTextColor(150,150,170); doc.setFontSize(8);
    doc.text(`Generated by FinanceIQ on ${new Date().toLocaleDateString()}`, 15, 285);

    doc.save(`FinanceIQ_${FULL_MONTHS[month]}_${year}.pdf`);
    showToast('PDF exported!', 'success');
  } catch(e) {
    showToast('PDF export failed. Please try CSV.', 'error');
  }
}

/* ============================================================
   MODAL HELPERS
============================================================ */
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

/* ============================================================
   TOAST
============================================================ */
function showToast(msg, type='') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  const icons = { success:'✅', error:'❌', warning:'⚠️', '':'ℹ️' };
  toast.innerHTML = `<span>${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity='0'; toast.style.transform='translateY(10px)'; toast.style.transition='all 0.3s'; setTimeout(()=>toast.remove(),300); }, 3000);
}

/* ============================================================
   REFRESH CURRENT PAGE
============================================================ */
function refreshCurrentPage() {
  const activePage = document.querySelector('.page.active');
  if (!activePage) return;
  const pageId = activePage.id.replace('page-','');
  switch(pageId) {
    case 'dashboard':    renderDashboard(); break;
    case 'transactions': renderTransactions(); break;
    case 'budget':       renderBudget(); break;
    case 'reports':      generateReport(); break;
    case 'insights':     generateInsights(); break;
  }
}

/* ============================================================
   KEYBOARD SHORTCUTS
============================================================ */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    document.getElementById('notif-panel').classList.remove('open');
  }
  if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
    e.preventDefault();
    openAddModal();
  }
});

/* ============================================================
   CLICK OUTSIDE TO CLOSE NOTIF PANEL
============================================================ */
document.addEventListener('click', e => {
  const panel = document.getElementById('notif-panel');
  const btn = e.target.closest('.icon-btn');
  if (panel && !panel.contains(e.target) && !btn) {
    panel.classList.remove('open');
  }
});

/* ============================================================
   AUTO-LOGIN (if session exists)
============================================================ */
window.addEventListener('DOMContentLoaded', async () => {
  const savedUser = localStorage.getItem('fiq_current_user');
  if (savedUser) {
    try {
      const user = JSON.parse(savedUser);
      state.user = user;
      const loadedFromBackend = await loadStateFromBackend();
      if (!loadedFromBackend) loadState();
      initApp();
    } catch(e) {
      localStorage.removeItem('fiq_current_user');
  localStorage.removeItem('fiq_token');
    }
  }
});

function togglePassword(id, icon){
  const input = document.getElementById(id);
  if(input.type === 'password'){
    input.type = 'text';
    icon.textContent = '🙈';
  } else {
    input.type = 'password';
    icon.textContent = '👁️';
  }
}
