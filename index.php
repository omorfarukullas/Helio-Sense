<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HelioSense â€” Solar Monitoring & Tracking System</title>
  
  <meta name="description" content="HelioSense IoT Real-Time Solar Monitoring, Dual-Panel Power Comparison and Automated Tracking Dashboard.">
  <meta name="author" content="HelioSense Team">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-container">
  
  <!-- Sidebar Navigation -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"></circle>
          <line x1="12" y1="1" x2="12" y2="3"></line>
          <line x1="12" y1="21" x2="12" y2="23"></line>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
          <line x1="1" y1="12" x2="3" y2="12"></line>
          <line x1="21" y1="12" x2="23" y2="12"></line>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
      </div>
      <div class="brand-info">
        <h1>HelioSense</h1>
        <p>Solar IoT Monitoring</p>
      </div>
    </div>
    
    <nav class="sidebar-nav">
      <a class="nav-link active" data-tab="dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        <span>Main Dashboard</span>
      </a>
      
      <a class="nav-link" data-tab="environment">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg>
        <span>Environment</span>
      </a>
      
      <a class="nav-link" data-tab="panels">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
        <span>Solar Panels</span>
      </a>
      
      <a class="nav-link" data-tab="tracking">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
        <span>Solar Tracking</span>
      </a>
      
      <a class="nav-link" data-tab="analytics">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
        <span>Analytics & Charts</span>
      </a>
      
      <a class="nav-link" data-tab="history">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        <span>Data History</span>
      </a>
      
      <a class="nav-link" data-tab="status">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        <span>System Status</span>
      </a>
    </nav>
    
    <div class="sidebar-footer">
      <div>ESP32 &rarr; PHP &rarr; MySQL</div>
      <div style="font-family: var(--font-mono); font-size: 11px;">Poll: 10s | Limit: 10s</div>
    </div>
  </aside>

  <!-- Main Area -->
  <div class="main-wrapper">
    
    <!-- Top Bar -->
    <header class="top-header">
      <div class="header-left">
        <button class="mobile-menu-btn" id="btn-mobile-menu" aria-label="Toggle Navigation">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <div class="header-title-box">
          <h2>HelioSense</h2>
          <p>Real-Time Solar Panel Performance Monitoring</p>
        </div>
      </div>
      
      <div class="header-right">
        <!-- Live Badge -->
        <div class="status-pill offline" id="main-status-pill">
          <span class="status-dot"></span> OFFLINE
        </div>
        
        <!-- Timestamp Box -->
        <div class="timestamp-pill" title="Last recorded timestamp">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
          <span id="header-last-reading">Connecting...</span>
        </div>
        
        <!-- Refresh Button -->
        <button class="icon-btn" id="btn-manual-refresh" title="Manual Refresh">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>
        </button>
        
        <!-- Theme Toggle -->
        <button class="icon-btn" id="btn-theme-toggle" title="Toggle Dark/Light Mode">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        </button>
      </div>
    </header>

    <!-- Content Tabs -->
    <main class="content-area">
      
      <!-- TAB 1: MAIN DASHBOARD -->
      <section class="tab-pane active" id="tab-dashboard">
        
        <!-- System Overview Mini Grid -->
        <div class="grid-4">
          <div class="card">
            <div class="card-header">
              <span class="card-title">System Status</span>
              <div class="card-icon icon-green"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="sys-overview-state">Detecting...</div>
            </div>
            <div class="stat-footer">
              <span class="live-relative-time">Checking time...</span>
              <span class="sensor-source-tag">10s poll</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Latest Reading</span>
              <div class="card-icon icon-blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" style="font-size: 18px;" id="stat-recorded-at">Loading...</div>
            </div>
            <div class="stat-footer">
              <span>Timestamp: recorded_at</span>
              <span class="sensor-source-tag">MySQL</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Total Readings</span>
              <div class="card-icon icon-purple"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value highlight-blue" id="sys-total-readings">0</div>
              <span class="stat-unit">rows</span>
            </div>
            <div class="stat-footer">
              <span>Database row count</span>
              <span class="sensor-source-tag">sensor_readings</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Tracking Angle</span>
              <div class="card-icon icon-amber"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value highlight-amber" id="track-servo-angle">--</div>
            </div>
            <div class="stat-footer">
              <span id="track-status-text">Checking balance...</span>
              <span class="sensor-source-tag">Servo</span>
            </div>
          </div>
        </div>

        <!-- Power Comparison Hero Bar -->
        <div class="comparison-container">
          <div class="comparison-header">
            <div>
              <h3 style="font-size: 16px; font-weight: 700;">POWER COMPARISON: FIXED VS MOVABLE PANEL</h3>
              <p style="font-size: 12px; color: var(--text-secondary);">Real-time generation difference and solar tracking efficiency gain</p>
            </div>
            <div id="comp-advantage-pill" class="advantage-pill">Calculating...</div>
          </div>

          <div class="comparison-grid">
            <div class="panel-col fixed">
              <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">Fixed Panel</div>
              <div style="font-size: 32px; font-weight: 800; font-family: var(--font-mono); color: var(--sky-blue);" id="comp-fixed-power">--</div>
              <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Stationary mount at fixed angle</div>
            </div>

            <div class="comparison-vs-badge">
              <div class="vs-circle">VS</div>
              <div style="font-size: 11px; color: var(--text-secondary); font-family: var(--font-mono);">Diff: <span id="comp-power-diff" style="font-weight: 700; color: var(--text-primary);">--</span></div>
            </div>

            <div class="panel-col movable">
              <div style="font-size: 12px; color: var(--text-secondary); text-transform: uppercase; font-weight: 600;">Movable (Tracking) Panel</div>
              <div style="font-size: 32px; font-weight: 800; font-family: var(--font-mono); color: var(--solar-amber);" id="comp-movable-power">--</div>
              <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Dual LDR sun-aligned tracking mount</div>
            </div>
          </div>

          <div class="power-progress-bar">
            <div class="bar-fixed" id="bar-fixed" style="width: 50%;"></div>
            <div class="bar-movable" id="bar-movable" style="width: 50%;"></div>
          </div>
        </div>

        <!-- Environmental Readings Quick Cards -->
        <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px;">Environmental Conditions</h3>
        <div class="grid-3">
          <div class="card">
            <div class="card-header">
              <span class="card-title">Ambient Temperature</span>
              <div class="card-icon icon-green"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-ambient-temp">--</div>
            </div>
            <div class="stat-footer">
              <span class="live-relative-time">Updated recently</span>
              <span class="sensor-source-tag">DHT11 / Ambient</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Humidity</span>
              <div class="card-icon icon-blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-humidity">--</div>
            </div>
            <div class="stat-footer">
              <span class="live-relative-time">Updated recently</span>
              <span class="sensor-source-tag">DHT11</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Panel Temperature</span>
              <div class="card-icon icon-rose"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-panel-temp">--</div>
            </div>
            <div class="stat-footer">
              <span class="live-relative-time">Updated recently</span>
              <span class="sensor-source-tag">DS18B20</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">BMP280 Temperature</span>
              <div class="card-icon icon-purple"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-bmp280-temp">--</div>
            </div>
            <div class="stat-footer">
              <span class="live-relative-time">Updated recently</span>
              <span class="sensor-source-tag">BMP280</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Atmospheric Pressure</span>
              <div class="card-icon icon-blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M16 12l-4-4-4 4"></path><path d="M12 16V8"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-pressure">--</div>
            </div>
            <div class="stat-footer">
              <span class="live-relative-time">Updated recently</span>
              <span class="sensor-source-tag">BMP280</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Solar Irradiance</span>
              <div class="card-icon icon-amber"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-irradiance">--</div>
            </div>
            <div class="stat-footer">
              <span class="live-relative-time">Updated recently</span>
              <span class="sensor-source-tag">Pyranometer</span>
            </div>
          </div>
        </div>

        <!-- Electrical Quick Grid -->
        <div class="grid-2">
          <!-- Fixed Panel Card -->
          <div class="card">
            <div class="card-header">
              <span class="card-title" style="color: var(--sky-blue);">Fixed Panel</span>
              <span class="sensor-source-tag">INA219 — Fixed Panel</span>
            </div>
            <div class="grid-3" style="margin-bottom: 0;">
              <div>
                <div style="font-size: 11px; color: var(--text-muted);">Voltage</div>
                <div style="font-size: 20px; font-weight: 700; font-family: var(--font-mono);" id="fixed-voltage">--</div>
              </div>
              <div>
                <div style="font-size: 11px; color: var(--text-muted);">Current</div>
                <div style="font-size: 20px; font-weight: 700; font-family: var(--font-mono);" id="fixed-current">--</div>
              </div>
              <div>
                <div style="font-size: 11px; color: var(--text-muted);">Power</div>
                <div style="font-size: 22px; font-weight: 800; font-family: var(--font-mono); color: var(--sky-blue);" id="fixed-power">--</div>
              </div>
            </div>
          </div>

          <!-- Movable Panel Card -->
          <div class="card">
            <div class="card-header">
              <span class="card-title" style="color: var(--solar-amber);">Movable Panel</span>
              <span class="sensor-source-tag">INA219 — Movable Panel</span>
            </div>
            <div class="grid-3" style="margin-bottom: 0;">
              <div>
                <div style="font-size: 11px; color: var(--text-muted);">Voltage</div>
                <div style="font-size: 20px; font-weight: 700; font-family: var(--font-mono);" id="movable-voltage">--</div>
              </div>
              <div>
                <div style="font-size: 11px; color: var(--text-muted);">Current</div>
                <div style="font-size: 20px; font-weight: 700; font-family: var(--font-mono);" id="movable-current">--</div>
              </div>
              <div>
                <div style="font-size: 11px; color: var(--text-muted);">Power</div>
                <div style="font-size: 22px; font-weight: 800; font-family: var(--font-mono); color: var(--solar-amber);" id="movable-power">--</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tracking Visualizer & Recent Readings Table -->
        <div class="grid-2">
          <!-- Tracking Quick View -->
          <div class="card">
            <div class="card-header">
              <span class="card-title">Tracking & Servo Angle</span>
              <span class="sensor-source-tag">LDR + Servo</span>
            </div>
            
            <div class="gauge-wrapper">
              <svg class="gauge-svg" viewBox="0 0 200 110">
                <defs>
                  <linearGradient id="gauge-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#38bdf8" />
                    <stop offset="50%" stop-color="#10b981" />
                    <stop offset="100%" stop-color="#f59e0b" />
                  </linearGradient>
                </defs>
                <path d="M 20 100 A 80 80 0 0 1 180 100" class="gauge-track" />
                <path d="M 20 100 A 80 80 0 0 1 180 100" class="gauge-fill" />
                <!-- Needle -->
                <g class="gauge-needle" id="servo-gauge-needle" style="transform: rotate(0deg);">
                  <line x1="100" y1="100" x2="100" y2="30" stroke="#f43f5e" stroke-width="3" stroke-linecap="round" />
                  <circle cx="100" cy="100" r="6" fill="#f43f5e" />
                </g>
              </svg>
              <div class="gauge-value-text" id="servo-gauge-val">90Â°</div>
            </div>

            <div class="ldr-balance-bar">
              <div class="ldr-indicator" id="ldr-left-card">
                <h4>LDR Left</h4>
                <span id="track-ldr-left">--</span>
              </div>
              <div class="ldr-indicator" id="ldr-right-card">
                <h4>LDR Right</h4>
                <span id="track-ldr-right">--</span>
              </div>
            </div>
          </div>

          <!-- Recent Readings Mini Table -->
          <div class="card">
            <div class="card-header">
              <span class="card-title">Recent Readings (Latest 5)</span>
              <button class="sensor-source-tag" onclick="document.querySelector('[data-tab=history]').click();">View All &rarr;</button>
            </div>
            
            <div class="table-wrapper" style="border:none;">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Fixed Panel W</th>
                    <th>Movable Panel W</th>
                    <th>Temp</th>
                    <th>Humidity</th>
                    <th>Irradiance</th>
                    <th>Servo</th>
                  </tr>
                </thead>
                <tbody id="recent-readings-tbody">
                  <tr><td colspan="8" style="text-align:center;">Loading recent records...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </section>

      <!-- TAB 2: ENVIRONMENT -->
      <section class="tab-pane" id="tab-environment">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">Environment & Atmospheric Monitoring</h3>
        
        <div class="grid-3">
          <div class="card">
            <div class="card-header">
              <span class="card-title">Ambient Temperature</span>
              <div class="card-icon icon-green"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-ambient-temp-2">--</div>
            </div>
            <div class="stat-footer">
              <span>Peak: <strong id="stat-env-max-temp">--</strong> | Avg: <strong id="stat-env-avg-temp">--</strong></span>
              <span class="sensor-source-tag">DHT11</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Relative Humidity</span>
              <div class="card-icon icon-blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-humidity-2">--</div>
            </div>
            <div class="stat-footer">
              <span>Average: <strong id="stat-env-avg-humidity">--</strong></span>
              <span class="sensor-source-tag">DHT11</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Panel Surface Temp</span>
              <div class="card-icon icon-rose"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-panel-temp-2">--</div>
            </div>
            <div class="stat-footer">
              <span>Thermal Probe</span>
              <span class="sensor-source-tag">DS18B20</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">BMP280 Temperature</span>
              <div class="card-icon icon-purple"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-bmp280-temp-2">--</div>
            </div>
            <div class="stat-footer">
              <span>On-board barometric probe</span>
              <span class="sensor-source-tag">BMP280</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Atmospheric Pressure</span>
              <div class="card-icon icon-blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-pressure-2">--</div>
            </div>
            <div class="stat-footer">
              <span>Barometric Pressure</span>
              <span class="sensor-source-tag">BMP280</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Solar Irradiance</span>
              <div class="card-icon icon-amber"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle></svg></div>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="env-irradiance-2">--</div>
            </div>
            <div class="stat-footer">
              <span>Peak: <strong id="stat-env-max-irradiance">--</strong></span>
              <span class="sensor-source-tag">W/mÂ²</span>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 3: SOLAR PANELS -->
      <section class="tab-pane" id="tab-panels">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">Solar Panels Telemetry & Performance Analysis</h3>
        
        <div class="grid-2">
          <!-- Fixed Panel -->
          <div class="card">
            <div class="card-header">
              <span class="card-title" style="color: var(--sky-blue);">Fixed Panel</span>
              <span class="sensor-source-tag">INA219 — Fixed Panel</span>
            </div>
            <div class="grid-3">
              <div>
                <div style="font-size: 12px; color: var(--text-muted);">Voltage</div>
                <div class="stat-value" id="fixed-voltage-2">--</div>
              </div>
              <div>
                <div style="font-size: 12px; color: var(--text-muted);">Current</div>
                <div class="stat-value" id="fixed-current-2">--</div>
              </div>
              <div>
                <div style="font-size: 12px; color: var(--text-muted);">Instant Power</div>
                <div class="stat-value highlight-blue" id="fixed-power-2">--</div>
              </div>
            </div>
            <div class="stat-footer">
              <span>Peak: <strong id="stat-fixed-peak-power">--</strong> | Avg: <strong id="stat-fixed-avg-power">--</strong> | Energy: <strong id="stat-fixed-energy">--</strong></span>
            </div>
          </div>

          <!-- Movable Panel -->
          <div class="card">
            <div class="card-header">
              <span class="card-title" style="color: var(--solar-amber);">Movable Panel</span>
              <span class="sensor-source-tag">INA219 — Movable Panel</span>
            </div>
            <div class="grid-3">
              <div>
                <div style="font-size: 12px; color: var(--text-muted);">Voltage</div>
                <div class="stat-value" id="movable-voltage-2">--</div>
              </div>
              <div>
                <div style="font-size: 12px; color: var(--text-muted);">Current</div>
                <div class="stat-value" id="movable-current-2">--</div>
              </div>
              <div>
                <div style="font-size: 12px; color: var(--text-muted);">Instant Power</div>
                <div class="stat-value highlight-amber" id="movable-power-2">--</div>
              </div>
            </div>
            <div class="stat-footer">
              <span>Peak: <strong id="stat-movable-peak-power">--</strong> | Avg: <strong id="stat-movable-avg-power">--</strong> | Energy: <strong id="stat-movable-energy">--</strong></span>
            </div>
          </div>
        </div>

        <!-- Light & Energy Summary -->
        <div class="grid-3">
          <div class="card">
            <div class="card-header">
              <span class="card-title">Movable Light Intensity</span>
              <span class="sensor-source-tag">BH1750</span>
            </div>
            <div class="stat-main">
              <div class="stat-value highlight-amber" id="movable-lux">--</div>
            </div>
            <div class="stat-footer">
              <span>Peak Lux: <strong id="stat-env-max-lux">--</strong></span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Tracking Advantage %</span>
              <span class="sensor-source-tag">Formula Gain</span>
            </div>
            <div class="stat-main">
              <div class="stat-value highlight-green" id="stat-power-gain">--</div>
            </div>
            <div class="stat-footer">
              <span>((Movable - Fixed) / Fixed) &times; 100</span>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Solar Irradiance</span>
              <span class="sensor-source-tag">W/mÂ²</span>
            </div>
            <div class="stat-main">
              <div class="stat-value" id="movable-irradiance">--</div>
            </div>
            <div class="stat-footer">
              <span>Solar Radiation Intensity</span>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 4: SOLAR TRACKING -->
      <section class="tab-pane" id="tab-tracking">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">Dual-Axis Light Seeking & Servo Orientation</h3>
        
        <div class="grid-2">
          <div class="card">
            <div class="card-header">
              <span class="card-title">Servo Motor Position (0Â° â€“ 180Â°)</span>
              <span class="sensor-source-tag">PWM Servo</span>
            </div>
            <div class="gauge-wrapper" style="padding: 20px 0;">
              <svg class="gauge-svg" viewBox="0 0 200 110" style="width: 280px; height: 150px;">
                <path d="M 20 100 A 80 80 0 0 1 180 100" class="gauge-track" stroke-width="16" />
                <path d="M 20 100 A 80 80 0 0 1 180 100" class="gauge-fill" stroke-width="16" />
                <g class="gauge-needle" id="servo-gauge-needle-2" style="transform: rotate(0deg);">
                  <line x1="100" y1="100" x2="100" y2="25" stroke="#f43f5e" stroke-width="4" stroke-linecap="round" />
                  <circle cx="100" cy="100" r="8" fill="#f43f5e" />
                </g>
              </svg>
              <div class="gauge-value-text" style="font-size: 34px;" id="servo-gauge-val-2">90Â°</div>
            </div>
            <div class="tracking-status-badge" id="track-status-text-2">Balanced Orientation</div>
          </div>

          <div class="card">
            <div class="card-header">
              <span class="card-title">Light Dependent Resistors (LDR)</span>
              <span class="sensor-source-tag">Analog ADC</span>
            </div>
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
              Dual differential LDR sensors measure sunlight angle to orient the motorized tracker toward peak solar intensity.
            </p>
            <div class="ldr-balance-bar" style="margin-top: 24px;">
              <div class="ldr-indicator" id="ldr-left-card-2" style="padding: 24px;">
                <h4 style="font-size: 13px;">LDR Left Channel</h4>
                <div style="font-size: 26px; font-weight: 800; font-family: var(--font-mono); margin-top: 6px;" id="track-ldr-left-2">--</div>
              </div>
              <div class="ldr-indicator" id="ldr-right-card-2" style="padding: 24px;">
                <h4 style="font-size: 13px;">LDR Right Channel</h4>
                <div style="font-size: 26px; font-weight: 800; font-family: var(--font-mono); margin-top: 6px;" id="track-ldr-right-2">--</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 5: ANALYTICS & CHARTS -->
      <section class="tab-pane" id="tab-analytics">
        <div class="filter-bar">
          <div class="range-buttons">
            <button class="btn-range active" data-range="1h">Last 1 Hour</button>
            <button class="btn-range" data-range="6h">Last 6 Hours</button>
            <button class="btn-range" data-range="12h">Last 12 Hours</button>
            <button class="btn-range" data-range="24h">Last 24 Hours</button>
            <button class="btn-range" data-range="7d">Last 7 Days</button>
            <button class="btn-range" data-range="custom">Custom Range</button>
          </div>

          <div class="custom-range-inputs" id="custom-range-box">
            <input type="date" class="input-date" id="input-start-date">
            <span style="color: var(--text-muted);">&rarr;</span>
            <input type="date" class="input-date" id="input-end-date">
            <button class="btn-primary" id="btn-apply-custom-range">Apply</button>
          </div>
        </div>

        <div class="grid-2">
          <div class="card chart-card">
            <div class="card-header"><span class="card-title">1. Fixed Panel Power vs Time</span><span class="sensor-source-tag">Watts</span></div>
            <div class="chart-container"><canvas id="chart-fixed-power"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">2. Movable Panel Power vs Time</span><span class="sensor-source-tag">Watts</span></div>
            <div class="chart-container"><canvas id="chart-movable-power"></canvas></div>
          </div>

          <div class="card chart-card" style="grid-column: 1 / -1;">
            <div class="card-header"><span class="card-title">3. Fixed Panel vs Movable Panel — Power Comparison</span><span class="sensor-source-tag">Comparative (W)</span></div>
            <div class="chart-container" style="height: 300px;"><canvas id="chart-power-comp"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">4. Ambient Temperature vs Time</span><span class="sensor-source-tag">Â°C</span></div>
            <div class="chart-container"><canvas id="chart-ambient-temp"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">5. Panel Temperature vs Time</span><span class="sensor-source-tag">Â°C</span></div>
            <div class="chart-container"><canvas id="chart-panel-temp"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">6. BMP280 Temperature vs Time</span><span class="sensor-source-tag">Â°C</span></div>
            <div class="chart-container"><canvas id="chart-bmp-temp"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">7. Humidity vs Time</span><span class="sensor-source-tag">%RH</span></div>
            <div class="chart-container"><canvas id="chart-humidity"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">8. Atmospheric Pressure vs Time</span><span class="sensor-source-tag">hPa</span></div>
            <div class="chart-container"><canvas id="chart-pressure"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">9. Solar Irradiance vs Time</span><span class="sensor-source-tag">W/mÂ²</span></div>
            <div class="chart-container"><canvas id="chart-irradiance"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">10. Light Intensity vs Time</span><span class="sensor-source-tag">lux</span></div>
            <div class="chart-container"><canvas id="chart-lux"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">11. Fixed Panel Voltage vs Time</span><span class="sensor-source-tag">Volts</span></div>
            <div class="chart-container"><canvas id="chart-fixed-voltage"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">12. Movable Panel Voltage vs Time</span><span class="sensor-source-tag">Volts</span></div>
            <div class="chart-container"><canvas id="chart-movable-voltage"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">13. Fixed Panel Current vs Time</span><span class="sensor-source-tag">Amperes</span></div>
            <div class="chart-container"><canvas id="chart-fixed-current"></canvas></div>
          </div>

          <div class="card chart-card">
            <div class="card-header"><span class="card-title">14. Movable Panel Current vs Time</span><span class="sensor-source-tag">Amperes</span></div>
            <div class="chart-container"><canvas id="chart-movable-current"></canvas></div>
          </div>

          <div class="card chart-card" style="grid-column: 1 / -1;">
            <div class="card-header"><span class="card-title">15. Servo Angle vs Time</span><span class="sensor-source-tag">Degrees (Â°)</span></div>
            <div class="chart-container"><canvas id="chart-servo"></canvas></div>
          </div>
        </div>
      </section>

      <!-- TAB 6: DATA HISTORY -->
      <section class="tab-pane" id="tab-history">
        <div class="filter-bar">
          <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" class="input-date" id="history-search-input" placeholder="Search by Reading ID..." style="width: 200px;">
            <select class="input-date" id="history-sort-select">
              <option value="desc">Newest First</option>
              <option value="asc">Oldest First</option>
            </select>
            <select class="input-date" id="history-range-select">
              <option value="all" selected>All Records</option>
              <option value="7d">Last 7 Days</option>
              <option value="24h">Last 24 Hours</option>
              <option value="12h">Last 12 Hours</option>
              <option value="6h">Last 6 Hours</option>
              <option value="1h">Last 1 Hour</option>
              <option value="custom">Custom Date Range</option>
            </select>
            <div id="history-custom-range-box" style="display:none; gap:8px; align-items:center; flex-wrap:wrap;" class="d-flex">
              <input type="date" class="input-date" id="history-start-date" style="width:155px;">
              <span style="color:var(--text-secondary); font-size:12px;">to</span>
              <input type="date" class="input-date" id="history-end-date" style="width:155px;">
              <button class="btn-primary" id="btn-apply-history-range" style="padding:6px 14px; font-size:12px;">Apply</button>
            </div>
          </div>

          <button class="btn-primary" id="btn-export-csv">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export CSV
          </button>
        </div>

        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Recorded At</th>
                <th>Ambient Temperature (&deg;C)</th>
                <th>Humidity (%)</th>
                <th>Panel Temperature (&deg;C)</th>
                <th>BMP280 Temperature (&deg;C)</th>
                <th>Pressure (hPa)</th>
                <th>Solar Irradiance (W/m&sup2;)</th>
                <th>Light (lux)</th>
                <th>Fixed Panel Voltage V</th>
                <th>Fixed Panel Current A</th>
                <th>Fixed Panel Power W</th>
                <th>Movable Panel Voltage V</th>
                <th>Movable Panel Current A</th>
                <th>Movable Panel Power W</th>
                <th>LDR Left (lux)</th>
                <th>LDR Right (lux)</th>
                <th>Servo Angle (&deg;)</th>
              </tr>
            </thead>
            <tbody id="history-tbody">
              <tr><td colspan="18" style="text-align: center; padding: 30px;">Loading historical sensor records...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="pagination-bar">
          <span id="pagination-info" style="font-size: 12px; color: var(--text-secondary);">Loading pagination...</span>
          <div style="display: flex; gap: 8px;">
            <button class="page-btn" id="btn-prev-page">&larr; Previous</button>
            <button class="page-btn" id="btn-next-page">Next &rarr;</button>
          </div>
        </div>
      </section>

      <!-- TAB 7: SYSTEM STATUS -->
      <section class="tab-pane" id="tab-status">
        <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">System Health & Sensor Diagnostics</h3>
        
        <div class="grid-3" style="margin-bottom: 24px;">
          <div class="card">
            <div class="card-header"><span class="card-title">MySQL Database</span></div>
            <div class="stat-main" id="diag-db-status"><span class="badge-available">Checking...</span></div>
            <div class="stat-footer"><span>Host: localhost | DB: heliosense</span></div>
          </div>

          <div class="card">
            <div class="card-header"><span class="card-title">ESP32 Ingestion Pipeline</span></div>
            <div class="stat-main" id="diag-esp-status"><span class="badge-unavailable">Checking...</span></div>
            <div class="stat-footer"><span>Live threshold: 10s</span></div>
          </div>

          <div class="card">
            <div class="card-header"><span class="card-title">Overall Sensor Coverage</span></div>
            <div class="stat-main"><div class="stat-value" style="font-size: 18px;" id="diag-overall-avail">Evaluating...</div></div>
            <div class="stat-footer"><span>Total DB Records: <strong id="diag-total-readings">0</strong></span></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <span class="card-title">Sensor Hardware Modules Status</span>
            <span class="sensor-source-tag">Diagnostics</span>
          </div>
          <div class="sensor-status-list" id="diag-sensor-list">
            <div style="padding: 20px; text-align: center; color: var(--text-muted);">Running hardware module scan...</div>
          </div>
        </div>
      </section>

    </main>

  </div>

</div>

<script src="assets/js/dashboard.js"></script>

</body>
</html>