<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 CRITICAL ASSET FAILURE 🚨 - Error {{ $code ?? 500 }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Roboto+Mono:wght@400;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-red: #ff0000;
            --primary-orange: #ff6600;
            --primary-yellow: #ffaa00;
            --dark-bg: #0a0a0a;
            --darker-bg: #050505;
            --glow-color: #ff0066;
            --neon-blue: #00ffff;
            --neon-green: #00ff00;
            --neon-pink: #ff00ff;
        }

        body {
            font-family: 'Roboto Mono', monospace;
            background: var(--darker-bg);
            color: #fff;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 0, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 102, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(255, 0, 102, 0.1) 0%, transparent 50%);
            animation: pulse-bg 4s ease-in-out infinite;
        }

        @keyframes pulse-bg {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        /* Particle Container */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--neon-blue);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--neon-blue);
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Main Container */
        .container {
            position: relative;
            z-index: 10;
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header Section */
        .header {
            text-align: center;
            padding: 3rem 0;
            position: relative;
        }

        .error-code {
            font-family: 'Orbitron', monospace;
            font-size: clamp(4rem, 15vw, 12rem);
            font-weight: 900;
            color: var(--primary-red);
            text-shadow: 
                0 0 10px var(--primary-red),
                0 0 20px var(--primary-red),
                0 0 30px var(--primary-red),
                0 0 40px var(--primary-red);
            animation: flicker 2s infinite;
            margin-bottom: 1rem;
            letter-spacing: 0.5rem;
        }

        @keyframes flicker {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
            51% { opacity: 1; }
            52% { opacity: 0.9; }
        }

        .error-title {
            font-family: 'Press Start 2P', cursive;
            font-size: clamp(1rem, 3vw, 2rem);
            color: var(--neon-yellow);
            text-shadow: 0 0 10px var(--neon-yellow);
            margin-bottom: 2rem;
            animation: slide-in 1s ease-out;
        }

        @keyframes slide-in {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Status Grid */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 3rem 0;
        }

        .status-card {
            background: linear-gradient(135deg, rgba(255, 0, 0, 0.1), rgba(255, 102, 0, 0.1));
            border: 2px solid var(--primary-red);
            border-radius: 15px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: card-enter 0.6s ease-out backwards;
        }

        .status-card:nth-child(1) { animation-delay: 0.1s; }
        .status-card:nth-child(2) { animation-delay: 0.2s; }
        .status-card:nth-child(3) { animation-delay: 0.3s; }
        .status-card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes card-enter {
            from {
                transform: translateY(50px) scale(0.8);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .status-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.5);
            border-color: var(--neon-blue);
        }

        .status-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .status-label {
            font-size: 0.875rem;
            color: var(--neon-blue);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }

        .status-value {
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-yellow);
            text-shadow: 0 0 10px var(--primary-yellow);
        }

        /* Error Message Section */
        .error-section {
            background: rgba(0, 0, 0, 0.6);
            border: 2px solid var(--primary-red);
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            position: relative;
            backdrop-filter: blur(10px);
        }

        .error-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 20px;
            padding: 2px;
            background: linear-gradient(45deg, var(--primary-red), var(--primary-orange), var(--primary-yellow), var(--primary-red));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: border-rotate 3s linear infinite;
        }

        @keyframes border-rotate {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }

        .section-title {
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem;
            color: var(--neon-green);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-title::before {
            content: '▶';
            color: var(--primary-red);
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .error-message {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.1rem;
            line-height: 1.8;
            color: #fff;
            background: rgba(255, 0, 0, 0.1);
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid var(--primary-red);
            word-break: break-all;
            position: relative;
            z-index: 1;
        }

        /* Command Suggestions */
        .commands-section {
            background: rgba(0, 255, 0, 0.05);
            border: 2px solid var(--neon-green);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }

        .command-item {
            background: rgba(0, 0, 0, 0.5);
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 8px;
            border-left: 4px solid var(--neon-green);
            font-family: 'Roboto Mono', monospace;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .command-item:hover {
            background: rgba(0, 255, 0, 0.1);
            transform: translateX(10px);
        }

        .command-item::before {
            content: '$';
            color: var(--neon-green);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .copy-btn {
            margin-left: auto;
            padding: 0.5rem 1rem;
            background: var(--neon-green);
            color: #000;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: var(--primary-yellow);
            transform: scale(1.1);
        }

        /* Progress Bar */
        .progress-container {
            margin: 2rem 0;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: rgba(255, 0, 0, 0.2);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
            border: 2px solid var(--primary-red);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-red), var(--primary-orange), var(--primary-yellow));
            width: 0%;
            animation: progress-animate 3s ease-in-out infinite;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes progress-animate {
            0%, 100% { width: 0%; }
            50% { width: 100%; }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* System Info */
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .info-item {
            background: rgba(0, 0, 0, 0.4);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .info-label {
            font-size: 0.75rem;
            color: var(--neon-blue);
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-family: 'Orbitron', monospace;
            font-size: 1.2rem;
            color: #fff;
        }

        /* Interactive Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            margin: 2rem 0;
        }

        .action-btn {
            padding: 1rem 2rem;
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .action-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: var(--primary-red);
            border-color: var(--primary-red);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-orange);
            border-color: var(--primary-orange);
            transform: scale(1.05);
            box-shadow: 0 0 20px var(--primary-orange);
        }

        .btn-secondary {
            background: transparent;
            border-color: var(--neon-blue);
            color: var(--neon-blue);
        }

        .btn-secondary:hover {
            background: var(--neon-blue);
            color: #000;
            transform: scale(1.05);
            box-shadow: 0 0 20px var(--neon-blue);
        }

        /* Real-time Clock */
        .clock-container {
            text-align: center;
            margin: 2rem 0;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 15px;
            border: 2px solid var(--neon-pink);
        }

        .clock {
            font-family: 'Orbitron', monospace;
            font-size: 2rem;
            color: var(--neon-pink);
            text-shadow: 0 0 10px var(--neon-pink);
        }

        /* Matrix Rain Effect */
        .matrix-rain {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
            opacity: 0.1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .error-code {
                font-size: 4rem;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Easter Egg */
        .konami-code {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 255, 0, 0.2);
            padding: 1rem;
            border-radius: 10px;
            border: 2px solid var(--neon-green);
            font-size: 0.75rem;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1000;
        }

        .konami-code.active {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>
    <div class="particles" id="particles"></div>
    <canvas class="matrix-rain" id="matrix"></canvas>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="error-code">{{ $code ?? 500 }}</div>
            <div class="error-title">ASSET MANIFEST NOT FOUND</div>
        </div>

        <!-- Status Grid -->
        <div class="status-grid">
            <div class="status-card">
                <div class="status-label">Error Code</div>
                <div class="status-value">{{ $code ?? 500 }}</div>
            </div>
            <div class="status-card">
                <div class="status-label">Status</div>
                <div class="status-value">CRITICAL</div>
            </div>
            <div class="status-card">
                <div class="status-label">Severity</div>
                <div class="status-value">HIGH</div>
            </div>
            <div class="status-card">
                <div class="status-label">Time</div>
                <div class="status-value" id="current-time">--:--:--</div>
            </div>
        </div>

        <!-- Error Message Section -->
        <div class="error-section">
            <div class="section-title">Error Details</div>
            <div class="error-message">
                <strong>Message:</strong> {{ $message ?? 'Vite manifest file not found. Assets have not been built.' }}<br>
                <strong>Timestamp:</strong> <span id="error-timestamp"></span><br>
                <strong>User Agent:</strong> <span id="user-agent"></span><br>
                <strong>URL:</strong> <span id="current-url"></span>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="section-title">System Status</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>

        <!-- Command Suggestions -->
        <div class="commands-section">
            <div class="section-title">Recommended Actions</div>
            <div class="command-item">
                <span>npm install</span>
                <button class="copy-btn" onclick="copyCommand('npm install')">Copy</button>
            </div>
            <div class="command-item">
                <span>npm run build</span>
                <button class="copy-btn" onclick="copyCommand('npm run build')">Copy</button>
            </div>
            <div class="command-item">
                <span>npm run dev</span>
                <button class="copy-btn" onclick="copyCommand('npm run dev')">Copy</button>
            </div>
            <div class="command-item">
                <span>php artisan optimize:clear</span>
                <button class="copy-btn" onclick="copyCommand('php artisan optimize:clear')">Copy</button>
            </div>
        </div>

        <!-- System Info -->
        <div class="system-info">
            <div class="info-item">
                <div class="info-label">PHP Version</div>
                <div class="info-value">{{ PHP_VERSION }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Laravel Version</div>
                <div class="info-value">{{ app()->version() }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Environment</div>
                <div class="info-value">{{ app()->environment() }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Debug Mode</div>
                <div class="info-value">{{ config('app.debug') ? 'ON' : 'OFF' }}</div>
            </div>
        </div>

        <!-- Real-time Clock -->
        <div class="clock-container">
            <div class="section-title">Server Time</div>
            <div class="clock" id="clock"></div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="action-btn btn-primary" onclick="location.reload()">🔄 Retry</button>
            <button class="action-btn btn-secondary" onclick="window.history.back()">← Go Back</button>
            <button class="action-btn btn-secondary" onclick="downloadErrorReport()">📥 Download Report</button>
            <button class="action-btn btn-secondary" onclick="toggleTheme()">🎨 Toggle Theme</button>
        </div>
    </div>

    <!-- Easter Egg -->
    <div class="konami-code" id="konami">
        🎉 KONAMI CODE ACTIVATED! 🎉
    </div>

    <script>
        // Particle System
        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (10 + Math.random() * 10) + 's';
                container.appendChild(particle);
            }
        }

        // Real-time Clock
        function updateClock() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false });
            document.getElementById('clock').textContent = time;
            document.getElementById('current-time').textContent = time;
            document.getElementById('error-timestamp').textContent = now.toISOString();
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock();

        // System Info
        document.getElementById('user-agent').textContent = navigator.userAgent;
        document.getElementById('current-url').textContent = window.location.href;

        // Copy Command Function
        function copyCommand(cmd) {
            navigator.clipboard.writeText(cmd).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#00ff00';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '';
                }, 2000);
            });
        }

        // Download Error Report
        function downloadErrorReport() {
            const report = {
                errorCode: {{ $code ?? 500 }},
                message: "{{ addslashes($message ?? '') }}",
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                url: window.location.href,
                phpVersion: "{{ PHP_VERSION }}",
                laravelVersion: "{{ app()->version() }}",
                environment: "{{ app()->environment() }}",
                debug: {{ config('app.debug') ? 'true' : 'false' }}
            };
            
            const blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `error-report-${Date.now()}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }

        // Theme Toggle
        let themeIndex = 0;
        const themes = [
            { name: 'Default', colors: {} },
            { name: 'Neon', colors: { '--primary-red': '#ff00ff', '--neon-blue': '#00ffff' } },
            { name: 'Matrix', colors: { '--primary-red': '#00ff00', '--neon-blue': '#00ff00' } }
        ];

        function toggleTheme() {
            themeIndex = (themeIndex + 1) % themes.length;
            const theme = themes[themeIndex];
            Object.entries(theme.colors).forEach(([prop, value]) => {
                document.documentElement.style.setProperty(prop, value);
            });
        }

        // Matrix Rain Effect
        function initMatrix() {
            const canvas = document.getElementById('matrix');
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            const chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン';
            const fontSize = 14;
            const columns = canvas.width / fontSize;
            const drops = Array(Math.floor(columns)).fill(1);

            function draw() {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.fillStyle = '#00ff00';
                ctx.font = fontSize + 'px monospace';

                for (let i = 0; i < drops.length; i++) {
                    const text = chars[Math.floor(Math.random() * chars.length)];
                    ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                    
                    if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                        drops[i] = 0;
                    }
                    drops[i]++;
                }
            }

            setInterval(draw, 50);
        }

        // Konami Code Easter Egg
        let konamiCode = [];
        const konamiSequence = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
        
        document.addEventListener('keydown', (e) => {
            konamiCode.push(e.key);
            if (konamiCode.length > konamiSequence.length) {
                konamiCode.shift();
            }
            
            if (konamiCode.join(',') === konamiSequence.join(',')) {
                document.getElementById('konami').classList.add('active');
                setTimeout(() => {
                    document.getElementById('konami').classList.remove('active');
                }, 3000);
                konamiCode = [];
            }
        });

        // Initialize
        createParticles();
        initMatrix();
        updateClock();

        // Resize handlers
        window.addEventListener('resize', () => {
            const canvas = document.getElementById('matrix');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    </script>
</body>
</html>
