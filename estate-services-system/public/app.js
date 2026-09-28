(function () {
  const API = '/api';

  let services = [];
  let staff = [];
  let selectedServiceId = null;

  const fmtKSh = (n) => 'KSh ' + Number(n).toLocaleString('en-KE');

  const STATUS_CLASS = {
    Pending: 'badge-pending',
    Confirmed: 'badge-confirmed',
    'In Progress': 'badge-progress',
    Completed: 'badge-completed',
    Cancelled: 'badge-cancelled',
  };
  const STATUS_OPTIONS = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];

  // ------------------------------------------------------------------
  // tiny fetch helper
  // ------------------------------------------------------------------
  async function api(method, url, body) {
    const res = await fetch(API + url, {
      method,
      headers: body ? { 'Content-Type': 'application/json' } : undefined,
      body: body ? JSON.stringify(body) : undefined,
    });
    let data = null;
    try {
      data = await res.json();
    } catch (e) {
      /* no body */
    }
    if (!res.ok) {
      throw new Error((data && data.error) || `Request failed (${res.status})`);
    }
    return data;
  }

  function toast(message, isError) {
    const el = document.getElementById('toast');
    el.textContent = message;
    el.classList.toggle('error', !!isError);
    el.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(() => el.classList.remove('show'), 2600);
  }

  // ------------------------------------------------------------------
  // tabs
  // ------------------------------------------------------------------
  document.querySelectorAll('.tab-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('active'));
      document.querySelectorAll('.panel').forEach((p) => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
      if (btn.dataset.tab === 'dash') loadStats();
      if (btn.dataset.tab === 'ledger') loadBookings();
      if (btn.dataset.tab === 'staff') loadStaff();
    });
  });

  // ------------------------------------------------------------------
  // services + booking form
  // ------------------------------------------------------------------
  async function loadServices() {
    services = await api('GET', '/services');
    const grid = document.getElementById('svc-grid');
    grid.innerHTML = '';
    services
      .filter((s) => s.active !== false)
      .forEach((svc) => {
        const el = document.createElement('div');
        el.className = 'svc-card';
        el.dataset.id = svc.id;
        el.innerHTML = `
          <div class="check"></div>
          <div class="cat">${svc.category}</div>
          <div class="name">${svc.name}</div>
          <div class="meta">
            <span class="price">${fmtKSh(svc.price)}</span>
            <span class="dur">${svc.duration}</span>
          </div>`;
        el.addEventListener('click', () => selectService(svc));
        grid.appendChild(el);
      });

    const filterService = document.getElementById('filter-service');
    filterService.innerHTML = '<option value="all">All services</option>';
    services.forEach((svc) => {
      const opt = document.createElement('option');
      opt.value = svc.id;
      opt.textContent = svc.name;
      filterService.appendChild(opt);
    });
  }

  function selectService(svc) {
    selectedServiceId = svc.id;
    document.querySelectorAll('.svc-card').forEach((c) => c.classList.remove('selected'));
    document.querySelector(`.svc-card[data-id="${svc.id}"]`).classList.add('selected');
    const note = document.getElementById('est-note');
    note.style.display = 'block';
    note.innerHTML = `Estimated cost for <strong>${svc.name}</strong>: <span class="amt">${fmtKSh(svc.price)}</span> · ${svc.duration}`;
    const btn = document.getElementById('submit-btn');
    btn.disabled = false;
    btn.textContent = 'Book ' + svc.name;
  }

  const form = document.getElementById('booking-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!selectedServiceId) return;
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Booking…';

    const payload = {
      serviceId: selectedServiceId,
      resident: document.getElementById('f-name').value.trim(),
      unit: document.getElementById('f-unit').value.trim(),
      phone: document.getElementById('f-phone').value.trim(),
      date: document.getElementById('f-date').value,
      time: document.getElementById('f-time').value,
      notes: document.getElementById('f-notes').value.trim(),
    };

    try {
      const booking = await api('POST', '/bookings', payload);
      toast(`Booked — reference ${booking.ref}`);
      form.reset();
      document.getElementById('f-date').valueAsDate = new Date();
      selectedServiceId = null;
      document.querySelectorAll('.svc-card').forEach((c) => c.classList.remove('selected'));
      document.getElementById('est-note').style.display = 'none';
      btn.disabled = true;
      btn.textContent = 'Select a service to continue';
      refreshHeader();
      document.querySelector('[data-tab="ledger"]').click();
    } catch (err) {
      toast(err.message, true);
      btn.disabled = false;
      btn.textContent = 'Try again';
    }
  });

  // ------------------------------------------------------------------
  // ledger
  // ------------------------------------------------------------------
  const searchInput = document.getElementById('search-input');
  const filterStatus = document.getElementById('filter-status');
  const filterService = document.getElementById('filter-service');
  let searchDebounce;
  searchInput.addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(loadBookings, 250);
  });
  filterStatus.addEventListener('change', loadBookings);
  filterService.addEventListener('change', loadBookings);

  async function loadBookings() {
    const list = document.getElementById('ledger-list');
    const params = new URLSearchParams();
    if (filterStatus.value !== 'all') params.set('status', filterStatus.value);
    if (filterService.value !== 'all') params.set('serviceId', filterService.value);
    if (searchInput.value.trim()) params.set('q', searchInput.value.trim());

    let rows;
    try {
      rows = await api('GET', '/bookings?' + params.toString());
    } catch (err) {
      list.innerHTML = `<div class="empty">Couldn't load bookings: ${err.message}</div>`;
      return;
    }

    list.innerHTML = '';
    if (rows.length === 0) {
      list.innerHTML = `<div class="empty">No bookings yet. Head to "New Booking" to add the estate's first request.</div>`;
      return;
    }

    rows.forEach((b) => {
      const el = document.createElement('div');
      el.className = 'entry';
      const staffOptions = ['<option value="">Unassigned</option>']
        .concat(staff.map((s) => `<option value="${s.id}" ${s.id === b.staffId ? 'selected' : ''}>${s.name}</option>`))
        .join('');

      el.innerHTML = `
        <div class="ref"><span class="code">${b.ref}</span>${b.date || '—'}</div>
        <div class="main">
          <div class="svc-name">${b.serviceName}</div>
          <div class="who">${b.resident} · Unit ${b.unit} · ${b.phone}</div>
          <div class="when">${b.date || 'no date'} ${b.time || ''}${b.notes ? ' · ' + b.notes : ''}</div>
        </div>
        <div class="side">
          <span class="price">${fmtKSh(b.price)}</span>
          <select class="staff-select" data-ref="${b.ref}">${staffOptions}</select>
          <span class="badge ${STATUS_CLASS[b.status]}">${b.status}</span>
          <select class="status-select" data-ref="${b.ref}">
            ${STATUS_OPTIONS.map((s) => `<option value="${s}" ${s === b.status ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </div>`;
      list.appendChild(el);
    });

    list.querySelectorAll('.status-select').forEach((sel) => {
      sel.addEventListener('change', async () => {
        try {
          await api('PATCH', '/bookings/' + sel.dataset.ref, { status: sel.value });
          toast(`${sel.dataset.ref} marked ${sel.value}`);
          refreshHeader();
        } catch (err) {
          toast(err.message, true);
        }
      });
    });

    list.querySelectorAll('.staff-select').forEach((sel) => {
      sel.addEventListener('change', async () => {
        try {
          await api('PATCH', '/bookings/' + sel.dataset.ref, { staffId: sel.value || null });
          toast(`${sel.dataset.ref} assigned`);
        } catch (err) {
          toast(err.message, true);
        }
      });
    });
  }

  // ------------------------------------------------------------------
  // staff
  // ------------------------------------------------------------------
  const staffForm = document.getElementById('staff-form');
  staffForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      name: document.getElementById('s-name').value.trim(),
      role: document.getElementById('s-role').value.trim(),
      phone: document.getElementById('s-phone').value.trim(),
    };
    try {
      await api('POST', '/staff', payload);
      staffForm.reset();
      toast('Staff member added');
      await loadStaff();
    } catch (err) {
      toast(err.message, true);
    }
  });

  async function loadStaff() {
    const list = document.getElementById('staff-list');
    staff = await api('GET', '/staff');
    list.innerHTML = '';
    if (staff.length === 0) {
      list.innerHTML = `<div class="empty">No staff on the roster yet.</div>`;
      return;
    }
    staff.forEach((s) => {
      const row = document.createElement('div');
      row.className = 'staff-row';
      row.innerHTML = `
        <div class="who">
          <div class="name">${s.name}</div>
          <div class="role">${s.role || 'General'}</div>
          ${s.phone ? `<div class="phone">${s.phone}</div>` : ''}
        </div>
        <button class="btn btn-ghost" data-id="${s.id}">Remove</button>`;
      row.querySelector('button').addEventListener('click', async () => {
        try {
          await api('DELETE', '/staff/' + s.id);
          toast('Staff member removed');
          await loadStaff();
        } catch (err) {
          toast(err.message, true);
        }
      });
      list.appendChild(row);
    });
  }

  // ------------------------------------------------------------------
  // dashboard
  // ------------------------------------------------------------------
  async function loadStats() {
    const stats = await api('GET', '/stats');
    document.getElementById('d-total').textContent = stats.total;
    document.getElementById('d-completed').textContent = stats.completed;
    document.getElementById('d-cancelled').textContent = stats.cancelled;
    document.getElementById('d-revenue').textContent = fmtKSh(stats.revenue);

    const svcBars = document.getElementById('svc-bars');
    const svcEntries = Object.values(stats.byService);
    const maxSvc = Math.max(1, ...svcEntries.map((s) => s.count));
    svcBars.innerHTML = svcEntries
      .map(
        (s) => `
      <div class="bar-row">
        <div class="label">${s.name}</div>
        <div class="bar-track"><div class="bar-fill" style="width:${(s.count / maxSvc) * 100}%"></div></div>
        <div class="count">${s.count}</div>
      </div>`
      )
      .join('');

    const statusBars = document.getElementById('status-bars');
    const maxSt = Math.max(1, ...STATUS_OPTIONS.map((s) => stats.byStatus[s] || 0));
    statusBars.innerHTML = STATUS_OPTIONS.map(
      (s) => `
      <div class="bar-row">
        <div class="label">${s}</div>
        <div class="bar-track"><div class="bar-fill" style="width:${((stats.byStatus[s] || 0) / maxSt) * 100}%"></div></div>
        <div class="count">${stats.byStatus[s] || 0}</div>
      </div>`
    ).join('');

    document.getElementById('hdr-total').textContent = stats.total;
    document.getElementById('hdr-open').textContent = stats.open;
    document.getElementById('hdr-revenue').textContent = fmtKSh(stats.revenue);
  }

  async function refreshHeader() {
    try {
      const stats = await api('GET', '/stats');
      document.getElementById('hdr-total').textContent = stats.total;
      document.getElementById('hdr-open').textContent = stats.open;
      document.getElementById('hdr-revenue').textContent = fmtKSh(stats.revenue);
    } catch (e) {
      /* non-fatal */
    }
  }

  // ------------------------------------------------------------------
  // init
  // ------------------------------------------------------------------
  document.getElementById('f-date').valueAsDate = new Date();

  (async function init() {
    try {
      await Promise.all([loadServices(), loadStaff()]);
      await refreshHeader();
    } catch (err) {
      toast('Failed to load: ' + err.message, true);
    }
  })();
})();
