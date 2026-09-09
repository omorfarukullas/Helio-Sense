/**
 * HelioSense - IoT Solar Monitoring & Tracking System
 * Modern Dashboard Controller
 */

const HelioSense = (function() {
  // Configuration
  const POLL_INTERVAL = 30000; // 30 second dashboard refresh (ESP32 sends every 3 min)
  const LIVE_THRESHOLD = 200;  // 3 min + 20s buffer
  
  // State
  let state = {
    latestReading: null,
    lastReadingId: null,
    isLive: false,
    lastRecordedAt: null,
    lastFetchTime: Date.now(),
    currentRange: '1h',
    customStart: '',
    customEnd: '',
    historyRange: 'all',
    historyCustomStart: '',
    historyCustomEnd: '',
    historyPage: 1,
    historyPerPage: 25,
    historySort: 'desc',
    historySearch: '',
    charts: {},
    pollTimer: null,
    relativeTimer: null
  };

  // Safe formatting helper
  function formatVal(val, decimals = 2, unit = '') {
    if (val === null || val === undefined || val === '') {
      return '<span class="no-data-tag">No Data</span>';
    }
    const num = parseFloat(val);
    if (isNaN(num)) {
      return '<span class="no-data-tag">No Data</span>';
    }
    const formatted = decimals !== null ? num.toFixed(decimals) : num.toString();
    return unit ? `${formatted} <span class="stat-unit">${unit}</span>` : formatted;
  }

  function formatRawVal(val, decimals = 2) {
    if (val === null || val === undefined || val === '') return 'No Data';
    const num = parseFloat(val);
    if (isNaN(num)) return 'No Data';
    return decimals !== null ? num.toFixed(decimals) : num.toString();
  }

  // Calculate relative time string
  function getRelativeTimeString(recordedAtStr) {
    if (!recordedAtStr) return 'No Recent Data';
    const recordedTime = new Date(recordedAtStr.replace(/-/g, '/')).getTime();
    if (isNaN(recordedTime)) return 'No Recent Data';
    
    const now = Date.now();
    const diffSec = Math.max(0, Math.floor((now - recordedTime) / 1000));
    
    if (diffSec < 5) return 'Just now';
    if (diffSec < 60) return `${diffSec} seconds ago`;
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin} minute${diffMin > 1 ? 's' : ''} ago`;
    const diffHours = Math.floor(diffMin / 60);
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
  }

  // Update Relative Timestamps
  function tickRelativeTime() {
    if (!state.lastRecordedAt) return;
    const relStr = getRelativeTimeString(state.lastRecordedAt);
    const elements = document.querySelectorAll('.live-relative-time');
    elements.forEach(el => el.textContent = `Updated ${relStr}`);

    // Update Live/Offline status based on local time difference from recorded_at
    const recordedTime = new Date(state.lastRecordedAt.replace(/-/g, '/')).getTime();
    const diffSec = Math.max(0, Math.floor((Date.now() - recordedTime) / 1000));
    const isLive = (diffSec <= LIVE_THRESHOLD);

    updateLiveBadge(isLive);
  }

  function updateLiveBadge(isLive) {
    state.isLive = isLive;
    const pills = document.querySelectorAll('.status-pill');
    pills.forEach(pill => {
      if (isLive) {
        pill.className = 'status-pill live';
        pill.innerHTML = '<span class="status-dot"></span> LIVE';
      } else {
        pill.className = 'status-pill offline';
        pill.innerHTML = '<span class="status-dot"></span> OFFLINE';
      }
    });

    const sysStateEl = document.getElementById('sys-overview-state');
    if (sysStateEl) {
      sysStateEl.textContent = isLive ? 'Receiving' : 'No Recent Data';
      sysStateEl.style.color = isLive ? 'var(--emerald-green)' : 'var(--rose-red)';
    }
  }

  // Fetch Latest Reading
  async function fetchLatest() {
    try {
      const res = await fetch('api/latest.php');
      const json = await res.json();
      if (!json.success || !json.data) return;

      const data = json.data;
      const reading = data.latest;
      
      if (reading) {
        const isNewReading = (state.lastReadingId !== reading.reading_id);
        state.latestReading = reading;
        state.lastReadingId = reading.reading_id;
        state.lastRecordedAt = reading.recorded_at;
        
        updateLiveBadge(data.is_live);
        renderLatestData(reading, isNewReading);
        renderRecentReadings(data.recent_readings || []);
      }

      // Update global counter
      const totalEl = document.getElementById('sys-total-readings');
      if (totalEl) totalEl.textContent = data.total_readings.toLocaleString();

    } catch (err) {
      console.warn('Live poll error:', err);
      updateLiveBadge(false);
    }
  }

  // Render Latest Data to DOM
  function renderLatestData(d, isNew) {
    // Header timestamp
    const headerTs = document.getElementById('header-last-reading');
    if (headerTs) headerTs.textContent = `${d.date}, ${d.time}`;

    const setVal = (id, html) => {
      const el = document.getElementById(id);
      if (el) {
        el.innerHTML = html;
        if (isNew) {
          el.classList.remove('val-flash');
          void el.offsetWidth; // trigger reflow
          el.classList.add('val-flash');
        }
      }
    };

    // System Overview
    setVal('stat-recorded-at', `${d.date}<br><small style="font-size:13px;color:var(--text-secondary)">${d.time}</small>`);
    
    // Environment
    setVal('env-ambient-temp', formatVal(d.ambient_temperature_c, 1, '°C'));
    setVal('env-humidity', formatVal(d.humidity_percent, 1, '%RH'));
    setVal('env-panel-temp', formatVal(d.panel_temperature_c, 1, '°C'));
    setVal('env-bmp280-temp', formatVal(d.bmp280_temperature_c, 1, '°C'));
    setVal('env-pressure', formatVal(d.atmospheric_pressure_hpa, 1, 'hPa'));
    setVal('env-irradiance', formatVal(d.irradiance_w_m2, 2, 'W/m²'));
    setVal('env-lux', formatVal(d.movable_light_lux, 1, 'lux'));

    // Fixed Panel
    setVal('fixed-voltage',  formatVal(d.fixed_voltage_v,  4, 'V'));
    setVal('fixed-current',  formatVal(d.fixed_current_a,  4, 'A'));
    setVal('fixed-power',    formatVal(d.fixed_power_w,    4, 'W'));

    // Movable Panel
    setVal('movable-voltage',    formatVal(d.movable_voltage_v,  4, 'V'));
    setVal('movable-current',    formatVal(d.movable_current_a,  4, 'A'));
    setVal('movable-power',      formatVal(d.movable_power_w,    4, 'W'));
    setVal('movable-lux',        formatVal(d.movable_light_lux,  1, 'lux'));
    setVal('movable-irradiance', formatVal(d.irradiance_w_m2,    2, 'W/m²'));

    // Tracking
    setVal('track-ldr-left', formatVal(d.ldr_left, null));
    setVal('track-ldr-right', formatVal(d.ldr_right, null));
    setVal('track-servo-angle', formatVal(d.servo_angle_deg, 1, '°'));
    
    // Tracking Status text & LDR cards
    const trackStatusEl = document.getElementById('track-status-text');
    if (trackStatusEl) {
      trackStatusEl.textContent = d.tracking_status || 'No Data';
      if (d.tracking_status === 'Balanced') {
        trackStatusEl.style.color = 'var(--emerald-green)';
      } else if (d.tracking_status && d.tracking_status.includes('stronger')) {
        trackStatusEl.style.color = 'var(--solar-amber)';
      } else {
        trackStatusEl.style.color = 'var(--text-muted)';
      }
    }

    const ldrLeftCard = document.getElementById('ldr-left-card');
    const ldrRightCard = document.getElementById('ldr-right-card');
    if (ldrLeftCard && ldrRightCard && d.ldr_left !== null && d.ldr_right !== null) {
      ldrLeftCard.classList.toggle('active', d.ldr_left > d.ldr_right + 60);
      ldrRightCard.classList.toggle('active', d.ldr_right > d.ldr_left + 60);
    }

    // Servo Angle Gauge Needle
    const needle = document.getElementById('servo-gauge-needle');
    const angleText = document.getElementById('servo-gauge-val');
    if (angleText) angleText.textContent = d.servo_angle_deg !== null ? `${d.servo_angle_deg}°` : 'No Data';
    if (needle && d.servo_angle_deg !== null) {
      // 0 deg = -90 deg rotation, 180 deg = 90 deg rotation
      const rotationDeg = -90 + Math.max(0, Math.min(180, d.servo_angle_deg));
      needle.style.transform = `rotate(${rotationDeg}deg)`;
    }

    // Power Comparison & Advantage
    setVal('comp-fixed-power',   formatVal(d.fixed_power_w,   4, 'W'));
    setVal('comp-movable-power', formatVal(d.movable_power_w, 4, 'W'));
    setVal('comp-power-diff',    formatVal(d.power_diff_w,    4, 'W'));

    const advPill = document.getElementById('comp-advantage-pill');
    if (advPill) {
      if (d.movable_advantage_percent !== null) {
        const advVal = d.movable_advantage_percent;
        advPill.textContent = `${advVal >= 0 ? '+' : ''}${advVal}% Advantage`;
        advPill.className = advVal >= 0 ? 'advantage-pill' : 'advantage-pill negative';
      } else {
        advPill.textContent = 'N/A';
        advPill.className = 'advantage-pill';
      }
    }

    // Power Progress Bar
    const fpBar = document.getElementById('bar-fixed');
    const mpBar = document.getElementById('bar-movable');
    if (fpBar && mpBar && d.fixed_power_w !== null && d.movable_power_w !== null) {
      const sum = d.fixed_power_w + d.movable_power_w;
      if (sum > 0) {
        const fpPct = (d.fixed_power_w / sum) * 100;
        const mpPct = (d.movable_power_w / sum) * 100;
        fpBar.style.width = `${fpPct}%`;
        mpBar.style.width = `${mpPct}%`;
      }
    }
  }

  // Render Recent 5 Readings Table
  function renderRecentReadings(readings) {
    const tbody = document.getElementById('recent-readings-tbody');
    if (!tbody) return;

    if (!readings || readings.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">No readings available</td></tr>';
      return;
    }

    tbody.innerHTML = readings.map(r => `
      <tr>
        <td><strong>${r.date}</strong></td>
        <td>${r.time}</td>
        <td>${formatRawVal(r.fixed_power_w,   4)} W</td>
        <td style="color:var(--solar-amber);font-weight:700;">${formatRawVal(r.movable_power_w, 4)} W</td>
        <td>${formatRawVal(r.ambient_temperature_c, 1)} °C</td>
        <td>${formatRawVal(r.humidity_percent,       1)} %</td>
        <td>${formatRawVal(r.irradiance_w_m2,        2)}</td>
        <td>${formatRawVal(r.servo_angle_deg,        1)}°</td>
      </tr>
    `).join('');
  }

  // History Page Loader
  async function loadHistory(page = 1) {
    state.historyPage = page;
    const url = `api/history.php?page=${page}&per_page=${state.historyPerPage}&sort=${state.historySort}&range=${state.historyRange}&start_date=${state.historyCustomStart}&end_date=${state.historyCustomEnd}&search=${encodeURIComponent(state.historySearch)}`;
    
    try {
      const res = await fetch(url);
      const json = await res.json();
      if (!json.success || !json.data) return;

      const data = json.data;
      renderHistoryTable(data.readings);
      renderPagination(data.pagination);
    } catch (e) {
      console.error('History load failed:', e);
    }
  }

  function renderHistoryTable(rows) {
    const tbody = document.getElementById('history-tbody');
    if (!tbody) return;

    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="20" style="text-align:center;padding:30px;color:var(--text-muted);">No records found matching criteria</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(r => `
      <tr>
        <td><strong>#${r.reading_id}</strong></td>
        <td>${r.recorded_at}</td>
        <td>${formatRawVal(r.ambient_temperature_c,    1)}</td>
        <td>${formatRawVal(r.humidity_percent,          1)}</td>
        <td>${formatRawVal(r.panel_temperature_c,       1)}</td>
        <td>${formatRawVal(r.bmp280_temperature_c,      1)}</td>
        <td>${formatRawVal(r.atmospheric_pressure_hpa,  1)}</td>
        <td>${formatRawVal(r.irradiance_w_m2,           2)}</td>
        <td>${formatRawVal(r.movable_light_lux,         1)}</td>
        <td>${formatRawVal(r.fixed_voltage_v,           4)}</td>
        <td>${formatRawVal(r.fixed_current_a,           4)}</td>
        <td style="color:var(--sky-blue);font-weight:700;">${formatRawVal(r.fixed_power_w, 4)}</td>
        <td>${formatRawVal(r.movable_voltage_v,         4)}</td>
        <td>${formatRawVal(r.movable_current_a,         4)}</td>
        <td style="color:var(--solar-amber);font-weight:700;">${formatRawVal(r.movable_power_w, 4)}</td>
        <td>${formatRawVal(r.ldr_left,                  null)}</td>
        <td>${formatRawVal(r.ldr_right,                 null)}</td>
        <td>${formatRawVal(r.servo_angle_deg,           1)}°</td>
      </tr>
    `).join('');
  }

  function renderPagination(p) {
    const info = document.getElementById('pagination-info');
    const prevBtn = document.getElementById('btn-prev-page');
    const nextBtn = document.getElementById('btn-next-page');

    if (info) info.textContent = `Page ${p.page} of ${p.total_pages} (${p.total_records.toLocaleString()} total records)`;
    if (prevBtn) prevBtn.disabled = !p.has_prev;
    if (nextBtn) nextBtn.disabled = !p.has_next;
  }

  // Export CSV
  function exportCSV() {
    const url = `api/history.php?format=csv&sort=${state.historySort}&range=${state.historyRange}&start_date=${state.historyCustomStart}&end_date=${state.historyCustomEnd}&search=${encodeURIComponent(state.historySearch)}`;
    window.location.href = url;
  }

  // System Status Diagnostics
  async function loadSystemStatus() {
    try {
      const res = await fetch('api/status.php');
      const json = await res.json();
      if (!json.success || !json.data) return;

      const d = json.data;
      
      const dbStatusEl = document.getElementById('diag-db-status');
      if (dbStatusEl) dbStatusEl.innerHTML = `<span class="badge-available">Connected (${d.database.ping_ms} ms)</span>`;

      const espStatusEl = document.getElementById('diag-esp-status');
      if (espStatusEl) {
        espStatusEl.innerHTML = d.live_status.is_live 
          ? `<span class="badge-available">Receiving</span>` 
          : `<span class="badge-unavailable">No Recent Data (${d.live_status.seconds_ago ? d.live_status.seconds_ago + 's ago' : 'Offline'})</span>`;
      }

      const totalCountEl = document.getElementById('diag-total-readings');
      if (totalCountEl) totalCountEl.textContent = d.total_readings.toLocaleString();

      const availEl = document.getElementById('diag-overall-avail');
      if (availEl) availEl.textContent = d.overall_availability;

      const sensorListEl = document.getElementById('diag-sensor-list');
      if (sensorListEl && d.sensors) {
        sensorListEl.innerHTML = d.sensors.map(s => `
          <div class="sensor-status-row">
            <div>
              <div class="sensor-info-title">${s.name}</div>
              <div class="sensor-info-fields">Fields: ${s.fields.join(', ')}</div>
            </div>
            <div>
              <span class="${s.available ? 'badge-available' : 'badge-unavailable'}">
                ${s.available ? '✓ ' + s.status : '✕ ' + s.status}
              </span>
            </div>
          </div>
        `).join('');
      }
    } catch (e) {
      console.error('System status fetch failed:', e);
    }
  }

  // Stats Loader
  async function loadStats() {
    try {
      const url = `api/stats.php?range=${state.currentRange}&start_date=${state.customStart}&end_date=${state.customEnd}`;
      const res = await fetch(url);
      const json = await res.json();
      if (!json.success || !json.data) return;

      const s = json.data;
      const setStat = (id, val, dec, unit) => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = formatVal(val, dec, unit);
      };

      setStat('stat-fixed-peak-power', s.fixed.peak_power_w, 3, 'W');
      setStat('stat-fixed-avg-power', s.fixed.avg_power_w, 3, 'W');
      setStat('stat-fixed-energy', s.fixed.energy_wh, 3, 'Wh');

      setStat('stat-movable-peak-power', s.movable.peak_power_w, 3, 'W');
      setStat('stat-movable-avg-power', s.movable.avg_power_w, 3, 'W');
      setStat('stat-movable-energy', s.movable.energy_wh, 3, 'Wh');

      setStat('stat-env-max-temp', s.environment.max_ambient_temp_c, 1, '°C');
      setStat('stat-env-avg-temp', s.environment.avg_ambient_temp_c, 1, '°C');
      setStat('stat-env-avg-humidity', s.environment.avg_humidity_percent, 1, '%');
      setStat('stat-env-max-lux', s.environment.max_lux, 1, 'lux');
      setStat('stat-env-max-irradiance', s.environment.max_irradiance_w_m2, 2, 'W/m²');

      const gainEl = document.getElementById('stat-power-gain');
      if (gainEl) {
        if (s.comparison.power_gain_percent !== null) {
          gainEl.innerHTML = `${s.comparison.power_gain_percent >= 0 ? '+' : ''}${s.comparison.power_gain_percent}%`;
          gainEl.style.color = s.comparison.power_gain_percent >= 0 ? 'var(--emerald-green)' : 'var(--rose-red)';
        } else {
          gainEl.innerHTML = '<span class="no-data-tag">N/A</span>';
        }
      }
    } catch (e) {
      console.error('Stats load failed:', e);
    }
  }

  // Chart Management
  const chartConfigs = [
    { id: 'chart-fixed-power', label: 'Fixed Power', field: 'fixed_power_w', unit: 'W', color: '#38bdf8' },
    { id: 'chart-movable-power', label: 'Movable Power', field: 'movable_power_w', unit: 'W', color: '#f59e0b' },
    { id: 'chart-power-comp', label: 'Power Comparison', dual: true, fields: ['fixed_power_w', 'movable_power_w'], labels: ['Fixed (W)', 'Movable (W)'], colors: ['#38bdf8', '#f59e0b'] },
    { id: 'chart-ambient-temp', label: 'Ambient Temperature', field: 'ambient_temperature_c', unit: '°C', color: '#10b981' },
    { id: 'chart-panel-temp', label: 'Panel Temperature', field: 'panel_temperature_c', unit: '°C', color: '#f43f5e' },
    { id: 'chart-bmp-temp', label: 'BMP280 Temperature', field: 'bmp280_temperature_c', unit: '°C', color: '#a855f7' },
    { id: 'chart-humidity', label: 'Humidity', field: 'humidity_percent', unit: '%RH', color: '#06b6d4' },
    { id: 'chart-pressure', label: 'Atmospheric Pressure', field: 'atmospheric_pressure_hpa', unit: 'hPa', color: '#8b5cf6' },
    { id: 'chart-irradiance', label: 'Solar Irradiance', field: 'irradiance_w_m2', unit: 'W/m²', color: '#fbbf24' },
    { id: 'chart-lux', label: 'Light Intensity', field: 'movable_light_lux', unit: 'lux', color: '#eab308' },
    { id: 'chart-fixed-voltage', label: 'Fixed Voltage', field: 'fixed_voltage_v', unit: 'V', color: '#0284c7' },
    { id: 'chart-movable-voltage', label: 'Movable Voltage', field: 'movable_voltage_v', unit: 'V', color: '#d97706' },
    { id: 'chart-fixed-current', label: 'Fixed Current', field: 'fixed_current_a', unit: 'A', color: '#0ea5e9' },
    { id: 'chart-movable-current', label: 'Movable Current', field: 'movable_current_a', unit: 'A', color: '#f97316' },
    { id: 'chart-servo', label: 'Servo Angle', field: 'servo_angle_deg', unit: '°', color: '#ec4899' }
  ];

  async function loadCharts() {
    const url = `api/chart.php?range=${state.currentRange}&start_date=${state.customStart}&end_date=${state.customEnd}`;
    try {
      const res = await fetch(url);
      const json = await res.json();
      if (!json.success || !json.data) return;

      const data = json.data;
      const labels = data.labels;
      const ds = data.datasets;

      chartConfigs.forEach(cfg => {
        const canvas = document.getElementById(cfg.id);
        if (!canvas) return;

        let chartDatasets = [];
        if (cfg.dual) {
          chartDatasets = [
            {
              label: cfg.labels[0],
              data: ds[cfg.fields[0]],
              borderColor: cfg.colors[0],
              backgroundColor: `${cfg.colors[0]}22`,
              borderWidth: 2,
              fill: true,
              tension: 0.3,
              spanGaps: true
            },
            {
              label: cfg.labels[1],
              data: ds[cfg.fields[1]],
              borderColor: cfg.colors[1],
              backgroundColor: `${cfg.colors[1]}22`,
              borderWidth: 2,
              fill: true,
              tension: 0.3,
              spanGaps: true
            }
          ];
        } else {
          chartDatasets = [{
            label: `${cfg.label} (${cfg.unit || ''})`,
            data: ds[cfg.field],
            borderColor: cfg.color,
            backgroundColor: `${cfg.color}18`,
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            spanGaps: true
          }];
        }

        if (state.charts[cfg.id]) {
          state.charts[cfg.id].data.labels = labels;
          state.charts[cfg.id].data.datasets = chartDatasets;
          state.charts[cfg.id].update('none');
        } else {
          state.charts[cfg.id] = new Chart(canvas, {
            type: 'line',
            data: {
              labels: labels,
              datasets: chartDatasets
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              interaction: {
                intersect: false,
                mode: 'index'
              },
              plugins: {
                legend: {
                  display: cfg.dual,
                  labels: { color: '#94a3b8', boxWidth: 12 }
                },
                tooltip: {
                  backgroundColor: '#151d30',
                  titleColor: '#f8fafc',
                  bodyColor: '#94a3b8',
                  borderColor: '#22304d',
                  borderWidth: 1,
                  padding: 10
                }
              },
              scales: {
                x: {
                  grid: { color: 'rgba(255,255,255,0.04)' },
                  ticks: { color: '#64748b', maxTicksLimit: 8 }
                },
                y: {
                  grid: { color: 'rgba(255,255,255,0.06)' },
                  ticks: { color: '#64748b' }
                }
              }
            }
          });
        }
      });
    } catch (e) {
      console.error('Chart load error:', e);
    }
  }

  // Theme Controller
  function initTheme() {
    const savedTheme = localStorage.getItem('heliosense_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('heliosense_theme', next);
    updateThemeIcon(next);
  }

  function updateThemeIcon(theme) {
    const btn = document.getElementById('btn-theme-toggle');
    if (!btn) return;
    btn.innerHTML = theme === 'dark' 
      ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>`
      : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>`;
  }

  // Navigation Switcher
  function switchTab(tabId) {
    document.querySelectorAll('.nav-link').forEach(link => {
      link.classList.toggle('active', link.getAttribute('data-tab') === tabId);
    });

    document.querySelectorAll('.tab-pane').forEach(pane => {
      pane.classList.toggle('active', pane.id === `tab-${tabId}`);
    });

    // Close mobile menu if open
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.remove('open');

    // Load tab-specific data
    if (tabId === 'analytics') {
      loadCharts();
    } else if (tabId === 'history') {
      loadHistory(1);
    } else if (tabId === 'status') {
      loadSystemStatus();
    } else if (tabId === 'panels' || tabId === 'environment') {
      loadStats();
    }
  }

  // Bind UI Events
  function bindEvents() {
    // Navigation
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = link.getAttribute('data-tab');
        if (tab) switchTab(tab);
      });
    });

    // Mobile menu toggle
    const mobileBtn = document.getElementById('btn-mobile-menu');
    const sidebar = document.getElementById('sidebar');
    if (mobileBtn && sidebar) {
      mobileBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
      });
    }

    // Theme toggle
    const themeBtn = document.getElementById('btn-theme-toggle');
    if (themeBtn) themeBtn.addEventListener('click', toggleTheme);

    // Top Header Refresh button
    const refreshBtn = document.getElementById('btn-manual-refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        const icon = refreshBtn.querySelector('svg');
        if (icon) {
          icon.style.transition = 'transform 0.5s ease';
          icon.style.transform = 'rotate(360deg)';
          setTimeout(() => { icon.style.transform = 'none'; }, 500);
        }
        fetchLatest();
        loadStats();
        loadCharts();
        loadHistory(state.historyPage || 1);
        loadSystemStatus();
      });
    }

    // Data History dedicated Refresh button
    const historyRefreshBtn = document.getElementById('btn-refresh-history');
    if (historyRefreshBtn) {
      historyRefreshBtn.addEventListener('click', () => {
        const icon = historyRefreshBtn.querySelector('svg');
        if (icon) {
          icon.style.transition = 'transform 0.5s ease';
          icon.style.transform = 'rotate(360deg)';
          setTimeout(() => { icon.style.transform = 'none'; }, 500);
        }
        loadHistory(state.historyPage || 1);
      });
    }

    // Range selector buttons
    document.querySelectorAll('.btn-range').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-range').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const range = btn.getAttribute('data-range');
        state.currentRange = range;

        const customInputs = document.getElementById('custom-range-box');
        if (customInputs) {
          customInputs.classList.toggle('active', range === 'custom');
        }

        if (range !== 'custom') {
          loadCharts();
          loadStats();
          loadHistory(1);
        }
      });
    });

    // Custom Date Range Apply
    const applyBtn = document.getElementById('btn-apply-custom-range');
    if (applyBtn) {
      applyBtn.addEventListener('click', () => {
        const start = document.getElementById('input-start-date').value;
        const end = document.getElementById('input-end-date').value;
        state.customStart = start;
        state.customEnd = end;
        loadCharts();
        loadStats();
        loadHistory(1);
      });
    }

    // History Pagination
    const prevBtn = document.getElementById('btn-prev-page');
    const nextBtn = document.getElementById('btn-next-page');
    if (prevBtn) prevBtn.addEventListener('click', () => loadHistory(state.historyPage - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => loadHistory(state.historyPage + 1));

    // History Search
    const searchInput = document.getElementById('history-search-input');
    if (searchInput) {
      let debounce = null;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
          state.historySearch = e.target.value.trim();
          loadHistory(1);
        }, 400);
      });
    }

    // History Sort Selector
    const sortSelect = document.getElementById('history-sort-select');
    if (sortSelect) {
      sortSelect.addEventListener('change', (e) => {
        state.historySort = e.target.value;
        loadHistory(1);
      });
    }

    // History Range Selector (independent from charts/stats)
    const historyRangeSelect = document.getElementById('history-range-select');
    if (historyRangeSelect) {
      historyRangeSelect.addEventListener('change', (e) => {
        state.historyRange = e.target.value;
        const customBox = document.getElementById('history-custom-range-box');
        if (customBox) customBox.style.display = e.target.value === 'custom' ? 'flex' : 'none';
        if (e.target.value !== 'custom') loadHistory(1);
      });
    }

    // History Custom Date Apply
    const historyApplyBtn = document.getElementById('btn-apply-history-range');
    if (historyApplyBtn) {
      historyApplyBtn.addEventListener('click', () => {
        const start = document.getElementById('history-start-date').value;
        const end = document.getElementById('history-end-date').value;
        state.historyRange = 'custom';
        state.historyCustomStart = start;
        state.historyCustomEnd = end;
        loadHistory(1);
      });
    }

    // Export CSV button
    const csvBtn = document.getElementById('btn-export-csv');
    if (csvBtn) csvBtn.addEventListener('click', exportCSV);
  }

  // Initialize HelioSense
  function init() {
    initTheme();
    bindEvents();
    
    // Initial fetches
    fetchLatest();
    loadStats();

    // Start Real-time Polling
    state.pollTimer = setInterval(fetchLatest, POLL_INTERVAL);
    state.relativeTimer = setInterval(tickRelativeTime, 1000);
  }

  return {
    init: init
  };
})();

document.addEventListener('DOMContentLoaded', HelioSense.init);
